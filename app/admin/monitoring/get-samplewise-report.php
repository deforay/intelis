<?php

use App\Utilities\AdminFilterClauseBuilder;
use App\Utilities\DataTableUtility;
use App\Utilities\SampleCountUtility;
use Psr\Http\Message\ServerRequestInterface;
use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
try {

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);


    $testType = (string) ($_POST['testType'] ?? 'vl');
    if (!in_array($testType, TestsService::getActiveTests(), true)) {
        throw new InvalidArgumentException("Inactive or unknown test type: $testType");
    }

    $table = TestsService::getTestTableName($testType);
    $resultColumn = TestsService::getResultColumn($testType);
    // A result counts as returned once dispatched or sent back to the source;
    // the column shows whichever happened, as the Returned total counts it.
    $returnedOn = 'COALESCE(vl.result_sent_to_source_datetime, vl.result_dispatched_datetime)';

    $orderColumns = $aColumns = [
        'vl.sample_code',
        'vl.remote_sample_code',
        'vl.external_sample_code',
        'f.facility_name',
        'l.facility_name',
        'vl.request_created_datetime',
        'vl.sample_received_at_lab_datetime',
        'b.request_created_datetime',
        'ts.status_name',
        "vl.$resultColumn",
        'vl.sample_tested_datetime',
        'vl.result_approved_datetime',
        $returnedOn,
        'vl.last_modified_datetime'
    ];

    [$sOffset, $sLimit] = DataTableUtility::paging($_POST);



    $sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);


    $columnSearch = $general->multipleColumnSearch($_POST['sSearch'], $aColumns);
    // Cancelled requests are not counted anywhere, so they are not listed either.
    $sWhere = [SampleCountUtility::countableWhere('vl')];
    if (!empty($columnSearch) && $columnSearch != '') {
        $sWhere[] = $columnSearch;
    }


    $fromQuery = "
                FROM $table as vl
                LEFT JOIN facility_details as l ON vl.lab_id = l.facility_id
                LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                LEFT JOIN batch_details as b ON vl.sample_batch_id=b.batch_id";





    // These five filters appear, verbatim, in a dozen admin endpoints, each with
    // its own alias. AdminFilterClauseBuilder is the one copy of them.
    $sWhere = array_merge($sWhere, AdminFilterClauseBuilder::buildStandardFilters($_POST, [
        'dateColumn' => 'vl.request_created_datetime',
        'labColumn' => 'vl.lab_id',
        'stateColumn' => 'f.facility_state_id',
        'districtColumn' => 'f.facility_district_id',
        'facilityColumn' => 'vl.facility_id',
    ]));

    $source = trim((string) ($_POST['originalSourceOfRequest'] ?? ''));
    if ($source === 'unrecorded') {
        $sWhere[] = "IFNULL(vl.source_of_request, '') = ''";
    } elseif ($source !== '') {
        $stored = array_map(
            fn(string $value): string => "'" . $db->escape($value) . "'",
            CommonService::storedSourcesOfRequest($source)
        );
        $sWhere[] = 'vl.source_of_request IN (' . implode(', ', $stored) . ')';
    }

    /* Implode all the where fields for filtering the data */
    $whereSql = empty($sWhere) ? ('') : ' WHERE ' . implode(' AND ', $sWhere);

    $sQuery = "SELECT
                    f.facility_name,
                    l.facility_name as 'labname',
                    vl.sample_code,
                    ts.status_name,
                    vl.external_sample_code,
                    vl.app_sample_code,
                    vl.sample_tested_datetime,
                    vl.remote_sample_code,
                    vl.request_created_datetime,
                    vl.sample_received_at_lab_datetime,
                    b.request_created_datetime as batch_request_created,
                    vl.$resultColumn AS result,
                    vl.result_approved_datetime,
                    $returnedOn AS result_returned_datetime,
                    vl.last_modified_datetime $fromQuery $whereSql";
    if (!empty($sOrder) && $sOrder !== '') {
        $sOrder = preg_replace('/\s+/', ' ', (string) $sOrder);
        $sQuery = "$sQuery ORDER BY $sOrder";
    }

    $_SESSION['samplewiseReportsQuery'] = $sQuery;

    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
    }

    [$rResult, $resultCount] = $db->getDataAndCount($sQuery);
    $_SESSION['samplewiseReportsQueryCount'] = $resultCount;

    $output = [
        "sEcho" => (int) $_POST['sEcho'],
        "iTotalRecords" => $resultCount,
        "iTotalDisplayRecords" => $resultCount,
        "calculation" => [],
        "aaData" => []
    ];

    foreach ($rResult as $key => $aRow) {

        $row = [];
        $row[] = $aRow['sample_code'];
        $row[] = $aRow['remote_sample_code'];
        $row[] = $aRow['external_sample_code'] ?? $aRow['app_sample_code'];
        $row[] = $aRow['facility_name'];
        $row[] = $aRow['labname'];
        $row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime'], true);
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'], true);
        $row[] = DateUtility::humanReadableDateFormat($aRow['batch_request_created'], true);
        $row[] = $aRow['status_name'];
        $row[] = $aRow['result'];
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'], true);
        $row[] = DateUtility::humanReadableDateFormat($aRow['result_approved_datetime'], true);
        $row[] = DateUtility::humanReadableDateFormat($aRow['result_returned_datetime'], true);
        $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'], true);

        $output['aaData'][] = $row;
    }

    // Every listed row is a request, so the total is a plain count of the listing.
    $calcValueQuery = "SELECT COUNT(*) AS 'totalSamplesRequested',
                SUM(CASE WHEN (vl.sample_received_at_lab_datetime is not null) THEN 1 ELSE 0 END) AS 'totalSamplesReceived',
                SUM(CASE WHEN (vl.sample_tested_datetime is not null) THEN 1 ELSE 0 END) AS 'totalSamplesTested',
                SUM(CASE WHEN ($returnedOn is not null) THEN 1 ELSE 0 END) AS 'totalSamplesDispatched'
                $fromQuery $whereSql";

    $_SESSION['samplewiseReportsCalc'] = $calcValueQuery;

    $calculateFields = $db->rawQuery($calcValueQuery);

    foreach ($calculateFields as $row) {
        $r = [];
        $r[] = (int) $row['totalSamplesRequested'];
        $r[] = (int) $row['totalSamplesReceived'];
        $r[] = (int) $row['totalSamplesTested'];
        $r[] = (int) $row['totalSamplesDispatched'];
        $output['calculation'][] = $r;
    }

    echo JsonUtility::encodeUtf8Json($output);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
    throw $e;
}
