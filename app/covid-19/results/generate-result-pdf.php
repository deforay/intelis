<?php


use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\Covid19Service;
use App\Helpers\PdfConcatenateHelper;
use App\Registries\ContainerRegistry;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

$tableName1 = "activity_log";
$tableName2 = "form_covid19";
/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$arr = $general->getGlobalConfig();
$sc = $general->getSystemConfig();
$systemConfig = array_merge($sc, SYSTEM_CONFIG);

//set mField Array
$mFieldArray = [];
if (isset($arr['r_mandatory_fields']) && trim((string) $arr['r_mandatory_fields']) != '') {
	$mFieldArray = explode(',', (string) $arr['r_mandatory_fields']);
}

//set query
$allQuery = $_SESSION['covid19PrintQuery'];
if (isset($_POST['id']) && trim((string) $_POST['id']) != '') {

	$searchQuery = "SELECT vl.*,f.*,
				l.facility_name as labName,
				l.facility_emails as labEmail,
				l.address as labAddress,
				l.facility_mobile_numbers as labPhone,
				l.facility_state as labState,
				l.facility_district as labCounty,
				l.facility_logo as facilityLogo,
				l.report_format as reportFormat,
				l.header_text as labHeaderText,
				rip.i_partner_name,
				rsrr.rejection_reason_name ,
				u_d.user_name as reviewedBy,
				a_u_d.user_name as approvedBy,
				rfs.funding_source_name,
				c.iso_name as nationality,
				rst.sample_name,
				vl.data_sync as dataSync,
				testres.test_reason_name as reasonForTesting
				FROM form_covid19 as vl
				LEFT JOIN r_countries as c ON vl.patient_nationality=c.id
				LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
				LEFT JOIN facility_details as l ON l.facility_id=vl.lab_id
				LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by
				LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by
				LEFT JOIN r_covid19_test_reasons as testres ON testres.test_reason_id=vl.reason_for_covid19_test
				LEFT JOIN r_covid19_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=vl.reason_for_sample_rejection
				LEFT JOIN r_implementation_partners as rip ON rip.i_partner_id=vl.implementing_partner
				LEFT JOIN r_funding_sources as rfs ON rfs.funding_source_id=vl.funding_source
				LEFT JOIN r_covid19_sample_type as rst ON rst.sample_id=vl.specimen_type
				WHERE vl.covid19_id IN(" . $_POST['id'] . ")";
} else {
	$searchQuery = $allQuery;
}
//echo($searchQuery);die;
$requestResult = $db->query($searchQuery);

if (($_SESSION['instanceType'] == 'vluser') && empty($requestResult[0]['result_printed_on_lis_datetime'])) {
	$pData = array('result_printed_on_lis_datetime' => date('Y-m-d H:i:s'));
	$db->where('covid19_id', $_POST['id']);
	$id = $db->update('form_covid19', $pData);
} elseif (($_SESSION['instanceType'] == 'remoteuser') && empty($requestResult[0]['result_printed_on_sts_datetime'])) {
	$pData = array('result_printed_on_sts_datetime' => date('Y-m-d H:i:s'));
	$db->where('covid19_id', $_POST['id']);
	$id = $db->update('form_covid19', $pData);
}

/* Test Results */
if (isset($_POST['type']) && $_POST['type'] == "qr") {
	try {
		$general->trackQRPageViews('covid19', $requestResult[0]['covid19_id'], $requestResult[0]['sample_code']);
	} catch (Exception $exc) {
		error_log($exc->getMessage());
		error_log($exc->getTraceAsString());
	}
}

$_SESSION['nbPages'] = sizeof($requestResult);
$_SESSION['aliasPage'] = 1;

//print_r($requestResult);die;
//header and footer




$fileArray = array(
	COUNTRY\SOUTH_SUDAN => 'pdf/result-pdf-ssudan.php',
	COUNTRY\SIERRA_LEONE => 'pdf/result-pdf-sierraleone.php',
	COUNTRY\DRC => 'pdf/result-pdf-drc.php',
	COUNTRY\CAMEROON => 'pdf/result-pdf-cameroon.php',
	COUNTRY\PNG => 'pdf/result-pdf-png.php',
	COUNTRY\WHO => 'pdf/result-pdf-who.php',
	COUNTRY\RWANDA => 'pdf/result-pdf-rwanda.php',
);

