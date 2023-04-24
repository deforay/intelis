<?php

// This file is included in /vl/results/generate-result-pdf.php


use App\Utilities\DateUtils;

$resultFilename = '';

if (sizeof($requestResult) > 0) {
     $_SESSION['rVal'] = $general->generateRandomString(6);
     $pathFront = (TEMP_PATH . DIRECTORY_SEPARATOR .  $_SESSION['rVal']);
     if (!file_exists($pathFront) && !is_dir($pathFront)) {
          mkdir(TEMP_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal'], 0777, true);
          $pathFront = realpath(TEMP_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal']);
     }
     $pages = [];
     $page = 1;
     foreach ($requestResult as $result) {
          $currentTime = DateUtils::getCurrentDateTime();

          $testedBy = '';
          if (isset($result['tested_by']) && !empty($result['tested_by'])) {
               $testedByRes = $users->getUserInfo($result['tested_by'], array('user_name', 'user_signature'));
               if ($testedByRes) {
                    $testedBy = $testedByRes['user_name'];
               }
          }
          $reviewedBy = '';
          if (isset($result['result_reviewed_by']) && !empty($result['result_reviewed_by'])) {
               $reviewedByRes = $users->getUserInfo($result['result_reviewed_by'], array('user_name', 'user_signature'));
               if ($reviewedByRes) {
                    $reviewedBy = $reviewedByRes['user_name'];
               }
          }

          $revisedBy = '';
          $revisedByRes = [];
          if (isset($result['revised_by']) && !empty($result['revised_by'])) {
               $revisedByRes = $users->getUserInfo($result['revised_by'], array('user_name', 'user_signature'));
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
          if (isset($result['result_approved_by']) && !empty($result['result_approved_by'])) {
               $resultApprovedByRes = $users->getUserInfo($result['result_approved_by'], array('user_name', 'user_signature'));
               if ($resultApprovedByRes) {
                    $resultApprovedBy = $resultApprovedByRes['result_approved_by'];
               }
               if (!empty($resultApprovedByRes['user_signature'])) {
                    $userSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $resultApprovedByRes['user_signature'];
               }
          }

          if (isset($result['approvedBy']) && trim($result['approvedBy']) != '') {
               $resultApprovedBy = ($result['approvedBy']);
               $userRes = $users->getUserInfo($result['result_approved_by'], 'user_signature');
          } else {
               $resultApprovedBy  = '';
          }

          $userSignaturePath = null;
          if (!empty($userRes['user_signature'])) {
               $userSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $userRes['user_signature'];
          }
          $_SESSION['aliasPage'] = $page;
          if (!isset($result['labName'])) {
               $result['labName'] = '';
          }
          $draftTextShow = false;
          //Set watermark text
          for ($m = 0; $m < count($mFieldArray); $m++) {
               if (!isset($result[$mFieldArray[$m]]) || trim($result[$mFieldArray[$m]]) == '' || $result[$mFieldArray[$m]] == null || $result[$mFieldArray[$m]] == '0000-00-00 00:00:00') {
                    $draftTextShow = true;
                    break;
               }
          }
          // create new PDF document
          $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
          if ($pdf->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
               $logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'];
          } else {
               $logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $arr['logo'];
          }
          $pdf->setHeading($logoPrintInPdf, $arr['header'], $result['labName'], $title = 'HIV VIRAL LOAD PATIENT REPORT');
          // set document information
          $pdf->SetCreator('VLSM');
          $pdf->SetTitle('HIV Viral Load Patient Report');
          //$pdf->SetSubject('TCPDF Tutorial');
          //$pdf->SetKeywords('TCPDF, PDF, example, test, guide');

          // set default header data
          $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

          // set header and footer fonts
          $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
          $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

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

          // set some language-dependent strings (optional)
          if (@file_exists(dirname(__FILE__) . '/lang/eng.php')) {
               require_once(dirname(__FILE__) . '/lang/eng.php');
               $pdf->setLanguageArray($l);
          }

          // ---------------------------------------------------------

          // set font
          $pdf->SetFont('helvetica', '', 18);

          $pdf->AddPage();
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
          if (!isset($result['labName']) || trim($result['labName']) == '') {
               $result['labName'] = '';
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
          if (isset($result['sample_received_at_vl_lab_datetime']) && trim($result['sample_received_at_vl_lab_datetime']) != '' && $result['sample_received_at_vl_lab_datetime'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", $result['sample_received_at_vl_lab_datetime']);
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

          if (isset($result['sample_tested_datetime']) && trim($result['sample_tested_datetime']) != '' && $result['sample_tested_datetime'] != '0000-00-00 00:00:00') {
               $expStr = explode(" ", $result['sample_tested_datetime']);
               $result['sample_tested_datetime'] = date('d/M/Y', strtotime($expStr[0])) . " " . $expStr[1];
          } else {
               $result['sample_tested_datetime'] = '';
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
          $messageTextSize = '12px';
          $vlResult = trim($result['result']);
          $vlResultCategory = strtolower(trim($result['vl_result_category']));
          if (!empty($vlResult) && !empty($vlResultCategory)) {
               $isResultNumeric = is_numeric($vlResult);

               if ($vlResultCategory == 'not suppressed') {
                    $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" style="width:50px;" alt="frown_face"/>';
                    $showMessage = ($arr['h_vl_msg']);
                    $messageTextSize = '15px';
               } else if ($vlResultCategory == 'suppressed') {
                    $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" style="width:50px;" alt="smile_face"/>';
                    $showMessage = ($arr['l_vl_msg']);
               }

               if (in_array(strtolower($vlResult), array("hiv-1 not detected", "below detection limit", "below detection level", 'bdl', 'not detected'))) {
                    $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" style="width:50px;" alt="smile_face"/>';
                    $showMessage = ($arr['l_vl_msg']);
               } else if (in_array(strtolower($vlResult), array("tnd", "target not detected", 'ldl'))) {
                    if ($vlResult == 'ldl' || $vlResult == 'LDL') {
                         $vlResult = 'LDL';
                    } else {
                         $vlResult = 'TND*';
                         $tndMessage = 'TND* - Target not Detected';
                    }
                    $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" style="width:50px;" alt="smile_face"/>';
                    $showMessage = ($arr['l_vl_msg']);
                    $tndMessage = 'TND* - Target not Detected';
               } else if (in_array(strtolower($vlResult), array("failed", "fail", "no_sample", "invalid"))) {
                    $vlResult = ($vlResult);
                    $smileyContent = '';
                    $showMessage = '';
                    $messageTextSize = '14px';
               } else if (in_array($vlResult, array("<20", "< 20", "<40", "< 40", "< 839", "<839"))) {
                    $vlResult = str_replace("<", "&lt;", $vlResult);
                    $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" style="width:50px;" alt="smile_face"/>';
                    $showMessage = ($arr['l_vl_msg']);
               } else if ($vlResult == '>10000000' || $vlResult == '> 10000000') {
                    $vlResult = str_replace(">", "&gt;", $vlResult);
                    $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" style="width:50px;" alt="frown_face"/>';
                    $showMessage = ($arr['h_vl_msg']);
               }
          }
          if (isset($arr['show_smiley']) && trim($arr['show_smiley']) == "no") {
               $smileyContent = '';
          }
          if ($result['result_status'] == '4') {
               $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/cross.png" style="width:50px;" alt="rejected"/>';
          }
          $html = '<table style="padding:4px 2px 2px 2px;width:100%;">';
          $html .= '<tr>';

          $html .= '<td colspan="3">';
          $html .= '<table style="padding:2px;">';
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">REQUESTING HEALTH FACILITY NAME</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">HEALTH FACILITY CODE</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">STATE</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">COUNTY</td>';
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
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">PATIENT NAME</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">UNIQUE ART (TRACNET) NO.</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">REASON FOR VL TESTING</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;"></td>';
          $html .= '</tr>';
          $html .= '<tr>';

          $patientFname = ($general->crypto('doNothing', $result['patient_first_name'], $result['patient_art_no']));


          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $patientFname . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['patient_art_no'] . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['test_reason_name']) . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;"></td>';
          $html .= '</tr>';
          $html .= '</table>';
          $html .= '</td>';
          $html .= '</tr>';

          $html .= '<tr>';
          $html .= '<td colspan="3">';
          $html .= '<table style="padding:8px 2px 2px 2px;">';
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">AGE</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">GENDER</td>';
          if ($result['patient_gender'] == 'female') {
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">BREAST FEEDING</td>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">PREGNANCY STATUS</td>';
          } else {
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
          }
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $age . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . (str_replace("_", " ", $result['patient_gender'])) . '</td>';
          if ($result['patient_gender'] == 'female') {
               $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . (str_replace("_", " ", $result['is_patient_breastfeeding'])) . '</td>';
               $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . (str_replace("_", " ", $result['is_patient_pregnant'])) . '</td>';
          } else {
               $html .= '<td colspan="2" style="line-height:10px;font-size:10px;text-align:left;"></td>';
               $html .= '<td colspan="2" style="line-height:10px;font-size:10px;text-align:left;"></td>';
          }
          $html .= '</tr>';

          $html .= '<tr>';
          $html .= '<td colspan="2" style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">REQUESTING CLINICIAN NAME</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">TEL</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">EMAIL</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="2" style="line-height:10px;font-size:10px;text-align:left;">' . ($result['request_clinician_name']) . '</td>';
          $html .= '<td colspan="2" style="line-height:10px;font-size:10px;text-align:left;">' . $result['request_clinician_phone_number'] . '</td>';
          $html .= '<td colspan="2" style="line-height:10px;font-size:10px;text-align:left;">' . $result['facility_emails'] . '</td>';
          $html .= '</tr>';
          $html .= '</table>';
          $html .= '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:12px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE ID</td>';
          $html .= '<td style="line-height:12px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE COLLECTION DATE</td>';
          $html .= '<td style="line-height:12px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE RECEIPT DATE</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['sample_code'] . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['sample_collection_date'] . " " . $sampleCollectionTime . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $sampleReceivedDate . " " . $sampleReceivedTime . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE REJECTION STATUS</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE TEST DATE</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">RESULT RELEASE DATE</td>';
          $html .= '</tr>';
          $rejectedStatus = (!empty($result['is_sample_rejected']) && $result['is_sample_rejected'] == 'yes') ? 'Rejected' : 'Not Rejected';
          $html .= '<tr>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $rejectedStatus . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['sample_tested_datetime'] . '</td>';
          $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $sampleDispatchDate . " " . $sampleDispatchTime . '</td>';
          $html .= '</tr>';

          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE TYPE</td>';
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
               $logValue = '&nbsp;&nbsp;Log Value&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $result['result_value_log'];
          } else {
               if ($isResultNumeric) {
                    $logV = round(log10($vlResult), 2);
                    $logValue = '&nbsp;&nbsp;Log Value&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $logV;
               } else {
                    //$logValue = '&nbsp;&nbsp;Log Value&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;0.0';
                    $logValue = '';
               }
          }
          $html .= '<tr style="background-color:#dbdbdb;"><td colspan="2" style="line-height:26px;font-size:12px;font-weight:bold;">&nbsp;&nbsp;Viral Load Result (copies/ml)&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $vlResult . '<br>' . $logValue . '</td><td >' . $smileyContent . '</td></tr>';
          if ($result['reason_for_sample_rejection'] != '') {
               $html .= '<tr><td colspan="3" style="line-height:26px;font-size:12px;font-weight:bold;text-align:left;">&nbsp;&nbsp;Rejection Reason&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $result['rejection_reason_name'] . '</td></tr>';
          }
          if (strpos(strtolower($result['vl_test_platform']), 'abbott') !== false) {
               $html .= '<tr>';
               $html .= '<td colspan="3" style="line-height:8px;font-size:8px;padding-top:8px;">Abbott Linear Detection range: 839 copies/ml - 10 million copies/ml</td>';
               $html .= '</tr>';
          }
          //$html .= '<tr><td colspan="3"></td></tr>';
          $html .= '</table>';
          $html .= '</td>';
          $html .= '</tr>';
          if (trim($showMessage) != '') {
               $html .= '<tr>';
               $html .= '<td colspan="3" style="line-height:13px;font-size:' . $messageTextSize . ';text-align:left;">' . $showMessage . '</td>';
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
          $html .= '<td colspan="3" style="line-height:15px;font-size:11px;font-weight:bold;">TEST PLATFORM &nbsp;&nbsp;:&nbsp;&nbsp; <span style="font-weight:normal;">' . ($result['vl_test_platform']) . '</span></td>';
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
                    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">TESTED BY</td>';
                    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SIGNATURE</td>';
                    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">DATE</td>';
                    $html .= '</tr>';

                    $html .= '<tr>';
                    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $testedBy . '</td>';
                    if (!empty($testUserSignaturePath) && $pdf->imageExists(($testUserSignaturePath))) {
                         $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $testUserSignaturePath . '" style="width:50px;" /></td>';
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
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">REVIEWED BY</td>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SIGNATURE</td>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">DATE</td>';
               $html .= '</tr>';

               $html .= '<tr>';
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $reviewedBy . '</td>';
               if (!empty($reviewedSignaturePath) && $pdf->imageExists(($reviewedSignaturePath))) {
                    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $reviewedSignaturePath . '" style="width:50px;" /></td>';
               } else {
                    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
               }
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . (!empty($result['result_reviewed_datetime']) ? $result['result_reviewed_datetime'] : $result['sample_tested_datetime']) . '</td>';
               $html .= '</tr>';
          }

          if (!empty($revisedBy)) {

               $html .= '<tr>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">REPORT REVISED BY</td>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SIGNATURE</td>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">DATE</td>';
               $html .= '</tr>';

               $html .= '<tr>';
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $revisedBy . '</td>';
               if (!empty($revisedSignaturePath) && $pdf->imageExists($revisedSignaturePath)) {
                    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $revisedSignaturePath . '" style="width:70px;" /></td>';
               } else {
                    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
               }
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . date('d/M/Y', strtotime($result['revised_on'])) . '</td>';
               $html .= '</tr>';
          }

          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:8px;"></td>';
          $html .= '</tr>';
          if (!empty($resultApprovedBy) && !empty($result['result_approved_datetime'])) {
               $html .= '<tr>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">APPROVED BY</td>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SIGNATURE</td>';
               $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">DATE</td>';
               $html .= '</tr>';

               $html .= '<tr>';
               $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $resultApprovedBy . '</td>';
               if (!empty($userSignaturePath) && $pdf->imageExists(($userSignaturePath))) {
                    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $userSignaturePath . '" style="width:50px;" /></td>';
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


          if (isset($result['lab_tech_comments']) && !empty($result['lab_tech_comments'])) {

               $html .= '<tr>';
               $html .= '<td colspan="3" style="line-height:20px;"></td>';
               $html .= '</tr>';
               $html .= '<tr>';
               $html .= '<td colspan="3" style="line-height:11px;font-size:11px;text-align:left;"><strong>Lab Comments:</strong> ' . $result['lab_tech_comments'] . '</td>';
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
          $html .= '<td style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_smile.png" alt="smile_face" style="width:10px;height:10px;"/> = VL < = 1000 copies/ml: Continue on current regimen</td>';
          $html .= '<td style="font-size:10px;text-align:left;">Printed on : ' . $printDate . '&nbsp;&nbsp;' . $printDateTime . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="2" style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_frown.png" alt="frown_face" style="width:10px;height:10px;"/> = VL > 1000 copies/ml: copies/ml: Clinical and counselling action required</td>';
          $html .= '</tr>';
          $html .= '</table>';
          $html .= '</td>';
          $html .= '</tr>';
          $html .= '</table>';
          if ($vlResult != '' || ($vlResult == '' && $result['result_status'] == '4')) {
               $pdf->writeHTML($html);
               $pdf->lastPage();
               $filename = $pathFront . DIRECTORY_SEPARATOR . 'p' . $page . '.pdf';
               $pdf->Output($filename, "F");
               if ($draftTextShow) {
                    //Watermark section
                    $watermark = new \App\Helpers\PdfWatermarkHelper();
$watermark->setFullPathToFile($filename);
                    $fullPathToFile = $filename;
                    $watermark->Output($filename, "F");
               }
               $pages[] = $filename;
               $page++;
          }
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
          $resultPdf = new \App\Helpers\PdfConcatenateHelper();
          $resultPdf->setFiles($pages);
          $resultPdf->setPrintHeader(false);
          $resultPdf->setPrintFooter(false);
          $resultPdf->concat();
          $resultFilename = 'VLSM-VL-Test-result-' . date('d-M-Y-H-i-s') . "-" . $general->generateRandomString(6) . '.pdf';
          $resultPdf->Output(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename, "F");
          $general->removeDirectory($pathFront);
          unset($_SESSION['rVal']);
     }
}

echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename);
