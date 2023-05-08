<?php

$rKey = '';
$sampleCodeKey = 'sample_code_key';
$sampleCode = 'sample_code';
$prefix = $arr['sample_code_prefix'];
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
if ($_SESSION['instanceType'] == 'remoteuser') {
	$rKey = 'R';
	$sampleCodeKey = 'remote_sample_code_key';
	$sampleCode = 'remote_sample_code';
	//check user exist in user_facility_map table
	$chkUserFcMapQry = "SELECT user_id FROM user_facility_map where user_id='" . $_SESSION['userId'] . "'";
	$chkUserFcMapResult = $db->query($chkUserFcMapQry);
	if ($chkUserFcMapResult) {
        $pdQuery = "SELECT DISTINCT gd.geo_name,gd.geo_id,gd.geo_code FROM geographical_divisions as gd JOIN facility_details as fd ON fd.facility_state_id=gd.geo_id JOIN user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where gd.geo_parent = 0 AND gd.geo_status='active' AND vlfm.user_id='" . $_SESSION['userId'] . "'";
	}
}
$bQuery = "SELECT * FROM batch_details";
$bResult = $db->rawQuery($bQuery);
$aQuery = "SELECT * from r_vl_art_regimen";
$aResult = $db->query($aQuery);

$pdResult = $db->query($pdQuery);
$province = "<option data-code='' data-name='' value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
	$province .= "<option data-code='" . $provinceName['geo_code'] . "' data-province-id='" . $provinceName['geo_id'] . "' data-name='" . substr(strtoupper($provinceName['geo_name']), 0, 3) . "' value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_code'] . "'>" . ($provinceName['geo_name']) . "</option>";
}

$facility = $general->generateSelectOptions($healthFacilities, null, '-- Select --');

