<?php
session_start();
ob_start();
#require_once('../../../startup.php');

use setasign\Fpdi\Tcpdf\Fpdi;

include_once(APPLICATION_PATH . '/includes/MysqliDb.php');
include_once(APPLICATION_PATH . '/models/General.php');
include_once(APPLICATION_PATH . '/models/Users.php');
include_once(APPLICATION_PATH.'/vendor/autoload.php');

$tableName1 = "activity_log";
$tableName2 = "vl_request_form";
$general = new General($db);
$users = new Model_Users($db);

$configQuery = "SELECT * from global_config";
$configResult = $db->query($configQuery);
$arr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($configResult); $i++) {
  $arr[$configResult[$i]['name']] = $configResult[$i]['value'];
}
if (isset($arr['default_time_zone']) && $arr['default_time_zone'] != '') {
  date_default_timezone_set($arr['default_time_zone']);
} else {
  date_default_timezone_set("Europe/London");
}
//set mField Array
$mFieldArray = array();
if (isset($arr['r_mandatory_fields']) && trim($arr['r_mandatory_fields']) != '') {
  $mFieldArray = explode(',', $arr['r_mandatory_fields']);
}
//set print time
$printedTime = date('Y-m-d H:i:s');
$expStr = explode(" ", $printedTime);
$printDate = $general->humanDateFormat($expStr[0]);
$printDateTime = $expStr[1];
//set query
if (isset($_POST['newData']) && $_POST['newData'] != '') {
  //$query = $_SESSION['vlPrintResultQuery'];
  $allQuery = $_SESSION['vlPrintRequestSearchResultQuery'];
} else {
  //$query = isset($_SESSION['vlResultQuery']) ? $_SESSION['vlResultQuery'] : null;
  $allQuery = $_SESSION['vlRequestSearchResultQuery'];
}
if (isset($_POST['id']) && trim($_POST['id']) != '') {
  //if(isset($_POST['resultMail'])){
  $searchQuery = "SELECT vl.*,f.*,imp.i_partner_name,rst.*,vltr.test_reason_name,l.facility_name as labName,
                  l.facility_logo as facilityLogo,
                  rsrr.rejection_reason_name 
                  FROM vl_request_form as vl 
                  LEFT JOIN r_vl_test_reasons as vltr ON vl.reason_for_vl_testing = vltr.test_reason_id 
                  LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id 
                  LEFT JOIN r_vl_sample_type as rst ON rst.sample_id=vl.sample_type 
                  LEFT JOIN facility_details as l ON l.facility_id=vl.lab_id 
                  LEFT JOIN r_implementation_partners as imp ON imp.i_partner_id=vl.implementing_partner
                  LEFT JOIN r_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=vl.reason_for_sample_rejection 
                  WHERE vl.vl_sample_id IN(" . $_POST['id'] . ")";
  //}else{
  //  $searchQuery = $query." and vl.vl_sample_id IN(".$_POST['id'].")";
  //}
} else {
  $searchQuery = $allQuery;
}
//echo($searchQuery);die;
$requestResult = $db->query($searchQuery);
$_SESSION['nbPages'] = sizeof($requestResult);
$_SESSION['aliasPage'] = 1;
//print_r($requestResult);die;
//header and footer
class MYPDF extends TCPDF
{

