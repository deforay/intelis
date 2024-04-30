<?php

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
/** @var Laminas\Diactoros\ServerRequest $request */
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
if (isset($arr['r_mandatory_fields']) && trim((string) $arr['r_mandatory_fields']) != '') {
    $mFieldArray = explode(',', (string) $arr['r_mandatory_fields']);
}

//set query
$allQuery = $_SESSION['eidPrintQuery'];
if (isset($_POST['id']) && trim((string) $_POST['id']) != '') {

    $searchQuery = "SELECT vl.*,f.*,
                    l.facility_name as labName,
                    l.report_format as reportFormat,
                    l.facility_logo as facilityLogo,
                    l.facility_attributes as vl_facility_attributes,
                    rip.i_partner_name,
                    rst.*,
                    rsrr.rejection_reason_name ,
                    r_c_a.recommended_corrective_action_name,
                    u_d.user_name as reviewedBy,
                    u_d.user_id as reviewedByUserId,
                    u_d.user_signature as reviewedBySignature,
                    a_u_d.user_name as approvedBy,
                    a_u_d.user_id as approvedByUserId,
                    a_u_d.user_signature as approvedBySignature,
                    r_r_b.user_name as revised,
                    tp.config_machine_name as testingPlatform,
                    JSON_UNQUOTE(JSON_EXTRACT(i.approved_by, '$.eid')) AS defaultApprovedBy,
                    JSON_UNQUOTE(JSON_EXTRACT(i.reviewed_by, '$.eid')) AS defaultReviewedBy
                    FROM form_eid as vl
                    LEFT JOIN facility_details as f ON vl.facility_id=f.facility_id
                    LEFT JOIN facility_details as l ON l.facility_id=vl.lab_id
                    LEFT JOIN user_details as u_d ON u_d.user_id=vl.result_reviewed_by
                    LEFT JOIN user_details as a_u_d ON a_u_d.user_id=vl.result_approved_by
                    LEFT JOIN user_details as r_r_b ON r_r_b.user_id=vl.revised_by
                    LEFT JOIN r_eid_sample_type as rst ON rst.sample_id=vl.specimen_type
                    LEFT JOIN r_eid_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=vl.reason_for_sample_rejection
                    LEFT JOIN r_implementation_partners as rip ON rip.i_partner_id=vl.implementing_partner
                    LEFT JOIN instrument_machines as tp ON tp.config_machine_id=vl.import_machine_name
                    LEFT JOIN instruments as i ON i.instrument_id=vl.instrument_id
                    LEFT JOIN r_recommended_corrective_actions as r_c_a ON r_c_a.recommended_corrective_action_id=vl.recommended_corrective_action
                    WHERE vl.eid_id IN(" . $_POST['id'] . ")";
} else {
    $searchQuery = $allQuery;
}
//echo($searchQuery);die;
$requestResult = $db->query($searchQuery);

$currentDateTime = DateUtility::getCurrentDateTime();

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

$randomFolderName = time() . '-' . $general->generateRandomString(6);

$pathFront = TEMP_PATH . DIRECTORY_SEPARATOR .  $randomFolderName;
MiscUtility::makeDirectory($pathFront);

$_SESSION['aliasPage'] = 1;

foreach ($requestResult as $result) {
    if (($general->isLISInstance()) && empty($result['result_printed_on_lis_datetime'])) {
        $pData = array('result_printed_on_lis_datetime' => $currentDateTime);
        $db->where('eid_id', $result['eid_id']);
        $id = $db->update('form_eid', $pData);
    } elseif (($general->isSTSInstance()) && empty($result['result_printed_on_sts_datetime'])) {
        $pData = array('result_printed_on_sts_datetime' => $currentDateTime);
        $db->where('eid_id', $result['eid_id']);
        $id = $db->update('form_eid', $pData);
    }

    $selectedReportFormats = [];
    if (!empty($result['reportFormat'])) {
        $selectedReportFormats = json_decode((string) $result['reportFormat'], true);
    }

    if (!empty($selectedReportFormats) && !empty($selectedReportFormats['eid']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $selectedReportFormats['eid'])) {
        require($selectedReportFormats['eid']);
    } else {
        require($fileArray[$arr['vl_form']]);
    }
}
if (!empty($pages)) {
    $resultPdf = new PdfConcatenateHelper();
    $resultPdf->setFiles($pages);
    $resultPdf->setPrintHeader(false);
    $resultPdf->setPrintFooter(false);
    $resultPdf->concat();
    $resultFilename = 'VLSM-EID-Test-result-' . date('d-M-Y-H-i-s') . "-" . $general->generateRandomString(6) . '.pdf';
    $resultPdf->Output(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename, "F");
    MiscUtility::removeDirectory($pathFront);
}

echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename);
