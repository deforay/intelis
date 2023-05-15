<?php

use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;
use App\Services\UsersService;
use App\Services\VlService;
use App\Utilities\DateUtility;


$title = "Enter VL Result";

require_once APPLICATION_PATH . '/header.php';




/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$vlService = ContainerRegistry::get(VlService::class);
$healthFacilities = $facilitiesService->getHealthFacilities('vl');
$testingLabs = $facilitiesService->getTestingLabs('vl');
$reasonForFailure = $vlService->getReasonForFailure();

$id = base64_decode($_GET['id']);

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
$statusQuery = "SELECT * FROM r_sample_status where status = 'active' AND status_id NOT IN(9,8,6)";
$statusResult = $db->rawQuery($statusQuery);

$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
$pdResult = $db->query($pdQuery);

$sQuery = "SELECT * from r_vl_sample_type where status='active'";
$sResult = $db->query($sQuery);

//get vl test reason list
$vlTestReasonQuery = "SELECT * from r_vl_test_reasons where test_reason_status = 'active'";
$vlTestReasonResult = $db->query($vlTestReasonQuery);

//get suspected treatment failure at
$suspectedTreatmentFailureAtQuery = "SELECT DISTINCT vl_sample_suspected_treatment_failure_at FROM form_vl where vlsm_country_id='" . $arr['vl_form'] . "'";
$suspectedTreatmentFailureAtResult = $db->rawQuery($suspectedTreatmentFailureAtQuery);

$vlQuery = "SELECT * from form_vl where vl_sample_id=?";
$vlQueryInfo = $db->rawQueryOne($vlQuery, array($id));

if (isset($vlQueryInfo['patient_dob']) && trim($vlQueryInfo['patient_dob']) != '' && $vlQueryInfo['patient_dob'] != '0000-00-00') {
	$vlQueryInfo['patient_dob'] = DateUtility::humanReadableDateFormat($vlQueryInfo['patient_dob']);
} else {
	$vlQueryInfo['patient_dob'] = '';
}

if (isset($vlQueryInfo['sample_collection_date']) && trim($vlQueryInfo['sample_collection_date']) != '' && $vlQueryInfo['sample_collection_date'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", $vlQueryInfo['sample_collection_date']);
	$vlQueryInfo['sample_collection_date'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['sample_collection_date'] = '';
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
	$vlQueryInfo['result_approved_datetime'] = DateUtility::humanReadableDateFormat(DateUtility::getCurrentDateTime());
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
if ($vlQueryInfo['remote_sample'] == 'yes') {
	$sampleCode = $vlQueryInfo['remote_sample_code'];
} else {
	$sampleCode = $vlQueryInfo['sample_code'];
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

$patientFullName = trim(implode(" ", array($patientFirstName, $patientMiddleName, $patientLastName)));

?>
<style>
	:disabled {
		background: white !important;
	}

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
if ($arr['vl_form'] == 1) {
	include('forms/update-southsudan-result.php');
} else if ($arr['vl_form'] == 2) {
	include('forms/update-sierraleone-result.php');
} else if ($arr['vl_form'] == 3) {
	include('forms/update-drc-result.php');
} else if ($arr['vl_form'] == 4) {
	// include('forms/update-zambia-result.php');
} else if ($arr['vl_form'] == 5) {
	include('forms/update-png-result.php');
} else if ($arr['vl_form'] == 6) {
	include('forms/update-who-result.php');
} else if ($arr['vl_form'] == 7) {
	include('forms/update-rwanda-result.php');
} else if ($arr['vl_form'] == 8) {
	// include('forms/update-angola-result.php'); 
}

?>
<script>
	$(document).ready(function() {
		$('.date').datepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: 'dd-M-yy',
			timeFormat: "HH:mm",
			maxDate: "Today",
			yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
		}).click(function() {
			$('.ui-datepicker-calendar').show();
		});
		$('.dateTime').datetimepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: 'dd-M-yy',
			timeFormat: "HH:mm",
			maxDate: "Today",
			onChangeMonthYear: function(year, month, widget) {
				setTimeout(function() {
					$('.ui-datepicker-calendar').show();
				});
			},
			yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
		}).click(function() {
			$('.ui-datepicker-calendar').show();
		});
		$('.date').mask('99-aaa-9999');
		$('.dateTime').mask('99-aaa-9999 99:99');

		$('.result-focus').change(function(e) {
			<?php if (isset($vlQueryInfo['result']) && $vlQueryInfo['result'] != "") { ?>
				var status = false;
				$(".result-focus").each(function(index) {
					if ($(this).val() != "") {
						status = true;
					}
				});
				if (status) {
					$('.change-reason').show();
					$('#reasonForResultChanges').addClass('isRequired');
				} else {
					$('.change-reason').hide();
					$('#reasonForResultChanges').removeClass('isRequired');
				}
			<?php } ?>
		});
	});
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
