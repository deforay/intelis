<?php

// this file is included in covid-19/results/generate-result-pdf.php


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
                        $image_file = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                        $this->Image($image_file, 10, 5, 25, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                    }
                }
                $this->SetFont('helvetica', 'B', 15);
                $this->writeHTMLCell(0, 0, 40, 7, $this->text, 0, 0, 0, true, 'L', true);
                if (trim($this->lab) != '') {
                    $this->SetFont('helvetica', 'B', 11);
                    $this->writeHTMLCell(0, 0, 40, 15, strtoupper($this->lab), 0, 0, 0, true, 'L', true);
                }

                $this->SetFont('helvetica', '', 9);
                $this->writeHTMLCell(0, 0, 40, 21, 'Juba - Addis Ababa Road (near Mobil roundabout)', 0, 0, 0, true, 'L', true);

                $this->SetFont('helvetica', '', 9);
                $this->writeHTMLCell(0, 0, 40, 26, 'E-mail : nphlsscovid19results@gmail.com&nbsp;&nbsp;|&nbsp;&nbsp;Phone : 0929310671', 0, 0, 0, true, 'L', true);


                $this->writeHTMLCell(0, 0, 10, 33, '<hr>', 0, 0, 0, true, 'C', true);
                $this->writeHTMLCell(0, 0, 10, 34, '<hr>', 0, 0, 0, true, 'C', true);
                $this->SetFont('helvetica', 'B', 12);
                $this->writeHTMLCell(0, 0, 20, 35, 'SARS-COV-2 Laboratory Report', 0, 0, 0, true, 'C', true);

                // $this->writeHTMLCell(0, 0, 25, 35, '<hr>', 0, 0, 0, true, 'C', true);
            } else {
                if (trim($this->logo) != '') {
                    if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                        $image_file = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                        $this->Image($image_file, 95, 5, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                    }
                }

                $this->SetFont('helvetica', 'B', 8);
                $this->writeHTMLCell(0, 0, 10, 22, $this->text, 0, 0, 0, true, 'C', true);
                if (trim($this->lab) != '') {
                    $this->SetFont('helvetica', '', 9);
                    $this->writeHTMLCell(0, 0, 10, 26, strtoupper($this->lab), 0, 0, 0, true, 'C', true);
                }

                $this->SetFont('helvetica', '', 14);
                $this->writeHTMLCell(0, 0, 10, 30, 'PATIENT REPORT FOR COVID-19 TEST', 0, 0, 0, true, 'C', true);

                $this->writeHTMLCell(0, 0, 15, 38, '<hr>', 0, 0, 0, true, 'C', true);
            }
        }
    }
}




$covid19Results = $general->getCovid19Results();

$countryFormId = $general->getGlobalConfig('vl_form');
$resultFilename = '';

