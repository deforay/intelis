<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;
use App\Services\UsersService;
use App\Services\GenericTestsService;
use App\Utilities\DateUtility;
use App\Services\CommonService;



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

if ($general->isSTSInstance()) {
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

$reasonForChangeArr = explode('##', (string) $genericResultInfo['reason_for_test_result_changes']);
$reasonForChange = $reasonForChangeArr[1];
$mandatoryClass = "";
if (!empty($_SESSION['instance']['type']) && $general->isLISInstance()) {
	$mandatoryClass = "isRequired";
}

$minPatientIdLength = 0;
if (isset($arr['generic_min_patient_id_length']) && $arr['generic_min_patient_id_length'] != "") {
	$minPatientIdLength = $arr['generic_min_patient_id_length'];
}

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
	#otherSection .col-md-6 {
		margin: 3px 0px;
	}

	.facilitySectionInput,
	.patientSectionInput .select2,
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
					<div class="box-body">
						<div class="box box-primary">
							<div class="box-header with-border">
								<h3 class="box-title">
									<?= _translate("Clinic Information: (To be filled by requesting Clinican/Nurse)"); ?>
								</h3>
							</div>
							<div class="row">
								<div class="col-md-6">
									<label class="col-lg-5" for="testType"><?= _translate("Test Type"); ?></label>
									<div class="col-lg-7">
										<select class="form-control" name="testType" id="testType"
											title="Please choose test type" style="width:100%;"
											onchange="getTestTypeForm()">
											<option value=""> <?= _translate("-- Select --"); ?> </option>
											<?php foreach ($testTypeResult as $testType) { ?>
												<option value="<?php echo $testType['test_type_id'] ?>" <?php echo ($genericResultInfo['test_type'] == $testType['test_type_id']) ? "selected='selected'" : "" ?>>
													<?php echo $testType['test_standard_name'] . ' (' . $testType['test_loinc_code'] . ')' ?>
												</option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
							<div class="row requestForm" style="display:none;">
								<div class="col-md-6">
									<label class="col-lg-5" for="sampleCode"><?= _translate("Sample ID"); ?> <span
											class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired <?php echo $sampleClass; ?>"
											id="sampleCode" name="sampleCode" <?php echo $maxLength; ?>
											placeholder="<?= _translate('Enter Sample ID'); ?>" readonly="readonly"
											title="<?= _translate("Please make sure you have selected Sample Collection Date and Requesting Facility"); ?>"
											value="<?php echo $genericResultInfo[$sampleCode]; ?>" style="width:100%;"
											onchange="checkSampleNameValidation('form_generic','<?php echo $sampleCode; ?>',this.id,'<?php echo "sample_id##" . $genericResultInfo["sample_id"]; ?>','This sample number already exists.Try another number',null)" />
										<input type="hidden" name="sampleCodeCol"
											value="<?= htmlspecialchars((string) $genericResultInfo['sample_code']); ?>"
											style="width:100%;">
									</div>
								</div>
								<div class="col-md-6">
									<label class="col-lg-5" for="sampleReordered">
										<?= _translate("Sample Reordered"); ?></label>
									<div class="col-lg-7">
										<input type="checkbox" class="" id="sampleReordered" name="sampleReordered"
											value="yes" <?php echo (trim((string) $genericResultInfo['sample_reordered']) === 'yes') ? 'checked="checked"' : '' ?> title="Please indicate if this is a reordered sample">

									</div>
								</div>
							</div>
							<div class="requestForm" style="display:none;">
								<div class="row">
									<div class="col-md-6">
										<label class="col-lg-5" for="province"><?= _translate("State/Province"); ?>
											<span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<select class="form-control isRequired" name="province" id="province"
												title="Please choose state" style="width:100%;"
												onchange="getProvinceDistricts(this);">
												<?php echo $province; ?>
											</select>
										</div>
									</div>
									<div class="col-md-6">
										<label class="col-lg-5" for="district"><?= _translate("District/County"); ?>
											<span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<select class="form-control isRequired" name="district" id="district"
												title="Please choose county" style="width:100%;"
												onchange="getFacilities(this);">
												<option value=""> <?= _translate("-- Select --"); ?> </option>
											</select>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-6">
										<label class="col-lg-5"
											for="facilityId"><?= _translate("Clinic/Health Center"); ?> <span
												class="mandatory">*</span></label>
										<div class="col-lg-7">
											<select class="form-control isRequired" id="facilityId" name="facilityId"
												title="Please select clinic/health center name" style="width:100%;"
												onchange="fillFacilityDetails(this);">

												<?= $facility; ?>
											</select>
										</div>
									</div>
									<div class="col-md-6" style="display:none;">
										<label class="col-lg-5"
											for="facilityCode"><?= _translate("Clinic/Health Center Code"); ?> </label>
										<div class="col-lg-7">
											<input type="text" class="form-control" style="width:100%;"
												name="facilityCode" id="facilityCode"
												placeholder="<?= _translate('Clinic/Health Center Code'); ?>"
												title="<?= _translate('Please enter clinic/health center code'); ?>"
												value="<?php echo $facilityResult[0]['facility_code']; ?>">
										</div>
									</div>
									<div class="col-md-6">
										<label class="col-lg-5"
											for="implementingPartner"><?= _translate("Implementing Partner"); ?></label>
										<div class="col-lg-7">
											<select class="form-control" name="implementingPartner"
												id="implementingPartner"
												title="<?= _translate('Please choose implementing partner'); ?>"
												style="width:100%;">
												<option value=""> <?= _translate("-- Select --"); ?> </option>
												<?php foreach ($implementingPartnerList as $implementingPartner) { ?>
													<option
														value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>"
														<?php echo ($implementingPartner['i_partner_id'] == $genericResultInfo['implementing_partner']) ? 'selected="selected"' : ''; ?>>
														<?php echo ($implementingPartner['i_partner_name']); ?>
													</option>
												<?php } ?>
											</select>
										</div>
									</div>
								</div>
								<div class="row facilityDetails"
									style="display:<?php echo (trim((string) $facilityResult[0]['facility_emails']) !== '' || trim((string) $facilityResult[0]['facility_mobile_numbers']) !== '' || trim((string) $facilityResult[0]['contact_person']) !== '') ? '' : 'none'; ?>;">
									<div class="col-xs-2 col-md-2 femails"
										style="display:<?php echo (trim((string) $facilityResult[0]['facility_emails']) !== '') ? '' : 'none'; ?>;">
										<strong><?= _translate("Clinic Email(s)"); ?></strong>
									</div>
									<div class="col-xs-2 col-md-2 femails facilityEmails"
										style="display:<?php echo (trim((string) $facilityResult[0]['facility_emails']) !== '') ? '' : 'none'; ?>;">
										<?php echo $facilityResult[0]['facility_emails']; ?>
									</div>
									<div class="col-xs-2 col-md-2 fmobileNumbers"
										style="display:<?php echo (trim((string) $facilityResult[0]['facility_mobile_numbers']) !== '') ? '' : 'none'; ?>;">
										<strong><?= _translate("Clinic Mobile No.(s)"); ?></strong>
									</div>
									<div class="col-xs-2 col-md-2 fmobileNumbers facilityMobileNumbers"
										style="display:<?php echo (trim((string) $facilityResult[0]['facility_mobile_numbers']) !== '') ? '' : 'none'; ?>;">
										<?php echo $facilityResult[0]['facility_mobile_numbers']; ?>
									</div>
									<div class="col-xs-2 col-md-2 fContactPerson"
										style="display:<?php echo (trim((string) $facilityResult[0]['contact_person']) !== '') ? '' : 'none'; ?>;">
										<strong><?= _translate("Clinic Contact Person"); ?> -</strong>
									</div>
									<div class="col-xs-2 col-md-2 fContactPerson facilityContactPerson"
										style="display:<?php echo (trim((string) $user) !== '') ? '' : 'none'; ?>;">
										<?php echo ($user); ?>
									</div>
								</div>


								<div class="row">
									<div class="col-md-6">
										<label class="col-lg-5"
											for="fundingSource"><?= _translate("Funding Source"); ?></label>
										<div class="col-lg-7">
											<select class="form-control" name="fundingSource" id="fundingSource"
												title="Please choose implementing partner" style="width:100%;">
												<option value=""> <?= _translate("-- Select --"); ?> </option>
												<?php foreach ($fundingSourceList as $fundingSource) { ?>
													<option
														value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>"
														<?php echo ($fundingSource['funding_source_id'] == $genericResultInfo['funding_source']) ? 'selected="selected"' : ''; ?>>
														<?php echo ($fundingSource['funding_source_name']); ?>
													</option>
												<?php } ?>
											</select>
										</div>
									</div>
									<div class="col-md-6">
										<label class="col-lg-5" for="labId"><?= _translate("Testing Lab"); ?> <span
												class="mandatory">*</span></label>
										<div class="col-lg-7">
											<select name="labId" id="labId" class="form-control isRequired"
												title="Please choose lab" onchange="autoFillFocalDetails();"
												style="width:100%;">
												<option value=""><?= _translate("-- Select --"); ?></option>
												<?php foreach ($lResult as $labName) { ?>
													<option data-focalperson="<?php echo $labName['contact_person']; ?>"
														data-focalphone="<?php echo $labName['facility_mobile_numbers']; ?>"
														value="<?php echo $labName['facility_id']; ?>" <?php echo (isset($genericResultInfo['lab_id']) && $genericResultInfo['lab_id'] == $labName['facility_id']) ? 'selected="selected"' : ''; ?>>
														<?php echo ($labName['facility_name']); ?>
													</option>
												<?php } ?>
											</select>
										</div>
									</div>
								</div>
								<div class="row" id="facilitySection"></div>
							</div>
						</div>
						<div class="box box-primary requestForm" style="display:none;">
							<div class="box-header with-border">
								<h3 class="box-title"><?= _translate("Patient Information"); ?></h3>
							</div>
							<div class="box-body">
								<div class="row">
									<div class="col-md-6">
										<label class="col-lg-5" for="artNo">EPID Number
											<?php if ($general->isLISInstance()) { ?><span
													class="mandatory">*</span><?php } ?></label>
										<div class="col-lg-7">
											<input type="text" name="artNo" id="artNo"
												class="form-control <?= $mandatoryClass; ?> patientId"
												placeholder="<?= _translate('Enter EPID Number'); ?>"
												title="<?= _translate('Enter EPID Number'); ?>"
												value="<?= htmlspecialchars((string) $genericResultInfo['patient_id']); ?>" />
										</div>

									</div>
									<div class="col-md-6">
										<label class="col-lg-5" for="artNo"><?= _translate("Laboratory Number"); ?>
											<?php if ($general->isLISInstance()) { ?><span
													class="mandatory">*</span><?php } ?></label>
										<div class="col-lg-7">
											<input type="text" name="laboratoryNumber" id="laboratoryNumber"
												class="form-control <?= $mandatoryClass; ?>"
												placeholder="<?= _translate('Enter Laboratory Number'); ?>"
												title="<?= _translate('Enter Laboratory Number'); ?>"
												value="<?= $genericResultInfo['laboratory_number']; ?>" />
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-6">
										<label class="col-lg-5" for="dob"><?= _translate("Date of Birth"); ?> </label>
										<div class="col-lg-7">
											<input type="text" name="dob" id="dob" class="form-control date"
												placeholder="<?= _translate('Enter DOB'); ?>"
												title="<?= _translate('Enter dob'); ?>"
												value="<?= htmlspecialchars((string) $genericResultInfo['patient_dob']); ?>"
												onchange="getAge();" />
										</div>
									</div>
									<div class="col-md-6">
										<label class="col-lg-5"
											for="ageInYears"><?= _translate("If DOB unknown, Age in Years"); ?> </label>
										<div class="col-lg-7">
											<input type="text" name="ageInYears" id="ageInYears"
												class="form-control forceNumeric" maxlength="3"
												placeholder="<?= _translate('Age in Years'); ?>"
												title="<?= _translate('Enter age in years'); ?>"
												value="<?= htmlspecialchars((string) $genericResultInfo['patient_age_in_years']); ?>" />
										</div>
									</div>

								</div>
								<div class="row">
									<div class="col-md-6">
										<label class="col-lg-5"
											for="ageInMonths"><?= _translate("If Age < 1, Age in Months"); ?> </label>
										<div class="col-lg-7">
											<input type="text" name="ageInMonths" id="ageInMonths"
												class="form-control forceNumeric" maxlength="2"
												placeholder="<?= _translate('Age in Month'); ?>"
												title="<?= _translate('Enter age in months'); ?>"
												value="<?= htmlspecialchars((string) $genericResultInfo['patient_age_in_months']); ?>" />
										</div>
									</div>
									<div class="col-md-6">
										<label class="col-lg-5"
											for="patientFirstName"><?= _translate("Patient Name (First Name, Last Name)"); ?>
											<span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<input type="text" name="patientFirstName" id="patientFirstName"
												class="form-control isRequired"
												placeholder="<?= _translate('Enter Patient Name'); ?>"
												title="<?= _translate('Enter patient name'); ?>"
												value="<?php echo $patientFullName; ?>" />
										</div>
									</div>

								</div>
								<div class="row">
									<div class="col-md-6">
										<label class="col-lg-5" for="gender"><?= _translate("Sex"); ?></label>
										<div class="col-lg-7">
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="genderMale" name="gender" value="male"
													title="<?= _translate('Please choose sex'); ?>" <?php echo ($genericResultInfo['patient_gender'] == 'male') ? "checked='checked'" : "" ?>>
												<?= _translate("Male"); ?>
											</label>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="genderFemale" name="gender"
													value="female" title="<?= _translate('Please choose sex'); ?>" <?php echo ($genericResultInfo['patient_gender'] == 'female') ? "checked='checked'" : "" ?>>
												<?= _translate("Female"); ?>
											</label>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="genderUnreported" name="gender"
													value="unreported" title="<?= _translate('Please choose sex'); ?>"
													<?php echo ($genericResultInfo['patient_gender'] == 'unreported') ? "checked='checked'" : "" ?>>
												<?= _translate("Unreported"); ?>
											</label>
										</div>
									</div>
									<div class="col-md-6">
										<label class="col-lg-5"
											for="receiveSms"><?= _translate("Patient consent to receive SMS?"); ?></label>
										<div class="col-lg-7">
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="receivesmsYes" name="receiveSms"
													value="yes"
													title="<?= _translate('Patient consent to receive SMS'); ?>"
													onclick="checkPatientReceivesms(this.value);" <?php echo ($genericResultInfo['consent_to_receive_sms'] == 'yes') ? "checked='checked'" : "" ?>>
												<?= _translate("Yes"); ?>
											</label>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="receivesmsNo" name="receiveSms"
													value="no"
													title="<?= _translate('Patient consent to receive SMS'); ?>"
													onclick="checkPatientReceivesms(this.value);" <?php echo ($genericResultInfo['consent_to_receive_sms'] == 'no') ? "checked='checked'" : "" ?>>
												<?= _translate("No"); ?>
											</label>
										</div>
									</div>

								</div>
								<div class="row ">
									<div class="col-md-6">
										<label class="col-lg-5"
											for="patientPhoneNumber"><?= _translate("Phone Number"); ?></label>
										<div class="col-lg-7">
											<input type="text" name="patientPhoneNumber" id="patientPhoneNumber"
												class="form-control phone-number" maxlength="15"
												placeholder="<?= _translate('Enter Phone Number'); ?>"
												title="<?= _translate('Enter phone number'); ?>"
												value="<?= htmlspecialchars((string) $genericResultInfo['patient_mobile_number']); ?>" />
										</div>
									</div>
									<div class="col-md-6 femaleSection"
										style="display:<?php echo ($genericResultInfo['patient_gender'] == 'female' || $genericResultInfo['patient_gender'] == '' || $genericResultInfo['patient_gender'] == null) ? "" : "none" ?>"
										;>
										<label class="col-lg-5"
											for="patientPregnant"><?= _translate("Is Patient Pregnant?"); ?> </label>
										<div class="col-lg-7">
											<label class="radio-inline">
												<input type="radio" class="" id="pregYes" name="patientPregnant"
													value="yes" title="<?= _translate('Is Patient Pregnant?'); ?>" <?php echo ($genericResultInfo['is_patient_pregnant'] == 'yes') ? "checked='checked'" : "" ?>>
												<?= _translate("Yes"); ?>
											</label>
											<label class="radio-inline">
												<input type="radio" class="" id="pregNo" name="patientPregnant"
													value="no" <?php echo ($genericResultInfo['is_patient_pregnant'] == 'no') ? "checked='checked'" : "" ?>>
												<?= _translate("No"); ?>
											</label>
										</div>
									</div>

								</div>
								<div class="row">
									<div class="col-md-6 femaleSection"
										style="display:<?php echo ($genericResultInfo['patient_gender'] == 'female' || $genericResultInfo['patient_gender'] == '' || $genericResultInfo['patient_gender'] == null) ? "" : "none" ?>"
										;>
										<label class="col-lg-5"
											for="breastfeeding"><?= _translate('Is Patient Breastfeeding?'); ?> </label>
										<div class="col-lg-7">
											<label class="radio-inline">
												<input type="radio" class="" id="breastfeedingYes" name="breastfeeding"
													value="yes" title="<?= _translate('Is Patient Breastfeeding?'); ?>"
													<?php echo ($genericResultInfo['is_patient_breastfeeding'] == 'yes') ? "checked='checked'" : "" ?>>
												<?= _translate("Yes"); ?>
											</label>
											<label class="radio-inline">
												<input type="radio" class="" id="breastfeedingNo" name="breastfeeding"
													value="no" <?php echo ($genericResultInfo['is_patient_breastfeeding'] == 'no') ? "checked='checked'" : "" ?>>
												<?= _translate("No"); ?>
											</label>
										</div>
									</div>
									<div class="col-md-6" style="display:none;" id="patientSection">
										<label class="col-lg-5"
											for=""><?= _translate("How long has this patient been on treatment ?"); ?>
										</label>
										<div class="col-lg-7">
											<input type="text" class="form-control" id="treatPeriod" name="treatPeriod"
												placeholder="<?= _translate('Enter Treatment Period'); ?>"
												title="<?= _translate('Please enter how long has this patient been on treatment'); ?>"
												value="<?= htmlspecialchars((string) $genericResultInfo['treatment_initiation']); ?>" />
										</div>
									</div>
								</div>
							</div>
							<div class="box box-primary">
								<div class="box-header with-border">
									<h3 class="box-title"><?= _translate("Sample Information"); ?></h3>
								</div>
								<div class="box-body">
									<div class="row">
										<div class="col-md-6">
											<label class="col-lg-5"
												for=""><?= _translate("Date of Sample Collection"); ?> <span
													class="mandatory">*</span></label>
											<div class="col-lg-7">
												<input type="text" class="form-control isRequired dateTime"
													style="width:100%;" name="sampleCollectionDate"
													id="sampleCollectionDate"
													placeholder="<?= _translate('Sample Collection Date'); ?>"
													title="<?= _translate('Please select sample collection date'); ?>"
													value="<?php echo $genericResultInfo['sample_collection_date']; ?>"
													onchange="checkCollectionDate(this.value);">
												<span class="expiredCollectionDate"
													style="color:red; display:none;"></span>
											</div>
										</div>
										<div class="col-md-6">
											<label class="col-lg-5" for=""><?= _translate("Sample Dispatched On"); ?>
												<span class="mandatory">*</span></label>
											<div class="col-lg-7">
												<input type="text" class="form-control isRequired dateTime"
													style="width:100%;" name="sampleDispatchedDate"
													id="sampleDispatchedDate"
													placeholder="<?= _translate('Sample Dispatched On'); ?>"
													title="<?= _translate('Please select sample dispatched on'); ?>"
													value="<?php echo $genericResultInfo['sample_dispatched_datetime']; ?>">
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-6">
											<label class="col-lg-5" for="specimenType"><?= _translate("Sample Type"); ?>
												<span class="mandatory">*</span></label>
											<div class="col-lg-7">
												<select name="specimenType" id="specimenType"
													class="form-control isRequired"
													title="<?= _translate('Please choose sample type'); ?>">
													<option value=""> <?= _translate("-- Select --"); ?> </option>
													<?php foreach ($sResult as $name) { ?>
														<option value="<?php echo $name['sample_type_id']; ?>" <?php echo ($genericResultInfo['specimen_type'] == $name['sample_type_id']) ? "selected='selected'" : "" ?>>
															<?php echo $name['sample_type_name']; ?>
														</option>
													<?php } ?>
												</select>
											</div>
										</div>

										<div class="col-md-6">
											<label class="col-lg-5 control-label labels"
												for="reasonForTesting"><?= _translate("Reason For Testing"); ?> <span
													class="mandatory result-span">*</span></label>
											<div class="col-lg-7">
												<select name="reasonForTesting" id="reasonForTesting"
													class="form-control result-optional"
													title="<?= _translate('Please choose reason for testing'); ?>">
													<option value=""><?= _translate("-- Select --"); ?></option>
													<?php foreach ($testReason as $treason) { ?>
														<option value="<?php echo $treason['test_reason_id']; ?>" <?php echo ($genericResultInfo['reason_for_testing'] == $treason['test_reason_id']) ? 'selected="selected"' : ''; ?>>
															<?php echo $treason['test_reason']; ?>
														</option>
													<?php } ?>
												</select>
											</div>
										</div>
									</div>
									<!-- <div id="specimenSection"></div> -->
								</div>
							</div>
							<div id="otherSection"></div>
							<?php if (_isAllowed('/generic-tests/results/generic-test-results.php') && $_SESSION['accessType'] != 'collection-site') { ?>
								<div class="box box-primary">
									<div class="box-header with-border">
										<h3 class="box-title"><?= _translate("Laboratory Information"); ?></h3>
									</div>
									<div class="box-body labSectionBody">
										<div class="row">
											<div class="col-md-6">
												<label class="col-lg-5" for="vlFocalPerson" class="col-lg-5 control-label">
													<?= _translate("Focal Person"); ?>
												</label>
												<div class="col-lg-7">
													<select class="form-control ajax-select2" id="vlFocalPerson"
														name="vlFocalPerson"
														title="<?= _translate('Please enter Focal Person'); ?>">
														<option
															value="<?= htmlspecialchars((string) $genericResultInfo['testing_lab_focal_person']); ?>"
															selected='selected'>
															<?= htmlspecialchars((string) $genericResultInfo['testing_lab_focal_person']); ?>
														</option>
													</select>
												</div>
											</div>
											<div class="col-md-6">
												<label class="col-lg-5" for="vlFocalPersonPhoneNumber"
													class="col-lg-5 control-label">
													<?= _translate("Focal Person Phone Number"); ?></label>
												<div class="col-lg-7">
													<input type="text" class="form-control phone-number labSection"
														id="vlFocalPersonPhoneNumber" name="vlFocalPersonPhoneNumber"
														maxlength="15" placeholder="<?= _translate('Phone Number'); ?>"
														title="<?= _translate('Please enter focal person phone number'); ?>"
														value="<?= htmlspecialchars((string) $genericResultInfo['testing_lab_focal_person_phone_number']); ?>" />
												</div>
											</div>
										</div>
										<div class="row" style="margin-top: 10px;">
											<div class="col-md-6">
												<label class="col-lg-5 control-label" for="sampleReceivedAtHubOn"><?= _translate("Date
													Sample Received at Hub (PHL)"); ?> </label>
												<div class="col-lg-7">
													<input type="text" class="form-control dateTime"
														id="sampleReceivedAtHubOn" name="sampleReceivedAtHubOn"
														placeholder="<?= _translate('Sample Received at HUB Date'); ?>"
														title="<?= _translate('Please select sample received at HUB date'); ?>"
														value="<?php echo $genericResultInfo['sample_received_at_hub_datetime']; ?>" />
												</div>
											</div>

											<div class="col-md-6">
												<label class="col-lg-5 control-label" for="sampleReceivedDate"><?= _translate("Date
													Sample Received at Testing Lab"); ?> </label>
												<div class="col-lg-7">
													<input type="text" class="form-control labSection dateTime"
														id="sampleReceivedDate" name="sampleReceivedDate"
														placeholder="<?= _translate('Sample Received Date'); ?>"
														title="<?= _translate('Please select sample received date'); ?>"
														value="<?php echo $genericResultInfo['sample_received_at_lab_datetime']; ?>" />
												</div>
											</div>
										</div>
										<div class="row" style="margin-top: 10px;">
											<div class="col-md-6">
												<label class="col-lg-5" for="testPlatform" class="col-lg-5 control-label">
													<?= _translate("Testing Platform"); ?> <span
														class="mandatory result-span">*</span></label>
												<div class="col-lg-7">
													<select name="testPlatform" id="testPlatform"
														class="form-control result-optional labSection"
														title="<?= _translate('Please choose VL Testing Platform'); ?>">
														<option value=""><?= _translate("-- Select --"); ?></option>
														<?php foreach ($importResult as $mName) { ?>
															<option
																value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] . '##' . $mName['instrument_id']; ?>"
																<?php echo ($genericResultInfo['test_platform'] == $mName['machine_name']) ? 'selected="selected"' : ''; ?>>
																<?php echo $mName['machine_name']; ?>
															</option>
														<?php } ?>
													</select>
												</div>
											</div>
											<div class="col-md-6">
												<label class="col-lg-5 control-label"
													for="isSampleRejected"><?= _translate("Is Sample Rejected?"); ?>
													<span class="mandatory result-span">*</span></label>
												<div class="col-lg-7">
													<select name="isSampleRejected" id="isSampleRejected"
														class="form-control labSection"
														title="<?= _translate('Please check if sample is rejected or not'); ?>">
														<option value=""><?= _translate("-- Select --"); ?></option>
														<option value="yes" <?php echo ($genericResultInfo['is_sample_rejected'] == 'yes') ? 'selected="selected"' : ''; ?>>
															<?= _translate("Yes"); ?>
														</option>
														<option value="no" <?php echo ($genericResultInfo['is_sample_rejected'] == 'no') ? 'selected="selected"' : ''; ?>>
															<?= _translate("No"); ?>
														</option>
													</select>
												</div>
											</div>
										</div>
										<div class="row rejectionReason"
											style="display:<?php echo ($genericResultInfo['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;margin-top: 10px;">
											<div class="col-md-6 rejectionReason"
												style="display:<?php echo ($genericResultInfo['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;">
												<label class="col-lg-5 control-label"
													for="rejectionReason"><?= _translate("Rejection Reason"); ?> </label>
												<div class="col-lg-7">
													<select name="rejectionReason" id="rejectionReason"
														class="form-control labSection"
														title="<?= _translate('Please choose reason'); ?>"
														onchange="checkRejectionReason();">
														<option value=""><?= _translate("-- Select --"); ?></option>
														<?php foreach ($rejectionTypeResult as $type) { ?>
															<optgroup
																label="<?php echo strtoupper((string) $type['rejection_type']); ?>">
																<?php
																foreach ($rejectionResult as $reject) {
																	if ($type['rejection_type'] == $reject['rejection_type']) { ?>
																		<option value="<?php echo $reject['rejection_reason_id']; ?>"
																			<?php echo ($genericResultInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>>
																			<?php echo ($reject['rejection_reason_name']); ?>
																		</option>
																	<?php }
																} ?>
															</optgroup>
														<?php }
														if ($general->isLISInstance() === false) { ?>
															<option value="other"><?= _translate("Other (Please Specify)"); ?>
															</option>
														<?php } ?>
													</select>
													<input type="text" class="form-control newRejectionReason"
														name="newRejectionReason" id="newRejectionReason"
														placeholder="<?= _translate('Rejection Reason'); ?>"
														title="<?= _translate('Please enter rejection reason'); ?>"
														style="width:100%;display:none;margin-top:2px;">
												</div>
											</div>
											<div class="col-md-6 rejectionReason"
												style="display:<?php echo ($genericResultInfo['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;">
												<label class="col-lg-5 control-label"
													for="rejectionDate"><?= _translate("Rejection Date"); ?>
												</label>
												<div class="col-lg-7">
													<input
														value="<?php echo DateUtility::humanReadableDateFormat($genericResultInfo['rejection_on']); ?>"
														class="form-control date rejection-date" type="text"
														name="rejectionDate" id="rejectionDate"
														placeholder="<?= _translate('Select Rejection Date'); ?>"
														title="<?= _translate('Please select Sample Rejection Date'); ?>" />
												</div>
											</div>
										</div>
										<div class="row" style="margin-top: 10px;">
											<div class="col-md-6">
												<label class="col-lg-5 control-label"
													for="sampleTestingDateAtLab"><?= _translate("Sample Testing Date"); ?>
													<span class="mandatory result-span">*</span></label>
												<div class="col-lg-7">
													<input type="text"
														class="form-control labSection dateTime <?php echo ($genericResultInfo['is_sample_rejected'] == 'no') ? 'isRequired' : ''; ?>"
														<?php echo ($genericResultInfo['is_sample_rejected'] == 'yes') ? ' disabled="disabled" ' : ''; ?> id="sampleTestingDateAtLab"
														name="sampleTestingDateAtLab"
														placeholder="<?= _translate('Sample Testing Date'); ?>"
														title="<?= _translate('Please select sample testing date'); ?>"
														value="<?php echo $genericResultInfo['sample_tested_datetime']; ?>" />
												</div>
											</div>
										</div>

										<div class="row" style="margin-top: 10px;">
											<!--<?php if (count($reasonForFailure) > 0) { ?>
												<div class="col-md-6 labSection" style="<?php echo (!isset($genericResultInfo['result']) || $genericResultInfo['result'] == 'Failed') ? '' : 'display: none;'; ?>">
													<label class="col-lg-5 control-label" for="reasonForFailure">Reason for
														Failure </label>
													<div class="col-lg-7">
														<select name="reasonForFailure" id="reasonForFailure" class="form-control vlResult" title="Please choose reason for failure" style="width: 100%;">
															<?= $general->generateSelectOptions($reasonForFailure, $genericResultInfo['reason_for_failure'], '-- Select --'); ?>
														</select>
													</div>
												</div>
											<?php } ?>--->
										<div class="col-md-6 vlResult">
											<label class="col-lg-5 control-label" for="resultDispatchedOn">
												<?= _translate("Date Results Dispatched"); ?>
											</label>
											<div class="col-lg-7">
												<input type="text" class="form-control labSection dateTime"
													id="resultDispatchedOn" name="resultDispatchedOn"
													placeholder="<?= _translate('Result Dispatched Date'); ?>"
													title="<?= _translate('Please select result dispatched date'); ?>"
													value="<?php echo $genericResultInfo['result_dispatched_datetime']; ?>" />
											</div>
										</div>
										<div class="col-md-6 vlResult subTestFields">
											<label class="col-lg-5 control-label subTestFields labels"
												for="subTestResult">
												<?= _translate("Tests Performed"); ?>
											</label>
											<div class="col-lg-7">
												<select class="form-control ms-container multiselect subTestFields"
													id="subTestResult" name="subTestResult[]"
													title="<?= _translate('Please select sub tests'); ?>" multiple
													onchange="loadSubTests();">
												</select>
											</div>
										</div>
									</div>
									<div class="row" id="resultSection">
									</div>
									<div class="row">
										<div class="col-md-6">
											<label class="col-lg-5 control-label" for="reviewedBy">
												<?= _translate("Reviewed By"); ?>
												<span class="mandatory review-approve-span"
													style="display: <?php echo ($genericResultInfo['is_sample_rejected'] != '') ? 'inline' : 'none'; ?>;">*</span>
											</label>
											<div class="col-lg-7">
												<select name="reviewedBy" id="reviewedBy" class="select2 form-control"
													title="<?= _translate('Please choose reviewed by'); ?>"
													style="width: 100%;">
													<?= $general->generateSelectOptions($userInfo, $genericResultInfo['result_reviewed_by'], '-- Select --'); ?>
												</select>
											</div>
										</div>
										<div class="col-md-6">
											<label class="col-lg-5 control-label" for="reviewedOn">
												<?= _translate("Reviewed On"); ?>
												<span class="mandatory review-approve-span"
													style="display: <?php echo ($genericResultInfo['is_sample_rejected'] != '') ? 'inline' : 'none'; ?>;">*</span>
											</label>
											<div class="col-lg-7">
												<input type="text"
													value="<?php echo $genericResultInfo['result_reviewed_datetime']; ?>"
													name="reviewedOn" id="reviewedOn" class="dateTime form-control"
													placeholder="<?= _translate('Reviewed on'); ?>"
													title="<?= _translate('Please enter the Reviewed on'); ?>" />
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-6">
											<label class="col-lg-5 control-label" for="testedBy">
												<?= _translate("Tested By"); ?>
											</label>
											<div class="col-lg-7">
												<select name="testedBy" id="testedBy" class="select2 form-control"
													title="<?= _translate('Please choose approved by'); ?>">
													<?= $general->generateSelectOptions($userInfo, $genericResultInfo['tested_by'], '-- Select --'); ?>
												</select>
											</div>
										</div>
										<?php
										$styleStatus = '';
										if ((($_SESSION['accessType'] == 'collection-site') && $genericResultInfo['result_status'] == SAMPLE_STATUS\RECEIVED_AT_CLINIC)) {
											$styleStatus = "display:none"; ?>
										<input type="hidden" name="status"
											value="<?= htmlspecialchars((string) $genericResultInfo['result_status']); ?>" />
										<?php } ?>
										<div class="col-md-6" style="margin-top: 10px;">
											<label class="col-lg-5 control-label" for="approvedBy">
												<?= _translate("Approved By"); ?>
												<span class="mandatory review-approve-span"
													style="display: <?php echo ($genericResultInfo['is_sample_rejected'] != '') ? 'block' : 'none'; ?>;">*</span>
											</label>
											<div class="col-lg-7">
												<select name="approvedBy" id="approvedBy"
													class="form-control labSection"
													title="<?= _translate('Please choose approved by'); ?>">
													<?= $general->generateSelectOptions($userInfo, $genericResultInfo['result_approved_by'], '-- Select --'); ?>
												</select>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-6">
											<label class="col-lg-5 control-label" for="approvedOn">
												<?= _translate("Approved On"); ?>
												<span class="mandatory review-approve-span"
													style="display: <?php echo ($genericResultInfo['is_sample_rejected'] != '') ? 'block' : 'none'; ?>;">*</span>
											</label>
											<div class="col-lg-7">
												<input type="text"
													value="<?php echo $genericResultInfo['result_approved_datetime']; ?>"
													class="form-control dateTime" id="approvedOn" name="approvedOn"
													placeholder="<?= _translate("Please enter date"); ?>"
													style="width:100%;" />
											</div>
										</div>
										<div class="col-md-6">
											<label class="col-lg-5 control-label" for="labComments">
												<?= _translate("Lab Tech. Comments"); ?>
											</label>
											<div class="col-lg-7">
												<textarea class="form-control labSection" name="labComments"
													id="labComments" placeholder="<?= _translate('Lab comments'); ?>"
													style="width:100%"><?php echo trim((string) $genericResultInfo['lab_tech_comments']); ?></textarea>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-6 change-reason"
											style="display:<?php echo ($reasonForChange === '' || $reasonForChange === '0') ? "none" : "block"; ?>;">
											<label class="col-lg-5 control-label" for="reasonForResultChanges">
												<?= _translate("Reason For Changes in Result"); ?><span
													class="mandatory">*</span>
											</label>
											<div class="col-lg-7">
												<textarea class="form-control" name="reasonForResultChanges"
													id="reasonForResultChanges"
													placeholder="<?= _translate('Enter Reason For Result Changes'); ?>"
													title="<?= _translate('Please enter reason for result changes'); ?>"
													style="width:100%;"><?= $reasonForChange; ?></textarea>
											</div>
										</div>
									</div>
									<?php if (!empty($allChange)) { ?>
									<div class="row">
										<div class="col-md-12">
											<?php echo $rch; ?>
										</div>
									</div>
									<?php } ?>
									<div class="row" id="labSection"></div>
								</div>
								<?php } ?>
							</div>
						</div>
					</div>
					<div class="box-footer">
						<input type="hidden" name="revised" id="revised" value="no" />
						<input type="hidden" name="vlSampleId" id="vlSampleId"
							value="<?= htmlspecialchars((string) $genericResultInfo['sample_id']); ?>" />
						<input type="hidden" name="isRemoteSample"
							value="<?= htmlspecialchars((string) $genericResultInfo['remote_sample']); ?>" />
						<input type="hidden" name="reasonForResultChangesHistory" id="reasonForResultChangesHistory"
							value="<?php echo base64_encode((string) $genericResultInfo['reason_for_testing']); ?>" />
						<input type="hidden" name="oldStatus"
							value="<?= htmlspecialchars((string) $genericResultInfo['result_status']); ?>" />
						<input type="hidden" name="countryFormId" id="countryFormId"
							value="<?php echo $arr['vl_form']; ?>" />
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">
							<?= _translate("Save"); ?>
						</a>&nbsp;
						<a href="view-requests.php" class="btn btn-default">
							<?= _translate("Cancel"); ?>
						</a>
					</div>
			</div>
		</div>
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
	$("#sampleTestingDateTimeAtLab").change(function () {
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

		if ($(this).val() == 'yes') {
			$('.rejectionReason').show();
			$('.vlResult').css('display', 'none');
			$("#sampleTestingDateTimeAtLab, #vlResult").val("");
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
					if (typeof (data.facilitySection) != "undefined" && data.facilitySection !== null && data.facilitySection.length > 0) {
						$("#facilitySection").html(data.facilitySection);
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
					} else {
						$('#resultSection').hide();
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
					$("#resultSection").html('');
					if (typeof (data.result) != "undefined" && data.result !== null && data.result.length > 0) {
						$("#resultSection").html(data.result);
						$('#resultSection').show();
					} else {
						$('#resultSection').hide();
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
<?php require_once APPLICATION_PATH . '/footer.php';
