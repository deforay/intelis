<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}



/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "form_hepatitis";
$primaryKey = "hepatitis_id";
//config  query
$configQuery = "SELECT * from global_config";
$configResult = $db->query($configQuery);
$arr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($configResult); $i++) {
    $arr[$configResult[$i]['name']] = $configResult[$i]['value'];
}
//system config
$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
    $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}
/* Array of database columns which should be read and sent back to DataTables. Use a space where
 * you want to insert a non-database field (for example a counter or static image)
 */
$aColumns = array('vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_id', 'vl.patient_name', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'fd.facility_name');
$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_id', 'vl.patient_name', 'vl.sample_collection_date', 'fd.facility_name');
if ($sarr['sc_user_type'] == 'standalone') {
    $aColumns = array('vl.sample_code', 'f.facility_name', 'vl.patient_id', 'vl.patient_name', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'fd.facility_name');
    $orderColumns = array('vl.sample_code', 'f.facility_name', 'vl.patient_id', 'vl.patient_name', 'vl.sample_collection_date', 'fd.facility_name');
}

/* Indexed column (used for fast and accurate table cardinality) */
$sIndexColumn = $primaryKey;

$sTable = $tableName;
/*
 * Paging
 */
$sLimit = null;
if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
    $sOffset = $_POST['iDisplayStart'];
    $sLimit = $_POST['iDisplayLength'];
}



$sOrder = "";
if (isset($_POST['iSortCol_0'])) {
    for ($i = 0; $i < (int) $_POST['iSortingCols']; $i++) {
        if ($_POST['bSortable_' . (int) $_POST['iSortCol_' . $i]] == "true") {
            $sOrder .= $orderColumns[(int) $_POST['iSortCol_' . $i]] . "
				 	" . ($_POST['sSortDir_' . $i]) . ", ";
        }
    }
    $sOrder = substr_replace($sOrder, "", -2);
}

/*
 * Filtering
 * NOTE this does not match the built-in DataTables filtering which does it
 * word by word on any field. It's possible to do here, but concerned about efficiency
 * on very large tables, and MySQL's regex functionality is very limited
 */

$sWhere = [];
if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
    $searchArray = explode(" ", (string) $_POST['sSearch']);
    $sWhereSub = "";
    foreach ($searchArray as $search) {
        if ($sWhereSub == "") {
            $sWhereSub .= "(";
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
$sQuery = "SELECT SQL_CALC_FOUND_ROWS vl.*,
                    f.*,s.*,fd.facility_name as labName,
                    ts.status_name
                    FROM form_hepatitis as vl
                    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                    LEFT JOIN facility_details as fd ON fd.facility_id=vl.lab_id
                    LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.specimen_type
                    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
                    INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                    WHERE vl.result_status != " . SAMPLE_STATUS\REJECTED . "
                    AND vl.sample_code is NOT NULL
                    AND (vl.hcv_vl_result IS NULL OR vl.hcv_vl_result='') AND (vl.hbv_vl_result IS NULL OR vl.hbv_vl_result='')";
$start_date = '';
$end_date = '';
if (isset($_POST['noResultBatchCode']) && trim((string) $_POST['noResultBatchCode']) != '') {
    $sWhere[] = ' b.batch_code LIKE "%' . $_POST['noResultBatchCode'] . '%"';
}

if (isset($_POST['noResultSampleTestDate']) && trim((string) $_POST['noResultSampleTestDate']) != '') {
    $s_c_date = explode("to", (string) $_POST['noResultSampleTestDate']);
    //print_r($s_c_date);die;
    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $start_date = DateUtility::isoDateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $end_date = DateUtility::isoDateFormat(trim($s_c_date[1]));
    }
    if (trim((string) $start_date) == trim((string) $end_date)) {
        $sWhere[] = ' DATE(vl.sample_collection_date) = "' . $start_date . '"';
    } else {
        $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
    }
}
if (isset($_POST['noResultSampleType']) && $_POST['noResultSampleType'] != '') {
    $sWhere[] = ' s.sample_id = "' . $_POST['noResultSampleType'] . '"';
}
if (isset($_POST['noResultState']) && trim((string) $_POST['noResultState']) != '') {
    $sWhere[] = " f.facility_state_id = '" . $_POST['noResultState'] . "' ";
}
if (isset($_POST['noResultDistrict']) && trim((string) $_POST['noResultDistrict']) != '') {
    $sWhere[] = " f.facility_district_id = '" . $_POST['noResultDistrict'] . "' ";
}
if (isset($_POST['noResultFacilityName']) && $_POST['noResultFacilityName'] != '') {
    $sWhere[] = ' f.facility_id IN (' . $_POST['noResultFacilityName'] . ')';
}
if (isset($_POST['noResultGender']) && $_POST['noResultGender'] != '') {
    $sWhere[] = ' vl.patient_gender = "' . $_POST['noResultGender'] . '"';
}
if (isset($_POST['noResultPatientPregnant']) && $_POST['noResultPatientPregnant'] != '') {
    $sWhere[] = ' vl.is_patient_pregnant = "' . $_POST['noResultPatientPregnant'] . '"';
}
if (isset($_POST['noResultPatientBreastfeeding']) && $_POST['noResultPatientBreastfeeding'] != '') {
    $sWhere[] = ' vl.is_patient_breastfeeding = "' . $_POST['noResultPatientBreastfeeding'] . '"';
}

if ($_SESSION['instanceType'] == 'remoteuser' && !empty($_SESSION['facilityMap'])) {
    $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
}

if (!empty($sWhere)) {
    $sWhere = ' AND ' . implode(' AND ', $sWhere);
} else {
    $sWhere = "";
}
$sQuery = $sQuery . ' ' . $sWhere;
$sQuery = $sQuery . ' group by vl.hepatitis_id';
if (!empty($sOrder)) {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . ' order by ' . $sOrder;
}
$_SESSION['resultNotAvailable'] = $sQuery;

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}

// echo $sQuery;die;
$rResult = $db->rawQuery($sQuery);
$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];
$_SESSION['resultNotAvailableCount'] = $iTotal;

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
    if (isset($aRow['sample_collection_date']) && trim((string) $aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
        $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
    } else {
        $aRow['sample_collection_date'] = '';
    }
    if ($aRow['remote_sample'] == 'yes') {
        $decrypt = 'remote_sample_code';
    } else {
        $decrypt = 'sample_code';
    }
    $patientName = $general->crypto('doNothing', $aRow['patient_name'], $aRow[$decrypt]);

    $row = [];

    $row[] = $aRow['sample_code'];
    if ($sarr['sc_user_type'] != 'standalone') {
        $row[] = $aRow['remote_sample_code'];
    }
    if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
        $key = base64_decode((string) $general->getGlobalConfig('key'));
        $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
        $patientName = $general->crypto('decrypt', $patientName, $key);
    }
    $row[] = ($aRow['facility_name']);
    $row[] = $aRow['patient_id'];
    $row[] = ($patientName);
    $row[] = $aRow['sample_collection_date'];
    $row[] = ($aRow['labName']);
    $row[] = ($aRow['status_name']);
    $output['aaData'][] = $row;
}
echo json_encode($output);
