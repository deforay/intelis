<?php


use App\Helpers\PdfWatermarkHelper;
use App\Utilities\DateUtility;

class SouthSudan_PDF extends MYPDF
{
    //Page header
    public function Header()
    {
        // Logo

        if ($this->htitle != '') {

            if (trim($this->logo) != '') {
                if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                    $this->Image($imageFilePath, 10, 5, 25, '', '', '', 'T');
                }
            }
            $this->SetFont('helvetica', 'B', 15);
            $this->writeHTMLCell(0, 0, 40, 7, $this->text, 0, 0, 0, true, 'L');
            if (trim($this->lab) != '') {
                $this->SetFont('helvetica', 'B', 11);
                // $this->writeHTMLCell(0, 0, 40, 15, strtoupper($this->lab), 0, 0, 0, true, 'L', true);
                $this->writeHTMLCell(0, 0, 40, 15, 'Public Health Laboratory', 0, 0, 0, true, 'L');
            }

            $this->SetFont('helvetica', '', 9);
            $this->writeHTMLCell(0, 0, 40, 21, $this->facilityInfo['address'], 0, 0, 0, true, 'L');

            $this->SetFont('helvetica', '', 9);

            $emil = (isset($this->facilityInfo['report_email']) && $this->facilityInfo['report_email'] != "") ? 'E-mail : ' . $this->facilityInfo['report_email'] : "";
            $phone = (isset($this->facilityInfo['facility_mobile_numbers']) && $this->facilityInfo['facility_mobile_numbers'] != "") ? 'Phone : ' . $this->facilityInfo['facility_mobile_numbers'] : "";
            if (isset($this->facilityInfo['report_email']) && $this->facilityInfo['report_email'] != "" && isset($this->facilityInfo['facility_mobile_numbers']) && $this->facilityInfo['facility_mobile_numbers'] != "") {
                $space = '&nbsp;&nbsp;|&nbsp;&nbsp;';
            } else {
                $space = "";
            }
            $this->writeHTMLCell(0, 0, 40, 26, $emil . $space . $phone, 0, 0, 0, true, 'L');


            $this->writeHTMLCell(0, 0, 10, 33, '<hr>', 0, 0, 0, true, 'C');
            $this->writeHTMLCell(0, 0, 10, 34, '<hr>', 0, 0, 0, true, 'C');
            $this->SetFont('helvetica', 'B', 12);
            $this->writeHTMLCell(0, 0, 20, 35, 'SARS-CoV-2 Laboratory Report', 0, 0, 0, true, 'C');

            // $this->writeHTMLCell(0, 0, 25, 35, '<hr>', 0, 0, 0, true, 'C', true);

        }
    }
}
//Set watermark text
for ($m = 0; $m < count($mFieldArray); $m++) {
    if (!isset($result[$mFieldArray[$m]]) || trim($result[$mFieldArray[$m]]) == '' || $result[$mFieldArray[$m]] == null || $result[$mFieldArray[$m]] == '0000-00-00 00:00:00') {
        $draftTextShow = true;
        break;
    }
}

$dateUtils = new DateUtility();

// create new PDF document
$pdf = new SouthSudan_PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
    $logoPrintInPdf = $result['facilityLogo'];
} else {
    $logoPrintInPdf = $arr['logo'];
}
$pdf->setHeading($logoPrintInPdf, $arr['header'], $result['labName'], $title = 'COVID-19 PATIENT REPORT', $labFacilityId = null, $formId = $arr['vl_form'], $facilityInfo);
// set document information
$pdf->SetCreator('VLSM');
$pdf->SetTitle('SARS-CoV-2 Patient Report');
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
$pdf->SetMargins(10, PDF_MARGIN_TOP + 14, 10);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

// set auto page breaks
$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

// set image scale factor
$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);



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
    $ageCalc = $dateUtils->ageInYearMonthDays($result['patient_dob']);
} elseif (isset($result['patient_age']) && trim($result['patient_age']) != '' && trim($result['patient_age']) > 0) {
    $age = $result['patient_age'];
}