if (sizeof($requestResult) > 0) {
    $_SESSION['rVal'] = $general->generateRandomString(6);
    $pathFront = (UPLOAD_PATH . DIRECTORY_SEPARATOR .  $_SESSION['rVal']);
    if (!file_exists($pathFront) && !is_dir($pathFront)) {
        mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal']);
        $pathFront = realpath(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal']);
    }
    $pages = array();
    $page = 1;
    foreach ($requestResult as $result) {

        $covid19TestQuery = "SELECT * from covid19_tests where covid19_id= " . $result['covid19_id'] . " ORDER BY test_id ASC";
        $covid19TestInfo = $db->rawQuery($covid19TestQuery);
        // echo "<pre>";print_r($covid19TestInfo);die;
        $patientFname = ucwords($general->crypto('decrypt', $result['patient_name'], $result['patient_id']));
        $patientLname = ucwords($general->crypto('decrypt', $result['patient_surname'], $result['patient_id']));

        $currentTime = $general->getDateTime();
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
        $pdf->setHeading($arr['logo'], $arr['header'], $result['labName'], $title = 'COVID-19 PATIENT REPORT', $labFacilityId = null, $formId = $arr['vl_form']);
        // set document information
        $pdf->SetCreator('VLSM');
        $pdf->SetTitle('Covid-19 Patient Report');
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
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

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
            $ageCalc = $general->ageInYearMonthDays($result['patient_dob']);
        } elseif (isset($result['patient_age']) && trim($result['patient_age']) != '' && trim($result['patient_age']) > 0) {
            $age = $result['patient_age'];
        }

        if (isset($result['sample_collection_date']) && trim($result['sample_collection_date']) != '' && $result['sample_collection_date'] != '0000-00-00 00:00:00') {
            $expStr = explode(" ", $result['sample_collection_date']);
            $result['sample_collection_date'] = $general->humanDateFormat($expStr[0]);
            $sampleCollectionTime = $expStr[1];
        } else {
            $result['sample_collection_date'] = '';
            $sampleCollectionTime = '';
        }
        $sampleReceivedDate = '';
        $sampleReceivedTime = '';
        if (isset($result['sample_received_at_vl_lab_datetime']) && trim($result['sample_received_at_vl_lab_datetime']) != '' && $result['sample_received_at_vl_lab_datetime'] != '0000-00-00 00:00:00') {
            $expStr = explode(" ", $result['sample_received_at_vl_lab_datetime']);
            $sampleReceivedDate = $general->humanDateFormat($expStr[0]);
            $sampleReceivedTime = $expStr[1];
        }
        $sampleDisbatchDate = '';
        $sampleDisbatchTime = '';
        if (isset($result['result_printed_datetime']) && trim($result['result_printed_datetime']) != '' && $result['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
            $expStr = explode(" ", $result['result_printed_datetime']);
            $sampleDisbatchDate = $general->humanDateFormat($expStr[0]);
            $sampleDisbatchTime = $expStr[1];
        } else {
            $expStr = explode(" ", $currentTime);
            $sampleDisbatchDate = $general->humanDateFormat($expStr[0]);
            $sampleDisbatchTime = $expStr[1];
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
            $result['sample_tested_datetime'] = $general->humanDateFormat($expStr[0]) . " " . $expStr[1];
        } else {
            $result['sample_tested_datetime'] = '';
        }

        if (!isset($result['patient_gender']) || trim($result['patient_gender']) == '') {
            $result['patient_gender'] = 'not reported';
        }

        $userRes = array();
        if (isset($result['authorized_by']) && trim($result['authorized_by']) != '') {
            $resultApprovedBy = ucwords($result['authorized_by']);
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
        if ($result['result'] != NULL && trim($result['result']) != '') {
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
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;">FULL NAME </td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $patientFname . ' ' . $patientLname . '</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">LABORATORY NAME</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . ($result['labName']) . '(' . ($result['facility_code']) . ')</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">SEX</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . ucwords(str_replace("_", " ", $result['patient_gender'])) . '</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">STATE</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . ucwords($result['labState']) . '</td>';

        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">AGE</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $ageCalc['year'] . 'Year(s) ' . $ageCalc['months'] . 'Months</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">COUNTY</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . ucwords($result['labCounty']) . '</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">PASSPORT # / NIN </td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $result['patient_passport_number'] . '</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">TEST PLATFORM</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $testPlatform . '</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">NATIONALITY</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $result['nationality'] . '</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;border-left:1px solid #67b3ff;">CASE ID</td>';
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . $result['patient_id'] . '</td>';
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
        //     $html .= '<td colspan="4" style="" >';
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
        //                                 <td align="center" style="line-height:20px;font-size:11px;">' . ucwords($covid19TestInfo[$indexKey]['result']) . '</td>
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
        $html .= '<td colspan="4" style="line-height:17px;font-size:11px;text-align:left;"><span style="font-weight:bold;">DATE RESULTS RELEASED :</span> ' . $sampleDisbatchDate . " " . $sampleDisbatchTime . '</td>';
        $html .= '</tr>';

        if ($result['reason_for_sample_rejection'] != '') {
            $html .= '<tr>';
            $html .= '<td colspan="4" style="line-height:20px;font-size:11px;text-align:left;font-weight:bold;">REJECTION REASON : <span style="font-weight:normal;">' . $result['rejection_reason_name'] . '</span></td>';
            $html .= '</tr>';
        }
        if (trim($result['approver_comments']) != '') {
            $html .= '<tr>';
            $html .= '<td colspan="4" style="line-height:17px;font-size:11px;font-weight:bold;">LAB COMMENTS : <span style="font-weight:normal;">' . ucfirst($result['approver_comments']) . '</span></td>';
            $html .= '</tr>';
        }
        $html .= '</table>';

        $html .= '<table>';
        $html .= '<tr>';
        $html .= '<td colspan="2" style="line-height:20px;"></td>';
        $html .= '</tr>';
        $html .= '</table>';

        $html .= '<table align="center">';
        $html .= '<tr>';
        $html .= '<td  colspan="4" style="text-align:center;" align="center">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
        $html .= '<table style="width:90%;padding:3px;border:1px solid #67b3ff;">';
        $html .= '<tr>';
        $html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid #67b3ff;">AUTHORISED BY</td>';
        $html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;">PRINT NAME</td>';
        $html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;">SIGNATURE</td>';
        $html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;">DATE & TIME</td>';
        $html .= '</tr>';

        $lmSign = "/uploads/covid-19/{$countryFormId}/pdf/lm.png";
        $html .= '<tr>';
        $html .= '<td style="line-height:17px;font-size:11px;text-align:left;font-weight:bold;border-bottom:1px solid #67b3ff;">Laboratory Manager</td>';
        $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;">James Ayei  Maror</td>';
        $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;"><img src="' . $lmSign . '" style="width:30px;"></td>';
        $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;">' . date('d-M-Y H:i:s a') . '</td>';
        $html .= '</tr>';

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
        $html .= '</tr>';
        $html .= '</table>';
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '</table>';

        $html .= '<table>';
        $html .= '<tr>';
        $html .= '<td colspan="2" style="line-height:20px;border-bottom:2px solid #d3d3d3;"></td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td colspan="2" style="font-size:10px;text-align:left;width:60%;"></td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="font-size:10px;text-align:left;">Printed on : ' . $printDate . '&nbsp;&nbsp;' . $printDateTime . '</td>';
        $html .= '<td style="font-size:10px;text-align:left;width:60%;"></td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td colspan="2" style="font-size:10px;text-align:left;width:60%;"></td>';
        $html .= '</tr>';
        $html .= '</table>';
        if ($result['result'] != '' || ($result['result'] == '' && $result['result_status'] == '4')) {
            $pdf->writeHTML($html);
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
            $action = ucwords($_SESSION['userName']) . ' printed the test result with patient code ' . $result['patient_id'];
            $resource = 'print-test-result';
            $data = array(
                'event_type' => $eventType,
                'action' => $action,
                'resource' => $resource,
                'date_time' => $currentTime
            );
            $db->insert($tableName1, $data);
            //Update print datetime in VL tbl.
            $vlQuery = "SELECT result_printed_datetime FROM form_covid19 as vl WHERE vl.covid19_id ='" . $result['covid19_id'] . "'";
            $vlResult = $db->query($vlQuery);
            if ($vlResult[0]['result_printed_datetime'] == NULL || trim($vlResult[0]['result_printed_datetime']) == '' || $vlResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
                $db = $db->where('covid19_id', $result['covid19_id']);
                $db->update($tableName2, array('result_printed_datetime' => $currentTime, 'result_dispatched_datetime' => $currentTime));
            }
        }
    }

    if (count($pages) > 0) {
        $resultPdf = new Pdf_concat();
        $resultPdf->setFiles($pages);
        $resultPdf->setPrintHeader(false);
        $resultPdf->setPrintFooter(false);
        $resultPdf->concat();
        $resultFilename = 'COVID-19-Test-result-' . date('d-M-Y-H-i-s') . '.pdf';
        $resultPdf->Output(UPLOAD_PATH . DIRECTORY_SEPARATOR . $resultFilename, "F");
        $general->removeDirectory($pathFront);
        unset($_SESSION['rVal']);
    }
}
echo $resultFilename;
