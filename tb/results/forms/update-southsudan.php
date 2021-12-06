<?php
// imported in tb-add-request.php based on country in global config

ob_start();

//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);
/* To get testing platform names */
$testPlatformResult = $general->getTestingPlatforms('tb');
// Nationality
$nationalityQry = "SELECT * FROM `r_countries` ORDER BY `iso_name` ASC";
$nationalityResult = $db->query($nationalityQry);

foreach ($nationalityResult as $nrow) {
	$nationalityList[$nrow['id']] = ucwords($nrow['iso_name']) . ' (' . $nrow['iso3'] . ')';
}

foreach ($testPlatformResult as $row) {
	$testPlatformList[$row['machine_name']] = $row['machine_name'];
}
//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);

$tbObj = new \Vlsm\Models\Tb();
$tbXPertResults = $tbObj->getTbResults('x-pert');
$tbLamResults = $tbObj->getTbResults('lam');
$specimenTypeResult = $tbObj->getTbSampleTypes();
$tbReasonsForTesting = $tbObj->getTbReasonsForTesting();


$rKey = '';
$sKey = '';
$sFormat = '';
$pdQuery = "SELECT * from province_details";
if ($_SESSION['accessType'] == 'collection-site') {
	$sampleCodeKey = 'remote_sample_code_key';
	$sampleCode = 'remote_sample_code';
	$rKey = 'R';
} else {
	$sampleCodeKey = 'sample_code_key';
	$sampleCode = 'sample_code';
	$rKey = '';
}
//check user exist in user_facility_map table
$chkUserFcMapQry = "SELECT user_id FROM vl_user_facility_map WHERE user_id='" . $_SESSION['userId'] . "'";
$chkUserFcMapResult = $db->query($chkUserFcMapQry);
if ($chkUserFcMapResult) {
	$pdQuery = "SELECT * FROM province_details as pd JOIN facility_details as fd ON fd.facility_state=pd.province_name JOIN vl_user_facility_map as vlfm ON vlfm.facility_id=fd.facility_id where user_id='" . $_SESSION['userId'] . "' group by province_name";
}
$pdResult = $db->query($pdQuery);
$province = "";
$province .= "<option value=''> -- Select -- </option>";
foreach ($pdResult as $provinceName) {
	$selected = "";
	if ($tbInfo['province_id'] == $provinceName['province_id']) {
		$selected = "selected='selected'";
	}
	$province .= "<option data-code='" . $provinceName['province_code'] . "' data-province-id='" . $provinceName['province_id'] . "' data-name='" . $provinceName['province_name'] . "' value='" . $provinceName['province_name'] . "##" . $provinceName['province_code'] . "'" . $selected . ">" . ucwords($provinceName['province_name']) . "</option>";
}

$facility = $general->generateSelectOptions($healthFacilities, $tbInfo['facility_id'], '-- Select --');

$microscope = array("No AFB" => "No AFB", "1+" => "1+", "2+" => "2+", "3+" => "3+");

$typeOfPatient = json_decode($typeOfPatient);
$reasonForTbTest = json_decode($tbInfo['reason_for_tb_test']);
$testTypeRequested = json_decode($tbInfo['tests_requested']);
?>

