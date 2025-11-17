<?php

use App\Services\VlService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

require_once APPLICATION_PATH . '/header.php';

$sCode = $labFieldDisabled = '';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


$formId = (int) $general->getGlobalConfig('vl_form');

$healthFacilities = $facilitiesService->getHealthFacilities('vl');
$healthFacilitiesAllColumns = $facilitiesService->getHealthFacilities('vl', false, true);

$testingLabs = $facilitiesService->getTestingLabs('vl');

$reasonForFailure = $vlService->getReasonForFailure();

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = isset($_GET['id']) ? MiscUtility::desqid($_GET['id']) : null;


// get instruments
$importQuery = "SELECT * FROM instruments WHERE status = 'active'";
$importResult = $db->query($importQuery);

$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);
$userInfo = [];
foreach ($userResult as $user) {
     $userInfo[$user['user_id']] = $user['user_name'];
}
//sample rejection reason
$rejectionQuery = "SELECT * FROM r_vl_sample_rejection_reasons
                         WHERE rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);
//rejection type
$rejectionTypeQuery = "SELECT DISTINCT rejection_type
                         FROM r_vl_sample_rejection_reasons
                         WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
//sample status
$statusQuery = "SELECT * FROM r_sample_status
                    WHERE `status` = 'active' AND status_id NOT IN(9,8)";
$statusResult = $db->rawQuery($statusQuery);

//Recommended corrective actgions
$condition = "status ='active' AND test_type='vl'";
$correctiveActions = $general->fetchDataFromTable('r_recommended_corrective_actions', $condition);

$pdResult = $general->fetchDataFromTable('geographical_divisions', "geo_parent = 0 AND geo_status='active'");

$sQuery = "SELECT * FROM r_vl_sample_type
               WHERE status='active'";
$sResult = $db->query($sQuery);

//get vl test reason list
$vlTestReasonQuery = "SELECT * FROM r_vl_test_reasons
                         WHERE test_reason_status = 'active'";
$vlTestReasonResult = $db->query($vlTestReasonQuery);

$vlQuery = "SELECT * FROM form_vl WHERE vl_sample_id=?";
$vlQueryInfo = $db->rawQueryOne($vlQuery, [$id]);


$vlQueryInfo['patient_dob'] = DateUtility::humanReadableDateFormat($vlQueryInfo['patient_dob'] ?? '');
$vlQueryInfo['test_request_date'] = DateUtility::humanReadableDateFormat($vlQueryInfo['test_request_date'] ?? '');

