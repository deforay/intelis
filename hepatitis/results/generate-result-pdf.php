<?php
if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
ob_start();

use setasign\Fpdi\Tcpdf\Fpdi;

ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);


$tableName1 = "activity_log";
$tableName2 = "form_hepatitis";
$general = new \Vlsm\Models\General();
$users = new \Vlsm\Models\Users();

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
	date_default_timezone_set(!empty(date_default_timezone_get()) ?  date_default_timezone_get() : "UTC");
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
	$query = $_SESSION['hepatitisPrintedResultsQuery'];
	$allQuery = $_SESSION['hepatitisPrintedSearchResultQuery'];
} else {
	$query = $_SESSION['hepatitisPrintQuery'];
	$allQuery = $_SESSION['hepatitisPrintSearchResultQuery'];
}
if (isset($_POST['id']) && trim($_POST['id']) != '') {

	$searchQuery = "SELECT vl.*,f.*,
				l.facility_name as labName,
				l.facility_state as labState,
				l.facility_district as labCounty,
				l.facility_logo as facilityLogo,
				rip.i_partner_name,
				rsrr.rejection_reason_name ,
				u_d.user_name as reviewedBy,
				a_u_d.user_name as approvedBy,
				rfs.funding_source_name,
				c.iso_name as nationality,
				rst.sample_name,
				testres.test_reason_name as reasonForTesting
				FROM form_hepatitis as vl
				LEFT JOIN r_countries as c ON vl.patient_nationality=c.id
				LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
				LEFT JOIN facility_details as l ON l.facility_id=vl.lab_id 
				LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by 
				LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by 
				LEFT JOIN r_hepatitis_test_reasons as testres ON testres.test_reason_id=vl.reason_for_hepatitis_test 
				LEFT JOIN r_hepatitis_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=vl.reason_for_sample_rejection 
				LEFT JOIN r_implementation_partners as rip ON rip.i_partner_id=vl.implementing_partner
				LEFT JOIN r_funding_sources as rfs ON rfs.funding_source_id=vl.funding_source 
				LEFT JOIN r_hepatitis_sample_type as rst ON rst.sample_id=vl.specimen_type 
				WHERE vl.hepatitis_id IN(" . $_POST['id'] . ")";
} else {
	$searchQuery = $allQuery;
}
// echo($searchQuery);die;
$requestResult = $db->query($searchQuery);
/* Test Results */

$_SESSION['nbPages'] = sizeof($requestResult);
$_SESSION['aliasPage'] = 1;
//print_r($requestResult);die;
//header and footer
class MYPDF extends TCPDF
{

	//Page header
	public function setHeading($logo, $text, $lab, $title = null, $labFacilityId = null, $formId = null)
	{
		$this->logo = $logo;
		$this->text = $text;
		$this->lab = $lab;
		$this->htitle = $title;
		$this->labFacilityId = $labFacilityId;
		$this->formId = $formId;
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
			if ($this->formId == 3) {
				$this->SetFont('helvetica', 'B', 16);
				$this->writeHTMLCell(0, 0, 10, 03, $this->text, 0, 0, 0, true, 'C', true);
				if (trim($this->lab) != '') {
					$this->SetFont('helvetica', '', 10);
					$this->writeHTMLCell(0, 0, 10, 10, strtoupper($this->lab), 0, 0, 0, true, 'C', true);
				}
				$this->SetFont('helvetica', 'b', 10);
				$this->writeHTMLCell(0, 0, 10, 18, 'Département de Virologie', 0, 0, 0, true, 'C', true);
				$this->SetFont('helvetica', 'u', 10);
				$this->writeHTMLCell(0, 0, 10, 25, 'Laboratoire National de Reference pour la Grippe et les Virus Respiratoires', 0, 0, 0, true, 'C', true);
				$this->SetFont('helvetica', 'b', 12);
				$this->writeHTMLCell(0, 0, 10, 33, 'RESULTATS DE LABORATOIRE DES ECHANTIONS RESPIRATOIRES', 0, 0, 0, true, 'C', true);
				$this->SetFont('helvetica', 'u', 10);
				$this->writeHTMLCell(0, 0, 10, 40, 'TESTES AU HEPATITIS PAR RT-PCR en temps réel N°', 0, 0, 0, true, 'C', true);
				$this->writeHTMLCell(0, 0, 15, 48, '<hr>', 0, 0, 0, true, 'C', true);
			} else {
				$this->SetFont('helvetica', 'B', 16);
				$this->writeHTMLCell(0, 0, 10, 18, $this->text, 0, 0, 0, true, 'C', true);
				if (trim($this->lab) != '') {
					$this->SetFont('helvetica', '', 10);
					$this->writeHTMLCell(0, 0, 10, 25, strtoupper($this->lab), 0, 0, 0, true, 'C', true);
				}
				$this->SetFont('helvetica', '', 12);
				$this->writeHTMLCell(0, 0, 10, 30, 'HCV Viral Load Results Report', 0, 0, 0, true, 'C', true);
				$this->writeHTMLCell(0, 0, 15, 38, '<hr>', 0, 0, 0, true, 'C', true);
			}
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

			// $this->SetFont('helvetica', 'B', 7);
			// $this->writeHTMLCell(30,0,16,28,$this->text, 0, 0, 0, true, 'A', true);(this two lines comment out for drc)
			$this->SetFont('helvetica', '', 14);
			$this->writeHTMLCell(0, 0, 10, 9, 'MINISTERE DE LA SANTE PUBLIQUE', 0, 0, 0, true, 'C', true);
			if ($this->text != '') {
				$this->SetFont('helvetica', '', 12);
				//        $this->writeHTMLCell(0,0,10,16,'PROGRAMME NATIONAL DE LUTTE CONTRE LE SIDA ET IST', 0, 0, 0, true, 'C', true);
				$this->writeHTMLCell(0, 0, 10, 16, strtoupper($this->text), 0, 0, 0, true, 'C', true);
				$thirdHeading = '23';
				$fourthHeading = '28';
				$hrLine = '36';
				$marginTop = '14';
			} else {
				$thirdHeading = '17';
				$fourthHeading = '23';
				$hrLine = '30';
				$marginTop = '9';
			}
			if (trim($this->lab) != '') {
				$this->SetFont('helvetica', '', 9);
				$this->writeHTMLCell(0, 0, 10, $thirdHeading, strtoupper($this->lab), 0, 0, 0, true, 'C', true);
			}
			$this->SetFont('helvetica', '', 12);
			$this->writeHTMLCell(0, 0, 10, $fourthHeading, 'RESULTATS CHARGE VIRALE', 0, 0, 0, true, 'C', true);
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

$fileArray = array(
    1 => 'pdf/result-pdf-ssudan.php',
    2 => 'pdf/result-pdf-zm.php',
    3 => 'pdf/result-pdf-drc.php',
    4 => 'pdf/result-pdf-zam.php',
    5 => 'pdf/result-pdf-png.php',
    6 => 'pdf/result-pdf-who.php',
    7 => 'pdf/result-pdf-rwanda.php',
    8 => 'pdf/result-pdf-angola.php',
);

$country = array(
    1 => 'South sudan',
    2 => 'Zimbabwe',
    3 => 'Democratic Republic of the Congo',
    4 => 'Zambia',
    5 => 'Papua New Guinea',
    6 => 'Who',
    7 => 'Rwanda',
    8 => 'Angola',
);

if (file_exists($fileArray[$arr['vl_form']])) {
    require_once($fileArray[$arr['vl_form']]);
} else {
    require_once('pdf/result-pdf-who.php');
}
