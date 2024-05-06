<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}



/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$key = (string) $general->getGlobalConfig('key');

$tableName = "form_cd4";
$primaryKey = "cd4_id";

$aColumns = array('vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_art_no', 'vl.patient_first_name', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'fd.facility_name', 'rsrr.rejection_reason_name');
$orderColumns = array('vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_art_no', 'vl.patient_first_name', 'vl.sample_collection_date', 'fd.facility_name', 'rsrr.rejection_reason_name');

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
$sQuery = "SELECT SQL_CALC_FOUND_ROWS vl.*,f.*,s.*,fd.facility_name as labName,rsrr.rejection_reason_name FROM form_cd4 as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN facility_details as fd ON fd.facility_id=vl.lab_id LEFT JOIN r_cd4_sample_types as s ON s.sample_id=vl.specimen_type LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id JOIN r_cd4_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=vl.reason_for_sample_rejection ";

$sWhere[] = " vl.is_sample_rejected='yes'";
$start_date = '';
$end_date = '';
if (isset($_POST['rjtBatchCode']) && trim((string) $_POST['rjtBatchCode']) != '') {
    $sWhere[] =  ' b.batch_code LIKE "%' . $_POST['rjtBatchCode'] . '%"';
}

if (isset($_POST['rjtSampleTestDate']) && trim((string) $_POST['rjtSampleTestDate']) != '') {
    $s_c_date = explode("to", (string) $_POST['rjtSampleTestDate']);
    //print_r($s_c_date);die;
    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $start_date = DateUtility::isoDateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $end_date = DateUtility::isoDateFormat(trim($s_c_date[1]));
    }
    if (trim((string) $start_date) == trim((string) $end_date)) {
        $sWhere[] = ' DATE(vl.sample_tested_datetime) = "' . $start_date . '"';
    } else {
        $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $start_date . '" AND DATE(vl.sample_tested_datetime) <= "' . $end_date . '"';
    }
}
if (isset($_POST['rjtSampleType']) && $_POST['rjtSampleType'] != '') {
    $sWhere[] =  ' s.sample_id = "' . $_POST['rjtSampleType'] . '"';
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
    $sWhere[] = ' vl.patient_gender = "' . $_POST['rjtGender'] . '"';
}
if (isset($_POST['rjtPatientPregnant']) && $_POST['rjtPatientPregnant'] != '') {
    $sWhere[] =  ' vl.is_patient_pregnant = "' . $_POST['rjtPatientPregnant'] . '"';
}
if (isset($_POST['rjtPatientBreastfeeding']) && $_POST['rjtPatientBreastfeeding'] != '') {
    $sWhere[] =  ' vl.is_patient_breastfeeding = "' . $_POST['rjtPatientBreastfeeding'] . '"';
}
if (isset($_POST['sampleRejectionReason']) && $_POST['sampleRejectionReason'] != '') {
    $sWhere[] =  ' vl.reason_for_sample_rejection = "' . $_POST['sampleRejectionReason'] . '"';
}

if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
    $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
}

if (!empty($sWhere)) {
    $sWhere = ' AND ' . implode(' AND ', $sWhere);
} else {
    $sWhere = "";
}
$sQuery = $sQuery . $sWhere;
$sQuery = $sQuery . ' group by vl.cd4_id';
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
    $patientName = $general->crypto('doNothing', $aRow['patient_first_name'], $aRow[$decrypt]);

    $row = [];
    $row[] = $aRow['sample_code'];
    if ($_SESSION['instance']['type'] != 'standalone') {
        $row[] = $aRow['remote_sample_code'];
    }
    if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
        $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
        $patientName = $general->crypto('decrypt', $patientName, $key);
    }
    $row[] = ($aRow['facility_name']);
    $row[] = $aRow['patient_art_no'];
    $row[] = $patientName;
    $row[] = $aRow['sample_collection_date'];
    $row[] = $aRow['labName'];
    $row[] = $aRow['rejection_reason_name'];
    $output['aaData'][] = $row;
}
echo json_encode($output);
