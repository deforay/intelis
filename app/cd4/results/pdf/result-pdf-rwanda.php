<?php

// This file is included in /cd4/results/generate-result-pdf.php
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Helpers\PdfWatermarkHelper;
use App\Registries\ContainerRegistry;
use App\Helpers\ResultPDFHelpers\CD4ResultPDFHelper;


/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$remoteURL = $general->getRemoteURL();


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
     $pdf = new CD4ResultPDFHelper(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
     if (MiscUtility::isImageValid(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
          $logoPrintInPdf = $result['facilityLogo'];
     } else {
          $logoPrintInPdf = $arr['logo'];
     }
     $pdf->setHeading($logoPrintInPdf, $arr['header'], $result['labName']);
     // set document information
     $pdf->SetCreator('InteLIS');
     $pdf->SetTitle('CD4 Test Result');
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

     if (isset($result['sample_collection_date']) && trim((string) $result['sample_collection_date']) !== '' && $result['sample_collection_date'] != '0000-00-00 00:00:00') {
          $expStr = explode(" ", (string) $result['sample_collection_date']);
          $result['sample_collection_date'] = DateUtility::humanReadableDateFormat($expStr[0]);
          $sampleCollectionTime = $expStr[1];
     } else {
          $result['sample_collection_date'] = '';
          $sampleCollectionTime = '';
     }
     $sampleReceivedDate = '';
     $sampleReceivedTime = '';
     if (isset($result['sample_received_at_lab_datetime']) && trim((string) $result['sample_received_at_lab_datetime']) !== '' && $result['sample_received_at_lab_datetime'] != '0000-00-00 00:00:00') {
          $expStr = explode(" ", (string) $result['sample_received_at_lab_datetime']);
          $sampleReceivedDate = DateUtility::humanReadableDateFormat($expStr[0]);
          $sampleReceivedTime = $expStr[1];
     }

     if (isset($result['sample_tested_datetime']) && trim((string) $result['sample_tested_datetime']) !== '' && $result['sample_tested_datetime'] != '0000-00-00 00:00:00') {
          $expStr = explode(" ", (string) $result['sample_tested_datetime']);
          $result['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
     } else {
          $result['sample_tested_datetime'] = '';
     }

     if (isset($result['last_cd4_date']) && trim((string) $result['last_cd4_date']) !== '' && $result['last_cd4_date'] != '0000-00-00') {
          $result['last_cd4_date'] = DateUtility::humanReadableDateFormat($result['last_cd4_date']);
     } else {
          $result['last_cd4_date'] = '';
     }
     if (!isset($result['patient_gender']) || trim((string) $result['patient_gender']) === '') {
          $result['patient_gender'] = _translate('Unreported');
     }
     $userRes = [];
     if (isset($result['approvedBy']) && trim((string) $result['approvedBy']) !== '') {
          $resultApprovedBy = ($result['approvedBy']);
          $userRes = $usersService->getUserByID($result['result_approved_by'], 'user_signature');
     } else {
          $resultApprovedBy = null;
     }
     $userSignaturePath = null;

     if (!empty($userRes['user_signature'])) {
          $userSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $userRes['user_signature'];
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

     $smileyContent = '';
     if (isset($arr['show_smiley']) && trim((string) $arr['show_smiley']) === "yes") {
          if (!empty($result['cd4_result']) && $result['cd4_result'] > 200) {
               $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . '<img src="/assets/img/smiley_smile.png" style="width:50px;" alt="smile_face"/>';
          } elseif (!empty($result['cd4_result']) && $result['cd4_result'] <= 200) {
               $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . '<img src="/assets/img/smiley_frown.png" style="width:50px;" alt="frown_face"/>';
          }
     }
     $html = '<table style="padding:0px 2px 2px 2px;z-index:1;">';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE ID</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE COLLECTION DATE</td>';
     $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">ART (TRACNET) NO.</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_code'] . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_collection_date'] . " " . $sampleCollectionTime . '</td>';
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
     $crAgResult = "";
     if ($result['crag_test_results'] != '' || $result['crag_test_results'] != NULL) {
          $crAgResult = '<br>&nbsp;&nbsp;CrAg Test Result &nbsp;&nbsp;:&nbsp;&nbsp;' . ucfirst((string) $result['crag_test_results']);
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
     $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $sampleReceivedDate . " " . $sampleReceivedTime . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_tested_datetime'] . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['sample_name']) . '</td>';
     $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['cd4_test_platform']) . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="4" style="line-height:16px;"></td>';
     $html .= '</tr>';
     $html .= '<tr style="background-color:#dbdbdb;"><td colspan="3" style="line-height:26px;font-size:12px;font-weight:bold;text-align:left;">&nbsp;&nbsp;CD4 RESULT (value)&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . htmlspecialchars((string) $result['cd4_result']) . $crAgResult . '</td><td>' . $smileyContent . '</td></tr>';
     $html .= '<tr><td colspan="3"></td></tr>';
     $html .= '</table>';
     $html .= '</td>';
     $html .= '</tr>';
     if (trim($showMessage) !== '') {
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
     if ($userSignaturePath !== null && $userSignaturePath !== '' && $userSignaturePath !== '0' && MiscUtility::isImageValid($userSignaturePath)) {
          $html .= '<tr>';
          $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;vertical-align: bottom;"><img src="' . $userSignaturePath . '" style="width:70px;margin-top:-20px;" /><br></td>';
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
     $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Date of Last CD4 Test&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . $result['last_cd4_date'] . '</span></td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Result of previous result value&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . $result['last_cd4_result'] . '</span></td>';
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
     $html .= '<td style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_smile.png" alt="smile_face" style="width:10px;height:10px;"/> </td>';
     $html .= '<td style="font-size:10px;text-align:left;">Printed on : ' . $printDate . '&nbsp;' . '</td>';
     $html .= '</tr>';
     $html .= '<tr>';
     $html .= '<td style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_frown.png" alt="frown_face" style="width:10px;height:10px;"/></td>';
     $html .= '<td style="font-size:10px;text-align:left;">' . $generatedAtTestingLab . '</td>';
     $html .= '</tr>';
     $html .= '</table>';
     $html .= '</td>';
     $html .= '</tr>';
     $html .= '</table>';
     if (!empty($result['cd4_result'])) {
          $pdf->writeHTML($html);
          if (isset($arr['vl_report_qr_code']) && $arr['vl_report_qr_code'] == 'yes' && !empty($remoteURL)) {
               $viewId = CommonService::encryptViewQRCode($result['unique_id']);
               $pdf->write2DBarcode($remoteURL . '/cd4/results/view.php?q=' . $viewId, 'QRCODE,H', 150, 170, 30, 30, [], 'N');
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
          //Add event log
          $eventType = 'print-result';
          $action = $_SESSION['userName'] . ' printed the test result with Patient ID/Code ' . $result['patient_art_no'];
          $resource = 'print-test-result';
          $data = ['event_type' => $eventType, 'action' => $action, 'resource' => $resource, 'date_time' => DateUtility::getCurrentDateTime()];
          $db->insert($tableName1, $data);
          //Update print datetime in VL tbl.
          $vlQuery = "SELECT result_printed_datetime FROM form_cd4 as vl WHERE vl.cd4_id ='" . $result['cd4_id'] . "'";
          $vlResult = $db->query($vlQuery);
          if ($vlResult[0]['result_printed_datetime'] == null || trim((string) $vlResult[0]['result_printed_datetime']) === '' || $vlResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
               $db->where('cd4_id', $result['cd4_id']);
               $db->update($tableName2, ['result_printed_datetime' => DateUtility::getCurrentDateTime()]);
          }
     }
}