  //Page header
  public function setHeading($logo, $text, $lab, $title = null, $labFacilityId = null)
  {
    $this->logo = $logo;
    $this->text = $text;
    $this->lab = $lab;
    $this->htitle = $title;
    $this->labFacilityId = $labFacilityId;
  }
  //Page header
  public function Header()
  {
    // Logo
    //$image_file = K_PATH_IMAGES.'logo_example.jpg';
    //$this->Image($image_file, 10, 10, 15, '', 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
    // Set font
    if ($this->htitle != '') {
      if (trim($this->logo) != '') {
        if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
          $image_file = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
          $this->Image($image_file, 95, 5, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
      }
      $this->SetFont('helvetica', 'B', 8);
      $this->writeHTMLCell(0, 0, 10, 22, $this->text, 0, 0, 0, true, 'C', true);
      if (trim($this->lab) != '') {
        $this->SetFont('helvetica', '', 9);
        $this->writeHTMLCell(0, 0, 10, 26, strtoupper($this->lab), 0, 0, 0, true, 'C', true);
      }
      $this->SetFont('helvetica', '', 14);
      $this->writeHTMLCell(0, 0, 10, 30, 'HIV VIRAL LOAD PATIENT REPORT', 0, 0, 0, true, 'C', true);

      $this->writeHTMLCell(0, 0, 15, 38, '<hr>', 0, 0, 0, true, 'C', true);
    } else {
      if (trim($this->logo) != '') {
        if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo)) {
          $image_file = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'facility-logo' . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo;
          $this->Image($image_file, 16, 13, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        } else if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
          $image_file = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
          $this->Image($image_file, 20, 13, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
      }
      if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'drc-logo.png')) {
        $image_file = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'drc-logo.png';
        $this->Image($image_file, 180, 13, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
      }

      if ($this->text != '') {
        $this->SetFont('helvetica', '', 16);
        $this->writeHTMLCell(0, 0, 10, 12, strtoupper($this->text), 0, 0, 0, true, 'C', true);
        $thirdHeading = '21';
        $fourthHeading = '28';
        $hrLine = '36';
        $marginTop = '14';
      } else {
        $thirdHeading = '14';
        $fourthHeading = '23';
        $hrLine = '30';
        $marginTop = '9'; 
      }
      if (trim($this->lab) != '') {
        $this->SetFont('helvetica', '', 10);
        $this->writeHTMLCell(0, 0, 8, $thirdHeading, strtoupper($this->lab), 0, 0, 0, true, 'C', true);
      }
      $this->SetFont('helvetica', '', 12);
      $this->writeHTMLCell(0, 0, 10, $fourthHeading, 'VIRAL LOAD TEST - PATIENT REPORT', 0, 0, 0, true, 'C', true);
      $this->writeHTMLCell(0, 0, 15, $hrLine, '<hr>', 0, 0, 0, true, 'C', true);
    }
  }

  // Page footer
  public function Footer()
  {
    // Position at 15 mm from bottom
    $this->SetY(-15);
    // Set font
    $this->SetFont('helvetica', '', 8);
    // Page number
    $this->Cell(0, 10, 'Page' . $_SESSION['aliasPage'] . '/' . $_SESSION['nbPages'], 0, false, 'C', 0, '', 0, false, 'T', 'M');
  }
}

class PDF_Rotate extends FPDI
{

  var $angle = 0;

  function Rotate($angle, $x = -1, $y = -1)
  {
    if ($x == -1)
      $x = $this->x;
    if ($y == -1)
      $y = $this->y;
    if ($this->angle != 0)
      $this->_out('Q');
    $this->angle = $angle;
    if ($angle != 0) {
      $angle *= M_PI / 180;
      $c = cos($angle);
      $s = sin($angle);
      $cx = $x * $this->k;
      $cy = ($this->h - $y) * $this->k;
      $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
    }
  }

  function _endpage()
  {
    if ($this->angle != 0) {
      $this->angle = 0;
      $this->_out('Q');
    }
    parent::_endpage();
  }
}

class Watermark extends PDF_Rotate
{

  var $_tplIdx;

  function Header()
  {
    global $fullPathToFile;

    //Put the watermark
    $this->SetFont('helvetica', 'B', 50);
    $this->SetTextColor(148, 162, 204);
    $this->RotatedText(67, 109, 'DRAFT', 45);

    if (is_null($this->_tplIdx)) {
      // THIS IS WHERE YOU GET THE NUMBER OF PAGES
      $this->numPages = $this->setSourceFile($fullPathToFile);
      $this->_tplIdx = $this->importPage(1);
    }
    $this->useTemplate($this->_tplIdx, 0, 0, 200);
  }

  function RotatedText($x, $y, $txt, $angle)
  {
    //Text rotated around its origin
    $this->Rotate($angle, $x, $y);
    $this->Text($x, $y, $txt);
    $this->Rotate(0);
    //$this->SetAlpha(0.7);
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
        $this->AddPage('P', array($s['w'], $s['h']));
        $this->useTemplate($tplidx);
      }
    }
  }
}
if ($arr['vl_form'] == 1) {
  include('resultPdfSouthSudan.php');
} else if ($arr['vl_form'] == 2) {
  include('resultPdfZm.php');
} else if ($arr['vl_form'] == 3) {
  include('resultPdfDrc.php');
} else if ($arr['vl_form'] == 4) {
  include('resultPdfZam.php');
} else if ($arr['vl_form'] == 5) {
  include('resultPdfPng.php');
} else if ($arr['vl_form'] == 6) {
  include('resultPdfWho.php');
} else if ($arr['vl_form'] == 7) {
  include('resultPdfRwd.php');
} else if ($arr['vl_form'] == 8) {
  include('resultPdfAng.php');
}
