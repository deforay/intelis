<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\Covid19Service;
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

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);

try {
    if (empty($_SESSION['covid19TATQuery'])) {
        echo '';
        return;
    }

    // SQL_CALC_FOUND_ROWS is only meaningful for the paged list behind this
    // export, and it is deprecated, so drop it from the export's copy.
    $sQuery = str_replace('SQL_CALC_FOUND_ROWS ', '', (string) $_SESSION['covid19TATQuery']);

    // Applied independently of the session so the export can never widen past
    // what this user is allowed to see, whatever the list page last stored.
    if (!empty($_SESSION['facilityMap'])) {
        $sQuery .= " AND vl.facility_id IN (" . $_SESSION['facilityMap'] . ")";
    }
    if ($labScope = $general->labScopeWhere('vl')) {
        $sQuery .= " AND $labScope";
    }

    $sampleCode = $general->isSTSInstance() ? 'remote_sample_code' : 'sample_code';

    $appliedFilters = AbstractTestService::turnaroundTimeFilterSummary($_POST, [
        'sampleCollectionDate' => _translate('Sample Collection Date'),
        'sampleReceivedDateAtLab' => _translate('Sample Received Date in Lab'),
        'sampleTestedDate' => _translate('Sample Test Date'),
        'batchCodeLabel' => _translate('Batch Code'),
        'sampleTypeLabel' => _translate('Sample Type'),
        'labNameLabel' => _translate('Testing Lab'),
    ]);

    echo $covid19Service->writeTurnaroundTimeExport(
        sql: $sQuery,
        params: [],
        columns: [
            ['Covid-19 Sample ID', $sampleCode, false],
            ['Sample Collection Date', 'sample_collection_date', true],
            ['Sample Received Date in Lab', 'sample_received_at_lab_datetime', true],
            ['Sample Test Date', 'sample_tested_datetime', true],
            ['Result Print Date', 'result_printed_datetime', true],
            ['Sample Email Date', 'result_mail_datetime', true],
            ['STS Result Print Date', 'result_printed_on_sts_datetime', true],
            ['LIS Result Print Date', 'result_printed_on_lis_datetime', true],
        ],
        appliedFilters: $appliedFilters,
        fileLabel: 'COVID-19'
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
