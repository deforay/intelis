<?php
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}
ob_start();

use setasign\Fpdi\Tcpdf\Fpdi;

#require_once('../../startup.php');  



$tableName1 = "activity_log";
$tableName2 = "vl_request_form";

$configQuery = "SELECT * from global_config";
$configResult = $db->query($configQuery);
$arr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($configResult); $i++) {
  $arr[$configResult[$i]['name']] = $configResult[$i]['value'];
}
$country = $arr['vl_form'];
if (isset($arr['default_time_zone']) && $arr['default_time_zone'] != '') {
  date_default_timezone_set($arr['default_time_zone']);
} else {
  date_default_timezone_set(!empty(date_default_timezone_get()) ?  date_default_timezone_get() : "UTC");
}
if (isset($_POST['reportedDate']) && trim($_POST['reportedDate']) != '') {
  $s_t_date = explode("to", $_POST['reportedDate']);
  if (isset($s_t_date[0]) && trim($s_t_date[0]) != "") {
    $start_date = $general->dateFormat(trim($s_t_date[0]));
  }
  if (isset($s_t_date[1]) && trim($s_t_date[1]) != "") {
    $end_date = $general->dateFormat(trim($s_t_date[1]));
  }
}


$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
  $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}



if ($sarr['sc_user_type'] == 'vluser') {
  $vlLabQuery = "SELECT * FROM facility_details where status='active' AND facility_id = " . $sarr['sc_testing_lab_id'];
  $vlLabResult = $db->rawQuery($vlLabQuery);
} else if (isset($_POST['lab']) && trim($_POST['lab']) != '') {
  $vlLabQuery = "SELECT * FROM facility_details where facility_id IN (" . $_POST['lab'] . ") AND status='active'";
  $vlLabResult = $db->rawQuery($vlLabQuery);
} else {
  $vlLabQuery = "SELECT * FROM facility_details where facility_type = 2 AND status='active'";
  $vlLabResult = $db->rawQuery($vlLabQuery);
}
//header and footer
//Pdf code start
// create new PDF document
class MYPDF extends TCPDF
{

  //Page header
  public function setHeading($title, $logo, $text, $lab, $report_date)
  {
    $this->logo = $logo;
    $this->text = $text;
    $this->lab = $lab;
    $this->title = $title;
    $this->report_date = $report_date;
  }
  //Page header
  public function Header()
  {
    // Logo
    //$image_file = K_PATH_IMAGES.'logo_example.jpg';
    //$this->Image($image_file, 10, 10, 15, '', 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
    // Set font
    if (trim($this->logo) != '') {
      if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
        $image_file = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
        $this->Image($image_file, 20, 13, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
      }
    }
    $this->SetFont('helvetica', 'B', 7);
    $this->writeHTMLCell(30, 0, 16, 28, $this->text, 0, 0, 0, true, 'A', true);
    $this->SetFont('helvetica', '', 18);
    $this->writeHTMLCell(0, 0, 10, 18, $this->title, 0, 0, 0, true, 'C', true);
    if (trim($this->lab) != '') {
      $this->SetFont('helvetica', '', 9);
      $this->writeHTMLCell(0, 0, 10, 26, strtoupper($this->lab), 0, 0, 0, true, 'C', true);
    }
    $this->SetFont('helvetica', '', 9);
    $this->writeHTMLCell(0, 0, 0, 26, 'Report Date : ' . $this->report_date, 0, 0, 0, true, 'R', true);
    $this->writeHTMLCell(0, 0, 15, 36, '<hr>', 0, 0, 0, true, 'C', true);
  }

  // Page footer
  public function Footer()
  {
    // Position at 15 mm from bottom
    $this->SetY(-15);
    // Set font
    $this->SetFont('helvetica', '', 8);
    // Page number
    $this->Cell(0, 10,  'Report generated on ' . date('d/m/Y H:i:s'), 0, false, 'C', 0, '', 0, false, 'T', 'M');
  }
}

