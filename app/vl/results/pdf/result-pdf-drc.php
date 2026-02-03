<?php

// imported in /vl/results/generate-result-pdf.php
use const SAMPLE_STATUS\REJECTED;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Helpers\PdfWatermarkHelper;
use App\Registries\ContainerRegistry;
use App\Helpers\ResultPDFHelpers\CountrySpecificHelpers\DrcVlPDFHelper;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

try {

	if (!empty($result)) {
		$_SESSION['aliasPage'] = $page;
		if (!isset($result['labName'])) {
			$result['labName'] = '';
		}
		$draftTextShow = false;

		// create new PDF document
		$pdf = new DrcVlPDFHelper(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
		if (MiscUtility::isImageValid(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
			$logoPrintInPdf = $result['facilityLogo'];
		} else {
			$logoPrintInPdf = $arr['logo'];
		}

		$headerText = isset($result['headerText']) && $result['headerText'] != '' ? $result['headerText'] : $arr['header'];

		$pdf->setHeading($logoPrintInPdf, $headerText, $result['labName'], '', $result['lab_id']);
		// set document information
		$pdf->SetCreator('InteLIS');
		$pdf->SetTitle('PROGRAMME NATIONAL DE LUTTE CONTRE LE SIDA ET IST');

		// set default header data
		$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

		// set header and footer fonts
		$pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
		$pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);

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


		// set font
		$pdf->SetFont('freesans', '', 18);

		$pdf->AddPage();
		if (!isset($result['facility_code']) || trim((string) $result['facility_code']) === '') {
			$result['facility_code'] = '';
		}
		if (!isset($result['facility_state']) || trim((string) $result['facility_state']) === '') {
			$result['facility_state'] = '';
		}
		if (!isset($result['facility_district']) || trim((string) $result['facility_district']) === '') {
			$result['facility_district'] = '';
		}
		if (!isset($result['facility_name']) || trim((string) $result['facility_name']) === '') {
			$result['facility_name'] = '';
		}
		if (!isset($result['labName']) || trim((string) $result['labName']) === '') {
			$result['labName'] = '';
		}
		//Set Age
		$age = DateUtility::calculatePatientAge($result);



		$result['result_printed_datetime'] = DateUtility::humanReadableDateFormat($result['result_printed_datetime'] ?? DateUtility::getCurrentDateTime(), true);
		$result['sample_collection_date'] = DateUtility::humanReadableDateFormat($result['sample_collection_date'] ?? '', true);
		$result['sample_received_at_lab_datetime'] = DateUtility::humanReadableDateFormat($result['sample_received_at_lab_datetime'] ?? '', true);
		$result['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($result['sample_tested_datetime'] ?? '', true);
		$result['last_viral_load_date'] = DateUtility::humanReadableDateFormat($result['last_viral_load_date'] ?? '');

		if (!isset($result['patient_gender']) || trim((string) $result['patient_gender']) === '') {
			$result['patient_gender'] = _translate('Unreported');
		}


		$resultApprovedBy = $result['approvedBy'] ?? null;
		if (empty($resultApprovedBy)) {
			$approvedByInfo = $usersService->getUserNameAndSignature($result['defaultApprovedBy']);
			$resultApprovedBy = $approvedByInfo['user_name'];
			$result['approvedBySignature'] = $approvedByInfo['user_signature'];
		}

		if (empty($result['result_approved_datetime']) && !empty($result['sample_tested_datetime'])) {
			$result['result_approved_datetime'] = $result['sample_tested_datetime'];
		}

		if (empty($result['result_reviewed_datetime']) && !empty($result['sample_tested_datetime'])) {
			$result['result_reviewed_datetime'] = $result['sample_tested_datetime'];
		}

		$approvedBySignaturePath = null;

		if (!empty($result['approvedBySignature'])) {
			$approvedBySignaturePath = MiscUtility::getFullImagePath($result['approvedBySignature'], UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature");
		}

		$smileyContent = '';
		$showMessage = '';
		$tndMessage = '';
		$messageTextSize = '15px';


		if (!empty($result['vl_result_category']) && $result['vl_result_category'] == 'suppressed') {
			$smileyContent = '<img src="/assets/img/smiley_smile.png" style="width:50px;" alt="smile_face"/>';
			$showMessage = $arr['l_vl_msg'];
		} elseif (!empty($result['vl_result_category']) && $result['vl_result_category'] == 'not suppressed') {
			$smileyContent = '<img src="/assets/img/smiley_frown.png" style="width:50px;" alt="frown_face"/>';
			$showMessage = ($arr['h_vl_msg']);
		} elseif ($result['result_status'] == REJECTED || $result['is_sample_rejected'] == 'yes') {
			$smileyContent = '<img src="/assets/img/cross.png" style="width:50px;" alt="rejected"/>';
		}

		if (isset($arr['show_smiley']) && trim((string) $arr['show_smiley']) === "no") {
			$smileyContent = '';
		} else {
			$smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $smileyContent;
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
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_collection_date'] . '</td>';

		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['patient_art_no'] . '</td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td colspan="3" style="line-height:10px;"></td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Âge</td>';
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Sexe</td>';
		$implementationPartner = "Partnaire d'appui";
		$html .= '<td colspan="2" style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">' . $implementationPartner . '</td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $age . '</td>';
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . _capitalizeFirstLetter(str_replace("_", " ", (string) $result['patient_gender'])) . '</td>';
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
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Date de réception de l\'échantillon</td>';
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Date de remise du résultat</td>';
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Type déchantillon</td>';
		$html .= '<td style="line-height:11px;font-size:11px;font-weight:bold;text-align:left;">Technique utilisée</td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_received_at_lab_datetime'] . '</td>';
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['result_printed_datetime'] . '</td>';
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . $result['sample_name'] . '</td>';
		$html .= '<td style="line-height:11px;font-size:11px;text-align:left;">' . ($result['instrument_machine_name'] ?? $result['vl_test_platform']) . '</td>';
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

		$html .= '<tr><td colspan="3"></td></tr>';

		if ($result['result'] == "< 40" || $result['result'] == "<40") {
			$logResult = '1.60';
		} elseif (!empty($result['result_value_log'])) {
			$logResult = $result['result_value_log'];
		} else {
			$logResult = '0.0';
		}

		$logValue = '';
		if (!empty($logResult)) {
			$logValue = '<br/>&nbsp;&nbsp;' . _translate("Log Value") . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp;' . $logResult;
		}

		$resultValue = $result['result'] ?? '';
		$decodedResult = is_numeric($resultValue) ? $resultValue : html_entity_decode((string) $resultValue, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$displayResult = is_numeric($decodedResult) ? round((float) $decodedResult) : $decodedResult;

		$html .= '<tr style="background-color:#dbdbdb;"><td colspan="3" style="line-height:26px;font-size:12px;font-weight:bold;text-align:left;">&nbsp;&nbsp;Résultat(copies/mL)&nbsp;&nbsp;&nbsp;&nbsp;:&nbsp;&nbsp;&nbsp;&nbsp; ' . htmlspecialchars((string) $displayResult, ENT_QUOTES | ENT_HTML5, 'UTF-8') . $logValue . '</td><td >' . $smileyContent . '</td></tr>';
		$html .= '<tr><td colspan="3"></td></tr>';
		$html .= '</table>';
		$html .= '</td>';
		$html .= '</tr>';
		if (trim((string) $showMessage) !== '') {
			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:13px;font-size:' . $messageTextSize . ';text-align:left;">' . $showMessage . '</td>';
			$html .= '</tr>';
			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:16px;"></td>';
			$html .= '</tr>';
		}
		if (trim($tndMessage) !== '') {
			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:13px;font-size:18px;text-align:left;">' . $tndMessage . '</td>';
			$html .= '</tr>';
			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:16px;"></td>';
			$html .= '</tr>';
		}
		//if (empty($signResults)) {

		if ($approvedBySignaturePath !== null && $approvedBySignaturePath !== '' && $approvedBySignaturePath !== '0' && MiscUtility::isImageValid($approvedBySignaturePath) && !empty($resultApprovedBy)) {
			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;vertical-align: bottom;"><img src="' . $approvedBySignaturePath . '" style="width:100px;margin-top:-20px;" /><br></td>';
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
		//}


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
			$html .= '<td colspan="3" style="line-height:11px;font-size:11px;font-weight:bold;">Resultats dernière charge virale(copies/mL)&nbsp;&nbsp;:&nbsp;&nbsp;<span style="font-weight:normal;">' . $result['last_viral_load_result'] . '</span></td>';
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
		// if (!empty($signResults)) {
		// 	$html .= '<table style="width:100%;padding:3px;border:1px solid gray;">';
		// 	$html .= '<tr>';
		// 	$html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid gray;">AUTORISÉ PAR</td>';
		// 	$html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">IMPRIMER LE NOM</td>';
		// 	$html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">SIGNATURE</td>';
		// 	$html .= '<td style="line-height:17px;font-size:13px;font-weight:bold;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">DATE & HEURE</td>';
		// 	$html .= '</tr>';
		// 	foreach ($signResults as $key => $row) {
		// 		$lmSign = UPLOAD_PATH . "/labs/" . $row['lab_id'] . "/signatures/" . $row['signature'];
		// 		$signature = '';
		// 		if (MiscUtility::isImageValid($lmSign)) {
		// 			$signature = '<img src="' . $lmSign . '" style="width:40px;" />';
		// 		}
		// 		$html .= '<tr>';
		// 		$html .= '<td style="line-height:17px;font-size:11px;text-align:left;font-weight:bold;border-bottom:1px solid gray;">' . $row['designation'] . '</td>';
		// 		$html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">' . $row['name_of_signatory'] . '</td>';
		// 		$html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">' . $signature . '</td>';
		// 		$html .= '<td style="line-height:17px;font-size:11px;text-align:left;border-bottom:1px solid gray;border-left:1px solid gray;">' . date('d-M-Y H:i:s a') . '</td>';
		// 		$html .= '</tr>';
		// 	}
		// 	$html .= '</table>';
		// }
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
		$html .= '<td style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_smile.png" alt="smile_face" style="width:10px;height:10px;"/> VL < 1000 copies/mL: Continue on current regimen</td>';
		$html .= '<td style="font-size:10px;text-align:left;">Printed on : ' . $printDate . '&nbsp;&nbsp;' . '</td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td colspan="2" style="font-size:10px;text-align:left;width:60%;"><img src="/assets/img/smiley_frown.png" alt="frown_face" style="width:10px;height:10px;"/> VL >= 1000 copies/mL:  Clinical and counselling action required</td>';
		$html .= '</tr>';
		$html .= '</table>';
		$html .= '</td>';
		$html .= '</tr>';
		$html .= '</table>';

		$pdf->writeHTML($html);
		$pdf->lastPage();
		$filename = $pathFront . DIRECTORY_SEPARATOR . 'p' . $page . '.pdf';
		$pdf->Output($filename, "F");
		if ($draftTextShow) {
			//Watermark section
			$watermark = new PdfWatermarkHelper();
			$watermark->setFullPathToFile($filename);
			//$fullPathToFile = $filename;
			$watermark->Output($filename, "F");
		}
		$pages[] = $filename;
		$page++;

		if (isset($_POST['source']) && trim((string) $_POST['source']) === 'print') {
			$sampleCode = 'sample_code';
			if ($general->isSTSInstance()) {
				$sampleCode = 'remote_sample_code';
				if (!empty($result['remote_sample']) && $result['remote_sample'] == 'yes') {
					$sampleCode = 'remote_sample_code';
				} else {
					$sampleCode = 'sample_code';
				}
			}
			$sampleId = (isset($result[$sampleCode]) && !empty($result[$sampleCode])) ? " " . _translate("Sample ID") . " " . $result[$sampleCode] : '';
			$patientId = (isset($result['patient_art_no']) && !empty($result['patient_art_no'])) ? " " . _translate("Patient ID") . " " . $result['patient_art_no'] : '';
			$concat = ($sampleId !== '' && $sampleId !== '0' && ($patientId !== '' && $patientId !== '0')) ? ' and' : '';
			//Add event log
			$eventType = 'print-result';
			$action = $_SESSION['userName'] . ' printed the test result with ' . $sampleId . $concat . $patientId;
			$resource = 'print-test-result';
			$data = [
				'event_type' => $eventType,
				'action' => $action,
				'resource' => $resource,
				'date_time' => DateUtility::getCurrentDateTime()
			];
			$db->insert('activity_log', $data);
			//Update print datetime in VL tbl.
			$vlQuery = "SELECT result_printed_datetime
						FROM form_vl as vl WHERE vl.vl_sample_id = ?";
			$vlResult = $db->rawQueryOne($vlQuery, [$result['vl_sample_id']]);
			if ($vlResult['result_printed_datetime'] == null || trim((string) $vlResult['result_printed_datetime']) === '' || str_starts_with(trim((string) $vlResult['result_printed_datetime']), '0000')) {
				$db->where('vl_sample_id', $result['vl_sample_id']);
				$db->update('form_vl', ['result_printed_datetime' => DateUtility::getCurrentDateTime()]);
			}
		}
	}
} catch (Throwable $e) {
	LoggerUtility::logError($e->getFile() . ":" . $e->getLine() . ":" . $e->getCode() . " - " . $e->getMessage(), [
		'exception' => $e,
		'file' => $e->getFile(),
		'line' => $e->getLine(),
		'stacktrace' => $e->getTraceAsString()
	]);
}
