<?php



ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);

use App\Models\General;
use App\Models\Users;
use App\Utilities\DateUtils;
use setasign\Fpdi\Tcpdf\Fpdi;

$tableName1 = "activity_log";
$tableName2 = "form_vl";
$general = new General();
$users = new Users();

$arr = $general->getGlobalConfig();

$requestResult = null;
if ((isset($_POST['id']) && !empty(trim($_POST['id']))) || (isset($_POST['sampleCodes']) && !empty(trim($_POST['sampleCodes'])))) {

  $searchQuery = "SELECT vl.*,
                  f.*,
                  imp.i_partner_name,
                  rst.*,
                  vltr.test_reason_name,
                  l.facility_name as labName,
                  u_d.user_name as reviewedBy,
                  a_u_d.user_name as approvedBy,
                  r_r_b.user_name as revised,
                  l.facility_logo as facilityLogo,
                  rsrr.rejection_reason_name 
                  FROM form_vl as vl 
                  LEFT JOIN r_vl_test_reasons as vltr ON vl.reason_for_vl_testing = vltr.test_reason_id 
                  LEFT JOIN facility_details as f ON vl.facility_id = f.facility_id 
                  LEFT JOIN r_vl_sample_type as rst ON rst.sample_id = vl.sample_type 
                  LEFT JOIN user_details as u_d ON u_d.user_id = vl.result_reviewed_by
                  LEFT JOIN user_details as a_u_d ON a_u_d.user_id = vl.result_approved_by
                  LEFT JOIN user_details as r_r_b ON r_r_b.user_id = vl.revised_by
                  LEFT JOIN facility_details as l ON l.facility_id = vl.lab_id 
                  LEFT JOIN r_implementation_partners as imp ON imp.i_partner_id = vl.implementing_partner
                  LEFT JOIN r_vl_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id = vl.reason_for_sample_rejection";

  $searchQueryWhere = [];
  if (!empty(trim($_POST['id']))) {
    $searchQueryWhere[] = " vl.vl_sample_id IN(" . $_POST['id'] . ") ";
  }

  if (isset($_POST['sampleCodes']) && !empty(trim($_POST['sampleCodes']))) {
    $searchQueryWhere[] = " vl.sample_code IN(" . $_POST['sampleCodes'] . ") ";
  }
  if (!empty($searchQueryWhere)) {
    $searchQuery .= " WHERE " . implode(" AND ", $searchQueryWhere);
  }
  //echo ($searchQuery);
  $requestResult = $db->query($searchQuery);
}


if (empty($requestResult) || $requestResult == false) {
  return null;
}

//set print time
$printedTime = date('Y-m-d H:i:s');
$expStr = explode(" ", $printedTime);
$printDate = DateUtils::humanReadableDateFormat($expStr[0]);
$printDateTime = $expStr[1];

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
    if ($this->htitle != '') {
      if (trim($this->logo) != '') {
        error_log($this->logo);
        if ($this->imageExists($this->logo)) {
          $this->Image($this->logo, 95, 5, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        } else if ($this->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo)) {
          $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo;
          $this->Image($imageFilePath, 95, 5, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        } else if ($this->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
          $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
          $this->Image($imageFilePath, 95, 5, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
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
        if ($this->imageExists($this->logo)) {
          $this->Image($this->logo, 20, 13, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        } else if ($this->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo)) {
          $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'facility-logo' . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo;
          $this->Image($imageFilePath, 20, 13, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        } else if ($this->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
          $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
          $this->Image($imageFilePath, 20, 13, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }
      }
      if ($this->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'drc-logo.png')) {
        $imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'drc-logo.png';
        $this->Image($imageFilePath, 180, 13, 15, '', '', '', 'T', false, 300, '', false, false, 0, false, false, false);
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
    //$this->Cell(0, 10, 'Page' . $_SESSION['aliasPage'] . '/' . $_SESSION['nbPages'], 0, false, 'C', 0, '', 0, false, 'T', 'M');
    $this->Cell(0, 10, 'Page 1 of 1', 0, false, 'C', 0, '', 0, false, 'T', 'M');
    
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
  var $files = [];
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
  include('pdf/result-pdf-ssudan.php');
} else if ($arr['vl_form'] == 2) {
  include('pdf/result-pdf-sierraleone.php');
} else if ($arr['vl_form'] == 3) {
  include('pdf/result-pdf-drc.php');
} else if ($arr['vl_form'] == 4) {
  include('pdf/result-pdf-zambia.php');
} else if ($arr['vl_form'] == 5) {
  include('pdf/result-pdf-png.php');
} else if ($arr['vl_form'] == 6) {
  include('pdf/result-pdf-who.php');
} else if ($arr['vl_form'] == 7) {
  include('pdf/result-pdf-rwanda.php');
} else if ($arr['vl_form'] == 8) {
  include('pdf/result-pdf-angola.php');
}
