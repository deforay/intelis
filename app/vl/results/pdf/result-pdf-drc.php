<?php

// imported in /vl/results/generate-result-pdf.php

use App\Helpers\PdfConcatenateHelper;
use App\Helpers\PdfWatermarkHelper;
use App\Utilities\DateUtility;

class DRC_PDF extends MYPDF
{
	public $text = '';
	public $lab = '';
	public $formId = '';
	public $logo = '';
	public $labFacilityId = '';

	//Page header
	public function Header()
	{
		// Logo
		//$imageFilePath = K_PATH_IMAGES.'logo_example.jpg';
		//$this->Image($imageFilePath, 10, 10, 15, '', 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
		// Set font
		$imageFilePath = null;
		if (trim($this->logo) != '') {
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
		if ($this->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'drc-logo.png')) {
			$imageFilePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . 'drc-logo.png';
			$this->Image($imageFilePath, 180, 13, 15, '', '', '', 'T');
		}

		$this->SetFont('helvetica', '', 14);
		$this->writeHTMLCell(0, 0, 10, 9, 'MINISTERE DE LA SANTE PUBLIQUE', 0, 0, 0, true, 'C');
		if ($this->text != '') {
			$this->SetFont('helvetica', '', 12);
			$this->writeHTMLCell(0, 0, 10, 16, strtoupper($this->text), 0, 0, 0, true, 'C');
			$thirdHeading = '23';
			$fourthHeading = '28';
			$hrLine = '36';
		} else {
			$thirdHeading = '17';
			$fourthHeading = '23';
			$hrLine = '30';
		}
		if (trim($this->lab) != '') {
			$this->SetFont('helvetica', '', 9);
			$this->writeHTMLCell(0, 0, 10, $thirdHeading, strtoupper($this->lab), 0, 0, 0, true, 'C');
		}
		$this->SetFont('helvetica', '', 12);
		$this->writeHTMLCell(0, 0, 10, $fourthHeading, 'RESULTATS CHARGE VIRALE', 0, 0, 0, true, 'C');
		$this->writeHTMLCell(0, 0, 15, $hrLine, '<hr>', 0, 0, 0, true, 'C');
	}
}


