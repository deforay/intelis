<?php

// this file is included in tb/results/generate-result-pdf.php
use const SAMPLE_STATUS\REJECTED;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Helpers\PdfWatermarkHelper;
use App\Services\TbService;
use App\Registries\ContainerRegistry;
use App\Helpers\ResultPDFHelpers\CountrySpecificHelpers\RwandaTBResultPDFHelper;


$usersService = ContainerRegistry::get(UsersService::class);
/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);
$tbResults = $tbService->getTbResults();
$tbLamResults = $tbService->getTbResults('lam');
$tbXPertResults = $tbService->getTbResults('x-pert');

$countryFormId = (int) $general->getGlobalConfig('vl_form');
$resultFilename = '';
try {
    if (!empty($requestResult)) {
        $_SESSION['rVal'] = MiscUtility::generateRandomString(6);
        $pathFront = TEMP_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal'];
        MiscUtility::makeDirectory($pathFront);
        $pages = [];
        $page = 1;
        foreach ($requestResult as $result) {

            $tbTestQuery = "SELECT tt.*, rtst.sample_name, l.facility_name as lab_name, test.user_name as testedBy, review.user_name as reviewedBy, revised.user_name as revisedBy, reject.rejection_reason_name as rejectionReason
        from tb_tests as tt 
        INNER JOIN r_tb_sample_type as rtst ON tt.specimen_type=rtst.sample_id  
        INNER JOIN facility_details as l ON tt.lab_id=l.facility_id 
        LEFT JOIN user_details as test ON tt.tested_by=test.user_id 
        LEFT JOIN user_details as review ON tt.result_reviewed_by=review.user_id 
        LEFT JOIN user_details as revised ON tt.revised_by=revised.user_id 
        LEFT JOIN r_tb_sample_rejection_reasons as reject ON tt.reason_for_sample_rejection=reject.rejection_reason_id 
        where tb_id= " . $result['tb_id'] . " ORDER BY tb_test_id DESC";
            // error_log($tbTestQuery);
            $tbTestInfo = $db->rawQuery($tbTestQuery);

            $facilityQuery = "SELECT * from form_tb as c19 INNER JOIN facility_details as fd ON c19.facility_id=fd.facility_id where tb_id= " . $result['tb_id'] . " GROUP BY fd.facility_id LIMIT 1";
            $facilityInfo = $db->rawQueryOne($facilityQuery);
            $patientFname = ($general->crypto('doNothing', $result['patient_name'], $result['patient_id']));
            $patientLname = ($general->crypto('doNothing', $result['patient_surname'], $result['patient_id']));

            $signQuery = "SELECT * from lab_report_signatories where lab_id=? AND test_types like '%tb%' AND signatory_status like 'active' ORDER BY display_order ASC";
            $signResults = $db->rawQuery($signQuery, [$result['lab_id']]);

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
            $pdfTemplatePath = null;
            $margintop = 15;
            $facilityReportFormat = (array) json_decode((string) $arr['report_format']);
            if (isset($selectedReportFormats['tb']['file']) && !empty($selectedReportFormats['tb']['file']) && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . "report-template" . DIRECTORY_SEPARATOR . 'tb' . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $selectedReportFormats['tb']['file'])) {
                $pdfTemplatePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . "report-template" . DIRECTORY_SEPARATOR . 'tb' . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $selectedReportFormats['tb']['file'];
                $margintop = $selectedReportFormats['tb']['mtop'];
            } elseif (isset($selectedReportFormats['default']['file']) && !empty($selectedReportFormats['default']['file']) && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . "report-template" . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $selectedReportFormats['default']['file'])) {
                $pdfTemplatePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . "report-template" . DIRECTORY_SEPARATOR . 'default' . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $selectedReportFormats['default']['file'];
                $margintop = $selectedReportFormats['default']['mtop'];
            } elseif (isset($facilityReportFormat['tb']->file) && !empty($facilityReportFormat['tb']->file) && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . "report-template" . DIRECTORY_SEPARATOR . 'tb' . DIRECTORY_SEPARATOR . $facilityReportFormat['tb']->file)) {
                $pdfTemplatePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . "report-template" . DIRECTORY_SEPARATOR . 'tb' . DIRECTORY_SEPARATOR . $facilityReportFormat['tb']->file;
                $margintop = $selectedReportFormats['tb']->mtop;
            }
            // create new PDF document
            $pdf = new RwandaTBResultPDFHelper(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false, $pdfTemplatePath);
            if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
                $logoPrintInPdf = $result['facilityLogo'];
            } else {
                $logoPrintInPdf = $arr['logo'];
            }
            $pdf->setHeading($logoPrintInPdf, $arr['header'], $result['labName'], $title = 'RWANDA TB SAMPLES REFERRAL SYSTEM', $labFacilityId = null, $formId = (int) $arr['vl_form'], $facilityInfo, $pdfTemplatePath);
            // set document information
            $pdf->SetCreator('InteLIS');
            $pdf->SetTitle('RWANDA TB SAMPLES REFERRAL SYSTEM');
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
            $pdf->SetMargins(10, PDF_MARGIN_TOP + $margintop, 10);
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
            //Set Age
            $ageCalc = 0;
            $age = 'Unknown';
            if (isset($result['patient_dob']) && trim((string) $result['patient_dob']) !== '' && $result['patient_dob'] != '0000-00-00') {
                $ageCalc = DateUtility::ageInYearMonthDays($result['patient_dob']);
            } elseif (isset($result['patient_age']) && trim((string) $result['patient_age']) !== '' && trim((string) $result['patient_age']) > 0) {
                $age = $result['patient_age'];
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
            $resultDispatchedDate = '';
            $resultDispatchedTime = '';
            if (isset($result['result_printed_datetime']) && trim((string) $result['result_printed_datetime']) !== '' && $result['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
                $expStr = explode(" ", (string) $result['result_printed_datetime']);
                $resultDispatchedDate = DateUtility::humanReadableDateFormat($expStr[0]);
                $resultDispatchedTime = $expStr[1];
            } else {
                $expStr = explode(" ", $currentTime);
                $resultDispatchedDate = DateUtility::humanReadableDateFormat($expStr[0]);
                $resultDispatchedTime = $expStr[1];
            }

            $approvedOnDate = '';
            $approvedOnTime = '';
            if (isset($result['result_approved_datetime']) && trim((string) $result['result_approved_datetime']) !== '' && $result['result_approved_datetime'] != '0000-00-00 00:00:00') {
                $expStr = explode(" ", (string) $result['result_approved_datetime']);
                $approvedOnDate = DateUtility::humanReadableDateFormat($expStr[0]);
                $approvedOnTime = $expStr[1];
            } else {
                $expStr = explode(" ", $currentTime);
                $approvedOnDate = DateUtility::humanReadableDateFormat($expStr[0]);
                $approvedOnTime = $expStr[1];
            }

            $testedBy = null;
            if (!empty($result['tested_by'])) {
                $testedByRes = $usersService->getUserByID($result['tested_by'], ['user_signature', 'user_name']);
                if ($testedByRes) {
                    $testedBy = $testedByRes['user_name'];
                }
            }

            $testedBySignaturePath = null;
            if (!empty($testedByRes['user_signature'])) {
                $testedBySignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $testedByRes['user_signature'];
            }

            if (isset($result['sample_tested_datetime']) && trim((string) $result['sample_tested_datetime']) !== '' && $result['sample_tested_datetime'] != '0000-00-00 00:00:00') {
                $expStr = explode(" ", (string) $result['sample_tested_datetime']);
                $result['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
            } else {
                $result['sample_tested_datetime'] = '';
            }

            if (!isset($result['patient_gender']) || trim((string) $result['patient_gender']) === '') {
                $result['patient_gender'] = _translate('Unreported');
            }

            $userRes = [];
            if (isset($result['authorized_by']) && trim((string) $result['authorized_by']) !== '') {
                $userRes = $usersService->getUserByID($result['authorized_by'], ['user_signature', 'user_name']);
                $resultAuthroizedBy = ($userRes['user_name']);
            } else {
                $resultAuthroizedBy = '';
            }
            $userSignaturePath = null;

            if (!empty($userRes['user_signature'])) {
                $userSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $userRes['user_signature'];
            }

            $userApprovedRes = [];
            if (isset($result['result_approved_by']) && trim((string) $result['result_approved_by']) !== '') {
                $userApprovedRes = $usersService->getUserByID($result['result_approved_by'], ['user_signature', 'user_name']);
                $resultApprovedBy = ($userApprovedRes['user_name']);
            } else {
                $resultApprovedBy = null;
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
            if ($result['result'] != null && trim((string) $result['result']) !== '') {
                $resultType = is_numeric($result['result']);
                if ($result['result'] == 'positive') {
                    $tbResult = $result['result'];
                    //$smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" alt="smile_face"/>';
                } elseif ($result['result'] == 'negative') {
                    $tbResult = $result['result'];
                    $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" alt="smile_face"/>';
                } elseif ($result['result'] == 'indeterminate') {
                    $tbResult = $result['result'];
                    $smileyContent = '';
                }
            }
            if (isset($arr['show_smiley']) && trim((string) $arr['show_smiley']) === "no") {
                $smileyContent = '';
            }
            if ($result['result_status'] == REJECTED) {
                $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/cross.png" alt="rejected"/>';
            }
            $fstate = "";
            if (isset($result['facility_state_id']) && $result['facility_state_id'] != "") {
                $geoResult = $geolocationService->getByProvinceId($result['facility_state_id']);
                $fstate = (isset($geoResult['geo_name']) && $geoResult['geo_name'] != "") ? $geoResult['geo_name'] : null;
            }
            $fdistrict = "";
            if (isset($result['facility_district_id']) && $result['facility_district_id'] != "") {
                $geoResult = $geolocationService->getByDistrictId($result['facility_district_id']);
                $fdistrict = (isset($geoResult['geo_name']) && $geoResult['geo_name'] != "") ? $geoResult['geo_name'] : null;
            }
            if (isset($result['facility_state']) && $result['facility_state'] != "") {
                $fstate = $result['facility_state'];
            }

            if (!empty($result['is_encrypted']) && $result['is_encrypted'] == 'yes') {
                $key = (string) $general->getGlobalConfig('key');
                $result['patient_id'] = $general->crypto('decrypt', $result['patient_id'], $key);
                $patientFname = $general->crypto('decrypt', $patientFname, $key);
                $patientLname = $general->crypto('decrypt', $patientLname, $key);
            }
            $barcodeFormat = $general->getGlobalConfig('barcode_format') ?? 'C39';
            $sampleCode = $result['sample_code'] ?? $result['remote_sample_code'];

            $html = '<table style="padding:3px;">';
            $html .= '<tr>';
            $html .= '   <td style="font-size:13px;font-weight:bolt;text-align:left">SAMPLE ID: ' . $sampleCode . '</td>';
            $html .= '   <td style="text-align:right"><img style="width:300px;height:25px;" src="' . $general->getBarcodeImageContent($sampleCode, $barcodeFormat) . '"></td>';
            $html .= '</tr>';
            $html .= '</table>';
            $html .= '<span style="text-align:center;font-size:14px;font-weight:bolt;"><u>PATIENT RESULT REPORT</u></span><br><br>';
            $html .= '<table style="padding:3px;">';
            $html .= '<tr style="font-size:10px;font-weight:bolt;border-radius:20%;width:100%;background-color:#c0c0c0;">';
            $html .= '   <td colspan="4">HEALTH FACILITY INFORMATION ' . $selectedReportFormats['tb'] . '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Health Facility Name:</td>';
            $html .= '<th style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $result['facility_name'] . '</th>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Source of Funding:</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">CDC</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Facility Type:</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $result['facilityType'] . '</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Province:</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $result['province'] . '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Facility Code:</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $result['facility_code'] . '</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">District:</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $result['district'] . '</td>';
            $html .= '</tr>';
            $html .= '<tr style="font-size:10px;font-weight:bolt;border-radius:20%;width:100%;background-color:#c0c0c0;">';
            $html .= '   <td colspan="4">PATIENT INFORMATION</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Patient Identifier:</td>';
            $html .= '<th style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $result['patient_id'] . '</th>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Gender:</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . ucwords(str_replace("_", " ", (string) $result['patient_gender'])) . '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Full Name:</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $patientFname . ' ' . $patientLname . '</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Date of Birth:</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . DateUtility::humanReadableDateFormat($result['patient_dob']) . '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">TRACNET ID:</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $result['etb_tracker_number'] . '</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Age:</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $ageCalc['year'] . 'Year(s) ' . $ageCalc['months'] . 'Months</td>';
            $html .= '</tr>';
            $html .= '<tr style="font-size:10px;font-weight:bolt;border-radius:20%;width:100%;">';
            $html .= '   <td colspan="4" style="background-color:#c0c0c0;">SPECIMEN INFORMATION</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Collection Date:</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $result['sample_collection_date'] . " " . $sampleCollectionTime . '</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Registered Date:</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . DateUtility::humanReadableDateFormat($result['request_created_datetime'], true) . '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Purpose of Test:</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $result['purpose_of_test'] . '</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Registered By:</td>';
            $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $result['sample_collection_date'] . " " . $sampleCollectionTime . '</td>';
            $html .= '</tr>';
            $html .= '</table><br><br>';

            if (!empty($tbTestInfo)) {
                $n = 1;
                foreach ($tbTestInfo as $row) {
                    $html .= '<table border="1" style="padding:3px;">';
                    $html .= '<tr style="font-size:10px;font-weight:bolt;border-radius:20%;width:100%;background-color:#c0c0c0;border:2px;">';
                    $html .= '<td colspan="4">LABORATORY RESULT - ' . $n . '</td>';
                    $html .= '</tr>';
                    $html .= '<tr>';
                    $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Laboratory:</td>';
                    $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $row['lab_name'] . '</td>';
                    $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Specimen Collected:</td>';
                    $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $row['sample_name'] . '</td>';
                    $html .= '</tr>';
                    $html .= '<tr>';
                    $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Date of reception:</td>';
                    $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . DateUtility::humanReadableDateFormat($row['sample_received_at_lab_datetime']) . '</td>';
                    $html .= '</tr>';

                    if (isset($row['is_sample_rejected']) && !empty($row['is_sample_rejected']) && $row['is_sample_rejected'] == 'yes') {
                        $html .= '          <tr>';
                        $html .= '            <td style="line-height:17px;font-size:12px;text-align:left;width:20%">Reason for sample rejection</td>';
                        $html .= '            <td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $row['rejectionReason'] . '</td>';
                        $html .= '            <td style="line-height:17px;font-size:12px;text-align:left;width:20%">Rejection Date</td>';
                        $html .= '            <td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . DateUtility::humanReadableDateFormat($row['rejection_on']) . '</td>';
                        $html .= '          </tr>';
                    } else {
                        $html .= '          <tr>';
                        $html .= '            <td style="line-height:17px;font-size:12px;text-align:left;width:20%">Type of Test</td>';
                        $html .= '            <td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $row['test_type'] . '</td>';
                        $html .= '            <td style="line-height:17px;font-size:12px;text-align:left;width:20%">Test Result</td>';
                        $html .= '            <td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $row['test_result'] . '</td>';
                        $html .= '          </tr>';
                    }
                    $html .= '<tr>';
                    $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Tested By:</td>';
                    $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $row['testedBy'] . '</td>';
                    $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Date Tested:</td>';
                    $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . DateUtility::humanReadableDateFormat($row['sample_received_at_lab_datetime']) . '</td>';
                    $html .= '</tr>';
                    $html .= '<tr>';
                    $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Reviewed By:</td>';
                    $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $row['reviewedBy'] . '</td>';
                    $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Date Reviewed:</td>';
                    $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . DateUtility::humanReadableDateFormat($row['result_reviewed_datetime']) . '</td>';
                    $html .= '</tr>';
                    $html .= '<tr>';
                    $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Revised By:</td>';
                    $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . $row['revisedBy'] . '</td>';
                    $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:20%">Date Revised:</td>';
                    $html .= '<td style="line-height:17px;font-size:12px;text-align:left;width:30%">' . DateUtility::humanReadableDateFormat($row['revised_on']) . '</td>';
                    $html .= '</tr>';
                    $html .= '<tr>';
                    $html .= '<td colspan="4" style="line-height:17px;font-size:12px;text-align:left;width:100%">Interpretation(Review Note): ' . $row['comments'] . '</td>';
                    $html .= '</tr>';
                    $n += 1;
                    $html .= '</table><br><br>';
                }
                $html .= '<table border="1" style="padding:3px;">';
                $html .= '<tr style="font-size:13px;font-weight:bolt;border-radius:20%;width:100%;background-color:#c0c0c0;border:2px;">';
                $html .= '<td colspan="4">Final Interpretation : ' . $tbResults[$result['result']] . '</td>';
                $html .= '</tr>';
                $html .= '</table>';
            }

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
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td colspan="3" style="line-height:17px;font-size:11px;text-align:justify;border-top:1px solid #67b3ff;">
                    <br>NP = Not Provided, DST = Drug Susceptibility Testing, LJ = Lowenstein-Jensen, MDR = Multi-Drug Restant TB Strain, XDR = Extensively Drug Resistant TB Stain, MGIT = Mycobacterium Growth Index Tube,
                    NTM = Non-TB Mycobacterium, ZN = Ziehl-Neelsen, 1-100 = Absolute colony counts on solid media, Smear Mircoscopy Grading 1-9/100 fields = absolute number of AFBs seen per 100 fields, 1+= 1-100/100 fields, 2+=1-9 AFBs/field;
                    3+=10+AFBs/field, FM = Fluorescent Microscopy, Negative = Zero AFBs/1 Length, Scanty = 1-29 AFB/1 Length, 2+=10-100 AFB/1 Field on average, 3+=>100 AFB/1 Field on average, LPA = Line Probe Assay,
                    FLQ = Fuoroquinolones(Ofloxacin, Moxifloxacin), EMB = Ethambutol, AG/CP = Injectible antibotics(Kanamycin, Amikacin/Capreomycin, Viomycin), PAS = Para-Aminosalicylic Acid
                </td>';
            $html .= '</tr>';
            $html .= '</table>';

            if ($result['result'] != '' || ($result['result'] == '' && $result['result_status'] == REJECTED)) {
                $viewId = CommonService::encryptViewQRCode($result['unique_id']);
                $pdf->writeHTML($html);
                $remoteURL = $general->getRemoteURL();
                if (isset($arr['tb_report_qr_code']) && $arr['tb_report_qr_code'] == 'yes') {
                    $h = 175;
                    if (!empty($signResults)) {
                        if (isset($facilityInfo['address']) && $facilityInfo['address'] != "") {
                            $h = 185;
                        }
                    } else {
                        $h = 148.5;
                    }
                    //$pdf->write2DBarcode($remoteURL . '/tb/results/view.php?q=' . $viewId . '', 'QRCODE,H', 170, $h, 20, 20, [], 'N');
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
                $action = ($_SESSION['userName'] ?: 'System') . ' generated the test result PDF with Patient ID/Code ' . $result['patient_id'];
                $resource = 'print-test-result';
                $data = ['event_type' => $eventType, 'action' => $action, 'resource' => $resource, 'date_time' => $currentTime];
                $db->insert($tableName1, $data);
                //Update print datetime in TB tbl.
                $tbQuery = "SELECT result_printed_datetime FROM form_tb as tb WHERE tb.tb_id ='" . $result['tb_id'] . "'";
                $tbResult = $db->query($tbQuery);
                if ($tbResult[0]['result_printed_datetime'] == null || trim((string) $tbResult[0]['result_printed_datetime']) === '' || $tbResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
                    $db->where('tb_id', $result['tb_id']);
                    $db->update($tableName2, ['result_printed_datetime' => $currentTime, 'result_dispatched_datetime' => $currentTime]);
                }
            }
        }
    }
} catch (Exception $exc) {
    error_log($exc->getMessage());
}
