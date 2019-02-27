<?php
ob_start();
session_start();
include_once(dirname(__FILE__) . '/../includes/MysqliDb.php');
include_once(dirname(__FILE__) . '/../includes/tcpdf/tcpdf.php');
include_once(dirname(__FILE__) . '/../General.php');
//define('UPLOAD_PATH','../uploads');
$general = new General($db);
$id = base64_decode($_POST['id']);
if(isset($_POST['frmSrc']) && trim($_POST['frmSrc']) == 'pk2'){
  $id = $_POST['ids'];
}

// Extend the TCPDF class to create custom Header and Footer
class MYPDF extends TCPDF {
    public function setHeading($logo,$text,$labname) {
        $this->logo = $logo;
        $this->text = $text;
        $this->labname = $labname;
    }
    //Page header
    public function Header() {
        // Logo
        //$image_file = K_PATH_IMAGES.'logo_example.jpg';
        //$this->Image($image_file, 10, 10, 15, '', 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        // Set font
        if(trim($this->logo)!=""){
            if (file_exists(dirname(__FILE__) . '/../uploads'. DIRECTORY_SEPARATOR . 'logo'. DIRECTORY_SEPARATOR.$this->logo)) {
                $image_file = dirname(__FILE__) . '/../uploads'. DIRECTORY_SEPARATOR . 'logo'. DIRECTORY_SEPARATOR.$this->logo;
                $this->Image($image_file,15, 10, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }
        }
        $this->SetFont('helvetica', '', 7);
        $this->writeHTMLCell(30,0,10,26,$this->text, 0, 0, 0, true, 'A', true);
        $this->SetFont('helvetica', '', 13);
        $this->writeHTMLCell(0,0,0,10,'SAMPLE REFERRAL MANIFEST ', 0, 0, 0, true, 'C', true);
        $this->SetFont('helvetica', '', 10);
        $this->writeHTMLCell(0,0,0,20,$this->labname, 0, 0, 0, true, 'C', true);
        
        if(trim($this->logo)!=""){
            if (file_exists(dirname(__FILE__) . '/../uploads'. DIRECTORY_SEPARATOR . 'logo'. DIRECTORY_SEPARATOR.$this->logo)) {
                $image_file = dirname(__FILE__) . '/../uploads'. DIRECTORY_SEPARATOR . 'logo'. DIRECTORY_SEPARATOR.$this->logo;
                $this->Image($image_file,262, 10, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
            }
        }
        $this->SetFont('helvetica', '', 7);
        $this->writeHTMLCell(30,0,255,26,$this->text, 0, 0, 0, true, 'A', true);
        $html='<hr/>';
        $this->writeHTMLCell(0, 0,10,32, $html, 0, 0, 0, true, 'J', true);
    }

    // Page footer
    public function Footer() {
        // Position at 15 mm from bottom
        $this->SetY(-15);
        // Set font
        $this->SetFont('helvetica', '', 8);
        // Page number
        $this->Cell(0, 10,  'Specimen Manifest Generated On : '.date('d/m/Y H:i:s'). ' | Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}




if(trim($id)!= ''){



    $sQuery="SELECT remote_sample_code,fd.facility_name as clinic_name,fd.facility_district,patient_first_name,patient_middle_name,patient_last_name,patient_dob,patient_age_in_years,sample_name,sample_collection_date,patient_gender,patient_art_no,pd.package_code, l.facility_name as lab_name from package_details as pd Join vl_request_form as vl ON vl.sample_package_id=pd.package_id Join facility_details as fd ON fd.facility_id=vl.facility_id Join facility_details as l ON l.facility_id=vl.lab_id Join r_sample_type as st ON st.sample_id=vl.sample_type where pd.package_id IN($id)";
    $result=$db->query($sQuery);


    $labname = isset($result[0]['lab_name']) ? $result[0]['lab_name'] : "";

    if (!file_exists(UPLOAD_PATH. DIRECTORY_SEPARATOR . "package_barcode") && !is_dir(UPLOAD_PATH. DIRECTORY_SEPARATOR."package_barcode")) {
        mkdir(UPLOAD_PATH. DIRECTORY_SEPARATOR."package_barcode");
    }
    $configQuery="SELECT * from global_config";
    $configResult=$db->query($configQuery);
    $arr = array();
    // now we create an associative array so that we can easily create view variables
    for ($i = 0; $i < sizeof($configResult); $i++) {
      $arr[$configResult[$i]['name']] = $configResult[$i]['value'];
    }
    $bQuery = "SELECT * from package_details as pd where package_id IN($id)";
    //echo $bQuery;die;
    $bResult = $db->query($bQuery);
    if(count($bResult)>0){
        

        // create new PDF document
        $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        $pdf->setHeading($arr['logo'],$arr['header'],$labname);
        
        // set document information
        $pdf->SetCreator('VLSTS');
        $pdf->SetAuthor('VLSTS');
        $pdf->SetTitle('Specimen Referral Manifest');
        $pdf->SetSubject('Specimen Referral Manifest');
        $pdf->SetKeywords('Specimen Referral Manifest');
    
        // set default header data
        $pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);
    
        // set header and footer fonts
        $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

        // set default monospaced font
        $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
        // set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, 36, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
        
        // set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        
        // set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        
        // set some language-dependent strings (optional)
        if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
            require_once(dirname(__FILE__).'/lang/eng.php');
            $pdf->setLanguageArray($l);
        }
    
        // set font
        $pdf->SetFont('helvetica', '', 10);
        $pdf->setPageOrientation('L');
        // add a page
        $pdf->AddPage();
        $tbl = '';
        $tbl .= '<h2> '.$result[0]['package_code'].'</h2>';
    $tbl .= '<table style="width:100%;border:1px solid #333;">
            
                <tr nobr="true">
                    <td align="center" style="font-size:11px;width:2%;border:1px solid #333;" ><strong><i>S/N</i></strong></td>
                    <td align="center" style="font-size:11px;width:11%;border:1px solid #333;"  ><strong><i>SAMPLE ID</i></strong></td>
                    <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><i>District</i></strong></td>
                    <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><i>Health facility</i></strong></td>
                    <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><i>Patient Full Name</i></strong></td>
                    <td align="center" style="font-size:11px;width:10%;border:1px solid #333;"  ><strong><i>TRACNET ID</i></strong></td>
                    <td align="center" style="font-size:11px;width:3%;border:1px solid #333;"  ><strong><i>Age</i></strong></td>
                    <td align="center" style="font-size:11px;width:8%;border:1px solid #333;"  ><strong><i>Date of Birth</i></strong></td>
                    <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><i>Gender</i></strong></td>
                    <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><i>Specimen Type</i></strong></td>
                    <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><i>Collection Date</i></strong></td>
                    <!-- <td align="center" style="font-size:11px;width:7%;border:1px solid #333;"  ><strong><i>Test Requested</i></strong></td> -->
                    <td align="center" style="font-size:11px;width:18%;border:1px solid #333;"  ><strong><i>Sample Barcode</i></strong></td>
                </tr>';
    
        $sampleCounter = 1;

        foreach($result as $sample){
            error_log($sample['package_code']);
            //var_dump($sample);die;
            $collectionDate = '';
            if(isset($sample['sample_collection_date']) && $sample['sample_collection_date'] != '' && $sample['sample_collection_date']!= NULL && $sample['sample_collection_date'] != '0000-00-00 00:00:00'){
                $cDate = explode(" ",$sample['sample_collection_date']);
                $collectionDate= $general->humanDateFormat($cDate[0])." ".$cDate[1];
            }
            $patientDOB = '';
            if(isset($sample['patient_dob']) && $sample['patient_dob'] != '' && $sample['patient_dob']!= NULL && $sample['patient_dob'] != '0000-00-00'){
                $patientDOB= $general->humanDateFormat($sample['patient_dob']);
            }
            $params = $pdf->serializeTCPDFtagParameters(array($sample['remote_sample_code'], 'C39', '', '','' ,7, 0.25,array('border'=>false,'align' => 'C','padding'=>1, 'fgcolor'=>array(0,0,0), 'bgcolor'=>array(255,255,255), 'text'=>false, 'font'=>'helvetica', 'fontsize'=>10, 'stretchtext'=>2),'N'));
            //$tbl.='<table cellspacing="0" cellpadding="3" style="width:100%">';
            $tbl.='<tr style="border:1px solid #333;">';
            $tbl.='<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">'.$sampleCounter.'.</td>';
            $tbl.='<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">'.$sample['remote_sample_code'].'</td>';
            $tbl.='<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">'.ucwords($sample['facility_district']).'</td>';
            $tbl.='<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">'.ucwords($sample['clinic_name']).'</td>';
            $tbl.='<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">'.ucwords($sample['patient_first_name']." ".$sample['patient_middle_name']." ".$sample['patient_last_name']).'</td>';
            $tbl.='<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">'.$sample['patient_art_no'].'</td>';
            $tbl.='<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">'.ucwords($sample['patient_age_in_years']).'</td>';
            $tbl.='<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">'.$patientDOB.'</td>';
            $tbl.='<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">'.ucwords(str_replace("_"," ",$sample['patient_gender'])).'</td>';
            $tbl.='<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">'.ucwords($sample['sample_name']).'</td>';
            $tbl.='<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">'.$collectionDate.'</td>';
            // $tbl.='<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;">VIRAL</td>';
            $tbl.='<td align="center"  style="vertical-align:middle;font-size:11px;border:1px solid #333;"><br><tcpdf method="write1DBarcode" params="'.$params.'" /></td>';
            $tbl.='</tr>';
            //$tbl .='</table>';
          $sampleCounter++;
       }
       $tbl.='</table>';

       $tbl.='<br><br><br><br><table cellspacing="0" style="width:100%;">';
       $tbl.='<tr style="">';
            $tbl.='<td align="right" style="vertical-align:middle;font-size:11px;width:15%;"><b>Generated By : </b></td><td align="left" style="width:18.33%;"><span style="font-size:12px;">'.$_SESSION['userName'].'</span></td>';
            $tbl.='<td align="right" style="vertical-align:middle;font-size:11px;width:15%;"><b>Verified By :  </b></td><td style="width:18.33%;"></td>';
            $tbl.='<td align="right" style="vertical-align:middle;font-size:11px;width:15%;"><b>Received By : <br>(at Referral lab/NRL)</b></td><td style="width:18.33%;"></td>';
       $tbl.='</tr>';
       $tbl.='</table>';
    //$tbl.='<br/><br/><b style="text-align:left;">Printed On:  </b>'.date('d/m/Y H:i:s');
    $pdf->writeHTMLCell('', '', 11,$pdf->getY(),$tbl, 0, 1, 0, true, 'C', true);
    $filename = trim($bResult[0]['package_code']).'.pdf';
    $pdf->Output(dirname(__FILE__) . '/../uploads'. DIRECTORY_SEPARATOR.'package_barcode'. DIRECTORY_SEPARATOR.$filename, "F");
    echo $filename;
  }
}
?>