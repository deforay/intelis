<?php

// This file is included in /vl/results/generate-result-pdf.php


use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Helpers\PdfWatermarkHelper;
use App\Helpers\PdfConcatenateHelper;
use App\Registries\ContainerRegistry;

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
               $reviewedSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $reviewedByRes['user_signature'];
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
                    $userSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $resultApprovedByRes['user_signature'];
               }
          }

          if (isset($result['approvedBy']) && trim($result['approvedBy']) != '') {
               $resultApprovedBy = ($result['approvedBy']);
               $userRes = $usersService->getUserInfo($result['result_approved_by'], 'user_signature');
          } else {
               $resultApprovedBy  = '';
          }

          $userSignaturePath = null;
          if (!empty($userRes['user_signature'])) {
               $userSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $userRes['user_signature'];
          }
          $_SESSION['aliasPage'] = $page;
          if (empty($result['labName'])) {
               $result['labName'] = '';
          }
          $draftTextShow = false;
          //Set watermark text
          if (!empty($mFieldArray)) {
               for ($m = 0; $m < count($mFieldArray); $m++) {
                    if (!isset($result[$mFieldArray[$m]]) || trim($result[$mFieldArray[$m]]) == '' || $result[$mFieldArray[$m]] == null || $result[$mFieldArray[$m]] == '0000-00-00 00:00:00') {
                         $draftTextShow = true;
                         break;
                    }
               }
          }
          //$pdf->writeHTMLCell(0, 0, 10, $fourthHeading, 'B.P. 7039; stewardship crossroads', 0, 0, 0, true, 'C');

          // create new PDF document
          $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

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
          $pdf->SetFont('helvetica', '', 18);


          $pdf->AddPage();
          $logo = '<img style="width:100px;" src="' . $logoPrintInPdf . '" />';

          //$htmlTitle = "<div style='width: 50px; height:50px;border:1px solid;'>CRESAR<br>RESEARCH CENTER FOR ARMY HEALTH<br>MILITARY HEALTH RESEARCH CENTER<br><span>B.P. 7039; stewardship crossroads</span></div>";
          $header = '<table style="padding:4px 2px 2px 2px;width:100%; border:1px solid black">';
          $header .= '<tr>';
          $header .= '<td style="line-height:30px;text-align:center;padding-top:20px;" width="15%">' . $logo . '</td>';
          $header .= '<td width="85%">' . $result['labName'] . "<br><span style='font-weight:bold;font-size:10px;'>CENTRE DE RECHERCHE POUR LA SANTÉ DES ARMÉES<br>MILITARY HEALTH RESEARCH CENTER</span><br><span style='font-size:10px;'>B.P.7039;carrefour de l'intendance : Tel : 222229161</span></td>";
          $header .= '</tr>';
          $header .= '</table>';
          $pdf->writeHTML($header, true, false, true, false, 'C');
          // $pdf->writeHTMLCell(0, 0, 15, 28, $htmlTitle, 0, 0, 0, true, 'C');
          //$pdf->writeHTMLCell(0, 0, 15, 30, '<hr>', 0, 0, 0, true, 'C');

          if (!isset($result['facility_code']) || trim($result['facility_code']) == '') {
               $result['facility_code'] = '';
          }
          if (!isset($result['facility_state']) || trim($result['facility_state']) == '') {
               $result['facility_state'] = '';
          }
          if (!isset($result['facility_district']) || trim($result['facility_district']) == '') {
               $result['facility_district'] = '';
          }
          if (!isset($result['facility_name']) || trim($result['facility_name']) == '') {
               $result['facility_name'] = '';
          }

          //Set Age
          $age = 'Unknown';
          if (isset($result['patient_dob']) && trim($result['patient_dob']) != '' && $result['patient_dob'] != '0000-00-00') {
               $todayDate = strtotime(date('Y-m-d'));
               $dob = strtotime($result['patient_dob']);
               $difference = $todayDate - $dob;
               $seconds_per_year = 60 * 60 * 24 * 365;
               $age = round($difference / $seconds_per_year);
          } elseif (isset($result['patient_age_in_years']) && trim($result['patient_age_in_years']) != '' && trim($result['patient_age_in_years']) > 0) {
               $age = $result['patient_age_in_years'];
          } elseif (isset($result['patient_age_in_months']) && trim($result['patient_age_in_months']) != '' && trim($result['patient_age_in_months']) > 0) {
               if ($result['patient_age_in_months'] > 1) {
                    $age = $result['patient_age_in_months'] . ' months';
               } else {
                    $age = $result['patient_age_in_months'] . ' month';
               }
          }

          if (isset($result['sample_collection_date']) && trim($result['sample_collection_date']) != '' && $result['sample_collection_date'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", $result['sample_collection_date']);
               $result['sample_collection_date'] = date('d/M/Y', strtotime($expStr[0]));
               $sampleCollectionTime = $expStr[1];
          } else {
               $result['sample_collection_date'] = '';
               $sampleCollectionTime = '';
          }
          $sampleReceivedDate = '';
          $sampleReceivedTime = '';
          if (isset($result['sample_received_at_lab_datetime']) && trim($result['sample_received_at_lab_datetime']) != '' && $result['sample_received_at_lab_datetime'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", $result['sample_received_at_lab_datetime']);
               $sampleReceivedDate = date('d/M/Y', strtotime($expStr[0]));
               $sampleReceivedTime = $expStr[1];
          }
          $sampleDispatchDate = '';
          $sampleDispatchTime = '';
          if (isset($result['result_printed_datetime']) && trim($result['result_printed_datetime']) != '' && $result['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", $result['result_printed_datetime']);
               $sampleDispatchDate = date('d/M/Y', strtotime($expStr[0]));
               $sampleDispatchTime = $expStr[1];
          } else {
               $expStr = explode(" ", $currentTime);
               $sampleDispatchDate = date('d/M/Y', strtotime($expStr[0]));
               $sampleDispatchTime = $expStr[1];
          }

          $modified = _translate("No");
          if (!empty($result['modified_by'])) {
               $modified = _translate("Yes");
          }

          $finalDate = date('d/M/Y', strtotime('+1 day', strtotime($result['sample_tested_datetime'])));

          if (isset($result['sample_tested_datetime']) && trim($result['sample_tested_datetime']) != '' && $result['sample_tested_datetime'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", $result['sample_tested_datetime']);
               $result['sample_tested_datetime'] = date('d/M/Y', strtotime($expStr[0])) . " " . $expStr[1];
          } else {
               $result['sample_tested_datetime'] = '';
          }

          if (isset($result['treatment_initiated_date']) && trim($result['treatment_initiated_date']) != '' && $result['treatment_initiated_date'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", $result['treatment_initiated_date']);
               $result['treatment_initiated_date'] = date('d/M/Y', strtotime($expStr[0])) . " " . $expStr[1];
          } else {
               $result['treatment_initiated_date'] = '';
          }

          if (isset($result['last_modified_datetime']) && trim($result['last_modified_datetime']) != '' && $result['last_modified_datetime'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", $result['last_modified_datetime']);
               $result['last_modified_datetime'] = date('d/M/Y', strtotime($expStr[0])) . " " . $expStr[1];
          } else {
               $result['last_modified_datetime'] = '';
          }


          if (isset($result['result_reviewed_datetime']) && trim($result['result_reviewed_datetime']) != '' && $result['result_reviewed_datetime'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", $result['result_reviewed_datetime']);
               $result['result_reviewed_datetime'] = date('d/M/Y', strtotime($expStr[0])) . " " . $expStr[1];
          } else {
               $result['result_reviewed_datetime'] = '';
          }

          if (isset($result['result_approved_datetime']) && trim($result['result_approved_datetime']) != '' && $result['result_approved_datetime'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", $result['result_approved_datetime']);
               $result['result_approved_datetime'] = date('d/M/Y', strtotime($expStr[0])) . " " . $expStr[1];
          } else {
               $result['result_approved_datetime'] = '';
          }

          if (isset($result['last_viral_load_date']) && trim($result['last_viral_load_date']) != '' && $result['last_viral_load_date'] != '0000-00-00') {
               $result['last_viral_load_date'] = date('d/M/Y', strtotime($result['last_viral_load_date']));
               $result['last_viral_load_date'] = date('d/M/Y', strtotime($result['last_viral_load_date']));
          } else {
               $result['last_viral_load_date'] = '';
          }
          if (!isset($result['patient_gender']) || trim($result['patient_gender']) == '') {
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

          if (isset($arr['show_smiley']) && trim($arr['show_smiley']) == "no") {
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
          $patientFname = ($general->crypto('doNothing', $result['patient_first_name'], $result['patient_art_no']));
          if (!empty($result['is_encrypted']) && $result['is_encrypted'] == 'yes') {
               $key = base64_decode($general->getGlobalConfig('key'));
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

          $html .= '<p style="font: size 11px;"><u>' . _translate("Patient Information") . '</u></p>';
          $html .= '<table style="padding:4px 2px 2px 2px;width:100%;">';
          $html .= '<tr>';
          $html .= '<td colspan="3">';
          $html .= '<table style="padding:2px;" border="1" cellpadding="8" cellspacing="0">';
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . _translate("Unique Code") . " : " . $result['patient_art_no'] . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . _translate("Region : ") . $result['facility_state'] . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate("Patient Name : ") . $patientFname . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate("Age : ") . $age . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . _translate("Sex : ") . (str_replace("_", " ", $result['patient_gender'])) . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' .  _translate("Type of Sample : ") . $result['sample_name'] . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate("Contact : ") . $result['patient_mobile_number'] . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate("Date of Sample Collected : ") . $result['sample_collection_date'] . " " . $sampleCollectionTime . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate("Collected By : ") . $result['facility_name'] . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' .  _translate("Name of Requestor : ") . $result['request_clinician_name'] . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate("Contact of Requestor : ") . $result['request_clinician_phone_number'] . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate('Treatment center : ') . ($result['facility_name']) . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate('ARV initiation date : ') . ($result['treatment_initiated_date']) . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate('Modified : ') . ($modified) . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate('Modification date : ') . ($result['last_modified_datetime']) . '</td>';
          $html .= '</tr>';
          $html .= '</table>';

          $html .= '<br><br>';

          $html .= '<table border="0" style="margin-top:24px;">';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate('Sample Received Date : ') . '</td>';
          $html .= '<td style="line-height:20px;font-size:10px;text-align:left; border: 1px solid black">' . $sampleReceivedDate . " " . $sampleReceivedTime . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:right;">' . _translate('Viral Load Serial Number : ') . '</td>';
          $html .= '<td style="line-height:20px;font-size:10px;text-align:left; border: 1px solid black">' . ($result['sample_code']) . '</td>';

          $html .= '</tr>';
          $html .= '<tr><td></td></tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate('Test Name : ') . '</td>';
          $html .= '<td style="line-height:20px;font-size:10px;text-align:left; border: 1px solid black">' . $result['vl_test_platform'] . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:right;">' . _translate('Sample Test Date : ') . '</td>';
          $html .= '<td style="line-height:20px;font-size:10px;text-align:left; border: 1px solid black">' . $result['sample_tested_datetime'] . '</td>';
          $html .= '</tr>';

          $html .= '<tr><td></td></tr>';
          $html .= '<tr>';
          $html .= '<td width="10%" style="line-height:10px;font-size:10px;text-align:left;">' . _translate('Result : ') . '</td>';
          $html .= '<td width="10%" style="line-height:20px;font-size:10px;text-align:left; border: 1px solid black">' . htmlspecialchars($result['result']) . '</td>';
          $html .= '<td width="20%" style="line-height:10px;font-size:10px;text-align:right;">' . _translate('Results (log) : ') . '</td>';
          $html .= '<td width="10%" style="line-height:20px;font-size:10px;text-align:left; border: 1px solid black">' . $logValue . '</td>';
          $html .= '<td width="20%" style="line-height:10px;font-size:10px;text-align:right;">' . _translate('Test Done By : ') . '</td>';
          $html .= '<td width="30%" style="line-height:10px;font-size:10px;text-align:left; border: 1px solid black">' . $result['labName'] . '</td>';

          $html .= '</tr>';
          $html .= '<tr><td width="100%" style="line-height:20px;font-size:10px;">';
          $html .= '<br>';
          $html .= '<p>' . _translate("N.B.: The Abbott 0.6ml test has a lower limit of quantification of 40 copies/ml and an upper limit of quantification
          of 10,000,000 copies/ml. For any results between these limits, the number of copies per ml is reported. When the virus is
          detected above or below the limits of quantification, this is reported. If the test is successful and no viruses are found
          detected, the result is reported as undetected target. For a variation in viral load to be significant, it is necessary
          that the difference between two measurements is at least 0.5 Log10 or a reduction or increase by a factor of 3 of the
          number of copies/ml.
          ") . '</p>';
          $html .= '<table border="0" style="margin-top:24px;">';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate('Comment, if applicable : ') . '</td>';
          $html .= '<td style="line-height:20px;font-size:10px;text-align:left; border: 1px solid black">' . $result['lab_tech_comments'] . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:right;">' . _translate('Date : ') . '</td>';
          $html .= '<td style="line-height:20px;font-size:10px;text-align:left; border: 1px solid black">' . $finalDate . '</td>';

          $html .= '</tr>';
          $html .= '<tr><td></td></tr>';

          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate('Validated by : ') . $reviewedBy  . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:right;">' . _translate('Authorized by : ') . $resultApprovedBy . '</td></tr>';
          if (!empty($reviewedSignaturePath) && $pdf->imageExists(($reviewedSignaturePath))) {
               $signImg = '<img src="' . $reviewedSignaturePath . '" style="width:50px;" />';
          } else {
               $signImg = '';
          }
          $html .= '<tr><td></td></tr>';
          if (!empty($signImg)) {
               $html .= '<tr><td style="line-height:10px;font-size:10px;text-align:left;">' . _translate('Signature : ') . $signImg  . '</td>';
          } else {
               $html .= '<tr><td style="line-height:10px;font-size:10px;text-align:left;"></td>';
          }
          $html .= '<td style="line-height:10px;font-size:10px;text-align:right;"></td>';

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
          if (isset($_POST['source']) && trim($_POST['source']) == 'print') {
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
               if ($vlResult[0]['result_printed_datetime'] == null || trim($vlResult[0]['result_printed_datetime']) == '' || $vlResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
                    $db = $db->where('vl_sample_id', $result['vl_sample_id']);
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