$resultFilename = '';
if (!empty($requestResult)) {
	$_SESSION['rVal'] = $general->generateRandomString(6);
	$pathFront = (TEMP_PATH . DIRECTORY_SEPARATOR .  $_SESSION['rVal']);
	if (!file_exists($pathFront) && !is_dir($pathFront)) {
		mkdir(TEMP_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal'], 0777, true);
		$pathFront = realpath(TEMP_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal']);
	}

	//$pathFront = $pathFront;
	$pages = [];
	$page = 1;
	foreach ($requestResult as $result) {

		$signQuery = "SELECT * FROM lab_report_signatories where lab_id=? AND test_types like '%vl%' AND signatory_status like 'active' ORDER BY display_order ASC";
		$signResults = $db->rawQuery($signQuery, array($result['lab_id']));

		$_SESSION['aliasPage'] = $page;
		if (!isset($result['labName'])) {
			$result['labName'] = '';
		}
		$draftTextShow = false;
		//Set watermark text
		for ($m = 0; $m < count($mFieldArray); $m++) {
			if (!isset($result[$mFieldArray[$m]]) || trim($result[$mFieldArray[$m]]) == '' || $result[$mFieldArray[$m]] == null || $result[$mFieldArray[$m]] == '0000-00-00 00:00:00') {
				$draftTextShow = true;
				break;
			}
		}
		// create new PDF document
		$pdf = new DRC_PDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
		if ($pdf->imageExists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
			$logoPrintInPdf = $result['facilityLogo'];
		} else {
			$logoPrintInPdf = $arr['logo'];
		}

		if (isset($result['headerText']) && $result['headerText'] != '') {
			$headerText = $result['headerText'];
		} else {
			$headerText = $arr['header'];
		}

		$pdf->setHeading($logoPrintInPdf, $headerText, $result['labName'], '', $result['lab_id']);
		// set document information
		$pdf->SetCreator(PDF_CREATOR);
		$pdf->SetTitle('PROGRAMME NATIONAL DE LUTTE CONTRE LE SIDA ET IST');

		// set default header data
		$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

		// set header and footer fonts
		$pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
		$pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

		// set default monospaced font
		$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

		// set margins
		if (isset($headerText) && $headerText != '') {
			$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP + 14, PDF_MARGIN_RIGHT);
		} else {
			$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP + 7, PDF_MARGIN_RIGHT);
		}
		$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

		// set auto page breaks
		$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

		// set image scale factor
		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

		// set some language-dependent strings (optional)
		//if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
		//    require_once(dirname(__FILE__).'/lang/eng.php');
		//    $pdf->setLanguageArray($l);
		//}

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
		} elseif (isset($result['patient_age_in_years']) && trim($result['patient_age_in_years']) != '' && trim($result['patient_age_in_years']) > 0) {
			$age = $result['patient_age_in_years'];
		} elseif (isset($result['patient_age_in_months']) && trim($result['patient_age_in_months']) != '' && trim($result['patient_age_in_months']) > 0) {
			if ($result['patient_age_in_months'] > 1) {
				$age = $result['patient_age_in_months'] . ' months';
			} else {
				$age = $result['patient_age_in_months'] . ' month';
			}
		}

		if (isset($result['sample_collection_date']) && trim($result['sample_collection_date']) != '' && $result['sample_collection_date'] != '0000-00-00 00:00:00') {
			$expStr = explode(" ", $result['sample_collection_date']);
			$result['sample_collection_date'] = DateUtility::humanReadableDateFormat($expStr[0]);
			$sampleCollectionTime = $expStr[1];
		} else {
			$result['sample_collection_date'] = '';
			$sampleCollectionTime = '';
		}
		$sampleReceivedDate = '';
		$sampleReceivedTime = '';
		if (isset($result['sample_received_at_vl_lab_datetime']) && trim($result['sample_received_at_vl_lab_datetime']) != '' && $result['sample_received_at_vl_lab_datetime'] != '0000-00-00 00:00:00') {
			$expStr = explode(" ", $result['sample_received_at_vl_lab_datetime']);
			$sampleReceivedDate = DateUtility::humanReadableDateFormat($expStr[0]);
			$sampleReceivedTime = $expStr[1];
		}

		if (isset($result['result_printed_datetime']) && trim($result['result_printed_datetime']) != '' && $result['result_printed_datetime'] != '0000-00-00 00:00:00') {
			$expStr = explode(" ", $result['result_printed_datetime']);
			$result['result_printed_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
		} else {
			$result['result_printed_datetime'] = DateUtility::getCurrentDateTime();
		}

		if (isset($result['sample_tested_datetime']) && trim($result['sample_tested_datetime']) != '' && $result['sample_tested_datetime'] != '0000-00-00 00:00:00') {
			$expStr = explode(" ", $result['sample_tested_datetime']);
			$result['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
		} else {
			$result['sample_tested_datetime'] = '';
		}

		if (isset($result['last_viral_load_date']) && trim($result['last_viral_load_date']) != '' && $result['last_viral_load_date'] != '0000-00-00') {
			$result['last_viral_load_date'] = DateUtility::humanReadableDateFormat($result['last_viral_load_date']);
		} else {
			$result['last_viral_load_date'] = '';
		}
		if (!isset($result['patient_gender']) || trim($result['patient_gender']) == '') {
			$result['patient_gender'] = 'not reported';
		}
		$resultApprovedBy  = '';
		$userRes = [];
		if (isset($result['approvedBy']) && trim($result['approvedBy']) != '') {
			$resultApprovedBy = ($result['approvedBy']);
			$userRes = $users->getUserInfo($result['result_approved_by'], 'user_signature');
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
			if (in_array(strtolower(trim($result['result'])), array("< 20", "< 40", "< 800", "< 400", "tnd", "target not detected", "not detected", "below detection level"))) {
				$vlResult = 'TND*';
				$smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" style="width:50px;" alt="smile_face"/>';
				$showMessage = ($arr['l_vl_msg']);
				$tndMessage = 'TND* - Target not Detected';
			} else if (in_array(strtolower(trim($result['result'])), array("failed", "fail", "no_sample", "invalid"))) {
				$vlResult = $result['result'];
				$smileyContent = '';
				$showMessage = '';
				$messageTextSize = '14px';
			} else if (trim($result['result']) > 1000 && $result['result'] <= 10000000) {
				$vlResult = $result['result'];
				$smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" alt="frown_face"/>';
				$showMessage = ($arr['h_vl_msg']);
				$messageTextSize = '15px';
			} else if (trim($result['result']) <= 1000 && $result['result'] >= 20) {
				$vlResult = $result['result'];
				$smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" alt="smile_face"/>';
				$showMessage = ($arr['l_vl_msg']);
			} else if (trim($result['result'] > 10000000) && $resultType) {
				$vlResult = $result['result'];
				$smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" alt="frown_face"/>';
				//$showMessage = 'Value outside machine detection limit';
			} else if (trim($result['result'] < 20) && $resultType) {
				$vlResult = $result['result'];
				$smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" alt="smile_face"/>';
				//$showMessage = 'Value outside machine detection limit';
			} else if (trim($result['result']) == '<20') {
				$vlResult = '&lt;20';
				$smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" alt="smile_face"/>';
				$showMessage = ($arr['l_vl_msg']);
			} else if (trim($result['result']) == '>10000000') {
				$vlResult = $result['result'];
				$smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" alt="frown_face"/>';
				$showMessage = ($arr['h_vl_msg']);
			} else if ($result['vl_test_platform'] == 'Roche') {
				$chkSign = '';
				$smileyShow = '';
				$chkSign = strchr($result['result'], '>');
				if ($chkSign != '') {
					$smileyShow = str_replace(">", "", $result['result']);
					$vlResult = $result['result'];
					//$showMessage = 'Invalid value';
				}
			}

			$chkSign = '';
			$chkSign = strchr($result['result'], '<');
			if ($chkSign != '') {
				$smileyShow = str_replace("<", "", $result['result']);
				$vlResult = str_replace("<", "&lt;", $result['result']);
				//$showMessage = 'Invalid value';
			}
			if (isset($smileyShow) && $smileyShow != '' && $smileyShow <= $arr['viral_load_threshold_limit']) {
				$smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_smile.png" alt="smile_face"/>';
			} else if (isset($smileyShow) && $smileyShow != '' && $smileyShow > $arr['viral_load_threshold_limit']) {
				$smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/smiley_frown.png" alt="frown_face"/>';
			}
		}
		if (isset($arr['show_smiley']) && trim($arr['show_smiley']) == "no") {
			$smileyContent = '';
		}
		if ($result['result_status'] == '4') {
			$smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/cross.png" alt="rejected"/>';
		}
		$html = '<br>';
		$html .= '<table style="padding:0px 2px 2px 2px;">';
		$html .= '<tr>';
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Échantillon id</td>';
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Date du prélèvement</td>';
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Code du patient</td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_code'] . '</td>';
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_collection_date'] . " " . $sampleCollectionTime . '</td>';

		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['patient_art_no'] . '</td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td colspan="3" style="line-height:10px;"></td>';
		$html .= '</tr>';
		//$html .='<tr>';
		// $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Pr�nom du patient</td>';
		// $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Nom de famille du patient</td>';
		// $html .='<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Mobile No.</td>';
		//$html .='</tr>';
		//$html .='<tr>';
		//  $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.($result['patient_first_name']).'</td>';
		//  $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.($result['patient_last_name']).'</td>';
		//  $html .='<td style="line-height:11px;font-size:11px;text-align:left;">'.$result['patient_mobile_number'].'</td>';
		//$html .='</tr>';
		//$html .='<tr>';
		//$html .='<td colspan="3" style="line-height:10px;"></td>';
		//$html .='</tr>';
		$html .= '<tr>';
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Âge</td>';
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Sexe</td>';
		$implementationPartner = "Partnaire d'appui";
		$html .= '<td colspan="2" style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . $implementationPartner . '</td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $age . '</td>';
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . (str_replace("_", " ", $result['patient_gender'])) . '</td>';
		$html .= '<td colspan="2" style="line-height:11px;font-size:11px;text-align:left;">' . $result['i_partner_name'] . '</td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td colspan="3" style="line-height:10px;"></td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td colspan="3" style="line-height:10px;"></td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Code Clinique</td>';
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Province</td>';
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Zone de santé</td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['facility_code'] . '</td>';
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['facility_state']) . '</td>';
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['facility_district']) . '</td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td colspan="3" style="line-height:10px;"></td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$healthCenter = "POINT DE COLLECT";
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . $healthCenter . '</td>';
		$html .= '<td colspan="2" style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Nom clinicien</td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['facility_name']) . '</td>';
		$html .= '<td colspan="2" style="line-height:11px;font-size:11px;text-align:left;">' . ($result['request_clinician_name']) . '</td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td colspan="3" style="line-height:10px;"></td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td colspan="3" style="line-height:10px;"></td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td colspan="3">';
		$html .= '<table style="padding:2px;">';
		$html .= '<tr>';
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Date de réception de léchantillon</td>';
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Date de remise du résultat</td>';
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Type déchantillon</td>';
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Technique utilisée</td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $sampleReceivedDate . " " . $sampleReceivedTime . '</td>';
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['result_printed_datetime'] . '</td>';
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['sample_name']) . '</td>';
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['vl_test_platform']) . '</td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td colspan="4" style="line-height:16px;"></td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Date de réalisation de la charge virale</td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_tested_datetime'] . '</td>';
		$html .= '</tr>';

		$html .= '<tr>';
		$html .= '<td colspan="3"></td>';
		$html .= '<td rowspan="3" style="text-align:left;">' . $smileyContent . '</td>';
		$html .= '</tr>';
		$logValue = '<br/>';
		if ($result['result_value_log'] != '') {
			$logValue = '<br/>&nbsp;&nbsp;Log Value&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $result['result_value_log'];
		} else {
			$logValue = '<br/>&nbsp;&nbsp;Log Value&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;0.0';
		}
		if ($result['result'] == "< 40") {
			$logValue = '<br/>&nbsp;&nbsp;Log Value&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;1.60';
		}
		$html .= '<tr><td colspan="3" style="line-height:26px;font-size:12px;font-weight:bold;text-align:left;background-color:#dbdbdb;">&nbsp;&nbsp;Résultat(copies/ml)&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $vlResult . $logValue . '</td></tr>';
		$html .= '<tr><td colspan="3"></td></tr>';
		$html .= '</table>';
		$html .= '</td>';
		$html .= '</tr>';
		if (trim($showMessage) != '') {
			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:13px;font-size:' . $messageTextSize . ';text-align:left;">' . $showMessage . '</td>';
			$html .= '</tr>';
			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:16px;"></td>';
			$html .= '</tr>';
		}
		if (trim($tndMessage) != '') {
			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:13px;font-size:18px;text-align:left;">' . $tndMessage . '</td>';
			$html .= '</tr>';
			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:16px;"></td>';
			$html .= '</tr>';
		}
		if (!isset($signResults) || empty($signResults)) {

			if (!empty($userSignaturePath) && $pdf->imageExists($userSignaturePath) && !empty($resultApprovedBy)) {
				$html .= '<tr>';
				$html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;vertical-align: bottom;"><img src="' . $userSignaturePath . '" style="width:70px;margin-top:-20px;" /><br></td>';
				$html .= '</tr>';
			}
			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Approuvé par&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . $resultApprovedBy . '</span></td>';
			$html .= '</tr>';
			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:10px;"></td>';
			$html .= '</tr>';

			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
			$html .= '</tr>';
			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:14px;"></td>';
			$html .= '</tr>';
		}


		if ($result['last_viral_load_date'] != '' || $result['last_viral_load_result'] != '') {
			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Resultats prècèdents</td>';
			$html .= '</tr>';
			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:8px;"></td>';
			$html .= '</tr>';
			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Date dernière charge virale (demande)&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . $result['last_viral_load_date'] . '</span></td>';
			$html .= '</tr>';
			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Resultats dernière charge virale(copies/ml)&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . $result['last_viral_load_result'] . '</span></td>';
			$html .= '</tr>';
			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:30px;border-bottom:2px solid #d3d3d3;"></td>';
			$html .= '</tr>';
		}
		$html .= '<tr>';
		$html .= '<td colspan="3" style="line-height:2px;"></td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<tr>';
		$html .= '<td colspan="3">';
		if (isset($signResults) && !empty($signResults)) {
			$html .= '<table style="width:100%;padding:3px;border:1px solid gray;">';
			$html .= '<tr>';
			$html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid gray;">AUTORISÉ PAR</td>';
			$html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">IMPRIMER LE NOM</td>';
			$html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">SIGNATURE</td>';
			$html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">DATE & HEURE</td>';
			$html .= '</tr>';
			foreach ($signResults as $key => $row) {
				$lmSign = "/uploads/labs/" . $row['lab_id'] . "/signatures/" . $row['signature'];
				$html .= '<tr>';
				$html .= '<td style="line-height:17px;font-size:11px;text-align:left;font-weight:bold;border-bottom:1px solid gray;">' . $row['designation'] . '</td>';
				$html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">' . $row['name_of_signatory'] . '</td>';
				$html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;"><img src="' . $lmSign . '" style="width:30px;"></td>';
				$html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">' . date('d-M-Y H:i:s a') . '</td>';
				$html .= '</tr>';
			}
			$html .= '</table>';
		}
		$html .= '</td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td colspan="3" style="line-height:12px;"></td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td colspan="3" style="line-height:2px;border-bottom:2px solid #d3d3d3;"></td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td colspan="3" style="line-height:12px;"></td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td colspan="3">';
		$html .= '<table>';
		$html .= '<tr>';
		$html .= '<td style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_smile.png" alt="smile_face" style="width:10px;height:10px;"/> = VL < = 1000 copies/ml: Continue on current regimen</td>';
		$html .= '<td style="font-size:10px;text-align:left;">Printed on : ' . $printDate . '&nbsp;&nbsp;' . $printDateTime . '</td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td colspan="2" style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_frown.png" alt="frown_face" style="width:10px;height:10px;"/> = VL > 1000 copies/ml: copies/ml: Clinical and counselling action required</td>';
		$html .= '</tr>';
		$html .= '</table>';
		$html .= '</td>';
		$html .= '</tr>';
		$html .= '</table>';
		if ($result['result'] != '') {
			$pdf->writeHTML($html);
			$pdf->lastPage();
			$filename = $pathFront . DIRECTORY_SEPARATOR . 'p' . $page . '.pdf';
			$pdf->Output($filename, "F");
			if ($draftTextShow) {
				//Watermark section
				$watermark = new PdfWatermarkHelper();
$watermark->setFullPathToFile($filename);
				$fullPathToFile = $filename;
				$watermark->Output($filename, "F");
			}
			$pages[] = $filename;
			$page++;
		}
		if (isset($_POST['source']) && trim($_POST['source']) == 'print') {
			//Add event log
			$eventType = 'print-result';
			$action = $_SESSION['userName'] . ' printed the test result with Patient ID/Code ' . $result['patient_art_no'];
			$resource = 'print-test-result';
			$data = array(
				'event_type' => $eventType,
				'action' => $action,
				'resource' => $resource,
				'date_time' => DateUtility::getCurrentDateTime()
			);
			$db->insert($tableName1, $data);
			//Update print datetime in VL tbl.
			$vlQuery = "SELECT result_printed_datetime FROM form_vl as vl WHERE vl.vl_sample_id ='" . $result['vl_sample_id'] . "'";
			$vlResult = $db->query($vlQuery);
			if ($vlResult[0]['result_printed_datetime'] == null || trim($vlResult[0]['result_printed_datetime']) == '' || $vlResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
				$db = $db->where('vl_sample_id', $result['vl_sample_id']);
				$db->update($tableName2, array('result_printed_datetime' => DateUtility::getCurrentDateTime()));
			}
		}
	}

	if (!empty($pages)) {
		$resultPdf = new PdfConcatenateHelper();
		$resultPdf->setFiles($pages);
		$resultPdf->setPrintHeader(false);
		$resultPdf->setPrintFooter(false);
		$resultPdf->concat();
		$resultFilename = 'VLSM-VL-Test-result-' . date('d-M-Y-H-i-s') . "-" . $general->generateRandomString(6) . '.pdf';
		$resultPdf->Output(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename, "F");
		$general->removeDirectory($pathFront);
		unset($_SESSION['rVal']);
	}
}

echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename);
