<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GeoLocationsService;
use App\Services\UsersService;



require_once APPLICATION_PATH . '/header.php';
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$geolocation = new GeoLocationsService();


/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$userResult = $usersService->getAllUsers();

$userInfo = [];
foreach ($userResult as $user) {
	$userInfo[$user['user_id']] = ($user['user_name']);
}

$id = base64_decode($_GET['id']);
//$id = $_GET['id'];
$facilityInfo = $db->rawQueryOne('SELECT * from facility_details where facility_id= ?', array($id));
$facilityAttributes = json_decode($facilityInfo['facility_attributes']);



$fQuery = "SELECT * FROM facility_type";
$fResult = $db->rawQuery($fQuery);
$pQuery = "SELECT * FROM geographical_divisions WHERE geo_parent = 0 and geo_status='active'";
$pResult = $db->rawQuery($pQuery);

$chkvlLabResult = $db->rawQuery('SELECT * from testing_lab_health_facilities_map as vlfm where vl_lab_id = ?', array($id));
$chkHcResult = $db->rawQuery('SELECT * from testing_lab_health_facilities_map as vlfm where facility_id = ?', array($id));

$fType = $facilityInfo['facility_type'];
// $vlfmQuery = "SELECT GROUP_CONCAT(DISTINCT vlfm.user_id SEPARATOR ',') as userId FROM user_facility_map as vlfm join facility_details as fd ON fd.facility_id=vlfm.facility_id where facility_type = " . $fType;
// $vlfmResult = $db->rawQuery($vlfmQuery);


// $uQuery = "SELECT * FROM user_details WHERE `status` like 'active' ORDER BY user_name";
// if (isset($vlfmResult[0]['userId'])) {
// 	$exp = explode(",", $vlfmResult[0]['userId']);
// 	foreach ($exp as $ex) {
// 		$noUserId[] = "'" . $ex . "'";
// 	}
// 	$imp = implode(",", $noUserId);
// 	$uQuery = $uQuery . " where user_id NOT IN(" . $imp . ")";
// }
// $uResult = $db->rawQuery($uQuery);

//$selectedResult = $db->rawQuery('SELECT * FROM user_facility_map as vlfm join user_details as ud ON ud.user_id=vlfm.user_id join facility_details as fd ON fd.facility_id=vlfm.facility_id WHERE vlfm.facility_id = ?', array($id));

$testTypeInfo = $db->rawQuery('SELECT * FROM testing_labs WHERE facility_id = ?', array($id));
$attrValue = json_decode($testTypeInfo[0]['attributes']);
$availPlatforms = $attrValue->platforms;


$signResults = $db->rawQuery('SELECT * FROM lab_report_signatories WHERE lab_id=? ORDER BY display_order, name_of_signatory', array($id));


$editTestType = '';
$div = '';
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
$countryShortCode = $general->getCountryShortCode();
$reportFormats = [];
if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) {
	$reportFormats['covid19'] = $general->activeReportFormats('covid-19', $countryShortCode);
}
if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) {
	$reportFormats['eid'] = $general->activeReportFormats('eid', $countryShortCode);
}
if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) {
	$reportFormats['vl'] = $general->activeReportFormats('vl', $countryShortCode);
}

if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) {
	$reportFormats['hepatitis'] = $general->activeReportFormats('hepatitis', $countryShortCode);
}

