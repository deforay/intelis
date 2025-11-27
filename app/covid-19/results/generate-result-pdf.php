<?php


use Psr\Http\Message\ServerRequestInterface;
use const COUNTRY\SOUTH_SUDAN;
use const COUNTRY\SIERRA_LEONE;
use const COUNTRY\DRC;
use const COUNTRY\CAMEROON;
use const COUNTRY\PNG;
use const COUNTRY\WHO;
use const COUNTRY\RWANDA;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\Covid19Service;
use App\Services\DatabaseService;
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
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$arr = $general->getGlobalConfig();
$sc = $general->getSystemConfig();
$systemConfig = array_merge($sc, SYSTEM_CONFIG);

//set mField Array
$mFieldArray = [];
if (isset($arr['r_mandatory_fields']) && trim((string) $arr['r_mandatory_fields']) !== '') {
	$mFieldArray = explode(',', (string) $arr['r_mandatory_fields']);
}

//set query
$allQuery = $_SESSION['covid19PrintQuery'];
if (isset($_POST['id']) && trim((string) $_POST['id']) !== '') {

	$searchQuery = "SELECT vl.*,f.*,
				l.facility_name as labName,
				l.facility_emails as labEmail,
				l.address as labAddress,
				l.facility_mobile_numbers as labPhone,
				l.facility_state as labState,
				l.facility_district as labCounty,
				l.facility_logo as facilityLogo,
				l.report_format as reportFormat,
				l.facility_attributes as vl_facility_attributes,
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

$currentDateTime = DateUtility::getCurrentDateTime();

$_SESSION['aliasPage'] = 1;


/* Test Results */
if (isset($_POST['type']) && $_POST['type'] == "qr") {
	try {
		$general->trackQRPageViews('covid19', $requestResult[0]['covid19_id'], $requestResult[0]['sample_code']);
	} catch (Exception $exc) {
		error_log($exc->getMessage());
	}
}

//print_r($requestResult);die;
//header and footer



$countryFormId = (int) $general->getGlobalConfig('vl_form');

$fileArray = [SOUTH_SUDAN => 'pdf/result-pdf-ssudan.php', SIERRA_LEONE => 'pdf/result-pdf-sierraleone.php', DRC => 'pdf/result-pdf-drc-1.php', CAMEROON => 'pdf/result-pdf-cameroon.php', PNG => 'pdf/result-pdf-png.php', WHO => 'pdf/result-pdf-who.php', RWANDA => 'pdf/result-pdf-rwanda.php'];


$resultFilename = '';
if (!empty($requestResult)) {

	$pathFront = TEMP_PATH . DIRECTORY_SEPARATOR . time() . '-' . MiscUtility::generateRandomString(6);
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
		$expStr = explode(" ", (string) $printedTime);
		$printDate = DateUtility::humanReadableDateFormat($expStr[0]);
		$printDateTime = $expStr[1];

		if (($general->isLISInstance()) && empty($result['result_printed_on_lis_datetime'])) {
			$pData = ['result_printed_on_lis_datetime' => $currentDateTime, 'result_printed_datetime' => $currentDateTime];
			$db->where('covid19_id', $result['covid19_id']);
			$id = $db->update('form_covid19', $pData);
		} elseif (($general->isSTSInstance()) && empty($result['result_printed_on_sts_datetime'])) {
			$pData = ['result_printed_on_sts_datetime' => $currentDateTime, 'result_printed_datetime' => $currentDateTime];
			$db->where('covid19_id', $result['covid19_id']);
			$id = $db->update('form_covid19', $pData);
		}

		/** @var Covid19Service $covid19Service */
		$covid19Service = ContainerRegistry::get(Covid19Service::class);
		$covid19Results = $covid19Service->getCovid19Results();

		$covid19TestQuery = "SELECT * FROM covid19_tests where covid19_id= ? ORDER BY test_id ASC";
		$covid19TestInfo = $db->rawQuery($covid19TestQuery, [$result['covid19_id']]);
		// Lab Details
		$labQuery = "SELECT * FROM facility_details WHERE facility_id= ?";
		$labInfo = $db->rawQueryOne($labQuery, [$result['lab_id']]);

		$facilityQuery = "SELECT * FROM form_covid19 as c19
							INNER JOIN facility_details as fd ON c19.facility_id=fd.facility_id
							WHERE covid19_id= ?";
		$facilityInfo = $db->rawQueryOne($facilityQuery, [$result['covid19_id']]);
		// echo "<pre>";print_r($covid19TestInfo);die;

		$patientFname = $result['patient_name'] ?? null;
		$patientLname = $result['patient_surname'] ?? null;

		$signQuery = "SELECT * FROM lab_report_signatories
						WHERE lab_id=? AND
						test_types LIKE '%covid19%' AND
						signatory_status LIKE 'active'
						ORDER BY display_order ASC";
		$signResults = $db->rawQuery($signQuery, [$result['lab_id']]);
		$currentDateTime = DateUtility::getCurrentDateTime();
		$_SESSION['aliasPage'] = $page;
		if (!isset($result['labName'])) {
			$result['labName'] = '';
		}
		$draftTextShow = false;
		//Set watermark text
		$counter = count($mFieldArray);
		//Set watermark text
		for ($m = 0; $m < $counter; $m++) {
			if (!isset($result[$mFieldArray[$m]]) || trim((string) $result[$mFieldArray[$m]]) === '' || $result[$mFieldArray[$m]] == null || $result[$mFieldArray[$m]] == '0000-00-00 00:00:00') {
				$draftTextShow = true;
				break;
			}
		}

		$selectedReportFormats = [];
		if (isset($result['reportFormat']) && $result['reportFormat'] != "") {
			$selectedReportFormats = json_decode((string) $result['reportFormat'], true);
		}
		if (!empty($selectedReportFormats) && !empty($selectedReportFormats['covid19']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $selectedReportFormats['covid19'])) {
			require($selectedReportFormats['covid19']);
		} else {
			require($fileArray[$countryFormId]);
		}
	}
	if ($pages !== []) {
		$resultFilename = 'COVID-19-Test-result-' . date('d-M-Y-H-i-s') . "-" . MiscUtility::generateRandomString(6) . '.pdf';
		$resultPdf = new PdfConcatenateHelper();
		$resultPdf->mergeFiles($pages, TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename, 50);

	}
	MiscUtility::removeDirectory($pathFront);
}
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename);
