<?php

declare(strict_types=1);

namespace App\Services;

use Throwable;
use App\Registries\AppRegistry;
use App\Utilities\DateUtility;
use App\Utilities\LoggerUtility;
use App\Utilities\FileCacheUtility;

/**
 * Which page a user opened, and how long it stayed in front of them.
 *
 * One row per user, page, day and login session. header.php counts the open.
 * The browser reports visible seconds, which land on the row the open created.
 *
 * Nothing here is allowed to break a page. A page that fails to render because
 * it could not count itself is worse than a gap in the counts, so every public
 * method swallows its own failures.
 */
final class PageUsageService
{
    /** Below this, a flush is not time spent on the page. */
    private const MIN_FLUSH_SECONDS = 5;

    /** The browser reports at least every 15 minutes, so nothing real is larger. */
    private const MAX_FLUSH_SECONDS = 4500;

    /** Ceiling for one user on one page in one session on one day. */
    private const MAX_DAY_SECONDS = 28800;

    /** Words a page file name spells in lower case that a reader expects in capitals. */
    private const ACRONYMS = [
        'api', 'cd4', 'covid', 'csv', 'dbs', 'eid', 'hiv', 'id', 'lis',
        'pdf', 'qa', 'qc', 'sms', 'sts', 'tat', 'tb', 'vl',
    ];

    private ?bool $enabled = null;
    private ?string $pageUrl = null;

    public function __construct(
        private readonly DatabaseService $db,
        private readonly CommonService $general,
        private readonly FileCacheUtility $fileCache
    ) {
    }

    /** False when global_config.track_page_usage is 'no'. */
    public function isEnabled(): bool
    {
        if ($this->enabled === null) {
            $flag = trim((string) ($this->general->getGlobalConfig('track_page_usage') ?? ''));
            $this->enabled = $flag !== 'no';
        }
        return $this->enabled;
    }

    /**
     * The current request as a tracking key: the path, plus a type parameter
     * when the request carries one. '' when the request is not a trackable page.
     */
    public function currentPageUrl(): string
    {
        $this->pageUrl ??= self::normalize((string) (AppRegistry::get('currentRequestURI') ?? ''));
        return $this->pageUrl;
    }

    /** Counts one page open. Called from header.php. */
    public function recordPageOpen(): void
    {
        try {
            if (CommonService::isCliRequest() || !$this->isEnabled()) {
                return;
            }
            $userId = trim((string) ($_SESSION['userId'] ?? ''));
            $page = $this->currentPageUrl();
            if ($userId === '' || $page === '') {
                return;
            }
            $now = DateUtility::getCurrentDateTime();
            $label = $this->label($page);
            $ip = substr((string) CommonService::getClientIpAddress(), 0, 64);
            $this->db->rawQuery(
                "INSERT INTO user_page_usage
                    (user_id, session_hash, page_url, page_name, module, usage_date, visits,
                     duration_seconds, first_seen_datetime, last_seen_datetime, last_ip_address)
                 VALUES (?, ?, ?, ?, ?, DATE(?), 1, 0, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    visits = visits + 1,
                    last_seen_datetime = ?,
                    last_ip_address = ?,
                    page_name = ?",
                [
                    $userId, $this->general->sessionHash(), $page, $label['name'], $label['module'],
                    $now, $now, $now, $ip,
                    $now, $ip, $label['name'],
                ]
            );
        } catch (Throwable $e) {
            LoggerUtility::logDebug('Page usage open not recorded: ' . $e->getMessage());
        }
    }

    /**
     * Adds visible seconds to the row this session opened for the page.
     *
     * A request naming a page the session never opened matches no row, so
     * changes nothing.
     */
    public function recordTime(string $page, int $seconds): void
    {
        try {
            if (CommonService::isCliRequest() || !$this->isEnabled()) {
                return;
            }
            $userId = trim((string) ($_SESSION['userId'] ?? ''));
            $page = self::normalize($page);
            if ($userId === '' || $page === '') {
                return;
            }
            if ($seconds < self::MIN_FLUSH_SECONDS || $seconds > self::MAX_FLUSH_SECONDS) {
                return;
            }
            // usage_date reaches back one day so the tail of a visit that crossed
            // midnight lands on the row that was opened, not on a new one.
            $this->db->rawQuery(
                "UPDATE user_page_usage
                    SET duration_seconds = LEAST(duration_seconds + ?, ?),
                        last_seen_datetime = ?
                  WHERE user_id = ? AND session_hash = ? AND page_url = ?
                    AND usage_date >= (CURDATE() - INTERVAL 1 DAY)
                  ORDER BY usage_date DESC
                  LIMIT 1",
                [
                    $seconds, self::MAX_DAY_SECONDS, DateUtility::getCurrentDateTime(),
                    $userId, $this->general->sessionHash(), $page,
                ]
            );
        } catch (Throwable $e) {
            LoggerUtility::logDebug('Page usage time not recorded: ' . $e->getMessage());
        }
    }

    /**
     * The module a page belongs to when neither the menu nor the privilege list
     * says: the first segment of its path, which is how this application files
     * pages ('/eid/requests/...' is EID).
     */
    public static function moduleFromPath(string $page): string
    {
        $segment = explode('/', trim((string) parse_url($page, PHP_URL_PATH), '/'))[0] ?? '';
        return $segment === 'covid-19' ? 'covid19' : $segment;
    }

    /**
     * The path, and a type parameter when there is one, because this application
     * tells its per-test-type pages apart by exactly that parameter.
     * Everything else in a query string is filter state and sample ids.
     */
    private static function normalize(string $uri): string
    {
        $uri = trim($uri);
        if ($uri === '') {
            return '';
        }
        $path = parse_url($uri, PHP_URL_PATH) ?: '';
        if (!preg_match('#^/[A-Za-z0-9._/\-]{1,200}$#', $path) || !str_ends_with($path, '.php')) {
            return '';
        }
        if ($path === '/common/track-page-usage.php') {
            return '';
        }
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);
        $key = array_key_exists('type', $query) ? 'type' : 't';
        $type = (string) ($query[$key] ?? '');
        if ($type !== '' && preg_match('#^[a-z0-9\-]{1,20}$#', $type)) {
            $path .= '?' . $key . '=' . $type;
        }
        return substr($path, 0, 255);
    }

