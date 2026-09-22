<?php

use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);


// Keep the sample's own stored lab selectable even if that lab has since been
// deactivated. Without it the required Testing Lab select renders empty and the
// user must pick some OTHER lab just to save, silently reassigning the sample.
$lResult = $facilitiesService->getTestingLabs('vl', byPassFacilityMap: true, allColumns: true, alwaysIncludeLabId: $vlQueryInfo['lab_id'] ?? null);

if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'alphanumeric') {
	$sampleClass = '';
	$maxLength = '';
	if ($arr['max_length'] != '' && $arr['sample_code'] == 'alphanumeric') {
		$maxLength = $arr['max_length'];
		$maxLength = "maxlength=$maxLength";
	}
} else {
	$sampleClass = '';
	$maxLength = '';
	if ($arr['max_length'] != '') {
		$maxLength = $arr['max_length'];
		$maxLength = "maxlength=$maxLength";
	}
}
// check if STS
if ($general->isSTSInstance() && $_SESSION['accessType'] == 'collection-site') {
	$sampleCode = 'remote_sample_code';
	if (!empty($vlQueryInfo['remote_sample']) && $vlQueryInfo['remote_sample'] == 'yes') {
		$sampleCode = 'remote_sample_code';
	} else {
		$sampleCode = 'sample_code';
	}
} else {
	$sampleCode = 'sample_code';
}
$province = $general->getUserMappedProvinces($_SESSION['facilityMap']);

$facility = $general->generateSelectOptions($healthFacilities, $vlQueryInfo['facility_id'], _translate("-- Select --"));


//facility details
if (isset($vlQueryInfo['facility_id']) && $vlQueryInfo['facility_id'] > 0) {
	$facilityQuery = "SELECT * FROM facility_details WHERE facility_id= ? AND status='active'";
	$facilityResult = $db->rawQuery($facilityQuery, [$vlQueryInfo['facility_id']]);
}

$facilityCode = $facilityResult[0]['facility_code'] ?? '';
$facilityMobileNumbers = $facilityResult[0]['facility_mobile_numbers'] ?? '';
$contactPerson = $facilityResult[0]['contact_person'] ?? '';
$facilityEmails = $facilityResult[0]['facility_emails'] ?? '';
$facilityState = $facilityResult[0]['facility_state'] ?? '';
$facilityDistrict = $facilityResult[0]['facility_district'] ?? '';

$user = '';
if ($contactPerson != '') {
	$contactUser = $usersService->getUserByID($contactPerson);
	if (!empty($contactUser)) {
		$user = $contactUser['user_name'];
	}
}


//var_dump($vlQueryInfo['sample_received_at_hub_datetime']);die;
$isGeneXpert = !empty($vlQueryInfo['vl_test_platform']) && (strcasecmp((string) $vlQueryInfo['vl_test_platform'], "genexpert") === 0);

if ($isGeneXpert && !empty($vlQueryInfo['result_value_hiv_detection']) && !empty($vlQueryInfo['result'])) {
	$vlQueryInfo['result'] = trim(str_ireplace((string) $vlQueryInfo['result_value_hiv_detection'], "", (string) $vlQueryInfo['result']));
} elseif ($isGeneXpert && !empty($vlQueryInfo['result'])) {

	$vlQueryInfo['result_value_hiv_detection'] = null;

	$hivDetectedStringsToSearch = [
		'HIV-1 Detected',
		'HIV 1 Detected',
		'HIV1 Detected',
		'HIV 1Detected',
		'HIV1Detected',
		'HIV Detected',
		'HIVDetected',
	];

	$hivNotDetectedStringsToSearch = [
		'HIV-1 Not Detected',
		'HIV-1 NotDetected',
		'HIV-1Not Detected',
		'HIV 1 Not Detected',
		'HIV1 Not Detected',
		'HIV 1Not Detected',
		'HIV1Not Detected',
		'HIV1NotDetected',
		'HIV1 NotDetected',
		'HIV 1NotDetected',
		'HIV Not Detected',
		'HIVNotDetected',
	];

	$detectedMatching = $general->checkIfStringExists($vlQueryInfo['result'] ?? '', $hivDetectedStringsToSearch);
	if ($detectedMatching !== false) {
		$vlQueryInfo['result'] = trim(str_ireplace((string) $detectedMatching, "", (string) $vlQueryInfo['result']));
		$vlQueryInfo['result_value_hiv_detection'] = "HIV-1 Detected";
	} else {
		$notDetectedMatching = $general->checkIfStringExists($vlQueryInfo['result'] ?? '', $hivNotDetectedStringsToSearch);
		if ($notDetectedMatching !== false) {
			$vlQueryInfo['result'] = trim(str_ireplace((string) $notDetectedMatching, "", (string) $vlQueryInfo['result']));
			$vlQueryInfo['result_value_hiv_detection'] = "HIV-1 Not Detected";
		}
	}
}

