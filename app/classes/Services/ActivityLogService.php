<?php

declare(strict_types=1);

namespace App\Services;

use App\Registries\AppRegistry;
use App\Utilities\DateUtility;
use App\Utilities\LoggerUtility;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;

/**
 * Writes the user activity log (activity_log).
 *
 * An entry is one sentence, such as "Updated user Jane Doe", plus optional
 * structure the User Activity Log page shows with it:
 *
 *   entity           ['type' => 'user', 'id' => 'u-12', 'label' => 'Jane Doe']
 *                    the record the entry is about; the page links its history
 *   changes          a list from self::changes(): each changed field, before and after
 *   facts            ['Facilities' => 12, ...] context worth keeping
 *   list             ['title' => 'Facilities removed', 'rows' => ['Lab A', ...]]
 *   important        true for a change that can alter results or what a user can do
 *   reason           why the entry is important, shown next to the flag
 *   importantFields  field names that make the entry important when they change
 *
 * When `changes` is given but empty, the entry still records the save and says
 * that nothing changed.
 *
 * Every entry also records the request it came from (a request id shared by all
 * entries one request writes, the route and the channel), the client IP, the
 * user agent and a hash of the session.
 *
 * Writing never throws: the log must not break the action it records.
 */
final class ActivityLogService
{
    /** A field whose name matches is logged as changed, never with its values. */
    private const string SECRET_PATTERN = '/(password|passwd|passphrase|secret|token|salt|dsn)|(^|[_-])key$/i';

    /** Longest before or after value kept. */
    private const int MAX_VALUE_LENGTH = 2000;

    /** Most rows kept in one details list, and most items added or removed per list field. */
    private const int MAX_LIST_ROWS = 1000;

    /** One id per PHP request, shared by every entry it writes. */
    private static ?string $requestId = null;

    /** @var array<string, true>|null activity_log's columns, read once */
    private ?array $columns = null;

    public function __construct(private readonly DatabaseService $db)
    {
    }

