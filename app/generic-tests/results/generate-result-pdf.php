<?php

use App\Services\UsersService;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use const SAMPLE_STATUS\REJECTED;
use App\Helpers\PdfWatermarkHelper;
use App\Helpers\PdfConcatenateHelper;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;
use Psr\Http\Message\ServerRequestInterface;
use App\Helpers\ResultPDFHelpers\GenericTestsResultPDFHelper;

// Sanitized values from $request object

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$tableName1 = "activity_log";
$tableName2 = "form_generic";
/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$usersService = ContainerRegistry::get(UsersService::class);

$arr = $general->getGlobalConfig();

$requestResult = null;
if ((isset($_POST['id']) && !in_array(trim((string) $_POST['id']), ['', '0'], true)) || (isset($_POST['sampleCodes']) && !in_array(trim((string) $_POST['sampleCodes']), ['', '0'], true))) {

	$searchQuery = "SELECT vl.*,
					f.*,
					vl.test_type as testType,
					imp.i_partner_name,
					rst.*,
					vltr.test_reason,
					l.facility_name as labName,
					u_d.user_name as reviewedBy,
					a_u_d.user_name as approvedBy,
					r_r_b.user_name as revised,
					l.facility_logo as facilityLogo,
					rsrr.rejection_reason_name,
					rtt.test_standard_name,
					rtt.test_loinc_code
					FROM form_generic as vl
					INNER JOIN r_test_types as rtt ON rtt.test_type_id = vl.test_type
					LEFT JOIN r_generic_test_reasons as vltr ON vl.reason_for_testing = vltr.test_reason_id
					LEFT JOIN facility_details as f ON vl.facility_id = f.facility_id
					LEFT JOIN r_generic_sample_types as rst ON rst.sample_type_id = vl.specimen_type
					LEFT JOIN user_details as u_d ON u_d.user_id = vl.result_reviewed_by
					LEFT JOIN user_details as a_u_d ON a_u_d.user_id = vl.result_approved_by
					LEFT JOIN user_details as r_r_b ON r_r_b.user_id = vl.revised_by
					LEFT JOIN facility_details as l ON l.facility_id = vl.lab_id
					LEFT JOIN r_implementation_partners as imp ON imp.i_partner_id = vl.implementing_partner
					LEFT JOIN r_generic_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id = vl.reason_for_sample_rejection
					LEFT JOIN instruments as i ON (
						(vl.instrument_id IS NOT NULL AND vl.instrument_id != '' AND i.instrument_id = vl.instrument_id)
						OR (
							(vl.instrument_id IS NULL OR vl.instrument_id = '')
							AND i.machine_name = vl.test_platform
						)
					)";

	$searchQueryWhere = [];
	if (!in_array(trim((string) $_POST['id']), ['', '0'], true)) {
		$searchQueryWhere[] = " vl.sample_id IN(" . $_POST['id'] . ") ";
	}

	if (isset($_POST['sampleCodes']) && !in_array(trim((string) $_POST['sampleCodes']), ['', '0'], true)) {
		$searchQueryWhere[] = " vl.sample_code IN(" . $_POST['sampleCodes'] . ") ";
	}
	// Facility isolation: a mapped STS user only gets PDFs for their own
	// facilities. No-op on LIS and for unmapped (all-access) users.
	if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
		$searchQueryWhere[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
	}
	if ($labScope = $general->labScopeWhere('vl')) {
		$searchQueryWhere[] = $labScope;
	}
	if ($searchQueryWhere !== []) {
		$searchQuery .= " WHERE " . implode(" AND ", $searchQueryWhere);
	}
	$searchQuery .= " GROUP BY vl.sample_id";
	$requestResult = $db->query($searchQuery);
	// echo "<pre>";print_r($requestResult);die;
}
if (empty($requestResult) || !$requestResult) {
	return null;
}

$currentDateTime = DateUtility::getCurrentDateTime();

foreach ($requestResult as $requestRow) {
	if (($general->isLISInstance()) && empty($requestRow['result_printed_on_lis_datetime'])) {
		$pData = ['result_printed_on_lis_datetime' => $currentDateTime, 'result_printed_datetime' => $currentDateTime];
		$db->where('sample_id', $requestRow['sample_id']);
		$id = $db->update('form_generic', $pData);
	} elseif (($general->isSTSInstance()) && empty($requestRow['result_printed_on_sts_datetime'])) {
		$pData = ['result_printed_on_sts_datetime' => $currentDateTime, 'result_printed_datetime' => $currentDateTime];
		$db->where('sample_id', $requestRow['sample_id']);
		$id = $db->update('form_generic', $pData);
	}
}

//set print time
$printedTime = date('Y-m-d H:i:s');
$expStr = explode(" ", $printedTime);
$printDate = DateUtility::humanReadableDateFormat($expStr[0]);
$printDateTime = $expStr[1];

$_SESSION['nbPages'] = count($requestResult);
$_SESSION['aliasPage'] = 1;





/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

