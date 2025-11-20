<?php

use Psr\Http\Message\ServerRequestInterface;
use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const COUNTRY\CAMEROON;
use App\Services\EidService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;


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

     $formId = (int) $general->getGlobalConfig('vl_form');

     /** @var EidService $eidService */
     $eidService = ContainerRegistry::get(EidService::class);
     $eidResults = $eidService->getEidResults();

     $barCodeEnabled = $general->getGlobalConfig('bar_code_printing');
     $key = (string) $general->getGlobalConfig('key');


     $tableName = "form_eid";
     $primaryKey = "eid_id";

     $sampleCode = 'sample_code';

     $aColumns = ['vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'l_f.facility_name', 'f.facility_name', 'vl.child_id', 'vl.child_name', 'vl.mother_id', 'vl.mother_name', 'f.facility_state', 'f.facility_district', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name'];
     $orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'labName', 'f.facility_name', 'vl.child_id', 'vl.child_name', 'vl.mother_id', 'vl.mother_name', 'f.facility_state', 'f.facility_district', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name'];

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

     $columnSearch = $general->multipleColumnSearch($_POST['sSearch'], $aColumns);

     $sWhere = [];
     if (!empty($columnSearch) && $columnSearch != '') {
          $sWhere[] = $columnSearch;
     }

     $sQuery = "SELECT vl.*, f.*,
     b.batch_code,
     ts.status_name,
     f.facility_name,
     l_f.facility_name as labName,
     f.facility_code,
     f.facility_state,
     f.facility_district,
     u_d.user_name as reviewedBy,
     a_u_d.user_name as approvedBy,
     rs.rejection_reason_name,
     r_f_s.funding_source_name,
     r_i_p.i_partner_name

     FROM form_eid as vl

     LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
     LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id
     LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
     LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
     LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by
     LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by
     LEFT JOIN r_eid_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection
     LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source
     LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner";

     if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) !== '') {
          $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
     }
     if (!empty($_POST['sampleCollectionDate'])) {
          [$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
          $sWhere[] = " DATE(vl.sample_collection_date) BETWEEN '$start_date' AND '$end_date'";
     }

     if (isset($_POST['sampleReceivedDateAtLab']) && trim((string) $_POST['sampleReceivedDateAtLab']) !== '') {
          [$labStartDate, $labEndDate] = DateUtility::convertDateRange($_POST['sampleReceivedDateAtLab'] ?? '');
          $sWhere[] = " DATE(vl.sample_received_at_lab_datetime) BETWEEN '$labStartDate' AND '$labEndDate'";
     }

     if (isset($_POST['sampleTestedDate']) && trim((string) $_POST['sampleTestedDate']) !== '') {
          [$testedStartDate, $testedEndDate] = DateUtility::convertDateRange($_POST['sampleTestedDate'] ?? '');
          $sWhere[] = " DATE(vl.sample_tested_datetime) BETWEEN '$testedStartDate' AND '$testedEndDate'";
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
               $sWhere[] = ' vl.child_gender="unreported" OR vl.child_gender="" OR vl.child_gender IS NULL';
          } else {
               $sWhere[] = ' vl.child_gender IN ("' . $_POST['gender'] . '")';
          }
     }
     /* Sample status filter */
     if (isset($_POST['status']) && trim((string) $_POST['status']) !== '') {
          $sWhere[] = '  (vl.result_status IS NOT NULL AND vl.result_status =' . $_POST['status'] . ')';
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
     if (isset($_POST['srcStatus']) && $_POST['srcStatus'] == 4) {
          $sWhere[] = " IFNULL(vl.is_sample_rejected, 'no') like 'yes'";
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
     if (isset($_POST['childId']) && $_POST['childId'] != "") {
          $sWhere[] = ' vl.child_id like "%' . $_POST['childId'] . '%"';
     }
     if (isset($_POST['childName']) && $_POST['childName'] != "") {
          $sWhere[] = ' vl.child_name like "%' . $_POST['childName'] . '%"';
     }
     if (isset($_POST['motherId']) && $_POST['motherId'] != "") {
          $sWhere[] = ' vl.mother_id like "%' . $_POST['motherId'] . '%"';
     }
     if (isset($_POST['motherName']) && $_POST['motherName'] != "") {
          $sWhere[] = ' vl.mother_name like "%' . $_POST['motherName'] . '%"';
     }
     if (isset($_POST['rejectedSamples']) && $_POST['rejectedSamples'] != "") {
          $sWhere[] = " IFNULL(vl.is_sample_rejected, 'no') not like 'yes' ";
     }


     if ($general->isSTSInstance()) {
          if (!empty($_SESSION['facilityMap'])) {
               $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
          }
     } elseif (!$_POST['hidesrcofreq']) {
          $sWhere[] = ' vl.result_status != ' . RECEIVED_AT_CLINIC;
     }
     if (!empty($sWhere)) {
          $_SESSION['eidRequestData']['sWhere'] = $sWhere = implode(" AND ", $sWhere);
          $sQuery = "$sQuery WHERE $sWhere";
     }
     if (!empty($sOrder) && $sOrder !== '') {
          $_SESSION['eidRequestData']['sOrder'] = $sOrder = preg_replace('/\s+/', ' ', (string) $sOrder);
          $sQuery = "$sQuery ORDER BY $sOrder";
     }
     $_SESSION['eidRequestSearchResultQuery'] = $sQuery;

     if (isset($sLimit) && isset($sOffset)) {
          $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
     }

     error_log($sQuery);

     [$rResult, $resultCount] = $db->getDataAndCount($sQuery);

     $_SESSION['eidRequestSearchResultQueryCount'] = $resultCount;

     $output = [
          "sEcho" => (int) $_POST['sEcho'],
          "iTotalRecords" => $resultCount,
          "iTotalDisplayRecords" => $resultCount,
          "aaData" => []
     ];
     $editRequest = false;
     $syncRequest = false;
     if (_isAllowed("/eid/requests/eid-edit-request.php")) {
          $editRequest = true;
          $syncRequest = true;
     }

     $sampleCodeColumn = $general->isSTSInstance() ? 'remote_sample_code' : 'sample_code';

     foreach ($rResult as $aRow) {

          $vlResult = '';
          $edit = '';
          $sync = '';
          $barcode = '';

          $row = [];
          $sampleCodeTooltip = '';
          $childTooltip = '';
          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
               $aRow['child_id'] = $general->crypto('decrypt', $aRow['child_id'], $key);
               $aRow['child_name'] = $general->crypto('decrypt', $aRow['child_name'], $key);
               $aRow['mother_id'] = $general->crypto('decrypt', $aRow['mother_id'], $key);
               $aRow['mother_name'] = $general->crypto('decrypt', $aRow['mother_name'], $key);
          }


          if (empty($aRow[$sampleCodeColumn]) && empty($aRow['sample_code'])) {
               $aRow[$sampleCodeColumn] = _translate("Generating...");
          }

          if (!empty($aRow['sample_package_code'])) {
               $sampleCodeTooltip .= _translate("Manifest Code", true) . " : " . $aRow['sample_package_code'] . '<br>';
          }
          if (!empty($aRow['batch_code'])) {
               $sampleCodeTooltip .= _translate("Batch Code", true) . " : " . $aRow['batch_code'];
          }

          if (!empty($aRow['child_dob'])) {
               $childTooltip .= _translate("Child DoB", true) . " : " . DateUtility::humanReadableDateFormat($aRow['child_dob']) . '<br>';
          }
          if (!empty($aRow['child_age'])) {
               $childTooltip .= _translate("Child Age", true) . " : " . $aRow['child_age'] . '<br>';
          }
          if (!empty($aRow['child_gender'])) {
               $childTooltip .= _translate("Child Sex", true) . " : " . $aRow['child_gender'] . '<br>';
          }

          //$row[]='<input type="checkbox" name="chk[]" class="checkTests" id="chk' . $aRow['eid_id'] . '"  value="' . $aRow['eid_id'] . '" onclick="toggleTest(this);"  />';
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
          $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
          $row[] = $aRow['batch_code'];
          $row[] = $aRow['labName'];
          $row[] = $aRow['facility_name'];
          if ($childTooltip !== '' && $childTooltip !== '0') {
               $row[] = '<span class="top-tooltip" title="' . $childTooltip . '">' . $aRow['child_id'] . '</span>';
          } else {
               $row[] = '<span>' . $aRow['child_id'] . '</span>';
          }
          $row[] = $aRow['child_name'];
          $row[] = $aRow['mother_id'];
          $row[] = $aRow['mother_name'];

          $row[] = $aRow['facility_state'];
          $row[] = $aRow['facility_district'];
          $row[] = ucwords($eidResults[$aRow['result']] ?? $aRow['result']);
          if ($formId == CAMEROON) {
               $row[] = $aRow['lab_assigned_code'];
          }
          $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '', true);
          $row[] = $aRow['status_name'];

          if ($editRequest) {
               $edit = '<a href="eid-edit-request.php?id=' . MiscUtility::sqid($aRow['eid_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Edit") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Edit") . '</em></a>';
               if ($aRow['result_status'] == 7 && $aRow['locked'] == 'yes' && !_isAllowed("/eid/requests/edit-locked-eid-samples")) {
                    $edit = '<a href="javascript:void(0);" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _translate("Locked") . '" disabled><em class="fa-solid fa-lock"></em>' . _translate("Locked") . '</a>';
               }
          }

          if ($syncRequest && $general->isLISInstance() && ($aRow['result_status'] == 7 || $aRow['result_status'] == 4)) {
               if ($aRow['data_sync'] == 0) {
                    $sync = '<a href="javascript:void(0);" class="btn btn-info btn-xs" style="margin-right: 2px;" title="' . _translate("Sync Sample") . '" onclick="forceResultSync(\'' . ($aRow['sample_code']) . '\')"> ' . _translate("Sync") . '</a>';
               }
          } else {
               $sync = "";
          }

          if (isset($barCodeEnabled) && $barCodeEnabled != "off") {
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
          'last_db_query' => $db->getLastQuery()
     ]);
}
