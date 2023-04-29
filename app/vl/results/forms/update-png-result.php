<?php

use App\Utilities\DateUtils;


//global config
$cSampleQuery = "SELECT * FROM global_config";
$cSampleResult = $db->query($cSampleQuery);
$arr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($cSampleResult); $i++) {
	$arr[$cSampleResult[$i]['name']] = $cSampleResult[$i]['value'];
}
$rKey = '';
$sampleCodeKey = 'sample_code_key';
$sampleCode = 'sample_code';
$prefix = $arr['sample_code_prefix'];
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
if ($_SESSION['instanceType'] == 'remoteuser') {
	$rKey = 'R';
	$sampleCodeKey = 'remote_sample_code_key';
	$sampleCode = 'remote_sample_code';
	if (!empty($vlQueryInfo['remote_sample']) && $vlQueryInfo['remote_sample'] == 'yes') {
		$sampleCode = 'remote_sample_code';
	} else {
		$sampleCode = 'sample_code';
	}
	//check user exist in user_facility_map table
	$chkUserFcMapQry = "SELECT user_id FROM user_facility_map WHERE user_id='" . $_SESSION['userId'] . "'";
	$chkUserFcMapResult = $db->query($chkUserFcMapQry);
	if ($chkUserFcMapResult) {
		$pdQuery = "SELECT DISTINCT gd.geo_name,gd.geo_id,gd.geo_code FROM geographical_divisions as gd JOIN facility_details as fd ON fd.facility_state_id=gd.geo_id JOIN user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where gd.geo_parent = 0 AND gd.geo_status='active' AND vlfm.user_id='" . $_SESSION['userId'] . "'";
	}
}
//sample rejection reason
$rejectionQuery = "SELECT * FROM r_vl_sample_rejection_reasons";
$rejectionResult = $db->rawQuery($rejectionQuery);

$bQuery = "SELECT * FROM batch_details";
$bResult = $db->rawQuery($bQuery);
//get import config
$importQuery = "SELECT * FROM instruments WHERE status = 'active'";
$importResult = $db->query($importQuery);


$aQuery = "SELECT * from r_vl_art_regimen";
$aResult = $db->query($aQuery);

$sQuery = "SELECT * from r_vl_sample_type where status='active'";
$sResult = $db->query($sQuery);

$pdResult = $db->query($pdQuery);

//facility details
$facilityQuery = "SELECT * from facility_details where facility_id='" . $vlQueryInfo['facility_id'] . "'";
$facilityResult = $db->query($facilityQuery);
if (!isset($facilityResult[0]['facility_state']) || $facilityResult[0]['facility_state'] == '') {
	$facilityResult[0]['facility_state'] = "";
}
$stateName = $facilityResult[0]['facility_state'];
$stateQuery = "SELECT * from geographical_divisions where geo_name='" . $stateName . "'";
$stateResult = $db->query($stateQuery);
if (!isset($stateResult[0]['geo_code']) || $stateResult[0]['geo_code'] == '') {
	$stateResult[0]['geo_code'] = "";
}
//district details
$districtQuery = "SELECT DISTINCT facility_district from facility_details where facility_state='" . $stateName . "'";
$districtResult = $db->query($districtQuery);

$province = "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
	$province .= "<option value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_code'] . "'>" . ($provinceName['geo_name']) . "</option>";
}

$facility = $general->generateSelectOptions($healthFacilities, $vlQueryInfo['facility_id'], '-- Selecione --');


if (isset($vlQueryInfo['failed_test_date']) && trim($vlQueryInfo['failed_test_date']) != '' && trim($vlQueryInfo['failed_test_date']) != '0000-00-00 00:00:00') {
	$failedDate = explode(" ", $vlQueryInfo['failed_test_date']);
	$vlQueryInfo['failed_test_date'] = DateUtils::humanReadableDateFormat($failedDate[0]) . " " . $failedDate[1];
} else {
	$vlQueryInfo['failed_test_date'] = '';
}
if (isset($vlQueryInfo['art_cd_date']) && trim($vlQueryInfo['art_cd_date']) != '' && $vlQueryInfo['art_cd_date'] != '0000-00-00') {
	$vlQueryInfo['art_cd_date'] = DateUtils::humanReadableDateFormat($vlQueryInfo['art_cd_date']);
} else {
	$vlQueryInfo['art_cd_date'] = '';
}
if (isset($vlQueryInfo['qc_date']) && trim($vlQueryInfo['qc_date']) != '' && $vlQueryInfo['qc_date'] != '0000-00-00') {
	$vlQueryInfo['qc_date'] = DateUtils::humanReadableDateFormat($vlQueryInfo['qc_date']);
} else {
	$vlQueryInfo['qc_date'] = '';
}
if (isset($vlQueryInfo['report_date']) && trim($vlQueryInfo['report_date']) != '' && $vlQueryInfo['report_date'] != '0000-00-00') {
	$vlQueryInfo['report_date'] = DateUtils::humanReadableDateFormat($vlQueryInfo['report_date']);
} else {
	$vlQueryInfo['report_date'] = '';
}
if (isset($vlQueryInfo['clinic_date']) && trim($vlQueryInfo['clinic_date']) != '' && $vlQueryInfo['clinic_date'] != '0000-00-00') {
	$vlQueryInfo['clinic_date'] = DateUtils::humanReadableDateFormat($vlQueryInfo['clinic_date']);
} else {
	$vlQueryInfo['clinic_date'] = '';
}

