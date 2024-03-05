<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\HepatitisService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$formConfigQuery = "SELECT * FROM `global_config` WHERE `name`='vl_form'";
$configResult = $db->query($formConfigQuery);
$arr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($configResult); $i++) {
    $arr[$configResult[$i]['name']] = $configResult[$i]['value'];
}
//system config
$systemConfigQuery = "SELECT * FROM `system_config`";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
    $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var HepatitisService $hepatitisService */
$hepatitisService = ContainerRegistry::get(HepatitisService::class);

//$hepatitisResults = $hepatitisService->getHepatitisResults();
$key = (string) $general->getGlobalConfig('key');

$tableName = "form_hepatitis";
$primaryKey = "hepatitis_id";

$sampleCode = 'sample_code';
$aColumns = array('vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_id', 'CONCAT(COALESCE(vl.patient_name,""), COALESCE(vl.patient_surname,""))', 'f.facility_name', 'l.facility_name', 'vl.hcv_vl_count', 'vl.hbv_vl_count', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y')", 'ts.status_name');
$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_id', 'vl.patient_name', 'f.facility_name', 'l.facility_name', 'vl.hcv_vl_count', 'vl.hbv_vl_count', 'vl.last_modified_datetime', 'ts.status_name');
if ($_SESSION['instance']['type'] == 'remoteuser') {
    $sampleCode = 'remote_sample_code';
} else if ($sarr['sc_user_type'] == 'standalone') {
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
        if ($sWhereSub == "") {
            $sWhereSub .= " (";
        } else {
            $sWhereSub .= " AND (";
        }
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
$sQuery = "SELECT SQL_CALC_FOUND_ROWS vl.*,b.*,ts.*,imp.*,
            f.facility_name, f.facility_district,f.facility_state,
            l.facility_name as labName,
            l.facility_logo as facilityLogo,
            l.header_text as headerText,
            f.facility_code,f.facility_state,f.facility_district,
            imp.i_partner_name,
            u_d.user_name as reviewedBy,
            a_u_d.user_name as approvedBy,
            c.iso_name as nationality,
            rs.rejection_reason_name
            FROM form_hepatitis as vl
            LEFT JOIN r_countries as c ON vl.patient_nationality=c.id
            LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
            LEFT JOIN facility_details as l ON vl.lab_id=l.facility_id
            INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
            LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
            LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by
            LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by
            LEFT JOIN r_hepatitis_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection
            LEFT JOIN r_implementation_partners as imp ON imp.i_partner_id=vl.implementing_partner";
$start_date = '';
$end_date = '';
$t_start_date = '';
$t_end_date = '';
if (!empty($_POST['sampleCollectionDate'])) {
    $s_c_date = explode("to", (string) $_POST['sampleCollectionDate']);
    //print_r($s_c_date);die;
    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $start_date = DateUtility::isoDateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $end_date = DateUtility::isoDateFormat(trim($s_c_date[1]));
    }
}

if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) != '') {
    $s_t_date = explode("to", (string) $_POST['sampleTestDate']);
    //print_r($s_t_date);die;
    if (isset($s_t_date[0]) && trim($s_t_date[0]) != "") {
        $t_start_date = DateUtility::isoDateFormat(trim($s_t_date[0]));
    }
    if (isset($s_t_date[1]) && trim($s_t_date[1]) != "") {
        $t_end_date = DateUtility::isoDateFormat(trim($s_t_date[1]));
    }
}

if (isset($_POST['district']) && trim((string) $_POST['district']) != '') {
    $sWhere[] = ' f.facility_district_id = "' . $_POST['district'] . '"';
}
if (isset($_POST['state']) && trim((string) $_POST['state']) != '') {
    $sWhere[] = ' f.facility_state_id = "' . $_POST['state'] . '"';
}

