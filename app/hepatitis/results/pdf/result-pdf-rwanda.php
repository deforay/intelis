<?php

use const SAMPLE_STATUS\REJECTED;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\HepatitisService;
use App\Helpers\PdfWatermarkHelper;
use App\Helpers\PdfConcatenateHelper;
use App\Registries\ContainerRegistry;
use App\Helpers\ResultPDFHelpers\HepatitisResultPDFHelper;

use const COUNTRY\RWANDA;

// this file is included in hepatitis/results/generate-result-pdf.php

/** @var HepatitisService $hepatitisService */
$hepatitisService = ContainerRegistry::get(HepatitisService::class);
//$hepatitisResults = $hepatitisService->getHepatitisResults();

$resultFilename = '';

$userRes = $usersService->getUserByID($_SESSION['userId'], 'user_signature');
$userSignaturePath = null;

if (!empty($userRes['user_signature'])) {
    $userSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $userRes['user_signature'];
}

if (!empty($requestResult)) {
    $_SESSION['rVal'] = MiscUtility::generateRandomString(6);
    $pathFront = TEMP_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal'];
    MiscUtility::makeDirectory($pathFront);
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
        $counter = count($mFieldArray);
        //Set watermark text
        for ($m = 0; $m < $counter; $m++) {
            if (!isset($result[$mFieldArray[$m]]) || trim((string) $result[$mFieldArray[$m]]) === '' || $result[$mFieldArray[$m]] == null || $result[$mFieldArray[$m]] == '0000-00-00 00:00:00') {
                $draftTextShow = true;
                break;
            }
        }
        // create new PDF document
        $pdf = new HepatitisResultPDFHelper(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->setHeading($arr['logo'], $arr['header'], $result['labName'], $title = 'HEPATATIS - VIRAL LOAD PATIENT REPORT');
        // set document information
        $pdf->SetCreator('InteLIS');
        $pdf->setTitle('Hepatitis Patient Report');
        //$pdf->SetSubject('TCPDF Tutorial');
        //$pdf->SetKeywords('TCPDF, PDF, example, test, guide');

        // set default header data
        $pdf->setHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

        // set header and footer fonts
        $pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
        $pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);

        // set default monospaced font
        $pdf->setDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // set margins
        $pdf->setMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP + 14, PDF_MARGIN_RIGHT);
        $pdf->setHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->setFooterMargin(PDF_MARGIN_FOOTER);

        // set auto page breaks
        $pdf->setAutoPageBreak(true, PDF_MARGIN_BOTTOM);

        // set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);



        // ---------------------------------------------------------

        // set font
        $pdf->setFont('helvetica', '', 18);

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
        $sampleDispatchDate = '';
        $sampleDispatchTime = '';
        if (isset($result['result_printed_datetime']) && trim((string) $result['result_printed_datetime']) !== '' && $result['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
            $expStr = explode(" ", (string) $result['result_printed_datetime']);
            $sampleDispatchDate = DateUtility::humanReadableDateFormat($expStr[0]);
            $sampleDispatchTime = $expStr[1];
        } else {
            $expStr = explode(" ", (string) $currentTime);
            $sampleDispatchDate = DateUtility::humanReadableDateFormat($expStr[0]);
            $sampleDispatchTime = $expStr[1];
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
        if (isset($result['approvedBy']) && trim((string) $result['approvedBy']) !== '') {
            $resultApprovedBy = ($result['approvedBy']);
        } else {
            $resultApprovedBy = null;
        }
        $vlResult = '';
        $showMessage = '';
        $tndMessage = '';
        $messageTextSize = '12px';

        $html = '<table style="padding:0px 2px 2px 2px;">';
        /* $html .= '<tr>';
                $html .= '<td colspan="3">';
                    $html .= '<table style="padding:2px;">';
                        $html .= '<tr>';
                            $html .= '<td style="line-height:11px;font-size:14px;font-weight:bold;text-align:left;">NRL SECTION CODE : '.($result['sample_code']).'</td>';
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
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['facility_name']) . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['funding_source_name']) . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['facility_name']) . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['facility_code']) . '</td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SITE CONTACT</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">COUNTRY</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;"></td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;"></td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['vl_testing_site']) . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"> RWANDA </td>';
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
        $patientFname = ($general->crypto('doNothing', $result['patient_name'], $result['patient_id']));
        $patientLname = ($general->crypto('doNothing', $result['patient_surname'], $result['patient_id']));

        if (!empty($result['is_encrypted']) && $result['is_encrypted'] == 'yes') {
            $key = (string) $general->getGlobalConfig('key');
            $result['patient_id'] = $general->crypto('decrypt', $result['patient_id'], $key);
            $patientFname = $general->crypto('decrypt', $patientFname, $key);
            $patientLname = $general->crypto('decrypt', $patientLname, $key);
        }

        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['patient_id'] . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $patientFname . ' ' . $patientLname . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . (str_replace("_", " ", (string) $result['patient_gender'])) . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . DateUtility::humanReadableDateFormat($result['patient_dob']) . ' / ' . $age . '</td>';
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
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_collection_date'] . ' ' . $sampleCollectionTime . '</td>';
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
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['labName']) . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['hepatitis_test_platform']) . '</td>';
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
        if ((isset($result['hcv_vl_count']) && $result['hcv_vl_count'] != "") && (isset($result['hbv_vl_count']) && $result['hbv_vl_count'] != "")) {
            $html .= '<tr>';
            $html .= '<td colspan="2" style="line-height:50px;font-size:14px;text-align:left;">&nbsp;&nbsp;<strong>TEST REQUESTED : </strong>' . $result['sample_tested_datetime'] . '</td>';
            $html .= '</tr>';
            $html .= '<tr>';
            $html .= '<td colspan="2" style="line-height:2px;"></td>';
            $html .= '</tr>';
            $html .= '<tr style="background-color:#dbdbdb;">';
            if (isset($result['hcv_vl_count']) && $result['hcv_vl_count'] != "") {
                $html .= '<td style="line-height:50px;font-size:14px;font-weight:bold;text-align:left;">&nbsp;&nbsp;HCV VL RESULTS : ' . ($result['hcv_vl_count']) . '</td>';
            } elseif (isset($result['hbv_vl_count']) && $result['hbv_vl_count'] != "") {
                $html .= '<td style="line-height:50px;font-size:14px;font-weight:bold;text-align:left;">&nbsp;&nbsp;HBV VL RESULTS : ' . ($result['hbv_vl_count']) . '</td>';
            }
            $html .= '</tr>';
        } else {
            $resultTxt = "Result";
            $resultVal = "";
            if (isset($result['hcv_vl_count']) && $result['hcv_vl_count'] != "") {
                $resultTxt = "HCV VL Result";
                $resultVal = ($result['hcv_vl_count']);
            } elseif (isset($result['hbv_vl_count']) && $result['hbv_vl_count'] != "") {
                $resultTxt = "HBV VL Result";
                $resultVal = ($result['hbv_vl_count']);
            }
            $html .= '<tr style="background-color:#dbdbdb;">';
            $html .= '<td style="line-height:50px;font-size:16px;font-weight:bold;text-align:left;">&nbsp;&nbsp;<strong>TEST REQUESTED : </strong>' . $result['sample_tested_datetime'] . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; ' . $resultTxt . ' : ' . $resultVal . '</td>';
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

        if (trim((string) $result['lab_tech_comments']) !== '') {
            $html .= '<tr>';
            $html .= '<td colspan="3" style="line-height:11px;font-size:11px;">LAB COMMENTS&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . ($result['lab_tech_comments']) . '</span></td>';
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

        if ($result['is_sample_rejected'] == 'no') {
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
        }

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
        if ($userSignaturePath !== null && $userSignaturePath !== '' && $userSignaturePath !== '0' && file_exists($userSignaturePath)) {
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $userSignaturePath . '" style="width:70px;" /></td>';
        } else {
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
        }
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . DateUtility::humanReadableDateFormat($result['result_approved_datetime']) . '</td>';
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
        if ($general->isLISInstance() && $result['data_sync'] == 0) {
            $generatedAtTestingLab = " | " . _translate("Report generated at Testing Lab");
        } else {
            $generatedAtTestingLab = "";
        }
        $html .= '<tr>';
        $html .= '<td style="font-size:10px;text-align:left;">Printed on : ' . $printDate . '&nbsp;&nbsp;' . $printDateTime . $generatedAtTestingLab . '</td>';
        $html .= '<td style="font-size:10px;text-align:left;width:60%;"></td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td colspan="2" style="font-size:10px;text-align:left;width:60%;"></td>';
        $html .= '</tr>';
        $html .= '</table>';
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '</table>';

        if (($result['hcv_vl_count'] != '' || $result['hbv_vl_count'] != '') || (($result['hcv_vl_count'] == '' || $result['hbv_vl_count'] == '') && $result['result_status'] == REJECTED)) {
            $pdf->writeHTML($html);
            if (isset($arr['hepatitis_report_qr_code']) && $arr['hepatitis_report_qr_code'] == 'yes' && !empty($general->getRemoteURL())) {
                $keyFromGlobalConfig = $general->getGlobalConfig('key');
                if (!empty($keyFromGlobalConfig)) {
                    $encryptedString = CommonService::encrypt($result['unique_id'], base64_decode((string) $keyFromGlobalConfig));
                    $remoteURL = $general->getRemoteURL();
                    $pdf->write2DBarcode($remoteURL . '/hepatitis/results/view.php?q=' . $encryptedString, 'QRCODE,H', 150, 200, 30, 30, [], 'N');
                }
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
            //Update print datetime in VL tbl.
            $vlQuery = "SELECT result_printed_datetime FROM form_hepatitis as vl WHERE vl.hepatitis_id ='" . $result['hepatitis_id'] . "'";
            $vlResult = $db->query($vlQuery);
            if ($vlResult[0]['result_printed_datetime'] == null || trim((string) $vlResult[0]['result_printed_datetime']) === '' || $vlResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
                $db->where('hepatitis_id', $result['hepatitis_id']);
                $db->update($tableName2, ['result_printed_datetime' => $currentTime, 'result_dispatched_datetime' => $currentTime]);
            }
        }
    }
}

MiscUtility::removeDirectory($pathFront);
unset($_SESSION['rVal']);
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename);
