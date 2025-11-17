<?php

use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

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
	$facilityQuery = "SELECT * from facility_details where `status`='active' AND facility_type='2' Order By facility_name";
	$lResult = $db->query($facilityQuery);
}

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

	if (!empty($vlQueryInfo['result'])) {
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
		<h1><em class="fa-solid fa-pen-to-square"></em> VIRAL LOAD LABORATORY REQUEST FORM </h1>
		<ol class="breadcrumb">
			<li><a href="/dashboard/index.php"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Enter VL Result</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">

		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?= _translate("indicates required fields"); ?> &nbsp;</div>
			</div>
			<div class="box-body">
				<!-- form start -->

				<div class="box-body">
					<div class="box box-primary">
						<div class="box-header with-border">
							<h3 class="box-title">Clinic Information: (To be filled by requesting Clinican/Nurse)</h3>
						</div>
						<div class="box-body">
							<div class="row">
								<div class="col-xs-4 col-md-4">
									<div class="form-group">
										<label for="sampleCode">Sample ID <span class="mandatory">*</span></label>
										<input type="text" class="form-control " id="sampleCode" name="sampleCode" placeholder="Enter Sample ID" title="<?= _translate("Please make sure you have selected Sample Collection Date and Requesting Facility"); ?>" value="<?= ($vlQueryInfo['sample_code']); ?>" <?php echo $disable; ?> style="width:100%;" />
									</div>
								</div>
								<div class="col-xs-4 col-md-4">
									<div class="form-group">
										<label for="sampleReordered">
											<input type="checkbox" class="" id="sampleReordered" name="sampleReordered" value="yes" <?php echo (trim((string) $vlQueryInfo['sample_reordered']) === 'yes') ? 'checked="checked"' : '' ?> <?php echo $disable; ?> title="Please indicate if this is a reordered sample"> Sample Reordered
										</label>
									</div>
								</div>

								<div class="col-xs-4 col-md-4">
									<div class="form-group">
										<label for="communitySample">Community Sample</label>
										<select class="form-control" name="communitySample" id="communitySample" title="Please choose if this is a community sample" style="width:100%;">
											<option value=""> -- Select -- </option>
											<option value="yes" <?php echo (isset($vlQueryInfo['community_sample']) && $vlQueryInfo['community_sample'] == 'yes') ? 'selected="selected"' : ''; ?>>Yes</option>
											<option value="no" <?php echo (isset($vlQueryInfo['community_sample']) && $vlQueryInfo['community_sample'] == 'no') ? 'selected="selected"' : ''; ?>>No</option>
										</select>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="province">State <span class="mandatory">*</span></label>
										<select class="form-control " name="province" id="province" title="Please choose state" <?php echo $disable; ?> style="width:100%;" onchange="getfacilityDetails(this);">
											<option value=""> -- Select -- </option>
											<?php foreach ($pdResult as $provinceName) { ?>
												<option value="<?php echo $provinceName['geo_name'] . "##" . $provinceName['geo_code']; ?>" <?php echo ($facilityResult[0]['facility_state'] . "##" . $stateResult[0]['geo_code'] === $provinceName['geo_name'] . "##" . $provinceName['geo_code']) ? "selected='selected'" : "" ?>><?php echo ($provinceName['geo_name']); ?></option>;
											<?php } ?>
										</select>
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="district">County <span class="mandatory">*</span></label>
										<select class="form-control" name="district" id="district" title="Please choose county" <?php echo $disable; ?> style="width:100%;" onchange="getfacilityDistrictwise(this);">
											<option value=""> -- Select -- </option>
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
										<label for="facilityId">Clinic/Health Center <span class="mandatory">*</span></label>
										<select class="form-control " id="facilityId" name="facilityId" title="Please select clinic/health center name" <?php echo $disable; ?> style="width:100%;" onchange="autoFillFacilityCode();">
											<?= $facility; ?>
										</select>
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="facilityCode">Clinic/Health Center Code </label>
										<input type="text" class="form-control" style="width:100%;" name="facilityCode" id="facilityCode" placeholder="Clinic/Health Center Code" title="Please enter clinic/health center code" value="<?php echo $facilityResult[0]['facility_code']; ?>" <?php echo $disable; ?>>
									</div>
								</div>
							</div>
							<div class="row facilityDetails" style="display:<?php echo (trim((string) $facilityResult[0]['facility_emails']) !== '' || trim((string) $facilityResult[0]['facility_mobile_numbers']) !== '' || trim((string) $facilityResult[0]['contact_person']) !== '') ? '' : 'none'; ?>;">
								<div class="col-xs-2 col-md-2 femails" style="display:<?php echo (trim((string) $facilityResult[0]['facility_emails']) !== '') ? '' : 'none'; ?>;">
									<strong>Clinic/Health Center Email(s)</strong>
								</div>
								<div class="col-xs-2 col-md-2 femails facilityEmails" style="display:<?php echo (trim((string) $facilityResult[0]['facility_emails']) !== '') ? '' : 'none'; ?>;">
									<?php echo $facilityResult[0]['facility_emails']; ?></div>
								<div class="col-xs-2 col-md-2 fmobileNumbers" style="display:<?php echo (trim((string) $facilityResult[0]['facility_mobile_numbers']) !== '') ? '' : 'none'; ?>;">
									<strong>Clinic/Health Center Mobile No.(s)</strong>
								</div>
								<div class="col-xs-2 col-md-2 fmobileNumbers facilityMobileNumbers" style="display:<?php echo (trim((string) $facilityResult[0]['facility_mobile_numbers']) !== '') ? '' : 'none'; ?>;">
									<?php echo $facilityResult[0]['facility_mobile_numbers']; ?></div>
								<div class="col-xs-2 col-md-2 fContactPerson" style="display:<?php echo (trim((string) $facilityResult[0]['contact_person']) !== '') ? '' : 'none'; ?>;">
									<strong>Clinic Contact Person -</strong>
								</div>
								<div class="col-xs-2 col-md-2 fContactPerson facilityContactPerson" style="display:<?php echo (trim((string) $user) !== '') ? '' : 'none'; ?>;">
									<?php echo ($user); ?></div>
							</div>
						</div>

						<div class="row">
							<div class="col-xs-3 col-md-3">
								<div class="form-group">
									<label for="implementingPartner">Implementing Partner</label>
									<select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose implementing partner" style="width:100%;" <?php echo $disable; ?>>
										<option value=""> -- Select -- </option>
										<?php
										foreach ($implementingPartnerList as $implementingPartner) {
										?>
											<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>" <?php echo ($implementingPartner['i_partner_id'] == $vlQueryInfo['implementing_partner']) ? 'selected="selected"' : ''; ?>><?= $implementingPartner['i_partner_name']; ?></option>
										<?php } ?>
									</select>
								</div>
							</div>
							<div class="col-xs-3 col-md-3">
								<div class="form-group">
									<label for="fundingSource">Funding Source</label>
									<select class="form-control" name="fundingSource" id="fundingSource" title="Please choose implementing partner" style="width:100%;" <?php echo $disable; ?>>
										<option value=""> -- Select -- </option>
										<?php
										foreach ($fundingSourceList as $fundingSource) {
										?>
											<option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>" <?php echo ($fundingSource['funding_source_id'] == $vlQueryInfo['funding_source']) ? 'selected="selected"' : ''; ?>><?= $fundingSource['funding_source_name']; ?>
											</option>
										<?php } ?>
									</select>
								</div>
							</div>
						</div>
					</div>
					<div class="box box-primary">
						<div class="box-header with-border">
							<h3 class="box-title">Patient Information</h3>
						</div>
						<div class="box-body">
							<div class="row">
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="artNo">ART (TRACNET) No. <span class="mandatory">*</span></label>
										<input type="text" name="artNo" id="artNo" class="form-control " placeholder="Enter ART Number" title="Enter art number" value="<?= ($vlQueryInfo['patient_art_no']); ?>" <?php echo $disable; ?> />
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="dob">Date of Birth </label>
										<input type="text" name="dob" id="dob" class="form-control date" placeholder="Enter DOB" title="Enter dob" value="<?= ($vlQueryInfo['patient_dob']); ?>" <?php echo $disable; ?> />
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="ageInYears">If DOB unknown, Age in Year </label>
										<input type="text" name="ageInYears" id="ageInYears" class="form-control forceNumeric" maxlength="2" placeholder="Age in Year" title="Enter age in years" <?php echo $disable; ?> value="<?= ($vlQueryInfo['patient_age_in_years']); ?>" />
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="ageInMonths">If Age < 1, Age in Month </label> <input type="text" name="ageInMonths" id="ageInMonths" class="form-control forceNumeric" maxlength="2" placeholder="Age in Month" title="Enter age in months" <?php echo $disable; ?> value="<?= ($vlQueryInfo['patient_age_in_months']); ?>" />
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="patientFirstName">Patient Name (First Name, Last Name) </label>
										<input type="text" name="patientFirstName" id="patientFirstName" class="form-control" placeholder="Enter Patient Name" title="Enter patient name" <?php echo $disable; ?> value="<?php echo $patientFullName; ?>" />
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="gender"><?= _translate("Sex"); ?></label><br>
										<label class="radio-inline" style="margin-left:0px;">
											<input type="radio" class="" id="genderMale" name="gender" value="male" title="Please choose sex" <?php echo $disable; ?> <?php echo ($vlQueryInfo['patient_gender'] == 'male') ? "checked='checked'" : "" ?>>
											Male
										</label>
										<label class="radio-inline" style="margin-left:0px;">
											<input type="radio" class="" id="genderFemale" name="gender" value="female" title="Please choose sex" <?php echo $disable; ?> <?php echo ($vlQueryInfo['patient_gender'] == 'female') ? "checked='checked'" : "" ?>> Female
										</label>
										<label class="radio-inline" style="margin-left:0px;">
											<input type="radio" class="" id="genderUnreported" name="gender" value="unreported" title="Please choose sex" <?php echo $disable; ?> <?php echo ($vlQueryInfo['patient_gender'] == 'unreported') ? "checked='checked'" : "" ?>>Unreported
										</label>
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="receiveSms">Patient consent to receive SMS?</label><br>
										<label class="radio-inline" style="margin-left:0px;">
											<input type="radio" class="" id="receivesmsYes" name="receiveSms" value="yes" title="Patient consent to receive SMS" <?php echo $disable; ?> onclick="checkPatientReceivesms(this.value);" <?php echo ($vlQueryInfo['consent_to_receive_sms'] == 'yes') ? "checked='checked'" : "" ?>> Yes
										</label>
										<label class="radio-inline" style="margin-left:0px;">
											<input type="radio" class="" id="receivesmsNo" name="receiveSms" value="no" title="Patient consent to receive SMS" <?php echo $disable; ?> onclick="checkPatientReceivesms(this.value);" <?php echo ($vlQueryInfo['consent_to_receive_sms'] == 'no') ? "checked='checked'" : "" ?>> No
										</label>
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="patientPhoneNumber">Phone Number</label>
										<input type="text" name="patientPhoneNumber" id="patientPhoneNumber" class="form-control phone-number" maxlength="15" placeholder="Enter Phone Number" title="Enter phone number" value="<?= ($vlQueryInfo['patient_mobile_number']); ?>" <?php echo $disable; ?> />
									</div>
								</div>
							</div>
							<div class="row femaleSection" style="display:<?php echo ($vlQueryInfo['patient_gender'] == 'female' || $vlQueryInfo['patient_gender'] == '' || $vlQueryInfo['patient_gender'] == null) ? "" : "none" ?>">
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="patientPregnant">Is Patient Pregnant? </label><br>
										<label class="radio-inline">
											<input type="radio" class="" id="pregYes" name="patientPregnant" value="yes" title="Is Patient Pregnant?" <?php echo $disable; ?> <?php echo ($vlQueryInfo['is_patient_pregnant'] == 'yes') ? "checked='checked'" : "" ?>> Yes
										</label>
										<label class="radio-inline">
											<input type="radio" class="" id="pregNo" name="patientPregnant" value="no" <?php echo $disable; ?> <?php echo ($vlQueryInfo['is_patient_pregnant'] == 'no') ? "checked='checked'" : "" ?>> No
										</label>
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="breastfeeding">Is Patient Breastfeeding? </label><br>
										<label class="radio-inline">
											<input type="radio" class="" id="breastfeedingYes" name="breastfeeding" value="yes" title="Is Patient Breastfeeding?" <?php echo $disable; ?> <?php echo ($vlQueryInfo['is_patient_breastfeeding'] == 'yes') ? "checked='checked'" : "" ?>> Yes
										</label>
										<label class="radio-inline">
											<input type="radio" class="" id="breastfeedingNo" name="breastfeeding" value="no" <?php echo $disable; ?> <?php echo ($vlQueryInfo['is_patient_breastfeeding'] == 'no') ? "checked='checked'" : "" ?>> No
										</label>
									</div>
								</div>
							</div>
						</div>
						<div class="box box-primary">
							<div class="box-header with-border">
								<h3 class="box-title">Sample Information</h3>
							</div>
							<div class="box-body">
								<div class="row">
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="">Date of Sample Collection <span class="mandatory">*</span></label>
											<input type="text" class="form-control " style="width:100%;" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" title="Please select sample collection date" value="<?php echo $vlQueryInfo['sample_collection_date']; ?>" <?php echo $disable; ?>>
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="">Sample Dispatched On <span class="mandatory">*</span></label>
											<input type="text" class="form-control isRequired dateTime" style="width:100%;" name="sampleDispatchedDate" id="sampleDispatchedDate" placeholder="Sample Dispatched On" title="Please select sample dispatched on" value="<?php echo $vlQueryInfo['sample_dispatched_datetime']; ?>" <?php echo $disable; ?>>
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="specimenType">Sample Type <span class="mandatory">*</span></label>
											<select name="specimenType" id="specimenType" class="form-control " title="Please choose sample type" <?php echo $disable; ?>>
												<option value=""> -- Select -- </option>
												<?php
												foreach ($sResult as $name) {
												?>
													<option value="<?php echo $name['sample_id']; ?>" <?php echo ($vlQueryInfo['specimen_type'] == $name['sample_id']) ? "selected='selected'" : "" ?>><?= $name['sample_name']; ?></option>
												<?php
												}
												?>
											</select>
										</div>
									</div>
								</div>
							</div>
							<div class="box box-primary">
								<div class="box-header with-border">
									<h3 class="box-title">Treatment Information</h3>
								</div>
								<div class="box-body">
									<div class="row">
										<div class="col-xs-3 col-md-3">
											<div class="form-group">
												<label for="">Date of Treatment Initiation</label>
												<input type="text" class="form-control date" name="dateOfArtInitiation" id="dateOfArtInitiation" placeholder="Date Of Treatment Initiated" title="Date Of treatment initiated" value="<?php echo $vlQueryInfo['treatment_initiated_date']; ?>" <?php echo $disable; ?> style="width:100%;">
											</div>
										</div>
										<div class="col-xs-3 col-md-3">
											<div class="form-group">
												<label for="artRegimen">Current Regimen</label>
												<select class="form-control" id="artRegimen" name="artRegimen" title="Please choose ART Regimen" <?php echo $disable; ?> style="width:100%;" onchange="checkARTValue();">
													<option value="">-- Select --</option>
													<?php foreach ($artRegimenResult as $heading) { ?>
														<optgroup label="<?= $heading['headings']; ?>">
															<?php
															foreach ($aResult as $regimen) {
																if ($heading['headings'] == $regimen['headings']) {
															?>
																	<option value="<?php echo $regimen['art_code']; ?>" <?php echo ($vlQueryInfo['current_regimen'] == $regimen['art_code']) ? "selected='selected'" : "" ?>><?php echo $regimen['art_code']; ?></option>
															<?php
																}
															}
															?>
														</optgroup>
													<?php } ?>
												</select>
												<input type="text" class="form-control newArtRegimen" name="newArtRegimen" id="newArtRegimen" placeholder="ART Regimen" title="Please enter art regimen" <?php echo $disable; ?> style="width:100%;display:none;margin-top:2px;">
											</div>
										</div>
										<div class="col-xs-3 col-md-3">
											<div class="form-group">
												<label for="">Date of Initiation of Current Regimen </label>
												<input type="text" class="form-control date" style="width:100%;" name="regimenInitiatedOn" id="regimenInitiatedOn" placeholder="Current Regimen Initiated On" title="Please enter current regimen initiated on" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['date_of_initiation_of_current_regimen']; ?>">
											</div>
										</div>
										<div class="col-xs-3 col-md-3">
											<div class="form-group">
												<label for="arvAdherence">ARV Adherence </label>
												<select name="arvAdherence" id="arvAdherence" class="form-control" title="Please choose adherence" <?php echo $disable; ?>>
													<option value=""> -- Select -- </option>
													<option value="good" <?php echo ($vlQueryInfo['arv_adherance_percentage'] == 'good') ? "selected='selected'" : "" ?>>Good >= 95%</option>
													<option value="fair" <?php echo ($vlQueryInfo['arv_adherance_percentage'] == 'fair') ? "selected='selected'" : "" ?>>Fair (85-94%)</option>
													<option value="poor" <?php echo ($vlQueryInfo['arv_adherance_percentage'] == 'poor') ? "selected='selected'" : "" ?>>Poor < 85%</option>
												</select>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-xs-3 col-md-3" style="display:none;">
											<div class="form-group">
												<label for="">How long has this patient been on treatment ? </label>
												<input type="text" class="form-control" id="treatPeriod" name="treatPeriod" placeholder="Enter Treatment Period" <?php echo $disable; ?> title="Please enter how long has this patient been on treatment" value="<?= ($vlQueryInfo['treatment_initiation']); ?>" />
											</div>
										</div>
									</div>
								</div>
								<div class="box box-primary">
									<div class="box-header with-border">
										<h3 class="box-title">Indication for Viral Load Testing</h3><small> (Please tick
											one):(To be completed by clinician)</small>
									</div>
									<div class="box-body">
										<div class="row">
											<div class="col-md-6">
												<div class="form-group">
													<div class="col-lg-12">
														<label class="radio-inline">
															<?php
															$vlTestReasonQueryRow = "SELECT * from r_vl_test_reasons where test_reason_id='" . trim((string) $vlQueryInfo['reason_for_vl_testing']) . "' OR test_reason_name = '" . trim((string) $vlQueryInfo['reason_for_vl_testing']) . "'";
															$vlTestReasonResultRow = $db->query($vlTestReasonQueryRow);
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
															<input type="radio" class="" id="rmTesting" name="reasonForVLTesting" value="routine" title="Please check routine monitoring" <?php echo $disable; ?> <?php echo $checked; ?> onclick="showTesting('rmTesting');">
															<strong>Routine Monitoring</strong>
														</label>
													</div>
												</div>
											</div>
										</div>
										<div class="row rmTesting hideTestData" style="display:<?php echo $display; ?>;">
											<div class="col-md-6">
												<label class="col-lg-5 control-label">Date of Last VL Test</label>
												<div class="col-lg-7">
													<input type="text" class="form-control date viralTestData" id="rmTestingLastVLDate" name="rmTestingLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" value="<?php echo (trim((string) $vlQueryInfo['last_vl_date_routine']) !== '' && $vlQueryInfo['last_vl_date_routine'] != null && $vlQueryInfo['last_vl_date_routine'] != '0000-00-00') ? DateUtility::humanReadableDateFormat($vlQueryInfo['last_vl_date_routine']) : ''; ?>" <?php echo $disable; ?> />
												</div>
											</div>
											<div class="col-md-6">
												<label for="rmTestingVlValue" class="col-lg-3 control-label">VL
													Result</label>
												<div class="col-lg-7">
													<input type="text" class="form-control forceNumeric viralTestData" id="rmTestingVlValue" name="rmTestingVlValue" placeholder="Enter VL Result" title="Please enter VL Result" value="<?php echo $vlQueryInfo['last_vl_result_routine']; ?>" <?php echo $disable; ?> />
													(copies/mL)
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
															if (trim((string) $vlQueryInfo['reason_for_vl_testing']) === 'failure') {
																$checked = 'checked="checked"';
																$display = 'block';
															} else {
																$checked = '';
																$display = 'none';
															}
															?>
															<input type="radio" class="" id="repeatTesting" name="reasonForVLTesting" value="failure" title="Repeat VL test after suspected treatment failure adherence counseling" <?php echo $disable; ?> <?php echo $checked; ?> onclick="showTesting('repeatTesting');">
															<strong>Repeat VL test after suspected treatment failure
																adherence counselling </strong>
														</label>
													</div>
												</div>
											</div>
										</div>
										<div class="row repeatTesting hideTestData" style="display: <?php echo $display; ?>;">
											<div class="col-md-6">
												<label class="col-lg-5 control-label">Date of Last VL Test</label>
												<div class="col-lg-7">
													<input type="text" class="form-control date viralTestData" id="repeatTestingLastVLDate" name="repeatTestingLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" value="<?php echo (trim((string) $vlQueryInfo['last_vl_date_failure_ac']) !== '' && $vlQueryInfo['last_vl_date_failure_ac'] != null && $vlQueryInfo['last_vl_date_failure_ac'] != '0000-00-00') ? DateUtility::humanReadableDateFormat($vlQueryInfo['last_vl_date_failure_ac']) : ''; ?>" <?php echo $disable; ?> />
												</div>
											</div>
											<div class="col-md-6">
												<label for="repeatTestingVlValue" class="col-lg-3 control-label">VL
													Result</label>
												<div class="col-lg-7">
													<input type="text" class="form-control forceNumeric viralTestData" id="repeatTestingVlValue" name="repeatTestingVlValue" placeholder="Enter VL Result" title="Please enter VL Result" value="<?php echo $vlQueryInfo['last_vl_result_failure_ac']; ?>" <?php echo $disable; ?> />
													(copies/mL)
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
															if (trim((string) $vlQueryInfo['reason_for_vl_testing']) === 'suspect') {
																$checked = 'checked="checked"';
																$display = 'block';
															} else {
																$checked = '';
																$display = 'none';
															}
															?>
															<input type="radio" class="" id="suspendTreatment" name="reasonForVLTesting" value="suspect" title="Suspect Treatment Failure" <?php echo $disable; ?> <?php echo $checked; ?> onclick="showTesting('suspendTreatment');">
															<strong>Suspect Treatment Failure</strong>
														</label>
													</div>
												</div>
											</div>
										</div>
										<div class="row suspendTreatment hideTestData" style="display: <?php echo $display; ?>;">
											<div class="col-md-6">
												<label class="col-lg-5 control-label">Date of Last VL Test</label>
												<div class="col-lg-7">
													<input type="text" class="form-control date viralTestData" id="suspendTreatmentLastVLDate" name="suspendTreatmentLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" value="<?php echo (trim((string) $vlQueryInfo['last_vl_date_failure']) !== '' && $vlQueryInfo['last_vl_date_failure'] != null && $vlQueryInfo['last_vl_date_failure'] != '0000-00-00') ? DateUtility::humanReadableDateFormat($vlQueryInfo['last_vl_date_failure']) : ''; ?>" <?php echo $disable; ?> />
												</div>
											</div>
											<div class="col-md-6">
												<label for="suspendTreatmentVlValue" class="col-lg-3 control-label">VL
													Result</label>
												<div class="col-lg-7">
													<input type="text" class="form-control forceNumeric viralTestData" id="suspendTreatmentVlValue" name="suspendTreatmentVlValue" placeholder="Enter VL Result" title="Please enter VL Result" value="<?php echo $vlQueryInfo['last_vl_result_failure']; ?>" <?php echo $disable; ?> />
													(copies/mL)
												</div>
											</div>
										</div>
										<div class="row">
											<div class="col-md-4">
												<label for="reqClinician" class="col-lg-5 control-label">Request
													Clinician</label>
												<div class="col-lg-7">
													<input type="text" class="form-control" id="reqClinician" name="reqClinician" placeholder="Requesting Clinician" title="Please enter request clinician" value="<?php echo $vlQueryInfo['request_clinician_name']; ?>" <?php echo $disable; ?> />
												</div>
											</div>
											<div class="col-md-4">
												<label for="reqClinicianPhoneNumber" class="col-lg-5 control-label">Phone Number</label>
												<div class="col-lg-7">
													<input type="text" class="form-control phone-number" id="reqClinicianPhoneNumber" name="reqClinicianPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter request clinician phone number" value="<?php echo $vlQueryInfo['request_clinician_phone_number']; ?>" <?php echo $disable; ?> />
												</div>
											</div>
											<div class="col-md-4">
												<label class="col-lg-5 control-label" for="requestDate">Request Date
												</label>
												<div class="col-lg-7">
													<input type="text" class="form-control date" id="requestDate" name="requestDate" placeholder="Request Date" title="Please select request date" value="<?php echo $vlQueryInfo['test_requested_on']; ?>" <?php echo $disable; ?> />
												</div>
											</div>
										</div>
										<div class="row" style="display:none;">
											<div class="col-md-4">
												<label class="col-lg-5 control-label" for="emailHf">Email for HF
												</label>
												<div class="col-lg-7">
													<input type="text" class="form-control isEmail" id="emailHf" name="emailHf" placeholder="Email for HF" title="Please enter email for hf" value="<?php echo $facilityResult[0]['facility_emails']; ?>" <?php echo $disable; ?> />
												</div>
											</div>
										</div>
									</div>
								</div>
								<?php if (_isAllowed('/vl/results/vlTestResult.php') && $_SESSION['accessType'] != 'collection-site') { ?>
									<form class="form-inline" method="post" name="vlRequestFormSudan" id="vlRequestFormSudan" autocomplete="off" action="updateVlTestResultHelper.php">
										<div class="box box-primary" style="<?php if ($_SESSION['accessType'] == 'collection-site') { ?> pointer-events:none;<?php } ?>">
											<div class="box-header with-border">
												<h3 class="box-title">Laboratory Information</h3>
											</div>
											<div class="box-body">
												<div class="row">
													<div class="col-md-6">
														<label for="labId" class="col-lg-5 control-label">Lab Name<span class="mandatory">*</span> </label>
														<div class="col-lg-7">
															<select name="labId" id="labId" class="select2 isRequired form-control labSection" title="Please choose lab" onchange="autoFillFocalDetails();">
																<option value="">-- Select --</option>
																<?php foreach ($lResult as $labName) { ?>
																	<option data-focalperson="<?php echo $labName['contact_person']; ?>" data-focalphone="<?php echo $labName['facility_mobile_numbers']; ?>" value="<?php echo $labName['facility_id']; ?>" <?php echo (isset($vlQueryInfo['lab_id']) && $vlQueryInfo['lab_id'] == $labName['facility_id']) ? 'selected="selected"' : ''; ?>><?= $labName['facility_name']; ?></option>
																<?php } ?>
															</select>
														</div>
													</div>
													<div class="col-md-6">
														<label for="vlFocalPerson" class="col-lg-5 control-label">VL Focal
															Person </label>
														<div class="col-lg-7">
															<select class="form-control ajax-select2" id="vlFocalPerson" name="vlFocalPerson" title="Please enter VL Focal Person">
																<option value="<?= ($vlQueryInfo['vl_focal_person']); ?>" selected='selected'> <?= ($vlQueryInfo['vl_focal_person']); ?></option>
															</select>
														</div>
													</div>
												</div>
												<div class="row">
													<div class="col-md-6">
														<label for="vlFocalPersonPhoneNumber" class="col-lg-5 control-label">VL Focal Person Phone
															Number</label>
														<div class="col-lg-7">
															<input type="text" class="form-control phone-number labSection" id="vlFocalPersonPhoneNumber" name="vlFocalPersonPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter vl focal person phone number" value="<?= ($vlQueryInfo['vl_focal_person_phone_number']); ?>" />
														</div>
													</div>
													<div class="col-md-6">
														<label class="col-lg-5 control-label" for="sampleReceivedAtHubOn">Date Sample Received at Hub (PHL)
															<span class="mandatory">*</span></label>
														<div class="col-lg-7">
															<input type="text" class="form-control dateTime isRequired" id="sampleReceivedAtHubOn" name="sampleReceivedAtHubOn" placeholder="Sample Received at HUB Date" title="Please select sample received at HUB date" value="<?php echo $vlQueryInfo['sample_received_at_hub_datetime']; ?>" />
														</div>
													</div>
												</div>
												<div class="row">
													<div class="col-md-6">
														<label class="col-lg-5 control-label" for="sampleReceivedDate">Date
															Sample Received at Testing Lab <span class="mandatory">*</span></label>
														<div class="col-lg-7">
															<input type="text" class="form-control labSection isRequired" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="Sample Received Date" title="Please select sample received date" value="<?php echo $vlQueryInfo['sample_received_at_lab_datetime']; ?>" />
														</div>
													</div>
													<div class="col-md-6">
														<label for="testingPlatform" class="col-lg-5 control-label">VL
															Testing Platform <span class="mandatory">*</span> </label>
														<div class="col-lg-7">
															<select name="testingPlatform" id="testingPlatform" class="isRequired result-optional form-control labSection" title="Please choose the VL Testing Platform">
																<option value="">-- Select --</option>
																<?php foreach ($importResult as $mName) { ?>
																	<option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] . '##' . $mName['instrument_id']; ?>" <?php echo ($vlQueryInfo['vl_test_platform'] == $mName['machine_name']) ? 'selected="selected"' : ''; ?>><?php echo $mName['machine_name']; ?></option>
																<?php } ?>
															</select>
														</div>
													</div>
												</div>
												<div class="row">
													<div class="col-md-6">
														<label class="col-lg-5 control-label" for="isSampleRejected">Is Sample
															Rejected? <span class="mandatory">*</span> </label>
														<div class="col-lg-7">
															<select name="isSampleRejected" id="isSampleRejected" class="form-control labSection isRequired" title="Please check if sample is rejected or not">
																<option value="">-- Select --</option>
																<option value="yes" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'selected="selected"' : ''; ?>>Yes</option>
																<option value="no" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'no') ? 'selected="selected"' : ''; ?>>No</option>
															</select>
														</div>
													</div>
													<div class="col-md-6">
														<label class="col-lg-5 control-label" for="sampleTestingDateAtLab">Sample Testing Date<span class="mandatory">*</span> </label>
														<div class="col-lg-7">
															<input type="text" class="form-control result-fields labSection  <?php echo ($vlQueryInfo['is_sample_rejected'] == 'no') ? 'isRequired' : ''; ?>" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? ' disabled="disabled" ' : ''; ?> id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="Sample Testing Date" title="Please select sample testing date" value="<?php echo $vlQueryInfo['sample_tested_datetime']; ?>" />
														</div>
													</div>
												</div>
												<div class="row">
													<div class="col-md-6 rejectionReason" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;">
														<label class="col-lg-5 control-label" for="rejectionReason">Rejection Reason<span class="mandatory">*</span> </label>
														<div class="col-lg-7">
															<select name="rejectionReason" id="rejectionReason" class="form-control labSection" title="Please choose reason" onchange="checkRejectionReason();">
																<option value="">-- Select --</option>
																<?php foreach ($rejectionTypeResult as $type) { ?>
																	<optgroup label="<?php echo strtoupper((string) $type['rejection_type']); ?>">
																		<?php foreach ($rejectionResult as $reject) {
																			if ($type['rejection_type'] == $reject['rejection_type']) { ?>
																				<option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($vlQueryInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?= $reject['rejection_reason_name']; ?></option>
																		<?php }
																		} ?>
																	</optgroup>
																<?php } ?>
																<option value="other">Other (Please Specify) </option>
															</select>
															<input type="text" class="form-control newRejectionReason" name="newRejectionReason" id="newRejectionReason" placeholder="Rejection Reason" title="Please enter rejection reason" style="width:100%;display:none;margin-top:2px;">
														</div>
													</div>
													<div class="col-md-6 rejectionReason" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;">
														<label class="col-lg-5 control-label" for="rejectionDate">Rejection
															Date <span class="mandatory">*</span></label>
														<div class="col-lg-7">
															<input value="<?php echo DateUtility::humanReadableDateFormat($vlQueryInfo['rejection_on']); ?>" class="form-control date rejection-date <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'isRequired' : ''; ?>" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" title="Please select Sample Rejection Date" />
														</div>
													</div>
												</div>
												<div class="row">
													<div class="col-md-6 vlResult" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'none' : 'block'; ?>;">
														<label class="col-lg-5 control-label" for="vlResult">Viral Load
															Result (copies/mL) <span class="mandatory">*</span></label>
														<div class="col-lg-7 resultInputContainer">
															<input list="possibleVlResults" class="form-control result-fields labSection" id="vlResult" name="vlResult" placeholder="Select or Type VL Result" title="Please enter viral load result" value="<?= ($vlQueryInfo['result']); ?>" onchange="calculateLogValue(this)">
															<datalist id="possibleVlResults">
															</datalist>
														</div>
													</div>
													<div class="col-md-6 vlResult" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'none' : 'block'; ?>;">
														<label class="col-lg-5 control-label" for="vlLog">Viral Load (Log)
														</label>
														<div class="col-lg-7">
															<input type="text" class="form-control labSection" id="vlLog" name="vlLog" placeholder="Viral Load (Log)" title="Please enter viral load in log" value="<?= ($vlQueryInfo['result_value_log']); ?>" <?php echo ($vlQueryInfo['result'] == 'Target Not Detected' || $vlQueryInfo['result'] == 'Below Detection Level') ? 'readonly="readonly"' : ''; ?> style="width:100%;" onchange="calculateLogValue(this);" />
														</div>
													</div>
												</div>
												<div class="row">

													<div class="col-md-6">
														<label class="col-lg-5 control-label" for="reviewedBy">Reviewed By
															<span class="mandatory review-approve-span" style="display: <?php echo ($vlQueryInfo['is_sample_rejected'] != '') ? 'inline' : 'none'; ?>;">*</span></label>
														<div class="col-lg-7">
															<select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose reviewed by" style="width: 100%;">
																<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_reviewed_by'], '-- Select --'); ?>
															</select>
														</div>
													</div>
													<div class="col-md-6 hivDetection" style="<?php echo (($isGeneXpert === false) || ($isGeneXpert && $vlQueryInfo['is_sample_rejected'] === 'yes')) ? 'display: none;' : ''; ?>">
														<label for="hivDetection" class="col-lg-5 control-label">HIV
															Detection </label>
														<div class="col-lg-7">
															<select name="hivDetection" id="hivDetection" class="form-control hivDetection labSection" title="Please choose HIV detection">
																<option value="">-- Select --</option>
																<option value="HIV-1 Detected" <?php echo (isset($vlQueryInfo['result_value_hiv_detection']) && $vlQueryInfo['result_value_hiv_detection'] == 'HIV-1 Detected') ? 'selected="selected"' : ''; ?>>HIV-1
																	Detected</option>
																<option value="HIV-1 Not Detected" <?php echo (isset($vlQueryInfo['result_value_hiv_detection']) && $vlQueryInfo['result_value_hiv_detection'] == 'HIV-1 Not Detected') ? 'selected="selected"' : ''; ?>>HIV-1 Not
																	Detected</option>
															</select>
														</div>
													</div>
												</div>
												<?php if (count($reasonForFailure) > 0) { ?>
													<div class="row">
														<div class="col-md-6 reasonForFailure" style="<?php echo (isset($vlQueryInfo['result']) && $vlQueryInfo['result'] == 'failed') ? '' : 'display: none;'; ?>">
															<label class="col-lg-5 control-label" for="reasonForFailure">Reason
																for Failure <span class="mandatory">*</span> </label>
															<div class="col-lg-7">
																<select name="reasonForFailure" id="reasonForFailure" class="form-control" title="Please choose reason for failure" style="width: 100%;">
																	<?= $general->generateSelectOptions($reasonForFailure, $vlQueryInfo['reason_for_failure'], '-- Select --'); ?>
																</select>
															</div>
														</div>
													</div>
												<?php } ?>
												<hr>
												<div class="row">

													<div class="col-md-6">
														<label class="col-lg-5 control-label" for="reviewedOn">Reviewed On
															<span class="mandatory review-approve-span" style="display: <?php echo ($vlQueryInfo['is_sample_rejected'] != '') ? 'inline' : 'none'; ?>;">*</span></label>
														<div class="col-lg-7">
															<input type="text" value="<?php echo $vlQueryInfo['result_reviewed_datetime']; ?>" name="reviewedOn" id="reviewedOn" class="dateTime form-control" placeholder="Reviewed on" title="Please enter the Reviewed on" />
														</div>
													</div>
													<div class="col-md-6">
														<label class="col-lg-5 control-label" for="testedBy">Tested By
														</label>
														<div class="col-lg-7">
															<select name="testedBy" id="testedBy" class="select2 form-control" title="Please choose approved by">
																<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['tested_by'], '-- Select --'); ?>
															</select>
														</div>
													</div>
												</div>
												<div class="row">

													<div class="col-md-6">
														<label class="col-lg-5 control-label" for="approvedBy">Approved By
															<span class="mandatory review-approve-span" style="display: <?php echo ($vlQueryInfo['is_sample_rejected'] != '') ? 'inline' : 'none'; ?>;">*</span></label>
														<div class="col-lg-7">
															<select name="approvedBy" id="approvedBy" class="form-control labSection" title="Please choose approved by">
																<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_approved_by'], '-- Select --'); ?>
															</select>
														</div>
													</div>
													<div class="col-md-6">
														<label class="col-lg-5 control-label" for="approvedOnDateTime">Approved On <span class="mandatory review-approve-span" style="display: <?php echo ($vlQueryInfo['is_sample_rejected'] != '') ? 'inline' : 'none'; ?>;">*</span></label>
														<div class="col-lg-7">
															<input type="text" value="<?php echo $vlQueryInfo['result_approved_datetime']; ?>" class="form-control dateTime" id="approvedOnDateTime" name="approvedOnDateTime" placeholder="<?= _translate("Please enter date"); ?>" style="width:100%;" />
														</div>
													</div>
												</div>
												<div class="row">

													<div class="col-md-6">
														<label class="col-lg-5 control-label" for="resultDispatchedOn">Date
															Results Dispatched </label>
														<div class="col-lg-7">
															<input type="text" class="form-control labSection" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="Result Dispatched Date" title="Please select result dispatched date" value="<?php echo $vlQueryInfo['result_dispatched_datetime']; ?>" />
														</div>
													</div>
													<div class="col-md-6">
														<label class="col-lg-5 control-label" for="labComments">Lab Tech.
															Comments </label>
														<div class="col-lg-7">
															<textarea class="form-control labSection" name="labComments" id="labComments" placeholder="Lab comments"><?php echo trim((string) $vlQueryInfo['lab_tech_comments']); ?></textarea>
														</div>
													</div>
												</div>
												<div class="row">
													<div class="col-md-6 reasonForResultChanges" style="display:none;">
														<label class="col-lg-6 control-label" for="reasonForResultChanges">Reason For Changes in Result<span class="mandatory">*</span> </label>
														<div class="col-lg-6">
															<textarea class="form-control" name="reasonForResultChanges" id="reasonForResultChanges" placeholder="Enter Reason For Result Changes" title="Please enter reason for result changes" style="width:100%;"></textarea>
														</div>
													</div>
												</div>
												<?php
												if (!empty($allChange)) {
												?>
													<div class="row">
														<div class="col-md-12">
															<?php echo $rch; ?>
														</div>
													</div>
												<?php } ?>
											</div>
										</div>
							</div>
							<div class="box-footer">
								<input type="hidden" name="revised" id="revised" value="no" />
								<input type="hidden" name="vlSampleId" id="vlSampleId" value="<?= ($vlQueryInfo['vl_sample_id']); ?>" />
								<input type="hidden" name="reasonForResultChangesHistory" id="reasonForResultChangesHistory" value="<?php echo base64_encode((string) $vlQueryInfo['reason_for_result_changes']); ?>" />
								<input type="hidden" name="sampleCode" id="sampleCode" value="<?= ($vlQueryInfo['sample_code']); ?>" />
								<input type="hidden" name="artNo" id="artNo" value="<?= ($vlQueryInfo['patient_art_no']); ?>" />
								<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>&nbsp;
								<a href="vlTestResult.php" class="btn btn-default"> Cancel</a>
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
		hivDetectionChange();

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
			//$('#approvedOn').addClass('isRequired');
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
			//$('#approvedOn').addClass('isRequired');
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
		if ((str.trim() == 'GeneXpert' || str.toLowerCase() == 'genexpert') && $('#isSampleRejected').val() != 'yes') {
			$('.hivDetection').prop('disabled', false);
			$('.hivDetection').show();
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
</script>