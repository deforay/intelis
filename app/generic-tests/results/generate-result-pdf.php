<?php

use Psr\Http\Message\ServerRequestInterface;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

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
					LEFT JOIN r_generic_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id = vl.reason_for_sample_rejection";

	$searchQueryWhere = [];
	if (!in_array(trim((string) $_POST['id']), ['', '0'], true)) {
		$searchQueryWhere[] = " vl.sample_id IN(" . $_POST['id'] . ") ";
	}

	if (isset($_POST['sampleCodes']) && !in_array(trim((string) $_POST['sampleCodes']), ['', '0'], true)) {
		$searchQueryWhere[] = " vl.sample_code IN(" . $_POST['sampleCodes'] . ") ";
	}
	if ($searchQueryWhere !== []) {
		$searchQuery .= " WHERE " . implode(" AND ", $searchQueryWhere);
	}
	// echo ($searchQuery);die;
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

include(__DIR__ . '/result-pdf.php');
