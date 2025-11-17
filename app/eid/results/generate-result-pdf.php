<?php

use Laminas\Diactoros\ServerRequest;
use const COUNTRY\SOUTH_SUDAN;
use const COUNTRY\SIERRA_LEONE;
use const COUNTRY\DRC;
use const COUNTRY\CAMEROON;
use const COUNTRY\PNG;
use const COUNTRY\WHO;
use const COUNTRY\RWANDA;
use const COUNTRY\BURKINA_FASO;
use App\Services\EidService;
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

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

$tableName1 = "activity_log";
$tableName2 = "form_eid";

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
$eidResults = $eidService->getEidResults();

$formId = (int) $general->getGlobalConfig('vl_form');
$arr = $general->getGlobalConfig();

//set print time
$printedTime = date('Y-m-d H:i:s');
$expStr = explode(" ", $printedTime);
$printDate = DateUtility::humanReadableDateFormat($expStr[0]);
$printDateTime = $expStr[1];

//set mField Array
$mFieldArray = [];
if (isset($arr['r_mandatory_fields']) && trim((string) $arr['r_mandatory_fields']) !== '') {
    $mFieldArray = explode(',', (string) $arr['r_mandatory_fields']);
}

//set query
$allQuery = $_SESSION['eidPrintQuery'];
if (isset($_POST['id']) && trim((string) $_POST['id']) !== '') {

    $searchQuery = "SELECT vl.*,f.*,
                    l.facility_name as labName,
                    l.report_format as reportFormat,
                    l.facility_logo as facilityLogo,
                    l.facility_attributes as vl_facility_attributes,
                    rip.i_partner_name,
                    rst.*,
                    rsrr.rejection_reason_name ,
                    r_c_a.recommended_corrective_action_name,
                    reviewer_user.user_name as reviewedBy,
                    reviewer_user.user_id as reviewedByUserId,
                    reviewer_user.user_signature as reviewedBySignature,
                    approver_user.user_name as approvedBy,
                    approver_user.user_id as approvedByUserId,
                    approver_user.user_signature as approvedBySignature,
                    reviser_user.user_name as revisedBy,
					reviser_user.user_id as revisedByUserId,
					reviser_user.user_signature as revisedBySignature,
					tester_user.user_name as testedBy,
					tester_user.user_id as testedByUserId,
					tester_user.user_signature as testedBySignature,
                    JSON_UNQUOTE(JSON_EXTRACT(i.approved_by, '$.eid')) AS defaultApprovedBy,
                    JSON_UNQUOTE(JSON_EXTRACT(i.reviewed_by, '$.eid')) AS defaultReviewedBy,
                    i.machine_name AS instrument_machine_name
                    FROM form_eid as vl
                    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                    LEFT JOIN facility_details as l ON l.facility_id=vl.lab_id
                    LEFT JOIN user_details as reviewer_user ON reviewer_user.user_id=vl.result_reviewed_by
                    LEFT JOIN user_details as approver_user ON approver_user.user_id=vl.result_approved_by
                    LEFT JOIN user_details as reviser_user ON reviser_user.user_id=vl.revised_by
					LEFT JOIN user_details as tester_user ON tester_user.user_id = vl.tested_by
                    LEFT JOIN r_eid_sample_type as rst ON rst.sample_id=vl.specimen_type
                    LEFT JOIN r_eid_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=vl.reason_for_sample_rejection
                    LEFT JOIN r_implementation_partners as rip ON rip.i_partner_id=vl.implementing_partner
                    LEFT JOIN instruments as i ON i.instrument_id = vl.instrument_id
                    LEFT JOIN r_recommended_corrective_actions as r_c_a ON r_c_a.recommended_corrective_action_id=vl.recommended_corrective_action
                    WHERE vl.eid_id IN(" . $_POST['id'] . ")";
} else {
    $searchQuery = $allQuery;
}
//echo($searchQuery); die;
$requestResult = $db->query($searchQuery);

$currentDateTime = DateUtility::getCurrentDateTime();

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

$pathFront = TEMP_PATH . DIRECTORY_SEPARATOR .  time() . '-' . MiscUtility::generateRandomString(6);
MiscUtility::makeDirectory($pathFront);

$pages = [];
$page = 1;
$_SESSION['aliasPage'] = $page;

foreach ($requestResult as $result) {
    if (($general->isLISInstance()) && empty($result['result_printed_on_lis_datetime'])) {
        $pData = ['result_printed_on_lis_datetime' => $currentDateTime, 'result_printed_datetime' => $currentDateTime];
        $db->where('eid_id', $result['eid_id']);
        $id = $db->update('form_eid', $pData);
    } elseif (($general->isSTSInstance()) && empty($result['result_printed_on_sts_datetime'])) {
        $pData = ['result_printed_on_sts_datetime' => $currentDateTime, 'result_printed_datetime' => $currentDateTime];
        $db->where('eid_id', $result['eid_id']);
        $id = $db->update('form_eid', $pData);
    }

    $selectedReportFormats = [];
    if (!empty($result['reportFormat'])) {
        $selectedReportFormats = json_decode((string) $result['reportFormat'], true);
    }
    if (!empty($selectedReportFormats) && !empty($selectedReportFormats['eid']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $selectedReportFormats['eid'])) {
        require $selectedReportFormats['eid'];
    } else {
        require $fileArray[$arr['vl_form']];
    }
}
if ($pages !== []) {
    $resultPdf = new PdfConcatenateHelper();
    $resultPdf->setFiles($pages);
    $resultPdf->setPrintHeader(false);
    $resultPdf->setPrintFooter(false);
    $resultPdf->concat();
    $resultFilename = 'InteLIS-EID-Test-result-' . date('d-M-Y-H-i-s') . "-" . MiscUtility::generateRandomString(6) . '.pdf';
    $resultPdf->Output(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename, "F");
}
MiscUtility::removeDirectory($pathFront);
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename);
