<?php

use Laminas\Diactoros\ServerRequest;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\HepatitisService;
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

    /** @var HepatitisService $hepatitisService */
    $hepatitisService = ContainerRegistry::get(HepatitisService::class);
    $hepatitisResults = $hepatitisService->getHepatitisResults();


    $tableName = "form_hepatitis";
    $primaryKey = "hepatitis_id";
    $key = (string) $general->getGlobalConfig('key');


    $sampleCode = 'sample_code';
    $aColumns = ['vl.sample_code', 'vl.external_sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_id', 'CONCAT(COALESCE(vl.patient_name,""), COALESCE(vl.patient_surname,""))', 'f.facility_name', 'l.facility_name', 'vl.hcv_vl_count', 'vl.hbv_vl_count', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y')", 'ts.status_name'];
    $orderColumns = ['vl.sample_code', 'vl.external_sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_id', 'vl.patient_name', 'f.facility_name', 'l.facility_name', 'vl.hcv_vl_count', 'vl.hbv_vl_count', 'vl.last_modified_datetime', 'ts.status_name'];
    if ($general->isSTSInstance()) {
        $sampleCode = 'remote_sample_code';
    } elseif ($general->isStandaloneInstance()) {
        $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
        $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
    }

    /* Indexed column (used for fast and accurate table cardinality) */
    $sIndexColumn = $primaryKey;

    $sTable = $tableName;

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


    $sQuery = "SELECT vl.*, l.facility_name as labName,b.batch_code,f.facility_name FROM form_hepatitis as vl
            LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
            LEFT JOIN facility_details as l ON vl.lab_id=l.facility_id
            INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
            LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";



    if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) !== '') {
        $sWhere[] = ' b.batch_code LIKE "%' . $_POST['batchCode'] . '%"';
    }
    if (!empty($_POST['sampleCollectionDate'])) {
        [$start_date, $end_date] = DateUtility::convertDateRange($_POST['dateRange'] ?? '');
        $sWhere[] =  " DATE(vl.sample_collection_date) BETWEEN '$start_date' AND '$end_date'";
    }
    if (isset($_POST['facilityName']) && $_POST['facilityName'] != '') {
        $sWhere[] = ' f.facility_id IN (' . $_POST['facilityName'] . ')';
    }
    if (isset($_POST['statusFilter']) && $_POST['statusFilter'] != '') {
        if ($_POST['statusFilter'] == 'approvedOrRejected') {
            $sWhere[] =  ' vl.result_status IN (4,7)';
        } elseif ($_POST['statusFilter'] == 'notApprovedOrRejected') {
            $sWhere[] = ' vl.result_status IN (6,8)';
        }
    }
    if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
        $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
    }

    $sWhere[] = ' (vl.hcv_vl_count !="" OR vl.hbv_vl_count !="") ';

    /* Implode all the where fields for filtering the data */
    if ($sWhere !== []) {
        $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere);
    }

    //echo $sQuery;
    if (!empty($sOrder) && $sOrder !== '') {
        $sOrder = preg_replace('/\s+/', ' ', (string) $sOrder);
        $sQuery = "$sQuery ORDER BY $sOrder";
    }

    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
    }

    $_SESSION['hepatitisRequestSearchResultQuery'] = $sQuery;

    [$rResult, $resultCount] = $db->getDataAndCount($sQuery);

    $_SESSION['hepatitisRequestSearchResultQueryCount'] = $resultCount;


    $output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $resultCount, "iTotalDisplayRecords" => $resultCount, "aaData" => []];
    $vlRequest = false;
    $vlView = false;
    if ((_isAllowed("/hepatitis/requests/hepatitis-edit-request.php"))) {
        $vlRequest = true;
    }
    if ((_isAllowed("hepatitis-requests.php"))) {
        $vlView = true;
    }

    foreach ($rResult as $aRow) {
        if (isset($aRow['sample_collection_date']) && trim((string) $aRow['sample_collection_date']) !== '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
            $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
        } else {
            $aRow['sample_collection_date'] = '';
        }

        $patientFname = ($general->crypto('doNothing', $aRow['patient_name'], $aRow['patient_id']));
        $patientLname = ($general->crypto('doNothing', $aRow['patient_surname'], $aRow['patient_id']));


        $status = '<select class="form-control"  name="status[]" id="' . $aRow['hepatitis_id'] . '" title="' . _translate("Please select status") . '" onchange="updateStatus(this,' . $aRow['status_id'] . ')">
               <option value="">' . _translate("-- Select --") . '</option>
               <option value="7" ' . ($aRow['status_id'] == "7" ? "selected=selected" : "") . '>' . _translate("Accepted") . '</option>
               <option value="4" ' . ($aRow['status_id'] == "4"  ? "selected=selected" : "") . '>' . _translate("Rejected") . '</option>
               <option value="2" ' . ($aRow['status_id'] == "2"  ? "selected=selected" : "") . '>' . _translate("Lost") . '</option>
               </select><br><br>';

        $row = [];
        $row[] = '<input type="checkbox" name="chk[]" class="checkTests" id="chk' . $aRow['hepatitis_id'] . '"  value="' . $aRow['hepatitis_id'] . '" onclick="toggleTest(this);"  />';
        $row[] = $aRow['sample_code'] . (empty($aRow['external_sample_code']) ? '' : "<br>/" . $aRow['external_sample_code']);
        if (!$general->isStandaloneInstance()) {
            $row[] = $aRow['remote_sample_code'];
        }
        if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
            $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
            $patientFname = $general->crypto('decrypt', $patientFname, $key);
            $patientLname = $general->crypto('decrypt', $patientLname, $key);
        }
        $row[] = $aRow['sample_collection_date'];
        $row[] = $aRow['batch_code'];
        $row[] = $aRow['patient_id'];
        $row[] = "$patientFname $patientLname";
        $row[] = $aRow['facility_name'];
        $row[] = $aRow['hcv_vl_count'];
        $row[] = $aRow['hbv_vl_count'];

        $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '');
        //$row[] = $status;

        $output['aaData'][] = $row;
    }

    echo JsonUtility::encodeUtf8Json($output);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery(),
    ]);
}
