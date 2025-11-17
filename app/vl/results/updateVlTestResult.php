<?php

use App\Services\VlService;
use App\Services\UsersService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;


$title = _translate("Enter VL Result");

require_once APPLICATION_PATH . '/header.php';


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

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();

$healthFacilities = $facilitiesService->getHealthFacilities('vl');
$testingLabs = $facilitiesService->getTestingLabs('vl');
$reasonForFailure = $vlService->getReasonForFailure();

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = isset($_GET['id']) ? MiscUtility::desqid((string) $_GET['id']) : null;


// get instruments
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

$pdResult = $general->fetchDataFromTable('geographical_divisions', "geo_parent = 0 AND geo_status='active'");

$sQuery = "SELECT * from r_vl_sample_type where status='active'";
$sResult = $db->query($sQuery);

//get vl test reason list
$vlTestReasonQuery = "SELECT * from r_vl_test_reasons where test_reason_status = 'active'";
$vlTestReasonResult = $db->query($vlTestReasonQuery);

//Recommended corrective actgions
$condition = "status ='active' AND test_type='vl'";
$correctiveActions = $general->fetchDataFromTable('r_recommended_corrective_actions', $condition);

$vlQuery = "SELECT * from form_vl where vl_sample_id=?";
$vlQueryInfo = $db->rawQueryOne($vlQuery, [$id]);

if (isset($vlQueryInfo['patient_dob']) && trim((string) $vlQueryInfo['patient_dob']) !== '' && $vlQueryInfo['patient_dob'] != '0000-00-00') {
	$vlQueryInfo['patient_dob'] = DateUtility::humanReadableDateFormat($vlQueryInfo['patient_dob']);
} else {
	$vlQueryInfo['patient_dob'] = '';
}