    /**
     * Writes one entry. See the class comment for $extra.
     *
     * @param string|null $userId who did it, for a call with no web session (the
     *                            mobile API); a web request takes the session's user
     * @param array<string, mixed> $extra
     */
    public function record(
        string $eventType,
        string $action,
        ?string $resource = null,
        array $extra = [],
        ?string $userId = null
    ): void {
        try {
            $this->write($eventType, $action, $resource, $extra, $userId);
        } catch (Throwable $e) {
            LoggerUtility::logWarning('Activity log entry not written: ' . $e->getMessage(), [
                'event_type' => $eventType,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
        }
    }

    /** @param array<string, mixed> $extra */
    private function write(string $eventType, string $action, ?string $resource, array $extra, ?string $userId): void
    {
        $changes = is_array($extra['changes'] ?? null) ? array_values($extra['changes']) : [];
        if (array_key_exists('changes', $extra) && $changes === []) {
            $action .= ' (no changes)';
        }

        $request = self::currentRequest();
        $data = [
            'event_type' => $eventType,
            'action' => $action,
            'resource' => $resource,
            'user_id' => $userId ?? (empty($_SESSION['userId']) ? null : (string) $_SESSION['userId']),
            'date_time' => DateUtility::getCurrentDateTime(),
            'ip_address' => self::clientIp($request),
        ];

        $optional = [
            'session_hash' => self::sessionHash() ?: null,
            'user_agent' => $request instanceof ServerRequestInterface
                ? (substr($request->getHeaderLine('User-Agent'), 0, 512) ?: null)
                : null,
        ];
        if ($this->hasColumn('details')) {
            [$important, $reason] = self::importance($extra, $changes);
            $entity = is_array($extra['entity'] ?? null) ? $extra['entity'] : [];
            $optional += [
                'entity_type' => self::nonEmpty($entity['type'] ?? null, 32),
                'entity_id' => self::nonEmpty($entity['id'] ?? null, 64),
                'entity_label' => self::nonEmpty($entity['label'] ?? null, 255),
                'details' => self::details($extra, $changes, $reason),
                'important' => $important ? 1 : 0,
                'request_id' => self::requestId(),
                'route' => self::route($request),
                'channel' => self::channel($request),
            ];
        }
        foreach ($optional as $column => $value) {
            if ($this->hasColumn($column)) {
                $data[$column] = $value;
            }
        }

        $this->db->insert('activity_log', $data);
    }

    /**
     * One row of a reference table, for recordRowSave() to compare against.
     *
     * @return array<string, mixed>|null
     */
    public function row(string $table, string $idColumn, int|string|null $id): ?array
    {
        if ($id === null || $id === '' || $id === 0) {
            return null;
        }
        try {
            return $this->db->rawQueryOne("SELECT * FROM `$table` WHERE `$idColumn` = ?", [$id]);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Logs a save of one row of a reference table, naming the row and listing
     * the columns that changed since $before (null for a new row).
     *
     * @param array<string, mixed>|null $before from row(), read before the save
     * @param array<string, string> $labels column => readable label
     * @param list<string> $importantFields columns whose change flags the entry
     */
    public function recordRowSave(
        string $eventType,
        string $statement,
        string $entityType,
        string $table,
        string $idColumn,
        int|string $id,
        string $labelColumn,
        ?array $before,
        array $labels = [],
        array $importantFields = []
    ): void {
        $after = $this->row($table, $idColumn, $id);
        if ($after === null) {
            // The row cannot be read back (a text key the insert did not
            // return): the save is still logged, without its changes.
            $this->record($eventType, $statement, 'reference-data');
            return;
        }
        $this->record($eventType, $statement, 'reference-data', [
            'entity' => ['type' => $entityType, 'id' => $id, 'label' => (string) ($after[$labelColumn] ?? $id)],
            'changes' => self::changes($before, $after, $labels, [$idColumn, 'updated_datetime', 'data_sync']),
            'importantFields' => $before === null ? [] : $importantFields,
        ]);
    }

    /**
     * Compares two versions of a record and lists what changed.
     *
     * Nested arrays are compared field by field with dotted names. A list (such
     * as a user's facilities) is compared item by item and recorded with the
     * items added and removed. Values are compared as display strings, so "100"
     * and 100 are the same. A secret field (password, token, key) records that
     * it changed, never its values.
     *
     * @param array<string, mixed>|null $before
     * @param array<string, mixed>|null $after
     * @param array<string, string> $labels field => readable label
     * @param list<string> $ignore fields to skip, such as timestamps
     * @return list<array{field: string, label: string, from: string, to: string, added?: list<string>, removed?: list<string>}>
     */
    public static function changes(?array $before, ?array $after, array $labels = [], array $ignore = []): array
    {
        $old = self::flatten($before ?? []);
        $new = self::flatten($after ?? []);
        $ignoreSet = array_flip($ignore);
        $out = [];
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $field) {
            $field = (string) $field;
            if (isset($ignoreSet[$field]) || isset($ignoreSet[strtok($field, '.')])) {
                continue;
            }
            $from = $old[$field] ?? '';
            $to = $new[$field] ?? '';
            if ($from === $to) {
                continue;
            }
            $label = self::label($field, $labels);
            $secret = preg_match(self::SECRET_PATTERN, self::leaf($field)) === 1;

            if (is_array($from) || is_array($to)) {
                $fromList = is_array($from) ? $from : ($from === '' ? [] : [$from]);
                $toList = is_array($to) ? $to : ($to === '' ? [] : [$to]);
                $added = array_values(array_diff($toList, $fromList));
                $removed = array_values(array_diff($fromList, $toList));
                if ($added === [] && $removed === []) {
                    continue; // same items, another order
                }
                $out[] = [
                    'field' => $field,
                    'label' => $label,
                    'from' => '',
                    'to' => '',
                    'added' => $secret ? [] : array_map(self::clip(...), array_slice($added, 0, self::MAX_LIST_ROWS)),
                    'removed' => $secret ? [] : array_map(self::clip(...), array_slice($removed, 0, self::MAX_LIST_ROWS)),
                ];
                continue;
            }

            if ($secret) {
                $from = $from === '' ? '' : '(hidden)';
                $to = $to === '' ? '' : '(hidden)';
            }
            $out[] = [
                'field' => $field,
                'label' => $label,
                'from' => self::clip($from),
                'to' => self::clip($to),
            ];
        }
        return $out;
    }

    /**
     * The field's own label, or its nearest labelled parent's followed by the
     * rest of its name: "test_results_config.result_type" with a label for
     * test_results_config reads "Result settings › Result type".
     *
     * @param array<string, string> $labels
     */
    private static function label(string $field, array $labels): string
    {
        if (isset($labels[$field])) {
            return $labels[$field];
        }
        $parts = explode('.', $field);
        for ($i = count($parts) - 1; $i > 0; $i--) {
            $parent = implode('.', array_slice($parts, 0, $i));
            if (isset($labels[$parent])) {
                return $labels[$parent] . ' › ' . self::humanize(implode('.', array_slice($parts, $i)));
            }
        }
        return self::humanize($field);
    }

    /** "facility_type" or "facilityType" becomes "Facility type"; "a.b" becomes "A › B". */
    public static function humanize(string $field): string
    {
        $words = preg_replace(['/([a-z0-9])([A-Z])/', '/[_\-]+/'], ['$1 $2', ' '], self::leaf($field));
        $label = ucfirst(strtolower(trim((string) $words)));
        $parent = str_contains($field, '.') ? substr($field, 0, (int) strrpos($field, '.')) : '';
        return $parent !== '' ? self::humanize($parent) . ' › ' . $label : $label;
    }

    /** Same id for every entry written during one PHP request. */
    public static function requestId(): string
    {
        return self::$requestId ??= bin2hex(random_bytes(8));
    }

    /**
     * Short, opaque per-session id: sha256 of the PHP session id, first 16 hex
     * chars (64 bits). Hashed (not the raw session id) so the stored value can't
     * be replayed against a live session. '' in CLI / when no session exists.
     */
    public static function sessionHash(): string
    {
        if (CommonService::isCliRequest()) {
            return '';
        }
        $sid = session_id();
        return $sid !== '' && $sid !== false ? substr(hash('sha256', $sid), 0, 16) : '';
    }

    /** Raw User-Agent of the current request (max 512 chars). '' in CLI. */
    public static function userAgent(): string
    {
        if (CommonService::isCliRequest()) {
            return '';
        }
        $request = AppRegistry::get('request');
        $ua = $request instanceof ServerRequestInterface
            ? $request->getHeaderLine('User-Agent')
            : (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        return substr($ua, 0, 512);
    }

    /**
     * The request being served, or null for a command-line run. A served page
     * always has one registered; a task never does. Checked without get(),
     * which logs a warning when there is none.
     */
    private static function currentRequest(): ?ServerRequestInterface
    {
        if (!AppRegistry::has('request')) {
            return null;
        }
        $request = AppRegistry::get('request');
        return $request instanceof ServerRequestInterface ? $request : null;
    }

    /**
     * The client's address. getClientIpAddress() refuses private ranges, which
     * is every user on a lab's own network, so the connection's own address is
     * the fallback: on a LAN it is the user's machine.
     */
    private static function clientIp(?ServerRequestInterface $request): ?string
    {
        if (!$request instanceof ServerRequestInterface) {
            return null;
        }
        $ip = CommonService::getClientIpAddress($request);
        if ($ip !== null && $ip !== '') {
            return $ip;
        }
        $remote = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? '');
        return filter_var($remote, FILTER_VALIDATE_IP) !== false ? $remote : null;
    }

    /** The mobile app and STS sync call /api/ and /remote/; tasks run from the command line. */
    private static function channel(?ServerRequestInterface $request): string
    {
        if (!$request instanceof ServerRequestInterface) {
            return 'cli';
        }
        $path = $request->getUri()->getPath();
        return (str_starts_with($path, '/api/') || str_starts_with($path, '/remote/')) ? 'api' : 'web';
    }

    /** "POST /users/editUserHelper.php", or the script for a command-line run. */
    private static function route(?ServerRequestInterface $request): ?string
    {
        if (!$request instanceof ServerRequestInterface) {
            $script = (string) ($_SERVER['argv'][0] ?? ($_SERVER['SCRIPT_NAME'] ?? ''));
            return $script !== '' ? substr(basename($script), 0, 255) : null;
        }
        $route = trim($request->getMethod() . ' ' . $request->getUri()->getPath());
        return $route !== '' ? substr($route, 0, 255) : null;
    }

    /**
     * @param array<string, mixed> $extra
     * @param list<array<string, mixed>> $changes
     * @return array{0: bool, 1: string}
     */
    private static function importance(array $extra, array $changes): array
    {
        $important = !empty($extra['important']);
        $reason = isset($extra['reason']) ? (string) $extra['reason'] : '';
        $watched = $extra['importantFields'] ?? [];
        if (is_array($watched) && $watched !== [] && $changes !== []) {
            $watchedSet = array_flip($watched);
            $hits = [];
            foreach ($changes as $change) {
                // The full field, its first part ("attributes") or its last part
                // ("threshold_value" inside "resultConfig.threshold_value").
                $field = (string) $change['field'];
                if (isset($watchedSet[$field]) || isset($watchedSet[strtok($field, '.')]) || isset($watchedSet[self::leaf($field)])) {
                    $hits[] = (string) $change['label'];
                }
            }
            if ($hits !== []) {
                $important = true;
                if ($reason === '') {
                    $reason = 'Important fields changed: ' . implode(', ', array_unique($hits));
                }
            }
        }
        return [$important, $important ? $reason : ''];
    }

    /**
     * @param array<string, mixed> $extra
     * @param list<array<string, mixed>> $changes
     */
    private static function details(array $extra, array $changes, string $reason): ?string
    {
        $details = [];
        if ($changes !== []) {
            $details['changes'] = $changes;
        }
        if (!empty($extra['facts']) && is_array($extra['facts'])) {
            foreach ($extra['facts'] as $label => $value) {
                $details['facts'][] = ['label' => (string) $label, 'value' => self::clip(self::display($value))];
            }
        }
        if (!empty($extra['list']['rows']) && is_array($extra['list']['rows'])) {
            $rows = array_map(static fn($row): string => self::clip(self::display($row)), array_values($extra['list']['rows']));
            $details['list'] = [
                'title' => (string) ($extra['list']['title'] ?? ''),
                'total' => count($rows),
                'rows' => array_slice($rows, 0, self::MAX_LIST_ROWS),
            ];
        }
        if ($reason !== '') {
            $details['reason'] = $reason;
        }
        if ($details === []) {
            return null;
        }
        $json = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        return $json === false ? null : $json;
    }

    /**
     * @param array<mixed> $values
     * @return array<string, string|list<string>>
     */
    private static function flatten(array $values, string $prefix = ''): array
    {
        $out = [];
        foreach ($values as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value) && $value !== [] && !array_is_list($value)) {
                $out += self::flatten($value, $name);
                continue;
            }
            if (is_array($value)) {
                $items = array_map(self::display(...), $value);
                $out[$name] = array_values(array_unique(array_filter($items, static fn(string $v): bool => $v !== '')));
                continue;
            }
            $out[$name] = self::display($value);
        }
        return $out;
    }

    private static function display(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $value ? 'yes' : 'no',
            is_array($value), is_object($value) => (string) json_encode($value, JSON_UNESCAPED_UNICODE),
            default => trim((string) $value),
        };
    }

    private static function clip(string $value): string
    {
        return mb_strlen($value) > self::MAX_VALUE_LENGTH
            ? mb_substr($value, 0, self::MAX_VALUE_LENGTH) . '…'
            : $value;
    }

    private static function leaf(string $field): string
    {
        $dot = strrpos($field, '.');
        return $dot === false ? $field : substr($field, $dot + 1);
    }

    private static function nonEmpty(mixed $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return mb_substr((string) $value, 0, $max);
    }

    private function hasColumn(string $column): bool
    {
        if ($this->columns === null) {
            $columns = [];
            foreach ($this->db->rawQuery('SHOW COLUMNS FROM `activity_log`') ?: [] as $row) {
                $columns[(string) $row['Field']] = true;
            }
            // Not cached when the read failed, so one bad read cannot drop the
            // optional columns for the rest of the request.
            if ($columns === []) {
                return false;
            }
            $this->columns = $columns;
        }
        return isset($this->columns[$column]);
    }
}
