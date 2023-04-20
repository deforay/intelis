<?php

// this file is included in tb/results/generate-result-pdf.php


use App\Utilities\DateUtils;

class SouthSudan_PDF extends MYPDF
{
    //Page header
    public function Header()
    {
        // Logo

        if ($this->htitle != '') {

            if (isset($this->formId) && $this->formId == 1) {
                if (trim($this->logo) != '') {
                    if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                        $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                        $this->Image($imageFilePath, 10, 5, 25, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                    }
                }
                $this->SetFont('helvetica', 'B', 15);
                $this->writeHTMLCell(0, 0, 15, 7, $this->text, 0, 0, 0, true, 'C', true);
                if (trim($this->lab) != '') {
                    $this->SetFont('helvetica', 'B', 11);
                    // $this->writeHTMLCell(0, 0, 40, 15, strtoupper($this->lab), 0, 0, 0, true, 'L', true);
                    $this->writeHTMLCell(0, 0, 15, 15, 'Public Health Laboratory', 0, 0, 0, true, 'C', true);
                }

                $this->SetFont('helvetica', '', 9);
                $this->writeHTMLCell(0, 0, 15, 21, $this->facilityInfo['address'], 0, 0, 0, true, 'C', true);

                $this->SetFont('helvetica', '', 9);

                $emil = (isset($this->facilityInfo['report_email']) && $this->facilityInfo['report_email'] != "") ? 'E-mail : ' . $this->facilityInfo['report_email'] : "";
                $phone = (isset($this->facilityInfo['facility_mobile_numbers']) && $this->facilityInfo['facility_mobile_numbers'] != "") ? 'Phone : ' . $this->facilityInfo['facility_mobile_numbers'] : "";
                if (isset($this->facilityInfo['report_email']) && $this->facilityInfo['report_email'] != "" && isset($this->facilityInfo['facility_mobile_numbers']) && $this->facilityInfo['facility_mobile_numbers'] != "") {
                    $space = '&nbsp;&nbsp;|&nbsp;&nbsp;';
                } else {
                    $space = "";
                }
                $this->writeHTMLCell(0, 0, 15, 26, $emil . $space . $phone, 0, 0, 0, true, 'L', true);


                $this->writeHTMLCell(0, 0, 10, 33, '<hr>', 0, 0, 0, true, 'C', true);
                $this->writeHTMLCell(0, 0, 10, 34, '<hr>', 0, 0, 0, true, 'C', true);
                $this->SetFont('helvetica', 'B', 12);
                $this->writeHTMLCell(0, 0, 20, 35, 'SOUTH SUDAN TB SAMPLES REFERRAL SYSTEM (SS)', 0, 0, 0, true, 'C', true);

                // $this->writeHTMLCell(0, 0, 25, 35, '<hr>', 0, 0, 0, true, 'C', true);
            } else {
                if (trim($this->logo) != '') {
                    if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                        $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                        $this->Image($imageFilePath, 95, 5, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                    }
                }

                $this->SetFont('helvetica', 'B', 8);
                $this->writeHTMLCell(0, 0, 10, 22, $this->text, 0, 0, 0, true, 'C', true);
                if (trim($this->lab) != '') {
                    $this->SetFont('helvetica', '', 9);
                    $this->writeHTMLCell(0, 0, 10, 26, strtoupper($this->lab), 0, 0, 0, true, 'C', true);
                }

                $this->SetFont('helvetica', '', 14);
                $this->writeHTMLCell(0, 0, 10, 30, 'PATIENT REPORT FOR TB TEST', 0, 0, 0, true, 'C', true);

                $this->writeHTMLCell(0, 0, 15, 38, '<hr>', 0, 0, 0, true, 'C', true);
            }
        }
    }
}


$dateUtils = new DateUtils();
$tbLamResults = $tbObj->getTbResults('lam');
$tbXPertResults = $tbObj->getTbResults('x-pert');

