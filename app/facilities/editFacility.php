<?php

use App\Services\UsersService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;



require_once APPLICATION_PATH . '/header.php';
/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


/** @var GeoLocationsService $geolocation */
$geolocation = ContainerRegistry::get(GeoLocationsService::class);



/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$userResult = $usersService->getAllUsers();

$userInfo = [];
foreach ($userResult as $user) {
	if (!empty($user['user_name'])) {
		$userInfo[$user['user_id']] = $user['user_name'];
	}
}

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;

//$id = $_GET['id'];
$facilityInfo = $db->rawQueryOne('SELECT * FROM facility_details WHERE facility_id= ?', [$id]);

$facilityAttributes = json_decode((string) $facilityInfo['facility_attributes']);


$facilityReportFormat = (array)json_decode($facilityInfo['report_format']);
// echo "<pre>";
// print_r($facilityReportFormat);die;
$fQuery = "SELECT * FROM facility_type";
$fResult = $db->rawQuery($fQuery);
$pResult = $general->fetchDataFromTable('geographical_divisions', "geo_parent = 0 AND geo_status='active'");

//$chkvlLabResult = $db->rawQuery('SELECT * from testing_lab_health_facilities_map as vlfm where vl_lab_id = ?', array($id));
//$chkHcResult = $db->rawQuery('SELECT * from testing_lab_health_facilities_map as vlfm where facility_id = ?', array($id));

$fType = $facilityInfo['facility_type'];

$labDiv = $allowFileDiv = "none";
if ($fType == 2) {
	$labDiv = $allowFileDiv = "block";
}

$testTypeInfo = $db->rawQuery('SELECT * FROM testing_labs WHERE facility_id = ?', [$id]);
$availPlatforms = [];
if (!empty($testTypeInfo) && !empty($testTypeInfo['attributes'])) {
	$attrValue = json_decode((string) $testTypeInfo['attributes']);
	$availPlatforms = $attrValue->platforms;
}

$signResults = $db->rawQuery('SELECT * FROM lab_report_signatories WHERE lab_id=? ORDER BY display_order, name_of_signatory', [$id]);

$editTestType = '';
$div = '';
$extraDiv = '';
if (!empty($testTypeInfo)) {
	$div .= '<table aria-describedby="table" class="table table-bordered table-striped" aria-hidden="true" ><thead><th> Test Type</th> <th> Monthly Target <span class="mandatory">*</span></th><th>Suppressed Monthly Target <span class="mandatory">*</span></th> </thead><tbody>';
	$tf = 0;
	foreach ($testTypeInfo as $test) {
		if ($editTestType) {
			$editTestType = $editTestType . ',' . $test['test_type'];
		} else {
			$editTestType = $test['test_type'];
		}

		$testOrg = '';
		if ($test['test_type'] == 'vl') {
			$testOrg = 'Viral Load';
			$extraDiv = '<td><input type="text" class="" name="supMonTar[]" id ="supMonTar' . $tf . '" value="' . $test['suppressed_monthly_target'] . '" title="Please enter Suppressed monthly target"/></td>';
		} else if ($test['test_type'] == 'eid') {
			$testOrg = 'Early Infant Diagnosis';
			$extraDiv = '<td></td>';
		} else if ($test['test_type'] == 'covid19') {
			$testOrg = 'Covid-19';
			$extraDiv = '<td></td>';
		}
		$div .= '<tr><td>' . $testOrg . '<input type="hidden" name="testData[]" id ="testData' . $tf . '" value="' . $test['test_type'] . '" /></td>';
		$div .= '<td><input type="text" class="" name="monTar[]" id ="monTar' . $tf . '" value="' . $test['monthly_target'] . '" title="Please enter monthly target"/></td>';
		$div .= $extraDiv;
		$div .= '</tr>';
		$tf++;
	}
	$div .= '</tbody></table>';
}
$reportFormats = [];
if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) {
	$reportFormats['covid19'] = $general->activeReportFormats('covid-19');
}
if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) {
	$reportFormats['eid'] = $general->activeReportFormats('eid');
}
if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) {
	$reportFormats['vl'] = $general->activeReportFormats('vl');
}

if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) {
	$reportFormats['hepatitis'] = $general->activeReportFormats('hepatitis');
}

if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) {
	$reportFormats['tb'] = $general->activeReportFormats('tb');
}
$formats = json_decode((string) $facilityInfo['report_format'], true);

$geoLocationParentArray = $geolocation->fetchActiveGeolocations();
$geoLocationChildArray = $geolocation->fetchActiveGeolocations(0, $facilityInfo['facility_state_id']);
$formId = (int) $general->getGlobalConfig('vl_form');
?>
<style>
	.ms-choice,
	.ms-choice:focus {
		border: 0px solid #aaa0 !important;
	}
