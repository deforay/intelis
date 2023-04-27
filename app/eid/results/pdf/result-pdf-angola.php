<?php
// this file is included in eid/results/generate-result-pdf.php


use App\Services\EidService;
use App\Utilities\DateUtils;


class MYPDFANG extends TCPDF
{
    //Page header
    public function setHeading($logo, $text, $lab)
    {
        $this->logo = $logo;
        //$this->text = $text;
        //$this->lab = $lab;
    }
    public function imageExists($filePath)
    {
        return (!empty($filePath) && file_exists($filePath) && !is_dir($filePath) && filesize($filePath) > 0 && false !== getimagesize($filePath));
    }
    //Page header
    public function Header()
    {
        // Logo
        //$imageFilePath = K_PATH_IMAGES.'logo_example.jpg';
        //$this->Image($imageFilePath, 10, 10, 15, '', 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        // Set font
        if (trim($this->logo) != '') {
            if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                $this->Image($imageFilePath, 95, 3, 15, '', '', '', 'T');
            }
        }
        //$this->SetFont('helvetica', 'B', 7);
        //$this->writeHTMLCell(30,0,16,28,$this->text, 0, 0, 0, true, 'A', true);
        $this->SetFont('helvetica', '', 7);
        $this->writeHTMLCell(0, 0, 10, 18, 'República de Angola', 0, 0, 0, true, 'C');
        $this->SetFont('helvetica', '', 7);
        $this->writeHTMLCell(0, 0, 10, 22, 'Ministério da Saúde', 0, 0, 0, true, 'C');
        $this->SetFont('helvetica', '', 7);
        $this->writeHTMLCell(0, 0, 10, 26, 'Instituto Nacional de Luta contra a SIDA', 0, 0, 0, true, 'C');
        $this->SetFont('helvetica', 'B', 8);
        $this->writeHTMLCell(0, 0, 10, 30, 'RELATÓRIO DE RESULTADOS DE QUANTIFICAÇÃO DE CARGA VIRAL DE VIH', 0, 0, 0, true, 'C');
        //if(trim($this->lab)!= ''){
        // $this->SetFont('helvetica', '', 9);
        //$this->writeHTMLCell(0,0,10,26,strtoupper($this->lab), 0, 0, 0, true, 'C', true);
        //}
        $this->writeHTMLCell(0, 0, 15, 36, '<hr>', 0, 0, 0, true, 'C');
    }

    // Page footer
    public function Footer()
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', '', 8);
        // Page number
        $this->Cell(0, 10, 'Page' . $_SESSION['aliasPage'] . '/' . $_SESSION['nbPages'], 0, false, 'C', 0);
    }
}



$eidModel = new EidService();
$eidResults = $eidModel->getEidResults();

$resultFilename = '';

$userRes = $users->getUserInfo($_SESSION['userId'], 'user_signature');
$userSignaturePath = null;

