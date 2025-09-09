<?php

use App\Services\TbService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Helpers\PdfConcatenateHelper;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 300000);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$tableName1 = "activity_log";
$tableName2 = "form_tb";
/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $users */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);

$formId = (int) $general->getGlobalConfig('vl_form');
$key = (string) $general->getGlobalConfig('key');

//set print time
$printedTime = date('Y-m-d H:i:s');
$expStr = explode(" ", $printedTime);
$printDate = DateUtility::humanReadableDateFormat($expStr[0]);
$printDateTime = $expStr[1];
//set query
$allQuery = $_SESSION['tbPrintQuery'];
if (isset($_POST['id']) && trim((string) $_POST['id']) != '') {

    $searchQuery = "SELECT tb.*,f.*,
				l.facility_name as labName,
				l.facility_emails as labEmail,
				l.address as labAddress,
				l.facility_mobile_numbers as labPhone,
				l.facility_state as labState,
				l.facility_district as labCounty,
				l.facility_logo as facilityLogo,
				l.report_format as reportFormat,
                l.facility_attributes,
				rip.i_partner_name,
				rsrr.rejection_reason_name ,
				requestor_user.user_name as requestedBy,
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
				rfs.funding_source_name,
				rst.sample_name,
				testres.test_reason_name as reasonForTesting,
                r_c_a.recommended_corrective_action_name

				FROM form_tb as tb
				LEFT JOIN facility_details as f ON tb.facility_id=f.facility_id
				LEFT JOIN facility_details as l ON l.facility_id=tb.lab_id
                LEFT JOIN user_details as reviewer_user ON reviewer_user.user_id=tb.result_reviewed_by
                LEFT JOIN user_details as approver_user ON approver_user.user_id=tb.result_approved_by
                LEFT JOIN user_details as reviser_user ON reviser_user.user_id=tb.revised_by
                LEFT JOIN user_details as tester_user ON tester_user.user_id = tb.tested_by
				LEFT JOIN user_details as requestor_user ON requestor_user.user_id=tb.request_created_by
				LEFT JOIN r_tb_test_reasons as testres ON testres.test_reason_id=tb.reason_for_tb_test
				LEFT JOIN r_tb_sample_rejection_reasons as rsrr ON rsrr.rejection_reason_id=tb.reason_for_sample_rejection
                LEFT JOIN r_recommended_corrective_actions as r_c_a ON r_c_a.recommended_corrective_action_id=tb.recommended_corrective_action
				LEFT JOIN r_implementation_partners as rip ON rip.i_partner_id=tb.implementing_partner
				LEFT JOIN r_funding_sources as rfs ON rfs.funding_source_id=tb.funding_source
				LEFT JOIN r_tb_sample_type as rst ON rst.sample_id=tb.specimen_type
				WHERE tb.tb_id IN(" . $_POST['id'] . ")";
} else {
    $searchQuery = $allQuery;
}

$requestResult = $db->query($searchQuery);
/* Test Results */

$currentDateTime = DateUtility::getCurrentDateTime();


if (isset($_POST['type']) && $_POST['type'] == "qr") {
    try {
        $general->trackQRPageViews('tb', $requestResult[0]['tb_id'], $requestResult[0]['sample_code']);
    } catch (Exception $exc) {
        error_log($exc->getMessage());
    }
}

$_SESSION['aliasPage'] = 1;
$arr = $general->getGlobalConfig();

//set mField Array
$mFieldArray = [];
if (isset($arr['r_mandatory_fields']) && trim((string) $arr['r_mandatory_fields']) != '') {
    $mFieldArray = explode(',', (string) $arr['r_mandatory_fields']);
}


$fileArray = [
    COUNTRY\SOUTH_SUDAN => 'pdf/result-pdf-ssudan.php',
    COUNTRY\SIERRA_LEONE => 'pdf/result-pdf-sierraleone.php',
    COUNTRY\DRC => 'pdf/result-pdf-drc.php',
    COUNTRY\CAMEROON => 'pdf/result-pdf-cameroon.php',
    COUNTRY\PNG => 'pdf/result-pdf-png.php',
    COUNTRY\WHO => 'pdf/result-pdf-who.php',
    COUNTRY\RWANDA => 'pdf/result-pdf-rwanda.php',
    COUNTRY\BURKINA_FASO => 'pdf/result-pdf-burkina-faso.php'
];