if (isset($result['sample_collection_date']) && trim($result['sample_collection_date']) != '' && $result['sample_collection_date'] != '0000-00-00 00:00:00') {
    $expStr = explode(" ", $result['sample_collection_date']);
    $result['sample_collection_date'] = DateUtility::humanReadableDateFormat($expStr[0]);
    $sampleCollectionTime = $expStr[1];
} else {
    $result['sample_collection_date'] = '';
    $sampleCollectionTime = '';
}
$sampleReceivedDate = '';
$sampleReceivedTime = '';
if (isset($result['sample_received_at_vl_lab_datetime']) && trim($result['sample_received_at_vl_lab_datetime']) != '' && $result['sample_received_at_vl_lab_datetime'] != '0000-00-00 00:00:00') {
    $expStr = explode(" ", $result['sample_received_at_vl_lab_datetime']);
    $sampleReceivedDate = DateUtility::humanReadableDateFormat($expStr[0]);
    $sampleReceivedTime = $expStr[1];
}
$sampleDispatchDate = '';
$sampleDispatchTime = '';
if (isset($result['result_printed_datetime']) && trim($result['result_printed_datetime']) != '' && $result['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
    $expStr = explode(" ", $result['result_printed_datetime']);
    $sampleDispatchDate = DateUtility::humanReadableDateFormat($expStr[0]);
    $sampleDispatchTime = $expStr[1];
} else {
    $expStr = explode(" ", $currentDateTime);
    $sampleDispatchDate = DateUtility::humanReadableDateFormat($expStr[0]);
    $sampleDispatchTime = $expStr[1];
}

$testedBy = '';
if (isset($result['tested_by']) && !empty($result['tested_by'])) {
    $testedByRes = $users->getUserInfo($result['tested_by'], 'user_name');
    if ($testedByRes) {
        $testedBy = $testedByRes['user_name'];
    }
}

$testUserSignaturePath = null;
if (!empty($testedByRes['user_signature'])) {
    $testUserSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $testedByRes['user_signature'];
}

if (isset($result['sample_tested_datetime']) && trim($result['sample_tested_datetime']) != '' && $result['sample_tested_datetime'] != '0000-00-00 00:00:00') {
    $expStr = explode(" ", $result['sample_tested_datetime']);
    $result['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
    $result['sample_tested_datetime'] = '';
}

if (!isset($result['patient_gender']) || trim($result['patient_gender']) == '') {
    $result['patient_gender'] = 'not reported';
}

$userRes = [];
if (isset($result['authorized_by']) && trim($result['authorized_by']) != '') {
    $resultApprovedBy = ($result['authorized_by']);
    $userRes = $users->getUserInfo($result['result_approved_by'], 'user_signature');
} else {
    $resultApprovedBy  = '';
}
$userSignaturePath = null;

if (!empty($userRes['user_signature'])) {
    $userSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $userRes['user_signature'];
}
$vlResult = '';
$smileyContent = '';
$showMessage = '';
$tndMessage = '';
$messageTextSize = '12px';
if ($result['result'] != null && trim($result['result']) != '') {
    $resultType = is_numeric($result['result']);
    if ($result['result'] == 'positive') {
        $vlResult = $result['result'];
        //$smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" alt="smile_face"/>';
    } else if ($result['result'] == 'negative') {
        $vlResult = $result['result'];
        $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" alt="smile_face"/>';
    } else if ($result['result'] == 'indeterminate') {
        $vlResult = $result['result'];
        $smileyContent = '';
    }
}
if (isset($arr['show_smiley']) && trim($arr['show_smiley']) == "no") {
    $smileyContent = '';
}
if ($result['result_status'] == '4') {
    $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/cross.png" alt="rejected"/>';
}
foreach ($covid19TestInfo as $indexKey => $rows) {
    $testPlatform = $rows['testing_platform'];
    $testMethod = $rows['test_name'];
}

$html = '<br><br>';
$html .= '<table style="padding:3px;border:1px solid #67b3ff;">';
$html .= '<tr>';
$html .= '<td colspan="2" style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid #67b3ff">CLIENT IDENTIFICATION DETAILS</td>';
$html .= '<td colspan="2" style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid #67b3ff;">TESTING LAB INFORMATION</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">FULL NAME </td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $patientFname . ' ' . $patientLname . '</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">LABORATORY NAME</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . ($result['labName']) . '(' . ($result['facility_code']) . ')</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">SEX</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . (str_replace("_", " ", $result['patient_gender'])) . '</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">EMAIL</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $result['labEmail'] . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">AGE</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $ageCalc['year'] . 'Year(s) ' . $ageCalc['months'] . 'Months</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">PHONE</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $result['labPhone'] . '</td>';

$html .= '</tr>';

$html .= '<tr>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">NATIONALITY</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $result['nationality'] . '</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">ADDRESS</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $result['labAddress'] . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">PASSPORT # / NIN </td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $result['patient_passport_number'] . '</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">STATE</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . ($result['labState']) . '</td>';

$html .= '</tr>';

$html .= '<tr>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">CASE ID</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $result['patient_id'] . '</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">COUNTY</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . ($result['labCounty']) . '</td>';
$html .= '</tr>';
$html .= '<tr>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;"></td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;"></td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">TEST PLATFORM</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $testPlatform . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td colspan="4" style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-top:1px solid #67b3ff;border-bottom:1px solid #67b3ff;">SPECIMEN INFORMATION</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">LAB SPECIMEN ID</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;font-weight:bold; color:#4ea6ff;">' . $result['sample_code'] . '</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">DATE SPECIMEN COLLECTED</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $result['sample_collection_date'] . " " . $sampleCollectionTime . '</td>';
$html .= '</tr>';
$html .= '<tr>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">SPECIMEN TYPE</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . ($result['sample_name']) . '</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">DATE SPECIMEN RECEIVED</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $sampleReceivedDate . " " . $sampleReceivedTime . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;"></td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;"></td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">DATE SPECIMEN TESTED</td>';
$html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $result['sample_tested_datetime'] . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td colspan="4" style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-top:1px solid #67b3ff;border-bottom:1px solid #67b3ff;">COVID-19 TESTS RESULTS</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td colspan="4" style="line-height:20px;font-size:11px;text-align:left;"><span style="font-weight:bold;">TEST METHOD :</span> ' . $testMethod . '</td>';
$html .= '</tr>';

// if (isset($covid19TestInfo) && count($covid19TestInfo) > 0 && $arr['covid19_tests_table_in_results_pdf'] == 'yes') {
//     /* Test Result Section */
//     $html .= '<tr>';
//     $html .= '<td colspan="4"  >';
//     $html .= '<table border="1" style="padding:2px;">
//                             <tr>
//                                 <td align="center" width="10%" style="line-height:20px;font-size:11px;font-weight:bold;">S. No.</td>
//                                 <td align="center" width="25%" style="line-height:20px;font-size:11px;font-weight:bold;">Test Method</td>
//                                 <td align="center" width="25%" style="line-height:20px;font-size:11px;font-weight:bold;">Test Platform</td>
//                                 <td align="center" width="20%" style="line-height:20px;font-size:11px;font-weight:bold;">Date of Testing</td>
//                                 <td align="center" width="20%" style="line-height:20px;font-size:11px;font-weight:bold;">Test Result</td>
//                             </tr>';

//     foreach ($covid19TestInfo as $indexKey => $rows) {
//         $html .= '<tr>
//                                 <td align="center" style="line-height:20px;font-size:11px;">' . ($indexKey + 1) . '</td>
//                                 <td align="center" style="line-height:20px;font-size:11px;">' . $covid19TestInfo[$indexKey]['test_name'] . '</td>
//                                 <td align="center" style="line-height:20px;font-size:11px;">' . $covid19TestInfo[$indexKey]['testing_platform'] . '</td>
//                                 <td align="center" style="line-height:20px;font-size:11px;">' . date("d-M-Y H:i:s", strtotime($covid19TestInfo[$indexKey]['sample_tested_datetime'])) . '</td>
//                                 <td align="center" style="line-height:20px;font-size:11px;">' . ($covid19TestInfo[$indexKey]['result']) . '</td>
//                             </tr>';
//     }
//     $html .= '</table>';
//     $html .= '</td>';
//     $html .= '</tr>';
// }
/* Result print here */
$resultFlag = "";
if (isset($result['result']) && $result['result'] == "negative") {
    $resultFlag = "(-)";
} else if (isset($result['result']) && $result['result'] == "postive") {
    $resultFlag = "(+)";
}

$html .= '<tr>';
$html .= '<td colspan="4" style="font-size:18px;font-weight:bold;font-weight:normal;"><br>RESULT : ' . $covid19Results[$result['result']] . ' ' . $resultFlag . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td colspan="4" style="line-height:17px;font-size:11px;text-align:left;"><span style="font-weight:bold;">DATE RESULTS RELEASED :</span> ' . $sampleDispatchDate . " " . $sampleDispatchTime . '</td>';
$html .= '</tr>';

if ($result['reason_for_sample_rejection'] != '') {
    $html .= '<tr>';
    $html .= '<td colspan="4" style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">REJECTION REASON : <span style="font-weight:normal;">' . $result['rejection_reason_name'] . '</span></td>';
    $html .= '</tr>';
}
if (trim($result['lab_tech_comments']) != '') {
    $html .= '<tr>';
    $html .= '<td colspan="4" style="line-height:17px;font-size:11px;font-weight:bold;">LAB COMMENTS : <span style="font-weight:normal;">' . ($result['lab_tech_comments']) . '</span></td>';
    $html .= '</tr>';
}
$html .= '</table>';

$html .= '<table>';
$html .= '<tr>';
$html .= '<td colspan="2" style="line-height:20px;"></td>';
$html .= '</tr>';
$html .= '</table>';

if (isset($signResults) && !empty($signResults)) {
    $html .= '<table align="center" style="min-height:120px">';
    $html .= '<tr>';
    $html .= '<td  colspan="4" style="text-align:center;" align="center">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
    $html .= '<table style="width:80%;padding:3px;border:1px solid #67b3ff;">';
    $html .= '<tr>';
    $html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid #67b3ff;">AUTHORISED BY</td>';
    $html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;">PRINT NAME</td>';
    $html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;">SIGNATURE</td>';
    $html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;">DATE & TIME</td>';
    $html .= '</tr>';
    foreach ($signResults as $key => $row) {
        $lmSign = "/uploads/labs/" . $row['lab_id'] . "/signatures/" . $row['signature'];
        $html .= '<tr>';
        $html .= '<td style="line-height:17px;font-size:11px;text-align:left;font-weight:bold;border-bottom:1px solid #67b3ff;">' . $row['designation'] . '</td>';
        $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;">' . $row['name_of_signatory'] . '</td>';
        $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;"><img src="' . $lmSign . '" style="width:30px;"></td>';
        $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;">' . date('d-M-Y H:i:s a') . '</td>';
        $html .= '</tr>';
    }
    /* 
            $lqSign = "/uploads/covid-19/{$countryFormId}/pdf/lq.png";
            $html .= '<tr>';
            $html .= '<td style="line-height:17px;font-size:11px;text-align:left;font-weight:bold;border-bottom:1px solid #67b3ff;">Laboratory Quality Manager</td>';
            $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;">Abe Gordon Abias</td>';
            $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;"><img src="' . $lqSign . '" style="width:30px;"></td>';
            $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;">' . date('d-M-Y H:i:s a') . '</td>';
            $html .= '</tr>';
            
            $lsSign = "/uploads/covid-19/{$countryFormId}/pdf/ls.png";
            $html .= '<tr>';
            $html .= '<td style="line-height:17px;font-size:11px;text-align:left;font-weight:bold;border-bottom:1px solid #67b3ff;">Laboratory Supervisor</td>';
            $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;">Dr. Simon Deng Nyicar</td>';
            $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;"><img src="' . $lsSign . '" style="width:30px;"></td>';
            $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;">' . date('d-M-Y H:i:s a') . '</td>';
            $html .= '</tr>'; */
    $html .= '</table>';
    $html .= '</td>';
    $html .= '</tr>';
    $html .= '</table>';
} else {
    $html .= '<tr>';
    $html .= '<td colspan="5" style="line-height:50px;"></td>';
    $html .= '</tr>';
}
$html .= '<table>';
$html .= '<tr>';
$html .= '<td colspan="2" style="line-height:20px;border-bottom:2px solid #d3d3d3;"></td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td colspan="2" style="font-size:10px;text-align:left;width:60%;"></td>';
$html .= '</tr>';
if ($_SESSION['instanceType'] == 'vluser' && $result['dataSync'] == 0) {
    $generatedAtTestingLab = " | " . _("Report generated at Testing Lab");
} else {
    $generatedAtTestingLab = "";
}
$html .= '<tr>';
$html .= '<td style="font-size:10px;text-align:left;" colspan="2">Printed on : ' . $printDate . '&nbsp;&nbsp;' . $printDateTime . $generatedAtTestingLab . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td colspan="2" style="font-size:10px;text-align:left;width:60%;"></td>';
$html .= '</tr>';
$html .= '</table>';
if (($result['result'] != '') || ($result['result'] == '' && $result['result_status'] == '4')) {
    $ciphering = "AES-128-CTR";
    $iv_length = openssl_cipher_iv_length($ciphering);
    $options = 0;
    $simple_string = $result['covid19_id'] . "&&&qr";
    $encryption_iv = SYSTEM_CONFIG['tryCrypt'];
    $encryption_key = SYSTEM_CONFIG['tryCrypt'];
    $Cid = openssl_encrypt(
        $simple_string,
        $ciphering,
        $encryption_key,
        $options,
        $encryption_iv
    );
    $pdf->writeHTML($html);
    if (isset($arr['covid19_report_qr_code']) && $arr['covid19_report_qr_code'] == 'yes' && !empty(SYSTEM_CONFIG['remoteURL'])) {
        $remoteUrl = rtrim(SYSTEM_CONFIG['remoteURL'], "/");
        $pdf->write2DBarcode($remoteUrl . '/covid-19/results/view.php?q=' . $Cid, 'QRCODE,H', 170, 175, 20, 20, $style, 'N');
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
if (isset($_POST['source']) && trim($_POST['source']) == 'print') {
    //Add event log
    $eventType = 'print-result';
    $action = ($_SESSION['userName'] ?: 'System') . ' generated the test result PDF with Patient ID/Code ' . $result['patient_id'];
    $resource = 'print-test-result';
    $data = array(
        'event_type' => $eventType,
        'action' => $action,
        'resource' => $resource,
        'date_time' => $currentDateTime
    );
    $db->insert($tableName1, $data);
    //Update print datetime in VL tbl.
    $vlQuery = "SELECT result_printed_datetime FROM form_covid19 as vl WHERE vl.covid19_id ='" . $result['covid19_id'] . "'";
    $vlResult = $db->query($vlQuery);
    if ($vlResult[0]['result_printed_datetime'] == null || trim($vlResult[0]['result_printed_datetime']) == '' || $vlResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
        $db = $db->where('covid19_id', $result['covid19_id']);
        $db->update($tableName2, array('result_printed_datetime' => $currentDateTime, 'result_dispatched_datetime' => $currentDateTime));
    }
}