if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) {
	$reportFormats['tb'] = $general->activeReportFormats('tb', $countryShortCode);
}
$formats = json_decode($facilityInfo['report_format'], true);
$labDiv = "none";
$allowFileDiv = "none";
if ($facilityInfo['test_type'] == 2) {
	$labDiv = "block";
}
if ($fType == "2") {
	$allowFileDiv = "block";
}
$geoLocationParentArray = $geolocation->fetchActiveGeolocations();
$geoLocationChildArray = $geolocation->fetchActiveGeolocations(0, $facilityInfo['facility_state_id']);
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
		<h1><em class="fa-solid fa-hospital"></em> <?php echo _("Edit Facility"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Facilities"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _("indicates required field"); ?> &nbsp;</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='editFacilityForm' id='editFacilityForm' autocomplete="off" enctype="multipart/form-data" action="editFacilityHelper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="facilityName" class="col-lg-4 control-label"><?php echo _("Facility Name"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="facilityName" name="facilityName" placeholder="<?php echo _('Facility Name'); ?>" title="<?php echo _('Please enter facility name'); ?>" value="<?= htmlspecialchars($facilityInfo['facility_name'], ENT_QUOTES, 'UTF-8'); ?>" onblur="checkNameValidation('facility_details','facility_name',this,'<?php echo "facility_id##" . htmlspecialchars($facilityInfo['facility_id'],  ENT_QUOTES, 'UTF-8'); ?>','<?php echo _("The facility name that you entered already exists.Enter another name"); ?>',null)" />
										<input type="hidden" class="form-control isRequired" id="facilityId" name="facilityId" value="<?php echo base64_encode($facilityInfo['facility_id']); ?>" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="facilityCode" class="col-lg-4 control-label"><?php echo _("Facility Code"); ?><br> <small><?php echo _("(National Unique Code)"); ?></small> </label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="facilityCode" name="facilityCode" placeholder="<?php echo _('Facility Code'); ?>" title="<?php echo _('Please enter facility code'); ?>" value="<?= htmlspecialchars($facilityInfo['facility_code']); ?>" onblur="checkNameValidation('facility_details','facility_code',this,'<?php echo "facility_id##" . htmlspecialchars($facilityInfo['facility_id'],  ENT_QUOTES, 'UTF-8'); ?>','<?php echo _("The code that you entered already exists.Try another code"); ?>',null)" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="otherId" class="col-lg-4 control-label"><?php echo _("Other/External Code"); ?> </label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="otherId" name="otherId" placeholder="<?php echo _('Other/External Code'); ?>" value="<?= htmlspecialchars($facilityInfo['other_id'],  ENT_QUOTES, 'UTF-8'); ?>" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="facilityType" class="col-lg-4 control-label"><?php echo _("Facility Type"); ?> <span class="mandatory">*</span> </label>
									<div class="col-lg-7">
										<select class="form-control isRequired" id="facilityType" name="facilityType" title="<?php echo _('Please select facility type'); ?>" onchange="<?php echo ($_SESSION['instanceType'] == 'remoteuser') ? 'getFacilityUser()' : ''; ?>getTestType(); showSignature(this.value);">
											<option value=""> <?php echo _("-- Select --"); ?> </option>
											<?php
											$k = 10;
											foreach ($fResult as $type) {
											?>
												<option data-disable="<?php echo $k; ?>" value="<?= htmlspecialchars($type['facility_type_id']); ?>" <?php echo ($facilityInfo['facility_type'] == $type['facility_type_id']) ? "selected='selected'" : "" ?>><?php echo ($type['facility_type_name']); ?></option>
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
									<label for="email" class="col-lg-4 control-label"><?php echo _("Email(s)"); ?> <br> <small><?php echo _("(comma separated)"); ?></small> </label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="email" name="email" placeholder="<?php echo _('eg-email1@gmail.com,email2@gmail.com'); ?>" value="<?= htmlspecialchars($facilityInfo['facility_emails']); ?>" />
									</div>
								</div>
							</div>
							<div class="col-md-6 allowResultsUpload" style="display:<?php echo $allowFileDiv; ?>;">
								<div class="form-group">
									<label for="allowResultUpload" class="col-lg-4 control-label"><?php echo _("Allow Results File Upload"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control" id="allowResultUpload" name="allowResultUpload" title="<?php echo _('Please select if this lab can upload test results file'); ?>">
											<option value=""> <?php echo _("-- Select --"); ?> </option>
											<option <?php if ($facilityAttributes->allow_results_file_upload == 'yes') echo 'selected="selected"'; ?> value="yes">Yes</option>
											<option <?php if ($facilityAttributes->allow_results_file_upload == 'no') echo 'selected="selected"'; ?> value="no">No</option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<br>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="testingPoints" class="col-lg-4 control-label"><?php echo _("Testing Point(s)"); ?><br> <small><?php echo _("(comma separated)"); ?></small> </label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="testingPoints" name="testingPoints" placeholder="<?php echo _('eg. VCT, PMTCT'); ?>" value="<?php echo implode(", ", json_decode($facilityInfo['testing_points'], true)); ?>" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="Lab Manager" class="col-lg-4 control-label"><?php echo _("Lab Manager"); ?></label>
									<div class="col-lg-7">
										<select name="contactPerson" id="contactPerson" class="select2 form-control" title="<?php echo _('Please choose Lab Manager'); ?>" style="width: 100%;">
											<?= $general->generateSelectOptions($userInfo, $facilityInfo['contact_person'], _("-- Select --")); ?>
										</select>
									</div>
								</div>
							</div>

						</div>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="phoneNo" class="col-lg-4 control-label"><?php echo _("Phone Number"); ?></label>
									<div class="col-lg-7">
										<input type="text" class="form-control forceNumeric" id="phoneNo" name="phoneNo" placeholder="<?php echo _('Phone Number'); ?>" value="<?= htmlspecialchars($facilityInfo['facility_mobile_numbers']); ?>" onblur="checkNameValidation('facility_details','facility_mobile_numbers',this,'<?php echo "facility_id##" . $facilityInfo['facility_id']; ?>','<?php echo _("The mobile no that you entered already exists.Enter another mobile no."); ?>',null)" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="state" class="col-lg-4 control-label"><?php echo _("Province/State"); ?> <span class="mandatory">*</span> </label>
									<div class="col-lg-7">
										<?php if (sizeof($geoLocationParentArray) > 0) { ?>
											<select name="stateId" id="stateId" class="form-control isRequired" title="<?php echo _('Please choose province/state'); ?>">
												<?= $general->generateSelectOptions($geoLocationParentArray, $facilityInfo['facility_state_id'], _("-- Select --")); ?>
												<option value="other">Other</option>
											</select>
											<input type="text" class="form-control" name="provinceNew" id="provinceNew" placeholder="<?php echo _('Enter Province/State'); ?>" title="<?php echo _('Please enter province/state'); ?>" style="margin-top:4px;display:none;" />
											<input type="hidden" name="state" id="state" value="<?= htmlspecialchars($facilityInfo['facility_state']); ?>" />
										<?php }
										if ((!isset($facilityInfo['facility_state_id']) || $facilityInfo['facility_state_id'] == "") && (isset($facilityInfo['facility_state']) || $facilityInfo['facility_state'] != "")) { ?>
											<input type="text" value="<?= htmlspecialchars($facilityInfo['facility_state']); ?>" class="form-control isRequired" name="oldState" id="oldState" placeholder="<?php echo _('Enter Province/State'); ?>" title="<?php echo _('Please enter province/state'); ?>" />
										<?php } ?>
									</div>
								</div>
							</div>


						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="district" class="col-lg-4 control-label"><?php echo _("District/County"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select name="districtId" id="districtId" class="form-control isRequired" title="<?php echo _('Please choose District/County'); ?>">
											<?= $general->generateSelectOptions($geoLocationChildArray, $facilityInfo['facility_district_id'], _("-- Select --")); ?>
											<option value="other">Other</option>
										</select>
										<input type="text" class="form-control" name="districtNew" id="districtNew" placeholder="<?php echo _('Enter District/County'); ?>" title="<?php echo _('Please enter District/County'); ?>" style="margin-top:4px;display:none;" />
										<input type="hidden" id="district" name="district" value="<?= htmlspecialchars($facilityInfo['facility_district']); ?>" />
										<?php if ((!isset($facilityInfo['facility_district_id']) || $facilityInfo['facility_district_id'] == "") && (isset($facilityInfo['facility_district']) || $facilityInfo['facility_district'] != "")) { ?>
											<input type="text" value="<?= htmlspecialchars($facilityInfo['facility_district']); ?>" class="form-control isRequired" name="oldDistrict" id="oldDistrict" placeholder="<?php echo _('Enter District/County'); ?>" title="<?php echo _('Please enter district/county'); ?>" />
										<?php } ?>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="hubName" class="col-lg-4 control-label"><?php echo _("Linked Hub Name (if applicable)"); ?></label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="hubName" name="hubName" placeholder="<?php echo _('Hub Name'); ?>" title="<?php echo _('Please enter hub name'); ?>" value="<?= htmlspecialchars($facilityInfo['facility_hub_name']); ?>" />
									</div>
								</div>
							</div>


						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="address" class="col-lg-4 control-label"><?php echo _("Address"); ?></label>
									<div class="col-lg-7">
										<textarea class="form-control" name="address" id="address" placeholder="<?php echo _('Address'); ?>"><?= htmlspecialchars($facilityInfo['address']); ?></textarea>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="country" class="col-lg-4 control-label"><?php echo _("Country"); ?></label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="country" name="country" placeholder="<?php echo _('Country'); ?>" value="<?= htmlspecialchars($facilityInfo['country']); ?>" />
									</div>
								</div>
							</div>

						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="latitude" class="col-lg-4 control-label"><?php echo _("Latitude"); ?></label>
									<div class="col-lg-7">
										<input type="text" class="form-control forceNumeric" id="latitude" name="latitude" placeholder="<?php echo _('Latitude'); ?>" title="<?php echo _('Please enter latitude'); ?>" value="<?= htmlspecialchars($facilityInfo['latitude']); ?>" />
									</div>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="longitude" class="col-lg-4 control-label"><?php echo _("Longitude"); ?></label>
									<div class="col-lg-7">
										<input type="text" class="form-control forceNumeric" id="longitude" name="longitude" placeholder="<?php echo _('Longitude'); ?>" title="<?php echo _('Please enter longitude'); ?>" value="<?= htmlspecialchars($facilityInfo['longitude']); ?>" />
									</div>
								</div>
							</div>

						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="status" class="col-lg-4 control-label"><?php echo _("Status"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='status' id='status' title="<?php echo _('Please select the status'); ?>">
											<option value=""> <?php echo _("-- Select --"); ?> </option>
											<option value="active" <?php echo ($facilityInfo['status'] == 'active') ? "selected='selected'" : "" ?>><?php echo _("Active"); ?></option>
											<option value="inactive" <?php echo ($facilityInfo['status'] == 'inactive') ? "selected='selected'" : "" ?>><?php echo _("Inactive"); ?></option>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="testType" class="col-lg-4 control-label"><?php echo _("Test Type"); ?></label>
									<div class="col-lg-7">
										<select type="text" class="" id="testType" name="testType[]" title="<?php echo _('Choose one test type'); ?>" onchange="getTestType();" multiple>
											<?php if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) { ?>
												<option value='vl' <?php echo (preg_match("/vl/i", $facilityInfo['test_type'])) ? "selected='selected'" : '';  ?>><?php echo _("Viral Load"); ?></option>
											<?php }
											if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) { ?>
												<option value='eid' <?php echo (preg_match("/eid/i", $facilityInfo['test_type'])) ? "selected='selected'" : '';  ?>><?php echo _("Early Infant Diagnosis"); ?></option>
											<?php }
											if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) { ?>
												<option value='covid19' <?php echo (preg_match("/covid19/i", $facilityInfo['test_type'])) ? "selected='selected'" : '';  ?>><?php echo _("Covid-19"); ?></option>
											<?php }
											if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) { ?>
												<option value='hepatitis' <?php echo (preg_match("/hepatitis/i", $facilityInfo['test_type'])) ? "selected='selected'" : '';  ?>><?php echo _("Hepatitis"); ?></option>
											<?php }
											if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) { ?>
												<option value='tb' <?php echo (preg_match("/tb/i", $facilityInfo['test_type'])) ? "selected='selected'" : '';  ?>><?php echo _("TB"); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6 availablePlatforms" style="display:none;">
								<div class="form-group">
									<label for="availablePlatforms" class="col-lg-4 control-label"><?php echo _("Available Platforms"); ?></label>
									<div class="col-lg-7">
										<select type="text" id="availablePlatforms" name="availablePlatforms[]" title="<?php echo _('Choose one Available Platforms'); ?>" multiple>
											<option value="microscopy" <?php echo in_array('microscopy', $availPlatforms) ? "selected='selected'" :  ''; ?>><?php echo _("Microscopy"); ?></option>
											<option value="xpert" <?php echo in_array('xpert', $availPlatforms) ? "selected='selected'" : '';  ?>><?php echo _("Xpert"); ?></option>
											<option value="lam" <?php echo in_array('lam', $availPlatforms) ? "selected='selected'" : '';  ?>><?php echo _("Lam"); ?></option>
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
									<label for="reportFormat" class="col-lg-4 control-label"><?php echo _("Report Format For VL"); ?></label>
									<div class="col-lg-7">
										<select class="form-control" name='reportFormat[vl]' id='reportFormat' title="<?php echo _('Please select the status'); ?>" onchange="checkIfExist(this);">
											<?php if ($count > 1) { ?>
												<option value=""><?php echo _("-- Select --"); ?></option>
											<?php } ?>
											<?php foreach ($reportFormats['vl'] as $key => $value) { ?>
												<option value="<?php echo $key; ?>" <?php echo ($formats['vl'] == $key) ? "selected='selected'" : ""; ?>><?php echo ($value); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
						<?php }
						if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) {
							$count = sizeof($reportFormats['eid']); ?>
							<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
								<div class="form-group">
									<label for="reportFormat" class="col-lg-4 control-label"><?php echo _("Report Format For EID"); ?></label>
									<div class="col-lg-7">
										<select class="form-control" name='reportFormat[eid]' id='reportFormat' title="<?php echo _('Please select the status'); ?>" onchange="checkIfExist(this);">
											<?php if (($count > 1)) { ?>
												<option value=""><?php echo _("-- Select --"); ?></option>
											<?php } ?>
											<?php foreach ($reportFormats['eid'] as $key => $value) { ?>
												<option value="<?php echo $key; ?>" <?php echo ($formats['eid'] == $key) ? "selected='selected'" : ""; ?>><?php echo ($value); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
						<?php }
						if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) {
							$count = sizeof($reportFormats['covid19']); ?>
							<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
								<div class="form-group">
									<label for="reportFormat" class="col-lg-4 control-label"><?php echo _("Report Format For Covid-19"); ?></label>
									<div class="col-lg-7">
										<select class="form-control" name='reportFormat[covid19]' id='reportFormat' title="<?php echo _('Please select the status'); ?>" onchange="checkIfExist(this);">
											<?php if (($count > 1)) { ?>
												<option value=""><?php echo _("-- Select --"); ?></option>
											<?php } ?>
											<?php foreach ($reportFormats['covid19'] as $key => $value) { ?>
												<option value="<?php echo $key; ?>" <?php echo ($formats['covid19'] == $key) ? "selected='selected'" : ""; ?>><?php echo ($value); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
						<?php }
						if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) {
							$count = sizeof($reportFormats['hepatitis']); ?>
							<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
								<div class="form-group">
									<label for="reportFormat" class="col-lg-4 control-label"><?php echo _("Report Format For Hepatitis"); ?></label>
									<div class="col-lg-7">
										<select class="form-control" name='reportFormat[hepatitis]' id='reportFormat' title="<?php echo _('Please select the status'); ?>" onchange="checkIfExist(this);">
											<?php if (($count > 1)) { ?>
												<option value=""><?php echo _("-- Select --"); ?></option>
											<?php } ?>
											<?php foreach ($reportFormats['hepatitis'] as $key => $value) { ?>
												<option value="<?php echo $key; ?>" <?php echo ($formats['hepatitis'] == $key) ? "selected='selected'" : ""; ?>><?php echo ($value); ?></option>
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
									<label for="reportFormat" class="col-lg-4 control-label"><?php echo _("Report Format For TB"); ?></label>
									<div class="col-lg-7">
										<select class="form-control" name='reportFormat[tb]' id='reportFormat' title="<?php echo _('Please select the status'); ?>" onchange="checkIfExist(this);">
											<?php if (($count > 1)) { ?>
												<option value=""><?php echo _("-- Select --"); ?></option>
											<?php } ?>
											<?php foreach ($reportFormats['tb'] as $key => $value) { ?>
												<option value="<?php echo $key; ?>" <?php echo ($formats['tb'] == $key) ? "selected='selected'" : ""; ?>><?php echo ($value); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
						<?php } ?>
					</div>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="" class="col-lg-4 control-label"><?php echo _("Logo Image"); ?> </label>
								<div class="col-lg-8">
									<div class="fileinput fileinput-new labLogo" data-provides="fileinput">
										<div class="fileinput-preview thumbnail" data-trigger="fileinput" style="width:200px; height:150px;">
											<?php

											if (isset($facilityInfo['facility_logo']) && trim($facilityInfo['facility_logo']) != '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $facilityInfo['facility_id'] . DIRECTORY_SEPARATOR . $facilityInfo['facility_logo'])) {
											?>
												<img src="/uploads/facility-logo/<?= htmlspecialchars($facilityInfo['facility_id']); ?>/<?= htmlspecialchars($facilityInfo['facility_logo']); ?>" alt="Logo image">
											<?php } else { ?>

											<?php } ?>
										</div>
										<div>
											<span class="btn btn-default btn-file"><span class="fileinput-new"><?php echo _("Select image"); ?></span><span class="fileinput-exists"><?php echo _("Change"); ?></span>
												<input type="file" id="labLogo" name="labLogo" title="<?php echo _('Please select logo image'); ?>" onchange="getNewLabImage('<?= htmlspecialchars($facilityInfo['facility_logo']); ?>');">
											</span>
											<?php
											if (isset($facilityInfo['facility_logo']) && trim($facilityInfo['facility_logo']) != '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $facilityInfo['facility_id'] . DIRECTORY_SEPARATOR . $facilityInfo['facility_logo'])) {
											?>
												<a id="clearLabImage" href="javascript:void(0);" class="btn btn-default" data-dismiss="fileupload" onclick="clearLabImage('<?= htmlspecialchars($facilityInfo['facility_logo']); ?>')"><?php echo _("Clear"); ?></a>
											<?php } ?>
											<a href="#" class="btn btn-default fileinput-exists" data-dismiss="fileinput"><?php echo _("Remove"); ?></a>
										</div>
									</div>
									<div class="box-body">
										<?php echo _("Please make sure logo image size of"); ?>: <code><?php echo _("80x80"); ?></code>
									</div>
								</div>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="" class="col-lg-4 control-label"><?php echo _("Header Text"); ?></label>
								<div class="col-lg-7">
									<input type="text" class="form-control " id="headerText" name="headerText" placeholder="<?php echo _('Header Text'); ?>" title="<?php echo _('Please enter header text'); ?>" value="<?= htmlspecialchars($facilityInfo['header_text']); ?>" />
								</div>
							</div>
						</div>
					</div>
					<div class="row" id="sampleType"></div>
					<div class="row-item labDiv" style="display:<?php echo $labDiv; ?>;">
						<hr>
						<h4 class="col-lg-12"><?= _("The following information is sometimes used to show names, designations and signatures in some reports."); ?></h4>
						<table aria-describedby="table" class="col-lg-12 table table-bordered">
							<thead>
								<tr>
									<th><?php echo _("Name of Signatory"); ?></th>
									<th><?php echo _("Designation"); ?></th>
									<th><?php echo _("Upload Signature (jpg, png)"); ?></th>
									<th><?php echo _("Test Types"); ?></th>
									<th><?php echo _("Display Order"); ?></th>
									<th><?php echo _("Current Status"); ?></th>
									<th><?php echo _("Action"); ?></th>
								</tr>
							</thead>
							<tbody id="signDetails">
								<?php if (isset($signResults) && !empty($signResults)) {
									foreach ($signResults as $key => $row) { ?>
										<tr>
											<td style="width:14%;"><input type="hidden" name="signId[]" id="signId<?php echo ($key + 1); ?>" value="<?php echo $row['signatory_id'] ?>" /><input value="<?php echo $row['name_of_signatory'] ?>" type="text" class="form-control" name="signName[]" id="signName<?php echo ($key + 1); ?>" placeholder="<?php echo _('Name'); ?>" title="<?php echo _('Please enter the name'); ?>"></td>
											<td style="width:14%;"><input value="<?php echo $row['designation'] ?>" type="text" class="form-control" name="designation[]" id="designation<?php echo ($key + 1); ?>" placeholder="<?php echo _('Designation'); ?>" title="<?php echo _('Please enter the Designation'); ?>"></td>
											<td style="width:10%;">
												<?php $lmSign = "/uploads/labs/" . $row['lab_id'] . "/signatures/" . $row['signature'];
												$show = "style='display:block'";
												if (isset($row['signature']) && $row['signature'] != "") {
													$show = "style='display:none'";
												?>
													<span id="spanClass<?php echo ($key + 1); ?>"><a href="javascript:void(0);" onclick="showFile(<?= $key + 1; ?>);"><span class="alert-danger" style="padding: 5px;border-radius: 50%;margin-top: 0px;float: right;">X</span></a><img alt="Facility" src="<?php echo $lmSign; ?>" style="width: 100px;" /></span>
												<?php }
												?>
												<input <?php echo $show; ?> class="showFile<?php echo ($key + 1); ?>" type="file" name="signature[]" id="signature<?= $key + 1; ?>" placeholder="<?php echo _('Signature'); ?>" title="<?php echo _('Please enter the Signature'); ?>">
											</td>
											<td style="width:14%;">
												<select type="text" class="select2" id="testSignType<?php echo ($key + 1); ?>" name="testSignType[<?= $key + 1; ?>][]" title="<?php echo _('Choose one test type'); ?>" multiple>
													<option value="vl" <?php echo (isset($row['test_types']) && in_array("vl", explode(",", $row['test_types']))) ? 'selected="selected"' : ''; ?>><?php echo _("Viral Load"); ?></option>
													<option value="eid" <?php echo (isset($row['test_types']) && in_array("eid", explode(",", $row['test_types']))) ? 'selected="selected"' : ''; ?>><?php echo _("Early Infant Diagnosis"); ?></option>
													<option value="covid19" <?php echo (isset($row['test_types']) && in_array("covid19", explode(",", $row['test_types']))) ? 'selected="selected"' : ''; ?>><?php echo _("Covid-19"); ?></option>
													<option value='hepatitis' <?php echo (isset($row['test_types']) && in_array("hepatitis", explode(",", $row['test_types']))) ? 'selected="selected"' : ''; ?>><?php echo _("Hepatitis"); ?></option>
													<option value='tb' <?php echo (isset($row['test_types']) && in_array("tb", explode(",", $row['test_types']))) ? 'selected="selected"' : ''; ?>><?php echo _("TB"); ?></option>
												</select>
											</td>
											<td style="width:14%;"><input value="<?php echo $row['display_order'] ?>" type="number" class="form-control" name="sortOrder[]" id="sortOrder<?= $key + 1; ?>" placeholder="<?php echo _('Display Order'); ?>" title="<?php echo _('Please enter the Display Order'); ?>"></td>
											<td style="width:14%;">
												<select class="form-control" id="signStatus<?= $key + 1; ?>" name="signStatus[]" title="<?php echo _('Please select the status'); ?>">
													<option value="active" <?php echo (isset($row['test_types']) && $row['test_types'] == 'active') ? 'selected="selected"' : ''; ?>><?php echo _("Active"); ?></option>
													<option value="inactive" <?php echo (isset($row['test_types']) && $row['test_types'] == 'inactive') ? 'selected="selected"' : ''; ?>><?php echo _("Inactive"); ?></option>
												</select>
											</td>
											<td style="vertical-align:middle;text-align: center;width:10%;">
												<a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addNewRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
												<a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeNewRow(this.parentNode.parentNode);deletedRow(<?php echo $row['signatory_id'] ?>);"><em class="fa-solid fa-minus"></em></a>
											</td>
										</tr>
									<?php }
								} else { ?>
									<tr>
										<td style="width:14%;"><input type="text" class="form-control" name="signName[]" id="signName1" placeholder="<?php echo _('Name'); ?>" title="<?php echo _('Please enter the name'); ?>"></td>
										<td style="width:14%;"><input type="text" class="form-control" name="designation[]" id="designation1" placeholder="<?php echo _('Designation'); ?>" title="<?php echo _('Please enter the Designation'); ?>"></td>
										<td style="width:10%;"><input type="file" name="signature[]" id="signature1" placeholder="<?php echo _('Signature'); ?>" title="<?php echo _('Please enter the Signature'); ?>"></td>
										<td style="width:14%;">
											<select type="text" class="select2" id="testSignType1" name="testSignType[1][]" title="<?php echo _('Choose one test type'); ?>" multiple>
												<option value="vl"><?php echo _("Viral Load"); ?></option>
												<option value="eid"><?php echo _("Early Infant Diagnosis"); ?></option>
												<option value="covid19"><?php echo _("Covid-19"); ?></option>
												<option value='hepatitis'><?php echo _("Hepatitis"); ?></option>
											</select>
										</td>
										<td style="width:14%;"><input type="number" class="form-control" name="sortOrder[]" id="sortOrder1" placeholder="<?php echo _('Display Order'); ?>" title="<?php echo _('Please enter the Display Order'); ?>"></td>
										<td style="width:14%;">
											<select class="form-control" id="signStatus1" name="signStatus[]" title="<?php echo _('Please select the status'); ?>">
												<option value="active"><?php echo _("Active"); ?></option>
												<option value="inactive"><?php echo _("Inactive"); ?></option>
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
			</div>
			<!-- /.box-body -->
			<div class="box-footer">
				<input type="hidden" name="selectedUser" id="selectedUser" />
				<input type="hidden" name="removedLabLogoImage" id="removedLabLogoImage" />
				<input type="hidden" name="deletedRow" id="deletedRow" />
				<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _("Submit"); ?></a>
				<a href="facilities.php" class="btn btn-default"> <?php echo _("Cancel"); ?></a>
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
<script type="text/javascript" src="/assets/js/multiselect.min.js"></script>
<script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script>

<script type="text/javascript">
	var deletedRowVar = [];
	$(document).ready(function() {

		getTestType();

		$('#facilityType').trigger('change');

		$("#testType").multipleSelect({
			placeholder: '<?php echo _("Select Test Type"); ?>',
			width: '100%'
		});
		$(".select2").select2({
			placeholder: '<?php echo _("Select Lab Manager"); ?>',
			width: '150px'
		});
		$("#availablePlatforms").multipleSelect({
			placeholder: '<?php echo _("Select Available Platforms"); ?>',
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
						dName: '<?php echo (isset($facilityInfo['facility_district_id']) && $facilityInfo['facility_district_id'] != "") ? trim($facilityInfo['facility_district_id']) : trim($facilityInfo['facility_district']); ?>'
					},
					function(data) {
						if (data != "") {
							details = data.split("###");
							$("#districtId").html(details[1]);
							$("#districtId").append('<option value="other"><?php echo _("Other"); ?></option>');
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
	// jQuery(document).ready(function($) {
	//   $('#search').multiselect({
	//     search: {
	//       left: '<input type="text" name="q" class="form-control" placeholder="Search..." />',
	//       right: '<input type="text" name="q" class="form-control" placeholder="Search..." />',
	//     },
	//     fireSearch: function(value) {
	//       return value.length > 3;
	//     }
	//   });
	// });

	function validateNow() {
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
		} else {
			$("#allowResultUpload option[value=yes]").removeAttr('selected', 'selected');
			$("#allowResultUpload option[value='']").attr('selected', 'selected');
			$('.allowResultsUpload').hide();
			$('#allowResultUpload').removeClass('isRequired');
			$('#allowResultUpload').val('');
		}
	});

	<?php
	if (count($chkvlLabResult) > 0) {
	?>
		$("select option[data-disable*='10']").prop('disabled', true);
		$("select option[data-disable*='30']").prop('disabled', true);
		$("select option[data-disable*='40']").prop('disabled', true);
	<?php
	}
	if (count($chkHcResult) > 0) {
	?>
		$("select option[data-disable*='20']").prop('disabled', true);
	<?php
	}
	?>

	function getFacilityUser() {
		if ($("#facilityType").val() == '1' || $("#facilityType").val() == '4') {
			$.post("/facilities/getFacilityMapUser.php", {
					fType: $("#facilityType").val(),
					facilityId: <?= $id; ?>,
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
		} else {
			$(".labDiv").hide();
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
				var div = '<table aria-describedby="table" class="table table-bordered table-striped" aria-hidden="true" ><thead><th> <?php echo _("Test Type"); ?></th> <th> <?php echo _("Monthly Target"); ?> <span class="mandatory">*</span></th><th><?php echo _("Suppressed Monthly Target"); ?> <span class="mandatory">*</span></th> </thead><tbody>';
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
						var extraDiv = '<td><input type="text" class="" name="supMonTar[]" id ="supMonTar' + i + '" value="' + supM + '" title="<?php echo _('Please enter Suppressed monthly target'); ?>"/></td>';
					} else if (testType[i] == 'eid') {
						testOrg = '<?php echo _("Early Infant Diagnosis"); ?>';
						var extraDiv = '<td></td>';
					} else if (testType[i] == 'covid19') {
						testOrg = '<?php echo _("Covid-19"); ?>';
						var extraDiv = '<td></td>';
					}
					div += '<tr><td>' + testOrg + '<input type="hidden" name="testData[]" id ="testData' + i + '" value="' + testType[i] + '" /></td>';
					div += '<td><input type="text" class="" name="monTar[]" id ="monTar' + i + '" value="' + oldMonTar + '" title="<?php echo _('Please enter monthly target'); ?>"/></td>';
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
			<td style="width:14%;"><input type="text" class="form-control" name="signName[]" id="signName${testCounter}" placeholder="<?php echo _('Name'); ?>" title="<?php echo _('Please enter the name'); ?>"></td>
			<td style="width:14%;"><input type="text" class="form-control" name="designation[]" id="designation${testCounter}" placeholder="<?php echo _('Designation'); ?>" title="<?php echo _('Please enter the Designation'); ?>"></td>
			<td style="width:14%;"><input type="file" name="signature[]" id="signature${testCounter}" placeholder="<?php echo _('Signature'); ?>" title="<?php echo _('Please enter the Signature'); ?>"></td>
			<td style="width:14%;">
				<select type="text" class="select2" id="testSignType${testCounter}" name="testSignType[${testCounter}][]" title="<?php echo _('Choose one test type'); ?>" multiple>
					<option value="vl"><?php echo _("Viral Load"); ?></option>
					<option value="eid"><?php echo _("Early Infant Diagnosis"); ?></option>
					<option value="covid19"><?php echo _("Covid-19"); ?></option>
					<option value='hepatitis'><?php echo _("Hepatitis"); ?></option>
					<option value='tb'><?php echo _("TB"); ?></option>
				</select>
			</td>
			<td style="width:14%;"><input type="text" class="form-control" name="sortOrder[]" id="sortOrder${testCounter}" placeholder="<?php echo _('Display Order'); ?>" title="<?php echo _('Please enter the Display Order'); ?>"></td>
			<td style="width:14%;">
				<select class="form-control" id="signStatus${testCounter}" name="signStatus[]" title="<?php echo _('Please select the status'); ?>">
					<option value="active"><?php echo _("Active"); ?></option>
					<option value="inactive"><?php echo _("Inactive"); ?></option>
				</select>
			</td>
			<td style="vertical-align:middle;text-align: center;width:10%;">
				<a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addNewRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
				<a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeNewRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
			</td>
		</tr>`;
		$("#signDetails").append(rowString);

		$("#testSignType" + testCounter).multipleSelect({
			placeholder: 'Select Test Type',
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

	function deletedRow(val) {
		deletedRowVar.push(val);
		$('#deletedRow').val(deletedRowVar);
		console.log($('#deletedRow').val());
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
