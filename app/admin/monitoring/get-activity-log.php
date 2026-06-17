<?php

use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use Psr\Http\Message\ServerRequestInterface;
use App\Registries\ContainerRegistry;

// Activity-log feed (EPT-style card timeline). Returns JSON:
//   { page, pageSize, total, totalPages, items: [ {...} ] }
// Each item carries everything the feed UI renders: action text, actionType
// (drives the icon/colour), actor name/role/email/initials, IP, user agent,
// session hash, context chip, and grouped date/time fields.

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {
    // ---- paging ----
    $page = max(1, (int) ($_POST['page'] ?? 1));
    $pageSize = (int) ($_POST['pageSize'] ?? 25);
    if ($pageSize < 1 || $pageSize > 200) {
        $pageSize = 25;
    }
    $offset = ($page - 1) * $pageSize;

    // ---- filters ----
    $where = [];

    if (!empty($_POST['dateRange']) && trim((string) $_POST['dateRange']) !== '') {
        [$startDate, $endDate] = DateUtility::convertDateRange($_POST['dateRange'], includeTime: true);
        $where[] = ' a.date_time BETWEEN "' . $startDate . '" AND "' . $endDate . '"';
    }

    if (!empty($_POST['createdBy']) && trim((string) $_POST['createdBy']) !== '') {
        $where[] = ' a.user_id = "' . $db->escape($_POST['createdBy']) . '"';
    }

    if (!empty($_POST['type']) && trim((string) $_POST['type']) !== '') {
        $where[] = ' a.event_type = "' . $db->escape($_POST['type']) . '"';
    }

    if (!empty($_POST['sessionHash']) && trim((string) $_POST['sessionHash']) !== '') {
        $where[] = ' a.session_hash = "' . $db->escape(trim((string) $_POST['sessionHash'])) . '"';
    }

    // Source toggle: logins, actions (everything that isn't a login event), or all.
    $loginEvents = "('login','log-out','login-fail','logout')";
    $source = (string) ($_POST['source'] ?? 'all');
    if ($source === 'logins') {
        $where[] = " a.event_type IN $loginEvents";
    } elseif ($source === 'actions') {
        $where[] = " a.event_type NOT IN $loginEvents";
    }

    // Free-text search across the action text and the actor name.
    if (!empty($_POST['search']) && trim((string) $_POST['search']) !== '') {
        $term = $db->escape(trim((string) $_POST['search']));
        $where[] = ' (a.action LIKE "%' . $term . '%" OR ud.user_name LIKE "%' . $term . '%" OR a.event_type LIKE "%' . $term . '%")';
    }

    // Lab scope: cloud-LIS lab operator sees only their lab's users' activity
    // (LIS = own lab + unassigned; cloud-LIS = strict, fail closed; STS = all).
    if ($scope = $general->labAdminScopeWhere('testing_lab_id', 'ud')) {
        $where[] = $scope;
    }

    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

    $from = " FROM activity_log as a
              LEFT JOIN user_details as ud ON a.user_id = ud.user_id
              LEFT JOIN roles as r ON ud.role_id = r.role_id
              LEFT JOIN resources as res ON a.resource = res.resource_id ";

    // total (for pagination)
    $countRow = $db->rawQueryOne("SELECT COUNT(*) AS c FROM activity_log as a LEFT JOIN user_details as ud ON a.user_id = ud.user_id $whereSql");
    $total = (int) ($countRow['c'] ?? 0);

    $sQuery = "SELECT a.log_id, a.event_type, a.action, a.ip_address, a.session_hash, a.user_agent,
                      a.date_time, ud.user_name, ud.email, r.role_name, res.display_name
               $from $whereSql
               ORDER BY a.date_time DESC, a.log_id DESC
               LIMIT $offset, $pageSize";

    $rows = $db->rawQuery($sQuery);

    $items = [];
    foreach ($rows as $aRow) {
        $name = trim((string) ($aRow['user_name'] ?? ''));
        $ts = !empty($aRow['date_time']) ? strtotime((string) $aRow['date_time']) : false;
        $context = !empty($aRow['display_name'])
            ? $aRow['display_name']
            : ucwords(str_replace(['-', '_'], ' ', (string) ($aRow['event_type'] ?? '')));

        $items[] = [
            'action'       => (string) ($aRow['action'] ?? ''),
            'actionType'   => activityActionType((string) ($aRow['event_type'] ?? '')),
            'userName'     => $name !== '' ? $name : _translate('System'),
            'userRole'     => (string) ($aRow['role_name'] ?? ''),
            'userEmail'    => (string) ($aRow['email'] ?? ''),
            'userInitials' => activityInitials($name),
            'ipAddress'    => (string) ($aRow['ip_address'] ?? ''),
            'userAgent'    => (string) ($aRow['user_agent'] ?? ''),
            'sessionHash'  => (string) ($aRow['session_hash'] ?? ''),
            'context'      => $context,
            'eventType'    => (string) ($aRow['event_type'] ?? ''),
            'time'         => $ts ? date('g:i a', $ts) : '',
            'dateKey'      => $ts ? date('Y-m-d', $ts) : '',
            'dateLabel'    => $ts ? strtoupper(date('D, d M Y', $ts)) : (string) ($aRow['date_time'] ?? ''),
        ];
    }

    echo JsonUtility::encodeUtf8Json([
        'page'       => $page,
        'pageSize'   => $pageSize,
        'total'      => $total,
        'totalPages' => $pageSize > 0 ? (int) ceil($total / $pageSize) : 1,
        'items'      => $items,
    ]);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
    http_response_code(500);
    echo JsonUtility::encodeUtf8Json(['page' => 1, 'pageSize' => 0, 'total' => 0, 'totalPages' => 1, 'items' => []]);
}

/** Map an activity event_type to a feed action class (icon + colour). */
function activityActionType(string $eventType): string
{
    $e = strtolower($eventType);
    return match (true) {
        str_contains($e, 'login') && str_contains($e, 'fail') => 'login-fail',
        str_contains($e, 'log-out'), str_contains($e, 'logout') => 'logout',
        str_contains($e, 'login') => 'login',
        str_contains($e, 'delete'), str_contains($e, 'remove') => 'delete',
        str_contains($e, 'import') => 'import',
        str_contains($e, 'add'), str_contains($e, 'create') => 'create',
        str_contains($e, 'update'), str_contains($e, 'edit'), str_contains($e, 'modif') => 'update',
        str_contains($e, 'export'), str_contains($e, 'download') => 'download',
        str_contains($e, 'mail'), str_contains($e, 'email'), str_contains($e, 'sent') => 'message',
        default => 'other',
    };
}

/** 1-2 uppercase initials from a name; '?' when blank. */
function activityInitials(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '?';
    }
    $out = '';
    foreach (preg_split('/\s+/', $name) ?: [] as $p) {
        if ($p !== '' && ctype_alpha(substr($p, 0, 1))) {
            $out .= strtoupper(substr($p, 0, 1));
        }
        if (strlen($out) >= 2) {
            break;
        }
    }
    return $out !== '' ? $out : strtoupper(substr($name, 0, 1));
}
