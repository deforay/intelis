<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$id = base64_decode($_POST['id']);
if (isset($_POST['frmSrc']) && trim($_POST['frmSrc']) == 'pk2') {
    $id = $_POST['ids'];
}

// Extend the TCPDF class to create custom Header and Footer
class MYPDF extends TCPDF
{
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
                $this->Image($imageFilePath, 15, 10, 15, '', '', '', 'T');
            }
        }
        $this->SetFont('helvetica', '', 7);
        $this->writeHTMLCell(30, 0, 10, 26, $this->text, 0, 0, 0, true, 'A');
        $this->SetFont('helvetica', '', 13);
        $this->writeHTMLCell(0, 0, 0, 10, 'EID Sample Referral Manifest ', 0, 0, 0, true, 'C');
        $this->SetFont('helvetica', '', 10);
        $this->writeHTMLCell(0, 0, 0, 20, $this->labname, 0, 0, 0, true, 'C');

        if (trim($this->logo) != "") {
            if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
                $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
                $this->Image($imageFilePath, 262, 10, 15, '', '', '', 'T');
            }
        }
        $this->SetFont('helvetica', '', 7);
        $this->writeHTMLCell(30, 0, 255, 26, $this->text, 0, 0, 0, true, 'A');
        $html = '<hr/>';
        $this->writeHTMLCell(0, 0, 10, 32, $html, 0, 0, 0, true, 'J');
    }

    // Page footer
    public function Footer()
    {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', '', 8);
        // Page number
        $this->Cell(0, 10,  'Specimen Manifest Generated On : ' . date('d/m/Y H:i:s') . ' | Page ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, false, 'C', 0);
    }
}




