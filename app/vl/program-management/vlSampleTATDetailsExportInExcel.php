<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\VlService;
use App\Abstracts\AbstractTestService;
use App\Registries\ContainerRegistry;
use App\Utilities\TurnaroundTimeUtility;

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

require_once __DIR__ . '/vlSampleTatFilters.php';

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
                FROM form_vl as vl
                INNER JOIN r_sample_status as ts ON ts.status_id = vl.result_status
                LEFT JOIN facility_details as f ON vl.facility_id = f.facility_id
                LEFT JOIN batch_details as b ON b.batch_id = vl.sample_batch_id
                WHERE vl.sample_collection_date IS NOT NULL
                    AND vl.sample_tested_datetime IS NOT NULL
                    AND IFNULL(vl.result, '') != '' ";

    [$sWhere, $params] = vlSampleTatFilterConditions($_POST, $general);

    // Keep the free-text search from the on-screen table in sync with the export.
    $aColumns = ['vl.sample_code', 'vl.remote_sample_code', 'vl.external_sample_code'];
    $columnSearch = $general->multipleColumnSearch($_POST['sSearch'] ?? '', $aColumns);
    if (!empty($columnSearch)) {
        $sWhere[] = $columnSearch;
    }

    if ($sWhere !== []) {
        $sQuery .= " AND " . implode(" AND ", $sWhere);
    }
    // Same plausibility guards the chart applies, so the export reconciles
    // against the chart drawn from the same filters.
    $sWhere = array_merge($sWhere, TurnaroundTimeUtility::plausibleDateConditions('vl'));

    $sQuery .= " ORDER BY vl.sample_collection_date DESC, vl.sample_code ASC";

    $appliedFilters = AbstractTestService::turnaroundTimeFilterSummary($_POST, [
        'sampleCollectionDate' => _translate('Sample Collection Date'),
        'sampleReceivedDateAtLab' => _translate('Sample Received Date in Lab'),
        'sampleTestedDate' => _translate('Sample Test Date'),
        'batchCodeLabel' => _translate('Batch Code'),
        'sampleTypeLabel' => _translate('Sample Type'),
        'labNameLabel' => _translate('Testing Lab'),
    ]);

    /** @var VlService $vlService */
    $vlService = ContainerRegistry::get(VlService::class);

    echo $vlService->writeTurnaroundTimeExport(
        sql: $sQuery,
        params: $params,
        columns: AbstractTestService::turnaroundTimeDetailColumns(),
        appliedFilters: $appliedFilters,
        fileLabel: 'VIRAL-LOAD'
    );
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
    http_response_code(500);
    echo '';
}
