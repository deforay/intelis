<?php


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
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = $request->getParsedBody();

$tableName1 = "activity_log";
$tableName2 = "form_vl";

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$arr = $general->getGlobalConfig();
//set mField Array
$mFieldArray = [];
if (isset($arr['r_mandatory_fields']) && trim((string) $arr['r_mandatory_fields']) != '') {
	$mFieldArray = explode(',', (string) $arr['r_mandatory_fields']);
}

$requestResult = null;
if ((isset($_POST['id']) && !empty(trim((string) $_POST['id']))) || (isset($_POST['sampleCodes']) && !empty(trim((string) $_POST['sampleCodes'])))) {

	$searchQuery = "SELECT vl.*,
                  f.*,
                  imp.i_partner_name,
                  rst.sample_name,
                  vltr.test_reason_name,
				  vl.sample_code,
				  vl.control_vl_testing_type,
				  vl.coinfection_type,
				  vl.reason_for_vl_testing_other,
                  l.facility_name as labName,
                  u_d.user_name as reviewedBy,
                  a_u_d.user_name as approvedBy,
				  vl.last_modified_by as modified_by,
                  r_r_b.user_name as revised,
                  l.facility_logo as facilityLogo,
                  rsrr.rejection_reason_name,
                  r_c_a.recommended_corrective_action_name
                  FROM form_vl as vl
                  LEFT JOIN r_vl_test_reasons as vltr ON vl.reason_for_vl_testing = vltr.test_reason_id
                  LEFT JOIN facility_details as f ON vl.facility_id = f.facility_id
                  LEFT JOIN r_vl_sample_type as rst ON rst.sample_id = vl.sample_type
                  LEFT JOIN user_details as u_d ON u_d.user_id = vl.result_reviewed_by
                  LEFT JOIN user_details as a_u_d ON a_u_d.user_id = vl.result_approved_by
                  LEFT JOIN user_details as r_r_b ON r_r_b.user_id = vl.revised_by
                  LEFT JOIN facility_details as l ON l.facility_id = vl.lab_id
                  LEFT JOIN r_implementation_partners as imp ON imp.i_partner_id = vl.implementing_partner
                  LEFT JOIN r_vl_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id = vl.reason_for_sample_rejection
                  LEFT JOIN r_recommended_corrective_actions as r_c_a ON r_c_a.recommended_corrective_action_id=vl.recommended_corrective_action";

	$searchQueryWhere = [];
	if (!empty(trim((string) $_POST['id']))) {
		$searchQueryWhere[] = " vl.vl_sample_id IN(" . $_POST['id'] . ") ";
	}

	if (isset($_POST['sampleCodes']) && !empty(trim((string) $_POST['sampleCodes']))) {
		$searchQueryWhere[] = " vl.sample_code IN(" . $_POST['sampleCodes'] . ") ";
	}
	if (!empty($searchQueryWhere)) {
		$searchQuery .= " WHERE " . implode(" AND ", $searchQueryWhere);
	}
	//echo ($searchQuery);
	$requestResult = $db->query($searchQuery);
}


if (empty($requestResult) || !$requestResult) {
	return null;
}

//set print time
$printDate = DateUtility::humanReadableDateFormat(date('Y-m-d H:i:s'), true);

if (($_SESSION['instanceType'] == 'vluser') && empty($requestResult[0]['result_printed_on_lis_datetime'])) {
	$pData = array('result_printed_on_lis_datetime' => date('Y-m-d H:i:s'));
	$db->where('vl_sample_id', $_POST['id']);
	$id = $db->update('form_vl', $pData);
} elseif (($_SESSION['instanceType'] == 'remoteuser') && empty($requestResult[0]['result_printed_on_sts_datetime'])) {
	$pData = array('result_printed_on_sts_datetime' => date('Y-m-d H:i:s'));
	$db->where('vl_sample_id', $_POST['id']);
	$id = $db->update('form_vl', $pData);
}

$_SESSION['nbPages'] = sizeof($requestResult);
$_SESSION['aliasPage'] = 1;

if ($arr['vl_form'] == COUNTRY\SOUTH_SUDAN) {
	include('pdf/result-pdf-ssudan.php');
} elseif ($arr['vl_form'] == COUNTRY\SIERRA_LEONE) {
	include('pdf/result-pdf-sierraleone.php');
} elseif ($arr['vl_form'] == COUNTRY\DRC) {
	include('pdf/result-pdf-drc.php');
} elseif ($arr['vl_form'] == COUNTRY\CAMEROON) {
	include('pdf/result-pdf-cameroon-cresar.php');
} elseif ($arr['vl_form'] == COUNTRY\PNG) {
	include('pdf/result-pdf-png.php');
} elseif ($arr['vl_form'] == COUNTRY\RWANDA) {
	include('pdf/result-pdf-rwanda.php');
}
