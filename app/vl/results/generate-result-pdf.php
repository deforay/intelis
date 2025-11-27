<?php


ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);


use Psr\Http\Message\ServerRequestInterface;
use const COUNTRY\SOUTH_SUDAN;
use const COUNTRY\SIERRA_LEONE;
use const COUNTRY\DRC;
use const COUNTRY\CAMEROON;
use const COUNTRY\PNG;
use const COUNTRY\WHO;
use const COUNTRY\RWANDA;
use const COUNTRY\BURKINA_FASO;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Helpers\PdfConcatenateHelper;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$arr = $general->getGlobalConfig();
//set mField Array
$mFieldArray = [];
if (isset($arr['r_mandatory_fields']) && trim((string) $arr['r_mandatory_fields']) !== '') {
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
					vl.reason_for_result_changes,
					l_f.facility_name as labName,
					l_f.report_format as reportFormat,
					l_f.facility_attributes as vl_facility_attributes,
					reviewer_user.user_name as reviewedBy,
					reviewer_user.user_id as reviewedByUserId,
					reviewer_user.user_signature as reviewedBySignature,
					approver_user.user_name as approvedBy,
                    approver_user.user_id as approvedByUserId,
                    approver_user.user_signature as approvedBySignature,
					vl.last_modified_by as modifiedBy,
					reviser_user.user_name as revisedBy,
					reviser_user.user_id as revisedByUserId,
					reviser_user.user_signature as revisedBySignature,
					tester_user.user_name as testedBy,
					tester_user.user_id as testedByUserId,
					tester_user.user_signature as testedBySignature,
					l_f.facility_logo as facilityLogo,
					rsrr.rejection_reason_name,
					funding.funding_source_name as funding_source_name,
					r_c_a.recommended_corrective_action_name,
					JSON_UNQUOTE(JSON_EXTRACT(i.approved_by, '$.vl')) AS defaultApprovedBy,
                    JSON_UNQUOTE(JSON_EXTRACT(i.reviewed_by, '$.vl')) AS defaultReviewedBy,
                    i.machine_name AS instrument_machine_name,
					i.lower_limit AS instrument_lower_limit,
					i.higher_limit AS instrument_higher_limit
					FROM form_vl as vl
					LEFT JOIN r_vl_test_reasons as vltr ON vl.reason_for_vl_testing = vltr.test_reason_id
					LEFT JOIN facility_details as c_f ON vl.facility_id = c_f.facility_id
					LEFT JOIN r_vl_sample_type as rst ON rst.sample_id = vl.specimen_type
					LEFT JOIN user_details as reviewer_user ON reviewer_user.user_id = vl.result_reviewed_by
					LEFT JOIN user_details as approver_user ON approver_user.user_id = vl.result_approved_by
					LEFT JOIN user_details as reviser_user ON reviser_user.user_id = vl.revised_by
					LEFT JOIN user_details as tester_user ON tester_user.user_id = vl.tested_by
					LEFT JOIN facility_details as l_f ON l_f.facility_id = vl.lab_id
					LEFT JOIN r_implementation_partners as imp ON imp.i_partner_id = vl.implementing_partner
					LEFT JOIN r_funding_sources as funding ON funding.funding_source_id = vl.funding_source
					LEFT JOIN r_vl_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id = vl.reason_for_sample_rejection
					LEFT JOIN r_recommended_corrective_actions as r_c_a ON r_c_a.recommended_corrective_action_id = vl.recommended_corrective_action
					LEFT JOIN instruments as i ON i.instrument_id = vl.instrument_id";

	$searchQueryWhere = [];
	if (!empty($_POST['id'])) {
		$searchQueryWhere[] = " vl.vl_sample_id IN(" . $_POST['id'] . ") ";
	}
	if (!empty($_POST['sampleCodes'])) {
		$searchQueryWhere[] = " vl.sample_code IN(" . $_POST['sampleCodes'] . ") ";
	}
	if ($searchQueryWhere !== []) {
		$searchQuery .= " WHERE " . implode(" AND ", $searchQueryWhere);
	}
	$requestResult = $db->query($searchQuery);
}

if (empty($requestResult) || !$requestResult) {
	return null;
}

$currentDateTime = DateUtility::getCurrentDateTime();

//set print time
$printDate = DateUtility::humanReadableDateFormat($currentDateTime, true);

$tableName1 = "activity_log";
$tableName2 = "form_vl";

$fileArray = [
	SOUTH_SUDAN => 'pdf/result-pdf-ssudan.php',
	SIERRA_LEONE => 'pdf/result-pdf-sierraleone.php',
	DRC => 'pdf/result-pdf-drc.php',
	CAMEROON => 'pdf/result-pdf-cameroon.php',
	PNG => 'pdf/result-pdf-png.php',
	WHO => 'pdf/result-pdf-who.php',
	RWANDA => 'pdf/result-pdf-rwanda.php',
	BURKINA_FASO => 'pdf/result-pdf-burkina-faso.php'
];

$pathFront = TEMP_PATH . DIRECTORY_SEPARATOR . time() . '-' . MiscUtility::generateRandomString(6);
MiscUtility::makeDirectory($pathFront);

$resultFilename = '';

$pages = [];
$page = 1;
$_SESSION['aliasPage'] = 1;

foreach ($requestResult as $result) {

	if (($general->isLISInstance()) && empty($result['result_printed_on_lis_datetime'])) {
		$pData = [
			'result_printed_on_lis_datetime' => $currentDateTime,
			'result_printed_datetime' => $currentDateTime
		];
		$db->where('vl_sample_id', $result['vl_sample_id']);
		$id = $db->update('form_vl', $pData);
	} elseif (($general->isSTSInstance()) && empty($result['result_printed_on_sts_datetime'])) {
		$pData = [
			'result_printed_on_sts_datetime' => $currentDateTime,
			'result_printed_datetime' => $currentDateTime
		];
		$db->where('vl_sample_id', $result['vl_sample_id']);
		$id = $db->update('form_vl', $pData);
	}

	$selectedReportFormats = [];
	if (!empty($result['reportFormat'])) {
		$selectedReportFormats = json_decode((string) $result['reportFormat'], true);
	}

	$fileToInclude = $fileArray[$arr['vl_form']];
	if (!empty($selectedReportFormats) && !empty($selectedReportFormats['vl'])) {
		$reportFilePath = realpath(__DIR__ . DIRECTORY_SEPARATOR . $selectedReportFormats['vl']);
		if ($reportFilePath !== false && is_file($reportFilePath)) {
			$fileExtension = pathinfo($reportFilePath, PATHINFO_EXTENSION);
			if ($fileExtension === 'php' || $fileExtension === 'phtml') {
				$fileToInclude = $reportFilePath;
			}
		}
	}
	require $fileToInclude;
}

$resultFilename = 'HIV-VL-Test-Result-' . date('d-M-Y-H-i-s') . "-" . MiscUtility::generateRandomString(6) . '.pdf';

if ($pages !== []) {
	$finalMerger = new PdfConcatenateHelper();
	$finalMerger->mergeFiles($pages, TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename, 50);
}

MiscUtility::removeDirectory($pathFront);
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename);
