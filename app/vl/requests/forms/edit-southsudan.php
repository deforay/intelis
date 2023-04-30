<?php

use App\Utilities\DateUtility;



//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);
//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);

$lResult = $facilitiesService->getTestingLabs('vl', true, true);

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
//check remote user
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
if ($_SESSION['instanceType'] == 'remoteuser') {
	$sampleCode = 'remote_sample_code';
	if (!empty($vlQueryInfo['remote_sample']) && $vlQueryInfo['remote_sample'] == 'yes') {
		$sampleCode = 'remote_sample_code';
	} else {
		$sampleCode = 'sample_code';
	}
} else {
	$sampleCode = 'sample_code';
}
//check user exists in user_facility_map table
$chkUserFcMapQry = "SELECT user_id FROM user_facility_map WHERE user_id='" . $_SESSION['userId'] . "'";
$chkUserFcMapResult = $db->query($chkUserFcMapQry);
if ($chkUserFcMapResult) {
	$pdQuery = "SELECT DISTINCT gd.geo_name,gd.geo_id,gd.geo_code FROM geographical_divisions as gd JOIN facility_details as fd ON fd.facility_state_id=gd.geo_id JOIN user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id WHERE gd.geo_parent = 0 AND gd.geo_status='active' AND vlfm.user_id='" . $_SESSION['userId'] . "'";
}

$pdResult = $db->query($pdQuery);
$province = "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
	$province .= "<option value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_id'] . "'>" . ($provinceName['geo_name']) . "</option>";
}

$facility = $general->generateSelectOptions($healthFacilities, $vlQueryInfo['facility_id'], '-- Select --');