$resultFilename = $showHideTable = '';
if (!empty($requestResult)) {
	$_SESSION['rVal'] = MiscUtility::generateRandomString(6);
	$showHideTable = (string) ($general->getGlobalConfig('generic_tests_table_in_results_pdf')) ?? 'no';
	$pathFront = TEMP_PATH . DIRECTORY_SEPARATOR . $_SESSION['rVal'];
	MiscUtility::makeDirectory($pathFront);
	$pages = [];
	$page = 1;
	foreach ($requestResult as $result) {
		$currentTime = DateUtility::getCurrentDateTime();

		$testResultUnits = $genericTestsService->getTestResultUnit($result['testType']);
		$testUnits = [];
		foreach ($testResultUnits as $key => $unit) {
			$testUnits[$unit['unit_id']] = $unit['unit_name'];
		}

		$testTypeQuery = "SELECT * FROM r_test_types WHERE test_type_id= ?";
		$testTypeResult = $db->rawQueryOne($testTypeQuery, [$result['testType']]);
		$testResultsAttribute = json_decode((string) $testTypeResult['test_results_config'], true);

		// The request-form field config drives this PDF too: fields the test type hides on the form
		// must not print here, and the Patient ID label must match whatever the form renamed it to.
		$advancedFormConfig = (isset($testResultsAttribute['advancedFormConfig']) && is_array($testResultsAttribute['advancedFormConfig']))
			? $testResultsAttribute['advancedFormConfig'] : [];
		$advShown = fn($key) => (($advancedFormConfig[$key] ?? '') !== 'no'); // shown unless explicitly "no" (mirrors the form toggles)
		$showPatientName      = $advShown('showPatientName');
		$showLaboratoryNumber = $advShown('showLaboratoryNumber');
		$showPregnancy        = $advShown('showPregnancy');
		$showBreastfeeding    = $advShown('showBreastfeeding');
		$patientIdLabel = (!empty($advancedFormConfig['patientIdLabel']))
			? mb_strtoupper(trim((string) $advancedFormConfig['patientIdLabel']), 'UTF-8') : 'EPID NUMBER';

		$subTestKey = [];
		foreach ($testResultsAttribute['result_type'] as $key => $resultType) {
			if ($resultType == 'quantitative') {
				$subTestKey[$testResultsAttribute['sub_test_name'][$key]] = $key;
			}
		}
		// echo "<pre>";print_r($testResultsAttribute);die;

		// $genericTestQuery = "SELECT res.*, m.test_method_name from generic_test_results as res INNER JOIN r_generic_test_methods AS m ON m.test_method_id=res.test_name where res.generic_id=? ORDER BY res.test_id ASC";
		// $genericTestInfo = $db->rawQuery($genericTestQuery, array($result['sample_id']));
		// Per-test rows. JOIN the lab / tested-reviewed-approved users / rejection reason so
		// the multi-test (TB-style) layout can name them without per-row lookups. The extra
		// aliased columns are additive — the legacy sub_test path below still reads res.*.
		$genericTestQuery = "SELECT res.*,
						l.facility_name as lab_name,
						u_test.user_name as testedByName,
						u_review.user_name as reviewedByName,
						u_approve.user_name as approvedByName,
						rsrr.rejection_reason_name as rejectionReasonName
						FROM generic_test_results as res
						LEFT JOIN facility_details as l ON res.lab_id = l.facility_id
						LEFT JOIN user_details as u_test ON u_test.user_id = res.tested_by
						LEFT JOIN user_details as u_review ON u_review.user_id = res.result_reviewed_by
						LEFT JOIN user_details as u_approve ON u_approve.user_id = res.result_approved_by
						LEFT JOIN r_generic_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id = res.reason_for_sample_rejection
						WHERE res.generic_id=? ORDER BY res.test_id ASC";
		$genericTestInfo = $db->rawQuery($genericTestQuery, [$result['sample_id']]);

		// Genuine multi-test = more than one test card, none of which is a legacy
		// quantitative sub-test row (those carry sub_test_name and use the table layout).
		$isMultiTest = is_array($genericTestInfo) && count($genericTestInfo) > 1;
		if ($isMultiTest) {
			foreach ($genericTestInfo as $gtRow) {
				if (!empty($gtRow['sub_test_name'])) {
					$isMultiTest = false;
					break;
				}
			}
		}
		// $testedBy = null;
		if (!empty($result['tested_by'])) {
			$testedByRes = $usersService->getUserByID($result['tested_by'], ['user_name', 'user_signature']);
			if ($testedByRes) {
				$testedBy = $testedByRes['user_name'];
			}
		}
		$reviewedBy = null;
		if (!empty($result['result_reviewed_by'])) {
			$reviewedByRes = $usersService->getUserByID($result['result_reviewed_by'], ['user_name', 'user_signature']);
			if ($reviewedByRes) {
				$reviewedBy = $reviewedByRes['user_name'];
			}
		}

		$revisedBy = null;
		$revisedByRes = [];
		if (!empty($result['revised_by'])) {
			$revisedByRes = $usersService->getUserByID($result['revised_by'], ['user_name', 'user_signature']);
			if ($revisedByRes) {
				$revisedBy = $revisedByRes['user_name'];
			}
		}

		$revisedBySignaturePath = $reviewedBySignaturePath = $testedBySignaturePath = null;
		if (!empty($testedByRes['user_signature'])) {
			$testedBySignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $testedByRes['user_signature'];
		}
		if (!empty($reviewedByRes['user_signature'])) {
			$reviewedBySignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $reviewedByRes['user_signature'];
		}
		if (!empty($revisedByRes['user_signature'])) {
			$revisedBySignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $revisedByRes['user_signature'];
		}

		$resultApprovedBy = '';
		$userSignaturePath = null;
		if (!empty($result['result_approved_by'])) {
			$resultApprovedByRes = $usersService->getUserByID($result['result_approved_by'], ['user_name', 'user_signature']);
			if ($resultApprovedByRes) {
				$resultApprovedBy = $resultApprovedByRes['result_approved_by'];
			}
			if (!empty($resultApprovedByRes['user_signature'])) {
				$userSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $resultApprovedByRes['user_signature'];
			}
		}

		if (isset($result['approvedBy']) && trim((string) $result['approvedBy']) !== '') {
			$resultApprovedBy = ($result['approvedBy']);
			$userRes = $usersService->getUserByID($result['result_approved_by'], 'user_signature');
		} else {
			$resultApprovedBy = null;
		}

		$userSignaturePath = null;
		if (!empty($userRes['user_signature'])) {
			$userSignaturePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "users-signature" . DIRECTORY_SEPARATOR . $userRes['user_signature'];
		}
		$_SESSION['aliasPage'] = $page;
		if (!isset($result['labName'])) {
			$result['labName'] = '';
		}
		$draftTextShow = false;
		//Set watermark text
		/* echo "<pre>";
		print_r($mFieldArray);die; */
		if (isset($mFieldArray) && count($mFieldArray) > 0) {
			$counter = count($mFieldArray);
			for ($m = 0; $m < $counter; $m++) {
				if (!isset($result[$mFieldArray[$m]]) || trim((string) $result[$mFieldArray[$m]]) === '' || $result[$mFieldArray[$m]] == null || $result[$mFieldArray[$m]] == '0000-00-00 00:00:00') {
					$draftTextShow = true;
					break;
				}
			}
		}

		// create new PDF document
		$pdf = new GenericTestsResultPDFHelper(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
		if (MiscUtility::isImageValid(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'])) {
			$logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $result['lab_id'] . DIRECTORY_SEPARATOR . $result['facilityLogo'];
		} else {
			$logoPrintInPdf = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'logo' . DIRECTORY_SEPARATOR . $arr['logo'];
		}
		$pdf->setHeading($logoPrintInPdf, $arr['header'], $result['labName'], $title = 'OTHER LAB TESTS PATIENT REPORT', null, $result['test_standard_name']);
		// set document information
		$pdf->SetCreator('InteLIS');
		$pdf->SetTitle('OTHER LAB TESTS PATIENT REPORT');
		//$pdf->SetSubject('TCPDF Tutorial');
		//$pdf->SetKeywords('TCPDF, PDF, example, test, guide');

		// set default header data
		$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);

		// set header and footer fonts
		$pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
		$pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);

		// set default monospaced font
		$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

		// set margins
		$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP + 14, PDF_MARGIN_RIGHT);
		$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

		// set auto page breaks
		$pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

		// set image scale factor
		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);



		// set font
		$pdf->SetFont('helvetica', '', 18);

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
		$age = 'Unknown';
		if (isset($result['patient_dob']) && trim((string) $result['patient_dob']) !== '' && $result['patient_dob'] != '0000-00-00') {
			$todayDate = strtotime(date('Y-m-d'));
			$dob = strtotime((string) $result['patient_dob']);
			$difference = $todayDate - $dob;
			$seconds_per_year = 60 * 60 * 24 * 365;
			$age = round($difference / $seconds_per_year);
		} elseif (isset($result['patient_age_in_years']) && trim((string) $result['patient_age_in_years']) !== '' && trim((string) $result['patient_age_in_years']) > 0) {
			$age = $result['patient_age_in_years'];
		} elseif (isset($result['patient_age_in_months']) && trim((string) $result['patient_age_in_months']) !== '' && trim((string) $result['patient_age_in_months']) > 0) {
			if ($result['patient_age_in_months'] > 1) {
				$age = $result['patient_age_in_months'] . ' months';
			} else {
				$age = $result['patient_age_in_months'] . ' month';
			}
		}

		if (isset($result['sample_collection_date']) && trim((string) $result['sample_collection_date']) !== '' && $result['sample_collection_date'] != '0000-00-00 00:00:00') {
			$expStr = explode(" ", (string) $result['sample_collection_date']);
			$result['sample_collection_date'] = date('d/M/Y', strtotime($expStr[0]));
			$sampleCollectionTime = $expStr[1];
		} else {
			$result['sample_collection_date'] = '';
			$sampleCollectionTime = '';
		}
		$sampleReceivedDate = '';
		$sampleReceivedTime = '';
		if (isset($result['sample_received_at_lab_datetime']) && trim((string) $result['sample_received_at_lab_datetime']) !== '' && $result['sample_received_at_lab_datetime'] != '0000-00-00 00:00:00') {
			$expStr = explode(" ", (string) $result['sample_received_at_lab_datetime']);
			$sampleReceivedDate = date('d/M/Y', strtotime($expStr[0]));
			$sampleReceivedTime = $expStr[1];
		}
		$sampleDispatchDate = '';
		$sampleDispatchTime = '';
		if (isset($result['result_printed_datetime']) && trim((string) $result['result_printed_datetime']) !== '' && $result['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
			$expStr = explode(" ", (string) $result['result_printed_datetime']);
			$sampleDispatchDate = date('d/M/Y', strtotime($expStr[0]));
			$sampleDispatchTime = $expStr[1];
		} else {
			$expStr = explode(" ", (string) $currentTime);
			$sampleDispatchDate = date('d/M/Y', strtotime($expStr[0]));
			$sampleDispatchTime = $expStr[1];
		}

		if (isset($result['sample_tested_datetime']) && trim((string) $result['sample_tested_datetime']) !== '' && $result['sample_tested_datetime'] != '0000-00-00 00:00:00') {
			$expStr = explode(" ", (string) $result['sample_tested_datetime']);
			$result['sample_tested_datetime'] = date('d/M/Y', strtotime($expStr[0])) . " " . $expStr[1];
		} else {
			$result['sample_tested_datetime'] = '';
		}

		if (isset($result['result_reviewed_datetime']) && trim((string) $result['result_reviewed_datetime']) !== '' && $result['result_reviewed_datetime'] != '0000-00-00 00:00:00') {
			$expStr = explode(" ", (string) $result['result_reviewed_datetime']);
			$result['result_reviewed_datetime'] = date('d/M/Y', strtotime($expStr[0])) . " " . $expStr[1];
		} else {
			$result['result_reviewed_datetime'] = '';
		}

		if (isset($result['result_approved_datetime']) && trim((string) $result['result_approved_datetime']) !== '' && $result['result_approved_datetime'] != '0000-00-00 00:00:00') {
			$expStr = explode(" ", (string) $result['result_approved_datetime']);
			$result['result_approved_datetime'] = date('d/M/Y', strtotime($expStr[0])) . " " . $expStr[1];
		} else {
			$result['result_approved_datetime'] = '';
		}

		if (isset($result['last_viral_load_date']) && trim((string) $result['last_viral_load_date']) !== '' && $result['last_viral_load_date'] != '0000-00-00') {
			$result['last_viral_load_date'] = date('d/M/Y', strtotime((string) $result['last_viral_load_date']));
			$result['last_viral_load_date'] = date('d/M/Y', strtotime($result['last_viral_load_date']));
		} else {
			$result['last_viral_load_date'] = '';
		}
		if (!isset($result['patient_gender']) || trim((string) $result['patient_gender']) === '') {
			$result['patient_gender'] = _translate('Unreported');
		}

		$smileyContent = '';
		$showMessage = '';
		$tndMessage = '';
		$messageTextSize = '12px';
		$vlResult = trim((string) $result['result']);

		if (isset($arr['show_smiley']) && trim((string) $arr['show_smiley']) === "no") {
			$smileyContent = '';
		}
		if ($result['result_status'] == REJECTED) {
			$smileyContent = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<img src="/assets/img/cross.png" style="width:50px;" alt="rejected"/>';
		}
		// ---- Professional layout: grouped sections, small grey labels over darker values ----
		// Reflows only the fields it is handed (4 per row), so config-hidden or empty fields never
		// leave blank gaps. Uses the two-row label/value table pattern, which renders aligned in TCPDF.
		$f = static function (string $label, $value): array {
			$value = trim((string) $value);
			return ['label' => $label, 'value' => ($value !== '' ? $value : "\xE2\x80\x94")];
		};
		$renderSection = static function (string $title, array $fields): string {
			$fields = array_values(array_filter($fields, static fn($x) => $x !== null));
			if ($fields === []) {
				return '';
			}
			$perRow = 4;
			$w = (int) floor(100 / $perRow);
			// Controlled gap above each section (a bare <br> is the 18px doc font here, too tall/uneven).
			$out = '<table cellpadding="0" cellspacing="0" style="width:100%;"><tr><td style="font-size:7px;line-height:11px;">&nbsp;</td></tr></table>';
			$out .= '<table cellpadding="3" cellspacing="0" style="width:100%;">';
			$out .= '<tr><td colspan="' . $perRow . '" style="font-size:10px;font-weight:bold;color:#1a1a1a;background-color:#e6e6e6;border:0.6px solid #bbbbbb;line-height:16px;">&nbsp;' . mb_strtoupper($title, 'UTF-8') . '</td></tr>';
			$out .= '</table>';
			// One table per row, label-tr above value-tr. Pad cells carry an explicit small font
			// so they don't inherit the 18px document font and balloon the row height.
			foreach (array_chunk($fields, $perRow) as $chunk) {
				$out .= '<table cellpadding="2" cellspacing="0" style="width:100%;"><tr>';
				foreach ($chunk as $item) {
					$out .= '<td style="width:' . $w . '%;font-size:8px;font-weight:bold;color:#777777;line-height:11px;">' . mb_strtoupper($item['label'], 'UTF-8') . '</td>';
				}
				for ($i = count($chunk); $i < $perRow; $i++) {
					$out .= '<td style="width:' . $w . '%;font-size:8px;line-height:11px;"></td>';
				}
				$out .= '</tr><tr>';
				foreach ($chunk as $item) {
					$out .= '<td style="width:' . $w . '%;font-size:11px;color:#000000;line-height:14px;">' . $item['value'] . '</td>';
				}
				for ($i = count($chunk); $i < $perRow; $i++) {
					$out .= '<td style="width:' . $w . '%;font-size:11px;line-height:14px;"></td>';
				}
				$out .= '</tr></table>';
				// Breathing room between field rows.
				$out .= '<table cellpadding="0" cellspacing="0" style="width:100%;"><tr><td style="font-size:4px;line-height:7px;">&nbsp;</td></tr></table>';
			}
			return $out;
		};

		$patientFname = ($general->crypto('doNothing', $result['patient_first_name'], $result['patient_id']));

		// Section 1 - requesting facility & patient
		$sec1 = [
			$f(_translate('Requesting Health Facility'), $result['facility_name']),
			$f(_translate('Facility Code'), $result['facility_code']),
			$f(_translate('State'), $result['facility_state']),
			$f(_translate('County'), $result['facility_district']),
			$showPatientName ? $f(_translate('Patient Name'), $patientFname) : null,
			$f($patientIdLabel, $result['patient_id']),
			$f(_translate('Reason for Testing'), $result['test_reason']),
			$f(_translate('Age'), $age),
			$f(_translate('Sex'), ucwords(str_replace('_', ' ', (string) $result['patient_gender']))),
		];
		if ($result['patient_gender'] == 'female') {
			$sec1[] = $showPregnancy ? $f(_translate('Pregnancy Status'), str_replace('_', ' ', (string) $result['is_patient_pregnant'])) : null;
			$sec1[] = $showBreastfeeding ? $f(_translate('Breast Feeding'), str_replace('_', ' ', (string) $result['is_patient_breastfeeding'])) : null;
		}
		if (!empty($result['request_clinician_name'])) {
			$sec1[] = $f(_translate('Requesting Clinician'), ucwords((string) $result['request_clinician_name']));
		}
		if (!empty($result['request_clinician_phone_number'])) {
			$sec1[] = $f(_translate('Tel'), $result['request_clinician_phone_number']);
		}
		if (!empty($result['facility_emails'])) {
			$sec1[] = $f(_translate('Email'), $result['facility_emails']);
		}

		// Section 2 - sample
		$rejectedStatus = (!empty($result['is_sample_rejected']) && $result['is_sample_rejected'] == 'yes') ? _translate('Rejected') : _translate('Not Rejected');
		$sec2 = [
			$f(_translate('Sample ID'), $result['sample_code']),
			$showLaboratoryNumber ? $f(_translate('Laboratory Number'), $result['laboratory_number']) : null,
			$f(_translate('Sample Type'), $result['sample_type_name']),
			$f(_translate('Collection Date'), trim($result['sample_collection_date'] . ' ' . $sampleCollectionTime)),
			$f(_translate('Receipt Date'), trim($sampleReceivedDate . ' ' . $sampleReceivedTime)),
			$f(_translate('Test Date'), $result['sample_tested_datetime']),
			$f(_translate('Rejection Status'), $rejectedStatus),
			$f(_translate('Result Release Date'), trim($sampleDispatchDate . ' ' . $sampleDispatchTime)),
		];

		$html = $renderSection(_translate('Requesting Facility & Patient Information'), $sec1);
		$html .= '<br>' . $renderSection(_translate('Sample Information'), $sec2);

		// --- Opt-in custom (dynamic) fields ---
		// A dynamic field appears here only when the admin flagged it show_on_report = 'yes' on the
		// test-type form builder (default off). Fields render grouped by their form section, reusing
		// the same section styling; values come from form_generic.test_type_form. Enabled-but-empty
		// fields are skipped so the report doesn't fill with blanks.
		$testFormConfig = json_decode((string) ($testTypeResult['test_form_config'] ?? ''), true);
		$dynamicValues = json_decode((string) ($result['test_type_form'] ?? ''), true);
		if (is_array($testFormConfig) && is_array($dynamicValues) && $dynamicValues !== []) {
			$sectionTitles = [
				'facilitySection' => _translate('Facility Information'),
				'patientSection'  => _translate('Patient Information'),
				'caseInformation' => _translate('Case Information'),
				'specimenSection' => _translate('Specimen Information'),
				'labSection'      => _translate('Laboratory Information'),
			];
			$fmtDynamic = static function (array $def, $val): string {
				$val = trim((string) ($val ?? ''));
				if ($val === '') {
					return '';
				}
				if (($def['field_type'] ?? '') === 'date') {
					return DateUtility::humanReadableDateFormat($val);
				}
				if (($def['field_type'] ?? '') === 'multiple') {
					return trim(str_replace(['##', ','], ', ', $val), ', ');
				}
				return $val;
			};
			// Keep only enabled + filled fields for a section, ordered by field_order.
			$buildDynamicFields = static function (array $defs) use ($dynamicValues, $fmtDynamic, $f): array {
				uasort($defs, static fn($a, $b): int => ((int) ($a['field_order'] ?? 0)) <=> ((int) ($b['field_order'] ?? 0)));
				$fields = [];
				foreach ($defs as $fieldId => $def) {
					if (!is_array($def) || ($def['show_on_report'] ?? 'no') !== 'yes') {
						continue;
					}
					$value = $fmtDynamic($def, $dynamicValues[$fieldId] ?? null);
					if ($value === '') {
						continue;
					}
					$fields[] = $f($def['field_name'] ?? '', $value);
				}
				return $fields;
			};
			foreach ($sectionTitles as $sectionKey => $sectionTitle) {
				if (empty($testFormConfig[$sectionKey]) || !is_array($testFormConfig[$sectionKey])) {
					continue;
				}
				$html .= $renderSection($sectionTitle, $buildDynamicFields($testFormConfig[$sectionKey]));
			}
			// otherSection holds custom-named groups: {sectionName: {fieldId: def}}
			if (!empty($testFormConfig['otherSection']) && is_array($testFormConfig['otherSection'])) {
				foreach ($testFormConfig['otherSection'] as $otherName => $defs) {
					if (is_array($defs)) {
						$html .= $renderSection((string) $otherName, $buildDynamicFields($defs));
					}
				}
			}
		}
		//echo '<pre>'; print_r($genericTestInfo); die;
		if ($isMultiTest) {
			// One card per test, harmonised with the sections above: a grey heading bar, then
			// grey-label / black-value fields (4 per row, empty fields reflow out). Rejected tests
			// show the rejection reason in place of the result; the result value stays bold.
			$gtFmt = static fn($d): string => (!empty($d) && trim((string) $d) !== '' && $d != '0000-00-00 00:00:00')
				? DateUtility::humanReadableDateFormat((string) $d, true) : '';
			$n = 1;
			foreach ($genericTestInfo as $row) {
				$card = [
					$f(_translate('Laboratory'), $row['lab_name'] ?? ''),
					$f(_translate('Date of Reception'), $gtFmt($row['sample_received_at_lab_datetime'])),
				];
				if (isset($row['is_sample_rejected']) && $row['is_sample_rejected'] === 'yes') {
					$card[] = $f(_translate('Reason for Sample Rejection'), $row['rejectionReasonName'] ?? '');
					$card[] = $f(_translate('Rejection Date'), $gtFmt($row['rejection_on']));
				} else {
					$resultUnitName = (isset($row['result_unit']) && isset($testUnits[$row['result_unit']])) ? ' ' . $testUnits[$row['result_unit']] : '';
					$card[] = $f(_translate('Test'), $row['test_name'] ?? '');
					$card[] = $f(_translate('Result'), '<b>' . ucwords((string) $row['result']) . $resultUnitName . '</b>');
				}
				$card[] = $f(_translate('Tested By'), $row['testedByName'] ?? '');
				$card[] = $f(_translate('Date Tested'), $gtFmt($row['sample_tested_datetime']));
				if (!empty($row['reviewedByName']) || !empty($row['result_reviewed_datetime'])) {
					$card[] = $f(_translate('Reviewed By'), $row['reviewedByName'] ?? '');
					$card[] = $f(_translate('Date Reviewed'), $gtFmt($row['result_reviewed_datetime']));
				}
				if (!empty($row['approvedByName']) || !empty($row['result_approved_datetime'])) {
					$card[] = $f(_translate('Approved By'), $row['approvedByName'] ?? '');
					$card[] = $f(_translate('Date Approved'), $gtFmt($row['result_approved_datetime']));
				}
				$html .= $renderSection(_translate('Laboratory Result') . ' ' . $n, $card);
				if (!empty($row['comments'])) {
					$html .= '<table cellpadding="2" cellspacing="0" style="width:100%;"><tr><td style="font-size:10px;color:#000000;line-height:14px;">' . _translate('Lab Comment') . ': ' . $row['comments'] . '</td></tr></table>';
				}
				$n++;
			}
		} elseif (!empty($genericTestInfo) && $showHideTable === 'yes') {
			$w = 25;
			$titleTest = isset($result['sub_tests']) && !empty($result['sub_tests']) ? "Range" : "Platform";
			/* Test Result Section */
			$innerHtml .= '<table border="0" style="padding:5px;">';
			$innerHtml .= '<tr>
                    <th align="left" style="width:' . $w . '%;line-height:15px;font-size:11px;font-weight:bold;border-right-color:white;border-bottom-color:black;border-top-color:black;">Test Name</th>
                    <th align="left" style="width:' . $w . '%;line-height:15px;font-size:11px;font-weight:bold;border-left-color:white;border-right-color:white;border-bottom-color:black;border-top-color:black;">Result</th>';
			$innerHtml .= '<th align="left" style="width:' . $w . '%;line-height:15px;font-size:11px;font-weight:bold;border-left-color:white;border-right-color:white;border-bottom-color:black;border-top-color:black;">' . $titleTest . '</th>';
			$innerHtml .= '<th align="left" style="width:' . $w . '%;line-height:15px;font-size:11px;font-weight:bold;border-left-color:white;border-bottom-color:black;border-top-color:black;">Unit</th>
               </tr>';
			if (isset($result['sub_tests']) && !empty($result['sub_tests'])) {
				$subTestsList = explode("##", (string) $result['sub_tests']);
				$subTestCnt = count($subTestsList);
				$n = 1;
				foreach ($subTestsList as $key => $subTestName) {
					$finalResult = [];
					$lastLineBorder = '';
					if (($subTestCnt - 1) == $n) {
						$lastLineBorder = 'border-bottom-color:black';
					}
					$innerHtml .= '<tr><td style="line-height:10px;font-size:11px;' . $lastLineBorder . ';">' . $subTestName . '</td>';
					foreach ($genericTestInfo as $indexKey => $rows) {
						if (strtolower($subTestName) == $rows['sub_test_name']) {
							$finalResult['finalResult'] = $rows['final_result'];
							$finalResult['finalResultUnit'] = $testUnits[$rows['final_result_unit']];
							// $finalResult['finalResultInterpretation'] = $rows['final_result_interpretation'];
							$n++;
						}
					}
					$rangeTxt = '<span style="color:black;">';
					if (isset($subTestKey[$subTestName]) && !empty($subTestKey[$subTestName])) {
						if (($testResultsAttribute['quantitative']['high_range'][$subTestKey[$subTestName]] <= $finalResult['finalResult']) || ($testResultsAttribute['quantitative']['threshold_range'][$subTestKey[$subTestName]] < $finalResult['finalResult'])) {
							$highRange = $testResultsAttribute['quantitative']['high_range'][$subTestKey[$subTestName]];
						}
						if ($testResultsAttribute['quantitative']['threshold_range'][$subTestKey[$subTestName]] == $finalResult['finalResult']) {
							$thresholdRange = $testResultsAttribute['quantitative']['threshold_range'][$subTestKey[$subTestName]];
						}
						if (($testResultsAttribute['quantitative']['low_range'][$subTestKey[$subTestName]] >= $finalResult['finalResult']) || ($testResultsAttribute['quantitative']['threshold_range'][$subTestKey[$subTestName]] >= $finalResult['finalResult'])) {
							$lowRange = $testResultsAttribute['quantitative']['low_range'][$subTestKey[$subTestName]];
						}
					}

					if (isset($finalResult['finalResult']) && !empty($finalResult['finalResult'])) {
						$innerHtml .= '<td style="line-height:10px;font-size:11px;' . $lastLineBorder . ';">' . ucwords((string) $finalResult['finalResult']) . '</td>';
					} else {
						$innerHtml .= '<td style="line-height:10px;font-size:11px;' . $lastLineBorder . ';"></td>';
					}

					if ((isset($highRange) && !empty($highRange)) || isset($thresholdRange) && !empty($thresholdRange) || isset($lowRange) && !empty($lowRange)) {
						$innerHtml .= '<td align="left" style="line-height:10px;font-size:11px;' . $lastLineBorder . ';">
                              <span>' . $testResultsAttribute['quantitative']['high_range'][$subTestKey[$subTestName]] . '</span>-
                              <span>' . $testResultsAttribute['quantitative']['threshold_range'][$subTestKey[$subTestName]] . '</span>-
                              <span>' . $testResultsAttribute['quantitative']['low_range'][$subTestKey[$subTestName]] . '</span>
                              </td>';
					} else {
						$innerHtml .= '<td style="line-height:10px;font-size:11px;' . $lastLineBorder . ';">&nbsp;&nbsp;&nbsp; -</td>';
					}
					if (isset($finalResult['finalResultUnit']) && !empty($finalResult['finalResultUnit'])) {
						$innerHtml .= '<td style="line-height:10px;font-size:11px;' . $lastLineBorder . ';">' . $finalResult['finalResultUnit'] . '</td>';
					} else {
						$innerHtml .= '<td style="line-height:10px;font-size:11px;' . $lastLineBorder . ';">&nbsp; -</td>';
					}

					$innerHtml .= '</tr>';
					$n++;
				}
			} else {
				foreach ($genericTestInfo as $indexKey => $rows) {
					$lastLineBorder = '';
					if ((count($genericTestInfo) - 1) == $indexKey) {
						$lastLineBorder = 'border-bottom-color:black';
					}
					$innerHtml .= '<tr>';
					$innerHtml .= '     <td style="line-height:10px;font-size:11px;' . $lastLineBorder . ';">' . $rows['test_name'] . '</td>';
					$innerHtml .= '     <td style="line-height:10px;font-size:11px;' . $lastLineBorder . ';">' . ucwords((string) $rows['result']) . '</td>';
					$innerHtml .= '     <td style="line-height:10px;font-size:11px;' . $lastLineBorder . ';">' . ucwords((string) $rows['testing_platform']) . '</td>';
					$innerHtml .= '     <td style="line-height:10px;font-size:11px;' . $lastLineBorder . ';">' . $testUnits[$rows['result_unit']] . '</td>';
					$innerHtml .= '</tr>';
				}
			}
			$innerHtml .= '</table>';
			$html .= $innerHtml;
		}
		$html .= '<table style="padding:4px 2px 2px 2px;width:100%;">';
		$html .= '<tr>';

		$html .= '<td colspan="3"><br>';
		// Result row: same grey-label / black-value language as the sections, just emphasised with a
		// light tint, a thin border and a larger value. No colour carries meaning (B&W print safe).
		$html .= '<table cellpadding="5" cellspacing="0" style="width:100%;background-color:#f2f2f2;border:0.6px solid #bbbbbb;"><tr>';
		$html .= '<td style="width:20%;font-size:8px;font-weight:bold;color:#777777;line-height:18px;">' . mb_strtoupper(_translate('Result'), 'UTF-8') . '</td>';
		$html .= '<td style="width:80%;font-size:14px;font-weight:bold;color:#000000;text-align:left;line-height:18px;">' . $vlResult . $smileyContent . '</td>';
		$html .= '</tr></table>';
		if ($result['reason_for_sample_rejection'] != '') {
			$html .= '<table cellpadding="2" cellspacing="0" style="width:100%;"><tr><td style="font-size:10px;font-weight:bold;color:#000000;">' . _translate('Rejection Reason') . ': ' . $result['rejection_reason_name'] . '</td></tr></table>';
		}
		if (str_contains(strtolower((string) $result['vl_test_platform']), 'abbott')) {
			$html .= '<table cellpadding="2" cellspacing="0" style="width:100%;"><tr><td style="font-size:8px;color:#777777;">Abbott Linear Detection range: 839 copies/mL - 10 million copies/mL</td></tr></table>';
		}
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

		$html .= '<tr>';
		$html .= '<td colspan="3" style="line-height:2px;border-bottom:0.6px solid #cccccc;"></td>';
		$html .= '</tr>';
		/* $html .= '<tr>';
		$html .= '<td colspan="3" style="line-height:15px;font-size:11px;font-weight:bold;">TEST PLATFORM &nbsp;&nbsp;:&nbsp;&nbsp; <span style="font-weight:normal;">' . ($result['test_platform']) . '</span></td>';
		$html .= '</tr>';

		$html .= '<tr>';
		$html .= '<td colspan="3" style="line-height:2px;border-bottom:0.6px solid #cccccc;"></td>';
		$html .= '</tr>';
		if ($result['is_sample_rejected'] == 'no') {
			 if (!empty($testedBy) && !empty($result['sample_tested_datetime'])) {
				  $html .= '<tr>';
				  $html .= '<td style="font-size:8px;font-weight:bold;color:#777777;line-height:13px;text-align:left;">TESTED BY</td>';
				  $html .= '<td style="font-size:8px;font-weight:bold;color:#777777;line-height:13px;text-align:left;">SIGNATURE</td>';
				  $html .= '<td style="font-size:8px;font-weight:bold;color:#777777;line-height:13px;text-align:left;">DATE</td>';
				  $html .= '</tr>';

				  $html .= '<tr>';
				  $html .= '<td style="font-size:11px;color:#000000;line-height:14px;text-align:left;">' . $testedBy . '</td>';
				  if (!empty($testedBySignaturePath) && MiscUtility::isImageValid(($testedBySignaturePath))) {
					   $html .= '<td style="font-size:11px;color:#000000;line-height:14px;text-align:left;"><img src="' . $testedBySignaturePath . '" style="width:50px;" /></td>';
				  } else {
					   $html .= '<td style="font-size:11px;color:#000000;line-height:14px;text-align:left;"></td>';
				  }
				  $html .= '<td style="font-size:11px;color:#000000;line-height:14px;text-align:left;">' . $result['sample_tested_datetime'] . '</td>';
				  $html .= '</tr>';
			 }
		} */
		// Page-level signature tables: only for single-test reports. In multi-test mode each
		// LABORATORY RESULT block above already carries its own tested/reviewed/approved chain.
		if (!$isMultiTest && !empty($reviewedBy)) {
			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:8px;"></td>';
			$html .= '</tr>';

			$html .= '<tr>';
			$html .= '<td style="font-size:8px;font-weight:bold;color:#777777;line-height:13px;text-align:left;">REVIEWED BY</td>';
			$html .= '<td style="font-size:8px;font-weight:bold;color:#777777;line-height:13px;text-align:left;">SIGNATURE</td>';
			$html .= '<td style="font-size:8px;font-weight:bold;color:#777777;line-height:13px;text-align:left;">DATE</td>';
			$html .= '</tr>';

			$html .= '<tr>';
			$html .= '<td style="font-size:11px;color:#000000;line-height:14px;text-align:left;">' . $reviewedBy . '</td>';
			if ($reviewedBySignaturePath !== null && $reviewedBySignaturePath !== '' && $reviewedBySignaturePath !== '0' && MiscUtility::isImageValid($reviewedBySignaturePath)) {
				$html .= '<td style="font-size:11px;color:#000000;line-height:14px;text-align:left;"><img src="' . $reviewedBySignaturePath . '" style="width:50px;" /></td>';
			} else {
				$html .= '<td style="font-size:11px;color:#000000;line-height:14px;text-align:left;"></td>';
			}
			$html .= '<td style="font-size:11px;color:#000000;line-height:14px;text-align:left;">' . (empty($result['result_reviewed_datetime']) ? $result['sample_tested_datetime'] : $result['result_reviewed_datetime']) . '</td>';
			$html .= '</tr>';
		}

		if (!$isMultiTest && !empty($revisedBy)) {

			$html .= '<tr>';
			$html .= '<td style="font-size:8px;font-weight:bold;color:#777777;line-height:13px;text-align:left;">REPORT REVISED BY</td>';
			$html .= '<td style="font-size:8px;font-weight:bold;color:#777777;line-height:13px;text-align:left;">SIGNATURE</td>';
			$html .= '<td style="font-size:8px;font-weight:bold;color:#777777;line-height:13px;text-align:left;">DATE</td>';
			$html .= '</tr>';

			$html .= '<tr>';
			$html .= '<td style="font-size:11px;color:#000000;line-height:14px;text-align:left;">' . $revisedBy . '</td>';
			if ($revisedBySignaturePath !== null && $revisedBySignaturePath !== '' && $revisedBySignaturePath !== '0' && MiscUtility::isImageValid($revisedBySignaturePath)) {
				$html .= '<td style="font-size:11px;color:#000000;line-height:14px;text-align:left;"><img src="' . $revisedBySignaturePath . '" style="width:70px;" /></td>';
			} else {
				$html .= '<td style="font-size:11px;color:#000000;line-height:14px;text-align:left;"></td>';
			}
			$html .= '<td style="font-size:11px;color:#000000;line-height:14px;text-align:left;">' . date('d/M/Y', strtotime((string) $result['revised_on'])) . '</td>';
			$html .= '</tr>';
		}

		$html .= '<tr>';
		$html .= '<td colspan="3" style="line-height:8px;"></td>';
		$html .= '</tr>';
		if (!$isMultiTest && !empty($resultApprovedBy) && !empty($result['result_approved_datetime'])) {
			$html .= '<tr>';
			$html .= '<td style="font-size:8px;font-weight:bold;color:#777777;line-height:13px;text-align:left;">APPROVED BY</td>';
			$html .= '<td style="font-size:8px;font-weight:bold;color:#777777;line-height:13px;text-align:left;">SIGNATURE</td>';
			$html .= '<td style="font-size:8px;font-weight:bold;color:#777777;line-height:13px;text-align:left;">DATE</td>';
			$html .= '</tr>';

			$html .= '<tr>';
			$html .= '<td style="font-size:11px;color:#000000;line-height:14px;text-align:left;">' . $resultApprovedBy . '</td>';
			if ($userSignaturePath !== null && $userSignaturePath !== '' && $userSignaturePath !== '0' && MiscUtility::isImageValid(($userSignaturePath))) {
				$html .= '<td style="font-size:11px;color:#000000;line-height:14px;text-align:left;"><img src="' . $userSignaturePath . '" style="width:50px;" /></td>';
			} else {
				$html .= '<td style="font-size:11px;color:#000000;line-height:14px;text-align:left;"></td>';
			}

			$html .= '<td style="font-size:11px;color:#000000;line-height:14px;text-align:left;">' . $result['result_approved_datetime'] . '</td>';
			$html .= '</tr>';
		}

		if (!empty($result['lab_tech_comments'])) {

			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:20px;"></td>';
			$html .= '</tr>';
			$html .= '<tr>';
			$html .= '<td colspan="3" style="font-size:11px;color:#000000;line-height:14px;text-align:left;"><strong>Lab Comments:</strong> ' . $result['lab_tech_comments'] . '</td>';
			$html .= '</tr>';

			$html .= '<tr>';
			$html .= '<td colspan="3" style="line-height:2px;"></td>';
			$html .= '</tr>';
		}
		$html .= '<tr>';
		$html .= '<td colspan="3" style="line-height:2px;border-bottom:0.6px solid #cccccc;"></td>';
		$html .= '</tr>';
		$html .= '<tr>';
		$html .= '<td colspan="3">';
		$html .= '<table>';
		$html .= '<tr>';
		$html .= '<td style="font-size:8px;text-align:left;width:55%;color:#777777;"></td>';
		$html .= '<td style="font-size:8px;text-align:right;color:#777777;">' . _translate('Printed on') . ' : ' . $printDate . '&nbsp;&nbsp;' . $printDateTime . '</td>';
		$html .= '</tr>';
		$html .= '</table>';
		$html .= '</td>';
		$html .= '</tr>';
		$html .= '</table>';

		if ($vlResult !== '' || ($vlResult === '' && $result['result_status'] == REJECTED)) {
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
		if (isset($_POST['source']) && trim((string) $_POST['source']) === 'print') {
			//Add event log
			$eventType = 'print-result';
			$action = $_SESSION['userName'] . ' generated the test result PDF with Patient ID/Code ' . $result['patient_id'];
			$resource = 'print-test-result';
			$data = ['event_type' => $eventType, 'action' => $action, 'resource' => $resource, 'date_time' => $currentTime];
			$db->insert($tableName1, $data);
			//Update print datetime in VL tbl.
			$vlQuery = "SELECT result_printed_datetime FROM form_generic as vl WHERE vl.sample_id ='" . $result['sample_id'] . "'";
			$vlResult = $db->query($vlQuery);
			if ($vlResult[0]['result_printed_datetime'] == null || trim((string) $vlResult[0]['result_printed_datetime']) === '' || $vlResult[0]['result_printed_datetime'] == '0000-00-00 00:00:00') {
				$db->where('sample_id', $result['sample_id']);
				$db->update($tableName2, ['result_printed_datetime' => $currentTime, 'result_dispatched_datetime' => $currentTime]);
			}
		}
	}

	if ($pages !== []) {
		$resultFilename = 'InteLIS-LAB-TESTS-RESULT-' . date('d-M-Y-H-i-s') . "-" . MiscUtility::generateRandomString(6) . '.pdf';
		$resultPdf = new PdfConcatenateHelper();
		$resultPdf->mergeFiles($pages, TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename, 50);
	}

	MiscUtility::removeDirectory($pathFront);
	unset($_SESSION['rVal']);
}

echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename);
