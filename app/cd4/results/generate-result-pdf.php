<?php

use Laminas\Diactoros\ServerRequest;
use const COUNTRY\SOUTH_SUDAN;
use const COUNTRY\SIERRA_LEONE;
use const COUNTRY\DRC;
use const COUNTRY\CAMEROON;
use const COUNTRY\PNG;
use const COUNTRY\WHO;
use const COUNTRY\RWANDA;

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

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$tableName1 = "activity_log";
$tableName2 = "form_cd4";

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
					f.*,
					imp.i_partner_name,
					rst.sample_name,
					vltr.test_reason_name,
					vl.sample_code,
					l.facility_name as labName,
					l.report_format as reportFormat,
					f.facility_attributes as vl_facility_attributes,
					l.facility_attributes,
					reviewer_user.user_name as reviewedBy,
					approver_user.user_name as approvedBy,
					vl.last_modified_by as modifiedBy,
					reviser_user.user_name as revised,
					l.facility_logo as facilityLogo,
					rsrr.rejection_reason_name,
					funding.funding_source_name as funding_source_name,
					r_c_a.recommended_corrective_action_name
					FROM form_cd4 as vl
					LEFT JOIN r_cd4_test_reasons as vltr ON vl.reason_for_cd4_testing = vltr.test_reason_id
					LEFT JOIN facility_details as f ON vl.facility_id = f.facility_id
					LEFT JOIN r_cd4_sample_types as rst ON rst.sample_id = vl.specimen_type
					LEFT JOIN user_details as reviewer_user ON reviewer_user.user_id = vl.result_reviewed_by
					LEFT JOIN user_details as approver_user ON approver_user.user_id = vl.result_approved_by
					LEFT JOIN user_details as reviser_user ON reviser_user.user_id = vl.revised_by
					LEFT JOIN facility_details as l ON l.facility_id = vl.lab_id
					LEFT JOIN r_implementation_partners as imp ON imp.i_partner_id = vl.implementing_partner
					LEFT JOIN r_funding_sources as funding ON funding.funding_source_id = vl.funding_source
					LEFT JOIN r_cd4_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id = vl.reason_for_sample_rejection
					LEFT JOIN r_recommended_corrective_actions as r_c_a ON r_c_a.recommended_corrective_action_id=vl.recommended_corrective_action";

	$searchQueryWhere = [];
	if (!empty($_POST['id'])) {
		$searchQueryWhere[] = " vl.cd4_id IN(" . $_POST['id'] . ") ";
	}
	if (!empty($_POST['sampleCodes'])) {
		$searchQueryWhere[] = " vl.sample_code IN(" . $_POST['sampleCodes'] . ") ";
	}
	if ($searchQueryWhere !== []) {
		$searchQuery .= " WHERE " . implode(" AND ", $searchQueryWhere);
	}
	//echo ($searchQuery);
	$requestResult = $db->query($searchQuery);
}

if (empty($requestResult) || !$requestResult) {
	return null;
}

$currentDateTime = DateUtility::getCurrentDateTime();

//set print time
$printDate = DateUtility::humanReadableDateFormat($currentDateTime, true);

$currentDateTime = DateUtility::getCurrentDateTime();

$fileArray = [SOUTH_SUDAN => 'pdf/result-pdf-ssudan.php', SIERRA_LEONE => 'pdf/result-pdf-sierraleone.php', DRC => 'pdf/result-pdf-drc.php', CAMEROON => 'pdf/result-pdf-cameroon.php', PNG => 'pdf/result-pdf-png.php', WHO => 'pdf/result-pdf-who.php', RWANDA => 'pdf/result-pdf-rwanda.php'];

$pathFront = TEMP_PATH . DIRECTORY_SEPARATOR .  time() . '-' . MiscUtility::generateRandomString(6);
MiscUtility::makeDirectory($pathFront);

$resultFilename = '';

$pages = [];
$page = 1;
$_SESSION['aliasPage'] = 1;
foreach ($requestResult as $result) {

	if (($general->isLISInstance()) && empty($result['result_printed_on_lis_datetime'])) {
		$pData = ['result_printed_on_lis_datetime' => $currentDateTime, 'result_printed_datetime' => $currentDateTime];
		$db->where('cd4_id', $result['cd4_id']);
		$id = $db->update('form_cd4', $pData);
	} elseif (($general->isSTSInstance()) && empty($result['result_printed_on_sts_datetime'])) {
		$pData = ['result_printed_on_sts_datetime' => $currentDateTime, 'result_printed_datetime' => $currentDateTime];
		$db->where('cd4_id', $result['cd4_id']);
		$id = $db->update('form_cd4', $pData);
	}


	$selectedReportFormats = [];
	if (!empty($result['reportFormat'])) {
		$selectedReportFormats = json_decode((string) $result['reportFormat'], true);
	}

	$fileToInclude = $fileArray[$arr['vl_form']];
	if (!empty($selectedReportFormats) && !empty($selectedReportFormats['cd4'])) {
		$includedFile = realpath(__DIR__ . DIRECTORY_SEPARATOR . $selectedReportFormats['cd4']);
		if ($includedFile !== false && file_exists($includedFile) && is_file($includedFile)) {
			$fileToInclude = $includedFile;
		}
	}

	require($fileToInclude);
}


if ($pages !== []) {
	$resultPdf = new PdfConcatenateHelper();
	$resultPdf->setFiles($pages);
	$resultPdf->setPrintHeader(false);
	$resultPdf->setPrintFooter(false);
	$resultPdf->concat();
	$resultFilename = 'InteLIS-CD4-Test-result-' . date('d-M-Y-H-i-s') . "-" . MiscUtility::generateRandomString(6) . '.pdf';
	$resultPdf->Output(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename, "F");
}

MiscUtility::removeDirectory($pathFront);
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename);