?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-pen-to-square"></em> VIRAL LOAD LABORATORY REQUEST FORM </h1>
		<ol class="breadcrumb">
			<li><a href="/dashboard/index.php"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Add VL Request</li>
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
				<form class="form-inline" method='post' name='vlRequestForm' id='vlRequestForm' autocomplete="off" action="addVlRequestHelper.php">
					<div class="box-body">
						<div class="box box-default">
							<div class="box-body">
								<div class="row">
									<div class="col-xs-3 col-md-3">
										<div class="form-group">
											<label for="sampleCode" class="labels">Laboratory ID <span class="mandatory">*</span></label>
											<input type="text" class="form-control sampleCode isRequired" id="sampleCode" name="sampleCode" placeholder="Enter Laboratory ID" title="Please enter laboratory ID" style="width:100%;" onblur="checkSampleNameValidation('form_vl','<?php echo $sampleCode; ?>',this.id,null,'This sample code already exists. Please try another Sample Code.',null)" readonly="readonly" />
										</div>
									</div>

									<?php if ($_SESSION['instanceType'] == 'remoteuser') { ?>

										<div class="col-xs-3 col-md-3">
											<div class="">
												<label for="labId" class="labels">VL Testing Lab <span class="mandatory">*</span></label>
												<select name="labId" id="labId" class="form-control isRequired" title="Please choose a VL testing hub" style="width:100%;">
													<?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
												</select>
											</div>
										</div>

									<?php } ?>

								</div>

								<br />
								<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
									<tr>
										<td colspan="6" style="font-size: 18px; font-weight: bold;">Section 1: Clinic Information</td>
									</tr>
									<tr>
										<td style="width:16%" class="labels">
											<label for="province">Province <span class="mandatory">*</span></label>
										</td>
										<td style="width:20%">
											<select class="form-control isRequired" name="province" id="province" title="Please choose province" style="width:100%;" onchange="getfacilityDetails(this);sampleCodeGeneration();">
												<?php echo $province; ?>
											</select>
										</td>
										<td style="width:10%" class="labels">
											<label for="district">District <span class="mandatory">*</span></label>
										</td>
										<td style="width:20%">
											<select class="form-control isRequired" name="district" id="district" title="Please choose district" onchange="getfacilityDistrictwise(this);" style="width:100%;">
												<option value=""> -- Select -- </option>
											</select>
										</td>
										<td style="width:10%" class="labels">
											<label for="fName">Clinic/Ward <span class="mandatory">*</span></label>
										</td>
										<td style="width:20%">
											<select class="form-control isRequired" id="fName" name="fName" title="Please select clinic/ward" style="width:100%;" onchange="getfacilityProvinceDetails(this)">
												<?php echo $facility; ?>
											</select>
										</td>
									</tr>
									<tr>
										<!--<td style="width:10%">
                        <label for="facility">Clinic/Ward <span class="mandatory">*</span></label>
                        </td>
                        <td style="width:20%">
                          <select class="form-control isRequired" id="wardData" name="wardData" title="Please select ward data" style="width:100%;">
			    <option value="">-- Select --</option>
			    <option value="inpatient">In-Patient</option>
			    <option value="outpatient">Out-Patient</option>
			    <option value="anc">ANC</option>
			  </select>
                        </td>-->
										<td style="width:16%" class="labels">
											<label for="reqClinician">Requesting Medical Officer <span class="mandatory">*</span></label>
										</td>
										<td style="width:20%">
											<input type="text" class="form-control isRequired " name="reqClinician" id="reqClinician" placeholder="Officer Name" title="Enter Medical Officer Name" style="width:100%;">
										</td>
										<td style="width:10%" class="labels">
											<label for="reqClinicianPhoneNumber">Clinic/Ward Telephone </label>
										</td>
										<td style="width:20%">
											<input type="text" class="form-control forceNumeric" name="reqClinicianPhoneNumber" id="reqClinicianPhoneNumber" placeholder="Telephone" title="Enter Telephone" style="width:100%;">
										</td>
										<td style="width:10%" class="labels">
											<label for="requestDate">Date Requested</label>
										</td>
										<td style="width:20%">
											<input type="text" class="form-control date" name="requestDate" id="requestDate" placeholder="Requested on Date" title="Enter Date" style="width:100%;">
										</td>
									</tr>
									<tr>
										<td colspan="6" style="font-size: 18px; font-weight: bold;">Section 2: Patient Information</td>
									</tr>
									<tr>
										<td>
											<label for="artNo" class="labels">Patient ID <span class="mandatory">*</span></label>
										</td>
										<td>
											<input type="text" class="form-control isRequired" placeholder="Enter Patient ID" name="artNo" id="artNo" title="Please enter Patient ID" style="width:100%;" />
											<span class="artNoGroup" id="artNoGroup"></span>

										</td>

										<td>
											<label for="gender" class="labels">Gender &nbsp;&nbsp;</label>
										</td>
										<td colspan="1">
											<select class="form-control" name="gender" id="gender" title="Please choose patient gender" style="width:100%;" onchange="">
												<option value="">-- Select --</option>
												<option value="male">Male</option>
												<option value="female">Female</option>
												<option value="not_recorded">Not Reported</option>
											</select>
										</td>
									</tr>

									<tr class="femaleFactor">
										<td>
											<label for="patientPregnant" class="labels">Patient Pregnant ?</label>
										</td>
										<td>
											<select class="form-control" name="patientPregnant" id="patientPregnant" title="Please choose if patient is pregnant" style="width:100%;" onchange="">
												<option value="">-- Select --</option>
												<option value="yes">Yes</option>
												<option value="no">No</option>
												<option value="not_reported">Not Reported</option>
											</select>
										</td>
										<td>
											<label for="breastfeeding" class="labels">Patient Breastfeeding ?</label>
										</td>
										<td>
											<select class="form-control" name="breastfeeding" id="breastfeeding" title="Please choose if patient is breastfeeding" onchange="" style="width:100%;">
												<option value=""> -- Select -- </option>
												<option value="yes">Yes</option>
												<option value="no">No</option>
												<option value="not_reported">Not Reported</option>
											</select>
										</td>
										<td></td>
										<td></td>
									</tr>
									<tr>
										<td><label for="dob" class="labels">Date Of Birth</label></td>
										<td>
											<input type="text" class="form-control date" placeholder="DOB" name="dob" id="dob" title="Please choose DOB" onchange="getAge();" style="width:100%;" />
										</td>
										<td><label for="ageInYears" class="labels">If DOB unknown, Age in Years</label></td>
										<td>
											<input type="text" name="ageInYears" id="ageInYears" class="form-control forceNumeric" maxlength="2" placeholder="Age in Year" title="Enter age in years" />
										</td>
										<td><label for="ageInMonths" class="labels">If Age < 1, Age in Months </label>
										</td>
										<td>
											<input type="text" name="ageInMonths" id="ageInMonths" class="form-control forceNumeric" maxlength="2" placeholder="Age in Month" title="Enter age in months" />
										</td>

									</tr>
									<tr>

									</tr>
									<tr>
										<td colspan="6" style="font-size: 18px; font-weight: bold;">Section 3: ART Information</td>
									</tr>
									<tr>
										<td style="width:8%">
											<label for="lineOfTreatment" class="labels">Line of Treatment </label>
										</td>
										<td style="width:10%">
											<label class="radio-inline">
												<input type="radio" class="" id="firstLine" name="lineOfTreatment" value="1" title="Please check ART Line"> First Line
											</label><br>
											<label class="radio-inline">
												<input type="radio" class="" id="secondLine" name="lineOfTreatment" value="2" title="Please check ART Line"> Second Line
											</label>
										</td>
										<td style="width:8%">
											<label for="cdCells" class="labels">CD4(cells/ul) </label>
										</td>
										<td style="width:10%">
											<input type="text" class="form-control" name="cdCells" id="cdCells" placeholder="CD4 Cells" title="CD4 Cells" style="width:100%;">
										</td>
										<td style="width:8%">
											<label for="cdDate" class="labels">CD4 Date </label>
										</td>
										<td>
											<input type="text" class="form-control date" name="cdDate" id="cdDate" placeholder="CD4 Date" title="Enter CD4 Date" style="width:100%;">
										</td>
									</tr>
									<tr>
										<td style="width:8%">
											<label for="artRegimen" class="labels">Current Regimen </label>
										</td>
										<td style="width:10%">
											<select class="form-control" id="artRegimen" name="artRegimen" title="Please choose ART Regimen" onchange="checkValue();" style="width: 100%;">
												<option value=""> -- Select -- </option>
												<?php
												foreach ($aResult as $parentRow) {
												?>
													<option value="<?php echo $parentRow['art_code']; ?>"><?php echo $parentRow['art_code']; ?></option>
												<?php
												}
												?>
												<option value="other">Other</option>
											</select>
											<input type="text" class="form-control newArtRegimen" name="newArtRegimen" id="newArtRegimen" placeholder="New Art Regimen" title="Please enter new ART regimen" style="display:none;width:100%;margin-top:1vh;">
										</td>
										<td>
											<label for="regimenInitiatedOn" class="labels">Current Regimen Start Date</label>
										</td>
										<td>
											<input type="text" class="form-control date" name="regimenInitiatedOn" id="regimenInitiatedOn" placeholder="Start Date" title="Enter Start Date" style="width:100%;">
										</td>
										<td colspan="2" class="clinicalStage labels"><label for="breastfeeding">WHO Clinical Stage</label>&nbsp;&nbsp;
											<label class="radio-inline">
												<input type="radio" id="clinicalOne" name="clinicalStage" value="one" title="WHO Clinical Statge">I
											</label>
											<label class="radio-inline">
												<input type="radio" id="clinicalTwo" name="clinicalStage" value="two" title="WHO Clinical Statge">II
											</label>
											<label class="radio-inline">
												<input type="radio" id="clinicalThree" name="clinicalStage" value="three" title="WHO Clinical Statge">III
											</label>
											<label class="radio-inline">
												<input type="radio" id="clinicalFour" name="clinicalStage" value="four" title="WHO Clinical Statge">IV
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
												&nbsp;&nbsp;&nbsp;&nbsp;<input type="radio" id="routineOne" name="reasonForVLTesting" value="First VL, routine monitoring (On ART for at least 6 months)" title="Please Check Routine">First VL, routine monitoring (On ART for at least 6 months)
											</label>
											<label class="radio-inline">
												<input type="radio" id="routineTwo" name="reasonForVLTesting" value="Annual routine follow-up VL (Previous VL < 1000 cp/mL)" title="Please Check Routine">Annual routine follow-up VL (Previous VL < 1000 cp/mL) </label>
										</td>
										<td colspan="3" class="suspect">
											<label for="suspect" class="labels">Suspected Treatment Failure</label><br />
											<label class="radio-inline">
												<input type="radio" id="suspectOne" name="reasonForVLTesting" value="Suspected TF" title="Please Suspected TF">Suspected TF
											</label>
											<label class="radio-inline">
												<input type="radio" id="suspectTwo" name="reasonForVLTesting" value="Follow-up VL after EAC (Previous VL >= 1000 cp/mL)" title="Please Suspected TF">Follow-up VL after EAC (Previous VL >= 1000 cp/mL)
											</label>
										</td>
									</tr>
									<tr>
										<td colspan="3">
											<label for="defaulter" class="labels">Defaulter/ LTFU/ Poor Adherer</label><br />
											<label class="radio-inline">
												<input type="radio" id="defaulter" name="reasonForVLTesting" value="VL (after 3 months EAC)" title="Check Defaulter/ LTFU/ Poor Adherer">VL (after 3 months EAC)
											</label>&nbsp;&nbsp;
										</td>
										<!--<td colspan="3">
											<label for="other">Other</label><br />
											<label class="radio-inline">
												<input type="radio" id="other" name="reasonForVLTesting" value="Re-collection requested by lab" title="Please check Other">Re-collection requested by lab
											</label>
											<label for="reason" class="labels">&nbsp;&nbsp;&nbsp;&nbsp;Reason</label>
											<label class="radio-inline">
												<input type="text" class="form-control" id="reason" name="reason" placeholder="Enter Reason" title="Enter Reason" style="width:100%;" readonly />
											</label>
										</td>-->
									</tr>
									<tr>
										<td colspan="2" style="font-size: 18px; font-weight: bold;">Section 5: Specimen information </td>
										<td colspan="4" style="font-size: 18px; font-weight: bold;" class="labels"> Type of sample to transport</td>
									</tr>
									<tr>
										<td class="labels">
											<label for="sampleCollectionDate">Collection Date <span class="mandatory">*</span></label>
										</td>
										<td>
											<label class="radio-inline">
												<input type="text" class="form-control isRequired" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" title="Please enter the sample collection date" onchange="sampleCodeGeneration();" style="width:100%;">
											</label>
										</td>
										<td colspan="4" class="typeOfSample">
											<label class="radio-inline">
												<input type="radio" id="dbs" name="typeOfSample" value="DBS" title="Check DBS">DBS
											</label>
											<label class="radio-inline" style="width:46%;">
												<input type="radio" id="wholeBlood" name="typeOfSample" value="Whole blood" title="Check Whole blood" style="margin-top:10px;">Whole Blood
												<input type="text" name="wholeBloodOne" id="wholeBloodOne" class="form-control" style="width: 20%;" />&nbsp; x &nbsp;<input type="text" name="wholeBloodTwo" id="wholeBloodTwo" class="form-control" style="width: 20%;" />&nbsp;vial(s)
											</label>
											<label class="radio-inline" style="width:42%;">
												<input type="radio" id="plasma" name="typeOfSample" value="Plasma" title="Check Plasma" style="margin-top:10px;">Plasma
												<input type="text" name="plasmaOne" id="plasmaOne" class="form-control" style="width: 20%;" />&nbsp;ml x &nbsp;<input type="text" name="plasmaTwo" id="plasmaTwo" class="form-control" style="width: 20%;" />&nbsp;vial(s)
											</label>
										</td>
									</tr>
									<tr>
										<td class="labels">
											<label for="collectedBy">Specimen Collected by</label>
										</td>
										<td>
											<label class="radio-inline">
												<input type="text" class="form-control" name="collectedBy" id="collectedBy" placeholder="Collected By" title="Enter Collected By" style="width:100%;">
											</label>
										</td>
										<td colspan="4" class="processTime labels"><label for="processTime">For onsite plasma <br /> processing only</label>
											<label class="radio-inline" style="width: 15%;">
												<input type="text" name="processTime" id="processTime" class="form-control" style="width: 100%;" placeholder="Time" title="Processing Time" />
											</label>&nbsp;
											<label class="labels" for="processTech">Processing Tech</label>
											<label class="radio-inline">
												<input type="text" name="processTech" id="processTech" class="form-control" style="width: 100%;" placeholder="Processing Tech" title="Processing Tech" />
											</label>
										</td>
									</tr>
									<?php if ($_SESSION['instanceType'] != 'remoteuser') { ?>
										<tr>
											<td colspan="6" style="font-size: 18px; font-weight: bold;">CPHL Use Only </td>
										</tr>
										<tr>
											<td class="labels"><label for="noResult">Sample Quality</label></td>
											<td>
												<label class="radio-inline">
													<input type="radio" id="sampleQtyAccept" name="noResult" value="no" title="Check Sample Quality">Accept
												</label>
												<label class="radio-inline">
													<input type="radio" id="sampleQtyReject" name="noResult" value="yes" title="Check Sample Quality">Reject
												</label>
											</td>
											<td class="rejectionReason labels" style="display:none;"><label for="rejectionReason">Reason <span class="mandatory">*</span></label></td>
											<td class="rejectionReason" style="display:none;">
												<select name="rejectionReason" id="rejectionReason" class="form-control" title="Please choose reason" style="width: 100%">
													<option value="">-- Select --</option>
													<?php foreach ($rejectionResult as $reject) { ?>
														<option value="<?php echo $reject['rejection_reason_id']; ?>"><?= $reject['rejection_reason_name']; ?></option>
													<?php } ?>
												</select>
											</td>
											<td class="rejectionReason labels" style="display: none;">Rejection Date<span class="mandatory">*</span></td>
											<td class="rejectionReason" style="display: none;"><input class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select Rejection Date" /></td>
										</tr>
										<tr>
											<td class="labId labels"><label for="labId">Laboratory Name</label></td>
											<td>
												<select name="labId" id="labId" class="form-control" title="Please choose lab name" style="width: 100%;">
													<?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
												</select>
											</td>
											<td class="specimenType labels"><label for="specimenType">Sample Type Received</label></td>
											<td>
												<select name="specimenType" id="specimenType" class="form-control" title="Please choose Specimen type" style="width: 100%">
													<option value=""> -- Select -- </option>
													<?php foreach ($sResult as $name) { ?>
														<option value="<?php echo $name['sample_id']; ?>"><?= $name['sample_name']; ?></option>
													<?php } ?>
												</select>
											</td>
											<td class="sampleReceivedDate labels"><label for="sampleReceivedDate">Date Received</label></td>
											<td>
												<input type="text" class="form-control" name="sampleReceivedDate" id="sampleReceivedDate" placeholder="Received Date" title="Enter Received Date" style="width:100%;">
											</td>
										</tr>
										<tr>
											<td class="techName labels"><label for="techName">Lab Tech. Name</label></td>
											<td>
												<input type="text" class="form-control" name="techName" id="techName" placeholder="Enter Lab Technician Name" title="Please enter lab technician name" style="width:100%;">
											</td>
											<td class="labels"><label for="sampleTestingDateAtLab">Test date</label></td>
											<td>
												<input type="text" class="form-control" name="sampleTestingDateAtLab" id="sampleTestingDateAtLab" placeholder="Test Date" title="Enter Testing Date" style="width:100%;">
											</td>
											<td class="labels"><label for="testingPlatform">Testing Platform</label></td>
											<td>
												<select name="testingPlatform" id="testingPlatform" class="form-control" title="Please choose VL Testing Platform" style="width: 100%">
													<option value="">-- Select --</option>
													<?php foreach ($importResult as $mName) { ?>
														<option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit']; ?>"><?php echo $mName['machine_name']; ?></option>
													<?php } ?>
												</select>
											</td>
											
										</tr>
										<tr>
										<td class="vlResult labels"><label for="vlResult">VL result</label></td>
											<td class="vlResult">
												<input type="text" class="form-control" name="cphlvlResult" id="cphlvlResult" placeholder="VL Result" title="Enter VL Result" style="width:100%;">
											</td>
											<td class="labels"><label for="batchQuality">Batch quality</label></td>
											<td>
												<label class="radio-inline">
													<input type="radio" id="passed" name="batchQuality" value="passed" title="Batch Quality">Passed
												</label>
												<label class="radio-inline">
													<input type="radio" id="failed" name="batchQuality" value="failed" title="Batch Quality">Failed
												</label>
											</td>
											<td class="labels"><label for="testQuality">Sample test quality</label></td>
											<td>
												<label class="radio-inline">
													<input type="radio" id="passed" name="testQuality" value="passed" title="Test Quality">Passed
												</label>
												<label class="radio-inline">
													<input type="radio" id="failed" name="testQuality" value="invalid" title="Test Quality">Invalid
												</label>
											</td>
											
										</tr>
										<tr>
										<td class="labels"><label for="batchNo">Batch</label></td>
											<td>
												<select name="batchNo" id="batchNo" class="form-control" title="Please choose batch number" style="width:100%">
													<option value="">-- Select --</option>
													<?php foreach ($bResult as $bName) { ?>
														<option value="<?php echo $bName['batch_id']; ?>"><?php echo $bName['batch_code']; ?></option>
													<?php } ?>
												</select>
											</td>
													</tr>
										<tr>
											<th colspan="6" style="font-size: 18px; font-weight: bold;">For failed / invalid runs only</th>
										</tr>
										<tr>
											<td class="labels"><label for="failedTestDate">Repeat Test date</label></td>
											<td>
												<input type="text" class="form-control" name="failedTestDate" id="failedTestDate" placeholder="Test Date" title="Enter Testing Date" style="width:100%;">
											</td>
											<td class="labels"><label for="failedTestingTech">Testing Platform</label></td>
											<td>
												<select name="failedTestingTech" id="failedTestingTech" class="form-control" title="Please choose VL Testing Platform" style="width: 100%">
													<option value="">-- Select --</option>
													<?php foreach ($importResult as $mName) { ?>
														<option value="<?php echo $mName['machine_name'] . '##' . $mName['lower_limit'] . '##' . $mName['higher_limit']; ?>"><?php echo $mName['machine_name']; ?></option>
													<?php } ?>
												</select>
											</td>
											<td class="labels"><label for="failedvlResult">VL result</label></td>
											<td>
												<input type="text" class="form-control" name="failedvlResult" id="failedvlResult" placeholder="VL Result" title="Enter VL Result" style="width:100%;">
											</td>
										</tr>
										<tr>
											<td class="labels"><label for="batchQuality">Batch quality</label></td>
											<td>
												<label class="radio-inline">
													<input type="radio" id="passed" name="failedbatchQuality" value="passed" title="Batch Quality">Passed
												</label>
												<label class="radio-inline">
													<input type="radio" id="failed" name="failedbatchQuality" value="failed" title="Batch Quality">Failed
												</label>
											</td>
											<td class="labels"><label for="testQuality">Sample test quality</label></td>
											<td>
												<label class="radio-inline">
													<input type="radio" id="passed" name="failedtestQuality" value="passed" title="Test Quality">Passed
												</label>
												<label class="radio-inline">
													<input type="radio" id="failed" name="failedtestQuality" value="invalid" title="Test Quality">Invalid
												</label>
											</td>
											<td class="labels"><label for="failedbatchNo">Batch</label></td>
											<td>
												<select name="failedbatchNo" id="failedbatchNo" class="form-control" title="Please choose batch number" style="width: 100%">
													<option value="">-- Select --</option>
													<?php foreach ($bResult as $bName) { ?>
														<option value="<?php echo $bName['batch_id']; ?>"><?php echo $bName['batch_code']; ?></option>
													<?php
													}
													?>
												</select>
											</td>
										</tr>
										<tr>
											<th class="labels" colspan="6" style="font-size: 18px; font-weight: bold;">Final Result</th>
										</tr>
										<tr>
											<td class="labels"><label for="finalViralResult">Final Viral Load Result(copies/ml)</label></td>
											<td>
												<input type="text" class="form-control" name="finalViralResult" id="finalViralResult" placeholder="Viral Load Result" title="Enter Viral Result" style="width:100%;">
											</td>
											<td class="labels"><label for="testQuality">QC Tech Name</label></td>
											<td>
												<input type="text" class="form-control" name="qcTechName" id="qcTechName" placeholder="QC Tech Name" title="Enter QC Tech Name" style="width:100%;">
											</td>
											<td class="labels"><label for="finalViralResult">Report Date</label></td>
											<td>
												<input type="text" class="form-control date" name="reportDate" id="reportDate" placeholder="Report Date" title="Enter Report Date" style="width:100%;">
											</td>
										</tr>
										<tr>
											<td class="labels"><label for="finalViralResult">QC Tech Signature</label></td>
											<td>
												<input type="text" class="form-control" name="qcTechSign" id="qcTechSign" placeholder="QC Tech Signature" title="Enter QC Tech Signature" style="width:100%;">
											</td>
											<td class="labels"><label for="testQuality">QC Date</label></td>
											<td colspan="5">
												<input type="text" class="form-control date" name="qcDate" id="qcDate" placeholder="QC Date" title="Enter QC Date" style="width:40%;">
											</td>
										</tr>
										<tr>
											<td style="width:14%;" class="labels"><label for="reviewedOn"> Reviewed On </label></td>
											<td style="width:14%;">
												<input type="text" name="reviewedOn" id="reviewedOn" class="dateTime form-control" placeholder="Reviewed on" title="Please enter the reviewed on" />
											</td>
											<td style="width:14%;" class="labels"><label for="reviewedBy"> Reviewed By </label></td>
											<td style="width:14%;">
												<select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose reviewed by" style="width: 100%;">
													<?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
												</select>
											</td>
											<td style="width:14%;" class="labels"><label for="approvedOn"> Approved On </label></td>
											<td style="width:14%;">
												<input type="text" name="approvedOn" id="approvedOn" class="dateTime form-control" placeholder="Approved on" title="Please enter the approved on" />
											</td>
										<tr>
											<td style="width:14%;" class="labels"><label for="approvedBy"> Approved By </label></td>
											<td style="width:14%;">
												<select name="approvedBy" id="approvedBy" class="select2 form-control" title="Please choose approved by" style="width: 100%;">
													<?= $general->generateSelectOptions($userInfo, null, '-- Select --'); ?>
												</select>
											</td>
										</tr>
									<?php } ?>
								</table>
							</div>
						</div>
					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'auto2' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
							<input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" />
							<input type="hidden" name="sampleCodeKey" id="sampleCodeKey" />
						<?php } ?>
						<input type="hidden" name="saveNext" id="saveNext" />
						<input type="hidden" name="vlSampleId" id="vlSampleId" />
						<input type="hidden" name="formId" id="formId" value="5" />
						<input type="hidden" name="provinceId" id="provinceId" />
						<input type="hidden" name="countryFormId" id="countryFormId" value="<?php echo $arr['vl_form']; ?>" />
						<a class="btn btn-primary btn-disabled" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
						<a href="vlRequest.php" class="btn btn-default"> Cancel</a>
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
	var sampleCodeGenerationEvent = null;
	var facilityListEvent = null;
	$(document).ready(function() {
		$("#artNo").on('input', function() {

let artNo = $.trim($(this).val());

if (artNo.length < 10) {
	 $("#artNoGroup").html('<small style="color:red;font-weight:bold;">Patient ART No. should be 10 characters long</small><br>');
}
if (artNo.length > 3) {

	 $.post("/common/patient-last-request-details.php", {
			   testType: 'vl',
			   patientId: artNo,
		  },
		  function(data) {
			   if (data != "0") {
					obj = $.parseJSON(data);
					if (obj.no_of_req_time != null && obj.no_of_req_time > 0) {
						 $("#artNoGroup").html('<small style="color:red">No. of times Test Requested for this Patient : ' + obj.no_of_req_time + '</small>');
					}
					if (obj.request_created_datetime != null) {
						 $("#artNoGroup").append('<br><small style="color:red">Last Test Request Added On VLSM : ' + obj.request_created_datetime + '</small>');
					}
					if (obj.sample_collection_date != null) {
						 $("#artNoGroup").append('<br><small style="color:red">Sample Collection Date for Last Request : ' + obj.sample_collection_date + '</small>');
					}
					if (obj.no_of_tested_time != null && obj.no_of_tested_time > 0) {
						 $("#artNoGroup").append('<br><small style="color:red">Total No. of times Patient tested for VL : ' + obj.no_of_tested_time + '</small>');
					}
			   } else {
					if (artNo.length < 10) {
						 $("#artNoGroup").html('<small style="color:red;font-weight:bold;">Patient ART No. should be 10 characters long</small><br>');
					} else {
						 $("#artNoGroup").html('');
					}
			   }
		  });
}

});
		$("#gender").change(function(){
			if($(this).val()=="female")
				$(".femaleFactor").show();
			else
				$(".femaleFactor").hide();
		});
		$("input[name='typeOfSample']").click(function(){
			if($(this).val()=="DBS")
			{
				$("#plasmaOne,#plasmaTwo").val("");
				$("#wholeBloodOne,#wholeBloodTwo").val("");
			}
			else if($(this).val()=="Whole blood")
			{
				$("#plasmaOne,#plasmaTwo").val("");
			}
			else if($(this).val()=="Plasma")
			{
				$("#wholeBloodOne,#wholeBloodTwo").val("");
			}
		});
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
		$('#sampleCollectionDate,#sampleReceivedDate,#sampleTestingDateAtLab,#failedTestDate').mask('99-aaa-9999 99:99');

		$('#sampleCollectionDate,#sampleReceivedDate,#sampleTestingDateAtLab,#failedTestDate').datetimepicker({
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

		$('#labId').select2({
			placeholder: "Select Laboratory Name"
		});
		
		
		$('#reviewedBy').select2({
			placeholder: "Select Reviewed By"
		});
		$('#approvedBy').select2({
			placeholder: "Select Approved By"
		});
	$('#labId').select2({
			placeholder: "Select Testing Lab Name"
		});
          $('#fName').select2({
               placeholder: "Select Clinic/Health Center"
          });
          $('#district').select2({
               placeholder: "District"
          });
          $('#province').select2({
               placeholder: "Province"
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
			$('.btn-disabled').attr('disabled', 'yes');
			$(".btn-disabled").prop("onclick", null).off("click");
			$.blockUI();
			var provinceCode = ($("#province").find(":selected").attr("data-code") == null || $("#province").find(":selected").attr("data-code") == '') ? $("#province").find(":selected").attr("data-name") : $("#province").find(":selected").attr("data-code");
			<?php if ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'auto2' || $arr['sample_code'] == 'YY' || $arr['sample_code'] == 'MMYY') { ?>
				insertSampleCode('vlRequestForm', 'vlSampleId', 'sampleCode', 'sampleCodeKey', 'sampleCodeFormat', 5, 'sampleCollectionDate', provinceCode, $("#province").find(":selected").attr("data-province-id"));
			<?php } else { ?>
				document.getElementById('vlRequestForm').submit();
			<?php } ?>
		}
	}

	function validateSaveNow() {
		flag = deforayValidator.init({
			formId: 'vlRequestForm'
		});
		$('.isRequired').each(function() {
			($(this).val() == '') ? $(this).css('background-color', '#FFFF99'): $(this).css('background-color', '#FFFFFF')
		});
		$("#saveNext").val('next');
		if (flag) {
			$('.btn-disabled').attr('disabled', 'yes');
			$(".btn-disabled").prop("onclick", null).off("click");
			$.blockUI();
			document.getElementById('vlRequestForm').submit();
		}
	}

	function getfacilityDetails(obj) {
		$.blockUI();
		var cName = $("#fName").val();
		var pName = $("#province").val();
		$('#reqClinicianPhoneNumber').val('');
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
							$("#fName").html(details[0]);
							$("#district").html(details[1]);
							$("#clinicianName").val(details[2]);
						}
					});
			}
		} else if (pName == '' && cName == '') {
			provinceName = true;
			facilityName = true;
			$("#province").html("<?php echo $province; ?>");
			$("#fName").html("<?php echo $facility; ?>");
		}
		$.unblockUI();
	}

	function getfacilityDistrictwise(obj) {
		$.blockUI();
		var dName = $("#district").val();
		var cName = $("#fName").val();
		$('#reqClinicianPhoneNumber').val('');
		if (dName != '') {
			$.post("/includes/siteInformationDropdownOptions.php", {
					dName: dName,
					cliName: cName,
					testType: 'vl'
				},
				function(data) {
					if (data != "") {
						details = data.split("###");
						$("#fName").html(details[0]);
					}
				});
		}
		$.unblockUI();
	}

	function getfacilityProvinceDetails(obj) {
		$.blockUI();
		$('#reqClinicianPhoneNumber').val($("#fName").find(":selected").attr("data-mobile-nos"));
		$.unblockUI();
		//check facility name
		//    var cName = $("#fName").val();
		//    var pName = $("#province").val();
		//    if(cName!='' && provinceName && facilityName){
		//      provinceName = false;
		//    }
		//    
		//    if(cName!='' && facilityName){
		//      $.post("/includes/siteInformationDropdownOptions.php", { cName : cName,testType: 'vl'},
		//      function(data){
		//	  if(data != ""){
		//            details = data.split("###");
		//            $("#province").html(details[0]);
		//            $("#district").html(details[1]);
		//            $("#clinicianName").val(details[2]);
		//	  }
		//      });
		//    }else if(pName=='' && cName==''){
		//      provinceName = true;
		//      facilityName = true;
		//      $("#province").html("< ?php echo $province;?>");
		//      $("#fName").html("< ?php echo $facility;?>");
		//    }
	}

	function checkValue() {
		var artRegimen = $("#artRegimen").val();
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

	function sampleCodeGeneration() {
		if (sampleCodeGenerationEvent) {
			sampleCodeGenerationEvent.abort();
		}

		var pName = $("#province").val();
		var sDate = $("#sampleCollectionDate").val();
		if (pName != '' && sDate != '') {
			// $.blockUI();
			var provinceCode = ($("#province").find(":selected").attr("data-code") == null || $("#province").find(":selected").attr("data-code") == '') ? $("#province").find(":selected").attr("data-name") : $("#province").find(":selected").attr("data-code");
			sampleCodeGenerationEvent = $.post("/vl/requests/sampleCodeGeneration.php", {
					sDate: sDate,
					autoTyp: 'auto2',
					provinceCode: provinceCode,
					'sampleFrom': 'png',
					'provinceId': $("#province").find(":selected").attr("data-province-id")
				},
				function(data) {
					var sCodeKey = JSON.parse(data);
					$("#sampleCode").val(sCodeKey.sampleCode);
					$("#sampleCodeFormat").val(sCodeKey.sampleCodeFormat);
					$("#sampleCodeKey").val(sCodeKey.maxId);
					$("#provinceId").val($("#province").find(":selected").attr("data-province-id"));
					checkSampleNameValidation('form_vl', '<?php echo $sampleCode; ?>', 'sampleCode', null, 'The laboratory ID that you entered already exists. Please try another ID', null)
					// $.unblockUI();
				});
		}
	}

	$("input:radio[name=noResult]").on("change", function() {
		if ($(this).val() == 'yes') {
			$(".rejectionReason,.vlresultequ").show();
			$(".reasonequ,.vlResult").hide();
			$('#rejectionReason').addClass("isRequired");
		} else {
			$(".reasonequ,.vlResult").show();
			$(".rejectionReason,.vlresultequ").hide();
			$('#rejectionReason').removeClass("isRequired");
		}
	})
	$("input:radio[name=reasonForVLTesting]").on("change", function() {
		if ($(this).val() == 'Re-collection requested by lab') {
			$('#reason').addClass("isRequired").attr('readonly', false);
		} else {
			$('#reason').removeClass("isRequired").attr('readonly', true).val('');
		}
	})
</script>