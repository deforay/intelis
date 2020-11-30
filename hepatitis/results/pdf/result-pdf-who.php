<?php

// this file is included in hepatitis/results/generate-result-pdf.php
$hepatitisDb = new \Vlsm\Models\Hepatitis($db);
$hepatitisResults = $hepatitisDb->getHepatitisResults();

$resultFilename = '';

$userRes = $users->getUserInfo($_SESSION['userId'], 'user_signature');
$userSignaturePath = null;

if (!empty($userRes['user_signature'])) {
    $userSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $userRes['user_signature'];
}

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
        $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->setHeading($arr['logo'], $arr['header'], $result['labName'], $title = 'EARLY INFANT DIAGNOSIS PATIENT REPORT');
        // set document information
        $pdf->SetCreator('VLSM');
        $pdf->SetTitle('Hepatitis Patient Report');
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
            $todayDate = strtotime(date('Y-m-d'));
            $dob = strtotime($result['patient_dob']);
            $difference = $todayDate - $dob;
            $seconds_per_year = 60 * 60 * 24 * 365;
            $age = round($difference / $seconds_per_year);
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
        }else{
            $expStr = explode(" ", $currentTime);
            $sampleDisbatchDate = $general->humanDateFormat($expStr[0]);
            $sampleDisbatchTime = $expStr[1];
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
        if (isset($result['approvedBy']) && trim($result['approvedBy']) != '') {
            $resultApprovedBy = ucwords($result['approvedBy']);
        } else {
            $resultApprovedBy  = '';
        }
        $vlResult = '';
        $showMessage = '';
        $tndMessage = '';
        $messageTextSize = '12px';

        // Smily for HCV Result
        $smileyContenthcv = '';
        if ($result['hcv_vl_result'] != NULL && trim($result['hcv_vl_result']) != '') {
            $resultType = is_numeric($result['hcv_vl_result']);
            if ($result['hcv_vl_result'] == 'positive') {
                $vlResult = $result['hcv_vl_result'];
                //$smileyContenthcv = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" alt="smile_face"/>';
            } else if ($result['hcv_vl_result'] == 'negative') {
                $vlResult = $result['hcv_vl_result'];
                $smileyContenthcv = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" alt="smile_face"/>';
            } else if ($result['hcv_vl_result'] == 'indeterminate') {
                $vlResult = $result['hcv_vl_result'];
                $smileyContenthcv = '';
            }
        }
        if (isset($arr['show_smiley']) && trim($arr['show_smiley']) == "no") {
            $smileyContenthcv = '';
        }
        if ($result['result_status'] == '4') {
            $smileyContenthcv = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/cross.png" alt="rejected"/>';
        }
        
        // Smily for HBV Result
        $smileyContenthbv = '';
        if ($result['hbv_vl_result'] != NULL && trim($result['hbv_vl_result']) != '') {
            $resultType = is_numeric($result['hbv_vl_result']);
            if ($result['hbv_vl_result'] == 'positive') {
                $vlResult = $result['hbv_vl_result'];
                //$smileyContenthbv = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" alt="smile_face"/>';
            } else if ($result['hbv_vl_result'] == 'negative') {
                $vlResult = $result['hbv_vl_result'];
                $smileyContenthbv = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" alt="smile_face"/>';
            } else if ($result['hbv_vl_result'] == 'indeterminate') {
                $vlResult = $result['hbv_vl_result'];
                $smileyContenthbv = '';
            }
        }
        if (isset($arr['show_smiley']) && trim($arr['show_smiley']) == "no") {
            $smileyContenthbv = '';
        }
        if ($result['result_status'] == '4') {
            $smileyContenthbv = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/cross.png" alt="rejected"/>';
        }

        $html = '';
        $html .= '<table style="padding:0px 2px 2px 2px;">';
            /* $html .= '<tr>';
                $html .= '<td colspan="3">';
                    $html .= '<table style="padding:2px;">';
                        $html .= '<tr>';
                            $html .= '<td style="line-height:11px;font-size:14px;font-weight:bold;text-align:left;">NRL SECTION CODE : '.ucwords($result['sample_code']).'</td>';
                        $html .= '</tr>';
                    $html .= '</table>';
                $html .= '</td>';
            $html .= '</tr>';

            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:2px;border-bottom:1px solid #d3d3d3;"></td>';
            $html .= '</tr>'; */
            
            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:2px;"></td>';
            $html .= '</tr>';
            
            $html .= '<tr>';
            $html .= '<td colspan="3" style="line-height:25px;font-size:13px;text-align:left;padding-bottom:5px;"><br><u>SITE INFORMATION</u></td>';
            $html .= '</tr>';
            
            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:2px;"></td>';
            $html .= '</tr>';
            
            $html .= '<tr>';
                $html .= '<td colspan="3">';
                    $html .= '<table  style="padding:8px 2px 2px 2px;">';
                        $html .= '<tr>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">DISTRICT NAME</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SOURCE OF FUNDING</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">HEALTH FACILITY</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">HEALTH FACILITY CODE</td>';
                        $html .= '</tr>';
                        $html .= '<tr>';
                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ucwords($result['facility_name']) . '</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">'. ucwords($result['funding_source_name']).'</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ucwords($result['facility_name']) . '</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ucwords($result['facility_code']) . '</td>';
                        $html .= '</tr>';

                        $html .= '<tr>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SITE CONTACT</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">COUNTRY</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;"></td>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;"></td>';
                        $html .= '</tr>';
                        $html .= '<tr>';
                            $countryName = (isset($country[$arr['vl_form']]) && $country[$arr['vl_form']] != "")?ucwords($country[$arr['vl_form']]):"Who";
                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">'.ucwords($result['vl_testing_site']).'</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">'.$countryName.'</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
                        $html .= '</tr>';
                    $html .= '</table>';
                $html .= '</td>';
            $html .= '</tr>';
            
            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:2px;border-bottom:1px solid #d3d3d3;"></td>';
            $html .= '</tr>';
            
            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:2px;"></td>';
            $html .= '</tr>';
            
            $html .= '<tr>';
            $html .= '<td colspan="3" style="line-height:25px;font-size:13px;text-align:left;padding-bottom:5px;"><br><u>PATIENT INFORMATION</u></td>';
            $html .= '</tr>';
            
            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:2px;"></td>';
            $html .= '</tr>';

            $html .= '<tr>';
                $html .= '<td colspan="3">';
                    $html .= '<table style="padding:8px 2px 2px 2px;">';
                        $html .= '<tr>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">TRACNET ID</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">NAME</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SEX</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">BIRTH DATE / AGE</td>';
                        $html .= '</tr>';
                        $html .= '<tr>';
                            $patientFname = ucwords($general->crypto('decrypt', $result['patient_name'], $result['patient_id']));
                            $patientLname = ucwords($general->crypto('decrypt', $result['patient_surname'], $result['patient_id']));

                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['patient_id'] . '</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $patientFname.' '.$patientLname . '</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ucwords(str_replace("_", " ", $result['patient_gender'])) . '</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $general->humanDateFormat($result['patient_dob']) . ' / '. $age . '</td>';
                        $html .= '</tr>';
                    $html .= '</table>';
                $html .= '</td>';
            $html .= '</tr>';

            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:2px;border-bottom:1px solid #d3d3d3;"></td>';
            $html .= '</tr>';
            
            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:2px;"></td>';
            $html .= '</tr>';
            
            $html .= '<tr>';
            $html .= '<td colspan="3" style="line-height:25px;font-size:13px;text-align:left;padding-bottom:5px;"><br><u>SPECIMEN INFORMATION</u></td>';
            $html .= '</tr>';
            
            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:2px;"></td>';
            $html .= '</tr>';

            $html .= '<tr>';
                $html .= '<td colspan="3">';
                    $html .= '<table style="padding:8px 2px 2px 2px;">';
                        $html .= '<tr>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE ID</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">COLLECTION DATE</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">PURPOSE OF TEST</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SPECIMEN TYPE</td>';
                        $html .= '</tr>';
                        $html .= '<tr>';
                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_code'] . '</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_collection_date'] . ' '.$sampleCollectionTime . '</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['reason_for_vl_test'] . '</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_name'] . '</td>';
                        $html .= '</tr>';

                        $html .= '<tr>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">DATE OF RECEPTION</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">TIME OF RECEPTION</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Testing Lab</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Testing Platform</td>';
                        $html .= '</tr>';
                        $html .= '<tr>';
                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $sampleReceivedDate . '</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $sampleReceivedTime . '</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">'.ucwords($result['labName']).'</td>';
                            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ucwords($result['hepatitis_test_platform']) . '</td>';
                        $html .= '</tr>';
                    $html .= '</table>';
                $html .= '</td>';
            $html .= '</tr>';

            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:2px;border-bottom:1px solid #d3d3d3;"></td>';
            $html .= '</tr>';

            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:2px;"></td>';
            $html .= '</tr>';

            $html .= '<tr>';
                $html .= '<td colspan="3">';
                    $html .= '<table style="padding:2px;">';
                        if((isset($result['hcv_vl_result']) && $result['hcv_vl_result'] != "") && (isset($result['hbv_vl_result']) && $result['hbv_vl_result'] != "")){
                            $html .= '<tr>';
                                $html .= '<td colspan="2" style="line-height:50px;font-size:14px;text-align:left;">&nbsp;&nbsp;<b>TEST REQUESTED : </b>'.$result['sample_tested_datetime'].'</td>';
                            $html .= '</tr>';
                            $html .= '<tr>';
                                $html .= '<td colspan="2" style="line-height:2px;"></td>';
                            $html .= '</tr>';
                            $html .= '<tr style="background-color:#dbdbdb;">';
                                if(isset($result['hcv_vl_result']) && $result['hcv_vl_result'] != ""){
                                    $html .= '<td style="line-height:50px;font-size:14px;font-weight:bold;text-align:left;">&nbsp;&nbsp;HCV VL RESULTS : '.ucwords($hepatitisResults[$result['hcv_vl_result']]).'</td>';
                                }
                                if(isset($result['hbv_vl_result']) && $result['hbv_vl_result'] != ""){
                                    $html .= '<td style="line-height:50px;font-size:14px;font-weight:bold;text-align:left;">&nbsp;&nbsp;HBV VL RESULTS : '.ucwords($hepatitisResults[$result['hbv_vl_result']]).'</td>';
                                }
                            $html .= '</tr>';
                        } else{
                            $resultTxt = "Result";
                            $resultVal = "";
                            if(isset($result['hcv_vl_result']) && $result['hcv_vl_result'] != ""){
                                $resultTxt = "HCV VL Result";
                                $resultVal = ucwords($hepatitisResults[$result['hcv_vl_result']]);
                            } else if(isset($result['hbv_vl_result']) && $result['hbv_vl_result'] != ""){
                                $resultTxt = "HBV VL Result";
                                $resultVal = ucwords($hepatitisResults[$result['hbv_vl_result']]);
                            }
                            $html .= '<tr style="background-color:#dbdbdb;">';
                                $html .= '<td style="line-height:50px;font-size:16px;font-weight:bold;text-align:left;">&nbsp;&nbsp;<b>TEST REQUESTED : </b>'.$result['sample_tested_datetime'].'&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; '.$resultTxt.' : '.$resultVal.'</td>';
                            $html .= '</tr>';

                        }
                        
                        if ($result['reason_for_sample_rejection'] != '') {
                            $html .= '<tr>';
                                $html .= '<td colspan="2" style="line-height:2px;"></td>';
                            $html .= '</tr>';
                            $html .= '<tr><td colspan="2" style="line-height:26px;font-size:12px;text-align:left;">&nbsp;&nbsp;Rejection Reason&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $result['rejection_reason_name'] . '</td></tr>';
                        }
                    $html .= '</table>';
                $html .= '</td>';
            $html .= '</tr>';
  
            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:2px;"></td>';
            $html .= '</tr>';

            if (trim($result['approver_comments']) != '') {
            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:11px;font-size:11px;">LAB COMMENTS&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . ucfirst($result['approver_comments']) . '</span></td>';
            $html .= '</tr>';
            
            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:10px;"></td>';
            $html .= '</tr>';
            }

            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:14px;"></td>';
            $html .= '</tr>';
            
            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:8px;"></td>';
            $html .= '</tr>';

            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:2px;border-bottom:1px solid #d3d3d3;"></td>';
            $html .= '</tr>';
            
            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:22px;"></td>';
            $html .= '</tr>';
            
            $html .= '<tr>';
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">TESTED BY</td>';
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">SIGNATURE</td>';
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">DATE</td>';
            $html .= '</tr>';
                
            $html .= '<tr>';
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
            $html .= '</tr>';
            
            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:8px;"></td>';
            $html .= '</tr>';
    
            $html .= '<tr>';
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">APPROVED BY</td>';
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">SIGNATURE</td>';
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">DATE</td>';
            $html .= '</tr>';
            
            $html .= '<tr>';
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $resultApprovedBy . '</td>';
                if (!empty($userSignaturePath) && file_exists($userSignaturePath)) {
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $userSignaturePath . '" style="width:70px;" /></td>';
                } else {
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
                }
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">'.$general->humanDateFormat($result['result_approved_datetime']).'</td>';
            $html .= '</tr>';

            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:20px;border-bottom:2px solid #d3d3d3;"></td>';
            $html .= '</tr>';
            
            $html .= '<tr>';
                $html .= '<td colspan="3" style="line-height:2px;"></td>';
            $html .= '</tr>';
    
            $html .= '<tr>';
                $html .= '<td colspan="3">';
                    $html .= '<table>';
                        $html .= '<tr>';
                            $html .= '<td style="font-size:10px;text-align:left;">Printed on : ' . $printDate . '&nbsp;&nbsp;' . $printDateTime . '</td>';
                            $html .= '<td style="font-size:10px;text-align:left;width:60%;"></td>';
                        $html .= '</tr>';
                        $html .= '<tr>';
                            $html .= '<td colspan="2" style="font-size:10px;text-align:left;width:60%;"></td>';
                        $html .= '</tr>';
                    $html .= '</table>';
                $html .= '</td>';
            $html .= '</tr>';
        $html .= '</table>';

        if (($result['hcv_vl_result'] != '' || $result['hbv_vl_result'] != '') || (($result['hcv_vl_result'] == '' || $result['hbv_vl_result'] == '') && $result['result_status'] == '4')) {
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
            $vlQuery = "SELECT result_printed_datetime FROM form_hepatitis as vl WHERE vl.hepatitis_id ='" . $result['hepatitis_id'] . "'";
            $vlResult = $db->query($vlQuery);
            if ($vlResult[0]['result_printed_datetime'] == NULL || trim($vlResult[0]['result_printed_datetime']) == '' || $vlResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
                $db = $db->where('hepatitis_id', $result['hepatitis_id']);
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
        $resultFilename = 'Hepatitis-Test-result-' . date('d-M-Y-H-i-s') . '.pdf';
        $resultPdf->Output(UPLOAD_PATH . DIRECTORY_SEPARATOR . $resultFilename, "F");
        $general->removeDirectory($pathFront);
        unset($_SESSION['rVal']);
    }
}
echo $resultFilename;
