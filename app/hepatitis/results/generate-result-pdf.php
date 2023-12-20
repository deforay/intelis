<?php

use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
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
if (isset($arr['r_mandatory_fields']) && trim((string) $arr['r_mandatory_fields']) != '') {
	$mFieldArray = explode(',', (string) $arr['r_mandatory_fields']);
}
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
				LEFT JOIN r_hepatitis_sample_type as rst ON rst.hepatitis_id=vl.specimen_type
				WHERE vl.hepatitis_id IN(" . $_POST['id'] . ")";
} else {
	$searchQuery = $allQuery;
}
// echo($searchQuery);die;
$requestResult = $db->query($searchQuery);

$currentDateTime = DateUtility::getCurrentDateTime();

foreach ($requestResult as $requestRow) {
	if (($_SESSION['instanceType'] == 'vluser') && empty($requestRow['result_printed_on_lis_datetime'])) {
		$pData = array('result_printed_on_lis_datetime' => $currentDateTime);
		$db->where('hepatitis_id', $requestRow['hepatitis_id']);
		$id = $db->update('form_hepatitis', $pData);
	} elseif (($_SESSION['instanceType'] == 'remoteuser') && empty($requestRow['result_printed_on_sts_datetime'])) {
		$pData = array('result_printed_on_sts_datetime' => $currentDateTime);
		$db->where('hepatitis_id', $requestRow['hepatitis_id']);
		$id = $db->update('form_hepatitis', $pData);
	}
}



/* Test Results */
$_SESSION['aliasPage'] = 1;
//print_r($requestResult);die;


$fileArray = array(
	COUNTRY\SOUTH_SUDAN => 'pdf/result-pdf-ssudan.php',
	COUNTRY\SIERRA_LEONE => 'pdf/result-pdf-sierraleone.php',
	COUNTRY\DRC => 'pdf/result-pdf-drc.php',
	COUNTRY\CAMEROON => 'pdf/result-pdf-cameroon.php',
	COUNTRY\PNG => 'pdf/result-pdf-png.php',
	COUNTRY\WHO => 'pdf/result-pdf-who.php',
	COUNTRY\RWANDA => 'pdf/result-pdf-rwanda.php'
);

require_once($fileArray[$formId]);