$countryFormId = $general->getGlobalConfig('vl_form');
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

        $tbTestQuery = "SELECT * from tb_tests where tb_id= " . $result['tb_id'] . " ORDER BY tb_test_id DESC";
        $tbTestInfo = $db->rawQuery($tbTestQuery);

        $facilityQuery = "SELECT * from form_tb as c19 INNER JOIN facility_details as fd ON c19.facility_id=fd.facility_id where tb_id= " . $result['tb_id'] . " GROUP BY fd.facility_id LIMIT 1";
        $facilityInfo = $db->rawQueryOne($facilityQuery);
        // echo "<pre>";print_r($tbTestInfo);die;
        $patientFname = ($general->crypto('doNothing', $result['patient_name'], $result['patient_id']));
        $patientLname = ($general->crypto('doNothing', $result['patient_surname'], $result['patient_id']));

        $signQuery = "SELECT * from lab_report_signatories where lab_id=? AND test_types like '%tb%' AND signatory_status like 'active' ORDER BY display_order ASC";
        $signResults = $db->rawQuery($signQuery, array($result['lab_id']));

        $currentTime = DateUtils::getCurrentDateTime();
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
        $pdf = new SouthSudan_PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
            $logoPrintInPdf = $result['facilityLogo'];
        } else {
            $logoPrintInPdf = $arr['logo'];
        }
        $pdf->setHeading($logoPrintInPdf, $arr['header'], $result['labName'], $title = 'SOUTH SUDAN TB SAMPLES REFERRAL SYSTEM (SS)', $labFacilityId = null, $formId = $arr['vl_form'], $facilityInfo);
        // set document information
        $pdf->SetCreator('VLSM');
        $pdf->SetTitle('SOUTH SUDAN TB SAMPLES REFERRAL SYSTEM (SS)');
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
        $ageCalc = 0;
        $age = 'Unknown';
        if (isset($result['patient_dob']) && trim($result['patient_dob']) != '' && $result['patient_dob'] != '0000-00-00') {
            $ageCalc = $dateUtils->ageInYearMonthDays($result['patient_dob']);
        } elseif (isset($result['patient_age']) && trim($result['patient_age']) != '' && trim($result['patient_age']) > 0) {
            $age = $result['patient_age'];
        }

        if (isset($result['sample_collection_date']) && trim($result['sample_collection_date']) != '' && $result['sample_collection_date'] != '0000-00-00 00:00:00') {
            $expStr = explode(" ", $result['sample_collection_date']);
            $result['sample_collection_date'] = DateUtils::humanReadableDateFormat($expStr[0]);
            $sampleCollectionTime = $expStr[1];
        } else {
            $result['sample_collection_date'] = '';
            $sampleCollectionTime = '';
        }
        $sampleReceivedDate = '';
        $sampleReceivedTime = '';
        if (isset($result['sample_received_at_lab_datetime']) && trim($result['sample_received_at_lab_datetime']) != '' && $result['sample_received_at_lab_datetime'] != '0000-00-00 00:00:00') {
            $expStr = explode(" ", $result['sample_received_at_lab_datetime']);
            $sampleReceivedDate = DateUtils::humanReadableDateFormat($expStr[0]);
            $sampleReceivedTime = $expStr[1];
        }
        $resultDispatchedDate = '';
        $resultDispatchedTime = '';
        if (isset($result['result_printed_datetime']) && trim($result['result_printed_datetime']) != '' && $result['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
            $expStr = explode(" ", $result['result_printed_datetime']);
            $resultDispatchedDate = DateUtils::humanReadableDateFormat($expStr[0]);
            $resultDispatchedTime = $expStr[1];
        } else {
            $expStr = explode(" ", $currentTime);
            $resultDispatchedDate = DateUtils::humanReadableDateFormat($expStr[0]);
            $resultDispatchedTime = $expStr[1];
        }

        $approvedOnDate = '';
        $approvedOnTime = '';
        if (isset($result['result_approved_datetime']) && trim($result['result_approved_datetime']) != '' && $result['result_approved_datetime'] != '0000-00-00 00:00:00') {
            $expStr = explode(" ", $result['result_approved_datetime']);
            $approvedOnDate = DateUtils::humanReadableDateFormat($expStr[0]);
            $approvedOnTime = $expStr[1];
        } else {
            $expStr = explode(" ", $currentTime);
            $approvedOnDate = DateUtils::humanReadableDateFormat($expStr[0]);
            $approvedOnTime = $expStr[1];
        }

        $testedBy = '';
        if (isset($result['tested_by']) && !empty($result['tested_by'])) {
            $testedByRes = $users->getUserInfo($result['tested_by'], array('user_signature', 'user_name'));
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
            $result['sample_tested_datetime'] = DateUtils::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
        } else {
            $result['sample_tested_datetime'] = '';
        }

        if (!isset($result['patient_gender']) || trim($result['patient_gender']) == '') {
            $result['patient_gender'] = 'not reported';
        }

        $userRes = [];
        if (isset($result['authorized_by']) && trim($result['authorized_by']) != '') {
            $userRes = $users->getUserInfo($result['authorized_by'], array('user_signature', 'user_name'));
            $resultAuthroizedBy = ($userRes['user_name']);
        } else {
            $resultAuthroizedBy  = '';
        }
        $userSignaturePath = null;

        if (!empty($userRes['user_signature'])) {
            $userSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $userRes['user_signature'];
        }

        $userApprovedRes = [];
        if (isset($result['result_approved_by']) && trim($result['result_approved_by']) != '') {
            $userApprovedRes = $users->getUserInfo($result['result_approved_by'], array('user_signature', 'user_name'));
            $resultApprovedBy = ($userApprovedRes['user_name']);
        } else {
            $resultApprovedBy  = '';
        }
        $userApprovedSignaturePath = null;
        if (!empty($userApprovedRes['user_signature'])) {
            $userApprovedSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $userApprovedRes['user_signature'];
        }
        $tbResult = '';
        $smileyContent = '';
        $showMessage = '';
        $tndMessage = '';
        $messageTextSize = '12px';
        if ($result['result'] != null && trim($result['result']) != '') {
            $resultType = is_numeric($result['result']);
            if ($result['result'] == 'positive') {
                $tbResult = $result['result'];
                //$smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" alt="smile_face"/>';
            } else if ($result['result'] == 'negative') {
                $tbResult = $result['result'];
                $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" alt="smile_face"/>';
            } else if ($result['result'] == 'indeterminate') {
                $tbResult = $result['result'];
                $smileyContent = '';
            }
        }
        if (isset($arr['show_smiley']) && trim($arr['show_smiley']) == "no") {
            $smileyContent = '';
        }
        if ($result['result_status'] == '4') {
            $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/cross.png" alt="rejected"/>';
        }
        $fstate = "";
        if (isset($result['facility_state_id']) && $result['facility_state_id'] != "") {
            $geoResult = $geoObj->getById($result['facility_state_id']);
            $fstate = (isset($geoResult['geo_name']) && $geoResult['geo_name'] != "") ? $geoResult['geo_name'] : null;
        }
        if (isset($result['facility_state']) && $result['facility_state'] != "") {
            $fstate = $result['facility_state'];
        }

        $html = '<br><br>';

        $html .= '<table style="padding:3px;">';
        $html .= '<tr>';
        $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:40%">HEALTH FACILITY</td>';
        $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">STATE</td>';
        $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">REGION</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="line-height:17px;font-size:12px;text-align:left;"><span style="font-weight:bold;">' . $result['facility_name'] . '</span></td>';
        $html .= '<td style="line-height:17px;font-size:12px;text-align:left;"><span style="font-weight:bold;">' . $fstate . '</span></td>';
        $html .= '<td style="line-height:17px;font-size:12px;text-align:left;"><span style="font-weight:bold;">GREATER EQUATORIA</span></td>';
        $html .= '</tr>';
        $html .= '</table>';

        $html .= '<br><br>';
        $html .= '<table style="padding:3px;border:1px solid #67b3ff;">';
        $html .= '<tr>';
        $html .= '<td colspan="4" style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-top:1px solid #67b3ff;border-bottom:1px solid #67b3ff;">PATIENT DETAILS</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">PATIENT NAME </td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $patientFname . ' ' . $patientLname . '</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">NATIONAL ID NO</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $result['patient_id'] . '</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">MDR TB NO </td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;"></td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">REASON FOR REQUEST</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;"></td>';
        $html .= '</tr>';
        $typeOfPatient = json_decode($result['patient_type']);
        $html .= '<tr>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">TB PATIENT CATEGORY</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . (str_replace("-", " ", $typeOfPatient)) . '</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">STATE & REGION</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $fstate . '</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">AGE</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $ageCalc['year'] . 'Year(s) ' . $ageCalc['months'] . 'Months</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">RESIDENCE ADDRESS</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $result['labAddress'] . '</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">SEX</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . (str_replace("_", " ", $result['patient_gender'])) . '</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;"></td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;"></td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td colspan="4" style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-top:1px solid #67b3ff;border-bottom:1px solid #67b3ff;">SPECIMEN DETAILS</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">SPECIMEN TYPE </td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $result['sample_name'] . '</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">COLLECTED</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $result['sample_collection_date'] . " " . $sampleCollectionTime . '</td>';

        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">APPEARANCE</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $result['patient_id'] . '</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">RECEIVED</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $sampleReceivedDate . " " . $sampleReceivedTime . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">VOLUME</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;"></td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">REQUESTED BY</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $result['requestedBy'] . '</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td colspan="4" style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-top:1px solid #67b3ff;border-bottom:1px solid #67b3ff;">SUSCEPTIBILITY RESULTS</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">TESTING LAB</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $result['labName'] . '</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">SAMPLE TESTED DATE TIME</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $result['sample_tested_datetime'] . '</td>';
        $html .= '</tr>';

        if (isset($tbTestInfo) && count($tbTestInfo) > 0) {
            /* Test Result Section */
            $html .= '<tr>';
            $html .= '<td colspan="4" style="border:1px solid #67b3ff;" >';
            $html .= '<table style="padding:2px;border:1px solid #ddd;">
                    <tr><th colspan="3" style="border:1px solid #ddd;line-height:20px;font-size:11px;font-weight:bold;text-align:center;">Microscopy Test Results</th></tr>
                    <tr>
                        <td align="center" width="10%" style="border:1px solid #ddd;line-height:20px;font-size:11px;font-weight:bold;">No AFB</td>
                        <td align="center" width="50%" style="border:1px solid #ddd;line-height:20px;font-size:11px;font-weight:bold;">Result</td>
                        <td align="center" width="40%" style="border:1px solid #ddd;line-height:20px;font-size:11px;font-weight:bold;">Actual No</td>
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
        /* Result print here */
        $html .= '<tr>';
        $html .= '<td colspan="4" style="font-size:15px;font-weight:normal;font-weight:normal;border:1px solid #67b3ff;"><br><br>XPERT MTB RESULT : <span style="font-weight:bold;">' . $tbXPertResults[$result['xpert_mtb_result']] . '</span></td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td colspan="4" style="font-size:15px;font-weight:normal;font-weight:normal;border:1px solid #67b3ff;">TB LAM RESULT : <span style="font-weight:bold;">' . $tbLamResults[$result['result']] . '</span></td>';
        $html .= '</tr>';

        if ($result['reason_for_sample_rejection'] != '') {
            $html .= '<tr>';
            $html .= '<td colspan="4" style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">REJECTION REASON : <span style="font-weight:normal;">' . $result['rejection_reason_name'] . '</span></td>';
            $html .= '</tr>';
        }
        if (trim($result['lab_tech_comments']) != '') {
            $html .= '<tr>';
            $html .= '<td colspan="4" style="line-height:17px;font-size:11px;font-weight:bold;text-align:left;border-top:1px solid #67b3ff;border-bottom:1px solid #67b3ff;">COMMENTS : <span style="font-weight:normal;">' . ($result['lab_tech_comments']) . '</span></td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
        $html .= '<br><br>';
        $html .= '<table>';
        $html .= '<tr>';
        $html .= '<td colspan="3" style="line-height:17px;font-size:11px;font-weight:bold;">For questions concerning this report, contact the Laboratory at Telephone Number 0925864308 / 0922302801</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td></td>';
        $html .= '<td style="line-height:17px;font-size:11px;font-weight:normal;"><img width="50" src="' . $userApprovedSignaturePath . '"/></td>';
        $html .= '<td></td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="line-height:17px;font-size:11px;font-weight:normal;">Print Time : ' . $printDate . " " . $printDateTime . '</td>';
        $html .= '<td style="line-height:17px;font-size:11px;font-weight:normal;">Result Approved : ' . $resultApprovedBy . '</td>';
        $html .= '<td style="line-height:17px;font-size:11px;font-weight:normal;">Date : ' . $approvedOnDate . " " . $approvedOnTime . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td colspan="3" style="line-height:17px;font-size:11px;text-align:justify;border-top:1px solid #67b3ff;">
                    <br><br>NP = Not Provided, DST = Drug Susceptibility Testing, LJ = Lowenstein-Jensen, MDR = Multi-Drug Restant TB Strain, XDR = Extensively Drug Resistant TB Stain, MGIT = Mycobacterium Growth Index Tube, 
                    NTM = Non-TB Mycobacterium, ZN = Ziehl-Neelsen, 1-100 = Absolute colony counts on solid media, Smear Mircoscopy Grading 1-9/100 fields = absolute number of AFBs seen per 100 fields, 1+= 1-100/100 fields, 2+=1-9 AFBs/field; 
                    3+=10+AFBs/field, FM = Fluorescent Microscopy, Negative = Zero AFBs/1 Length, Scanty = 1-29 AFB/1 Length, 2+=10-100 AFB/1 Field on average, 3+=>100 AFB/1 Field on average, LPA = Line Probe Assay,
                    FLQ = Fuoroquinolones(Ofloxacin, Moxifloxacin), EMB = Ethambutol, AG/CP = Injectible antibotics(Kanamycin, Amikacin/Capreomycin, Viomycin), PAS = Para-Aminosalicylic Acid
                </td>';
        $html .= '</tr>';
        $html .= '</table>';

        if ($result['result'] != '' || ($result['result'] == '' && $result['result_status'] == '4')) {
            $ciphering = "AES-128-CTR";
            $iv_length = openssl_cipher_iv_length($ciphering);
            $options = 0;
            $simple_string = $result['tb_id'] . "&&&qr";
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
            $remoteUrl = rtrim(SYSTEM_CONFIG['remoteURL'], "/");
            if (isset($arr['tb_report_qr_code']) && $arr['tb_report_qr_code'] == 'yes') {
                $h = 175;
                if (isset($signResults) && !empty($signResults)) {
                    if (isset($facilityInfo['address']) && $facilityInfo['address'] != "") {
                        $h = 185;
                    }
                } else {
                    $h = 148.5;
                }
                //$pdf->write2DBarcode($remoteUrl . '/tb/results/view.php?q=' . $Cid . '', 'QRCODE,H', 170, $h, 20, 20, $style, 'N');
            }
            $pdf->lastPage();
            $filename = $pathFront . DIRECTORY_SEPARATOR . 'p' . $page . '.pdf';
            $pdf->Output($filename, "F");
            if ($draftTextShow) {
                //Watermark section
                $watermark = new Watermark();
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
                'date_time' => $currentTime
            );
            $db->insert($tableName1, $data);
            //Update print datetime in TB tbl.
            $tbQuery = "SELECT result_printed_datetime FROM form_tb as tb WHERE tb.tb_id ='" . $result['tb_id'] . "'";
            $tbResult = $db->query($tbQuery);
            if ($tbResult[0]['result_printed_datetime'] == null || trim($tbResult[0]['result_printed_datetime']) == '' || $tbResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
                $db = $db->where('tb_id', $result['tb_id']);
                $db->update($tableName2, array('result_printed_datetime' => $currentTime, 'result_dispatched_datetime' => $currentTime));
            }
        }
    }

    if (!empty($pages)) {
        $resultPdf = new Pdf_concat();
        $resultPdf->setFiles($pages);
        $resultPdf->setPrintHeader(false);
        $resultPdf->setPrintFooter(false);
        $resultPdf->concat();
        $resultFilename = 'VLSM-TB-Test-result-' . date('d-M-Y-H-i-s') . "-" . $general->generateRandomString(6) . '.pdf';
        $resultPdf->Output(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename, "F");
        $general->removeDirectory($pathFront);
        unset($_SESSION['rVal']);
    }
}
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename);