    /** @return array{name:string,module:?string} */
    private function label(string $page): array
    {
        $labels = $this->labels();
        return $labels[$page] ?? ['name' => self::prettyName($page), 'module' => self::moduleFromPath($page) ?: null];
    }

    /**
     * Path to label, from the menu first and the privilege list second.
     * Cached for an hour, so a renamed page catches up without a deploy.
     *
     * @return array<string, array{name:string,module:?string}>
     */
    private function labels(): array
    {
        $db = $this->db;
        return $this->fileCache->get('page_usage_labels', function () use ($db): array {
            $map = [];
            $menu = $db->rawQuery(
                "SELECT link, display_text, module FROM s_app_menu
                  WHERE link IS NOT NULL AND link <> '' AND link NOT LIKE '#%'"
            );
            foreach ($menu as $row) {
                $map[(string) $row['link']] = [
                    'name' => (string) $row['display_text'],
                    'module' => $row['module'] !== null ? (string) $row['module'] : null,
                ];
            }
            $privileges = $db->rawQuery(
                "SELECT p.privilege_name, r.display_name, r.module
                   FROM privileges p
                   LEFT JOIN resources r ON r.resource_id = p.resource_id
                  WHERE p.privilege_name IS NOT NULL AND p.privilege_name <> ''"
            );
            foreach ($privileges as $row) {
                $url = (string) $row['privilege_name'];
                if (isset($map[$url]) || empty($row['display_name'])) {
                    continue;
                }
                $map[$url] = [
                    'name' => (string) $row['display_name'],
                    'module' => $row['module'] !== null ? (string) $row['module'] : null,
                ];
            }
            return $map;
        });
    }

    /**
     * The name to show for a stored row. A row named from its file name before
     * acronyms were kept in capitals ('Eid Quality Monitoring') shows the
     * corrected name; a name from the menu or the privilege list is kept.
     */
    public static function displayName(string $stored, string $page): string
    {
        $derived = self::prettyName($page);
        return ($stored === '' || strcasecmp($stored, $derived) === 0) ? $derived : $stored;
    }

    /** '/eid/qa/eid-quality-monitoring.php' => 'EID Quality Monitoring' */
    public static function prettyName(string $page): string
    {
        $base = basename(parse_url($page, PHP_URL_PATH) ?: $page, '.php');
        $words = preg_split('/[^a-z0-9]+/i', $base, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode(' ', array_map(
            static fn(string $word): string => in_array(strtolower($word), self::ACRONYMS, true)
                ? strtoupper($word)
                : ucfirst($word),
            $words
        ));
    }
}
