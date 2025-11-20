<?php

use App\Registries\AppRegistry;
use App\Services\CD4Service;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;


$title = _translate("Enter CD4 Result");

require_once APPLICATION_PATH . '/header.php';


/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var Cd4Service $cd4Service */
$cd4Service = ContainerRegistry::get(CD4Service::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$formId = (int) $general->getGlobalConfig('vl_form');

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();

$healthFacilities = $facilitiesService->getHealthFacilities('cd4');
$testingLabs = $facilitiesService->getTestingLabs('cd4');

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
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
$rejectionQuery = "SELECT * FROM r_cd4_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);
//rejection type
$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_cd4_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
//sample status
$statusQuery = "SELECT * FROM r_sample_status where status = 'active' AND status_id NOT IN(9,8,6)";
$statusResult = $db->rawQuery($statusQuery);

$pdResult = $general->fetchDataFromTable('geographical_divisions', "geo_parent = 0 AND geo_status='active'");

$sQuery = "SELECT * from r_cd4_sample_types where status='active'";
$sResult = $db->query($sQuery);

//get vl test reason list
$vlTestReasonQuery = "SELECT * from r_cd4_test_reasons where test_reason_status = 'active'";
$vlTestReasonResult = $db->query($vlTestReasonQuery);

//Recommended corrective actgions
$condition = "status ='active' AND test_type='cd4'";
$correctiveActions = $general->fetchDataFromTable('r_recommended_corrective_actions', $condition);


$cd4Query = "SELECT * from form_cd4 where cd4_id=?";
$cd4QueryInfo = $db->rawQueryOne($cd4Query, [$id]);

if (isset($cd4QueryInfo['patient_dob']) && trim((string) $cd4QueryInfo['patient_dob']) !== '' && $cd4QueryInfo['patient_dob'] != '0000-00-00') {
	$cd4QueryInfo['patient_dob'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['patient_dob']);
} else {
	$cd4QueryInfo['patient_dob'] = '';
}

if (isset($cd4QueryInfo['sample_collection_date']) && trim((string) $cd4QueryInfo['sample_collection_date']) !== '' && $cd4QueryInfo['sample_collection_date'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $cd4QueryInfo['sample_collection_date']);
	$cd4QueryInfo['sample_collection_date'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$cd4QueryInfo['sample_collection_date'] = '';
}
if (isset($cd4QueryInfo['sample_dispatched_datetime']) && trim((string) $cd4QueryInfo['sample_dispatched_datetime']) !== '' && $cd4QueryInfo['sample_dispatched_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $cd4QueryInfo['sample_dispatched_datetime']);
	$cd4QueryInfo['sample_dispatched_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$cd4QueryInfo['sample_dispatched_datetime'] = '';
}

if (isset($cd4QueryInfo['result_approved_datetime']) && trim((string) $cd4QueryInfo['result_approved_datetime']) !== '' && $cd4QueryInfo['result_approved_datetime'] != '0000-00-00 00:00:00') {
	$sampleCollectionDate = $cd4QueryInfo['result_approved_datetime'];
	$expStr = explode(" ", (string) $cd4QueryInfo['result_approved_datetime']);
	$cd4QueryInfo['result_approved_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$sampleCollectionDate = '';
	$cd4QueryInfo['result_approved_datetime'] = DateUtility::humanReadableDateFormat(DateUtility::getCurrentDateTime());
}

if (isset($cd4QueryInfo['treatment_initiated_date']) && trim((string) $cd4QueryInfo['treatment_initiated_date']) !== '' && $cd4QueryInfo['treatment_initiated_date'] != '0000-00-00') {
	$cd4QueryInfo['treatment_initiated_date'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['treatment_initiated_date']);
} else {
	$cd4QueryInfo['treatment_initiated_date'] = '';
}

if (isset($cd4QueryInfo['date_of_initiation_of_current_regimen']) && trim((string) $cd4QueryInfo['date_of_initiation_of_current_regimen']) !== '' && $cd4QueryInfo['date_of_initiation_of_current_regimen'] != '0000-00-00') {
	$cd4QueryInfo['date_of_initiation_of_current_regimen'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['date_of_initiation_of_current_regimen']);
} else {
	$cd4QueryInfo['date_of_initiation_of_current_regimen'] = '';
}

if (isset($cd4QueryInfo['test_requested_on']) && trim((string) $cd4QueryInfo['test_requested_on']) !== '' && $cd4QueryInfo['test_requested_on'] != '0000-00-00') {
	$cd4QueryInfo['test_requested_on'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['test_requested_on']);
} else {
	$cd4QueryInfo['test_requested_on'] = '';
}


if (isset($cd4QueryInfo['sample_received_at_hub_datetime']) && trim((string) $cd4QueryInfo['sample_received_at_hub_datetime']) !== '' && $cd4QueryInfo['sample_received_at_hub_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $cd4QueryInfo['sample_received_at_hub_datetime']);
	$cd4QueryInfo['sample_received_at_hub_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$cd4QueryInfo['sample_received_at_hub_datetime'] = '';
}


if (isset($cd4QueryInfo['sample_received_at_lab_datetime']) && trim((string) $cd4QueryInfo['sample_received_at_lab_datetime']) !== '' && $cd4QueryInfo['sample_received_at_lab_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $cd4QueryInfo['sample_received_at_lab_datetime']);
	$cd4QueryInfo['sample_received_at_lab_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$cd4QueryInfo['sample_received_at_lab_datetime'] = '';
}

if (isset($cd4QueryInfo['sample_tested_datetime']) && trim((string) $cd4QueryInfo['sample_tested_datetime']) !== '' && $cd4QueryInfo['sample_tested_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $cd4QueryInfo['sample_tested_datetime']);
	$cd4QueryInfo['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$cd4QueryInfo['sample_tested_datetime'] = '';
}

if (isset($cd4QueryInfo['result_dispatched_datetime']) && trim((string) $cd4QueryInfo['result_dispatched_datetime']) !== '' && $cd4QueryInfo['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $cd4QueryInfo['result_dispatched_datetime']);
	$cd4QueryInfo['result_dispatched_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$cd4QueryInfo['result_dispatched_datetime'] = '';
}
if (isset($cd4QueryInfo['last_viral_load_date']) && trim((string) $cd4QueryInfo['last_viral_load_date']) !== '' && $cd4QueryInfo['last_viral_load_date'] != '0000-00-00') {
	$cd4QueryInfo['last_viral_load_date'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['last_viral_load_date']);
} else {
	$cd4QueryInfo['last_viral_load_date'] = '';
}
//Set Date of demand
if (isset($cd4QueryInfo['date_test_ordered_by_physician']) && trim((string) $cd4QueryInfo['date_test_ordered_by_physician']) !== '' && $cd4QueryInfo['date_test_ordered_by_physician'] != '0000-00-00') {
	$cd4QueryInfo['date_test_ordered_by_physician'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['date_test_ordered_by_physician']);
} else {
	$cd4QueryInfo['date_test_ordered_by_physician'] = '';
}
//Has patient changed regimen section
if (trim((string) $cd4QueryInfo['has_patient_changed_regimen']) === "yes") {
	if (isset($cd4QueryInfo['regimen_change_date']) && trim((string) $cd4QueryInfo['regimen_change_date']) !== '' && $cd4QueryInfo['regimen_change_date'] != '0000-00-00') {
		$cd4QueryInfo['regimen_change_date'] = DateUtility::humanReadableDateFormat($cd4QueryInfo['regimen_change_date']);
	} else {
		$cd4QueryInfo['regimen_change_date'] = '';
	}
} else {
	$cd4QueryInfo['reason_for_regimen_change'] = '';
	$cd4QueryInfo['regimen_change_date'] = '';
}
//Set Dispatched From Clinic To Lab Date
if (isset($cd4QueryInfo['sample_dispatched_datetime']) && trim((string) $cd4QueryInfo['sample_dispatched_datetime']) !== '' && $cd4QueryInfo['sample_dispatched_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $cd4QueryInfo['sample_dispatched_datetime']);
	$cd4QueryInfo['sample_dispatched_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$cd4QueryInfo['sample_dispatched_datetime'] = '';
}
//Set Date of result printed datetime
if (isset($cd4QueryInfo['result_printed_datetime']) && trim((string) $cd4QueryInfo['result_printed_datetime']) !== "" && $cd4QueryInfo['result_printed_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $cd4QueryInfo['result_printed_datetime']);
	$cd4QueryInfo['result_printed_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$cd4QueryInfo['result_printed_datetime'] = '';
}
//reviewed datetime
if (isset($cd4QueryInfo['result_reviewed_datetime']) && trim((string) $cd4QueryInfo['result_reviewed_datetime']) !== '' && $cd4QueryInfo['result_reviewed_datetime'] != null && $cd4QueryInfo['result_reviewed_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $cd4QueryInfo['result_reviewed_datetime']);
	$cd4QueryInfo['result_reviewed_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$cd4QueryInfo['result_reviewed_datetime'] = '';
}
if ($cd4QueryInfo['remote_sample'] == 'yes') {
	$sampleCode = $cd4QueryInfo['remote_sample_code'];
} else {
	$sampleCode = $cd4QueryInfo['sample_code'];
}

$patientFirstName = $cd4QueryInfo['patient_first_name'] ?? '';
$patientMiddleName = $cd4QueryInfo['patient_middle_name'] ?? '';
$patientLastName = $cd4QueryInfo['patient_last_name'] ?? '';


if (!empty($cd4QueryInfo['is_encrypted']) && $cd4QueryInfo['is_encrypted'] == 'yes') {
	$key = (string) $general->getGlobalConfig('key');
	$cd4QueryInfo['patient_art_no'] = $general->crypto('decrypt', $cd4QueryInfo['patient_art_no'], $key);
	if ($patientFirstName != '') {
		$patientFirstName = $general->crypto('decrypt', $patientFirstName, $key);
	}

	if ($patientMiddleName != '') {
		$patientMiddleName = $general->crypto('decrypt', $patientMiddleName, $key);
	}

	if ($patientLastName != '') {
		$patientLastName = $general->crypto('decrypt', $patientLastName, $key);
	}
}

$patientFullName = trim(implode(" ", [$patientFirstName, $patientMiddleName, $patientLastName]));

$artRegimenQuery = "SELECT DISTINCT headings FROM r_vl_art_regimen";
$artRegimenResult = $db->rawQuery($artRegimenQuery);
$aQuery = "SELECT * from r_vl_art_regimen WHERE art_status = 'active'";
$aResult = $db->query($aQuery);

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
if ($formId == COUNTRY\SOUTH_SUDAN) {
	include(__DIR__ . '/forms/update-southsudan-result.php');
} elseif ($formId == COUNTRY\SIERRA_LEONE) {
	include(__DIR__ . '/forms/update-sierraleone-result.php');
} elseif ($formId == COUNTRY\DRC) {
	include(__DIR__ . '/forms/update-drc-result.php');
} elseif ($formId == COUNTRY\CAMEROON) {
	include(__DIR__ . '/forms/update-cameroon-result.php');
} elseif ($formId == COUNTRY\PNG) {
	include(__DIR__ . '/forms/update-png-result.php');
} elseif ($formId == COUNTRY\RWANDA) {
	include(__DIR__ . '/forms/update-rwanda-result.php');
}

?>
<script type="text/javascript"
	src="/assets/js/datalist-css.min.js?v=<?= filemtime(WEB_ROOT . "/assets/js/datalist-css.min.js") ?>"></script>

<script>
	$(document).ready(function () {

		$('.result-focus').change(function (e) {
			<?php //if (isset($cd4QueryInfo['result']) && $cd4QueryInfo['result'] != "") {
			?>
			var status = false;
			$(".result-focus").each(function (index) {
				if ($(this).val() != "") {
					status = true;
				}
			});
			if (status == true) {
				$('.change-reason').show();
				$('.reasonForResultChanges').show();
				$('#reasonForResultChanges').addClass('isRequired');
			} else {
				$('.change-reason').hide();
				$('.reasonForResultChanges').hide();
				$('#reasonForResultChanges').removeClass('isRequired');
			}
			<?php //}
			?>
		});

		$("#vlFocalPerson").select2({
			placeholder: "Enter Request Focal name",
			minimumInputLength: 0,
			width: '100%',
			allowClear: true,
			id: function (bond) {
				return bond._id;
			},
			ajax: {
				placeholder: "Type one or more character to search",
				url: "/includes/get-data-list.php",
				dataType: 'json',
				delay: 250,
				data: function (params) {
					return {
						fieldName: 'cd4_focal_person',
						tableName: 'form_cd4',
						q: params.term, // search term
						page: params.page
					};
				},
				processResults: function (data, params) {
					params.page = params.page || 1;
					return {
						results: data.result,
						pagination: {
							more: (params.page * 30) < data.total_count
						}
					};
				},
				//cache: true
			},
			escapeMarkup: function (markup) {
				return markup;
			}
		});

		$("#vlFocalPerson").change(function () {
			$.blockUI();
			var search = $(this).val();
			if ($.trim(search) != '') {
				$.get("/includes/get-data-list.php", {
					fieldName: 'cd4_focal_person',
					tableName: 'form_cd4',
					returnField: 'cd4_focal_person_phone_number',
					limit: 1,
					q: search,
				},
					function (data) {
						if (data != "") {
							$("#cd4FocalPersonPhoneNumber").val(data);
						}
					});
			}
			$.unblockUI();
		});
	});
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
