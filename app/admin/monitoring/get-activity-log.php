<?php

use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\ActivityLogFeedService;
use Psr\Http\Message\ServerRequestInterface;

// Feed for the User Activity Log page. Returns JSON:
//   { page, pageSize, total, totalPages, items: [ {...} ] }
// See ActivityLogFeedService for the filters and the fields of an item.

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {
    // AJAX requests bypass the access control layer, so the page's own
    // privilege is checked here.
    _requirePrivilege('/admin/monitoring/activity-log.php');

    $pageSize = (int) ($_POST['pageSize'] ?? 25);
    if ($pageSize < 1 || $pageSize > 200) {
        $pageSize = 25;
    }

    /** @var ActivityLogFeedService $feedService */
    $feedService = ContainerRegistry::get(ActivityLogFeedService::class);
    echo JsonUtility::encodeUtf8Json($feedService->feed($_POST, (int) ($_POST['page'] ?? 1), $pageSize));
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
    http_response_code($e->getCode() === 403 ? 403 : 500);
    echo JsonUtility::encodeUtf8Json(['page' => 1, 'pageSize' => 0, 'total' => 0, 'totalPages' => 1, 'items' => []]);
}
