<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\EidService;
use App\Abstracts\AbstractTestService;
use App\Registries\ContainerRegistry;

ini_set('memory_limit', '512M');
set_time_limit(600);
ini_set('max_execution_time', 600);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);

try {
    $sQuery = "SELECT vl.sample_code,
                    vl.remote_sample_code,
                    vl.external_sample_code,
                    vl.sample_collection_date,
                    vl.sample_dispatched_datetime,
                    vl.sample_received_at_lab_datetime,
                    vl.sample_tested_datetime,
                    vl.result_printed_datetime,
                    vl.result_printed_on_sts_datetime,
                    vl.result_printed_on_lis_datetime
                FROM form_eid AS vl
                INNER JOIN r_sample_status AS ts ON ts.status_id = vl.result_status
                LEFT JOIN facility_details AS f ON vl.facility_id = f.facility_id
                LEFT JOIN batch_details AS b ON b.batch_id = vl.sample_batch_id
                WHERE vl.sample_collection_date IS NOT NULL
                    AND vl.sample_tested_datetime IS NOT NULL
                    AND IFNULL(vl.result, '') != ''";

    if (!empty($_SESSION['eidTatData']['sWhere'])) {
        $sQuery .= " AND " . $_SESSION['eidTatData']['sWhere'];
    }

    // Applied independently of the session so the export can never widen past
    // what this user is allowed to see, whatever the list page last stored.
    if (!empty($_SESSION['facilityMap'])) {
        $sQuery .= " AND vl.facility_id IN (" . $_SESSION['facilityMap'] . ")";
    }
    if ($labScope = $general->labScopeWhere('vl')) {
        $sQuery .= " AND $labScope";
    }

    if (!empty($_SESSION['eidTatData']['sOrder'])) {
        $sQuery .= " ORDER BY " . $_SESSION['eidTatData']['sOrder'];
    } else {
        $sQuery .= " ORDER BY vl.sample_collection_date DESC, vl.sample_code ASC";
    }

    $appliedFilters = AbstractTestService::turnaroundTimeFilterSummary($_POST, [
        'sampleCollectionDate' => _translate('Sample Collection Date'),
        'sampleReceivedDateAtLab' => _translate('Sample Received Date in Lab'),
        'sampleTestedDate' => _translate('Sample Test Date'),
        'batchCodeLabel' => _translate('Batch Code'),
        'sampleTypeLabel' => _translate('Sample Type'),
        'labNameLabel' => _translate('Testing Lab'),
    ]);

    echo $eidService->writeTurnaroundTimeExport(
        sql: $sQuery,
        params: [],
        columns: AbstractTestService::turnaroundTimeDetailColumns(),
        appliedFilters: $appliedFilters,
        fileLabel: 'EID'
    );
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
        'trace' => $e->getTraceAsString(),
    ]);
    http_response_code(500);
    echo '';
}
