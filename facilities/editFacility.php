<?php
ob_start();
#require_once('../startup.php'); 
include_once(APPLICATION_PATH . '/header.php');
$general = new \Vlsm\Models\General();
$geolocation = new \Vlsm\Models\GeoLocations();

$usersModel = new \Vlsm\Models\Users();
$userResult = $usersModel->getActiveUsers();

$userInfo = array();
foreach ($userResult as $user) {
	$userInfo[$user['user_id']] = ucwords($user['user_name']);
}

$id = base64_decode($_GET['id']);
$facilityQuery = "SELECT * from facility_details where facility_id=$id";
$facilityInfo = $db->query($facilityQuery);
$fQuery = "SELECT * FROM facility_type";
$fResult = $db->rawQuery($fQuery);
$pQuery = "SELECT * FROM province_details";
$pResult = $db->rawQuery($pQuery);
$chkvlLabQuery = "SELECT * from vl_facility_map as vlfm where vl_lab_id IN(" . $id . ")";
$chkvlLabResult = $db->rawQuery($chkvlLabQuery);
$chkHcQuery = "SELECT * from vl_facility_map as vlfm where facility_id IN(" . $id . ")";
$chkHcResult = $db->rawQuery($chkHcQuery);
$fType = $facilityInfo[0]['facility_type'];
$vlfmQuery = "SELECT GROUP_CONCAT(DISTINCT vlfm.user_id SEPARATOR ',') as userId FROM vl_user_facility_map as vlfm join facility_details as fd ON fd.facility_id=vlfm.facility_id where facility_type = " . $fType;
$vlfmResult = $db->rawQuery($vlfmQuery);
$uQuery = "SELECT * FROM user_details";
if (isset($vlfmResult[0]['userId'])) {
	$exp = explode(",", $vlfmResult[0]['userId']);
	foreach ($exp as $ex) {
		$noUserId[] = "'" . $ex . "'";
	}
	$imp = implode(",", $noUserId);
	$uQuery = $uQuery . " where user_id NOT IN(" . $imp . ")";
}
$uResult = $db->rawQuery($uQuery);
$selectedQuery = "SELECT * FROM vl_user_facility_map as vlfm join user_details as ud ON ud.user_id=vlfm.user_id join facility_details as fd ON fd.facility_id=vlfm.facility_id where vlfm.facility_id = " . $id;
$selectedResult = $db->rawQuery($selectedQuery);

$testTypeQuery = "SELECT * from testing_labs where facility_id=$id";
$testTypeInfo = $db->query($testTypeQuery);

