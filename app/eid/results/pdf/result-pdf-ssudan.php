<?php

// this file is included in eid/results/generate-result-pdf.php
use App\Helpers\PdfConcatenateHelper;
use App\Helpers\PdfWatermarkHelper;
use App\Registries\ContainerRegistry;
use App\Services\EidService;
use App\Utilities\DateUtility;


/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();

$resultFilename = '';


if (!empty($requestResult)) {
    $_SESSION['rVal'] = $general->generateRandomString(6);
    $pathFront = (TEMP_PATH . DIRECTORY_SEPARATOR .  $_SESSION['rVal']);
    if (!file_exists($pathFront) && !is_dir($pathFront)) {
        mkdir(TEMP_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal'], 0777, true);
        $pathFront = realpath(TEMP_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal']);
    }
    $pages = [];
    $page = 1;
    foreach ($requestResult as $result) {
        $currentTime = DateUtility::getCurrentDateTime();
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

        $pdf->setHeading($logoPrintInPdf, $arr['header'], $result['labName'], $title = 'EARLY INFANT DIAGNOSIS PATIENT REPORT');
        // set document information
        $pdf->SetCreator('VLSM');
        $pdf->SetTitle('Early Infant Diagnosis Patient Report');
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
        if (isset($result['child_dob']) && trim($result['child_dob']) != '' && $result['child_dob'] != '0000-00-00') {
            $todayDate = strtotime(date('Y-m-d'));
            $dob = strtotime($result['child_dob']);
            $difference = $todayDate - $dob;
            $seconds_per_year = 60 * 60 * 24 * 365;
            $age = round($difference / $seconds_per_year);
        } elseif (isset($result['child_age']) && trim($result['child_age']) != '' && trim($result['child_age']) > 0) {
            $age = $result['child_age'];
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
        $testedBy = '';
        if (isset($result['tested_by']) && !empty($result['tested_by'])) {
            $testedByRes = $usersService->getUserInfo($result['tested_by'], array('user_name', 'user_signature'));
            if ($testedByRes) {
                $testedBy = $testedByRes['user_name'];
            }
        }

        $checkDateIsset = strpos($result['result_approved_datetime'], "0000-00-00");
        if ($checkDateIsset !== false) {
            $result['result_approved_datetime'] = null;
        }
        if (isset($result['approvedBy']) && trim($result['approvedBy']) != '') {
            $resultApprovedBy = ($result['approvedBy']);
        } else {
            $resultApprovedBy  = '';
        }

        $reviewedBy = '';
        if (isset($result['reviewedBy']) && !empty($result['reviewedBy'])) {
            $reviewedBy = $result['reviewedBy'];
        } else {
            $reviewedBy = $resultApprovedBy;
            $result['reviewedBySignature'] = $result['approvedBySignature'];
            $result['result_reviewed_datetime'] = $result['result_approved_datetime'];
        }

        $revisedBy = '';
        $revisedByRes = [];
        if (isset($result['revised_by']) && !empty($result['revised_by'])) {
            $revisedByRes = $usersService->getUserInfo($result['revised_by'], array('user_name', 'user_signature'));
            if ($revisedByRes) {
                $revisedBy = $revisedByRes['user_name'];
            }
        }

        $revisedSignaturePath = $reviewedBySignaturePath = $testUserSignaturePath = null;
        if (!empty($testedByRes['user_signature'])) {
            $testUserSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $testedByRes['user_signature'];
        }
        if (!empty($result['reviewedBySignature'])) {
            $reviewedBySignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $result['reviewedBySignature'];
        }
        if (!empty($result['approvedBySignature'])) {
            $approvedBySignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $result['approvedBySignature'];
        }
        if (!empty($revisedByRes['user_signature'])) {
            $revisedSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $revisedByRes['user_signature'];
        }

        if (isset($result['sample_tested_datetime']) && trim($result['sample_tested_datetime']) != '' && $result['sample_tested_datetime'] != '0000-00-00 00:00:00') {
            $expStr = explode(" ", $result['sample_tested_datetime']);
            $result['sample_tested_datetime'] = date('d/M/Y', strtotime($expStr[0]));
        } else {
            $result['sample_tested_datetime'] = '';
        }

        if (!isset($result['child_gender']) || trim($result['child_gender']) == '') {
            $result['child_gender'] = 'not reported';
        }

        $finalResult = '';
        $smileyContent = '';
        $showMessage = '';
        $tndMessage = '';
        $messageTextSize = '12px';
        if ($result['result'] != null && trim($result['result']) != '') {
            $resultType = is_numeric($result['result']);
            if ($result['result'] == 'positive') {
                $finalResult = $result['result'];
                $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" style="width:50px;" alt="smile_face"/>';
            } else if ($result['result'] == 'negative') {
                $finalResult = $result['result'];
                $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" style="width:50px;" alt="smile_face"/>';
            } else if ($result['result'] == 'indeterminate') {
                $finalResult = $result['result'];
                $smileyContent = '';
            } else {
                $finalResult = $result['result'];
                $smileyContent = '';
            }
        }
        if (isset($arr['show_smiley']) && trim($arr['show_smiley']) == "no") {
            $smileyContent = '';
        }
        if ($result['result_status'] == '4') {
            $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/cross.png" style="width:25px;" alt="rejected"/>';
        }
        $html = '<table style="padding:0px 2px 2px 2px;">';
        $html .= '<tr>';

        $html .= '<td colspan="3">';
        $html .= '<table style="padding:2px;">';
        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Health Facility/POE</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Health Facility/POE CODE</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Health Facility/POE STATE</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Health Facility/POE COUNTY</td>';
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
        $html .= '<table style="padding:8px 2px 2px 2px;">';
        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">TESTING LAB NAME</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">PATIENT NAME</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">MOTHER ART NUMBER</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">CHILD ID</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $patientFname = ($general->crypto('doNothing', $result['child_name'], $result['child_id']));

        $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['labName']) . '</td>';
        $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $patientFname . '</td>';

        $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['mother_id'] . '</td>';
        $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['child_id'] . '</td>';
        $html .= '</tr>';
        $html .= '</table>';
        $html .= '</td>';
        $html .= '</tr>';

        $html .= '<tr>';

        $html .= '<td colspan="3">';
        $html .= '<table style="padding:8px 2px 2px 2px;">';

        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">AGE IN MONTHS</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">GENDER</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;"></td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;"></td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['child_age'] . '</td>';
        $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . (str_replace("_", " ", $result['child_gender'])) . '</td>';
        $html .= '<td style="line-height:10px;font-size:10px;text-align:left;"></td>';
        $html .= '<td style="line-height:10px;font-size:10px;text-align:left;"></td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td colspan="2" style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">REQUESTING CLINICIAN NAME</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">TEL</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">EMAIL</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td colspan="2" style="line-height:10px;font-size:10px;text-align:left;">' . ($result['sample_requestor_name']) . '</td>';
        $html .= '<td colspan="2" style="line-height:10px;font-size:10px;text-align:left;">' . $result['facility_mobile_numbers'] . '</td>';
        $html .= '<td colspan="2" style="line-height:10px;font-size:10px;text-align:left;">' . $result['facility_emails'] . '</td>';
        $html .= '</tr>';
        $html .= '</table>';
        $html .= '</td>';
        $html .= '</tr>';


        $html .= '<tr>';
        $html .= '<td colspan="3">';
        $html .= '<table style="padding:8px 2px 2px 2px;">';
        $html .= '<tr>';

        $html .= '</tr>';
        $html .= '<tr>';

        $html .= '</tr>';
        $html .= '</table>';
        $html .= '</td>';
        $html .= '</tr>';


        $html .= '<tr>';
        $html .= '<td colspan="3" style="line-height:2px;border-bottom:1px solid #d3d3d3;"></td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td colspan="3" style="line-height:5px;"></td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE ID</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE COLLECTION DATE</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE RECEIPT DATE</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td colspan="3" style="line-height:5px;"></td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['sample_code'] . '</td>';
        $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['sample_collection_date'] . " " . $sampleCollectionTime . '</td>';
        $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $sampleReceivedDate . " " . $sampleReceivedTime . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td colspan="3" style="line-height:5px;"></td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE TYPE</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE TEST DATE</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">RESULT RELEASE DATE</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td colspan="3" style="line-height:5px;"></td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['sample_name']) . '</td>';
        $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . (!empty($result['sample_tested_datetime']) ? $result['sample_tested_datetime'] : '-') . '</td>';
        $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $sampleDispatchDate . " " . $sampleDispatchTime . '</td>';
        $html .= '</tr>';

        // $html .= '<tr>';
        // $html .= '<td colspan="3" style="line-height:5px;"></td>';
        // $html .= '</tr>';
        // $html .= '<tr>';
        // $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SAMPLE REJECTION STATUS</td>';
        // $html .= '</tr>';
        // // $html .= '<tr>';
        // // $html .= '<td colspan="3" style="line-height:10px;"></td>';
        // // $html .= '</tr>';
        // $html .= '<tr>';
        // $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ((!empty($result['is_sample_rejected']) && $result['is_sample_rejected'] == 'yes') ? 'Rejected' : 'Not Rejected') . '</td>';
        // $html .= '</tr>';
        // $html .= '<tr>';
        // $html .= '<td colspan="3" style="line-height:5px;"></td>';
        // $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td colspan="3">';
        $html .= '<br><br><table style="padding:4px 2px 2px 2px;">';

        if (!empty($result['is_sample_rejected']) && $result['is_sample_rejected'] == 'yes') {
            $finalResult = 'Rejected';
        } else {
            $finalResult = $eidResults[$result['result']];
        }

        $html .= '<tr style="background-color:#dbdbdb;"><td colspan="2" style="line-height:40px;font-size:18px;font-weight:normal;">&nbsp;&nbsp;Result &nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $finalResult . '</td><td >' . $smileyContent . '</td></tr>';
        //$html .= '<tr style="background-color:#dbdbdb;"><td colspan="2" style="line-height:70px;font-size:18px;font-weight:normal;">&nbsp;&nbsp;Result &nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . ($result['result']) . '</td><td >' . $smileyContent . '</td></tr>';
        if ($result['reason_for_sample_rejection'] != '') {
            $html .= '<tr><td colspan="3" style="line-height:26px;font-size:12px;font-weight:bold;text-align:left;">&nbsp;&nbsp;Rejection Reason&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $result['rejection_reason_name'] . '</td></tr>';
        }

        // $html .= '<tr><td colspan="3"></td></tr>';
        $html .= '</table>';
        $html .= '</td>';
        $html .= '</tr>';


        $html .= '<tr>';
        $html .= '<td colspan="3" style="line-height:10px;"></td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">TEST PLATFORM</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['testingPlatform']) . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td colspan="3" style="line-height:2px;border-bottom:1px solid #d3d3d3;"></td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td colspan="3" style="line-height:22px;"></td>';
        $html .= '</tr>';

        if (!empty($testedBy) && !empty($result['sample_tested_datetime'])) {
            $html .= '<tr>';
            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">TESTED BY</td>';
            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SIGNATURE</td>';
            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">DATE</td>';
            $html .= '</tr>';

            $html .= '<tr>';
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $testedBy . '</td>';
            if (!empty($testUserSignaturePath) &&  $general->imageExists($testUserSignaturePath)) {
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $testUserSignaturePath . '" style="width:50px;" /></td>';
            } else {
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
            }
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_tested_datetime'] . '</td>';
            $html .= '</tr>';
        }

        if (!empty($reviewedBy)) {

            $html .= '<tr>';
            $html .= '<td colspan="3" style="line-height:22px;"></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">REVIEWED BY</td>';
            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SIGNATURE</td>';
            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">DATE</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $reviewedBy . '</td>';
            if (!empty($reviewedBySignaturePath) &&  $general->imageExists($reviewedBySignaturePath)) {
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $reviewedBySignaturePath . '" style="width:50px;" /></td>';
            } else {
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
            }
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . (!empty($result['result_reviewed_datetime']) ? date('d/M/Y', strtotime($result['result_reviewed_datetime'])) : '') . '</td>';
            $html .= '</tr>';
        }


        if (!empty($resultApprovedBy) && !empty($result['result_approved_datetime'])) {
            $html .= '<tr>';
            $html .= '<td colspan="3" style="line-height:22px;"></td>';
            $html .= '</tr>';

            $html .= '<tr>';
            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">APPROVED BY</td>';
            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SIGNATURE</td>';
            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">DATE</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $resultApprovedBy . '</td>';
            if (!empty($approvedBySignaturePath) &&  $general->imageExists($approvedBySignaturePath)) {
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $approvedBySignaturePath . '" style="width:50px;" /></td>';
            } else {
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
            }

            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . (!empty($result['result_approved_datetime']) ? date('d/M/Y', strtotime($result['result_approved_datetime'])) : '') . '</td>';
            $html .= '</tr>';
        }



        // $html .= '<tr>';
        // $html .= '<td colspan="3" style="line-height:22px;"></td>';
        // $html .= '</tr>';


        if (!empty($revisedBy)) {
            $html .= '<tr>';
            $html .= '<td colspan="3" style="line-height:22px;"></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">REPORT REVISED BY</td>';
            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SIGNATURE</td>';
            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">DATE</td>';
            $html .= '</tr>';

            $html .= '<tr>';
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $revisedBy . '</td>';
            if (!empty($revisedSignaturePath) && $general->imageExists($revisedSignaturePath)) {
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $revisedSignaturePath . '" style="width:70px;" /></td>';
            } else {
                $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
            }
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . date('d/M/Y', strtotime($result['revised_on'])) . '</td>';
            $html .= '</tr>';
        }
        if (!empty($result['lab_tech_comments'])) {
            $html .= '<tr>';
            $html .= '<td colspan="3" style="line-height:22px;"></td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Comments</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['lab_tech_comments'] . '</td>';
            $html .= '</tr>';
        }


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
        if ($result['result'] != '' || ($result['result'] == '' && $result['result_status'] == '4')) {
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
        if (isset($_POST['source']) && trim($_POST['source']) == 'print') {
            //Add event log
            $eventType = 'print-result';
            $action = $_SESSION['userName'] . ' print the test result with child code ' . $result['child_id'];
            $resource = 'print-test-result';
            $data = array(
                'event_type' => $eventType,
                'action' => $action,
                'resource' => $resource,
                'date_time' => $currentTime
            );
            $db->insert($tableName1, $data);
            //Update print datetime in VL tbl.
            $vlQuery = "SELECT result_printed_datetime FROM form_eid as vl WHERE vl.eid_id ='" . $result['eid_id'] . "'";
            $eidResult = $db->query($vlQuery);
            if ($eidResult[0]['result_printed_datetime'] == null || trim($eidResult[0]['result_printed_datetime']) == '' || $eidResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
                $db = $db->where('eid_id', $result['eid_id']);
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
        $resultFilename = 'VLSM-EID-Test-result-' . date('d-M-Y-H-i-s') . "-" . $general->generateRandomString(6) . '.pdf';
        $resultPdf->Output(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename, "F");
        $general->removeDirectory($pathFront);
        unset($_SESSION['rVal']);
    }
}

echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename);
