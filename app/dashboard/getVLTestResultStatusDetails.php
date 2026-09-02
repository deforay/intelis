<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Utilities\SampleCountUtility;


// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {

     /** @var CommonService $general */
     $general = ContainerRegistry::get(CommonService::class);
     $configQuery = "SELECT `value` FROM global_config where name ='vl_form'";
     $configResult = $db->query($configQuery);
     $tableName = "form_vl";
     $primaryKey = "vl_sample_id";


     $aColumns = ['vl.sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result'];
     $orderColumns = ['vl.sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result'];

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

     $sQuery = "SELECT * FROM form_vl as vl
                    INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                    LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.specimen_type
                    LEFT JOIN r_vl_art_regimen as art ON vl.current_regimen=art.art_id
                    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";


     if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) !== '') {
          $sWhere[] = ' b.batch_code LIKE "%' . $db->escapeLike($_POST['batchCode']) . '%"';
     }
     if (!empty($_POST['sampleCollectionDate'])) {
          [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
          $sWhere[] = " DATE(vl.sample_collection_date) BETWEEN '$start_date' AND '$end_date'";
     }
     if (isset($_POST['sampleType']) && $_POST['sampleType'] != '') {
          $sWhere[] = ' s.sample_id = ' . (int) $_POST['sampleType'];
     }
     if (isset($_POST['facilityName']) && $_POST['facilityName'] != '') {
          $sWhere[] = ' f.facility_id = ' . (int) $_POST['facilityName'];
     }

     if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
          $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
     }
     // Lab isolation (cloud-LIS): scope to this user's lab. No-op unless the session
     // carries a lab id, so byte-identical for every existing LIS/STS user.
     if ($labScope = $general->labScopeWhere('vl')) {
          $sWhere[] = $labScope;
     }

     // Keep $sWhere an array throughout: the old string fallback made the
     // countableWhere append below fatal on a filterless request.
     $sWhere[] = ' vl.result_status = ' . (int) $_POST['status'];

     // A cancelled sample was called off before testing, so it does not belong
     // in this list.
     $sWhere[] = SampleCountUtility::countableWhere('vl');
     $sQuery = "$sQuery WHERE " . implode(' AND ', $sWhere);

     $sQuery = "$sQuery ORDER BY vl.last_modified_datetime DESC";

     if (!empty($sOrder) && $sOrder !== '') {
          $sOrder = preg_replace('/\s+/', ' ', $sOrder);
          $sQuery = "$sQuery,$sOrder";
     }

     if (isset($sLimit) && isset($sOffset)) {
          $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
     }

     [$rResult, $resultCount] = $db->getDataAndCount($sQuery);

     $output = [
          "sEcho" => (int) $_POST['sEcho'],
          "iTotalRecords" => $resultCount,
          "iTotalDisplayRecords" => $resultCount,
          "aaData" => []
     ];


     foreach ($rResult as $aRow) {
          $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
          $patientFname = $aRow['patient_first_name'];
          $patientMname = $aRow['patient_middle_name'];
          $patientLname = $aRow['patient_last_name'];

          $row = [];
          $row[] = $aRow['sample_code'];
          $row[] = $aRow['sample_collection_date'];
          $row[] = $aRow['batch_code'];
          $row[] = $aRow['patient_art_no'];
          $row[] = "$patientFname $patientMname $patientLname";
          $row[] = $aRow['facility_name'];
          $row[] = $aRow['facility_state'];
          $row[] = $aRow['facility_district'];
          $row[] = $aRow['sample_name'];
          $row[] = $aRow['result'];
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
     throw $e;
}
