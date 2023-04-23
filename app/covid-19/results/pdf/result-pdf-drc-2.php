<?php

use App\Services\UserService;
use App\Utilities\DateUtils;

class DRC_PDF extends MYPDF
{
    //Page header
    public function Header()
    {
        // Logo
        if ($this->htitle != '') {
            if (trim($this->logo) != '') {
                if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                    $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                    $this->Image($imageFilePath, 10, 5, 25, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
                }
            }
            if (isset($this->facilityInfo) && count($this->facilityInfo) > 0 && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->facilityInfo['facility_id'] . DIRECTORY_SEPARATOR . $this->facilityInfo['facility_logo'])) {
                $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->facilityInfo['facility_id'] . DIRECTORY_SEPARATOR . $this->facilityInfo['facility_logo'];
                $this->Image($imageFilePath, 175, 5, 25, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }
        }
        $this->SetFont('helvetica', 'B', 12);
        $this->writeHTMLCell(0, 0, 12, 5, 'REPUBLIQUE DEMOCRATIQUE DU CONGO', 0, 0, false, true, 'C', true);
        $this->SetFont('helvetica', 'B', 10);
        $this->writeHTMLCell(0, 0, 12, 10, $this->text, 0, 0, false, true, 'C', true);
        if (trim($this->lab) != '') {
            $this->SetFont('helvetica', 'B', 11);
            $this->writeHTMLCell(0, 0, 12, 15, strtoupper($this->lab), 0, 0, false, true, 'C', true);
        }
        $this->SetFont('helvetica', 'B', 11);
        $this->writeHTMLCell(0, 0, 12, 20, 'Province du Nord-Kivu', 0, 0, false, true, 'C', true);
        $this->writeHTMLCell(0, 0, 12, 25, 'Laboratoire P3/P2/P2 Rodolphe Merleux INRB-COMA', 0, 0, false, true, 'C', true);
        $this->writeHTMLCell(0, 0, 12, 30, 'Laboratoire Réglonal de Santé Publique', 0, 0, false, true, 'C', true);
        $this->SetTextColor(255, 0, 0);
        $this->writeHTMLCell(0, 0, 12, 35, 'Membre du réseau GABRIEL', 0, 0, false, true, 'C', true);
        $this->SetTextColor(0, 0, 0);
        $this->writeHTMLCell(0, 0, 12, 40, '<hr>', 0, 0, false, true, 'C', true);
        $this->writeHTMLCell(0, 0, 12, 41, '<hr>', 0, 0, false, true, 'C', true);

        // Define the path to the image that you want to use as watermark.
        $img_file = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->facilityInfo['facility_id'] . DIRECTORY_SEPARATOR . $this->logo;
        if (!empty($this->logo) && file_exists($img_file)) {
        } else if (!empty($this->logo) && UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo) {
            if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                $img_file = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
            }
        } else {
            $img_file = "";
        }
        // Render the image
        if ($img_file != "") {
            $this->SetAlpha(0.1);
            $this->Image($img_file, 55, 60, 100, null, '', '', '', false, 300, 'M', false, false, 0);
        }
        /* $stamp = "";
        if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->facilityInfo['facility_id'] . DIRECTORY_SEPARATOR . 'stamps' . DIRECTORY_SEPARATOR . 'stamp-1.png')) {
            $stamp = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->facilityInfo['facility_id'] . DIRECTORY_SEPARATOR . 'stamps' . DIRECTORY_SEPARATOR . 'stamp-1.png';
        }
        if ($stamp != "") {
            $this->SetAlpha(0.6);
            $this->Image($stamp, 40, 125, 50, null, '', '', '', false, 300, '', false, false, 0);
            $this->Image($stamp, 120, 125, 50, null, '', '', '', false, 300, '', false, false, 0);
        } */
    }
    // Page footer
    public function Footer()
    {
        $this->writeHTML("<hr>");
        // Set font
        $this->SetFont('helvetica', 'I', 8);
        if ($this->systemConfig['sc_user_type'] == 'vluser' && $this->dataSync == 0) {
            $generatedAtTestingLab = " | " . _("Report generated at Testing Lab");
        } else {
            $generatedAtTestingLab = "";
        }

        $formatter = new IntlDateFormatter('fr_FR', IntlDateFormatter::LONG, IntlDateFormatter::FULL, $_SESSION['APP_TIMEZONE'],IntlDateFormatter::GREGORIAN, "EEEE dd MMMM, Y");
        $this->writeHTMLCell(0, 0, 10, 290, $formatter->format(strtotime($this->resultPrintedDate)) . ' ' . $generatedAtTestingLab, 0, 0, false, true, 'L', true);
        $this->writeHTMLCell(0, 0, 10, 280, 'N 29 Av des Orchidees O. le volcan C. de Goma Tel: +243 817933409 +234 993549796', 0, 0, false, true, 'C', true);
        $this->writeHTMLCell(0, 0, 10, 285, 'E-mail : info@inrbgoma.com, inrbgoma@gmail.com', 0, 0, false, true, 'C', true);
        $this->writeHTMLCell(0, 0, 10, 290, 'inrbgoma.com', 0, 0, false, true, 'C', true);
    }
}

