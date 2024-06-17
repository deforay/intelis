<?php

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
try {

    $db->beginReadOnlyTransaction();

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);


    $testType = $_POST['testType'] ?? 'vl';
    $resultColumn = "result";
    if ($testType == "cd4") {
        $resultColumn = "cd4_result";
    }

    $table = TestsService::getTestTableName($testType);
    $testName = TestsService::getTestName($testType);

    // if (isset($testType) && $testType == 'vl') {
    //     $url = "/vl/requests/vl-requests.php";
    // }
    // if (isset($testType) && $testType == 'eid') {
    //     $url = "/eid/requests/eid-requests.php";
    // }
    // if (isset($testType) && $testType == 'covid19') {
    //     $url = "/covid-19/requests/covid-19-requests.php";
    // }
    // if (isset($testType) && $testType == 'hepatitis') {
    //     $url = "/hepatitis/requests/hepatitis-requests.php";
    // }
    // if (isset($testType) && $testType == 'tb') {
    //     $url = "/tb/requests/tb-requests.php";
    // }

    /*
    * Array of database columns which should be read and sent back to DataTables. Use a space where
    * you want to insert a non-database field (for example a counter or static image)
    */
    $orderColumns = $aColumns = [
        'f.facility_name',
        'l.facility_name',
        'vl.external_sample_code',
        'vl.request_created_datetime',
        'vl.remote_sample_code',
        'vl.request_created_datetime',
        'vl.sample_received_at_lab_datetime',
        'b.request_created_datetime',
        "vl.$resultColumn",
        'vl.sample_tested_datetime',
        'vl.result_approved_datetime',
        'vl.result_sent_to_source_datetime',
        'vl.last_modified_datetime'
    ];

    /*
    * Paging
    */
    $sOffset = $sLimit = null;
    if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
        $sOffset = $_POST['iDisplayStart'];
        $sLimit = $_POST['iDisplayLength'];
    }



    $sOrder = "";
    if (isset($_POST['iSortCol_0'])) {
        $sOrder = "";
        for ($i = 0; $i < (int) $_POST['iSortingCols']; $i++) {
            if ($_POST['bSortable_' . (int) $_POST['iSortCol_' . $i]] == "true") {
                $sOrder .= $orderColumns[(int) $_POST['iSortCol_' . $i]] . "
                " . ($_POST['sSortDir_' . $i]) . ", ";
            }
        }
        $sOrder = substr_replace($sOrder, "", -2);
    }



    $sWhere = [];
    if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
        $searchArray = explode(" ", (string) $_POST['sSearch']);
        $sWhereSub = "";
        foreach ($searchArray as $search) {
            $sWhereSub .= " (";
            $colSize = count($aColumns);

            for ($i = 0; $i < $colSize; $i++) {
                if ($i < $colSize - 1) {
                    $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' OR ";
                } else {
                    $sWhereSub .= $aColumns[$i] . " LIKE '%" . ($search) . "%' ";
                }
            }
            $sWhereSub .= ")";
        }
        $sWhere[] = $sWhereSub;
    }



    /*
    * SQL queries
    * Get data to display
    */
    $aWhere = '';
    $sQuery = '';

    $sQuery = "SELECT
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
                    vl.$resultColumn,vl.result_reviewed_datetime,
                    vl.result_approved_datetime,
                    vl.result_sent_to_source_datetime,
                    vl.last_modified_datetime
                FROM $table as vl
                LEFT JOIN facility_details as l ON vl.lab_id = l.facility_id
                LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                LEFT JOIN batch_details as b ON vl.sample_batch_id=b.batch_id";

    [$start_date, $end_date] = DateUtility::convertDateRange($_POST['dateRange'] ?? '');

    if (isset($_POST['dateRange']) && trim((string) $_POST['dateRange']) != '') {
        $sWhere[] = ' DATE(vl.request_created_datetime) BETWEEN "' . $start_date . '" AND "' . $end_date . '"';
    }
    if (isset($_POST['labName']) && trim((string) $_POST['labName']) != '') {
        $sWhere[] = ' vl.lab_id IN (' . $_POST['labName'] . ')';
    }
    if (isset($_POST['state']) && trim((string) $_POST['state']) != '') {
        $provinceId = implode(',', $_POST['state']);
        $sWhere[] = ' f.facility_state_id  IN (' . $provinceId . ')';
    }
    if (isset($_POST['district']) && trim((string) $_POST['district']) != '') {
        $districtId = implode(',', $_POST['district']);
        $sWhere[] = ' f.facility_district_id  IN (' . $districtId . ')';
    }
    if (isset($_POST['facilityId']) && trim((string) $_POST['facilityId']) != '') {
        $facilityId = implode(',', $_POST['facilityId']);
        $sWhere[] = ' vl.facility_id  IN (' . $facilityId . ')';
    }

    if (isset($_POST['srcRequest']) && trim((string) $_POST['srcRequest']) != '') {
        $sWhere[] = ' vl.source_of_request = "' . $_POST['srcRequest'] . '"';
    }

    /* Implode all the where fields for filtering the data */
    if (!empty($sWhere)) {
        $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere);
    }

    //$sQuery = $sQuery . ' GROUP BY source_of_request, lab_id, DATE(vl.request_created_datetime)';
    if (!empty($sOrder)) {
        $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
        $sQuery = $sQuery . " ORDER BY " . $sOrder;
    }
    //echo $sQuery; die;
    $_SESSION['samplewiseReportsQuery'] = $sQuery;

    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
    }

    [$rResult, $resultCount] = $db->getQueryResultAndCount($sQuery);


    $calcValueQuery = "SELECT SUM(CASE WHEN (vl.request_created_datetime is not null AND vl.request_created_datetime > '0000-00-00') THEN 1 ELSE 0 END) AS 'totalSamplesRequested',
                SUM(CASE WHEN (vl.request_created_datetime is not null AND vl.request_created_datetime  > '0000-00-00') THEN 1 ELSE 0 END) AS 'totalSamplesAcknowledged',
                SUM(CASE WHEN (vl.sample_received_at_lab_datetime is not null AND vl.sample_received_at_lab_datetime  > '0000-00-00') THEN 1 ELSE 0 END) AS 'totalSamplesReceived',
                SUM(CASE WHEN (vl.sample_tested_datetime is not null AND vl.sample_tested_datetime  > '0000-00-00') THEN 1 ELSE 0 END) AS 'totalSamplesTested',
                SUM(CASE WHEN ((vl.result_dispatched_datetime is not null AND vl.result_dispatched_datetime  > '0000-00-00') OR (vl.result_sent_to_source_datetime is not null AND vl.result_sent_to_source_datetime  > '0000-00-00')) THEN 1 ELSE 0 END) AS 'totalSamplesDispatched'
                FROM $table as vl
            LEFT JOIN facility_details as l ON vl.lab_id = l.facility_id
            LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
            LEFT JOIN batch_details as b ON vl.sample_batch_id=b.batch_id";

    if (!empty($sWhere)) {
        $calcValueQuery = $calcValueQuery . ' WHERE ' . implode(" AND ", $sWhere);
    }

    if (!empty($sOrder)) {
        $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
        $calcValueQuery = $calcValueQuery . " ORDER BY " . $sOrder;
    }
    $_SESSION['samplewiseReportsCalc'] = $calcValueQuery;

    $calculateFields = $db->rawQuery($calcValueQuery);


    /*
    * Output
    */
    $output = array(
        "sEcho" => (int) $_POST['sEcho'],
        "iTotalRecords" => $resultCount,
        "iTotalDisplayRecords" => $resultCount,
        "calculation" => [],
        "aaData" => []
    );
    foreach ($calculateFields as $row) {
        $r = [];
        $r[] = $row['totalSamplesRequested'];
        $r[] = $row['totalSamplesAcknowledged'];
        $r[] = $row['totalSamplesReceived'];
        $r[] = $row['totalSamplesTested'];
        $r[] = $row['totalSamplesDispatched'];
        $output['calculation'][] = $r;
    }
    foreach ($rResult as $key => $aRow) {

        $row = [];
        //$row[] = $aRow['f.facility_name'];
        $row[] = $aRow['sample_code'];
        $row[] = $aRow['remote_sample_code'];
        $row[] = $aRow['external_sample_code'] ?? $aRow['app_sample_code'];
        $row[] = $aRow['labname'];
        $row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime'], true);
        $row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime'], true);
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'], true);
        $row[] = DateUtility::humanReadableDateFormat($aRow['batch_request_created'], true);
        $row[] = $aRow['status_name'];
        $row[] = $aRow['result'];
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'], true);
        $row[] = (!empty($aRow['sample_tested_datetime']) && !empty($aRow['result_approved_datetime'])) ? DateUtility::humanReadableDateFormat($aRow['result_approved_datetime'], true) : "";
        $row[] = DateUtility::humanReadableDateFormat($aRow['result_sent_to_source_datetime'], true);
        $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'], true);

        $output['aaData'][] = $row;
    }

    echo JsonUtility::encodeUtf8Json($output);

    $db->commitTransaction();
} catch (Exception $exc) {
    LoggerUtility::log('error', $exc->getMessage(), ['trace' => $exc->getTraceAsString()]);
}