if (isset($vlQueryInfo['sample_collection_date']) && trim((string) $vlQueryInfo['sample_collection_date']) !== '' && $vlQueryInfo['sample_collection_date'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $vlQueryInfo['sample_collection_date']);
	$vlQueryInfo['sample_collection_date'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['sample_collection_date'] = '';
}
if (isset($vlQueryInfo['sample_dispatched_datetime']) && trim((string) $vlQueryInfo['sample_dispatched_datetime']) !== '' && $vlQueryInfo['sample_dispatched_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $vlQueryInfo['sample_dispatched_datetime']);
	$vlQueryInfo['sample_dispatched_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['sample_dispatched_datetime'] = '';
}

if (isset($vlQueryInfo['result_approved_datetime']) && trim((string) $vlQueryInfo['result_approved_datetime']) !== '' && $vlQueryInfo['result_approved_datetime'] != '0000-00-00 00:00:00') {
	$sampleCollectionDate = $vlQueryInfo['result_approved_datetime'];
	$expStr = explode(" ", (string) $vlQueryInfo['result_approved_datetime']);
	$vlQueryInfo['result_approved_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$sampleCollectionDate = '';
	$vlQueryInfo['result_approved_datetime'] = DateUtility::humanReadableDateFormat(DateUtility::getCurrentDateTime());
}

if (isset($vlQueryInfo['treatment_initiated_date']) && trim((string) $vlQueryInfo['treatment_initiated_date']) !== '' && $vlQueryInfo['treatment_initiated_date'] != '0000-00-00') {
	$vlQueryInfo['treatment_initiated_date'] = DateUtility::humanReadableDateFormat($vlQueryInfo['treatment_initiated_date']);
} else {
	$vlQueryInfo['treatment_initiated_date'] = '';
}

if (isset($vlQueryInfo['date_of_initiation_of_current_regimen']) && trim((string) $vlQueryInfo['date_of_initiation_of_current_regimen']) !== '' && $vlQueryInfo['date_of_initiation_of_current_regimen'] != '0000-00-00') {
	$vlQueryInfo['date_of_initiation_of_current_regimen'] = DateUtility::humanReadableDateFormat($vlQueryInfo['date_of_initiation_of_current_regimen']);
} else {
	$vlQueryInfo['date_of_initiation_of_current_regimen'] = '';
}

if (isset($vlQueryInfo['test_requested_on']) && trim((string) $vlQueryInfo['test_requested_on']) !== '' && $vlQueryInfo['test_requested_on'] != '0000-00-00') {
	$vlQueryInfo['test_requested_on'] = DateUtility::humanReadableDateFormat($vlQueryInfo['test_requested_on']);
} else {
	$vlQueryInfo['test_requested_on'] = '';
}


if (isset($vlQueryInfo['sample_received_at_hub_datetime']) && trim((string) $vlQueryInfo['sample_received_at_hub_datetime']) !== '' && $vlQueryInfo['sample_received_at_hub_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $vlQueryInfo['sample_received_at_hub_datetime']);
	$vlQueryInfo['sample_received_at_hub_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['sample_received_at_hub_datetime'] = '';
}


if (isset($vlQueryInfo['sample_received_at_lab_datetime']) && trim((string) $vlQueryInfo['sample_received_at_lab_datetime']) !== '' && $vlQueryInfo['sample_received_at_lab_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $vlQueryInfo['sample_received_at_lab_datetime']);
	$vlQueryInfo['sample_received_at_lab_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['sample_received_at_lab_datetime'] = '';
}

if (isset($vlQueryInfo['sample_tested_datetime']) && trim((string) $vlQueryInfo['sample_tested_datetime']) !== '' && $vlQueryInfo['sample_tested_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $vlQueryInfo['sample_tested_datetime']);
	$vlQueryInfo['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['sample_tested_datetime'] = '';
}

if (isset($vlQueryInfo['result_dispatched_datetime']) && trim((string) $vlQueryInfo['result_dispatched_datetime']) !== '' && $vlQueryInfo['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $vlQueryInfo['result_dispatched_datetime']);
	$vlQueryInfo['result_dispatched_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['result_dispatched_datetime'] = '';
}
if (isset($vlQueryInfo['last_viral_load_date']) && trim((string) $vlQueryInfo['last_viral_load_date']) !== '' && $vlQueryInfo['last_viral_load_date'] != '0000-00-00') {
	$vlQueryInfo['last_viral_load_date'] = DateUtility::humanReadableDateFormat($vlQueryInfo['last_viral_load_date']);
} else {
	$vlQueryInfo['last_viral_load_date'] = '';
}
//Set Date of demand
if (isset($vlQueryInfo['date_test_ordered_by_physician']) && trim((string) $vlQueryInfo['date_test_ordered_by_physician']) !== '' && $vlQueryInfo['date_test_ordered_by_physician'] != '0000-00-00') {
	$vlQueryInfo['date_test_ordered_by_physician'] = DateUtility::humanReadableDateFormat($vlQueryInfo['date_test_ordered_by_physician']);
} else {
	$vlQueryInfo['date_test_ordered_by_physician'] = '';
}
//Has patient changed regimen section
if (trim((string) $vlQueryInfo['has_patient_changed_regimen']) === "yes") {
	if (isset($vlQueryInfo['regimen_change_date']) && trim((string) $vlQueryInfo['regimen_change_date']) !== '' && $vlQueryInfo['regimen_change_date'] != '0000-00-00') {
		$vlQueryInfo['regimen_change_date'] = DateUtility::humanReadableDateFormat($vlQueryInfo['regimen_change_date']);
	} else {
		$vlQueryInfo['regimen_change_date'] = '';
	}
} else {
	$vlQueryInfo['reason_for_regimen_change'] = '';
	$vlQueryInfo['regimen_change_date'] = '';
}
//Set Dispatched From Clinic To Lab Date
if (isset($vlQueryInfo['sample_dispatched_datetime']) && trim($vlQueryInfo['sample_dispatched_datetime']) !== '' && $vlQueryInfo['sample_dispatched_datetime'] !== '0000-00-00 00:00:00') {
	$expStr = explode(" ", $vlQueryInfo['sample_dispatched_datetime']);
	$vlQueryInfo['sample_dispatched_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['sample_dispatched_datetime'] = '';
}
//Set Date of result printed datetime
if (isset($vlQueryInfo['result_printed_datetime']) && trim((string) $vlQueryInfo['result_printed_datetime']) !== "" && $vlQueryInfo['result_printed_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $vlQueryInfo['result_printed_datetime']);
	$vlQueryInfo['result_printed_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['result_printed_datetime'] = '';
}
//reviewed datetime
if (isset($vlQueryInfo['result_reviewed_datetime']) && trim((string) $vlQueryInfo['result_reviewed_datetime']) !== '' && $vlQueryInfo['result_reviewed_datetime'] != null && $vlQueryInfo['result_reviewed_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $vlQueryInfo['result_reviewed_datetime']);
	$vlQueryInfo['result_reviewed_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$vlQueryInfo['result_reviewed_datetime'] = '';
}
$sampleCode = $vlQueryInfo['remote_sample'] == 'yes' ? $vlQueryInfo['remote_sample_code'] : $vlQueryInfo['sample_code'];

$patientFirstName = $vlQueryInfo['patient_first_name'] ?? '';
$patientMiddleName = $vlQueryInfo['patient_middle_name'] ?? '';
$patientLastName = $vlQueryInfo['patient_last_name'] ?? '';


if (!empty($arr['display_encrypt_pii_option']) && $arr['display_encrypt_pii_option'] == "yes" && !empty($vlQueryInfo['is_encrypted']) && $vlQueryInfo['is_encrypted'] == 'yes') {
	$key = (string) $general->getGlobalConfig('key');
	$vlQueryInfo['patient_art_no'] = $general->crypto('decrypt', $vlQueryInfo['patient_art_no'], $key);
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
} elseif ($formId == COUNTRY\BURKINA_FASO) {
    include(__DIR__ . '/forms/update-burkina-faso-result.php');
}

?>
<script type="text/javascript" src="/assets/js/datalist-css.min.js?v=<?= filemtime(WEB_ROOT . "/assets/js/datalist-css.min.js") ?>"></script>
<?php
// Common JS functions in a PHP file
// Why PHP? Because we can use PHP variables in the JS code
require_once WEB_ROOT . "/assets/js/test-specific/vl.js.php";

?>
<script>
	$(document).ready(function() {


		let dateFormatMask = '<?= $_SESSION['jsDateFormatMask'] ?? '99-aaa-9999'; ?>';
		$('.date').mask(dateFormatMask);
		$('.dateTime').mask(dateFormatMask + ' 99:99');

		$('.result-focus').change(function(e) {
			<?php //if (isset($vlQueryInfo['result']) && $vlQueryInfo['result'] != "") {
			?>
			var status = false;
			$(".result-focus").each(function(index) {
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
			id: function(bond) {
				return bond._id;
			},
			ajax: {
				placeholder: "Type one or more character to search",
				url: "/includes/get-data-list.php",
				dataType: 'json',
				delay: 250,
				data: function(params) {
					return {
						fieldName: 'vl_focal_person',
						tableName: 'form_vl',
						q: params.term, // search term
						page: params.page
					};
				},
				processResults: function(data, params) {
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
			escapeMarkup: function(markup) {
				return markup;
			}
		});

		$("#vlFocalPerson").change(function() {
			$.blockUI();
			var search = $(this).val();
			if ($.trim(search) != '') {
				$.get("/includes/get-data-list.php", {
						fieldName: 'vl_focal_person',
						tableName: 'form_vl',
						returnField: 'vl_focal_person_phone_number',
						limit: 1,
						q: search,
					},
					function(data) {
						if (data != "") {
							$("#vlFocalPersonPhoneNumber").val(data);
						}
					});
			}
			$.unblockUI();
		});
	});
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
