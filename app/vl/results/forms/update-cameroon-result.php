<?php

use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$lResult = $facilitiesService->getTestingLabs('vl', byPassFacilityMap: true, allColumns: true);

$province = $general->getUserMappedProvinces($_SESSION['facilityMap']);

$facility = $general->generateSelectOptions($healthFacilities, $vlQueryInfo['facility_id'], '-- Select --');



//facility details
if (isset($vlQueryInfo['facility_id']) && $vlQueryInfo['facility_id'] > 0) {
	$facilityQuery = "SELECT * FROM facility_details where facility_id= ? AND status='active'";
	$facilityResult = $db->rawQuery($facilityQuery, [$vlQueryInfo['facility_id']]);
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
if (!isset($facilityResult[0]['facility_state']) || $facilityResult[0]['facility_state'] == '') {
	$facilityResult[0]['facility_state'] = '';
}
if (!isset($facilityResult[0]['facility_district']) || $facilityResult[0]['facility_district'] == '') {
	$facilityResult[0]['facility_district'] = '';
}

$user = '';
if ($facilityResult[0]['contact_person'] != '') {
	$contactUser = $usersService->getUserByID($facilityResult[0]['contact_person']);
	if (!empty($contactUser)) {
		$user = $contactUser['user_name'];
	}
}

$stateName = $facilityResult[0]['facility_state'];
if (trim((string) $stateName) !== '') {
	$stateQuery = "SELECT * from geographical_divisions where geo_name='" . $stateName . "'";
	$stateResult = $db->query($stateQuery);
}
if (!isset($stateResult[0]['geo_code']) || $stateResult[0]['geo_code'] == '') {
	$stateResult[0]['geo_code'] = '';
}
//district details
$districtResult = [];
if (trim((string) $stateName) !== '') {
	$districtQuery = "SELECT DISTINCT facility_district from facility_details where facility_state='" . $stateName . "' AND status='active'";
	$districtResult = $db->query($districtQuery);
	/* $facilityQuery = "SELECT * from facility_details where `status`='active' AND facility_type='2'";
	$lResult = $db->query($facilityQuery); */
}
$lResult = $facilitiesService->getTestingLabs('vl', byPassFacilityMap: true, allColumns: true);
//set reason for changes history
$rch = '';
$allChange = [];
if (isset($vlQueryInfo['reason_for_result_changes']) && $vlQueryInfo['reason_for_result_changes'] != '') {
	$rch .= '<h4>Result Changes History</h4>';
	$rch .= '<table style="width:100%;">';
	$rch .= '<thead><tr style="border-bottom:2px solid #d3d3d3;"><th style="width:20%;">USER</th><th style="width:60%;">MESSAGE</th><th style="width:20%;text-align:center;">DATE</th></tr></thead>';
	$rch .= '<tbody>';
	$allChange = json_decode((string) $vlQueryInfo['reason_for_result_changes'], true);
	if (!empty($allChange)) {
		$allChange = array_reverse($allChange);
		foreach ($allChange as $change) {
			$usrResult = $usersService->getUserByID($change['usr']);
			$name = $usrResult['user_name'] ?? '';
			$expStr = explode(" ", (string) $change['dtime']);
			$changedDate = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
			$rch .= '<tr><td>' . $name . '</td><td>' . ($change['msg']) . '</td><td style="text-align:center;">' . $changedDate . '</td></tr>';
		}
		$rch .= '</tbody>';
		$rch .= '</table>';
	}
}
$disable = "disabled = 'disabled'";

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
$testReasonsResultDetails = $general->getDataByTableAndFields("r_vl_test_reasons", ['test_reason_id', 'test_reason_name', 'parent_reason'], false, " test_reason_status like 'active' ");
$subTestReasons = $testReasonsResult = [];
foreach ($testReasonsResultDetails as $row) {
	if ($row['parent_reason'] == 0) {
		$testReasonsResult[$row['test_reason_id']] = $row['test_reason_name'];
	} else {
		$subTestReasons[$row['parent_reason']][$row['test_reason_id']] = $row['test_reason_name'];
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
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _translate('VIRAL LOAD LABORATORY REQUEST FORM'); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/dashboard/index.php"><em class="fa-solid fa-chart-pie"></em> <?= _translate('Home'); ?></a></li>
			<li class="active"><?= _translate('Enter VL Result'); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">

		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?= _translate('indicates required fields'); ?> &nbsp;</div>
			</div>
			<div class="box-body">
				<!-- form start -->

				<div class="box-body">
					<div class="box box-primary">
						<div class="box-header with-border">
							<h3 class="box-title"><?= _translate('Clinic Information: (To be filled by requesting Clinican/Nurse)'); ?></h3>
						</div>
						<div class="box-body">
							<div class="row">
								<div class="col-xs-4 col-md-4">
									<div class="form-group">
										<label for="sampleCode"><?= _translate('Sample ID'); ?> <span class="mandatory">*</span></label>
										<input type="text" class="form-control " id="sampleCode" name="sampleCode" placeholder="<?= _translate('Enter Sample ID'); ?>" title="<?= _translate("Please make sure you have selected Sample Collection Date and Requesting Facility"); ?>" value="<?= ($vlQueryInfo['sample_code']); ?>" <?php echo $disable; ?> style="width:100%;" />
									</div>
								</div>
								<div class="col-xs-4 col-md-4">
									<div class="form-group">
										<label for="sampleReordered">
											<input type="checkbox" class="" id="sampleReordered" name="sampleReordered" value="yes" <?php echo (trim((string) $vlQueryInfo['sample_reordered']) === 'yes') ? 'checked="checked"' : '' ?> <?php echo $disable; ?> title="<?= _translate('Please indicate if this is a reordered sample'); ?>"> <?= _translate('Sample Reordered'); ?>
										</label>
									</div>
								</div>

								<div class="col-xs-4 col-md-4">
									<div class="form-group">
										<label for="communitySample"><?= _translate('Community Sample'); ?></label>
										<select class="form-control" name="communitySample" id="communitySample" title="<?= _translate('Please choose if this is a community sample'); ?>" style="width:100%;">
											<option value=""> <?= _translate('-- Select --'); ?> </option>
											<option value="yes" <?php echo (isset($vlQueryInfo['community_sample']) && $vlQueryInfo['community_sample'] == 'yes') ? 'selected="selected"' : ''; ?>><?= _translate('Yes'); ?></option>
											<option value="no" <?php echo (isset($vlQueryInfo['community_sample']) && $vlQueryInfo['community_sample'] == 'no') ? 'selected="selected"' : ''; ?>><?= _translate('No'); ?></option>
										</select>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="province"><?= _translate('Region'); ?> <span class="mandatory">*</span></label>
										<select class="form-control " name="province" id="province" title="<?= _translate('Please choose state'); ?>" <?php echo $disable; ?> style="width:100%;" onchange="getfacilityDetails(this);">
											<option value=""> <?= _translate('-- Select --'); ?> </option>
											<?php foreach ($pdResult as $provinceName) { ?>
												<option value="<?php echo $provinceName['geo_name'] . "##" . $provinceName['geo_code']; ?>" <?php echo ($facilityResult[0]['facility_state'] . "##" . $stateResult[0]['geo_code'] === $provinceName['geo_name'] . "##" . $provinceName['geo_code']) ? "selected='selected'" : "" ?>><?php echo ($provinceName['geo_name']); ?></option>;
											<?php } ?>
										</select>
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="district"><?= _translate('County'); ?> <span class="mandatory">*</span></label>
										<select class="form-control" name="district" id="district" title="<?= _translate('Please choose county'); ?>" <?php echo $disable; ?> style="width:100%;" onchange="getfacilityDistrictwise(this);">
											<option value=""> <?= _translate('-- Select --'); ?>' </option>
											<?php
											foreach ($districtResult as $districtName) { ?>
												<option value="<?php echo $districtName['facility_district']; ?>" <?php echo ($facilityResult[0]['facility_district'] == $districtName['facility_district']) ? "selected='selected'" : "" ?>><?php echo ($districtName['facility_district']); ?></option>
											<?php
											} ?>
										</select>
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="facilityId"><?= _translate('Facility'); ?> <span class="mandatory">*</span></label>
										<select class="form-control " id="facilityId" name="facilityId" title="<?= _translate('Please select clinic/health center name'); ?>" <?php echo $disable; ?> style="width:100%;" onchange="autoFillFacilityCode();">
											<?= $facility; ?>
										</select>
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="facilityCode"><?= _translate('Facility Code'); ?> </label>
										<input type="text" class="form-control" style="width:100%;" name="facilityCode" id="facilityCode" placeholder="<?= _translate('Clinic/Health Center Code'); ?>" title="<?= _translate('Please enter clinic/health center code'); ?>" value="<?php echo $facilityResult[0]['facility_code']; ?>" <?php echo $disable; ?>>
									</div>
								</div>
							</div>
							<div class="row facilityDetails" style="display:<?php echo (trim((string) $facilityResult[0]['facility_emails']) !== '' || trim((string) $facilityResult[0]['facility_mobile_numbers']) !== '' || trim((string) $facilityResult[0]['contact_person']) !== '') ? '' : 'none'; ?>;">
								<div class="col-xs-2 col-md-2 femails" style="display:<?php echo (trim((string) $facilityResult[0]['facility_emails']) !== '') ? '' : 'none'; ?>;">
									<strong><?= _translate('Clinic/Health Center Email(s)'); ?></strong>
								</div>
								<div class="col-xs-2 col-md-2 femails facilityEmails" style="display:<?php echo (trim((string) $facilityResult[0]['facility_emails']) !== '') ? '' : 'none'; ?>;">
									<?php echo $facilityResult[0]['facility_emails']; ?></div>
								<div class="col-xs-2 col-md-2 fmobileNumbers" style="display:<?php echo (trim((string) $facilityResult[0]['facility_mobile_numbers']) !== '') ? '' : 'none'; ?>;">
									<strong><?= _translate('Clinic/Health Center Mobile No.(s)'); ?></strong>
								</div>
								<div class="col-xs-2 col-md-2 fmobileNumbers facilityMobileNumbers" style="display:<?php echo (trim((string) $facilityResult[0]['facility_mobile_numbers']) !== '') ? '' : 'none'; ?>;">
									<?php echo $facilityResult[0]['facility_mobile_numbers']; ?></div>
								<div class="col-xs-2 col-md-2 fContactPerson" style="display:<?php echo (trim((string) $facilityResult[0]['contact_person']) !== '') ? '' : 'none'; ?>;">
									<strong><?= _translate('Clinic Contact Person'); ?></strong>
								</div>
								<div class="col-xs-2 col-md-2 fContactPerson facilityContactPerson" style="display:<?php echo (trim((string) $user) !== '') ? '' : 'none'; ?>;">
									<?php echo ($user); ?></div>
							</div>
							<div class="row">
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="fundingSource"><?= _translate("Project Name"); ?></label>
										<select <?php echo $disable; ?> class="form-control" name="fundingSource" id="fundingSource" title="<?= _translate('Please choose implementing partner'); ?>" style="width:100%;">
											<option value=""> <?= _translate("-- Select --"); ?> </option>
											<?php
											foreach ($fundingSourceList as $fundingSource) { ?>
												<option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>" <?php echo ($fundingSource['funding_source_id'] == $vlQueryInfo['funding_source']) ? 'selected="selected"' : ''; ?>><?= $fundingSource['funding_source_name']; ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="implementingPartner"><?= _translate("Implementing Partner"); ?></label>
										<select <?php echo $disable; ?> class="form-control" name="implementingPartner" id="implementingPartner" title="<?= _translate('Please choose implementing partner'); ?>" style="width:100%;">
											<option value=""> <?= _translate('-- Select --'); ?> </option>
											<?php
											foreach ($implementingPartnerList as $implementingPartner) { ?>
												<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>" <?php echo ($implementingPartner['i_partner_id'] == $vlQueryInfo['implementing_partner']) ? 'selected="selected"' : ''; ?>><?= $implementingPartner['i_partner_name']; ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
								<div class="col-md-3 col-md-3">
									<label for="labId"><?= _translate("Testing Lab"); ?> <span class="mandatory">*</span></label>
									<select <?php echo $disable; ?> name="labId" id="labId" class="select2 form-control isRequired" title="Please choose lab" onchange="autoFillFocalDetails();setSampleDispatchDate();" style="width:100%;">
										<option value="">-- Select --</option>
										<?php foreach ($lResult as $labName) { ?>
											<option data-focalperson="<?php echo $labName['contact_person']; ?>" data-focalphone="<?php echo $labName['facility_mobile_numbers']; ?>" value="<?php echo $labName['facility_id']; ?>" <?php echo ($labName['facility_id'] == $vlQueryInfo['lab_id']) ? 'selected="selected"' : ''; ?>><?= $labName['facility_name']; ?></option>
										<?php } ?>
									</select>
								</div>
							</div>
							<?php if ($general->isLISInstance()) { ?>
								<div class="row">
									<div class="col-md-3">
										<label for="labAssignedCode"><?= _translate('Lab Assigned Code'); ?> </label>
										<input <?php echo $disable; ?> name="labAssignedCode" id="labAssignedCode" class="form-control" placeholder="<?= _translate('Enter Lab Assigned Code'); ?>" title="<?= _translate('Please enter Lab Assigned Code'); ?>" value="<?= $vlQueryInfo['lab_assigned_code']; ?>" <?php echo $labFieldDisabled; ?>>
									</div>
								</div>
						</div>
					<?php } ?>
					</div>
					<div class="box box-primary">
						<div class="box-header with-border">
							<h3 class="box-title"><?= _translate('Patient Information'); ?></h3>
						</div>
						<div class="box-body">
							<div class="row">
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="artNo"><?= _translate('ART (TRACNET) No.'); ?> <span class="mandatory">*</span></label>
										<input type="text" name="artNo" id="artNo" class="form-control " placeholder="<?= _translate('Enter ART Number'); ?>" title="<?= _translate('Enter art number'); ?>" value="<?= ($vlQueryInfo['patient_art_no']); ?>" <?php echo $disable; ?> />
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="dob"><?= _translate('Date of Birth'); ?> </label>
										<input type="text" name="dob" id="dob" class="form-control date" placeholder="<?= _translate('Enter DOB'); ?>" title="<?= _translate('Enter dob'); ?>" value="<?= ($vlQueryInfo['patient_dob']); ?>" <?php echo $disable; ?> />
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="ageInYears"><?= _translate('If DOB unknown, Age in Year'); ?> </label>
										<input type="text" name="ageInYears" id="ageInYears" class="form-control forceNumeric" maxlength="2" placeholder="<?= _translate('Age in Year'); ?>" title="<?= _translate('Enter age in years'); ?>" <?php echo $disable; ?> value="<?= ($vlQueryInfo['patient_age_in_years']); ?>" />
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="ageInMonths"><?= _translate('If Age < 1, Age in Month'); ?> </label> <input type="text" name="ageInMonths" id="ageInMonths" class="form-control forceNumeric" maxlength="2" placeholder="<?= _translate('Age in Month'); ?>" title="<?= _translate('Enter age in months'); ?>" <?php echo $disable; ?> value="<?= ($vlQueryInfo['patient_age_in_months']); ?>" />
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="patientFirstName"><?= _translate('Patient First Name'); ?> </label>
										<input type="text" name="patientFirstName" id="patientFirstName" class="form-control" placeholder="<?= _translate('Enter Patient Name'); ?>" title="<?= _translate('Enter patient name'); ?>" <?php echo $disable; ?> value="<?= $vlQueryInfo['patient_first_name'] ?>" />
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="patientLastName"><?= _translate('Patient Last Name'); ?> </label>
										<input type="text" name="patientLastName" id="patientLastName" value="<?= $vlQueryInfo['patient_last_name'] ?>" class="form-control" placeholder="<?= _translate('Enter Patient Last Name'); ?>" title="<?= _translate('Enter patient last name'); ?>" <?php echo $disable; ?> />
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="gender"><?= _translate('Sex'); ?></label><br>
										<label class="radio-inline" style="margin-left:0px;">
											<input type="radio" class="" id="genderMale" name="gender" value="male" title="<?= _translate('Please choose sex'); ?>" <?php echo $disable; ?> <?php echo ($vlQueryInfo['patient_gender'] == 'male') ? "checked='checked'" : "" ?>>
											<?= _translate('Male'); ?>
										</label>
										<label class="radio-inline" style="margin-left:0px;">
											<input type="radio" class="" id="genderFemale" name="gender" value="female" title="<?= _translate('Please choose sex'); ?>" <?php echo $disable; ?> <?php echo ($vlQueryInfo['patient_gender'] == 'female') ? "checked='checked'" : "" ?>> <?= _translate('Female'); ?>
										</label>
										<label class="radio-inline" style="margin-left:0px;">
											<input type="radio" id="genderUnreported" name="gender" value="unreported" title="<?= _translate('Please select sex'); ?>" <?php echo $disable; ?> <?php echo ($vlQueryInfo['patient_gender'] == 'unreported') ? "checked='checked'" : "" ?>><?= _translate('Unreported'); ?>
										</label>
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="healthInsuranceCode"><?= _translate('Universal Health Coverage'); ?></label>
										<input type="text" name="healthInsuranceCode" id="healthInsuranceCode" value="<?= ($vlQueryInfo['health_insurance_code']); ?>" class="form-control" placeholder="<?= _translate('Enter Universal Health Coverage'); ?>" <?php echo $disable; ?> title="<?= _translate('Enter Universal Health Coverage'); ?>" maxlength="32" />
									</div>
								</div>

								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="patientPhoneNumber"><?= _translate('Phone Number'); ?></label>
										<input type="text" name="patientPhoneNumber" id="patientPhoneNumber" class="form-control phone-number" maxlength="15" placeholder="<?= _translate('Enter Phone Number'); ?>" title="<?= _translate('Enter phone number'); ?>" value="<?= ($vlQueryInfo['patient_mobile_number']); ?>" <?php echo $disable; ?> />
									</div>
								</div>
								<div class="row femaleSection" style="display:<?php echo ($vlQueryInfo['patient_gender'] == 'female') ? "" : "none" ?>" ;>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="patientPregnant"><?= _translate('Is Patient Pregnant?'); ?> <span class="mandatory">*</span></label><br>
											<label class="radio-inline">
												<input type="radio" class="<?php echo ($vlQueryInfo['patient_gender'] == 'female') ? "isRequired" : ""; ?>" id="pregYes" name="patientPregnant" value="yes" title="<?= _translate('Please check if patient is pregnant'); ?>" <?php echo ($vlQueryInfo['is_patient_pregnant'] == 'yes') ? "checked='checked'" : "" ?>> <?= _translate('Yes'); ?>
											</label>
											<label class="radio-inline">
												<input type="radio" class="" id="pregNo" name="patientPregnant" value="no" <?php echo ($vlQueryInfo['is_patient_pregnant'] == 'no') ? "checked='checked'" : "" ?>> <?= _translate('No'); ?>
											</label>
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="patientPregnant"><?= _translate('If Yes, Number of weeks of pregnancy?'); ?> </label>
											<input type="text" class="forceNumeric form-control" id="noOfPregnancyWeeks" name="noOfPregnancyWeeks" value="<?= ($vlQueryInfo['no_of_pregnancy_weeks']); ?>" title="<?= _translate('Number of weeks of pregnancy'); ?>" placeholder="<?= _translate('Number of weeks of pregnancy'); ?>">
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="breastfeeding"><?= _translate('Is Patient Breastfeeding?'); ?> <span class="mandatory">*</span></label><br>
											<label class="radio-inline">
												<input type="radio" class="<?php echo ($vlQueryInfo['patient_gender'] == 'female') ? "isRequired" : ""; ?>" id="breastfeedingYes" name="breastfeeding" value="yes" title="<?= _translate('Please check if patient is breastfeeding'); ?>" <?php echo ($vlQueryInfo['is_patient_breastfeeding'] == 'yes') ? "checked='checked'" : "" ?>> <?= _translate('Yes'); ?>
											</label>
											<label class="radio-inline">
												<input type="radio" class="" id="breastfeedingNo" name="breastfeeding" value="no" <?php echo ($vlQueryInfo['is_patient_breastfeeding'] == 'no') ? "checked='checked'" : "" ?>> <?= _translate('No'); ?>
											</label>
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="patientPregnant"><?= _translate('If Yes, For how many weeks?'); ?> </label>
											<input type="text" class="forceNumeric form-control" id="noOfBreastfeedingWeeks" name="noOfBreastfeedingWeeks" value="<?= ($vlQueryInfo['no_of_breastfeeding_weeks']); ?>" title="<?= _translate('Number of weeks of breastfeeding'); ?>" placeholder="<?= _translate('Number of weeks of breastfeeding'); ?>">
										</div>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="box box-primary">
						<div class="box-header with-border">
							<h3 class="box-title"><?= _translate('Sample Information'); ?></h3>
						</div>
						<div class="box-body">
							<div class="row">
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for=""><?= _translate('Date of Sample Collection'); ?> <span class="mandatory">*</span></label>
										<input type="text" class="form-control " style="width:100%;" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" title="Please select sample collection date" value="<?php echo $vlQueryInfo['sample_collection_date']; ?>" <?php echo $disable; ?>>
									</div>
								</div>

								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="specimenType"><?= _translate('Sample Type'); ?> <span class="mandatory">*</span></label>
										<select name="specimenType" id="specimenType" class="form-control " title="<?= _translate('Please choose sample type'); ?>" <?php echo $disable; ?>>
											<option value=""> <?= _translate('-- Select --'); ?> </option>
											<?php
											foreach ($sResult as $name) { ?>
												<option value="<?php echo $name['sample_id']; ?>" <?php echo ($vlQueryInfo['specimen_type'] == $name['sample_id']) ? "selected='selected'" : "" ?>><?= _translate($name['sample_name']); ?></option>
											<?php
											} ?>
										</select>
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-group">
										<label for="reqClinician" class=""><?= _translate('Requesting Clinician Name'); ?></label>

										<input type="text" class="form-control" id="reqClinician" name="reqClinician" value="<?= $vlQueryInfo['request_clinician_name']; ?>" placeholder="<?= _translate('Requesting Clinician name'); ?>" title="<?= _translate('Please enter request clinician'); ?>" <?php echo $disable; ?> />
									</div>
								</div>
								<div class="col-md-3">
									<div class="form-group">
										<label for="reqClinicianPhoneNumber" class=""><?= _translate('Contact Number'); ?> </label>
										<input type="text" class="form-control forceNumeric" id="reqClinicianPhoneNumber" value="<?= $vlQueryInfo['request_clinician_phone_number']; ?>" name="reqClinicianPhoneNumber" maxlength="15" placeholder="<?= _translate('Phone Number'); ?>" title="<?= _translate('Please enter request clinician phone number'); ?>" <?php echo $disable; ?> />
									</div>
								</div>
							</div>
						</div>
						<div class="box box-primary">
							<div class="box-header with-border">
								<h3 class="box-title"><?= _translate('Treatment Information'); ?></h3>
							</div>
							<div class="box-body">
								<div class="row">
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for=""><?= _translate('Treatment Start Date'); ?></label>
											<input type="text" class="form-control date" name="dateOfArtInitiation" id="dateOfArtInitiation" placeholder="<?= _translate('Treatment Start Date'); ?>" title="<?= _translate('Treatment Start Date'); ?>" value="<?php echo $vlQueryInfo['treatment_initiated_date']; ?>" <?php echo $disable; ?> style="width:100%;">
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="lineOfTreatment" class="labels"><?= _translate('Line of Treatment'); ?> </label>
											<select class="form-control" name="lineOfTreatment" id="lineOfTreatment" title="<?= _translate('Line Of Treatment'); ?>" <?php echo $disable; ?>>
												<option value=""><?= _translate('--Select--'); ?></option>
												<option value="1" <?php echo ($vlQueryInfo['line_of_treatment'] == '1') ? "selected='selected' " : "" ?>><?= _translate('1st Line'); ?></option>
												<option value="2" <?php echo ($vlQueryInfo['line_of_treatment'] == '2') ? "selected='selected' " : "" ?>><?= _translate('2nd Line'); ?></option>
												<option value="3" <?php echo ($vlQueryInfo['line_of_treatment'] == '3') ? "selected='selected' " : "" ?>><?= _translate('3rd Line'); ?></option>
												<option value="n/a" <?php echo ($vlQueryInfo['line_of_treatment'] == 'n/a') ? "selected='selected' " : "" ?>><?= _translate('N/A'); ?></option>
											</select>
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for=""> <?= _translate('Current ARV Protocol'); ?></label>
											<select class="form-control <?php echo ($general->isSTSInstance()) ? "isRequired" : ''; ?>" id="artRegimen" name="artRegimen" title="<?= _translate('Please choose ART Regimen'); ?>" <?php echo $disable; ?> style="width:100%;" onchange="checkARTValue();">
												<option value=""><?= _translate('-- Select --'); ?></option>
												<?php foreach ($artRegimenResult as $heading) { ?>
													<optgroup label="<?= $heading['headings']; ?>">
														<?php
														foreach ($aResult as $regimen) {
															if ($heading['headings'] == $regimen['headings']) {
														?>
																<option value="<?php echo $regimen['art_code']; ?>" <?php echo $disable; ?> <?php echo ($vlQueryInfo['current_regimen'] == $regimen['art_code']) ? "selected='selected'" : "" ?>><?php echo $regimen['art_code']; ?></option>
														<?php
															}
														}
														?>
													</optgroup>
												<?php } ?>
												<option value="other"><?= _translate('Other'); ?></option>
											</select>
										</div>
									</div>

								</div>

							</div>
							<div class="box box-primary">
								<div class="box-header with-border">
									<h3 class="box-title"><?= _translate('Reason of Request of the Viral Load'); ?> <span class="mandatory">*</span></h3><small> <?= _translate('(Please pick one): (To be completed by clinician)'); ?></small>
								</div>
								<div class="box-body">
									<?php if (isset($testReasonsResult) && $testReasonsResult !== []) {
										foreach ($testReasonsResult as $key => $title) { ?>
											<div class="row">
												<div class="col-md-6">
													<div class="form-group">
														<div class="col-lg-12">
															<label class="radio-inline">
																<input type="radio" <?php echo ($vlQueryInfo['reason_for_vl_testing'] == $key || (isset($subTestReasons[$key]) && in_array($vlQueryInfo['reason_for_vl_testing'], array_keys($subTestReasons[$key])))) ? "checked='checked'" : ""; ?> class="isRequired" id="rmTesting<?php echo $key; ?>" name="reasonForVLTesting" value="<?php echo $key; ?>" title="<?= _translate('Please check viral load indication testing type'); ?>" onclick="showTesting('rmTesting<?php echo $key; ?>', <?php echo $key; ?>);">
																<strong><?= _translate($title); ?></strong>
															</label>
														</div>
													</div>
												</div>
											</div>
											<?php if ($key == 5) { ?>
												<div class="row rmTesting5 well" style="display:<?php echo (isset($vlQueryInfo['reason_for_vl_testing_other']) && !empty($vlQueryInfo['reason_for_vl_testing_other'])) ? "block" : "none"; ?>;">
													<div class="col-md-6">
														<label class="col-lg-5 control-label"><?= _translate('Please specify other reasons'); ?></label>
														<div class="col-lg-7">
															<input type="text" value="<?php echo $vlQueryInfo['reason_for_vl_testing_other'] ?? null; ?>" class="form-control reasonfortest" id="newreasonForVLTesting" name="newreasonForVLTesting" placeholder="<?= _translate('Please specify other test reason') ?>" title="<?= _translate('Please specify other test reason') ?>" />
														</div>
													</div>
												</div>
											<?php } ?>
											<?php if (isset($subTestReasons[$key]) && (isset($subTestReasons[$key]) && $subTestReasons[$key] !== [])) { ?>
												<div class="row rmTesting<?php echo $key; ?> hideTestData well" style="display:<?php echo ($vlQueryInfo['reason_for_vl_testing'] == $key || in_array($vlQueryInfo['reason_for_vl_testing'], array_keys($subTestReasons[$key]))) ? "block" : "none"; ?>;">
													<div class="col-md-6">
														<label class="col-lg-5 control-label"><?= _translate('Choose reason for testing'); ?></label>
														<div class="col-lg-7">
															<select name="controlVlTestingType[<?php echo $key; ?>]" id="controlVlType" class="form-control reasonfortest" title="<?= _translate('Please choose a reason for VL testing'); ?>" onchange="checkreasonForVLTesting();">
																<option value=""> <?= _translate("-- Select --"); ?> </option>
																<?php foreach ($subTestReasons[$key] as $testReasonId => $row) { ?>
																	<option value="<?php echo $testReasonId; ?>" <?php echo ($vlQueryInfo['reason_for_vl_testing'] == $testReasonId) ? "selected='selected'" : ""; ?>><?php echo _translate($row); ?></option>
																<?php } ?>
															</select>
														</div>
													</div>
												</div>
									<?php }
										}
									} ?>
									<hr>

								</div>
							</div>
							<?php if (_isAllowed('/vl/results/vlTestResult.php') && $_SESSION['accessType'] != 'collection-site') { ?>
								<form class="form-inline" method="post" name="vlRequestFormSudan" id="vlRequestFormSudan" autocomplete="off" action="updateVlTestResultHelper.php">
									<div class="box box-primary" style="<?php if ($_SESSION['accessType'] == 'collection-site') { ?> pointer-events:none;<?php } ?>">
										<div class="box-header with-border">
											<h3 class="box-title"><?= _translate('Laboratory Information'); ?></h3>
										</div>
										<div class="box-body">
											<div class="row">
												<div class="col-md-6">
													<label for="cvNumber" class="col-lg-5 control-label"><?= _translate('CV Number'); ?> </label>
													<div class="col-lg-7">
														<input name="cvNumber" id="cvNumber" class="form-control" placeholder="<?= _translate('Enter CV Number'); ?>" title="<?= _translate('Please enter CV Number'); ?>" value="<?= $vlQueryInfo['cv_number']; ?>" <?php echo $labFieldDisabled; ?>>
													</div>
												</div>

												<div class="col-md-6">
													<label for="testingPlatform" class="col-lg-5 control-label"><?= _translate('VL Testing Platform'); ?><span class="mandatory">*</span> </label>
													<div class="col-lg-7">
														<select name="testingPlatform" id="testingPlatform" class="form-control" title="<?= _translate('Please choose VL Testing Platform'); ?>" <?php echo $labFieldDisabled; ?> onchange="hivDetectionChange();">
															<option value=""><?= _translate('-- Select --'); ?></option>
															<?php foreach ($importResult as $mName) { ?>
																<option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] . '##' . $mName['instrument_id']; ?>" <?php echo ($vlQueryInfo['vl_test_platform'] == $mName['machine_name']) ? 'selected="selected"' : ''; ?>><?php echo $mName['machine_name']; ?></option>
															<?php } ?>
														</select>
													</div>
												</div>

											</div>
											<div class="row">
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="sampleReceivedDate"><?= _translate('Date Sample Received at Testing Lab'); ?><span class="mandatory">*</span> </label>
													<div class="col-lg-7">
														<input type="text" class="form-control dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _translate('Sample Received Date'); ?>" value="<?php echo $vlQueryInfo['sample_received_at_lab_datetime']; ?>" title="<?= _translate('Please select sample received date'); ?>" <?php echo $labFieldDisabled; ?> />
													</div>
												</div>
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="sampleTestingDateAtLab"><?= _translate('Sample Testing Date'); ?> <span class="mandatory">*</span></label>
													<div class="col-lg-7">
														<input type="text" class="form-control dateTime" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="<?= _translate('Sample Testing Date'); ?>" value="<?php echo $vlQueryInfo['sample_tested_datetime']; ?>" title="<?= _translate('Please select sample testing date'); ?>" <?php echo $labFieldDisabled; ?> onchange="checkSampleTestingDate();" />
													</div>
												</div>

											</div>
											<div class="row">
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="isSampleRejected"><?= _translate('Is Sample Rejected?'); ?><span class="mandatory">*</span> </label>
													<div class="col-lg-7">
														<select name="isSampleRejected" id="isSampleRejected" class="form-control" title="<?= _translate('Please check if sample is rejected or not'); ?>">
															<option value=""><?= _translate('-- Select --'); ?></option>
															<option value="yes" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'selected="selected"' : ''; ?>><?= _translate('Yes'); ?></option>
															<option value="no" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'no') ? 'selected="selected"' : ''; ?>><?= _translate('No'); ?></option>
														</select>
													</div>
												</div>
											</div>
											<div class="row rejectionReason" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;">
												<div class="col-md-6 rejectionReason" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;">
													<label class="col-lg-5 control-label" for="rejectionReason"><?= _translate('Rejection Reason'); ?> <span class="mandatory">*</span></label>
													<div class="col-lg-7">
														<select name="rejectionReason" id="rejectionReason" class="form-control" title="<?= _translate('Please choose reason'); ?>" <?php echo $labFieldDisabled; ?> onchange="checkRejectionReason();">
															<option value=""><?= _translate('-- Select --'); ?></option>
															<?php foreach ($rejectionTypeResult as $type) { ?>
																<optgroup label="<?php echo strtoupper((string) $type['rejection_type']); ?>">
																	<?php foreach ($rejectionResult as $reject) {
																		if ($type['rejection_type'] == $reject['rejection_type']) {
																	?>
																			<option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($vlQueryInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>> <?= $reject['rejection_reason_name']; ?></option>
																	<?php }
																	} ?>
																</optgroup>
															<?php } ?>

														</select>
													</div>
												</div>
												<div class="col-md-6 rejectionReason" style="display:none;">
													<label class="col-lg-5 control-label labels" for="rejectionDate"><?= _translate('Rejection Date'); ?> </label>
													<div class="col-lg-7">
														<input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="<?= _translate('Select Rejection Date'); ?>" title="<?= _translate('Please select rejection date'); ?>" />
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-md-6 vlResult">
													<label class="col-lg-5 control-label" for="vlResult"><?= _translate('Viral Load Result (copies/mL)'); ?> </label>
													<div class="col-lg-7 resultInputContainer">
														<input list="possibleVlResults" autocomplete="off" class="form-control" id="vlResult" name="vlResult" placeholder="<?= _translate('Viral Load Result'); ?>" title="<?= _translate('Please enter viral load result'); ?>" value="<?= ($vlQueryInfo['result']); ?>" <?php echo $labFieldDisabled; ?> style="width:100%;" onchange="calculateLogValue(this)" disabled />
														<datalist id="possibleVlResults">

														</datalist>
													</div>
												</div>
												<div class="col-md-6 vlResult" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'none' : 'block'; ?>;">
													<label class="col-lg-5 control-label" for="vlLog"><?= _translate('Viral Load (Log)'); ?> </label>
													<div class="col-lg-7">
														<input type="text" class="form-control" id="vlLog" name="vlLog" placeholder="<?= _translate('Viral Load Log'); ?>" title="<?= _translate('Please enter viral load log'); ?>" <?php echo $labFieldDisabled; ?> style="width:100%;" onchange="calculateLogValue(this);" />
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="reviewedOn"><?= _translate('Reviewed On'); ?> <span class="mandatory">*</span></label>
													<div class="col-lg-7">
														<input type="text" name="reviewedOn" id="reviewedOn" value="<?php echo $vlQueryInfo['result_reviewed_datetime']; ?>" class="dateTime form-control" placeholder="<?= _translate('Reviewed on'); ?>" title="<?= _translate('Please enter the Reviewed on'); ?>" />
													</div>
												</div>
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="reviewedBy"><?= _translate('Reviewed By'); ?> <span class="mandatory">*</span></label>
													<div class="col-lg-7">
														<select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="<?= _translate('Please choose reviewed by'); ?>" style="width: 100%;">
															<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_reviewed_by'], '<?= _translate("-- Select --"); ?>'); ?>
														</select>
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="approvedOnDateTime"><?= _translate('Approved On'); ?> <span class="mandatory">*</span></label>
													<div class="col-lg-7">
														<input type="text" name="approvedOnDateTime" id="approvedOnDateTime" value="<?php echo $vlQueryInfo['result_approved_datetime']; ?>" class="dateTime form-control" placeholder="Approved on" title="Please enter the approved on date" />
													</div>
												</div>
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="approvedBy"><?= _translate('Approved By'); ?><span class="mandatory">*</span> </label>
													<div class="col-lg-7">
														<select name="approvedBy" id="approvedBy" class="form-control isRequired" title="Please choose approved by" <?php echo $labFieldDisabled; ?>>
															<option value=""><?= _translate('-- Select --'); ?></option>
															<?php foreach ($userResult as $uName) { ?>
																<option value="<?php echo $uName['user_id']; ?>" <?php echo ($vlQueryInfo['result_approved_by'] == $uName['user_id']) ? "selected=selected" : ""; ?>><?php echo ($uName['user_name']); ?></option>
															<?php } ?>
														</select>
													</div>
												</div>
											</div>
											<div class="row">

												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="resultDispatchedOn"><?= _translate('Date Results Dispatched'); ?><span class="mandatory">*</span> </label>
													<div class="col-lg-7">
														<input type="text" class="form-control dateTime" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="<?= _translate('Result Dispatched Date'); ?>" value="<?php echo $vlQueryInfo['result_dispatched_datetime']; ?>" title="<?= _translate('Please select result dispatched date'); ?>" <?php echo $labFieldDisabled; ?> />
													</div>
												</div>
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="testedBy"><?= _translate('Tested By'); ?> <span class="mandatory">*</span> </label>
													<div class="col-lg-7">
														<select name="testedBy" id="testedBy" class="select2 form-control isRequired" title="Please choose tested by" style="width: 100%;">
															<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['tested_by'], '-- Select --'); ?>
														</select>
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="labComments"><?= _translate('Lab Tech. Comments'); ?> </label>
													<div class="col-lg-7">
														<textarea class="form-control" name="labComments" id="labComments" placeholder="<?= _translate('Lab comments'); ?>" <?php echo $labFieldDisabled; ?>><?php echo trim((string) $vlQueryInfo['lab_tech_comments']); ?></textarea>
													</div>
												</div>
											</div>
										</div>
									</div>
						</div>
						<div class="box-footer">
							<input type="hidden" name="revised" id="revised" value="no" />
							<input type="hidden" name="vlSampleId" id="vlSampleId" value="<?= ($vlQueryInfo['vl_sample_id']); ?>" />
							<input type="hidden" name="reasonForResultChangesHistory" id="reasonForResultChangesHistory" value="<?php echo base64_encode((string) $vlQueryInfo['reason_for_result_changes']); ?>" />
							<input type="hidden" name="sampleCode" id="sampleCode" value="<?= ($vlQueryInfo['sample_code']); ?>" />
							<input type="hidden" name="artNo" id="artNo" value="<?= ($vlQueryInfo['patient_art_no']); ?>" />
							<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?= _translate('Save'); ?></a>&nbsp;
							<a href="vlTestResult.php" class="btn btn-default"> <?= _translate('Cancel'); ?></a>
						</div>

						</form>
					<?php } ?>
					</div>
				</div>
			</div>
		</div>
</div>
</section>
</div>
<script type="text/javascript" src="/assets/js/datalist-css.min.js?v=<?= filemtime(WEB_ROOT . "/assets/js/datalist-css.min.js") ?>"></script>
<script type="text/javascript">
	let __clone = null;
	let reason = null;
	let resultValue = null;

	$(document).ready(function() {

		autoFillFocalDetails();
		$('#labId').select2({
			width: '100%',
			placeholder: "Select Testing Lab"
		});
		$('#reviewedBy').select2({
			width: '100%',
			placeholder: "Select Reviewed By"
		});
		$('#testedBy').select2({
			width: '100%',
			placeholder: "Select Tested By"
		});

		$('#approvedBy').select2({
			width: '100%',
			placeholder: "Select Approved By"
		});
		$('#testingPlatform').select2({
			placeholder: "Select Testing Platform"
		});
		$('#sampleReceivedDate,#sampleTestingDateAtLab,#resultDispatchedOn').datetimepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
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

		$('#sampleReceivedDate,#sampleTestingDateAtLab,#resultDispatchedOn').mask('<?= $_SESSION['jsDateFormatMask'] ?? '99-aaa-9999'; ?> 99:99');

		//$("#hivDetection, #isSampleRejected").trigger('change');

		setTimeout(function() {
			$("#vlResult").trigger('change');
			$("#hivDetection, #isSampleRejected").trigger('change');
			// just triggering sample collection date is enough,
			// it will automatically do everything that labId and facilityId changes will do
			$("#sampleCollectionDate").trigger('change');
			__clone = $(".labSection").clone();
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
		$('.reasonfortest').attr('disabled', true);

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
			$('#hivDetection').removeClass('isRequired');
			//$('#approvedBy').addClass('isRequired');
			//$('#approvedOnDateTime').addClass('isRequired');
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
			//$('#approvedBy').addClass('isRequired');
			//$('#approvedOnDateTime').addClass('isRequired');
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
			$('#approvedOnDateTime').removeClass('isRequired');
			//$(".hivDetection").trigger("change");
		}
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
		$('#vlResult, #isSampleRejected').addClass('isRequired');
		//$("#isSampleRejected").val("");
		$("#isSampleRejected").trigger("change");
		hivDetectionChange();
	});

	function hivDetectionChange() {

		var text = $('#testingPlatform').val();
		if (!text) return;
		var str1 = text.split("##");
		var str = str1[0];
		if ((text == 'GeneXpert' || str.toLowerCase() == 'genexpert') && $('#isSampleRejected').val() != 'yes') {
			$('.hivDetection').prop('disabled', false);
			$('.hivDetection').show();
			$('#hivDetection').addClass('isRequired');
		} else {
			$('.hivDetection').hide();
			$("#hivDetection").val("");
			$('#hivDetection').removeClass('isRequired');
		}

		//Get VL results by platform id
		var platformId = str1[3];
		$("#possibleVlResults").html('');
		$.post("/vl/requests/getVlResults.php", {
				instrumentId: platformId,
			},
			function(data) {
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
		if ($('#isSampleRejected').val() == "yes") {
			$('.vlResult, #vlResult').removeClass('isRequired');
		}
		if ($('#failed').prop('checked')) {
			$('#vlResult').removeClass('isRequired');
		}
		flag = deforayValidator.init({
			formId: 'vlRequestFormSudan'
		});

		$('.isRequired').each(function() {
			($(this).val() == '') ? $(this).css('background-color', '#FFFF99'): $(this).css('background-color', '#FFFFFF')
		});
		if (flag) {
			$.blockUI();
			document.getElementById('vlRequestFormSudan').submit();
		}
	}

	function autoFillFocalDetails() {
		var labId = $("#labId").val();
		if ($.trim(labId) != '') {
			$("#vlFocalPerson").val($('#labId option:selected').attr('data-focalperson'));
			$("#vlFocalPersonPhoneNumber").val($('#labId option:selected').attr('data-focalphone'));
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
	$('#vlRequestFormSudan').keypress((e) => {
		// Enter key corresponds to number 13
		if (e.which === 13) {
			e.preventDefault();
			//console.log('form submitted');
			validateNow() // Trigger the validateNow function
		}
	});
	// Handle Enter key specifically for select2 elements
	$(document).on('keydown', '.select2-container--open', function(e) {
		if (e.which === 13) {
			e.preventDefault(); // Prevent the default form submission
			validateNow() // Trigger the validateNow function
		}
	});
</script>