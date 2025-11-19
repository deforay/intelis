<?php

// this file is included in eid/results/generate-result-pdf.php
use const SAMPLE_STATUS\REJECTED;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Helpers\PdfWatermarkHelper;
use App\Registries\ContainerRegistry;
use App\Helpers\ResultPDFHelpers\EIDResultPDFHelper;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);


$pages = [];
$page = 1;

if (!empty($result)) {
    $currentTime = DateUtility::getCurrentDateTime();
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
    $pdf = new EIDResultPDFHelper(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    if (MiscUtility::isImageValid(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
        $logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'];
    } else {
        $logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $arr['logo'];
    }

    $pdf->setHeading($logoPrintInPdf, $arr['header'], $result['labName'], $title = 'EARLY INFANT DIAGNOSIS PATIENT REPORT');
    // set document information
    $pdf->SetCreator('InteLIS');
    $pdf->SetTitle('Early Infant Diagnosis Patient Report');
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



    // ---------------------------------------------------------

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

    $result['sample_collection_date'] = DateUtility::humanReadableDateFormat($result['sample_collection_date'] ?? '', true, 'd/M/Y H:i:s');
    $result['sample_received_at_lab_datetime'] = DateUtility::humanReadableDateFormat($result['sample_received_at_lab_datetime'] ?? '', true, 'd/M/Y H:i:s');
    $result['result_printed_datetime'] = DateUtility::humanReadableDateFormat($result['result_printed_datetime'] ?? DateUtility::getCurrentDateTime(), true, 'd/M/Y H:i:s');
    $result['result_approved_datetime'] = DateUtility::humanReadableDateFormat($result['result_approved_datetime'] ?? '', true, 'd/M/Y H:i:s');
    $testedBy = $result['testedBy'] ?? null;
    $revisedBy = $result['revisedBy'] ?? null;

    $reviewedBy = $result['reviewedBy'] ?? null;
    if (empty($reviewedBy)) {
        $reviwerInfo = $usersService->getUserNameAndSignature($result['defaultReviewedBy']);
        $reviewedBy = $reviwerInfo['user_name'];
        $result['reviewedBySignature'] = $reviwerInfo['user_signature'];
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

    $revisedBySignaturePath = $reviewedBySignaturePath = $testedBySignaturePath = $approvedBySignaturePath = null;
    if (!empty($result['testedBySignature'])) {
        $testedBySignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $result['testedBySignature'];
    }
    if (!empty($result['reviewedBySignature'])) {
        $reviewedBySignaturePath = MiscUtility::getFullImagePath($result['reviewedBySignature'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature");
    }
    if (!empty($result['approvedBySignature'])) {
        $approvedBySignaturePath = MiscUtility::getFullImagePath($result['approvedBySignature'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature");
    }
    if (!empty($result['reviewedBySignature'])) {
        $revisedBySignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $result['reviewedBySignature'];
    }

    $result['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($result['sample_tested_datetime'] ?? '', true, 'd/M/Y H:i');

    if (!isset($result['child_gender']) || trim((string) $result['child_gender']) === '') {
        $result['child_gender'] = _translate('Unreported');
    }

    $finalResult = '';
    $smileyContent = '';
    $showMessage = '';
    $tndMessage = '';
    $messageTextSize = '12px';
    if ($result['result'] != null && trim((string) $result['result']) !== '') {
        $resultType = is_numeric($result['result']);
        if ($result['result'] == 'positive') {
            $finalResult = $result['result'];
            $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" style="width:50px;" alt="smile_face"/>';
        } elseif ($result['result'] == 'negative') {
            $finalResult = $result['result'];
            $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" style="width:50px;" alt="smile_face"/>';
        } elseif ($result['result'] == 'indeterminate') {
            $finalResult = $result['result'];
            $smileyContent = '';
        } else {
            $finalResult = $result['result'];
            $smileyContent = '';
        }
    }
    if (isset($arr['show_smiley']) && trim((string) $arr['show_smiley']) === "no") {
        $smileyContent = '';
    }
    if ($result['result_status'] == REJECTED) {
        $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/cross.png" style="width:25px;" alt="rejected"/>';
    }
    $html = '<table style="padding:0px 2px 2px 2px;">';
    $html .= '<tr>';

    $html .= '<td colspan="3">';
    $html .= '<table style="padding:2px;">';
    $html .= '<tr>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("HEALTH FACILITY/POE") . '</td>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("FACILITY/POE CODE") . '</td>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("FACILITY/POE STATE") . '</td>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("FACILITY/POE COUNTY") . '</td>';
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
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("TESTING LAB NAME") . '</td>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("PATIENT NAME") . '</td>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("MOTHER ART NUMBER") . '</td>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("CHILD ID") . '</td>';
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
    $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ucwords((string) $result['labName']) . '</td>';
    $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ucwords((string) $patientFname) . '</td>';

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
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("AGE IN MONTHS") . '</td>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SEX") . '</td>';
    $html .= '<td colspan="2" style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("REQUESTING CLINICIAN NAME") . '</td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['child_age'] . '</td>';
    $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . ucwords(str_replace("_", " ", (string) $result['child_gender'])) . '</td>';
    $html .= '<td colspan="2" style="line-height:10px;font-size:10px;text-align:left;">' . ($result['sample_requestor_name']) . '</td>';
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
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SAMPLE ID") . '</td>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SAMPLE COLLECTION DATE") . '</td>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SAMPLE RECEIPT DATE") . '</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:5px;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['sample_code'] . '</td>';
    $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['sample_collection_date'] . '</td>';
    $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['sample_received_at_lab_datetime'] . '</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:5px;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SAMPLE TYPE") . '</td>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SAMPLE TEST DATE") . '</td>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("RESULT RELEASE DATE") . '</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:5px;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . _translate($result['sample_name']) . '</td>';
    $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . (empty($result['sample_tested_datetime']) ? '-' : $result['sample_tested_datetime']) . '</td>';
    $html .= '<td style="line-height:10px;font-size:10px;text-align:left;">' . $result['result_printed_datetime'] . '</td>';
    $html .= '</tr>';

    $html .= '<tr>';
    $html .= '<td colspan="3">';
    $html .= '<br><br><table style="padding:4px 2px 2px 2px;">';

    if (!empty($result['is_sample_rejected']) && $result['is_sample_rejected'] == 'yes') {
        $finalResult = _translate('Rejected');
    } else {
        $finalResult = _translate($eidResults[$result['result']]);
    }

    $html .= '<tr style="background-color:#dbdbdb;"><td colspan="2" style="line-height:40px;font-size:18px;font-weight:normal;">&nbsp;&nbsp;' . _translate("Result") . ' &nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . _translate($finalResult) . '</td><td >' . $smileyContent . '</td></tr>';

    if ($result['reason_for_sample_rejection'] != '') {
        $html .= '<tr><td colspan="3" style="line-height:26px;font-size:12px;font-weight:bold;text-align:left;">&nbsp;&nbsp;' . _translate("Rejection Reason") . '&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . _translate($result['rejection_reason_name']) . '</td></tr>';
    }

    $html .= '</table>';
    $html .= '</td>';
    $html .= '</tr>';


    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:10px;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("TEST PLATFORM") . '</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['instrument_machine_name'] ?? $result['eid_test_platform']) . '</td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:2px;border-bottom:1px solid #d3d3d3;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="3" style="line-height:22px;"></td>';
    $html .= '</tr>';

    if (!empty($testedBy) && !empty($result['sample_tested_datetime'])) {
        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("TESTED BY") . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SIGNATURE") . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("DATE") . '</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $testedBy . '</td>';
        if ($testedBySignaturePath !== null && $testedBySignaturePath !== '' && $testedBySignaturePath !== '0' && MiscUtility::isImageValid($testedBySignaturePath)) {
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $testedBySignaturePath . '" style="width:50px;" /></td>';
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
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("REVIEWED BY") . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SIGNATURE") . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("DATE") . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $reviewedBy . '</td>';
        if ($reviewedBySignaturePath !== null && $reviewedBySignaturePath !== '' && $reviewedBySignaturePath !== '0' && MiscUtility::isImageValid($reviewedBySignaturePath)) {
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $reviewedBySignaturePath . '" style="width:50px;" /></td>';
        } else {
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
        }
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . (empty($result['result_reviewed_datetime']) ? '' : date('d/M/Y', strtotime((string) $result['result_reviewed_datetime']))) . '</td>';
        $html .= '</tr>';
    }


    if (!empty($resultApprovedBy) && !empty($result['result_approved_datetime'])) {
        $html .= '<tr>';
        $html .= '<td colspan="3" style="line-height:22px;"></td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("APPROVED BY") . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SIGNATURE") . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("DATE") . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $resultApprovedBy . '</td>';
        if ($approvedBySignaturePath !== null && $approvedBySignaturePath !== '' && $approvedBySignaturePath !== '0' && MiscUtility::isImageValid($approvedBySignaturePath)) {
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $approvedBySignaturePath . '" style="width:50px;" /></td>';
        } else {
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
        }

        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . (empty($result['result_approved_datetime']) ? '' : date('d/M/Y', strtotime((string) $result['result_approved_datetime']))) . '</td>';
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
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("REPORT REVISED BY") . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("SIGNATURE") . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("DATE") . '</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $revisedBy . '</td>';
        if ($revisedBySignaturePath !== null && $revisedBySignaturePath !== '' && $revisedBySignaturePath !== '0' && MiscUtility::isImageValid($revisedBySignaturePath)) {
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $revisedBySignaturePath . '" style="width:70px;" /></td>';
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
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . _translate("Comments") . '</td>';
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
        $html .= '<td colspan="3" style="line-height:11px;font-size:11px;text-align:left;"><strong>' . _translate("Lab Comments") . ':</strong> ' . $result['lab_tech_comments'] . '</td>';
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
    $html .= '<td style="font-size:10px;text-align:left;">' . _translate("Printed on") . ' : ' . $printDate . '&nbsp;&nbsp;' . $printDateTime . '</td>';
    $html .= '<td style="font-size:10px;text-align:left;width:60%;"></td>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<td colspan="2" style="font-size:10px;text-align:left;width:60%;"></td>';
    $html .= '</tr>';
    $html .= '</table>';
    $html .= '</td>';
    $html .= '</tr>';
    $html .= '</table>';
    if ($result['result'] != '' || ($result['result'] == '' && $result['result_status'] == REJECTED)) {
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
    if (isset($_POST['source']) && trim((string) $_POST['source']) === 'print') {
        //Add event log
        $eventType = 'print-result';
        $action = $_SESSION['userName'] . ' print the test result with child code ' . $result['child_id'];
        $resource = 'print-test-result';
        $data = ['event_type' => $eventType, 'action' => $action, 'resource' => $resource, 'date_time' => $currentTime];
        $db->insert($tableName1, $data);
        //Update print datetime in VL tbl.
        $vlQuery = "SELECT result_printed_datetime FROM form_eid as vl WHERE vl.eid_id ='" . $result['eid_id'] . "'";
        $eidResult = $db->query($vlQuery);
        if ($eidResult[0]['result_printed_datetime'] == null || trim((string) $eidResult[0]['result_printed_datetime']) === '' || $eidResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
            $db->where('eid_id', $result['eid_id']);
            $db->update($tableName2, ['result_printed_datetime' => $currentTime, 'result_dispatched_datetime' => $currentTime]);
        }
    }
}
