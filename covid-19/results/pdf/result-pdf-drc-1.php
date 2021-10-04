<?php
class DRC_PDF extends MYPDF
{
    //Page header
    public function Header()
    {
        // Logo

        if ($this->htitle != '') {

            if (trim($this->logo) != '') {
                if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                    $image_file = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                    $this->Image($image_file, 10, 5, 25, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                }
            }
            if (trim($this->logo) != '') {
                if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                    $image_file = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                    $this->Image($image_file, 175, 5, 25, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                }
            }
            $this->SetFont('helvetica', 'B', 12);
            $this->writeHTMLCell(0, 0, 0, 5, 'REPUBLIQUE DEMOCRATIQUE DU CONGO', 0, 0, 0, true, 'C', true);
            $this->SetFont('helvetica', 'B', 10);
            $this->writeHTMLCell(0, 0, 0, 10, $this->text, 0, 0, 0, true, 'C', true);
            if (trim($this->lab) != '') {
                $this->SetFont('helvetica', 'B', 11);
                $this->writeHTMLCell(0, 0, 0, 15, strtoupper($this->lab), 0, 0, 0, true, 'C', true);
            }
            $this->SetFont('helvetica', '', 10);
            $this->writeHTMLCell(0, 0, 0, 20, 'Département de virologie', 0, 0, 0, true, 'C', true);
            $this->SetFont('helvetica', 'U', 11);
            $this->writeHTMLCell(0, 0, 0, 28, 'Laboratoire National de Référence Pour la Grippe et les virus respiratoires', 0, 0, 0, true, 'C', true);

            $this->SetFont('helvetica', 'B,U', 12);
            $this->writeHTMLCell(0, 0, 0, 36, 'RÉSULTATS DES LABORATOIRES DES ECHANTILLONS RESPIRATOIRES', 0, 0, 0, true, 'C', true);
            $this->SetFont('helvetica', '', 12);
            $this->writeHTMLCell(0, 0, 0, 44, 'TESTES AU nCOV-19 PAR RT-PCR en temps réel n°...', 0, 0, 0, true, 'C', true);

            $this->writeHTMLCell(0, 0, 10, 49, '<hr>', 0, 0, 0, true, 'C', true);
            $this->writeHTMLCell(0, 0, 10, 50, '<hr>', 0, 0, 0, true, 'C', true);
        }
    }
}

// create new PDF document
$pdf = new DRC_PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
    $logoPrintInPdf = $result['facilityLogo'];
} else {
    $logoPrintInPdf = $arr['logo'];
}

