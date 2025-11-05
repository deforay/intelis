<?php

use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

try {

     /** @var CommonService $general */
     $general = ContainerRegistry::get(CommonService::class);

     /** @var TestRequestsService $testRequestsService */
     $testRequestsService = ContainerRegistry::get(TestRequestsService::class);
     $testRequestsService->processSampleCodeQueue();

     $formId = (int) $general->getGlobalConfig('vl_form');

     /** @var FacilitiesService $facilitiesService */
     $facilitiesService = ContainerRegistry::get(FacilitiesService::class);

     $barCodePrinting = (string) $general->getGlobalConfig('bar_code_printing');
     $key = (string) $general->getGlobalConfig('key');


     $tableName = "form_vl";
     $primaryKey = "vl_sample_id";

     $sampleCode = 'sample_code';
     $aColumns = ['vl.sample_code', 'vl.remote_sample_code', "DATE_FORMAT(vl.sample_collection_date,'%d-%b-%Y')", 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'testingLab.facility_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', "DATE_FORMAT(vl.last_modified_datetime,'%d-%b-%Y %H:%i:%s')", 'ts.status_name'];
     $orderColumns = ['vl.sample_code', 'vl.remote_sample_code', 'vl.sample_collection_date', 'b.batch_code', 'vl.patient_art_no', 'vl.patient_first_name', 'testingLab.facility_name', 'f.facility_name', 'f.facility_state', 'f.facility_district', 's.sample_name', 'vl.result', 'vl.last_modified_datetime', 'ts.status_name'];


     if ($formId == COUNTRY\CAMEROON) {
          $CountrySpecificFields = ['health_insurance_code', 'lab_assigned_code'];

          $index = array_search('s.sample_name', $aColumns);
          if ($index !== false) {
               array_splice($aColumns, $index + 1, 0, $CountrySpecificFields);
               array_splice($orderColumns, $index + 1, 0, $CountrySpecificFields);
          }
     }

     if ($general->isSTSInstance()) {
          $sampleCode = 'remote_sample_code';
     } elseif ($general->isStandaloneInstance()) {
          $aColumns = array_values(array_diff($aColumns, ['vl.remote_sample_code']));
          $orderColumns = array_values(array_diff($orderColumns, ['vl.remote_sample_code']));
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

     $sQuery = "SELECT
               vl.vl_sample_id,
               vl.sample_code,
               vl.remote_sample_code,
               vl.patient_art_no,
               vl.patient_first_name,
               vl.patient_middle_name,
               vl.patient_last_name,
               vl.patient_mobile_number,
               vl.request_clinician_phone_number,
               vl.patient_dob,
               vl.patient_gender,
               vl.health_insurance_code,
               vl.patient_age_in_years,
               vl.sample_collection_date,
               vl.treatment_initiated_date,
               vl.date_of_initiation_of_current_regimen,
               vl.test_requested_on,
               vl.sample_tested_datetime,
               vl.arv_adherance_percentage,
               vl.is_sample_rejected,
               vl.reason_for_sample_rejection,
               vl.result_value_log,
               vl.result_value_absolute,
               vl.result,
               vl.result_value_hiv_detection,
               vl.current_regimen,
               vl.is_patient_pregnant,
               vl.is_patient_breastfeeding,
               vl.request_clinician_name,
               vl.cv_number,
               vl.lab_tech_comments,
               vl.sample_received_at_hub_datetime,
               vl.sample_received_at_lab_datetime,
               vl.result_dispatched_datetime,
               vl.request_created_datetime,
               vl.result_printed_datetime,
               vl.last_modified_datetime,
               vl.result_status,
               vl.locked,
               vl.data_sync,
               vl.is_encrypted,
               vl.form_attributes,
               vl.health_insurance_code,
               vl.lab_assigned_code,
               vl.sample_package_code,
               s.sample_name as sample_name,
               b.batch_code,
               ts.status_name,
               f.facility_name,
               testingLab.facility_name as lab_name,
               f.facility_code,
               f.facility_state,
               f.facility_district,
               u_d.user_name as reviewedBy,
               a_u_d.user_name as approvedBy,
               rs.rejection_reason_name,
               tr.test_reason_name,
               r_f_s.funding_source_name,
               r_i_p.i_partner_name,
               rs.rejection_reason_name as rejection_reason

               FROM form_vl as vl

               LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
               LEFT JOIN facility_details as testingLab ON vl.lab_id=testingLab.facility_id
               LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.specimen_type
               LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
               LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id
               LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by
               LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by
               LEFT JOIN r_vl_sample_rejection_reasons as rs ON rs.rejection_reason_id=vl.reason_for_sample_rejection
               LEFT JOIN r_vl_test_reasons as tr ON tr.test_reason_id=vl.reason_for_vl_testing
               LEFT JOIN r_funding_sources as r_f_s ON r_f_s.funding_source_id=vl.funding_source
               LEFT JOIN r_implementation_partners as r_i_p ON r_i_p.i_partner_id=vl.implementing_partner";





     if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) != '') {
          $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
     }
     if (!empty($_POST['sampleCollectionDate'])) {
          [$startDate, $endDate] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '', includeTime: true);
          $sWhere[] = " vl.sample_collection_date BETWEEN '$startDate' AND '$endDate'";
     }
     if (isset($_POST['sampleReceivedDateAtLab']) && trim((string) $_POST['sampleReceivedDateAtLab']) != '') {
          [$labStartDate, $labEndDate] = DateUtility::convertDateRange($_POST['sampleReceivedDateAtLab'] ?? '', includeTime: true);
          $sWhere[] = " vl.sample_received_at_lab_datetime BETWEEN '$labStartDate' AND '$labEndDate'";
     }
     if (isset($_POST['sampleTestedDate']) && trim((string) $_POST['sampleTestedDate']) != '') {
          [$testedStartDate, $testedEndDate] = DateUtility::convertDateRange($_POST['sampleTestedDate'] ?? '', includeTime: true);
          $sWhere[] = " vl.sample_tested_datetime BETWEEN '$testedStartDate' AND '$testedEndDate'";
     }
     /* Viral load filter */
     if (isset($_POST['vLoad']) && trim((string) $_POST['vLoad']) != '') {
          if ($_POST['vLoad'] === 'suppressed') {
               $sWhere[] = " vl.vl_result_category = 'suppressed' AND vl.vl_result_category is NOT NULL ";
          } else {
               $sWhere[] = "  vl.vl_result_category = 'not suppressed' AND vl.vl_result_category is NOT NULL ";
          }
     }

     if (isset($_POST['sampleType']) && trim((string) $_POST['sampleType']) != '') {
          $sWhere[] = ' s.sample_id = "' . $_POST['sampleType'] . '"';
     }
     if (isset($_POST['facilityName']) && trim((string) $_POST['facilityName']) != '') {
          $sWhere[] = ' f.facility_id IN (' . $_POST['facilityName'] . ')';
     }
     if (isset($_POST['vlLab']) && trim((string) $_POST['vlLab']) != '') {
          $sWhere[] = ' vl.lab_id IN (' . $_POST['vlLab'] . ')';
     }
     if (isset($_POST['gender']) && trim((string) $_POST['gender']) != '') {
          if (trim((string) $_POST['gender']) == "unreported") {
               $sWhere[] = ' (vl.patient_gender="unreported" OR vl.patient_gender="" OR vl.patient_gender IS NULL)';
          } else {
               $sWhere[] = ' vl.patient_gender IN ("' . $_POST['gender'] . '")';
          }
     }

     /* Sample status filter */
     if (isset($_POST['status']) && trim((string) $_POST['status']) != '') {
          $sWhere[] = '  (vl.result_status IS NOT NULL AND vl.result_status =' . $_POST['status'] . ')';
     }
     if (isset($_POST['showReordSample']) && trim((string) $_POST['showReordSample']) != '') {
          $sWhere[] = ' vl.sample_reordered IN ("' . $_POST['showReordSample'] . '")';
     }
     if (isset($_POST['communitySample']) && trim((string) $_POST['communitySample']) != '') {
          $sWhere[] = ' (vl.community_sample IS NOT NULL AND vl.community_sample ="' . $_POST['communitySample'] . '") ';
     }
     if (isset($_POST['patientPregnant']) && trim((string) $_POST['patientPregnant']) != '') {
          $sWhere[] = ' vl.is_patient_pregnant IN ("' . $_POST['patientPregnant'] . '")';
     }

     if (isset($_POST['breastFeeding']) && trim((string) $_POST['breastFeeding']) != '') {
          $sWhere[] = ' vl.is_patient_breastfeeding IN ("' . $_POST['breastFeeding'] . '")';
     }
     if (isset($_POST['fundingSource']) && trim((string) $_POST['fundingSource']) != '') {
          $sWhere[] = ' vl.funding_source IN ("' . base64_decode((string) $_POST['fundingSource']) . '")';
     }
     if (isset($_POST['implementingPartner']) && trim((string) $_POST['implementingPartner']) != '') {
          $sWhere[] = ' vl.implementing_partner IN ("' . base64_decode((string) $_POST['implementingPartner']) . '")';
     }
     if (isset($_POST['district']) && trim((string) $_POST['district']) != '') {
          $sWhere[] = ' f.facility_district_id = "' . $_POST['district'] . '"';
     }
     if (isset($_POST['state']) && trim((string) $_POST['state']) != '') {
          $sWhere[] = ' f.facility_state_id = "' . $_POST['state'] . '"';
     }

     if (isset($_POST['reqSampleType']) && trim((string) $_POST['reqSampleType']) == 'result') {
          $sWhere[] = ' vl.result != "" ';
     } elseif (isset($_POST['reqSampleType']) && trim((string) $_POST['reqSampleType']) == 'noresult') {
          $sWhere[] = ' (vl.result IS NULL OR vl.result = "") ';
     }
     if (isset($_POST['srcOfReq']) && trim((string) $_POST['srcOfReq']) != '') {
          $sWhere[] = ' vl.source_of_request = "' . $_POST['srcOfReq'] . '" ';
     }
     /* Source of request show model conditions */
     if (isset($_POST['dateRangeModel']) && trim((string) $_POST['dateRangeModel']) != '') {
          $sWhere[] = ' DATE(vl.sample_collection_date) = "' . DateUtility::isoDateFormat($_POST['dateRangeModel']) . '"';
     }
     if (isset($_POST['srcOfReqModel']) && trim((string) $_POST['srcOfReqModel']) != '') {
          $sWhere[] = ' vl.source_of_request = "' . $_POST['srcOfReqModel'] . '" ';
     }
     if (isset($_POST['labIdModel']) && trim((string) $_POST['labIdModel']) != '') {
          $sWhere[] = ' vl.lab_id = "' . $_POST['labIdModel'] . '" ';
     }
     if (isset($_POST['srcStatus']) && $_POST['srcStatus'] == SAMPLE_STATUS\REJECTED) {
          $sWhere[] = ' vl.is_sample_rejected is not null AND vl.is_sample_rejected = "yes"';
     }
     if (isset($_POST['srcStatus']) && $_POST['srcStatus'] == SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB) {
          $sWhere[] = " vl.sample_received_at_lab_datetime is NOT NULL ";
     }
     if (isset($_POST['srcStatus']) && $_POST['srcStatus'] == SAMPLE_STATUS\ACCEPTED) {
          $sWhere[] = ' vl.result is not null AND vl.result != "" AND result_status = ' . SAMPLE_STATUS\ACCEPTED;
     }
     if (isset($_POST['srcStatus']) && $_POST['srcStatus'] == "sent") {
          $sWhere[] = " IFNULL(vl.result_sent_to_source, '') = 'sent' ";
     }
     if (isset($_POST['patientId']) && $_POST['patientId'] != "") {
          $sWhere[] = ' vl.patient_art_no = "' . $_POST['patientId'] . '"';
     }
     if (isset($_POST['patientName']) && $_POST['patientName'] != "") {
          $sWhere[] = " CONCAT(COALESCE(vl.patient_first_name,''), COALESCE(vl.patient_middle_name,''),COALESCE(vl.patient_last_name,'')) like '%" . $_POST['patientName'] . "%'";
     }
     if (!empty($_POST['rejectedSamples']) && $_POST['rejectedSamples'] == 'no') {
          $sWhere[] = " IFNULL(vl.is_sample_rejected, 'no') != 'yes' ";
     }
     if (!empty($_POST['requestCreatedDatetime'])) {
          [$sRequestCreatedDatetime, $eRequestCreatedDatetime] = DateUtility::convertDateRange($_POST['requestCreatedDatetime'] ?? '', includeTime: true);
          $sWhere[] = " vl.request_created_datetime BETWEEN '$sRequestCreatedDatetime' AND '$eRequestCreatedDatetime' ";
     }

     if (isset($_POST['printDate']) && trim((string) $_POST['printDate']) != '') {
          [$sPrintDate, $ePrintDate] = DateUtility::convertDateRange($_POST['printDate'] ?? '', includeTime: true);
          $sWhere[] = " vl.result_printed_datetime BETWEEN '$sPrintDate' AND '$ePrintDate'";
     }

     if ($general->isSTSInstance()) {
          if (!empty($_SESSION['facilityMap'])) {
               $sWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")  ";
          }
     } elseif (!$_POST['hidesrcofreq']) {
          $sWhere[] = ' vl.result_status != ' . SAMPLE_STATUS\RECEIVED_AT_CLINIC;
     }

     $sWhere[] = ' vl.result_status != ' . SAMPLE_STATUS\CANCELLED;

     if (!empty($sWhere)) {
          $_SESSION['vlRequestData']['sWhere'] = $sWhere = implode(" AND ", $sWhere);
          $sQuery = "$sQuery WHERE $sWhere";
     }

     if (!empty($sOrder) && $sOrder !== '') {
          $_SESSION['vlRequestData']['sOrder'] = $sOrder = preg_replace('/\s+/', ' ', $sOrder);
          $sQuery = "$sQuery ORDER BY $sOrder";
     }


     $_SESSION['vlRequestQuery'] = $sQuery;

     if (isset($sLimit) && isset($sOffset)) {
          $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
     }

     [$rResult, $resultCount] = $db->getDataAndCount($sQuery);

     $_SESSION['vlRequestQueryCount'] = $resultCount;

     $output = [
          "sEcho" => (int) $_POST['sEcho'],
          "iTotalRecords" => $resultCount,
          "iTotalDisplayRecords" => $resultCount,
          "aaData" => []
     ];
     $editRequest =  $syncRequest = false;
     if (_isAllowed("/vl/requests/editVlRequest.php")) {
          $editRequest = $syncRequest = true;
     }

     $sampleCodeColumn = $general->isSTSInstance() ? 'remote_sample_code' : 'sample_code';

     foreach ($rResult as $aRow) {

          $vlResult = '';
          $edit = '';
          $sync = '';
          $barcode = '';

          $patientFname = $aRow['patient_first_name'];
          $patientMname = $aRow['patient_middle_name'];
          $patientLname = $aRow['patient_last_name'];

          if (empty($aRow[$sampleCodeColumn]) && empty($aRow['sample_code'])) {
               $aRow[$sampleCodeColumn] = _translate("Generating...");
          }


          $row = [];
          $sampleCodeTooltip = [];
          $patientTooltip = [];
          if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes' && !empty($key)) {
               $aRow['patient_art_no'] = CommonService::crypto('decrypt', $aRow['patient_art_no'], $key);
               $patientFname = CommonService::crypto('decrypt', $patientFname, $key);
               $patientMname = CommonService::crypto('decrypt', $patientMname, $key);
               $patientLname = CommonService::crypto('decrypt', $patientLname, $key);
          }
          if (!empty($aRow['sample_package_code'])) {
               $sampleCodeTooltip[] = _translate("Manifest Code") . " : " . $aRow['sample_package_code'];
          }
          if (!empty($aRow['batch_code'])) {
               $sampleCodeTooltip[] = _translate("Batch Code") . " : " . $aRow['batch_code'];
          }
          if ($aRow['form_attributes'] != "") {
               $formAttributes = json_decode($aRow['form_attributes']);

               if (!empty($formAttributes->storage) && is_string($formAttributes->storage)) {
                    $storageObj = json_decode($formAttributes->storage);

                    $freezer = $storageObj->storageCode;
                    $rack = $storageObj->rack;
                    $box = $storageObj->box;
                    $position = $storageObj->position;

                    $sampleCodeTooltip[] =  _translate("Freezer") . ' - ' . $freezer . ', ' . _translate("Rack") . ' - ' . $rack . ', ' . _translate("Box") . ' - ' . $box . ', ' . _translate("Position") . ' - ' . $position;
               }
          }
          $sampleCodeTooltip[] = _translate("Request Created On") . " : " . DateUtility::humanReadableDateFormat($aRow['request_created_datetime'] ?? '', true);


          if (!empty($aRow['patient_dob'])) {
               $patientTooltip[] = _translate("Patient Date of Birth") . " : " . DateUtility::humanReadableDateFormat($aRow['patient_dob']);
          }
          if (!empty($aRow['patient_age_in_years'])) {
               $patientTooltip[] = _translate("Patient Age") . " : " . $aRow['patient_age_in_years'];
          }
          if (!empty($aRow['patient_gender'])) {
               $patientTooltip[] = _translate("Patient Sex") . " : " . $aRow['patient_gender'];
          }
          if (!empty($aRow['current_regimen'])) {
               $patientTooltip[] = _translate("Current Regimen") . " : " . $aRow['current_regimen'];
          }

          if (!empty($patientTooltip)) {
               $patientTooltip = 'class="top-tooltip" title="' . implode('<br>', $patientTooltip) . '"';
          } else {
               $patientTooltip = '';
          }

          if (!empty($sampleCodeTooltip)) {
               $sampleCodeTooltip = 'class="top-tooltip" title="' . implode('<br>', $sampleCodeTooltip) . '"';
          } else {
               $sampleCodeTooltip = '';
          }

          $row[] = "<span $sampleCodeTooltip>" . $aRow['sample_code'] . '</span>';

          if ($general->isStandaloneInstance() === false) {
               $row[] = "<span $sampleCodeTooltip>" . $aRow['remote_sample_code'] . '</span>';
          }

          $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
          $row[] = $aRow['batch_code'];
          $row[] = "<span $patientTooltip>" . $aRow['patient_art_no'] . "</span>";
          $row[] = trim(implode(" ", array($patientFname, $patientMname, $patientLname)));
          $row[] = $aRow['lab_name'];
          $row[] = $aRow['facility_name'];
          $row[] = $aRow['facility_state'];
          $row[] = $aRow['facility_district'];
          $row[] = $aRow['sample_name'];
          if ($formId == COUNTRY\CAMEROON) {
               $row[] = $aRow['health_insurance_code'] ?? null;
               $row[] = $aRow['lab_assigned_code'];
          }
          $row[] = $aRow['result'];
          $row[] = DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '', true);
          $row[] = $aRow['status_name'];

          // BUTTONS
          if ($editRequest) {
               if ($general->isLISInstance() && $aRow['result_status'] == SAMPLE_STATUS\RECEIVED_AT_CLINIC) {
                    $edit = '';
               } else {
                    $edit = '<a href="editVlRequest.php?id=' . MiscUtility::sqid((int)$aRow['vl_sample_id']) . '" class="btn btn-primary btn-xs" style="margin-right: 2px;" title="' . _translate("Edit") . '"><em class="fa-solid fa-pen-to-square"></em> ' . _translate("Edit") . '</em></a>';
               }
               if ($aRow['result_status'] == 7 && $aRow['locked'] == 'yes' && !_isAllowed("/vl/requests/edit-locked-vl-samples")) {
                    $edit = '<a href="javascript:void(0);" class="btn btn-default btn-xs" style="margin-right: 2px;" title="' . _translate("Locked") . '" disabled><em class="fa-solid fa-lock"></em>' . _translate("Locked") . '</a>';
               }
          }

          if (isset($barCodePrinting) && $barCodePrinting != "off") {
               $fac = ($aRow['facility_name']) . " | " . $aRow['sample_collection_date'];
               $barcode = "<br><a href='javascript:void(0)' onclick=\"printBarcodeLabel('{$aRow[$sampleCode]}', '{$fac}', '{$aRow['patient_art_no']}')\" class='btn btn-default btn-xs' style='margin-right: 2px;' title='" . _translate("Barcode") . "'><em class='fa-solid fa-barcode'></em> " . _translate("Barcode") . " </a>";
          }

          $sync = "";
          if ($syncRequest && $general->isLISInstance() && ($aRow['result_status'] == 7 || $aRow['result_status'] == 4)) {
               if ($aRow['data_sync'] == 0) {
                    $sync = '<a href="javascript:void(0);" class="btn btn-info btn-xs" style="margin-right: 2px;" title="' . _translate("Sync Sample") . '" onclick="forceResultSync(\'' . ($aRow['sample_code']) . '\')"> ' . _translate("Sync") . '</a>';
               }
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
          'exception' => $e,
          'file' => $e->getFile(),
          'line' => $e->getLine(),
          'last_db_query' => $db->getLastQuery(),
          'last_db_error' => $db->getLastError(),
          'stacktrace' => $e->getTraceAsString()
     ]);
}
