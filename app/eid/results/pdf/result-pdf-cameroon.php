<?php

// this file is included in eid/results/generate-result-pdf.php
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\ResultPdfService;
use App\Helpers\PdfWatermarkHelper;
use App\Registries\ContainerRegistry;
use App\Helpers\ResultPDFHelpers\EIDResultPDFHelper;


/** @var ResultPdfService $resultPdfService */
$resultPdfService = ContainerRegistry::get(ResultPdfService::class);


if (!empty($result)) {

    $displayPageNoInFooter = true;
    $displaySignatureTable = true;
    $reportTopMargin = 14;

    if (!empty($result['vl_facility_attributes'])) {
        $vlFacilityAttributes = json_decode($result['vl_facility_attributes'], true);
        if (!empty($vlFacilityAttributes) && isset($vlFacilityAttributes['display_page_number_in_footer'])) {
            $displayPageNoInFooter = ($vlFacilityAttributes['display_page_number_in_footer']) == 'yes';
        }
        if (!empty($vlFacilityAttributes) && isset($vlFacilityAttributes['display_signature_table'])) {
            $displaySignatureTable = ($vlFacilityAttributes['display_signature_table']) == 'yes';
        }
        if (!empty($vlFacilityAttributes) && isset($vlFacilityAttributes['report_top_margin'])) {
            $reportTopMargin = (isset($vlFacilityAttributes['report_top_margin'])) ? $vlFacilityAttributes['report_top_margin'] : $reportTopMargin;
        }
    }

    $currentTime = DateUtility::getCurrentDateTime();
    $_SESSION['aliasPage'] = $page;
    if (!isset($result['labName'])) {
        $result['labName'] = '';
    }
    $draftTextShow = false;
    //Set watermark text
    for ($m = 0; $m < count($mFieldArray); $m++) {
        if (!isset($result[$mFieldArray[$m]]) || trim((string) $result[$mFieldArray[$m]]) == '' || $result[$mFieldArray[$m]] == null || $result[$mFieldArray[$m]] == '0000-00-00 00:00:00') {
            $draftTextShow = true;
            break;
        }
    }

    $reportTemplatePath = $resultPdfService->getReportTemplate($result['lab_id']);

    // create new PDF document
    $pdf = new EIDResultPDFHelper(orientation: PDF_PAGE_ORIENTATION, unit: PDF_UNIT, format: PDF_PAGE_FORMAT, unicode: true, encoding: 'UTF-8', diskCache: false, pdfTemplatePath: $reportTemplatePath, enableFooter: $displayPageNoInFooter);

    if (empty($reportTemplatePath)) {
        if ($pdf->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
            $logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'];
        } else {
            $logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $arr['logo'];
        }

        $pdf->setHeading($logoPrintInPdf, $arr['header'], $result['labName'], $title = 'EARLY INFANT DIAGNOSIS PATIENT REPORT');
    }
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
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP + $reportTopMargin, PDF_MARGIN_RIGHT);
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
    if (!isset($result['labName']) || trim((string) $result['labName']) == '') {
        $result['labName'] = '';
    }
    //Set Age
    $age = 'Unknown';
    if (isset($result['child_dob']) && trim((string) $result['child_dob']) != '' && $result['child_dob'] != '0000-00-00') {
        $todayDate = strtotime(date('Y-m-d'));
        $dob = strtotime((string) $result['child_dob']);
        $difference = $todayDate - $dob;
        $seconds_per_year = 60 * 60 * 24 * 365;
        $age = round($difference / $seconds_per_year);
    } elseif (isset($result['child_age']) && trim((string) $result['child_age']) != '' && trim((string) $result['child_age']) > 0) {
        $age = $result['child_age'];
    }

    if (isset($result['sample_collection_date']) && trim((string) $result['sample_collection_date']) != '' && $result['sample_collection_date'] != '0000-00-00 00:00:00') {
        $expStr = explode(" ", (string) $result['sample_collection_date']);
        $result['sample_collection_date'] = date('d/M/Y', strtotime($expStr[0]));
        $sampleCollectionTime = $expStr[1];
    } else {
        $result['sample_collection_date'] = '';
        $sampleCollectionTime = '';
    }
    $sampleReceivedDate = '';
    $sampleReceivedTime = '';
    if (isset($result['sample_received_at_lab_datetime']) && trim((string) $result['sample_received_at_lab_datetime']) != '' && $result['sample_received_at_lab_datetime'] != '0000-00-00 00:00:00') {
        $expStr = explode(" ", (string) $result['sample_received_at_lab_datetime']);
        $sampleReceivedDate = date('d/M/Y', strtotime($expStr[0]));
        $sampleReceivedTime = $expStr[1];
    }
    $sampleDispatchDate = '';
    $sampleDispatchTime = '';
    if (isset($result['result_printed_datetime']) && trim((string) $result['result_printed_datetime']) != '' && $result['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
        $expStr = explode(" ", (string) $result['result_printed_datetime']);
        $sampleDispatchDate = date('d/M/Y', strtotime($expStr[0]));
        $sampleDispatchTime = $expStr[1];
    } else {
        $expStr = explode(" ", $currentTime);
        $sampleDispatchDate = date('d/M/Y', strtotime($expStr[0]));
        $sampleDispatchTime = $expStr[1];
    }
    $testedBy = '';
    if (!empty($result['tested_by'])) {
        $testedByRes = $usersService->getUserInfo($result['tested_by'], array('user_name', 'user_signature'));
        if ($testedByRes) {
            $testedBy = $testedByRes['user_name'];
        }
    }
    $sampleTestedDatetime = '';
    if (isset($result['sample_tested_datetime']) && trim((string) $result['sample_tested_datetime']) != '' && $result['sample_tested_datetime'] != '0000-00-00 00:00:00') {
        $expStr = explode(" ", (string) $result['sample_tested_datetime']);
        $sampleTestedDatetime = $expStr[0];
        $result['sample_tested_datetime'] = date('d/M/Y', strtotime($expStr[0]));
    } else {
        $result['sample_tested_datetime'] = '';
    }

    $checkDateIsset = strpos((string) $result['result_approved_datetime'], "0000-00-00");
    if ($checkDateIsset !== false) {
        $result['result_approved_datetime'] = null;
    }
    if (isset($result['approvedBy']) && trim((string) $result['approvedBy']) != '') {
        $resultApprovedBy = ($result['approvedBy']);
    } else {
        if (!empty($result['defaultApprovedBy'])) {
            $approvedByRes = $usersService->getUserInfo($result['defaultApprovedBy'], array('user_name', 'user_signature'));
            if ($approvedByRes) {
                $resultApprovedBy = $approvedByRes['user_name'];
                $result['approvedBySignature'] = $approvedByRes['user_signature'];
                $result['result_approved_datetime'] = $sampleTestedDatetime;
            }
        }
    }

    $reviewedBy = '';
    if (!empty($result['reviewedBy'])) {
        $reviewedBy = $result['reviewedBy'];
    } else {
        if (!empty($result['defaultReviewedBy'])) {
            $reviewedByRes = $usersService->getUserInfo($result['defaultReviewedBy'], array('user_name', 'user_signature'));
            if ($reviewedByRes) {
                $reviewedBy = $reviewedByRes['user_name'];
                $result['reviewedBySignature'] = $reviewedByRes['user_signature'];
                $result['result_reviewed_datetime'] = $sampleTestedDatetime;
            }
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

    $revisedSignaturePath = $reviewedBySignaturePath = $testUserSignaturePath = $approvedBySignaturePath = null;
    if (!empty($testedByRes['user_signature'])) {
        $testUserSignaturePath = $testedByRes['user_signature'];
    }
    if (!empty($result['reviewedBySignature'])) {
        $reviewedBySignaturePath = $result['reviewedBySignature'];
    }
    if (!empty($result['approvedBySignature'])) {
        $approvedBySignaturePath = $result['approvedBySignature'];
    }
    if (!empty($revisedByRes['user_signature'])) {
        $revisedSignaturePath = $revisedByRes['user_signature'];
    }

    if (!isset($result['child_gender']) || trim((string) $result['child_gender']) == '') {
        $result['child_gender'] = _translate('Unreported');
    }

    $finalResult = '';
    $smileyContent = '';
    $showMessage = '';
    $tndMessage = '';
    $messageTextSize = '12px';
    if ($result['result'] != null && trim((string) $result['result']) != '') {
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
    if (isset($arr['show_smiley']) && trim((string) $arr['show_smiley']) == "no") {
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
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">ARV PROTOCOL FOLLOWED BY MOTHER</td>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">CHILD ID</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $patientFname = ($general->crypto('doNothing', $result['child_name'], $result['child_id']));

    if (!empty($result['is_encrypted']) && $result['is_encrypted'] == 'yes') {
        $key = (string) $general->getGlobalConfig('key');
        $result['child_id'] = $general->crypto('decrypt', $result['child_id'], $key);
        $patientFname = $general->crypto('decrypt', $patientFname, $key);
        $result['mother_id'] = $general->crypto('decrypt', $result['mother_id'], $key);
        //$aRow['mother_name'] = $general->crypto('decrypt', $aRow['mother_name'], $key);
    }

    $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ($result['labName']) . '</td>';
    $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $patientFname . '</td>';

    $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['mother_arv_protocol'] . '</td>';
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
    $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ucwords(str_replace("_", " ", (string) $result['child_gender'])) . '</td>';
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
        $finalResult = $eidResults[$result['result']] ?? ucwords((string) $result['result']);
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
    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['eid_test_platform']) . '</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:2px;border-bottom:1px solid #d3d3d3;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:22px;"></td>';
    $html .= '</tr>';

    if (!empty($testedBy) && !empty($result['sample_tested_datetime']) && $displaySignatureTable) {
        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">TESTED BY</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SIGNATURE</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">DATE</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $testedBy . '</td>';
        if (!empty($testUserSignaturePath) && MiscUtility::imageExists($testUserSignaturePath)) {
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $testUserSignaturePath . '" style="width:50px;" /></td>';
        } else {
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
        }
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_tested_datetime'] . '</td>';
        $html .= '</tr>';
    }

    if (!empty($reviewedBy) && $displaySignatureTable) {

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
        if (!empty($reviewedBySignaturePath) && MiscUtility::imageExists($reviewedBySignaturePath)) {
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $reviewedBySignaturePath . '" style="width:50px;" /></td>';
        } else {
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
        }
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . (!empty($result['result_reviewed_datetime']) ? date('d/M/Y', strtotime((string) $result['result_reviewed_datetime'])) : '') . '</td>';
        $html .= '</tr>';
    }


    if (!empty($resultApprovedBy) && !empty($result['result_approved_datetime']) && $displaySignatureTable) {
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
        if (!empty($approvedBySignaturePath) && MiscUtility::imageExists($approvedBySignaturePath)) {
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $approvedBySignaturePath . '" style="width:50px;" /></td>';
        } else {
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
        }

        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . (!empty($result['result_approved_datetime']) ? date('d/M/Y', strtotime((string) $result['result_approved_datetime'])) : '') . '</td>';
        $html .= '</tr>';
    }



    // $html .= '<tr>';
    // $html .= '<td colspan="3" style="line-height:22px;"></td>';
    // $html .= '</tr>';


    if (!empty($revisedBy) && $displaySignatureTable) {
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
        if (!empty($revisedSignaturePath) && MiscUtility::imageExists($revisedSignaturePath)) {
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $revisedSignaturePath . '" style="width:70px;" /></td>';
        } else {
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
        }
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . date('d/M/Y', strtotime((string) $result['revised_on'])) . '</td>';
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


    if (!empty($result['lab_tech_comments'])) {

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
    if (isset($_POST['source']) && trim((string) $_POST['source']) == 'print') {
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
        if ($eidResult[0]['result_printed_datetime'] == null || trim((string) $eidResult[0]['result_printed_datetime']) == '' || $eidResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
            $db->where('eid_id', $result['eid_id']);
            $db->update($tableName2, array('result_printed_datetime' => $currentTime, 'result_dispatched_datetime' => $currentTime));
        }
    }
}