if (trim($id) != '') {

    $sQuery = "SELECT remote_sample_code,pd.number_of_samples,fd.facility_name as clinic_name,fd.facility_district,child_name,child_dob,child_age,mother_name,sample_collection_date,child_gender,child_id,pd.package_code, l.facility_name as lab_name, u_d.user_name as releaser_name,
                u_d.phone_number as phone,u_d.email as email,DATE_FORMAT(pd.request_created_datetime,'%d-%b-%Y') as created_date
                from package_details as pd Join form_eid as vl ON vl.sample_package_id=pd.package_id 
                Join facility_details as fd ON fd.facility_id=vl.facility_id 
                Join facility_details as l ON l.facility_id=vl.lab_id 
                LEFT JOIN user_details as u_d ON u_d.user_id=pd.added_by 
                where pd.package_id IN($id)";
    $result = $db->query($sQuery);


    $labname = $result[0]['lab_name'] ?? "";

    if (!file_exists(TEMP_PATH . DIRECTORY_SEPARATOR . "sample-manifests") && !is_dir(TEMP_PATH . DIRECTORY_SEPARATOR . "sample-manifests")) {
        mkdir(TEMP_PATH . DIRECTORY_SEPARATOR . "sample-manifests", 0777, true);
    }
    $configQuery = "SELECT * from global_config";
    $configResult = $db->query($configQuery);
    $arr = [];
    // now we create an associative array so that we can easily create view variables
    for ($i = 0; $i < sizeof($configResult); $i++) {
        $arr[$configResult[$i]['name']] = $configResult[$i]['value'];
    }
    $showPatientName = $arr['eid_show_participant_name_in_manifest'];

    $bQuery = "SELECT * from package_details as pd where package_id IN($id)";
    //echo $bQuery;die;
    $bResult = $db->query($bQuery);
    if (!empty($bResult)) {


        // create new PDF document
        $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        $pdf->setHeading($arr['logo'], $arr['header'], $labname);

        // set document information
        $pdf->SetCreator('VLSTS');
        $pdf->SetAuthor('VLSTS');
        $pdf->SetTitle('Specimen Referral Manifest');
        $pdf->SetSubject('Specimen Referral Manifest');
        $pdf->SetKeywords('Specimen Referral Manifest');

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


        if($arr['vl_form']==2)
        {
            //$pdf->writeHTMLCell(0, 20, 10, 10, 'FACILITY RELEASER INFORMATION ', 0, 0, 0, true, 'C', true);
            $pdf->WriteHTML('<b>FACILITY RELEASER INFORMATION</b>');
    
            $tbl1 = '<br>';
            $tbl1.='<table nobr="true" style="width:100%;" border="0" cellpadding="2">';
            $tbl1.='<tr>
            <td align="left"> Releaser Name :  '.$result[0]['releaser_name'].'</td>
            <td align="left"> Date :  '.$result[0]['created_date'].'</td>     
            </tr>
            <tr>
            <td align="left"> Phone No. :  '.$result[0]['phone'].'</td>
            <td align="left"> Email :  '.$result[0]['email'].'</td>
            </tr>
            <tr>
            <td align="left"> Facility Name :  '.$result[0]['clinic_name'].'</td>
            <td align="left"> District :  '.$result[0]['facility_district'].'</td>     
            </tr>';
            $tbl1.='</table>';
            $pdf->writeHTMLCell('', '', 11, $pdf->getY(), $tbl1, 0, 1, 0, true, 'C');
    
            $pdf->WriteHTML('<p></p><b>SPECIMEN PACKAGING</b>');
    
            $tbl2 = '<br>';
            $tbl2.='<table nobr="true" style="width:100%;" border="0" cellpadding="2">';
            $tbl2.='<tr>
            <td align="left"> Number of specimen included :  '.$result[0]['number_of_samples'].'</td>
            <td align="left"> Forms completed and included :  Yes / No</td>     
            </tr>
            <tr>
            <td align="left"> Packaged By :  ..................</td>
            <td align="left"> Date :  ...................</td>
            </tr>';
            $tbl2.='</table>';
    
            $pdf->writeHTMLCell('', '', 11, $pdf->getY(), $tbl2, 0, 1, 0, true, 'C');
    
            $pdf->WriteHTML('<p></p><b>CHAIN OF CUSTODY : </b>(persons relinquishing and receiving specimen fill their respective sections)');
            $pdf->WriteHTML('<p></p><b>To be completed at facility in the presence of specimen courier</b>');
            $tbl3 = '<br>';
            $tbl3 .= '<table border="1">
            <tr>
                <td colspan="2">Relinquished By (Laboratory)</td>
                <td colspan="2">Received By (Courier)</td>
            </tr>
            <tr>
                <td align="left"> Name : <br><br> Sign : <br><br> Phone No. :</td>
                <td align="left"> Date : <br><p></p><br> Time :</td>
                <td align="left"> Name : <br><br> Sign : <br><br> Phone No. :</td>
                <td align="left"> Date : <br><p></p><br> Time :</td>
            </tr>
            </table>';
            $pdf->writeHTMLCell('', '', 11, $pdf->getY(), $tbl3, 0, 1, 0, true, 'C');
    
            $pdf->WriteHTML('<p></p><b>To be completed at testing laboratory by specimen reception personnel</b>');
                $tbl4 = '<br>';
                $tbl4 .= '<table border="1">
                <tr>
                    <td colspan="2">Relinquished By (Courier)</td>
                    <td colspan="2">Received By (Laboratory)</td>
                </tr>
                <tr>
                    <td align="left"> Name : <br><br> Sign : <br><br> Phone No. :</td>
                    <td align="left"> Date : <br><p></p><br> Time :</td>
                    <td align="left"> Name : <br><br> Sign : <br><br> Phone No. :</td>
                    <td align="left"> Date : <br><p></p><br> Time :</td>
                </tr>
            </table>';
            $pdf->writeHTMLCell('', '', 11, $pdf->getY(), $tbl4, 0, 1, 0, true, 'C');
    
        }

        $tbl = '<p></p><span style="font-size:1.7em;"> ' . $result[0]['package_code'];
        $tbl .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img style="width:200px;height:30px;" src="' . $general->getBarcodeImageContent($result[0]['package_code']) . '">';
        $tbl .=  '</span><br>';

        if (isset($result) && !empty($result) && sizeof($result) > 0) {
            $tbl .= '<table style="width:100%;border:1px solid #333;">';
            if ($showPatientName == "yes") {
                $tbl .= '<tr nobr="true">
                        <td align="center" style="font-size:11px;width:3%;border:1px solid #333;" ><strong><em>S. No.</em></strong></td>
                        <td align="center" style="font-size:11px;width:11%;border:1px solid #333;"  ><strong><em>SAMPLE ID</em></strong></td>
                        <!-- <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><em>District</em></strong></td> -->
                        <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Health facility, District</em></strong></td>
                        <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Child Name</em></strong></td>
                       <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Child ID</em></strong></td>
                        <td align="center" style="font-size:11px;width:8%;border:1px solid #333;"  ><strong><em>Date of Birth</em></strong></td>
                        <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><em>Child Gender</em></strong></td>
                        <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><em>Mother Name</em></strong></td>
                        <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><em>Sample Collection Date</em></strong></td>
                        <!-- <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><em>Test Requested</em></strong></td> -->
                        <td align="center" style="font-size:11px;width:20%;border:1px solid #333;"  ><strong><em>Sample Barcode</em></strong></td>
                    </tr>';
            } else {
                $tbl .= '<tr nobr="true">
                        <td align="center" style="font-size:11px;width:3%;border:1px solid #333;" ><strong><em>S. No.</em></strong></td>
                        <td align="center" style="font-size:11px;width:11%;border:1px solid #333;"  ><strong><em>SAMPLE ID</em></strong></td>
                        <!-- <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><em>District</em></strong></td> -->
                        <td align="center" style="font-size:11px;width:12%;border:1px solid #333;"  ><strong><em>Health facility, District</em></strong></td>
                       <td align="center" style="font-size:11px;width:12%;border:1px solid #333;"  ><strong><em>Child ID</em></strong></td>
                        <td align="center" style="font-size:11px;width:8%;border:1px solid #333;"  ><strong><em>Date of Birth</em></strong></td>
                        <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><em>Child Gender</em></strong></td>
                        <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Mother Name</em></strong></td>
                        <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><em>Sample Collection Date</em></strong></td>
                        <!-- <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><em>Test Requested</em></strong></td> -->
                        <td align="center" style="font-size:11px;width:20%;border:1px solid #333;"  ><strong><em>Sample Barcode</em></strong></td>
                    </tr>';
            }
            $sampleCounter = 1;

            foreach ($result as $sample) {
                //var_dump($sample);die;
                $collectionDate = '';
                if (isset($sample['sample_collection_date']) && $sample['sample_collection_date'] != '' && $sample['sample_collection_date'] != null && $sample['sample_collection_date'] != '0000-00-00 00:00:00') {
                    $cDate = explode(" ", $sample['sample_collection_date']);
                    $collectionDate = DateUtility::humanReadableDateFormat($cDate[0]) . " " . $cDate[1];
                }
                $patientDOB = '';
                if (isset($sample['child_dob']) && $sample['child_dob'] != '' && $sample['child_dob'] != null && $sample['child_dob'] != '0000-00-00') {
                    $patientDOB = DateUtility::humanReadableDateFormat($sample['child_dob']);
                }
                $params = $pdf->serializeTCPDFtagParameters(array($sample['remote_sample_code'], 'C39', '', '', '', 9, 0.25, array('border' => false, 'align' => 'C', 'padding' => 1, 'fgcolor' => array(0, 0, 0), 'bgcolor' => array(255, 255, 255), 'text' => false, 'font' => 'helvetica', 'fontsize' => 10, 'stretchtext' => 2), 'N'));
                //$tbl.='<table cellspacing="0" cellpadding="3" style="width:100%">';
                $tbl .= '<tr style="border:1px solid #333;">';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $sampleCounter . '.</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $sample['remote_sample_code'] . '</td>';
                // $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['facility_district']) . '</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['clinic_name']) . ', ' . $sample['facility_district'] . '</td>';
                if ($showPatientName == "yes") {
                    $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['child_name']) . '</td>';
                }
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $sample['child_id'] . '</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $patientDOB . '</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . (str_replace("_", " ", $sample['child_gender'])) . '</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . ($sample['mother_name']) . '</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">' . $collectionDate . '</td>';
                // $tbl.='<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">VIRAL</td>';
                $tbl .= '<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;"><img style="width:180px;height:25px;" src="' . $general->getBarcodeImageContent($sample['remote_sample_code']) . '"/></td>';
                $tbl .= '</tr>';
                //$tbl .='</table>';
                $sampleCounter++;
            }
            $tbl .= '</table>';
        }
        $tbl .= '<br><br><br><br><table cellspacing="0" style="width:100%;">';
        $tbl .= '<tr >';
        $tbl .= '<td align="right" style="vertical-align:middle;font-size:11px;width:15%;"><strong>Generated By : </strong></td><td align="left" style="width:18.33%;"><span style="font-size:12px;">' . $_SESSION['userName'] . '</span></td>';
        $tbl .= '<td align="right" style="vertical-align:middle;font-size:11px;width:15%;"><strong>Verified By :  </strong></td><td style="width:18.33%;"></td>';
        $tbl .= '<td align="right" style="vertical-align:middle;font-size:11px;width:15%;"><strong>Received By : <br>(at Referral lab/NRL)</strong></td><td style="width:18.33%;"></td>';
        $tbl .= '</tr>';
        $tbl .= '</table>';
        //$tbl.='<br/><br/><strong style="text-align:left;">Printed On:  </strong>'.date('d/m/Y H:i:s');
        $pdf->writeHTMLCell('', '', 11, $pdf->getY(), $tbl, 0, 1, 0, true, 'C');

        $filename = trim($bResult[0]['package_code']) . '-' . date('Ymd') . '-' . $general->generateRandomString(6) . '-Manifest.pdf';
        $pdf->Output(TEMP_PATH . DIRECTORY_SEPARATOR . 'sample-manifests' . DIRECTORY_SEPARATOR . $filename, "F");
        echo $filename;
    }
}
