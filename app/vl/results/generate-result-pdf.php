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
use App\Helpers\PdfConcatenateHelper;
use App\Registries\ContainerRegistry;
use App\Utilities\LoggerUtility;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

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
if ((!empty($_POST['id'])) || !empty($_POST['sampleCodes'])) {

	$searchQuery = "SELECT vl.*,
					c_f.*,
					imp.i_partner_name,
					rst.sample_name,
					vltr.test_reason_name,
					vl.sample_code,
					vl.control_vl_testing_type,
					vl.coinfection_type,
					vl.reason_for_vl_testing_other,
					l_f.facility_name as labName,
					l_f.report_format as reportFormat,
					l_f.facility_attributes as vl_facility_attributes,
					u_d.user_name as reviewedBy,
					a_u_d.user_name as approvedBy,
                    a_u_d.user_id as approvedByUserId,
					vl.last_modified_by as modified_by,
					r_r_b.user_name as revised,
					l_f.facility_logo as facilityLogo,
					rsrr.rejection_reason_name,
					funding.funding_source_name as funding_source_name,
					r_c_a.recommended_corrective_action_name,
					JSON_UNQUOTE(JSON_EXTRACT(i.approved_by, '$.vl')) AS defaultApprovedBy,
                    JSON_UNQUOTE(JSON_EXTRACT(i.reviewed_by, '$.vl')) AS defaultReviewedBy,
                    i.machine_name AS instrument_machine_name
					FROM form_vl as vl
					LEFT JOIN r_vl_test_reasons as vltr ON vl.reason_for_vl_testing = vltr.test_reason_id
					LEFT JOIN facility_details as c_f ON vl.facility_id = c_f.facility_id
					LEFT JOIN r_vl_sample_type as rst ON rst.sample_id = vl.specimen_type
					LEFT JOIN user_details as u_d ON u_d.user_id = vl.result_reviewed_by
					LEFT JOIN user_details as a_u_d ON a_u_d.user_id = vl.result_approved_by
					LEFT JOIN user_details as r_r_b ON r_r_b.user_id = vl.revised_by
					LEFT JOIN facility_details as l_f ON l_f.facility_id = vl.lab_id
					LEFT JOIN r_implementation_partners as imp ON imp.i_partner_id = vl.implementing_partner
					LEFT JOIN r_funding_sources as funding ON funding.funding_source_id = vl.funding_source
					LEFT JOIN r_vl_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id = vl.reason_for_sample_rejection
					LEFT JOIN r_recommended_corrective_actions as r_c_a ON r_c_a.recommended_corrective_action_id = vl.recommended_corrective_action
					LEFT JOIN instruments as i ON i.instrument_id = vl.instrument_id OR i.machine_name = vl.vl_test_platform
                    LEFT JOIN instrument_machines as im ON im.config_machine_name = vl.vl_test_platform";

	$searchQueryWhere = [];
	if (!empty($_POST['id'])) {
		$searchQueryWhere[] = " vl.vl_sample_id IN(" . $_POST['id'] . ") ";
	}
	if (!empty($_POST['sampleCodes'])) {
		$searchQueryWhere[] = " vl.sample_code IN(" . $_POST['sampleCodes'] . ") ";
	}
	if (!empty($searchQueryWhere)) {
		$searchQuery .= " WHERE " . implode(" AND ", $searchQueryWhere);
	}
	LoggerUtility::log('info', $searchQuery);
	$requestResult = $db->query($searchQuery);
}


if (empty($requestResult) || !$requestResult) {
	return null;
}

$currentDateTime = DateUtility::getCurrentDateTime();

//set print time
$printDate = DateUtility::humanReadableDateFormat($currentDateTime, true);


$fileArray = array(
	COUNTRY\SOUTH_SUDAN => 'pdf/result-pdf-ssudan.php',
	COUNTRY\SIERRA_LEONE => 'pdf/result-pdf-sierraleone.php',
	COUNTRY\DRC => 'pdf/result-pdf-drc.php',
	COUNTRY\CAMEROON => 'pdf/result-pdf-cameroon.php',
	COUNTRY\PNG => 'pdf/result-pdf-png.php',
	COUNTRY\WHO => 'pdf/result-pdf-who.php',
	COUNTRY\RWANDA => 'pdf/result-pdf-rwanda.php',
	COUNTRY\BURKINA_FASO => 'pdf/result-pdf-burkina-faso.php'
);

$randomFolderName = time() . '-' . MiscUtility::generateRandomString(6);

$pathFront = TEMP_PATH . DIRECTORY_SEPARATOR .  $randomFolderName;
MiscUtility::makeDirectory($pathFront);

$resultFilename = '';

$pages = [];
$page = 1;
$_SESSION['aliasPage'] = 1;
foreach ($requestResult as $result) {


	$selectedReportFormats = [];
	if (!empty($result['reportFormat'])) {
		$selectedReportFormats = json_decode((string) $result['reportFormat'], true);
	}

	$fileToInclude = $fileArray[$arr['vl_form']];
	if (!empty($selectedReportFormats) && !empty($selectedReportFormats['vl']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $selectedReportFormats['vl'])) {
		$fileExtension = pathinfo(__DIR__ . DIRECTORY_SEPARATOR . $selectedReportFormats['vl'], PATHINFO_EXTENSION);
		if (($fileExtension === 'php' || $fileExtension === 'phtml')) {
			$fileToInclude = $selectedReportFormats['vl'];
		}
	}
	require(__DIR__ . DIRECTORY_SEPARATOR . $fileToInclude);
}


if (!empty($pages)) {
	$resultPdf = new PdfConcatenateHelper();
	$resultPdf->setFiles($pages);
	$resultPdf->setPrintHeader(false);
	$resultPdf->setPrintFooter(false);
	$resultPdf->concat();
	$resultFilename = 'VLSM-VL-Test-result-' . date('d-M-Y-H-i-s') . "-" . MiscUtility::generateRandomString(6) . '.pdf';
	$resultPdf->Output(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename, "F");
	MiscUtility::removeDirectory($pathFront);
}

echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename);
