<?php

use Psr\Http\Message\ServerRequestInterface;
use const COUNTRY\CAMEROON;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
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
     $formId = (int) $general->getGlobalConfig('vl_form');


     /** @var FacilitiesService $facilitiesService */
     $facilitiesService = ContainerRegistry::get(FacilitiesService::class);


     $sampleCode = 'sample_code';
     $aColumns = ['vl.sample_code', 'vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_art_no', "CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,''))", 'f.facility_name', 'testingLab.facility_name', 'vl.lab_assigned_code', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y')", 'ts.status_name'];
     $orderColumns = ['vl.sample_code', 'vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_art_no', "CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,''))", 'f.facility_name', 'testingLab.facility_name', 'vl.lab_assigned_code', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', "vl.last_modified_datetime", 'ts.status_name'];
     if (!empty($_POST['from']) && $_POST['from'] == "enterresult") {
          $aColumns = ['vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_art_no', "CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,''))", 'f.facility_name', 'testingLab.facility_name', 'vl.lab_assigned_code', 's.sample_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y')", 'ts.status_name'];
          $orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'b.batch_code', 'vl.patient_art_no', "CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,''))", 'f.facility_name', 'testingLab.facility_name', 'vl.lab_assigned_code', 's.sample_name', 'vl.result', "vl.last_modified_datetime", 'ts.status_name'];
     }
     if ($general->isSTSInstance()) {
          $sampleCode = 'remote_sample_code';
     } elseif ($general->isStandaloneInstance()) {
          $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
          $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
     }

     if ($formId != CAMEROON) {
          $aColumns = array_values(array_diff($aColumns, ['vl.lab_assigned_code']));
          $orderColumns = array_values(array_diff($orderColumns, ['vl.lab_assigned_code']));
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

     $sQuery = "SELECT vl.vl_sample_id,
     vl.sample_code,
     vl.remote_sample,
     vl.remote_sample_code,
     b.batch_code,
     vl.sample_collection_date,
     vl.sample_tested_datetime,
     vl.patient_art_no,
     vl.patient_first_name,
     vl.patient_middle_name,
     vl.patient_last_name,
     vl.lab_assigned_code,
     f.facility_name,
     f.facility_district,
     f.facility_state,
     testingLab.facility_name as lab_name,
     s.sample_name as sample_name,
     vl.result,
     vl.reason_for_vl_testing,
     vl.last_modified_datetime,
     vl.vl_test_platform,
     vl.result_status,
     vl.request_clinician_name,
     vl.requesting_phone,
     vl.patient_mobile_number,
     vl.result_value_log,
     vl.lab_technician,
     vl.patient_gender,
     vl.locked,
     vl.is_encrypted,
     ts.status_name,
     vl.result_approved_datetime,
     vl.result_reviewed_datetime,
     vl.sample_received_at_hub_datetime,
     vl.sample_received_at_lab_datetime,
     vl.result_dispatched_datetime,
     vl.result_printed_datetime,
     vl.result_approved_by
     FROM form_vl as vl
     LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
     LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
     LEFT JOIN facility_details as testingLab ON vl.lab_id=testingLab.facility_id
     LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.specimen_type
     INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status ";


     [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '', includeTime: true);
     [$t_start_date, $t_end_date] = DateUtility::convertDateRange($_POST['sampleTestDate'] ?? '', includeTime: true);
     [$r_start_date, $r_end_date] = DateUtility::convertDateRange($_POST['sampleReceivedDate'] ?? '', includeTime: true);

     if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) !== '') {
          $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
     }

     if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
          $sWhere[] = ' f.facility_district_id = "' . $_POST['district'] . '"';
     }
     if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
          $sWhere[] = ' f.facility_state_id = "' . $_POST['state'] . '"';
     }

     if (isset($_POST['patientId']) && $_POST['patientId'] != "") {
          $sWhere[] = ' vl.patient_art_no like "%' . $_POST['patientId'] . '%"';
     }
     if (isset($_POST['patientName']) && $_POST['patientName'] != "") {
          $sWhere[] = " CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,'')) like '%" . $_POST['patientName'] . "%'";
     }

     if (!empty($_POST['sampleCollectionDate'])) {
          $sWhere[] = " vl.sample_collection_date BETWEEN '$start_date' AND '$end_date'";
     }

     if (isset($_POST['sampleTestDate']) && trim((string) $_POST['sampleTestDate']) !== '') {
          $sWhere[] = " vl.sample_tested_datetime BETWEEN '$t_start_date' AND '$t_end_date'";
     }

     if (isset($_POST['sampleReceivedDate']) && trim((string) $_POST['sampleReceivedDate']) !== '') {
          $sWhere[] = " vl.sample_received_at_lab_datetime BETWEEN '$r_start_date' AND '$r_end_date'";
     }


     if (isset($_POST['sampleType']) && trim((string) $_POST['sampleType']) !== '') {
          $sWhere[] = ' s.sample_id = "' . $_POST['sampleType'] . '"';
     }
     if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
          $sWhere[] = ' f.facility_id IN (' . $_POST['facilityName'] . ')';
     }
     if (isset($_POST['vlLab']) && trim((string) $_POST['vlLab']) !== '') {
          $sWhere[] = ' vl.lab_id IN (' . $_POST['vlLab'] . ')';
     }

     if (isset($_POST['status']) && trim((string) $_POST['status']) !== '') {
          if ($_POST['status'] == 'no_result') {
               $statusCondition = '  (vl.result is NULL OR vl.result ="")  AND vl.result_status = ' . REJECTED;
          } elseif ($_POST['status'] == 'result') {
               $statusCondition = ' (vl.result is NOT NULL AND vl.result !=""  AND vl.result_status = ' . REJECTED;
          } else {
               $statusCondition = ' vl.result_status= ' . REJECTED;
          }
          $sWhere[] = $statusCondition;
     }
     if (isset($_POST['gender']) && trim((string) $_POST['gender']) !== '') {
          if (trim((string) $_POST['gender']) === "unreported") {
               $sWhere[] = ' (vl.patient_gender = "unreported" OR vl.patient_gender ="" OR vl.patient_gender IS NULL)';
          } else {
               $sWhere[] = ' vl.patient_gender ="' . $_POST['gender'] . '"';
          }
     }
     if (isset($_POST['fundingSource']) && trim((string) $_POST['fundingSource']) !== '') {
          $sWhere[] = ' vl.funding_source ="' . base64_decode((string) $_POST['fundingSource']) . '"';
     }
     if (isset($_POST['implementingPartner']) && trim((string) $_POST['implementingPartner']) !== '') {
          $sWhere[] = ' vl.implementing_partner ="' . base64_decode((string) $_POST['implementingPartner']) . '"';
     }

     // Only approved results can be printed
     if (!isset($_POST['status']) || trim((string) $_POST['status']) === '') {
          $baseCondition = "((vl.result_status = " . ACCEPTED . " AND vl.result IS NOT NULL AND vl.result != '') OR (vl.result_status = " . REJECTED . " AND (vl.result IS NULL OR vl.result = '')))";

          if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'print') {
               $sWhere[] = "$baseCondition AND result_printed_datetime IS NOT NULL";
          } else {
               $sWhere[] = "$baseCondition AND result_printed_datetime IS NULL";
          }
     } else {
          $sWhere[] = "vl.result_status != " . RECEIVED_AT_CLINIC;
     }

     if (!empty($_SESSION['facilityMap'])) {
          $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")";
     }

     if ($sWhere !== []) {
          $sQuery = $sQuery . ' WHERE' . implode(" AND ", $sWhere);
     }

     if (!empty($sOrder) && $sOrder !== '') {
          $sOrder = preg_replace('/\s+/', ' ', (string) $sOrder);
          $sQuery = "$sQuery ORDER BY $sOrder";
     }

     $_SESSION['vlResultQuery'] = $sQuery;

     if (isset($sLimit) && isset($sOffset)) {
          $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
     }

     [$rResult, $resultCount] = $db->getDataAndCount($sQuery);

     $_SESSION['vlResultQueryCount'] = $resultCount;


     $output = [
          "sEcho" => (int) $_POST['sEcho'],
          "iTotalRecords" => $resultCount,
          "iTotalDisplayRecords" => $resultCount,
          "aaData" => []
     ];
     foreach ($rResult as $aRow) {
          $row = [];
          if (isset($_POST['vlPrint'])) {
               if (isset($_POST['vlPrint']) && $_POST['vlPrint'] == 'not-print') {
                    $row[] = '<input type="checkbox" name="chk[]" class="checkRows" id="chk' . $aRow['vl_sample_id'] . '"  value="' . $aRow['vl_sample_id'] . '" onclick="checkedRow(this);"  />';
               } else {
                    $row[] = '<input type="checkbox" name="chkPrinted[]" class="checkPrintedRows" id="chkPrinted' . $aRow['vl_sample_id'] . '"  value="' . $aRow['vl_sample_id'] . '" onclick="checkedPrintedRow(this);"  />';
               }
               $print = '<a href="javascript:void(0);" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Print") . '" onclick="generateResultPDF(' . $aRow['vl_sample_id'] . ')"><em class="fa-solid fa-print"></em> ' . _translate("Print") . '</a>';
          } else {
               $print = '<a href="updateVlTestResult.php?id=' . MiscUtility::sqid((int) $aRow['vl_sample_id']) . '" class="btn btn-success btn-xs" style="margin-right: 2px;" title="' . _translate("Result") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Enter Result") . '</a>';
               if ($aRow['result_status'] == 7 && $aRow['locked'] == 'yes' && !_isAllowed("/vl/requests/edit-locked-vl-samples")) {
                    $print = '<a href="javascript:void(0);" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _translate("Locked") . '" disabled><em class="fa-solid fa-lock"></em>' . _translate("Locked") . '</a>';
               }
          }

          $patientFname = $aRow['patient_first_name'] ?? '';
          $patientMname = $aRow['patient_middle_name'] ?? '';
          $patientLname = $aRow['patient_last_name'] ?? '';

          $row[] = $aRow['sample_code'];
          if (!$general->isStandaloneInstance()) {
               $row[] = $aRow['remote_sample_code'];
          }
          $row[] = $aRow['batch_code'];
          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $aRow['patient_art_no'] = $general->crypto('decrypt', $aRow['patient_art_no'], $key);
               $patientFname = $general->crypto('decrypt', $patientFname, $key);
               $patientMname = $general->crypto('decrypt', $patientMname, $key);
               $patientLname = $general->crypto('decrypt', $patientLname, $key);
          }
          $row[] = $aRow['patient_art_no'];
          $row[] = "$patientFname $patientMname $patientLname";
          $row[] = $aRow['facility_name'];
          $row[] = $aRow['lab_name'];
          if ($formId == CAMEROON) {
               $row[] = $aRow['lab_assigned_code'];
          }

          if (empty($_POST['from']) || $_POST['from'] != "enterresult") {
               $row[] = $aRow['facility_state'];
               $row[] = $aRow['facility_district'];
          }
          $row[] = $aRow['sample_name'];
          $row[] = $aRow['result'];

          $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '', true);
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
          'last_db_query' => $db->getLastQuery()
     ]);
}