if (isset($_POST['patientId']) && $_POST['patientId'] != "") {
    $sWhere[] = ' vl.patient_id like "%' . $_POST['patientId'] . '%"';
}
if (isset($_POST['patientName']) && $_POST['patientName'] != "") {
    $sWhere[] = " CONCAT(COALESCE(vl.patient_name,''), COALESCE(vl.patient_surname,'')) like '%" . $_POST['patientName'] . "%'";
}


if (!empty($_POST['sampleCollectionDate'])) {
    if (trim((string) $start_date) == trim((string) $end_date)) {
        $sWhere[] = ' DATE(vl.sample_collection_date) like  "' . $start_date . '"';
    } else {
        $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
    }
}

if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) != '') {
    if (trim((string) $t_start_date) == trim((string) $t_end_date)) {
        $sWhere[] = ' DATE(vl.sample_tested_datetime) = "' . $t_start_date . '"';
    } else {
        $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $t_start_date . '" AND DATE(vl.sample_tested_datetime) <= "' . $t_end_date . '"';
    }
}
if (isset($_POST['sampleType']) && trim((string) $_POST['sampleType']) != '') {
    $sWhere[] = ' s.sample_id = "' . $_POST['sampleType'] . '"';
}
if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) != '') {
    $sWhere[] = ' f.facility_id IN (' . $_POST['facilityName'] . ')';
}

if (isset($_POST['labId']) && trim((string) $_POST['labId']) != '') {
    $sWhere[] = ' vl.lab_id IN (' . $_POST['labId'] . ')';
}
if (isset($_POST['artNo']) && trim((string) $_POST['artNo']) != '') {
    $sWhere[] = " vl.child_id LIKE '%" . $_POST['artNo'] . "%' ";
}
if (isset($_POST['status']) && trim((string) $_POST['status']) != '') {
    if ($_POST['status'] == 'no_result') {
        $statusCondition = '  (vl.hcv_vl_count is NULL AND vl.hcv_vl_count  ="" AND vl.hbv_vl_count is NULL AND vl.hbv_vl_count  ="")';
    } else if ($_POST['status'] == 'result') {
        $statusCondition = ' (vl.hcv_vl_count is NOT NULL OR vl.hcv_vl_count  !="" OR vl.hbv_vl_count is NOT NULL OR vl.hbv_vl_count  !="")';
    } else {
        $statusCondition = ' vl.result_status = ' . SAMPLE_STATUS\REJECTED;
    }
    $sWhere[] = $statusCondition;
}
if (isset($_POST['gender']) && trim((string) $_POST['gender']) != '') {
    if (trim((string) $_POST['gender']) == "unreported") {
        $sWhere[] = ' (vl.patient_gender = "unreported" OR vl.patient_gender ="" OR vl.patient_gender IS NULL)';
    } else {
        $sWhere[] = ' vl.patient_gender ="' . $_POST['gender'] . '"';
    }
}
if (isset($_POST['fundingSource']) && trim((string) $_POST['fundingSource']) != '') {
    $sWhere[] = ' vl.funding_source ="' . base64_decode((string) $_POST['fundingSource']) . '"';
}
if (isset($_POST['implementingPartner']) && trim((string) $_POST['implementingPartner']) != '') {
    $sWhere[] = ' vl.implementing_partner ="' . base64_decode((string) $_POST['implementingPartner']) . '"';
}

if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) != '') {
    $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}


