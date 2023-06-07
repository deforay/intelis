<?php

use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;
use App\Services\UsersService;
use App\Services\VlService;
use App\Utilities\DateUtility;

require_once APPLICATION_PATH . '/header.php';

$sCode = $labFieldDisabled = '';

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

$healthFacilities = $facilitiesService->getHealthFacilities('vl');
$testingLabs = $facilitiesService->getTestingLabs('vl');

$reasonForFailure = $vlService->getReasonForFailure();
if ($_SESSION['instanceType'] == 'remoteuser') {
     $labFieldDisabled = 'disabled="disabled"';
}

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_GET = $request->getQueryParams();
$id = (isset($_GET['id'])) ? base64_decode($_GET['id']) : null;


//get import config
$importQuery = "SELECT * FROM instruments WHERE status = 'active'";
$importResult = $db->query($importQuery);

$userResult = $usersService->getActiveUsers($_SESSION['facilityMap']);
$userInfo = [];
foreach ($userResult as $user) {
     $userInfo[$user['user_id']] = ($user['user_name']);
}
//sample rejection reason
$rejectionQuery = "SELECT * FROM r_vl_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);
//rejection type
$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_vl_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
//sample status
$statusQuery = "SELECT * FROM r_sample_status WHERE `status` = 'active' AND status_id NOT IN(9,8)";
$statusResult = $db->rawQuery($statusQuery);

$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
$pdResult = $db->query($pdQuery);

$sQuery = "SELECT * FROM r_vl_sample_type WHERE status='active'";
$sResult = $db->query($sQuery);

//get vl test reason list
$vlTestReasonQuery = "SELECT * FROM r_vl_test_reasons WHERE test_reason_status = 'active'";
$vlTestReasonResult = $db->query($vlTestReasonQuery);

//get suspected treatment failure at
$suspectedTreatmentFailureAtQuery = "SELECT DISTINCT vl_sample_suspected_treatment_failure_at FROM form_vl where vlsm_country_id= ?";
$suspectedTreatmentFailureAtResult = $db->rawQuery($suspectedTreatmentFailureAtQuery, [$arr['vl_form']]);

$vlQuery = "SELECT * FROM form_vl WHERE vl_sample_id=?";
$vlQueryInfo = $db->rawQueryOne($vlQuery, array($id));
//echo "<pre>"; print_r($vlQueryInfo); die;
if (isset($vlQueryInfo['patient_dob']) && trim($vlQueryInfo['patient_dob']) != '' && $vlQueryInfo['patient_dob'] != '0000-00-00') {
     $vlQueryInfo['patient_dob'] = DateUtility::humanReadableDateFormat($vlQueryInfo['patient_dob']);
} else {
     $vlQueryInfo['patient_dob'] = '';
}