//regimen heading
$artRegimenQuery = "SELECT DISTINCT headings FROM r_vl_art_regimen";
$artRegimenResult = $db->rawQuery($artRegimenQuery);
$aQuery = "SELECT * from r_vl_art_regimen where art_status ='active'";
$aResult = $db->query($aQuery);
//facility details
if (isset($vlQueryInfo['facility_id']) && $vlQueryInfo['facility_id'] > 0) {
	$facilityQuery = "SELECT * FROM facility_details where facility_id= ? AND status='active'";
	$facilityResult = $db->rawQuery($facilityQuery, array($vlQueryInfo['facility_id']));
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
//set reason for changes history
$rch = '';
$allChange = [];
if (isset($vlQueryInfo['reason_for_vl_result_changes']) && $vlQueryInfo['reason_for_vl_result_changes'] != '' && $vlQueryInfo['reason_for_vl_result_changes'] != null) {
	$allChange = json_decode($vlQueryInfo['reason_for_vl_result_changes'], true);
	if (!empty($allChange)) {
		$rch .= '<h4>Result Changes History</h4>';
		$rch .= '<table style="width:100%;">';
		$rch .= '<thead><tr style="border-bottom:2px solid #d3d3d3;"><th style="width:20%;">USER</th><th style="width:60%;">MESSAGE</th><th style="width:20%;text-align:center;">DATE</th></tr></thead>';
		$rch .= '<tbody>';
		$allChange = array_reverse($allChange);
		foreach ($allChange as $change) {
			$usrQuery = "SELECT user_name FROM user_details where user_id='" . $change['usr'] . "'";
			$usrResult = $db->rawQuery($usrQuery);
			$name = '';
			if (isset($usrResult[0]['user_name'])) {
				$name = ($usrResult[0]['user_name']);
			}
			$expStr = explode(" ", $change['dtime']);
			$changedDate = DateUtility::humanReadableDateFormat($expStr[0]) . " " . $expStr[1];
			$rch .= '<tr><td>' . $name . '</td><td>' . ($change['msg']) . '</td><td style="text-align:center;">' . $changedDate . '</td></tr>';
		}
		$rch .= '</tbody>';
		$rch .= '</table>';
	}
}

//var_dump($vlQueryInfo['sample_received_at_hub_datetime']);die;
$isGeneXpert = !empty($vlQueryInfo['vl_test_platform']) && (strcasecmp($vlQueryInfo['vl_test_platform'], "genexpert") === 0);

if ($isGeneXpert === true && !empty($vlQueryInfo['result_value_hiv_detection']) && !empty($vlQueryInfo['result'])) {
	$vlQueryInfo['result'] = trim(str_ireplace($vlQueryInfo['result_value_hiv_detection'], "", $vlQueryInfo['result']));
} else if ($isGeneXpert === true && !empty($vlQueryInfo['result'])) {

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

	$detectedMatching = $general->checkIfStringExists($vlQueryInfo['result'], $hivDetectedStringsToSearch);
	if ($detectedMatching !== false) {
		$vlQueryInfo['result'] = trim(str_ireplace($detectedMatching, "", $vlQueryInfo['result']));
		$vlQueryInfo['result_value_hiv_detection'] = "HIV-1 Detected";
	} else {
		$notDetectedMatching = $general->checkIfStringExists($vlQueryInfo['result'], $hivNotDetectedStringsToSearch);
		if ($notDetectedMatching !== false) {
			$vlQueryInfo['result'] = trim(str_ireplace($notDetectedMatching, "", $vlQueryInfo['result']));
			$vlQueryInfo['result_value_hiv_detection'] = "HIV-1 Not Detected";
		}
	}
}
//echo $vlQueryInfo['lab_id']; die;
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
		<h1><em class="fa-solid fa-pen-to-square"></em> VIRAL LOAD LABORATORY REQUEST FORM </h1>
		<ol class="breadcrumb">
			<li><a href="/dashboard/index.php"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Edit Vl Request</li>
		</ol>
	</section>
	<?php
	//print_r(array_column($vlTestReasonResult, 'last_name')$oneDimensionalArray = array_map('current', $vlTestReasonResult));die;
	?>
	<!-- Main content -->
	<section class="content">

		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
			</div>
			<div class="box-body">
				<!-- form start -->
				<form class="form-inline" method="post" name="vlRequestFormRwd" id="vlRequestFormRwd" autocomplete="off" action="editVlRequestHelper.php">
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
											<input type="text" class="form-control isRequired <?php echo $sampleClass; ?>" id="sampleCode" name="sampleCode" <?php echo $maxLength; ?> placeholder="Enter Sample ID" readonly="readonly" title="Please enter sample id" value="<?php echo ($sCode != '') ? $sCode : $vlQueryInfo[$sampleCode]; ?>" style="width:100%;" onchange="checkSampleNameValidation('form_vl','<?php echo $sampleCode; ?>',this.id,'<?php echo "vl_sample_id##" . $vlQueryInfo["vl_sample_id"]; ?>','This sample number already exists.Try another number',null)" />
											<input type="hidden" name="sampleCodeCol" value="<?php echo $vlQueryInfo['sample_code']; ?>" style="width:100%;">
										</div>
									</div>
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="sampleReordered">
												<input type="checkbox" class="" id="sampleReordered" name="sampleReordered" value="yes" <?php echo (trim($vlQueryInfo['sample_reordered']) == 'yes') ? 'checked="checked"' : '' ?> title="Please indicate if this is a reordered sample"> Sample Reordered
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
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="province">State/Province <span class="mandatory">*</span></label>
											<select class="form-control isRequired" name="province" id="province" title="Please choose state" style="width:100%;" onchange="getProvinceDistricts(this);">
												<?php echo $province; ?>
											</select>
										</div>
									</div>
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="district">District/County <span class="mandatory">*</span></label>
											<select class="form-control isRequired" name="district" id="district" title="Please choose county" style="width:100%;" onchange="getFacilities(this);">
												<option value=""> -- Select -- </option>
											</select>
										</div>
									</div>
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="fName">Clinic/Health Center <span class="mandatory">*</span></label>
											<select class="form-control isRequired" id="fName" name="fName" title="Please select clinic/health center name" style="width:100%;" onchange="fillFacilityDetails(this);">

												<?= $facility; ?>
											</select>
										</div>
									</div>
									<div class="col-xs-3 col-md-3" style="display:none;">
										<div class="form-group">
											<label for="fCode">Clinic/Health Center Code </label>
											<input type="text" class="form-control" style="width:100%;" name="fCode" id="fCode" placeholder="Clinic/Health Center Code" title="Please enter clinic/health center code" value="<?php echo $facilityResult[0]['facility_code']; ?>">
										</div>
									</div>
								</div>
								<div class="row facilityDetails" style="display:<?php echo (trim($facilityResult[0]['facility_emails']) != '' || trim($facilityResult[0]['facility_mobile_numbers']) != '' || trim($facilityResult[0]['contact_person']) != '') ? '' : 'none'; ?>;">
									<div class="col-xs-2 col-md-2 femails" style="display:<?php echo (trim($facilityResult[0]['facility_emails']) != '') ? '' : 'none'; ?>;"><strong>Clinic Email(s)</strong></div>
									<div class="col-xs-2 col-md-2 femails facilityEmails" style="display:<?php echo (trim($facilityResult[0]['facility_emails']) != '') ? '' : 'none'; ?>;"><?php echo $facilityResult[0]['facility_emails']; ?></div>
									<div class="col-xs-2 col-md-2 fmobileNumbers" style="display:<?php echo (trim($facilityResult[0]['facility_mobile_numbers']) != '') ? '' : 'none'; ?>;"><strong>Clinic Mobile No.(s)</strong></div>
									<div class="col-xs-2 col-md-2 fmobileNumbers facilityMobileNumbers" style="display:<?php echo (trim($facilityResult[0]['facility_mobile_numbers']) != '') ? '' : 'none'; ?>;"><?php echo $facilityResult[0]['facility_mobile_numbers']; ?></div>
									<div class="col-xs-2 col-md-2 fContactPerson" style="display:<?php echo (trim($facilityResult[0]['contact_person']) != '') ? '' : 'none'; ?>;"><strong>Clinic Contact Person -</strong></div>
									<div class="col-xs-2 col-md-2 fContactPerson facilityContactPerson" style="display:<?php echo (trim($facilityResult[0]['contact_person']) != '') ? '' : 'none'; ?>;"><?php echo ($facilityResult[0]['contact_person']); ?></div>
								</div>


								<div class="row">
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="implementingPartner">Implementing Partner</label>
											<select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose implementing partner" style="width:100%;">
												<option value=""> -- Select -- </option>
												<?php
												foreach ($implementingPartnerList as $implementingPartner) {
												?>
													<option value="<?php echo base64_encode($implementingPartner['i_partner_id']); ?>" <?php echo ($implementingPartner['i_partner_id'] == $vlQueryInfo['implementing_partner']) ? 'selected="selected"' : ''; ?>><?= $implementingPartner['i_partner_name']; ?></option>
												<?php } ?>
											</select>
										</div>
									</div>
									<div class="col-xs-4 col-md-4">
										<div class="form-group">
											<label for="fundingSource">Funding Source</label>
											<select class="form-control" name="fundingSource" id="fundingSource" title="Please choose implementing partner" style="width:100%;">
												<option value=""> -- Select -- </option>
												<?php
												foreach ($fundingSourceList as $fundingSource) {
												?>
													<option value="<?php echo base64_encode($fundingSource['funding_source_id']); ?>" <?php echo ($fundingSource['funding_source_id'] == $vlQueryInfo['funding_source']) ? 'selected="selected"' : ''; ?>><?= $fundingSource['funding_source_name']; ?></option>
												<?php } ?>
											</select>
										</div>
									</div>

									<div class="col-md-4 col-md-4">
										<label for="labId">Testing Lab <span class="mandatory">*</span></label>
										<select name="labId" id="labId" class="form-control isRequired" title="Please choose lab" onchange="autoFillFocalDetails();" style="width:100%;">
											<option value="">-- Select --</option>
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
								<h3 class="box-title">Patient Information</h3>
							</div>
							<div class="box-body">
								<div class="row">
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="artNo">ART (TRACNET) No. <span class="mandatory">*</span></label>
											<input type="text" name="artNo" id="artNo" class="form-control isRequired" placeholder="Enter ART Number" title="Enter art number" value="<?php echo $vlQueryInfo['patient_art_no']; ?>" />
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="dob">Date of Birth </label>
											<input type="text" name="dob" id="dob" class="form-control date" placeholder="Enter DOB" title="Enter dob" value="<?php echo $vlQueryInfo['patient_dob']; ?>" onchange="getAge();checkARTInitiationDate();" />
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="ageInYears">If DOB unknown, Age in Years </label>
											<input type="text" name="ageInYears" id="ageInYears" class="form-control forceNumeric" maxlength="3" placeholder="Age in Years" title="Enter age in years" value="<?php echo $vlQueryInfo['patient_age_in_years']; ?>" />
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="ageInMonths">If Age
												< 1, Age in Months </label> <input type="text" name="ageInMonths" id="ageInMonths" class="form-control forceNumeric" maxlength="2" placeholder="Age in Month" title="Enter age in months" value="<?php echo $vlQueryInfo['patient_age_in_months']; ?>" />
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="patientFirstName">Patient Name (First Name, Last Name) <span class="mandatory">*</span></label>
											<input type="text" name="patientFirstName" id="patientFirstName" class="form-control isRequired" placeholder="Enter Patient Name" title="Enter patient name" value="<?php echo $patientFullName; ?>" />
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="gender">Gender</label><br>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="genderMale" name="gender" value="male" title="Please check gender" <?php echo ($vlQueryInfo['patient_gender'] == 'male') ? "checked='checked'" : "" ?>> Male
											</label>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="genderFemale" name="gender" value="female" title="Please check gender" <?php echo ($vlQueryInfo['patient_gender'] == 'female') ? "checked='checked'" : "" ?>> Female
											</label>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="genderNotRecorded" name="gender" value="not_recorded" title="Please check gender" <?php echo ($vlQueryInfo['patient_gender'] == 'not_recorded') ? "checked='checked'" : "" ?>>Not Recorded
											</label>
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="gender">Patient consent to receive SMS?</label><br>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="receivesmsYes" name="receiveSms" value="yes" title="Patient consent to receive SMS" onclick="checkPatientReceivesms(this.value);" <?php echo ($vlQueryInfo['consent_to_receive_sms'] == 'yes') ? "checked='checked'" : "" ?>> Yes
											</label>
											<label class="radio-inline" style="margin-left:0px;">
												<input type="radio" class="" id="receivesmsNo" name="receiveSms" value="no" title="Patient consent to receive SMS" onclick="checkPatientReceivesms(this.value);" <?php echo ($vlQueryInfo['consent_to_receive_sms'] == 'no') ? "checked='checked'" : "" ?>> No
											</label>
										</div>
									</div>
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="patientPhoneNumber">Phone Number</label>
											<input type="text" name="patientPhoneNumber" id="patientPhoneNumber" class="form-control forceNumeric" maxlength="15" placeholder="Enter Phone Number" title="Enter phone number" value="<?php echo $vlQueryInfo['patient_mobile_number']; ?>" />
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
												<input type="text" class="form-control isRequired dateTime" style="width:100%;" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" title="Please select sample collection date" value="<?php echo $vlQueryInfo['sample_collection_date']; ?>" onchange="checkSampleReceviedDate();checkSampleTestingDate();">
											</div>
										</div>
										<div class="col-xs-3 col-md-3">
											<div class="form-group">
												<label for="">Sample Dispatched On <span class="mandatory">*</span></label>
												<input type="text" class="form-control isRequired dateTime" style="width:100%;" name="sampleDispatchedDate" id="sampleDispatchedDate" placeholder="Sample Dispatched On" title="Please select sample dispatched on" value="<?php echo $vlQueryInfo['sample_dispatched_datetime']; ?>">
											</div>
										</div>
										<div class="col-xs-3 col-md-3">
											<div class="form-group">
												<label for="specimenType">Sample Type <span class="mandatory">*</span></label>
												<select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose sample type">
													<option value=""> -- Select -- </option>
													<?php foreach ($sResult as $name) { ?>
														<option value="<?php echo $name['sample_id']; ?>" <?php echo ($vlQueryInfo['sample_type'] == $name['sample_id']) ? "selected='selected'" : "" ?>><?= $name['sample_name']; ?></option>
													<?php } ?>
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
													<input type="text" class="form-control date" name="dateOfArtInitiation" id="dateOfArtInitiation" placeholder="Date Of Treatment Initiated" title="Date Of treatment initiated" value="<?php echo $vlQueryInfo['treatment_initiated_date']; ?>" style="width:100%;" onchange="checkARTInitiationDate();">
												</div>
											</div>
											<div class="col-xs-3 col-md-3">
												<div class="form-group">
													<label for="artRegimen">Current Regimen</label>
													<select class="form-control" id="artRegimen" name="artRegimen" title="Please choose ART Regimen" style="width:100%;" onchange="checkARTRegimenValue();">
														<option value="">-- Select --</option>
														<?php foreach ($artRegimenResult as $heading) { ?>
															<optgroup label="<?= $heading['headings']; ?>">
																<?php foreach ($aResult as $regimen) {
																	if ($heading['headings'] == $regimen['headings']) { ?>
																		<option value="<?php echo $regimen['art_code']; ?>" <?php echo ($vlQueryInfo['current_regimen'] == $regimen['art_code']) ? "selected='selected'" : "" ?>><?php echo $regimen['art_code']; ?></option>
																<?php }
																} ?>
															</optgroup>
														<?php }
														if ($sarr['sc_user_type'] != 'vluser') {  ?>
															<option value="other">Other</option>
														<?php } ?>
													</select>
													<input type="text" class="form-control newArtRegimen" name="newArtRegimen" id="newArtRegimen" placeholder="ART Regimen" title="Please enter art regimen" style="width:100%;display:none;margin-top:2px;">
												</div>
											</div>
											<div class="col-xs-3 col-md-3">
												<div class="form-group">
													<label for="">Date of Initiation of Current Regimen </label>
													<input type="text" class="form-control date" style="width:100%;" name="regimenInitiatedOn" id="regimenInitiatedOn" placeholder="Current Regimen Initiated On" title="Please enter current regimen initiated on" value="<?php echo $vlQueryInfo['date_of_initiation_of_current_regimen']; ?>">
												</div>
											</div>
											<div class="col-xs-3 col-md-3">
												<div class="form-group">
													<label for="arvAdherence">ARV Adherence </label>
													<select name="arvAdherence" id="arvAdherence" class="form-control" title="Please choose adherence">
														<option value=""> -- Select -- </option>
														<option value="good" <?php echo ($vlQueryInfo['arv_adherance_percentage'] == 'good') ? "selected='selected'" : "" ?>>Good >= 95%</option>
														<option value="fair" <?php echo ($vlQueryInfo['arv_adherance_percentage'] == 'fair') ? "selected='selected'" : "" ?>>Fair (85-94%)</option>
														<option value="poor" <?php echo ($vlQueryInfo['arv_adherance_percentage'] == 'poor') ? "selected='selected'" : "" ?>>Poor < 85%</option>
													</select>
												</div>
											</div>
										</div>
										<div class="row ">
											<div class="col-xs-3 col-md-3 femaleSection" style="display:<?php echo ($vlQueryInfo['patient_gender'] == 'female' || $vlQueryInfo['patient_gender'] == '' || $vlQueryInfo['patient_gender'] == null) ? "" : "none" ?>" ;>
												<div class="form-group">
													<label for="patientPregnant">Is Patient Pregnant? </label><br>
													<label class="radio-inline">
														<input type="radio" class="" id="pregYes" name="patientPregnant" value="yes" title="Please check one" <?php echo ($vlQueryInfo['is_patient_pregnant'] == 'yes') ? "checked='checked'" : "" ?>> Yes
													</label>
													<label class="radio-inline">
														<input type="radio" class="" id="pregNo" name="patientPregnant" value="no" <?php echo ($vlQueryInfo['is_patient_pregnant'] == 'no') ? "checked='checked'" : "" ?>> No
													</label>
												</div>
											</div>
											<div class="col-xs-3 col-md-3 femaleSection" style="display:<?php echo ($vlQueryInfo['patient_gender'] == 'female' || $vlQueryInfo['patient_gender'] == '' || $vlQueryInfo['patient_gender'] == null) ? "" : "none" ?>" ;>
												<div class="form-group">
													<label for="breastfeeding">Is Patient Breastfeeding? </label><br>
													<label class="radio-inline">
														<input type="radio" class="" id="breastfeedingYes" name="breastfeeding" value="yes" title="Please check one" <?php echo ($vlQueryInfo['is_patient_breastfeeding'] == 'yes') ? "checked='checked'" : "" ?>> Yes
													</label>
													<label class="radio-inline">
														<input type="radio" class="" id="breastfeedingNo" name="breastfeeding" value="no" <?php echo ($vlQueryInfo['is_patient_breastfeeding'] == 'no') ? "checked='checked'" : "" ?>> No
													</label>
												</div>
											</div>
											<div class="col-xs-3 col-md-3" style="display:none;">
												<div class="form-group">
													<label for="">How long has this patient been on treatment ? </label>
													<input type="text" class="form-control" id="treatPeriod" name="treatPeriod" placeholder="Enter Treatment Period" title="Please enter how long has this patient been on treatment" value="<?php echo $vlQueryInfo['treatment_initiation']; ?>" />
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
																$vlTestReasonQueryRow = "SELECT * from r_vl_test_reasons where test_reason_id='" . trim($vlQueryInfo['reason_for_vl_testing']) . "' OR test_reason_name = '" . trim($vlQueryInfo['reason_for_vl_testing']) . "'";
																$vlTestReasonResultRow = $db->query($vlTestReasonQueryRow);
																$checked = '';
																$display = '';
																if (trim($vlQueryInfo['reason_for_vl_testing']) == 'routine' || isset($vlTestReasonResultRow[0]['test_reason_id']) && $vlTestReasonResultRow[0]['test_reason_name'] == 'routine') {
																	$checked = 'checked="checked"';
																	$display = 'block';
																} else {
																	$checked = '';
																	$display = 'none';
																}
																?>
																<input type="radio" class="isRequired" id="rmTesting" name="reasonForVLTesting" value="routine" title="Please select indication/reason for testing" <?php echo $checked; ?> onclick="showTesting('rmTesting');">
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
														<input type="text" class="form-control date viralTestData" id="rmTestingLastVLDate" name="rmTestingLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" value="<?php echo (trim($vlQueryInfo['last_vl_date_routine']) != '' && $vlQueryInfo['last_vl_date_routine'] != null && $vlQueryInfo['last_vl_date_routine'] != '0000-00-00') ? DateUtility::humanReadableDateFormat($vlQueryInfo['last_vl_date_routine']) : ''; ?>" />
													</div>
												</div>
												<div class="col-md-6">
													<label for="rmTestingVlValue" class="col-lg-3 control-label">VL Result</label>
													<div class="col-lg-7">
														<input type="text" class="form-control forceNumeric viralTestData" id="rmTestingVlValue" name="rmTestingVlValue" placeholder="Enter VL Result" title="Please enter VL Result" value="<?php echo $vlQueryInfo['last_vl_result_routine']; ?>" />
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
																if (trim($vlQueryInfo['reason_for_vl_testing']) == 'failure' || isset($vlTestReasonResultRow[0]['test_reason_id']) && $vlTestReasonResultRow[0]['test_reason_name'] == 'failure') {
																	$checked = 'checked="checked"';
																	$display = 'block';
																} else {
																	$checked = '';
																	$display = 'none';
																}
																?>
																<input type="radio" class="isRequired" id="repeatTesting" name="reasonForVLTesting" value="failure" title="Repeat VL test after suspected treatment failure adherence counseling (Reason for testing)" <?php echo $checked; ?> onclick="showTesting('repeatTesting');">
																<strong>Repeat VL test after suspected treatment failure adherence counselling </strong>
															</label>
														</div>
													</div>
												</div>
											</div>
											<div class="row repeatTesting hideTestData" style="display: <?php echo $display; ?>;">
												<div class="col-md-6">
													<label class="col-lg-5 control-label">Date of Last VL Test</label>
													<div class="col-lg-7">
														<input type="text" class="form-control date viralTestData" id="repeatTestingLastVLDate" name="repeatTestingLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" value="<?php echo (trim($vlQueryInfo['last_vl_date_failure_ac']) != '' && $vlQueryInfo['last_vl_date_failure_ac'] != null && $vlQueryInfo['last_vl_date_failure_ac'] != '0000-00-00') ? DateUtility::humanReadableDateFormat($vlQueryInfo['last_vl_date_failure_ac']) : ''; ?>" />
													</div>
												</div>
												<div class="col-md-6">
													<label for="repeatTestingVlValue" class="col-lg-3 control-label">VL Result</label>
													<div class="col-lg-7">
														<input type="text" class="form-control forceNumeric viralTestData" id="repeatTestingVlValue" name="repeatTestingVlValue" placeholder="Enter VL Result" title="Please enter VL Result" value="<?php echo $vlQueryInfo['last_vl_result_failure_ac']; ?>" />
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
																if (trim($vlQueryInfo['reason_for_vl_testing']) == 'suspect' || isset($vlTestReasonResultRow[0]['test_reason_id']) && $vlTestReasonResultRow[0]['test_reason_name'] == 'suspect') {
																	$checked = 'checked="checked"';
																	$display = 'block';
																} else {
																	$checked = '';
																	$display = 'none';
																}
																?>
																<input type="radio" class="isRequired" id="suspendTreatment" name="reasonForVLTesting" value="suspect" title="Suspect Treatment Failure (Reason for testing)" <?php echo $checked; ?> onclick="showTesting('suspendTreatment');">
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
														<input type="text" class="form-control date viralTestData" id="suspendTreatmentLastVLDate" name="suspendTreatmentLastVLDate" placeholder="Select Last VL Date" title="Please select Last VL Date" value="<?php echo (trim($vlQueryInfo['last_vl_date_failure']) != '' && $vlQueryInfo['last_vl_date_failure'] != null && $vlQueryInfo['last_vl_date_failure'] != '0000-00-00') ? DateUtility::humanReadableDateFormat($vlQueryInfo['last_vl_date_failure']) : ''; ?>" />
													</div>
												</div>
												<div class="col-md-6">
													<label for="suspendTreatmentVlValue" class="col-lg-3 control-label">VL Result</label>
													<div class="col-lg-7">
														<input type="text" class="form-control forceNumeric viralTestData" id="suspendTreatmentVlValue" name="suspendTreatmentVlValue" placeholder="Enter VL Result" title="Please enter VL Result" value="<?php echo $vlQueryInfo['last_vl_result_failure']; ?>" />
														(copies/ml)
													</div>
												</div>
											</div>
											<p>&nbsp;</p>
											<div class="row">
												<div class="col-md-4">
													<label for="reqClinician" class="col-lg-5 control-label">Request Clinician</label>
													<div class="col-lg-7">
														<select class="form-control ajax-select2" id="reqClinician" name="reqClinician" placeholder="Request Clinician" title="Please enter request clinician" value="<?php echo $vlQueryInfo['request_clinician_name']; ?>">
															<option value="<?php echo $vlQueryInfo['request_clinician_name']; ?>" selected='selected'> <?php echo $vlQueryInfo['request_clinician_name']; ?></option>
														</select>
													</div>
												</div>
												<div class="col-md-4">
													<label for="reqClinicianPhoneNumber" class="col-lg-5 control-label">Phone Number</label>
													<div class="col-lg-7">
														<input type="text" class="form-control forceNumeric" id="reqClinicianPhoneNumber" name="reqClinicianPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter request clinician phone number" value="<?php echo $vlQueryInfo['request_clinician_phone_number']; ?>" />
													</div>
												</div>
												<div class="col-md-4">
													<label class="col-lg-5 control-label" for="requestDate">Request Date </label>
													<div class="col-lg-7">
														<input type="text" class="form-control date" id="requestDate" name="requestDate" placeholder="Request Date" title="Please select request date" value="<?php echo $vlQueryInfo['test_requested_on']; ?>" />
													</div>
												</div>
											</div>
											<div class="row" style="display:none;">

												<div class="col-md-4">
													<label class="col-lg-5 control-label" for="emailHf">Email for HF </label>
													<div class="col-lg-7">
														<input type="text" class="form-control isEmail" id="emailHf" name="emailHf" placeholder="Email for HF" title="Please enter email for hf" value="<?php echo $facilityResult[0]['facility_emails']; ?>" />
													</div>
												</div>
											</div>
										</div>
									</div>
									<?php if ($usersService->isAllowed('vlTestResult.php') && $_SESSION['accessType'] != 'collection-site') { ?>
										<div class="box-header with-border">
											<h3 class="box-title">Laboratory Information</h3>
										</div>
										<div class="box-body labSectionBody">
											<div class="row">
												<!-- <div class="col-md-4">
													<label for="labId" class="col-lg-5 control-label">Lab Name </label>
													<div class="col-lg-7">
														<select name="labId" id="labId" class="select2 form-control labSection" title="Please choose lab" onchange="autoFillFocalDetails();">
															<option value="">-- Select --</option>
															<?php foreach ($lResult as $labName) { ?>
																<option data-focalperson="<?php echo $labName['contact_person']; ?>" data-focalphone="<?php echo $labName['facility_mobile_numbers']; ?>" value="<?php echo $labName['facility_id']; ?>" <?php echo (isset($vlQueryInfo['lab_id']) && $vlQueryInfo['lab_id'] == $labName['facility_id']) ? 'selected="selected"' : ''; ?>><?= $labName['facility_name']; ?></option>
															<?php } ?>
														</select>
													</div>
												</div> -->
												<div class="col-md-4">
													<label for="vlFocalPerson" class="col-lg-5 control-label">VL Focal Person </label>
													<div class="col-lg-7">
														<select class="form-control ajax-select2" id="vlFocalPerson" name="vlFocalPerson" title="Please enter VL Focal Person">
															<option value="<?php echo $vlQueryInfo['vl_focal_person']; ?>" selected='selected'> <?php echo $vlQueryInfo['vl_focal_person']; ?></option>
														</select>
													</div>
												</div>
												<div class="col-md-4">
													<label for="vlFocalPersonPhoneNumber" class="col-lg-5 control-label">VL Focal Person Phone Number</label>
													<div class="col-lg-7">
														<input type="text" class="form-control forceNumeric labSection" id="vlFocalPersonPhoneNumber" name="vlFocalPersonPhoneNumber" maxlength="15" placeholder="Phone Number" title="Please enter vl focal person phone number" value="<?php echo $vlQueryInfo['vl_focal_person_phone_number']; ?>" />
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-md-4">
													<label class="col-lg-5 control-label" for="sampleReceivedAtHubOn">Date Sample Received at Hub (PHL) </label>
													<div class="col-lg-7">
														<input type="text" class="form-control dateTime" id="sampleReceivedAtHubOn" name="sampleReceivedAtHubOn" placeholder="Sample Received at HUB Date" title="Please select sample received at HUB date" value="<?php echo $vlQueryInfo['sample_received_at_hub_datetime']; ?>" onchange="checkSampleReceviedAtHubDate()" />
													</div>
												</div>
												<div class="col-md-4">
													<label class="col-lg-5 control-label" for="sampleReceivedDate">Date Sample Received at Testing Lab </label>
													<div class="col-lg-7">
														<input type="text" class="form-control labSection dateTime" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="Sample Received Date" title="Please select sample received date" value="<?php echo $vlQueryInfo['sample_received_at_vl_lab_datetime']; ?>" onchange="checkSampleReceviedDate()" />
													</div>
												</div>
												<div class="col-md-4">
													<label class="col-lg-5 control-label" for="sampleTestingDateAtLab">Sample Testing Date <span class="mandatory result-span">*</span></label>
													<div class="col-lg-7">
														<input type="text" class="form-control isRequired dateTime result-fieldsform-control result-fields labSection <?php echo ($vlQueryInfo['is_sample_rejected'] == 'no') ? 'isRequired' : ''; ?>" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? ' disabled="disabled" ' : ''; ?> id="sampleTestingDateAtLab" name="sampleTestingDateAtLab" placeholder="Sample Testing Date" title="Please select sample testing date" value="<?php echo $vlQueryInfo['sample_tested_datetime']; ?>" onchange="checkSampleTestingDate();" />
													</div>
												</div>

											</div>
											<div class="row">
												<div class="col-md-4">
													<label for="testingPlatform" class="col-lg-5 control-label">VL Testing Platform <span class="mandatory result-span">*</span></label>
													<div class="col-lg-7">
														<select name="testingPlatform" id="testingPlatform" class="form-control isRequired result-optional labSection" title="Please choose VL Testing Platform">
															<option value="">-- Select --</option>
															<?php foreach ($importResult as $mName) { ?>
																<option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] . '##' . $mName['config_id']; ?>" <?php echo ($vlQueryInfo['vl_test_platform'] == $mName['machine_name']) ? 'selected="selected"' : ''; ?>><?php echo $mName['machine_name']; ?></option>
															<?php } ?>
														</select>
													</div>
												</div>
												<div class="col-md-4">
													<label class="col-lg-5 control-label" for="noResult">Sample Rejection <span class="mandatory result-span">*</span></label>
													<div class="col-lg-7">
														<select name="noResult" id="noResult" class="form-control isRequired labSection" title="Please check if sample is rejected or not">
															<option value="">-- Select --</option>
															<option value="yes" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'selected="selected"' : ''; ?>>Yes</option>
															<option value="no" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'no') ? 'selected="selected"' : ''; ?>>No</option>
														</select>
													</div>
												</div>
												<div class="col-md-4 rejectionReason" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;">
													<label class="col-lg-5 control-label" for="rejectionReason">Rejection Reason </label>
													<div class="col-lg-7">
														<select name="rejectionReason" id="rejectionReason" class="form-control labSection" title="Please choose reason" onchange="checkRejectionReason();">
															<option value="">-- Select --</option>
															<?php foreach ($rejectionTypeResult as $type) { ?>
																<optgroup label="<?php echo ($type['rejection_type']); ?>">
																	<?php
																	foreach ($rejectionResult as $reject) {
																		if ($type['rejection_type'] == $reject['rejection_type']) { ?>
																			<option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($vlQueryInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?= $reject['rejection_reason_name']; ?></option>
																	<?php }
																	} ?>
																</optgroup>
															<?php }
															if ($sarr['sc_user_type'] != 'vluser') {  ?>
																<option value="other">Other (Please Specify) </option>
															<?php } ?>
														</select>
														<input type="text" class="form-control newRejectionReason" name="newRejectionReason" id="newRejectionReason" placeholder="Rejection Reason" title="Please enter rejection reason" style="width:100%;display:none;margin-top:2px;">
													</div>
												</div>
												<div class="col-md-4 rejectionReason" style="margin-top: 10px;display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? '' : 'none'; ?>;">
													<label class="col-lg-5 control-label" for="rejectionDate">Rejection Date </label>
													<div class="col-lg-7">
														<input value="<?php echo DateUtility::humanReadableDateFormat($vlQueryInfo['rejection_on']); ?>" class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" title="Please select Sample Rejection Date" />
													</div>
												</div>
												<div class="col-md-4 hivDetection" style="<?php echo (($isGeneXpert === false) || ($isGeneXpert === true && $vlQueryInfo['is_sample_rejected'] === 'yes')) ? 'display: none;' : ''; ?>">
													<label for="hivDetection" class="col-lg-5 control-label">HIV Detection </label>
													<div class="col-lg-7">
														<select name="hivDetection" id="hivDetection" class="form-control hivDetection labSection" title="Please choose HIV detection">
															<option value="">-- Select --</option>
															<option value="HIV-1 Detected" <?php echo (isset($vlQueryInfo['result_value_hiv_detection']) && $vlQueryInfo['result_value_hiv_detection'] == 'HIV-1 Detected') ? 'selected="selected"' : ''; ?>>HIV-1 Detected</option>
															<option value="HIV-1 Not Detected" <?php echo (isset($vlQueryInfo['result_value_hiv_detection']) && $vlQueryInfo['result_value_hiv_detection'] == 'HIV-1 Not Detected') ? 'selected="selected"' : ''; ?>>HIV-1 Not Detected</option>
														</select>
													</div>
												</div>
												<?php if (!isset($vlQueryInfo['is_sample_rejected']) || empty($vlQueryInfo['is_sample_rejected']) || $vlQueryInfo['is_sample_rejected'] != 'yes') { ?>
											</div>
											<div class="row">
											<?php } ?>
											<div class="col-md-4 vlResult" style="margin-top: 10px;display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'none' : 'block'; ?>;">
												<label class="col-lg-5 control-label" for="vlResult">Viral Load Result (copies/ml) </label>
												<div class="col-lg-7 resultInputContainer">
													<input list="possibleVlResults" class="form-control result-fields labSection" id="vlResult" name="vlResult" placeholder="Select or Type VL Result" title="Please enter viral load result" value="<?php echo $vlQueryInfo['result']; ?>" onchange="calculateLogValue(this)">
													<datalist id="possibleVlResults" title="Please enter viral load result">

													</datalist>
												</div>
											</div>
											<div class="col-md-4 vlLog" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? 'none' : 'block'; ?>;">
												<label class="col-lg-5 control-label" for="vlLog">Viral Load (Log) </label>
												<div class="col-lg-7">
													<input type="text" class="form-control labSection" id="vlLog" name="vlLog" placeholder="Viral Load (Log)" title="Please enter viral load in log" value="<?php echo $vlQueryInfo['result_value_log']; ?>" <?php echo ($vlQueryInfo['result'] == 'Target Not Detected' || $vlQueryInfo['result'] == 'Below Detection Level') ? 'readonly="readonly"' : ''; ?> style="width:100%;" onchange="calculateLogValue(this);" />
												</div>
											</div>
											<?php if (count($reasonForFailure) > 0) { ?>
												<div class="col-md-4 labSection" style="<?php echo (!isset($vlQueryInfo['result']) || $vlQueryInfo['result'] == 'Failed') ? '' : 'display: none;'; ?>">
													<label class="col-lg-5 control-label" for="reasonForFailure">Reason for Failure  </label>
													<div class="col-lg-7">
														<select name="reasonForFailure" id="reasonForFailure" class="form-control vlResult" title="Please choose reason for failure" style="width: 100%;">
															<?= $general->generateSelectOptions($reasonForFailure, $vlQueryInfo['reason_for_failure'], '-- Select --'); ?>
														</select>
													</div>
												</div>
											<?php } ?>
											<div class="col-md-4 vlResult" style="margin-top: 10px;">
												<label class="col-lg-5 control-label" for="resultDispatchedOn">Date Results Dispatched </label>
												<div class="col-lg-7">
													<input type="text" class="form-control labSection dateTime" id="resultDispatchedOn" name="resultDispatchedOn" placeholder="Result Dispatched Date" title="Please select result dispatched date" value="<?php echo $vlQueryInfo['result_dispatched_datetime']; ?>" />
												</div>
											</div>
											</div>
											<div class="row">
												<div class="col-md-4" style="margin-top: 10px;">
													<label class="col-lg-5 control-label" for="reviewedBy">Reviewed By <span class="mandatory review-approve-span" style="display: <?php echo ($vlQueryInfo['is_sample_rejected'] != '') ? 'inline' : 'none'; ?>;">*</span></label>
													<div class="col-lg-7">
														<select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose reviewed by" style="width: 100%;">
															<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_reviewed_by'], '-- Select --'); ?>
														</select>
													</div>
												</div>
												<div class="col-md-4">
													<label class="col-lg-5 control-label" for="reviewedOn">Reviewed On <span class="mandatory review-approve-span" style="display: <?php echo ($vlQueryInfo['is_sample_rejected'] != '') ? 'inline' : 'none'; ?>;">*</span></label>
													<div class="col-lg-7">
														<input type="text" value="<?php echo $vlQueryInfo['result_reviewed_datetime']; ?>" name="reviewedOn" id="reviewedOn" class="dateTime form-control" placeholder="Reviewed on" title="Please enter the Reviewed on" />
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
												<?php
												$styleStatus = '';
												if ((($_SESSION['accessType'] == 'collection-site') && $vlQueryInfo['result_status'] == 9) || ($sCode != '')) {
													$styleStatus = "display:none";
												?>
													<input type="hidden" name="status" value="<?php echo $vlQueryInfo['result_status']; ?>" />
												<?php
												}
												?>

											</div>
											<div class="row">
												<div class="col-md-4" style="margin-top: 10px;">
													<label class="col-lg-5 control-label" for="approvedBy">Approved By <span class="mandatory review-approve-span" style="display: <?php echo ($vlQueryInfo['is_sample_rejected'] != '') ? 'block' : 'none'; ?>;">*</span></label>
													<div class="col-lg-7">
														<select name="approvedBy" id="approvedBy" class="form-control labSection" title="Please choose approved by">
															<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_approved_by'], '-- Select --'); ?>
														</select>
													</div>
												</div>
												<div class="col-md-4">
													<label class="col-lg-5 control-label" for="approvedOn">Approved On <span class="mandatory review-approve-span" style="display: <?php echo ($vlQueryInfo['is_sample_rejected'] != '') ? 'block' : 'none'; ?>;">*</span></label>
													<div class="col-lg-7">
														<input type="text" value="<?php echo $vlQueryInfo['result_approved_datetime']; ?>" class="form-control dateTime" id="approvedOn" name="approvedOn" placeholder="<?= _("Please enter date"); ?>" style="width:100%;" />
													</div>
												</div>
											</div>
											<div class="row">
												<div class="col-md-6">
													<label class="col-lg-5 control-label" for="labComments">Lab Tech. Comments </label>
													<div class="col-lg-7">
														<textarea class="form-control labSection" name="labComments" id="labComments" placeholder="Lab comments" style="width:100%"><?php echo trim($vlQueryInfo['lab_tech_comments']); ?></textarea>
													</div>
												</div>
												<div class="col-md-6 reasonForResultChanges" style="display:none;">
													<label class="col-lg-6 control-label" for="reasonForResultChanges">Reason For Changes in Result<span class="mandatory">*</span></label>
													<div class="col-lg-6">
														<textarea class="form-control" name="reasonForResultChanges" id="reasonForResultChanges" placeholder="Enter Reason For Result Changes" title="Please enter reason for result changes" style="width:100%;"></textarea>
													</div>
												</div>
											</div>
											<?php if (!empty($allChange)) { ?>
												<div class="row">
													<div class="col-md-12"><?php echo $rch; ?></div>
												</div>
											<?php } ?>
										</div>
									<?php } ?>
								</div>
							</div>
							<div class="box-footer">
								<input type="hidden" name="revised" id="revised" value="no" />
								<input type="hidden" name="vlSampleId" id="vlSampleId" value="<?php echo $vlQueryInfo['vl_sample_id']; ?>" />
								<input type="hidden" name="isRemoteSample" value="<?php echo $vlQueryInfo['remote_sample']; ?>" />
								<input type="hidden" name="reasonForResultChangesHistory" id="reasonForResultChangesHistory" value="<?php echo base64_encode($vlQueryInfo['reason_for_vl_result_changes']); ?>" />
								<input type="hidden" name="oldStatus" value="<?php echo $vlQueryInfo['result_status']; ?>" />
								<input type="hidden" name="countryFormId" id="countryFormId" value="<?php echo $arr['vl_form']; ?>" />
								<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>&nbsp;
								<a href="vlRequest.php" class="btn btn-default"> Cancel</a>
							</div>
				</form>
			</div>
	</section>
