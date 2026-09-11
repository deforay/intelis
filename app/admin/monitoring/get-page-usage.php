<?php
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Utilities\DurationUtility;
use App\Registries\ContainerRegistry;
use Psr\Http\Message\ServerRequestInterface;
// Page usage feed. Returns JSON: { groupBy, rows: [...], totals: {...} }
// Rows are ordered by time spent, longest first.
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());
/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
// The three groupings the page offers. The browser sends a key, never a column
// name, so nothing it sends reaches the GROUP BY.
const PAGE_USAGE_GROUPS = ['user-page' => ['ud.user_name, u.page_url', 'ud.user_name, MAX(u.page_name) AS page_name,
u.page_url, MAX(u.module) AS module'],'user' => ['ud.user_name', "ud.user_name, '' AS page_name, '' AS page_url, '' AS module"], 'page'=> ['u.page_url', "'' AS user_name, MAX(u.page_name) AS page_name, u.page_url, MAX(u.module) AS module"]];
try {
$groupBy = (string) ($_POST['groupBy'] ?? 'user-page');
if (!isset(PAGE_USAGE_GROUPS[$groupBy])) {
    $groupBy = 'user-page';
}
[$groupSql, $selectSql] = PAGE_USAGE_GROUPS[$groupBy];

$where = [];
if (!empty($_POST['dateRange']) && trim((string) $_POST['dateRange']) !== '') {
    [$startDate, $endDate] = DateUtility::convertDateRange($_POST['dateRange']);
    $where[] = ' u.usage_date BETWEEN "' . $db->escape($startDate) . '" AND "' . $db->escape($endDate) . '"';
}
if (!empty($_POST['userId']) && trim((string) $_POST['userId']) !== '') {
    $where[] = ' u.user_id = "' . $db->escape($_POST['userId']) . '"';
}
if (!empty($_POST['sessHash']) && trim((string) $_POST['sessHash']) !== '') {
    $where[] = ' u.session_hash = "' . $db->escape($_POST['sessHash']) . '"';
}

if (!empty($_POST['search']) && trim((string) $_POST['search']) !== '') {
    $term = $db->escape(trim((string) $_POST['search']));
    $where[] = ' (u.page_name LIKE "%' . $term . '%" OR u.page_url LIKE "%' . $term . '%" OR ud.user_name LIKE "%' . $term . '%")';
}
// A cloud-LIS lab operator sees their own lab's users only.
if ($scope = $general->labAdminScopeWhere('testing_lab_id', 'ud')) {
    $where[] = $scope;
}
$whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
$from = ' FROM user_page_usage AS u LEFT JOIN user_details AS ud ON ud.user_id = u.user_id LEFT JOIN roles as r ON r.role_id = ud.role_id';
//echo "SELECT $selectSql, SUM(u.visits) AS visits, SUM(u.duration_seconds) AS seconds, MIN(u.first_seen_datetime) AS first_seen, MAX(u.last_seen_datetime) AS last_seen $from $whereSql GROUP BY $groupSql ORDER BY seconds DESC, visits DESC LIMIT 1000"; die;
$rows = $db->rawQuery("SELECT $selectSql,u.session_hash,r.role_name, SUM(u.visits) AS visits, SUM(u.duration_seconds) AS seconds, MIN(u.first_seen_datetime) AS first_seen, MAX(u.last_seen_datetime) AS last_seen $from $whereSql GROUP BY $groupSql ORDER BY seconds DESC, visits DESC LIMIT 1000");
$totalRow = $db->rawQueryOne("SELECT SUM(u.visits) AS visits, SUM(u.duration_seconds) AS seconds, COUNT(DISTINCT u.user_id) AS users, COUNT(DISTINCT u.page_url) AS pages $from $whereSql");
$items = [];
foreach ($rows as $row) {
    $seconds = (int) ($row['seconds'] ?? 0);
    $visits = (int) ($row['visits'] ?? 0);
    $items[] = [
        'userName' => (string) ($row['user_name'] ?? ''),
        'role' => (string) ($row['role_name'] ?? ''),
        'pageName' => (string) ($row['page_name'] ?? ''),
        'pageUrl'=> (string) ($row['page_url'] ?? ''),
        'module'=> (string) ($row['module'] ?? ''),
        'visits'=> $visits,
        'seconds'=> $seconds,
        'timeSpent' => DurationUtility::humanReadable($seconds),'average' => DurationUtility::humanReadable($visits > 0 ? intdiv($seconds, $visits) : 0),
        'firstSeen' => DateUtility::humanReadableDateFormat($row['first_seen'] ?? '', true),
        'sessionHash' => $row['session_hash'],
        'lastSeen' => DateUtility::humanReadableDateFormat($row['last_seen'] ?? '', true),
    ];
}
echo JsonUtility::encodeUtf8Json([
'groupBy' => $groupBy,
'rows' => $items,
'totals' => [
'users' => (int) ($totalRow['users'] ?? 0),
'pages' => (int) ($totalRow['pages'] ?? 0),
'visits' => (int) ($totalRow['visits'] ?? 0),
'timeSpent' => DurationUtility::humanReadable((int) ($totalRow['seconds'] ?? 0)),
],
]);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
    'trace' => $e->getTraceAsString(),
    'file' => $e->getFile(),
    'line' => $e->getLine(),
    'last_db_error' => $db->getLastError(),
    'last_db_query' => $db->getLastQuery(),
    ]);http_response_code(500);
    echo JsonUtility::encodeUtf8Json(['groupBy' => 'user-page', 'rows' => [], 'totals' => []]);
}