$disable = "disabled = 'disabled'";
?>
<style>
	:disabled {
		background: white;
	}

	.form-control {
		background: #fff !important;
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
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-pen-to-square"></em> VIRAL LOAD LABORATORY REQUEST FORM </h1>
		<ol class="breadcrumb">
			<li><a href="/dashboard/index.php"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Add Vl Request</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">

		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->

				<div class="box-body">
					<div class="box box-default">
						<div class="box-body">
							<div class="row">
								<div class="col-xs-3 col-md-3">
									<div class="form-group">
										<label for="sampleCode" class="labels">Laboratory ID </label>
										<input type="text" class="form-control sampleCode" id="sampleCode" name="sampleCode" <?php echo $disable; ?> placeholder="Enter Laboratory ID" title="Please enter laboratory ID" style="width:100%;" value="<?php echo (isset($sCode) && $sCode != '') ? $sCode : $vlQueryInfo[$sampleCode]; ?>" onblur="checkNameValidation('form_vl','<?php echo $sampleCode; ?>',this,'<?php echo "vl_sample_id##" . $vlQueryInfo["vl_sample_id"]; ?>','The Laboratory ID that you entered already exists. Please try another ID',null)" />
									</div>
								</div>
							</div>
							<br />
							<table class="table" aria-hidden="true" style="width:100%">
								<tr>
									<td colspan="6" style="font-size: 18px; font-weight: bold;">Section 1: Clinic Information</td>
								</tr>
								<tr>
									<td style="width:13%" class="labels">
										<label for="province">Province </label>
									</td>
									<td style="width:20%">
										<select class="form-control" name="province" id="province" <?php echo $disable; ?> title="Please choose province" style="width:100%;" onchange="getfacilityDetails(this);">
											<option value=""> -- Select -- </option>
											<?php foreach ($pdResult as $provinceName) { ?>
												<option value="<?php echo $provinceName['geo_name'] . "##" . $provinceName['geo_code']; ?>" <?php echo (strtolower($facilityResult[0]['facility_state']) . "##" . $stateResult[0]['geo_code'] == strtolower($provinceName['geo_name']) . "##" . $provinceName['geo_code']) ? "selected='selected'" : "" ?>><?php echo ($provinceName['geo_name']); ?></option>;
											<?php } ?>
										</select>
									</td>
									<td style="width:13%" class="labels">
										<label for="district">District </label>
									</td>
									<td style="width:20%">
										<select class="form-control" name="district" id="district" <?php echo $disable; ?> title="Please choose district" onchange="getfacilityDistrictwise(this);" style="width:100%;">
											<option value=""> -- Select -- </option>
											<?php
											foreach ($districtResult as $districtName) {
											?>
												<option value="<?php echo $districtName['facility_district']; ?>" <?php echo ($facilityResult[0]['facility_district'] == $districtName['facility_district']) ? "selected='selected'" : "" ?>><?php echo ($districtName['facility_district']); ?></option>
											<?php
											}
											?>
										</select>
									</td>
									<td style="width:14%" class="labels">
										<label for="clinicName">Clinic/Ward </label>
									</td>
									<td style="width:20%">
										<select class="form-control" id="clinicName" name="clinicName" title="Please select clinic/ward" <?php echo $disable; ?> style="width:100%;" onchange="getfacilityProvinceDetails(this)">
											<?= $facility; ?>
										</select>
									</td>
								</tr>
								<tr>
									<!--<td>
                        <label for="facility">Clinic/Ward </label>
                        </td>
                        <td>
                          <select class="form-control" id="wardData" name="wardData" < ?php echo $disable; ?> title="Please select ward data" style="width:100%;">
                          <option value="">-- Select --</option>
                          <option value="inpatient" < ?php echo ($vlQueryInfo['ward']=="inpatient")?"selected='selected'":""?>>In-Patient</option>
                          <option value="outpatient" < ?php echo ($vlQueryInfo['ward']=="outpatient")?"selected='selected'":""?>>Out-Patient</option>
                          <option value="anc"< ?php echo ($vlQueryInfo['ward']=="anc")?"selected='selected'":""?>>ANC</option>
                          </select>
                        </td>-->
									<td class="labels">
										<label for="officerName">Requesting Medical Officer </label>
									</td>
									<td>
										<input type="text" class="form-control" name="officerName" id="officerName" <?php echo $disable; ?> placeholder="Officer Name" title="Enter Medical Officer Name" style="width:100%;" value="<?php echo $vlQueryInfo['lab_contact_person']; ?>">
									</td>
									<td class="labels">
										<label for="telephone">Telephone </label>
									</td>
									<td>
										<input type="text" class="form-control" name="telephone" id="telephone" <?php echo $disable; ?> placeholder="Telephone" title="Enter Telephone" style="width:100%;" value="<?php echo $vlQueryInfo['lab_phone_number']; ?>">
									</td>
									<td class="labels">
										<label for="clinicDate">Date </label>
									</td>
									<td>
										<input type="text" class="form-control date" name="clinicDate" id="clinicDate" <?php echo $disable; ?> placeholder="Date" title="Enter Date" style="width:100%;" value="<?php echo $vlQueryInfo['clinic_date']; ?>">
									</td>
								</tr>
								<tr>
									<td colspan="6" style="font-size: 18px; font-weight: bold;">Section 2: Patient Information</td>
								</tr>
								<tr>
									<td class="labels">
										<label for="patientFname">First Name </label>
									</td>
									<td>
										<input type="text" class="form-control" name="patientFname" id="patientFname" <?php echo $disable; ?> placeholder="First Name" title="Enter First Name" style="width:100%;" value="<?php echo $patientFirstName; ?>">
									</td>
									<td class="labels">
										<label for="surName">Last Name </label>
									</td>
									<td>
										<input type="text" class="form-control" name="surName" id="surName" placeholder="Last Name" <?php echo $disable; ?> title="Enter Last Name" style="width:100%;" value="<?php echo $patientLastName; ?>">
									</td>
									<td colspan="2" class="labels">
										<label for="gender">Gender &nbsp;&nbsp;</label>
										<label class="radio-inline">
											<input type="radio" class="" id="genderMale" name="gender" value="male" <?php echo $disable; ?> title="Please check gender" <?php echo ($vlQueryInfo['patient_gender'] == 'male') ? "checked='checked'" : "" ?>> Male
										</label>
										<label class="radio-inline">
											<input type="radio" class="" id="genderFemale" name="gender" value="female" <?php echo $disable; ?> title="Please check gender" <?php echo ($vlQueryInfo['patient_gender'] == 'female') ? "checked='checked'" : "" ?>> Female
										</label>
										<label class="radio-inline">
											<input type="radio" class="" id="genderNotRecorded" name="gender" value="not_recorded" <?php echo $disable; ?> title="Please check gender" <?php echo ($vlQueryInfo['patient_gender'] == 'not_recorded') ? "checked='checked'" : "" ?>> Not Recorded
										</label>
									</td>
								</tr>
								<tr>
									<td class="labels"><label for="dob">Date Of Birth</label></td>
									<td>
										<input type="text" class="form-control date" placeholder="DOB" name="dob" id="dob" <?php echo $disable; ?> title="Please choose DOB" style="width:100%;" value="<?php echo $vlQueryInfo['patient_dob']; ?>" />
									</td>
									<td class="labels"><label for="patientARTNo">Clinic ID </label></td>
									<td>
										<input type="text" class="form-control" placeholder="Enter Clinic ID" name="patientARTNo" id="patientARTNo" <?php echo $disable; ?> title="Please enter Clinic ID" value="<?php echo $vlQueryInfo['patient_art_no']; ?>" style="width:100%;" />
									</td>
									<td></td>
									<td></td>
								</tr>
								<tr>
									<td colspan="6" style="font-size: 18px; font-weight: bold;">Section 3: ART Information</td>
								</tr>
								<tr>
									<td class="labels">
										<label for="artLine">Line of Treatment </label>
									</td>
									<td>
										<label class="radio-inline">
											<input type="radio" class="" id="firstLine" name="artLine" value="1" <?php echo ($vlQueryInfo['line_of_treatment'] == 1) ? 'checked="checked"' : ''; ?> <?php echo $disable; ?> title="Please check ART Line"> First Line
										</label>
										<label class="radio-inline">
											<input type="radio" class="" id="secondLine" name="artLine" value="2" <?php echo ($vlQueryInfo['line_of_treatment'] == 2) ? 'checked="checked"' : ''; ?> <?php echo $disable; ?> title="Please check ART Line"> Second Line
										</label>
									</td>
									<td class="labels">
										<label for="cdCells">CD4(cells/ul) </label>
									</td>
									<td>
										<input type="text" class="form-control" name="cdCells" id="cdCells" <?php echo $disable; ?> placeholder="CD4 Cells" title="CD4 Cells" style="width:100%;" value="<?php echo $vlQueryInfo['art_cd_cells']; ?>">
									</td>
									<td class="labels">
										<label for="cdDate">CD4 Date </label>
									</td>
									<td>
										<input type="text" class="form-control date" name="cdDate" id="cdDate" <?php echo $disable; ?> placeholder="CD4 Date" title="Enter CD4 Date" style="width:100%;" value="<?php echo $vlQueryInfo['art_cd_date']; ?>">
									</td>
								</tr>
								<tr>
									<td class="labels">
										<label for="currentRegimen">Current Regimen </label>
									</td>
									<td>
										<select class="form-control" id="currentRegimen" name="currentRegimen" <?php echo $disable; ?> title="Please choose ART Regimen" onchange="checkValue();" style="width:100%;">
											<option value=""> -- Select -- </option>
											<?php
											foreach ($aResult as $parentRow) {
											?>
												<option value="<?php echo $parentRow['art_code']; ?>" <?php echo ($vlQueryInfo['current_regimen'] == $parentRow['art_code']) ? "selected='selected'" : "" ?>><?php echo $parentRow['art_code']; ?></option>
											<?php
											}
											?>
											<option value="other">Other</option>
										</select>
										<input type="text" class="form-control newArtRegimen" name="newArtRegimen" id="newArtRegimen" placeholder="New Art Regimen" title="Please enter new ART regimen" style="display:none;width:100%;margin-top:1vh;">
									</td>
									<td class="labels">
										<label for="regStartDate">Current Regimen Start Date</label>
									</td>
									<td>
										<input type="text" class="form-control date" name="regStartDate" id="regStartDate" <?php echo $disable; ?> placeholder="Start Date" title="Enter Start Date" style="width:100%;" value="<?php echo $vlQueryInfo['date_of_initiation_of_current_regimen']; ?>">
									</td>
									<td colspan="2" class="clinicalStage"><label for="breastfeeding" class="labels">WHO Clinical Stage</label>&nbsp;&nbsp;
										<label class="radio-inline">
											<input type="radio" id="clinicalOne" name="clinicalStage" value="one" <?php echo $disable; ?> title="WHO Clinical Statge" <?php echo ($vlQueryInfo['who_clinical_stage'] == 'one') ? "checked='checked'" : "" ?>>I
										</label>
										<label class="radio-inline">
											<input type="radio" id="clinicalTwo" name="clinicalStage" value="two" <?php echo $disable; ?> title="WHO Clinical Statge" <?php echo ($vlQueryInfo['who_clinical_stage'] == 'two') ? "checked='checked'" : "" ?>>II
										</label>
										<label class="radio-inline">
											<input type="radio" id="clinicalThree" name="clinicalStage" value="three" <?php echo $disable; ?> title="WHO Clinical Statge" <?php echo ($vlQueryInfo['who_clinical_stage'] == 'three') ? "checked='checked'" : "" ?>>III
										</label>
										<label class="radio-inline">
											<input type="radio" id="clinicalFour" name="clinicalStage" value="four" <?php echo $disable; ?> title="WHO Clinical Statge" <?php echo ($vlQueryInfo['who_clinical_stage'] == 'four') ? "checked='checked'" : "" ?>>IV
										</label>
									</td>
								</tr>
								<tr>
									<td colspan="6" style="font-size: 18px; font-weight: bold;">Section 4: Reason For Testing</td>
								</tr>
								<tr>
									<td colspan="3" class="routine">
										<label for="routine" class="labels">Routine</label><br />
										<label class="radio-inline">
											&nbsp;&nbsp;&nbsp;&nbsp;<input type="radio" id="routineOne" name="reasonForTest" <?php echo $disable; ?> value="First VL, routine monitoring (On ART for at least 6 months)" title="Please Check Routine" <?php echo ($vlQueryInfo['reason_testing_png'] == 'First VL, routine monitoring (On ART for at least 6 months)') ? "checked='checked'" : "" ?>>First VL, routine monitoring (On ART for at least 6 months)
										</label>
										<label class="radio-inline">
											<input type="radio" id="routineTwo" name="reasonForTest" <?php echo $disable; ?> value="Annual routine follow-up VL (Previous VL < 1000 cp/mL)" title="Please Check Routine" <?php echo ($vlQueryInfo['reason_testing_png'] == 'Annual routine follow-up VL (Previous VL < 1000 cp/mL)') ? "checked='checked'" : "" ?>>Annual routine follow-up VL (Previous VL < 1000 cp/mL) </label>
									</td>
									<td colspan="3" class="suspect">
										<label for="suspect" class="labels">Suspected Treatment Failure</label><br />
										<label class="radio-inline">
											<input type="radio" id="suspectOne" name="reasonForTest" value="Suspected TF" <?php echo $disable; ?> title="Please Suspected TF" <?php echo ($vlQueryInfo['reason_testing_png'] == 'Suspected TF') ? "checked='checked'" : "" ?>>Suspected TF
										</label>
										<label class="radio-inline">
											<input type="radio" id="suspectTwo" name="reasonForTest" <?php echo $disable; ?> value="Follow-up VL after EAC (Previous VL >= 1000 cp/mL)" title="Please Suspected TF" <?php echo ($vlQueryInfo['reason_testing_png'] == 'Follow-up VL after EAC (Previous VL >= 1000 cp/mL)') ? "checked='checked'" : "" ?>>Follow-up VL after EAC (Previous VL >= 1000 cp/mL)
										</label>
									</td>
								</tr>
								<tr>
									<td colspan="3">
										<label for="defaulter" class="labels">Defaulter/ LTFU/ Poor Adherer</label><br />
										<label class="radio-inline">
											<input type="radio" id="defaulter" name="reasonForTest" <?php echo $disable; ?> value="VL (after 3 months EAC)" title="Check Defaulter/ LTFU/ Poor Adherer" <?php echo ($vlQueryInfo['reason_testing_png'] == 'VL (after 3 months EAC)') ? "checked='checked'" : "" ?>>VL (after 3 months EAC)
										</label>&nbsp;&nbsp;
									</td>
									<td colspan="3">
										<label for="other">Other</label><br />
										<label class="radio-inline">
											<input type="radio" id="other" name="reasonForTest" <?php echo $disable; ?> value="Re-collection requested by lab" title="Please check Other" <?php echo ($vlQueryInfo['reason_testing_png'] == 'Re-collection requested by lab') ? "checked='checked'" : "" ?>>Re-collection requested by lab
										</label>
										<label for="reason" class="labels">&nbsp;&nbsp;&nbsp;&nbsp;Reason</label>
										<label class="radio-inline">
											<input type="text" class="form-control" id="reason" name="reason" <?php echo $disable; ?> placeholder="Enter Reason" title="Enter Reason" style="width:100%;" />
										</label>
									</td>
								</tr>
								<tr>
									<td colspan="2" style="font-size: 18px; font-weight: bold;">Section 5: Specimen information </td>
									<td colspan="4" style="font-size: 18px; font-weight: bold;" class="labels"> Type of sample to transport</td>
								</tr>
								<tr>
									<td>
										<label for="collectionDate" class="labels">Collection date</label>
									</td>
									<td>
										<input type="text" class="form-control " name="collectionDate" id="collectionDate" <?php echo $disable; ?> placeholder="Collection Date" title="Enter Collection Date" style="width:100%;" value="<?php echo $vlQueryInfo['sample_collection_date']; ?>">
									</td>
									<td colspan="4" class="typeOfSample">
										<label class="radio-inline">
											<input type="radio" id="dbs" name="typeOfSample" value="DBS" <?php echo $disable; ?> title="Check DBS" <?php echo ($vlQueryInfo['sample_to_transport'] == 'DBS') ? "checked='checked'" : "" ?>>DBS
										</label>
										<label class="radio-inline" style="width:46%;">
											<input type="radio" id="wholeBlood" name="typeOfSample" value="Whole blood" <?php echo $disable; ?> title="Check Whole blood" style="margin-top:10px;" <?php echo ($vlQueryInfo['sample_to_transport'] == 'Whole blood') ? "checked='checked'" : "" ?>>Whole Blood
											<input type="text" name="wholeBloodOne" id="wholeBloodOne" class="form-control" style="width: 20%;" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['whole_blood_ml']; ?>" />&nbsp; x &nbsp;<input type="text" name="wholeBloodTwo" id="wholeBloodTwo" class="form-control" style="width: 20%;" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['whole_blood_vial']; ?>" />&nbsp;vial(s)
										</label>
										<label class="radio-inline" style="width:42%;">
											<input type="radio" id="plasma" name="typeOfSample" value="Plasma" title="Check Plasma" <?php echo $disable; ?> style="margin-top:10px;" <?php echo ($vlQueryInfo['sample_to_transport'] == 'Plasma') ? "checked='checked'" : "" ?>>Plasma
											<input type="text" name="plasmaOne" id="plasmaOne" class="form-control" style="width: 20%;" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['plasma_ml']; ?>" />&nbsp;ml x &nbsp;<input type="text" name="plasmaTwo" id="plasmaTwo" class="form-control" style="width: 20%;" <?php echo $disable; ?> value="<?php echo $vlQueryInfo['plasma_vial']; ?>" />&nbsp;vial(s)
										</label>
									</td>
								</tr>
								<tr>
									<td class="labels">
										<label for="collectedBy">Specimen Collected by</label>
									</td>
									<td>
										<input type="text" class="form-control " name="collectedBy" id="collectedBy" placeholder="Collected By" title="Enter Collected By" <?php echo $disable; ?> style="width:100%;" value="<?php echo $vlQueryInfo['sample_collected_by']; ?>">
									</td>
									<td class="processTime labels"><label for="processTime">For onsite plasma processing only</label></td>
									<td>
										<input type="text" name="processTime" id="processTime" class="form-control" style="width: 100%;" placeholder="Time" <?php echo $disable; ?> title="Processing Time" value="<?php echo $vlQueryInfo['plasma_process_time']; ?>" />
									</td>
									<td><label for="processTech labels">Processing Tech</label></td>
									<td>
										<input type="text" name="processTech" id="processTech" class="form-control" style="width: 100%;" placeholder="Processing Tech" <?php echo $disable; ?> title="Processing Tech" value="<?php echo $vlQueryInfo['plasma_process_tech']; ?>" />
									</td>
								</tr>
							</table>
							<form class="form-inline" method='post' name='vlRequestForm' id='vlRequestForm' autocomplete="off" action="updateVlRequestHelperPng.php">
								<table class="table" aria-hidden="true" style="width:100%">
									<tr>
										<td colspan="6" class="labels" style="font-size: 18px; font-weight: bold;">CPHL Use Only </td>
									</tr>
									<tr>
										<td><label for="isSampleRejected" class="labels">Sample Quality</label></td>
										<td>
											<label class="radio-inline">
												<input type="radio" id="sampleQtyAccept" name="isSampleRejected" value="no" title="Check Sample Quality" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'no') ? "checked='checked'" : "" ?>>Accept
											</label>
											<label class="radio-inline">
												<input type="radio" id="sampleQtyReject" name="isSampleRejected" value="yes" title="Check Sample Quality" <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? "checked='checked'" : "" ?>>Reject
											</label>
										</td>
										<td class="rejectionReason" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? "" : "none"; ?>"><label for="rejectionReason" class="labels">Reason <span class="mandatory">*</span></label></td>
										<td class="rejectionReason" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? "" : "none"; ?>">
											<select name="rejectionReason" id="rejectionReason" class="form-control <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? "isRequired" : ""; ?>" title="Please choose reason" style="width:100%;">
												<option value="">-- Select --</option>
												<?php foreach ($rejectionResult as $reject) { ?>
													<option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($vlQueryInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? "selected='selected'" : "" ?>><?= $reject['rejection_reason_name']; ?></option>
												<?php } ?>
											</select>
										</td>

										<td class="laboratoryId labels"><label for="laboratoryId">Laboratory Name <span class="mandatory">*</span></label></td>
										<td>
											<select name="laboratoryId" id="laboratoryId" class="form-control isRequired" title="Please choose lab name" style="width:100%;">
												<?= $general->generateSelectOptions($testingLabs, $vlQueryInfo['lab_id'], '-- Select --'); ?>
											</select>
										</td>
										<td class="reasonequ" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? "none" : ""; ?>"></td>
										<td class="reasonequ" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? "none" : ""; ?>"></td>
									</tr>
									<tr class="show-rejection" style="display:none;">
										<th scope="row" class="labels">Rejection Date<span class="mandatory">*</span></th>
										<td><input value="<?php echo DateUtils::humanReadableDateFormat($vlQueryInfo['rejection_on']); ?>" class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" /></td>
										<td></td>
										<td></td>
									</tr>
									<tr>
										<td class="sampleType labels"><label for="sampleType">Sample Type Received</label></td>
										<td>
											<select name="sampleType" id="sampleType" class="form-control" title="Please choose Specimen type" style="width:100%;">
												<option value=""> -- Select -- </option>
												<?php foreach ($sResult as $name) { ?>
													<option value="<?php echo $name['sample_id']; ?>" <?php echo ($vlQueryInfo['sample_type'] == $name['sample_id']) ? "selected='selected'" : "" ?>><?= $name['sample_name']; ?></option>
												<?php } ?>
											</select>
										</td>
										<td class="receivedDate labels"><label for="receivedDate">Date Received</label></td>
										<td>
											<input type="text" class="form-control" name="receivedDate" id="receivedDate" placeholder="Received Date" title="Enter Received Date" style="width:100%;" value="<?php echo $vlQueryInfo['sample_received_at_vl_lab_datetime']; ?>">
										</td>
										<td class="techName labels"><label for="techName">Lab Tech. Name</label></td>
										<td>
											<input type="text" class="form-control" name="techName" id="techName" placeholder="Enter Lab Technician Name" title="Please enter lab technician name" style="width:100%;" value="<?php echo $vlQueryInfo['tech_name_png']; ?>">
										</td>
									</tr>
									<tr>
										<td class="labels"><label for="testDate">Test date <span class="mandatory">*</span></label></td>
										<td>
											<input type="text" class="form-control isRequired" name="testDate" id="testDate" placeholder="Test Date" title="Enter Testing Date" style="width:100%;" value="<?php echo $vlQueryInfo['sample_tested_datetime']; ?>">
										</td>
										<td class="labels"><label for="testingTech">Testing Platform <span class="mandatory">*</span></label></td>
										<td>
											<select name="testingTech" id="testingTech" class="form-control isRequired" title="Please choose VL Testing Platform" style="width:100%;">
												<option value="">-- Select --</option>
												<?php foreach ($importResult as $mName) { ?>
													<option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit']; ?>" <?php echo ($vlQueryInfo['vl_test_platform'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] == $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit']) ? "selected='selected'" : "" ?>><?php echo $mName['machine_name']; ?></option>
												<?php
												}
												?>
											</select>
										</td>
										<td class="vlResult labels" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? "none" : ""; ?>"><label for="vlResult">VL result <span class="mandatory">*</span></label></td>
										<td class="vlResult" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? "none" : ""; ?>">
											<input type="text" class="form-control <?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? "" : "isRequired"; ?>" name="vlResult" id="vlResult" placeholder="VL Result" title="Enter VL Result" style="width:100%;" value="<?php echo $vlQueryInfo['result']; ?>">
										</td>
										<td class="vlresultequ" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? "" : "none"; ?>"></td>
										<td class="vlresultequ" style="display:<?php echo ($vlQueryInfo['is_sample_rejected'] == 'yes') ? "" : "none"; ?>"></td>
									</tr>
									<tr>
										<td class="labels"><label for="batchQuality">Batch quality</label></td>
										<td>
											<label class="radio-inline">
												<input type="radio" id="passed" name="batchQuality" value="passed" title="Batch Quality" <?php echo ($vlQueryInfo['batch_quality'] == 'passed') ? "checked='checked'" : "" ?>>Passed
											</label>
											<label class="radio-inline">
												<input type="radio" id="failed" name="batchQuality" value="failed" title="Batch Quality" <?php echo ($vlQueryInfo['batch_quality'] == 'failed') ? "checked='checked'" : "" ?>>Failed
											</label>
										</td>
										<td class="labels"><label for="testQuality">Sample test quality</label></td>
										<td>
											<label class="radio-inline">
												<input type="radio" id="passed" name="testQuality" value="passed" title="Test Quality" <?php echo ($vlQueryInfo['sample_test_quality'] == 'passed') ? "checked='checked'" : "" ?>>Passed
											</label>
											<label class="radio-inline">
												<input type="radio" id="failed" name="testQuality" value="invalid" title="Test Quality" <?php echo ($vlQueryInfo['sample_test_quality'] == 'invalid') ? "checked='checked'" : "" ?>>Invalid
											</label>
										</td>
										<td class="labels"><label for="vlResult">Batch</label></td>
										<td>
											<select name="batchNo" id="batchNo" class="form-control" title="Please choose batch number" style="width:100%;">
												<option value="">-- Select --</option>
												<?php foreach ($bResult as $bName) { ?>
													<option value="<?php echo $bName['batch_id']; ?>" <?php echo ($vlQueryInfo['sample_batch_id'] == $bName['batch_id']) ? "selected='selected'" : "" ?>><?php echo $bName['batch_code']; ?></option>
												<?php
												}
												?>
											</select>
										</td>
									</tr>
									<tr>
										<th colspan="6" style="font-size: 18px; font-weight: bold;">For failed / invalid runs only</th>
									</tr>
									<tr>
										<td class="labels"><label for="testDate">Repeat Test date</label></td>
										<td>
											<input type="text" class="form-control " name="failedTestDate" id="failedTestDate" placeholder="Test Date" title="Enter Testing Date" style="width:100%;" value="<?php echo $vlQueryInfo['failed_test_date']; ?>">
										</td>
										<td class="labels"><label for="testingTech">Testing Platform</label></td>
										<td>
											<select name="failedTestingTech" id="failedTestingTech" class="form-control" title="Please choose VL Testing Platform" style="width:100%;">
												<option value="">-- Select --</option>
												<?php foreach ($importResult as $mName) { ?>
													<option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit']; ?>" <?php echo ($vlQueryInfo['failed_test_tech'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit'] == $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit']) ? "selected='selected'" : "" ?>><?php echo $mName['machine_name']; ?></option>
												<?php
												}
												?>
											</select>
										</td>
										<td class="labels"><label for="vlResult">VL result</label></td>
										<td>
											<input type="text" class="form-control " name="failedvlResult" id="failedvlResult" placeholder="VL Result" title="Enter VL Result" style="width:100%;" value="<?php echo $vlQueryInfo['failed_vl_result']; ?>">
										</td>
									</tr>
									<tr>
										<td class="labels"><label for="failedbatchQuality">Batch quality</label></td>
										<td>
											<label class="radio-inline">
												<input type="radio" id="passed" name="failedbatchQuality" value="passed" title="Batch Quality" <?php echo ($vlQueryInfo['failed_batch_quality'] == 'passed') ? "checked='checked'" : "" ?>>Passed
											</label>
											<label class="radio-inline">
												<input type="radio" id="failed" name="failedbatchQuality" value="failed" title="Batch Quality" <?php echo ($vlQueryInfo['failed_batch_quality'] == 'failed') ? "checked='checked'" : "" ?>>Failed
											</label>
										</td>
										<td class="labels"><label for="failedtestQuality">Sample test quality</label></td>
										<td>
											<label class="radio-inline">
												<input type="radio" id="passed" name="failedtestQuality" value="passed" title="Test Quality" <?php echo ($vlQueryInfo['failed_sample_test_quality'] == 'passed') ? "checked='checked'" : "" ?>>Passed
											</label>
											<label class="radio-inline">
												<input type="radio" id="failed" name="failedtestQuality" value="invalid" title="Test Quality" <?php echo ($vlQueryInfo['failed_sample_test_quality'] == 'invalid') ? "checked='checked'" : "" ?>>Invalid
											</label>
										</td>
										<td class="labels"><label for="vlResult">Batch</label></td>
										<td>
											<select name="failedbatchNo" id="failedbatchNo" class="form-control" title="Please choose batch number" style="width:100%;">
												<option value="">-- Select --</option>
												<?php foreach ($bResult as $bName) { ?>
													<option value="<?php echo $bName['batch_id']; ?>" <?php echo ($vlQueryInfo['failed_batch_id'] == $bName['batch_id']) ? "selected='selected'" : "" ?>><?php echo $bName['batch_code']; ?></option>
												<?php
												}
												?>
											</select>
										</td>
									</tr>
									<tr>
										<td class="labels"><label for="finalViralResult">Final Viral Load Result (copies/ml)</label></td>
										<td>
											<input type="text" class="form-control" name="finalViralResult" id="finalViralResult" placeholder="Viral Load Result" title="Enter Viral Result" style="width:100%;" value="<?php echo $vlQueryInfo['result']; ?>">
										</td>
										<td class="labels"><label for="testQuality">QC Tech Name</label></td>
										<td>
											<input type="text" class="form-control" name="qcTechName" id="qcTechName" placeholder="QC Tech Name" title="Enter QC Tech Name" style="width:100%;" value="<?php echo $vlQueryInfo['qc_tech_name']; ?>">
										</td>
										<td class="labels"><label for="finalViralResult">Report Date</label></td>
										<td>
											<input type="text" class="form-control date" name="reportDate" id="reportDate" placeholder="Report Date" title="Enter Report Date" style="width:100%;" value="<?php echo $vlQueryInfo['report_date']; ?>">
										</td>
									</tr>
									<tr>
										<td class="labels"><label for="finalViralResult">QC Tech Signature</label></td>
										<td>
											<input type="text" class="form-control" name="qcTechSign" id="qcTechSign" placeholder="QC Tech Signature" title="Enter QC Tech Signature" style="width:100%;" value="<?php echo $vlQueryInfo['qc_tech_sign']; ?>">
										</td>
										<td class="labels"><label for="testQuality">QC Date</label></td>
										<td>
											<input type="text" class="form-control date" name="qcDate" id="qcDate" placeholder="QC Date" title="Enter QC Date" style="width:100%;" value="<?php echo $vlQueryInfo['qc_date']; ?>">
										</td>
										<td class="labels"><label for="status">Status</label></td>
										<td>
											<select class="form-control" id="status" name="status" title="Please select test status" style="width:100%;">
												<option value="">-- Select --</option>
												<option value="7" <?php echo (7 == $vlQueryInfo['result_status']) ? 'selected="selected"' : ''; ?>>Accepted</option>
												<option value="4" <?php echo (4 == $vlQueryInfo['result_status']) ? 'selected="selected"' : ''; ?>>Rejected</option>
											</select>
										</td>
									</tr>
									<tr>
										<td style="width:14%;" class="labels"><label for="reviewedBy"> Reviewed By </label></td>
										<td style="width:14%;">
											<select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose reviewed by" style="width: 100%;">
												<?= $general->generateSelectOptions($userInfo, $vlQueryInfo['result_reviewed_by'], '-- Select --'); ?>
											</select>
										</td>
										<td style="width:14%;" class="labels"><label for="reviewedOn"> Reviewed On </label></td>
										<td style="width:14%;">
											<input type="text" name="reviewedOn" value="<?php echo $vlQueryInfo['result_reviewed_datetime']; ?>" id="reviewedOn" class="dateTime form-control" placeholder="Reviewed on" title="Please enter the reviewed on" />
										</td>
									</tr>
									<tr class="change-reason">
										<th class="change-reason labels" style="display: none;">Reason for Changing <span class="mandatory">*</span></td>
										<td class="change-reason" style="display: none;"><textarea type="text" name="reasonForResultChanges" id="reasonForResultChanges" class="form-control date" placeholder="Enter the reason for changing" title="Please enter the reason for changing"></textarea></td>
										<th scope="row"></th>
										<td></td>
									</tr>
								</table>
						</div>
					</div>
				</div>
				<!-- /.box-body -->
				<div class="box-footer">
					<input type="hidden" name="revised" id="revised" value="no" />
					<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
					<input type="hidden" name="vlSampleId" id="vlSampleId" value="<?php echo $vlQueryInfo['vl_sample_id']; ?>" />
					<a href="vlTestResult.php" class="btn btn-default"> Cancel</a>
				</div>
				<!-- /.box-footer -->
				</form>
				<!-- /.row -->
			</div>

		</div>
		<!-- /.box -->

	</section>
	<!-- /.content -->
