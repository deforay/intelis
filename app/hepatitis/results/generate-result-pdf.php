<?php

use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);


$tableName1 = "activity_log";
$tableName2 = "form_hepatitis";
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$formId = $general->getGlobalConfig('vl_form');

//set print time
$printedTime = date('Y-m-d H:i:s');
$expStr = explode(" ", $printedTime);
$printDate = DateUtility::humanReadableDateFormat($expStr[0]);
$printDateTime = $expStr[1];
//set query
$allQuery = $_SESSION['hepatitisPrintQuery'];
if (isset($_POST['id']) && trim((string) $_POST['id']) != '') {

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

if (($_SESSION['instanceType'] == 'vluser') && empty($requestResult[0]['result_printed_on_lis_datetime'])) {
	$pData = array('result_printed_on_lis_datetime' => date('Y-m-d H:i:s'));
	$db = $db->where('hepatitis_id', $_POST['id']);
	$id = $db->update('form_hepatitis', $pData);
} elseif (($_SESSION['instanceType'] == 'remoteuser') && empty($requestResult[0]['result_printed_on_sts_datetime'])) {
	$pData = array('result_printed_on_sts_datetime' => date('Y-m-d H:i:s'));
	$db = $db->where('hepatitis_id', $_POST['id']);
	$id = $db->update('form_hepatitis', $pData);
}


/* Test Results */

$_SESSION['nbPages'] = sizeof($requestResult);
$_SESSION['aliasPage'] = 1;
//print_r($requestResult);die;
//header and footer
class MYPDF extends TCPDF
{
	public string $logo = '';
	public string $text = '';
	public string $lab = '';
	public string $htitle = '';
	public string $labFacilityId = '';
	public string $formId = '';
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
	public function imageExists($filePath): bool
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
			if (trim((string) $this->logo) != '') {
				if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
					$imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
					$this->Image($imageFilePath, 95, 5, 15, '', '', '', 'T');
				}
			}
			$this->SetFont('helvetica', 'B', 16);
			$this->writeHTMLCell(0, 0, 10, 18, $this->text, 0, 0, 0, true, 'C');
			if (trim((string) $this->lab) != '') {
				$this->SetFont('helvetica', '', 10);
				$this->writeHTMLCell(0, 0, 10, 25, strtoupper((string) $this->lab), 0, 0, 0, true, 'C');
			}
			$this->SetFont('helvetica', '', 12);
			$this->writeHTMLCell(0, 0, 10, 30, 'Hepatitis Viral Load Results Report', 0, 0, 0, true, 'C');
			$this->writeHTMLCell(0, 0, 15, 38, '<hr>', 0, 0, 0, true, 'C');
		} else {
			if (trim((string) $this->logo) != '') {
				if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo)) {
					$imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'facility-logo' . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo;
					$this->Image($imageFilePath, 16, 13, 15, '', '', '', 'T');
				} elseif (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
					$imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
					$this->Image($imageFilePath, 20, 13, 15, '', '', '', 'T');
				}
			}
			if (file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'drc-logo.png')) {
				$imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'drc-logo.png';
				$this->Image($imageFilePath, 180, 13, 15, '', '', '', 'T');
			}

			$this->SetFont('helvetica', '', 14);
			$this->writeHTMLCell(0, 0, 10, 9, 'MINISTERE DE LA SANTE PUBLIQUE', 0, 0, 0, true, 'C');
			if ($this->text != '') {
				$this->SetFont('helvetica', '', 12);
				$this->writeHTMLCell(0, 0, 10, 16, strtoupper((string) $this->text), 0, 0, 0, true, 'C');
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
			if (trim((string) $this->lab) != '') {
				$this->SetFont('helvetica', '', 9);
				$this->writeHTMLCell(0, 0, 10, $thirdHeading, strtoupper((string) $this->lab), 0, 0, 0, true, 'C');
			}
			$this->SetFont('helvetica', '', 12);
			$this->writeHTMLCell(0, 0, 10, $fourthHeading, 'RESULTATS CHARGE VIRALE', 0, 0, 0, true, 'C');
			$this->writeHTMLCell(0, 0, 15, $hrLine, '<hr>', 0, 0, 0, true, 'C');
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
		$this->Cell(0, 10, 'Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, false, 'C', 0);
	}
}



$fileArray = array(
	1 => 'pdf/result-pdf-ssudan.php',
	2 => 'pdf/result-pdf-sierraleone.php',
	3 => 'pdf/result-pdf-drc.php',
	4 => 'pdf/result-pdf-cameroon.php',
	5 => 'pdf/result-pdf-png.php',
	6 => 'pdf/result-pdf-who.php',
	7 => 'pdf/result-pdf-rwanda.php'
);

$country = array(
	1 => 'South sudan',
	2 => 'Sierra Leone',
	3 => 'Democratic Republic of the Congo',
	4 => 'Cameroon',
	5 => 'Papua New Guinea',
	6 => 'WHO',
	7 => 'Rwanda'
);

require($fileArray[$formId]);
