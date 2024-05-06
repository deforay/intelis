<?php

use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}



/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$key = (string) $general->getGlobalConfig('key');


/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);


$tableName = "form_eid";
$primaryKey = "eid_id";
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

$aColumns = array('vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.child_id', 'vl.child_name', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'fd.facility_name', 'rsrr.rejection_reason_name', 'r_c_a.recommended_corrective_action_name');
$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.child_id', 'vl.child_name', 'vl.sample_collection_date', 'fd.facility_name', 'rsrr.rejection_reason_name', 'r_c_a.recommended_corrective_action_name');

if ($general->isStandaloneInstance()) {
    $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
    $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
}

/* Indexed column (used for fast and accurate table cardinality) */
$sIndexColumn = $primaryKey;

$sTable = $tableName;
/*
         * Paging
         */
$sOffset = $sLimit = null;
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
$aWhere = '';
$sQuery = "SELECT SQL_CALC_FOUND_ROWS vl.*,f.*,s.*,fd.facility_name as labName,rsrr.rejection_reason_name,r_c_a.recommended_corrective_action_name FROM form_eid as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
LEFT JOIN facility_details as fd ON fd.facility_id=vl.lab_id
LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.specimen_type
LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
JOIN r_vl_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=vl.reason_for_sample_rejection
LEFT JOIN r_recommended_corrective_actions as r_c_a ON r_c_a.recommended_corrective_action_id=vl.recommended_corrective_action ";

$sWhere[] = " vl.is_sample_rejected='yes' ";
if (isset($_POST['rjtBatchCode']) && trim((string) $_POST['rjtBatchCode']) != '') {
    $sWhere[] = '  b.batch_code LIKE "%' . $_POST['rjtBatchCode'] . '%"';
}
[$start_date, $end_date] = DateUtility::convertDateRange($_POST['rjtSampleTestDate'] ?? '');
if (isset($_POST['rjtSampleTestDate']) && trim((string) $_POST['rjtSampleTestDate']) != '') {
    if (trim((string) $start_date) == trim((string) $end_date)) {
        $sWhere[] =  ' DATE(vl.sample_tested_datetime) = "' . $start_date . '"';
    } else {
        $sWhere[] =  ' DATE(vl.sample_tested_datetime) >= "' . $start_date . '" AND DATE(vl.sample_tested_datetime) <= "' . $end_date . '"';
    }
}
if (isset($_POST['rjtSampleType']) && $_POST['rjtSampleType'] != '') {
    $sWhere[] = ' s.sample_id = "' . $_POST['rjtSampleType'] . '"';
}
if (isset($_POST['rjtState']) && trim((string) $_POST['rjtState']) != '') {
    $sWhere[] = " f.facility_state_id = '" . $_POST['rjtState'] . "' ";
}
if (isset($_POST['rjtDistrict']) && trim((string) $_POST['rjtDistrict']) != '') {
    $sWhere[] = " f.facility_district_id = '" . $_POST['rjtDistrict'] . "' ";
}
if (isset($_POST['rjtFacilityName']) && $_POST['rjtFacilityName'] != '') {
    $sWhere[] =  ' f.facility_id IN (' . $_POST['rjtFacilityName'] . ')';
}
if (isset($_POST['rjtGender']) && $_POST['rjtGender'] != '') {
    if (trim((string) $_POST['rjtGender']) == "unreported") {
        $sWhere[] =  ' (vl.child_gender = "unreported" OR vl.child_gender ="" OR vl.child_gender IS NULL)';
    } else {
        $sWhere[] =  ' (vl.child_gender IS NOT NULL AND vl.child_gender ="' . $_POST['rjtGender'] . '") ';
    }
}
if (isset($_POST['rjtPatientPregnant']) && $_POST['rjtPatientPregnant'] != '') {
    $sWhere[] = '  vl.is_patient_pregnant = "' . $_POST['rjtPatientPregnant'] . '"';
}
if (isset($_POST['rjtPatientBreastfeeding']) && $_POST['rjtPatientBreastfeeding'] != '') {
    $sWhere[] = '  vl.is_patient_breastfeeding = "' . $_POST['rjtPatientBreastfeeding'] . '"';
}
if (isset($_POST['sampleRejectionReason']) && $_POST['sampleRejectionReason'] != '') {
    $sWhere[] = '  vl.reason_for_sample_rejection = "' . $_POST['sampleRejectionReason'] . '"';
}

if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
    if (!empty($_SESSION['facilityMap'])) {
        $sWhere[] =  " vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
    }
}

if (!empty($sWhere)) {
    $sQuery = $sQuery . ' WHERE ' . implode(" AND ", $sWhere);
}
$sQuery = $sQuery . ' group by vl.eid_id ';
if (!empty($sOrder)) {
    $sOrder = preg_replace('/(\v|\s)+/', ' ', $sOrder);
    $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
}
$_SESSION['rejectedViralLoadResult'] = $sQuery;
if (isset($sLimit) && isset($sOffset)) {
    $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
}

// echo $sQuery;die;
$rResult = $db->rawQuery($sQuery);
// print_r($rResult);
/* Data set length after filtering */

$aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
$iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];
$_SESSION['rejectedViralLoadResultCount'] = $iTotal;

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
    $childName = $general->crypto('doNothing', $aRow['child_name'], $aRow[$decrypt]);

    $row = [];
    $row[] = $aRow['sample_code'];
    if ($_SESSION['instance']['type'] != 'standalone') {
        $row[] = $aRow['remote_sample_code'];
    }
    if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
        $aRow['child_id'] = $general->crypto('decrypt', $aRow['child_id'], $key);
        $childName = $general->crypto('decrypt', $childName, $key);
    }
    $row[] = ($aRow['facility_name']);
    $row[] = $aRow['child_id'];
    $row[] = $childName;
    $row[] = $aRow['sample_collection_date'];
    $row[] = $aRow['labName'];
    $row[] = $aRow['rejection_reason_name'];
    $row[] = $aRow['recommended_corrective_action_name'];
    $output['aaData'][] = $row;
}
echo json_encode($output);