?>
<style>
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
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-pen-to-square"></em> <?= _translate("VIRAL LOAD LABORATORY REQUEST FORM"); ?> </h1>
		<ol class="breadcrumb">
			<li><a href="/dashboard/index.php"><em class="fa-solid fa-chart-pie"></em> <?= _translate("Home"); ?></a></li>
			<li class="active"><?= _translate("Edit VL Request"); ?></li>
		</ol>
	</section>
	<?php
	//print_r(array_column($vlTestReasonResult, 'last_name')$oneDimensionalArray = array_map('current', $vlTestReasonResult));die;
	?>
	<!-- Main content -->
	<section class="content">

		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?= _translate("indicates required fields"); ?> &nbsp;</div>
			</div>
			<div class="box-body">
				<!-- form start -->
				<form class="form-inline" method="post" name="vlRequestFormRwd" id="vlRequestFormRwd" autocomplete="off" action="editVlRequestHelper.php">
					<div class="box-body">
						<div class="box box-primary">
							<div class="box-header with-border">
								<h3 class="box-title"><?= _translate("Clinic Information: (To be filled by requesting Clinican/Nurse)"); ?></h3>
							</div>
							<div class="box-body">
								<div class="row">
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="sampleCode"><?= _translate("Sample ID"); ?> <span class="mandatory">*</span></label>
											<input type="text" class="form-control isRequired <?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" <?php echo $maxLength; ?> placeholder="<?= _htmlTranslate("Enter Sample ID"); ?>" readonly="readonly" title="<?= _translate("Please make sure you have selected Sample Collection Date and Requesting Facility"); ?>" value="<?php echo $vlQueryInfo[$sampleCode]; ?>" style="width:100%;" onchange="checkSampleNameValidation('form_vl','<?php echo $sampleCode; ?>',this.id,'<?php echo "vl_sample_id##" . $vlQueryInfo["vl_sample_id"]; ?>','This sample number already exists.Try another number',null)" />
											<input type="hidden" name="sampleCodeCol" value="<?= $vlQueryInfo['sample_code'] ?>" style="width:100%;">
										</div>
									</div>
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="sampleReordered">
												<input type="checkbox" class="" id="sampleReordered" name="sampleReordered" value="yes" <?php echo (trim((string) $vlQueryInfo['sample_reordered']) === 'yes') ? 'checked="checked"' : '' ?> title="<?= _htmlTranslate("Please indicate if this is a reordered sample"); ?>"> <?= _translate("Sample Reordered"); ?>
											</label>
										</div>
									</div>

									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="communitySample"><?= _translate("Community Sample"); ?></label>
											<select class="form-control" name="communitySample" id="communitySample" title="<?= _htmlTranslate("Please choose if this is a community sample"); ?>" onclick="updateLocationOfSample();" style="width:100%;">
												<option value=""> <?= _translate("-- Select --"); ?> </option>
												<option value="yes" <?php echo (isset($vlQueryInfo['community_sample']) && $vlQueryInfo['community_sample'] == 'yes') ? 'selected="selected"' : ''; ?>><?= _translate("Yes"); ?></option>
												<option value="no" <?php echo (isset($vlQueryInfo['community_sample']) && $vlQueryInfo['community_sample'] == 'no') ? 'selected="selected"' : ''; ?>><?= _translate("No"); ?></option>
											</select>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="province"><?= _translate("State/Province"); ?> <span class="mandatory">*</span></label>
											<select class="form-control isRequired" name="province" id="province" title="<?= _htmlTranslate("Please choose state"); ?>" style="width:100%;" onchange="getProvinceDistricts(this);">
												<?php echo $province; ?>
											</select>
										</div>
									</div>
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="district"><?= _translate("District/County"); ?> <span class="mandatory">*</span></label>
											<select class="form-control isRequired" name="district" id="district" title="<?= _htmlTranslate("Please choose county"); ?>" style="width:100%;" onchange="getFacilities(this);">
												<option value=""> <?= _translate("-- Select --"); ?> </option>
											</select>
										</div>
									</div>
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="facilityId"><?= _translate("Clinic/Health Center"); ?> <span class="mandatory">*</span></label>
											<select class="form-control isRequired" id="facilityId" name="facilityId" title="<?= _htmlTranslate("Please select clinic/health center name"); ?>" style="width:100%;" onchange="fillFacilityDetails(this);">

												<?= $facility; ?>
											</select>
										</div>
									</div>
									<div class="col-xs-3 col-md-3" style="display:none;">
										<div class="form-group">
											<label for="facilityCode"><?= _translate("Clinic/Health Center Code"); ?> </label>
											<input type="text" class="form-control" style="width:100%;" name="facilityCode" id="facilityCode" placeholder="<?= _htmlTranslate("Clinic/Health Center Code"); ?>" title="<?= _htmlTranslate("Please enter clinic/health center code"); ?>" value="<?php echo $facilityResult[0]['facility_code']; ?>">
										</div>
									</div>
								</div>
								<div class="row facilityDetails" style="display:<?php echo (trim((string) $facilityResult[0]['facility_emails']) !== '' || trim((string) $facilityResult[0]['facility_mobile_numbers']) !== '' || trim((string) $facilityResult[0]['contact_person']) !== '') ? '' : 'none'; ?>;">
									<div class="col-xs-2 col-md-2 femails" style="display:<?php echo (trim((string) $facilityResult[0]['facility_emails']) !== '') ? '' : 'none'; ?>;"><strong><?= _translate("Clinic Email(s)"); ?></strong></div>
									<div class="col-xs-2 col-md-2 femails facilityEmails" style="display:<?php echo (trim((string) $facilityResult[0]['facility_emails']) !== '') ? '' : 'none'; ?>;"><?php echo $facilityResult[0]['facility_emails']; ?></div>
									<div class="col-xs-2 col-md-2 fmobileNumbers" style="display:<?php echo (trim((string) $facilityResult[0]['facility_mobile_numbers']) !== '') ? '' : 'none'; ?>;"><strong><?= _translate("Clinic Mobile No.(s)"); ?></strong></div>
									<div class="col-xs-2 col-md-2 fmobileNumbers facilityMobileNumbers" style="display:<?php echo (trim((string) $facilityResult[0]['facility_mobile_numbers']) !== '') ? '' : 'none'; ?>;"><?php echo $facilityResult[0]['facility_mobile_numbers']; ?></div>
									<div class="col-xs-2 col-md-2 fContactPerson" style="display:<?php echo (trim((string) $facilityResult[0]['contact_person']) !== '') ? '' : 'none'; ?>;"><strong><?= _translate("Clinic Contact Person -"); ?></strong></div>
									<div class="col-xs-2 col-md-2 fContactPerson facilityContactPerson" style="display:<?php echo (trim((string) $user) !== '') ? '' : 'none'; ?>;"><?php echo ($user); ?></div>
								</div>


								<div class="row">
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="implementingPartner"><?= _translate("Implementing Partner"); ?></label>
											<select class="form-control" name="implementingPartner" id="implementingPartner" title="<?= _htmlTranslate("Please choose implementing partner"); ?>" style="width:100%;">
												<option value=""> <?= _translate("-- Select --"); ?> </option>
												<?php
												foreach ($implementingPartnerList as $implementingPartner) {
												?>
													<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>" <?php echo ($implementingPartner['i_partner_id'] == $vlQueryInfo['implementing_partner']) ? 'selected="selected"' : ''; ?>><?= $implementingPartner['i_partner_name']; ?></option>
												<?php } ?>
											</select>
										</div>
									</div>
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="fundingSource"><?= _translate("Funding Source"); ?></label>
											<select class="form-control" name="fundingSource" id="fundingSource" title="<?= _htmlTranslate("Please choose implementing partner"); ?>" style="width:100%;">
												<option value=""> <?= _translate("-- Select --"); ?> </option>
												<?php
												foreach ($fundingSourceList as $fundingSource) {
												?>
													<option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>" <?php echo ($fundingSource['funding_source_id'] == $vlQueryInfo['funding_source']) ? 'selected="selected"' : ''; ?>><?= $fundingSource['funding_source_name']; ?></option>
												<?php } ?>
											</select>
										</div>
									</div>

									<div class="col-md-4 col-md-4">
										<label for="labId"><?= _translate("Testing Lab"); ?> <span class="mandatory">*</span></label>
										<select name="labId" id="labId" class="form-control isRequired" title="<?= _htmlTranslate("Please choose lab"); ?>" style="width:100%;">
											<option value=""><?= _translate("-- Select --"); ?></option>
											<?php foreach ($lResult as $labName) { ?>
												<option data-focalperson="<?php echo $labName['contact_person']; ?>" data-focalphone="<?php echo $labName['facility_mobile_numbers']; ?>" value="<?php echo $labName['facility_id']; ?>" <?php echo (isset($vlQueryInfo['lab_id']) && $vlQueryInfo['lab_id'] == $labName['facility_id']) ? 'selected="selected"' : ''; ?>><?= $labName['facility_name']; ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="box box-primary">
							<div class="box-header with-border">
								<h3 class="box-title"><?= _translate("Patient Information"); ?></h3>
							</div>
							<div class="box-body">
								<div class="row">
									<div class="col-md-12 encryptPIIContainer">
										<label class="col-lg-5 control-label" for="encryptPII"><?= _translate('Patient is from Defence Forces (Patient Name and Patient ID will not be synced between LIS and STS)'); ?> <span class="mandatory">*</span></label>
										<div class="col-lg-5">
											<select name="encryptPII" id="encryptPII" class="form-control" title="<?= _translate('Encrypt Patient Identifying Information'); ?>">
												<option value=""><?= _translate('--Select--'); ?></option>
												<option value="no" <?php echo ($vlQueryInfo['is_encrypted'] == "no") ? "selected='selected'" : ""; ?>><?= _translate('No'); ?></option>
												<option value="yes" <?php echo ($vlQueryInfo['is_encrypted'] == "yes") ? "selected='selected'" : ""; ?>><?= _translate('Yes'); ?></option>
											</select>
										</div>
									</div>
									<br>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="artNo"><?= _translate("ART (TRACNET) No."); ?> <span class="mandatory">*</span></label>
											<input type="text" name="artNo" id="artNo" class="form-control isRequired patientId" placeholder="<?= _htmlTranslate("Enter ART Number"); ?>" title="<?= _htmlTranslate("Enter art number"); ?>" value="<?= ($vlQueryInfo['patient_art_no']); ?>" />
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="dob"><?= _translate("Date of Birth"); ?> </label>
											<input type="text" name="dob" id="dob" class="form-control date" placeholder="<?= _htmlTranslate("Enter DOB"); ?>" title="<?= _htmlTranslate("Enter dob"); ?>" value="<?= ($vlQueryInfo['patient_dob']); ?>" />
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="ageInYears"><?= _translate("If DOB unknown, Age in Years"); ?> </label>
											<input type="text" name="ageInYears" id="ageInYears" class="form-control forceNumeric" maxlength="3" placeholder="<?= _htmlTranslate("Age in Years"); ?>" title="<?= _htmlTranslate("Enter age in years"); ?>" value="<?= ($vlQueryInfo['patient_age_in_years']); ?>" />
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="ageInMonths"><?= _translate("If Age < 1, Age in Months"); ?>
												</label> <input type="text" name="ageInMonths" id="ageInMonths" class="form-control forceNumeric" maxlength="2" placeholder="<?= _htmlTranslate("Age in Month"); ?>" title="<?= _htmlTranslate("Enter age in months"); ?>" value="<?= ($vlQueryInfo['patient_age_in_months']); ?>" />
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="patientFirstName"><?= _translate("Patient Name (First Name, Last Name)"); ?> <span class="mandatory">*</span></label>
											<input type="text" name="patientFirstName" id="patientFirstName" class="form-control isRequired" placeholder="<?= _htmlTranslate("Enter Patient Name"); ?>" title="<?= _htmlTranslate("Enter patient name"); ?>" value="<?php echo $patientFullName; ?>" />
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="gender"><?= _translate("Sex"); ?> <span class="mandatory">*</span></label><br>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="isRequired" id="genderMale" name="gender" value="male" title="<?= _htmlTranslate("Please choose sex"); ?>" <?php echo ($vlQueryInfo['patient_gender'] == 'male') ? "checked='checked'" : "" ?>> <?= _translate("Male"); ?>
											</label>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="genderFemale" name="gender" value="female" title="<?= _htmlTranslate("Please choose sex"); ?>" <?php echo ($vlQueryInfo['patient_gender'] == 'female') ? "checked='checked'" : "" ?>> <?= _translate("Female"); ?>
											</label>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="genderUnreported" name="gender" value="unreported" title="<?= _htmlTranslate("Please choose sex"); ?>" <?php echo ($vlQueryInfo['patient_gender'] == 'unreported') ? "checked='checked'" : "" ?>><?= _translate("Unreported"); ?>
											</label>
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="receiveSms"><?= _translate("Patient consent to receive SMS?"); ?></label><br>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="receivesmsYes" name="receiveSms" value="yes" title="<?= _htmlTranslate("Patient consent to receive SMS"); ?>" onclick="checkPatientReceivesms(this.value);" <?php echo ($vlQueryInfo['consent_to_receive_sms'] == 'yes') ? "checked='checked'" : "" ?>> <?= _translate("Yes"); ?>
											</label>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="receivesmsNo" name="receiveSms" value="no" title="<?= _htmlTranslate("Patient consent to receive SMS"); ?>" onclick="checkPatientReceivesms(this.value);" <?php echo ($vlQueryInfo['consent_to_receive_sms'] == 'no') ? "checked='checked'" : "" ?>> <?= _translate("No"); ?>
											</label>
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="patientPhoneNumber"><?= _translate("Phone Number"); ?></label>
											<input type="text" name="patientPhoneNumber" id="patientPhoneNumber" class="form-control phone-number" maxlength="15" placeholder="<?= _htmlTranslate("Enter Phone Number"); ?>" title="<?= _htmlTranslate("Enter phone number"); ?>" value="<?= ($vlQueryInfo['patient_mobile_number']); ?>" />
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-xs-3 col-md-3 femaleSection" style="display:<?php echo ($vlQueryInfo['patient_gender'] == 'female' || $vlQueryInfo['patient_gender'] == '' || $vlQueryInfo['patient_gender'] == null) ? "" : "none" ?>" ;>
										<div class="form-group">
											<label for="patientPregnant"><?= _translate("Is Patient Pregnant?"); ?> </label><br>
											<label class="radio-inline">
												<input type="radio" class="" id="pregYes" name="patientPregnant" value="yes" title="<?= _htmlTranslate("Is Patient Pregnant?"); ?>" <?php echo ($vlQueryInfo['is_patient_pregnant'] == 'yes') ? "checked='checked'" : "" ?>> <?= _translate("Yes"); ?>
											</label>
											<label class="radio-inline">
												<input type="radio" class="" id="pregNo" name="patientPregnant" value="no" <?php echo ($vlQueryInfo['is_patient_pregnant'] == 'no') ? "checked='checked'" : "" ?>> <?= _translate("No"); ?>
											</label>
										</div>
									</div>
									<div class="col-xs-3 col-md-3 femaleSection" style="display:<?php echo ($vlQueryInfo['patient_gender'] == 'female' || $vlQueryInfo['patient_gender'] == '' || $vlQueryInfo['patient_gender'] == null) ? "" : "none" ?>" ;>
										<div class="form-group">
											<label for="breastfeeding"><?= _translate("Is Patient Breastfeeding?"); ?> </label><br>
											<label class="radio-inline">
												<input type="radio" class="" id="breastfeedingYes" name="breastfeeding" value="yes" title="<?= _htmlTranslate("Is Patient Breastfeeding?"); ?>" <?php echo ($vlQueryInfo['is_patient_breastfeeding'] == 'yes') ? "checked='checked'" : "" ?>> <?= _translate("Yes"); ?>
											</label>
											<label class="radio-inline">
												<input type="radio" class="" id="breastfeedingNo" name="breastfeeding" value="no" <?php echo ($vlQueryInfo['is_patient_breastfeeding'] == 'no') ? "checked='checked'" : "" ?>> <?= _translate("No"); ?>
											</label>
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
										<div class="col-xs-3 col-md-3">
											<div class="form-group">
												<label for=""><?= _translate("Date of Sample Collection"); ?> <span class="mandatory">*</span></label>
												<input type="text" class="form-control isRequired dateTime" style="width:100%;" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="<?= _htmlTranslate("Sample Collection Date"); ?>" title="<?= _htmlTranslate("Please select sample collection date"); ?>" value="<?php echo $vlQueryInfo['sample_collection_date']; ?>" onchange="checkSampleTestingDate(); checkCollectionDate(this.value);">
												<span class="expiredCollectionDate" style="color:red; display:none;"></span>
											</div>
										</div>
										<div class="col-xs-3 col-md-3">
											<div class="form-group">
												<label for=""><?= _translate("Sample Dispatched On"); ?> <span class="mandatory">*</span></label>
												<input type="text" class="form-control isRequired dateTime" style="width:100%;" name="sampleDispatchedDate" id="sampleDispatchedDate" placeholder="<?= _htmlTranslate("Sample Dispatched On"); ?>" title="<?= _htmlTranslate("Please select sample dispatched on"); ?>" value="<?php echo $vlQueryInfo['sample_dispatched_datetime']; ?>">
											</div>
										</div>
										<div class="col-xs-3 col-md-3">
											<div class="form-group">
												<label for="specimenType"><?= _translate("Sample Type"); ?> <span class="mandatory">*</span></label>
												<select name="specimenType" id="specimenType" class="form-control isRequired" title="<?= _htmlTranslate("Please choose sample type"); ?>">
													<option value=""> <?= _translate("-- Select --"); ?> </option>
													<?php foreach ($sResult as $name) { ?>
														<option value="<?php echo $name['sample_id']; ?>" <?php echo ($vlQueryInfo['specimen_type'] == $name['sample_id']) ? "selected='selected'" : "" ?>><?= $name['sample_name']; ?></option>
													<?php } ?>
												</select>
											</div>
										</div>
										<div class="col-xs-3 col-md-3">
											<div class="form-group">
												<label for="locationOfSampleCollection"><?= _translate("Location Of Sample Collection"); ?></label>
												<select name="locationOfSampleCollection" id="locationOfSampleCollection" class="form-control" onclick="updateLocationOfSample();" title="<?= _htmlTranslate("Please choose location of sample collection"); ?>">
													<option value=""> <?= _translate("-- Select --"); ?> </option>
													<option value="facility" <?php echo ($vlQueryInfo['location_of_sample_collection'] == 'facility') ? "selected='selected'" : "" ?>><?= _translate("Facility"); ?></option>
													<option value="community" <?php echo ($vlQueryInfo['location_of_sample_collection'] == 'community') ? "selected='selected'" : "" ?>><?= _translate("Community"); ?></option>
													<option value="unreported" <?php echo ($vlQueryInfo['location_of_sample_collection'] == 'unreported') ? "selected='selected'" : "" ?>><?= _translate("Unreported"); ?></option>
												</select>
											</div>
										</div>

										<div class="col-xs-3 col-md-3">
											<div class="form-group">
												<label for="sampleReceivedAtHubOn"><?= _translate("Date Sample Received at Hub (PHL)"); ?> <span class="mandatory">*</span></label>
												<input type="text" class="form-control dateTime isRequired" id="sampleReceivedAtHubOn" name="sampleReceivedAtHubOn" placeholder="<?= _htmlTranslate("Sample Received at HUB Date"); ?>" title="<?= _htmlTranslate("Please select sample received at HUB date"); ?>" value="<?php echo $vlQueryInfo['sample_received_at_hub_datetime']; ?>" />
											</div>
										</div>

										<div class="col-xs-3 col-md-3">
											<div class="form-group">
												<label for="sampleReceivedDate"><?= _translate("Date Sample Received at Testing Lab"); ?> <span class="mandatory">*</span> </label>
												<input type="text" class="form-control dateTime isRequired" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _htmlTranslate("Sample Received Date"); ?>" title="<?= _htmlTranslate("Please select sample received date"); ?>" value="<?php echo $vlQueryInfo['sample_received_at_lab_datetime']; ?>" />
											</div>
										</div>
									</div>

								</div>
								<div class="box box-primary">
									<div class="box-header with-border">
										<h3 class="box-title"><?= _translate("Treatment Information"); ?></h3>
									</div>
									<div class="box-body">
										<div class="row">
											<div class="col-xs-3 col-md-3">
												<div class="form-group">
													<label for=""><?= _translate("Date of Treatment Initiation"); ?></label>
													<input type="text" class="form-control date" name="dateOfArtInitiation" id="dateOfArtInitiation" placeholder="<?= _htmlTranslate("Date Of Treatment Initiated"); ?>" title="<?= _htmlTranslate("Date Of treatment initiated"); ?>" value="<?php echo $vlQueryInfo['treatment_initiated_date']; ?>" style="width:100%;">
												</div>
											</div>
											<div class="col-xs-3 col-md-3">
												<div class="form-group">
													<label for="artRegimen"><?= _translate("Current Regimen"); ?></label>
													<select class="form-control" id="artRegimen" name="artRegimen" title="<?= _htmlTranslate("Please choose ART Regimen"); ?>" style="width:100%;" onchange="checkARTRegimenValue();">
														<option value=""><?= _translate("-- Select --"); ?></option>
														<?php foreach ($artRegimenResult as $heading) { ?>
															<optgroup label="<?= $heading['headings']; ?>">
																<?php foreach ($aResult as $regimen) {
																	if ($heading['headings'] == $regimen['headings']) { ?>
																		<option value="<?php echo $regimen['art_code']; ?>" <?php echo ($vlQueryInfo['current_regimen'] == $regimen['art_code']) ? "selected='selected'" : "" ?>><?php echo $regimen['art_code']; ?></option>
																<?php }
																} ?>
															</optgroup>
														<?php }  ?>
														<option value="other"><?= _translate("Other"); ?></option>

													</select>
													<input type="text" class="form-control newArtRegimen" name="newArtRegimen" id="newArtRegimen" placeholder="<?= _htmlTranslate("ART Regimen"); ?>" title="<?= _htmlTranslate("Please enter art regimen"); ?>" style="width:100%;display:none;margin-top:2px;">
												</div>
											</div>
											<div class="col-xs-3 col-md-3">
												<div class="form-group">
													<label for=""><?= _translate("Date of Initiation of Current Regimen"); ?> </label>
													<input type="text" class="form-control date" style="width:100%;" name="regimenInitiatedOn" id="regimenInitiatedOn" placeholder="<?= _htmlTranslate("Current Regimen Initiated On"); ?>" title="<?= _htmlTranslate("Please enter current regimen initiated on"); ?>" value="<?php echo $vlQueryInfo['date_of_initiation_of_current_regimen']; ?>">
												</div>
											</div>
											<div class="col-xs-3 col-md-3">
												<div class="form-group">
													<label for="arvAdherence"><?= _translate("ARV Adherence"); ?> </label>
													<select name="arvAdherence" id="arvAdherence" class="form-control" title="<?= _htmlTranslate("Please choose adherence"); ?>">
														<option value=""> <?= _translate("-- Select --"); ?> </option>
														<option value="good" <?php echo ($vlQueryInfo['arv_adherance_percentage'] == 'good') ? "selected='selected'" : "" ?>><?= _translate("Good >= 95%"); ?></option>
														<option value="fair" <?php echo ($vlQueryInfo['arv_adherance_percentage'] == 'fair') ? "selected='selected'" : "" ?>><?= _translate("Fair (85-94%)"); ?></option>
														<option value="poor" <?php echo ($vlQueryInfo['arv_adherance_percentage'] == 'poor') ? "selected='selected'" : "" ?>><?= _translate("Poor < 85%"); ?></option>
													</select>
												</div>
											</div>
										</div>
										<div class="row ">
											<div class="col-xs-3 col-md-3" style="display:none;">
												<div class="form-group">
													<label for=""><?= _translate("How long has this patient been on treatment ?"); ?> </label>
													<input type="text" class="form-control" id="treatPeriod" name="treatPeriod" placeholder="<?= _htmlTranslate("Enter Treatment Period"); ?>" title="<?= _htmlTranslate("Please enter how long has this patient been on treatment"); ?>" value="<?= ($vlQueryInfo['treatment_initiation']); ?>" />
												</div>
											</div>
										</div>
									</div>
									<div class="box box-primary">
										<div class="box-header with-border">
											<h3 class="box-title"><?= _translate("Indication for Viral Load Testing"); ?></h3><small> <?= _translate("(Please tick one):(To be completed by clinician)"); ?></small>
										</div>
										<div class="box-body">
											<div class="row">
												<div class="col-md-6">
													<div class="form-group">
														<div class="col-lg-12">
															<label class="radio-inline">
																<?php
																$vlTestReasonResultRow = \App\Registries\ContainerRegistry::get(\App\Repositories\Reference\ReferenceDataRepository::class)
																     ->findByIdOrName('test-reason', 'vl', trim((string) $vlQueryInfo['reason_for_vl_testing']));
																$checked = '';
																$display = '';
																if (trim((string) $vlQueryInfo['reason_for_vl_testing']) === 'routine' || isset($vlTestReasonResultRow[0]['test_reason_id']) && $vlTestReasonResultRow[0]['test_reason_name'] == 'routine') {
																	$checked = 'checked="checked"';
																	$display = 'block';
																} else {
																	$checked = '';
																	$display = 'none';
																}
																?>
																<input type="radio" class="isRequired" id="rmTesting" name="reasonForVLTesting" value="routine" title="<?= _htmlTranslate("Please select indication/reason for testing"); ?>" <?php echo $checked; ?> onclick="showTesting('rmTesting');">
																<strong><?= _translate("Routine Monitoring"); ?></strong>
															</label>
														</div>
													</div>
												</div>
											</div>
											<div class="row rmTesting hideTestData" style="display:<?php echo $display; ?>;">
												<div class="col-md-6">
													<label class="col-lg-5 control-label"><?= _translate("Date of Last VL Test"); ?></label>
													<div class="col-lg-7">
														<input type="text" class="form-control date viralTestData" id="rmTestingLastVLDate" name="rmTestingLastVLDate" placeholder="<?= _htmlTranslate("Select Last VL Date"); ?>" title="<?= _htmlTranslate("Please select Last VL Date"); ?>" value="<?php echo (trim((string) $vlQueryInfo['last_vl_date_routine']) !== '' && $vlQueryInfo['last_vl_date_routine'] != null && $vlQueryInfo['last_vl_date_routine'] != '0000-00-00') ? DateUtility::humanReadableDateFormat($vlQueryInfo['last_vl_date_routine']) : ''; ?>" />
													</div>
												</div>
												<div class="col-md-6">
													<label for="rmTestingVlValue" class="col-lg-3 control-label"><?= _translate("VL Result"); ?></label>
													<div class="col-lg-7">
														<input type="text" class="form-control forceNumeric viralTestData" id="rmTestingVlValue" name="rmTestingVlValue" placeholder="<?= _htmlTranslate("Enter VL Result"); ?>" title="<?= _htmlTranslate("Please enter VL Result"); ?>" value="<?php echo $vlQueryInfo['last_vl_result_routine']; ?>" />
														(<?= _translate("copies/mL"); ?>)
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-md-8">
													<div class="form-group">
														<div class="col-lg-12">
															<label class="radio-inline">
																<?php
																$checked = '';
																$display = '';
																if (trim((string) $vlQueryInfo['reason_for_vl_testing']) === 'failure' || isset($vlTestReasonResultRow[0]['test_reason_id']) && $vlTestReasonResultRow[0]['test_reason_name'] == 'failure') {
																	$checked = 'checked="checked"';
																	$display = 'block';
																} else {
																	$checked = '';
																	$display = 'none';
																}
																?>
																<input type="radio" class="isRequired" id="repeatTesting" name="reasonForVLTesting" value="failure" title="<?= _htmlTranslate("Repeat VL test after suspected treatment failure adherence counseling (Reason for testing)"); ?>" <?php echo $checked; ?> onclick="showTesting('repeatTesting');">
																<strong><?= _translate("Repeat VL test after suspected treatment failure adherence counselling"); ?> </strong>
															</label>
														</div>
													</div>
												</div>
											</div>
											<div class="row repeatTesting hideTestData" style="display: <?php echo $display; ?>;">
												<div class="col-md-6">
													<label class="col-lg-5 control-label"><?= _translate("Date of Last VL Test"); ?></label>
													<div class="col-lg-7">
														<input type="text" class="form-control date viralTestData" id="repeatTestingLastVLDate" name="repeatTestingLastVLDate" placeholder="<?= _htmlTranslate("Select Last VL Date"); ?>" title="<?= _htmlTranslate("Please select Last VL Date"); ?>" value="<?php echo (trim((string) $vlQueryInfo['last_vl_date_failure_ac']) !== '' && $vlQueryInfo['last_vl_date_failure_ac'] != null && $vlQueryInfo['last_vl_date_failure_ac'] != '0000-00-00') ? DateUtility::humanReadableDateFormat($vlQueryInfo['last_vl_date_failure_ac']) : ''; ?>" />
													</div>
												</div>
												<div class="col-md-6">
													<label for="repeatTestingVlValue" class="col-lg-3 control-label"><?= _translate("VL Result"); ?></label>
													<div class="col-lg-7">
														<input type="text" class="form-control forceNumeric viralTestData" id="repeatTestingVlValue" name="repeatTestingVlValue" placeholder="<?= _htmlTranslate("Enter VL Result"); ?>" title="<?= _htmlTranslate("Please enter VL Result"); ?>" value="<?php echo $vlQueryInfo['last_vl_result_failure_ac']; ?>" />
														(<?= _translate("copies/mL"); ?>)
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-md-6">
													<div class="form-group">
														<div class="col-lg-12">
															<label class="radio-inline">
																<?php
																$checked = '';
																$display = '';
																if (trim((string) $vlQueryInfo['reason_for_vl_testing']) === 'suspect' || isset($vlTestReasonResultRow[0]['test_reason_id']) && $vlTestReasonResultRow[0]['test_reason_name'] == 'suspect') {
																	$checked = 'checked="checked"';
																	$display = 'block';
																} else {
																	$checked = '';
																	$display = 'none';
																}
																?>
																<input type="radio" class="isRequired" id="suspendTreatment" name="reasonForVLTesting" value="suspect" title="<?= _htmlTranslate("Suspect Treatment Failure (Reason for testing)"); ?>" <?php echo $checked; ?> onclick="showTesting('suspendTreatment');">
																<strong><?= _translate("Suspected Treatment Failure"); ?></strong>
															</label>
														</div>
													</div>
												</div>
											</div>
											<div class="row suspendTreatment hideTestData" style="display: <?php echo $display; ?>;">
												<div class="col-md-6">
													<label class="col-lg-5 control-label"><?= _translate("Date of Last VL Test"); ?></label>
													<div class="col-lg-7">
														<input type="text" class="form-control date viralTestData" id="suspendTreatmentLastVLDate" name="suspendTreatmentLastVLDate" placeholder="<?= _htmlTranslate("Select Last VL Date"); ?>" title="<?= _htmlTranslate("Please select Last VL Date"); ?>" value="<?php echo (trim((string) $vlQueryInfo['last_vl_date_failure']) !== '' && $vlQueryInfo['last_vl_date_failure'] != null && $vlQueryInfo['last_vl_date_failure'] != '0000-00-00') ? DateUtility::humanReadableDateFormat($vlQueryInfo['last_vl_date_failure']) : ''; ?>" />
													</div>
												</div>
												<div class="col-md-6">
													<label for="suspendTreatmentVlValue" class="col-lg-3 control-label"><?= _translate("VL Result"); ?></label>
													<div class="col-lg-7">
														<input type="text" class="form-control forceNumeric viralTestData" id="suspendTreatmentVlValue" name="suspendTreatmentVlValue" placeholder="<?= _htmlTranslate("Enter VL Result"); ?>" title="<?= _htmlTranslate("Please enter VL Result"); ?>" value="<?php echo $vlQueryInfo['last_vl_result_failure']; ?>" />
														(<?= _translate("copies/mL"); ?>)
													</div>
												</div>
											</div>
											<p>&nbsp;</p>
											<div class="row">
												<div class="col-md-4">
													<label for="reqClinician" class="col-lg-5 control-label"><?= _translate("Requesting Clinician"); ?></label>
													<div class="col-lg-7">
														<select class="form-control ajax-select2" id="reqClinician" name="reqClinician" placeholder="<?= _htmlTranslate("Requesting Clinician"); ?>" title="<?= _htmlTranslate("Please enter request clinician"); ?>" value="<?php echo $vlQueryInfo['request_clinician_name']; ?>">
															<option value="<?php echo $vlQueryInfo['request_clinician_name']; ?>" selected='selected'> <?php echo $vlQueryInfo['request_clinician_name']; ?></option>
														</select>
													</div>
												</div>
												<div class="col-md-4">
													<label for="reqClinicianPhoneNumber" class="col-lg-5 control-label"><?= _translate("Phone Number"); ?></label>
													<div class="col-lg-7">
														<input type="text" class="form-control phone-number" id="reqClinicianPhoneNumber" name="reqClinicianPhoneNumber" maxlength="15" placeholder="<?= _htmlTranslate("Phone Number"); ?>" title="<?= _htmlTranslate("Please enter request clinician phone number"); ?>" value="<?php echo $vlQueryInfo['request_clinician_phone_number']; ?>" />
													</div>
												</div>
												<div class="col-md-4">
													<label class="col-lg-5 control-label" for="requestDate"><?= _translate("Request Date"); ?> </label>
													<div class="col-lg-7">
														<input type="text" class="form-control date" id="requestDate" name="requestDate" placeholder="<?= _htmlTranslate("Request Date"); ?>" title="<?= _htmlTranslate("Please select request date"); ?>" value="<?php echo $vlQueryInfo['test_requested_on']; ?>" />
													</div>
												</div>
											</div>
											<!--	<div class="row" style="display:none;">

												<div class="col-md-4">
													<label class="col-lg-5 control-label" for="emailHf">Email for HF </label>
													<div class="col-lg-7">
														<input type="text" class="form-control isEmail" id="emailHf" name="emailHf" placeholder="Email for HF" title="Please enter email for hf" value="<?php echo $facilityResult[0]['facility_emails']; ?>" />
													</div>
												</div>
											</div>--->
										</div>
									</div>
									<?php if (_isAllowed('/vl/results/vlTestResult.php') && $_SESSION['accessType'] != 'collection-site') { ?>
										<div class="box-header with-border">
											<h3 class="box-title"><?= _translate("Laboratory Information"); ?></h3>
										</div>
										<div class="box-body labSectionBody">
											<div class="row">
												<!-- <div class="col-md-4">
													<label for="labId" class="col-lg-5 control-label">Lab Name </label>
													<div class="col-lg-7">
														<select name="labId" id="labId" class="select2 form-control labSection" title="Please choose lab">
															<option value="">-- Select --</option>
															<?php foreach ($lResult as $labName) { ?>
																<option data-focalperson="<?php echo $labName['contact_person']; ?>" data-focalphone="<?php echo $labName['facility_mobile_numbers']; ?>" value="<?php echo $labName['facility_id']; ?>" <?php echo (isset($vlQueryInfo['lab_id']) && $vlQueryInfo['lab_id'] == $labName['facility_id']) ? 'selected="selected"' : ''; ?>><?= $labName['facility_name']; ?></option>
															<?php } ?>
														</select>
													</div>
												</div> -->
												<div class="col-md-6">
													<label for="vlFocalPerson" class="col-lg-5 control-label"><?= _translate("VL Focal Person"); ?> </label>
													<div class="col-lg-7">
														<select class="form-control ajax-select2" id="vlFocalPerson" name="vlFocalPerson" title="<?= _htmlTranslate("Please enter focal person name"); ?>">
															<option value="<?= ($vlQueryInfo['vl_focal_person']); ?>" selected='selected'> <?= ($vlQueryInfo['vl_focal_person']); ?></option>
														</select>
													</div>
												</div>
												<div class="col-md-6">
													<label for="vlFocalPersonPhoneNumber" class="col-lg-5 control-label"><?= _translate("VL Focal Person Phone Number"); ?></label>
													<div class="col-lg-7">
														<input type="text" class="form-control phone-number labSection" id="vlFocalPersonPhoneNumber" name="vlFocalPersonPhoneNumber" maxlength="15" placeholder="<?= _htmlTranslate("Phone Number"); ?>" title="<?= _htmlTranslate("Please enter focal person phone number"); ?>" value="<?= ($vlQueryInfo['vl_focal_person_phone_number']); ?>" />
													</div>
												</div>
											</div>

											<div class="row">
												<div class="col-md-6">
													<label for="testingPlatform" class="col-lg-5 control-label"><?= _translate("VL Testing Platform"); ?> <span class="mandatory result-span">*</span></label>
													<div class="col-lg-7">
														<select name="testingPlatform" id="testingPlatform" class="form-control result-optional labSection" title="<?= _htmlTranslate("Please choose VL Testing Platform"); ?>">
															<option value=""><?= _translate("-- Select --"); ?></option>
															<?php foreach ($importResult as $mName) { ?>
																<option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] . '##' . $mName['instrument_id']; ?>" <?php echo ($vlQueryInfo['vl_test_platform'] == $mName['machine_name']) ? 'selected="selected"' : ''; ?>><?php echo $mName['machine_name']; ?><?php echo (($mName['status'] ?? '') !== 'active') ? ' (' . _translate('Inactive') . ')' : ''; ?></option>
															<?php } ?>
														</select>
													</div>
												</div>
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="isSampleRejected"><?= _translate("Is Sample Rejected?"); ?> <span class="mandatory result-span">*</span></label>
													<div class="col-lg-7">
														<select name="isSampleRejected" id="isSampleRejected" class="form-control labSection" title="<?= _htmlTranslate("Please check if sample is rejected or not"); ?>">
															<option value=""><?= _translate("-- Select --"); ?></option>
															<option value="yes" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'selected="selected"' : ''; ?>><?= _translate("Yes"); ?></option>
															<option value="no" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'no') ? 'selected="selected"' : ''; ?>><?= _translate("No"); ?></option>
														</select>
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-md-6 rejectionReason" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;">
													<label class="col-lg-5 control-label" for="rejectionReason"><?= _translate("Rejection Reason"); ?> </label>
													<div class="col-lg-7">
														<select name="rejectionReason" id="rejectionReason" class="form-control labSection" title="<?= _htmlTranslate("Please choose reason"); ?>" onchange="checkRejectionReason();">
															<option value=""><?= _translate("-- Select --"); ?></option>
															<?php foreach ($rejectionTypeResult as $type) { ?>
																<optgroup label="<?php echo strtoupper((string) $type['rejection_type']); ?>">
																	<?php
																	foreach ($rejectionResult as $reject) {
																		if ($type['rejection_type'] == $reject['rejection_type']) { ?>
																			<option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($vlQueryInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?= $reject['rejection_reason_name']; ?></option>
																	<?php }
																	} ?>
																</optgroup>
															<?php } ?>
															<option value="other"><?= _translate("Other (Please Specify)"); ?> </option>

														</select>
														<input type="text" class="form-control newRejectionReason" name="newRejectionReason" id="newRejectionReason" placeholder="<?= _htmlTranslate("Rejection Reason"); ?>" title="<?= _htmlTranslate("Please enter rejection reason"); ?>" style="width:100%;display:none;margin-top:2px;">
													</div>
												</div>
												<div class="col-md-6 rejectionReason" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;">
													<label class="col-lg-5 control-label" for="rejectionDate"><?= _translate("Rejection Date"); ?> </label>
													<div class="col-lg-7">
														<input value="<?php echo DateUtility::humanReadableDateFormat($vlQueryInfo['rejection_on']); ?>" class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="<?= _htmlTranslate("Select Rejection Date"); ?>" title="<?= _htmlTranslate("Please select Sample Rejection Date"); ?>" />
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="sampleTestingDateAtLab"><?= _translate("Sample Testing Date"); ?> <span class="mandatory result-span">*</span></label>
													<div class="col-lg-7">
														<input type="text" class="form-control dateTime result-fieldsform-control result-fields labSection <?php echo ($vlQueryInfo['is_sample_rejected'] == 'no') ? 'isRequired' : ''; ?>" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? ' disabled="disabled" ' : ''; ?> id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="<?= _htmlTranslate("Sample Testing Date"); ?>" title="<?= _htmlTranslate("Please select sample testing date"); ?>" value="<?php echo $vlQueryInfo['sample_tested_datetime']; ?>" onchange="checkSampleTestingDate();" />
													</div>
												</div>
												<div class="col-md-6 vlResult" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'none' : 'block'; ?>;">
													<label class="col-lg-5 control-label" for="vlResult"><?= _translate("Viral Load Result (copies/mL)"); ?> </label>
													<div class="col-lg-7 resultInputContainer">
														<input list="possibleVlResults" class="form-control result-fields labSection" id="vlResult" name="vlResult" placeholder="<?= _htmlTranslate("Select or Type VL Result"); ?>" title="<?= _htmlTranslate("Please enter viral load result"); ?>" value="<?= ($vlQueryInfo['result']); ?>" onchange="calculateLogValue(this)">
														<datalist id="possibleVlResults" title="<?= _htmlTranslate("Please enter viral load result"); ?>">

														</datalist>
													</div>
												</div>
											</div>
											<div class="row">

												<div class="col-md-6 vlLog" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'none' : 'block'; ?>;">
													<label class="col-lg-5 control-label" for="vlLog"><?= _translate("Viral Load (Log)"); ?> </label>
													<div class="col-lg-7">
														<input type="text" class="form-control labSection" id="vlLog" name="vlLog" placeholder="<?= _htmlTranslate("Viral Load (Log)"); ?>" title="<?= _htmlTranslate("Please enter viral load in log"); ?>" value="<?= ($vlQueryInfo['result_value_log']); ?>" <?php echo ($vlQueryInfo['result'] == 'Target Not Detected' || $vlQueryInfo['result'] == 'Below Detection Level') ? 'readonly="readonly"' : ''; ?> style="width:100%;" onchange="calculateLogValue(this);" />
													</div>
												</div>
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="reviewedBy"><?= _translate("Reviewed By"); ?> <span class="mandatory review-approve-span" style="display: <?php echo ($vlQueryInfo['is_sample_rejected'] != '') ? 'inline' : 'none'; ?>;">*</span></label>
													<div class="col-lg-7">
														<select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="<?= _htmlTranslate("Please choose reviewed by"); ?>" style="width: 100%;">
															<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_reviewed_by'], _translate("-- Select --")); ?>
														</select>
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-md-6 hivDetection" style="<?php echo (($isGeneXpert === false) || ($isGeneXpert && $vlQueryInfo['is_sample_rejected'] === 'yes')) ? 'display: none;' : ''; ?>">
													<label for="hivDetection" class="col-lg-5 control-label"><?= _translate("HIV Detection"); ?> </label>
													<div class="col-lg-7">
														<select name="hivDetection" id="hivDetection" class="form-control hivDetection labSection" title="<?= _htmlTranslate("Please choose HIV detection"); ?>">
															<option value=""><?= _translate("-- Select --"); ?></option>
															<option value="HIV-1 Detected" <?php echo (isset($vlQueryInfo['result_value_hiv_detection']) && $vlQueryInfo['result_value_hiv_detection'] == 'HIV-1 Detected') ? 'selected="selected"' : ''; ?>><?= _translate("HIV-1 Detected"); ?></option>
															<option value="HIV-1 Not Detected" <?php echo (isset($vlQueryInfo['result_value_hiv_detection']) && $vlQueryInfo['result_value_hiv_detection'] == 'HIV-1 Not Detected') ? 'selected="selected"' : ''; ?>><?= _translate("HIV-1 Not Detected"); ?></option>
														</select>
													</div>
												</div>
												<?php if (count($reasonForFailure) > 0) { ?>
													<div class="col-md-4 labSection" style="<?php echo (!isset($vlQueryInfo['result']) || $vlQueryInfo['result'] == 'Failed') ? '' : 'display: none;'; ?>">
														<label class="col-lg-5 control-label" for="reasonForFailure"><?= _translate("Reason for Failure"); ?> </label>
														<div class="col-lg-7">
															<select name="reasonForFailure" id="reasonForFailure" class="form-control vlResult" title="<?= _htmlTranslate("Please choose reason for failure"); ?>" style="width: 100%;">
																<?= $general->generateSelectOptions($reasonForFailure, $vlQueryInfo['reason_for_failure'], _translate("-- Select --")); ?>
															</select>
														</div>
													</div>
												<?php } ?>
											</div>
											<hr>
											<div class="row">

												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="reviewedOn"><?= _translate("Reviewed On"); ?> <span class="mandatory review-approve-span" style="display: <?php echo ($vlQueryInfo['is_sample_rejected'] != '') ? 'inline' : 'none'; ?>;">*</span></label>
													<div class="col-lg-7">
														<input type="text" value="<?php echo $vlQueryInfo['result_reviewed_datetime']; ?>" name="reviewedOn" id="reviewedOn" class="dateTime form-control" placeholder="<?= _htmlTranslate("Reviewed on"); ?>" title="<?= _htmlTranslate("Please enter the Reviewed on"); ?>" />
													</div>
												</div>
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="testedBy"><?= _translate("Tested By"); ?> </label>
													<div class="col-lg-7">
														<select name="testedBy" id="testedBy" class="select2 form-control" title="<?= _htmlTranslate("Please choose approved by"); ?>">
															<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['tested_by'], _translate("-- Select --")); ?>
														</select>
													</div>
												</div>
											</div>
											<div class="row">

												<?php $styleStatus = '';
												if ((($_SESSION['accessType'] == 'collection-site') && $vlQueryInfo['result_status'] == SAMPLE_STATUS\RECEIVED_AT_CLINIC) || ($sCode != '')) {
													$styleStatus = "display:none"; ?>
													<input type="hidden" name="status" value="<?= ($vlQueryInfo['result_status']); ?>" />
												<?php } ?>
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="approvedBy"><?= _translate("Approved By"); ?> <span class="mandatory review-approve-span" style="display: <?php echo ($vlQueryInfo['is_sample_rejected'] != '') ? 'block' : 'none'; ?>;">*</span></label>
													<div class="col-lg-7">
														<select name="approvedBy" id="approvedBy" class="form-control labSection" title="<?= _htmlTranslate("Please choose approved by"); ?>">
															<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_approved_by'], _translate("-- Select --")); ?>
														</select>
													</div>
												</div>
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="approvedOn"><?= _translate("Approved On"); ?><span class="mandatory review-approve-span" style="display: <?php echo ($vlQueryInfo['is_sample_rejected'] != '') ? 'block' : 'none'; ?>;">*</span></label>
													<div class="col-lg-7">
														<input type="text" value="<?php echo $vlQueryInfo['result_approved_datetime']; ?>" class="form-control dateTime" id="approvedOn" name="approvedOnDateTime" placeholder="<?= _translate("Please enter date"); ?>" style="width:100%;" />
													</div>
												</div>
											</div>
											<div class="row">

												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="resultDispatchedOn"><?= _translate("Date Results Dispatched"); ?> </label>
													<div class="col-lg-7">
														<input type="text" class="form-control labSection dateTime" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="<?= _htmlTranslate("Result Dispatched Date"); ?>" title="<?= _htmlTranslate("Please select result dispatched date"); ?>" value="<?php echo $vlQueryInfo['result_dispatched_datetime']; ?>" />
													</div>
												</div>
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="labComments"><?= _translate("Lab Tech. Comments"); ?> </label>
													<div class="col-lg-7">
														<textarea class="form-control labSection" name="labComments" id="labComments" placeholder="<?= _htmlTranslate("Lab comments"); ?>" style="width:100%"><?php echo trim((string) $vlQueryInfo['lab_tech_comments']); ?></textarea>
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-md-6 reasonForResultChanges" style="display:none;">
													<label class="col-lg-5 control-label" for="reasonForResultChanges"><?= _translate("Reason For Changes in Result"); ?><span class="mandatory">*</span></label>
													<div class="col-lg-7">
														<textarea class="form-control" name="reasonForResultChanges" id="reasonForResultChanges" placeholder="<?= _htmlTranslate("Enter Reason For Result Changes"); ?>" title="<?= _htmlTranslate("Please enter reason for result changes"); ?>" style="width:100%;"></textarea>
													</div>
												</div>
											</div>
										</div>
									<?php } ?>
								</div>
							</div>
							<div class="box-footer">
								<input type="hidden" name="revised" id="revised" value="no" />
								<input type="hidden" name="vlSampleId" id="vlSampleId" value="<?= ($vlQueryInfo['vl_sample_id']); ?>" />
								<input type="hidden" name="isRemoteSample" value="<?= ($vlQueryInfo['remote_sample']); ?>" />
								<input type="hidden" name="reasonForResultChangesHistory" id="reasonForResultChangesHistory" value="<?php echo base64_encode((string) $vlQueryInfo['reason_for_result_changes']); ?>" />
								<input type="hidden" name="oldStatus" value="<?= ($vlQueryInfo['result_status']); ?>" />
								<input type="hidden" name="countryFormId" id="countryFormId" value="<?php echo $arr['vl_form']; ?>" />
								<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?= _translate("Save"); ?></a>&nbsp;
								<a href="/vl/requests/vl-requests.php" class="btn btn-default"> <?= _translate("Cancel"); ?></a>
							</div>
				</form>
			</div>
	</section>