$signQuery = "SELECT * from lab_report_signatories where lab_id=?";
$signResults = $db->rawQuery($signQuery, array($id));
// echo "<pre>";
// print_r($signResults);die;
$editTestType = '';
$div = '';
if (count($testTypeInfo) > 0) {
	$div .= '<table class="table table-bordered table-striped"><thead><th> Test Type</th> <th> Monthly Target <span class="mandatory">*</span></th><th>Suppressed Monthly Target <span class="mandatory">*</span></th> </thead><tbody>';
	$tf = 0;
	foreach ($testTypeInfo as $test) {
		if ($editTestType)
			$editTestType = $editTestType . ',' . $test['test_type'];
		else
			$editTestType = $test['test_type'];

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
$cntId = $general->reportPdfNames();

if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true) {
	$reportFormats['covid19'] = $general->activeReportFormats('covid-19', $cntId['covid19'], null, true);
}
if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true) {
	$reportFormats['eid'] = $general->activeReportFormats('eid', $cntId['eid'], null, true);
}
if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] == true) {
	$reportFormats['vl'] = $general->activeReportFormats('vl', $cntId['vl'], null, true);
}
if ($arr['vl_form'] == 7) {
	if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] == true) {
		$reportFormats['hepatitis'] = $general->activeReportFormats('hepatitis', $cntId['hepatitis'], null, true);
	}
}
if (isset($systemConfig['modules']['tb']) && $systemConfig['modules']['tb'] == true) {
	$reportFormats['tb'] = $general->activeReportFormats('tb', $cntId['tb'], null, true);
}
$formats = json_decode($facilityInfo[0]['report_format'], true);
$labDiv = "none";
if ($facilityInfo[0]['test_type'] == 2) {
	$labDiv = "block";
}
$geoLocationParentArray = $geolocation->fetchActiveGeolocations(0, 0);
$geoLocationChildArray = $geolocation->fetchActiveGeolocations(0, $facilityInfo[0]['facility_state_id']);
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
		<h1><i class="fa fa-hospital-o"></i> Edit Facility</h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
			<li class="active">Facilities</li>
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
				<form class="form-horizontal" method='post' name='editFacilityForm' id='editFacilityForm' autocomplete="off" enctype="multipart/form-data" action="editFacilityHelper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="facilityName" class="col-lg-4 control-label">Facility Name <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="facilityName" name="facilityName" placeholder="Facility Name" title="Please enter facility name" value="<?php echo $facilityInfo[0]['facility_name']; ?>" onblur="checkNameValidation('facility_details','facility_name',this,'<?php echo "facility_id##" . $facilityInfo[0]['facility_id']; ?>','The facility name that you entered already exists.Enter another name',null)" />
										<input type="hidden" class="form-control isRequired" id="facilityId" name="facilityId" value="<?php echo base64_encode($facilityInfo[0]['facility_id']); ?>" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="facilityCode" class="col-lg-4 control-label">Facility Code</label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="facilityCode" name="facilityCode" placeholder="Facility Code" title="Please enter facility code" value="<?php echo $facilityInfo[0]['facility_code']; ?>" onblur="checkNameValidation('facility_details','facility_code',this,'<?php echo "facility_id##" . $facilityInfo[0]['facility_id']; ?>','The code that you entered already exists.Try another code',null)" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="otherId" class="col-lg-4 control-label">Other Id </label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="otherId" name="otherId" placeholder="Other Id" value="<?php echo $facilityInfo[0]['other_id']; ?>" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="facilityType" class="col-lg-4 control-label">Facility Type <span class="mandatory">*</span> </label>
									<div class="col-lg-7">
										<select class="form-control isRequired" id="facilityType" name="facilityType" title="Please select facility type" onchange="<?php echo ($_SESSION['instanceType'] == 'remoteuser') ? 'getFacilityUser()' : ''; ?>;getTestType(); showSignature(this.value);">
											<option value=""> -- Select -- </option>
											<?php
											$k = 10;
											foreach ($fResult as $type) {
											?>
												<option data-disable="<?php echo $k; ?>" value="<?php echo $type['facility_type_id']; ?>" <?php echo ($facilityInfo[0]['facility_type'] == $type['facility_type_id']) ? "selected='selected'" : "" ?>><?php echo ucwords($type['facility_type_name']); ?></option>
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
									<label for="email" class="col-lg-4 control-label">Email(s) </label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="email" name="email" placeholder="eg-email1@gmail.com,email2@gmail.com" value="<?php echo $facilityInfo[0]['facility_emails']; ?>" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="testingPoints" class="col-lg-4 control-label">Testing Point(s)<br> <small>(comma separated)</small> </label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="testingPoints" name="testingPoints" placeholder="eg. VCT, PMTCT" value="<?php echo implode(", ", json_decode($facilityInfo[0]['testing_points'], true)); ?>" />
									</div>
								</div>
							</div>
							<!--<div class="col-md-6">
                    <div class="form-group">
                        <label for="reportEmail" class="col-lg-4 control-label">Report Email(s) </label>
                        <div class="col-lg-7">
                        <textarea class="form-control" id="reportEmail" name="reportEmail" placeholder="eg-user1@gmail.com,user2@gmail.com" rows="3">< ?php echo $facilityInfo[0]['report_email']; ?></textarea>
                        </div>
                    </div>
                  </div>-->
						</div>
						<br>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="Lab Manager" class="col-lg-4 control-label">Lab Manager</label>
									<div class="col-lg-7">
										<select name="contactPerson" id="contactPerson" class="select2 form-control" title="Please choose Lab Manager" style="width: 100%;">
											<?= $general->generateSelectOptions($userInfo, $facilityInfo[0]['contact_person'], '-- Select --'); ?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="phoneNo" class="col-lg-4 control-label">Phone Number</label>
									<div class="col-lg-7">
										<input type="text" class="form-control checkNum" id="phoneNo" name="phoneNo" placeholder="Phone Number" value="<?php echo $facilityInfo[0]['facility_mobile_numbers']; ?>" onblur="checkNameValidation('facility_details','facility_mobile_numbers',this,'<?php echo "facility_id##" . $facilityInfo[0]['facility_id']; ?>','The mobile no that you entered already exists.Enter another mobile no.',null)" />
									</div>
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="state" class="col-lg-4 control-label">Province/State <span class="mandatory">*</span> </label>
									<div class="col-lg-7">
										<?php if (sizeof($geoLocationParentArray) > 0) { ?>
											<select name="stateId" id="stateId" class="form-control isRequired" title="Please choose province/state">
												<?= $general->generateSelectOptions($geoLocationParentArray, $facilityInfo[0]['facility_state_id'], '-- Select --'); ?>
												<option value="other">Other</option>
											</select>
											<input type="text" class="form-control" name="provinceNew" id="provinceNew" placeholder="Enter Province/State" title="Please enter province/state" style="margin-top:4px;display:none;" />
											<input type="hidden" name="state" id="state" value="<?php echo $facilityInfo[0]['facility_state']; ?>" />
										<?php }
										if ((!isset($facilityInfo[0]['facility_state_id']) || $facilityInfo[0]['facility_state_id'] == "") && (isset($facilityInfo[0]['facility_state']) || $facilityInfo[0]['facility_state'] != "")) { ?>
											<input type="text" value="<?php echo $facilityInfo[0]['facility_state']; ?>" class="form-control isRequired" name="oldState" id="oldState" placeholder="Enter Province/State" title="Please enter province/state" />
										<?php } ?>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="district" class="col-lg-4 control-label">District/County <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select name="districtId" id="districtId" class="form-control isRequired" title="Please choose District/County">
											<?= $general->generateSelectOptions($geoLocationChildArray, $facilityInfo[0]['facility_district_id'], '-- Select --'); ?>
											<option value="other">Other</option>
										</select>
										<input type="text" class="form-control" name="districtNew" id="districtNew" placeholder="Enter District/County" title="Please enter District/County" style="margin-top:4px;display:none;" />
										<input type="hidden" id="district" name="district" value="<?php echo $facilityInfo[0]['facility_district']; ?>" />
										<?php if ((!isset($facilityInfo[0]['facility_district_id']) || $facilityInfo[0]['facility_district_id'] == "") && (isset($facilityInfo[0]['facility_district']) || $facilityInfo[0]['facility_district'] != "")) { ?>
											<input type="text" value="<?php echo $facilityInfo[0]['facility_district']; ?>" class="form-control isRequired" name="oldDistrict" id="oldDistrict" placeholder="Enter District/County" title="Please enter district/county" />
										<?php } ?>
									</div>
								</div>
							</div>

						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="hubName" class="col-lg-4 control-label">Linked Hub Name (If Applicable)</label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="hubName" name="hubName" placeholder="Hub Name" title="Please enter hub name" value="<?php echo $facilityInfo[0]['facility_hub_name']; ?>" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="address" class="col-lg-4 control-label">Address</label>
									<div class="col-lg-7">
										<textarea class="form-control" name="address" id="address" placeholder="Address"><?php echo $facilityInfo[0]['address']; ?></textarea>
									</div>
								</div>
							</div>

						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="country" class="col-lg-4 control-label">Country</label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="country" name="country" placeholder="Country" value="<?php echo $facilityInfo[0]['country']; ?>" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="latitude" class="col-lg-4 control-label">Latitude</label>
									<div class="col-lg-7">
										<input type="text" class="form-control checkNum" id="latitude" name="latitude" placeholder="Latitude" title="Please enter latitude" value="<?php echo $facilityInfo[0]['latitude']; ?>" />
									</div>
								</div>
							</div>

						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="longitude" class="col-lg-4 control-label">Longitude</label>
									<div class="col-lg-7">
										<input type="text" class="form-control checkNum" id="longitude" name="longitude" placeholder="Longitude" title="Please enter longitude" value="<?php echo $facilityInfo[0]['longitude']; ?>" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="status" class="col-lg-4 control-label">Status <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='status' id='status' title="Please select the status">
											<option value=""> -- Select -- </option>
											<option value="active" <?php echo ($facilityInfo[0]['status'] == 'active') ? "selected='selected'" : "" ?>>Active</option>
											<option value="inactive" <?php echo ($facilityInfo[0]['status'] == 'inactive') ? "selected='selected'" : "" ?>>Inactive</option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="testType" class="col-lg-4 control-label">Test Type</label>
									<div class="col-lg-7">
										<select type="text" class="" id="testType" name="testType[]" title="Choose one test type" onchange="getTestType();" multiple>
											<?php if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] == true) { ?>
												<option value='vl' <?php echo (preg_match("/vl/i", $facilityInfo[0]['test_type'])) ? "selected='selected'" : '';  ?>>Viral Load</option>
											<?php }
											if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true) { ?>
												<option value='eid' <?php echo (preg_match("/eid/i", $facilityInfo[0]['test_type'])) ? "selected='selected'" : '';  ?>>Early Infant Diagnosis</option>
											<?php }
											if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true) { ?>
												<option value='covid19' <?php echo (preg_match("/covid19/i", $facilityInfo[0]['test_type'])) ? "selected='selected'" : '';  ?>>Covid-19</option>
											<?php }
											if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] == true) { ?>
												<option value='hepatitis' <?php echo (preg_match("/hepatitis/i", $facilityInfo[0]['test_type'])) ? "selected='selected'" : '';  ?>>Hepatitis</option>
											<?php }
											if (isset($systemConfig['modules']['tb']) && $systemConfig['modules']['tb'] == true) { ?>
												<option value='tb' <?php echo (preg_match("/tb/i", $facilityInfo[0]['test_type'])) ? "selected='selected'" : '';  ?>>Tb</option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6 availablePlatforms" style="display:none;">
								<div class="form-group">
									<label for="availablePlatforms" class="col-lg-4 control-label">Available Platforms</label>
									<div class="col-lg-7">
										<select type="text" id="availablePlatforms" name="availablePlatforms[]" title="Choose one Available Platforms" multiple>
											<option value="microscopy" <?php echo (preg_match("/microscopy/i", $testTypeInfo[0]['attributes'])) ? "selected='selected'" : '';  ?>>Microscopy</option>
											<option value="xpert" <?php echo (preg_match("/xpert/i", $testTypeInfo[0]['attributes'])) ? "selected='selected'" : '';  ?>>Xpert</option>
											<option value="lam" <?php echo (preg_match("/lam/i", $testTypeInfo[0]['attributes'])) ? "selected='selected'" : '';  ?>>Lam</option>

										</select>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="row labDiv" style="display:<?php echo $labDiv; ?>;">
						<?php if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] == true) {
							$count = sizeof($reportFormats['vl']); ?>
							<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
								<div class="form-group">
									<label for="reportFormat" class="col-lg-4 control-label">Report Format For VL</label>
									<div class="col-lg-7">
										<select class="form-control" name='reportFormat[vl]' id='reportFormat' title="Please select the status" onchange="checkIfExist(this);">
											<?php if (($count > 1)) { ?>
												<option value="">-- Select --</option>
											<?php } ?>
											<?php foreach ($reportFormats['vl'] as $key => $value) { ?>
												<option value="<?php echo $key; ?>" <?php echo ($formats['vl'] == $key) ? "selected='selected'" : ""; ?>><?php echo ucwords($value); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
						<?php }
						if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true) {
							$count = sizeof($reportFormats['eid']); ?>
							<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
								<div class="form-group">
									<label for="reportFormat" class="col-lg-4 control-label">Report Format For EID</label>
									<div class="col-lg-7">
										<select class="form-control" name='reportFormat[eid]' id='reportFormat' title="Please select the status" onchange="checkIfExist(this);">
											<?php if (($count > 1)) { ?>
												<option value="">-- Select --</option>
											<?php } ?>
											<?php foreach ($reportFormats['eid'] as $key => $value) { ?>
												<option value="<?php echo $key; ?>" <?php echo ($formats['eid'] == $key) ? "selected='selected'" : ""; ?>><?php echo ucwords($value); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
						<?php }
						if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true) {
							$count = sizeof($reportFormats['covid19']); ?>
							<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
								<div class="form-group">
									<label for="reportFormat" class="col-lg-4 control-label">Report Format For Covid-19</label>
									<div class="col-lg-7">
										<select class="form-control" name='reportFormat[covid19]' id='reportFormat' title="Please select the status" onchange="checkIfExist(this);">
											<?php if (($count > 1)) { ?>
												<option value="">-- Select --</option>
											<?php } ?>
											<?php foreach ($reportFormats['covid19'] as $key => $value) { ?>
												<option value="<?php echo $key; ?>" <?php echo ($formats['covid19'] == $key) ? "selected='selected'" : ""; ?>><?php echo ucwords($value); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
						<?php }
						if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] == true) {
							$count = sizeof($reportFormats['hepatitis']); ?>
							<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
								<div class="form-group">
									<label for="reportFormat" class="col-lg-4 control-label">Report Format For Hepatitis</label>
									<div class="col-lg-7">
										<select class="form-control" name='reportFormat[hepatitis]' id='reportFormat' title="Please select the status" onchange="checkIfExist(this);">
											<?php if (($count > 1)) { ?>
												<option value="">-- Select --</option>
											<?php } ?>
											<?php foreach ($reportFormats['hepatitis'] as $key => $value) { ?>
												<option value="<?php echo $key; ?>" <?php echo ($formats['hepatitis'] == $key) ? "selected='selected'" : ""; ?>><?php echo ucwords($value); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
						<?php }
						if (isset($systemConfig['modules']['tb']) && $systemConfig['modules']['tb'] == true) {
							$count = sizeof($reportFormats['tb']); ?>
							<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
								<div class="form-group">
									<label for="reportFormat" class="col-lg-4 control-label">Report Format For Tb</label>
									<div class="col-lg-7">
										<select class="form-control" name='reportFormat[tb]' id='reportFormat' title="Please select the status" onchange="checkIfExist(this);">
											<?php if (($count > 1)) { ?>
												<option value="">-- Select --</option>
											<?php } ?>
											<?php foreach ($reportFormats['tb'] as $key => $value) { ?>
												<option value="<?php echo $key; ?>" <?php echo ($formats['tb'] == $key) ? "selected='selected'" : ""; ?>><?php echo ucwords($value); ?></option>
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
								<label for="" class="col-lg-4 control-label">Logo Image </label>
								<div class="col-lg-8">
									<div class="fileinput fileinput-new labLogo" data-provides="fileinput">
										<div class="fileinput-preview thumbnail" data-trigger="fileinput" style="width:200px; height:150px;">
											<?php

											if (isset($facilityInfo[0]['facility_logo']) && trim($facilityInfo[0]['facility_logo']) != '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $facilityInfo[0]['facility_id'] . DIRECTORY_SEPARATOR . $facilityInfo[0]['facility_logo'])) {
											?>
												<img src=".././uploads/facility-logo/<?php echo $facilityInfo[0]['facility_id']; ?>/<?php echo $facilityInfo[0]['facility_logo']; ?>" alt="Logo image">
											<?php } else { ?>
												<img src="http://www.placehold.it/200x150/EFEFEF/AAAAAA&text=No image">
											<?php } ?>
										</div>
										<div>
											<span class="btn btn-default btn-file"><span class="fileinput-new">Select image</span><span class="fileinput-exists">Change</span>
												<input type="file" id="labLogo" name="labLogo" title="Please select logo image" onchange="getNewLabImage('<?php echo $facilityInfo[0]['facility_logo']; ?>');">
											</span>
											<?php
											if (isset($facilityInfo[0]['facility_logo']) && trim($facilityInfo[0]['facility_logo']) != '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $facilityInfo[0]['facility_id'] . DIRECTORY_SEPARATOR . $facilityInfo[0]['facility_logo'])) {
											?>
												<a id="clearLabImage" href="javascript:void(0);" class="btn btn-default" data-dismiss="fileupload" onclick="clearLabImage('<?php echo $facilityInfo[0]['facility_logo']; ?>')">Clear</a>
											<?php } ?>
											<a href="#" class="btn btn-default fileinput-exists" data-dismiss="fileinput">Remove</a>
										</div>
									</div>
									<div class="box-body">
										Please make sure logo image size of: <code>80x80</code>
									</div>
								</div>
							</div>
						</div>
						<!-- <div class="col-md-6">
							<div class="form-group">
								<label for="stampLogo" class="col-lg-4 control-label">Logo Image </label>
								<div class="col-lg-8">
									<div class="fileinput fileinput-new stampLogo" data-provides="fileinput">
										<div class="fileinput-preview thumbnail" data-trigger="fileinput" style="width:200px; height:150px;">
											<?php

											if (isset($facilityInfo[0]['facility_logo']) && trim($facilityInfo[0]['facility_logo']) != '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $facilityInfo[0]['facility_id'] . DIRECTORY_SEPARATOR . $facilityInfo[0]['facility_logo'])) {
											?>
												<img src=".././uploads/facility-logo/<?php echo $facilityInfo[0]['facility_id']; ?>/<?php echo $facilityInfo[0]['facility_logo']; ?>" alt="Logo image">
											<?php } else { ?>
												<img src="http://www.placehold.it/200x150/EFEFEF/AAAAAA&text=No image">
											<?php } ?>
										</div>
										<div>
											<span class="btn btn-default btn-file"><span class="fileinput-new">Select image</span><span class="fileinput-exists">Change</span>
												<input type="file" id="stampLogo" name="stampLogo" title="Please select logo image" onchange="getNewLabImage('<?php echo $facilityInfo[0]['facility_logo']; ?>');">
											</span>
											<?php
											if (isset($facilityInfo[0]['facility_logo']) && trim($facilityInfo[0]['facility_logo']) != '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "facility-logo" . DIRECTORY_SEPARATOR . $facilityInfo[0]['facility_id'] . DIRECTORY_SEPARATOR . $facilityInfo[0]['facility_logo'])) {
											?>
												<a id="clearLabImage" href="javascript:void(0);" class="btn btn-default" data-dismiss="fileupload" onclick="clearLabImage('<?php echo $facilityInfo[0]['facility_logo']; ?>')">Clear</a>
											<?php } ?>
											<a href="#" class="btn btn-default fileinput-exists" data-dismiss="fileinput">Remove</a>
										</div>
									</div>
									<div class="box-body">
										Please make sure logo image size of: <code>80x80</code>
									</div>
								</div>
							</div>
						</div> -->
						<div class="col-md-6">
							<div class="form-group">
								<label for="" class="col-lg-4 control-label">Header Text</label>
								<div class="col-lg-7">
									<input type="text" class="form-control " id="headerText" name="headerText" placeholder="Header Text" title="Please enter header text" value="<?php echo $facilityInfo[0]['header_text']; ?>" />
								</div>
							</div>
						</div>
					</div>

					<div class="row labDiv" style="display:<?php echo $labDiv; ?>;">
						<table class="table table-bordered">
							<thead>
								<tr>
									<th>Name</th>
									<th>Designation</th>
									<th>Upload Sign</th>
									<th>Test Types</th>
									<th>Display Order</th>
									<th>Status</th>
									<th>Action</th>
								</tr>
							</thead>
							<tbody id="signDetails">
								<?php if (isset($signResults) && !empty($signResults)) {
									foreach ($signResults as $key => $row) { ?>
										<tr>
											<td style="width:14%;"><input type="hidden" name="signId[]" id="signId<?php echo ($key + 1); ?>" value="<?php echo $row['signatory_id'] ?>" /><input value="<?php echo $row['name_of_signatory'] ?>" type="text" class="form-control" name="signName[]" id="signName<?php echo ($key + 1); ?>" placeholder="Name" title="Please enter the name"></td>
											<td style="width:14%;"><input value="<?php echo $row['designation'] ?>" type="text" class="form-control" name="designation[]" id="designation<?php echo ($key + 1); ?>" placeholder="Designation" title="Please enter the Designation"></td>
											<td style="width:10%;">
												<?php $lmSign = "/uploads/labs/" . $row['lab_id'] . "/signatures/" . $row['signature'];
												$show = "style='display:block'";
												if (isset($row['signature']) && $row['signature'] != "") {
													$show = "style='display:none'";
												?>
													<span id="spanClass<?php echo ($key + 1); ?>"><a href="javascript:void(0);" onclick="showFile(<?php echo ($key + 1); ?>);"><span class="alert-danger" style="padding: 5px;border-radius: 50%;margin-top: 0px;float: right;">X</span></a><img src="<?php echo $lmSign; ?>" style="width: 100px;" /></span>
												<?php }
												?>
												<input <?php echo $show; ?> class="showFile<?php echo ($key + 1); ?>" type="file" name="signature[]" id="signature<?php echo ($key + 1); ?>" placeholder="Signature" title="Please enter the Signature">
											</td>
											<td style="width:14%;">
												<select type="text" class="select2" id="testSignType<?php echo ($key + 1); ?>" name="testSignType[<?php echo ($key + 1); ?>][]" title="Choose one test type" multiple>
													<option value="vl" <?php echo (isset($row['test_types']) && in_array("vl", explode(",", $row['test_types']))) ? 'selected="selected"' : ''; ?>>Viral Load</option>
													<option value="eid" <?php echo (isset($row['test_types']) && in_array("eid", explode(",", $row['test_types']))) ? 'selected="selected"' : ''; ?>>Early Infant Diagnosis</option>
													<option value="covid19" <?php echo (isset($row['test_types']) && in_array("covid19", explode(",", $row['test_types']))) ? 'selected="selected"' : ''; ?>>Covid-19</option>
													<option value='hepatitis' <?php echo (isset($row['test_types']) && in_array("hepatitis", explode(",", $row['test_types']))) ? 'selected="selected"' : ''; ?>>Hepatitis</option>
													<option value='tb' <?php echo (isset($row['test_types']) && in_array("tb", explode(",", $row['test_types']))) ? 'selected="selected"' : ''; ?>>TB</option>
												</select>
											</td>
											<td style="width:14%;"><input value="<?php echo $row['display_order'] ?>" type="text" class="form-control" name="sortOrder[]" id="sortOrder<?php echo ($key + 1); ?>" placeholder="Display Order" title="Please enter the Display Order"></td>
											<td style="width:14%;">
												<select class="form-control" id="signStatus<?php echo ($key + 1); ?>" name="signStatus[]" title="Please select the status">
													<option value="active" <?php echo (isset($row['test_types']) && $row['test_types'] == 'active') ? 'selected="selected"' : ''; ?>>Active</option>
													<option value="inactive" <?php echo (isset($row['test_types']) && $row['test_types'] == 'inactive') ? 'selected="selected"' : ''; ?>>Inactive</option>
												</select>
											</td>
											<td style="vertical-align:middle;text-align: center;width:10%;">
												<a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addNewRow();"><i class="fa fa-plus"></i></a>&nbsp;
												<a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeNewRow(this.parentNode.parentNode);deletedRow(<?php echo $row['signatory_id'] ?>);"><i class="fa fa-minus"></i></a>
											</td>
										</tr>
									<?php }
								} else { ?>
									<tr>
										<td style="width:14%;"><input type="text" class="form-control" name="signName[]" id="signName1" placeholder="Name" title="Please enter the name"></td>
										<td style="width:14%;"><input type="text" class="form-control" name="designation[]" id="designation1" placeholder="Designation" title="Please enter the Designation"></td>
										<td style="width:10%;"><input type="file" name="signature[]" id="signature1" placeholder="Signature" title="Please enter the Signature"></td>
										<td style="width:14%;">
											<select type="text" class="select2" id="testSignType1" name="testSignType[1][]" title="Choose one test type" multiple>
												<option value="vl">Viral Load</option>
												<option value="eid">Early Infant Diagnosis</option>
												<option value="covid19">Covid-19</option>
												<option value='hepatitis'>Hepatitis</option>
											</select>
										</td>
										<td style="width:14%;"><input type="text" class="form-control" name="sortOrder[]" id="sortOrder1" placeholder="Display Order" title="Please enter the Display Order"></td>
										<td style="width:14%;">
											<select class="form-control" id="signStatus1" name="signStatus[]" title="Please select the status">
												<option value="active">Active</option>
												<option value="inactive">Inactive</option>
											</select>
										</td>
										<td style="vertical-align:middle;text-align: center;width:10%;">
											<a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addNewRow();"><i class="fa fa-plus"></i></a>&nbsp;
											<a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeNewRow(this.parentNode.parentNode);"><i class="fa fa-minus"></i></a>
										</td>
									</tr>
								<?php } ?>
							</tbody>
						</table>
					</div>

					<div class="row" id="userDetails">
						<?php if (($facilityInfo[0]['facility_type'] == 1 || $facilityInfo[0]['facility_type'] == 4) && $_SESSION['instanceType'] == 'remoteuser') { ?>
							<h4>User Facility Map Details</h4>
							<div class="col-xs-5">
								<select name="from[]" id="search" class="form-control" size="8" multiple="multiple">
									<?php
									foreach ($uResult as $uName) {
									?>
										<option value="<?php echo $uName['user_id']; ?>"><?php echo ucwords($uName['user_name']); ?></option>
									<?php
									}
									?>
								</select>
							</div>

							<div class="col-xs-2">
								<button type="button" id="search_rightAll" class="btn btn-block"><i class="glyphicon glyphicon-forward"></i></button>
								<button type="button" id="search_rightSelected" class="btn btn-block"><i class="glyphicon glyphicon-chevron-right"></i></button>
								<button type="button" id="search_leftSelected" class="btn btn-block"><i class="glyphicon glyphicon-chevron-left"></i></button>
								<button type="button" id="search_leftAll" class="btn btn-block"><i class="glyphicon glyphicon-backward"></i></button>
							</div>

							<div class="col-xs-5">
								<select name="to[]" id="search_to" class="form-control" size="8" multiple="multiple">
									<?php
									foreach ($selectedResult as $uName) {
									?>
										<option value="<?php echo $uName['user_id']; ?>" selected="selected"><?php echo ucwords($uName['user_name']); ?></option>
									<?php
									}
									?>
								</select>
							</div>
						<?php } ?>
					</div>
					<div class="row" id="testDetails" style="display:none;">
						<?php echo $div; ?>
					</div>
			</div>
			<!-- /.box-body -->
			<div class="box-footer">
				<input type="hidden" name="selectedUser" id="selectedUser" />
				<input type="hidden" name="removedLabLogoImage" id="removedLabLogoImage" />
				<input type="hidden" name="deletedRow" id="deletedRow" />
				<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
				<a href="facilities.php" class="btn btn-default"> Cancel</a>
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
		$("#testType").multipleSelect({
			placeholder: 'Select Test Type',
			width: '100%'
		});
		$(".select2").select2({
			placeholder: 'Select Lab Manager',
			width: '150px'
		});
		$("#availablePlatforms").multipleSelect({
			placeholder: 'Select Available Platforms',
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
						dName: '<?php echo (isset($facilityInfo[0]['facility_district_id']) && $facilityInfo[0]['facility_district_id'] != "") ? trim($facilityInfo[0]['facility_district_id']) : trim($facilityInfo[0]['facility_district']); ?>'
					},
					function(data) {
						if (data != "") {
							details = data.split("###");
							$("#districtId").html(details[1]);
							$("#districtId").append('<option value="other">Other</option>');
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
					fType: $("#facilityType").val()
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
				var div = '<table class="table table-bordered table-striped"><thead><th> Test Type</th> <th> Monthly Target <span class="mandatory">*</span></th><th>Suppressed Monthly Target <span class="mandatory">*</span></th> </thead><tbody>';
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
						var extraDiv = '<td><input type="text" class="" name="supMonTar[]" id ="supMonTar' + i + '" value="' + supM + '" title="Please enter Suppressed monthly target"/></td>';
					} else if (testType[i] == 'eid') {
						testOrg = 'Early Infant Diagnosis';
						var extraDiv = '<td></td>';
					} else if (testType[i] == 'covid19') {
						testOrg = 'Covid-19';
						var extraDiv = '<td></td>';
					}
					div += '<tr><td>' + testOrg + '<input type="hidden" name="testData[]" id ="testData' + i + '" value="' + testType[i] + '" /></td>';
					div += '<td><input type="text" class="" name="monTar[]" id ="monTar' + i + '" value="' + oldMonTar + '" title="Please enter monthly target"/></td>';
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
	}

	let testCounter = document.getElementById("signDetails").rows.length;

	function addNewRow() {
		testCounter++;
		let rowString = `<tr>
			<td style="width:14%;"><input type="text" class="form-control" name="signName[]" id="signName${testCounter}" placeholder="Name" title="Please enter the name"></td>
			<td style="width:14%;"><input type="text" class="form-control" name="designation[]" id="designation${testCounter}" placeholder="Designation" title="Please enter the Designation"></td>
			<td style="width:14%;"><input type="file" name="signature[]" id="signature${testCounter}" placeholder="Signature" title="Please enter the Signature"></td>
			<td style="width:14%;">
				<select type="text" class="select2" id="testSignType${testCounter}" name="testSignType[${testCounter}][]" title="Choose one test type" multiple>
					<option value="vl">Viral Load</option>
					<option value="eid">Early Infant Diagnosis</option>
					<option value="covid19">Covid-19</option>
					<option value='hepatitis'>Hepatitis</option>
					<option value='tb'>TB</option>
				</select>
			</td>
			<td style="width:14%;"><input type="text" class="form-control" name="sortOrder[]" id="sortOrder${testCounter}" placeholder="Display Order" title="Please enter the Display Order"></td>
			<td style="width:14%;">
				<select class="form-control" id="signStatus${testCounter}" name="signStatus[]" title="Please select the status">
					<option value="active">Active</option>
					<option value="inactive">Inactive</option>
				</select>
			</td>
			<td style="vertical-align:middle;text-align: center;width:10%;">
				<a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addNewRow();"><i class="fa fa-plus"></i></a>&nbsp;
				<a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeNewRow(this.parentNode.parentNode);"><i class="fa fa-minus"></i></a>
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
include(APPLICATION_PATH . '/footer.php');
?>