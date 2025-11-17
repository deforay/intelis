<?php

use Laminas\Diactoros\ServerRequest;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\ACCEPTED;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\HepatitisService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
try {

     /** @var CommonService $general */
     $general = ContainerRegistry::get(CommonService::class);

     $key = (string) $general->getGlobalConfig('key');

     $tableName = "form_hepatitis";
     $primaryKey = "hepatitis_id";


     $sampleCode = 'sample_code';
     $aColumns = ['vl.sample_code', 'vl.external_sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_id', 'CONCAT(COALESCE(vl.patient_name,""), COALESCE(vl.patient_surname,""))', 'f.facility_name', 'vl.hcv_vl_count', 'vl.hbv_vl_count', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y')", 'ts.status_name'];
     $orderColumns = ['vl.sample_code', 'vl.external_sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_id', 'vl.patient_name', 'f.facility_name', 'vl.hcv_vl_count', 'vl.hbv_vl_count', 'vl.last_modified_datetime', 'ts.status_name'];
     if ($general->isSTSInstance()) {
          $sampleCode = 'remote_sample_code';
     } elseif ($general->isStandaloneInstance()) {
          $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
          $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
     }
     if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'print') {
          array_unshift($orderColumns, "vl.hepatitis_id");
     }
     /* Indexed column (used for fast and accurate table cardinality) */
     $sIndexColumn = $primaryKey;

     $sTable = $tableName;

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

     $sQuery = "SELECT vl.hepatitis_id,vl.sample_code, vl.external_sample_code,
          vl.remote_sample_code, vl.patient_id, vl.patient_name,
          vl.patient_surname,vl.patient_phone_number,vl.patient_gender,vl.is_sample_collected,vl.reason_for_hepatitis_test,
          vl.specimen_type,vl.hepatitis_test_platform,vl.result_status,vl.locked,vl.is_sample_rejected,vl.reason_for_sample_rejection,
          vl.result,vl.result_reviewed_datetime,vl.result_reviewed_by,vl.result_approved_datetime,vl.result_approved_by,
          vl.hcv_vl_count,vl.hbv_vl_count,vl.is_encrypted,
          vl.result_dispatched_datetime,vl.last_modified_datetime as lastModifiedDate,vl.last_modified_by ,b.*,ts.*,f.facility_name,
          l_f.facility_name as labName,
          l_f.facility_logo as facilityLogo,
          l_f.header_text as headerText,
          f.facility_code,
          f.facility_state,
          f.facility_district,
          u_d.user_name as reviewedBy,
          a_u_d.user_name as approvedBy
          FROM form_hepatitis as vl
          LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
          LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id
          INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
          LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
          LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by
          LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by";


     if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) !== '') {
          $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
     }
     if (!empty($_POST['sampleCollectionDate'])) {
          [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate']);
          $sWhere[] = " DATE(vl.sample_collection_date) BETWEEN '$start_date' AND '$end_date'";
     }

     if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
          $sWhere[] = ' f.facility_id IN (' . $_POST['facilityName'] . ')';
     }

     if (isset($_POST['vlLab']) && trim((string) $_POST['vlLab']) !== '') {
          $sWhere[] = ' vl.lab_id IN (' . $_POST['vlLab'] . ')';
     }

     if (isset($_POST['status']) && trim((string) $_POST['status']) !== '') {
          if ($_POST['status'] == 'no_result') {
               $statusCondition = ' ((vl.hcv_vl_count is NULL OR vl.hcv_vl_count  = "" OR vl.hbv_vl_count is NULL OR vl.hbv_vl_count  = "") AND vl.result_status = ' . RECEIVED_AT_TESTING_LAB . ')';
          } elseif ($_POST['status'] == 'result') {
               $statusCondition = ' vl.hcv_vl_count is NOT NULL OR vl.hcv_vl_count  != "" OR vl.hbv_vl_count is NOT NULL OR vl.hbv_vl_count  != "" ';
          } else {
               $statusCondition = ' vl.is_sample_rejected = "yes" AND vl.result_status = ' . REJECTED;
          }
          $sWhere[] = $statusCondition;
     } else {
          $sWhere[] = " ((vl.result_status = " . ACCEPTED . " AND (vl.hcv_vl_count is NULL AND vl.hcv_vl_count  ='' AND vl.hbv_vl_count is NULL AND vl.hbv_vl_count  ='')) OR (vl.result_status = " . REJECTED . " AND (vl.hcv_vl_count is NULL AND vl.hcv_vl_count  ='' AND vl.hbv_vl_count is NULL AND vl.hbv_vl_count  =''))) AND (result_printed_datetime is NULL OR DATE(result_printed_datetime) = '0000-00-00')";
     }

     if (isset($_POST['fundingSource']) && trim((string) $_POST['fundingSource']) !== '') {
          $sWhere[] = '  vl.funding_source ="' . base64_decode((string) $_POST['fundingSource']) . '"';
     }
     if (isset($_POST['implementingPartner']) && trim((string) $_POST['implementingPartner']) !== '') {
          $sWhere[] = '  vl.implementing_partner ="' . base64_decode((string) $_POST['implementingPartner']) . '"';
     }

     if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
          $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")  ";
     }

     if ($sWhere !== []) {
          $sWhere = implode(' AND ', $sWhere);
          $sQuery = "$sQuery WHERE $sWhere";
     }

     if (!empty($sOrder) && $sOrder !== '') {
          $sOrder = preg_replace('/\s+/', ' ', (string) $sOrder);
          $sQuery = "$sQuery ORDER BY $sOrder";
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

     /** @var HepatitisService $hepatitisService */
     $hepatitisService = ContainerRegistry::get(HepatitisService::class);
     $hepatitisResults = $hepatitisService->getHepatitisResults();
     foreach ($rResult as $aRow) {
          $row = [];
          $print = '<a href="/hepatitis/results/hepatitis-update-result.php?id=' . MiscUtility::sqid($aRow['hepatitis_id']) . '" class="btn btn-success btn-xs" style="margin-right: 2px;" title="' . _translate("Result") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Enter Result") . '</a>';
          if ($aRow['result_status'] == 7 && $aRow['locked'] == 'yes' && !_isAllowed("/hepatitis/requests/edit-locked-hepatitis-samples")) {
               $print = '<a href="javascript:void(0);" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _translate("Locked") . '" disabled><em class="fa-solid fa-lock"></em> ' . _translate("Locked") . '</a>';
          }

          $row[] = $aRow['sample_code'] . (empty($aRow['external_sample_code']) ? '' : "<br>/" . $aRow['external_sample_code']);
          if (!$general->isStandaloneInstance()) {
               $row[] = $aRow['remote_sample_code'];
          }
          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
               $aRow['patient_name'] = $general->crypto('decrypt', $aRow['patient_name'], $key);
               $aRow['patient_surname'] = $general->crypto('decrypt', $aRow['patient_surname'], $key);
          }
          $row[] = $aRow['batch_code'];
          $row[] = $aRow['facility_name'];
          $row[] = $aRow['patient_id'];
          $row[] = $aRow['patient_name'] . " " . $aRow['patient_surname'];
          $row[] = $aRow['hcv_vl_count'];
          $row[] = $aRow['hbv_vl_count'];

          if (isset($aRow['lastModifiedDate']) && trim((string) $aRow['lastModifiedDate']) !== '' && $aRow['lastModifiedDate'] != '0000-00-00 00:00:00') {
               $aRow['last_modified_datetime'] = DateUtility::humanReadableDateFormat($aRow['lastModifiedDate'], true);
          } else {
               $aRow['last_modified_datetime'] = '';
          }

          $row[] = $aRow['last_modified_datetime'];
          $row[] = $aRow['status_name'];
          $row[] = $print;
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
}
