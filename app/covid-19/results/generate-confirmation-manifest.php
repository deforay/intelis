<?php


use App\Models\General;
use App\Utilities\DateUtils;

$general = new General();
$id = base64_decode($_GET['id']);
// die($id);
// Extend the TCPDF class to create custom Header and Footer
class MYPDF extends TCPDF
{
    public $logo;
    public $text;
    public $labname;

    public function setHeading($logo, $text, $labname)
    {
        $this->logo = $logo;
        $this->text = $text;
        $this->labname = $labname;
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
        if (trim($this->logo) != "") {
            if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                $this->Image($imageFilePath, 15, 10, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }
        }
        $this->SetFont('helvetica', '', 7);
        $this->writeHTMLCell(30, 0, 10, 26, $this->text, 0, 0, 0, true, 'A', true);
        $this->SetFont('helvetica', '', 13);
        $this->writeHTMLCell(0, 0, 0, 10, 'Covid-19 Positive Confirmation Manifest ', 0, 0, 0, true, 'C', true);
        $this->SetFont('helvetica', '', 10);
        $this->writeHTMLCell(0, 0, 0, 20, $this->labname, 0, 0, 0, true, 'C', true);

        if (trim($this->logo) != "") {
            if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                $this->Image($imageFilePath, 262, 10, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }
        }
        $this->SetFont('helvetica', '', 7);
        $this->writeHTMLCell(30, 0, 255, 26, $this->text, 0, 0, 0, true, 'A', true);
        $html = '<hr/>';
        $this->writeHTMLCell(0, 0, 10, 32, $html, 0, 0, 0, true, 'J', true);
    }

