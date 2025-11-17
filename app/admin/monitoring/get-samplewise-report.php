<?php

use Laminas\Diactoros\ServerRequest;
use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
try {

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);


    $testType = $_POST['testType'] ?? 'vl';
    $resultColumn = "result";
    if ($testType == "cd4") {
        $resultColumn = "cd4_result";
    }

    $table = TestsService::getTestTableName($testType);
    $testName = TestsService::getTestName($testType);
    $primaryColumn = TestsService::getPrimaryColumn($testType);

    $orderColumns = $aColumns = [
        'vl.sample_code',
        'vl.remote_sample_code',
        'vl.external_sample_code',
        'f.facility_name',
        'l.facility_name',
        'vl.request_created_datetime',
        'vl.request_created_datetime',
        'vl.sample_received_at_lab_datetime',
        'b.request_created_datetime',
        'ts.status_name',
        "vl.$resultColumn",
        'vl.sample_tested_datetime',
        'vl.result_approved_datetime',
        'vl.result_sent_to_source_datetime',
        'vl.last_modified_datetime'
    ];

    $sOffset = $sLimit = null;
    if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
        $sOffset = $_POST['iDisplayStart'];
        $sLimit = $_POST['iDisplayLength'];
    }



    $sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);


    $columnSearch = $general->multipleColumnSearch($_POST['sSearch'], $aColumns);
    $sWhere = [];
    if (!empty($columnSearch) && $columnSearch != '') {
        $sWhere[] = $columnSearch;
    }


    $fromQuery = "
                FROM $table as vl
                LEFT JOIN facility_details as l ON vl.lab_id = l.facility_id
                LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                LEFT JOIN batch_details as b ON vl.sample_batch_id=b.batch_id";





    if (isset($_POST['dateRange']) && trim((string) $_POST['dateRange']) !== '') {
        [$start_date, $end_date] = DateUtility::convertDateRange($_POST['dateRange'] ?? '', includeTime: true);
        $sWhere[] = " vl.request_created_datetime BETWEEN '$start_date' AND '$end_date' ";
    }
    if (isset($_POST['labName']) && trim((string) $_POST['labName']) !== '') {
        $sWhere[] = " vl.lab_id IN (" . $_POST['labName'] . ")";
    }
    if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
        $provinceId = implode(',', $_POST['state']);
        $sWhere[] = " f.facility_state_id  IN ($provinceId)";
    }
    if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
        $districtId = implode(',', $_POST['district']);
        $sWhere[] = " f.facility_district_id  IN ($districtId)";
    }
    if (isset($_POST['facilityId']) && trim((string) $_POST['facilityId']) !== '') {
        $facilityId = implode(',', $_POST['facilityId']);
        $sWhere[] = " vl.facility_id  IN ($facilityId)";
    }

    if (isset($_POST['originalSourceOfRequest']) && trim((string) $_POST['originalSourceOfRequest']) !== '') {
        $sWhere[] = ' vl.source_of_request = "' . $_POST['originalSourceOfRequest'] . '"';
    }

    /* Implode all the where fields for filtering the data */
    $whereSql = empty($sWhere) ? ('') : ' WHERE ' . implode(' AND ', $sWhere);

    $sQuery = "SELECT
                    vl.$primaryColumn,
                    f.facility_name,
                    l.facility_name as 'labname',
                    vl.sample_code,
                    ts.status_name,
                    vl.external_sample_code,
                    vl.app_sample_code,
                    vl.sample_tested_datetime,
                    vl.request_created_datetime as request_created,
                    vl.remote_sample_code,
                    vl.request_created_datetime,
                    vl.sample_received_at_lab_datetime,
                    b.request_created_datetime as batch_request_created,
                    vl.$resultColumn,
                    vl.result_reviewed_datetime,
                    vl.result_approved_datetime,
                    vl.result_sent_to_source_datetime,
                    vl.last_modified_datetime $fromQuery $whereSql";

    //$sQuery = $sQuery . ' GROUP BY source_of_request, lab_id, DATE(vl.request_created_datetime)';
    if (!empty($sOrder) && $sOrder !== '') {
        $sOrder = preg_replace('/\s+/', ' ', (string) $sOrder);
        $sQuery = "$sQuery ORDER BY $sOrder";
    }

    $_SESSION['samplewiseReportsQuery'] = $sQuery;

    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
    }

    [$rResult, $resultCount] = $db->getDataAndCount($sQuery);

    $output = [
        "sEcho" => (int) $_POST['sEcho'],
        "iTotalRecords" => $resultCount,
        "iTotalDisplayRecords" => $resultCount,
        "calculation" => [],
        "aaData" => []
    ];

    foreach ($rResult as $key => $aRow) {

        $row = [];
        //$row[] = $aRow['f.facility_name'];
        $row[] = $aRow['sample_code'];
        $row[] = $aRow['remote_sample_code'];
        $row[] = $aRow['external_sample_code'] ?? $aRow['app_sample_code'];
        $row[] = $aRow['facility_name'];
        $row[] = $aRow['labname'];
        $row[] = DateUtility::humanReadableDateFormat($aRow['request_created'], true);
        $row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime'], true);
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'], true);
        $row[] = DateUtility::humanReadableDateFormat($aRow['batch_request_created'], true);
        $row[] = $aRow['status_name'];
        $row[] = $aRow['result'];
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'], true);
        $row[] = DateUtility::humanReadableDateFormat($aRow['result_approved_datetime'], true);
        $row[] = DateUtility::humanReadableDateFormat($aRow['result_sent_to_source_datetime'], true);
        $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'], true);

        $output['aaData'][] = $row;
    }

    $calcValueQuery = "SELECT SUM(CASE WHEN (vl.request_created_datetime is not null) THEN 1 ELSE 0 END) AS 'totalSamplesRequested',
                SUM(CASE WHEN (vl.request_created_datetime is not null) THEN 1 ELSE 0 END) AS 'totalSamplesAcknowledged',
                SUM(CASE WHEN (vl.sample_received_at_lab_datetime is not null) THEN 1 ELSE 0 END) AS 'totalSamplesReceived',
                SUM(CASE WHEN (vl.sample_tested_datetime is not null) THEN 1 ELSE 0 END) AS 'totalSamplesTested',
                SUM(CASE WHEN ((vl.result_dispatched_datetime is not null) OR (vl.result_sent_to_source_datetime is not null)) THEN 1 ELSE 0 END) AS 'totalSamplesDispatched'
                FROM $table as vl
            LEFT JOIN facility_details as l ON vl.lab_id = l.facility_id
            LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
            LEFT JOIN batch_details as b ON vl.sample_batch_id=b.batch_id
            INNER JOIN ( SELECT vl.$primaryColumn AS id $fromQuery $whereSql ) AS flt ON flt.id = vl.$primaryColumn";

    $_SESSION['samplewiseReportsCalc'] = $calcValueQuery;

    $calculateFields = $db->rawQuery($calcValueQuery);

    foreach ($calculateFields as $row) {
        $r = [];
        $r[] = $row['totalSamplesRequested'];
        $r[] = $row['totalSamplesAcknowledged'];
        $r[] = $row['totalSamplesReceived'];
        $r[] = $row['totalSamplesTested'];
        $r[] = $row['totalSamplesDispatched'];
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
}
