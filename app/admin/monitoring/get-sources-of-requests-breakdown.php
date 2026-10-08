<?php

use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\SourcesOfRequestsReportUtility;

// The By Clinic and Trend tabs of the Sources of Requests report, loaded when
// opened. Same filters, and the same rows, as the By Source summary.

// AJAX requests skip the page ACL, so this checks the report's own privilege.
_requirePrivilege('/admin/monitoring/sources-of-requests.php');

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {
    ['from' => $from, 'where' => $where] = SourcesOfRequestsReportUtility::reportScope($_POST, $general);
    $fromWhere = "$from WHERE " . implode(' AND ', $where);

    $output = match ((string) ($_POST['view'] ?? '')) {
        'clinic' => SourcesOfRequestsReportUtility::byClinic($db, $fromWhere),
        'trend' => SourcesOfRequestsReportUtility::trend($db, $fromWhere),
        default => throw new InvalidArgumentException('Unknown view'),
    };

    echo JsonUtility::encodeUtf8Json($output);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery(),
    ]);
    throw $e;
}
