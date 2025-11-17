<?php

use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Services\VlService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Services\FacilitiesService;
use App\Services\CommonService;
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

$healthFacilities = $facilitiesService->getHealthFacilities('cd4');
$healthFacilitiesAllColumns = $facilitiesService->getHealthFacilities('cd4', false, true);

$testingLabs = $facilitiesService->getTestingLabs('cd4');


$reasonForFailure = $vlService->getReasonForFailure();

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;


// get instruments
$importQuery = "SELECT * FROM instruments WHERE status = 'active'";
$importResult = $db->query($importQuery);

$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);
$userInfo = [];
foreach ($userResult as $user) {
     $userInfo[$user['user_id']] = ($user['user_name']);
}
//sample rejection reason
$rejectionQuery = "SELECT * FROM r_cd4_sample_rejection_reasons
                         WHERE rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);
//rejection type
$rejectionTypeQuery = "SELECT DISTINCT rejection_type
                         FROM r_cd4_sample_rejection_reasons
                         WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
//sample status
$statusQuery = "SELECT * FROM r_sample_status
                    WHERE `status` = 'active' AND status_id NOT IN(9,8)";
$statusResult = $db->rawQuery($statusQuery);

//Recommended corrective actgions
$condition = "status ='active' AND test_type='cd4'";
$correctiveActions = $general->fetchDataFromTable('r_recommended_corrective_actions', $condition);

$pdResult = $general->fetchDataFromTable('geographical_divisions', "geo_parent = 0 AND geo_status='active'");

$sQuery = "SELECT * FROM r_cd4_sample_types
               WHERE status='active'";
$sResult = $db->query($sQuery);

//get cd4 test reason list
$cd4TestReasonQuery = "SELECT * FROM r_cd4_test_reasons
                         WHERE test_reason_status = 'active'";
$cd4TestReasonResult = $db->query($cd4TestReasonQuery);

$cd4Query = "SELECT * FROM form_cd4 WHERE cd4_id=?";
$cd4QueryInfo = $db->rawQueryOne($cd4Query, [$id]);


$cd4QueryInfo['patient_dob'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['patient_dob'] ?? '');

$sampleCollectionDate = $cd4QueryInfo['sample_collection_date'] ?? '';
$cd4QueryInfo['sample_collection_date'] = DateUtility::humanReadableDateFormat($sampleCollectionDate, true) ?: DateUtility::getCurrentDateTime();
$cd4QueryInfo['sample_dispatched_datetime'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['sample_dispatched_datetime'] ?? '', true);
$cd4QueryInfo['result_approved_datetime'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['result_approved_datetime'] ?? '', true);
$cd4QueryInfo['treatment_initiated_date'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['treatment_initiated_date'] ?? '');
$cd4QueryInfo['date_of_initiation_of_current_regimen'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['date_of_initiation_of_current_regimen'] ?? '');
$cd4QueryInfo['test_requested_on'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['test_requested_on'] ?? '');
$cd4QueryInfo['sample_received_at_hub_datetime'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['sample_received_at_hub_datetime'] ?? '', true);
$cd4QueryInfo['sample_received_at_lab_datetime'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['sample_received_at_lab_datetime'] ?? '', true);
$cd4QueryInfo['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['sample_tested_datetime'] ?? '', true);
$cd4QueryInfo['result_dispatched_datetime'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['result_dispatched_datetime'] ?? '', true);
$cd4QueryInfo['last_viral_load_date'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['last_viral_load_date'] ?? '');
$cd4QueryInfo['date_test_ordered_by_physician'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['date_test_ordered_by_physician'] ?? '');

//Has patient changed regimen section
if (trim((string) $cd4QueryInfo['has_patient_changed_regimen']) === "yes") {
     $cd4QueryInfo['regimen_change_date'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['regimen_change_date'] ?? '');
} else {
     $cd4QueryInfo['reason_for_regimen_change'] = $cd4QueryInfo['regimen_change_date'] = '';
}
//Set Dispatched From Clinic To Lab Date
$cd4QueryInfo['sample_dispatched_datetime'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['sample_dispatched_datetime'] ?? '', true);

//Set Date of result printed datetime
$cd4QueryInfo['result_printed_datetime'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['result_printed_datetime'] ?? '', true);

//reviewed datetime
$cd4QueryInfo['result_reviewed_datetime'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['result_reviewed_datetime'] ?? '', true);

$patientFirstName = $cd4QueryInfo['patient_first_name'] ?? '';
$patientMiddleName = $cd4QueryInfo['patient_middle_name'] ?? '';
$patientLastName = $cd4QueryInfo['patient_last_name'] ?? '';

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();

$artRegimenQuery = "SELECT DISTINCT headings FROM r_vl_art_regimen";
$artRegimenResult = $db->rawQuery($artRegimenQuery);
$aQuery = "SELECT * FROM r_vl_art_regimen where art_status ='active'";
$aResult = $db->query($aQuery);

if (!empty($cd4QueryInfo['is_encrypted']) && $cd4QueryInfo['is_encrypted'] == 'yes') {
     $key = (string) $general->getGlobalConfig('key');
     $cd4QueryInfo['patient_art_no'] = $general->crypto('decrypt', $cd4QueryInfo['patient_art_no'], $key);
     if ($patientFirstName != '') {
          $cd4QueryInfo['patient_first_name'] = $patientFirstName = $general->crypto('decrypt', $patientFirstName, $key);
     }

     if ($patientMiddleName != '') {
          $patientMiddleName = $general->crypto('decrypt', $patientMiddleName, $key);
     }

     if ($patientLastName != '') {
          $cd4QueryInfo['patient_last_name']  = $patientLastName = $general->crypto('decrypt', $patientLastName, $key);
     }
     $patientFullName = $patientFirstName . " " . $patientMiddleName . " " . $patientLastName;
} else {
     $patientFullName = trim($patientFirstName ?? ' ' . $patientMiddleName ?? ' ' . $patientLastName ?? '');
}
$minPatientIdLength = 0;
if (isset($arr['cd4_min_patient_id_length']) && $arr['cd4_min_patient_id_length'] != "") {
     $minPatientIdLength = $arr['cd4_min_patient_id_length'];
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
     COUNTRY\RWANDA => 'forms/edit-rwanda.php'
];

require_once($fileArray[$formId]);


?>

<script type="text/javascript" src="/assets/js/datalist-css.min.js?v=<?= filemtime(WEB_ROOT . "/assets/js/datalist-css.min.js") ?>"></script>

<?php

require_once APPLICATION_PATH . '/footer.php';
