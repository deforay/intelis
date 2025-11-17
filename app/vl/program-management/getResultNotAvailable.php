<?php

use const SAMPLE_STATUS\REJECTED;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$key = (string) $general->getGlobalConfig('key');

$aColumns = ['vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_art_no', 'vl.patient_first_name', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'fd.facility_name'];
$orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_art_no', 'vl.patient_first_name', 'vl.sample_collection_date', 'fd.facility_name'];
if ($general->isStandaloneInstance()) {
    $aColumns = MiscUtility::removeMatchingElements($aColumns, ['vl.remote_sample_code']);
    $orderColumns = MiscUtility::removeMatchingElements($orderColumns, ['vl.remote_sample_code']);
}

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

$sQuery = "SELECT vl.*,
            f.*,s.*,fd.facility_name as labName,
            ts.status_name
            FROM form_vl as vl
            LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
            LEFT JOIN facility_details as fd ON fd.facility_id=vl.lab_id
            LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.specimen_type
            LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
            LEFT JOIN r_vl_art_regimen as art ON vl.current_regimen=art.art_id
            INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
            WHERE vl.result_status != " . REJECTED . "
            AND vl.sample_code is NOT NULL
            AND (vl.result IS NULL OR vl.result='')";

if (isset($_POST['noResultBatchCode']) && trim((string) $_POST['noResultBatchCode']) !== '') {
    $sWhere[] = ' b.batch_code LIKE "%' . $_POST['noResultBatchCode'] . '%"';
}

if (isset($_POST['noResultSampleTestDate']) && trim((string) $_POST['noResultSampleTestDate']) !== '') {
    [$start_date, $end_date] = DateUtility::convertDateRange($_POST['noResultSampleTestDate'] ?? '');
    $sWhere[] = " DATE(vl.sample_collection_date) BETWEEN '$start_date' AND '$end_date'";
}
if (isset($_POST['noResultSampleType']) && $_POST['noResultSampleType'] != '') {
    $sWhere[] = ' s.sample_id = "' . $_POST['noResultSampleType'] . '"';
}
if (isset($_POST['noResultState']) && trim((string) $_POST['noResultState']) !== '') {
    $sWhere[] = " f.facility_state_id = '" . $_POST['noResultState'] . "' ";
}
if (isset($_POST['noResultDistrict']) && trim((string) $_POST['noResultDistrict']) !== '') {
    $sWhere[] = " f.facility_district_id = '" . $_POST['noResultDistrict'] . "' ";
}
if (isset($_POST['noResultFacilityName']) && $_POST['noResultFacilityName'] != '') {
    $sWhere[] = ' f.facility_id IN (' . $_POST['noResultFacilityName'] . ')';
}
if (isset($_POST['noResultGender']) && $_POST['noResultGender'] != '') {
    if (trim((string) $_POST['noResultGender']) === "unreported") {
        $sWhere[] = ' (vl.patient_gender = "unreported" OR vl.patient_gender ="" OR vl.patient_gender IS NULL)';
    } else {
        $sWhere[] = ' vl.patient_gender ="' . $_POST['noResultGender'] . '"';
    }
}
if (isset($_POST['noResultPatientPregnant']) && $_POST['noResultPatientPregnant'] != '') {
    $sWhere[] = ' vl.is_patient_pregnant = "' . $_POST['noResultPatientPregnant'] . '"';
}
if (isset($_POST['noResultPatientBreastfeeding']) && $_POST['noResultPatientBreastfeeding'] != '') {
    $sWhere[] = ' vl.is_patient_breastfeeding = "' . $_POST['noResultPatientBreastfeeding'] . '"';
}
if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
    $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
}
if (!empty($sWhere)) {
    $sWhere = implode(" AND ", $sWhere);
}
$sQuery = $sQuery . ' AND ' . $sWhere;
$sQuery .= ' group by vl.vl_sample_id';
if (!empty($sOrder) && $sOrder !== '') {
    $sOrder = preg_replace('/\s+/', ' ', (string) $sOrder);
    $sQuery = "$sQuery ORDER BY $sOrder";
}
$_SESSION['resultNotAvailable'] = $sQuery;

if (isset($sLimit) && isset($sOffset)) {
    $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
}


[$rResult, $resultCount] = $db->getDataAndCount($sQuery);
$_SESSION['resultNotAvailableCount'] = $resultCount;


$output = [
    "sEcho" => (int) $_POST['sEcho'],
    "iTotalRecords" => $resultCount,
    "iTotalDisplayRecords" => $resultCount,
    "aaData" => []
];

foreach ($rResult as $aRow) {

    $decrypt = $aRow['remote_sample'] == 'yes' ? 'remote_sample_code' : 'sample_code';
    $patientFname = $aRow['patient_first_name'] ?? '';
    $patientMname = $aRow['patient_middle_name'] ?? '';
    $patientLname = $aRow['patient_last_name'] ?? '';
    $row = [];

    $row[] = $aRow['sample_code'];
    if (!$general->isStandaloneInstance()) {
        $row[] = $aRow['remote_sample_code'];
    }
    if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
        $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
        $patientFname = $general->crypto('decrypt', $patientFname, $key);
        $patientMname = $general->crypto('decrypt', $patientMname, $key);
        $patientLname = $general->crypto('decrypt', $patientLname, $key);
    }
    $row[] = $aRow['facility_name'];
    $row[] = $aRow['patient_art_no'];
    $row[] = trim("$patientFname $patientMname $patientLname");
    $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
    $row[] = $aRow['labName'];
    $row[] = $aRow['status_name'];
    $output['aaData'][] = $row;
}
echo json_encode($output);
