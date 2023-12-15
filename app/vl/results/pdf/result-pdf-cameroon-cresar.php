<?php

// This file is included in /vl/results/generate-result-pdf.php


use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Helpers\PdfWatermarkHelper;
use App\Helpers\PdfConcatenateHelper;
use App\Registries\ContainerRegistry;
use App\Helpers\ResultPDFHelpers\VLResultPDFHelper;

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$resultFilename = '';

if (!empty($requestResult)) {
     $_SESSION['rVal'] = $general->generateRandomString(6);

     $pathFront = TEMP_PATH . DIRECTORY_SEPARATOR .  $_SESSION['rVal'];
     MiscUtility::makeDirectory($pathFront);


     $pages = [];
     $page = 1;
     foreach ($requestResult as $result) {
          $currentTime = DateUtility::getCurrentDateTime();

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
               $testUserSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $testedByRes['user_signature'];
          }
          if (!empty($reviewedByRes['user_signature'])) {
               $reviewedSignaturePath = $reviewedByRes['user_signature'];
          }
          if (!empty($revisedByRes['user_signature'])) {
               $revisedSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $revisedByRes['user_signature'];
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
               //$userSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $userRes['user_signature'];
               $userSignaturePath = $userRes['user_signature'];
          }
          $_SESSION['aliasPage'] = $page;
          if (empty($result['labName'])) {
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
          //$pdf->writeHTMLCell(0, 0, 10, $fourthHeading, 'B.P. 7039; stewardship crossroads', 0, 0, 0, true, 'C');

          // create new PDF document
          $pdf = new VLResultPDFHelper(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

          if ($pdf->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
               $logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'];
          } else {
               $logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $arr['logo'];
          }
          $arr['training_mode_text'] = (isset($arr['training_mode']) && $arr['training_mode'] == 'yes') ? $arr['training_mode_text'] : null;
          // $pdf->setHeading($logoPrintInPdf, null, null, null, null, $arr['training_mode_text']);

          // set header and footer fonts
          $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
          $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

          // set default monospaced font
          $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

          // set margins
          $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP - 12, PDF_MARGIN_RIGHT);
          //$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
          $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

          // set auto page breaks
          $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

          // set image scale factor
          $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

          // set font
          $pdf->SetFont('helvetica', '', 16);


          $pdf->AddPage();
          $logo = '<img style="width:96px;" src="' . $logoPrintInPdf . '" />';

          //$htmlTitle = "<div style='width: 50px; height:50px;border:1px solid;'>CRESAR<br>RESEARCH CENTER FOR ARMY HEALTH<br>MILITARY HEALTH RESEARCH CENTER<br><span>B.P. 7039; stewardship crossroads</span></div>";
          $header = '<table style="padding:4px 2px 2px 2px;width:100%; border:1px solid black">';
          $header .= '<tr>';
          $header .= '<td style="text-align:center;" width="15%">' . $logo . '</td>';
          $header .= '<td width="85%">' . $result['labName'] . "<br><span style='font-weight:bold;font-size:5px;'>CENTRE DE RECHERCHE POUR LA SANTÉ DES ARMÉES<br>MILITARY HEALTH RESEARCH CENTER</span><br><span style='font-size:10px;'>B.P.7039;carrefour de l'intendance : Tel : 222229161</span></td>";
          $header .= '</tr>';
          $header .= '</table>';
          $pdf->writeHTML($header, true, false, true, false, 'C');
          // $pdf->writeHTMLCell(0, 0, 15, 28, $htmlTitle, 0, 0, 0, true, 'C');
          //$pdf->writeHTMLCell(0, 0, 15, 30, '<hr>', 0, 0, 0, true, 'C');

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

          //Set Age
          $age = 'Unknown';
          if (isset($result['patient_dob']) && trim((string) $result['patient_dob']) != '' && $result['patient_dob'] != '0000-00-00') {
               $todayDate = strtotime(date('Y-m-d'));
               $dob = strtotime((string) $result['patient_dob']);
               $difference = $todayDate - $dob;
               $seconds_per_year = 60 * 60 * 24 * 365;
               $age = round($difference / $seconds_per_year);
          } elseif (isset($result['patient_age_in_years']) && trim((string) $result['patient_age_in_years']) != '' && trim((string) $result['patient_age_in_years']) > 0) {
               $age = $result['patient_age_in_years'];
          } elseif (isset($result['patient_age_in_months']) && trim((string) $result['patient_age_in_months']) != '' && trim((string) $result['patient_age_in_months']) > 0) {
               if ($result['patient_age_in_months'] > 1) {
                    $age = $result['patient_age_in_months'] . ' months';
               } else {
                    $age = $result['patient_age_in_months'] . ' month';
               }
          }

          if (isset($result['sample_collection_date']) && trim((string) $result['sample_collection_date']) != '' && $result['sample_collection_date'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $result['sample_collection_date']);
               $result['sample_collection_date'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
          } else {
               $result['sample_collection_date'] = '';
          }

          if (isset($result['sample_received_at_lab_datetime']) && trim((string) $result['sample_received_at_lab_datetime']) != '' && $result['sample_received_at_lab_datetime'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $result['sample_received_at_lab_datetime']);
               $result['sample_received_at_lab_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
          } else {
               $result['sample_received_at_lab_datetime'] = "";
          }
          $sampleDispatchDate = '';
          $sampleDispatchTime = '';
          if (isset($result['result_printed_datetime']) && trim((string) $result['result_printed_datetime']) != '' && $result['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $result['result_printed_datetime']);
               $result['result_printed_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
          } else {
               $expStr = explode(" ", $currentTime);
               $sampleDispatchDate = date('d/M/Y', strtotime($expStr[0]));
               $sampleDispatchTime = $expStr[1];
          }

          $modified = _translate("No");
          if ($result['result_modified'] == "yes") {
               $modified = _translate("Yes");
          }

          $finalDate = date('d-m-Y', strtotime('+1 day', strtotime((string) $result['sample_tested_datetime'])));

          if (isset($result['sample_tested_datetime']) && trim((string) $result['sample_tested_datetime']) != '' && $result['sample_tested_datetime'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $result['sample_tested_datetime']);
               $result['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
          } else {
               $result['sample_tested_datetime'] = '';
          }

          if (isset($result['treatment_initiated_date']) && trim((string) $result['treatment_initiated_date']) != '' && $result['treatment_initiated_date'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $result['treatment_initiated_date']);
               $result['treatment_initiated_date'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
          } else {
               $result['treatment_initiated_date'] = '';
          }

          if (isset($result['last_modified_datetime']) && trim((string) $result['last_modified_datetime']) != '' && $result['last_modified_datetime'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $result['last_modified_datetime']);
               $result['last_modified_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
          } else {
               $result['last_modified_datetime'] = '';
          }


          if (isset($result['result_reviewed_datetime']) && trim((string) $result['result_reviewed_datetime']) != '' && $result['result_reviewed_datetime'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", (string) $result['result_reviewed_datetime']);
               $result['result_reviewed_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
          } else {
               $result['result_reviewed_datetime'] = '';
          }

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

          //$html .= '<td colspan="3">'._translate('').'</td>';
          //stewardship crossroads


          $html .= '<tr>';

          $html .= '<td colspan="3">';
          $patientFname = $result['patient_first_name'] ?? '';
          if (!empty($result['is_encrypted']) && $result['is_encrypted'] == 'yes') {
               $key = (string) $general->getGlobalConfig('key');
               $result['patient_art_no'] = $general->crypto('decrypt', $result['patient_art_no'], $key);
               $patientFname = $general->crypto('decrypt', $patientFname, $key);
          }


          $logValue = '';
          if ($result['result_value_log'] != '' && $result['result_value_log'] != null && ($result['reason_for_sample_rejection'] == '' || $result['reason_for_sample_rejection'] == null)) {
               $logValue = '&nbsp;' . $result['result_value_log'];
          } else {
               if ($isResultNumeric) {
                    $logV = round(log10($result['result']), 2);
                    $logValue = '&nbsp;&nbsp;' . $logV;
               } else {
                    $logValue = '';
               }
          }

          $html = '<h5 align="center"><u>' . _translate('HIV VIRAL LOAD RESULT SHEET') . '</u></h5>';
          $html .= '<p><u>' . _translate("Patient Information") . '</u></p>';
          $html .= '<table style="width:100%;">';
          $html .= '<tr>';
          $html .= '<td colspan="3">';
          $html .= '<table border="1" cellpadding="8" cellspacing="0">';
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . _translate("Unique Code") . " : " . $result['patient_art_no'] . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . _translate("Region") . " : " . $result['facility_state'] . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate("Patient Name") . " : " . $patientFname . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate("Age") . " : " . $age . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . _translate("Sex : ") . (str_replace("_", " ", (string) $result['patient_gender'])) . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' .  _translate("Type of Sample") . " : " . $result['sample_name'] . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate("Contact") . " : " . $result['patient_mobile_number'] . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate("Date of Sample Collection") . " : " . $result['sample_collection_date'] . " " . $sampleCollectionTime . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate("Collected By") . " : " . $result['facility_name'] . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' .  _translate("Name of Requestor") . " : " . $result['request_clinician_name'] . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate("Contact of Requestor") . " : " . $result['request_clinician_phone_number'] . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate('Treatment center') . " : " . ($result['facility_name']) . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate('ARV initiation date') . " : " . ($result['treatment_initiated_date']) . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate('Modified') . " : " . ($modified) . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate('Modification date') . " : " . ($result['last_modified_datetime']) . '</td>';
          $html .= '</tr>';
          $html .= '</table>';

          $html .= '<br><br>';

          $html .= '<table border="0">';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate('Sample Received Date') . ' : </td>';
          $html .= '<td style="line-height:20px;font-size:10px;text-align:left; border: 1px solid black">&nbsp;' . $result['sample_received_at_lab_datetime'] . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:right;">' . _translate('Viral Load Serial Number') . ' : &nbsp;</td>';
          $html .= '<td style="line-height:20px;font-size:10px;text-align:left; border: 1px solid black">&nbsp;' . ($result['cv_number']) . '</td>';

          $html .= '</tr>';
          $html .= '<tr><td></td></tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate('Test Name') . ' : </td>';
          $html .= '<td style="line-height:20px;font-size:10px;text-align:left; border: 1px solid black">&nbsp;' . $result['vl_test_platform'] . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:right;">' . _translate('Test Date') . ' : &nbsp;</td>';
          $html .= '<td style="line-height:20px;font-size:10px;text-align:left; border: 1px solid black">&nbsp;' . $result['sample_tested_datetime'] . '</td>';
          $html .= '</tr>';

          $html .= '<tr><td></td></tr>';
          $html .= '<tr>';
          $html .= '<td width="15%" style="line-height:10px;font-size:10px;text-align:left;">' . _translate('Result') . ' (copies/ml) : </td>';
          $html .= '<td width="10%" style="line-height:15px;font-size:10px;text-align:left; border: 1px solid black">&nbsp;' . htmlspecialchars((string) $result['result']) . '</td>';
          $html .= '<td width="20%" style="line-height:10px;font-size:10px;text-align:right;">' . _translate('Results (log)') . ' : &nbsp;</td>';
          $html .= '<td width="10%" style="line-height:15px;font-size:10px;text-align:left; border: 1px solid black">&nbsp;' . $logValue . '</td>';
          $html .= '<td width="20%" style="line-height:10px;font-size:10px;text-align:right;">' . _translate('Test Done By') . ' : &nbsp;</td>';
          $html .= '<td width="25%" style="line-height:15px;font-size:10px;text-align:left; padding-left:5px !important; border: 1px solid black">&nbsp;' . $result['labName'] . '</td>';

          $html .= '</tr>';
          $html .= '<tr><td width="100%" style="line-height:18px;font-size:10px;">';
          $html .= '<br>';
          $html .= '<p>' . "N.B : Le test Abbott 0.6ml a une limite inférieure de quantification de 40 copies/ml et une limite supérieure de quantification de 10.000.000 copies/ml. Pour tout résultat entre ces limites, le nombre de copies par ml est signalé. Lorsque le virus est détecté au dessus ou en dessous des limites de quantification, ceci est signalé. Si le test est réussi et qu'aucun virus n'est détecté, le résultat est signalé comme cible non détectée. Pour qu'une variation de la charge virale soit significative, il faut que la différence entre deux mesures soit d'au moins 0.5 Log10 soit une réduction ou une augmentation d'un facteur de 3 du nombre de copies/ml" . '</p>';
          $html .= '<table border="0" style="margin-top:24px;">';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate('Comment, if applicable') . ' : </td>';
          $html .= '<td style="line-height:20px;font-size:10px;text-align:left; border: 1px solid black">&nbsp;' . $result['lab_tech_comments'] . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:right;">' . _translate('Date') . ' : </td>';
          $html .= '<td style="line-height:20px;font-size:10px;text-align:left; border: 1px solid black">&nbsp;' . $finalDate . '</td>';

          $html .= '</tr>';
          $html .= '<tr><td></td></tr>';

          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate('Validated by') . ' : ' . $reviewedBy  . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:right;">' . _translate('Authorized by') . ' : ' . $resultApprovedBy . '</td></tr>';

          if (!empty($reviewedSignaturePath) && $pdf->imageExists($reviewedSignaturePath)) {
               $signImg = '<img src="' . $reviewedSignaturePath . '" style="width:50px;" />';
          } else {
               $signImg = '';
          }

          if (!empty($userSignaturePath) && $pdf->imageExists($userSignaturePath)) {
               $signImgApproved = '<img src="' . $reviewedSignaturePath . '" style="width:50px;" />';
          } else {
               $signImgApproved = '';
          }

          $html .= '<tr><td></td></tr>';
          if ($reviewedBy != $resultApprovedBy) {

               if (!empty($signImg)) {
                    $html .= '<tr><td style="line-height:10px;font-size:10px;text-align:left;">' . _translate('Signature') . ' : ' . $signImg  . '</td>';
               } else {
                    $html .= '<tr><td style="line-height:10px;font-size:10px;text-align:left;"></td>';
               }

               $html .= '<td style="line-height:10px;font-size:10px;text-align:right;">' . _translate('Signature') . ' : ' . $signImgApproved  . '</td>';
          } else {
               if (!empty($signImg)) {
                    $html .= '<tr><td style="line-height:10px;font-size:10px;text-align:left;">' . _translate('Signature') . ' : ' . $signImg  . '</td>';
               } else {
                    $html .= '<tr><td style="line-height:10px;font-size:10px;text-align:left;"></td>';
               }
          }
          $html .= '</tr>';

          $html .= '</table>';

          $html .= '</td></tr>';
          $html .= '</table>';

          $html .= '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3">';
          $html .= '<table style="padding:4px 2px 2px 2px;">';

          $html .= '</table>';


          // if ($result['result'] != '' || ($result['result'] == '' && $result['result_status'] == '4')) {
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
          // }
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
     if (!empty($pages)) {
          $resultPdf = new PdfConcatenateHelper();
          $resultPdf->setFiles($pages);
          $resultPdf->setPrintHeader(false);
          $resultPdf->setPrintFooter(false);
          $resultPdf->concat();
          $resultFilename = 'VLSM-VL-Test-result-' . date('d-M-Y-H-i-s') . "-" . $general->generateRandomString(6) . '.pdf';
          $resultPdf->Output(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename, "F");
          MiscUtility::removeDirectory($pathFront);
          unset($_SESSION['rVal']);
     }
}

echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename);
