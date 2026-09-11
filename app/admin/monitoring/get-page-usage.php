<?php

use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\PageUsageService;
use App\Utilities\DurationUtility;
use App\Registries\ContainerRegistry;
use Psr\Http\Message\ServerRequestInterface;

// Page usage feed for /admin/monitoring/page-usage.php. One call returns
// everything the page draws: the totals, the most used pages, the most active
// users, and the user-by-page detail.

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

const PAGE_USAGE_TOP = 10;
const PAGE_USAGE_DETAIL_LIMIT = 500;

$moduleLabels = [
    'admin' => _translate('Admin'),
    'dashboard' => _translate('Dashboard'),
    'vl' => _translate('VL'),
    'eid' => _translate('EID'),
    'covid19' => _translate('COVID-19'),
    'tb' => _translate('TB'),
    'hepatitis' => _translate('Hepatitis'),
    'cd4' => _translate('CD4'),
    'generic-tests' => _translate('Custom Tests'),
    'common' => _translate('Common'),
    'reports' => _translate('Reports'),
];
$moduleOf = static function (?string $module, string $pageUrl) use ($moduleLabels): string {
    $key = (string) ($module ?: PageUsageService::moduleFromPath($pageUrl));
    return $moduleLabels[$key] ?? ucfirst($key);
};
$nameOf = static fn(array $row): string => _translate(
    PageUsageService::displayName((string) ($row['page_name'] ?? ''), (string) $row['page_url'])
);