if (!empty($userRes['user_signature'])) {
    $userSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $userRes['user_signature'];
}


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
        $pdf = new MYPDFANG(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
            $logoPrintInPdf = $result['facilityLogo'];
        } else {
            $logoPrintInPdf = $arr['logo'];
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
            $result['sample_collection_date'] = DateUtils::humanReadableDateFormat($expStr[0]);
            $sampleCollectionTime = $expStr[1];
        } else {
            $result['sample_collection_date'] = '';
            $sampleCollectionTime = '';
        }
        $sampleReceivedDate = '';
        $sampleReceivedTime = '';
        if (isset($result['sample_received_at_vl_lab_datetime']) && trim($result['sample_received_at_vl_lab_datetime']) != '' && $result['sample_received_at_vl_lab_datetime'] != '0000-00-00 00:00:00') {
            $expStr = explode(" ", $result['sample_received_at_vl_lab_datetime']);
            $sampleReceivedDate = DateUtils::humanReadableDateFormat($expStr[0]);
            $sampleReceivedTime = $expStr[1];
        }
        $sampleDispatchDate = '';
        $sampleDispatchTime = '';
        if (isset($result['result_dispatched_datetime']) && trim($result['result_dispatched_datetime']) != '' && $result['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
            $expStr = explode(" ", $result['result_dispatched_datetime']);
            $sampleDispatchDate = DateUtils::humanReadableDateFormat($expStr[0]);
            $sampleDispatchTime = $expStr[1];
        }

        if (isset($result['sample_tested_datetime']) && trim($result['sample_tested_datetime']) != '' && $result['sample_tested_datetime'] != '0000-00-00 00:00:00') {
            $expStr = explode(" ", $result['sample_tested_datetime']);
            $result['sample_tested_datetime'] = DateUtils::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
        } else {
            $result['sample_tested_datetime'] = '';
        }

        if (!isset($result['child_gender']) || trim($result['child_gender']) == '') {
            $result['child_gender'] = 'not reported';
        }
        if (isset($result['approvedBy']) && trim($result['approvedBy']) != '') {
            $resultApprovedBy = ($result['approvedBy']);
        } else {
            $resultApprovedBy  = '';
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
                //$smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" alt="smile_face"/>';
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
        $html = '<table style="padding:0px 2px 2px 2px;">';
        $html .= '<tr>';

        $html .= '<td colspan="3">';
        $html .= '<table style="padding:2px;">';
        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">REQUESTING HEALTH CENTER NAME</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">HEALTH FACILITY CODE</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Province/State</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">District/County</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['facility_name']) . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['facility_code']) . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['facility_state']) . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['facility_district']) . '</td>';
        $html .= '</tr>';
        $html .= '</table>';
        $html .= '</td>';
        $html .= '</tr>';

        $html .= '<tr>';

        $html .= '<td colspan="3">';
        $html .= '<table style="padding:8px 2px 2px 2px;">';
        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">PATIENT NAME</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">CHILD ID</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">AGE</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">GENDER</td>';
        $html .= '</tr>';
        $html .= '<tr>';

        $patientFname = ($general->crypto('doNothing', $result['child_name'], $result['child_id']));


        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $patientFname . '</td>';

        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['child_id'] . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $age . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . (str_replace("_", " ", $result['child_gender'])) . '</td>';
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
        $html .= '<td colspan="3" style="line-height:10px;"></td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Nº da amostra</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Data da colheita de amostra</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Data de Recepção de Amostras</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_code'] . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_collection_date'] . " " . $sampleCollectionTime . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $sampleReceivedDate . " " . $sampleReceivedTime . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td colspan="3" style="line-height:10px;"></td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Data da Quantificação</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">RESULT RELEASE DATE</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Plataforma usada</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_tested_datetime'] . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $sampleDispatchDate . " " . $sampleDispatchTime . '</td>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['eid_test_platform']) . '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td colspan="3" style="line-height:10px;"></td>';
        $html .= '</tr>';

        $html .= '<tr>';
        $html .= '<td colspan="3">';
        $html .= '<table style="padding:12px 2px 2px 2px;">';

        $html .= '<tr style="background-color:#dbdbdb;"><td colspan="2" style="line-height:70px;font-size:18px;font-weight:normal;">&nbsp;&nbsp;Resultado &nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $eidResults[$result['result']] . '</td><td >' . $smileyContent . '</td></tr>';
        //$html .= '<tr style="background-color:#dbdbdb;"><td colspan="2" style="line-height:70px;font-size:18px;font-weight:normal;">&nbsp;&nbsp;Result &nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . ($result['result']) . '</td><td >' . $smileyContent . '</td></tr>';
        if ($result['reason_for_sample_rejection'] != '') {
            $html .= '<tr><td colspan="3" style="line-height:26px;font-size:12px;font-weight:bold;text-align:left;">&nbsp;&nbsp;Rejection Reason&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $result['rejection_reason_name'] . '</td></tr>';
        }

        $html .= '<tr><td colspan="3"></td></tr>';
        $html .= '</table>';
        $html .= '</td>';
        $html .= '</tr>';

        if (trim($result['lab_tech_comments']) != '') {
            $html .= '<tr>';
            $html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">LAB COMMENTS&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . ($result['lab_tech_comments']) . '</span></td>';
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
            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">TESTED BY</td>';
            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SIGNATURE</td>';
            $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">DATE</td>';
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
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">APPROVED BY</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">SIGNATURE</td>';
        $html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">DATE</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $resultApprovedBy . '</td>';
        if (!empty($userSignaturePath) && file_exists($userSignaturePath)) {
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"><img src="' . $userSignaturePath . '" style="width:70px;" /></td>';
        } else {
            $html .= '<td style="line-height:11px;font-size:11px;text-align:left;"></td>';
        }

        $html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . DateUtils::humanReadableDateFormat($result['result_approved_datetime']) . '</td>';
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
        if ($result['result'] != '' || ($result['result'] == '' && $result['result_status'] == '4')) {
            $pdf->writeHTML($html);
            $pdf->lastPage();
            $filename = $pathFront . DIRECTORY_SEPARATOR . 'p' . $page . '.pdf';
            $pdf->Output($filename, "F");
            if ($draftTextShow) {
                //Watermark section
                $watermark = new \App\Helpers\PdfWatermarkHelper();
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
                'date_time' => DateUtils::getCurrentDateTime()
            );
            $db->insert($tableName1, $data);
            //Update print datetime in VL tbl.
            $vlQuery = "SELECT result_printed_datetime FROM form_eid as vl WHERE vl.eid_id ='" . $result['eid_id'] . "'";
            $vlResult = $db->query($vlQuery);
            if ($vlResult[0]['result_printed_datetime'] == null || trim($vlResult[0]['result_printed_datetime']) == '' || $vlResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
                $db = $db->where('eid_id', $result['eid_id']);
                $db->update($tableName2, array('result_printed_datetime' => DateUtils::getCurrentDateTime()));
            }
        }
    }

    if (!empty($pages)) {
        $resultPdf = new \App\Helpers\PdfConcatenateHelper();
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
