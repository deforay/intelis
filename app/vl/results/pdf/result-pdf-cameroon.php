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

if (!empty($result)) {

     $currentTime = DateUtility::getCurrentDateTime();

     $reportTemplatePath = $resultPdfService->getReportTemplate($result['lab_id']);

     $testedBy = '';
     if (!empty($result['tested_by'])) {
          $testedByRes = $usersService->getUserInfo($result['tested_by'], array('user_name', 'user_signature'));
          if ($testedByRes) {
               $testedBy = $testedByRes['user_name'];
          }
     }
     $reviewedBy = '';
     if (!empty($result['result_reviewed_by'])) {
          $reviewedByRes = $usersService->getUserInfo($result['result_reviewed_by'], array('user_name', 'user_signature'));
          if ($reviewedByRes) {
               $reviewedBy = $reviewedByRes['user_name'];
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

     $revisedSignaturePath = $reviewedSignaturePath = $testUserSignaturePath = null;
     if (!empty($testedByRes['user_signature'])) {
          $testUserSignaturePath =  $testedByRes['user_signature'];
     }
     if (!empty($reviewedByRes['user_signature'])) {
          $reviewedSignaturePath =  $reviewedByRes['user_signature'];
     }
     if (!empty($revisedByRes['user_signature'])) {
          $revisedSignaturePath =  $revisedByRes['user_signature'];
     }

     $resultApprovedBy = '';
     $userSignaturePath = null;
     if (!empty($result['result_approved_by'])) {
          $resultApprovedByRes = $usersService->getUserInfo($result['result_approved_by'], array('user_name', 'user_signature'));
          if ($resultApprovedByRes) {
               $resultApprovedBy = $resultApprovedByRes['result_approved_by'] ?? null;
          }
          if (!empty($resultApprovedByRes['user_signature'])) {
               $userSignaturePath =  $resultApprovedByRes['user_signature'];
          }
     }

     if (isset($result['approvedBy']) && trim((string) $result['approvedBy']) != '') {
          $resultApprovedBy = ($result['approvedBy']);
          $userRes = $usersService->getUserInfo($result['result_approved_by'], 'user_signature');
     } else {
          $resultApprovedBy  = '';
     }

     $userSignaturePath = null;
     if (!empty($userRes['user_signature'])) {
          $userSignaturePath =  $userRes['user_signature'];
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
     $pdf = new VLResultPDFHelper(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false, $reportTemplatePath);

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
     $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP + 16, PDF_MARGIN_RIGHT);
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
     $age = 'Unknown';
     if (!empty($result['patient_dob']) && $result['patient_dob'] != '0000-00-00') {
          $dob = new DateTime($result['patient_dob']);
          $today = new DateTime(date('Y-m-d'));
          $age = $dob->diff($today)->y;
     } elseif (!empty($result['patient_age_in_years']) && $result['patient_age_in_years'] > 0) {
          $age = $result['patient_age_in_years'];
     } elseif (!empty($result['patient_age_in_months']) && $result['patient_age_in_months'] > 0) {
          $age = $result['patient_age_in_months'] . ' month' . ($result['patient_age_in_months'] > 1 ? 's' : '');
     }

     $result['result_printed_datetime'] = DateUtility::humanReadableDateFormat($result['result_printed_datetime'] ?? $currentTime, true, 'd/M/Y H:i:s');
     $result['sample_collection_date'] = DateUtility::humanReadableDateFormat($result['sample_collection_date'] ?? '', true, 'd/M/Y H:i:s');
     $result['sample_received_at_lab_datetime'] = DateUtility::humanReadableDateFormat($result['sample_received_at_lab_datetime'] ?? '', true, 'd/M/Y H:i:s');
     $result['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($result['sample_tested_datetime'] ?? '', true, 'd/M/Y H:i:s');
     $result['result_reviewed_datetime'] = DateUtility::humanReadableDateFormat($result['result_reviewed_datetime'] ?? '', true, 'd/M/Y H:i:s');
     $result['result_approved_datetime'] = DateUtility::humanReadableDateFormat($result['result_approved_datetime'] ?? '', true, 'd/M/Y H:i:s');
     $result['last_viral_load_date'] = DateUtility::humanReadableDateFormat($result['last_viral_load_date'] ?? '', true, 'd/M/Y H:i:s');

     if (!isset($result['patient_gender']) || trim((string) $result['patient_gender']) == '') {
          $result['patient_gender'] = 'not reported';
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
     } elseif ($result['result_status'] == '4' || $result['is_sample_rejected'] == 'yes') {
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
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("REQUESTING HEALTH FACILITY NAME") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("HEALTH FACILITY CODE") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("STATE") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("COUNTY") . '</td>';
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
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("PROJECT NAME") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("REQUESTING CLINICIAN NAME") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("TEL") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("EMAIL") . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['funding_source_name'] ?? '-') . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['request_clinician_name'] ?? '-') . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['request_clinician_phone_number'] ?? '-') . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['facility_emails'] ?? '-') . '</td>';
     $html .= '</tr>';
     $html .= '</table>';
     $html .= '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3">';
     $html .= '<table style="padding:4px 2px 2px 2px;">';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("PATIENT NAME") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("UNIQUE ART (TRACNET) NO.") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("REASON FOR VL TESTING") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("CURRENT ARV PROTOCOL") . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';

     $patientFname = $result['patient_first_name'] ?? '-';
     if (!empty($result['is_encrypted']) && $result['is_encrypted'] == 'yes') {
          $key = (string) $general->getGlobalConfig('key');
          $result['patient_art_no'] = $general->crypto('decrypt', $result['patient_art_no'], $key);
          $patientFname = $general->crypto('decrypt', $patientFname, $key);
     }
     $testReasonType = "";
     if ($result['test_reason_name'] == "coinfection") {
          $result['test_reason_name'] = _translate("Co-infection");
          $testReasonType = empty($result['coinfection_type']) ? '' : ' - ' . $result['coinfection_type'];
     } elseif ($result['test_reason_name'] == "controlVlTesting") {
          $result['test_reason_name'] = _translate("Control VL Testing");
          $testReasonType = empty($result['control_vl_testing_type']) ? '' : ' - ' . $result['control_vl_testing_type'];
     } elseif ($result['test_reason_name'] == "other") {
          $result['test_reason_name'] = _translate("Other Reason");
          $testReasonType = empty($result['reason_for_vl_testing_other']) ? '' : ' - ' . $result['reason_for_vl_testing_other'];
     }

     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $patientFname . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['patient_art_no'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['test_reason_name'] . $testReasonType . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['current_regimen'] . '</td>';
     $html .= '</tr>';
     $html .= '</table>';
     $html .= '</td>';
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td colspan="4">';
     $html .= '<table style="padding:8px 2px 2px 2px;">';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("AGE") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("GENDER") . '</td>';
     if ($result['patient_gender'] == 'female') {
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("BREASTFEEDING") . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("PREGNANCY STATUS") . '</td>';
     } else {
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
     }
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $age . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ucwords(_translate($result['patient_gender']) ?? '') . '</td>';
     if ($result['patient_gender'] == 'female') {
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ucwords(_translate($result['is_patient_breastfeeding']) ?? '-') . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ucwords(_translate($result['is_patient_pregnant']) ?? '-') . '</td>';
     } else {
          $html .= '<td colspan="2" style="line-height:10px;font-size:10px;text-align:left;"></td>';
          $html .= '<td colspan="2" style="line-height:10px;font-size:10px;text-align:left;"></td>';
     }
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("PATIENT CONTACT") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;"></td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;"></td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;"></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['patient_mobile_number'] ?? '-') . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;"></td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;"></td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;"></td>';
     $html .= '</tr>';
     $html .= '</table>';
     $html .= '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:12px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SAMPLE ID") . '</td>';
     $html .= '<td style="line-height:12px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SAMPLE COLLECTION DATE") . '</td>';
     $html .= '<td style="line-height:12px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SAMPLE RECEIPT DATE") . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['sample_code'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['sample_collection_date'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['sample_received_at_lab_datetime'] . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("IS SAMPLE REJECTED?") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SAMPLE TEST DATE") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("RESULT RELEASE DATE") . '</td>';
     $html .= '</tr>';
     $rejectedStatus = (!empty($result['is_sample_rejected']) && $result['is_sample_rejected'] == 'yes') ? 'Rejected' : 'Not Rejected';
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate($rejectedStatus) . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['sample_tested_datetime'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['result_printed_datetime'] . '</td>';
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SAMPLE TYPE") . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['sample_name']) . '</td>';
     $html .= '</tr>';
     // $html .= '<tr>';
     // $html .= '<td colspan="3" style="line-height:10px;"></td>';
     // $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td colspan="3">';
     $html .= '<table style="padding:10px 2px 2px 2px;">';
     $logValue = '';
     if ($result['result_value_log'] != '' && $result['result_value_log'] != null && ($result['reason_for_sample_rejection'] == '' || $result['reason_for_sample_rejection'] == null)) {
          $logValue = '&nbsp;&nbsp;' . _translate("Log Value") . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $result['result_value_log'];
     } else {
          if ($isResultNumeric) {
               $logV = round(log10($result['result']), 2);
               $logValue = '&nbsp;&nbsp;' . _translate("Log Value") . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $logV;
          } else {
               $logValue = '';
          }
     }
     $html .= '<tr style="background-color:#dbdbdb;"><td colspan="2" style="line-height:26px;font-size:12px;font-weight:bold;">&nbsp;&nbsp;' . _translate("Viral Load Result") . ' (copies/ml)&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . htmlspecialchars((string) $result['result']) . '<br>' . $logValue . '</td><td >' . $smileyContent . '</td></tr>';
     if ($result['reason_for_sample_rejection'] != '') {
          $html .= '<tr><td colspan="3" style="line-height:26px;font-size:12px;font-weight:bold;text-align:left;">&nbsp;&nbsp;' . _translate("Rejection Reason") . '&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $result['rejection_reason_name'] . '</td></tr>';
     }
     if (str_contains(strtolower((string)$result['vl_test_platform']), 'abbott')) {
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:10px;font-size:10px;padding-top:10px;">Technique : Quantification de l&#39;ARN du VIH circulant par RT-PCR Temps réel Abbott (Seuil de sensibilité 40 Copies/ml)</td>';
          $html .= '</tr>';
     }
     //$html .= '<tr><td colspan="3"></td></tr>';
     $html .= '</table>';
     $html .= '</td>';
     $html .= '</tr>';
     if (trim((string) $showMessage) != '') {
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:12px;font-size:' . $messageTextSize . ';text-align:left;">' . $showMessage . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:16px;"></td>';
          $html .= '</tr>';
     }
     if (trim($tndMessage) != '') {
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:13px;font-size:18px;text-align:left;">' . $tndMessage . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:16px;"></td>';
          $html .= '</tr>';
     }
     // if (trim($result['lab_tech_comments']) != '') {
     //      $html .= '<tr>';
     //      $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">LAB COMMENTS&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . ($result['lab_tech_comments']) . '</span></td>';
     //      $html .= '</tr>';
     //      $html .= '<tr>';
     //      $html .= '<td colspan="3" style="line-height:10px;"></td>';
     //      $html .= '</tr>';
     // }
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
     $html .= '</tr>';
     // $html .= '<tr>';
     // $html .= '<td colspan="3" style="line-height:14px;"></td>';
     // $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:15px;font-size:11px;font-weight:bold;">' . _translate("TEST PLATFORM") . ' &nbsp;&nbsp;:&nbsp;&nbsp; <span style="font-weight:normal;">' . ($result['vl_test_platform']) . '</span></td>';
     $html .= '</tr>';
     // $html .= '<tr>';
     // $html .= '<td colspan="3" style="line-height:8px;"></td>';
     // $html .= '</tr>';
     // if (isset($result['last_viral_load_result']) && $result['last_viral_load_result'] != null) {
     //      $html .= '<tr>';
     //      $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">PREVIOUS RESULTS</td>';
     //      $html .= '</tr>';
     //      $html .= '<tr>';
     //      $html .= '<td colspan="3" style="line-height:8px;"></td>';
     //      $html .= '</tr>';
     //      $html .= '<tr>';
     //      $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Date of Last VL Test&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . $result['last_viral_load_date'] . '</span></td>';
     //      $html .= '</tr>';
     //      $html .= '<tr>';
     //      $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Result of previous viral load(copies/ml)&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . $result['last_viral_load_result'] . '</span></td>';
     //      $html .= '</tr>';
     // }
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
     $html .= '</tr>';
     // $html .= '<tr>';
     // $html .= '<td colspan="3" style="line-height:8px;"></td>';
     // $html .= '</tr>';
     if ($result['is_sample_rejected'] == 'no') {
          if (!empty($testedBy) && !empty($result['sample_tested_datetime'])) {
               $html .= '<tr>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("TESTED BY") . '</td>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SIGNATURE") . '</td>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("DATE") . '</td>';
               $html .= '</tr>';

               $html .= '<tr>';
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $testedBy . '</td>';
               if (!empty($testUserSignaturePath) && $pdf->imageExists(($testUserSignaturePath))) {
                    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $testUserSignaturePath . '" style="width:40px;" /></td>';
               } else {
                    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
               }
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_tested_datetime'] . '</td>';
               $html .= '</tr>';
          }
     }
     if (!empty($reviewedBy)) {
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:8px;"></td>';
          $html .= '</tr>';

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
     }

     if (!empty($revisedBy)) {

          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("REPORT REVISED BY") . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SIGNATURE") . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("DATE") . '</td>';
          $html .= '</tr>';

          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $revisedBy . '</td>';
          if (!empty($revisedSignaturePath) && $pdf->imageExists($revisedSignaturePath)) {
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $revisedSignaturePath . '" style="width:40px;" /></td>';
          } else {
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
          }
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . date('d/M/Y', strtotime((string) $result['revised_on'])) . '</td>';
          $html .= '</tr>';
     }

     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:8px;"></td>';
     $html .= '</tr>';
     if (!empty($resultApprovedBy) && !empty($result['result_approved_datetime'])) {
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("APPROVED BY") . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SIGNATURE") . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("DATE") . '</td>';
          $html .= '</tr>';

          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $resultApprovedBy . '</td>';
          if (!empty($userSignaturePath) && $pdf->imageExists(($userSignaturePath))) {
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $userSignaturePath . '" style="width:40px;" /></td>';
          } else {
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
          }

          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['result_approved_datetime'] . '</td>';
          $html .= '</tr>';
     }

     // $html .= '<tr>';
     // $html .= '<td colspan="3" style="line-height:2px;"></td>';
     // $html .= '</tr>';

     // if (!empty($result['lab_tech_comments'])) {
     //      $html .= '<tr>';
     //      $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Comments</td>';
     //      $html .= '</tr>';
     //      $html .= '<tr>';
     //      $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['lab_tech_comments'] . '</td>';
     //      $html .= '</tr>';
     // }


     if (!empty($result['lab_tech_comments'])) {

          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:20px;"></td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:11px;font-size:11px;text-align:left;"><strong>' . _translate("Lab Comments") . ':</strong> ' . $result['lab_tech_comments'] . '</td>';
          $html .= '</tr>';

          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:2px;"></td>';
          $html .= '</tr>';
     }
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
     $html .= '<td style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_smile.png" alt="smile_face" style="width:10px;height:10px;"/> = VL < = 1000 copies/ml: ' . _translate("Continue on current regimen") . '</td>';
     $html .= '<td style="font-size:10px;text-align:left;">' . _translate("Printed on") . ' : ' . $printDate . '&nbsp;&nbsp;' . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="2" style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_frown.png" alt="frown_face" style="width:10px;height:10px;"/> = VL > 1000 copies/ml: ' . _translate("Clinical and counselling action required") . '</td>';
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
     }
     if (isset($_POST['source']) && trim((string) $_POST['source']) == 'print') {
          //Add event log
          $eventType = 'print-result';
          $action = $_SESSION['userName'] . ' generated the test result PDF with Patient ID/Code ' . $result['patient_art_no'];
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
