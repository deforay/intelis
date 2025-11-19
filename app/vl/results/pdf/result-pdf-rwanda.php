<?php

// This file is included in /vl/results/generate-result-pdf.php
use const SAMPLE_STATUS\REJECTED;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Helpers\PdfWatermarkHelper;
use App\Helpers\ResultPDFHelpers\VLResultPDFHelper;


if (!empty($result)) {
     $_SESSION['aliasPage'] = $page;
     if (!isset($result['labName'])) {
          $result['labName'] = '';
     }
     $draftTextShow = false;
     //Set watermark text
     $counter = count($mFieldArray);
     //Set watermark text
     for ($m = 0; $m < $counter; $m++) {
          if (!isset($result[$mFieldArray[$m]]) || trim((string) $result[$mFieldArray[$m]]) === '' || $result[$mFieldArray[$m]] == null || $result[$mFieldArray[$m]] == '0000-00-00 00:00:00') {
               $draftTextShow = true;
               break;
          }
     }
     // create new PDF document
     $pdf = new VLResultPDFHelper(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
     if (MiscUtility::isImageValid(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
          $logoPrintInPdf = $result['facilityLogo'];
     } else {
          $logoPrintInPdf = $arr['logo'];
     }
     $pdf->setHeading($logoPrintInPdf, $arr['header'], $result['labName']);
     // set document information
     $pdf->SetCreator('InteLIS');

     $pdf->SetTitle('Viral Load Test Result');
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
     $result['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($result['sample_tested_datetime'] ?? '', true);
     $result['last_viral_load_date'] = DateUtility::humanReadableDateFormat($result['last_viral_load_date'] ?? '');

     if (!isset($result['patient_gender']) || trim((string) $result['patient_gender']) === '') {
          $result['patient_gender'] = _translate('Unreported');
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


     $approvedBySignaturePath = null;
     if (!empty($result['approvedBySignature'])) {
          $approvedBySignaturePath = MiscUtility::getFullImagePath($result['approvedBySignature'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature");
     }

     $smileyContent = '';
     $showMessage = '';
     $tndMessage = '';
     $messageTextSize = '15px';

     if (!empty($result['is_encrypted']) && $result['is_encrypted'] == 'yes') {
          $key = (string) $general->getGlobalConfig('key');
          $result['patient_art_no'] = $general->crypto('decrypt', $result['patient_art_no'], $key);
          $result['patient_first_name'] = $general->crypto('decrypt', $result['patient_first_name'], $key);
          $result['patient_last_name'] = $general->crypto('decrypt', $result['patient_last_name'], $key);
     }


     if (!empty($result['vl_result_category']) && $result['vl_result_category'] == 'suppressed') {
          $smileyContent = '<img src="/assets/img/smiley_smile.png" style="width:50px;" alt="smile_face"/>';
          $showMessage = $arr['l_vl_msg'];
     } elseif (!empty($result['vl_result_category']) && $result['vl_result_category'] == 'not suppressed') {
          $smileyContent = '<img src="/assets/img/smiley_frown.png" style="width:50px;" alt="frown_face"/>';
          $showMessage = $arr['h_vl_msg'];
     } elseif ($result['result_status'] == REJECTED || $result['is_sample_rejected'] == 'yes') {
          $smileyContent = '<img src="/assets/img/cross.png" style="width:50px;" alt="rejected"/>';
     }

     if (isset($arr['show_smiley']) && trim((string) $arr['show_smiley']) === "no") {
          $smileyContent = '';
     } else {
          $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $smileyContent;
     }
     $html = '<table style="padding:0px 2px 2px 2px;z-index:1;">';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE ID</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE COLLECTION DATE</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">ART (TRACNET) NO.</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_code'] . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_collection_date'] . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['patient_art_no'] . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:10px;"></td>';
     $html .= '</tr>';
     if ($arr['patient_name_pdf'] == 'fullname') {
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">PATIENT NAME</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">MOBILE NO.</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">AGE</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['patient_first_name'] . " " . $result['patient_last_name']) . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['patient_mobile_number'] . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $age . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:10px;"></td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SEX</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:11px;font-size:11px;text-align:left;">' . (str_replace("_", " ", (string) $result['patient_gender'])) . '</td>';
          $html .= '</tr>';
     } elseif ($arr['patient_name_pdf'] == 'hidename') {
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">MOBILE NO.</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">AGE</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SEX</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['patient_mobile_number'] . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $age . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . (str_replace("_", " ", (string) $result['patient_gender'])) . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:10px;"></td>';
          $html .= '</tr>';
     } else {
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">PATIENT FIRST NAME</td>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">MOBILE NO.</td>';
          $html .= '</tr>';
          $html .= '<tr>';

          $patientFname = $result['patient_first_name'] ?? '';

          if (!empty($result['is_encrypted']) && $result['is_encrypted'] == 'yes') {
               $key = (string) $general->getGlobalConfig('key');
               $result['patient_art_no'] = $general->crypto('decrypt', $result['patient_art_no'], $key);
               $patientFname = $general->crypto('decrypt', $patientFname, $key);
          }

          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $patientFname . '</td>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['patient_mobile_number'] . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:10px;"></td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">AGE</td>';
          $html .= '<td colspan="2" style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SEX</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $age . '</td>';
          $html .= '<td colspan="2" style="line-height:11px;font-size:11px;text-align:left;">' . (str_replace("_", " ", (string) $result['patient_gender'])) . '</td>';
          $html .= '</tr>';
     }
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:10px;"></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:10px;"></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">CLINIC/HEALTH CENTER CODE</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Province/State</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">District/County</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['facility_code'] . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['facility_state']) . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['facility_district']) . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:10px;"></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">CLINIC/HEALTH CENTER NAME</td>';
     $html .= '<td colspan="2" style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">CLINICAN NAME</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['facility_name']) . '</td>';
     $html .= '<td colspan="2" style="line-height:11px;font-size:11px;text-align:left;">' . ($result['request_clinician_name']) . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:10px;"></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:10px;"></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3">';
     $html .= '<table style="padding:2px;">';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE RECEIPT DATE</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE TEST DATE</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SPECIMEN TYPE</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">PLATFORM</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_received_at_lab_datetime'] . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_tested_datetime'] . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_name'] . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['instrument_machine_name'] . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="4" style="line-height:16px;"></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3"></td>';
     $html .= '<td rowspan="3" style="text-align:left;">' . $smileyContent . '</td>';
     $html .= '</tr>';
     $html .= '<tr><td colspan="3" style="line-height:26px;font-size:12px;font-weight:bold;text-align:left;background-color:#dbdbdb;">&nbsp;&nbsp;VIRAL LOAD RESULT (copies/mL)&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . htmlspecialchars((string) $result['result']) . '</td></tr>';
     $html .= '<tr><td colspan="3"></td></tr>';
     $html .= '</table>';
     $html .= '</td>';
     $html .= '</tr>';
     if (trim((string) $showMessage) !== '') {
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:13px;font-size:' . $messageTextSize . ';text-align:left;">' . $showMessage . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:16px;"></td>';
          $html .= '</tr>';
     }
     if (trim($tndMessage) !== '') {
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:13px;font-size:18px;text-align:left;">' . $tndMessage . '</td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:16px;"></td>';
          $html .= '</tr>';
     }
     if ($approvedBySignaturePath !== null && $approvedBySignaturePath !== '' && $approvedBySignaturePath !== '0' && MiscUtility::isImageValid($approvedBySignaturePath)) {
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;vertical-align: bottom;"><img src="' . $approvedBySignaturePath . '" style="width:70px;margin-top:-20px;" /><br></td>';
          $html .= '</tr>';
     }
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">APPROVED BY&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . $resultApprovedBy . '</span></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:10px;"></td>';
     $html .= '</tr>';
     if (isset($result['lab_tech_comments']) && trim((string) $result['lab_tech_comments']) !== '') {
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">LAB COMMENTS&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . ($result['lab_tech_comments']) . '</span></td>';
          $html .= '</tr>';
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:10px;"></td>';
          $html .= '</tr>';
     }
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:14px;"></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">PREVIOUS RESULTS</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:8px;"></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Date of Last VL Test&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . $result['last_viral_load_date'] . '</span></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Result of previous viral load(copies/mL)&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . $result['last_viral_load_result'] . '</span></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:110px;border-bottom:2px solid #d3d3d3;"></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:2px;"></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3">';
     $html .= '<table>';
     if ($general->isLISInstance() && $result['data_sync'] == 0) {
          $generatedAtTestingLab = _translate("Report generated at Testing Lab");
     } else {
          $generatedAtTestingLab = "";
     }
     $html .= '<tr>';
     $html .= '<td style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_smile.png" alt="smile_face" style="width:10px;height:10px;"/> VL < 1000 copies/mL: Continue on current regimen</td>';
     $html .= '<td style="font-size:10px;text-align:left;">Printed on : ' . $printDate . '&nbsp;' . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_frown.png" alt="frown_face" style="width:10px;height:10px;"/> VL >= 1000 copies/mL:  Clinical and counselling action required</td>';
     $html .= '<td style="font-size:10px;text-align:left;">' . $generatedAtTestingLab . '</td>';
     $html .= '</tr>';
     $html .= '</table>';
     $html .= '</td>';
     $html .= '</tr>';
     $html .= '</table>';
     if (!empty($result['result'])) {
          $pdf->writeHTML($html);
          if (isset($arr['vl_report_qr_code']) && $arr['vl_report_qr_code'] == 'yes' && !empty($general->getRemoteURL())) {
               $viewId = CommonService::encryptViewQRCode($result['unique_id']);
               $remoteURL = $general->getRemoteURL();
               $pdf->write2DBarcode($remoteURL . '/vl/results/view.php?q=' . $viewId, 'QRCODE,H', 150, 170, 30, 30, [], 'N');
          }
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
          $action = $_SESSION['userName'] . ' printed the test result with ' . $sampleId . $concat . $patientId;
          $resource = 'print-test-result';
          $data = ['event_type' => $eventType, 'action' => $action, 'resource' => $resource, 'date_time' => DateUtility::getCurrentDateTime()];
          $db->insert($tableName1, $data);
          //Update print datetime in VL tbl.
          $vlQuery = "SELECT result_printed_datetime FROM form_vl as vl WHERE vl.vl_sample_id ='" . $result['vl_sample_id'] . "'";
          $vlResult = $db->query($vlQuery);
          if ($vlResult[0]['result_printed_datetime'] == null || trim((string) $vlResult[0]['result_printed_datetime']) === '' || $vlResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
               $db->where('vl_sample_id', $result['vl_sample_id']);
               $db->update($tableName2, ['result_printed_datetime' => DateUtility::getCurrentDateTime()]);
          }
     }
}
