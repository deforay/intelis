<?php
// imported in tb-add-request.php based on country in global config

use App\Registries\ContainerRegistry;
use App\Services\TbService;
use App\Utilities\DateUtility;


// Sanitize values before using them in the form
$vlQueryInfo = array_map('htmlspecialchars', $tbInfo);

//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);
/* To get testing platform names */
$testPlatformResult = $general->getTestingPlatforms('tb');
// Nationality
$nationalityQry = "SELECT * FROM `r_countries` ORDER BY `iso_name` ASC";
$nationalityResult = $db->query($nationalityQry);

foreach ($nationalityResult as $nrow) {
	$nationalityList[$nrow['id']] = ($nrow['iso_name']) . ' (' . $nrow['iso3'] . ')';
}

foreach ($testPlatformResult as $row) {
	$testPlatformList[$row['machine_name']] = $row['machine_name'];
}
//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);


/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);
$tbXPertResults = $tbService->getTbResults('x-pert');
$tbLamResults = $tbService->getTbResults('lam');
$specimenTypeResult = $tbService->getTbSampleTypes();
$tbReasonsForTesting = $tbService->getTbReasonsForTesting();
// To get the reason details value

$rKey = '';
$sKey = '';
$sFormat = '';
$pdQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
if ($_SESSION['accessType'] == 'collection-site') {
	$sampleCodeKey = 'remote_sample_code_key';
	$sampleCode = 'remote_sample_code';
	if (!empty($tbInfo['remote_sample']) && $tbInfo['remote_sample'] == 'yes') {
		$sampleCode = 'remote_sample_code';
	} else {
		$sampleCode = 'sample_code';
	}
	$rKey = 'R';
} else {
	$sampleCodeKey = 'sample_code_key';
	$sampleCode = 'sample_code';
	$rKey = '';
}
//check user exist in user_facility_map table
$chkUserFcMapQry = "SELECT user_id FROM user_facility_map WHERE user_id='" . $_SESSION['userId'] . "'";
$chkUserFcMapResult = $db->query($chkUserFcMapQry);
if ($chkUserFcMapResult) {
	$pdQuery = "SELECT DISTINCT gd.geo_name,gd.geo_id,gd.geo_code FROM geographical_divisions as gd JOIN facility_details as fd ON fd.facility_state_id=gd.geo_id JOIN user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where gd.geo_parent = 0 AND gd.geo_status='active' AND vlfm.user_id='" . $_SESSION['userId'] . "'";
}
$pdResult = $db->query($pdQuery);
$province = "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
	$selected = "";
	if ($tbInfo['geo_id'] == $provinceName['geo_id']) {
		$selected = "selected='selected'";
	}
	$province .= "<option data-code='" . $provinceName['geo_code'] . "' data-province-id='" . $provinceName['geo_id'] . "' data-name='" . $provinceName['geo_name'] . "' value='" . $provinceName['geo_name'] . "##" . $provinceName['geo_code'] . "'" . $selected . ">" . ($provinceName['geo_name']) . "</option>";
}

$facility = $general->generateSelectOptions($healthFacilities, $tbInfo['facility_id'], '-- Select --');

$microscope = array("No AFB" => "No AFB", "1+" => "1+", "2+" => "2+", "3+" => "3+");

$typeOfPatient = json_decode($tbInfo['patient_type']);
$reasonForTbTest = json_decode($tbInfo['reason_for_tb_test']);
$testTypeRequested = json_decode($tbInfo['tests_requested']);
$diagnosis = (array)$reasonForTbTest->elaboration->diagnosis;
$followup = (array)$reasonForTbTest->elaboration->followup;
$attributes = null;
if (isset($tbInfo['lab_id']) && $tbInfo['lab_id'] > 0) {
	$db->where("f.facility_id", $tbInfo['lab_id']);
	$db->join("testing_labs as l", "l.facility_id=f.facility_id", "INNER");
	$results = $db->getOne("facility_details as f");
	if (isset($results['attributes']) && $results['attributes'] != "") {
		$attributes = json_decode($results['attributes'], true);
	}
}

?>

