<?php

use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

$tableName1 = "activity_log";
$tableName2 = "form_eid";
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
$allQuery = $_SESSION['eidPrintQuery'];
if (isset($_POST['id']) && trim((string) $_POST['id']) != '') {

	$searchQuery = "SELECT vl.*,f.*,l.facility_name as labName,
                  l.facility_logo as facilityLogo,
                  rip.i_partner_name,
                  rst.*,
                  rsrr.rejection_reason_name ,
				  r_c_a.recommended_corrective_action_name,
                  u_d.user_name as reviewedBy,
                  u_d.user_id as reviewedByUserId,
                  u_d.user_signature as reviewedBySignature,
                  a_u_d.user_name as approvedBy,
                  a_u_d.user_id as approvedByUserId,
                  a_u_d.user_signature as approvedBySignature,
                  r_r_b.user_name as revised,
                  tp.config_machine_name as testingPlatform
                  FROM form_eid as vl
                  LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                  LEFT JOIN facility_details as l ON l.facility_id=vl.lab_id
                  LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by
                  LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by
                  LEFT JOIN user_details as r_r_b ON r_r_b.user_id=vl.revised_by
                  LEFT JOIN r_eid_sample_type as rst ON rst.sample_id=vl.specimen_type
                  LEFT JOIN r_eid_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=vl.reason_for_sample_rejection
                  LEFT JOIN r_implementation_partners as rip ON rip.i_partner_id=vl.implementing_partner
                  LEFT JOIN instrument_machines as tp ON tp.config_machine_id=vl.import_machine_name
				  LEFT JOIN r_recommended_corrective_actions as r_c_a ON r_c_a.recommended_corrective_action_id=vl.recommended_corrective_action
                  WHERE vl.eid_id IN(" . $_POST['id'] . ")";
} else {
	$searchQuery = $allQuery;
}
//echo($searchQuery);die;
$requestResult = $db->query($searchQuery);

if (($_SESSION['instanceType'] == 'vluser') && empty($requestResult[0]['result_printed_on_lis_datetime'])) {
	$pData = array('result_printed_on_lis_datetime' => date('Y-m-d H:i:s'));
	$db = $db->where('eid_id', $_POST['id']);
	$id = $db->update('form_eid', $pData);
} elseif (($_SESSION['instanceType'] == 'remoteuser') && empty($requestResult[0]['result_printed_on_sts_datetime'])) {
	$pData = array('result_printed_on_sts_datetime' => date('Y-m-d H:i:s'));
	$db = $db->where('eid_id', $_POST['id']);
	$id = $db->update('form_eid', $pData);
}

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
		$this->formId  = $formId;
	}

	public function imageExists($filePath): bool
	{
		return (!empty($filePath) && file_exists($filePath) && !is_dir($filePath) && filesize($filePath) > 0 && false !== getimagesize($filePath));
	}

	//Page header
	public function Header()
	{
		if ($this->htitle != '') {
			if (trim((string) $this->logo) != '') {
				if ($this->imageExists($this->logo)) {
					$imageFilePath = $this->logo;
				} else if ($this->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo)) {
					$imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'facility-logo' . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo;
				} else if ($this->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
					$imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
				}
				if (!empty($imageFilePath)) {
					$this->Image($imageFilePath, 95, 5, 15, '', '', '', 'T');
				}
			}
			$this->SetFont('helvetica', 'B', 8);
			$this->writeHTMLCell(0, 0, 10, 22, $this->text, 0, 0, 0, true, 'C');
			if (trim((string) $this->lab) != '') {
				$this->SetFont('helvetica', '', 9);
				$this->writeHTMLCell(0, 0, 10, 26, strtoupper((string) $this->lab), 0, 0, 0, true, 'C');
			}
			$this->SetFont('helvetica', '', 14);
			$this->writeHTMLCell(0, 0, 10, 30, 'EARLY INFANT DIAGNOSIS TEST - PATIENT REPORT', 0, 0, 0, true, 'C');
			$this->writeHTMLCell(0, 0, 15, 38, '<hr>', 0, 0, 0, true, 'C');
		} else {
			if (trim((string) $this->logo) != '') {
				if ($this->imageExists($this->logo)) {
					$imageFilePath = $this->logo;
				} else if ($this->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo)) {
					$imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'facility-logo' . DIRECTORY_SEPARATOR . $this->labFacilityId . DIRECTORY_SEPARATOR . $this->logo;
				} else if ($this->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo)) {
					$imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $this->logo;
				}
				if (!empty($imageFilePath)) {
					$this->Image($imageFilePath, 20, 13, 15, '', '', '', 'T');
				}
			}

			// $this->SetFont('helvetica', 'B', 7);
			// $this->writeHTMLCell(30,0,16,28,$this->text, 0, 0, 0, true, 'A', true);(this two lines comment out for drc)
			$this->SetFont('helvetica', '', 14);
			$this->writeHTMLCell(0, 0, 10, 9, 'MINISTERE DE LA SANTE PUBLIQUE', 0, 0, 0, true, 'C');
			if ($this->text != '') {
				$this->SetFont('helvetica', '', 12);
				//        $this->writeHTMLCell(0,0,10,16,'PROGRAMME NATIONAL DE LUTTE CONTRE LE SIDA ET IST', 0, 0, 0, true, 'C', true);
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
			if ($this->formId == 3) {
				$this->writeHTMLCell(0, 0, 10, $fourthHeading, 'DIAGNOSTIC PRÉCOCE DU NOURRISSON', 0, 0, 0, true, 'C');
			} else {
				$this->writeHTMLCell(0, 0, 10, $fourthHeading, 'RESULTATS CHARGE VIRALE', 0, 0, 0, true, 'C');
			}
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



if ($formId == COUNTRY\SOUTH_SUDAN) {
	include('pdf/result-pdf-ssudan.php');
} else if ($formId == COUNTRY\SIERRA_LEONE) {
	include('pdf/result-pdf-sierraleone.php');
} else if ($formId == COUNTRY\DRC) {
	include('pdf/result-pdf-drc.php');
} else if ($formId == COUNTRY\CAMEROON) {
	include('pdf/result-pdf-cameroon.php');
} else if ($formId == COUNTRY\PNG) {
	include('pdf/result-pdf-png.php');
} else if ($formId == COUNTRY\RWANDA) {
	include('pdf/result-pdf-rwanda.php');
}
