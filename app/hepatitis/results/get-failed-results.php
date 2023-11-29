<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


$formConfigQuery = "SELECT * FROM global_config";
$configResult = $db->query($formConfigQuery);
$gconfig = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($configResult); $i++) {
    $gconfig[$configResult[$i]['name']] = $configResult[$i]['value'];
}
//system config
$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
    $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tableName = "form_hepatitis";
$primaryKey = "hepatitis_id";

/* Array of database columns which should be read and sent back to DataTables. Use a space where
* you want to insert a non-database field (for example a counter or static image)
*/
$sampleCode = 'sample_code';

$aColumns = array('vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_id', 'vl.patient_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name');
$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_id', 'vl.patient_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name');

if ($_SESSION['instanceType'] == 'remoteuser') {
    $sampleCode = 'remote_sample_code';
} else if ($sarr['sc_user_type'] == 'standalone') {
    $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
    $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
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

/*
* Ordering
*/

$sOrder = "";
if (isset($_POST['iSortCol_0'])) {
    $sOrder = "";
    for ($i = 0; $i < intval($_POST['iSortingCols']); $i++) {
        if ($_POST['bSortable_' . intval($_POST['iSortCol_' . $i])] == "true") {
            if (!empty($orderColumns[intval($_POST['iSortCol_' . $i])]))
                $sOrder .= $orderColumns[intval($_POST['iSortCol_' . $i])] . "
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
    $searchArray = explode(" ", $_POST['sSearch']);
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

/* Individual column filtering */
for ($i = 0; $i < count($aColumns); $i++) {
    if (isset($_POST['bSearchable_' . $i]) && $_POST['bSearchable_' . $i] == "true" && $_POST['sSearch_' . $i] != '') {
        $sWhere[] = $aColumns[$i] . " LIKE '%" . ($_POST['sSearch_' . $i]) . "%' ";
    }
}

/*
          * SQL queries
          * Get data to display
          */

$sQuery = "SELECT vl.*, f.*, ts.status_name, b.batch_code FROM form_hepatitis as vl
          LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
          LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
          LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

$start_date = '';
$end_date = '';
if (!empty($_POST['sampleCollectionDate'])) {
    [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
}

if (!empty($_POST['sampleCollectionDate'])) {
    if (trim($start_date) == trim($end_date)) {
        $sWhere[] = ' DATE(vl.sample_collection_date) like  "' . $start_date . '"';
    } else {
        $sWhere[] =  ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
    }
}

if (isset($_POST['sampleType']) && $_POST['sampleType'] != '') {
    $sWhere[] =  ' vl.specimen_type = "' . $_POST['sampleType'] . '"';
}
if (isset($_POST['facilityName']) && $_POST['facilityName'] != '') {
    $sWhere[] =  ' f.facility_id IN (' . $_POST['facilityName'] . ')';
}
if (isset($_POST['district']) && trim($_POST['district']) != '') {
    $sWhere[] =  " f.facility_district_id = '" . $_POST['district'] . "' ";
}
if (isset($_POST['state']) && trim($_POST['state']) != '') {
    $sWhere[] = " f.facility_state_id = '" . $_POST['state'] . "' ";
}
if (isset($_POST['vlLab']) && trim($_POST['vlLab']) != '') {
    $sWhere[] =  '  vl.lab_id IN (' . $_POST['vlLab'] . ')';
}
if (isset($_POST['status']) && $_POST['status'] != '') {
    $sWhere[] =  ' vl.result_status = "' . $_POST['status'] . '"';
}
if (isset($_POST['patientId']) && $_POST['patientId'] != "") {
    $sWhere[] = ' vl.patient_id like "%' . $_POST['patientId'] . '%"';
}
if (isset($_POST['patientName']) && $_POST['patientName'] != "") {
    $sWhere[] = " CONCAT(COALESCE(vl.patient_name,''), COALESCE(vl.patient_surname,'')) like '%" . $_POST['patientName'] . "%'";
}
if ($_SESSION['instanceType'] == 'remoteuser' && !empty($_SESSION['facilityMap'])) {
    $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
}


if (!empty($sWhere)) {
    $sWhere = ' where ' . implode(' AND ', $sWhere);
} else {
    $sWhere = "";
}

$sQuery = $sQuery . $sWhere;
if (isset($sOrder) && !empty(trim($sOrder))) {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . " ORDER BY " . $sOrder;
}
$_SESSION['covid19RequestSearchResultQuery'] = $sQuery;
if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}
$rResult = $db->rawQuery($sQuery);

$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];

$_SESSION['covid19RequestSearchResultQueryCount'] = $iTotal;

$output = array(
    "sEcho" => intval($_POST['sEcho']),
    "iTotalRecords" => $iTotal,
    "iTotalDisplayRecords" => $iFilteredTotal,
    "aaData" => []
);

foreach ($rResult as $aRow) {

    if (isset($aRow['sample_collection_date']) && trim($aRow['sample_collection_date']) != '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
        $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
    } else {
        $aRow['sample_collection_date'] = '';
    }
    if (isset($aRow['last_modified_datetime']) && trim($aRow['last_modified_datetime']) != '' && $aRow['last_modified_datetime'] != '0000-00-00 00:00:00') {
        $aRow['last_modified_datetime'] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'], true);
    } else {
        $aRow['last_modified_datetime'] = '';
    }

    $row = [];

    $row[] = '<input type="checkbox" name="chk[]" class="checkTests" id="chk' . $aRow['hepatitis_id'] . '"  value="' . $aRow['hepatitis_id'] . '" onchange="resetBtnShowHide();" onclick="toggleTest(this);"  />';
    $row[] = $aRow['sample_code'];
    if ($_SESSION['instanceType'] != 'standalone') {
        $row[] = $aRow['remote_sample_code'];
    }
    if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
        $key = base64_decode($general->getGlobalConfig('key'));
        $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
        $aRow['patient_name'] = $general->crypto('decrypt', $aRow['patient_name'], $key);
    }
    $row[] = $aRow['sample_collection_date'];
    $row[] = $aRow['batch_code'];
    $row[] = ($aRow['facility_name']);
    $row[] = $aRow['patient_id'];
    $row[] = $aRow['patient_name'];

    $row[] = ($aRow['facility_state']);
    $row[] = ($aRow['facility_district']);
    $row[] = ($aRow['result']);
    $row[] = $aRow['last_modified_datetime'];
    $row[] = ($aRow['status_name']);


    $row[] = '<a href="javascript:void(0);" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Failed result retest") . '" onclick="retestSample(\'' . trim(base64_encode($aRow['hepatitis_id'])) . '\')"><em class="fa-solid fa-arrows-rotate"></em>' . _translate("Retest") . '</a>';

    $output['aaData'][] = $row;
}
echo json_encode($output);