</style>
<link href="/assets/css/jasny-bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="/assets/css/jquery.multiselect.css" type="text/css" />
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-hospital"></em> <?php echo _translate("Edit Facility"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("Facilities"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _translate("indicates required fields"); ?> &nbsp;</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='editFacilityForm' id='editFacilityForm' autocomplete="off" enctype="multipart/form-data" action="editFacilityHelper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="facilityName" class="col-lg-4 control-label"><?php echo _translate("Facility Name"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="facilityName" name="facilityName" placeholder="<?php echo _translate('Facility Name'); ?>" title="<?php echo _translate('Please enter facility name'); ?>" value="<?= ((string) $facilityInfo['facility_name']); ?>" onblur="checkNameValidation('facility_details','facility_name',this,'<?php echo "facility_id##" . ((string) $facilityInfo['facility_id']); ?>','<?php echo _translate("The facility name that you entered already exists.Enter another name"); ?>',null)" />
										<input type="hidden" class="form-control isRequired" id="facilityId" name="facilityId" value="<?php echo base64_encode((string) $facilityInfo['facility_id']); ?>" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="facilityCode" class="col-lg-4 control-label"><?php echo _translate("Facility Code"); ?><br> <small><?php echo _translate("(National Unique Code)"); ?></small> </label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="facilityCode" name="facilityCode" placeholder="<?php echo _translate('Facility Code'); ?>" title="<?php echo _translate('Please enter facility code'); ?>" value="<?= ((string) $facilityInfo['facility_code']); ?>" onblur="checkNameValidation('facility_details','facility_code',this,'<?php echo 'facility_id##' . ((string) $facilityInfo['facility_id']); ?>','<?php echo _translate("The code that you entered already exists.Try another code"); ?>',null)" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="otherId" class="col-lg-4 control-label"><?php echo _translate("Other/External Code"); ?> </label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="otherId" name="otherId" placeholder="<?php echo _translate('Other/External Code'); ?>" value="<?= ((string) $facilityInfo['other_id']); ?>" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="facilityType" class="col-lg-4 control-label"><?php echo _translate("Facility Type"); ?> <span class="mandatory">*</span> </label>
									<div class="col-lg-7">
										<select class="form-control isRequired" id="facilityType" name="facilityType" title="<?php echo _translate('Please select facility type'); ?>" onchange="getTestType(); showSignature(this.value);">
											<option value=""> <?php echo _translate("-- Select --"); ?> </option>
											<?php
											$k = 10;
											foreach ($fResult as $type) {
											?>
												<option data-disable="<?php echo $k; ?>" value="<?= ((string) $type['facility_type_id']); ?>" <?php echo ($facilityInfo['facility_type'] == $type['facility_type_id']) ? "selected='selected'" : "" ?>><?php echo ($type['facility_type_name']); ?></option>
											<?php
												$k = $k + 10;
											}
											?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="email" class="col-lg-4 control-label"><?php echo _translate("Email(s)"); ?> <br> <small><?php echo _translate("(comma separated)"); ?></small> </label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="email" name="email" placeholder="e.g. email1@gmail.com,email2@gmail.com" value="<?= ((string) $facilityInfo['facility_emails']); ?>" />
									</div>
								</div>
							</div>
							<div class="col-md-6 allowResultsUpload" style="display:<?php echo $allowFileDiv; ?>;">
								<div class="form-group">
									<label for="allowResultUpload" class="col-lg-4 control-label"><?php echo _translate("Allow Results File Upload"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control" id="allowResultUpload" name="allowResultUpload" title="<?php echo _translate('Please select if this lab can upload test results file'); ?>">
											<option value=""> <?php echo _translate("-- Select --"); ?> </option>
											<option <?php if (isset($facilityAttributes->allow_results_file_upload) && $facilityAttributes->allow_results_file_upload === 'yes') echo 'selected="selected"'; ?> value="yes">Yes</option>
											<option <?php if (isset($facilityAttributes->allow_results_file_upload) && $facilityAttributes->allow_results_file_upload === 'no') echo 'selected="selected"'; ?> value="no">No</option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<br>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="testingPoints" class="col-lg-4 control-label"><?php echo _translate("Testing Point(s)"); ?><br> <small><?php echo _translate("(comma separated)"); ?></small> </label>
									<div class="col-lg-7">
										<?php
										$testingPointsJSON = $facilityInfo['testing_points'] ?? '[]';
										$decoded = json_decode($testingPointsJSON, true);
										$testingPoints = is_array($decoded) ? implode(", ", $decoded) : '';
										?>
										<input type="text" class="form-control" id="testingPoints" name="testingPoints" placeholder="<?php echo _translate('eg. VCT, PMTCT'); ?>" value="<?= $testingPoints ?>" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="Lab Manager" class="col-lg-4 control-label"><?php echo _translate("Lab Manager"); ?></label>
									<div class="col-lg-7">
										<select name="contactPerson" id="contactPerson" class="select2 form-control" title="<?php echo _translate('Please choose Lab Manager'); ?>" style="width: 100%;">
											<?= $general->generateSelectOptions($userInfo, $facilityInfo['contact_person'], _translate("-- Select --")); ?>
										</select>
									</div>
								</div>
							</div>

						</div>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="phoneNo" class="col-lg-4 control-label"><?php echo _translate("Phone Number"); ?></label>
									<div class="col-lg-7">
										<input type="text" class="form-control phone-number" id="phoneNo" name="phoneNo" placeholder="<?php echo _translate('Phone Number'); ?>" value="<?= ((string) $facilityInfo['facility_mobile_numbers']); ?>" onblur="checkNameValidation('facility_details','facility_mobile_numbers',this,'<?php echo "facility_id##" . $facilityInfo['facility_id']; ?>','<?php echo _translate("The mobile no that you entered already exists.Enter another mobile no."); ?>',null)" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="state" class="col-lg-4 control-label"><?php echo _translate("Province/State"); ?> <span class="mandatory">*</span> </label>
									<div class="col-lg-7">
										<?php if (sizeof($geoLocationParentArray) > 0) { ?>
											<select name="stateId" id="stateId" class="form-control isRequired" title="<?php echo _translate('Please choose province/state'); ?>">
												<?= $general->generateSelectOptions($geoLocationParentArray, $facilityInfo['facility_state_id'], _translate("-- Select --")); ?>
												<option value="other">Other</option>
											</select>
											<input type="text" class="form-control" name="provinceNew" id="provinceNew" placeholder="<?php echo _translate('Enter Province/State'); ?>" title="<?php echo _translate('Please enter province/state'); ?>" style="margin-top:4px;display:none;" />
											<input type="hidden" name="state" id="state" value="<?= ((string) $facilityInfo['facility_state']); ?>" />
										<?php }
										if ((!isset($facilityInfo['facility_state_id']) || $facilityInfo['facility_state_id'] == "") && (isset($facilityInfo['facility_state']) || $facilityInfo['facility_state'] != "")) { ?>
											<input type="text" value="<?= ((string) $facilityInfo['facility_state']); ?>" class="form-control isRequired" name="oldState" id="oldState" placeholder="<?php echo _translate('Enter Province/State'); ?>" title="<?php echo _translate('Please enter province/state'); ?>" />
										<?php } ?>
									</div>
								</div>
							</div>


						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="district" class="col-lg-4 control-label"><?php echo _translate("District/County"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select name="districtId" id="districtId" class="form-control isRequired" title="<?php echo _translate('Please choose District/County'); ?>">
											<?= $general->generateSelectOptions($geoLocationChildArray, $facilityInfo['facility_district_id'], _translate("-- Select --")); ?>
											<option value="other">Other</option>
										</select>
										<input type="text" class="form-control" name="districtNew" id="districtNew" placeholder="<?php echo _translate('Enter District/County'); ?>" title="<?php echo _translate('Please enter District/County'); ?>" style="margin-top:4px;display:none;" />
										<input type="hidden" id="district" name="district" value="<?= ((string) $facilityInfo['facility_district']); ?>" />
										<?php if ((!isset($facilityInfo['facility_district_id']) || $facilityInfo['facility_district_id'] == "") && (isset($facilityInfo['facility_district']) || $facilityInfo['facility_district'] != "")) { ?>
											<input type="text" value="<?= ((string) $facilityInfo['facility_district']); ?>" class="form-control isRequired" name="oldDistrict" id="oldDistrict" placeholder="<?php echo _translate('Enter District/County'); ?>" title="<?php echo _translate('Please enter district/county'); ?>" />
										<?php } ?>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="hubName" class="col-lg-4 control-label"><?php echo _translate("Linked Hub Name (if applicable)"); ?></label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="hubName" name="hubName" placeholder="<?php echo _translate('Hub Name'); ?>" title="<?php echo _translate('Please enter hub name'); ?>" value="<?= ((string) $facilityInfo['facility_hub_name']); ?>" />
									</div>
								</div>
							</div>


						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="address" class="col-lg-4 control-label"><?php echo _translate("Address"); ?></label>
									<div class="col-lg-7">
										<textarea class="form-control" name="address" id="address" placeholder="<?php echo _translate('Address'); ?>"><?= ((string) $facilityInfo['address']); ?></textarea>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="country" class="col-lg-4 control-label"><?php echo _translate("Country"); ?></label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="country" name="country" placeholder="<?php echo _translate('Country'); ?>" value="<?= ((string) $facilityInfo['country']); ?>" />
									</div>
								</div>
							</div>

						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="latitude" class="col-lg-4 control-label"><?php echo _translate("Latitude"); ?></label>
									<div class="col-lg-7">
										<input type="text" class="form-control forceNumeric" id="latitude" name="latitude" placeholder="<?php echo _translate('Latitude'); ?>" title="<?php echo _translate('Please enter latitude'); ?>" value="<?= ((string) $facilityInfo['latitude']); ?>" />
									</div>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="longitude" class="col-lg-4 control-label"><?php echo _translate("Longitude"); ?></label>
									<div class="col-lg-7">
										<input type="text" class="form-control forceNumeric" id="longitude" name="longitude" placeholder="<?php echo _translate('Longitude'); ?>" title="<?php echo _translate('Please enter longitude'); ?>" value="<?= ((string) $facilityInfo['longitude']); ?>" />
									</div>
								</div>
							</div>

						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="status" class="col-lg-4 control-label"><?php echo _translate("Status"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='status' id='status' title="<?php echo _translate('Please select the status'); ?>">
											<option value=""> <?php echo _translate("-- Select --"); ?> </option>
											<option value="active" <?php echo ($facilityInfo['status'] == 'active') ? "selected='selected'" : "" ?>><?php echo _translate("Active"); ?></option>
											<option value="inactive" <?php echo ($facilityInfo['status'] == 'inactive') ? "selected='selected'" : "" ?>><?php echo _translate("Inactive"); ?></option>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="testType" class="col-lg-4 control-label test-type"><?php echo _translate("Test Type"); ?></label>
									<div class="col-lg-7">
										<select id="testType" name="testType[]" title="<?php echo _translate('Choose at least one test type'); ?>" onchange="getTestType();" multiple>
											<option value=""><?php echo _translate("Select Test Types"); ?></option>

											<?php if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) { ?>
												<option value='vl' <?php echo (preg_match("/vl/i", (string) $facilityInfo['test_type'])) ? "selected='selected'" : '';  ?>><?php echo _translate("Viral Load"); ?></option>
											<?php }
											if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) { ?>
												<option value='eid' <?php echo (preg_match("/eid/i", (string) $facilityInfo['test_type'])) ? "selected='selected'" : '';  ?>><?php echo _translate("Early Infant Diagnosis"); ?></option>
											<?php }
											if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) { ?>
												<option value='covid19' <?php echo (preg_match("/covid19/i", (string) $facilityInfo['test_type'])) ? "selected='selected'" : '';  ?>><?php echo _translate("Covid-19"); ?></option>
											<?php }
											if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) { ?>
												<option value='hepatitis' <?php echo (preg_match("/hepatitis/i", (string) $facilityInfo['test_type'])) ? "selected='selected'" : '';  ?>><?php echo _translate("Hepatitis"); ?></option>
											<?php }
											if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) { ?>
												<option value='tb' <?php echo (preg_match("/tb/i", (string) $facilityInfo['test_type'])) ? "selected='selected'" : '';  ?>><?php echo _translate("TB"); ?></option>
											<?php }
											if (isset(SYSTEM_CONFIG['modules']['cd4']) && SYSTEM_CONFIG['modules']['cd4'] === true) { ?>
												<option value='cd4' <?php echo (preg_match("/cd4/i", (string) $facilityInfo['test_type'])) ? "selected='selected'" : '';  ?>><?php echo _translate("CD4"); ?></option>
											<?php }
											if (isset(SYSTEM_CONFIG['modules']['generic-tests']) && SYSTEM_CONFIG['modules']['generic-tests'] === true) { ?>
												<option value='generic-tests' <?php echo (preg_match("/generic-tests/i", (string) $facilityInfo['test_type'])) ? "selected='selected'" : '';  ?>><?php echo _translate("Other Lab Tests"); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6 availablePlatforms" style="display:none;">
								<div class="form-group">
									<label for="availablePlatforms" class="col-lg-4 control-label"><?php echo _translate("Available Platforms"); ?></label>
									<div class="col-lg-7">
										<select id="availablePlatforms" name="availablePlatforms[]" title="<?php echo _translate('Choose one Available Platforms'); ?>" multiple>
											<option value="microscopy" <?php echo in_array('microscopy', $availPlatforms) ? "selected='selected'" :  ''; ?>><?php echo _translate("Microscopy"); ?></option>
											<option value="xpert" <?php echo in_array('xpert', $availPlatforms) ? "selected='selected'" : '';  ?>><?php echo _translate("Xpert"); ?></option>
											<option value="lam" <?php echo in_array('lam', $availPlatforms) ? "selected='selected'" : '';  ?>><?php echo _translate("Lam"); ?></option>
										</select>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="row labDiv" style="display:<?php echo $labDiv; ?>;">
						<?php if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) {
							$count = sizeof($reportFormats['vl']); ?>
							<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
								<div class="form-group">
									<label for="reportFormat" class="col-lg-4 control-label"><?php echo _translate("Report Format For VL"); ?></label>
									<div class="col-lg-7">
										<select class="form-control" name='reportFormat[vl]' id='reportFormat' title="<?php echo _translate('Please select the status'); ?>" onchange="checkIfExist(this);">
											<?php if ($count > 1) { ?>
												<option value=""><?php echo _translate("-- Select --"); ?></option>
											<?php } ?>
											<?php foreach ($reportFormats['vl'] as $key => $value) {
												foreach ($value as $k => $v) { ?>
													<option value="<?php echo $k; ?>" <?php echo (!empty($formats) && $formats['vl'] == $k) ? "selected='selected'" : ""; ?>><?php echo ($v); ?></option>
											<?php
												}
											} ?>
										</select>
									</div>
								</div>
							</div>
						<?php }
						if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) {
							$count = sizeof($reportFormats['eid']); ?>
							<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
								<div class="form-group">
									<label for="reportFormat" class="col-lg-4 control-label"><?php echo _translate("Report Format For EID"); ?></label>
									<div class="col-lg-7">
										<select class="form-control" name='reportFormat[eid]' id='reportFormat' title="<?php echo _translate('Please select the status'); ?>" onchange="checkIfExist(this);">
											<?php if (($count > 1)) { ?>
												<option value=""><?php echo _translate("-- Select --"); ?></option>
											<?php } ?>
											<?php foreach ($reportFormats['eid'] as $key => $value) { ?>
												<?php foreach ($value as $k => $v) { ?>
													<option value="<?php echo $k; ?>" <?php echo (!empty($formats) && $formats['eid'] == $k) ? "selected='selected'" : ""; ?>><?php echo ($v); ?></option>
											<?php }
											} ?>
										</select>
									</div>
								</div>
							</div>
						<?php }
						if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) {
							$count = sizeof($reportFormats['covid19']); ?>
							<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
								<div class="form-group">
									<label for="reportFormat" class="col-lg-4 control-label"><?php echo _translate("Report Format For Covid-19"); ?></label>
									<div class="col-lg-7">
										<select class="form-control" name='reportFormat[covid19]' id='reportFormat' title="<?php echo _translate('Please select the status'); ?>" onchange="checkIfExist(this);">
											<?php if (($count > 1)) { ?>
												<option value=""><?php echo _translate("-- Select --"); ?></option>
											<?php } ?>
											<?php foreach ($reportFormats['covid19'] as $key => $value) {
												foreach ($value as $k => $v) { ?>
													<option value="<?php echo $k; ?>" <?php echo (!empty($formats) && $formats['covid19'] == $k) ? "selected='selected'" : ""; ?>><?php echo ($v); ?></option>
											<?php }
											} ?>
										</select>
									</div>
								</div>
							</div>
						<?php }
						if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) {
							$count = sizeof($reportFormats['hepatitis']); ?>
							<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
								<div class="form-group">
									<label for="reportFormat" class="col-lg-4 control-label"><?php echo _translate("Report Format For Hepatitis"); ?></label>
									<div class="col-lg-7">
										<select class="form-control" name='reportFormat[hepatitis]' id='reportFormat' title="<?php echo _translate('Please select the status'); ?>" onchange="checkIfExist(this);">
											<?php if (($count > 1)) { ?>
												<option value=""><?php echo _translate("-- Select --"); ?></option>
											<?php } ?>
											<?php foreach ($reportFormats['hepatitis'] as $key => $value) { ?>
												<option value="<?php echo $key; ?>" <?php echo (!empty($formats) && $formats['hepatitis'] == $key) ? "selected='selected'" : ""; ?>><?php echo ($value); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
						<?php }
						if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) {
							$count = sizeof($reportFormats['tb']); ?>
							<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
								<div class="form-group">
									<label for="reportFormat" class="col-lg-4 control-label"><?php echo _translate("Report Format For TB"); ?></label>
									<div class="col-lg-7">
										<select class="form-control" name='reportFormat[tb]' id='reportFormat' title="<?php echo _translate('Please select the status'); ?>" onchange="checkIfExist(this);">
											<?php if (($count > 1)) { ?>
												<option value=""><?php echo _translate("-- Select --"); ?></option>
											<?php } ?>
											<?php foreach ($reportFormats['tb'] as $key => $value) { ?>
												<option value="<?php echo $key; ?>" <?php echo (!empty($formats) && $formats['tb'] == $key) ? "selected='selected'" : ""; ?>><?php echo ($value); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
						<?php } ?>
					</div>
					<div class="row logoImage">
						<div class="col-md-6">
							<div class="form-group">
								<label for="" class="col-lg-4 control-label"><?php echo _translate("Logo Image"); ?> </label>
								<div class="col-lg-8">
									<div class="fileinput fileinput-new labLogo" data-provides="fileinput">
										<div class="fileinput-preview thumbnail" data-trigger="fileinput" style="width:200px; height:150px;">
											<?php

											if (isset($facilityInfo['facility_logo']) && trim((string) $facilityInfo['facility_logo']) != '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $facilityInfo['facility_id'] . DIRECTORY_SEPARATOR . $facilityInfo['facility_logo'])) {
											?>
												<img src="/uploads/facility-logo/<?= ((string) $facilityInfo['facility_id']); ?>/<?= ((string) $facilityInfo['facility_logo']); ?>" alt="Logo image">
											<?php } else { ?>

											<?php } ?>
										</div>
										<div>
											<span class="btn btn-default btn-file"><span class="fileinput-new"><?php echo _translate("Select image"); ?></span><span class="fileinput-exists"><?php echo _translate("Change"); ?></span>
												<input type="file" id="labLogo" name="labLogo" title="<?php echo _translate('Please select logo image'); ?>" onchange="getNewLabImage('<?= ((string) $facilityInfo['facility_logo']); ?>');">
											</span>
											<?php
											if (isset($facilityInfo['facility_logo']) && trim((string) $facilityInfo['facility_logo']) != '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $facilityInfo['facility_id'] . DIRECTORY_SEPARATOR . $facilityInfo['facility_logo'])) {
											?>
												<a id="clearLabImage" href="javascript:void(0);" class="btn btn-default" data-dismiss="fileupload" onclick="clearLabImage('<?= ((string) $facilityInfo['facility_logo']); ?>')"><?php echo _translate("Clear"); ?></a>
											<?php } ?>
											<a href="#" class="btn btn-default fileinput-exists" data-dismiss="fileinput"><?php echo _translate("Remove"); ?></a>
										</div>
									</div>
									<div class="box-body">
										<?php echo _translate("Please make sure logo image size of"); ?>: <code><?php echo _translate("80x80"); ?></code>
									</div>
								</div>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="" class="col-lg-4 control-label"><?php echo _translate("Header Text"); ?></label>
								<div class="col-lg-7">
									<input type="text" class="form-control " id="headerText" name="headerText" placeholder="<?php echo _translate('Header Text'); ?>" title="<?php echo _translate('Please enter header text'); ?>" value="<?= ((string) $facilityInfo['header_text']); ?>" />
								</div>
							</div>
						</div>
					</div>
					<div class="row reportTemplate" style="display:<?php echo $labDiv; ?>;">
						<div class="col-md-6">
							<div class="form-group">
								<label for="reportTemplate" class="col-lg-4 control-label">
									<?php echo _translate("Upload Report Template"); ?>
								</label>
								<div class="col-lg-7">
									<?php
									$showreportTemplate = "style='display:block'";
									if (isset($facilityAttributes) && isset($facilityAttributes->report_template) && $facilityAttributes->report_template != '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . $facilityInfo['facility_id'] . DIRECTORY_SEPARATOR . "report-template" . DIRECTORY_SEPARATOR . $facilityAttributes->report_template)) {
										$lmSign = "/uploads/labs/" . $facilityInfo['facility_id'] . "/report-template/" . $facilityAttributes->report_template;
										$showreportTemplate = "style='display:none'";
									?>
										<span id="reportTemplateSpan" class="form-control">
											<a href="javascript:void(0);" onclick="showreportTemplateFile();">
												<span class="alert-danger" style="padding: 0.4em; border-radius: 50%; float: right; height: 1.6em; width: 1.6em; display: flex; align-items: center; justify-content: center; text-align: center; line-height: 1;">X</span>
											</a>
											<a href=<?php echo $lmSign; ?> target="_blank"> <?php echo $facilityAttributes->report_template; ?> </a>
										</span>
									<?php }
									?>
									<input <?php echo $showreportTemplate; ?> class="showreportTemplateFile form-control" type="file" name="reportTemplate" id="reportTemplate" placeholder="' . _translate('Upload Report Template') . '" accept=".pdf" title="' . _translate('Please Upload Report Template') . '">
								</div>
							</div>
						</div>
					</div>
					<?php if ($formId == COUNTRY\CAMEROON) { ?>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="displayPagenoInFooter" class="col-lg-4 control-label">
										<?php echo _translate("Display Page Number in Footer"); ?>
									</label>
									<div class="col-lg-7">
										<select class="form-control" name='displayPagenoInFooter' id='displayPagenoInFooter' title="<?php echo _translate('Display Page Number in Footer'); ?>">
											<option value=""> <?php echo _translate("-- Select --"); ?> </option>
											<option value="yes" <?php echo (isset($facilityAttributes->display_page_number_in_footer) && $facilityAttributes->display_page_number_in_footer === 'yes') ? "selected='selected'" : "" ?>><?php echo _translate("Yes"); ?></option>
											<option value="no" <?php echo (isset($facilityAttributes->display_page_number_in_footer) && $facilityAttributes->display_page_number_in_footer === 'no') ? "selected='selected'" : "" ?>><?php echo _translate("No"); ?></option>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="displaySignatureTable" class="col-lg-4 control-label">
										<?php echo _translate("Display Signature Table"); ?>
									</label>
									<div class="col-lg-7">
										<select class="form-control" name='displaySignatureTable' id='displaySignatureTable' title="<?php echo _translate('Display Signature Table'); ?>">
											<option value=""> <?php echo _translate("-- Select --"); ?> </option>
											<option value="yes" <?php echo (isset($facilityAttributes->display_signature_table) && $facilityAttributes->display_signature_table === 'yes') ? "selected='selected'" : "" ?>><?php echo _translate("Yes"); ?></option>
											<option value="no" <?php echo (isset($facilityAttributes->display_signature_table) && $facilityAttributes->display_signature_table === 'no') ? "selected='selected'" : "" ?>><?php echo _translate("No"); ?></option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="reportTopMargin" class="col-lg-4 control-label">
										<?php echo _translate("Report Top Margin"); ?>
									</label>
									<div class="col-lg-7">
										<input type="number" value="<?php echo (isset($facilityAttributes->report_top_margin)) ? $facilityAttributes->report_top_margin : '17' ?>" class="form-control" name="reportTopMargin" id="reportTopMargin" placeholder="<?php echo _translate('Report Top Margin'); ?>" title="<?php echo _translate('Please enter the report top margin'); ?>">
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="bottomTextLocation" class="col-lg-4 control-label">
										<?php echo _translate("Bottom Text Location"); ?>
									</label>
									<div class="col-lg-7">
										<select class="form-control" name="bottomTextLocation" id="bottomTextLocation" title="<?php echo _translate('Please enter the Bottom Text Location'); ?>">
											<option value="aboveFooter" <?php echo (isset($facilityAttributes->bottom_text_location) && $facilityAttributes->bottom_text_location === 'aboveFooter') ? "selected='selected'" : "" ?>><?php echo _translate("Above Footer"); ?></option>
											<option value="belowPlatformName" <?php echo (isset($facilityAttributes->bottom_text_location) && $facilityAttributes->bottom_text_location === 'belowPlatformName') ? "selected='selected'" : "" ?>><?php echo _translate("Below Platform Name"); ?></option>
										</select>
									</div>
								</div>
							</div>
						</div>
					<?php } ?>
					<div class="row" id="sampleType"></div>
					<div class="row-item labDiv" style="display:<?php echo $labDiv; ?>;">
						<hr>
						<h4 class="col-lg-12"><?= _translate("The following information is sometimes used to show names, designations and signatures in some reports."); ?></h4>
						<table aria-describedby="table" class="col-lg-12 table table-bordered">
							<thead>
								<tr>
									<th><?php echo _translate("Name of Signatory"); ?></th>
									<th><?php echo _translate("Designation"); ?></th>
									<th><?php echo _translate("Upload Signature (jpg, png)"); ?></th>
									<th><?php echo _translate("Test Types"); ?></th>
									<th><?php echo _translate("Display Order"); ?></th>
									<th><?php echo _translate("Current Status"); ?></th>
									<th><?php echo _translate("Action"); ?></th>
								</tr>
							</thead>
							<tbody id="signDetails">
								<?php if (!empty($signResults)) {
									foreach ($signResults as $key => $row) { ?>
										<tr>
											<td style="vertical-align:middle;width:15%;"><input type="hidden" name="signId[]" id="signId<?php echo ($key + 1); ?>" value="<?php echo $row['signatory_id'] ?>" /><input value="<?php echo $row['name_of_signatory'] ?>" type="text" class="form-control" name="signName[]" id="signName<?php echo ($key + 1); ?>" placeholder="<?php echo _translate('Name'); ?>" title="<?php echo _translate('Please enter the name'); ?>"></td>
											<td style="vertical-align:middle;width:10%;"><input value="<?php echo $row['designation'] ?>" type="text" class="form-control" name="designation[]" id="designation<?php echo ($key + 1); ?>" placeholder="<?php echo _translate('Designation'); ?>" title="<?php echo _translate('Please enter the Designation'); ?>"></td>
											<td style="vertical-align:middle;width:10%;">
												<?php $lmSign = "/uploads/labs/" . $row['lab_id'] . "/signatures/" . $row['signature'];
												$show = "style='display:block'";
												if (isset($row['signature']) && $row['signature'] != "") {
													$show = "style='display:none'";
												?>
													<span id="spanClass<?php echo ($key + 1); ?>"><a href="javascript:void(0);" onclick="showFile(<?= $key + 1; ?>);"><span class="alert-danger" style="padding: 0.4em; border-radius: 50%; float: right; height: 1.6em; width: 1.6em; display: flex; align-items: center; justify-content: center; text-align: center; line-height: 1;">X</span></a><img alt="Facility" src="<?php echo $lmSign; ?>" style="width: 100px;" /></span>
												<?php }
												?>
												<input <?php echo $show; ?> class="showFile<?php echo ($key + 1); ?>" type="file" name="signature[]" id="signature<?= $key + 1; ?>" placeholder="<?php echo _translate('Signature'); ?>" title="<?php echo _translate('Please enter the Signature'); ?>">
											</td>
											<td style="vertical-align:middle;width:10%;">
												<select class="select2 testSignType" id="testSignType<?php echo ($key + 1); ?>" name="testSignType[<?= $key + 1; ?>][]" title="<?php echo _translate('Choose at least one test type'); ?>" multiple>
													<option value="vl" <?php echo (isset($row['test_types']) && in_array("vl", explode(",", (string) $row['test_types']))) ? 'selected="selected"' : ''; ?>><?php echo _translate("Viral Load"); ?></option>
													<option value="eid" <?php echo (isset($row['test_types']) && in_array("eid", explode(",", (string) $row['test_types']))) ? 'selected="selected"' : ''; ?>><?php echo _translate("Early Infant Diagnosis"); ?></option>
													<option value="covid19" <?php echo (isset($row['test_types']) && in_array("covid19", explode(",", (string) $row['test_types']))) ? 'selected="selected"' : ''; ?>><?php echo _translate("Covid-19"); ?></option>
													<option value='hepatitis' <?php echo (isset($row['test_types']) && in_array("hepatitis", explode(",", (string) $row['test_types']))) ? 'selected="selected"' : ''; ?>><?php echo _translate("Hepatitis"); ?></option>
													<option value='tb' <?php echo (isset($row['test_types']) && in_array("tb", explode(",", (string) $row['test_types']))) ? 'selected="selected"' : ''; ?>><?php echo _translate("TB"); ?></option>
													<option value='generic-tests' <?php echo (isset($row['test_types']) && in_array("generic-tests", explode(",", (string) $row['test_types']))) ? 'selected="selected"' : ''; ?>><?php echo _translate("Other Lab Tests"); ?></option>
													<option value='cd4' <?php echo (isset($row['test_types']) && in_array("cd4", explode(",", (string) $row['test_types']))) ? 'selected="selected"' : ''; ?>><?php echo _translate("CD4"); ?></option>
												</select>
											</td>
											<td style="vertical-align:middle;width:5%;"><input value="<?php echo $row['display_order'] ?>" type="number" class="form-control" name="sortOrder[]" id="sortOrder<?= $key + 1; ?>" placeholder="<?php echo _translate('Display Order'); ?>" title="<?php echo _translate('Please enter the Display Order'); ?>"></td>
											<td style="vertical-align:middle;width:7%;">
												<select class="form-control" id="signStatus<?= $key + 1; ?>" name="signStatus[]" title="<?php echo _translate('Please select the status'); ?>">
													<option value="active" <?php echo (isset($row['test_types']) && $row['test_types'] == 'active') ? 'selected="selected"' : ''; ?>><?php echo _translate("Active"); ?></option>
													<option value="inactive" <?php echo (isset($row['test_types']) && $row['test_types'] == 'inactive') ? 'selected="selected"' : ''; ?>><?php echo _translate("Inactive"); ?></option>
												</select>
											</td>
											<td style="vertical-align:middle;text-align: center;width:7%;">
												<a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addNewRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
												<a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeNewRow(this.parentNode.parentNode);deletedRow(<?php echo $row['signatory_id'] ?>);"><em class="fa-solid fa-minus"></em></a>
											</td>
										</tr>
									<?php }
								} else { ?>
									<tr>
										<td style="width:14%;"><input type="text" class="form-control" name="signName[]" id="signName1" placeholder="<?php echo _translate('Name'); ?>" title="<?php echo _translate('Please enter the name'); ?>"></td>
										<td style="width:14%;"><input type="text" class="form-control" name="designation[]" id="designation1" placeholder="<?php echo _translate('Designation'); ?>" title="<?php echo _translate('Please enter the Designation'); ?>"></td>
										<td style="width:10%;"><input type="file" name="signature[]" id="signature1" placeholder="<?php echo _translate('Signature'); ?>" title="<?php echo _translate('Please enter the Signature'); ?>"></td>
										<td style="width:14%;">
											<select class="select2 testSignType" id="testSignType1" name="testSignType[1][]" title="<?php echo _translate('Choose at least one test type'); ?>" multiple>
												<option value="vl"><?php echo _translate("Viral Load"); ?></option>
												<option value="eid"><?php echo _translate("Early Infant Diagnosis"); ?></option>
												<option value="covid19"><?php echo _translate("Covid-19"); ?></option>
												<option value='hepatitis'><?php echo _translate("Hepatitis"); ?></option>
												<option value='tb'><?php echo _translate("TB"); ?></option>
												<option value='generic-tests'><?php echo _translate("Other Lab Tests"); ?></option>
												<option value='cd4'><?php echo _translate("CD4"); ?></option>
											</select>
										</td>
										<td style="width:14%;"><input type="number" class="form-control" name="sortOrder[]" id="sortOrder1" placeholder="<?php echo _translate('Display Order'); ?>" title="<?php echo _translate('Please enter the Display Order'); ?>"></td>
										<td style="width:14%;">
											<select class="form-control" id="signStatus1" name="signStatus[]" title="<?php echo _translate('Please select the status'); ?>">
												<option value="active"><?php echo _translate("Active"); ?></option>
												<option value="inactive"><?php echo _translate("Inactive"); ?></option>
											</select>
										</td>
										<td style="vertical-align:middle;text-align: center;width:10%;">
											<a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addNewRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
											<a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeNewRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
										</td>
									</tr>
								<?php } ?>
							</tbody>
						</table>
					</div>

					<div class="row" id="userDetails"></div>
					<div class="row" id="testDetails" style="display:none;">
						<?php echo $div; ?>
					</div>

					<div class="row testTypeFileSection" <?php echo (isset($facilityReportFormat) && !empty($facilityReportFormat)) ? "" : 'style="display:none;"'; ?>>
						<div class="col-md-12">
							<hr>
							<h4><?php echo _translate("Test Type Templates"); ?></h4>
							<table class="table table-bordered" id="testTypeFileTable">
								<thead>
									<tr>
										<th style="width:30%;vertical-align:middle;text-align: center;"><?php echo _translate("Test Type"); ?></th>
										<th style="width:50%;vertical-align:middle;text-align: center;"><?php echo _translate("Upload File (PDF)"); ?></th>
										<th style="width:10%;vertical-align:middle;text-align: center;"><?php echo _translate("Header Margin"); ?></th>
										<th style="width:10%;vertical-align:middle;text-align: center;"><?php echo _translate("Action"); ?></th>
									</tr>
								</thead>
								<tbody id="testTypeFileDetails">
									<?php if (isset($facilityReportFormat) && !empty($facilityReportFormat)) {
										$n = 1;
										foreach ($facilityReportFormat as $test => $file) {
											$filePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . "report-template" . DIRECTORY_SEPARATOR . $test . DIRECTORY_SEPARATOR . $facilityInfo['facility_id'] . DIRECTORY_SEPARATOR . $file->file;  ?>
											<tr>
												<td>
													<select class="form-control testTypeFileSelect" name="testTypeFile[<?php echo ($n - 1); ?>]" id="testTypeFile<?php echo $n; ?>">
														<option value=""><?php echo _translate("Select Test Type"); ?></option>
														<option value="default" <?php echo ($test == 'default') ? 'selected="selected"' : ''; ?>><?php echo _translate("Default format"); ?></option>
														<?php if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) { ?>
															<option value="vl" <?php echo ($test == 'vl') ? 'selected="selected"' : ''; ?>><?php echo _translate("Viral Load"); ?></option>
														<?php }
														if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) { ?>
															<option value="eid" <?php echo ($test == 'eid') ? 'selected="selected"' : ''; ?>><?php echo _translate("Early Infant Diagnosis"); ?></option>
														<?php }
														if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) { ?>
															<option value="covid19" <?php echo ($test == 'covid19') ? 'selected="selected"' : ''; ?>><?php echo _translate("Covid-19"); ?></option>
														<?php }
														if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) { ?>
															<option value='hepatitis' <?php echo ($test == 'hepatitis') ? 'selected="selected"' : ''; ?>><?php echo _translate("Hepatitis"); ?></option>
														<?php }
														if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) { ?>
															<option value='tb' <?php echo ($test == 'tb') ? 'selected="selected"' : ''; ?>><?php echo _translate("TB"); ?></option>
														<?php }
														if (isset(SYSTEM_CONFIG['modules']['cd4']) && SYSTEM_CONFIG['modules']['cd4'] === true) { ?>
															<option value='cd4' <?php echo ($test == 'cd4') ? 'selected="selected"' : ''; ?>><?php echo _translate("CD4"); ?></option>
														<?php }
														if (isset(SYSTEM_CONFIG['modules']['generic-tests']) && SYSTEM_CONFIG['modules']['generic-tests'] === true) { ?>
															<option value='generic-tests' <?php echo ($test == 'generic-tests') ? 'selected="selected"' : ''; ?>><?php echo _translate("Other Lab Tests"); ?></option>
														<?php } ?>
													</select>
												</td>
												<td style="vertical-align:middle;text-align: center;margin-left:20px;">
													<?php if (isset($file) && !empty($file) && (file_exists($filePath) && !is_dir($filePath))) { ?>
														<div class="m-5">
															<a href="/uploads/labs/report-template/<?php echo $test; ?>/<?php echo $facilityInfo['facility_id']; ?>/<?php echo $file->file; ?>" class="oldFile<?php echo $n; ?> btn btn-sm btn-primary" title="View / Expend Current File" target="_blank" style="margin-left: -35px; "><i class="fa fa-eye"></i> View</a>
															<a href="javascript:void(0);" class="btn btn-warning oldFile<?php echo $n; ?>" title="Replace Report Layout" onclick="removeReport(<?php echo $n; ?>);">Replace</a>
															<a href="javascript:void(0);" class="btn btn-danger oldFile<?php echo $n; ?>" title="Delete Report Template" onclick="deleteReport(<?php echo $n; ?>);removeReport(<?php echo $n; ?>);"><i class="icon-trash"></i> Remove</a>
															<input type="hidden" name="deleteTemplate[<?php echo ($n - 1); ?>]" id="deleteTemplate<?php echo $n; ?>" />
															<input type="hidden" value="<?php echo $file->file; ?>" name="oldTemplate[<?php echo ($n - 1); ?>]" id="oldTemplate<?php echo $n; ?>" />
														</div>
													<?php } ?>
													<input <?php echo (isset($file->file) && !empty($file->file) && file_exists($filePath)) ? 'style="display:none;"' : ''; ?> type="file" class="form-control newFile<?php echo $n; ?>" name="reportTemplate[]" id="reportTemplate<?php echo $n; ?>" accept=".pdf" title="<?php echo _translate('Please upload PDF file'); ?>">
												</td>
												<td>
													<input value="<?php echo $file->mtop; ?>" type="text" class="form-control" name="headerMargin[<?php echo ($n - 1); ?>]" id="headerMargin<?php echo $n; ?>" placeholder="<?php echo _translate('Top margin'); ?>" title="<?php echo _translate('Please enter the header margin'); ?>">
												</td>
												<td style="vertical-align:middle;text-align: center;">
													<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="addTestTypeFileRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
													<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeTestTypeFileRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
												</td>
											</tr>
										<?php $n += 1;
										}
									} else { ?>
										<tr>
											<td style="text-align: center;">
												<input type="hidden" value="default" class="form-control testTypeFileSelect" name="testTypeFile[]" id="testTypeFile1">
												<label class="label-control text-center"><?php echo _translate("Default"); ?></label>
											</td>
											<td>
												<input type="file" class="form-control" name="reportTemplate[]" id="reportTemplate1" accept=".pdf" title="<?php echo _translate('Please upload PDF file'); ?>">
											</td>
											<td>
												<input type="text" class="form-control" name="headerMargin[]" id="headerMargin1" placeholder="<?php echo _translate('Top margin'); ?>" title="<?php echo _translate('Please enter the header margin'); ?>">
											</td>
											<td style="vertical-align:middle;text-align: center;">
												<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="addTestTypeFileRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
											</td>
										</tr>
									<?php } ?>
								</tbody>
							</table>
						</div>
					</div>
			</div>
			<!-- /.box-body -->
			<div class="box-footer">
				<input type="hidden" name="selectedUser" id="selectedUser" />
				<input type="hidden" name="removedLabLogoImage" id="removedLabLogoImage" />
				<input type="hidden" name="deletedRow" id="deletedRow" />
				<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _translate("Submit"); ?></a>
				<a href="facilities.php" class="btn btn-default"> <?php echo _translate("Cancel"); ?></a>
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
<script type="text/javascript" src="/assets/js/jquery.multiselect.js"></script>

