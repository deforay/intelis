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

    $resultChangeColumn = " vl.reason_for_changing";
    if ($testType == "vl" || $testType == "cd4") {
        $resultChangeColumn = " vl.reason_for_result_changes";
    }

    $table = TestsService::getTestTableName($testType);
    $testName = TestsService::getTestName($testType);


    $rejectionTable = "r_{$testType}_sample_rejection_reasons";
    /*
     * Array of database columns which should be read and sent back to DataTables. Use a space where
     * you want to insert a non-database field (for example a counter or static image)
     */
    $orderColumns = $aColumns = [
        'vl.sample_code',
        'vl.remote_sample_code',
        'vl.sample_collection_date',
        'vl.sample_received_at_lab_datetime',
        'vl.sample_tested_datetime',
        "vl.$resultColumn",
        't_b.user_name',
        'ins.machine_name',
        'ts.status_name',
        'vl.manual_result_entry',
        'vl.is_sample_rejected',
        'vl.rs.rejection_reason_name',
        'result_modified',
        "$resultChangeColumn",
        'vl.last_modified_datetime',
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



    if ($testType == "covid19") {
        $joinCond = " LEFT JOIN covid19_tests as ct ON ct.covid19_id=vl.covid19_id
    LEFT JOIN instruments as ins ON ins.instrument_id=ct.instrument_id";
    } else {
        $joinCond = " LEFT JOIN instruments as ins ON ins.instrument_id=vl.instrument_id";
    }

    $aWhere = '';
    $sQuery = '';


    $sQuery = "SELECT
                    vl.sample_code,
                    ts.status_name,
                    t_b.user_name as testedByName,
                    vl.sample_tested_datetime,
                    vl.sample_collection_date,
                    vl.remote_sample_code,vl.result_modified,
                    vl.sample_received_at_lab_datetime,vl.last_modified_datetime,
                    vl.is_sample_rejected,$resultChangeColumn,
                    vl.$resultColumn,rs.rejection_reason_name,
                    ins.machine_name,vl.manual_result_entry,vl.import_machine_file_name
                FROM $table as vl
                LEFT JOIN facility_details as l ON vl.lab_id = l.facility_id
                LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                LEFT JOIN user_details as t_b ON t_b.user_id=vl.tested_by
                LEFT JOIN $rejectionTable as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection
                $joinCond
                LEFT JOIN batch_details as b ON vl.sample_batch_id=b.batch_id";

    [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleTestDate'] ?? '');

    if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) !== '') {
        $sWhere[] = ' DATE(vl.sample_tested_datetime) BETWEEN "' . $start_date . '" AND "' . $end_date . '"';
    }
    if (isset($_POST['sampleBatchCode']) && trim((string) $_POST['sampleBatchCode']) !== '') {
        $code = $_POST['sampleBatchCode'];
        $sWhere[] = " vl.sample_code = '$code' OR b.batch_code = '$code' ";
    }


    /* Implode all the where fields for filtering the data */
    if ($sWhere !== []) {
        $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere);
    }

    if (!empty($sOrder) && $sOrder !== '') {
        $sOrder = preg_replace('/\s+/', ' ', $sOrder);
        $sQuery = $sQuery . " ORDER BY " . $sOrder;
    }


    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
    }
    $_SESSION['testResultReportsQuery'] = $sQuery;

    [$rResult, $resultCount] = $db->getDataAndCount($sQuery);


    /*
     * Output
     */
    $output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $resultCount, "iTotalDisplayRecords" => $resultCount, "calculation" => [], "aaData" => []];

    foreach ($rResult as $key => $aRow) {

        $rejectedObj = json_decode((string) $aRow['reason_for_result_changes']);
        $row = [];
        //$row[] = $aRow['f.facility_name'];
        $row[] = $aRow['sample_code'];
        $row[] = $aRow['remote_sample_code'];
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'], true);
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'], true);
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'], true);
        $row[] = $aRow['result'];
        $row[] = $aRow['testedByName'];
        $row[] = $aRow['machine_name'];
        $row[] = $aRow['status_name'];
        $row[] = $aRow['manual_result_entry'];
        $row[] = $aRow['is_sample_rejected'];
        $row[] = $aRow['rejection_reason_name'];
        $row[] = $aRow["result_modified"];
        $row[] = $rejectedObj->reasonForChange;
        $row[] = DateUtility::humanReadableDateFormat($aRow["last_modified_datetime"]);
        $fileName = $aRow['import_machine_file_name'];
        if (!empty($aRow['import_machine_file_name']) && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results" . DIRECTORY_SEPARATOR . $aRow['import_machine_file_name'])) {
            $a = "/uploads/imported-results" . DIRECTORY_SEPARATOR . $fileName;
            $row[] = '<a title="' . $fileName . '" href="' . $a . '" download> Download </a>';
        } else {
            $row[] = $fileName;
        }
        $output['aaData'][] = $row;
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