try {
    // AJAX requests bypass the access control layer, so the page's own
    // privilege is checked here.
    _requirePrivilege('/admin/monitoring/page-usage.php');

    $where = [];
    $params = [];
    $dateRange = trim((string) ($_POST['dateRange'] ?? ''));
    if ($dateRange !== '') {
        [$startDate, $endDate] = DateUtility::convertDateRange($dateRange);
        if ($startDate !== '' && $endDate !== '') {
            $where[] = 'u.usage_date BETWEEN ? AND ?';
            $params[] = $startDate;
            $params[] = $endDate;
        }
    }
    $exactFilters = ['userId' => 'u.user_id', 'pageUrl' => 'u.page_url', 'sessionHash' => 'u.session_hash'];
    foreach ($exactFilters as $field => $column) {
        $value = trim((string) ($_POST[$field] ?? ''));
        if ($value !== '') {
            $where[] = "$column = ?";
            $params[] = $value;
        }
    }
    $search = trim((string) ($_POST['search'] ?? ''));
    if ($search !== '') {
        $like = '%' . addcslashes($search, '%_\\') . '%';
        $where[] = '(u.page_name LIKE ? OR u.page_url LIKE ? OR ud.user_name LIKE ?)';
        array_push($params, $like, $like, $like);
    }
    // A cloud-LIS lab operator sees their own lab's users only.
    if ($scope = $general->labAdminScopeWhere('testing_lab_id', 'ud')) {
        $where[] = $scope;
    }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $from = ' FROM user_page_usage AS u
              LEFT JOIN user_details AS ud ON ud.user_id = u.user_id
              LEFT JOIN roles AS r ON r.role_id = ud.role_id';

    $totalRow = $db->rawQueryOne(
        "SELECT COUNT(DISTINCT u.user_id) AS users,
                COUNT(DISTINCT u.page_url) AS pages,
                COUNT(DISTINCT u.user_id, NULLIF(u.session_hash, '')) AS sessions,
                COALESCE(SUM(u.visits), 0) AS visits,
                COALESCE(SUM(u.duration_seconds), 0) AS seconds
         $from $whereSql",
        $params
    ) ?: [];

    $pageRows = $db->rawQuery(
        "SELECT u.page_url, MAX(u.page_name) AS page_name, MAX(u.module) AS module,
                COUNT(DISTINCT u.user_id) AS users,
                SUM(u.visits) AS visits, SUM(u.duration_seconds) AS seconds
         $from $whereSql
         GROUP BY u.page_url
         ORDER BY seconds DESC, visits DESC
         LIMIT " . PAGE_USAGE_TOP,
        $params
    ) ?: [];

    $userRows = $db->rawQuery(
        "SELECT u.user_id, MAX(ud.user_name) AS user_name, MAX(r.role_name) AS role_name,
                COUNT(DISTINCT u.page_url) AS pages,
                SUM(u.visits) AS visits, SUM(u.duration_seconds) AS seconds,
                MAX(u.last_seen_datetime) AS last_seen
         $from $whereSql
         GROUP BY u.user_id
         ORDER BY seconds DESC, visits DESC
         LIMIT " . PAGE_USAGE_TOP,
        $params
    ) ?: [];

    // One row per user and page. The session shown is the latest one, and the
    // count says how many others there were.
    $detailRows = $db->rawQuery(
        "SELECT u.user_id, u.page_url,
                MAX(ud.user_name) AS user_name, MAX(r.role_name) AS role_name,
                MAX(u.page_name) AS page_name, MAX(u.module) AS module,
                COUNT(DISTINCT NULLIF(u.session_hash, '')) AS sessions,
                SUBSTRING_INDEX(
                    GROUP_CONCAT(u.session_hash ORDER BY u.last_seen_datetime DESC SEPARATOR ','),
                    ',',
                    1
                ) AS last_session,
                SUM(u.visits) AS visits, SUM(u.duration_seconds) AS seconds,
                MAX(u.last_seen_datetime) AS last_seen
         $from $whereSql
         GROUP BY u.user_id, u.page_url
         ORDER BY seconds DESC, visits DESC
         LIMIT " . (PAGE_USAGE_DETAIL_LIMIT + 1),
        $params
    ) ?: [];
    $truncated = count($detailRows) > PAGE_USAGE_DETAIL_LIMIT;
    $detailRows = array_slice($detailRows, 0, PAGE_USAGE_DETAIL_LIMIT);

    $pages = array_map(static fn(array $row): array => [
        'pageUrl' => (string) $row['page_url'],
        'pageName' => $nameOf($row),
        'module' => $moduleOf($row['module'], (string) $row['page_url']),
        'users' => (int) $row['users'],
        'visits' => (int) $row['visits'],
        'seconds' => (int) $row['seconds'],
        'timeSpent' => DurationUtility::humanReadable((int) $row['seconds']),
    ], $pageRows);

    $users = array_map(static fn(array $row): array => [
        'userId' => (string) $row['user_id'],
        'userName' => (string) ($row['user_name'] ?? ''),
        'role' => (string) ($row['role_name'] ?? ''),
        'pages' => (int) $row['pages'],
        'visits' => (int) $row['visits'],
        'seconds' => (int) $row['seconds'],
        'timeSpent' => DurationUtility::humanReadable((int) $row['seconds']),
        'lastSeen' => DateUtility::humanReadableDateFormat($row['last_seen'] ?? '', true),
    ], $userRows);

    $rows = array_map(static function (array $row) use ($moduleOf, $nameOf): array {
        $seconds = (int) $row['seconds'];
        $visits = (int) $row['visits'];
        return [
            'userId' => (string) $row['user_id'],
            'userName' => (string) ($row['user_name'] ?? ''),
            'role' => (string) ($row['role_name'] ?? ''),
            'pageUrl' => (string) $row['page_url'],
            'pageName' => $nameOf($row),
            'module' => $moduleOf($row['module'], (string) $row['page_url']),
            'sessions' => (int) $row['sessions'],
            'lastSession' => (string) ($row['last_session'] ?? ''),
            'visits' => $visits,
            'seconds' => $seconds,
            'timeSpent' => DurationUtility::humanReadable($seconds),
            'average' => DurationUtility::humanReadable($visits > 0 ? intdiv($seconds, $visits) : 0),
            'lastSeen' => DateUtility::humanReadableDateFormat($row['last_seen'] ?? '', true),
        ];
    }, $detailRows);

    echo JsonUtility::encodeUtf8Json([
        'totals' => [
            'users' => (int) ($totalRow['users'] ?? 0),
            'sessions' => (int) ($totalRow['sessions'] ?? 0),
            'pages' => (int) ($totalRow['pages'] ?? 0),
            'visits' => (int) ($totalRow['visits'] ?? 0),
            'seconds' => (int) ($totalRow['seconds'] ?? 0),
            'timeSpent' => DurationUtility::humanReadable((int) ($totalRow['seconds'] ?? 0)),
        ],
        'pages' => $pages,
        'users' => $users,
        'rows' => $rows,
        'truncated' => $truncated,
    ]);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery(),
    ]);
    http_response_code($e->getCode() === 403 ? 403 : 500);
    echo JsonUtility::encodeUtf8Json([
        'totals' => [],
        'pages' => [],
        'users' => [],
        'rows' => [],
        'truncated' => false,
    ]);
}