if (isset($vlQueryInfo['sample_collection_date']) && trim($vlQueryInfo['sample_collection_date']) != '' && $vlQueryInfo['sample_collection_date'] != '0000-00-00 00:00:00') {
     $sampleCollectionDate = $vlQueryInfo['sample_collection_date'];
     $expStr = explode(" ", $vlQueryInfo['sample_collection_date']);
     $vlQueryInfo['sample_collection_date'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
     $sampleCollectionDate = '';
     $vlQueryInfo['sample_collection_date'] = DateUtility::getCurrentDateTime();
}

if (isset($vlQueryInfo['sample_dispatched_datetime']) && trim($vlQueryInfo['sample_dispatched_datetime']) != '' && $vlQueryInfo['sample_dispatched_datetime'] != '0000-00-00 00:00:00') {
     $expStr = explode(" ", $vlQueryInfo['sample_dispatched_datetime']);
     $vlQueryInfo['sample_dispatched_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
     $vlQueryInfo['sample_dispatched_datetime'] = '';
}

if (isset($vlQueryInfo['result_approved_datetime']) && trim($vlQueryInfo['result_approved_datetime']) != '' && $vlQueryInfo['result_approved_datetime'] != '0000-00-00 00:00:00') {
     $sampleCollectionDate = $vlQueryInfo['result_approved_datetime'];
     $expStr = explode(" ", $vlQueryInfo['result_approved_datetime']);
     $vlQueryInfo['result_approved_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
     $sampleCollectionDate = '';
     $vlQueryInfo['result_approved_datetime'] = '';
}

if (isset($vlQueryInfo['treatment_initiated_date']) && trim($vlQueryInfo['treatment_initiated_date']) != '' && $vlQueryInfo['treatment_initiated_date'] != '0000-00-00') {
     $vlQueryInfo['treatment_initiated_date'] = DateUtility::humanReadableDateFormat($vlQueryInfo['treatment_initiated_date']);
} else {
     $vlQueryInfo['treatment_initiated_date'] = '';
}

if (isset($vlQueryInfo['date_of_initiation_of_current_regimen']) && trim($vlQueryInfo['date_of_initiation_of_current_regimen']) != '' && $vlQueryInfo['date_of_initiation_of_current_regimen'] != '0000-00-00') {
     $vlQueryInfo['date_of_initiation_of_current_regimen'] = DateUtility::humanReadableDateFormat($vlQueryInfo['date_of_initiation_of_current_regimen']);
} else {
     $vlQueryInfo['date_of_initiation_of_current_regimen'] = '';
}

if (isset($vlQueryInfo['test_requested_on']) && trim($vlQueryInfo['test_requested_on']) != '' && $vlQueryInfo['test_requested_on'] != '0000-00-00') {
     $vlQueryInfo['test_requested_on'] = DateUtility::humanReadableDateFormat($vlQueryInfo['test_requested_on']);
} else {
     $vlQueryInfo['test_requested_on'] = '';
}


if (isset($vlQueryInfo['sample_received_at_hub_datetime']) && trim($vlQueryInfo['sample_received_at_hub_datetime']) != '' && $vlQueryInfo['sample_received_at_hub_datetime'] != '0000-00-00 00:00:00') {
     $expStr = explode(" ", $vlQueryInfo['sample_received_at_hub_datetime']);
     $vlQueryInfo['sample_received_at_hub_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
     $vlQueryInfo['sample_received_at_hub_datetime'] = '';
}


if (isset($vlQueryInfo['sample_received_at_vl_lab_datetime']) && trim($vlQueryInfo['sample_received_at_vl_lab_datetime']) != '' && $vlQueryInfo['sample_received_at_vl_lab_datetime'] != '0000-00-00 00:00:00') {
     $expStr = explode(" ", $vlQueryInfo['sample_received_at_vl_lab_datetime']);
     $vlQueryInfo['sample_received_at_vl_lab_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
     $vlQueryInfo['sample_received_at_vl_lab_datetime'] = '';
}


if (isset($vlQueryInfo['sample_tested_datetime']) && trim($vlQueryInfo['sample_tested_datetime']) != '' && $vlQueryInfo['sample_tested_datetime'] != '0000-00-00 00:00:00') {
     $expStr = explode(" ", $vlQueryInfo['sample_tested_datetime']);
     $vlQueryInfo['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
     $vlQueryInfo['sample_tested_datetime'] = '';
}

if (isset($vlQueryInfo['result_dispatched_datetime']) && trim($vlQueryInfo['result_dispatched_datetime']) != '' && $vlQueryInfo['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
     $expStr = explode(" ", $vlQueryInfo['result_dispatched_datetime']);
     $vlQueryInfo['result_dispatched_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
     $vlQueryInfo['result_dispatched_datetime'] = '';
}
if (isset($vlQueryInfo['last_viral_load_date']) && trim($vlQueryInfo['last_viral_load_date']) != '' && $vlQueryInfo['last_viral_load_date'] != '0000-00-00') {
     $vlQueryInfo['last_viral_load_date'] = DateUtility::humanReadableDateFormat($vlQueryInfo['last_viral_load_date']);
} else {
     $vlQueryInfo['last_viral_load_date'] = '';
}
//Set Date of demand
if (isset($vlQueryInfo['date_test_ordered_by_physician']) && trim($vlQueryInfo['date_test_ordered_by_physician']) != '' && $vlQueryInfo['date_test_ordered_by_physician'] != '0000-00-00') {
     $vlQueryInfo['date_test_ordered_by_physician'] = DateUtility::humanReadableDateFormat($vlQueryInfo['date_test_ordered_by_physician']);
} else {
     $vlQueryInfo['date_test_ordered_by_physician'] = '';
}
//Has patient changed regimen section
if (trim($vlQueryInfo['has_patient_changed_regimen']) == "yes") {
     if (isset($vlQueryInfo['regimen_change_date']) && trim($vlQueryInfo['regimen_change_date']) != '' && $vlQueryInfo['regimen_change_date'] != '0000-00-00') {
          $vlQueryInfo['regimen_change_date'] = DateUtility::humanReadableDateFormat($vlQueryInfo['regimen_change_date']);
     } else {
          $vlQueryInfo['regimen_change_date'] = '';
     }
} else {
     $vlQueryInfo['reason_for_regimen_change'] = '';
     $vlQueryInfo['regimen_change_date'] = '';
}
//Set Dispatched From Clinic To Lab Date
if (isset($vlQueryInfo['sample_dispatched_datetime']) && trim($vlQueryInfo['sample_dispatched_datetime']) != '' && $vlQueryInfo['sample_dispatched_datetime'] != '0000-00-00 00:00:00') {
     $expStr = explode(" ", $vlQueryInfo['sample_dispatched_datetime']);
     $vlQueryInfo['sample_dispatched_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
     $vlQueryInfo['sample_dispatched_datetime'] = '';
}
//Set Date of result printed datetime
if (isset($vlQueryInfo['result_printed_datetime']) && trim($vlQueryInfo['result_printed_datetime']) != "" && $vlQueryInfo['result_printed_datetime'] != '0000-00-00 00:00:00') {
     $expStr = explode(" ", $vlQueryInfo['result_printed_datetime']);
     $vlQueryInfo['result_printed_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
     $vlQueryInfo['result_printed_datetime'] = '';
}
//reviewed datetime
if (isset($vlQueryInfo['result_reviewed_datetime']) && trim($vlQueryInfo['result_reviewed_datetime']) != '' && $vlQueryInfo['result_reviewed_datetime'] != null && $vlQueryInfo['result_reviewed_datetime'] != '0000-00-00 00:00:00') {
     $expStr = explode(" ", $vlQueryInfo['result_reviewed_datetime']);
     $vlQueryInfo['result_reviewed_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
     $vlQueryInfo['result_reviewed_datetime'] = '';
}


if ($vlQueryInfo['patient_first_name'] != '') {
     $patientFirstName = $general->crypto('doNothing', $vlQueryInfo['patient_first_name'], $vlQueryInfo['patient_art_no']);
} else {
     $patientFirstName = '';
}
if ($vlQueryInfo['patient_middle_name'] != '') {
     $patientMiddleName = $general->crypto('doNothing', $vlQueryInfo['patient_middle_name'], $vlQueryInfo['patient_art_no']);
} else {
     $patientMiddleName = '';
}
if ($vlQueryInfo['patient_last_name'] != '') {
     $patientLastName = $general->crypto('doNothing', $vlQueryInfo['patient_last_name'], $vlQueryInfo['patient_art_no']);
} else {
     $patientLastName = '';
}
$patientFullName = [];
if (trim($patientFirstName) != '') {
     $patientFullName[] = trim($patientFirstName);
}
if (trim($patientMiddleName) != '') {
     $patientFullName[] = trim($patientMiddleName);
}
if (trim($patientLastName) != '') {
     $patientFullName[] = trim($patientLastName);
}

if (!empty($patientFullName)) {
     $patientFullName = implode(" ", $patientFullName);
} else {
     $patientFullName = '';
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

$fileArray = array(
     1 => 'forms/edit-southsudan.php',
     2 => 'forms/edit-sierraleone.php',
     3 => 'forms/edit-drc.php',
     //4 => 'forms/edit-zambia.php',
     5 => 'forms/edit-png.php',
     6 => 'forms/edit-who.php',
     7 => 'forms/edit-rwanda.php',
     8 => 'forms/edit-angola.php',
);

require($fileArray[$arr['vl_form']]);


?>

<script type="text/javascript" src="/assets/js/datalist-css.min.js"></script>

<?php
// Common JS functions in a PHP file
// Why PHP? Because we can use PHP variables in the JS code
require_once APPLICATION_PATH . "/vl/vl.js.php";
?>
<script>

</script>
<?php require_once APPLICATION_PATH . '/footer.php';
