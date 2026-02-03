<?php

// This file is included in /vl/results/generate-result-pdf.php
use const SAMPLE_STATUS\REJECTED;
use App\Services\TbService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Helpers\PdfWatermarkHelper;
use App\Registries\ContainerRegistry;
use App\Helpers\ResultPDFHelpers\CountrySpecificHelpers\SierraLeoneTBResultPDFHelper;

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);

$pages = [];
$page = 1;

if (!empty($result)) {

     $tbXPertResults = $tbService->getTbResults('x-pert');
     $countryFormId = (int) $general->getGlobalConfig('vl_form');

     $result['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($result['sample_tested_datetime'] ?? '', true);
     $result['result_reviewed_datetime'] = DateUtility::humanReadableDateFormat($result['result_reviewed_datetime'] ?? '', true);
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

     $revisedBySignaturePath = $reviewedBySignaturePath = $testedBySignaturePath = null;
     if (!empty($result['testedBySignature'])) {
          $testedBySignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $result['testedBySignature'];
     }
     if (!empty($result['reviewedBySignature'])) {
          $reviewedBySignaturePath = MiscUtility::getFullImagePath($result['reviewedBySignature'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature");
     }
     if (!empty($result['approvedBySignature'])) {
          $approvedBySignaturePath = MiscUtility::getFullImagePath($result['approvedBySignature'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature");
     }
     if (!empty($result['reviewedBySignature'])) {
          $revisedBySignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $result['reviewedBySignature'];
     }


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
     $pdf = new SierraLeoneTBResultPDFHelper(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
     if (MiscUtility::isImageValid(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
          $logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'];
     } else {
          $logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $arr['logo'];
     }
     $arr['training_mode_text'] = (isset($arr['training_mode']) && $arr['training_mode'] == 'yes') ? $arr['training_mode_text'] : null;
     $pdf->setHeading($logoPrintInPdf, $arr['header'], $result['labName'], $title = 'HIV VIRAL LOAD PATIENT REPORT', null, $arr['training_mode_text']);
     // set document information
     $pdf->SetCreator('InteLIS');
     $pdf->SetTitle('BURKINA FASO TB SAMPLES REFERRAL SYSTEM (SS)');
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
     } elseif (isset($result['patient_age']) && trim((string) $result['patient_age']) !== '' && trim((string) $result['patient_age']) > 0) {
          $age = $result['patient_age'];
     }

     $result['patient_dob'] = DateUtility::humanReadableDateFormat($result['patient_dob'] ?? '', false);
     $result['sample_collection_date'] = DateUtility::humanReadableDateFormat($result['sample_collection_date'] ?? '', true);
     $result['sample_received_at_lab_datetime'] = DateUtility::humanReadableDateFormat($result['sample_received_at_lab_datetime'] ?? '', true);

     $result['result_printed_datetime'] = DateUtility::humanReadableDateFormat($result['result_printed_datetime'] ?? DateUtility::getCurrentDateTime(), true);

     if (!isset($result['patient_gender']) || trim((string) $result['patient_gender']) === '') {
          $result['patient_gender'] = _translate('Unreported');
     }
     $smileyContent = '';
     $showMessage = '';
     $tndMessage = '';
     $messageTextSize = '15px';

     if ($result['result_status'] == REJECTED || $result['is_sample_rejected'] == 'yes') {
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
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("PATIENT ID") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("DOB") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("AGE") . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';

     if (!empty($result['is_encrypted']) && $result['is_encrypted'] == 'yes') {
          $key = (string) $general->getGlobalConfig('key');
          $result['patient_id'] = $general->crypto('decrypt', $result['patient_id'], $key);
          $patientFname = $general->crypto('decrypt', $patientFname, $key);
          $patientLname = $general->crypto('decrypt', $patientLname, $key);
     }
     if (isset($tbInfo['reason_for_tb_test']) && !empty($tbInfo['reason_for_tb_test'])) {
          $reasonForTbTest = json_decode((string) $tbInfo['reason_for_tb_test']);
          $diagnosis = (array) $reasonForTbTest->elaboration->diagnosis;
          $followup = (array) $reasonForTbTest->elaboration->followup;
     }

     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $patientFname . ' ' . $patientLname . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['patient_id'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['patient_dob'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $age . '</td>';
     $html .= '</tr>';
     $html .= '</table>';
     $html .= '</td>';
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td colspan="3">';
     $html .= '<table style="padding:8px 2px 2px 2px;">';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SEX") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("WEIGHT(kg)") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("DISPLACED POPULATION") . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("COMMUNITY ACTOR") . '</td>';
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ucwords(str_replace("_", " ", (string) $result['patient_gender'])) . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['patient_weight'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ucwords((string) $result['is_displaced_population']) . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ucwords((string) $result['is_referred_by_community_actor']) . '</td>';
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
     $rejectedStatus = (!empty($result['is_sample_rejected']) && $result['is_sample_rejected'] == 'yes') ? _translate("Rejected") : _translate("Not Rejected");
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $rejectedStatus . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['sample_tested_datetime'] . '</td>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['result_printed_datetime'] . '</td>';
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SAMPLE TYPE") . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['sample_name']) . '</td>';
     $html .= '</tr>';

     if (!empty($tbTestInfo)) {
          /* Test Result Section */
          $html .= '<tr>';
          $html .= '<td colspan="4">';
          $html .= '<table style="padding:2px;">
                  <tr><th colspan="3" style="border:1px solid #ddd;line-height:20px;font-size:11px;font-weight:bold;text-align:center;">' . _translate("Microscopy Test Results") . '</th></tr>
                  <tr>
                      <td align="center" width="10%" style="border:1px solid #ddd;line-height:20px;font-size:11px;font-weight:bold;">' . _translate("No AFB") . '</td>
                      <td align="center" width="50%" style="border:1px solid #ddd;line-height:20px;font-size:11px;font-weight:bold;">' . _translate("Result") . '</td>
                      <td align="center" width="40%" style="border:1px solid #ddd;line-height:20px;font-size:11px;font-weight:bold;">' . _translate("Actual Number") . '</td>
                  </tr>';

          foreach ($tbTestInfo as $indexKey => $rows) {
               $html .= '<tr>
                      <td align="center" style="border:1px solid #ddd;line-height:20px;font-size:11px;font-weight:normal;">' . ($indexKey + 1) . '</td>
                      <td align="center" style="border:1px solid #ddd;line-height:20px;font-size:11px;font-weight:normal;">' . $rows['test_result'] . '</td>
                      <td align="center" style="border:1px solid #ddd;line-height:20px;font-size:11px;font-weight:normal;">' . $rows['actual_no'] . '</td>
                  </tr>';
          }
          $html .= '</table>';
          $html .= '</td>';
          $html .= '</tr>';
     }
     $html .= '<tr>';
     $html .= '<td colspan="3">';
     $html .= '<table style="padding:10px 2px 2px 2px;">';
     $logValue = '';

     $html .= '<tr style="background-color:#dbdbdb;"><td colspan="2" style="line-height:26px;font-size:12px;font-weight:bold;width:90%;">&nbsp;&nbsp;' . _translate("XPERT MTB Result") . '&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . ucwords((string) $tbXPertResults[$result['xpert_mtb_result']]) . '</td><td style="width:10%;">' . $smileyContent . '</td></tr>';
     if ($result['reason_for_sample_rejection'] != '' && $result['is_sample_rejected'] == 'yes') {
          $html .= '<tr><td colspan="3" style="line-height:26px;font-size:12px;font-weight:bold;text-align:left;">&nbsp;&nbsp;' . _translate("Rejection Reason") . '&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $result['rejection_reason_name'] . '</td></tr>';
     }
     $html .= '</table>';
     $html .= '</td>';
     $html .= '</tr>';

     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
     $html .= '</tr>';

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
          if (!empty($approvedBySignaturePath) && MiscUtility::isImageValid(($approvedBySignaturePath))) {
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $approvedBySignaturePath . '" style="width:50px;" /></td>';
          } else {
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
          }

          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['result_approved_datetime'] . '</td>';
          $html .= '</tr>';
     }

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

     $html .= '<tr>';
     $html .= '<td colspan="3">';
     $html .= '<table>';
     $html .= '<tr>';
     $html .= '<td style="font-size:10px;text-align:center;">' . _translate("Printed on") . ' : ' . $printDate . '&nbsp;&nbsp;' . '</td>';
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
          $sampleId = (isset($result[$sampleCode]) && !empty($result[$sampleCode])) ? " " . _translate("Sample ID") . " " . $result[$sampleCode] : '';
          $patientId = (isset($result['patient_id']) && !empty($result['patient_id'])) ? " " . _translate("Patient ID") . " " . $result['patient_id'] : '';
          $concat = ($sampleId !== '' && $sampleId !== '0' && ($patientId !== '' && $patientId !== '0')) ? ' and' : '';
          //Add event log
          $eventType = 'print-result';
          $action = $_SESSION['userName'] . " " . _translate("generated the test result PDF with") . " " . $sampleId . $concat . $patientId;
          $resource = 'print-test-result';
          $data = ['event_type' => $eventType, 'action' => $action, 'resource' => $resource, 'date_time' => $currentDateTime];
          $db->insert($tableName1, $data);
          //Update print datetime in VL tbl.
          $vlQuery = "SELECT result_printed_datetime FROM $tableName2 as vl WHERE vl.tb_id ='" . $result['tb_id'] . "'";
          $vlResult = $db->query($vlQuery);
          if ($vlResult[0]['result_printed_datetime'] == null || trim((string) $vlResult[0]['result_printed_datetime']) === '' || $vlResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
               $db->where('tb_id', $result['tb_id']);
               $db->update($tableName2, ['result_printed_datetime' => $currentDateTime, 'result_dispatched_datetime' => $currentDateTime]);
          }
     }
}