if (!isset($_POST['status']) || trim((string) $_POST['status']) == '') {
    if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'not-print') {
        $sWhere[] = " ((vl.result_status = 7 AND (vl.hcv_vl_count is NOT NULL OR vl.hcv_vl_count  !='' OR vl.hbv_vl_count is NOT NULL OR vl.hbv_vl_count  !='')) OR (vl.result_status = 4 AND (vl.hcv_vl_count is NULL OR vl.hcv_vl_count  ='' OR vl.hbv_vl_count is NULL OR vl.hbv_vl_count  =''))) AND (result_printed_datetime is NULL OR DATE(result_printed_datetime) = '0000-00-00')";
    } else {
        $sWhere[] = " ((vl.result_status = 7 AND (vl.hcv_vl_count is NOT NULL OR vl.hcv_vl_count  !='' OR vl.hbv_vl_count is NOT NULL OR vl.hbv_vl_count  !='')) OR (vl.result_status = 4 AND (vl.hcv_vl_count is NULL OR vl.hcv_vl_count  ='' OR vl.hbv_vl_count is NULL OR vl.hbv_vl_count  =''))) AND (result_printed_datetime is not NULL OR result_printed_datetime  > '0000-00-00')";
    }
} else {
    $sWhere[] = " vl.result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC;
}
if ($_SESSION['instance']['type'] == 'remoteuser' && !empty($_SESSION['facilityMap'])) {
    $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
}
if (!empty($sWhere)) {
    $sQuery = $sQuery . ' WHERE' . implode(" AND ", $sWhere);
}
if (!empty($sOrder)) {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . ' order by ' . $sOrder;
}
$_SESSION['hepatitisPrintQuery'] = $sQuery;
if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
//error_log($sQuery);
// die($sQuery);
$rResult = $db->rawQuery($sQuery);
/* Data set length after filtering */
$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];

/*
 * Output
 */
$output = array(
    "sEcho" => (int) $_POST['sEcho'],
    "iTotalRecords" => $iTotal,
    "iTotalDisplayRecords" => $iFilteredTotal,
    "aaData" => []
);

foreach ($rResult as $aRow) {
    $row = [];
    if (isset($_POST['vlPrint'])) {
        if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'not-print') {
            $row[] = '<input type="checkbox" name="chk[]" class="checkRows" id="chk' . $aRow['hepatitis_id'] . '"  value="' . $aRow['hepatitis_id'] . '" onclick="checkedRow(this);"  />';
        } else {
            $row[] = '<input type="checkbox" name="chkPrinted[]" class="checkPrintedRows" id="chkPrinted' . $aRow['hepatitis_id'] . '"  value="' . $aRow['hepatitis_id'] . '" onclick="checkedPrintedRow(this);"  />';
        }
        $print = '<a href="javascript:void(0);" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Print") . '" onclick="resultPDF(' . $aRow['hepatitis_id'] . ')"><em class="fa-solid fa-print"></em> ' . _translate("Print") . '</a>';
    }

    $patientFname = $general->crypto('doNothing', $aRow['patient_name'], $aRow['patient_id']);
    $patientLname = $general->crypto('doNothing', $aRow['patient_surname'], $aRow['patient_id']);

    $row[] = $aRow['sample_code'];
    if ($_SESSION['instance']['type'] != 'standalone') {
        $row[] = $aRow['remote_sample_code'];
    }
    if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
        $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
        $patientFname = $general->crypto('decrypt', $patientFname, $key);
        $patientLname = $general->crypto('decrypt', $patientLname, $key);
    }
    $row[] = $aRow['batch_code'];
    $row[] = $aRow['patient_id'];
    $row[] = ($patientFname . " " . $patientLname);
    $row[] = ($aRow['facility_name']);
    $row[] = ($aRow['labName']);
    $row[] = ($aRow['facility_state']);
    $row[] = ($aRow['facility_district']);
    $row[] = $aRow['hcv_vl_count'];
    $row[] = $aRow['hbv_vl_count'];

    if (isset($aRow['last_modified_datetime']) && trim((string) $aRow['last_modified_datetime']) != '' && $aRow['last_modified_datetime'] != '0000-00-00 00:00:00') {
        $aRow['last_modified_datetime'] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'], true);
    } else {
        $aRow['last_modified_datetime'] = '';
    }

    $row[] = $aRow['last_modified_datetime'];
    $row[] = ($aRow['status_name']);
    $row[] = $print;
    $output['aaData'][] = $row;
}

echo json_encode($output);
