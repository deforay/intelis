<?php

use App\Services\TbService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\REFERRED;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use Psr\Http\Message\ServerRequestInterface;


// Sanitized values from $request object

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {

     /** @var CommonService $general */
     $general = ContainerRegistry::get(CommonService::class);

     /** @var TestRequestsService $testRequestsService */
     $testRequestsService = ContainerRegistry::get(TestRequestsService::class);
     $testRequestsService->processSampleCodeQueue();

     /** @var TbService $tbService */
     $tbService = ContainerRegistry::get(TbService::class);
     $tbResults = $tbService->getTbResults() ?? [];

     // Global config
     $gconfig = $general->getGlobalConfig();
     $key = (string) $general->getGlobalConfig('key');

     //system config
     $sarr = $general->getSystemConfig();
     $tableName = "form_tb";
     $primaryKey = "tb_id";

     $sampleCode = 'sample_code';

     $aColumns = ['vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'l.facility_name', 'f.facility_name', 'vl.patient_id', 'CONCAT(COALESCE(vl.patient_name,""), COALESCE(vl.patient_surname,""))', 'f.facility_state', 'f.facility_district', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name'];
     $orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'l.facility_name', 'f.facility_name', 'vl.patient_id', 'vl.patient_name', 'f.facility_state', 'f.facility_district', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name'];

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
          $sOffset = $_POST['iDisplayStart'];
          $sLimit = $_POST['iDisplayLength'];
     }



     $sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);



     $sWhere = [];
     if (isset($_POST['sSearch']) && $_POST['sSearch'] != "") {
          $searchArray = explode(" ", (string) $_POST['sSearch']);
          $sWhereSub = "";
          foreach ($searchArray as $search) {
               if ($sWhereSub === "") {
                    $sWhereSub .= " (";
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


     $sQuery = '';

     $sQuery = "SELECT vl.*, f.*, l.facility_name as lab_name, rtbr.result as lamResult, ts.status_name, b.batch_code FROM form_tb as vl
          LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
          LEFT JOIN facility_details as l ON vl.lab_id=l.facility_id
          LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
          LEFT JOIN r_tb_results as rtbr ON rtbr.result_id=vl.result
          LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

     [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
     [$labStartDate, $labEndDate] = DateUtility::convertDateRange($_POST['sampleReceivedDateAtLab'] ?? '');
     [$testedStartDate, $testedEndDate] = DateUtility::convertDateRange($_POST['sampleTestedDate'] ?? '');

     if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) !== '') {
          $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
     }
     if (!empty($_POST['sampleCollectionDate'])) {
          if (trim((string) $start_date) === trim((string) $end_date)) {
               $sWhere[] = ' DATE(vl.sample_collection_date) like  "' . $start_date . '"';
          } else {
               $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
          }
     }
     if (isset($_POST['sampleReceivedDateAtLab']) && trim((string) $_POST['sampleReceivedDateAtLab']) !== '') {
          if (trim((string) $labStartDate) === trim((string) $labEndDate)) {
               $sWhere[] = ' DATE(vl.sample_received_at_lab_datetime) = "' . $labStartDate . '"';
          } else {
               $sWhere[] = " DATE(vl.sample_received_at_lab_datetime) BETWEEN '$labStartDate' AND '$labEndDate'";
          }
     }

     if (isset($_POST['sampleTestedDate']) && trim((string) $_POST['sampleTestedDate']) !== '') {
          if (trim((string) $testedStartDate) === trim((string) $testedEndDate)) {
               $sWhere[] = ' DATE(vl.sample_tested_datetime) = "' . $testedStartDate . '"';
          } else {
               $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $testedStartDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $testedEndDate . '"';
          }
     }

     if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) !== '') {
          $sWhere[] = ' f.facility_id IN (' . $_POST['facilityName'] . ')';
     }
     if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
          $sWhere[] = " f.facility_district_id = '" . $_POST['district'] . "' ";
     }
     if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
          $sWhere[] = " f.facility_state_id = '" . $_POST['state'] . "' ";
     }
     if (isset($_POST['vlLab']) && trim((string) $_POST['vlLab']) !== '') {
          $sWhere[] = ' vl.lab_id IN (' . $_POST['vlLab'] . ')';
     }
     if (isset($_POST['gender']) && trim((string) $_POST['gender']) !== '') {
          if (trim((string) $_POST['gender']) === "unreported") {
               $sWhere[] = ' vl.patient_gender="unreported" OR vl.patient_gender="" OR vl.patient_gender IS NULL';
          } else {
               $sWhere[] = ' vl.patient_gender IN ("' . $_POST['gender'] . '")';
          }
     }
     /* Sample status filter */
     if (isset($_POST['status']) && trim((string) $_POST['status']) !== '') {
          $sWhere[] = '  (vl.result_status IS NOT NULL AND vl.result_status =' . $_POST['status'] . ')';
     }
     if (isset($_POST['srcStatus']) && $_POST['srcStatus'] == 4) {
          $sWhere[] = ' vl.is_sample_rejected is not null AND vl.is_sample_rejected like "yes"';
     }
     if (isset($_POST['srcStatus']) && $_POST['srcStatus'] == 6) {
          $sWhere[] = " vl.sample_received_at_lab_datetime is NOT NULL ";
     }
     if (isset($_POST['srcStatus']) && $_POST['srcStatus'] == 7) {
          $sWhere[] = ' vl.result is not null AND vl.result not like "" AND result_status = ' . ACCEPTED;
     }
     if (isset($_POST['srcStatus']) && $_POST['srcStatus'] == "sent") {
          $sWhere[] = ' vl.result_sent_to_source is not null and vl.result_sent_to_source = "sent"';
     }

     if (isset($_POST['showReordSample']) && trim((string) $_POST['showReordSample']) !== '') {
          $sWhere[] = ' vl.sample_reordered IN ("' . $_POST['showReordSample'] . '")';
     }
     if (isset($_POST['fundingSource']) && trim((string) $_POST['fundingSource']) !== '') {
          $sWhere[] = ' vl.funding_source IN ("' . base64_decode((string) $_POST['fundingSource']) . '")';
     }
     if (isset($_POST['implementingPartner']) && trim((string) $_POST['implementingPartner']) !== '') {
          $sWhere[] = ' vl.implementing_partner IN ("' . base64_decode((string) $_POST['implementingPartner']) . '")';
     }
     if (isset($_POST['srcOfReq']) && trim((string) $_POST['srcOfReq']) !== '') {
          $sWhere[] = ' vl.source_of_request like "' . $_POST['srcOfReq'] . '"';
     }
     if (isset($_POST['reqSampleType']) && trim((string) $_POST['reqSampleType']) === 'result') {
          $sWhere[] = ' vl.result != "" ';
     } elseif (isset($_POST['reqSampleType']) && trim((string) $_POST['reqSampleType']) === 'noresult') {
          $sWhere[] = ' (vl.result IS NULL OR vl.result = "") ';
     }
     /* Source of request show model conditions */
     if (isset($_POST['dateRangeModel']) && trim((string) $_POST['dateRangeModel']) !== '') {
          $sWhere[] = ' DATE(vl.sample_collection_date) like "' . DateUtility::isoDateFormat($_POST['dateRangeModel']) . '"';
     }
     if (isset($_POST['srcOfReqModel']) && trim((string) $_POST['srcOfReqModel']) !== '') {
          $sWhere[] = ' vl.source_of_request like "' . $_POST['srcOfReqModel'] . '" ';
     }
     if (isset($_POST['labIdModel']) && trim((string) $_POST['labIdModel']) !== '') {
          $sWhere[] = ' vl.lab_id like "' . $_POST['labIdModel'] . '" ';
     }
     if (isset($_POST['patientId']) && trim((string) $_POST['patientId']) !== '') {
          $sWhere[] = " vl.patient_id LIKE '%" . $_POST['patientId'] . "%' ";
     }
     if (isset($_POST['patientName']) && $_POST['patientName'] != "") {
          $sWhere[] = " CONCAT(COALESCE(vl.patient_name,''), COALESCE(vl.patient_surname,'')) like '%" . $_POST['patientName'] . "%'";
     }
     if (isset($_POST['rejectedSamples']) && $_POST['rejectedSamples'] != "") {
          $sWhere[] = " IFNULL(vl.is_sample_rejected, 'no') not like 'yes' ";
     }
     if ($general->isSTSInstance()) {
          if (!empty($_SESSION['facilityMap'])) {
               $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
          }
     } elseif (!$_POST['hidesrcofreq']) {
          $sWhere[] = 'vl.result_status != ' . RECEIVED_AT_CLINIC;
          $sWhere[] = 'vl.result_status != ' . REFERRED;
     }
     if ($sWhere !== []) {
          $_SESSION['tbRequestData']['sWhere'] = $sWhere = implode(" AND ", $sWhere);
          $sQuery = "$sQuery WHERE $sWhere";
     }

     if (!empty($sOrder) && $sOrder !== '') {
          $_SESSION['tbRequestData']['sOrder'] = $sOrder = preg_replace('/\s+/', ' ', (string) $sOrder);
          $sQuery = "$sQuery ORDER BY $sOrder";
     }
     $_SESSION['tbRequestSearchResultQuery'] = $sQuery;

     if (isset($sLimit) && isset($sOffset)) {
          $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
     }

     [$rResult, $resultCount] = $db->getDataAndCount($sQuery);

     $_SESSION['tbRequestSearchResultQueryCount'] = $resultCount;


     $output = ["sEcho" => (int) $_POST['sEcho'], "iTotalRecords" => $resultCount, "iTotalDisplayRecords" => $resultCount, "aaData" => []];
     $editRequest = false;
     $syncRequest = false;
     if ((_isAllowed("/tb/requests/tb-edit-request.php"))) {
          $editRequest = true;
          $syncRequest = true;
     }

     $sampleCodeColumn = $general->isSTSInstance() ? 'remote_sample_code' : 'sample_code';

     foreach ($rResult as $aRow) {
          $vlResult = '';
          $edit = '';
          $view = '';
          $sync = '';
          $barcode = '';
          $aRow['sample_collection_date'] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
          $aRow['last_modified_datetime'] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '');

          if (empty($aRow[$sampleCodeColumn]) && empty($aRow['sample_code'])) {
               $aRow[$sampleCodeColumn] = _translate("Generating...");
          }

          $row = [];
          $sampleCodeTooltip = '';
          $patientTooltip = '';
          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
               $aRow['patient_name'] = $general->crypto('decrypt', $aRow['patient_name'], $key);
               $aRow['patient_surname'] = $general->crypto('decrypt', $aRow['patient_surname'], $key);
          }

          if (!empty($aRow['sample_package_code'])) {
               $sampleCodeTooltip .= _translate("Manifest Code", true) . " : " . $aRow['sample_package_code'] . '<br>';
          }
          if (!empty($aRow['batch_code'])) {
               $sampleCodeTooltip .= _translate("Batch Code", true) . " : " . $aRow['batch_code'];
          }
          if (!empty($aRow['patient_dob'])) {
               $patientTooltip .= _translate("Patient DoB", true) . " : " . DateUtility::humanReadableDateFormat($aRow['patient_dob']) . '<br>';
          }
          if (!empty($aRow['patient_age'])) {
               $patientTooltip .= _translate("Patient Age", true) . " : " . $aRow['patient_age'] . '<br>';
          }
          if (!empty($aRow['patient_gender'])) {
               $patientTooltip .= _translate("Patient Sex", true) . " : " . $aRow['patient_gender'] . '<br>';
          }

          if ($sampleCodeTooltip !== '' && $sampleCodeTooltip !== '0') {
               $row[] = '<span class="top-tooltip" title="' . $sampleCodeTooltip . '">' . $aRow['sample_code'] . '</span>';
          } else {
               $row[] = '<span>' . $aRow['sample_code'] . '</span>';
          }
          if (!$general->isStandaloneInstance()) {
               if ($sampleCodeTooltip !== '' && $sampleCodeTooltip !== '0') {
                    $row[] = '<span class="top-tooltip" title="' . $sampleCodeTooltip . '">' . $aRow['remote_sample_code'] . '</span>';
               } else {
                    $row[] = '<span>' . $aRow['remote_sample_code'] . '</span>';
               }
          }

          $row[] = $aRow['sample_collection_date'];
          $row[] = $aRow['batch_code'];
          $row[] = ($aRow['lab_name']);
          $row[] = ($aRow['facility_name']);
          if ($patientTooltip !== '' && $patientTooltip !== '0') {
               $row[] = '<span class="top-tooltip" title="' . $patientTooltip . '">' . $aRow['patient_id'] . '</span>';
          } else {
               $row[] = '<span>' . $aRow['patient_id'] . '</span>';
          }
          $row[] = $aRow['patient_name'] . " " . $aRow['patient_surname'];
          $row[] = ($aRow['facility_state']);
          $row[] = ($aRow['facility_district']);
          $row[] = $tbResults[$aRow['result']] ?? $aRow['result'] ?? null;
          $row[] = $aRow['last_modified_datetime'];
          $row[] = ($aRow['status_name']);

          if ($editRequest) {
               $edit = '<a href="tb-edit-request.php?id=' . base64_encode((string) $aRow['tb_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Edit") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Edit") . '</em></a>';
               if ($aRow['result_status'] == 7 && $aRow['locked'] == 'yes' && !_isAllowed("/tb/requests/edit-locked-tb-samples")) {
                    $edit = '<a href="javascript:void(0);" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _translate("Locked") . '" disabled><em class="fa-solid fa-lock"></em>' . _translate("Locked") . '</a>';
               }
          }


          if ($syncRequest && $general->isLISInstance() && ($aRow['result_status'] == 7 || $aRow['result_status'] == 4)) {
               if ($aRow['data_sync'] == 0) {
                    $sync = '<a href="javascript:void(0);" class="btn btn-info btn-xs" style="margin-right: 2px;" title="' . _translate("Sync Sample") . '" onclick="forceResultSync(\'' . ($aRow['sample_code']) . '\')"><em class="fa-solid fa-arrows-rotate"></em> ' . _translate("Sync") . '</a>';
               }
          } else {
               $sync = "";
          }

          if (isset($gconfig['bar_code_printing']) && $gconfig['bar_code_printing'] != "off") {
               $fac = ($aRow['facility_name']) . " | " . $aRow['sample_collection_date'];
               $barcode = '<br><a href="javascript:void(0)" onclick="printBarcodeLabel(\'' . $aRow[$sampleCode] . '\',\'' . $fac . '\')" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _translate("Barcode") . '"><em class="fa-solid fa-barcode"></em> ' . _translate("Barcode") . ' </a>';
          }
          $actions = "";
          if ($editRequest) {
               $actions .= $edit;
          }
          if ($syncRequest) {
               $actions .= $sync;
          }
          if (!$_POST['hidesrcofreq']) {
               $row[] = $actions . $barcode;
          }

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