</div>
<script type="text/javascript" src="<?= _asset('/assets/js/datalist-css.min.js') ?>"></script>
<script type="text/javascript">
	let provinceName = true;
	let facilityName = true;

	let __clone = null;
	let reason = null;
	let resultValue = null;

	$(document).ready(function() {
		hivDetectionChange();
		$('#sampleCollectionDate').trigger('changeDate');
		//getFacilities(document.getElementById("district"));
		$("#labId,#facilityId,#sampleCollectionDate").on('change', function() {

			if ($("#labId").val() != '' && $("#labId").val() == $("#facilityId").val() && $("#sampleDispatchedDate").val() == "") {
				$('#sampleDispatchedDate').val($('#sampleCollectionDate').val());
			}
			if ($("#labId").val() != '' && $("#labId").val() == $("#facilityId").val() && $("#sampleReceivedDate").val() == "") {
				$('#sampleReceivedDate').val($('#sampleCollectionDate').val());
				$('#sampleReceivedAtHubOn').val($('#sampleCollectionDate').val());
			}
		});

		$("#labId").on('change', function() {
			if ($("#labId").val() != "") {
				$.post("/includes/get-sample-type.php", {
						facilityId: $('#labId').val(),
						testType: 'vl',
						sampleId: '<?php echo $vlQueryInfo['specimen_type']; ?>'
					},
					function(data) {
						if (data != "") {
							$("#specimenType").html(data);
						}
					});
			}
		});

		$('#facilityId').select2({
			width: '100%',
			placeholder: "<?= _jsTranslate("Select Clinic/Health Center"); ?>"
		});
		$('#labId').select2({
			width: '100%',
			placeholder: "<?= _jsTranslate("Select Testing Lab"); ?>"
		});
		$('#reviewedBy').select2({
			width: '100%',
			placeholder: "<?= _jsTranslate("Select Reviewed By"); ?>"
		});
		$('#testedBy').select2({
			width: '100%',
			placeholder: "<?= _jsTranslate("Select Tested By"); ?>"
		});

		$('#approvedBy').select2({
			width: '100%',
			placeholder: "<?= _jsTranslate("Select Approved By"); ?>"
		});
		$('#facilityId').select2({
			placeholder: "<?= _jsTranslate("Select Clinic/Health Center"); ?>"
		});
		$('#district').select2({
			placeholder: "<?= _jsTranslate("District"); ?>"
		});
		$('#province').select2({
			placeholder: "<?= _jsTranslate("Province"); ?>"
		});

		$('#artRegimen').select2({
			placeholder: "<?= _jsTranslate("Select ART Regimen"); ?>"
		});

		getfacilityProvinceDetails($("#facilityId").val());


		setTimeout(function() {
			$("#vlResult").trigger('change');
			$("#hivDetection, #isSampleRejected").trigger('change');
			// just triggering sample collection date is enough,
			// it will automatically do everything that labId and facilityId changes will do
			$("#sampleCollectionDate").trigger('change');
			__clone = $(".labSectionBody").clone();
			reason = ($("#reasonForResultChanges").length) ? $("#reasonForResultChanges").val() : '';
			resultValue = $("#vlResult").val();

			$(".labSection").on("change", function() {
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

		checkPatientReceivesms('<?php echo $vlQueryInfo['consent_to_receive_sms']; ?>');

		$("#reqClinician").select2({
			placeholder: "<?= _jsTranslate("Enter Requesting Clinician Name"); ?>",
			minimumInputLength: 0,
			width: '100%',
			allowClear: true,
			id: function(bond) {
				return bond._id;
			},
			ajax: {
				placeholder: "<?= _jsTranslate("Type one or more character to search"); ?>",
				url: "/includes/get-data-list.php",
				dataType: 'json',
				delay: 250,
				data: function(params) {
					return {
						fieldName: 'request_clinician_name',
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

		$("#reqClinician").change(function() {
			$.blockUI();
			var search = $(this).val();
			if ($.trim(search) != '') {
				$.get("/includes/get-data-list.php", {
						fieldName: 'request_clinician_name',
						tableName: 'form_vl',
						returnField: 'request_clinician_phone_number',
						limit: 1,
						q: search,
					},
					function(data) {
						if (data != "") {
							$("#reqClinicianPhoneNumber").val(data);
						}
					});
			}
			$.unblockUI();
		});

		$("#vlFocalPerson").select2({
			placeholder: "<?= _jsTranslate("Enter Request Focal name"); ?>",
			minimumInputLength: 0,
			width: '100%',
			allowClear: true,
			id: function(bond) {
				return bond._id;
			},
			ajax: {
				placeholder: "<?= _jsTranslate("Type one or more character to search"); ?>",
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

		$('#vlResult').on('change', function() {
			if ($(this).val().trim().toLowerCase() == 'failed' || $(this).val().trim().toLowerCase() == 'error') {
				if ($(this).val().trim().toLowerCase() == 'failed') {
					$('.reasonForFailure').show();
					$('#reasonForFailure').addClass('isRequired');
				}
				$('#vlLog, .hivDetection').attr('readonly', true);
			} else {
				$('.reasonForFailure').hide();
				$('#reasonForFailure').removeClass('isRequired');
				$('#vlLog, .hivDetection').attr('readonly', false);
			}
		});

	});

	function showTesting(chosenClass) {
		$(".viralTestData").val('');
		$(".hideTestData").hide();
		$("." + chosenClass).show();
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
					testType: 'vl'
				},
				function(data) {
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
			$("#facilityId").html("<option data-code='' data-emails='' data-mobile-nos='' data-contact-person='' value=''> <?= _jsTranslate("-- Select --"); ?> </option>");
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
					testType: 'vl'
				},
				function(data) {
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
					testType: 'vl'
				},
				function(data) {
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
		($.trim(femails) != '') ? $(".femails").show(): $(".femails").hide();
		($.trim(femails) != '') ? $(".facilityEmails").html(femails): $(".facilityEmails").html('');
		($.trim(fmobilenos) != '') ? $(".fmobileNumbers").show(): $(".fmobileNumbers").hide();
		($.trim(fmobilenos) != '') ? $(".facilityMobileNumbers").html(fmobilenos): $(".facilityMobileNumbers").html('');
		($.trim(fContactPerson) != '') ? $(".fContactPerson").show(): $(".fContactPerson").hide();
		($.trim(fContactPerson) != '') ? $(".facilityContactPerson").html(fContactPerson): $(".facilityContactPerson").html('');
	}
	$("input:radio[name=gender]").click(function() {
		if ($(this).val() == 'male' || $(this).val() == 'unreported') {
			$('.femaleSection').hide();
			$('input[name="breastfeeding"]').prop('checked', false);
			$('input[name="patientPregnant"]').prop('checked', false);
		} else if ($(this).val() == 'female') {
			$('.femaleSection').show();
		}
	});
	$("#sampleTestingDateAtLab").change(function() {
		if ($(this).val() != "") {
			$(".result-fields").attr("disabled", false);
			$(".result-fields").addClass("isRequired");
			$(".result-span").show();
			$('.vlResult').css('display', 'block');
			$('.vlLog').css('display', 'block');
			$('.rejectionReason').hide();
			$('#rejectionReason').removeClass('isRequired');
			$('#rejectionDate').removeClass('isRequired');
			$('#rejectionReason').val('');
			$(".review-approve-span").hide();
			$("#hivDetection, #isSampleRejected").trigger('change');
		}
	});
	$("#isSampleRejected").on("change", function() {

		hivDetectionChange();


		if ($(this).val() == 'yes') {
			$('.rejectionReason').show();
			$('.vlResult, .hivDetection').css('display', 'none');
			$('.vlLog').css('display', 'none');
			$("#sampleTestingDateAtLab, #vlResult, .hivDetection").val("");
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
			$('.vlResult,.vlLog').css('display', 'block');
			$('.rejectionReason').hide();
			$('#rejectionReason').removeClass('isRequired');
			$('#rejectionDate').removeClass('isRequired');
			$('#rejectionReason').val('');
			$('#reviewedBy').addClass('isRequired');
			$('#reviewedOn').addClass('isRequired');
			$('#approvedBy').addClass('isRequired');
			$('#approvedOn').addClass('isRequired');
			//$(".hivDetection").trigger("change");
		} else {
			$(".result-fields").attr("disabled", false);
			$(".result-fields").removeClass("isRequired");
			$(".result-optional").removeClass("isRequired");
			$(".result-span").show();
			$('.vlResult,.vlLog').css('display', 'block');
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
			//$(".hivDetection").trigger("change");
		}
	});

	$('#hivDetection').on("change", function() {
		if (this.value == null || this.value == '' || this.value == undefined) {
			return false;
		} else if (this.value === 'HIV-1 Not Detected') {
			$("#isSampleRejected").val("no");
			$('#vlResult').attr('disabled', false);
			$('#vlLog').attr('disabled', false);
			$("#vlResult,#vlLog").val('');
			$(".vlResult, .vlLog").hide();
			$("#reasonForFailure").removeClass('isRequired');
			$('#vlResult').removeClass('isRequired');
		} else if (this.value === 'HIV-1 Detected') {
			$("#isSampleRejected").val("no");
			$(".vlResult, .vlLog").show();
			$("#isSampleRejected").trigger("change");
			$('#vlResult').addClass('isRequired');
		}
	});

	$('#testingPlatform').on("change", function() {
		$(".vlResult, .vlLog").show();
		//$('#vlResult, #isSampleRejected').addClass('isRequired');
		$("#isSampleRejected").val("");
		//$("#isSampleRejected").trigger("change");
		hivDetectionChange();
	});

	function hivDetectionChange() {

		var text = $('#testingPlatform').val();
		if (!text) {
			$("#vlResult").attr("disabled", true);
			return;
		}
		var str1 = text.split("##");
		var str = str1[0];
		if ((str.trim() == 'GeneXpert' || str.toLowerCase() == 'genexpert') && $('#isSampleRejected').val() != 'yes') {
			$('.hivDetection').prop('disabled', false);
			$('.hivDetection').show();
		} else {
			$('.hivDetection').hide();
			$("#hivDetection").val("");
		}

		//Get VL results by platform id
		var platformId = str1[3];
		$("#possibleVlResults").html('');
		$.post("/vl/requests/getVlResults.php", {
				instrumentId: platformId,
			},
			function(data) {
				// alert(data);
				$("#vlResult").attr("disabled", false);
				if (data != "") {
					$("#possibleVlResults").html(data);
				}
			});
	}

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

		clearDatePlaceholderValues('input.date, input.dateTime');

		if ($('#isSampleRejected').val() == "yes") {
			$('.vlResult, #vlResult').removeClass('isRequired');
		}
		flag = deforayValidator.init({
			formId: 'vlRequestFormRwd'
		});

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

	function calculateLogValue(obj) {
		if (obj.id == "vlResult") {
			absValue = $("#vlResult").val();
			absValue = Number.parseFloat(absValue).toFixed();
			if (absValue != '' && absValue != 0 && !isNaN(absValue)) {
				//$("#vlResult").val(absValue);
				$("#vlLog").val(Math.round(Math.log10(absValue) * 100) / 100);
			} else {
				$("#vlLog").val('');
			}
		}
		if (obj.id == "vlLog") {
			logValue = $("#vlLog").val();
			if (logValue != '' && logValue != 0 && !isNaN(logValue)) {
				var absVal = Math.round(Math.pow(10, logValue) * 100) / 100;
				if (absVal != 'Infinity' && !isNaN(absVal)) {
					$("#vlResult").val(Math.round(Math.pow(10, logValue) * 100) / 100);
				}
			} else {
				$("#vlResult").val('');
			}
		}
	}
</script>