<script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script>

<script type="text/javascript">
	var deletedRowVar = [];
	$(document).ready(function() {

		getTestType();

		$('#facilityType').trigger('change');

		$(".testSignType").select2({
			placeholder: '<?php echo _translate("Select Test Type", true); ?>',
			width: '150px'
		});

		$("#testType").selectize({
			plugins: ["restore_on_backspace", "remove_button", "clear_button"],
		});

		$("#contactPerson").select2({
			placeholder: '<?php echo _translate("Select Lab Manager", true); ?>',
			width: '100%'
		});

		$("#stateId").select2({
			placeholder: '<?php echo _translate("Select Province", true); ?>',
			width: '100%'
		});

		$("#districtId").select2({
			placeholder: '<?php echo _translate("Select District", true); ?>',
			width: '100%'
		});

		$("#availablePlatforms").multipleSelect({
			placeholder: '<?php echo _translate("Select Available Platforms", true); ?>',
			width: '100%'
		});

		$("#stateId").change(function() {
			if ($(this).val() == 'other') {
				$('#provinceNew').show();
			} else {
				$('#provinceNew').hide();
				$('#state').val($("#stateId option:selected").text());
			}
			$.blockUI();
			var pName = $(this).val();
			if ($.trim(pName) != '') {
				$.post("/includes/siteInformationDropdownOptions.php", {
						pName: pName,
						dName: '<?php echo (isset($facilityInfo['facility_district_id']) && $facilityInfo['facility_district_id'] != "") ? trim((string) $facilityInfo['facility_district_id']) : trim((string) $facilityInfo['facility_district']); ?>'
					},
					function(data) {
						if (data != "") {
							details = data.split("###");
							$("#districtId").html(details[1]);
							$("#districtId").append('<option value="other"><?php echo _translate("Other"); ?></option>');
						}
					});
			}
			$.unblockUI();
		});

		$("#districtId").change(function() {
			if ($(this).val() == 'other') {
				$('#districtNew').show();
			} else {
				$('#districtNew').hide();
				$('#district').val($("#districtId option:selected").text());
			}
		});
		<?php if (isset($fType) && $fType == 2) { ?>
			showSignature(2);
		<?php } ?>

	});
	var selVal = [];
	var first = 0;
	$('#search_to option').each(function(i, selected) {
		selVal[i] = $(selected).val();
	});
	$("#selectedUser").val(selVal);

	function validateNow() {
		$('.selectize-control, .selectize-dropdown').removeClass('isRequired');
		var selVal = [];
		$('#search_to option').each(function(i, selected) {
			selVal[i] = $(selected).val();
		});
		$("#selectedUser").val(selVal);
		$('#state').val($("#stateId option:selected").text());
		$('#district').val($("#districtId option:selected").text());
		flag = deforayValidator.init({
			formId: 'editFacilityForm'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('editFacilityForm').submit();
		}
	}

	function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
		var removeDots = obj.value.replace(/\./g, "");
		removeDots = removeDots.replace(/\,/g, "");
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
					document.getElementById(obj.id).value = "";
				}
			});
	}

	$('#state').on('change', function() {
		if (this.value == 'other') {
			$('#provinceNew').show();
			$('#provinceNew').addClass('isRequired');
			$('#provinceNew').focus();
		} else {
			$('#provinceNew').hide();
			$('#provinceNew').removeClass('isRequired');
			$('#provinceNew').val('');
		}
	});

	$('#facilityType').on('change', function() {
		if (this.value == '2') {
			$("#allowResultUpload option[value=yes]").attr('selected', 'selected');
			$("#allowResultUpload option[value='']").removeAttr('selected', 'selected');
			$('.allowResultsUpload').show();
			$('#allowResultUpload').addClass('isRequired');
			$('#allowResultUpload').focus();

			$("#testType").addClass('isRequired');
			$(".test-type").append('<span class="mandatory">*</span>');
		} else {
			$("#allowResultUpload option[value=yes]").removeAttr('selected', 'selected');
			$("#allowResultUpload option[value='']").attr('selected', 'selected');
			$('.allowResultsUpload').hide();
			$('#allowResultUpload').removeClass('isRequired');
			$('#allowResultUpload').val('');

			$("#testType").removeClass('isRequired');
			$(".test-type").find('span').remove();
		}
	});

	<?php
	//if (count($chkvlLabResult) > 0) {
	?>
	// $("select option[data-disable*='10']").prop('disabled', true);
	// $("select option[data-disable*='30']").prop('disabled', true);
	// $("select option[data-disable*='40']").prop('disabled', true);
	<?php
	//}
	//if (count($chkHcResult) > 0) {
	?>
	//$("select option[data-disable*='20']").prop('disabled', true);
	<?php
	//}
	?>

	function getFacilityUser() {
		if ($("#facilityType").val() == '1' || $("#facilityType").val() == '4') {
			$.post("/facilities/getFacilityMapUser.php", {
					fType: $("#facilityType").val(),
					facilityId: <?= ($id); ?>,
				},
				function(data) {
					$("#userDetails").html(data);
				});
		} else {
			$("#userDetails").html('');
		}
		if ($("#facilityType").val() == '2') {
			$(".logoImage").show();
		} else {
			$(".logoImage").hide();
		}
	}

	function clearLabImage(img) {
		$(".labLogo").fileinput("clear");
		$("#clearLabImage").addClass("hide");
		$("#removedLabLogoImage").val(img);
	}

	function getNewLabImage(img) {
		$("#clearLabImage").addClass("hide");
		$("#removedLabLogoImage").val(img);
	}

	function showSignature(facilityType) {
		if (facilityType == 2) {
			$(".labDiv").show();
			$(".reportTemplate").show();
		} else {
			$(".labDiv").hide();
			$(".reportTemplate").hide();
		}
	}

	function getTestType() {
		if (first == 1) {
			var facility = $("#facilityType").val();
			var testType = $("#testType").val();

			if (testType == 'tb') {
				$('.availablePlatforms').show();
			} else {
				$('.availablePlatforms').hide();
			}

			if (facility && (testType.length > 0) && facility == '2') {
				var div = '<table aria-describedby="table" class="table table-bordered table-striped" aria-hidden="true" ><thead><th> <?php echo _translate("Test Type"); ?></th> <th> <?php echo _translate("Monthly Target"); ?> <span class="mandatory">*</span></th><th><?php echo _translate("Suppressed Monthly Target"); ?> <span class="mandatory">*</span></th> </thead><tbody>';
				for (var i = 0; i < testType.length; i++) {
					var testOrg = '';
					if ($('#monTar' + i).val())
						var oldMonTar = $('#monTar' + i).val();
					else
						var oldMonTar = '';
					if (testType[i] == 'vl') {
						if ($("#supMonTar" + i).val())
							var supM = $("#supMonTar" + i).val();
						else
							var supM = '';
						testOrg = 'Viral Load';
						var extraDiv = '<td><input type="text" class="" name="supMonTar[]" id ="supMonTar' + i + '" value="' + supM + '" title="<?php echo _translate('Please enter Suppressed monthly target', true); ?>"/></td>';
					} else if (testType[i] == 'eid') {
						testOrg = '<?php echo _translate("Early Infant Diagnosis", true); ?>';
						var extraDiv = '<td></td>';
					} else if (testType[i] == 'covid19') {
						testOrg = '<?php echo _translate("Covid-19", true); ?>';
						var extraDiv = '<td></td>';
					} else if (testType[i] == 'hepatitis') {
						testOrg = 'Hepatitis';
						var extraDiv = '<td></td>';
					} else if (testType[i] == 'tb') {
						testOrg = 'TB';
						var extraDiv = '<td></td>';
					} else if (testType[i] == 'generic-tests') {
						testOrg = 'Other Lab Tests';
						var extraDiv = '<td></td>';
					}

					div += '<tr><td>' + testOrg + '<input type="hidden" name="testData[]" id ="testData' + i + '" value="' + testType[i] + '" /></td>';
					div += '<td><input type="text" class="" name="monTar[]" id ="monTar' + i + '" value="' + oldMonTar + '" title="<?php echo _translate('Please enter monthly target', true); ?>"/></td>';
					div += extraDiv;
					div += '</tr>';
				}
				div += '</tbody></table>';
				$("#testDetails").html(div);
			} else {
				$("#testDetails").html('');
			}
		}
		first = 1;

		if ($("#testType").val() != '') {
			$.post("/facilities/getSampleType.php", {
					facilityId: $("#facilityId").val(),
					testType: $("#testType").val()
				},
				function(data) {
					$("#sampleType").html(data);
				});
		} else {
			$("#sampleType").html('');
		}
	}

	let testCounter = document.getElementById("signDetails").rows.length;

	function addNewRow() {
		testCounter++;
		let rowString = `<tr>
			<td style="width:14%;"><input type="text" class="form-control" name="signName[]" id="signName${testCounter}" placeholder="<?php echo _translate('Name', true); ?>" title="<?php echo _translate('Please enter the name', true); ?>"></td>
			<td style="width:14%;"><input type="text" class="form-control" name="designation[]" id="designation${testCounter}" placeholder="<?php echo _translate('Designation', true); ?>" title="<?php echo _translate('Please enter the Designation', true); ?>"></td>
			<td style="width:14%;"><input type="file" name="signature[]" id="signature${testCounter}" placeholder="<?php echo _translate('Signature', true); ?>" title="<?php echo _translate('Please enter the Signature', true); ?>"></td>
			<td style="width:14%;">
				<select class="select2 testSignType" id="testSignType${testCounter}" name="testSignType[${testCounter}][]" title="<?php echo _translate('Choose at least one test type', true); ?>" multiple>
					<option value="vl"><?php echo _translate("Viral Load", true); ?></option>
					<option value="eid"><?php echo _translate("Early Infant Diagnosis", true); ?></option>
					<option value="covid19"><?php echo _translate("Covid-19", true); ?></option>
					<option value='hepatitis'><?php echo _translate("Hepatitis", true); ?></option>
					<option value='tb'><?php echo _translate("TB", true); ?></option>
					<option value='generic-tests'><?php echo _translate("Other Lab Tests", true); ?></option>
					<option value='cd4'><?php echo _translate("CD4", true); ?></option>
				</select>
			</td>
			<td style="width:14%;"><input type="text" class="form-control" name="sortOrder[]" id="sortOrder${testCounter}" placeholder="<?php echo _translate('Display Order', true); ?>" title="<?php echo _translate('Please enter the Display Order', true); ?>"></td>
			<td style="width:14%;">
				<select class="form-control" id="signStatus${testCounter}" name="signStatus[]" title="<?php echo _translate('Please select the status', true); ?>">
					<option value="active"><?php echo _translate("Active", true); ?></option>
					<option value="inactive"><?php echo _translate("Inactive", true); ?></option>
				</select>
			</td>
			<td style="vertical-align:middle;text-align: center;width:10%;">
				<a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addNewRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
				<a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeNewRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
			</td>
		</tr>`;
		$("#signDetails").append(rowString);

		$("#testSignType" + testCounter).select2({
			placeholder: '<?php echo _translate("Select Test Type", true); ?>',
			width: '150px'
		});
	}

	function removeNewRow(el) {
		$(el).fadeOut("slow", function() {
			el.parentNode.removeChild(el);
			rl = document.getElementById("signDetails").rows.length;
			if (rl == 0) {
				testCounter = 0;
				addNewRow();
			}
		});
	}

	function showFile(count) {
		$('#spanClass' + count).hide();
		$('.showFile' + count).show();
	}

	function showreportTemplateFile() {
		$('#reportTemplateSpan').hide();
		$('.showreportTemplateFile').show();
	}

	function deletedRow(val) {
		deletedRowVar.push(val);
		$('#deletedRow').val(deletedRowVar);
		console.log($('#deletedRow').val());
	}
	let testTypeFileCounter = <?php echo (count($facilityReportFormat) > 0) ? count($facilityReportFormat) : 1; ?>;
	// Function to add new test type file row
	function addTestTypeFileRow() {
		testTypeFileCounter++;
		let rowString = `<tr>
            <td>
                <select class="form-control testTypeFileSelect" name="testTypeFile[]" id="testTypeFile${testTypeFileCounter}">
                    <option value=""><?php echo _translate("Select Test Type", true); ?></option>
					<option value="default"><?php echo _translate("Default format"); ?></option>
                    <?php if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) { ?>
                        <option value="vl"><?php echo _translate("Viral Load", true); ?></option>
                    <?php }
					if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) { ?>
                        <option value="eid"><?php echo _translate("Early Infant Diagnosis", true); ?></option>
                    <?php }
					if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) { ?>
                        <option value="covid19"><?php echo _translate("Covid-19", true); ?></option>
                    <?php }
					if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) { ?>
                        <option value='hepatitis'><?php echo _translate("Hepatitis", true); ?></option>
                    <?php }
					if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) { ?>
                        <option value='tb'><?php echo _translate("TB", true); ?></option>
                    <?php }
					if (isset(SYSTEM_CONFIG['modules']['cd4']) && SYSTEM_CONFIG['modules']['cd4'] === true) { ?>
                        <option value='cd4'><?php echo _translate("CD4", true); ?></option>
                    <?php }
					if (isset(SYSTEM_CONFIG['modules']['generic-tests']) && SYSTEM_CONFIG['modules']['generic-tests'] === true) { ?>
                        <option value='generic-tests'><?php echo _translate("Other Lab Tests", true); ?></option>
                    <?php } ?>
                </select>
            </td>
            <td>
                <input type="file" class="form-control" name="reportTemplate[]" id="reportTemplate${testTypeFileCounter}" accept=".pdf" title="<?php echo _translate('Please upload PDF file', true); ?>">
            </td>
			<td>
				<input type="text" class="form-control" name="headerMargin[]" id="headerMargin${testTypeFileCounter}" placeholder="<?php echo _translate('Top margin'); ?>" title="<?php echo _translate('Please enter the header margin'); ?>">
			</td>
            <td style="vertical-align:middle;text-align: center;">
                <a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="addTestTypeFileRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
                <a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeTestTypeFileRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
            </td>
        </tr>`;

		$("#testTypeFileDetails").append(rowString);
	}

	// Function to remove test type file row
	function removeTestTypeFileRow(el) {
		let rowCount = document.getElementById("testTypeFileDetails").rows.length;
		if (rowCount > 1) {
			$(el).fadeOut("slow", function() {
				console.log(el.parentNode);
				return false;
				el.parentNode.removeChild(el);
			});
		} else {
			alert("<?php echo _translate('At least one row is required', true); ?>");
		}
	}

	// Show/hide the test type file section based on facility type
	function showTestTypeFileSection(facilityType) {
		if (facilityType == 2) {
			$(".testTypeFileSection").show();
		} else {
			$(".testTypeFileSection").hide();
		}
	}

	// Modify your existing showSignature function to include the new section
	// Replace your existing showSignature function with this updated version:
	function showSignature(facilityType) {
		if (facilityType == 2) {
			$(".labDiv").show();
			$(".testTypeFileSection").show(); // Add this line
			$("#testSignType1").select2({
				placeholder: '<?php echo _translate("Select Test Type", true); ?>',
				width: '100%'
			});
		} else {
			$(".labDiv").hide();
			$(".testTypeFileSection").hide(); // Add this line
		}
	}

	function removeReport(row) {
		$('.newFile' + row).show();
		$('.oldFile' + row).hide();
	}

	function deleteReport(row) {
		if (confirm("Are you sure want to remove the template? once deleted can't be undone!")) {
			$('#deleteTemplate' + row).val('yes');
		}
	}
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