    // Page footer
    public function Footer()
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', '', 8);
        // Page number
        $this->Cell(0, 10,  'Positive Confirmation Manifest Generated On : ' . date('d/m/Y H:i:s') . ' | Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}




if (trim($id) != '') {

    $sQuery = "SELECT sample_code,remote_sample_code,fd.facility_name as clinic_name,fd.facility_district,CONCAT(patient_name,patient_surname) as `patient_fullname`,patient_dob,patient_age,sample_collection_date,patient_gender,patient_id,cpcm.manifest_code, l.facility_name as lab_name from covid19_positive_confirmation_manifest as cpcm Join form_covid19 as vl ON vl.positive_test_manifest_id=cpcm.manifest_id Join facility_details as fd ON fd.facility_id=vl.facility_id Join facility_details as l ON l.facility_id=vl.lab_id where cpcm.manifest_code LIKE '%$id%'";
    // die($sQuery);
    $result = $db->query($sQuery);

    $labname = isset($result[0]['lab_name']) ? $result[0]['lab_name'] : "";

    $configQuery = "SELECT * from global_config";
    $configResult = $db->query($configQuery);
    $arr = array();
    // now we create an associative array so that we can easily create view variables
    for ($i = 0; $i < sizeof($configResult); $i++) {
        $arr[$configResult[$i]['name']] = $configResult[$i]['value'];
    }
    $bQuery = "SELECT * from covid19_positive_confirmation_manifest as cpcm where manifest_code LIKE '%$id%'";
    //echo $bQuery;die;
    $bResult = $db->query($bQuery);
    if (!empty($bResult)) {


        // create new PDF document
        $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->setHeading($arr['logo'], $arr['header'], $labname);

        // set document information
        $pdf->SetCreator('VLSTS');
        $pdf->SetAuthor('VLSTS');
        $pdf->SetTitle('Positive Confirmation Manifest');
        $pdf->SetSubject('Positive Confirmation Manifest');
        $pdf->SetKeywords('Positive Confirmation Manifest');

        // set default header data
        $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

        // set header and footer fonts
        $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

        // set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

        // set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, 36, PDF_MARGIN_RIGHT);
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

        // set font
        $pdf->SetFont('helvetica', '', 10);
        $pdf->setPageOrientation('L');
        // add a page
        $pdf->AddPage();
        $tbl = '';
        $packageCodeBarCode = $pdf->serializeTCPDFtagParameters(array($result[0]['manifest_code'], 'C39', '', '', 0, 8, 0.25, array('border' => false, 'align' => 'L', 'padding' => 0, 'fgcolor' => array(0, 0, 0), 'bgcolor' => array(255, 255, 255), 'text' => false, 'font' => 'helvetica', 'fontsize' => 8, 'stretchtext' => 2), 'N'));
        //$tbl .= '<h1> ' . $result[0]['manifest_code'] . '<tcpdf method="write1DBarcode" params="' . $packageCodeBarCode . '" /> </h1>';
        $tbl .= '<span style="font-size:1.7em;"> ' . $result[0]['manifest_code'] . ' <tcpdf method="write1DBarcode" params="' . $packageCodeBarCode . '" /> </span>';
        $tbl .= '<br>';
        $tbl .= '<table style="width:100%;border:1px solid #333;">
            
                <tr nobr="true">
                    <td align="center" style="font-size:11px;width:3%;border:1px solid #333;" ><strong><em>S. No.</em></strong></td>
                    <td align="center" style="font-size:11px;width:12%;border:1px solid #333;"  ><strong><em>SAMPLE ID</em></strong></td>
                    <td align="center" style="font-size:11px;width:14%;border:1px solid #333;"  ><strong><em>Health facility, District</em></strong></td>
                    <td align="center" style="font-size:11px;width:11%;border:1px solid #333;"  ><strong><em>Patient Name</em></strong></td>
                    <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Patient ID</em></strong></td>
                    <td align="center" style="font-size:11px;width:8%;border:1px solid #333;"  ><strong><em>Date of Birth</em></strong></td>
                    <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><em>Patient Gender</em></strong></td>
                    <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Sample Collection Date</em></strong></td>
                    <!-- <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><em>Test Requested</em></strong></td> -->
                    <td align="center" style="font-size:11px;width:22%;border:1px solid #333;"  ><strong><em>Sample Barcode</em></strong></td>
                </tr>';

        $sampleCounter = 1;

        foreach ($result as $sample) {
            //var_dump($sample);die;
            $collectionDate = '';
            if (isset($sample['sample_collection_date']) && $sample['sample_collection_date'] != '' && $sample['sample_collection_date'] != null && $sample['sample_collection_date'] != '0000-00-00 00:00:00') {
                $cDate = explode(" ", $sample['sample_collection_date']);
                $collectionDate = DateUtils::humanReadableDateFormat($cDate[0]) . " " . $cDate[1];
            }
            $patientDOB = '';
            if (isset($sample['patient_dob']) && $sample['patient_dob'] != '' && $sample['patient_dob'] != null && $sample['patient_dob'] != '0000-00-00') {
                $patientDOB = DateUtils::humanReadableDateFormat($sample['patient_dob']);
            }
            $params = $pdf->serializeTCPDFtagParameters(array($sample['sample_code'], 'C39', '', '', '', 9, 0.25, array('border' => false, 'align' => 'C', 'padding' => 1, 'fgcolor' => array(0, 0, 0), 'bgcolor' => array(255, 255, 255), 'text' => false, 'font' => 'helvetica', 'fontsize' => 10, 'stretchtext' => 2), 'N'));
            //$tbl.='<table cellspacing="0" cellpadding="3" style="width:100%">';
            $tbl .= '<tr style="border:1px solid #333;">';
            $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $sampleCounter . '.</td>';
            $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $sample['sample_code'] . '</td>';
            // $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['facility_district']) . '</td>';
            $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['clinic_name']) . ', ' . $sample['facility_district'] . '</td>';
            $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['patient_fullname']) . '</td>';
            $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $sample['patient_id'] . '</td>';
            $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $patientDOB . '</td>';
            $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . (str_replace("_", " ", $sample['patient_gender'])) . '</td>';
            $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $collectionDate . '</td>';
            // $tbl.='<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">VIRAL</td>';
            $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;"><br><tcpdf method="write1DBarcode" params="' . $params . '" /></td>';
            $tbl .= '</tr>';
            //$tbl .='</table>';
            $sampleCounter++;
        }
        $tbl .= '</table>';

        $tbl .= '<br><br><br><br><table cellspacing="0" style="width:100%;">';
        $tbl .= '<tr >';
        $tbl .= '<td align="right" style="vertical-align:middle;font-size:11px;width:15%;"><strong>Generated By : </strong></td><td align="left" style="width:18.33%;"><span style="font-size:12px;">' . $_SESSION['userName'] . '</span></td>';
        $tbl .= '<td align="right" style="vertical-align:middle;font-size:11px;width:15%;"><strong>Verified By :  </strong></td><td style="width:18.33%;"></td>';
        $tbl .= '<td align="right" style="vertical-align:middle;font-size:11px;width:15%;"><strong>Received By : <br>(at Referral lab/NRL)</strong></td><td style="width:18.33%;"></td>';
        $tbl .= '</tr>';
        $tbl .= '</table>';
        //$tbl.='<br/><br/><strong style="text-align:left;">Printed On:  </strong>'.date('d/m/Y H:i:s');
        // $pdf->writeHTMLCell('', '', 11, $pdf->getY(), $tbl, 0, 1, 0, true, 'C', true);
        $filename = trim($bResult[0]['manifest_code']) . '-' . date('Y-m-d') . '-Manifest.pdf';
        $pdf->writeHTML($tbl);
        $pdf->Output($filename, "I");
        exit;
    }
}
