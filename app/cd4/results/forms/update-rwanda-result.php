<?php

use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$province = $general->getUserMappedProvinces($_SESSION['facilityMap']);

$facility = $general->generateSelectOptions($healthFacilities, $cd4QueryInfo['facility_id'], '-- Select --');


//facility details
if (isset($cd4QueryInfo['facility_id']) && $cd4QueryInfo['facility_id'] > 0) {
	$facilityQuery = "SELECT * FROM facility_details where facility_id= ? AND status='active'";
	$facilityResult = $db->rawQuery($facilityQuery, array($cd4QueryInfo['facility_id']));
}
if (!isset($facilityResult[0]['facility_code'])) {
	$facilityResult[0]['facility_code'] = '';
}
if (!isset($facilityResult[0]['facility_mobile_numbers'])) {
	$facilityResult[0]['facility_mobile_numbers'] = '';
}
$user = '';
if (!isset($facilityResult[0]['contact_person'])) {
	$facilityResult[0]['contact_person'] = '';
} else {
	$contactUser = $usersService->getUserByID($facilityResult[0]['contact_person']);
	if (!empty($contactUser)) {
		$user = $contactUser['user_name'];
	}
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

$stateName = $facilityResult[0]['facility_state'];
if (trim((string) $stateName) != '') {
	$stateQuery = "SELECT * from geographical_divisions where geo_name='" . $stateName . "'";
	$stateResult = $db->query($stateQuery);
}
if (!isset($stateResult[0]['geo_code']) || $stateResult[0]['geo_code'] == '') {
	$stateResult[0]['geo_code'] = '';
}
//district details
$districtResult = [];
if (trim((string) $stateName) != '') {
	$districtQuery = "SELECT DISTINCT facility_district from facility_details where facility_state='" . $stateName . "' AND status='active'";
	$districtResult = $db->query($districtQuery);
	$facilityQuery = "SELECT * from facility_details where `status`='active' AND facility_type='2' Order By facility_name";
	$lResult = $db->query($facilityQuery);
}

//set reason for changes history
$rch = '';
if (isset($cd4QueryInfo['reason_for_result_changes']) && $cd4QueryInfo['reason_for_result_changes'] != '' && $cd4QueryInfo['reason_for_result_changes'] != null) {
	$rch .= '<h4>Result Changes History</h4>';
	$rch .= '<table style="width:100%;">';
	$rch .= '<thead><tr style="border-bottom:2px solid #d3d3d3;"><th style="width:20%;">USER</th><th style="width:60%;">MESSAGE</th><th style="width:20%;text-align:center;">DATE</th></tr></thead>';
	$rch .= '<tbody>';
	$splitChanges = explode('vlsm', (string) $cd4QueryInfo['reason_for_result_changes']);
	for ($c = 0; $c < count($splitChanges); $c++) {
		$getData = explode("##", $splitChanges[$c]);
		$expStr = explode(" ", $getData[2]);
		$changedDate = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
		$rch .= '<tr><td>' . ($getData[0]) . '</td><td>' . ($getData[1]) . '</td><td style="text-align:center;">' . $changedDate . '</td></tr>';
	}
	$rch .= '</tbody>';
	$rch .= '</table>';
}
$disable = "disabled = 'disabled'";

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
		<h1><em class="fa-solid fa-pen-to-square"></em> CD4 LABORATORY REQUEST FORM </h1>
		<ol class="breadcrumb">
			<li><a href="/dashboard/index.php"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Enter CD4 Request</li>
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
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="sampleCode">Sample ID <span class="mandatory">*</span></label>
										<input type="text" class="form-control isRequired" id="sampleCode" name="sampleCode" placeholder="Enter Sample ID" title="<?= _translate("Please make sure you have selected Sample Collection Date and Requesting Facility"); ?>" value="<?= ($cd4QueryInfo['sample_code']); ?>" <?php echo $disable; ?> style="width:100%;" />
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="sampleReordered">
											<input type="checkbox" class="" id="sampleReordered" name="sampleReordered" value="yes" <?php echo (trim((string) $cd4QueryInfo['sample_reordered']) == 'yes') ? 'checked="checked"' : '' ?> <?php echo $disable; ?> title="Please indicate if this is a reordered sample"> Sample Reordered
										</label>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-xs-3 col-md-3">
									<label for="province">Province <span class="mandatory">*</span></label>
									<select class="form-control isRequired" name="province" id="province" title="Please choose province" <?php echo $disable; ?> style="width:100%;" onchange="getfacilityDetails(this);">
										<option value=""> -- Select -- </option>
										<?php foreach ($pdResult as $provinceName) { ?>
											<option value="<?php echo $provinceName['geo_name'] . "##" . $provinceName['geo_code']; ?>" <?php echo ($facilityResult[0]['facility_state'] . "##" . $stateResult[0]['geo_code'] == $provinceName['geo_name'] . "##" . $provinceName['geo_code']) ? "selected='selected'" : "" ?>><?php echo ($provinceName['geo_name']); ?></option>;
										<?php } ?>
									</select>
								</div>
								<div class="col-xs-3 col-md-3">
									<label for="district">District <span class="mandatory">*</span></label>
									<select class="form-control isRequired" name="district" id="district" title="Please choose district" <?php echo $disable; ?> style="width:100%;" onchange="getfacilityDistrictwise(this);">
										<option value=""> -- Select -- </option>
										<?php
										foreach ($districtResult as $districtName) {
										?>
											<option value="<?php echo $districtName['facility_district']; ?>" <?php echo ($facilityResult[0]['facility_district'] == $districtName['facility_district']) ? "selected='selected'" : "" ?>><?php echo ($districtName['facility_district']); ?></option>
										<?php
										}
										?>
									</select>
								</div>
								<div class="col-xs-3 col-md-3">
									<label for="facilityId">Health Facility Name <span class="mandatory">*</span></label>
									<select class="form-control isRequired" id="facilityId" name="facilityId" title="Please select Health Facility Name" <?php echo $disable; ?> style="width:100%;" onchange="autoFillFacilityCode();">
										<?= $facility; ?>
									</select>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="facilityCode">Facility Code </label>
										<input type="text" class="form-control" style="width:100%;" name="facilityCode" id="facilityCode" placeholder="Facility Code" title="Please enter Facility code" value="<?php echo $facilityResult[0]['facility_code']; ?>" <?php echo $disable; ?>>
									</div>
								</div>
							</div>
							<!--<div class="row facilityDetails" style="display:< ?php echo (trim((string) $facilityResult[0]['facility_emails']) != '' || trim((string) $facilityResult[0]['facility_mobile_numbers']) != '' || trim((string) $facilityResult[0]['contact_person']) != '') ? '' : 'none'; ?>;">
								<div class="col-xs-2 col-md-2 femails" style="display:< ?php echo (trim((string) $facilityResult[0]['facility_emails']) != '') ? '' : 'none'; ?>;">
									<strong>Clinic/Health Center Email(s)</strong>
								</div>
								<div class="col-xs-2 col-md-2 femails facilityEmails" style="display:<?php echo (trim((string) $facilityResult[0]['facility_emails']) != '') ? '' : 'none'; ?>;">
									<?php echo $facilityResult[0]['facility_emails']; ?></div>
								<div class="col-xs-2 col-md-2 fmobileNumbers" style="display:<?php echo (trim((string) $facilityResult[0]['facility_mobile_numbers']) != '') ? '' : 'none'; ?>;">
									<strong>Clinic/Health Center Mobile No.(s)</strong>
								</div>
								<div class="col-xs-2 col-md-2 fmobileNumbers facilityMobileNumbers" style="display:<?php echo (trim((string) $facilityResult[0]['facility_mobile_numbers']) != '') ? '' : 'none'; ?>;">
									<?php echo $facilityResult[0]['facility_mobile_numbers']; ?></div>
								<div class="col-xs-2 col-md-2 fContactPerson" style="display:<?php echo (trim((string) $facilityResult[0]['contact_person']) != '') ? '' : 'none'; ?>;">
									<strong>Clinic Contact Person </strong>
								</div>
								<div class="col-xs-2 col-md-2 fContactPerson facilityContactPerson" style="display:<?php echo (trim((string) $user) != '') ? '' : 'none'; ?>;">
									<?php echo ($user); ?></div>
							</div>-->
							<div class="row">
								<!--<div class="col-xs-3 col-md-3">
                                                  <div class="">
                                                       <label for="facilityCode">Affiliated District Hospital </label>
                                                       <input type="text" class="form-control" style="width:100%;" name="facilityCode" id="facilityCode" placeholder="Affiliated District Hospital" title="Please enter Affiliated District Hospital" < ?php echo $disable; ?>>
                                                  </div>
                                             </div>-->
								<div class="col-xs-3 col-md-3">
									<div class="">
										<label for="labId">Affiliated CD4 Testing Hub <span class="mandatory">*</span></label>
										<select name="labId" id="labId" class="form-control isRequired" title="Please choose a CD4 testing hub" style="width:100%;" <?php echo $disable; ?>>
											<?= $general->generateSelectOptions($testingLabs, $cd4QueryInfo['lab_id'], '-- Select --'); ?>
										</select>
									</div>
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
										<input type="text" name="artNo" id="artNo" class="form-control isRequired" placeholder="Enter ART Number" title="Enter art number" value="<?= ($cd4QueryInfo['patient_art_no']); ?>" <?php echo $disable; ?> />
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="dob">Date of Birth <span class="mandatory">*</span></label>
										<input type="text" name="dob" id="dob" class="form-control date isRequired" placeholder="Enter DOB" title="Enter dob" value="<?= ($cd4QueryInfo['patient_dob']); ?>" <?php echo $disable; ?> />
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="ageInYears">If DOB unknown, Age in Year </label>
										<input type="text" name="ageInYears" id="ageInYears" class="form-control forceNumeric" maxlength="2" placeholder="Age in Year" title="Enter age in years" <?php echo $disable; ?> value="<?= ($cd4QueryInfo['patient_age_in_years']); ?>" />
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="ageInMonths">If Age < 1, Age in Month </label> <input type="text" name="ageInMonths" id="ageInMonths" class="form-control forceNumeric" maxlength="2" placeholder="Age in Month" title="Enter age in months" <?php echo $disable; ?> value="<?= ($cd4QueryInfo['patient_age_in_months']); ?>" />
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="patientFirstName">Patient Name / Code </label>
										<input type="text" name="patientFirstName" id="patientFirstName" class="form-control" placeholder="Enter Patient Name" title="Enter patient name" <?php echo $disable; ?> value="<?php echo $patientFirstName; ?>" />
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="gender"><?= _translate("Sex"); ?> <span class="mandatory">*</span></label><br>
										<label class="radio-inline" style="margin-left:0px;">
											<input type="radio" class="isRequired" id="genderMale" name="gender" value="male" title="Please choose sex" <?php echo $disable; ?> <?php echo ($cd4QueryInfo['patient_gender'] == 'male') ? "checked='checked'" : "" ?>> Male
										</label>&nbsp;&nbsp;
										<label class="radio-inline" style="margin-left:0px;">
											<input type="radio" id="genderFemale" name="gender" value="female" title="Please choose sex" <?php echo $disable; ?> <?php echo ($cd4QueryInfo['patient_gender'] == 'female') ? "checked='checked'" : "" ?>> Female
										</label>

									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="patientPhoneNumber">Phone Number</label>
										<input type="text" name="patientPhoneNumber" id="patientPhoneNumber" class="form-control phone-number" maxlength="15" placeholder="Enter Phone Number" title="Enter phone number" value="<?= ($cd4QueryInfo['patient_mobile_number']); ?>" <?php echo $disable; ?> />
									</div>
								</div>
							</div>
							<div class="row femaleSection" style="display:<?php echo ($cd4QueryInfo['patient_gender'] == 'female') ? "" : "none" ?>" ;>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="patientPregnant">Is Patient Pregnant? <span class="mandatory">*</span></label><br>
										<label class="radio-inline">
											<input type="radio" class="<?php echo ($cd4QueryInfo['patient_gender'] == 'female') ? "isRequired" : ""; ?>" id="pregYes" name="patientPregnant" value="yes" title="Please check if patient is pregnant" <?php echo $disable; ?> <?php echo ($cd4QueryInfo['is_patient_pregnant'] == 'yes') ? "checked='checked'" : "" ?>> Yes
										</label>
										<label class="radio-inline">
											<input type="radio" id="pregNo" name="patientPregnant" value="no" <?php echo $disable; ?> <?php echo ($cd4QueryInfo['is_patient_pregnant'] == 'no') ? "checked='checked'" : "" ?>> No
										</label>
									</div>
								</div>
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="breastfeeding">Is Patient Breastfeeding? <span class="mandatory">*</span></label><br>
										<label class="radio-inline">
											<input type="radio" class="<?php echo ($cd4QueryInfo['patient_gender'] == 'female') ? "isRequired" : ""; ?>" id="breastfeedingYes" name="breastfeeding" value="yes" title="Please check if patient is breastfeeding" <?php echo $disable; ?> <?php echo ($cd4QueryInfo['is_patient_breastfeeding'] == 'yes') ? "checked='checked'" : "" ?>> Yes
										</label>
										<label class="radio-inline">
											<input type="radio" id="breastfeedingNo" name="breastfeeding" value="no" <?php echo $disable; ?> <?php echo ($cd4QueryInfo['is_patient_breastfeeding'] == 'no') ? "checked='checked'" : "" ?>> No
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
											<input type="text" class="form-control isRequired" style="width:100%;" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" title="Please select sample collection date" value="<?php echo $cd4QueryInfo['sample_collection_date']; ?>" <?php echo $disable; ?>>
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="specimenType">Sample Type <span class="mandatory">*</span></label>
											<select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose sample type" <?php echo $disable; ?>>
												<option value=""> -- Select -- </option>
												<?php
												foreach ($sResult as $name) {
												?>
													<option value="<?php echo $name['sample_id']; ?>" <?php echo ($cd4QueryInfo['specimen_type'] == $name['sample_id']) ? "selected='selected'" : "" ?>><?= $name['sample_name']; ?></option>
												<?php
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="">Is sample re-ordered as part of corrective action? <span class="mandatory">*</span></label>
											<select name="isSampleReordered" id="isSampleReordered" class="form-control <?php echo ($general->isSTSInstance()) ? "isRequired" : ''; ?>" title="Please choose adherence">
												<option value=""> -- Select -- </option>
												<option value="yes" <?php echo $cd4QueryInfo['sample_reordered'] == 'yes' ? 'selected="selected"' : ''; ?>>Yes</option>
												<option value="no" <?php echo $cd4QueryInfo['sample_reordered'] == 'no' ? 'selected="selected"' : ''; ?>>No</option>
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
												<input type="text" class="form-control date" name="dateOfArtInitiation" id="dateOfArtInitiation" placeholder="Date Of Treatment Initiated" title="Date Of treatment initiated" value="<?php echo $cd4QueryInfo['treatment_initiated_date']; ?>" <?php echo $disable; ?> style="width:100%;">
											</div>
										</div>
										<div class="col-xs-3 col-md-3">
											<div class="form-group">
												<label for="artRegimen">Current Regimen
													<?php echo ($general->isSTSInstance()) ? "<span class='mandatory'>*</span>" : ''; ?>
												</label>
												<select class="form-control <?php echo ($general->isSTSInstance()) ? "isRequired" : ''; ?>" id="artRegimen" name="artRegimen" title="Please choose ART Regimen" <?php echo $disable; ?> style="width:100%;" onchange="checkARTValue();">
													<option value="">-- Select --</option>
													<?php foreach ($artRegimenResult as $heading) { ?>
														<optgroup label="<?= $heading['headings']; ?>">
															<?php
															foreach ($aResult as $regimen) {
																if ($heading['headings'] == $regimen['headings']) {
															?>
																	<option value="<?php echo $regimen['art_code']; ?>" <?php echo $disable; ?> <?php echo ($cd4QueryInfo['current_regimen'] == $regimen['art_code']) ? "selected='selected'" : "" ?>><?php echo $regimen['art_code']; ?></option>
															<?php
																}
															}
															?>
														</optgroup>
													<?php } ?>
													<option value="other">Other</option>
												</select>
												<input type="text" class="form-control newArtRegimen" name="newArtRegimen" id="newArtRegimen" placeholder="ART Regimen" title="Please enter art regimen" <?php echo $disable; ?> style="width:100%;display:none;margin-top:2px;">
											</div>
										</div>
										<div class="col-xs-3 col-md-3">
											<div class="form-group">
												<label for="">Date of Initiation of Current Regimen
													<?php echo ($general->isSTSInstance()) ? "<span class='mandatory'>*</span>" : ''; ?>
												</label>
												<input type="text" class="form-control date <?php echo ($general->isSTSInstance()) ? "isRequired" : ''; ?>" style="width:100%;" name="regimenInitiatedOn" id="regimenInitiatedOn" placeholder="Current Regimen Initiated On" title="Please enter current regimen initiated on" <?php echo $disable; ?> value="<?php echo $cd4QueryInfo['date_of_initiation_of_current_regimen']; ?>">
											</div>
										</div>
										<div class="col-xs-3 col-md-3">
											<div class="form-group">
												<label for="arvAdherence">ARV Adherence
													<?php echo ($general->isSTSInstance()) ? "<span class='mandatory'>*</span>" : ''; ?>
												</label>
												<select name="arvAdherence" id="arvAdherence" class="form-control <?php echo ($general->isSTSInstance()) ? "isRequired" : ''; ?>" title="Please choose adherence" <?php echo $disable; ?>>
													<option value=""> -- Select -- </option>
													<option value="good" <?php echo ($cd4QueryInfo['arv_adherance_percentage'] == 'good') ? "selected='selected'" : "" ?>>Good >= 95%</option>
													<option value="fair" <?php echo ($cd4QueryInfo['arv_adherance_percentage'] == 'fair') ? "selected='selected'" : "" ?>>Fair (85-94%)</option>
													<option value="poor" <?php echo ($cd4QueryInfo['arv_adherance_percentage'] == 'poor') ? "selected='selected'" : "" ?>>Poor < 85%</option>
												</select>
											</div>
										</div>
									</div>

								</div>
								<div class="box box-primary">
									<div class="box-header with-border">
										<h3 class="box-title">INDICATION FOR CD4 COUNT TESTING <span class="mandatory">*</span></h3><small> (Please tick one):(To be
											completed by clinician)</small>
									</div>
									<div class="box-body">
										<div class="row">
											<div class="col-md-6">
												<div class="form-group">
													<div class="col-lg-12">
														<label class="radio-inline">
															<?php
															$cd4TestReasonQueryRow = "SELECT * from r_cd4_test_reasons where test_reason_id='" . trim((string) $cd4QueryInfo['reason_for_cd4_testing']) . "' OR test_reason_name = '" . trim((string) $cd4QueryInfo['reason_for_cd4_testing']) . "'";
															$cd4TestReasonResultRow = $db->query($cd4TestReasonQueryRow);
															$checked = '';
															$display = '';
															$cd4Date = '';
															$cd4Value = '';
															$cd4ValuePercentage = '';
															$cragResult = '';
															if (trim((string) $cd4QueryInfo['reason_for_cd4_testing']) == 'baselineInitiation' || isset($cd4TestReasonResultRow[0]['test_reason_id']) && $cd4TestReasonResultRow[0]['test_reason_name'] == 'baselineInitiation') {
																$checked = 'checked="checked"';
																$display = 'block';
																$cd4Date = $cd4QueryInfo['last_cd4_date'];
																$cd4Value = $cd4QueryInfo['last_cd4_result'];
																$cd4ValuePercentage = $cd4QueryInfo['last_cd4_result_percentage'];
																$cragResult = $cd4QueryInfo['last_cd4_crag_result'];
															} else {
																$checked = '';
																$display = 'none';
															}
															?>
															<input type="radio" class="isRequired" id="baselineInitiation" name="reasonForCD4Testing" value="baselineInitiation" title="Please check CD4 indication testing type" onclick="showTesting('baselineInitiation');" <?= $checked; ?> <?php echo $disable; ?>>
															<strong>Baseline at ART initiation or re-initiation</strong>
														</label>
													</div>
												</div>
											</div>
										</div>
										<div class="row baselineInitiation hideTestData well" style="display:<?php echo $display; ?>;">
											<div class="col-md-4">
												<label class="col-lg-5 control-label">Last CD4 date</label>
												<div class="col-lg-7">
													<input type="text" class="form-control date viralTestData" id="baselineInitiationLastCd4Date" name="baselineInitiationLastCd4Date" placeholder="Select Last CD4 Date" title="Please select Last CD4 Date" value="<?= DateUtility::humanReadableDateFormat($cd4Date); ?>" <?php echo $disable; ?> />
												</div>
											</div>
											<div class="col-md-4">
												<label for="baselineInitiationCD4Value" class="col-lg-5 control-label"> Absolute value & Percentage :</label>
												<div class="col-lg-7">
													<div class="col-xs-6"><input type="text" class="form-control forceNumeric viralTestData input-sm" id="baselineInitiationLastCd4Result" name="baselineInitiationLastCd4Result" placeholder="Enter CD4 Result" title="Please enter CD4 Result" value="<?= $cd4Value; ?>" <?php echo $disable; ?> />(cells/ml)</div>
													<div class="col-xs-6"><input type="text" class="form-control forceNumeric viralTestData input-sm" id="baselineInitiationLastCd4ResultPercentage" name="baselineInitiationLastCd4ResultPercentage" placeholder="CD4 Result %" title="Please enter CD4 Result" value="<?= $cd4ValuePercentage; ?>" <?php echo $disable; ?> /></div>
												</div>
											</div>
											<div class="col-md-4">
                                                                 <label for="baselineInitiationLastCd4Result" class="col-lg-5 control-label">CrAg Result</label>
                                                                 <div class="col-lg-7">
                                                                      <div class="col-lg-7">
                                                                           <select class="form-control viralTestData" id="baselineInitiationLastCrAgResult" name="baselineInitiationLastCrAgResult" placeholder="CrAg Test Results" title="Please select CrAg Test results" style="width:100%;" <?php echo $disable; ?>>
                                                                                <option value="">--Select--</option>
                                                                                <option <?= ($cragResult == 'positive') ? 'selected="selected"' : ''; ?> value="positive">Positive</option>
                                                                                <option <?= ($cragResult == 'negative') ? 'selected="selected"' : ''; ?> value="negative">Negative</option>
                                                                                <option <?= ($cragResult == 'intermediate') ? 'selected="selected"' : ''; ?> value="intermediate">Indeterminate</option>
                                                                                <option <?= ($cragResult == 'testNotDone') ? 'selected="selected"' : ''; ?> value="testNotDone">Test not done</option>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                       </div>
										</div>
										<div class="row">
											<div class="col-md-6">
												<div class="form-group">
													<div class="col-lg-12">
														<label class="radio-inline">
															<?php
															$cd4TestReasonQueryRow = "SELECT * from r_cd4_test_reasons where test_reason_id='" . trim((string) $cd4QueryInfo['reason_for_cd4_testing']) . "' OR test_reason_name = '" . trim((string) $cd4QueryInfo['reason_for_cd4_testing']) . "'";
															$cd4TestReasonResultRow = $db->query($cd4TestReasonQueryRow);
															$checked = '';
															$display = '';
															if (trim((string) $cd4QueryInfo['reason_for_cd4_testing']) == 'assessmentAHD' || isset($cd4TestReasonResultRow[0]['test_reason_id']) && $cd4TestReasonResultRow[0]['test_reason_name'] == 'assessmentAHD') {
																$checked = 'checked="checked"';
																$display = 'block';
																$cd4Date = $cd4QueryInfo['last_cd4_date'];
																$cd4Value = $cd4QueryInfo['last_cd4_result'];
																$cd4ValuePercentage = $cd4QueryInfo['last_cd4_result_percentage'];
																$cragResult = $cd4QueryInfo['last_cd4_crag_result'];

															} else {
																$checked = '';
																$display = 'none';
															}
															?>
															<input type="radio" class="" id="assessmentAHD" name="reasonForCD4Testing" value="assessmentAHD" title="Please check CD4 indication testing type" onclick="showTesting('assessmentAHD');" <?= $checked; ?> <?php echo $disable; ?>>
															<strong>Assessment for Advanced HIV Disease (AHD)</strong>
														</label>
													</div>
												</div>
											</div>
										</div>
										<div class="row assessmentAHD hideTestData well" style="display: <?= $display; ?>;margin-bottom:20px;">
											<div class="col-md-4">
												<label class="col-lg-5 control-label">Last CD4 date</label>
												<div class="col-lg-7">
													<input type="text" class="form-control date viralTestData" id="assessmentAHDLastCd4Date" name="assessmentAHDLastCd4Date" placeholder="Select Last CD4 Date" title="Please select Last CD4 Date" value="<?= DateUtility::humanReadableDateFormat($cd4Date); ?>" <?php echo $disable; ?> />
												</div>
											</div>
											<div class="col-md-4">
												<label for="assessmentAHDCD4Value" class="col-lg-5 control-label">Absolute value & Percentage</label>
												<div class="col-lg-7">
													<div class="col-xs-6"><input type="text" class="form-control forceNumeric viralTestData" id="assessmentAHDLastCd4Result" name="assessmentAHDLastCd4Result" placeholder="CD4 Result" title="Please enter CD4 Result" value="<?= $cd4Value; ?>" <?php echo $disable; ?> />(cells/ml)</div>
													<div class="col-xs-6"><input type="text" class="form-control forceNumeric viralTestData" id="assessmentAHDLastCd4ResultPercentage" name="assessmentAHDLastCd4ResultPercentage" placeholder="CD4 Result %" title="Please enter CD4 Result" value="<?= $cd4ValuePercentage; ?>" <?php echo $disable; ?> /></div>
												</div>
											</div>
											<div class="col-md-4">
                                                <label for="assessmentAHDLastCd4Result" class="col-lg-5 control-label">CrAg Result</label>
                                                <div class="col-lg-7">
                                                    <div class="col-lg-7">
                                                        <select class="form-control viralTestData" id="assessmentAHDLastCrAgResult" name="assessmentAHDLastCrAgResult" placeholder="CrAg Test Results" title="Please select CrAg Test results" style="width:100%;" <?php echo $disable; ?>>
                                                            <option value="">--Select--</option>
                                                            <option <?= ($cragResult == 'positive') ? 'selected="selected"' : ''; ?> value="positive">Positive</option>
                                                            <option <?= ($cragResult == 'negative') ? 'selected="selected"' : ''; ?> value="negative">Negative</option>
                                                            <option <?= ($cragResult == 'intermediate') ? 'selected="selected"' : ''; ?> value="intermediate">Indeterminate</option>
                                                            <option <?= ($cragResult == 'testNotDone') ? 'selected="selected"' : ''; ?> value="testNotDone">Test not done</option>
                                                        </select>
                                                     </div>
                                                </div>
                                            </div>
										</div>
										<div class="row">
											<div class="col-md-8">
												<div class="form-group">
													<div class="col-lg-12">
														<label class="radio-inline">
															<?php
															$cd4TestReasonQueryRow = "SELECT * from r_cd4_test_reasons where test_reason_id='" . trim((string) $cd4QueryInfo['reason_for_cd4_testing']) . "' OR test_reason_name = '" . trim((string) $cd4QueryInfo['reason_for_cd4_testing']) . "'";
															$cd4TestReasonResultRow = $db->query($cd4TestReasonQueryRow);
															$checked = '';
															$display = '';
															if (trim((string) $cd4QueryInfo['reason_for_cd4_testing']) == 'treatmentCoinfection' || isset($cd4TestReasonResultRow[0]['test_reason_id']) && $cd4TestReasonResultRow[0]['test_reason_name'] == 'treatmentCoinfection') {
																$checked = 'checked="checked"';
																$display = 'block';
																$cd4Date = $cd4QueryInfo['last_cd4_date'];
																$cd4Value = $cd4QueryInfo['last_cd4_result'];
																$cd4ValuePercentage = $cd4QueryInfo['last_cd4_result_percentage'];
																$cragResult = $cd4QueryInfo['last_cd4_crag_result'];

															} else {
																$checked = '';
																$display = 'none';
															}
															?>
															<input type="radio" class="" id="treatmentCoinfection" name="reasonForCD4Testing" value="treatmentCoinfection" title="Please check CD4 indication testing type" onclick="showTesting('treatmentCoinfection');" <?= $checked; ?> <?php echo $disable; ?>>
															<strong>Treatment follow up of TB-HIV co-infection </strong>
														</label>
													</div>
												</div>
											</div>
										</div>
										<div class="row treatmentCoinfection hideTestData well" style="display:<?= $display; ?>;">
											<div class="col-md-4">
												<label class="col-lg-5 control-label">Last CD4 date</label>
												<div class="col-lg-7">
													<input type="text" class="form-control date viralTestData" id="treatmentCoinfectionLastCd4Date" name="treatmentCoinfectionLastCd4Date" placeholder="Select Last CD4 Date" title="Please select Last CD4 Date" value="<?= DateUtility::humanReadableDateFormat($cd4Date); ?>" <?php echo $disable; ?> />
												</div>
											</div>
											<div class="col-md-4">
												<label for="treatmentCoinfectionCD4Value" class="col-lg-5 control-label">Absolute value & Percentage</label>
												<div class="col-lg-7">
													<div class="col-xs-6"><input type="text" class="form-control forceNumeric viralTestData" id="treatmentCoinfectionLastCd4Result" name="treatmentCoinfectionLastCd4Result" placeholder="CD4 Result" title="Please enter CD4 Result" value="<?= $cd4Value; ?>" <?php echo $disable; ?> />(cells/ml)</div>
													<div class="col-xs-6"><input type="text" class="form-control forceNumeric viralTestData" id="treatmentCoinfectionLastCd4ResultPercentage" name="treatmentCoinfectionLastCd4ResultPercentage" placeholder="CD4 Result %" title="Please enter CD4 Result" value="<?= $cd4ValuePercentage; ?>" <?php echo $disable; ?> /></div>
												</div>
											</div>
											<div class="col-md-4">
                                                            <label for="assessmentAHDLastCd4Result" class="col-lg-5 control-label">CrAg Result</label>
                                                                 <div class="col-lg-7">
                                                                      <div class="col-lg-7">
                                                                           <select class="form-control viralTestData" id="treatmentCoinfectionLastCrAgResult" name="treatmentCoinfectionLastCrAgResult" placeholder="CrAg Test Results" title="Please select CrAg Test results" style="width:100%;" <?php echo $disable; ?>>
                                                                               <option value="">--Select--</option>
                                                                               <option <?= ($cragResult == 'positive') ? 'selected="selected"' : ''; ?> value="positive">Positive</option>
                                                                               <option <?= ($cragResult == 'negative') ? 'selected="selected"' : ''; ?> value="negative">Negative</option>
                                                                               <option <?= ($cragResult == 'intermediate') ? 'selected="selected"' : ''; ?> value="intermediate">Indeterminate</option>
                                                                               <option <?= ($cragResult == 'testNotDone') ? 'selected="selected"' : ''; ?> value="testNotDone">Test not done</option>
                                                                           </select>
                                                                      </div>
                                                                 </div>
                                                       </div>
										</div>

										<?php if (isset(SYSTEM_CONFIG['recency']['vlsync']) && SYSTEM_CONFIG['recency']['vlsync']) { ?>
											<div class="row">
												<div class="col-md-6">
													<div class="form-group">
														<div class="col-lg-12">
															<label class="radio-inline">
																<input type="radio" class="" id="recencyTest" name="reasonForVLTesting" value="recency" title="Please check viral load indication testing type" <?php echo $disable; ?> <?php echo trim((string) $cd4QueryInfo['reason_for_cd4_testing']) == '9999' ? "checked='checked'" : ""; ?> onclick="showTesting('recency')">
																<strong>Confirmation Test for Recency</strong>
															</label>
														</div>
													</div>
												</div>
											</div>
										<?php } ?>
										<hr>
										<div class="row">
											<div class="col-md-4">
												<label for="reqClinician" class="col-lg-5 control-label">Request
													Clinician
													<?php echo ($general->isSTSInstance()) ? "<span class='mandatory'>*</span>" : ''; ?>
												</label>
												<div class="col-lg-7">
													<input type="text" class="form-control <?php echo ($general->isSTSInstance()) ? "isRequired" : ''; ?>" id="reqClinician" name="reqClinician" placeholder="Requesting Clinician" title="Please enter request clinician" value="<?php echo $cd4QueryInfo['request_clinician_name']; ?>" <?php echo $disable; ?> />
												</div>
											</div>
											<div class="col-md-4">
												<label for="reqClinicianPhoneNumber" class="col-lg-5 control-label">Phone Number
													<?php echo ($general->isSTSInstance()) ? "<span class='mandatory'>*</span>" : ''; ?>
												</label>
												<div class="col-lg-7">
													<input type="text" class="form-control phone-number <?php echo ($general->isSTSInstance()) ? "isRequired" : ''; ?>" id="reqClinicianPhoneNumber" name="reqClinicianPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter request clinician phone number" value="<?php echo $cd4QueryInfo['request_clinician_phone_number']; ?>" <?php echo $disable; ?> />
												</div>
											</div>
											<div class="col-md-4">
												<label class="col-lg-5 control-label" for="requestDate">Request Date
													<?php echo ($general->isSTSInstance()) ? "<span class='mandatory'>*</span>" : ''; ?>
												</label>
												<div class="col-lg-7">
													<input type="text" class="form-control date <?php echo ($general->isSTSInstance()) ? "isRequired" : ''; ?>" id="requestDate" name="requestDate" placeholder="Request Date" title="Please select request date" value="<?php echo $cd4QueryInfo['test_requested_on']; ?>" <?php echo $disable; ?> />
												</div>
											</div>
										</div>
										<div class="row">
											<div class="col-md-4">
												<label for="cd4FocalPerson" class="col-lg-5 control-label">CD4 Focal
													Person
													<?php echo ($general->isSTSInstance()) ? "<span class='mandatory'>*</span>" : ''; ?>
												</label>
												<div class="col-lg-7">
													<input type="text" class="form-control <?php echo ($general->isSTSInstance()) ? "isRequired" : ''; ?>" id="cd4FocalPerson" name="cdrFocalPerson" placeholder="VL Focal Person" title="Please enter cd4 focal person name" value="<?= ($cd4QueryInfo['cd4_focal_person']); ?>" <?php echo $disable; ?> />
												</div>
											</div>
											<div class="col-md-4">
												<label for="cd4FocalPersonPhoneNumber" class="col-lg-5 control-label">CD4
													Focal Person Phone Number
													<?php echo ($general->isSTSInstance()) ? "<span class='mandatory'>*</span>" : ''; ?>
												</label>
												<div class="col-lg-7">
													<input type="text" class="form-control phone-number <?php echo ($general->isSTSInstance()) ? "isRequired" : ''; ?>" id="cd4FocalPersonPhoneNumber" name="cd4FocalPersonPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter cd4 focal person phone number" value="<?= ($cd4QueryInfo['cd4_focal_person_phone_number']); ?>" <?php echo $disable; ?> />
												</div>
											</div>
											<div class="col-md-4">
												<label class="col-lg-5 control-label" for="emailHf">Email for HF
												</label>
												<div class="col-lg-7">
													<input type="text" class="form-control" id="emailHf" name="emailHf" placeholder="Email for HF" title="Please enter email for hf" value="<?php echo $facilityResult[0]['facility_emails']; ?>" <?php echo $disable; ?> />
												</div>
											</div>
										</div>
									</div>
								</div>
								<form class="form-inline" method="post" name="cd4RequestFormRwd" id="cd4RequestFormRwd" autocomplete="off" action="cd4-update-result-helper.php">
									<div class="box box-primary">
										<div class="box-header with-border">
											<h3 class="box-title">Laboratory Information</h3>
										</div>
										<div class="box-body">
											<div class="row">
												<div class="col-md-6">
													<label for="testingPlatform" class="col-lg-5 control-label">CD4 Testing Platform<span class="mandatory">*</span> </label>
													<div class="col-lg-7">
														<select name="testingPlatform" id="testingPlatform" class="form-control isRequired" title="Please choose VL Testing Platform" <?php echo $labFieldDisabled; ?>>
															<option value="">-- Select --</option>
															<?php foreach ($importResult as $mName) { ?>
																<option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] . '##' . $mName['instrument_id']; ?>" <?php echo ($cd4QueryInfo['cd4_test_platform'] == $mName['machine_name']) ? 'selected="selected"' : ''; ?>><?php echo $mName['machine_name']; ?></option>
															<?php } ?>
														</select>
													</div>
												</div>
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="sampleReceivedDate">Date Sample Received at Testing Lab <span class="mandatory">*</span></label>
													<div class="col-lg-7">
														<input type="text" class="form-control dateTime isRequired" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="Sample Received Date" title="Please select sample received date" <?php echo $labFieldDisabled; ?> value="<?php echo $cd4QueryInfo['sample_received_at_lab_datetime']; ?>" />
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="sampleTestingDateAtLab">Sample Testing Date <span class="mandatory">*</span></label>
													<div class="col-lg-7">
														<input type="text" class="form-control dateTime isRequired" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="Sample Testing Date" title="Please select sample testing date" <?php echo $labFieldDisabled; ?> onchange="checkSampleTestingDate();" value="<?php echo $cd4QueryInfo['sample_tested_datetime']; ?>" />
													</div>
												</div>
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="reviewedBy">Tested By<span class="mandatory">*</span> </label>
													<div class="col-lg-7">
														<select name="testedBy" id="testedBy" class="select2 form-control isRequired" title="Please choose tested by" style="width: 100%;">
															<?= $general->generateSelectOptions($userInfo, $cd4QueryInfo['tested_by'], '-- Select --'); ?>
														</select>
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="isSampleRejected">Is Sample Rejected? <span class="mandatory">*</span></label>
													<div class="col-lg-7">
														<select name="isSampleRejected" id="isSampleRejected" class="form-control isRequired" title="Please check if sample is rejected or not">
															<option value="">-- Select --</option>
															<option value="yes" <?= ($cd4QueryInfo['is_sample_rejected'] === 'yes') ? 'selected="selected"' : ''; ?>>Yes</option>
															<option value="no" <?= ($cd4QueryInfo['is_sample_rejected'] === 'no') ? 'selected="selected"' : ''; ?>>No</option>
														</select>
													</div>
												</div>

												<div class="col-md-6 rejectionReason" style="display:none;">
													<label class="col-lg-5 control-label" for="rejectionReason">Rejection Reason </label>
													<div class="col-lg-7">
														<select name="rejectionReason" id="rejectionReason" class="form-control" title="Please choose reason" <?php echo $labFieldDisabled; ?> onchange="checkRejectionReason();">
															<option value="">-- Select --</option>
															<?php foreach ($rejectionTypeResult as $type) { ?>
																<optgroup label="<?php echo strtoupper((string) $type['rejection_type']); ?>">
																	<?php foreach ($rejectionResult as $reject) {
																		if ($type['rejection_type'] == $reject['rejection_type']) {
																	?>
																			<option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($cd4QueryInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?= $reject['rejection_reason_name']; ?></option>
																	<?php }
																	} ?>
																</optgroup>
															<?php }
															if ($general->isLISInstance() === false) { ?>
																<option value="other">Other (Please Specify) </option>
															<?php } ?>
														</select>
														<input type="text" class="form-control newRejectionReason" name="newRejectionReason" id="newRejectionReason" placeholder="Rejection Reason" title="Please enter rejection reason" style="width:100%;display:none;margin-top:2px;">
													</div>
												</div>

												<div class="col-md-6 rejectionReason" style="display:none;">
													<label class="col-lg-5 control-label labels" for="rejectionDate">Rejection Date </label>
													<div class="col-lg-7">
														<input value="<?php echo DateUtility::humanReadableDateFormat($cd4QueryInfo['rejection_on']); ?>" class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" title="Please select rejection date" />
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-md-6 cd4Result">
													<label class="col-lg-5 control-label" for="cd4Result">Sample Results (CD4 count -Absolute value)<span class="mandatory">*</span> </label>
													<div class="col-lg-7 resultInputContainer">
														<input value="<?= $cd4QueryInfo['cd4_result']; ?>" class="form-control isRequired" id="cd4Result" name="cd4Result" placeholder="CD4 Result" title="Please enter CD4 result" style="width:100%;" onchange="getCrAgResults(this.value);"  />
													</div>
												</div>
												<div class="col-md-6 cd4Result">
													<label class="col-lg-5 control-label" for="cd4ResultPercentage">Sample Results (Percentage) <span class="mandatory">*</span></label>
													<div class="col-lg-7">
														<input type="text" value="<?= $cd4QueryInfo['cd4_result_percentage']; ?>" class="form-control isRequired" id="cd4ResultPercentage" name="cd4ResultPercentage" placeholder="CD4 Result Value percentage" title="Please enter CD4 Result Value percentage" style="width:100%;" />
													</div>
												</div>
											</div>
											<div class="row crAgResults" style="display:none;">
                                                                 <div class="col-md-6 cd4Result">
																 <label class="col-lg-5 control-label" for="cd4Result">CrAg test Result (If CD4 Count <= 200)</label>
																 <div class="col-lg-7">
                                                                           <select class="form-control" id="crAgResults" name="crAgResults" placeholder="CrAg Test Results" title="Please select CrAg Test results" style="width:100%;">
                                                                                <option value="">--Select--</option>
																				<option value="positive" <?php echo ($cd4QueryInfo['crag_test_results'] == "positive") ? 'selected="selected"' : ''; ?>>Positive</option>
                                                                                <option value="negative" <?php echo ($cd4QueryInfo['crag_test_results'] == "negative") ? 'selected="selected"' : ''; ?>>Negative</option>
                                                                                <option value="intermediate" <?php echo ($cd4QueryInfo['crag_test_results'] == "intermediate") ? 'selected="selected"' : ''; ?>>Indeterminate</option>
                                                                                <option value="testNotDone" <?php echo ($cd4QueryInfo['crag_test_results'] == "testNotDone") ? 'selected="selected"' : ''; ?>>Test not done</option>
																			</select>
                                                                      </div>
                                                                 </div>
                                                       </div>
											<div class="row">
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="approvedOnDateTime">Approved On <span class="mandatory">*</span></label>
													<div class="col-lg-7">
														<input type="text" name="approvedOnDateTime" id="approvedOnDateTime" class="dateTime form-control isRequired" placeholder="Approved on" title="Please enter the Approved on" value="<?php echo $cd4QueryInfo['result_approved_datetime']; ?>" />
													</div>
												</div>
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="approvedBy">Approved By <span class="mandatory">*</span></label>
													<div class="col-lg-7">
														<select name="approvedBy" id="approvedBy" class="form-control isRequired" title="Please choose approved by" <?php echo $labFieldDisabled; ?>>
															<option value="">-- Select --</option>
															<?php foreach ($userResult as $uName) { ?>
																<option value="<?php echo $uName['user_id']; ?>" <?php echo ($cd4QueryInfo['result_approved_by'] == $uName['user_id']) ? "selected=selected" : ""; ?>><?php echo ($uName['user_name']); ?></option>
															<?php } ?>
														</select>
													</div>
												</div>
											</div>
											<div class="row">

												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="resultDispatchedOn">Date Results Dispatched <span class="mandatory">*</span></label>
													<div class="col-lg-7">
														<input type="text" class="form-control dateTime isRequired" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="Result Dispatched Date" title="Please select result dispatched date" <?php echo $labFieldDisabled; ?> value="<?php echo $cd4QueryInfo['result_dispatched_datetime']; ?>" />
													</div>
												</div>
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="labComments">Lab Tech. Comments </label>
													<div class="col-lg-7">
														<textarea class="form-control" name="labComments" id="labComments" placeholder="Lab comments" <?php echo $labFieldDisabled; ?>><?php echo trim((string) $cd4QueryInfo['lab_tech_comments']); ?></textarea>
													</div>
												</div>
											</div>
										</div>
									</div>
							</div>
							<div class="box-footer">
								<!-- BARCODESTUFF START -->
								<?php if (isset($global['bar_code_printing']) && $global['bar_code_printing'] == 'zebra-printer') { ?>
									<div id="printer_data_loading" style="display:none"><span id="loading_message">Loading Printer Details...</span><br />
										<div class="progress" style="width:100%">
											<div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
											</div>
										</div>
									</div> <!-- /printer_data_loading -->
									<div id="printer_details" style="display:none">
										<span id="selected_printer">No printer selected!</span>
										<button type="button" class="btn btn-success" onclick="changePrinter()">Change/Retry</button>
									</div><br /> <!-- /printer_details -->
									<div id="printer_select" style="display:none">
										Zebra Printer Options<br />
										Printer: <select id="printers"></select>
									</div> <!-- /printer_select -->
								<?php } ?>
								<!-- BARCODESTUFF END -->
								<a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
								<input type="hidden" name="saveNext" id="saveNext" />
								<?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
									<input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
									<input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
								<?php } ?>
								<input type="hidden" name="cd4SampleId" id="cd4SampleId" value="<?= ($cd4QueryInfo['cd4_id']); ?>" />
								<input type="hidden" name="provinceId" id="provinceId" />
								<a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateSaveNow();return false;">Save and Next</a>
								<a href="/cd4/requests/cd4-requests.php" class="btn btn-default"> Cancel</a>
							</div>
							<input type="hidden" id="selectedSample" value="" name="selectedSample" class="" />
							<input type="hidden" name="countryFormId" id="countryFormId" value="<?php echo $arr['vl_form']; ?>" />

							</form>
						</div>
	</section>
</div>
<script>
	$(document).ready(function() {
		$("#vlLog").on('keyup keypress blur change paste', function() {
			if ($(this).val() != '') {
				if ($(this).val() != $(this).val().replace(/[^\d\.]/g, "")) {
					$(this).val('');
					alert('Please enter only numeric values for Viral Load Log')
				}
			}
		});
		$('#labId').select2({
			placeholder: "Select Lab Name"
		});
		$('#reviewedBy').select2({
			placeholder: "Select Reviewed By"
		});
		$('#approvedBy').select2({
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
		__clone = $("#cd4RequestFormRwd .labSection").clone();
		reason = ($("#reasonForResultChanges").length) ? $("#reasonForResultChanges").val() : '';
		result = ($("#vlResult").length) ? $("#vlResult").val() : '';
	});

	$("#isSampleRejected").on("change", function() {
		if ($(this).val() == 'yes') {
			$('.rejectionReason').show();
			$('#rejectionReason').addClass('isRequired');
		} else {
			$('.rejectionReason').hide();
			$('#rejectionReason').removeClass('isRequired');
			$('#rejectionReason').val('');
			$('#rejectionDate').val('');

		}
	});


	$(".labSection").on("change", function() {
		if ($.trim(result) != '') {
			if ($(".labSection").serialize() == $(__clone).serialize()) {
				$(".reasonForResultChanges").css("display", "none");
				$("#reasonForResultChanges").removeClass("isRequired");
			} else {
				$(".reasonForResultChanges").css("display", "block");
				$("#reasonForResultChanges").addClass("isRequired");
			}
		}
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

	function validateNow() {
		flag = deforayValidator.init({
			formId: 'cd4RequestFormRwd'
		});

		$('.isRequired').each(function() {
			($(this).val() == '') ? $(this).css('background-color', '#FFFF99'): $(this).css('background-color', '#FFFFFF')
		});
		if (flag) {
			$.blockUI();
			document.getElementById('cd4RequestFormRwd').submit();
		}
	}

	function getCrAgResults(cd4Count)
     {
          if(cd4Count <= 200){
               $(".crAgResults").show();
          }
          else{
               $(".crAgResults").hide();
          }
     }
</script>
