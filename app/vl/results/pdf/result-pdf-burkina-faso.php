<?php

// This file is included in /vl/results/generate-result-pdf.php
use const SAMPLE_STATUS\REJECTED;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Helpers\PdfWatermarkHelper;
use App\Registries\ContainerRegistry;
use App\Helpers\ResultPDFHelpers\VLResultPDFHelper;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$arr = $general->getGlobalConfig();

$pages = [];
$page = 1;

if (!empty($result)) {

     $currentTime = DateUtility::getCurrentDateTime();

     $result['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($result['sample_tested_datetime'] ?? '', true);
     $result['result_reviewed_datetime'] = DateUtility::humanReadableDateFormat($result['result_reviewed_datetime'] ?? $result['sample_tested_datetime'], true);
     $result['result_approved_datetime'] = DateUtility::humanReadableDateFormat($result['result_approved_datetime'] ?? '', true);
     $result['date_of_initiation_of_current_regimen'] = DateUtility::humanReadableDateFormat($result['date_of_initiation_of_current_regimen'] ?? '', true);
     $result['last_cd4_date'] = DateUtility::humanReadableDateFormat($result['last_cd4_date'] ?? '', true);
     $result['last_cd8_date'] = DateUtility::humanReadableDateFormat($result['last_cd8_date'] ?? '', true);

     $testedBy = $result['testedBy'] ?? null;
     $revisedBy = $result['revisedBy'] ?? null;

     $reviewedBy = $result['reviewedBy'] ?? null;
     if (empty($reviewedBy)) {
          $reviwerInfo = $usersService->getUserNameAndSignature($result['defaultReviewedBy']);
          $reviewedBy = $reviwerInfo['user_name'];
          $result['reviewedBySignature'] = $reviwerInfo['user_signature'];
     }

     $resultApprovedBy = $result['approvedBy'] ?? null;
     if (empty($resultApprovedBy)) {
          $approvedByInfo = $usersService->getUserNameAndSignature($result['defaultApprovedBy']);
          $resultApprovedBy = $approvedByInfo['user_name'];
          $result['approvedBySignature'] = $approvedByInfo['user_signature'];
     }

     if (empty($result['result_approved_datetime']) && !empty($result['sample_tested_datetime'])) {
          $result['result_approved_datetime'] = $result['sample_tested_datetime'];
     }

     if (empty($result['result_reviewed_datetime']) && !empty($result['sample_tested_datetime'])) {
          $result['result_reviewed_datetime'] = $result['sample_tested_datetime'];
     }


     $revisedBySignaturePath = $reviewedBySignaturePath = $testedBySignaturePath = $approvedBySignaturePath = null;

     if (!empty($result['testedBySignature'])) {
          $testedBySignaturePath = MiscUtility::getFullImagePath($result['testedBySignature'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature");
     }
     if (!empty($result['reviewedBySignature'])) {
          $reviewedBySignaturePath = MiscUtility::getFullImagePath($result['reviewedBySignature'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature");
     }
     if (!empty($result['approvedBySignature'])) {
          $approvedBySignaturePath = MiscUtility::getFullImagePath($result['approvedBySignature'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature");
     }
     if (!empty($result['revisedBySignature'])) {
          $revisedBySignaturePath = MiscUtility::getFullImagePath($result['revisedBySignature'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature");
     }

     $_SESSION['aliasPage'] = $page;
     if (!isset($result['labName'])) {
          $result['labName'] = '';
     }
     $draftTextShow = false;
     //Set watermark text
     if (!empty($mFieldArray)) {
          $counter = count($mFieldArray);
          for ($m = 0; $m < $counter; $m++) {
               if (!isset($result[$mFieldArray[$m]]) || trim((string) $result[$mFieldArray[$m]]) === '' || $result[$mFieldArray[$m]] == null || $result[$mFieldArray[$m]] == '0000-00-00 00:00:00') {
                    $draftTextShow = true;
                    break;
               }
          }
     }
     // create new PDF document
     $pdf = new VLResultPDFHelper(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
     if (MiscUtility::isImageValid(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
          $logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'];
     } else {
          $logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $arr['logo'];
     }
     $arr['training_mode_text'] = (isset($arr['training_mode']) && $arr['training_mode'] == 'yes') ? $arr['training_mode_text'] : null;
     $pdf->setHeading($logoPrintInPdf, $arr['header'], $result['labName'], $title = 'HIV VIRAL LOAD PATIENT REPORT', null, $arr['training_mode_text']);
     // set document information
     $pdf->SetCreator('InteLIS');
     $pdf->SetTitle('HIV Viral Load Patient Report');
     //$pdf->SetSubject('TCPDF Tutorial');
     //$pdf->SetKeywords('TCPDF, PDF, example, test, guide');

     // set default header data
     $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

     // set header and footer fonts
     $pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
     $pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);

     // set default monospaced font
     $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

     // set margins
     $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP + 14, PDF_MARGIN_RIGHT);
     $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
     $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

     // set auto page breaks
     $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

     // set image scale factor
     $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);



     // set font
     $pdf->SetFont('helvetica', '', 18);

     $pdf->AddPage();
     if (!isset($result['facility_code']) || trim((string) $result['facility_code']) === '') {
          $result['facility_code'] = '';
     }
     if (!isset($result['facility_state']) || trim((string) $result['facility_state']) === '') {
          $result['facility_state'] = '';
     }
     if (!isset($result['facility_district']) || trim((string) $result['facility_district']) === '') {
          $result['facility_district'] = '';
     }
     if (!isset($result['facility_name']) || trim((string) $result['facility_name']) === '') {
          $result['facility_name'] = '';
     }
     if (!isset($result['labName']) || trim((string) $result['labName']) === '') {
          $result['labName'] = '';
     }
     //Set Age
     $age = 'Unknown';
     if (isset($result['patient_dob']) && trim((string) $result['patient_dob']) !== '' && $result['patient_dob'] != '0000-00-00') {
          $todayDate = strtotime(date('Y-m-d'));
          $dob = strtotime((string) $result['patient_dob']);
          $difference = $todayDate - $dob;
          $seconds_per_year = 60 * 60 * 24 * 365;
          $age = round($difference / $seconds_per_year);
     } elseif (isset($result['patient_age_in_years']) && trim((string) $result['patient_age_in_years']) !== '' && trim((string) $result['patient_age_in_years']) > 0) {
          $age = $result['patient_age_in_years'];
     } elseif (isset($result['patient_age_in_months']) && trim((string) $result['patient_age_in_months']) !== '' && trim((string) $result['patient_age_in_months']) > 0) {
          if ($result['patient_age_in_months'] > 1) {
               $age = $result['patient_age_in_months'] . ' months';
          } else {
               $age = $result['patient_age_in_months'] . ' month';
          }
     }

     $result['sample_collection_date'] = DateUtility::humanReadableDateFormat($result['sample_collection_date'] ?? '', true);
     $result['sample_received_at_lab_datetime'] = DateUtility::humanReadableDateFormat($result['sample_received_at_lab_datetime'] ?? '', true);

     $result['result_printed_datetime'] = DateUtility::humanReadableDateFormat($result['result_printed_datetime'] ?? DateUtility::getCurrentDateTime(), true);

     if (isset($result['last_viral_load_date']) && trim((string) $result['last_viral_load_date']) !== '' && $result['last_viral_load_date'] != '0000-00-00') {
          $result['last_viral_load_date'] = date('d/M/Y', strtotime((string) $result['last_viral_load_date']));
          $result['last_viral_load_date'] = date('d/M/Y', strtotime($result['last_viral_load_date']));
     } else {
          $result['last_viral_load_date'] = '';
     }
     if (!isset($result['patient_gender']) || trim((string) $result['patient_gender']) === '') {
          $result['patient_gender'] = _translate('Unreported');
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
     } elseif ($result['result_status'] == REJECTED || $result['is_sample_rejected'] == 'yes') {
          $smileyContent = '<img src="/assets/img/cross.png" style="width:50px;" alt="rejected"/>';
     }

     if (isset($arr['show_smiley']) && trim((string) $arr['show_smiley']) === "no") {
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
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("REGION") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("DISTRICT") . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['facility_name']) . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['facility_code']) . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['facility_state']) . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['facility_district']) . '</td>';
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
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;"></td>';
     $html .= '</tr>';
     $html .= '<tr>';

     $patientFname = $result['patient_first_name'] ?? '';


     if (!empty($result['is_encrypted']) && $result['is_encrypted'] == 'yes') {
          $key = (string) $general->getGlobalConfig('key');
          $result['patient_art_no'] = $general->crypto('decrypt', $result['patient_art_no'], $key);
          if ($patientFname != '') {
               $patientFname = $general->crypto('decrypt', $patientFname, $key);
          }
     }

     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ucwords((string) $patientFname) . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['patient_art_no'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ucwords((string) $result['test_reason_name']) . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;"></td>';
     $html .= '</tr>';
     $html .= '</table>';
     $html .= '</td>';
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td colspan="3">';
     $html .= '<table style="padding:8px 2px 2px 2px;">';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("AGE") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SEX") . '</td>';
     if ($result['patient_gender'] == 'female') {
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("BREAST FEEDING") . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("PREGNANCY STATUS") . '</td>';
     } else {
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
     }
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $age . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ucwords(str_replace("_", " ", (string) $result['patient_gender'])) . '</td>';
     if ($result['patient_gender'] == 'female') {
          if (!empty($result['is_patient_breastfeeding'])) {
               $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ucwords(str_replace("_", " ", (string) $result['is_patient_breastfeeding'])) . '</td>';
          } else {
               $html .= '<td style="line-height:10px;font-size:10px;text-align:left;"> - </td>';
          }
          if (!empty($result['is_patient_pregnant'])) {
               $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ucwords(str_replace("_", " ", (string) $result['is_patient_pregnant'])) . '</td>';
          } else {
               $html .= '<td style="line-height:10px;font-size:10px;text-align:left;"> - </td>';
          }
     } else {
          $html .= '<td colspan="2" style="line-height:10px;font-size:10px;text-align:left;"></td>';
          $html .= '<td colspan="2" style="line-height:10px;font-size:10px;text-align:left;"></td>';
     }
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
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SAMPLE REJECTION STATUS") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SAMPLE TEST DATE") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("RESULT RELEASE DATE") . '</td>';
     $html .= '</tr>';
     $rejectedStatus = (!empty($result['is_sample_rejected']) && $result['is_sample_rejected'] == 'yes') ? _translate('Rejected') : _translate('Not Rejected');
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $rejectedStatus . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['sample_tested_datetime'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['result_printed_datetime'] . '</td>';
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SAMPLE TYPE") . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate($result['sample_name']) . '</td>';
     $html .= '</tr>';
     /* $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">LINE OF TREATMENT</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">DATE OF INITIAL OF TREATMENT</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">ARV ADHERENCE</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['line_of_treatment'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['date_of_initiation_of_current_regimen'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['arv_adherance_percentage'] . '</td>';
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">DURATION OF TREATMENT</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">LATEST CD4 RESULT</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">CD4 PERCENTAGE</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['treatment_duration_precise'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['last_cd4_result'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['last_cd4_percentage'] . '</td>';
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">LATEST CD4 DATE</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">LAST HIV-1 VIRAL LOAD</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">DATE</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['last_cd4_date'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['last_cd8_result'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['last_cd8_date'] . '</td>';
     $html .= '</tr>'; */

     $html .= '<tr>';
     $html .= '<td colspan="3">';
     $html .= '<table style="padding:10px 2px 2px 2px;">';
     $logValue = '';
     if ($result['result_value_log'] != '' && $result['result_value_log'] != null && ($result['reason_for_sample_rejection'] == '' || $result['reason_for_sample_rejection'] == null)) {
          $logValue = '&nbsp;&nbsp;' . _translate("Log Value") . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $result['result_value_log'];
     } elseif ($isResultNumeric) {
          $logV = round(log10($result['result']), 2);
          $logValue = '&nbsp;&nbsp;Log Value&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $logV;
     } else {
          $logValue = '';
     }
     $html .= '<tr style="background-color:#dbdbdb;"><td colspan="2" style="line-height:26px;font-size:12px;font-weight:bold;">&nbsp;&nbsp;Viral Load Result (copies/mL)&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . htmlspecialchars((string) $result['result']) . '<br>' . $logValue . '</td><td >' . $smileyContent . '</td></tr>';
     if ($result['reason_for_sample_rejection'] != '' && $result['is_sample_rejected'] == 'yes') {
          $corrective = '';
          if ($result["recommended_corrective_action_name"] != "") {
               $corrective = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ' . _translate("Recommended Corrective Action") . '&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;' . $result["recommended_corrective_action_name"];
          }
          $html .= '<tr><td colspan="3" style="line-height:26px;font-size:12px;font-weight:bold;text-align:left;">&nbsp;&nbsp;' . _translate("Rejection Reason") . '&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $result['rejection_reason_name'] . $corrective . '</td></tr>';
     }
     if (str_contains(strtolower((string) $result['instrument_machine_name']), 'abbott')) {
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:8px;font-size:8px;padding-top:8px;">' . _translate("Abbott Linear Detection range") . ': 839 copies/mL - 10 million copies/mL</td>';
          $html .= '</tr>';
     }
     //$html .= '<tr><td colspan="3"></td></tr>';
     $html .= '</table>';
     $html .= '</td>';
     $html .= '</tr>';
     if (trim((string) $showMessage) !== '') {
          $html .= '<tr>';
          //$html .= '<td colspan="3" style="line-height:13px;font-size:' . $messageTextSize . ';text-align:left;">' . $showMessage . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:16px;"></td>';
          $html .= '</tr>';
     }
     if (trim($tndMessage) !== '') {
          $html .= '<tr>';
          //  $html .= '<td colspan="3" style="line-height:13px;font-size:18px;text-align:left;">' . $tndMessage . '</td>';
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
     $html .= '<td colspan="3" style="line-height:15px;font-size:11px;font-weight:bold;">' . _translate("TEST PLATFORM") . ' &nbsp;&nbsp;:&nbsp;&nbsp; <span style="font-weight:normal;">' . ($result['instrument_machine_name']) . '</span></td>';
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
     //      $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Result of previous viral load(copies/mL)&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . $result['last_viral_load_result'] . '</span></td>';
     //      $html .= '</tr>';
     // }
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
     $html .= '</tr>';
     // $html .= '<tr>';
     // $html .= '<td colspan="3" style="line-height:8px;"></td>';
     // $html .= '</tr>';
     if ($result['is_sample_rejected'] == 'no' && (!empty($testedBy) && !empty($result['sample_tested_datetime']))) {
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("TESTED BY") . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SIGNATURE") . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("DATE") . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $testedBy . '</td>';
          if ($testedBySignaturePath !== null && $testedBySignaturePath !== '' && $testedBySignaturePath !== '0' && MiscUtility::isImageValid(($testedBySignaturePath))) {
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $testedBySignaturePath . '" style="width:50px;" /></td>';
          } else {
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
          }
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_tested_datetime'] . '</td>';
          $html .= '</tr>';
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
          if ($reviewedBySignaturePath !== null && $reviewedBySignaturePath !== '' && $reviewedBySignaturePath !== '0' && MiscUtility::isImageValid(($reviewedBySignaturePath))) {
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $reviewedBySignaturePath . '" style="width:50px;" /></td>';
          } else {
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
          }
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . (empty($result['result_reviewed_datetime']) ? $result['sample_tested_datetime'] : $result['result_reviewed_datetime']) . '</td>';
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
          if ($revisedBySignaturePath !== null && $revisedBySignaturePath !== '' && $revisedBySignaturePath !== '0' && MiscUtility::isImageValid($revisedBySignaturePath)) {
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $revisedBySignaturePath . '" style="width:70px;" /></td>';
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
          if ($approvedBySignaturePath !== null && $approvedBySignaturePath !== '' && $approvedBySignaturePath !== '0' && MiscUtility::isImageValid(($approvedBySignaturePath))) {
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $approvedBySignaturePath . '" style="width:50px;" /></td>';
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
     $html .= '<td style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_smile.png" alt="smile_face" style="width:10px;height:10px;"/> VL < 1000 copies/mL: ' . _translate("Continue on current regimen") . '</td>';
     $html .= '<td style="font-size:10px;text-align:left;">' . _translate("Printed on") . ' : ' . $printDate . '&nbsp;&nbsp;' . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="2" style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_frown.png" alt="frown_face" style="width:10px;height:10px;"/> VL >= 1000 copies/mL:  ' . _translate("Clinical and counselling action required") . '</td>';
     $html .= '</tr>';
     $html .= '</table>';
     $html .= '</td>';
     $html .= '</tr>';
     $html .= '</table>';

     if ($result['result'] != '' || ($result['result'] == '' && $result['result_status'] == REJECTED)) {
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
     if (isset($_POST['source']) && trim((string) $_POST['source']) === 'print') {
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
          $concat = ($sampleId !== '' && $sampleId !== '0' && ($patientId !== '' && $patientId !== '0')) ? ' and' : '';
          //Add event log
          $eventType = 'print-result';
          $action = $_SESSION['userName'] . ' generated the test result PDF with ' . $sampleId . $concat . $patientId;
          $resource = 'print-test-result';
          $data = ['event_type' => $eventType, 'action' => $action, 'resource' => $resource, 'date_time' => $currentTime];
          $db->insert($tableName1, $data);
          //Update print datetime in VL tbl.
          $vlQuery = "SELECT result_printed_datetime FROM form_vl as vl WHERE vl.vl_sample_id ='" . $result['vl_sample_id'] . "'";
          $vlResult = $db->query($vlQuery);
          if ($vlResult[0]['result_printed_datetime'] == null || trim((string) $vlResult[0]['result_printed_datetime']) === '' || $vlResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
               $db->where('vl_sample_id', $result['vl_sample_id']);
               $db->update($tableName2, ['result_printed_datetime' => $currentTime, 'result_dispatched_datetime' => $currentTime]);
          }
     }
}
