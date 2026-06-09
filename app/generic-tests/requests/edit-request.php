<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;
use App\Services\UsersService;
use App\Services\GenericTestsService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Exceptions\SystemException;



require_once APPLICATION_PATH . '/header.php';

$labFieldDisabled = '';



/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$healthFacilities = $facilitiesService->getHealthFacilities('generic-tests');
$testingLabs = $facilitiesService->getTestingLabs('generic-tests');

$reasonForFailure = $genericTestsService->getReasonForFailure();

if ($general->isSTSInstance() && ($_SESSION['accessType'] ?? '') !== 'testing-lab') {
	$labFieldDisabled = 'disabled="disabled"';
}

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
/* To get testing platform names */
$testPlatformResult = $general->getTestingPlatforms('generic-tests');
foreach ($testPlatformResult as $row) {
	$testPlatformList[$row['machine_name']] = $row['machine_name'];
}

//sample rejection reason
$rejectionQuery = "SELECT * FROM r_generic_sample_rejection_reasons where rejection_reason_status = 'active'";
$rejectionResult = $db->rawQuery($rejectionQuery);
//rejection type
$rejectionTypeQuery = "SELECT DISTINCT rejection_type FROM r_generic_sample_rejection_reasons WHERE rejection_reason_status ='active'";
$rejectionTypeResult = $db->rawQuery($rejectionTypeQuery);
//sample status
$statusQuery = "SELECT * FROM r_sample_status WHERE `status` = 'active' AND status_id NOT IN(9,8)";
$statusResult = $db->rawQuery($statusQuery);


$sQuery = "SELECT * FROM r_generic_sample_types WHERE sample_type_status='active'";
$sResult = $db->query($sQuery);

//get vl test reason list
$vlTestReasonQuery = "SELECT * FROM r_generic_test_reasons WHERE test_reason_status = 'active'";
$testReason = $db->query($vlTestReasonQuery);

$vlQuery = "SELECT * FROM form_generic WHERE sample_id=?";
$genericResultInfo = $db->rawQueryOne($vlQuery, [$id]);

$genericResultInfo['patient_dob'] = DateUtility::humanReadableDateFormat($genericResultInfo['patient_dob'] ?? null);

