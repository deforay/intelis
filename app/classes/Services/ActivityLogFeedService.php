<?php

declare(strict_types=1);

namespace App\Services;

use App\Utilities\ActivityLogUtility;
use App\Utilities\DateUtility;

/**
 * Reads the user activity log for the User Activity Log page and its export.
 *
 * Every filter value is bound, never interpolated. The lab scope is the one the
 * page has always applied: a cloud-LIS lab operator sees only their own lab's
 * users, an LIS its own lab and unassigned users, an STS everyone.
 */
final readonly class ActivityLogFeedService
{
    /** Most entries one export returns. */
    public const int EXPORT_LIMIT = 50000;

    /** Event types that make up the Logins view. */
    private const array LOGIN_EVENTS = ['login', 'log-out', 'login-fail', 'logout'];

    /** Readable names for entity_type values. Reference data is labelled by labelFor(). */
    private const array ENTITY_LABELS = [
        'user' => 'User',
        'facility' => 'Facility',
        'global-config' => 'General settings',
        'custom-test' => 'Custom Test',
        'geo-division' => 'Geographical division',
        'funding-source' => 'Funding source',
        'implementation-partner' => 'Implementation partner',
        'corrective-action' => 'Recommended corrective action',
        'lab-storage' => 'Lab storage',
    ];

    /** Test-type prefixes of reference data entity types, such as "vl-sample-type". */
    private const array TEST_TYPE_PREFIXES = [
        'vl' => 'VL',
        'eid' => 'EID',
        'cd4' => 'CD4',
        'tb' => 'TB',
        'covid19' => 'COVID-19',
        'hepatitis' => 'Hepatitis',
        'generic' => 'Custom Tests',
    ];

    public function __construct(private DatabaseService $db, private CommonService $general)
    {
    }

    /**
     * One page of entries, newest first.
     *
     * @param array<string, mixed> $filters source, dateRange, type, createdBy,
     *        sessionHash, search, entityType, entityId, important, requestId
     * @return array{page: int, pageSize: int, total: int, totalPages: int, items: list<array<string, mixed>>}
     */
    public function feed(array $filters, int $page = 1, int $pageSize = 25): array
    {
        $page = max(1, $page);
        $pageSize = ($pageSize < 1 || $pageSize > self::EXPORT_LIMIT) ? 25 : $pageSize;
        $structured = $this->hasStructuredColumns();
        [$whereSql, $params] = $this->where($filters, $structured);

        $from = ' FROM activity_log AS a LEFT JOIN user_details AS ud ON a.user_id = ud.user_id ';
        $total = (int) ($this->db->rawQueryOne("SELECT COUNT(*) AS c $from $whereSql", $params)['c'] ?? 0);

        $columns = 'a.log_id, a.event_type, a.action, a.ip_address, a.session_hash, a.user_agent, a.date_time,
                    ud.user_name, ud.email, r.role_name, res.display_name';
        if ($structured) {
            $columns .= ', a.entity_type, a.entity_id, a.entity_label, a.details, a.important,
                          a.request_id, a.route, a.channel';
        }
        $offset = ($page - 1) * $pageSize;
        $rows = $this->db->rawQuery(
            "SELECT $columns $from
                LEFT JOIN roles AS r ON ud.role_id = r.role_id
                LEFT JOIN resources AS res ON a.resource = res.resource_id
              $whereSql
              ORDER BY a.date_time DESC, a.log_id DESC
              LIMIT $offset, $pageSize",
            $params
        ) ?: [];

        return [
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'totalPages' => max(1, (int) ceil($total / $pageSize)),
            'items' => array_map($this->item(...), $rows),
        ];
    }

    /** Readable name for an entity_type value, such as "VL sample type". */
    public static function labelFor(string $entityType): string
    {
        if (isset(self::ENTITY_LABELS[$entityType])) {
            return self::translate(self::ENTITY_LABELS[$entityType]);
        }
        $dash = strpos($entityType, '-');
        $prefix = $dash === false ? '' : substr($entityType, 0, $dash);
        if (isset(self::TEST_TYPE_PREFIXES[$prefix])) {
            $rest = substr($entityType, $dash + 1);
            $rest = self::ENTITY_LABELS[$rest] ?? ActivityLogService::humanize($rest);
            return self::translate(self::TEST_TYPE_PREFIXES[$prefix]) . ' › ' . self::translate($rest);
        }
        return self::translate(ActivityLogService::humanize($entityType));
    }

    /**
     * @param array<string, mixed> $filters
     * @return array{0: string, 1: list<mixed>}
     */
    private function where(array $filters, bool $structured): array
    {
        $text = static fn(string $key): string => trim((string) ($filters[$key] ?? ''));
        $where = [];
        $params = [];

        if ($text('dateRange') !== '') {
            [$start, $end] = DateUtility::convertDateRange($text('dateRange'), includeTime: true);
            if ($start !== '' && $end !== '') {
                $where[] = 'a.date_time BETWEEN ? AND ?';
                array_push($params, $start, $end);
            }
        }
        if ($text('createdBy') !== '') {
            $where[] = 'a.user_id = ?';
            $params[] = $text('createdBy');
        }
        if ($text('type') !== '') {
            $where[] = 'a.event_type = ?';
            $params[] = $text('type');
        }
        if ($text('sessionHash') !== '') {
            $where[] = 'a.session_hash = ?';
            $params[] = $text('sessionHash');
        }

        $placeholders = implode(',', array_fill(0, count(self::LOGIN_EVENTS), '?'));
        if ($text('source') === 'logins') {
            $where[] = "a.event_type IN ($placeholders)";
            array_push($params, ...self::LOGIN_EVENTS);
        } elseif ($text('source') === 'actions') {
            $where[] = "(a.event_type IS NULL OR a.event_type NOT IN ($placeholders))";
            array_push($params, ...self::LOGIN_EVENTS);
        }

        if ($structured) {
            if ($text('entityType') !== '') {
                $where[] = 'a.entity_type = ?';
                $params[] = $text('entityType');
            }
            if ($text('entityId') !== '') {
                $where[] = 'a.entity_id = ?';
                $params[] = $text('entityId');
            }
            if ($text('important') === '1') {
                $where[] = 'a.important = 1';
            }
            if ($text('requestId') !== '') {
                $where[] = 'a.request_id = ?';
                $params[] = $text('requestId');
            }
        }

        if ($text('search') !== '') {
            $like = '%' . addcslashes($text('search'), '%_\\') . '%';
            $fields = ['a.action', 'ud.user_name', 'a.event_type'];
            if ($structured) {
                // CAST reads the before and after values, so a search for an old
                // name finds the entry that changed it.
                array_push($fields, 'a.entity_label', 'CAST(a.details AS CHAR)');
            }
            $where[] = '(' . implode(' OR ', array_map(static fn(string $f): string => "$f LIKE ?", $fields)) . ')';
            array_push($params, ...array_fill(0, count($fields), $like));
        }

        $scope = $this->general->labAdminScopeWhere('testing_lab_id', 'ud');
        if ($scope !== '') {
            $where[] = $scope;
        }

        return [$where === [] ? '' : ' WHERE ' . implode(' AND ', $where), $params];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function item(array $row): array
    {
        $name = trim((string) ($row['user_name'] ?? ''));
        $ts = empty($row['date_time']) ? false : strtotime((string) $row['date_time']);
        $eventType = (string) ($row['event_type'] ?? '');
        $context = empty($row['display_name'])
            ? ucwords(str_replace(['-', '_'], ' ', $eventType))
            : (string) $row['display_name'];

        $details = [];
        if (!empty($row['details'])) {
            $decoded = json_decode((string) $row['details'], true);
            $details = is_array($decoded) ? $decoded : [];
        }
        $changes = [];
        foreach ($details['changes'] ?? [] as $change) {
            $change['label'] = self::translate((string) ($change['label'] ?? ''));
            $changes[] = $change;
        }
        $facts = [];
        foreach ($details['facts'] ?? [] as $fact) {
            $fact['label'] = self::translate((string) ($fact['label'] ?? ''));
            $facts[] = $fact;
        }
        $list = is_array($details['list'] ?? null) ? $details['list'] : null;
        if ($list !== null) {
            $list['title'] = self::translate((string) ($list['title'] ?? ''));
        }
        $entityType = (string) ($row['entity_type'] ?? '');

        return [
            'logId' => (int) ($row['log_id'] ?? 0),
            'action' => (string) ($row['action'] ?? ''),
            'actionType' => ActivityLogUtility::activityActionType($eventType),
            'userName' => $name !== '' ? $name : self::translate('System'),
            'userRole' => (string) ($row['role_name'] ?? ''),
            'userEmail' => (string) ($row['email'] ?? ''),
            'userInitials' => ActivityLogUtility::activityInitials($name),
            'ipAddress' => (string) ($row['ip_address'] ?? ''),
            'userAgent' => (string) ($row['user_agent'] ?? ''),
            'sessionHash' => (string) ($row['session_hash'] ?? ''),
            'context' => $context,
            'eventType' => $eventType,
            'timestamp' => (string) ($row['date_time'] ?? ''),
            'time' => $ts ? date('g:i a', $ts) : '',
            'dateKey' => $ts ? date('Y-m-d', $ts) : '',
            'dateLabel' => $ts ? strtoupper(date('D, d M Y', $ts)) : (string) ($row['date_time'] ?? ''),
            'entityType' => $entityType,
            'entityId' => (string) ($row['entity_id'] ?? ''),
            'entityLabel' => (string) ($row['entity_label'] ?? ''),
            'entityTypeLabel' => $entityType !== '' ? self::labelFor($entityType) : '',
            'changes' => $changes,
            'facts' => $facts,
            'list' => $list,
            'important' => !empty($row['important']),
            'reason' => self::translateReason((string) ($details['reason'] ?? '')),
            'requestId' => (string) ($row['request_id'] ?? ''),
            'route' => (string) ($row['route'] ?? ''),
            'channel' => (string) ($row['channel'] ?? ''),
        ];
    }

    /**
     * Labels are stored in English and translated for the reader. A composite
     * label ("Result config › Threshold value") is translated part by part.
     */
    private static function translate(string $text): string
    {
        if ($text === '' || !function_exists('_translate')) {
            return $text;
        }
        if (!str_contains($text, ' › ')) {
            return (string) _translate($text);
        }
        return implode(' › ', array_map(static fn(string $part): string => (string) _translate($part), explode(' › ', $text)));
    }

    /** "Sentence: A, B" translates the sentence and each item separately. */
    private static function translateReason(string $reason): string
    {
        $colon = strpos($reason, ': ');
        if ($colon === false) {
            return self::translate($reason);
        }
        $items = array_map(self::translate(...), explode(', ', substr($reason, $colon + 2)));
        return self::translate(substr($reason, 0, $colon)) . ': ' . implode(', ', $items);
    }

    /** The 5.9.1 columns arrive together, so one of them stands for all. */
    private function hasStructuredColumns(): bool
    {
        return $this->db->rawQueryOne("SHOW COLUMNS FROM `activity_log` LIKE 'details'") !== null;
    }
}
