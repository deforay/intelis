<?php

// this file is included in covid-19/results/generate-result-pdf.php
use const SAMPLE_STATUS\REJECTED;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\Covid19Service;
use App\Helpers\PdfWatermarkHelper;
use App\Registries\ContainerRegistry;
use App\Helpers\ResultPDFHelpers\Covid19ResultPDFHelper;

if (!class_exists('SouthSudanCovid19PDFHelper')) {
    class SouthSudanCovid19PDFHelper extends Covid19ResultPDFHelper
    {
        //Page header
        #[Override]
        public function Header(): void
        {
            // Logo

            if ($this->htitle !== null && $this->htitle !== '' && $this->htitle !== '0' && trim($this->htitle) !== '') {

                if ($this->formId !== null && $this->formId == 1) {
                    if ($this->logo !== null && $this->logo !== '' && $this->logo !== '0' && trim($this->logo) !== '' && MiscUtility::isImageValid($this->logo)) {
                        $this->Image($this->logo, 10, 5, 25, '', '', '', 'T');
                    }
                    $this->SetFont('helvetica', 'B', 15);
                    $this->writeHTMLCell(0, 0, 40, 7, $this->text, 0, 0, 0, true, 'L');
                    if ($this->lab !== null && $this->lab !== '' && $this->lab !== '0' && trim($this->lab) !== '') {
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
                } else {
                    if ($this->logo !== null && $this->logo !== '' && $this->logo !== '0' && trim($this->logo) !== '' && MiscUtility::isImageValid($this->logo)) {
                        $this->Image($this->logo, 10, 5, 25, '', '', '', 'T');
                    }

                    $this->SetFont('helvetica', 'B', 8);
                    $this->writeHTMLCell(0, 0, 10, 22, $this->text, 0, 0, 0, true, 'C');
                    if ($this->lab !== null && $this->lab !== '' && $this->lab !== '0' && trim($this->lab) !== '') {
                        $this->SetFont('helvetica', '', 9);
                        $this->writeHTMLCell(0, 0, 10, 26, strtoupper($this->lab), 0, 0, 0, true, 'C');
                    }

                    $this->SetFont('helvetica', '', 14);
                    $this->writeHTMLCell(0, 0, 10, 30, 'PATIENT REPORT FOR COVID-19 TEST', 0, 0, 0, true, 'C');

                    $this->writeHTMLCell(0, 0, 15, 38, '<hr>', 0, 0, 0, true, 'C');
                }
            }
        }
    }
}

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


$covid19Results = $covid19Service->getCovid19Results();

$countryFormId = (int) $general->getGlobalConfig('vl_form');
$resultFilename = '';

if (!empty($requestResult)) {
    $_SESSION['rVal'] = MiscUtility::generateRandomString(6);
    $pathFront = TEMP_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal'];
    MiscUtility::makeDirectory($pathFront);
    $pages = [];
    $page = 1;
    foreach ($requestResult as $result) {

        $covid19TestQuery = "SELECT * from covid19_tests where covid19_id= " . $result['covid19_id'] . " ORDER BY test_id ASC";
        $covid19TestInfo = $db->rawQuery($covid19TestQuery);

        $facilityQuery = "SELECT * from form_covid19 as c19 INNER JOIN facility_details as fd ON c19.facility_id=fd.facility_id where covid19_id= " . $result['covid19_id'] . " GROUP BY fd.facility_id LIMIT 1";
        $facilityInfo = $db->rawQueryOne($facilityQuery);
        // echo "<pre>";print_r($covid19TestInfo);die;
        $patientFname = ($general->crypto('doNothing', $result['patient_name'], $result['patient_id']));
        $patientLname = ($general->crypto('doNothing', $result['patient_surname'], $result['patient_id']));

        if (!empty($result['is_encrypted']) && $result['is_encrypted'] == 'yes') {
            $key = (string) $general->getGlobalConfig('key');
            $result['patient_id'] = $general->crypto('decrypt', $result['patient_id'], $key);
            $patientFname = $general->crypto('decrypt', $patientFname, $key);
            $patientLname = $general->crypto('decrypt', $patientLname, $key);
        }

        $signQuery = "SELECT * from lab_report_signatories where lab_id=? AND test_types like '%covid19%' AND signatory_status like 'active' ORDER BY display_order ASC";
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
        // create new PDF document
        $pdf = new SouthSudanCovid19PDFHelper(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        if (MiscUtility::isImageValid(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
            $logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'];
        } else {
            $logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $arr['logo'];
        }
        $pdf->setHeading($logoPrintInPdf, $arr['header'], $result['labName'], $title = 'COVID-19 PATIENT REPORT', $labFacilityId = null, $formId = (int) $arr['vl_form'], $facilityInfo);
        // set document information
        $pdf->SetCreator('InteLIS');
        $pdf->SetTitle('SARS-CoV-2 Patient Report');
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

        $stamp = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . 'stamps' . DIRECTORY_SEPARATOR . 'hrl-stamp.png';

        if (MiscUtility::isImageValid($stamp)) {
            $pdf->SetAlpha(0.6);
            $pdf->Image($stamp, 90, 165, 40, null);
        }
        //Set Age
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

        $testedBy = null;
        if (!empty($result['tested_by'])) {
            $testedByRes = $usersService->getUserByID($result['tested_by'], 'user_name');
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
            $resultApprovedBy = ($result['authorized_by']);
            $userRes = $usersService->getUserByID($result['result_approved_by'], 'user_signature');
        } else {
            $resultApprovedBy = null;
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
        if ($result['result'] != null && trim((string) $result['result']) !== '') {
            $resultType = is_numeric($result['result']);
            if ($result['result'] == 'positive') {
                $vlResult = $result['result'];
                //$smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" alt="smile_face"/>';
            } elseif ($result['result'] == 'negative') {
                $vlResult = $result['result'];
                $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" alt="smile_face"/>';
            } elseif ($result['result'] == 'indeterminate') {
                $vlResult = $result['result'];
                $smileyContent = '';
            }
        }
        if (isset($arr['show_smiley']) && trim((string) $arr['show_smiley']) === "no") {
            $smileyContent = '';
        }
        if ($result['result_status'] == REJECTED) {
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
        $html .= '<td style="line-height:20px;font-size:11px;text-align:left;border-left:1px solid #67b3ff;">' . (str_replace("_", " ", (string) $result['patient_gender'])) . '</td>';
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

        // if (isset($covid19TestInfo) && !empty($covid19TestInfo) && $arr['covid19_tests_table_in_results_pdf'] == 'yes') {
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
        } elseif (isset($result['result']) && $result['result'] == "postive") {
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
        if (trim((string) $result['lab_tech_comments']) !== '') {
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

        if (!empty($signResults)) {
            $lh = 20;
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
                $lmSign = UPLOAD_PATH . "/labs/" . $row['lab_id'] . "/signatures/" . $row['signature'];
                $signature = '';
                if (MiscUtility::isImageValid($lmSign)) {
                    $signature = '<img src="' . $lmSign . '" style="width:40px;" />';
                }
                $html .= '<tr>';
                $html .= '<td style="line-height:17px;font-size:11px;text-align:left;font-weight:bold;border-bottom:1px solid #67b3ff;">' . $row['designation'] . '</td>';
                $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;">' . $row['name_of_signatory'] . '</td>';
                $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;">' . $signature . '</td>';
                $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid #67b3ff;border-left:1px solid #67b3ff;">' . date('d-M-Y H:i:s a') . '</td>';
                $html .= '</tr>';
            }
            $html .= '</table>';
        } else {
            $lh = 0;
            $html .= '<tr>';
            $html .= '<td colspan="5" style="line-height:50px;"></td>';
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
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '</table>';

        $html .= '<table>';
        $html .= '<tr>';
        $html .= '<td colspan="2" style="line-height:' . $lh . 'px;border-bottom:2px solid #d3d3d3;"></td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td colspan="2" style="font-size:10px;text-align:left;width:60%;"></td>';
        $html .= '</tr>';
        if ($general->isLISInstance() && $result['data_sync'] == 0) {
            $generatedAtTestingLab = " | " . _translate("Report generated at Testing Lab");
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
        $showQR = false;
        if (isset($arr['covid19_report_qr_code']) && $arr['covid19_report_qr_code'] == 'yes' && !empty($general->getRemoteURL())) {
            $showQR = true;
        }
        if (($showQR || !empty($result['result'])) || ($result['result'] == '' && $result['result_status'] == REJECTED)) {
            $viewId = CommonService::encryptViewQRCode($result['unique_id']);
            $pdf->writeHTML($html);
            $remoteURL = $general->getRemoteURL();
            if (isset($arr['covid19_report_qr_code']) && $arr['covid19_report_qr_code'] == 'yes') {
                $h = 175;
                if (!empty($signResults)) {
                    if (isset($facilityInfo['address']) && $facilityInfo['address'] != "") {
                        $h = 185;
                    }
                } else {
                    $h = 142.5;
                }
                if (isset($arr['covid19_report_qr_code']) && $arr['covid19_report_qr_code'] == 'yes' && !empty($general->getRemoteURL())) {
                    $remoteURL = $general->getRemoteURL();
                    $pdf->write2DBarcode($remoteURL . '/covid-19/results/view.php?q=' . $viewId, 'QRCODE,H', 170, $h, 20, 20, [], 'N');
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
            $vlQuery = "SELECT result_printed_datetime FROM form_covid19 as vl WHERE vl.covid19_id ='" . $result['covid19_id'] . "'";
            $vlResult = $db->query($vlQuery);
            if ($vlResult[0]['result_printed_datetime'] == null || trim((string) $vlResult[0]['result_printed_datetime']) === '' || $vlResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
                $db->where('covid19_id', $result['covid19_id']);
                $db->update($tableName2, ['result_printed_datetime' => $currentTime, 'result_dispatched_datetime' => $currentTime]);
            }
        }
    }
}