$sampleCollectionDate = $vlQueryInfo['sample_collection_date'] ?? '';
$vlQueryInfo['sample_collection_date'] = DateUtility::humanReadableDateFormat($sampleCollectionDate, true) ?: DateUtility::getCurrentDateTime();
$vlQueryInfo['sample_dispatched_datetime'] = DateUtility::humanReadableDateFormat($vlQueryInfo['sample_dispatched_datetime'] ?? '', true);
$vlQueryInfo['result_approved_datetime'] = DateUtility::humanReadableDateFormat($vlQueryInfo['result_approved_datetime'] ?? '', true);
$vlQueryInfo['treatment_initiated_date'] = DateUtility::humanReadableDateFormat($vlQueryInfo['treatment_initiated_date'] ?? '');
$vlQueryInfo['date_of_initiation_of_current_regimen'] = DateUtility::humanReadableDateFormat($vlQueryInfo['date_of_initiation_of_current_regimen'] ?? '');
$vlQueryInfo['test_requested_on'] = DateUtility::humanReadableDateFormat($vlQueryInfo['test_requested_on'] ?? '');
$vlQueryInfo['sample_received_at_hub_datetime'] = DateUtility::humanReadableDateFormat($vlQueryInfo['sample_received_at_hub_datetime'] ?? '', true);
$vlQueryInfo['sample_received_at_lab_datetime'] = DateUtility::humanReadableDateFormat($vlQueryInfo['sample_received_at_lab_datetime'] ?? '', true);
$vlQueryInfo['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($vlQueryInfo['sample_tested_datetime'] ?? '', true);
$vlQueryInfo['result_dispatched_datetime'] = DateUtility::humanReadableDateFormat($vlQueryInfo['result_dispatched_datetime'] ?? '', true);
$vlQueryInfo['last_viral_load_date'] = DateUtility::humanReadableDateFormat($vlQueryInfo['last_viral_load_date'] ?? '');
$vlQueryInfo['date_test_ordered_by_physician'] = DateUtility::humanReadableDateFormat($vlQueryInfo['date_test_ordered_by_physician'] ?? '');

//Has patient changed regimen section
if (trim((string) $vlQueryInfo['has_patient_changed_regimen']) === "yes") {
     $vlQueryInfo['regimen_change_date'] = DateUtility::humanReadableDateFormat($vlQueryInfo['regimen_change_date'] ?? '');
} else {
     $vlQueryInfo['reason_for_regimen_change'] = $vlQueryInfo['regimen_change_date'] = '';
}
//Set Dispatched From Clinic To Lab Date
$vlQueryInfo['sample_dispatched_datetime'] = DateUtility::humanReadableDateFormat($vlQueryInfo['sample_dispatched_datetime'] ?? '', true);

//Set Date of result printed datetime
$vlQueryInfo['result_printed_datetime'] = DateUtility::humanReadableDateFormat($vlQueryInfo['result_printed_datetime'] ?? '', true);

//reviewed datetime
$vlQueryInfo['result_reviewed_datetime'] = DateUtility::humanReadableDateFormat($vlQueryInfo['result_reviewed_datetime'] ?? '', true);

$patientFirstName = $vlQueryInfo['patient_first_name'] ?? '';
$patientMiddleName = $vlQueryInfo['patient_middle_name'] ?? '';
$patientLastName = $vlQueryInfo['patient_last_name'] ?? '';

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();

$artRegimenQuery = "SELECT DISTINCT headings FROM r_vl_art_regimen";
$artRegimenResult = $db->rawQuery($artRegimenQuery);
$aQuery = "SELECT * FROM r_vl_art_regimen where art_status ='active'";
$aResult = $db->query($aQuery);


if (!empty($arr['display_encrypt_pii_option']) && $arr['display_encrypt_pii_option'] == "yes" && !empty($vlQueryInfo['is_encrypted']) && $vlQueryInfo['is_encrypted'] == 'yes') {
     $key = (string) $general->getGlobalConfig('key');
     $vlQueryInfo['patient_art_no'] = $general->crypto('decrypt', $vlQueryInfo['patient_art_no'], $key);
     if ($patientFirstName != '') {
          $vlQueryInfo['patient_first_name'] = $patientFirstName = $general->crypto('decrypt', $patientFirstName, $key);
     }

     if ($patientMiddleName != '') {
          $patientMiddleName = $general->crypto('decrypt', $patientMiddleName, $key);
     }

     if ($patientLastName != '') {
          $vlQueryInfo['patient_last_name']  = $patientLastName = $general->crypto('decrypt', $patientLastName, $key);
     }
     $patientFullName = $patientFirstName . " " . $patientMiddleName . " " . $patientLastName;
} else {
     $patientFullName = trim($patientFirstName ?? ' ' . $patientMiddleName ?? ' ' . $patientLastName ?? '');
}

$minPatientIdLength = 0;
if (isset($arr['vl_min_patient_id_length']) && $arr['vl_min_patient_id_length'] != "") {
     $minPatientIdLength = $arr['vl_min_patient_id_length'];
}
?>
<style>
     .ui_tpicker_second_label {
          display: none !important;
     }

     .ui_tpicker_second_slider {
          display: none !important;
     }

     .ui_tpicker_millisec_label {
          display: none !important;
     }

     .ui_tpicker_millisec_slider {
          display: none !important;
     }

     .ui_tpicker_microsec_label {
          display: none !important;
     }

     .ui_tpicker_microsec_slider {
          display: none !important;
     }

     .ui_tpicker_timezone_label {
          display: none !important;
     }

     .ui_tpicker_timezone {
          display: none !important;
     }

     .ui_tpicker_time_input {
          width: 100%;
     }
</style>
<?php

$fileArray = [
     COUNTRY\SOUTH_SUDAN => 'forms/edit-southsudan.php',
     COUNTRY\SIERRA_LEONE => 'forms/edit-sierraleone.php',
     COUNTRY\DRC => 'forms/edit-drc.php',
     COUNTRY\CAMEROON => 'forms/edit-cameroon.php',
     COUNTRY\PNG => 'forms/edit-png.php',
     COUNTRY\WHO => 'forms/edit-who.php',
     COUNTRY\RWANDA => 'forms/edit-rwanda.php',
     COUNTRY\BURKINA_FASO => 'forms/edit-burkina-faso.php'
];

require_once($fileArray[$formId]);


?>

<script type="text/javascript" src="/assets/js/datalist-css.min.js?v=<?= filemtime(WEB_ROOT . "/assets/js/datalist-css.min.js") ?>"></script>

<?php
// Common JS functions in a PHP file
// Why PHP? Because we can use PHP variables in the JS code
require_once WEB_ROOT . "/assets/js/test-specific/vl.js.php";


require_once APPLICATION_PATH . '/footer.php';