$resultFilename = '';
if (!empty($requestResult)) {
    $_SESSION['rVal'] = MiscUtility::generateRandomString(6);
    $pathFront = TEMP_PATH . DIRECTORY_SEPARATOR .  $_SESSION['rVal'];
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
        $expStr = explode(" ", $printedTime);
        $printDate = DateUtility::humanReadableDateFormat($expStr[0]);
        $printDateTime = $expStr[1];


        if (($general->isLISInstance()) && empty($result['result_printed_on_lis_datetime'])) {
            $pData = array('result_printed_on_lis_datetime' => $currentDateTime, 'result_printed_datetime' => $currentDateTime);
            $db->where('tb_id', $result['tb_id']);
            $id = $db->update('form_tb', $pData);
        } elseif (($general->isSTSInstance()) && empty($result['result_printed_on_sts_datetime'])) {
            $pData = array('result_printed_on_sts_datetime' => $currentDateTime, 'result_printed_datetime' => $currentDateTime);
            $db->where('tb_id', $result['tb_id']);
            $id = $db->update('form_tb', $pData);
        }


        $tbTestQuery = "SELECT * from tb_tests where tb_id= " . $result['tb_id'] . " ORDER BY tb_test_id ASC";
        $tbTestInfo = $db->rawQuery($tbTestQuery);
        // Lab Details
        $facilityQuery = "SELECT * from form_tb as c19 INNER JOIN facility_details as fd ON c19.facility_id=fd.facility_id where tb_id= " . $result['tb_id'] . " GROUP BY fd.facility_id LIMIT 1";
        $facilityInfo = $db->rawQueryOne($facilityQuery);

        $patientFname = ($general->crypto('doNothing', $result['patient_name'], $result['patient_id']));
        $patientLname = ($general->crypto('doNothing', $result['patient_surname'], $result['patient_id']));

        if (!empty($result['is_encrypted']) && $result['is_encrypted'] == 'yes') {
            $result['patient_id'] = $general->crypto('decrypt', $result['patient_id'], $key);
            $patientFname = $general->crypto('decrypt', $patientFname, $key);
            $patientLname = $general->crypto('decrypt', $patientLname, $key);
        }

        $signQuery = "SELECT * from lab_report_signatories where lab_id=? AND test_types like '%tb%' AND signatory_status like 'active' ORDER BY display_order ASC";
        $signResults = $db->rawQuery($signQuery, array($result['lab_id']));
        $currentDateTime = DateUtility::getCurrentDateTime();

        $_SESSION['aliasPage'] = $page;
        if (!isset($result['labName'])) {
            $result['labName'] = '';
        }
        $draftTextShow = false;
        //Set watermark text


        $selectedReportFormats = [];
        if (isset($result['reportFormat']) && $result['reportFormat'] != "") {
            $selectedReportFormats = json_decode((string) $result['reportFormat'], true);
        }

        if (!empty($selectedReportFormats) && !empty($selectedReportFormats['tb']) && file_exists(__DIR__ . DIRECTORY_SEPARATOR . $selectedReportFormats['tb'])) {
            require_once($selectedReportFormats['tb']);
        } else {
            require_once($fileArray[$formId]);
        }
    }
    if (!empty($pages)) {
        $resultPdf = new PdfConcatenateHelper();
        $resultPdf->setFiles($pages);
        $resultPdf->setPrintHeader(false);
        $resultPdf->setPrintFooter(false);
        $resultPdf->concat();
        $resultFilename = 'VLSM-TB-Test-result-' . date('d-M-Y-H-i-s') . "-" . MiscUtility::generateRandomString(6) . '.pdf';
        $resultPdf->Output(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename, "F");
    }
}

MiscUtility::removeDirectory($pathFront);
unset($_SESSION['rVal']);
echo base64_encode(TEMP_PATH . DIRECTORY_SEPARATOR . $resultFilename);
