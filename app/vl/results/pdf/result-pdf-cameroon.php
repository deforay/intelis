<?php

// This file is included in /vl/results/generate-result-pdf.php
use const SAMPLE_STATUS\REJECTED;
use App\Services\VlService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\ResultPdfService;
use App\Helpers\PdfWatermarkHelper;
use App\Services\InstrumentsService;
use App\Registries\ContainerRegistry;
use App\Helpers\ResultPDFHelpers\VLResultPDFHelper;

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var InstrumentsService $instrumentsService */
$instrumentsService = ContainerRegistry::get(InstrumentsService::class);


/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

/** @var ResultPdfService $resultPdfService */
$resultPdfService = ContainerRegistry::get(ResultPdfService::class);


$globalConfig = $general->getGlobalConfig();

$key = (string) $general->getGlobalConfig('key');

$vlFacilityAttributes = null;

if (!empty($result)) {
     $result = MiscUtility::arrayEmptyStringsToNull($result);
     $displayPageNoInFooter = true;
     $displaySignatureTable = true;
     $reportTopMargin = 17;

     if (!empty($result['vl_facility_attributes'])) {
          $vlFacilityAttributes = json_decode((string) $result['vl_facility_attributes'], true);
          if (!empty($vlFacilityAttributes) && isset($vlFacilityAttributes['display_page_number_in_footer'])) {
               $displayPageNoInFooter = ($vlFacilityAttributes['display_page_number_in_footer']) == 'yes';
          }
          if (!empty($vlFacilityAttributes) && isset($vlFacilityAttributes['display_signature_table'])) {
               $displaySignatureTable = ($vlFacilityAttributes['display_signature_table']) == 'yes';
          }
          if (!empty($vlFacilityAttributes) && isset($vlFacilityAttributes['report_top_margin'])) {
               $reportTopMargin = $vlFacilityAttributes['report_top_margin'] ?? $reportTopMargin;
          }
     }

     $currentTime = DateUtility::getCurrentDateTime();

     $result['result_printed_datetime'] = DateUtility::humanReadableDateFormat($result['result_printed_datetime'] ?? $currentTime, true);
     $result['sample_collection_date'] = DateUtility::humanReadableDateFormat($result['sample_collection_date'] ?? '', true);
     $result['sample_received_at_lab_datetime'] = DateUtility::humanReadableDateFormat($result['sample_received_at_lab_datetime'] ?? '', true);
     $result['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($result['sample_tested_datetime'] ?? '', true);
     $result['result_reviewed_datetime'] = DateUtility::humanReadableDateFormat($result['result_reviewed_datetime'] ?? $result['sample_tested_datetime'], true);
     $result['result_approved_datetime'] = DateUtility::humanReadableDateFormat($result['result_approved_datetime'] ?? '', true);
     $result['last_viral_load_date'] = DateUtility::humanReadableDateFormat($result['last_viral_load_date'] ?? '', true);

     $reportTemplatePath = $resultPdfService->getReportTemplate($result['lab_id']);

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
     $pdf = new VLResultPDFHelper(orientation: PDF_PAGE_ORIENTATION, unit: PDF_UNIT, format: PDF_PAGE_FORMAT, unicode: true, encoding: 'UTF-8', diskCache: false, pdfTemplatePath: $reportTemplatePath, enableFooter: $displayPageNoInFooter);

     // Get the current page dimensions
     $page_width = $pdf->getPageWidth();
     $page_height = $pdf->getPageHeight();

     if (empty($reportTemplatePath)) {
          if (MiscUtility::isImageValid(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
               $logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'];
          } else {
               $logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $globalConfig['logo'];
          }
          // set default header data
          $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);
          $pdf->setHeading($logoPrintInPdf, $globalConfig['header'], $result['labName'], $title = 'HIV VIRAL LOAD PATIENT REPORT');
     }
     // set document information
     $pdf->SetCreator('VLSM');
     $pdf->SetTitle('HIV Viral Load Patient Report');

     // set header and footer fonts
     $pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
     $pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);

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
     $age = DateUtility::calculatePatientAge($result);

     if (!isset($result['patient_gender']) || trim((string) $result['patient_gender']) === '') {
          $result['patient_gender'] = 'Unreported';
     }
     $descriptionText = "";
     if (!empty($result['instrument_id'])) {
          $instrumentInfo = $instrumentsService->getSingleInstrument($result['instrument_id'], ['additional_text']);
          if ($instrumentInfo) {
               $descriptionText = isset($instrumentInfo['additional_text']) && !empty($instrumentInfo['additional_text']) ? $instrumentInfo['additional_text'] : '';
          }
     }
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

     $sameReviewerAndApprover = (!empty($reviewedBy) && $reviewedBy == $resultApprovedBy);

     $approvedBySignaturePath = null;

     if (!empty($result['approvedBySignature'])) {
          $approvedBySignaturePath =  MiscUtility::getFullImagePath($result['approvedBySignature'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature");
     }


     $smileyContent = '';
     $showMessage = '';
     $tndMessage = '';
     $messageTextSize = '15px';

     if (empty($result['vl_result_category']) || $result['vl_result_category'] == '') {
          $result['vl_result_category'] = $vlService->getVLResultCategory($result['result_status'], $result['result']);
     }

     if (isset($globalConfig['show_smiley']) && trim((string) $globalConfig['show_smiley']) === "no") {
          $smileyContent = '';
     } else {
          if (!empty($result['vl_result_category']) && $result['vl_result_category'] == 'suppressed') {
               $smileyContent = '<img src="/assets/img/smiley_smile.png" style="width:50px;" alt="smile_face"/>';
               $showMessage = $globalConfig['l_vl_msg'] ?? '';
          } elseif (!empty($result['vl_result_category']) && $result['vl_result_category'] == 'not suppressed') {
               $smileyContent = '<img src="/assets/img/smiley_frown.png" style="width:50px;" alt="frown_face"/>';
               $showMessage = $globalConfig['h_vl_msg'] ?? '';
          } elseif ($result['result_status'] == REJECTED || $result['is_sample_rejected'] == 'yes') {
               $smileyContent = '<img src="/assets/img/cross.png" style="width:50px;" alt="rejected"/>';
          }
          $smileyContent = "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;$smileyContent";
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

     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("PATIENT'S CONTACT", true) . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SEX", true) . '</td>';
     if ($result['patient_gender'] == 'female') {
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("BREASTFEEDING?", true) . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("PREGNANT?", true) . '</td>';
     } else {
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
     }
     $html .= '</tr>';
     $html .= '<tr>';

     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['patient_mobile_number'] ?? '-') . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate(_capitalizeFirstLetter($result['patient_gender']), true) . '</td>';
     if ($result['patient_gender'] == 'female') {
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . (_translate(_capitalizeFirstLetter($result['is_patient_breastfeeding']), true) ?? '-') . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . (_translate(_capitalizeFirstLetter($result['is_patient_pregnant']), true) ?? '-') . '</td>';
     } else {
          $html .= '<td colspan="2" style="line-height:10px;font-size:10px;text-align:left;"></td>';
          $html .= '<td colspan="2" style="line-height:10px;font-size:10px;text-align:left;"></td>';
     }

     $html .= '</tr>';
     $html .= '</table>';


     $html .= '<table style="padding:8px 4px 2px 2px;">';
     $html .= '<tr>';
     if (!empty($result['health_insurance_code'])) {
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("HEALTH INSURANCE CODE") . '</td>';
     }
     if (!empty($result['lab_assigned_code'])) {
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("LAB ASSIGNED CODE") . '</td>';
     }
     $html .= '</tr><tr>';

     if (!empty($result['health_insurance_code'])) {
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['health_insurance_code'] . '</td>';
     }
     if (!empty($result['lab_assigned_code'])) {
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['lab_assigned_code'] . '</td>';
     }

     $html .= '</tr></table>';


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
     $html .= '<td style="line-height:10px;font-size:10px;font-weight:bold;text-align:left;">' . _translate("TESTED BY") . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;font-weight:bold;text-align:left;">' . ($sameReviewerAndApprover === false ? _translate("REVIEWED BY") : '') . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate($result['sample_name']) . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $testedBy . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($sameReviewerAndApprover === false ? $reviewedBy : '') . '</td>';
     $html .= '</tr>';
     // $html .= '<tr>';
     // $html .= '<td colspan="3" style="line-height:10px;"></td>';
     // $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td colspan="3">';
     $html .= '<table style="padding:8px 2px 2px 2px;">';
     $logValue = '';
     if ($result['is_sample_rejected'] !== 'yes' && isset($globalConfig['vl_display_log_result']) && trim((string) $globalConfig['vl_display_log_result']) === "yes") {
          if ($result['result_value_log'] != '' && !empty($result['result_value_log'])) {
               $result['result_value_log'] = round($result['result_value_log'], 2);
               $logValue = '<br/>&nbsp;&nbsp;' . _translate("Log Value") . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $result['result_value_log'];
          } elseif (is_numeric($result['result'])) {
               $logV = round(log10($result['result']), 2);
               $logValue = '<br/>&nbsp;&nbsp;' . _translate("Log Value") . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $logV;
          } else {
               $logValue = '';
          }
     }
     $vlFinalResult = '&nbsp;&nbsp;' . _translate("Viral Load Result") . ' (copies/mL)&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' .
          htmlspecialchars((string) (is_numeric($result['result']) ? round($result['result']) : $result['result']));
     $html .= '<tr style="background-color:#dbdbdb;"><td colspan="2" style="line-height:26px;font-size:12px;font-weight:bold;">' . $vlFinalResult . $logValue . '</td><td >' . $smileyContent . '</td></tr>';
     if (!empty($result['reason_for_sample_rejection']) && $result['reason_for_sample_rejection'] != '') {
          $html .= '<tr><td colspan="3" style="line-height:26px;font-size:12px;font-weight:bold;text-align:left;">&nbsp;&nbsp;' . _translate("Rejection Reason") . '&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $result['rejection_reason_name'] . '</td></tr>';
     }
     if (str_contains(strtolower((string)$result['instrument_machine_name']), 'm2000')) {
          $html .= '<tr>';
          $html .= '<td colspan="3" style="font-size:10px;padding-top:10px;">' . _translate("Technique: Quantification of circulating HIV RNA by Abbott Real-Time RT-PCR (Sensitivity threshold 40 copies/mL for Plasma and 839 copies/mL for DBS)") . '</td>';
          $html .= '</tr>';
     }

     //$html .= '<tr><td colspan="3"></td></tr>';
     $html .= '</table>';
     $html .= '</td>';
     $html .= '</tr>';
     if (trim((string) $showMessage) !== '') {
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:10px;font-size:' . $messageTextSize . ';text-align:left;">' . $showMessage . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:14px;"></td>';
          $html .= '</tr>';
     }
     if (trim($tndMessage) !== '') {
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
     $modified = "No";
     if ($result['result_modified'] == "yes") {
          $modified = "Yes";

          $resultHistory = json_decode((string) $result['reason_for_result_changes']);
          $dateOfModified = $resultHistory->dateOfChange;
          $prevResult = $resultHistory->previousResult;
     }
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;font-weight:bold;text-align:left;">' . _translate("Was the result modified?") . ' : ' . _translate($modified) . '</td>';
     if ($modified === 'Yes' && ($result['reason_for_result_changes'] != "") && $resultHistory->dateOfChange != "") {
          $html .= '<td style="line-height:10px;font-size:10px;font-weight:bold;text-align:left;">' . _translate("Result Modification Date") . ' : ' . DateUtility::humanReadableDateFormat($dateOfModified) . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;font-weight:bold;text-align:left;">' . _translate("Previous Result") . ' : ' . $prevResult . ' cp/mL</td>';
     }
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:13px;font-size:10px;font-weight:bold;">' . _translate("TEST PLATFORM") . ' &nbsp;&nbsp;:&nbsp;&nbsp; <span style="font-weight:normal;">' . $result['instrument_machine_name'] . '</span></td>';
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:1px;border-bottom:2px solid #d3d3d3;"></td>';
     $html .= '</tr>';



     // Define the HTML block you want to position at the bottom
     $bottomHtml = '<table>';

     if (!empty($descriptionText) && $descriptionText != "") {
          $bottomHtml .= '<tr>
                              <td colspan="3" style="font-size:10px;text-align:left;">' . $descriptionText . '</td>';
          $bottomHtml .= '</tr>';
     } else {
          $bottomHtml .= '<tr><td colspan="3" style="font-size:10px;text-align:left;">';
          $bottomHtml .= '<u><strong>NB</strong></u> : ' . _translate("For a variation in Viral Load to be significant, the difference between two measurements must be at least 0.5 Log<sub>10</sub> or a reduction or increase of a factor of 3 in the number of copies/mL");
          $bottomHtml .= '</td></tr>';

          $bottomHtml .= '<tr><td colspan="3" style="line-height:2px;"></td></tr>';

          $bottomHtml .= '<tr><td colspan="3" style="font-size:11px;text-align:left;color:#808080;">(*)&nbsp;';
          $bottomHtml .= '<u><strong>' . _translate("Detection Limit (DL)") . '</strong></u> : ' . _translate("&lt; 40 (1.60 Log<sub>10</sub>) copies/mL  for Plasma and 839 (2.92 Log<sub>10</sub>) copies/mL for DBS");
          $bottomHtml .= '<br> &nbsp;&nbsp;&nbsp;&nbsp;';
          $bottomHtml .= '<u><strong>' . _translate("Quantification Limits (QL)") . '</strong></u> : ' .  _translate("Between 40 and 10,000000 (1.60 and 7.0 Log<sub>10</sub>) copies/mL for Plasma and 839 and 10,000000 (2.92 and 7.0 Log<sub>10</sub>) copies/mL for DBS");
          $bottomHtml .= '</td></tr>';
     }

     $bottomHtml .= '<tr><td colspan="3" style="line-height:2px;"></td></tr>';

     $bottomHtml .= '<tr>';
     $bottomHtml .= '<td colspan="3" style="line-height:2px;font-size:2em;border-bottom:2px solid #d3d3d3;padding-"><br><br></td>';
     $bottomHtml .= '</tr>';
     $bottomHtml .= '<tr>';
     $bottomHtml .= '<td colspan="3">';
     $bottomHtml .= '<table>';
     $bottomHtml .= '<tr>';
     $bottomHtml .= '<td style="font-size:10px;text-align:left;width:75%;"><img src="/assets/img/smiley_smile.png" alt="smile_face" style="width:8px;height:8px;"/> VL < 1000 copies/mL: ' . _translate("Continue on current regimen") . '</td>';
     $bottomHtml .= '<td style="font-size:10px;text-align:left;">' . _translate("Printed on") . ' : ' . $printDate . '&nbsp;&nbsp;' . '</td>';
     $bottomHtml .= '</tr>';
     $bottomHtml .= '<tr>';
     $bottomHtml .= '<td colspan="2" style="font-size:10px;text-align:left;width:75%;">
                              <img src="/assets/img/smiley_frown.png" alt="frown_face" style="width:8px;height:8px;"/> VL >= 1000 copies/mL: ' . _translate("Clinical and counselling action required") .
          '</td>';
     $bottomHtml .= '</tr>';
     $bottomHtml .= '</table>';
     $bottomHtml .= '</td>';
     $bottomHtml .= '</tr>';
     $bottomHtml .= '</table>';

     $bottomTextLocation = !empty($vlFacilityAttributes) && !empty($vlFacilityAttributes->bottom_text_location) ? $vlFacilityAttributes->bottom_text_location : 'belowPlatformName';

     if ($bottomTextLocation == 'belowPlatformName') {
          // Use writeHTMLCell() to position the bottom HTML content
          $html .= '<tr><td colspan="5">' . $bottomHtml . '</td></tr><tr><td colspan="5">&nbsp;</td></tr>';
     }


     if ($displaySignatureTable && (!empty($resultApprovedBy) && !empty($result['result_approved_datetime']) && $displaySignatureTable)) {
         $approvertext = $sameReviewerAndApprover ? _translate("REVIEWED AND APPROVED BY") : _translate("APPROVED BY");
         $approvedBySignatureExists = $approvedBySignaturePath !== null && $approvedBySignaturePath !== '' && $approvedBySignaturePath !== '0' && MiscUtility::isImageValid($approvedBySignaturePath);
         $html .= '<tr>';
         $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . $approvertext . '</td>';
         $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SIGNATURE") . '</td>';
         $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("DATE") . '</td>';
         $html .= '</tr>';
         $html .= '<tr>';
         $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $resultApprovedBy . '</td>';
         if ($approvedBySignatureExists) {
              $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $approvedBySignaturePath . '" style="width:100px;" /></td>';
         } else {
              $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
         }
         $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['result_approved_datetime'] . '</td>';
         $html .= '</tr>';
         $html .= '<tr>';
         $html .= '<td colspan="3" style="line-height:2px;"></td>';
         $html .= '</tr>';
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
     $html .= '</table>';

     if ($result['result'] != '' || (empty($result['result']) && $result['result_status'] == REJECTED)) {

          $pdf->writeHTML($html);

          if ($bottomTextLocation == 'aboveFooter') {

               // Calculate where to start the bottom HTML
               $margin_bottom = 10; // Adjust based on the height of your footer
               $bottom_content_height = 50; // Adjust based on your content's estimated height
               $start_y = $page_height - $bottom_content_height - $margin_bottom;
               $pdf->writeHTMLCell(0, 0, null, $start_y, $bottomHtml, 0, 1, 0, true, 'L', true);
          }

          $pdf->lastPage();

          $filename = $pathFront . DIRECTORY_SEPARATOR . 'p' . $page . '.pdf';

          if ($draftTextShow) {
               //Watermark section
               $watermark = new PdfWatermarkHelper();
               $watermark->setFullPathToFile($filename);
               $fullPathToFile = $filename;
               $watermark->Output($filename, "F");
          } else {
               $pdf->Output($filename, "F");
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