$pdf->setHeading($logoPrintInPdf, $arr['header'], $result['labName'], $title = 'COVID-19 PATIENT REPORT', null, 3);
// set document information
$pdf->SetCreator('VLSM');
$pdf->SetTitle('Covid-19 Rapport du patient');
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
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP + 24, PDF_MARGIN_RIGHT);
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
$sampleDispatchDate = '';
$sampleDispatchTime = '';
if (isset($result['result_printed_datetime']) && trim($result['result_printed_datetime']) != '' && $result['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
    $expStr = explode(" ", $result['result_printed_datetime']);
    $sampleDispatchDate = $general->humanDateFormat($expStr[0]);
    $sampleDispatchTime = $expStr[1];
} else {
    $expStr = explode(" ", $currentTime);
    $sampleDispatchDate = $general->humanDateFormat($expStr[0]);
    $sampleDispatchTime = $expStr[1];
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
if (isset($result['approvedBy']) && trim($result['approvedBy']) != '') {
    $resultApprovedBy = ucwords($result['approvedBy']);
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
        $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" alt="smile_face"/>';
    } else if ($result['result'] == 'negative') {
        $vlResult = $result['result'];
        $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" alt="smile_face"/>';
    } else if ($result['result'] == 'indeterminate') {
        $vlResult = $result['result'];
        $smileyContent = '';
    }
}
if ($result['result_status'] == '4') {
    $smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/cross.png" alt="rejected"/>';
}
if (isset($arr['show_smiley']) && trim($arr['show_smiley']) == "no") {
    $smileyContent = '';
}
$html = '';
$html .= '<table style="padding:0px 2px 2px 2px;">';
$html .= '<tr>';
$html .= '<td>';
$html .= '<table style="padding:10px;">';

$html .= '<tr>';
$html .= '<td width="20%" style="line-height:14px;font-size:11px;text-align:left;font-weight:bold;">Labid</td>';
$html .= '<td width="5%" style="line-height:14px;font-size:11px;text-align:center;">:</td>';
$html .= '<td width="50%" style="line-height:14px;font-size:11px;text-align:left;">' . ucwords($result['labName']) . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="20%" style="line-height:14px;font-size:11px;text-align:left;font-weight:bold;">Province</td>';
$html .= '<td width="5%" style="line-height:14px;font-size:11px;text-align:center;">:</td>';
$html .= '<td width="50%" style="line-height:14px;font-size:11px;text-align:left;">' . ucwords($result['facility_state']) . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="20%" style="line-height:14px;font-size:11px;text-align:left;font-weight:bold;">Zone de santé</td>';
$html .= '<td width="5%" style="line-height:14px;font-size:11px;text-align:center;">:</td>';
$html .= '<td width="50%" style="line-height:14px;font-size:11px;text-align:left;">' . ucwords($result['facility_district']) . '</td>';
$html .= '</tr>';

$patientFname = ucwords($general->crypto('decrypt', $result['patient_name'], $result['patient_id']));
$patientLname = ucwords($general->crypto('decrypt', $result['patient_surname'], $result['patient_id']));
$html .= '<tr>';
$html .= '<td width="20%" style="line-height:14px;font-size:11px;text-align:left;font-weight:bold;">Nom de Malade</td>';
$html .= '<td width="5%" style="line-height:14px;font-size:11px;text-align:center;">:</td>';
$html .= '<td width="50%" style="line-height:14px;font-size:11px;text-align:left;">' . $patientFname . ' ' . $patientLname . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="20%" style="line-height:14px;font-size:11px;text-align:left;font-weight:bold;">Age</td>';
$html .= '<td width="5%" style="line-height:14px;font-size:11px;text-align:center;">:</td>';
$html .= '<td width="50%" style="line-height:14px;font-size:11px;text-align:left;">' . $general->humanDateFormat($result['patient_dob']) . '/' . $age . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="20%" style="line-height:14px;font-size:11px;text-align:left;font-weight:bold;">Sexe</td>';
$html .= '<td width="5%" style="line-height:14px;font-size:11px;text-align:center;">:</td>';
$html .= '<td width="50%" style="line-height:14px;font-size:11px;text-align:left;">' . ucwords(str_replace("_", " ", $result['patient_gender'])) . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="20%" style="line-height:14px;font-size:11px;text-align:left;font-weight:bold;">Adresse</td>';
$html .= '<td width="5%" style="line-height:14px;font-size:11px;text-align:center;">:</td>';
$html .= '<td width="50%" style="line-height:14px;font-size:11px;text-align:left;">' . $result['patient_address'] . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="20%" style="line-height:14px;font-size:11px;text-align:left;font-weight:bold;">Commune</td>';
$html .= '<td width="5%" style="line-height:14px;font-size:11px;text-align:center;">:</td>';
$html .= '<td width="50%" style="line-height:14px;font-size:11px;text-align:left;"></td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="20%" style="line-height:14px;font-size:11px;text-align:left;font-weight:bold;">Téléphone</td>';
$html .= '<td width="5%" style="line-height:14px;font-size:11px;text-align:center;">:</td>';
$html .= '<td width="50%" style="line-height:14px;font-size:11px;text-align:left;">' . $result['patient_phone_number'] . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="20%" style="line-height:14px;font-size:11px;text-align:left;font-weight:bold;">Type de Cas</td>';
$html .= '<td width="5%" style="line-height:14px;font-size:11px;text-align:center;">:</td>';
$html .= '<td width="50%" style="line-height:14px;font-size:11px;text-align:left;">' . $result['reasonForTesting'] . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="20%" style="line-height:14px;font-size:11px;text-align:left;font-weight:bold;">Structure Sanitaire</td>';
$html .= '<td width="5%" style="line-height:14px;font-size:11px;text-align:center;">:</td>';
$html .= '<td width="50%" style="line-height:14px;font-size:11px;text-align:left;">' . ucwords($result['facility_name']) . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="20%" style="line-height:14px;font-size:11px;text-align:left;font-weight:bold;">Date de Prélévement</td>';
$html .= '<td width="5%" style="line-height:14px;font-size:11px;text-align:center;">:</td>';
$html .= '<td width="50%" style="line-height:14px;font-size:11px;text-align:left;">' . $result['sample_collection_date'] . " " . $sampleCollectionTime . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="20%" style="line-height:14px;font-size:11px;text-align:left;font-weight:bold;">Date de Réception</td>';
$html .= '<td width="5%" style="line-height:14px;font-size:11px;text-align:center;">:</td>';
$html .= '<td width="50%" style="line-height:14px;font-size:11px;text-align:left;">' . $sampleReceivedDate . " " . $sampleReceivedTime . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="50%" style="line-height:14px;font-size:11px;text-align:left;" colspan="2"><b>Resultats SARS-CoV-2 &nbsp;&nbsp;:</b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $covid19Results[$result['result']] . '</td>';
$html .= '<td width="50%" style="line-height:14px;font-size:11px;text-align:left;"><b>Date de Sortie Résultats &nbsp;&nbsp;:</b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $printDate . '&nbsp;&nbsp;' . $printDateTime . '</td>';
$html .= '</tr>';

if (isset($covid19TestInfo) && count($covid19TestInfo) > 0 && $arr['covid19_tests_table_in_results_pdf'] == 'yes') {
    $html .= '<tr>';
    $html .= '<td style="line-height:14px;font-size:12px;text-align:left;" colspan="3"><b>Tests de Controle :</b></td>';
    $html .= '</tr>';

    foreach ($covid19TestInfo as $indexKey => $rows) {
        $html .= '<tr>';
        $html .= '<td width="50%" style="line-height:14px;font-size:11px;text-align:left;" colspan="2"><b>Resultats ' . ($indexKey + 1) . 'éme Prélévement &nbsp;&nbsp;:</b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . ucwords($covid19TestInfo[$indexKey]['result']) . '</td>';
        $html .= '<td width="50%" style="line-height:14px;font-size:11px;text-align:left;"><b>Date de Sortie Résultats &nbsp;&nbsp;:</b>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $general->humanDateFormat($covid19TestInfo[$indexKey]['sample_tested_datetime']) . '</td>';
        $html .= '</tr>';
    }
}
$html .= '<tr>';
$html .= '<td width="100%" style="line-height:14px;font-size:11px;text-align:center;" colspan="3"><b>Fait a Kinshasa, le :</b>' . $general->humanDateFormat($result['result_approved_datetime']) . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td colspan="3">';
if (isset($signResults) && !empty($signResults)) {
    $html .= '<table style="width:100%;padding:3px;border:1px solid gray;">';
    $html .= '<tr>';
    $html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid gray;">AUTORISÉ PAR</td>';
    $html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">IMPRIMER LE NOM</td>';
    $html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">SIGNATURE</td>';
    $html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">DATE & HEURE</td>';
    $html .= '</tr>';
    foreach ($signResults as $key => $row) {
        $lmSign = "/uploads/labs/" . $row['lab_id'] . "/signatures/" . $row['signature'];
        $html .= '<tr>';
        $html .= '<td style="line-height:17px;font-size:11px;text-align:left;font-weight:bold;border-bottom:1px solid gray;">' . $row['designation'] . '</td>';
        $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">' . $row['name_of_signatory'] . '</td>';
        $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;"><img src="' . $lmSign . '" style="width:30px;"></td>';
        $html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">' . date('d-M-Y H:i:s a') . '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';
}
$html .= '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="100%" style="line-height:20px;border-bottom:2px solid #d3d3d3;" colspan="3"></td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="100%" style="line-height:14px;font-size:11px;text-align:left;color:#545252;" colspan="3">' . $sampleDispatchDate . ' ' . $sampleDispatchTime . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Department de virologie</td>';
$html .= '</tr>';
$html .= '</table>';
$html .= '</td></tr></table>';

if ($result['result'] != '' || ($result['result'] == '' && $result['result_status'] == '4')) {
    $ciphering = "AES-128-CTR";
    $iv_length = openssl_cipher_iv_length($ciphering);
    $options = 0;
    $simple_string = $result['covid19_id'] . "&&&qr";
    $encryption_iv = $systemConfig['tryCrypt'];
    $encryption_key = $systemConfig['tryCrypt'];
    $Cid = openssl_encrypt(
        $simple_string,
        $ciphering,
        $encryption_key,
        $options,
        $encryption_iv
    );
    $pdf->writeHTML($html);
    $systemConfig['remoteURL'] = rtrim($systemConfig['remoteURL'], "/");
    if (isset($arr['covid19_report_qr_code']) && $arr['covid19_report_qr_code'] == 'yes') {
        $pdf->write2DBarcode($systemConfig['remoteURL'] . '/covid-19/results/view.php?q=' . $Cid . '', 'QRCODE,H', 170, 60, 100, 100, $style, 'N');
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