<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><i class="fa fa-edit"></i> TB LABORATORY TEST REQUEST FORM</h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
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
				<form class="form-horizontal" method="post" name="editTbRequestForm" id="editTbRequestForm" autocomplete="off" action="tb-update-result-helper.php">
					<div class="box-body">
						<div class="box box-default disabledForm">
							<div class="box-body">
								<div class="box-header with-border sectionHeader">
									<h3 class="box-title">TESTING LAB INFORMATION</h3>
								</div>
								<div class="box-header with-border">
									<h3 class="box-title" style="font-size:1em;">To be filled by requesting Clinician/Nurse</h3>
								</div>
								<table class="table" style="width:100%">
									<tr>
										<?php if ($_SESSION['accessType'] == 'collection-site') { ?>
											<th style="width: 16.6%;"><label class="label-control" for="sampleCode">Sample ID </label></th>
											<td style="width: 16.6%;">
												<span id="sampleCodeInText" style="width:100%;border-bottom:1px solid #333;"><?php echo (isset($tbInfo['remote_sample_code']) && $tbInfo['remote_sample_code'] != "") ? $tbInfo['remote_sample_code'] : $tbInfo['sample_code']; ?></span>
												<input type="hidden" id="sampleCode" name="sampleCode" />
											</td>
										<?php } else { ?>
											<th style="width: 14%;"><label class="label-control" for="sampleCode">Sample ID </label></th>
											<td style="width: 18%;">
												<input type="text" value="<?php echo $tbInfo['sample_code']; ?>" class="form-control" id="sampleCode" name="sampleCode" readonly="readonly" placeholder="Sample ID" title="Please enter sample code" style="width:100%;" onchange="checkSampleNameValidation('form_tb','<?php echo $sampleCode; ?>',this.id,null,'The sample id that you entered already exists. Please try another sample id',null)" />
											</td>
										<?php } ?>
										<th></th>
										<td></td>
										<th></th>
										<td></td>
									</tr>
								</table>
								<div class="box-header with-border sectionHeader">
									<h3 class="box-title">REFERRING HEALTH FACILITY INFORMATION</h3>
								</div>
								<table class="table" style="width:100%">
									<tr>
										<td><label class="label-control" for="province">Health Facility/POE State </label></td>
										<td>
											<select class="form-control select2" name="province" id="province" title="Please choose State" onchange="getfacilityDetails(this);" style="width:100%;">
												<?php echo $province; ?>
											</select>
										</td>
										<td><label class="label-control" for="district">Health Facility/POE County </label></td>
										<td>
											<select class="form-control select2" name="district" id="district" title="Please choose County" style="width:100%;" onchange="getfacilityDistrictwise(this);">
												<option value=""> -- Select -- </option>
											</select>
										</td>
										<td><label class="label-control" for="facilityId">Health Facility/POE </label></td>
										<td>
											<select class="form-control " name="facilityId" id="facilityId" title="Please choose service provider" style="width:100%;" onchange="getfacilityProvinceDetails(this);">
												<?php echo $facility; ?>
											</select>
										</td>
									</tr>
									<tr>
										<th><label for="requestedDate">Date of request </label></th>
										<td>
											<input type="text" value="<?php echo $tbInfo['request_created_datetime']; ?>" class="date-time form-control" id="requestedDate" name="sampleReceivedDate" placeholder="e.g 09-Jan-1992 05:30" title="Please enter date of request date" style="width:100%;" />
										</td>
										<td><label class="label-control" for="referringUnit">Referring Unit </label></td>
										<td>
											<select class="form-control " name="referringUnit" id="referringUnit" title="Please choose referring unit" style="width:100%;">
												<option value="">-- Select --</option>
												<option value="art" <?php echo (isset($tbInfo['referring_unit']) && $tbInfo['referring_unit'] == 'art') ? "selected='selected'" : ""; ?>>ART</option>
												<option value="opd" <?php echo (isset($tbInfo['referring_unit']) && $tbInfo['referring_unit'] == 'opd') ? "selected='selected'" : ""; ?>>OPD</option>
												<option value="tb <?php echo (isset($tbInfo['referring_unit']) && $tbInfo['referring_unit'] == 'tb') ? "selected='selected'" : ""; ?>">TB</option>
												<option value="pmtct" <?php echo (isset($tbInfo['referring_unit']) && $tbInfo['referring_unit'] == 'pmtct') ? "selected='selected'" : ""; ?>>PMTCT</option>
												<option value="medical" <?php echo (isset($tbInfo['referring_unit']) && $tbInfo['referring_unit'] == 'medical') ? "selected='selected'" : ""; ?>>Medical</option>
												<option value="paediatric" <?php echo (isset($tbInfo['referring_unit']) && $tbInfo['referring_unit'] == 'paediatric') ? "selected='selected'" : ""; ?>>Paediatric</option>
												<option value="nutrition" <?php echo (isset($tbInfo['referring_unit']) && $tbInfo['referring_unit'] == 'nutrition') ? "selected='selected'" : ""; ?>>Nutrition</option>
												<option value="others" <?php echo (isset($tbInfo['referring_unit']) && $tbInfo['referring_unit'] == 'others') ? "selected='selected'" : ""; ?>>Others</option>
											</select>
											<input type="text" style="display: none;" name="otherReferringUnit" id="otherReferringUnit" placeholder="Enter other reffering unit" title="Please enter the other referring unit" />
										</td>
										<?php if ($_SESSION['accessType'] == 'collection-site') { ?>
											<td><label class="label-control" for="labId">Testing Laboratory </label> </td>
											<td>
												<select name="labId" id="labId" class="form-control select2" title="Please select Testing Testing Laboratory" style="width:100%;">
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
									<input style="width:30%;" type="text" name="patientNoSearch" id="patientNoSearch" class="" placeholder="Enter Patient ID or Patient Name" title="Enter art number or patient name" />&nbsp;&nbsp;
									<a style="margin-top:-0.35%;" href="javascript:void(0);" class="btn btn-default btn-sm" onclick="showPatientList();"><i class="fa fa-search">&nbsp;</i>Search</a><span id="showEmptyResult" style="display:none;color: #ff0000;font-size: 15px;"><b>&nbsp;No Patient Found</b></span>
								</div>
								<table class="table" style="width:100%">
									<tr>
										<th><label for="patientId">Unique ART Number </label></th>
										<td>
											<input type="text" value="<?php echo $tbInfo['patient_id']; ?>" class="form-control" id="patientId" name="patientId" placeholder="Patient Identification" title="Please enter Patient ID" style="width:100%;" onchange="" />
										</td>
										<th><label for="firstName">First Name </label></th>
										<td>
											<input type="text" value="<?php echo $tbInfo['patient_name']; ?>" class="form-control" id="firstName" name="firstName" placeholder="First Name" title="Please enter First name" style="width:100%;" onchange="" />
										</td>
									</tr>
									<tr>
										<th><label for="lastName">Sur name </label></th>
										<td>
											<input type="text" value="<?php echo $tbInfo['patient_surname']; ?>" class="form-control " id="lastName" name="lastName" placeholder="Last name" title="Please enter Last name" style="width:100%;" onchange="" />
										</td>
										<th><label for="patientDob">Date of Birth </label></th>
										<td>
											<input type="text" value="<?php echo $general->humanDateFormat($tbInfo['patient_dob']); ?>" class="form-control" id="patientDob" name="patientDob" placeholder="Date of Birth" title="Please enter Date of birth" style="width:100%;" onchange="calculateAgeInYears();" />
										</td>
									</tr>
									<tr>
										<th>Age (years)</th>
										<td><input type="number" value="<?php echo $tbInfo['patient_age']; ?>" max="150" maxlength="3" oninput="this.value=this.value.slice(0,$(this).attr('maxlength'))" class="form-control " id="patientAge" name="patientAge" placeholder="Patient Age (in years)" title="Patient Age" style="width:100%;" onchange="" /></td>
										<th><label for="patientGender">Gender </label></th>
										<td>
											<select class="form-control" name="patientGender" id="patientGender" title="Please select the gender">
												<option value=''> -- Select -- </option>
												<option value='male' <?php echo (isset($tbInfo['patient_gender']) && $tbInfo['patient_gender'] == 'male') ? "selected='selected'" : ""; ?>> Male </option>
												<option value='female' <?php echo (isset($tbInfo['patient_gender']) && $tbInfo['patient_gender'] == 'female') ? "selected='selected'" : ""; ?>> Female </option>
												<option value='other' <?php echo (isset($tbInfo['patient_gender']) && $tbInfo['patient_gender'] == 'other') ? "selected='selected'" : ""; ?>> Other </option>
											</select>
										</td>
									</tr>
									<tr>
										<th><label for="typeOfPatient">Type of patient </label></th>
										<td>
											<select class="select2 form-control" name="typeOfPatient[]" id="typeOfPatient" title="Please select the type of patient" multiple>
												<option value=''> -- Select -- </option>
												<option value='new' <?php echo (isset($typeOfPatient) && in_array('new', $typeOfPatient)) ? "selected='selected'" : ""; ?>> New </option>
												<option value='loss-to-follow-up' <?php echo (isset($typeOfPatient) && in_array('loss-to-follow-up', $typeOfPatient)) ? "selected='selected'" : ""; ?>> Loss to Follow Up </option>
												<option value='treatment-failure' <?php echo (isset($typeOfPatient) && in_array('treatment-failure', $typeOfPatient)) ? "selected='selected'" : ""; ?>> Treatment Failure </option>
												<option value='relapse' <?php echo (isset($typeOfPatient) && in_array('relapse', $typeOfPatient)) ? "selected='selected'" : ""; ?>> Relapse </option>
												<option value='other' <?php echo (isset($typeOfPatient) && in_array('other', $typeOfPatient)) ? "selected='selected'" : ""; ?>> Other </option>
											</select>
											<input type="text" class="form-control" id="typeOfPatientOther" name="typeOfPatientOther" placeholder="Enter type of patient if others" title="Please enter type of patient if others" style="display: none;" />
										</td>
										<th><label for="reasonForTbTest">Type of Examination </label></th>
										<td>
											<select name="reasonForTbTest[]" id="reasonForTbTest" class="select2 form-control" title="Please choose reason for examination" style="width:100%" multiple>
												<option value="">--Seelct--</option>
												<?php foreach ($tbReasonsForTesting as $key => $val) { ?>
													<option value="<?php echo $key; ?>" <?php echo (isset($reasonForTbTest) && in_array($key, $reasonForTbTest)) ? "selected='selected'" : ""; ?>><?php echo ucwords($val); ?></option>
												<?php } ?>
											</select>
										</td>
									</tr>
								</table>

								<div class="box-header with-border sectionHeader">
									<h3 class="box-title">SPECIMEN INFORMATION</h3>
								</div>
								<table class="table">
									<tr>
										<th><label class="label-control" for="sampleCollectionDate">Date of Specimen Collected </label></th>
										<td>
											<input class="form-control" value="<?php echo $tbInfo['sample_collection_date']; ?>" type="text" name="sampleCollectionDate" id="sampleCollectionDate" placeholder="Sample Collection Date" onchange="sampleCodeGeneration();" />
										</td>
										<th><label class="label-control" for="sampleReceivedDate">Date of Specimen Reception </label></th>
										<td>
											<input type="text" class="date-time form-control" value="<?php echo $tbInfo['sample_received_at_lab_datetime']; ?>" id="sampleReceivedDate" name="sampleReceivedDate" placeholder="e.g 09-Jan-1992 05:30" title="Please enter sample receipt date" style="width:100%;" />
										</td>
									</tr>
									<tr>
										<th><label class="label-control" for="specimenType">Specimen Type </label></th>
										<td>
											<select name="specimenType" id="specimenType" class="form-control" title="Please choose specimen type" style="width:100%">
												<?php echo $general->generateSelectOptions($specimenTypeResult, $tbInfo['specimen_type'], '-- Select --'); ?>
												<option value='other'> Other </option>
											</select>
											<input type="text" id="sampleTypeOther" name="sampleTypeOther" placeholder="Enter sample type of others" title="Please enter the sample type if others" style="display: none;" />
										</td>
										<th>
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
									</tr>
									<tr>
										<th>
											<label class="label-control" for="testTypeRequested">Test(s) requested </label>
										</th>
										<td>
											<select name="testTypeRequested[]" id="testTypeRequested" class="select2 form-control" title="Please choose type of test request" style="width:100%" multiple>
												<optgroup label="Microscopy">
													<option value="ZN" <?php echo (isset($testTypeRequested) && in_array("ZN", $testTypeRequested)) ? "selected='selecetd'" : ""; ?>>ZN</option>
													<option value="FM" <?php echo (isset($testTypeRequested) && in_array("FM", $testTypeRequested)) ? "selected='selecetd'" : ""; ?>>FM</option>
												</optgroup>
												<optgroup label="X pert MTB">
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
						<?php if ($usersModel->isAllowed('tb-update-result.php', $systemConfig) || $_SESSION['accessType'] != 'collection-site') { ?>
							<?php // if (false) { 
							?>
							<div class="box box-primary">
								<div class="box-body">
									<div class="box-header with-border">
										<h3 class="box-title">Results (To be completed in the Laboratory) </h3>
									</div>
									<table class="table" style="width:100%">
										<tr>
											<td><label class="label-control" for="labId">Testing Laboratory<span class="mandatory">*</span></label> </td>
											<td>
												<select name="labId" id="labId" class="form-control select2 isRequired" title="Please select Testing Testing Laboratory" style="width:100%;">
													<?= $general->generateSelectOptions($testingLabs, $tbInfo['lab_id'], '-- Select --'); ?>
												</select>
											</td>
											<th><label class="label-control" for="sampleTestedDateTime">Date of Sample Tested <span class="mandatory">*</span></label></th>
											<td>
												<input type="text" value="<?php echo $tbInfo['sample_tested_datetime']; ?>" class="date-time form-control isRequired" value="<?php echo $tbInfo['sample_collection_date']; ?>" id="sampleTestedDateTime" name="sampleTestedDateTime" placeholder="e.g 09-Jan-1992 05:30" title="Please enter sample tested" style="width:100%;" />
											</td>
										</tr>
										<tr>
											<th><label class="label-control" for="isSampleRejected">Is Sample Rejected? <span class="mandatory">*</span></label></th>
											<td>
												<select class="form-control isRequired" name="isSampleRejected" id="isSampleRejected" title="Please select the Is sample rejected?">
													<option value=''> -- Select -- </option>
													<option value="yes" <?php echo (isset($tbInfo['is_sample_rejected']) && $tbInfo['is_sample_rejected'] == "yes") ? "selected='selecetd'" : ""; ?>> Yes </option>
													<option value="no" <?php echo (isset($tbInfo['is_sample_rejected']) && $tbInfo['is_sample_rejected'] == "no") ? "selected='selecetd'" : ""; ?>> No </option>
												</select>
											</td>

											<th class="show-rejection" style="display:none;"><label class="label-control" for="sampleRejectionReason">Reason for Rejection <span class="mandatory">*</span></label></th>
											<td class="show-rejection" style="display:none;">
												<select class="form-control" name="sampleRejectionReason" id="sampleRejectionReason" title="Please select the reason for rejection">
													<option value="">-- Select --</option>
													<?php foreach ($rejectionTypeResult as $type) { ?>
														<optgroup label="<?php echo ucwords($type['rejection_type']); ?>">
															<?php
															foreach ($rejectionResult as $reject) {
																if ($type['rejection_type'] == $reject['rejection_type']) { ?>
																	<option value="<?php echo $reject['rejection_reason_id']; ?>" <?php echo ($tbInfo['reason_for_sample_rejection'] == $reject['rejection_reason_id']) ? 'selected="selected"' : ''; ?>><?php echo ucwords($reject['rejection_reason_name']); ?></option>
															<?php }
															} ?>
														</optgroup>
													<?php }
													if ($covid19Info['reason_for_sample_rejection'] == 9999) {
														echo '<option value="9999" selected="selected">Unspecified</option>';
													} ?>
												</select>
											</td>
										</tr>
										<tr class="show-rejection" style="display:none;">
											<th><label class="label-control" for="rejectionDate">Rejection Date<span class="mandatory">*</span></label></th>
											<td><input value="<?php echo $general->humanDateFormat($tbInfo['rejection_on']); ?>" class="form-control date rejection-date" type="text" name="rejectionDate" id="rejectionDate" placeholder="Select rejection date" title="Please select the rejection date" /></td>
											<td></td>
											<td></td>
										</tr>
										<tr>
											<td colspan="4">
												<table class="table table-bordered table-striped">
													<thead>
														<tr>
															<th style="width: 10%;" class="text-center">No AFB</th>
															<th style="width: 20%;" class="text-center">Actual No</th>
															<th style="width: 20%;" class="text-center">Microscopy (Result)</th>
															<th style="width: 20%;" class="text-center">Test Platforms</th>
															<th style="width: 10%;" class="text-center">Action</th>
														</tr>
													</thead>
													<tbody id="testKitNameTable">
														<?php
														if (count($tbTestInfo) > 0) {

															foreach ($tbTestInfo as $key => $tbRow) { ?>
																<tr>
																	<td class="text-center"><?php echo ($key + 1); ?></td>
																	<td>
																		<input type="text" value="<?php echo $tbRow['actual_no']; ?>" class="form-control test-name-table-input isRequired" id="actualNo<?php echo ($key + 1); ?>" name="actualNo[]" placeholder="Enter the actual number" title="Please enter the actual number" />
																	</td>
																	<td>
																		<select class="form-control test-result test-name-table-input isRequired" name="testResult[]" id="testResult<?php echo ($key + 1); ?>" title="Please select the result for row <?php echo ($key + 1); ?>">
																			<?= $general->generateSelectOptions($microscope, $tbRow['test_result'], '-- Select --'); ?>
																		</select>
																	</td>
																	<td>
																		<select class="form-control test-name-table-input" name="testPlatforms[]" id="testPlatforms<?php echo ($key + 1); ?>" title="Please select the test platform for row 1">
																			<?= $general->generateSelectOptions($testPlatformList, $tbRow['testing_platform'], '-- Select --'); ?>
																		</select>
																	</td>
																	<td style="vertical-align:middle;text-align: center;width:100px;">
																		<a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addTestRow();"><i class="fa fa-plus"></i></a>&nbsp;
																		<a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode);"><i class="fa fa-minus"></i></a>
																	</td>
																</tr>
															<?php }
														} else { ?>
															<tr>
																<td class="text-center">1</td>
																<td>
																	<input type="text" class="form-control test-name-table-input isRequired" id="actualNo1" name="actualNo[]" placeholder="Enter the actual number" title="Please enter the actual number" />
																</td>
																<td>
																	<select class="form-control test-result test-name-table-input isRequired" name="testResult[]" id="testResult1" title="Please select the result for row 1">
																		<?= $general->generateSelectOptions($microscope, null, '-- Select --'); ?>
																	</select>
																</td>
																<td>
																	<select class="form-control test-name-table-input" name="testPlatforms[]" id="testPlatforms1" title="Please select the test platform for row 1">
																		<?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?>
																	</select>
																</td>
																<td style="vertical-align:middle;text-align: center;width:100px;">
																	<a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addTestRow();"><i class="fa fa-plus"></i></a>&nbsp;
																	<a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode);"><i class="fa fa-minus"></i></a>
																</td>
															</tr>
														<?php } ?>
													</tbody>
												</table>
											</td>
										</tr>
										<tr>
											<th><label class="label-control" for="xPertMTMResult">X pert MTB Result <span class="mandatory">*</span></label></th>
											<td>
												<select class="form-control test-result test-name-table-input isRequired" name="xPertMTMResult" id="xPertMTMResult" title="Please select the X Pert MTM Result">
													<?= $general->generateSelectOptions($tbXPertResults, $tbInfo['xpert_mtb_result'], '-- Select --'); ?>
												</select>
											</td>
											<th><label class="label-control" for="result">TB LAM Result <span class="mandatory">*</span></label></th>
											<td>
												<select class="form-control isRequired" name="result" id="result" title="Please select the TB LAM result">
													<?= $general->generateSelectOptions($tbLamResults, $tbInfo['result'], '-- Select --'); ?>
												</select>
											</td>
										</tr>
										<tr>
											<th><label class="label-control" for="reviewedBy">Reviewed By <span class="mandatory">*</span></label></th>
											<td>
												<select name="reviewedBy" id="reviewedBy" class="select2 form-control isRequired" title="Please choose reviewed by" style="width: 100%;">
													<?= $general->generateSelectOptions($userInfo, $tbInfo['result_reviewed_by'], '-- Select --'); ?>
												</select>
											</td>
											<th><label class="label-control" for="reviewedOn">Reviewed on <span class="mandatory">*</span></label></td>
											<td><input type="text" value="<?php echo $tbInfo['result_reviewed_datetime']; ?>" name="reviewedOn" id="reviewedOn" class="date-time disabled-field form-control isRequired" placeholder="Reviewed on" title="Please enter the reviewed on" /></td>
										</tr>
										<tr>
											<th><label class="label-control" for="approvedBy">Approved By <span class="mandatory">*</span></label></th>
											<td>
												<select name="approvedBy" id="approvedBy" class="select2 form-control isRequired" title="Please choose approved by" style="width: 100%;">
													<?= $general->generateSelectOptions($userInfo, $tbInfo['result_approved_by'], '-- Select --'); ?>
												</select>
											</td>
											<th><label class="label-control" for="approvedOn">Approved on <span class="mandatory">*</span></label></td>
											<td><input type="text" value="<?php echo $tbInfo['result_approved_datetime']; ?>" name="approvedOn" id="approvedOn" class="date-time form-control isRequired" placeholder="Approved on" title="Please enter the approved on" /></td>
										</tr>
										<tr>
											<th><label class="label-control" for="testedBy">Tested By <span class="mandatory">*</span></label></th>
											<td>
												<select name="testedBy" id="testedBy" class="select2 form-control isRequired" title="Please choose approved by" style="width: 100%;">
													<?= $general->generateSelectOptions($userInfo, $tbInfo['tested_by'], '-- Select --'); ?>
												</select>
											</td>
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
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();$('#saveNext').val('next');return false;">Save and Next</a>
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
	changeProvince = true;
	changeFacility = true;
	provinceName = true;
	facilityName = true;
	machineName = true;
	tableRowId = 2;

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
		patientArray = pDetails.split("##");
		$("#patientProvince").val(patientArray[14] + '##' + patientArray[16]).trigger('change');
		$("#firstName").val(patientArray[0]);
		$("#lastName").val(patientArray[1]);
		$("#patientPhoneNumber").val(patientArray[8]);
		$("#patientGender").val(patientArray[2]);
		$("#patientAge").val(patientArray[4]);
		$("#patientDob").val(patientArray[3]);
		$("#patientId").val(patientArray[9]);
		$("#patientPassportNumber").val(patientArray[10]);
		$("#patientAddress").text(patientArray[11]);
		$("#patientNationality").select2('val', patientArray[12]);
		$("#patientCity").val(patientArray[13]);

		setTimeout(function() {
			$("#patientDistrict").val(patientArray[15]).trigger('change');
		}, 3000);
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

		$('.disabledForm input, .disabledForm select , .disabledForm textarea').attr('disabled', true);
		$('.disabledForm input, .disabledForm select , .disabledForm textarea').removeClass("isRequired");
	});

	let testCounter = 1;

	function addTestRow() {
		testCounter++;
		let rowString = `<tr>
			<td class="text-center">${testCounter}</td>
            <td><input type="text" class="form-control test-name-table-input" id="actualNo${testCounter}" name="actualNo[]" placeholder="Enter the actual number" title="Please enter the actual number" /></td>
            <td><select class="form-control test-result test-name-table-input" name="testResult[]" id="testResult${testCounter}" title="Please select the result for row ${testCounter}"><?= $general->generateSelectOptions($microscope, null, '-- Select --'); ?></select></td>
			<td><select class="form-control test-name-table-input" name="testPlatforms[]" id="testPlatforms${testCounter}" title="Please select the test platform for row ${testCounter}"><?= $general->generateSelectOptions($testPlatformList, null, '-- Select --'); ?></select></td>
            <td style="vertical-align:middle;text-align: center;width:100px;">
                <a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addTestRow(this);"><i class="fa fa-plus"></i></a>&nbsp;
                <a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeTestRow(this.parentNode.parentNode);"><i class="fa fa-minus"></i></a>
            </td>
        </tr>`;

		$("#testKitNameTable").append(rowString);

	}

	function removeTestRow(el) {
		$(el).fadeOut("slow", function() {
			el.parentNode.removeChild(el);
			rl = document.getElementById("testKitNameTable").rows.length;
			if (rl == 0) {
				testCounter = 0;
				addTestRow();
			}
		});
	}

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

	function otherTbTestName(val, id) {
		if (val == 'other') {
			$('.testNameOther' + id).show();
		} else {
			$('.testNameOther' + id).hide();
		}
	}
</script>