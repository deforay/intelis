<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Utilities\SampleCountUtility;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;


// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$key = (string) $general->getGlobalConfig('key');

$tableName = "form_hepatitis";
$primaryKey = "hepatitis_id";

$sampleCode = 'sample_code';
$aColumns = ['vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_name', 'vl.patient_id', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", "DATE_FORMAT(vl.sample_tested_datetime,'%d-%b-%Y')", 'fd.facility_name', 'vl.hcv_vl_count', 'vl.hbv_vl_count'];
$orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'f.facility_name', 'vl.patient_id', 'vl.patient_name', 'vl.sample_collection_date', 'vl.sample_tested_datetime', 'fd.facility_name', 'vl.hcv_vl_count', 'vl.hbv_vl_count'];
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
    $sOffset = (int) $_POST['iDisplayStart'];
    $sLimit = (int) $_POST['iDisplayLength'];
}


$sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);

$sWhere = [];
$columnSearch = $general->multipleColumnSearch($_POST['sSearch'] ?? null, $aColumns);
if (!empty($columnSearch)) {
    $sWhere[] = $columnSearch;
}


$sQuery = "SELECT vl.*,f.facility_name,s.sample_id,b.batch_code,fd.facility_name as labName
FROM form_hepatitis as vl LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN facility_details as fd ON fd.facility_id=vl.lab_id LEFT JOIN r_hepatitis_sample_type as s ON s.sample_id=vl.specimen_type LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id where vl.result_status=7
    -- Parenthesised. AND binds tighter than OR, so this read as
    -- (status=7 AND hcv='positive') OR (hbv='positive'), and the hbv branch
    -- carried no status filter at all.
    AND (vl.hcv_vl_count = 'positive' OR vl.hbv_vl_count = 'positive')
    AND " . SampleCountUtility::countableWhere('vl') . "";
$start_date = '';
$end_date = '';

if (isset($_POST['hvlBatchCode']) && trim((string) $_POST['hvlBatchCode']) !== '') {
    // The filter is fed by a dropdown of exact batch codes; LIKE made
    // batch "B1" also match "B12".
    $sWhere[] =  ' b.batch_code = "' . $db->escape((string) $_POST['hvlBatchCode']) . '"';
}

if (isset($_POST['hvlSampleTestDate']) && trim((string) $_POST['hvlSampleTestDate']) !== '') {
    $s_c_date = explode("to", (string) $_POST['hvlSampleTestDate']);

    if (isset($s_c_date[0]) && trim($s_c_date[0]) !== "") {
        $start_date = DateUtility::isoDateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) !== "") {
        $end_date = DateUtility::isoDateFormat(trim($s_c_date[1]));
    }
    if (trim((string) $start_date) === trim((string) $end_date)) {
        $sWhere[] = '  DATE(vl.sample_tested_datetime) = "' . $start_date . '"';
    } else {
        $sWhere[] = '  DATE(vl.sample_tested_datetime) >= "' . $start_date . '" AND DATE(vl.sample_tested_datetime) <= "' . $end_date . '"';
    }
}
if (isset($_POST['hvlSampleType']) && $_POST['hvlSampleType'] != '') {
    $sWhere[] = ' vl.specimen_type = ' . (int) $_POST['hvlSampleType'];
}
if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
    $sWhere[] = ' f.facility_state_id = ' . (int) $_POST['state'] . ' ';
}
if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
    $sWhere[] = ' f.facility_district_id = ' . (int) $_POST['district'] . ' ';
}
if (isset($_POST['hvlFacilityName']) && $_POST['hvlFacilityName'] != '') {
    $sWhere[] =  ' f.facility_id IN (' . $db->inIntList($_POST['hvlFacilityName']) . ')';
}
if (isset($_POST['hvlGender']) && $_POST['hvlGender'] != '') {
    if (trim((string) $_POST['hvlGender']) === "unreported") {
        $sWhere[] =  ' (vl.patient_gender = "unreported" OR vl.patient_gender ="" OR vl.patient_gender IS NULL)';
    } else {
        $sWhere[] =  ' (vl.patient_gender IS NOT NULL AND vl.patient_gender ="' . $db->escape((string) $_POST['hvlGender']) . '") ';
    }
}
if (isset($_POST['hvlPatientPregnant']) && $_POST['hvlPatientPregnant'] != '') {
    $sWhere[] =  ' vl.is_patient_pregnant = "' . $db->escape((string) $_POST['hvlPatientPregnant']) . '"';
}
if (isset($_POST['hvlPatientBreastfeeding']) && $_POST['hvlPatientBreastfeeding'] != '') {
    $sWhere[] = ' vl.is_patient_breastfeeding = "' . $db->escape((string) $_POST['hvlPatientBreastfeeding']) . '"';
}
if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
    $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
}

if ($labScope = $general->labScopeWhere('vl')) {
    $sWhere[] = $labScope;
}

$sWhere = $sWhere === [] ? "" : ' AND ' . implode(' AND ', $sWhere);
$sQuery .= $sWhere;

$sQuery .= ' group by vl.hepatitis_id';
if (!empty($sOrder) && $sOrder !== '') {
    $sOrder = preg_replace('/\s+/', ' ', $sOrder);
    $sQuery = "$sQuery ORDER BY $sOrder";
}
$_SESSION['highViralResult'] = $sQuery;
if (isset($sLimit) && isset($sOffset)) {
    $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
}

[$rResult, $resultCount] = $db->getDataAndCount($sQuery);


$_SESSION['highViralResultCount'] = $resultCount;

$output = [
    "sEcho" => (int) $_POST['sEcho'],
    "iTotalRecords" => $resultCount,
    "iTotalDisplayRecords" => $resultCount,
    "aaData" => []
];

foreach ($rResult as $aRow) {
    if (isset($aRow['sample_collection_date']) && trim((string) $aRow['sample_collection_date']) !== '' && $aRow['sample_collection_date'] != '0000-00-00 00:00:00') {
        $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
    } else {
        $aRow['sample_collection_date'] = '';
    }
    if (isset($aRow['sample_tested_datetime']) && trim((string) $aRow['sample_tested_datetime']) !== '' && $aRow['sample_tested_datetime'] != '0000-00-00 00:00:00') {
        $aRow['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime']);
    } else {
        $aRow['sample_tested_datetime'] = '';
    }
    $decrypt = $aRow['remote_sample'] == 'yes' ? 'remote_sample_code' : 'sample_code';
    $patientName = $general->crypto('doNothing', $aRow['patient_name'], $aRow[$decrypt]);
    $row = [];
    $row[] = $aRow['sample_code'];
    if (!$general->isStandaloneInstance()) {
        $row[] = $aRow['remote_sample_code'];
    }
    if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
        $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
        $patientName = $general->crypto('decrypt', $patientName, $key);
    }
    $row[] = $aRow['facility_name'];
    $row[] = $aRow['patient_id'];
    $row[] = $patientName;
    $row[] = $aRow['sample_collection_date'];
    $row[] = $aRow['sample_tested_datetime'];
    $row[] = $aRow['labName'];
    $row[] = $aRow['hcv_vl_count'];
    $row[] = $aRow['hbv_vl_count'];
    $row[] = '';
    $output['aaData'][] = $row;
}
echo json_encode($output);