</div>
<script type="text/javascript" src="/assets/js/datalist-css.min.js"></script>
<script type="text/javascript">
	let provinceName = true;
	let facilityName = true;

	let __clone = null;
	let reason = null;
	let resultValue = null;

	$(document).ready(function() {
		hivDetectionChange();
		//getFacilities(document.getElementById("district"));
		$("#labId,#fName,#sampleCollectionDate").on('change', function() {

			if ($("#labId").val() != '' && $("#labId").val() == $("#fName").val() && $("#sampleDispatchedDate").val() == "") {
				$('#sampleDispatchedDate').val($('#sampleCollectionDate').val());
			}
			if ($("#labId").val() != '' && $("#labId").val() == $("#fName").val() && $("#sampleReceivedDate").val() == "") {
				$('#sampleReceivedDate').val($('#sampleCollectionDate').val());
				$('#sampleReceivedAtHubOn').val($('#sampleCollectionDate').val());
			}
		});

		$("#labId").on('change', function() {
			if ($("#labId").val() != "") {
				$.post("/includes/get-sample-type.php", {
						facilityId: $('#labId').val(),
						testType: 'vl',
						sampleId: '<?php echo $vlQueryInfo['sample_type']; ?>'
					},
					function(data) {
						if (data != "") {
							$("#specimenType").html(data);
						}
					});
			}
		});




		/*$("#sampleCollectionDate").datetimepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: 'dd-M-yy',
			timeFormat: "HH:mm",
			maxDate: "Today",
			onSelect: function(date) {
				var dt2 = $('#sampleDispatchedDate');
				var startDate = $(this).datetimepicker('getDate');
				var minDate = $(this).datetimepicker('getDate');
				//dt2.datetimepicker('setDate', minDate);
				startDate.setDate(startDate.getDate() + 1000000);
				dt2.datetimepicker('option', 'maxDate', "Today");
				dt2.datetimepicker('option', 'minDate', minDate);
				dt2.datetimepicker('option', 'minDateTime', minDate);
			}
		});
		$('#sampleDispatchedDate').datetimepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: 'dd-M-yy',
			timeFormat: "HH:mm",
			yearRange: "-100:+100",
		});*/

		$('#sampleCollectionDate').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            timeFormat: "HH:mm",
            maxDate: "Today",
           // yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>",
			onSelect: function(date) {
				var dt2 = $('#sampleDispatchedDate');
				var startDate = $(this).datetimepicker('getDate');
				var minDate = $(this).datetimepicker('getDate');
				dt2.datetimepicker('setDate', minDate);
				startDate.setDate(startDate.getDate() + 1000000);
				dt2.datetimepicker('option', 'maxDate', "Today");
				dt2.datetimepicker('option', 'minDate', minDate);
				dt2.datetimepicker('option', 'minDateTime', minDate);
				dt2.val($(this).val());
			}
        }).click(function() {
            $('.ui-datepicker-calendar').show();
        });
	

		var minDate = $('#sampleCollectionDate').datetimepicker('getDate');
		var collectDate = $("#sampleCollectionDate").toString();
        var dispatchDate = $("#sampleDispatchedDate").toString();
		if($("#sampleDispatchedDate").val()=="" || (collectDate >= dispatchDate))
			$("#sampleDispatchedDate").val($('#sampleCollectionDate').val());
		
		$('#sampleDispatchedDate').datetimepicker({
            changeMonth: true,
            changeYear: true,
            dateFormat: 'dd-M-yy',
            timeFormat: "HH:mm",
            minDate: minDate,
			startDate: minDate,
        });


		autoFillFocalDetails();
		$('#fName').select2({
			width: '100%',
			placeholder: "Select Clinic/Health Center"
		});
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
		$('#facilityId').select2({
			placeholder: "Select Clinic/Health Center"
		});
		$('#district').select2({
			placeholder: "District"
		});
		$('#province').select2({
			placeholder: "Province"
		});
		//getAge();

		getfacilityProvinceDetails($("#fName").val());




		setTimeout(function() {
			$("#vlResult").trigger('change');
			$("#hivDetection, #noResult").trigger('change');
			// just triggering sample collection date is enough,
			// it will automatically do everything that labId and fName changes will do
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
			placeholder: "Enter Request Clinician name",
			minimumInputLength: 0,
			width: '100%',
			allowClear: true,
			id: function(bond) {
				return bond._id;
			},
			ajax: {
				placeholder: "Type one or more character tp search",
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
			placeholder: "Enter Request Focal name",
			minimumInputLength: 0,
			width: '100%',
			allowClear: true,
			id: function(bond) {
				return bond._id;
			},
			ajax: {
				placeholder: "Type one or more character tp search",
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
		var cName = $("#fName").val();
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
						$("#fName").html(details[0]);
						$("#district").html(details[1]);
						$("#fCode").val('');
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
			$("#fName").html("<option data-code='' data-emails='' data-mobile-nos='' data-contact-person='' value=''> -- Select -- </option>");
		}
		$.unblockUI();
	}

	function getFacilities(obj) {
		//alert(obj);
		$.blockUI();
		var dName = $("#district").val();
		var cName = $("#fName").val();
		if (dName != '') {
			$.post("/includes/siteInformationDropdownOptions.php", {
					dName: dName,
					cliName: cName,
					fType:2,
					testType: 'vl'
				},
				function(data) {
					if (data != "") {
						details = data.split("###");
						$("#fName").html(details[0]);
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
		var cName = $("#fName").val();
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
		$("#fCode").val($('#fName').find(':selected').data('code'));
		var femails = $('#fName').find(':selected').data('emails');
		var fmobilenos = $('#fName').find(':selected').data('mobile-nos');
		var fContactPerson = $('#fName').find(':selected').data('contact-person');
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
		if ($(this).val() == 'male' || $(this).val() == 'not_recorded') {
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
			$("#hivDetection, #noResult").trigger('change');
		}
	});
	$("#noResult").on("change", function() {

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
			$("#noResult").val("no");
			$('#vlResult').attr('disabled', false);
			$('#vlLog').attr('disabled', false);
			$("#vlResult,#vlLog").val('');
			$(".vlResult, .vlLog").hide();
			$("#reasonForFailure").removeClass('isRequired');
			$('#vlResult').removeClass('isRequired');
		} else if (this.value === 'HIV-1 Detected') {
			$("#noResult").val("no");
			$(".vlResult, .vlLog").show();
			$("#noResult").trigger("change");
			$('#vlResult').addClass('isRequired');
		}
	});

	$('#testingPlatform').on("change", function() {
		$(".vlResult, .vlLog").show();
		//$('#vlResult, #noResult').addClass('isRequired');
		$("#noResult").val("");
		//$("#noResult").trigger("change");
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
		if ((text == 'GeneXpert' || str.toLowerCase() == 'genexpert') && $('#noResult').val() != 'yes') {
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
				if (data != "") {
					$("#possibleVlResults").html(data);
					$("#vlResult").attr("disabled", false);
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
		// 	$("#vlFocalPerson").val($('#labId option:selected').attr('data-focalperson')).trigger('change');
		// 	$("#vlFocalPersonPhoneNumber").val($('#labId option:selected').attr('data-focalphone'));
		// }
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