class Pdf_concat extends FPDI
{
  var $files = array();

  function setFiles($files)
  {
    $this->files = $files;
  }

  function concat()
  {
    foreach ($this->files as $file) {
      $pagecount = $this->setSourceFile($file);
      for ($i = 1; $i <= $pagecount; $i++) {
        $tplidx = $this->ImportPage($i);
        $s = $this->getTemplatesize($tplidx);
        $this->AddPage('L', array($s['w'], $s['h']));
        $this->useTemplate($tplidx);
      }
    }
  }
}

if (sizeof($vlLabResult) > 0) {
  $_SESSION['rVal'] = $general->generateRandomString(6);
  if (!file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal']) && !is_dir(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal'])) {
    mkdir(UPLOAD_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal']);
  }
  $pathFront = realpath(UPLOAD_PATH . $_SESSION['rVal'] . '/');
  $pages = array();
  $page = 1;
  $_SESSION['nbPages'] = (count($vlLabResult) + 1);
  foreach ($vlLabResult as $vlLab) {
    $_SESSION['aliasPage'] = $page;
    // create new PDF document
    $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->setHeading('VIRAL LOAD STATISTICS', $arr['logo'], $arr['header'], $vlLab['facility_name'], $_POST['reportedDate']);
    $pdf->setPageOrientation('L');
    // set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetTitle('VIRAL LOAD LAB WEEKLY REPORT');
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
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

    // set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

    // set font
    $pdf->SetFont('helvetica', '', 8);
    $sQuery = "SELECT
	
		 vl.facility_id,f.facility_code,f.facility_state,f.facility_district,f.facility_name,
		
		SUM(CASE
			WHEN (reason_for_sample_rejection IS NOT NULL AND reason_for_sample_rejection!= '' AND reason_for_sample_rejection!= 0) THEN 1
		             ELSE 0
		           END) AS rejections,
		SUM(CASE 
			WHEN ((patient_age_in_years >= 0 AND patient_age_in_years <= 15) AND ((vl.vl_result_category like 'suppressed') AND vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1
		             ELSE 0
		           END) AS lt15suppressed,
		SUM(CASE 
             WHEN ((patient_age_in_years >= 0 AND patient_age_in_years <= 15) AND vl.result IS NOT NULL AND vl.result!= '' AND vl.vl_result_category like 'suppressed' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1
             ELSE 0
           END) AS lt15NotSuppressed,
		SUM(CASE 
             WHEN (patient_age_in_years > 15 AND patient_gender IN ('m','male','M','MALE') AND ((vl.vl_result_category like 'suppressed') AND vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1
             ELSE 0
           END) AS gt15suppressedM,
		SUM(CASE 
             WHEN (patient_age_in_years > 15 AND patient_gender IN ('m','male','M','MALE') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.vl_result_category like 'suppressed' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1
             ELSE 0
           END) AS gt15NotSuppressedM,
		SUM(CASE 
             WHEN (patient_age_in_years > 15 AND patient_gender IN ('f','female','F','FEMALE') AND ((vl.vl_result_category like 'suppressed') AND vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1
             ELSE 0
           END) AS gt15suppressedF,
		SUM(CASE 
             WHEN (patient_age_in_years > 15 AND patient_gender IN ('f','female','F','FEMALE') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.vl_result_category like 'suppressed' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1
             ELSE 0
           END) AS gt15NotSuppressedF,	
		SUM(CASE 
             WHEN ((is_patient_pregnant ='Yes' OR is_patient_pregnant ='YES' OR is_patient_pregnant ='yes' OR is_patient_breastfeeding ='Yes' OR is_patient_breastfeeding ='YES' OR is_patient_breastfeeding ='yes') AND ((vl.vl_result_category like 'suppressed') AND vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1
             ELSE 0
           END) AS pregsuppressed,	
		SUM(CASE 
             WHEN ((is_patient_pregnant ='Yes' OR is_patient_pregnant ='YES' OR is_patient_pregnant ='yes' OR is_patient_breastfeeding ='Yes' OR is_patient_breastfeeding ='YES' OR is_patient_breastfeeding ='yes') AND vl.result IS NOT NULL AND vl.result!= '' AND vl.vl_result_category like 'suppressed' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1
             ELSE 0
           END) AS pregNotSuppressed,           	           	
		SUM(CASE 
             WHEN (((patient_age_in_years = '' OR patient_age_in_years is NULL) OR (patient_gender = '' OR patient_gender is NULL)) AND ((vl.vl_result_category like 'suppressed') AND vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1
             ELSE 0
           END) AS usuppressed, 
		SUM(CASE 
             WHEN (((patient_age_in_years = '' OR patient_age_in_years is NULL) OR (patient_gender = '' OR patient_gender is NULL)) AND vl.result IS NOT NULL AND vl.result!= '' AND vl.vl_result_category like 'suppressed' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00') THEN 1
             ELSE 0
           END) AS uNotSuppressed,               
		SUM(CASE 
             WHEN (((vl.vl_result_category like 'suppressed') AND vl.result IS NOT NULL AND vl.result!= '' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1
             ELSE 0
           END) AS totalLessThan1000,     
		SUM(CASE 
             WHEN ((vl.result IS NOT NULL AND vl.result!= '' AND vl.vl_result_category like 'suppressed' AND sample_tested_datetime is not null AND sample_tested_datetime not like '' AND DATE(sample_tested_datetime) !='1970-01-01' AND DATE(sample_tested_datetime) !='0000-00-00')) THEN 1
             ELSE 0
           END) AS totalGreaterThan1000,
		COUNT(result) as total
		 FROM vl_request_form as vl RIGHT JOIN facility_details as f ON f.facility_id=vl.facility_id
       WHERE vl.lab_id = " . $vlLab['facility_id'] . " AND vl.vlsm_country_id = " . $country;
    if (isset($_POST['reportedDate']) && trim($_POST['reportedDate']) != '') {
      if (trim($start_date) == trim($end_date)) {
        $sQuery = $sQuery . ' AND DATE(vl.sample_tested_datetime) = "' . $start_date . '"';
      } else {
        $sQuery = $sQuery . ' AND DATE(vl.sample_tested_datetime) >= "' . $start_date . '" AND DATE(vl.sample_tested_datetime) <= "' . $end_date . '"';
      }
    }
    if (isset($_POST['searchData']) && trim($_POST['searchData']) != '') {
      //$sQuery = $sQuery.' AND (f.facility_state LIKE "%'.$_POST['searchData'].'%" OR f.facility_district LIKE "%'.$_POST['searchData'].'%" OR f.facility_name LIKE "%'.$_POST['searchData'].'%")';
    }
    $sQuery = $sQuery . ' GROUP BY vl.facility_id';
    $sResult = $db->rawQuery($sQuery);
    //error_log($sQuery);
    $pdf->AddPage();
    //Statistics pdf start
    $html = '';
    $html .= '<table style="border:2px solid #f4f4f4;">';
    $html .= '<thead>';
    $html .= '<tr>';
    $html .= '<th rowspan="2" align="center" style="border:1px solid #f4f4f4;"><strong>Province/State</strong></th>';
    $html .= '<th rowspan="2" align="center" style="border:1px solid #f4f4f4;"><strong>District/County</strong></th>';
    $html .= '<th rowspan="2" align="center" style="border:1px solid #f4f4f4;"><strong>Site Name</strong></th>';
    $html .= '<th rowspan="2" align="center" style="border:1px solid #f4f4f4;"><strong>Site ID</strong></th>';
    $html .= '<th rowspan="2" align="center" style="border:1px solid #f4f4f4;"><strong>No. of Rejections</strong></th>';
    $html .= '<th colspan="2" align="center" style="border:1px solid #f4f4f4;"><strong>Viral Load Results - Peds</strong></th>';
    $html .= '<th colspan="4" align="center" style="border:1px solid #f4f4f4;"><strong>Viral Load Results - Adults</strong></th>';
    $html .= '<th colspan="2" align="center" style="border:1px solid #f4f4f4;"><strong>Viral Load Results - Pregnant/Breastfeeding Women</strong></th>';
    $html .= '<th colspan="2" align="center" style="border:1px solid #f4f4f4;"><strong>Age/Sex Unknown</strong></th>';
    $html .= '<th colspan="2" align="center" style="border:1px solid #f4f4f4;"><strong>Totals</strong></th>';
    $html .= '<th rowspan="2" align="center" style="border:1px solid #f4f4f4;"><strong>Total Test per Clinic</strong></th>';
    $html .= '</tr>';
    $html .= '<tr>';
    $html .= '<th align="center" style="border:1px solid #f4f4f4;"> <strong>&lt;= 15 yrs &lt;= 1000 cp/ml</strong></th>';
    $html .= '<th align="center" style="border:1px solid #f4f4f4;"> <strong>&lt;= 15 yrs &gt; 1000 cp/ml</strong></th>';
    $html .= '<th align="center" style="border:1px solid #f4f4f4;"> <strong>&gt; 15yrs Male &lt; 1000 cp/ml</strong></th>';
    $html .= '<th align="center" style="border:1px solid #f4f4f4;"> <strong>&gt; 15yrs Male &gt; 1000 cp/ml</strong></th>';
    $html .= '<th align="center" style="border:1px solid #f4f4f4;"> <strong>&gt; 15yrs Female &lt;= 1000 cp/ml</strong></th>';
    $html .= '<th align="center" style="border:1px solid #f4f4f4;"> <strong>&gt; 15yrs  Female &gt; 1000 cp/ml</strong></th>';
    $html .= '<th align="center" style="border:1px solid #f4f4f4;"> <strong>&lt;= 1000 cp/ml</strong></th>';
    $html .= '<th align="center" style="border:1px solid #f4f4f4;"> <strong>&gt; 1000 cp/ml</strong></th>';
    $html .= '<th align="center" style="border:1px solid #f4f4f4;"><strong>Unknown Age/Sex &lt;= 1000ml</strong></th>';
    $html .= '<th align="center" style="border:1px solid #f4f4f4;"><strong>Unknown Age/Sex &gt; 1000ml</strong></th>';
    $html .= '<th align="center" style="border:1px solid #f4f4f4;"> <strong>&lt;= 1000 cp/ml</strong></th>';
    $html .= '<th align="center" style="border:1px solid #f4f4f4;"> <strong>&gt; 1000 cp/ml</strong></th>';
    $html .= '</tr>';
    $html .= '</thead>';
    $html .= '<tbody>';
    if (count($sResult) > 0) {
      foreach ($sResult as $result) {
        $html .= '<tr>';
        $html .= '<td style="min-height:20px;border:1px solid #f4f4f4;">' . ucwords($result['facility_state']) . '</td>';
        $html .= '<td style="border:1px solid #f4f4f4;">' . ucwords($result['facility_district']) . '</td>';
        $html .= '<td style="border:1px solid #f4f4f4;">' . ucwords($result['facility_name']) . '</td>';
        $html .= '<td style="border:1px solid #f4f4f4;">' . $result['facility_code'] . '</td>';
        $html .= '<td align="center" style="border:1px solid #f4f4f4;">' . $result['rejections'] . '</td>';
        $html .= '<td align="center" style="border:1px solid #f4f4f4;">' . $result['lt15suppressed'] . '</td>';
        $html .= '<td align="center" style="border:1px solid #f4f4f4;">' . $result['lt15NotSuppressed'] . '</td>';
        $html .= '<td align="center" style="border:1px solid #f4f4f4;">' . $result['gt15suppressedM'] . '</td>';
        $html .= '<td align="center" style="border:1px solid #f4f4f4;">' . $result['gt15NotSuppressedM'] . '</td>';
        $html .= '<td align="center" style="border:1px solid #f4f4f4;">' . $result['gt15suppressedF'] . '</td>';
        $html .= '<td align="center" style="border:1px solid #f4f4f4;">' . $result['gt15NotSuppressedF'] . '</td>';
        $html .= '<td align="center" style="border:1px solid #f4f4f4;">' . $result['pregsuppressed'] . '</td>';
        $html .= '<td align="center" style="border:1px solid #f4f4f4;">' . $result['pregNotSuppressed'] . '</td>';
        $html .= '<td align="center" style="border:1px solid #f4f4f4;">' . $result['usuppressed'] . '</td>';
        $html .= '<td align="center" style="border:1px solid #f4f4f4;">' . $result['uNotSuppressed'] . '</td>';
        $html .= '<td align="center" style="border:1px solid #f4f4f4;">' . $result['totalLessThan1000'] . '</td>';
        $html .= '<td align="center" style="border:1px solid #f4f4f4;">' . $result['totalGreaterThan1000'] . '</td>';
        $html .= '<td align="center" style="border:1px solid #f4f4f4;">' . $result['total'] . '</td>';
        $html .= '</tr>';
      }
    } else {
      $html .= '<tr>';
      $html .= '<td style="min-height:20px;border:1px solid #f4f4f4;"></td>';
      $html .= '<td style="border:1px solid #f4f4f4;"></td>';
      $html .= '<td style="border:1px solid #f4f4f4;"></td>';
      $html .= '<td style="border:1px solid #f4f4f4;"></td>';
      $html .= '<td align="center" style="border:1px solid #f4f4f4;"></td>';
      $html .= '<td align="center" style="border:1px solid #f4f4f4;"></td>';
      $html .= '<td align="center" style="border:1px solid #f4f4f4;"></td>';
      $html .= '<td align="center" style="border:1px solid #f4f4f4;"></td>';
      $html .= '<td align="center" style="border:1px solid #f4f4f4;"></td>';
      $html .= '<td align="center" style="border:1px solid #f4f4f4;"></td>';
      $html .= '<td align="center" style="border:1px solid #f4f4f4;"></td>';
      $html .= '<td align="center" style="border:1px solid #f4f4f4;"></td>';
      $html .= '<td align="center" style="border:1px solid #f4f4f4;"></td>';
      $html .= '<td align="center" style="border:1px solid #f4f4f4;"></td>';
      $html .= '<td align="center" style="border:1px solid #f4f4f4;"></td>';
      $html .= '<td align="center" style="border:1px solid #f4f4f4;"></td>';
      $html .= '<td align="center" style="border:1px solid #f4f4f4;"></td>';
      $html .= '<td align="center" style="border:1px solid #f4f4f4;"></td>';
      $html .= '</tr>';
    }
    $html .= '</tbody>';
    $html .= '</table>';
    $pdf->writeHTML($html);
    $pdf->lastPage();
    $filename = $pathFront . DIRECTORY_SEPARATOR . 'p' . $page . '.pdf';
    $pdf->Output($filename, "F");
    $pages[] = $filename;
    $page++;
  }
  $_SESSION['aliasPage'] = $page;
  //Statistics pdf end
  if ($page > 1) {


    $filename = $pathFront . DIRECTORY_SEPARATOR . 'p' . $page . '.pdf';
    $pdf->Output($filename, "F");
    $pages[] = $filename;
    //Super lab performance pdf end
    if (count($pages) > 0) {
      $resultPdf = new Pdf_concat();
      $resultPdf->setFiles($pages);
      $resultPdf->setPrintHeader(false);
      $resultPdf->setPrintFooter(false);
      $resultPdf->concat();
      $reportFilename = 'VLSM-VL-Lab-Weekly-Report-' . date('d-M-Y-H-i-s') . '.pdf';
      $resultPdf->Output(UPLOAD_PATH . DIRECTORY_SEPARATOR . $reportFilename, "F");
      $general->removeDirectory($pathFront);
      unset($_SESSION['rVal']);
    }
  }
}
