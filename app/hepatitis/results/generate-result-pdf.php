<?php

use const COUNTRY\DRC;
use const COUNTRY\PNG;
use const COUNTRY\WHO;
use const COUNTRY\RWANDA;
use const COUNTRY\CAMEROON;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use const COUNTRY\SOUTH_SUDAN;
use App\Services\CommonService;
use const COUNTRY\SIERRA_LEONE;
use App\Services\DatabaseService;
use App\Helpers\PdfConcatenateHelper;
use App\Registries\ContainerRegistry;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);


$tableName1 = "activity_log";
$tableName2 = "form_hepatitis";
/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$formId = (int) $general->getGlobalConfig('vl_form');

//set print time
$printedTime = date('Y-m-d H:i:s');
$expStr = explode(" ", $printedTime);
$printDate = DateUtility::humanReadableDateFormat($expStr[0]);
$printDateTime = $expStr[1];
$mFieldArray = [];
if (isset($arr['r_mandatory_fields']) && trim((string) $arr['r_mandatory_fields']) !== '') {
	$mFieldArray = explode(',', (string) $arr['r_mandatory_fields']);
}
//set query
$allQuery = $_SESSION['hepatitisPrintQuery'];
if (isset($_POST['id']) && trim((string) $_POST['id']) !== '') {

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

$currentDateTime = DateUtility::getCurrentDateTime();

foreach ($requestResult as $requestRow) {
	if (($general->isLISInstance()) && empty($requestRow['result_printed_on_lis_datetime'])) {
		$pData = ['result_printed_on_lis_datetime' => $currentDateTime, 'result_printed_datetime' => $currentDateTime];
		$db->where('hepatitis_id', $requestRow['hepatitis_id']);
		$id = $db->update('form_hepatitis', $pData);
	} elseif (($general->isSTSInstance()) && empty($requestRow['result_printed_on_sts_datetime'])) {
		$pData = ['result_printed_on_sts_datetime' => $currentDateTime, 'result_printed_datetime' => $currentDateTime];
		$db->where('hepatitis_id', $requestRow['hepatitis_id']);
		$id = $db->update('form_hepatitis', $pData);
	}
}



/* Test Results */
$pages = [];
$page = 1;
$_SESSION['aliasPage'] = $page;
//print_r($requestResult);die;


$fileArray = [SOUTH_SUDAN => 'pdf/result-pdf-ssudan.php', SIERRA_LEONE => 'pdf/result-pdf-sierraleone.php', DRC => 'pdf/result-pdf-drc.php', CAMEROON => 'pdf/result-pdf-cameroon.php', PNG => 'pdf/result-pdf-png.php', WHO => 'pdf/result-pdf-who.php', RWANDA => 'pdf/result-pdf-rwanda.php'];

require_once($fileArray[$formId]);


if ($pages !== []) {
	$resultFilename = 'InteLIS-Hepatitis-Test-result-' . date('d-M-Y-H-i-s') . "-" . MiscUtility::generateRandomString(6) . '.pdf';
	$resultPdf = new PdfConcatenateHelper();
	$resultPdf->mergeFiles($pages, TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename, 50);
}