if (isset($genericResultInfo['sample_collection_date']) && trim((string) $genericResultInfo['sample_collection_date']) !== '' && $genericResultInfo['sample_collection_date'] != '0000-00-00 00:00:00') {
	$sampleCollectionDate = $genericResultInfo['sample_collection_date'];
	$expStr = explode(" ", (string) $genericResultInfo['sample_collection_date']);
	$genericResultInfo['sample_collection_date'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$sampleCollectionDate = '';
	$genericResultInfo['sample_collection_date'] = DateUtility::getCurrentDateTime();
}

if (isset($genericResultInfo['sample_dispatched_datetime']) && trim((string) $genericResultInfo['sample_dispatched_datetime']) !== '' && $genericResultInfo['sample_dispatched_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $genericResultInfo['sample_dispatched_datetime']);
	$genericResultInfo['sample_dispatched_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$genericResultInfo['sample_dispatched_datetime'] = '';
}

if (isset($genericResultInfo['result_approved_datetime']) && trim((string) $genericResultInfo['result_approved_datetime']) !== '' && $genericResultInfo['result_approved_datetime'] != '0000-00-00 00:00:00') {
	$sampleCollectionDate = $genericResultInfo['result_approved_datetime'];
	$expStr = explode(" ", (string) $genericResultInfo['result_approved_datetime']);
	$genericResultInfo['result_approved_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$sampleCollectionDate = '';
	$genericResultInfo['result_approved_datetime'] = '';
}

if (isset($genericResultInfo['treatment_initiated_date']) && trim((string) $genericResultInfo['treatment_initiated_date']) !== '' && $genericResultInfo['treatment_initiated_date'] != '0000-00-00') {
	$genericResultInfo['treatment_initiated_date'] = DateUtility::humanReadableDateFormat($genericResultInfo['treatment_initiated_date']);
} else {
	$genericResultInfo['treatment_initiated_date'] = '';
}

if (isset($genericResultInfo['test_requested_on']) && trim((string) $genericResultInfo['test_requested_on']) !== '' && $genericResultInfo['test_requested_on'] != '0000-00-00') {
	$genericResultInfo['test_requested_on'] = DateUtility::humanReadableDateFormat($genericResultInfo['test_requested_on']);
} else {
	$genericResultInfo['test_requested_on'] = '';
}


if (isset($genericResultInfo['sample_received_at_hub_datetime']) && trim((string) $genericResultInfo['sample_received_at_hub_datetime']) !== '' && $genericResultInfo['sample_received_at_hub_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $genericResultInfo['sample_received_at_hub_datetime']);
	$genericResultInfo['sample_received_at_hub_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$genericResultInfo['sample_received_at_hub_datetime'] = '';
}

if (isset($genericResultInfo['sample_received_at_lab_datetime']) && trim((string) $genericResultInfo['sample_received_at_lab_datetime']) !== '' && $genericResultInfo['sample_received_at_lab_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $genericResultInfo['sample_received_at_lab_datetime']);
	$genericResultInfo['sample_received_at_lab_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$genericResultInfo['sample_received_at_lab_datetime'] = '';
}


if (isset($genericResultInfo['sample_tested_datetime']) && trim((string) $genericResultInfo['sample_tested_datetime']) !== '' && $genericResultInfo['sample_tested_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $genericResultInfo['sample_tested_datetime']);
	$genericResultInfo['sample_tested_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$genericResultInfo['sample_tested_datetime'] = '';
}

if (isset($genericResultInfo['result_dispatched_datetime']) && trim((string) $genericResultInfo['result_dispatched_datetime']) !== '' && $genericResultInfo['result_dispatched_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $genericResultInfo['result_dispatched_datetime']);
	$genericResultInfo['result_dispatched_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$genericResultInfo['result_dispatched_datetime'] = '';
}

//Set Date of demand
if (isset($genericResultInfo['date_test_ordered_by_physician']) && trim((string) $genericResultInfo['date_test_ordered_by_physician']) !== '' && $genericResultInfo['date_test_ordered_by_physician'] != '0000-00-00') {
	$genericResultInfo['date_test_ordered_by_physician'] = DateUtility::humanReadableDateFormat($genericResultInfo['date_test_ordered_by_physician']);
} else {
	$genericResultInfo['date_test_ordered_by_physician'] = '';
}

//Set Dispatched From Clinic To Lab Date
if (isset($genericResultInfo['sample_dispatched_datetime']) && trim((string) $genericResultInfo['sample_dispatched_datetime']) !== '' && $genericResultInfo['sample_dispatched_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $genericResultInfo['sample_dispatched_datetime']);
	$genericResultInfo['sample_dispatched_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$genericResultInfo['sample_dispatched_datetime'] = '';
}
//Set Date of result printed datetime
if (isset($genericResultInfo['result_printed_datetime']) && trim((string) $genericResultInfo['result_printed_datetime']) !== "" && $genericResultInfo['result_printed_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $genericResultInfo['result_printed_datetime']);
	$genericResultInfo['result_printed_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$genericResultInfo['result_printed_datetime'] = '';
}
//reviewed datetime
if (isset($genericResultInfo['result_reviewed_datetime']) && trim((string) $genericResultInfo['result_reviewed_datetime']) !== '' && $genericResultInfo['result_reviewed_datetime'] != null && $genericResultInfo['result_reviewed_datetime'] != '0000-00-00 00:00:00') {
	$expStr = explode(" ", (string) $genericResultInfo['result_reviewed_datetime']);
	$genericResultInfo['result_reviewed_datetime'] = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
} else {
	$genericResultInfo['result_reviewed_datetime'] = '';
}


if ($genericResultInfo['patient_first_name'] != '') {
	$patientFirstName = $general->crypto('doNothing', $genericResultInfo['patient_first_name'], $genericResultInfo['patient_id']);
} else {
	$patientFirstName = '';
}
if ($genericResultInfo['patient_middle_name'] != '') {
	$patientMiddleName = $general->crypto('doNothing', $genericResultInfo['patient_middle_name'], $genericResultInfo['patient_id']);
} else {
	$patientMiddleName = '';
}
if ($genericResultInfo['patient_last_name'] != '') {
	$patientLastName = $general->crypto('doNothing', $genericResultInfo['patient_last_name'], $genericResultInfo['patient_id']);
} else {
	$patientLastName = '';
}
$patientFullName = [];
if (trim((string) $patientFirstName) !== '') {
	$patientFullName[] = trim((string) $patientFirstName);
}
if (trim((string) $patientMiddleName) !== '') {
	$patientFullName[] = trim((string) $patientMiddleName);
}
if (trim((string) $patientLastName) !== '') {
	$patientFullName[] = trim((string) $patientLastName);
}

$patientFullName = $patientFullName === [] ? '' : implode(" ", $patientFullName);
$testMethods = $genericTestsService->getTestMethod($genericResultInfo['test_type']);
//$testResultUnits = $general->getDataByTableAndFields("r_generic_test_result_units", array("unit_id", "unit_name"), true, "unit_status='active'");
$testResultUnits = $genericTestsService->getTestResultUnit($genericResultInfo['test_type']);

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();

$lResult = $facilitiesService->getTestingLabs('generic-tests', true, true);

if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'alphanumeric') {
	$sampleClass = '';
	$maxLength = '';
	if ($arr['max_length'] != '' && $arr['sample_code'] == 'alphanumeric') {
		$maxLength = $arr['max_length'];
		$maxLength = "maxlength=" . $maxLength;
	}
} else {
	$sampleClass = '';
	$maxLength = '';
	if ($arr['max_length'] != '') {
		$maxLength = $arr['max_length'];
		$maxLength = "maxlength=" . $maxLength;
	}
}
// check if STS
if ($general->isSTSInstance()) {
	$sampleCode = 'remote_sample_code';
	if (!empty($genericResultInfo['remote_sample']) && $genericResultInfo['remote_sample'] == 'yes') {
		$sampleCode = 'remote_sample_code';
	} else {
		$sampleCode = 'sample_code';
	}
} else {
	$sampleCode = 'sample_code';
}
$province = $general->getUserMappedProvinces($_SESSION['facilityMap']);
$facility = $general->generateSelectOptions($healthFacilities, $genericResultInfo['facility_id'], '-- Select --');
//facility details
if (isset($genericResultInfo['facility_id']) && $genericResultInfo['facility_id'] > 0) {
	$facilityQuery = "SELECT f.*, u.user_name as contact_person
						FROM facility_details as f
						LEFT JOIN user_details as u ON u.user_id=f.contact_person
						WHERE f.facility_id= ? AND f.status='active'";
	$facilityResult = $db->rawQuery($facilityQuery, [$genericResultInfo['facility_id']]);
}
if (!isset($facilityResult[0]['facility_code'])) {
	$facilityResult[0]['facility_code'] = '';
}
if (!isset($facilityResult[0]['facility_mobile_numbers'])) {
	$facilityResult[0]['facility_mobile_numbers'] = '';
}
if (!isset($facilityResult[0]['contact_person'])) {
	$facilityResult[0]['contact_person'] = '';
}
if (!isset($facilityResult[0]['facility_emails'])) {
	$facilityResult[0]['facility_emails'] = '';
}
if (!isset($facilityResult[0]['facility_state'])) {
	$facilityResult[0]['facility_state'] = '';
}
if (!isset($facilityResult[0]['facility_district'])) {
	$facilityResult[0]['facility_district'] = '';
}

$user = '';
if ($facilityResult[0]['contact_person'] != '') {
	$contactUser = $usersService->getUserByID($facilityResult[0]['contact_person']);
	if (!empty($contactUser)) {
		$user = $contactUser['user_name'];
	}
}
//echo '<pre>'; print_r($facility); die;
$testTypeQuery = "SELECT * FROM r_test_types where test_status='active' ORDER BY test_standard_name ASC";
$testTypeResult = $db->rawQuery($testTypeQuery);

$testTypeForm = json_decode((string) $genericResultInfo['test_type_form'], true);

// Result-change history: a normalized list of {usr, msg, dtime} entries, oldest first.
// MiscUtility::parseResultChangeHistory tolerates both the current JSON format and legacy "##"/"vlsm" rows.
$resultChangeHistory = MiscUtility::parseResultChangeHistory($genericResultInfo['reason_for_test_result_changes'] ?? null);
$latestChangeReason = !empty($resultChangeHistory) ? (string) (end($resultChangeHistory)['msg'] ?? '') : '';

$mandatoryClass = "";
if (!empty($_SESSION['instance']['type']) && $general->isLISInstance()) {
	$mandatoryClass = "isRequired";
}

$minPatientIdLength = 0;
if (isset($arr['generic_min_patient_id_length']) && $arr['generic_min_patient_id_length'] != "") {
	$minPatientIdLength = $arr['generic_min_patient_id_length'];
}

$userId =  $_SESSION['userId'];
$checkNonAdminUser = $general->isNonAdmin($userId);

if($checkNonAdminUser != 1 && $genericResultInfo['locked'] == 'yes')
{
	http_response_code(403);
    throw new SystemException('Invalid URL', 403);
}
elseif($genericResultInfo['locked'] == 'no' && _isAllowed("/generic-tests/requests/edit-request.php") || $checkNonAdminUser == 1 && $genericResultInfo['locked'] == 'yes')
{
?><!-- Content Wrapper. Contains page content -->
<link rel="stylesheet" href="/assets/css/jquery.multiselect.css" type="text/css" />
<style>
	.ms-choice {
		border: 0px solid #aaa;
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

	.facilitySectionInput,
	.patientSectionInput,
	.caseInformationInput,
	#otherSection .col-md-6 {
		margin: 3px 0px;
	}

	.facilitySectionInput,
	.patientSectionInput .select2,
	.caseInformationInput .select2,
	#otherSection .col-md-6 .select2 {
		margin: 3px 0px;
	}

	.table>tbody>tr>td {
		border-top: none;
	}

	.form-control {
		width: 100% !important;
	}

	.row {
		margin-top: 6px;
	}

	#sampleCode {
		background-color: #fff;
	}

	select#subTestResult {
		display: none !important;
	}
</style>
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-pen-to-square"></em> <?= _translate("LABORATORY REQUEST FORM"); ?> </h1>
		<ol class="breadcrumb">
			<li><a href="/dashboard/index.php"><em class="fa-solid fa-chart-pie"></em> <?= _translate("Home"); ?></a>
			</li>
			<li class="active"><?= _translate("Edit Request"); ?></li>
		</ol>
	</section>
	<?php
	//print_r(array_column($vlTestReasonResult, 'last_name')$oneDimensionalArray = array_map('current', $vlTestReasonResult));die;
	?>
	<!-- Main content -->
	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span>
					<?= _translate("indicates required fields"); ?> &nbsp;</div>
			</div>
			<div class="box-body">
				<!-- form start -->
				<form class="form-inline" method="post" name="vlRequestFormRwd" id="vlRequestFormRwd" autocomplete="off"
					action="edit-request-helper.php">
					<?php
					$formMode                 = 'edit';
					$showBarcode              = false;
					$showPatientSearch        = false;
					$showSaveNextClone        = false;
					$showSubTestPicker        = false;
					$showChangeReason         = true;
					$disableNonResult         = false;
					$onTestTypeChange         = 'getTestTypeForm();';
					$onFacilityChange         = 'fillFacilityDetails(this);';
					$onLabChange              = 'autoFillFocalDetails();';
					$onSampleCollectionChange = 'checkCollectionDate(this.value);';
					include __DIR__ . '/_request-form-body.php';
					?>
					</div>
</form>
</div>
</section>
</div>
<script type="text/javascript" src="/assets/js/jquery.multiselect.js"></script>

<script type="text/javascript"
	src="/assets/js/datalist-css.min.js?v=<?= filemtime(WEB_ROOT . "/assets/js/datalist-css.min.js") ?>"></script>
<script>
	let provinceName = true;
	let facilityName = true;
	let testCounter = <?php echo (empty($genericTestInfo)) ? (1) : count($genericTestInfo); ?>;
	let __clone = null;
	let reason = null;
	let resultValue = null;
	$(document).ready(function () {


		checkCollectionDate('<?php echo $genericTestInfo['sample_collection_date']; ?>');


		$("#subTestResult").multipleSelect({
			placeholder: '<?php echo _translate("Select Sub Tests"); ?>',
			width: '100%'
		});
		var testType = $("#testType").val();
		//getTestTypeConfigList(testType);



		let dateFormatMask = '<?= $_SESSION['jsDateFormatMask'] ?? '99-aaa-9999'; ?>';
		$('.date').mask(dateFormatMask);
		$('.dateTime').mask(dateFormatMask + ' 99:99');

		$('.result-focus').change(function (e) {
			var status = false;
			$(".result-focus").each(function (index) {
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
		});

		$("#labId,#facilityId,#sampleCollectionDate").on('change', function () {

			if ($("#labId").val() != '' && $("#labId").val() == $("#facilityId").val() && $(
				"#sampleDispatchedDate").val() == "") {
				$('#sampleDispatchedDate').val($('#sampleCollectionDate').val());
			}
			if ($("#labId").val() != '' && $("#labId").val() == $("#facilityId").val() && $(
				"#sampleReceivedDate").val() == "") {
				$('#sampleReceivedDate').val($('#sampleCollectionDate').val());
				$('#sampleReceivedAtHubOn').val($('#sampleCollectionDate').val());
			}
		});


		autoFillFocalDetails();
		$("#specimenType").select2({
			width: '100%',
			placeholder: "<?php echo _translate("Select Specimen Type", true); ?>"
		});
		$("#testType").select2({
			width: '100%',
			placeholder: "<?php echo _translate("Select Test Type", true); ?>"
		});
		$('#labId').select2({
			width: '100%',
			placeholder: "<?php echo _translate("Select Testing Lab", true); ?>"
		});
		$('#facilityId').select2({
			width: '100%',
			placeholder: "<?php echo _translate("Select Clinic/Health Center", true); ?>"
		});
		$('#reviewedBy').select2({
			width: '100%',
			placeholder: "<?php echo _translate("Select Reviewed By", true); ?>"
		});
		$('#testedBy').select2({
			width: '100%',
			placeholder: "<?php echo _translate("Select Tested By", true); ?>"
		});

		$('#approvedBy').select2({
			width: '100%',
			placeholder: "<?php echo _translate("Select Approved By", true); ?>"
		});
		$('#facilityId').select2({
			width: '100%',
			placeholder: "<?php echo _translate("Select Clinic/Health Center", true); ?>"
		});
		$('#district').select2({
			width: '100%',
			placeholder: "<?php echo _translate("District", true); ?>"
		});
		$('#province').select2({
			width: '100%',
			placeholder: "<?php echo _translate("Province", true); ?>"
		});
		$('#implementingPartner').select2({
			width: '100%',
			placeholder: "<?php echo _translate("Implementing Partner", true); ?>"
		});
		$('#fundingSource').select2({
			width: '100%',
			placeholder: "<?php echo _translate("Funding Source", true); ?>"
		});

		//getAge();
		getTestTypeForm();

		getfacilityProvinceDetails($("#facilityId").val());

		setTimeout(function () {
			$("#vlResult").trigger('change');
			$("#isSampleRejected").trigger('change');
			// just triggering sample collection date is enough,
			// it will automatically do everything that labId and facilityId changes will do
			//$("#sampleCollectionDate").trigger('change');
			__clone = $(".labSectionBody").clone();
			reason = ($("#reasonForResultChanges").length) ? $("#reasonForResultChanges").val() : '';
			resultValue = $("#vlResult").val();

			$(".labSection").on("change", function () {
				if ($.trim(resultValue) != '') {
					if ($(".labSection").serialize() === $(__clone).serialize()) {
						$(".reasonForResultChanges").css("display", "none");
						$("#reasonForResultChanges").removeClass("isRequired");
					} else {
						$(".reasonForResultChanges").css("display", "block");
						$("#reasonForResultChanges").addClass("isRequired");
					}
				}
			});

		}, 500);

		checkPatientReceivesms('<?php echo $genericResultInfo['consent_to_receive_sms']; ?>');

		$("#reqClinician").select2({
			placeholder: "<?= _translate('Enter Requesting Clinician Name', true); ?>",
			minimumInputLength: 0,
			width: '100%',
			allowClear: true,
			id: function (bond) {
				return bond._id;
			},
			ajax: {
				placeholder: "<?= _translate('Type one or more character to search'); ?>",
				url: "/includes/get-data-list.php",
				dataType: 'json',
				delay: 250,
				data: function (params) {
					return {
						fieldName: 'request_clinician_name',
						tableName: 'form_generic',
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

		$("#reqClinician").change(function () {
			$.blockUI();
			var search = $(this).val();
			if ($.trim(search) != '') {
				$.get("/includes/get-data-list.php", {
					fieldName: 'request_clinician_name',
					tableName: 'form_generic',
					returnField: 'request_clinician_phone_number',
					limit: 1,
					q: search,
				},
					function (data) {
						if (data != "") {
							$("#reqClinicianPhoneNumber").val(data);
						}
					});
			}
			$.unblockUI();
		});

		$("#vlFocalPerson").select2({
			placeholder: "<?= _translate('Enter Request Focal name'); ?>",
			minimumInputLength: 0,
			width: '100%',
			allowClear: true,
			id: function (bond) {
				return bond._id;
			},
			ajax: {
				placeholder: "<?= _translate('Type one or more character to search'); ?>",
				url: "/includes/get-data-list.php",
				dataType: 'json',
				delay: 250,
				data: function (params) {
					return {
						fieldName: 'testing_lab_focal_person',
						tableName: 'form_generic',
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
					fieldName: 'testing_lab_focal_person',
					tableName: 'form_generic',
					returnField: 'testing_lab_focal_person_phone_number',
					limit: 1,
					q: search,
				},
					function (data) {
						if (data != "") {
							$("#vlFocalPersonPhoneNumber").val(data);
						}
					});
			}
			$.unblockUI();
		});

		$('#vlResult').on('change', function () {
			if ($(this).val().trim().toLowerCase() == 'failed' || $(this).val().trim().toLowerCase() ==
				'error') {
				if ($(this).val().trim().toLowerCase() == 'failed') {
					$('.reasonForFailure').show();
					$('#reasonForFailure').addClass('isRequired');
				}
			} else {
				$('.reasonForFailure').hide();
				$('#reasonForFailure').removeClass('isRequired');
			}
		});
		getSubTestList($("#testType").val());
		loadSubTests();
	});

	function checkSampleNameValidation(tableName, fieldName, id, fnct, alrt) {
		if ($.trim($("#" + id).val()) != '') {
			$.blockUI();
			$.post("/generic-tests/requests/checkSampleDuplicate.php", {
				tableName: tableName,
				fieldName: fieldName,
				value: $("#" + id).val(),
				fnct: fnct,
				format: "html"
			},
				function (data) {
					if (data != 0) { }
				});
			$.unblockUI();
		}
	}


	function clearDOB(val) {
		if ($.trim(val) != "") {
			$("#dob").val("");
		}
	}


	function showPatientList() {
		$("#showEmptyResult").hide();
		if ($.trim($("#artPatientNo").val()) != '') {
			$.post("/generic-tests/requests/search-patients.php", {
				artPatientNo: $.trim($("#artPatientNo").val())
			},
				function (data) {
					if (data >= '1') {
						showModal('patientModal.php?artNo=' + $.trim($("#artPatientNo").val()), 900, 520);
					} else {
						$("#showEmptyResult").show();
					}
				});
		}
	}

	function getfacilityProvinceDetails(obj) {
		$.blockUI();
		//check facility name`
		var cName = $("#facilityId").val();
		var pName = $("#province").val();
		if (cName != '' && provinceName && facilityName) {
			provinceName = false;
		}
		if (cName != '' && facilityName) {
			$.post("/includes/siteInformationDropdownOptions.php", {
				cName: cName,
				testType: 'generic-tests'
			},
				function (data) {
					if (data != "") {
						details = data.split("###");
						$("#province").html(details[0]);
						$("#district").html(details[1]);
					}
				});
		} else if (pName == '' && cName == '') {
			provinceName = true;
			facilityName = true;
			$("#province").html("<?php echo $province; ?>");
			$("#facilityId").html("<?php echo $facility; ?>");
		}
		$.unblockUI();
	}

	function getProvinceDistricts(obj) {
		$.blockUI();
		var cName = $("#facilityId").val();
		var pName = $("#province").val();
		if (pName != '' && provinceName && facilityName) {
			facilityName = false;
		}
		if ($.trim(pName) != '') {
			//if (provinceName) {
			$.post("/includes/siteInformationDropdownOptions.php", {
				pName: pName,
				testType: 'generic-tests'
			},
				function (data) {
					if (data != "") {
						details = data.split("###");
						$("#facilityId").html(details[0]);
						$("#district").html(details[1]);
						$("#facilityCode").val('');
						$(".facilityDetails").hide();
						$(".facilityEmails").html('');
						$(".facilityMobileNumbers").html('');
						$(".facilityContactPerson").html('');
					}
				});
			//}
		} else if (pName == '' && cName == '') {
			provinceName = true;
			facilityName = true;
			$("#province").html("<?php echo $province; ?>");
			$("#facilityId").html(
				"<option data-code='' data-emails='' data-mobile-nos='' data-contact-person='' value=''> -- Select -- </option>"
			);
		}
		$.unblockUI();
	}

	function getFacilities(obj) {
		//alert(obj);
		$.blockUI();
		var dName = $("#district").val();
		var cName = $("#facilityId").val();
		if (dName != '') {
			$.post("/includes/siteInformationDropdownOptions.php", {
				dName: dName,
				cliName: cName,
				fType: 2,
				testType: 'generic-tests'
			},
				function (data) {
					if (data != "") {
						details = data.split("###");
						$("#facilityId").html(details[0]);
						//$("#labId").html(details[1]);
						$(".facilityDetails").hide();
						$(".facilityEmails").html('');
						$(".facilityMobileNumbers").html('');
						$(".facilityContactPerson").html('');
					}
				});
		}
		$.unblockUI();
	}

	function getfacilityProvinceDetails(obj) {
		$.blockUI();
		//check facility name
		var cName = $("#facilityId").val();
		var pName = $("#province").val();
		if (cName != '' && provinceName && facilityName) {
			provinceName = false;
		}
		if (cName != '' && facilityName) {
			$.post("/includes/siteInformationDropdownOptions.php", {
				cName: cName,
				testType: 'generic-tests'
			},
				function (data) {
					if (data != "") {
						details = data.split("###");
						$("#province").html(details[0]);
						$("#district").html(details[1]);
						$("#clinicianName").val(details[2]);
					}
				});
		} else if (pName == '' && cName == '') {
			provinceName = true;
			facilityName = true;
			$("#province").html("<?php echo $province; ?>");
			$("#facilityId").html("<?php echo $facility; ?>");
		}
		$.unblockUI();
	}

	function fillFacilityDetails(obj) {
		getfacilityProvinceDetails(obj)
		$("#facilityCode").val($('#facilityId').find(':selected').data('code'));
		var femails = $('#facilityId').find(':selected').data('emails');
		var fmobilenos = $('#facilityId').find(':selected').data('mobile-nos');
		var fContactPerson = $('#facilityId').find(':selected').data('contact-person');
		if ($.trim(femails) != '' || $.trim(fmobilenos) != '' || fContactPerson != '') {
			$(".facilityDetails").show();
		} else {
			$(".facilityDetails").hide();
		}
		($.trim(femails) != '') ? $(".femails").show() : $(".femails").hide();
		($.trim(femails) != '') ? $(".facilityEmails").html(femails) : $(".facilityEmails").html('');
		($.trim(fmobilenos) != '') ? $(".fmobileNumbers").show() : $(".fmobileNumbers").hide();
		($.trim(fmobilenos) != '') ? $(".facilityMobileNumbers").html(fmobilenos) : $(".facilityMobileNumbers").html('');
		($.trim(fContactPerson) != '') ? $(".fContactPerson").show() : $(".fContactPerson").hide();
		($.trim(fContactPerson) != '') ? $(".facilityContactPerson").html(fContactPerson) : $(".facilityContactPerson").html(
			'');
	}
	$("input:radio[name=gender]").click(function () {
		if ($(this).val() == 'male' || $(this).val() == 'unreported') {
			$('.femaleSection').hide();
			$('input[name="breastfeeding"]').prop('checked', false);
			$('input[name="patientPregnant"]').prop('checked', false);
		} else if ($(this).val() == 'female') {
			$('.femaleSection').show();
		}
	});
	$("#sampleTestingDateAtLab").change(function () {
		if ($(this).val() != "") {
			$(".result-fields").attr("disabled", false);
			$(".result-fields").addClass("isRequired");
			$(".result-span").show();
			$('.vlResult').css('display', 'block');
			$('.rejectionReason').hide();
			$('#rejectionReason').removeClass('isRequired');
			$('#rejectionDate').removeClass('isRequired');
			$('#rejectionReason').val('');
			$(".review-approve-span").hide();
			$("#isSampleRejected").trigger('change');
		}
	});
	$("#isSampleRejected").on("change", function () {

		// Prompt for a change reason when the rejection status differs from what was saved.
		if ($(this).val() !== <?= json_encode((string) ($genericResultInfo['is_sample_rejected'] ?? '')) ?>) {
			$('.change-reason, .reasonForResultChanges').show();
			$('#reasonForResultChanges').addClass('isRequired');
		}

		if ($(this).val() == 'yes') {
			$('.rejectionReason').show();
			$('.vlResult').css('display', 'none');
			$("#sampleTestingDateAtLab, #vlResult").val("");
			$(".result-fields").val("");
			$(".result-fields").attr("disabled", true);
			$(".result-fields").removeClass("isRequired");
			$(".result-span").hide();
			$(".review-approve-span").show();
			$('#rejectionReason').addClass('isRequired');
			$('#rejectionDate').addClass('isRequired');
			$('#reviewedBy').addClass('isRequired');
			$('#reviewedOn').addClass('isRequired');
			$('#approvedBy').addClass('isRequired');
			$('#approvedOn').addClass('isRequired');
			$(".result-optional").removeClass("isRequired");
			$("#reasonForFailure").removeClass('isRequired');
		} else if ($(this).val() == 'no') {
			$(".result-fields").attr("disabled", false);
			$(".result-fields").addClass("isRequired");
			$(".result-span").show();
			$(".review-approve-span").show();
			$('.vlResult').css('display', 'block');
			$('.rejectionReason').hide();
			$('#rejectionReason').removeClass('isRequired');
			$('#rejectionDate').removeClass('isRequired');
			$('#rejectionReason').val('');
			$('#reviewedBy').addClass('isRequired');
			$('#reviewedOn').addClass('isRequired');
			$('#approvedBy').addClass('isRequired');
			$('#approvedOn').addClass('isRequired');
		} else {
			$(".result-fields").attr("disabled", false);
			$(".result-fields").removeClass("isRequired");
			$(".result-optional").removeClass("isRequired");
			$(".result-span").show();
			$('.vlResult').css('display', 'block');
			$('.rejectionReason').hide();
			$(".result-span").hide();
			$(".review-approve-span").hide();
			$('#rejectionReason').removeClass('isRequired');
			$('#rejectionDate').removeClass('isRequired');
			$('#rejectionReason').val('');
			$('#reviewedBy').removeClass('isRequired');
			$('#reviewedOn').removeClass('isRequired');
			$('#approvedBy').removeClass('isRequired');
			$('#approvedOn').removeClass('isRequired');
		}
	});


	$('#testingPlatform').on("change", function () {
		$(".vlResult").show();
		//$('#vlResult, #isSampleRejected').addClass('isRequired');
		$("#isSampleRejected").val("");
		//$("#isSampleRejected").trigger("change");
	});


	function checkRejectionReason() {
		var rejectionReason = $("#rejectionReason").val();
		if (rejectionReason == "other") {
			$("#newRejectionReason").show();
			$("#newRejectionReason").addClass("isRequired");
		} else {
			$("#newRejectionReason").hide();
			$("#newRejectionReason").removeClass("isRequired");
			$('#newRejectionReason').val("");
		}
	}

	function validateNow() {
		flag = deforayValidator.init({
			formId: 'vlRequestFormRwd'
		});

		/* $('.isRequired').each(function() {
			($(this).val() == '') ? $(this).css('background-color', '#FFFF99'): $(this).css('background-color', '#FFFFFF')
		}); */
		if (flag) {
			$.blockUI();
			document.getElementById('vlRequestFormRwd').submit();
		}
	}

	function checkPatientReceivesms(val) {
		if (val == 'yes') {
			$('#patientPhoneNumber').addClass('isRequired');
		} else {
			$('#patientPhoneNumber').removeClass('isRequired');
		}
	}

	function autoFillFocalDetails() {
		// labId = $("#labId").val();
		// if ($.trim(labId) != '') {
		//     $("#vlFocalPerson").val($('#labId option:selected').attr('data-focalperson')).trigger('change');
		//     $("#vlFocalPersonPhoneNumber").val($('#labId option:selected').attr('data-focalphone'));
		// }
	}

	// Apply a test type's optional "Advanced Configuration" (from getTestTypeForm.php):
	// toggle a few non-dynamic fields and override the Patient ID label. Missing/empty
	// config => every field shown, label unchanged (backward compatible).
	function applyAdvancedFormConfig(cfg) {
		cfg = cfg || {};
		var toggleField = function (selector, on) {
			var $wrap = $(selector).first().closest('.col-md-6');
			if (!$wrap.length) { return; }
			$wrap.toggle(on);
			if (!on) { $wrap.find('.isRequired').removeClass('isRequired'); }
		};
		// Sex-coupled fields: when allowed, keep them under the female-section/sex logic;
		// when hidden by config, detach so a later sex change can't re-show them.
		var toggleFemaleField = function (selector, on) {
			var $wrap = $(selector).first().closest('.col-md-6');
			if (!$wrap.length) { return; }
			if (on) {
				var isFemale = $('input:radio[name=gender]:checked').val() === 'female';
				$wrap.addClass('femaleSection').toggle(isFemale);
			} else {
				$wrap.removeClass('femaleSection').hide().find('.isRequired').removeClass('isRequired');
			}
		};
		toggleField('#implementingPartner', cfg.showImplementingPartner !== 'no');
		toggleField('#fundingSource', cfg.showFundingSource !== 'no');
		toggleField('#patientFirstName', cfg.showPatientName !== 'no');
		toggleField('#laboratoryNumber', cfg.showLaboratoryNumber !== 'no');
		toggleField('#ageInMonths', cfg.showAgeInMonths !== 'no');
		toggleFemaleField('#pregYes', cfg.showPregnancy !== 'no');
		toggleFemaleField('#breastfeedingYes', cfg.showBreastfeeding !== 'no');
		if (cfg.patientIdLabel && String(cfg.patientIdLabel).trim() !== '') {
			var lbl = String(cfg.patientIdLabel).trim();
			$('#artNoLabelText').text(lbl);
			$('#artNo').attr('placeholder', '<?= _jsTranslate('Enter') ?> ' + lbl).attr('title', lbl);
		}
	}

	function getTestTypeForm() {
		removeDynamicForm();
		var testType = $("#testType").val();
		getTestTypeConfigList(testType);
		getSubTestList(testType);
		if (testType != "") {
			var editId = $('#vlSampleId').val();
			var resultVal = $('#result').val() ? $('#result').val() : '<?php echo $genericResultInfo['result']; ?>';
			var testedTypeForm = '<?php echo base64_encode((string) $genericResultInfo['test_type_form']); ?>';
			var testResultUnit = '<?php echo $genericResultInfo['result_unit']; ?>';
			$(".requestForm").show();
			$.post("/generic-tests/requests/getTestTypeForm.php", {
				testType: testType,
				vlSampleId: editId,
				result: resultVal,
				testTypeForm: testedTypeForm,
				// resultInterpretation: '<?php echo $genericResultInfo['final_result_interpretation']; ?>',
				resultUnit: testResultUnit,
			},
				function (data) {
					data = JSON.parse(data);
					applyAdvancedFormConfig(data.advancedFormConfig);
					if (typeof (data.facilitySection) != "undefined" && data.facilitySection !== null && data.facilitySection.length > 0) {
						$("#facilitySection").html(data.facilitySection);
					}
					if (typeof (data.caseInformation) != "undefined" && data.caseInformation !== null && data.caseInformation.length > 0) {
						$("#caseInformation").html(data.caseInformation);
						$("#caseInformationBox").show();
					} else {
						$("#caseInformationBox").hide();
					}
					if (typeof (data.patientSection) != "undefined" && data.patientSection !== null && data.patientSection.length > 0) {
						$("#patientSection").after(data.patientSection);
					}
					if (typeof (data.labSection) != "undefined" && data.labSection !== null && data.labSection.length > 0) {
						$("#labSection").html(data.labSection);
					}
					if (typeof (data.result) != "undefined" && data.result !== null && data.result.length > 0) {
						$("#resultSection").html(data.result);
						$('#resultSection').show();
					} 
					if (typeof (data.specimenSection) != "undefined" && data.specimenSection !== null && data.specimenSection.length > 0) {
						$("#specimenSection").after(data.specimenSection);
					}
					if (typeof (data.otherSection) != "undefined" && data.otherSection !== null && data.otherSection.length > 0) {
						$("#otherSection").html(data.otherSection);
					}

					$(".dynamicFacilitySelect2").select2({
						width: '100%',
						placeholder: "<?php echo _translate("Select any one of the option"); ?>"
					});

					$(".dynamicSelect2").select2({
						width: '100%',
						placeholder: "<?php echo _translate("Select any one of the option"); ?>"
					});

					$(".multipleSelectize").selectize({
						plugins: ["restore_on_backspace", "remove_button", "clear_button"],
					});
					if ($('#resultType').val() == 'qualitative') {
						// $('.final-result-row').attr('colspan', 4)
						$('.testResultUnit').hide();
					} else {
						// $('.final-result-row').attr('colspan', 5)
						$('.testResultUnit').show();
					}
				});
		} else {
			removeDynamicForm();
		}
	}

	function removeDynamicForm() {
		$(".facilitySection").html('');
		$(".patientSectionInput").remove();
		$("#labSection").html('');
		$(".specimenSectionInput").remove();
		$("#caseInformation").html('');
		$("#caseInformationBox").hide();
		$("#otherSection").html('');
		$(".requestForm").hide();
	}


	function getTestTypeConfigList(testTypeId) {

		$.post("/includes/get-test-type-config.php", {
			testTypeId: testTypeId,
			sampleTypeId: '<?php echo $genericResultInfo['specimen_type']; ?>',
			testReasonId: '<?php echo $genericResultInfo['reason_for_testing']; ?>',
			//testMethodId: '< ?php echo $genericResultInfo['reason_for_testing']; ?>'
		},
			function (data) {
				Obj = $.parseJSON(data);
				if (data != "") {
					$("#specimenType").html(Obj['sampleTypes']);
					$("#reasonForTesting").html(Obj['testReasons']);

					if ($("#testName1").val() == '')
						$("#testName1").html(Obj['testMethods']);
					if ($("#testResultUnit1").val() == '')
						$("#testResultUnit1").html(Obj['testResultUnits']);
					if ($("#finalTestResultUnit").val() == '')
						$("#finalTestResultUnit").html(Obj['testResultUnits']);

				}
			});
	}

	function loadSubTests() {
		var testType = $("#testType").val();
		var subTestResult = $("#subTestResult").val();
		if (testType != "") {
			var editId = $('#vlSampleId').val();
			var resultVal = $('#result').val() ? $('#result').val() : '<?php echo $genericResultInfo['result']; ?>';
			var testedTypeForm = '<?php echo base64_encode((string) $genericResultInfo['test_type_form']); ?>';
			var testResultUnit = '<?php echo $genericResultInfo['result_unit']; ?>';
			$(".requestForm").show();
			$.post("/generic-tests/requests/getTestTypeForm.php", {
				vlSampleId: editId,
				result: resultVal,
				testTypeForm: testedTypeForm,
				testType: testType,
				subTests: subTestResult
				// resultInterpretation: '<?php echo $genericResultInfo['final_result_interpretation']; ?>',
			},
				function (data) {
					data = JSON.parse(data);
					applyAdvancedFormConfig(data.advancedFormConfig);
					$("#resultSection").html('');
					if (typeof (data.result) != "undefined" && data.result !== null && data.result.length > 0) {
						$("#resultSection").html(data.result);
						$('#resultSection').show();
					} 

					$(".dynamicFacilitySelect2").select2({
						width: '100%',
						placeholder: "<?php echo _translate("Select any one of the option"); ?>"
					});
					$(".dynamicSelect2").select2({
						width: '100%',
						placeholder: "<?php echo _translate("Select any one of the option"); ?>"
					});

					if ($('#resultType').val() == 'qualitative') {
						// $('.final-result-row').attr('colspan', 4)
						$('.testResultUnit').hide();
					} else {
						// $('.final-result-row').attr('colspan', 5)
						$('.testResultUnit').show();
					}
				});
		} else {
			$(".specimenSectionInput").remove();
			$("#caseInformation").html('');
			$("#caseInformationBox").hide();
		}

	}

	function getSubTestList(testType) {
		$.post("/generic-tests/requests/get-sub-test-list.php", {
			subTests: '<?php echo base64_encode((string) $genericResultInfo['sub_tests']); ?>',
			testTypeId: testType
		},
			function (data) {
				if (data != "") {
					$("#subTestResult").html(data);
					$("#subTestResult").multipleSelect({
						placeholder: '<?php echo _translate("Select Sub Tests"); ?>',
						width: '100%'
					});
					var length = $('#subTestResult > option').length;
					if (length > 1) {
						$('.subTestFields').show();
					} else {
						$('.subTestFields').hide();
					}
				}
			});
	}

	function addTestRow(row, subTest) {
		var unitTest = '';
		subrow = document.getElementById("testKitNameTable" + row).rows.length
		$('.ins-row-' + row + subrow).attr('disabled', true);
		$('.ins-row-' + row + subrow).addClass('disabled');
		testCounter = (subrow + 1);
		options = $("#resultListQl" + row).html();
		testMethodOptions = $("#testName" + row + (testCounter - 1)).html();
		if ($('.qualitative-field').hasClass('testResultUnit')) {
			unitTest = `<td class="testResultUnit">
					<select class="form-control resultUnit" id="testResultUnit${row}${testCounter}" name="testResultUnit[${subTest}][]" placeholder='<?php echo _translate("Enter test result unit"); ?>' title='<?php echo _translate("Please enter test result unit"); ?>'>
						 <option value="">--Select--</option>
						 <?php foreach ($testResultUnits as $key => $unit) { ?>
							  <option value="<?php echo $key; ?>"><?php echo $unit; ?></option>
						 <?php } ?>
					</select>
					</td>`;
		}
		let rowString = `<tr>
					<td class="text-center">${(subrow + 1)}</td>
					<td>
						 <select class="form-control test-name-table-input" id="testName${row}${testCounter}" name="testName[${subTest}][]" title="Please enter the name of the Testkit (or) Test Method used">${testMethodOptions}</select>
						 <input type="text" name="testNameOther[${subTest}][]" id="testNameOther${row}${testCounter}" class="form-control testNameOther${testCounter}" title="Please enter the name of the Testkit (or) Test Method used" placeholder="<?= _translate('Please enter the name of the Testkit (or) Test Method used'); ?>" style="display: none;margin-top: 10px;" />
					</td>
					<td><input type="text" name="testDate[${subTest}][]" id="testDate${row}${testCounter}" class="form-control test-name-table-input dateTime" placeholder="<?= _translate('Tested on'); ?>" title="Please enter the tested on for row ${testCounter}" /></td>
					<td><select name="testingPlatform[${subTest}][]" id="testingPlatform${row}${testCounter}" class="form-control test-name-table-input" title="Please select the Testing Platform for ${testCounter}"><?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?></select></td>
					<td class="kitlabels" style="display: none;"><input type="text" name="lotNo[${subTest}][]" id="lotNo${row}${testCounter}" class="form-control kit-fields${testCounter}" placeholder="<?= _translate('Kit lot no'); ?>" title="Please enter the kit lot no. for row ${testCounter}" style="display:none;"/></td>
					<td class="kitlabels" style="display: none;"><input type="text" name="expDate[${subTest}][]" id="expDate${row}${testCounter}" class="form-control expDate kit-fields${testCounter}" placeholder="<?= _translate('Expiry date'); ?>" title="Please enter the expiry date for row ${testCounter}" style="display:none;"/></td>
					<td><select class="form-control result-select" name="testResult[${subTest}][]" id="testResult${row}${testCounter}" title="Enter result">${options}</select></td>
					${unitTest}
					<td style="vertical-align:middle;text-align: center;width:100px;">
						 <a class="btn btn-xs btn-primary ins-row-${row}${testCounter} test-name-table" href="javascript:void(0);" onclick="addTestRow(${row}, \'${subTest}\');"><em class="fa-solid fa-plus"></em></a>&nbsp;
						 <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode, ${row},${subrow});"><em class="fa-solid fa-minus"></em></a>
					</td>
			   </tr>`;
		$("#testKitNameTable" + row).append(rowString);
		$("#testName" + testCounter).val("");
		$('.date').datepicker({
			changeMonth: true,
			changeYear: true,
			onSelect: function () {
				$(this).change();
			},
			dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
			timeFormat: "HH:mm",
			maxDate: "Today",
			yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
		}).click(function () {
				$('.ui-datepicker-calendar').show();
			});

	$('.expDate').datepicker({
		changeMonth: true,
		changeYear: true,
		onSelect: function () {
			$(this).change();
		},
		dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
		timeFormat: "HH:mm",
		// minDate: "Today",
		yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
		}).click(function () {
			$('.ui-datepicker-calendar').show();
		});



	if ($('.kitlabels').is(':visible') == true) {
		$('.kitlabels').show();
	}
	if ($('#resultType').val() == 'qualitative') {
		// $('.final-result-row').attr('colspan', 4)
		$('.testResultUnit').hide();
	} else {
		// $('.final-result-row').attr('colspan', 5)
		$('.testResultUnit').show();
	}
	}

	function removeTestRow(el, row, subrow) {
		$('.ins-row-' + row + subrow).attr('disabled', false);
		$('.ins-row-' + row + subrow).removeClass('disabled');
		$(el).fadeOut("slow", function () {
			el.parentNode.removeChild(el);
			rl = document.getElementById("testKitNameTable" + row).rows.length;
			if (rl == 0) {
				testCounter = 0;
				addTestRow(row, (subrow + 1));
			}
		});
	}

	function updateInterpretationResult(obj) {
		if (obj.value) {
			$.post("get-result-interpretation.php", {
				result: obj.value,
				resultType: $('#resultType').val(),
				testType: $('#testType').val()
			},
				function (interpretation) {
					if (interpretation != "") {
						$('#resultInterpretation').val(interpretation);
					} else {
						$('#resultInterpretation').val('');
					}
				});
		}
	}
</script>
<?php } require_once APPLICATION_PATH . '/footer.php';
