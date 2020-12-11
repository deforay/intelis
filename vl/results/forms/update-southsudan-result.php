<?php
ob_start();

//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);
//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);


$province = '';
$province .= "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
	$province .= "<option value='" . $provinceName['province_name'] . "##" . $provinceName['province_code'] . "'>" . ucwords($provinceName['province_name']) . "</option>";
}

$facility = $general->generateSelectOptions($healthFacilities, $vlQueryInfo['facility_id'], '-- Select --');

$artRegimenQuery = "SELECT DISTINCT headings FROM r_vl_art_regimen";
$artRegimenResult = $db->rawQuery($artRegimenQuery);
$aQuery = "SELECT * from r_vl_art_regimen WHERE art_status = 'active'";
$aResult = $db->query($aQuery);

//facility details
if (isset($vlQueryInfo['facility_id']) && $vlQueryInfo['facility_id'] > 0) {
	$facilityQuery = "SELECT * from facility_details where facility_id='" . $vlQueryInfo['facility_id'] . "' AND status='active'";
	$facilityResult = $db->query($facilityQuery);
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
$stateName = $facilityResult[0]['facility_state'];
if (trim($stateName) != '') {
	$stateQuery = "SELECT * from province_details where province_name='" . $stateName . "'";
	$stateResult = $db->query($stateQuery);
}
if (!isset($stateResult[0]['province_code']) || $stateResult[0]['province_code'] == '') {
	$stateResult[0]['province_code'] = '';
}
//district details
// $districtResult = array();
// if (trim($stateName) != '') {
//   $districtQuery = "SELECT DISTINCT facility_district from facility_details where facility_state='" . $stateName . "' AND status='active'";
//   $districtResult = $db->query($districtQuery);
//   $facilityQuery = "SELECT * from facility_details where `status`='active' AND facility_type='2'";
//   $lResult = $db->query($facilityQuery);
// }

//set reason for changes history
$rch = '';
$allChange = array();
if (isset($vlQueryInfo['reason_for_vl_result_changes']) && $vlQueryInfo['reason_for_vl_result_changes'] != '') {
	$rch .= '<h4>Result Changes History</h4>';
	$rch .= '<table style="width:100%;">';
	$rch .= '<thead><tr style="border-bottom:2px solid #d3d3d3;"><th style="width:20%;">USER</th><th style="width:60%;">MESSAGE</th><th style="width:20%;text-align:center;">DATE</th></tr></thead>';
	$rch .= '<tbody>';
	$allChange = json_decode($vlQueryInfo['reason_for_vl_result_changes'], true);
	if (count($allChange) > 0) {
		$allChange = array_reverse($allChange);
		foreach ($allChange as $change) {
			$usrQuery = "SELECT user_name FROM user_details where user_id='" . $change['usr'] . "'";
			$usrResult = $db->rawQuery($usrQuery);
			$name = '';
			if (isset($usrResult[0]['user_name'])) {
				$name = ucwords($usrResult[0]['user_name']);
			}
			$expStr = explode(" ", $change['dtime']);
			$changedDate = $general->humanDateFormat($expStr[0]) . " " . $expStr[1];
			$rch .= '<tr><td>' . $name . '</td><td>' . ucfirst($change['msg']) . '</td><td style="text-align:center;">' . $changedDate . '</td></tr>';
		}
		$rch .= '</tbody>';
		$rch .= '</table>';
	}
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
		<h1><i class="fa fa-edit"></i> VIRAL LOAD LABORATORY REQUEST FORM </h1>
		<ol class="breadcrumb">
			<li><a href="/dashboard/index.php"><i class="fa fa-dashboard"></i> Home</a></li>
			<li class="active">Enter Vl Request</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">

		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
			</div>
			<div class="box-body">
				<!-- form start -->
				<form class="form-inline" method="post" name="vlRequestFormSudan" id="vlRequestFormSudan" autocomplete="off" action="updateVlTestResultHelper.php">
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
											<input type="text" class="form-control " id="sampleCode" name="sampleCode" placeholder="Enter Sample ID" title="Please enter sample id" value="<?php echo $vlQueryInfo['sample_code']; ?>" <?php echo $disable; ?> style="width:100%;" />
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="sampleReordered">
												<input type="checkbox" class="" id="sampleReordered" name="sampleReordered" value="yes" <?php echo (trim($vlQueryInfo['sample_reordered']) == 'yes') ? 'checked="checked"' : '' ?> <?php echo $disable; ?> title="Please check sample reordered"> Sample Reordered
											</label>
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
													<option value="<?php echo $provinceName['province_name'] . "##" . $provinceName['province_code']; ?>" <?php echo ($facilityResult[0]['facility_state'] . "##" . $stateResult[0]['province_code'] == $provinceName['province_name'] . "##" . $provinceName['province_code']) ? "selected='selected'" : "" ?>><?php echo ucwords($provinceName['province_name']); ?></option>;
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
												foreach ($districtResult as $districtName) {
												?>
													<option value="<?php echo $districtName['facility_district']; ?>" <?php echo ($facilityResult[0]['facility_district'] == $districtName['facility_district']) ? "selected='selected'" : "" ?>><?php echo ucwords($districtName['facility_district']); ?></option>
												<?php
												}
												?>
											</select>
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="fName">Clinic/Health Center <span class="mandatory">*</span></label>
											<select class="form-control " id="fName" name="fName" title="Please select clinic/health center name" <?php echo $disable; ?> style="width:100%;" onchange="autoFillFacilityCode();">
												<?= $facility; ?>
											</select>
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="fCode">Clinic/Health Center Code </label>
											<input type="text" class="form-control" style="width:100%;" name="fCode" id="fCode" placeholder="Clinic/Health Center Code" title="Please enter clinic/health center code" value="<?php echo $facilityResult[0]['facility_code']; ?>" <?php echo $disable; ?>>
										</div>
									</div>
								</div>
								<div class="row facilityDetails" style="display:<?php echo (trim($facilityResult[0]['facility_emails']) != '' || trim($facilityResult[0]['facility_mobile_numbers']) != '' || trim($facilityResult[0]['contact_person']) != '') ? '' : 'none'; ?>;">
									<div class="col-xs-2 col-md-2 femails" style="display:<?php echo (trim($facilityResult[0]['facility_emails']) != '') ? '' : 'none'; ?>;"><strong>Clinic/Health Center Email(s)</strong></div>
									<div class="col-xs-2 col-md-2 femails facilityEmails" style="display:<?php echo (trim($facilityResult[0]['facility_emails']) != '') ? '' : 'none'; ?>;"><?php echo $facilityResult[0]['facility_emails']; ?></div>
									<div class="col-xs-2 col-md-2 fmobileNumbers" style="display:<?php echo (trim($facilityResult[0]['facility_mobile_numbers']) != '') ? '' : 'none'; ?>;"><strong>Clinic/Health Center Mobile No.(s)</strong></div>
									<div class="col-xs-2 col-md-2 fmobileNumbers facilityMobileNumbers" style="display:<?php echo (trim($facilityResult[0]['facility_mobile_numbers']) != '') ? '' : 'none'; ?>;"><?php echo $facilityResult[0]['facility_mobile_numbers']; ?></div>
									<div class="col-xs-2 col-md-2 fContactPerson" style="display:<?php echo (trim($facilityResult[0]['contact_person']) != '') ? '' : 'none'; ?>;"><strong>Clinic Contact Person -</strong></div>
									<div class="col-xs-2 col-md-2 fContactPerson facilityContactPerson" style="display:<?php echo (trim($facilityResult[0]['contact_person']) != '') ? '' : 'none'; ?>;"><?php echo ucwords($facilityResult[0]['contact_person']); ?></div>
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
												<option value="<?php echo base64_encode($implementingPartner['i_partner_id']); ?>" <?php echo ($implementingPartner['i_partner_id'] == $vlQueryInfo['implementing_partner']) ? 'selected="selected"' : ''; ?>><?php echo ucwords($implementingPartner['i_partner_name']); ?></option>
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
												<option value="<?php echo base64_encode($fundingSource['funding_source_id']); ?>" <?php echo ($fundingSource['funding_source_id'] == $vlQueryInfo['funding_source']) ? 'selected="selected"' : ''; ?>><?php echo ucwords($fundingSource['funding_source_name']); ?></option>
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
											<input type="text" name="artNo" id="artNo" class="form-control " placeholder="Enter ART Number" title="Enter art number" value="<?php echo $vlQueryInfo['patient_art_no']; ?>" <?php echo $disable; ?> />
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="dob">Date of Birth </label>
											<input type="text" name="dob" id="dob" class="form-control date" placeholder="Enter DOB" title="Enter dob" value="<?php echo $vlQueryInfo['patient_dob']; ?>" <?php echo $disable; ?> />
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="ageInYears">If DOB unknown, Age in Year </label>
											<input type="text" name="ageInYears" id="ageInYears" class="form-control checkNum" maxlength="2" placeholder="Age in Year" title="Enter age in years" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['patient_age_in_years']; ?>" />
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="ageInMonths">If Age
												< 1, Age in Month </label> <input type="text" name="ageInMonths" id="ageInMonths" class="form-control checkNum" maxlength="2" placeholder="Age in Month" title="Enter age in months" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['patient_age_in_months']; ?>" />
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="patientFirstName">Patient Name (First Name, Last Name) </label>
											<input type="text" name="patientFirstName" id="patientFirstName" class="form-control" placeholder="Enter Patient Name" title="Enter patient name" <?php echo $disable; ?> value="<?php echo $patientFirstName; ?>" />
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="gender">Gender</label><br>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="genderMale" name="gender" value="male" title="Please check gender" <?php echo $disable; ?> <?php echo ($vlQueryInfo['patient_gender'] == 'male') ? "checked='checked'" : "" ?>> Male
											</label>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="genderFemale" name="gender" value="female" title="Please check gender" <?php echo $disable; ?> <?php echo ($vlQueryInfo['patient_gender'] == 'female') ? "checked='checked'" : "" ?>> Female
											</label>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="genderNotRecorded" name="gender" value="not_recorded" title="Please check gender" <?php echo $disable; ?> <?php echo ($vlQueryInfo['patient_gender'] == 'not_recorded') ? "checked='checked'" : "" ?>>Not Recorded
											</label>
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="gender">Gender</label><br>
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
											<input type="text" name="patientPhoneNumber" id="patientPhoneNumber" class="form-control checkNum" maxlength="15" placeholder="Enter Phone Number" title="Enter phone number" value="<?php echo $vlQueryInfo['patient_mobile_number']; ?>" <?php echo $disable; ?> />
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
												<label for="specimenType">Sample Type <span class="mandatory">*</span></label>
												<select name="specimenType" id="specimenType" class="form-control " title="Please choose sample type" <?php echo $disable; ?>>
													<option value=""> -- Select -- </option>
													<?php
													foreach ($sResult as $name) {
													?>
														<option value="<?php echo $name['sample_id']; ?>" <?php echo ($vlQueryInfo['sample_type'] == $name['sample_id']) ? "selected='selected'" : "" ?>><?php echo ucwords($name['sample_name']); ?></option>
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
															<optgroup label="<?php echo ucwords($heading['headings']); ?>">
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
														<option value="poor" <?php echo ($vlQueryInfo['arv_adherance_percentage'] == 'poor') ? "selected='selected'" : "" ?>>Poor < 85%</option> </select> </div> </div> </div> <div class="row femaleSection" style="display:<?php echo ($vlQueryInfo['patient_gender'] == 'female' || $vlQueryInfo['patient_gender'] == '' || $vlQueryInfo['patient_gender'] == null) ? "" : "none" ?>" ;>
																<div class="col-xs-3 col-md-3">
																	<div class="form-group">
																		<label for="patientPregnant">Is Patient Pregnant? </label><br>
																		<label class="radio-inline">
																			<input type="radio" class="" id="pregYes" name="patientPregnant" value="yes" title="Please check one" <?php echo $disable; ?> <?php echo ($vlQueryInfo['is_patient_pregnant'] == 'yes') ? "checked='checked'" : "" ?>> Yes
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
																			<input type="radio" class="" id="breastfeedingYes" name="breastfeeding" value="yes" title="Please check one" <?php echo $disable; ?> <?php echo ($vlQueryInfo['is_patient_breastfeeding'] == 'yes') ? "checked='checked'" : "" ?>> Yes
																		</label>
																		<label class="radio-inline">
																			<input type="radio" class="" id="breastfeedingNo" name="breastfeeding" value="no" <?php echo $disable; ?> <?php echo ($vlQueryInfo['is_patient_breastfeeding'] == 'no') ? "checked='checked'" : "" ?>> No
																		</label>
																	</div>
																</div>
																<div class="col-xs-3 col-md-3" style="display:none;">
																	<div class="form-group">
																		<label for="">How long has this patient been on treatment ? </label>
																		<input type="text" class="form-control" id="treatPeriod" name="treatPeriod" placeholder="Enter Treatment Period" <?php echo $disable; ?> title="Please enter how long has this patient been on treatment" value="<?php echo $vlQueryInfo['treatment_initiation']; ?>" />
																	</div>
																</div>
												</div>
											</div>
											<div class="box box-primary">
												<div class="box-header with-border">
													<h3 class="box-title">Indication for Viral Load Testing</h3><small> (Please tick one):(To be completed by clinician)</small>
												</div>
												<div class="box-body">
													<div class="row">
														<div class="col-md-6">
															<div class="form-group">
																<div class="col-lg-12">
																	<label class="radio-inline">
																		<?php
																		$checked = '';
																		$display = '';
																		if (trim($vlQueryInfo['reason_for_vl_testing']) == 'routine') {
																			$checked = 'checked="checked"';
																			$display = 'block';
																		} else {
																			$checked = '';
																			$display = 'none';
																		}
																		?>
																		<input type="radio" class="" id="rmTesting" name="stViralTesting" value="routine" title="Please check routine monitoring" <?php echo $disable; ?> <?php echo $checked; ?> onclick="showTesting('rmTesting');">
																		<strong>Routine Monitoring</strong>
																	</label>
																</div>
															</div>
														</div>
													</div>
													<div class="row rmTesting hideTestData" style="display:<?php echo $display; ?>;">
														<div class="col-md-6">
															<label class="col-lg-5 control-label">Date of last viral load test</label>
															<div class="col-lg-7">
																<input type="text" class="form-control date viralTestData" id="rmTestingLastVLDate" name="rmTestingLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" value="<?php echo (trim($vlQueryInfo['last_vl_date_routine']) != '' && $vlQueryInfo['last_vl_date_routine'] != null && $vlQueryInfo['last_vl_date_routine'] != '0000-00-00') ? $general->humanDateFormat($vlQueryInfo['last_vl_date_routine']) : ''; ?>" <?php echo $disable; ?> />
															</div>
														</div>
														<div class="col-md-6">
															<label for="rmTestingVlValue" class="col-lg-3 control-label">VL Value</label>
															<div class="col-lg-7">
																<input type="text" class="form-control checkNum viralTestData" id="rmTestingVlValue" name="rmTestingVlValue" placeholder="Enter VL Value" title="Please enter vl value" value="<?php echo $vlQueryInfo['last_vl_result_routine']; ?>" <?php echo $disable; ?> />
																(copies/ml)
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
																		if (trim($vlQueryInfo['reason_for_vl_testing']) == 'failure') {
																			$checked = 'checked="checked"';
																			$display = 'block';
																		} else {
																			$checked = '';
																			$display = 'none';
																		}
																		?>
																		<input type="radio" class="" id="repeatTesting" name="stViralTesting" value="failure" title="Repeat VL test after suspected treatment failure adherence counseling" <?php echo $disable; ?> <?php echo $checked; ?> onclick="showTesting('repeatTesting');">
																		<strong>Repeat VL test after suspected treatment failure adherence counselling </strong>
																	</label>
																</div>
															</div>
														</div>
													</div>
													<div class="row repeatTesting hideTestData" style="display: <?php echo $display; ?>;">
														<div class="col-md-6">
															<label class="col-lg-5 control-label">Date of last viral load test</label>
															<div class="col-lg-7">
																<input type="text" class="form-control date viralTestData" id="repeatTestingLastVLDate" name="repeatTestingLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" value="<?php echo (trim($vlQueryInfo['last_vl_date_failure_ac']) != '' && $vlQueryInfo['last_vl_date_failure_ac'] != null && $vlQueryInfo['last_vl_date_failure_ac'] != '0000-00-00') ? $general->humanDateFormat($vlQueryInfo['last_vl_date_failure_ac']) : ''; ?>" <?php echo $disable; ?> />
															</div>
														</div>
														<div class="col-md-6">
															<label for="repeatTestingVlValue" class="col-lg-3 control-label">VL Value</label>
															<div class="col-lg-7">
																<input type="text" class="form-control checkNum viralTestData" id="repeatTestingVlValue" name="repeatTestingVlValue" placeholder="Enter VL Value" title="Please enter vl value" value="<?php echo $vlQueryInfo['last_vl_result_failure_ac']; ?>" <?php echo $disable; ?> />
																(copies/ml)
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
																		if (trim($vlQueryInfo['reason_for_vl_testing']) == 'suspect') {
																			$checked = 'checked="checked"';
																			$display = 'block';
																		} else {
																			$checked = '';
																			$display = 'none';
																		}
																		?>
																		<input type="radio" class="" id="suspendTreatment" name="stViralTesting" value="suspect" title="Suspect Treatment Failure" <?php echo $disable; ?> <?php echo $checked; ?> onclick="showTesting('suspendTreatment');">
																		<strong>Suspect Treatment Failure</strong>
																	</label>
																</div>
															</div>
														</div>
													</div>
													<div class="row suspendTreatment hideTestData" style="display: <?php echo $display; ?>;">
														<div class="col-md-6">
															<label class="col-lg-5 control-label">Date of last viral load test</label>
															<div class="col-lg-7">
																<input type="text" class="form-control date viralTestData" id="suspendTreatmentLastVLDate" name="suspendTreatmentLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" value="<?php echo (trim($vlQueryInfo['last_vl_date_failure']) != '' && $vlQueryInfo['last_vl_date_failure'] != null && $vlQueryInfo['last_vl_date_failure'] != '0000-00-00') ? $general->humanDateFormat($vlQueryInfo['last_vl_date_failure']) : ''; ?>" <?php echo $disable; ?> />
															</div>
														</div>
														<div class="col-md-6">
															<label for="suspendTreatmentVlValue" class="col-lg-3 control-label">VL Value</label>
															<div class="col-lg-7">
																<input type="text" class="form-control checkNum viralTestData" id="suspendTreatmentVlValue" name="suspendTreatmentVlValue" placeholder="Enter VL Value" title="Please enter vl value" value="<?php echo $vlQueryInfo['last_vl_result_failure']; ?>" <?php echo $disable; ?> />
																(copies/ml)
															</div>
														</div>
													</div>
													<div class="row">
														<div class="col-md-4">
															<label for="reqClinician" class="col-lg-5 control-label">Request Clinician</label>
															<div class="col-lg-7">
																<input type="text" class="form-control" id="reqClinician" name="reqClinician" placeholder="Request Clinician" title="Please enter request clinician" value="<?php echo $vlQueryInfo['request_clinician_name']; ?>" <?php echo $disable; ?> />
															</div>
														</div>
														<div class="col-md-4">
															<label for="reqClinicianPhoneNumber" class="col-lg-5 control-label">Phone Number</label>
															<div class="col-lg-7">
																<input type="text" class="form-control checkNum" id="reqClinicianPhoneNumber" name="reqClinicianPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter request clinician phone number" value="<?php echo $vlQueryInfo['request_clinician_phone_number']; ?>" <?php echo $disable; ?> />
															</div>
														</div>
														<div class="col-md-4">
															<label class="col-lg-5 control-label" for="requestDate">Request Date </label>
															<div class="col-lg-7">
																<input type="text" class="form-control date" id="requestDate" name="requestDate" placeholder="Request Date" title="Please select request date" value="<?php echo $vlQueryInfo['test_requested_on']; ?>" <?php echo $disable; ?> />
															</div>
														</div>
													</div>
													<div class="row" style="display:none;">
														<div class="col-md-4">
															<label class="col-lg-5 control-label" for="emailHf">Email for HF </label>
															<div class="col-lg-7">
																<input type="text" class="form-control isEmail" id="emailHf" name="emailHf" placeholder="Email for HF" title="Please enter email for hf" value="<?php echo $facilityResult[0]['facility_emails']; ?>" <?php echo $disable; ?> />
															</div>
														</div>
													</div>
												</div>
											</div>
											<div class="box box-primary" style="<?php if ($sarr['user_type'] == 'remoteuser') { ?> pointer-events:none;<?php } ?>">
												<div class="box-header with-border">
													<h3 class="box-title">Laboratory Information</h3>
												</div>
												<div class="box-body">
													<div class="row">
														<div class="col-md-4">
															<label for="labId" class="col-lg-5 control-label">Lab Name<span class="mandatory">*</span> </label>
															<div class="col-lg-7">
																<select name="labId" id="labId" class="isRequired form-control labSection" title="Please choose lab" onchange="autoFillFocalDetails();">
																	<?= $general->generateSelectOptions($testingLabs, $vlQueryInfo['lab_id'], '-- Select --'); ?>
																</select>
															</div>
														</div>
														<div class="col-md-4">
															<label for="vlFocalPerson" class="col-lg-5 control-label">VL Focal Person </label>
															<div class="col-lg-7">
																<input type="text" class="form-control labSection" id="vlFocalPerson" name="vlFocalPerson" placeholder="VL Focal Person" title="Please enter vl focal person name" value="<?php echo $vlQueryInfo['vl_focal_person']; ?>" />
															</div>
														</div>
														<div class="col-md-4">
															<label for="vlFocalPersonPhoneNumber" class="col-lg-5 control-label">VL Focal Person Phone Number</label>
															<div class="col-lg-7">
																<input type="text" class="form-control checkNum labSection" id="vlFocalPersonPhoneNumber" name="vlFocalPersonPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter vl focal person phone number" value="<?php echo $vlQueryInfo['vl_focal_person_phone_number']; ?>" />
															</div>
														</div>
													</div>
													<div class="row">
														<div class="col-md-4">
															<label class="col-lg-5 control-label" for="sampleReceivedAtHubOn">Date Sample Received at Hub (PHL) </label>
															<div class="col-lg-7">
																<input type="text" class="form-control dateTime" id="sampleReceivedAtHubOn" name="sampleReceivedAtHubOn" placeholder="Sample Received at HUB Date" title="Please select sample received at HUB date" value="<?php echo $vlQueryInfo['sample_received_at_hub_datetime']; ?>" />
															</div>
														</div>
														<div class="col-md-4">
															<label class="col-lg-5 control-label" for="sampleReceivedOn">Date Sample Received at Testing Lab </label>
															<div class="col-lg-7">
																<input type="text" class="form-control labSection" id="sampleReceivedOn" name="sampleReceivedOn" placeholder="Sample Received Date" title="Please select sample received date" value="<?php echo $vlQueryInfo['sample_received_at_vl_lab_datetime']; ?>" />
															</div>
														</div>
														<div class="col-md-4">
															<label class="col-lg-5 control-label" for="sampleTestingDateAtLab">Sample Testing Date<span class="mandatory">*</span> </label>
															<div class="col-lg-7">
																<input type="text" class="isRequired form-control labSection" id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="Sample Testing Date" title="Please select sample testing date" value="<?php echo $vlQueryInfo['sample_tested_datetime']; ?>" />
															</div>
														</div>

													</div>
													<div class="row">
														<div class="col-md-4">
															<label for="testingPlatform" class="col-lg-5 control-label">VL Testing Platform<span class="mandatory">*</span> </label>
															<div class="col-lg-7">
																<select name="testingPlatform" id="testingPlatform" class="isRequired form-control labSection" title="Please choose VL Testing Platform">
																	<option value="">-- Select --</option>
																	<?php foreach ($importResult as $mName) { ?>
																		<option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit']; ?>" <?php echo ($vlQueryInfo['vl_test_platform'] == $mName['machine_name']) ? 'selected="selected"' : ''; ?>><?php echo $mName['machine_name']; ?></option>
																	<?php
																	}
																	?>
																</select>
															</div>
														</div>
														<div class="col-md-4">
															<label class="col-lg-5 control-label" for="noResult">Sample Rejected ? </label>
															<div class="col-lg-7">
																<label class="radio-inline">
																	<input class="labSection" id="noResultYes" name="noResult" value="yes" title="Please check one" type="radio" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'checked="checked"' : ''; ?>> Yes
																</label>
																<label class="radio-inline">
																	<input class="labSection" id="noResultNo" name="noResult" value="no" title="Please check one" type="radio" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'no') ? 'checked="checked"' : ''; ?>> No
																</label>
															</div>
														</div>
														<div class="col-md-4 rejectionReason" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;">
															<label class="col-lg-5 control-label" for="rejectionReason">Rejection Reason<span class="mandatory">*</span> </label>
															<div class="col-lg-7">
																<select name="rejectionReason" id="rejectionReason" class="form-control labSection" title="Please choose reason" onchange="checkRejectionReason();">
																	<option value="">-- Select --</option>
																	<?php foreach ($rejectionTypeResult as $type) { ?>
																		<optgroup label="<?php echo ucwords($type['rejection_type']); ?>">
																			<?php
																			foreach ($rejectionResult as $reject) {
																				if ($type['rejection_type'] == $reject['rejection_type']) {
																			?>
																					<option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($vlQueryInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?php echo ucwords($reject['rejection_reason_name']); ?></option>
																			<?php
																				}
																			}
																			?>
																		</optgroup>
																	<?php } ?>
																	<option value="other">Other (Please Specify) </option>
																</select>
																<input type="text" class="form-control newRejectionReason" name="newRejectionReason" id="newRejectionReason" placeholder="Rejection Reason" title="Please enter rejection reason" style="width:100%;display:none;margin-top:2px;">
															</div>
														</div>
														<div class="col-md-4 vlResult" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'none' : 'block'; ?>;">
															<label class="col-lg-5 control-label" for="vlResult">Viral Load Result (copiesl/ml) <span class="mandatory">*</span></label>
															<div class="col-lg-7">
																<input type="text" class="<?php echo ($vlQueryInfo['is_sample_rejected'] == 'no' && $vlQueryInfo['result'] != 'Target Not Detected' && $vlQueryInfo['result'] == 'Below Detection Level') ? 'isRequired' : ''; ?> form-control labSection" id="vlResult" name="vlResult" placeholder="Viral Load Result" title="Please enter viral load result" value="<?php echo $vlQueryInfo['result_value_absolute']; ?>" <?php echo ($vlQueryInfo['result'] == 'Target Not Detected' || $vlQueryInfo['result'] == 'Below Detection Level') ? 'readonly="readonly"' : ''; ?> style="width:100%;" onchange="calculateLogValue(this);" />

																<input type="checkbox" class="labSection" id="tnd" name="tnd" value="yes" <?php echo ($vlQueryInfo['result'] == 'Target Not Detected') ? 'checked="checked"' : '';
																																			echo ($vlQueryInfo['result'] == 'Below Detection Level') ? 'disabled="disabled"' : '' ?> title="Please check tnd"> Target Not Detected<br>
																<input type="checkbox" class="labSection" id="bdl" name="bdl" value="yes" <?php echo ($vlQueryInfo['result'] == 'Below Detection Level') ? 'checked="checked"' : '';
																																			echo ($vlQueryInfo['result'] == 'Target Not Detected') ? 'disabled="disabled"' : '' ?> title="Please check bdl"> Below Detection Level
															</div>
														</div>
													</div>
													<div class="row">
														<div class="col-md-4 vlResult" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'none' : 'block'; ?>;">
															<label class="col-lg-5 control-label" for="vlLog">Viral Load Log </label>
															<div class="col-lg-7">
																<input type="text" class="form-control labSection" id="vlLog" name="vlLog" placeholder="Viral Load Log" title="Please enter viral load log" value="<?php echo $vlQueryInfo['result_value_log']; ?>" <?php echo ($vlQueryInfo['result'] == 'Target Not Detected' || $vlQueryInfo['result'] == 'Below Detection Level') ? 'readonly="readonly"' : ''; ?> style="width:100%;" onchange="calculateLogValue(this);" />
															</div>
														</div>
														<div class="col-md-4">
															<label class="col-lg-5 control-label" for="resultDispatchedOn">Date Results Dispatched </label>
															<div class="col-lg-7">
																<input type="text" class="form-control labSection" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="Result Dispatched Date" title="Please select result dispatched date" value="<?php echo $vlQueryInfo['result_dispatched_datetime']; ?>" />
															</div>
														</div>
														<div class="col-md-4">
															<label class="col-lg-5 control-label" for="testedBy">Tested By </label>
															<div class="col-lg-7">
																<select name="testedBy" id="testedBy" class="select2 form-control" title="Please choose approved by">
																	<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['tested_by'], '-- Select --'); ?>
																</select>
															</div>
														</div>
													</div><br />
													<div class="row">
														<div class="col-md-4">
															<label class="col-lg-5 control-label" for="approvedBy">Approved By </label>
															<div class="col-lg-7">
																<select name="approvedBy" id="approvedBy" class="form-control labSection" title="Please choose approved by">
																	<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_approved_by'], '-- Select --'); ?>
																</select>
															</div>
														</div>
														<div class="col-md-4">
															<label class="col-lg-5 control-label" for="approvedOnDateTime">Approved On </label>
															<div class="col-lg-7">
																<input type="text" value="<?php echo $vlQueryInfo['result_approved_datetime']; ?>" class="form-control dateTime" id="approvedOnDateTime" name="approvedOnDateTime" placeholder="e.g 09-Jan-1992 05:30" <?php echo $labFieldDisabled; ?> style="width:100%;" />
															</div>
														</div>
														<div class="col-md-4" style="<?php echo (($sarr['user_type'] == 'remoteuser')) ? 'display:none;' : ''; ?>">
															<label class="col-lg-5 control-label" for="status">Status <span class="mandatory">*</span></label>
															<div class="col-lg-7">
																<select class="form-control labSection  <?php echo (($sarr['user_type'] != 'remoteuser')) ? 'isRequired' : ''; ?>" id="status" name="status" title="Please select test status">
																	<option value="">-- Select --</option>
																	<?php
																	foreach ($statusResult as $status) {
																	?>
																		<option value="<?php echo $status['status_id']; ?>" <?php echo ($vlQueryInfo['result_status'] == $status['status_id']) ? 'selected="selected"' : ''; ?>><?php echo ucwords($status['status_name']); ?></option>
																	<?php } ?>
																</select>
															</div>
														</div>
													</div>
													<div class="row">
														<div class="col-md-8">
															<label class="col-lg-2 control-label" for="labComments">Lab Tech. Comments </label>
															<div class="col-lg-10">
																<textarea class="form-control labSection" name="labComments" id="labComments" placeholder="Lab comments" style="width:100%"><?php echo trim($vlQueryInfo['approver_comments']); ?></textarea>
															</div>
														</div>

													</div>

													<div class="row reasonForResultChanges" style="display:none;">
														<br>
														<div class="col-md-6 ">
															<label class="col-lg-2 control-label" for="reasonForResultChanges">Reason For Changes in Result<span class="mandatory">*</span> </label>
															<div class="col-lg-10">
																<textarea class="form-control" name="reasonForResultChanges" id="reasonForResultChanges" placeholder="Enter Reason For Result Changes" title="Please enter reason for result changes" style="width:100%;"></textarea>
															</div>
														</div>
													</div>
													<?php
													if (count($allChange) > 0) {
													?>
														<div class="row">
															<div class="col-md-12"><?php echo $rch; ?></div>
														</div>
													<?php } ?>
												</div>
											</div>
										</div>
										<div class="box-footer">
											<input type="hidden" name="vlSampleId" id="vlSampleId" value="<?php echo $vlQueryInfo['vl_sample_id']; ?>" />
											<input type="hidden" name="reasonForResultChangesHistory" id="reasonForResultChangesHistory" value="<?php echo base64_encode($vlQueryInfo['reason_for_vl_result_changes']); ?>" />
											<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>&nbsp;
											<a href="vlTestResult.php" class="btn btn-default"> Cancel</a>
										</div>
				</form>
			</div>
	</section>
</div>
<script>
	$(document).ready(function() {
		$('#sampleReceivedOn,#sampleTestingDateAtLab,#resultDispatchedOn').datetimepicker({
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
			yearRange: <?php echo (date('Y') - 100); ?> + ":" + "<?php echo (date('Y')) ?>"
		}).click(function() {
			$('.ui-datepicker-calendar').show();
		});
		$('#sampleReceivedOn,#sampleTestingDateAtLab,#resultDispatchedOn').mask('99-aaa-9999 99:99');
		__clone = $("#vlRequestFormSudan .labSection").clone();
		reason = ($("#reasonForResultChanges").length) ? $("#reasonForResultChanges").val() : '';
		result = ($("#vlResult").length) ? $("#vlResult").val() : '';
	});

	$("input:radio[name=noResult]").click(function() {
		if ($(this).val() == 'yes') {
			$('.rejectionReason').show();
			$('.vlResult').css('display', 'none');
			$('#rejectionReason').addClass('isRequired');
			$("#status").val(4);
			$('#vlResult').removeClass('isRequired');
		} else {
			$('.vlResult').css('display', 'block');
			$('.rejectionReason').hide();
			$('#rejectionReason').removeClass('isRequired');
			$('#rejectionReason').val('');
			$("#status").val('');
			$('#vlResult').addClass('isRequired');
			if ($('#tnd').is(':checked') || $('#bdl').is(':checked')) {
				$('#vlResult').removeClass('isRequired');
			}
		}
	});

	$('#tnd').change(function() {
		if ($('#tnd').is(':checked')) {
			$('#vlResult,#vlLog').attr('readonly', true);
			$("#vlResult").removeClass("isRequired");
			$('#bdl').prop('checked', false).attr('disabled', true);
		} else {
			$('#vlResult,#vlLog').attr('readonly', false);
			$("#vlResult").addClass("isRequired");
			$('#bdl').attr('disabled', false);
		}
	});
	$('#bdl').change(function() {
		if ($('#bdl').is(':checked')) {
			$('#vlResult,#vlLog').attr('readonly', true);
			$("#vlResult").removeClass("isRequired");
			$('#tnd').prop('checked', false).attr('disabled', true);
		} else {
			$('#vlResult,#vlLog').attr('readonly', false);
			$("#vlResult").addClass("isRequired");
			$('#tnd').attr('disabled', false);
		}
	});

	$('#vlResult,#vlLog').on('input', function(e) {
		if (this.value != '') {
			$('#tnd,#bdl').attr('disabled', true);
		} else {
			$('#tnd,#bdl').attr('disabled', false);
		}
	});

	$("#vlRequestFormSudan .labSection").on("change", function() {
		if ($.trim(result) != '') {
			if ($("#vlRequestFormSudan .labSection").serialize() == $(__clone).serialize()) {
				$(".reasonForResultChanges").css("display", "block");
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

	function validateNow() {
		flag = deforayValidator.init({
			formId: 'vlRequestFormSudan'
		});

		$('.isRequired').each(function() {
			($(this).val() == '') ? $(this).css('background-color', '#FFFF99'): $(this).css('background-color', '#FFFFFF')
		});
		if (flag) {
			if ($('#noResultYes').is(':checked')) {
				if ($("#status").val() != 4) {
					alert("Status should be Rejected.Because you have chosen Sample Rejection");
					return false;
				}
			}
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
			if (absValue != '' && absValue != 0 && !isNaN(absValue)) {
				$("#vlLog").val(Math.round(Math.log10(absValue) * 100) / 100);
			} else {
				$("#vlLog").val('');
			}
		}
		if (obj.id == "vlLog") {
			logValue = $("#vlLog").val();
			if (logValue != '' && logValue != 0 && !isNaN(logValue)) {
				var absVal = Math.round(Math.pow(10, logValue) * 100) / 100;
				if (absVal != 'Infinity') {
					$("#vlResult").val(Math.round(Math.pow(10, logValue) * 100) / 100);
				}
			} else {
				$("#vlResult").val('');
			}
		}
	}
</script>