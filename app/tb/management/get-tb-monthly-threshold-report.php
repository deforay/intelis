<?php

use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use App\Registries\AppRegistry;
use App\Utilities\SampleCountUtility;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$tableName = "form_tb";
$primaryKey = "tb_id";

$aColumns = ['vl.sample_tested_datetime', 'f.facility_name'];
$orderColumns = ['vl.sample_tested_datetime', 'f.facility_name'];

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

// The output loop below compares monthly_target against per-facility-per-month
// totals, so the SELECT must aggregate; without the SUM columns and GROUP BY
// every row carried null totals and the report showed nothing meaningful.
$sQuery = "SELECT
          DATE_FORMAT(DATE(vl.sample_tested_datetime), '%b-%Y') as monthrange,
          f.facility_id,
          f.facility_name,
          hf.monthly_target,
          SUM(CASE WHEN (vl.is_sample_rejected IS NOT NULL AND vl.is_sample_rejected LIKE 'yes%') THEN 1 ELSE 0 END) as totalRejected,
          SUM(CASE WHEN (vl.sample_tested_datetime IS NULL AND vl.sample_collection_date IS NOT NULL) THEN 1 ELSE 0 END) as totalReceived,
          SUM(CASE WHEN (vl.sample_collection_date IS NOT NULL) THEN 1 ELSE 0 END) as totalCollected
          FROM testing_labs as hf INNER JOIN form_tb as vl ON vl.lab_id=hf.facility_id LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id  ";

[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
$sTestDate = '';
$eTestDate = '';
if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) !== '') {
     $s_t_date = explode("to", (string) $_POST['sampleTestDate']);
     if (isset($s_t_date[0]) && trim($s_t_date[0]) !== "") {
          $sTestDate = DateUtility::isoDateFormat(trim($s_t_date[0]));
     }
     if (isset($s_t_date[1]) && trim($s_t_date[1]) !== "") {
          $eTestDate = DateUtility::isoDateFormat(trim($s_t_date[1]));
     }
}

if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) !== '') {
     if (trim((string) $sTestDate) === trim((string) $eTestDate)) {
          $sWhere[] = ' DATE(vl.sample_tested_datetime) = "' . $sTestDate . '"';
     } else {
          $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $sTestDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $eTestDate . '"';
     }
}

if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
     $sWhere[] = ' vl.lab_id IN (' . $db->inIntList($_POST['facilityName']) . ')';
}

$sWhere[] = '  vl.result_status != ' . RECEIVED_AT_CLINIC;

// A cancelled sample was called off before testing, so it belongs in none of
// the totals this report compares against the monthly target.
$sWhere[] = SampleCountUtility::countableWhere('vl');

if (!empty($_SESSION['facilityMap'])) {
     $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
}

if ($labScope = $general->labScopeWhere('vl')) {
    $sWhere[] = $labScope;
}
$sWhere[] = " hf.test_type = 'tb'";
$sQuery = $sQuery . ' WHERE ' . implode(' AND ', $sWhere);
$sQuery .= ' GROUP BY f.facility_id, YEAR(vl.sample_tested_datetime), MONTH(vl.sample_tested_datetime)';
$_SESSION['tbMonitoringThresholdReportQuery'] = $sQuery;
$rResult = $db->rawQuery($sQuery);


$output = ["sEcho" => (int) $_POST['sEcho'], "aaData" => []];

$cnt = 0;
foreach ($rResult as $rowData) {
     $targetType1 = false;
     $targetType2 = false;
     $targetType3 = false;
     if (($_POST['targetType'] ?? '') == 1) {
         if ($rowData['monthly_target'] > $rowData['totalCollected']) {
              $targetType1 = true;
         }
     } elseif (($_POST['targetType'] ?? '') == 2) {
         if ($rowData['monthly_target'] < $rowData['totalCollected']) {
              $targetType2 = true;
         }
     } elseif (($_POST['targetType'] ?? '') == 3) {
         $targetType3 = true;
     }
     if ($targetType1 || $targetType2 || $targetType3) {
          $cnt++;
          $data = [];
          $data[] = ($rowData['facility_name']);
          $data[] = $rowData['monthrange'];
          $data[] = $rowData['totalReceived'];
          $data[] = $rowData['totalRejected'];
          $data[] = $rowData['totalCollected'];
          $data[] = $rowData['monthly_target'];
          $output['aaData'][] = $data;
     }
}
$output['iTotalDisplayRecords'] = $cnt;
$output['iTotalRecords'] = $cnt;
echo json_encode($output);