$users = new UserService();

// create new PDF document
$pdf = new DRC_PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->setHeading($arr['logo'], $arr['header'], $result['labName'], $title = 'COVID-19 PATIENT REPORT', null, 3, $labInfo, $currentDateTime, $result['dataSync'], $systemConfig);
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
$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP + 15, PDF_MARGIN_RIGHT);
$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
$pdf->SetFooterMargin(20);

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
$resultPrintedDate = '';
$resultPrintedTime = '';
if (isset($result['result_printed_datetime']) && trim($result['result_printed_datetime']) != '' && $result['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
    $expStr = explode(" ", $result['result_printed_datetime']);
    $resultPrintedDate = DateUtils::humanReadableDateFormat($expStr[0]);
    $resultPrintedTime = $expStr[1];
} else {
    $expStr = explode(" ", $currentDateTime);
    $resultPrintedDate = DateUtils::humanReadableDateFormat($expStr[0]);
    $resultPrintedTime = $expStr[1];
}

if (isset($result['sample_tested_datetime']) && trim($result['sample_tested_datetime']) != '' && $result['sample_tested_datetime'] != '0000-00-00 00:00:00') {
    $expStr = explode(" ", $result['sample_tested_datetime']);
    $result['sample_tested_datetime'] = DateUtils::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
    $result['sample_tested_datetime'] = '';
}

if (!isset($result['patient_gender']) || trim($result['patient_gender']) == '') {
    $result['patient_gender'] = 'not reported';
}

$userRes = [];
if (isset($result['approvedBy']) && trim($result['approvedBy']) != '') {
    $resultApprovedBy = ($result['approvedBy']);
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
if ($result['result'] != null && trim($result['result']) != '') {
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
$html = '<table style="padding:0px 2px 2px 2px;">';
$html .= '<tr>';
$html .= '<td style="line-height:14px;font-size:12px;text-align:center;font-weight:bold;color:red;">SERVICE COVID-VOYAGE</td>';
$html .= '</tr>';
$html .= '<tr>';
$html .= '<td style="line-height:14px;font-size:12px;text-align:center;font-weight:bold;">A l&lsquo;entrée du pays, le test Covid-19 est obligatoire et fixé à 45$. Morci de ne pas oublier<br> When entering the country, the Covid-19 test is mandatory and costs 45$. Please do not forget</td>';
$html .= '</tr>';
$html .= '<tr>';
$html .= '<td>';
$html .= '<table style="padding:10px;">';

$html .= '<tr>';
$html .= '<td colspan="2" style="line-height:14px;font-size:12px;text-align:left;font-weight:bold;">INFORMATION SUR LE VOYAGEUR<br><span style="font-size:10px;font-weight:normal;">TRAVELLERS INFORMATION</span></td>';
$html .= '<td style="line-height:14px;font-size:12px;text-align:left;font-weight:bold;">ID LABO : <u>' . ($result['labName']) . '</u> /21<br><span style="font-size:10px;font-weight:normal;">LAB ID</span></td>';
$html .= '</tr>';

$patientFname = ($general->crypto('doNothing', $result['patient_name'], $result['patient_id']));
$patientLname = ($general->crypto('doNothing', $result['patient_surname'], $result['patient_id']));
$html .= '<tr>';
$html .= '<td width="40%" style="line-height:14px;font-size:12px;text-align:left;font-weight:bold;">Noms<br><span style="font-size:10px;font-weight:normal;">Full Name</span></td>';
$html .= '<td width="5%" style="line-height:14px;font-size:12px;text-align:center;">:</td>';
$html .= '<td width="55%" style="line-height:14px;font-size:12px;text-align:left;">' . $patientLname . ' ' . $patientFname . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="40%" style="line-height:14px;font-size:12px;text-align:left;font-weight:bold;">Age<br><span style="font-size:10px;font-weight:normal;">Age</span></td>';
$html .= '<td width="5%" style="line-height:14px;font-size:12px;text-align:center;">:</td>';
$html .= '<td width="55%" style="line-height:14px;font-size:12px;text-align:left;">' . $age . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="40%" style="line-height:14px;font-size:12px;text-align:left;font-weight:bold;">Sexe<br><span style="font-size:10px;font-weight:normal;">Gender</span></td>';
$html .= '<td width="5%" style="line-height:14px;font-size:12px;text-align:center;">:</td>';
$html .= '<td width="55%" style="line-height:14px;font-size:12px;text-align:left;">' . (str_replace("_", " ", $result['patient_gender'])) . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="40%" style="line-height:14px;font-size:12px;text-align:left;font-weight:bold;">Pays<br><span style="font-size:10px;font-weight:normal;">Country</span></td>';
$html .= '<td width="5%" style="line-height:14px;font-size:12px;text-align:center;">:</td>';
$html .= '<td width="55%" style="line-height:14px;font-size:12px;text-align:left;">' . $result['patient_nationality'] . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="40%" style="line-height:14px;font-size:12px;text-align:left;font-weight:bold;">Ville<br><span style="font-size:10px;font-weight:normal;">City</span></td>';
$html .= '<td width="5%" style="line-height:14px;font-size:12px;text-align:center;">:</td>';
$html .= '<td width="55%" style="line-height:14px;font-size:12px;text-align:left;">' . ($result['patient_city']) . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="40%" style="line-height:14px;font-size:12px;text-align:left;font-weight:bold;">Adresse<br><span style="font-size:10px;font-weight:normal;">Address</span></td>';
$html .= '<td width="5%" style="line-height:14px;font-size:12px;text-align:center;">:</td>';
$html .= '<td width="55%" style="line-height:14px;font-size:12px;text-align:left;">' . $result['patient_address'] . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="40%" style="line-height:14px;font-size:12px;text-align:left;font-weight:bold;">N Passeport<br><span style="font-size:10px;font-weight:normal;">Passport N</span></td>';
$html .= '<td width="5%" style="line-height:14px;font-size:12px;text-align:center;">:</td>';
$html .= '<td width="20%" style="line-height:14px;font-size:12px;text-align:left;">' . $result['patient_passport_number'] . '</td>';
$html .= '<td width="20%" style="line-height:14px;font-size:12px;text-align:left;font-weight:bold;">Téléphone<br><span style="font-size:10px;font-weight:normal;">Telephone Number</span></td>';
$html .= '<td width="5%" style="line-height:14px;font-size:12px;text-align:center;">:</td>';
$html .= '<td width="15%" style="line-height:14px;font-size:12px;text-align:left;">' . $result['patient_phone_number'] . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="40%" style="line-height:14px;font-size:12px;text-align:left;font-weight:bold;">Date & Heure de Prélevement<br><span style="font-size:10px;font-weight:normal;">Sample collection date & time</span></td>';
$html .= '<td width="5%" style="line-height:14px;font-size:12px;text-align:center;">:</td>';
$html .= '<td width="55%" style="line-height:14px;font-size:12px;text-align:left;">' . $result['sample_collection_date'] . ' ' . $sampleCollectionTime . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="40%" style="line-height:14px;font-size:12px;text-align:left;font-weight:bold;">Date & Heure d analyse<br><span style="font-size:10px;font-weight:normal;">Sample Testing date & time</span></td>';
$html .= '<td width="5%" style="line-height:14px;font-size:12px;text-align:center;">:</td>';
$html .= '<td width="55%" style="line-height:14px;font-size:12px;text-align:left;">' . $result['sample_tested_datetime'] . '</td>';
$html .= '</tr>';

$html .= '<tr>';
$html .= '<td width="40%" style="line-height:14px;font-size:15px;text-align:left;font-weight:bold;">Résultats RT-PCR SARS CoV-2<br><span style="font-size:10px;font-weight:normal;">Results of RT-PCR SARS Cov-2</span></td>';
$html .= '<td width="5%" style="line-height:14px;font-size:15px;text-align:center;">:</td>';
$html .= '<td width="55%" style="line-height:14px;font-size:15px;text-align:left;">' . $covid19Results[$result['result']] . '</td>';
$html .= '</tr>';
if (isset($covid19TestInfo) && count($covid19TestInfo) > 0 && $arr['covid19_tests_table_in_results_pdf'] == 'yes') {
    $html .= '<tr>';
    $html .= '<td style="line-height:14px;font-size:12px;text-align:left;" colspan="3"><strong>Tests de Controle :</strong></td>';
    $html .= '</tr>';

    foreach ($covid19TestInfo as $indexKey => $rows) {
        $html .= '<tr>';
        $html .= '<td width="55%" style="line-height:14px;font-size:12px;text-align:left;" colspan="2"><strong>Resultats ' . ($indexKey + 1) . 'éme Prélévement &nbsp;&nbsp;:</strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . ($rows['result']) . '</td>';
        $html .= '<td width="55%" style="line-height:14px;font-size:12px;text-align:left;"><strong>Date de Sortie Résultats &nbsp;&nbsp;:</strong>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . DateUtils::humanReadableDateFormat($rows['sample_tested_datetime']) . '</td>';
        $html .= '</tr>';
    }
}
$html .= '<tr>';
$html .= '<td width="100%" style="line-height:14px;font-size:12px;text-align:center;" colspan="3"><strong>Fait à Goma, le :</strong>' . DateUtils::humanReadableDateFormat($result['result_approved_datetime']) . '</td>';
$html .= '</tr>';



$labManagerRes = $users->getUserInfo($result['lab_manager'], 'user_name');
if ($labManagerRes) {
    $labManager = $labManagerRes['user_name'];
} else {
    $labManager = "";
}

$html .= '<tr>';
$html .= '<td colspan="3" style="line-height:14px;font-size:12px;text-align:center;"><br><br><strong>' . $labManager . '</strong><br>Médecin Virologue<br><span style="font-size:10px;font-weight:normal;">Medical Virologist</span></td>';
$html .= '</tr>';
$html .= '<tr>';
$html .= '<td colspan="3" style="line-height:14px;font-size:12px;text-align:center;"><br><br><br><br><br><br>
Validité: 14 jours en DRC, 3 jours à l&lsquo;étranger<br><span style="font-size:10px;font-weight:normal;">Validity: 14 days inside DRC, 3 days abroad<br>Réf: Arrété Min. N 1250/CAB/MIN/SPHP/32/DC/GSK/2021 du 26 Aout 2021</span></td>';
$html .= '</tr>';
$html .= '</table>';
$html .= '</td></tr></table>';

if ($result['result'] != '' || ($result['result'] == '' && $result['result_status'] == '4')) {
    $ciphering = "AES-128-CTR";
    $iv_length = openssl_cipher_iv_length($ciphering);
    $options = 0;
    $simple_string = $result['covid19_id'] . "&&&qr";
    $encryption_iv = SYSTEM_CONFIG['tryCrypt'];
    $encryption_key = SYSTEM_CONFIG['tryCrypt'];
    $Cid = openssl_encrypt(
        $simple_string,
        $ciphering,
        $encryption_key,
        $options,
        $encryption_iv
    );
    $pdf->writeHTML($html);
    if (isset($arr['covid19_report_qr_code']) && $arr['covid19_report_qr_code'] == 'yes' && !empty(SYSTEM_CONFIG['remoteURL'])) {
        $remoteUrl = rtrim(SYSTEM_CONFIG['remoteURL'], "/");
        $pdf->write2DBarcode($remoteUrl . '/covid-19/results/view.php?q=' . $Cid, 'QRCODE,H', 20, 235, 30, 30, $style, 'N');
        $pdf->writeHTML('<span style="font-size:12px;font-weight:normal;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;scan me</span>');
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
    $action = ($_SESSION['userName'] ?: 'System') . ' generated the test result PDF with Patient ID/Code ' . $result['patient_id'];
    $resource = 'print-test-result';
    $data = array(
        'event_type' => $eventType,
        'action' => $action,
        'resource' => $resource,
        'date_time' => $currentDateTime
    );
    $db->insert($tableName1, $data);
    //Update print datetime in VL tbl.
    $vlQuery = "SELECT result_printed_datetime FROM form_covid19 as vl WHERE vl.covid19_id ='" . $result['covid19_id'] . "'";
    $vlResult = $db->query($vlQuery);
    if ($vlResult[0]['result_printed_datetime'] == null || trim($vlResult[0]['result_printed_datetime']) == '' || $vlResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
        $db = $db->where('covid19_id', $result['covid19_id']);
        $db->update($tableName2, array('result_printed_datetime' => $currentDateTime, 'result_dispatched_datetime' => $currentDateTime));
    }
}
