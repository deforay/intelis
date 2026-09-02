<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {

     /** @var CommonService $general */
     $general = ContainerRegistry::get(CommonService::class);
     $key = (string) $general->getGlobalConfig('key');

     $tableName = "form_covid19";
     $primaryKey = "covid19_id";


     $aColumns = ['vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'ts.status_name'];
     $orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'ts.status_name'];
     if ($general->isStandaloneInstance()) {
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




     $aWhere = '';
     $sQuery = "SELECT SQL_CALC_FOUND_ROWS * FROM form_covid19 as vl
               LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
               LEFT JOIN r_covid19_sample_type as s ON s.sample_id=vl.specimen_type
               INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
               LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

     [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');

     if (!empty($_POST['sampleCollectionDate'])) {
          if (trim((string) $start_date) === trim((string) $end_date)) {
               $sWhere[] = ' DATE(vl.sample_collection_date) like  "' . $start_date . '"';
          } else {
               $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
          }
     }
     if (isset($_POST['formField']) && trim((string) $_POST['formField']) !== '') {
          // Whitelist keeps request values out of the SQL as identifiers, and maps
          // each option to the column that actually holds it.
          $checkableFields = [
               'sample_code' => 'vl.sample_code',
               'sample_collection_date' => 'vl.sample_collection_date',
               'sample_batch_id' => 'vl.sample_batch_id',
               'patient_id' => 'vl.patient_id',
               'patient_name' => 'vl.patient_name',
               'result' => 'vl.result',
               'result_status' => 'vl.result_status',
          ];
          $sWhereSub = '';
          $searchArray = explode(",", (string) $_POST['formField']);
          foreach ($searchArray as $search) {
               if (!isset($checkableFields[$search])) {
                    continue;
               }
               $column = $checkableFields[$search];
               if ($sWhereSub === "") {
                    $sWhereSub .= "  ((";
               } else {
                    $sWhereSub .= " AND (";
               }
               if ($search === 'sample_collection_date') {
                    $sWhereSub .= $column . " IS NULL";
               } else {
                    $sWhereSub .= $column . " ='' OR " . $column . " IS NULL";
               }
               $sWhereSub .= ")";
          }
          if ($sWhereSub !== '') {
               $sWhereSub .= ")";
               $sWhere[] = $sWhereSub;
          }
     }



     //$dWhere = '';
     if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
          $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")  ";
          // $dWhere = $dWhere . " AND vl.facility_id IN (" . $userfacilityMapresult[0]['facility_id'] . ") ";
     }

     if ($labScope = $general->labScopeWhere('vl')) {
         $sWhere[] = $labScope;
     }

     $sWhere = $sWhere === [] ? "" : ' WHERE ' . implode(' AND ', $sWhere);

     $sQuery .= $sWhere;

     $_SESSION['vlIncompleteForm'] = $sQuery;
     if (!empty($sOrder) && $sOrder !== '') {
          $sOrder = preg_replace('/\s+/', ' ', $sOrder);
          $sQuery = $sQuery . ' ORDER BY ' . $sOrder;
     }

     if (isset($sLimit) && isset($sOffset)) {
          $sQuery = $sQuery . ' LIMIT ' . $sOffset . ',' . $sLimit;
     }

     $rResult = $db->rawQuery($sQuery);

     $aResultFilterTotal = $db->rawQueryOne("SELECT FOUND_ROWS() as `totalCount`");
     $iTotal = $iFilteredTotal = $aResultFilterTotal['totalCount'];
     $_SESSION['vlIncompleteFormCount'] = $iTotal;

     /*
      * Output
      */
     $output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $iTotal, "iTotalDisplayRecords" => $iFilteredTotal, "aaData" => []];

     foreach ($rResult as $aRow) {
          $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');

          $decrypt = $aRow['remote_sample'] == 'yes' ? 'remote_sample_code' : 'sample_code';

          $patientFname = ($general->crypto('doNothing', $aRow['patient_name'], $aRow[$decrypt]));
          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $patientFname = $general->crypto('decrypt', $patientFname, $key);
          }
          $row = [];
          $row[] = $aRow['sample_code'];
          if (!$general->isStandaloneInstance()) {
               $row[] = $aRow['remote_sample_code'];
          }
          $row[] = $aRow['sample_collection_date'];
          $row[] = $aRow['batch_code'];
          $row[] = ($patientFname);
          $row[] = ($aRow['facility_name']);
          $row[] = ($aRow['facility_state']);
          $row[] = ($aRow['facility_district']);
          $row[] = ($aRow['sample_name']);
          $row[] = $aRow['result'];
          $row[] = ($aRow['status_name']);
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