<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-pen-to-square"></em> TB LABORATORY TEST REQUEST FORM</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Edit New Request</li>
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
				<form class="form-horizontal" method="post" name="editTbRequestForm" id="editTbRequestForm" autocomplete="off" action="tb-edit-request-helper.php">
					<div class="box-body">
						<div class="box box-default">
							<div class="box-body">
								<div class="box-header with-border sectionHeader">
									<h3 class="box-title">TESTING LAB INFORMATION</h3>
								</div>
								<div class="box-header with-border">
									<h3 class="box-title" style="font-size:1em;">To be filled by requesting Clinician/Nurse</h3>
								</div>
								<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
									<tr>
										<?php if ($_SESSION['accessType'] == 'collection-site') { ?>
											<th scope="row" style="width: 16.6%;"><label class="label-control" for="sampleCode">Sample ID </label></th>
											<td style="width: 16.6%;">
												<span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"><?php echo (isset($tbInfo['remote_sample_code']) && $tbInfo['remote_sample_code'] != "") ? $tbInfo['remote_sample_code'] : $tbInfo['sample_code']; ?></span>
												<input type="hidden" id="sampleCode" name="sampleCode" />
											</td>
										<?php } else { ?>
											<th scope="row" style="width: 14%;"><label class="label-control" for="sampleCode">Sample ID </label><span class="mandatory">*</span></th>
											<td style="width: 18%;">
												<input type="text" value="<?php echo $tbInfo['sample_code']; ?>" class="form-control isRequired" id="sampleCode" name="sampleCode" readonly="readonly" placeholder="Sample ID" title="Please enter sample code" style="width:100%;" onchange="checkSampleNameValidation('form_tb','<?php echo $sampleCode; ?>',this.id,null,'The sample id that you entered already exists. Please try another sample id',null)" />
											</td>
										<?php } ?>
										<th scope="row"></th>
										<td></td>
										<th scope="row"></th>
										<td></td>
									</tr>
								</table>
								<div class="box-header with-border sectionHeader">
									<h3 class="box-title">REFERRING HEALTH FACILITY INFORMATION</h3>
								</div>
								<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
									<tr>
										<td><label class="label-control" for="province">Health Facility/POE State </label><span class="mandatory">*</span></td>
										<td>
											<select class="form-control select2 isRequired" name="province" id="province" title="Please choose State" onchange="getfacilityDetails(this);" style="width:100%;">
												<?php echo $province; ?>
											</select>
										</td>
										<td><label class="label-control" for="district">Health Facility/POE County </label><span class="mandatory">*</span></td>
										<td>
											<select class="form-control select2 isRequired" name="district" id="district" title="Please choose County" style="width:100%;" onchange="getfacilityDistrictwise(this);">
												<option value=""> -- Select -- </option>
											</select>
										</td>
										<td><label class="label-control" for="facilityId">Health Facility/POE </label><span class="mandatory">*</span></td>
										<td>
											<select class="form-control isRequired " name="facilityId" id="facilityId" title="Please choose service provider" style="width:100%;" onchange="getfacilityProvinceDetails(this);">
												<?php echo $facility; ?>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="requestedDate">Date of request <span class="mandatory">*</span></label></th>
										<td>
											<input type="text" value="<?php echo $tbInfo['request_created_datetime']; ?>" class="date-time form-control" id="requestedDate" name="sampleReceivedDate" placeholder="<?= _("Please enter date"); ?>" title="Please enter date of request date" style="width:100%;" />
										</td>
										<td><label class="label-control" for="referringUnit">Referring Unit </label></td>
										<td>
											<select class="form-control " name="referringUnit" id="referringUnit" title="Please choose referring unit" style="width:100%;" onchange="showOther(this.value, 'typeOfReferringUnit');">
												<option value="">-- Select --</option>
												<option value="art" <?php echo (isset($tbInfo['referring_unit']) && $tbInfo['referring_unit'] == 'art') ? "selected='selected'" : ""; ?>>ART</option>
												<option value="opd" <?php echo (isset($tbInfo['referring_unit']) && $tbInfo['referring_unit'] == 'opd') ? "selected='selected'" : ""; ?>>OPD</option>
												<option value="tb <?php echo (isset($tbInfo['referring_unit']) && $tbInfo['referring_unit'] == 'tb') ? "selected='selected'" : ""; ?>">TB</option>
												<option value="pmtct" <?php echo (isset($tbInfo['referring_unit']) && $tbInfo['referring_unit'] == 'pmtct') ? "selected='selected'" : ""; ?>>PMTCT</option>
												<option value="medical" <?php echo (isset($tbInfo['referring_unit']) && $tbInfo['referring_unit'] == 'medical') ? "selected='selected'" : ""; ?>>Medical</option>
												<option value="paediatric" <?php echo (isset($tbInfo['referring_unit']) && $tbInfo['referring_unit'] == 'paediatric') ? "selected='selected'" : ""; ?>>Paediatric</option>
												<option value="nutrition" <?php echo (isset($tbInfo['referring_unit']) && $tbInfo['referring_unit'] == 'nutrition') ? "selected='selected'" : ""; ?>>Nutrition</option>
												<option value="other" <?php echo (isset($tbInfo['referring_unit']) && $tbInfo['referring_unit'] == 'other') ? "selected='selected'" : ""; ?>>Others</option>
											</select>
										</td>
										<td>
											<input type="text" class="form-control typeOfReferringUnit" style="display: none;" name="typeOfReferringUnit" id="typeOfReferringUnit" value="<?php echo $tbInfo['other_referring_unit']; ?>" placeholder="Enter other reffering unit" title="Please enter the other referring unit" />
										</td>
										<?php if ($_SESSION['accessType'] == 'collection-site') { ?>
											<td><label class="label-control" for="labId">Testing Laboratory <span class="mandatory">*</span></label> </td>
											<td>
												<select name="labId" id="labId" class="form-control select2 isRequired" title="Please select Testing Testing Laboratory" style="width:100%;">
													<?= $general->generateSelectOptions($testingLabs, $tbInfo['lab_id'], '-- Select --'); ?>
												</select>
											</td>
										<?php } ?>
									</tr>
								</table>


								<div class="box-header with-border sectionHeader">
									<h3 class="box-title">PATIENT INFORMATION</h3>
								</div>
								<div class="box-header with-border">
									<input style="width:30%;" type="text" name="artPatientNo" id="artPatientNo" class="" placeholder="Enter Patient ID or Patient Name" title="Enter art number or patient name" />&nbsp;&nbsp;
									<a style="margin-top:-0.35%;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList();"><em class="fa-solid fa-magnifying-glass"></em>Search</a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><strong>&nbsp;No Patient Found</strong></span>
								</div>
								<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
									<tr>
										<th scope="row"><label for="patientId">Unique ART Number</label></th>
										<td>
											<input type="text" value="<?php echo $tbInfo['patient_id']; ?>" class="form-control" id="patientId" name="patientId" placeholder="Patient Identification" title="Please enter Patient ID" style="width:100%;" onchange="" />
										</td>
										<th scope="row"><label for="firstName">First Name <span class="mandatory">*</span> </label></th>
										<td>
											<input type="text" value="<?php echo $tbInfo['patient_name']; ?>" class="form-control isRequired" id="firstName" name="firstName" placeholder="First Name" title="Please enter First name" style="width:100%;" onchange="" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="lastName">Sur name </label></th>
										<td>
											<input type="text" value="<?php echo $tbInfo['patient_surname']; ?>" class="form-control " id="lastName" name="lastName" placeholder="Last name" title="Please enter Last name" style="width:100%;" onchange="" />
										</td>
										<th scope="row"><label for="patientDob">Date of Birth </label></th>
										<td>
											<input type="text" value="<?php echo DateUtility::humanReadableDateFormat($tbInfo['patient_dob']); ?>" class="form-control" id="patientDob" name="patientDob" placeholder="Date of Birth" title="Please enter Date of birth" style="width:100%;" onchange="calculateAgeInYears();" />
										</td>
									</tr>
									<tr>
										<th scope="row">Age (years)</th>
										<td><input type="number" value="<?php echo $tbInfo['patient_age']; ?>" max="150" maxlength="3" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="patientAge" name="patientAge" placeholder="Patient Age (in years)" title="Patient Age" style="width:100%;" onchange="" /></td>
										<th scope="row"><label for="patientGender">Gender <span class="mandatory">*</span> </label></th>
										<td>
											<select class="form-control isRequired" name="patientGender" id="patientGender" title="Please select the gender">
												<option value=''> -- Select -- </option>
												<option value='male' <?php echo (isset($tbInfo['patient_gender']) && $tbInfo['patient_gender'] == 'male') ? "selected='selected'" : ""; ?>> Male </option>
												<option value='female' <?php echo (isset($tbInfo['patient_gender']) && $tbInfo['patient_gender'] == 'female') ? "selected='selected'" : ""; ?>> Female </option>
												<option value='other' <?php echo (isset($tbInfo['patient_gender']) && $tbInfo['patient_gender'] == 'other') ? "selected='selected'" : ""; ?>> Other </option>
											</select>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="typeOfPatient">Type of patient<span class="mandatory">*</span> </label></th>
										<td>
											<select class="select2 form-control isRequired" name="typeOfPatient[]" id="typeOfPatient" title="Please select the type of patient" onchange="showOther(this.value,'typeOfPatientOther');" multiple>
												<option value=''> -- Select -- </option>
												<option value='new' <?php echo (isset($typeOfPatient) && in_array("new", $typeOfPatient)) ? "selected='selected'" : ""; ?>> New </option>
												<option value='loss-to-follow-up' <?php echo (isset($typeOfPatient) && in_array("loss-to-follow-up", $typeOfPatient)) ? "selected='selected'" : ""; ?>> Loss to Follow Up </option>
												<option value='treatment-failure' <?php echo (isset($typeOfPatient) && in_array("treatment-failure", $typeOfPatient)) ? "selected='selected'" : ""; ?>> Treatment Failure </option>
												<option value='relapse' <?php echo (isset($typeOfPatient) && in_array("relapse", $typeOfPatient)) ? "selected='selected'" : ""; ?>> Relapse </option>
												<option value='other' <?php echo (isset($typeOfPatient) && in_array("other", $typeOfPatient)) ? "selected='selected'" : ""; ?>> Other </option>
											</select>
										</td>
										<td>
											<input type="text" class="form-control typeOfPatientOther" value="<?php echo $tbInfo['other_patient_type']; ?>" id="typeOfPatientOther" name="typeOfPatientOther" placeholder="Enter type of patient if others" title="Please enter type of patient if others" style="display: none;" />
										</td>
									</tr>
									<tr>
										<th scope="row" colspan="4">Reason for Examination</th>
									</tr>
									<tr style=" border: 1px solid #8080804f; ">
										<td>
											<label class="radio-inline" style="margin-left:0;">
												<input type="radio" class="isRequired diagnosis-check" id="reasonForTbTest1" name="reasonForTbTest[reason]" value="diagnosis" title="Select reason for examination" onchange="checkSubReason(this,'diagnosis', 'followup-uncheck');" <?php echo (isset($reasonForTbTest->reason->diagnosis) && $reasonForTbTest->reason->diagnosis == "yes") ? "checked" : ""; ?>>
												<strong>Diagnosis</strong>
											</label>
										</td>
										<td style="float: left;text-align: center;">
											<div class="diagnosis hide-reasons" style="display: <?php echo (isset($reasonForTbTest->reason->diagnosis) && $reasonForTbTest->reason->diagnosis == "yes") ? "block" : "none"; ?>;">
												<ul style=" display: inline-flex; list-style: none; padding: 0px; ">
													<li>
														<label class="radio-inline" style="width:4%;margin-left:0;">
															<input type="checkbox" class="diagnosis-check reason-checkbox" id="presumptiveTb" name="reasonForTbTest[elaboration][diagnosis][Presumptive TB]" value="yes" <?php echo (isset($diagnosis['Presumptive TB']) && $diagnosis['Presumptive TB'] == "yes") ? "checked" : ""; ?>>
														</label>
														<label class="radio-inline" for="presumptiveTb" style="padding-left:17px !important;margin-left:0;">Presumptive TB</label>
													</li>
													<li>
														<label class="radio-inline" style="width:4%;margin-left:0;">
															<input type="checkbox" class="diagnosis-check reason-checkbox" id="rifampicinResistantTb" name="reasonForTbTest[elaboration][diagnosis][Rifampicin-resistant TB]" value="yes" <?php echo (isset($diagnosis['Rifampicin-resistant TB']) && $diagnosis['Rifampicin-resistant TB'] == "yes") ? "checked" : ""; ?>>
														</label>
														<label class="radio-inline" for="rifampicinResistantTb" style="padding-left:17px !important;margin-left:0;">Rifampicin-resistant TB</label>
													</li>
													<li>
														<label class="radio-inline" style="width:4%;margin-left:0;">
															<input type="checkbox" class="diagnosis-check reason-checkbox" id="mdrtb" name="reasonForTbTest[elaboration][diagnosis][MDR-TB]" value="yes" <?php echo (isset($diagnosis['MDR-TB']) && $diagnosis['MDR-TB'] == "yes") ? "checked" : ""; ?>>
														</label>
														<label class="radio-inline" for="mdrtb" style="padding-left:17px !important;margin-left:0;">MDR-TB</label>
													</li>
												</ul>
											</div>
										</td>
										<td>
											<label class="radio-inline" style="margin-left:0;">
												<input type="radio" class="isRequired followup-uncheck" id="reasonForTbTest1" name="reasonForTbTest[reason]" value="followup" title="Select reason for examination" onchange="checkSubReason(this,'follow-up', 'diagnosis-check');" <?php echo (isset($reasonForTbTest->reason->followup) && $reasonForTbTest->reason->followup == "yes") ? "checked" : ""; ?>>
												<strong>Follow Up</strong>
											</label>
										</td>
										<td style="float: left;text-align: center;">
											<div class="follow-up hide-reasons" style="display: <?php echo (isset($reasonForTbTest->reason->followup) && $reasonForTbTest->reason->followup == "yes") ? "block" : "none"; ?>;">
												<input type="text" value=" <?php echo (isset($followup['value']) && $followup['value'] != "" && trim($followup['value']) != "") ? $followup['value'] : ""; ?>" class="form-control followup-uncheck reason-checkbox" id="followUp" name="reasonForTbTest[elaboration][followup][value]" placeholder="Enter the follow up" title="Please enter the follow up">
											</div>
										</td>
									</tr>
								</table>

								<div class="box-header with-border sectionHeader">
									<h3 class="box-title">SPECIMEN INFORMATION</h3>
								</div>
								<table aria-describedby="table" class="table" aria-hidden="true">
									<tr>
										<th scope="row"><label class="label-control" for="sampleCollectionDate">Date Specimen Collected <span class="mandatory">*</span></label></th>
										<td>
											<input class="form-control isRequired" value="<?php echo $tbInfo['sample_collection_date']; ?>" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" onchange="sampleCodeGeneration();" />
										</td>
										<th scope="row"><label class="label-control" for="specimenType">Specimen Type <span class="mandatory">*</span></label></th>
										<td>
											<select name="specimenType" id="specimenType" class="form-control isRequired" title="Please choose specimen type" style="width:100%" onchange="showOther(this.value,'specimenTypeOther')">
												<?php echo $general->generateSelectOptions($specimenTypeResult, $tbInfo['specimen_type'], '-- Select --'); ?>
												<option value='other' <?php echo ($tbInfo['specimen_type'] == 'other') ? "selected='selected'" : ""; ?>> Other </option>
											</select>
										</td>
										<td>
											<input class="form-control specimenTypeOther" type="text" id="specimenTypeOther" value="<?php echo $tbInfo['other_specimen_type']; ?>" name="specimenTypeOther" placeholder="Enter sample type of others" title="Please enter the sample type if others" style="display: none;" />
										</td>
									</tr>
									<tr>
										<th scope="row">
											<label class="label-control" for="testNumber">Specimen Number</label>
										</th>
										<td>
											<select class="form-control" name="testNumber" id="testNumber" title="Prélévement" style="width:100%;">
												<option value="">--Select--</option>
												<?php foreach (range(1, 5) as $element) {
													$selected = (isset($tbInfo['specimen_quality']) && $tbInfo['specimen_quality'] == $element) ? "selected='selected'" : "";
													echo '<option value="' . $element . '"' . $selected . '>' . $element . '</option>';
												} ?>
											</select>
										</td>
										<th scope="row">
											<label class="label-control" for="testTypeRequested">Test(s) requested </label>
										</th>
										<td>
											<select name="testTypeRequested[]" id="testTypeRequested" class="select2 form-control" title="Please choose type of test request" style="width:100%" multiple>
												<optgroup label="Microscopy">
													<option value="ZN" <?php echo (isset($testTypeRequested) && in_array("ZN", $testTypeRequested)) ? "selected='selecetd'" : ""; ?>>ZN</option>
													<option value="FM" <?php echo (isset($testTypeRequested) && in_array("FM", $testTypeRequested)) ? "selected='selecetd'" : ""; ?>>FM</option>
												</optgroup>
												<optgroup label="Xpert MTB">
													<option value="MTB/RIF" <?php echo (isset($testTypeRequested) && in_array("MTB/RIF", $testTypeRequested)) ? "selected='selecetd'" : ""; ?>>MTB/RIF</option>
													<option value="MTB/RIF ULTRA" <?php echo (isset($tbInfo['tests_requested']) && in_array("MTB/RIF ULTRA", $testTypeRequested)) ? "selected='selecetd'" : ""; ?>>MTB/RIF ULTRA</option>
													<option value="TB LAM" <?php echo (isset($tbInfo['tests_requested']) && in_array("TB LAM", $testTypeRequested)) ? "selected='selecetd'" : ""; ?>>TB LAM</option>
												</optgroup>
											</select>
										</td>
									</tr>
								</table>
							</div>
						</div>
						<?php if ($usersService->isAllowed('tb-update-result.php') || $_SESSION['accessType'] != 'collection-site') { ?>
							<?php // if (false) {
							?>
							<div class="box box-primary">
								<div class="box-body">
									<div class="box-header with-border">
										<h3 class="box-title">Results (To be completed in the Laboratory) </h3>
									</div>
									<table aria-describedby="table" class="table" aria-hidden="true" style="width:100%">
										<tr>
											<td><label class="label-control" for="labId">Testing Laboratory</label> </td>
											<td>
												<select name="labId" id="labId" class="form-control select2" title="Please select Testing Testing Laboratory" style="width:100%;">
													<?= $general->generateSelectOptions($testingLabs, $tbInfo['lab_id'], '-- Select --'); ?>
												</select>
											</td>
											<th scope="row"><label class="label-control" for="sampleReceivedDate">Date of Reception </label></th>
											<td>
												<input type="text" class="date-time form-control" value="<?php echo $tbInfo['sample_received_at_lab_datetime']; ?>" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="<?= _("Please enter date"); ?>" title="Please enter sample receipt date" style="width:100%;" />
											</td>
										</tr>
										<tr>
											<th scope="row"><label class="label-control" for="sampleTestedDateTime">Date of Sample Tested</label></th>
											<td>
												<input type="text" value="<?php echo $tbInfo['sample_tested_datetime']; ?>" class="date-time form-control" value="<?php echo $tbInfo['sample_collection_date']; ?>" id="sampleTestedDateTime" name="sampleTestedDateTime" placeholder="<?= _("Please enter date"); ?>" title="Please enter sample tested" style="width:100%;" />
											</td>
											<th scope="row"><label class="label-control" for="sampleDispatchedDate">Sample Dispatched On</label></th>
											<td>
												<input type="text" value="<?php echo $tbInfo['sample_dispatched_datetime']; ?>" class="date-time form-control" id="sampleDispatchedDate" name="sampleDispatchedDate" placeholder="<?= _("Please enter date"); ?>" title="Please choose sample dispatched date" style="width:100%;" />
											</td>
										</tr>
										<tr>
											<th scope="row"><label class="label-control" for="testedBy">Tested By</label></th>
											<td>
												<select name="testedBy" id="testedBy" class="select2 form-control" title="Please choose approved by" style="width: 100%;">
													<?= $general->generateSelectOptions($userInfo, $tbInfo['tested_by'], '-- Select --'); ?>
												</select>
											</td>
											<th scope="row"><label class="label-control" for="isSampleRejected">Is Sample Rejected?</label></th>
											<td>
												<select class="form-control" name="isSampleRejected" id="isSampleRejected" title="Please select the Is sample rejected?">
													<option value=''> -- Select -- </option>
													<option value="yes" <?php echo (isset($tbInfo['is_sample_rejected']) && $tbInfo['is_sample_rejected'] == "yes") ? "selected='selecetd'" : ""; ?>> Yes </option>
													<option value="no" <?php echo (isset($tbInfo['is_sample_rejected']) && $tbInfo['is_sample_rejected'] == "no") ? "selected='selecetd'" : ""; ?>> No </option>
												</select>
											</td>
										</tr>
										<tr class="show-rejection" style="display:none;">
											<th scope="row" class="show-rejection" style="display:none;"><label class="label-control" for="sampleRejectionReason">Reason for Rejection<span class="mandatory">*</span></label></th>
											<td class="show-rejection" style="display:none;">
												<select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason" title="Please select the reason for rejection">
													<option value="">-- Select --</option>
													<?php foreach ($rejectionTypeResult as $type) { ?>
														<optgroup label="<?php echo ($type['rejection_type']); ?>">
															<?php
															foreach ($rejectionResult as $reject) {
																if ($type['rejection_type'] == $reject['rejection_type']) { ?>
																	<option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($tbInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?= $reject['rejection_reason_name']; ?></option>
															<?php }
															} ?>
														</optgroup>
													<?php }
													if ($tbInfo['reason_for_sample_rejection'] == 9999) {
														echo '<option value="9999" selected="selected">Unspecified</option>';
													} ?>
												</select>
											</td>
											<th scope="row"><label class="label-control" for="rejectionDate">Rejection Date<span class="mandatory">*</span></label></th>
											<td><input value="<?php echo DateUtility::humanReadableDateFormat($tbInfo['rejection_on']); ?>" class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select rejection date" title="Please select the rejection date" /></td>
										</tr>
										<tr class="platform microscopy" <?php echo (isset($attributes) && $attributes != "" && in_array("microscopy", $attributes)) ? 'style="display:none;"' : ''; ?>>
											<td colspan="4">
												<table aria-describedby="table" class="table table-bordered table-striped" aria-hidden="true">
													<thead>
														<tr>
															<th scope="row" colspan="3" style="text-align: center;">Microscopy Test Results</th>
														</tr>
														<tr>
															<th scope="row" style="width: 10%;" class="text-center">Test #</th>
															<th scope="row" style="width: 40%;" class="text-center">Result</th>
															<th scope="row" style="width: 40%;" class="text-center">Actual Number</th>
														</tr>
													</thead>
													<tbody id="testKitNameTable">
														<?php
														$n = count($tbTestInfo);
														foreach (range(1, 3) as $no) {
															if ($n >= $no) { ?>
																<tr>
																	<td class="text-center"><?php echo $no; ?></td>
																	<td>
																		<select class="form-control test-result test-name-table-input" name="testResult[]" id="testResult<?php echo $no; ?>" title="Please select the result for row <?php echo $no; ?>">
																			<?= $general->generateSelectOptions($microscope, $tbTestInfo[$no - 1]['test_result'], '-- Select --'); ?>
																		</select>
																	</td>
																	<td>
																		<input type="text" value="<?php echo $tbTestInfo[$no - 1]['actual_no']; ?>" class="form-control test-name-table-input" id="actualNo<?php echo $no; ?>" name="actualNo[]" placeholder="Enter the actual number" title="Please enter the actual number" />
																	</td>
																</tr>
															<?php
															} else { ?>
																<tr>
																	<td class="text-center"><?php echo $no; ?></td>
																	<td>
																		<select class="form-control test-result test-name-table-input" name="testResult[]" id="testResult<?php echo $no; ?>" title="Please select the result for row <?php echo $no; ?>">
																			<?= $general->generateSelectOptions($microscope, null, '-- Select --'); ?>
																		</select>
																	</td>
																	<td>
																		<input type="text" class="form-control test-name-table-input" id="actualNo<?php echo $no; ?>" name="actualNo[]" placeholder="Enter the actual number" title="Please enter the actual number" />
																	</td>
																</tr>
														<?php }
														} ?>
													</tbody>
												</table>
											</td>
										</tr>
										<tr>
											<th scope="row" class="platform xpert" <?php echo (isset($attributes) && $attributes != "" && in_array("xpert", $attributes)) ? 'style="display:none;"' : ''; ?>><label class="label-control" for="xPertMTMResult">Xpert MTB Result</label></th>
											<td class="platform xpert" <?php echo (isset($attributes) && $attributes != "" && in_array("xpert", $attributes)) ? 'style="display:none;"' : ''; ?>>
												<select class="form-control" name="xPertMTMResult" id="xPertMTMResult" title="Please select the Xpert MTM Result">
													<?= $general->generateSelectOptions($tbXPertResults, $tbInfo['xpert_mtb_result'], '-- Select --'); ?>
												</select>
											</td>
											<th scope="row" class="platform lam" <?php echo (isset($attributes) && $attributes != "" && in_array("lam", $attributes)) ? 'style="display:none;"' : ''; ?>><label class="label-control" for="result">TB LAM Result</label></th>
											<td class="platform lam" <?php echo (isset($attributes) && $attributes != "" && in_array("lam", $attributes)) ? 'style="display:none;"' : ''; ?>>
												<select class="form-control" name="result" id="result" title="Please select the TB LAM result">
													<?= $general->generateSelectOptions($tbLamResults, $tbInfo['result'], '-- Select --'); ?>
												</select>
											</td>
										</tr>
										<tr>
											<th scope="row"><label class="label-control" for="reviewedBy">Reviewed By</label></th>
											<td>
												<select name="reviewedBy" id="reviewedBy" class="select2 form-control" title="Please choose reviewed by" style="width: 100%;">
													<?= $general->generateSelectOptions($userInfo, $tbInfo['result_reviewed_by'], '-- Select --'); ?>
												</select>
											</td>
											<th scope="row"><label class="label-control" for="reviewedOn">Reviewed on</label></th>
											<td><input type="text" value="<?php echo $tbInfo['result_reviewed_datetime']; ?>" name="reviewedOn" id="reviewedOn" class="dateTime disabled-field form-control" placeholder="Reviewed on" title="Please enter the reviewed on" /></td>
										</tr>
										<tr>
											<th scope="row"><label class="label-control" for="approvedBy">Approved By</label></th>
											<td>
												<select name="approvedBy" id="approvedBy" class="select2 form-control" title="Please choose approved by" style="width: 100%;">
													<?= $general->generateSelectOptions($userInfo, $tbInfo['result_approved_by'], '-- Select --'); ?>
												</select>
											</td>
											<th scope="row"><label class="label-control" for="approvedOn">Approved on</label></th>
											<td><input type="text" value="<?php echo $tbInfo['result_approved_datetime']; ?>" name="approvedOn" id="approvedOn" class="date-time form-control" placeholder="Approved on" title="Please enter the approved on" /></td>
										</tr>

									</table>
								</div>
							</div>
						<?php } ?>
					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<?php if ($arr['tb_sample_code'] == 'auto' || $arr['tb_sample_code'] == 'YY' || $arr['tb_sample_code'] == 'MMYY') { ?>
							<input type="hidden" name="sampleCodeFormat" id="sampleCodeFormat" value="<?php echo $sFormat; ?>" />
							<input type="hidden" name="sampleCodeKey" id="sampleCodeKey" value="<?php echo $sKey; ?>" />
							<input type="hidden" name="saveNext" id="saveNext" />
						<?php } ?>
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
						<input type="hidden" name="formId" id="formId" value="1" />
						<input type="hidden" name="tbSampleId" id="tbSampleId" value="<?php echo $id; ?>" />
						<a href="/tb/requests/tb-requests.php" class="btn btn-default"> Cancel</a>
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
<script type="text/javascript">
	provinceName = true;
	facilityName = true;

	function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
		var removeDots = obj.value.replace(/\./g, "");
		var removeDots = removeDots.replace(/\,/g, "");
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
					document.getElementById(obj.id).value = "";
				}
			});
	}

	function getTestingPoints() {
		var labId = $("#labId").val();
		var selectedTestingPoint = null;
		if (labId) {
			$.post("/includes/getTestingPoints.php", {
					labId: labId,
					selectedTestingPoint: selectedTestingPoint
				},
				function(data) {
					if (data != "") {
						$(".testingPointField").show();
						$("#testingPoint").html(data);
					} else {
						$(".testingPointField").hide();
						$("#testingPoint").html('');
					}
				});
		}
	}

	function getfacilityDetails(obj) {

		$.blockUI();
		var cName = $("#facilityId").val();
		var pName = $("#province").val();
		if (pName != '' && provinceName && facilityName) {
			facilityName = false;
		}
		if ($.trim(pName) != '') {
			$.post("/includes/siteInformationDropdownOptions.php", {
					pName: pName,
					testType: 'tb'
				},
				function(data) {
					if (data != "") {
						details = data.split("###");
						$("#facilityId").html(details[0]);
						$("#district").html(details[1]);
					}
				});
			sampleCodeGeneration();
		} else if (pName == '') {
			provinceName = true;
			facilityName = true;
			$("#province").html("<?php echo $province; ?>");
			$("#facilityId").html("<?php echo $facility; ?>");
			$("#facilityId").select2("val", "");
			$("#district").html("<option value=''> -- Select -- </option>");
		}
		$.unblockUI();
	}

	function getPatientDistrictDetails(obj) {

		$.blockUI();
		var pName = obj.value;
		if ($.trim(pName) != '') {
			$.post("/includes/siteInformationDropdownOptions.php", {
					pName: pName,
					testType: 'tb'
				},
				function(data) {
					if (data != "") {
						details = data.split("###");
						$("#patientDistrict").html(details[1]);
					}
				});
		} else if (pName == '') {
			$(obj).html("<?php echo $province; ?>");
			$("#patientDistrict").html("<option value=''> -- Select -- </option>");
		}
		$.unblockUI();
	}

	function setPatientDetails(pDetails) {
		patientArray = JSON.parse(pDetails);
		$("#firstName").val(patientArray['firstname']);
		$("#lastName").val(patientArray['lastname']);
		$("#patientGender").val(patientArray['gender']);
		$("#patientAge").val(patientArray['age']);
		$("#patientDob").val(patientArray['dob']);
		$("#patientId").val(patientArray['patient_id']);
	}

	function sampleCodeGeneration() {
		var pName = $("#province").val();
		var sDate = $("#sampleCollectionDate").val();
		if (pName != '' && sDate != '') {
			$.post("/tb/requests/generate-sample-code.php", {
					sDate: sDate,
					pName: pName
				},
				function(data) {
					var sCodeKey = JSON.parse(data);
					$("#sampleCode").val(sCodeKey.sampleCode);
					$("#sampleCodeInText").html(sCodeKey.sampleCodeInText);
					$("#sampleCodeFormat").val(sCodeKey.sampleCodeFormat);
					$("#sampleCodeKey").val(sCodeKey.sampleCodeKey);
				});
		}
	}

	function getfacilityDistrictwise(obj) {
		$.blockUI();
		var dName = $("#district").val();
		var cName = $("#facilityId").val();
		if (dName != '') {
			$.post("/includes/siteInformationDropdownOptions.php", {
					dName: dName,
					cliName: cName,
					testType: 'tb'
				},
				function(data) {
					if (data != "") {
						details = data.split("###");
						$("#facilityId").html(details[0]);
					}
				});
		} else {
			$("#facilityId").html("<option value=''> -- Select -- </option>");
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
					testType: 'tb'
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


	function validateNow() {
		if ($('#isResultAuthorized').val() != "yes") {
			$('#authorizedBy,#authorizedOn').removeClass('isRequired');
		}
		flag = deforayValidator.init({
			formId: 'editTbRequestForm'
		});
		if (flag) {
			document.getElementById('editTbRequestForm').submit();
		}
	}

	$(document).ready(function() {

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
		if ($("#sampleDispatchedDate").val() == "" || (collectDate >= dispatchDate))
			$("#sampleDispatchedDate").val($('#sampleCollectionDate').val());

		$('#sampleDispatchedDate').datetimepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: 'dd-M-yy',
			timeFormat: "HH:mm",
			minDate: minDate,
			startDate: minDate,
		});

		$("#labId,#facilityId,#sampleCollectionDate").on('change', function() {
			if ($("#labId").val() != '' && $("#labId").val() == $("#facilityId").val() && $("#sampleDispatchedDate").val() == "") {
				$('#sampleDispatchedDate').datetimepicker("setDate", new Date($('#sampleCollectionDate').datetimepicker('getDate')));
			}
			if ($("#labId").val() != '' && $("#labId").val() == $("#facilityId").val() && $("#sampleReceivedDate").val() == "") {
				// $('#sampleReceivedDate').datetimepicker("setDate", new Date($('#sampleCollectionDate').datetimepicker('getDate')));
			}

			if ($("#labId").val() != "") {
				$.post("/includes/get-sample-type.php", {
						facilityId: $('#labId').val(),
						testType: 'tb',
						sampleId: '<?php echo $tbInfo['specimen_type']; ?>'
					},
					function(data) {
						if (data != "") {
							$("#specimenType").html(data);
						}
					});
			}
		});

		$("#labId,#facilityId,#sampleCollectionDate").trigger('change');

		showOther($("#referringUnit").val(), 'typeOfReferringUnit');
		showOther($("#typeOfPatient").val(), 'typeOfPatientOther');
		showOther($("#specimenType").val(), 'specimenTypeOther');
		$(".select2").select2();
		$(".select2").select2({
			tags: true
		});
		$('#typeOfPatient').select2({
			placeholder: "Select Type of Patient"
		});
		$('#reasonForTbTest').select2({
			placeholder: "Select Test Reqest Type"
		});
		$('#testTypeRequested').select2({
			placeholder: "Select Type of Examination"
		});
		$('#facilityId').select2({
			placeholder: "Select Clinic/Health Center"
		});
		$('#labTechnician').select2({
			placeholder: "Select Lab Technician"
		});

		$('#patientNationality').select2({
			placeholder: "Select Nationality"
		});

		$('#patientProvince').select2({
			placeholder: "Select Patient State"
		});

		$('#isResultAuthorized').change(function(e) {
			checkIsResultAuthorized();
		});

		$('#sourceOfAlertPOE').change(function(e) {
			if (this.value == 'others') {
				$('.show-alert-poe').show();
				$('#alertPoeOthers').addClass('isRequired');
			} else {
				$('.show-alert-poe').hide();
				$('#alertPoeOthers').removeClass('isRequired');
			}
		});
		<?php if (isset($arr['tb_positive_confirmatory_tests_required_by_central_lab']) && $arr['tb_positive_confirmatory_tests_required_by_central_lab'] == 'yes') { ?>
			$(document).on('change', '.test-result, #result', function(e) {
				checkPostive();
			});
		<?php } ?>
		getfacilityProvinceDetails($("#facilityId").val());
		$("#labId").change(function(e) {
			if ($(this).val() != "") {
				$.post("/tb/requests/get-attributes-data.php", {
						id: this.value,
					},
					function(data) {
						console.log(data);
						if (data != "" && data != false) {
							_data = jQuery.parseJSON(data);
							$(".platform").hide();
							$.each(_data, function(index, value) {
								$("." + value).show();
							});
						}
					});
			}
		});
	});

	function checkIsResultAuthorized() {
		if ($('#isResultAuthorized').val() == 'no') {
			$('#authorizedBy,#authorizedOn').val('');
			$('#authorizedBy,#authorizedOn').prop('disabled', true);
			$('#authorizedBy,#authorizedOn').addClass('disabled');
			$('#authorizedBy,#authorizedOn').removeClass('isRequired');
		} else {
			$('#authorizedBy,#authorizedOn').prop('disabled', false);
			$('#authorizedBy,#authorizedOn').removeClass('disabled');
			$('#authorizedBy,#authorizedOn').addClass('isRequired');
		}
	}

	function showOther(obj, othersId) {
		if (obj == 'other') {
			$('.' + othersId).show();
		} else {
			$('.' + othersId).hide();
		}
	}

	function checkSubReason(obj, show, opUncheck) {
		$('.reason-checkbox').prop("checked", false);
		if (opUncheck == "followup-uncheck") {
			$('#followUp').val("");
			$("#xPertMTMResult").prop('disabled', false);
		} else {
			$("#xPertMTMResult").prop('disabled', true);
		}
		$('.' + opUncheck).prop("checked", false);
		if ($(obj).prop("checked", true)) {
			$('.' + show).show(300);
			$('.' + show).removeClass('hide-reasons');
			$('.hide-reasons').hide(300);
			$('.' + show).addClass('hide-reasons');
		}
	}
</script>