$resultFilename = '';
if (!empty($requestResult)) {
	$_SESSION['rVal'] = $general->generateRandomString(6);
	$pathFront = TEMP_PATH . DIRECTORY_SEPARATOR .  $_SESSION['rVal'];
	MiscUtility::makeDirectory($pathFront);
	$pages = [];
	$page = 1;
	foreach ($requestResult as $result) {
		//set print time
		if (isset($result['result_printed_datetime']) && $result['result_printed_datetime'] != "") {
			$printedTime = date('Y-m-d H:i:s', strtotime((string) $result['result_printed_datetime']));
		} else {
			$printedTime = DateUtility::getCurrentDateTime();
		}
		$expStr = explode(" ", $printedTime);
		$printDate = DateUtility::humanReadableDateFormat($expStr[0]);
		$printDateTime = $expStr[1];

		/** @var Covid19Service $covid19Service */
		$covid19Service = ContainerRegistry::get(Covid19Service::class);
		$covid19Results = $covid19Service->getCovid19Results();
		$countryFormId = (int) $general->getGlobalConfig('vl_form');

		$covid19TestQuery = "SELECT * from covid19_tests where covid19_id= " . $result['covid19_id'] . " ORDER BY test_id ASC";
		$covid19TestInfo = $db->rawQuery($covid19TestQuery);
		// Lab Details
		$labQuery = "SELECT * from facility_details where facility_id= " . $result['lab_id'] . " LIMIT 1";
		$labInfo = $db->rawQueryOne($labQuery);

		$facilityQuery = "SELECT * from form_covid19 as c19 INNER JOIN facility_details as fd ON c19.facility_id=fd.facility_id where covid19_id= " . $result['covid19_id'] . " GROUP BY fd.facility_id LIMIT 1";
		$facilityInfo = $db->rawQueryOne($facilityQuery);
		// echo "<pre>";print_r($covid19TestInfo);die;

		$patientFname = ($general->crypto('doNothing', $result['patient_name'], $result['patient_id']));
		$patientLname = ($general->crypto('doNothing', $result['patient_surname'], $result['patient_id']));

		$signQuery = "SELECT * from lab_report_signatories where lab_id=? AND test_types like '%covid19%' AND signatory_status like 'active' ORDER BY display_order ASC";
		$signResults = $db->rawQuery($signQuery, array($result['lab_id']));
		$currentDateTime = DateUtility::getCurrentDateTime();
		$_SESSION['aliasPage'] = $page;
		if (!isset($result['labName'])) {
			$result['labName'] = '';
		}
		$draftTextShow = false;
		//Set watermark text
		for ($m = 0; $m < count($mFieldArray); $m++) {
			if (!isset($result[$mFieldArray[$m]]) || trim((string) $result[$mFieldArray[$m]]) == '' || $result[$mFieldArray[$m]] == null || $result[$mFieldArray[$m]] == '0000-00-00 00:00:00') {
				$draftTextShow = true;
				break;
			}
		}

		$selectedReportFormats = [];
		if (isset($result['reportFormat']) && $result['reportFormat'] != "") {
			$selectedReportFormats = json_decode((string) $result['reportFormat'], true);
		}
		if (!empty($selectedReportFormats) && !empty($selectedReportFormats['covid19'])) {
			require_once($selectedReportFormats['covid19']);
		} else {
			require_once($fileArray[$arr['vl_form']]);
		}
	}
	if (!empty($pages)) {
		$resultPdf = new PdfConcatenateHelper();
		$resultPdf->setFiles($pages);
		$resultPdf->setPrintHeader(false);
		$resultPdf->setPrintFooter(false);
		$resultPdf->concat();
		$resultFilename = 'COVID-19-Test-result-' . date('d-M-Y-H-i-s') . "-" . $general->generateRandomString(6) . '.pdf';
		$resultPdf->Output(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename, "F");
		MiscUtility::removeDirectory($pathFront);
		unset($_SESSION['rVal']);
	}
}
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename);