</div>
<script>
	provinceName = true;
	facilityName = true;
	$(document).ready(function() {
		$('.date').datepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: 'dd-M-yy',
			timeFormat: "HH:mm",
			yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
		}).click(function() {
			$('.ui-datepicker-calendar').show();
		});
		$('.date').mask('99-aaa-9999');
		$('#collectionDate,#receivedDate,#testDate,#failedTestDate').mask('99-aaa-9999 99:99');

		$('#collectionDate,#receivedDate,#testDate,#failedTestDate').datetimepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: 'dd-M-yy',
			timeFormat: "HH:mm",
			onChangeMonthYear: function(year, month, widget) {
				setTimeout(function() {
					$('.ui-datepicker-calendar').show();
				});
			},
			yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
		}).click(function() {
			$('.ui-datepicker-calendar').show();
		});

		$('#processTime').timepicker({
			changeMonth: true,
			changeYear: true,
			timeFormat: "HH:mm",
			yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
		}).click(function() {
			$('.ui-datepicker-calendar').hide();
		});
	});

	function validateNow() {
		flag = deforayValidator.init({
			formId: 'vlRequestForm'
		});
		$('.isRequired').each(function() {
			($(this).val() == '') ? $(this).css('background-color', '#FFFF99'): $(this).css('background-color', '#FFFFFF')
		});
		$("#saveNext").val('save');
		if (flag) {
			$.blockUI();
			document.getElementById('vlRequestForm').submit();
		}
	}

	function getfacilityDetails(obj) {
		$.blockUI();
		var cName = $("#clinicName").val();
		var pName = $("#province").val();
		$('#telephone').val('');
		if (pName != '' && provinceName && facilityName) {
			facilityName = false;
		}
		if (pName != '') {
			if (provinceName) {
				$.post("/includes/siteInformationDropdownOptions.php", {
						pName: pName,
						testType: 'vl'
					},
					function(data) {
						if (data != "") {
							details = data.split("###");
							$("#clinicName").html(details[0]);
							$("#district").html(details[1]);
							$("#clinicianName").val(details[2]);
						}
					});
			}
		} else if (pName == '' && cName == '') {
			provinceName = true;
			facilityName = true;
			$("#province").html("<?php echo $province; ?>");
			$("#clinicName").html("<?php echo $facility; ?>");
		}
		$.unblockUI();
	}

	function getfacilityDistrictwise(obj) {
		$.blockUI();
		var dName = $("#district").val();
		var cName = $("#clinicName").val();
		$('#telephone').val('');
		if (dName != '') {
			$.post("/includes/siteInformationDropdownOptions.php", {
					dName: dName,
					cliName: cName,
					testType: 'vl'
				},
				function(data) {
					if (data != "") {
						details = data.split("###");
						$("#clinicName").html(details[0]);
					}
				});
		}
		$.unblockUI();
	}

	function getfacilityProvinceDetails(obj) {
		$.blockUI();
		$('#telephone').val($("#clinicName").find(":selected").attr("data-mobile-nos"));
		$.unblockUI();
		////check facility name
		//var cName = $("#clinicName").val();
		//var pName = $("#province").val();
		//if(cName!='' && provinceName && facilityName){
		//  provinceName = false;
		//}
		//if(cName!='' && facilityName){
		//  $.post("/includes/siteInformationDropdownOptions.php", { cName : cName,testType: 'vl'},
		//  function(data){
		//    if(data != ""){
		//      details = data.split("###");
		//      $("#province").html(details[0]);
		//      $("#district").html(details[1]);
		//      $("#clinicianName").val(details[2]);
		//    }
		//  });
		//}else if(pName=='' && cName==''){
		//  provinceName = true;
		//  facilityName = true;
		//  $("#province").html("< ?php echo $province;?>");
		//  $("#clinicName").html("< ?php echo $facility;?>");
		//}
	}

	function checkValue() {
		var artRegimen = $("#currentRegimen").val();
		if (artRegimen == 'other') {
			$(".newArtRegimen").show();
			$("#newArtRegimen").addClass("isRequired");
		} else {
			$(".newArtRegimen").hide();
			$("#newArtRegimen").removeClass("isRequired");
		}
	}

	function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
		var removeDots = obj.value.replace(/\./g, "");
		var removeDots = removeDots.replace(/\,/g, "");
		//str=obj.value;
		removeDots = removeDots.replace(/\s{2,}/g, ' ');
		$.post("/includes/checkDuplicate.php", {
				tableName: tableName,
				fieldName: fieldName,
				value: removeDots.trim(),
				fnct: fnct,
				format: "html"
			},
			function(data) {
				if (data === '1') {
					alert(alrt);
					$('#' + obj.id).val('');
					duplicateName = false;
				}
			});
	}

	$("input:radio[name=isSampleRejected]").on("change", function() {
		if ($(this).val() == 'yes') {
			$(".rejectionReason,.vlresultequ").show();
			$(".reasonequ,.vlResult").hide();
			$('#rejectionReason').addClass("isRequired");
			$('#vlResult').removeClass("isRequired");
		} else {
			$(".reasonequ,.vlResult").show();
			$(".rejectionReason,.vlresultequ").hide();
			$('#rejectionReason').removeClass("isRequired");
			$('#vlResult').addClass("isRequired");
		}
	})
</script>