<?php

// This file is included in /vl/results/generate-result-pdf.php

use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Services\ResultPdfService;
use App\Helpers\PdfWatermarkHelper;
use App\Registries\ContainerRegistry;
use App\Helpers\ResultPDFHelpers\VLResultPDFHelper;


/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var ResultPdfService $resultPdfService */
$resultPdfService = ContainerRegistry::get(ResultPdfService::class);


$arr = $general->getGlobalConfig();

$key = (string) $general->getGlobalConfig('key');

if (!empty($result)) {
     $displayPageNoInFooter = true;
     $displaySignatureTable = true;
     $reportTopMargin = 17;

     if (!empty($result['vl_facility_attributes'])) {
          $vlFacilityAttributes = json_decode($result['vl_facility_attributes'], true);
          if (!empty($vlFacilityAttributes) && isset($vlFacilityAttributes['display_page_number_in_footer'])) {
               $displayPageNoInFooter = ($vlFacilityAttributes['display_page_number_in_footer']) == 'yes';
          }
          if (!empty($vlFacilityAttributes) && isset($vlFacilityAttributes['display_signature_table'])) {
               $displaySignatureTable = ($vlFacilityAttributes['display_signature_table']) == 'yes';
          }
          if (!empty($vlFacilityAttributes) && isset($vlFacilityAttributes['report_top_margin'])) {
               $reportTopMargin = (isset($vlFacilityAttributes['report_top_margin'])) ? $vlFacilityAttributes['report_top_margin'] : $reportTopMargin;
          }
     }

     $currentTime = DateUtility::getCurrentDateTime();

     $result['result_printed_datetime'] = DateUtility::humanReadableDateFormat($result['result_printed_datetime'] ?? $currentTime, true);
     $result['sample_collection_date'] = DateUtility::humanReadableDateFormat($result['sample_collection_date'] ?? '', true);
     $result['sample_received_at_lab_datetime'] = DateUtility::humanReadableDateFormat($result['sample_received_at_lab_datetime'] ?? '', true);
     $result['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($result['sample_tested_datetime'] ?? '', true);
     $result['result_reviewed_datetime'] = DateUtility::humanReadableDateFormat($result['result_reviewed_datetime'] ?? '', true);
     $result['result_approved_datetime'] = DateUtility::humanReadableDateFormat($result['result_approved_datetime'] ?? '', true);
     $result['last_viral_load_date'] = DateUtility::humanReadableDateFormat($result['last_viral_load_date'] ?? '', true);

     $reportTemplatePath = $resultPdfService->getReportTemplate($result['lab_id']);

     $testedBy = '';
     if (!empty($result['tested_by'])) {
          $testedByRes = $usersService->getUserInfo($result['tested_by'], array('user_name', 'user_signature'));
          if ($testedByRes) {
               $testedBy = $testedByRes['user_name'];
          }
     }

     $reviewedBy = '';
     $reviewedByRes = [];
     if (!empty($result['result_reviewed_by'])) {
          $reviewedByRes = $usersService->getUserInfo($result['result_reviewed_by'], array('user_name', 'user_signature'));
          if ($reviewedByRes) {
               $reviewedBy = $reviewedByRes['user_name'];
          }
     } else {
          if (!empty($result['defaultReviewedBy'])) {
               $reviewedByRes = $usersService->getUserInfo($result['defaultReviewedBy'], array('user_name', 'user_signature'));
               if ($reviewedByRes) {
                    $reviewedBy = $reviewedByRes['user_name'];
               }
               if (empty($result['result_reviewed_datetime']) && !empty($result['sample_tested_datetime'])) {
                    $result['result_reviewed_datetime'] = $result['sample_tested_datetime'];
               }
          }
     }

     $revisedBy = '';
     $revisedByRes = [];
     if (!empty($result['revised_by'])) {
          $revisedByRes = $usersService->getUserInfo($result['revised_by'], array('user_name', 'user_signature'));
          if ($revisedByRes) {
               $revisedBy = $revisedByRes['user_name'];
          }
     }

     $resultApprovedBy = '';
     $approvedByRes = [];
     if (isset($result['approvedBy']) && trim((string) $result['approvedBy']) != '' && !empty($result['result_approved_by'])) {
          $resultApprovedBy = ($result['approvedBy']);
          $approvedByRes = $usersService->getUserInfo($result['result_approved_by'], 'user_signature');
     } else {
          if (!empty($result['defaultApprovedBy'])) {
               $approvedByRes = $usersService->getUserInfo($result['defaultApprovedBy'], array('user_name', 'user_signature'));
               if ($approvedByRes) {
                    $resultApprovedBy = $approvedByRes['user_name'];
               }
               if (empty($result['result_approved_datetime']) && !empty($result['sample_tested_datetime'])) {
                    $result['result_approved_datetime'] = $result['sample_tested_datetime'];
               }
          }
     }

     $revisedSignaturePath = $reviewedSignaturePath = $testUserSignaturePath = $approvedSignaturePath = null;
     if (!empty($testedByRes['user_signature'])) {
          $testUserSignaturePath =  $testedByRes['user_signature'];
     }
     if (!empty($reviewedByRes['user_signature'])) {
          $reviewedSignaturePath =  $reviewedByRes['user_signature'];
     }
     if (!empty($revisedByRes['user_signature'])) {
          $revisedSignaturePath =  $revisedByRes['user_signature'];
     }
     if (!empty($approvedByRes['user_signature'])) {
          $approvedSignaturePath =  $approvedByRes['user_signature'];
     }

     $_SESSION['aliasPage'] = $page;
     if (!isset($result['labName'])) {
          $result['labName'] = '';
     }
     $draftTextShow = false;
     //Set watermark text
     if (!empty($mFieldArray)) {
          for ($m = 0; $m < count($mFieldArray); $m++) {
               if (!isset($result[$mFieldArray[$m]]) || trim((string) $result[$mFieldArray[$m]]) == '' || $result[$mFieldArray[$m]] == null || $result[$mFieldArray[$m]] == '0000-00-00 00:00:00') {
                    $draftTextShow = true;
                    break;
               }
          }
     }
     // create new PDF document
     $pdf = new VLResultPDFHelper(orientation: PDF_PAGE_ORIENTATION, unit: PDF_UNIT, format: PDF_PAGE_FORMAT, unicode: true, encoding: 'UTF-8', diskCache: false, pdfTemplatePath: $reportTemplatePath, enableFooter: $displayPageNoInFooter);

     if (empty($reportTemplatePath)) {
          if ($pdf->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
               $logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'];
          } else {
               $logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $arr['logo'];
          }
          // set default header data
          $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);
          $pdf->setHeading($logoPrintInPdf, $arr['header'], $result['labName'], $title = 'HIV VIRAL LOAD PATIENT REPORT');
     }
     // set document information
     $pdf->SetCreator('VLSM');
     $pdf->SetTitle('HIV Viral Load Patient Report');

     // set header and footer fonts
     $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
     $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

     // set default monospaced font
     $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

     // set margins
     $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP + $reportTopMargin, PDF_MARGIN_RIGHT);
     $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
     $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

     // set auto page breaks
     $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

     // set image scale factor
     $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

     // set font
     $pdf->SetFont('helvetica', '', 18);

     $pdf->AddPage();
     if (!isset($result['facility_code']) || trim((string) $result['facility_code']) == '') {
          $result['facility_code'] = '';
     }
     if (!isset($result['facility_state']) || trim((string) $result['facility_state']) == '') {
          $result['facility_state'] = '';
     }
     if (!isset($result['facility_district']) || trim((string) $result['facility_district']) == '') {
          $result['facility_district'] = '';
     }
     if (!isset($result['facility_name']) || trim((string) $result['facility_name']) == '') {
          $result['facility_name'] = '';
     }
     if (!isset($result['labName']) || trim((string) $result['labName']) == '') {
          $result['labName'] = '';
     }

     //Set Age
     $age = DateUtility::calculatePatientAge($result);

     if (!isset($result['patient_gender']) || trim((string) $result['patient_gender']) == '') {
          $result['patient_gender'] = 'Unreported';
     }

     $smileyContent = '';
     $showMessage = '';
     $tndMessage = '';
     $messageTextSize = '15px';


     if (!empty($result['vl_result_category']) && $result['vl_result_category'] == 'suppressed') {
          $smileyContent = '<img src="/assets/img/smiley_smile.png" style="width:50px;" alt="smile_face"/>';
          $showMessage = ($arr['l_vl_msg']);
     } elseif (!empty($result['vl_result_category']) && $result['vl_result_category'] == 'not suppressed') {
          $smileyContent = '<img src="/assets/img/smiley_frown.png" style="width:50px;" alt="frown_face"/>';
          $showMessage = ($arr['h_vl_msg']);
     } elseif ($result['result_status'] == SAMPLE_STATUS\REJECTED || $result['is_sample_rejected'] == 'yes') {
          $smileyContent = '<img src="/assets/img/cross.png" style="width:50px;" alt="rejected"/>';
     }

     if (isset($arr['show_smiley']) && trim((string) $arr['show_smiley']) == "no") {
          $smileyContent = '';
     } else {
          $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $smileyContent;
     }
     $html = '<table style="padding:4px 2px 2px 2px;width:100%;">';
     $html .= '<tr>';

     $html .= '<td colspan="3">';
     $html .= '<table style="padding:2px;">';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("FACILITY") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("FACILITY CODE") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("REGION") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("DISTRICT") . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['facility_name'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['facility_code'] ?? '-') . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['facility_state'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['facility_district'] . '</td>';
     $html .= '</tr>';
     $html .= '</table>';
     $html .= '<table style="padding:14px 2px 2px 2px;">';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("PROJECT") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("REQUESTING CLINICIAN NAME") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("TEL") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("EMAIL") . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['funding_source_name'] ?? '-') . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['request_clinician_name'] ?? '-') . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['request_clinician_phone_number'] ?? '-') . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . (isset($result['facility_emails']) && $result['facility_emails'] != '' && $result['facility_emails'] != 'NULL' ? $result['facility_emails'] : '-') . '</td>';
     $html .= '</tr>';
     $html .= '</table>';
     $html .= '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3">';
     $html .= '<table style="padding:4px 2px 2px 2px;">';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("PATIENT NAME") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("PATIENT IDENTIFIER") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("ARV PROTOCOL") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("AGE") . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';


     if (!empty($result['is_encrypted']) && $result['is_encrypted'] == 'yes') {
          $result['patient_art_no'] = $general->crypto('decrypt', $result['patient_art_no'], $key);
          $result['patient_first_name'] = $general->crypto('decrypt', $result['patient_first_name'], $key);
          $result['patient_middle_name'] = $general->crypto('decrypt', $result['patient_middle_name'], $key);
          $result['patient_last_name'] = $general->crypto('decrypt', $result['patient_last_name'], $key);
     }

     $patientFname = trim($result['patient_first_name'] . " " . $result['patient_middle_name'] . " " . $result['patient_last_name']);
     // $testReasonType = "";
     // if ($result['test_reason_name'] == "coinfection") {
     //      $result['test_reason_name'] = _translate("Co-infection", true);
     //      $testReasonType = empty($result['coinfection_type']) ? '' : ' - ' . _translate($result['coinfection_type'], true);
     // } elseif ($result['test_reason_name'] == "controlVlTesting") {
     //      $result['test_reason_name'] = _translate("Control VL Testing", true);
     //      $testReasonType = empty($result['control_vl_testing_type']) ? '' : ' - ' . _translate($result['control_vl_testing_type'], true);
     // } elseif ($result['test_reason_name'] == "other") {
     //      $result['test_reason_name'] = _translate("Other Reason", true);
     //      $testReasonType = empty($result['reason_for_vl_testing_other']) ? '' : ' - ' . $result['reason_for_vl_testing_other'];
     // }

     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $patientFname . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['patient_art_no'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['current_regimen'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $age . '</td>';
     $html .= '</tr>';
     $html .= '</table>';
     $html .= '</td>';
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td colspan="4">';
     $html .= '<table style="padding:8px 2px 2px 2px;">';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("GENDER", true) . '</td>';
     if ($result['patient_gender'] == 'female') {
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("BREASTFEEDING", true) . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("PREGNANCY STATUS", true) . '</td>';
     } else {
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
     }
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("PATIENT'S CONTACT", true) . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';

     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate(_capitalizeFirstLetter($result['patient_gender']), true) . '</td>';
     if ($result['patient_gender'] == 'female') {
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . (_translate(_capitalizeFirstLetter($result['is_patient_breastfeeding']), true) ?? '-') . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . (_translate(_capitalizeFirstLetter($result['is_patient_pregnant']), true) ?? '-') . '</td>';
     } else {
          $html .= '<td colspan="2" style="line-height:10px;font-size:10px;text-align:left;"></td>';
          $html .= '<td colspan="2" style="line-height:10px;font-size:10px;text-align:left;"></td>';
     }
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['patient_mobile_number'] ?? '-') . '</td>';
     $html .= '</tr>';
     $html .= '</table>';
     if (!empty($result['health_insurance_code'])) {
          $html .= '<table style="padding:8px 4px 2px 2px;">';
          $html .= '<tr><td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("HEALTH INSURANCE CODE") . '</td></tr>';
          $html .= '<tr><td style="line-height:10px;font-size:10px;text-align:left;">' . $result['health_insurance_code'] . '</td></tr>';
          $html .= '</table>';
     }

     $html .= '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SAMPLE ID") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SAMPLE COLLECTION DATE") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SAMPLE RECEIPT DATE") . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:8px;font-size:10px;text-align:left;">' . $result['sample_code'] . '</td>';
     $html .= '<td style="line-height:8px;font-size:10px;text-align:left;">' . $result['sample_collection_date'] . '</td>';
     $html .= '<td style="line-height:8px;font-size:10px;text-align:left;">' . $result['sample_received_at_lab_datetime'] . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SAMPLE REJECTED?") . '</td>';
     $html .= '<td style="line-height:10px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SAMPLE TEST DATE") . '</td>';
     $html .= '<td style="line-height:10px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("RESULT RELEASE DATE") . '</td>';
     $html .= '</tr>';
     $rejectedStatus = (!empty($result['is_sample_rejected']) && $result['is_sample_rejected'] == 'yes') ? 'Rejected' : 'Not Rejected';
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate($rejectedStatus) . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['sample_tested_datetime'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['result_printed_datetime'] . '</td>';
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;font-weight:bold;text-align:left;">' . _translate("SAMPLE TYPE") . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate($result['sample_name']) . '</td>';
     $html .= '</tr>';
     // $html .= '<tr>';
     // $html .= '<td colspan="3" style="line-height:10px;"></td>';
     // $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td colspan="3">';
     $html .= '<table style="padding:8px 2px 2px 2px;">';
     $logValue = '';
     if (isset($arr['vl_display_log_result']) && trim((string) $arr['vl_display_log_result']) == "yes") {
          if (!empty($result['is_sample_rejected']) && $result['is_sample_rejected'] === 'yes' && $result['result_value_log'] != '' && $result['result_value_log'] != null && ($result['reason_for_sample_rejection'] == '' || $result['reason_for_sample_rejection'] == null)) {
               $logValue = '<br/>&nbsp;&nbsp;' . _translate("Log Value") . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $result['result_value_log'];
          } else {
               if (is_numeric($result['result'])) {
                    $logV = round(log10($result['result']), 2);
                    $logValue = '<br/>&nbsp;&nbsp;' . _translate("Log Value") . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $logV;
               } else {
                    $logValue = '';
               }
          }
     }
     $vlFinalResult = '&nbsp;&nbsp;' . _translate("Viral Load Result") . ' (copies/ml)&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . htmlspecialchars((string) $result['result']);
     $html .= '<tr style="background-color:#dbdbdb;"><td colspan="2" style="line-height:26px;font-size:12px;font-weight:bold;">' . $vlFinalResult . $logValue . '</td><td >' . $smileyContent . '</td></tr>';
     if (!empty($result['reason_for_sample_rejection']) && $result['reason_for_sample_rejection'] != '') {
          $html .= '<tr><td colspan="3" style="line-height:26px;font-size:12px;font-weight:bold;text-align:left;">&nbsp;&nbsp;' . _translate("Rejection Reason") . '&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $result['rejection_reason_name'] . '</td></tr>';
     }
     if (str_contains(strtolower((string)$result['vl_test_platform']), 'abbott')) {
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:8px;font-size:10px;padding-top:10px;">' . _translate("Technique: Quantification of circulating HIV RNA by Abbott Real-Time RT-PCR (Sensitivity threshold 40 copies/mL for Plasma and 839 copies/mL for DBS)") . '</td>';
          $html .= '</tr>';
     }
     //$html .= '<tr><td colspan="3"></td></tr>';
     $html .= '</table>';
     $html .= '</td>';
     $html .= '</tr>';
     if (trim((string) $showMessage) != '') {
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:10px;font-size:' . $messageTextSize . ';text-align:left;">' . $showMessage . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:14px;"></td>';
          $html .= '</tr>';
     }
     if (trim($tndMessage) != '') {
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:11px;font-size:16px;text-align:left;">' . $tndMessage . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:13px;"></td>';
          $html .= '</tr>';
     }

     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:1px;border-bottom:2px solid #d3d3d3;"></td>';
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:13px;font-size:10px;font-weight:bold;">' . _translate("TEST PLATFORM") . ' &nbsp;&nbsp;:&nbsp;&nbsp; <span style="font-weight:normal;">' . ($result['vl_test_platform']) . '</span></td>';
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:1px;border-bottom:2px solid #d3d3d3;"></td>';
     $html .= '</tr>';

     if ($displaySignatureTable) {
          if ($result['is_sample_rejected'] === 'no' && !empty($testedBy) && !empty($result['sample_tested_datetime']) && !empty($testUserSignaturePath) && $pdf->imageExists(($testUserSignaturePath))) {
               $html .= '<tr>';
               $html .= '<td style="line-height:8px;font-size:10px;font-weight:bold;text-align:left;">' . _translate("TESTED BY") . '</td>';
               $html .= '<td style="line-height:8px;font-size:10px;font-weight:bold;text-align:left;">' . _translate("SIGNATURE") . '</td>';
               $html .= '<td style="line-height:8px;font-size:10px;font-weight:bold;text-align:left;">' . _translate("DATE") . '</td>';
               $html .= '</tr>';

               $html .= '<tr>';
               $html .= '<td style="line-height:8px;font-size:10px;text-align:left;">' . $testedBy . '</td>';
               if (!empty($testUserSignaturePath) && $pdf->imageExists(($testUserSignaturePath))) {
                    $html .= '<td style="line-height:8px;font-size:10px;text-align:left;"><img src="' . $testUserSignaturePath . '" style="width:40px;" /></td>';
               } else {
                    $html .= '<td style="line-height:8px;font-size:10px;text-align:left;"></td>';
               }
               $html .= '<td style="line-height:8px;font-size:10px;text-align:left;">' . $result['sample_tested_datetime'] . '</td>';
               $html .= '</tr>';
               $html .= '<tr>';
               $html .= '<td colspan="3" style="line-height:1px;"></td>';
               $html .= '</tr>';
          }

          if (!empty($revisedBy) && !empty($revisedSignaturePath) && $pdf->imageExists($revisedSignaturePath)) {

               $html .= '<tr>';
               $html .= '<td style="line-height:8px;font-size:10px;font-weight:bold;text-align:left;">' . _translate("REPORT REVISED BY") . '</td>';
               $html .= '<td style="line-height:8px;font-size:10px;font-weight:bold;text-align:left;">' . _translate("SIGNATURE") . '</td>';
               $html .= '<td style="line-height:8px;font-size:10px;font-weight:bold;text-align:left;">' . _translate("DATE") . '</td>';
               $html .= '</tr>';

               $html .= '<tr>';
               $html .= '<td style="line-height:8px;font-size:10px;text-align:left;">' . $revisedBy . '</td>';
               if (!empty($revisedSignaturePath) && $pdf->imageExists($revisedSignaturePath)) {
                    $html .= '<td style="line-height:8px;font-size:10px;text-align:left;"><img src="' . $revisedSignaturePath . '" style="width:40px;" /></td>';
               } else {
                    $html .= '<td style="line-height:8px;font-size:10px;text-align:left;"></td>';
               }
               $html .= '<td style="line-height:8px;font-size:10px;text-align:left;">' . DateUtility::humanReadableDateFormat($result['revised_on'] ?? '') . '</td>';
               $html .= '</tr>';
               $html .= '<tr>';
               $html .= '<td colspan="3" style="line-height:1px;"></td>';
               $html .= '</tr>';
          }
          if ($reviewedBy != $resultApprovedBy) {
               if (!empty($reviewedBy)) {
                    $html .= '<tr>';
                    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("REVIEWED BY") . '</td>';
                    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SIGNATURE") . '</td>';
                    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("DATE") . '</td>';
                    $html .= '</tr>';

                    $html .= '<tr>';
                    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $reviewedBy . '</td>';
                    if (!empty($reviewedSignaturePath) && $pdf->imageExists(($reviewedSignaturePath))) {
                         $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $reviewedSignaturePath . '" style="width:40px;" /></td>';
                    } else {
                         $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
                    }
                    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . (!empty($result['result_reviewed_datetime']) ? $result['result_reviewed_datetime'] : $result['sample_tested_datetime']) . '</td>';
                    $html .= '</tr>';
                    $html .= '<tr>';
                    $html .= '<td colspan="3" style="line-height:2px;"></td>';
                    $html .= '</tr>';
               }
               if (!empty($resultApprovedBy) && !empty($result['result_approved_datetime']) && $displaySignatureTable) {
                    $html .= '<tr>';
                    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("APPROVED BY") . '</td>';
                    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SIGNATURE") . '</td>';
                    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("DATE") . '</td>';
                    $html .= '</tr>';

                    $html .= '<tr>';
                    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $resultApprovedBy . '</td>';
                    if (!empty($approvedSignaturePath) && $pdf->imageExists(($approvedSignaturePath))) {
                         $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $approvedSignaturePath . '" style="width:100px;" /></td>';
                    } else {
                         $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
                    }

                    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['result_approved_datetime'] . '</td>';
                    $html .= '</tr>';
                    $html .= '<tr>';
                    $html .= '<td colspan="3" style="line-height:2px;"></td>';
                    $html .= '</tr>';
               }
          } else {
               if (!empty($reviewedBy)) {
                    $html .= '<tr>';
                    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("REVIEWED AND APPROVED BY") . '</td>';
                    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SIGNATURE") . '</td>';
                    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("DATE") . '</td>';
                    $html .= '</tr>';

                    $html .= '<tr>';
                    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $reviewedBy . '</td>';
                    if (!empty($reviewedSignaturePath) && $pdf->imageExists(($reviewedSignaturePath))) {
                         $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $reviewedSignaturePath . '" style="width:40px;" /></td>';
                    } else {
                         $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
                    }
                    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . (!empty($result['result_reviewed_datetime']) ? $result['result_reviewed_datetime'] : $result['sample_tested_datetime']) . '</td>';
                    $html .= '</tr>';
                    $html .= '<tr>';
                    $html .= '<td colspan="3" style="line-height:2px;"></td>';
                    $html .= '</tr>';
               }
          }
     }

     if (!empty($result['lab_tech_comments'])) {
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:10px;font-size:10px;text-align:left;"><strong>' . _translate("Lab Comments") . ':</strong> ' . $result['lab_tech_comments'] . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:1px;"></td>';
          $html .= '</tr>';
     }

     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:1px;"></td>';
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:10px;font-size:10px;text-align:left;">';
     $html .= '<u><strong>NB</strong></u> : ' . _translate("For a variation in Viral Load to be significant, the difference between two measurements must be at least 0.5 Log<sub>10</sub> or a reduction or increase of a factor of 3 in the number of copies/mL") . ' </td>';
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:2px;"></td>';
     $html .= '</tr>';


     $html .= '<tr><td colspan="3" style="line-height:11px;font-size:11px;text-align:left;color:#808080;">(*)&nbsp;';
     $html .= '<u><strong>' . _translate("Detection Limit (DL)") . '</strong></u> : ' . _translate("&lt; 40 (1.60 Log<sub>10</sub>) copies/mL  for Plasma and 839 (2.92 Log<sub>10</sub>) copies/mL for DBS");
     $html .= '<br> &nbsp;&nbsp;&nbsp;&nbsp;';
     $html .= '<u><strong>' . _translate("Quantification Limits (QL)") . '</strong></u> : ' .  _translate("Between 40 and 10,000000 (1.60 and 7.0 Log<sub>10</sub>) copies/mL for Plasma and 839 and 10,000000 (2.92 and 7.0 Log<sub>10</sub>) copies/mL for DBS");
     $html .= '</td></tr>';

     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:2px;"></td>';
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
     $html .= '</tr>';
     // $html .= '<tr>';
     // $html .= '<td colspan="3" style="line-height:2px;"></td>';
     // $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3">';
     $html .= '<table>';
     $html .= '<tr>';
     $html .= '<td style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_smile.png" alt="smile_face" style="width:8px;height:8px;"/> = VL < = 1000 copies/ml: ' . _translate("Continue on current regimen") . '</td>';
     $html .= '<td style="font-size:10px;text-align:left;">' . _translate("Printed on") . ' : ' . $printDate . '&nbsp;&nbsp;' . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="2" style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_frown.png" alt="frown_face" style="width:8px;height:8px;"/> = VL > 1000 copies/ml: ' . _translate("Clinical and counselling action required") . '</td>';
     $html .= '</tr>';
     $html .= '</table>';
     $html .= '</td>';
     $html .= '</tr>';
     $html .= '</table>';
     if ($result['result'] != '' || (empty($result['result']) && $result['result_status'] == SAMPLE_STATUS\REJECTED)) {
          $pdf->writeHTML($html);
          $pdf->lastPage();
          $filename = $pathFront . DIRECTORY_SEPARATOR . 'p' . $page . '.pdf';
          $pdf->Output($filename, "F");
          if ($draftTextShow) {
               //Watermark section
               $watermark = new PdfWatermarkHelper();
               $watermark->setFullPathToFile($filename);
               $fullPathToFile = $filename;
               $watermark->Output($filename, "F");
          }
          $pages[] = $filename;
          $page++;
     }
     if (isset($_POST['source']) && trim((string) $_POST['source']) == 'print') {
          $sampleCode = 'sample_code';
          if ($general->isSTSInstance()) {
               $sampleCode = 'remote_sample_code';
               if (!empty($result['remote_sample']) && $result['remote_sample'] == 'yes') {
                    $sampleCode = 'remote_sample_code';
               } else {
                    $sampleCode = 'sample_code';
               }
          }
          $sampleId = (isset($result[$sampleCode]) && !empty($result[$sampleCode])) ? ' sample id ' . $result[$sampleCode] : '';
          $patientId = (isset($result['patient_art_no']) && !empty($result['patient_art_no'])) ? ' patient id ' . $result['patient_art_no'] : '';
          $concat = (!empty($sampleId) && !empty($patientId)) ? ' and' : '';
          //Add event log
          $eventType = 'print-result';
          $action = $_SESSION['userName'] . ' generated the test result PDF with ' . $sampleId . $concat . $patientId;
          $resource = 'print-test-result';
          $data = array(
               'event_type' => $eventType,
               'action' => $action,
               'resource' => $resource,
               'date_time' => $currentTime
          );
          $db->insert($tableName1, $data);
          //Update print datetime in VL tbl.
          $vlQuery = "SELECT result_printed_datetime FROM form_vl as vl WHERE vl.vl_sample_id ='" . $result['vl_sample_id'] . "'";
          $vlResult = $db->query($vlQuery);
          if ($vlResult[0]['result_printed_datetime'] == null || trim((string) $vlResult[0]['result_printed_datetime']) == '' || $vlResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
               $db->where('vl_sample_id', $result['vl_sample_id']);
               $db->update($tableName2, array('result_printed_datetime' => $currentTime, 'result_dispatched_datetime' => $currentTime));
          }
     }
}
