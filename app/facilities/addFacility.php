<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\GeoLocationsService;
use App\Services\UsersService;



require_once APPLICATION_PATH . '/header.php';
/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

$fQuery = "SELECT * FROM facility_type";
$fResult = $db->rawQuery($fQuery);
$pResult = $general->fetchDataFromTable('geographical_divisions', "geo_parent = 0 AND geo_status='active'");

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$userResult = $usersService->getAllUsers();

$userInfo = [];
foreach ($userResult as $user) {
	if (!empty($user['user_name'])) {
		$userInfo[$user['user_id']] = ($user['user_name']);
	}
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
if (isset(SYSTEM_CONFIG['modules']['generic-tests']) && SYSTEM_CONFIG['modules']['generic-tests'] === true) {
	$reportFormats['generic-tests'] = $general->activeReportFormats('generic-tests');
}
$geoLocationParentArray = $geolocationService->fetchActiveGeolocations();
$formId = (int) $general->getGlobalConfig('vl_form');
?>
<style>
	.ms-choice {
		border: 0px solid #aaa;
	}
</style>
<link href="/assets/css/jasny-bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="/assets/css/jquery.multiselect.css" type="text/css" />

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-hospital"></em>
			<?php echo _translate("Add Facility"); ?>
		</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em>
					<?php echo _translate("Home"); ?>
				</a></li>
			<li class="active">
				<?php echo _translate("Facilities"); ?>
			</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span>
					<?php echo _translate("indicates required fields"); ?> &nbsp;
				</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='addFacilityForm' id='addFacilityForm' autocomplete="off" enctype="multipart/form-data" action="addFacilityHelper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="facilityName" class="col-lg-4 control-label">
										<?php echo _translate("Facility Name"); ?> <span class="mandatory">*</span>
									</label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="facilityName" name="facilityName" placeholder="<?php echo _translate('Facility Name'); ?>" title="<?php echo _translate('Please enter facility name'); ?>" onblur='checkNameValidation("facility_details","facility_name",this,null,"<?php echo _translate("The facility name that you entered already exists.Enter another name"); ?>",null)' />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="facilityCode" class="col-lg-4 control-label">
										<?php echo _translate("Facility Code"); ?><br> <small>
											<?php echo _translate("(National Unique Code)"); ?>
										</small>
									</label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="facilityCode" name="facilityCode" placeholder="<?php echo _translate('Facility Code'); ?>" title="<?php echo _translate('Please enter facility code'); ?>" onblur='checkNameValidation("facility_details","facility_code",this,null,"<?php echo _translate("The code that you entered already exists.Try another code"); ?>",null)' />
									</div>
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="otherId" class="col-lg-4 control-label">
										<?php echo _translate("Other/External Code"); ?>
									</label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="otherId" name="otherId" placeholder="<?php echo _translate('Other/External Code'); ?>" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="facilityType" class="col-lg-4 control-label">
										<?php echo _translate("Facility Type"); ?> <span class="mandatory">*</span>
									</label>
									<div class="col-lg-7">
										<select class="form-control isRequired" id="facilityType" name="facilityType" title="<?php echo _translate('Please select facility type'); ?>" onchange="getTestType(); showSignature(this.value);">
											<option value="">
												<?php echo _translate("-- Select --"); ?>
											</option>
											<?php
											foreach ($fResult as $type) {
											?>
												<option value="<?php echo $type['facility_type_id']; ?>"><?php echo ($type['facility_type_name']); ?></option>
											<?php
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
									<label for="email" class="col-lg-4 control-label">
										<?php echo _translate("Email(s)"); ?> <br> <small>
											<?php echo _translate("(comma separated)"); ?>
										</small>
									</label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="email" name="email" placeholder="e.g. email1@gmail.com, email2@gmail.com" />
									</div>
								</div>
							</div>
							<div class="col-md-6 allowResultsUpload" style="display:none;">
								<div class="form-group">
									<label for="allowResultUpload" class="col-lg-4 control-label">
										<?php echo _translate("Allow Results File Upload?"); ?> <span class="mandatory">*</span>
									</label>
									<div class="col-lg-7">
										<select class="form-control" id="allowResultUpload" name="allowResultUpload" title="<?php echo _translate('Please select if this lab can upload test results file'); ?>">
											<option value="">
												<?php echo _translate("-- Select --"); ?>
											</option>
											<option value="yes" "selected='selected'">yes</option>
											<option value="no">no</option>
										</select>
									</div>
								</div>
							</div>

							<!--<div class="col-md-6">
					<div class="form-group">
						<label for="reportEmail" class="col-lg-4 control-label">Report Email(s) </label>
						<div class="col-lg-7">
						<textarea class="form-control" id="reportEmail" name="reportEmail" placeholder="eg-user1@gmail.com,user2@gmail.com" rows="3"></textarea>
						</div>
					</div>
				  </div>-->
						</div>
						<br>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="testingPoints" class="col-lg-4 control-label">
										<?php echo _translate("Testing Point(s)"); ?><br> <small>
											<?php echo _translate("(comma separated)"); ?>
										</small>
									</label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="testingPoints" name="testingPoints" placeholder="<?php echo _translate('eg. VCT, PMTCT'); ?>" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="Lab Manager" class="col-lg-4 control-label">
										<?php echo _translate("Lab Manager"); ?>
									</label>
									<div class="col-lg-7">
										<select name="contactPerson" id="contactPerson" class="contactPerson form-control" title="<?php echo _translate('Please choose the Lab Manager'); ?>" style="width: 100% !important;">
											<?= $general->generateSelectOptions($userInfo, null, _translate("-- Select --")); ?>
										</select>
									</div>
								</div>
							</div>

						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="phoneNo" class="col-lg-4 control-label">
										<?php echo _translate("Phone Number"); ?>
									</label>
									<div class="col-lg-7">
										<input type="text" class="form-control phone-number" id="phoneNo" name="phoneNo" placeholder="<?php echo _translate('Phone Number'); ?>" onblur='checkNameValidation("facility_details","facility_mobile_numbers",this,null,"<?php echo _translate("The mobile no that you entered already exists.Enter another mobile no."); ?>",null)' />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="state" class="col-lg-4 control-label">
										<?php echo _translate("Province/State"); ?> <span class="mandatory">*</span>
									</label>
									<div class="col-lg-7">
										<?php if (sizeof($geoLocationParentArray) > 0) { ?>
											<select name="stateId" id="stateId" class="form-control isRequired" title="<?php echo _translate('Please choose province/state'); ?>">
												<?= $general->generateSelectOptions($geoLocationParentArray, null, _translate("-- Select --")); ?>
												<option value="other">
													<?php echo _translate("Other"); ?>
												</option>
											</select>
											<input type="text" class="form-control" name="provinceNew" id="provinceNew" placeholder="<?php echo _translate('Enter Province/State'); ?>" title="<?php echo _translate('Please enter province/state'); ?>" style="margin-top:4px;display:none;" />
											<input type="hidden" name="state" id="state" />
										<?php } else { ?>
											<input type="text" class="form-control" name="provinceNew" id="provinceNew" placeholder="<?php echo _translate('Enter Province/State'); ?>" title="<?php echo _translate('Please enter province/state'); ?>" style="margin-top:4px;" />
										<?php } ?>
									</div>
								</div>
							</div>



							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label for="district" class="col-lg-4 control-label">
											<?php echo _translate("District/County"); ?> <span class="mandatory">*</span>
										</label>
										<div class="col-lg-7">
											<select name="districtId" id="districtId" class="form-control isRequired" title="<?php echo _translate('Please choose District/County'); ?>">
												<option value="">
													<?php echo _translate("-- Select --"); ?>
												</option>
												<option value="other">
													<?php echo _translate("Other"); ?>
												</option>
											</select>
											<input type="text" class="form-control" name="districtNew" id="districtNew" placeholder="<?php echo _translate('Enter District/County'); ?>" title="<?php echo _translate('Please enter District/County'); ?>" style="margin-top:4px;display:none;" />
											<input type="hidden" id="district" name="district" />
										</div>
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-group">
										<label for="hubName" class="col-lg-4 control-label">
											<?php echo _translate("Linked Hub Name (If applicable)"); ?>
										</label>
										<div class="col-lg-7">
											<input type="text" class="form-control" id="hubName" name="hubName" placeholder="<?php echo _translate('Hub Name'); ?>" title="<?php echo _translate('Please enter hub name'); ?>" />
										</div>
									</div>
								</div>

							</div>
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label for="address" class="col-lg-4 control-label">
											<?php echo _translate("Address"); ?>
										</label>
										<div class="col-lg-7">
											<textarea class="form-control" name="address" id="address" placeholder="<?php echo _translate('Address'); ?>"></textarea>
										</div>
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-group">
										<label for="country" class="col-lg-4 control-label">
											<?php echo _translate("Country"); ?>
										</label>
										<div class="col-lg-7">
											<input type="text" class="form-control" id="country" name="country" placeholder="<?php echo _translate('Country'); ?>" />
										</div>
									</div>
								</div>

							</div>
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label for="latitude" class="col-lg-4 control-label">
											<?php echo _translate("Latitude"); ?>
										</label>
										<div class="col-lg-7">
											<input type="text" class="form-control forceNumeric" id="latitude" name="latitude" placeholder="<?php echo _translate('Latitude'); ?>" title="<?php echo _translate('Please enter latitude'); ?>" />
										</div>
									</div>
								</div>
								<div class="col-md-6">
									<div class="form-group">
										<label for="longitude" class="col-lg-4 control-label">
											<?php echo _translate("Longitude"); ?>
										</label>
										<div class="col-lg-7">
											<input type="text" class="form-control forceNumeric" id="longitude" name="longitude" placeholder="<?php echo _translate('Longitude'); ?>" title="<?php echo _translate('Please enter longitude'); ?>" />
										</div>
									</div>
								</div>

							</div>
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label for="testType" class="col-lg-4 control-label test-type">
											<?php echo _translate("Test Type"); ?>
										</label>
										<div class="col-lg-7">
											<select class="" id="testType" name="testType[]" title="<?php echo _translate('Choose at least one test type'); ?>" onchange="getTestType();" multiple>
												<option value=""><?php echo _translate("Select Test Types"); ?></option>

												<?php if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) { ?>
													<option value="vl">
														<?php echo _translate("Viral Load"); ?>
													</option>
												<?php }
												if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) { ?>
													<option value="eid">
														<?php echo _translate("Early Infant Diagnosis"); ?>
													</option>
												<?php }
												if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) { ?>
													<option value="covid19">
														<?php echo _translate("Covid-19"); ?>
													</option>
												<?php }
												if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) { ?>
													<option value='hepatitis'>
														<?php echo _translate("Hepatitis"); ?>
													</option>
												<?php }
												if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) { ?>
													<option value='tb'>
														<?php echo _translate("TB"); ?>
													</option>
												<?php }
												if (isset(SYSTEM_CONFIG['modules']['cd4']) && SYSTEM_CONFIG['modules']['cd4'] === true) { ?>
													<option value='cd4'>
														<?php echo _translate("CD4"); ?>
													</option>
												<?php }
												if (isset(SYSTEM_CONFIG['modules']['generic-tests']) && SYSTEM_CONFIG['modules']['generic-tests'] === true) { ?>
													<option value='generic-tests'>
														<?php echo _translate("Other Lab Tests"); ?>
													</option>
												<?php } ?>
											</select>
										</div>
									</div>
								</div>
								<div class="col-md-6 availablePlatforms" style="display:none;">
									<div class="form-group">
										<label for="availablePlatforms" class="col-lg-4 control-label">
											<?php echo _translate("Available Platforms"); ?>
										</label>
										<div class="col-lg-7">
											<select id="availablePlatforms" name="availablePlatforms[]" title="<?php echo _translate('Choose one Available Platforms'); ?>" multiple>
												<option value="microscopy">
													<?php echo _translate("Microscopy"); ?>
												</option>
												<option value="xpert">
													<?php echo _translate("Xpert"); ?>
												</option>
												<option value="lam">
													<?php echo _translate("LAM"); ?>
												</option>
											</select>
										</div>
									</div>
								</div>
							</div>
							<div class="row labDiv" style="display:none;">
								<?php if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) {
									$count = sizeof($reportFormats['vl']); ?>
									<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
										<div class="form-group">
											<label for="reportFormat" class="col-lg-4 control-label">
												<?php echo _translate("Report Format For VL"); ?>
											</label>
											<div class="col-lg-7">
												<select class="form-control" name='reportFormat[vl]' id='reportFormat' title="<?php echo _translate('Please select the status'); ?>" onchange="checkIfExist(this);">
													<?php if ($count > 1) { ?>
														<option value="">
															<?php echo _translate("-- Select --"); ?>
														</option>
													<?php } ?>
													<?php foreach ($reportFormats['vl'] as $key => $value) {
														foreach ($value as $k => $v) { ?>
															<option value="<?php echo $k; ?>"><?php echo ($v); ?></option>
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
											<label for="reportFormat" class="col-lg-4 control-label">
												<?php echo _translate("Report Format For EID"); ?>
											</label>
											<div class="col-lg-7">
												<select class="form-control" name='reportFormat[eid]' id='reportFormat' title="<?php echo _translate('Please select the status'); ?>" onchange="checkIfExist(this);">
													<?php if (($count > 1)) { ?>
														<option value="">
															<?php echo _translate("-- Select --"); ?>
														</option>
													<?php } ?>
													<?php foreach ($reportFormats['eid'] as $key => $value) {
														foreach ($value as $k => $v) { ?>
															<option value="<?php echo $k; ?>"><?php echo ($v); ?></option>
													<?php
														}
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
											<label for="reportFormat" class="col-lg-4 control-label">
												<?php echo _translate("Report Format For Covid-19"); ?>
											</label>
											<div class="col-lg-7">
												<select class="form-control" name='reportFormat[covid19]' id='reportFormat' title="<?php echo _translate('Please select the status'); ?>" onchange="checkIfExist(this);">
													<?php if (($count > 1)) { ?>
														<option value="">
															<?php echo _translate("-- Select --"); ?>
														</option>
													<?php } ?>
													<?php foreach ($reportFormats['covid19'] as $key => $value) {
														foreach ($value as $k => $v) { ?>
															<option value="<?php echo $k; ?>"><?php echo ($v); ?></option>
													<?php
														}
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
											<label for="reportFormat" class="col-lg-4 control-label">
												<?php echo _translate("Report Format For Hepatitis"); ?>
											</label>
											<div class="col-lg-7">
												<select class="form-control" name='reportFormat[hepatitis]' id='reportFormat' title="<?php echo _translate('Please select the status'); ?>" onchange="checkIfExist(this);">
													<?php if (($count > 1)) { ?>
														<option value="">
															<?php echo _translate("-- Select --"); ?>
														</option>
													<?php } ?>
													<?php foreach ($reportFormats['hepatitis'] as $key => $value) {
														foreach ($value as $k => $v) { ?>
															<option value="<?php echo $k; ?>"><?php echo ($v); ?></option>
													<?php
														}
													} ?>
												</select>
											</div>
										</div>
									</div>
								<?php }
								if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) {
									$count = sizeof($reportFormats['tb']); ?>
									<div class="col-md-6" style="display:<?php echo ($count > 1) ? 'block' : 'none'; ?>">
										<div class="form-group">
											<label for="reportFormat" class="col-lg-4 control-label">
												<?php echo _translate("Report Format For TB"); ?>
											</label>
											<div class="col-lg-7">
												<select class="form-control" name='reportFormat[tb]' id='reportFormat' title="<?php echo _translate('Please select the status'); ?>" onchange="checkIfExist(this);">
													<?php if (($count > 1)) { ?>
														<option value="">
															<?php echo _translate("-- Select --"); ?>
														</option>
													<?php } ?>
													<?php foreach ($reportFormats['tb'] as $key => $value) {
														foreach ($value as $k => $v) { ?>
															<option value="<?php echo $k; ?>"><?php echo ($v); ?></option>
													<?php
														}
													} ?>
												</select>
											</div>
										</div>
									</div>
								<?php } ?>
							</div>
							<div class="row logoImage" style="display:none;">
								<div class="col-md-6">
									<div class="form-group">
										<label for="labLogo" class="col-lg-4 control-label">
											<?php echo _translate("Logo Image"); ?>
										</label>
										<div class="col-lg-8">
											<div class="fileinput fileinput-new labLogo" data-provides="fileinput">
												<div class="fileinput-preview thumbnail" data-trigger="fileinput" style="width:200px; height:150px;">

												</div>
												<div>
													<span class="btn btn-default btn-file"><span class="fileinput-new">
															<?php echo _translate("Select image"); ?>
														</span><span class="fileinput-exists">
															<?php echo _translate("Change"); ?>
														</span>
														<input type="file" id="labLogo" name="labLogo" title="<?php echo _translate('Please select logo image'); ?>">
													</span>
													<a href="#" class="btn btn-default fileinput-exists" data-dismiss="fileinput">
														<?php echo _translate("Remove"); ?>
													</a>
												</div>
											</div>
											<div class="box-body">
												<?php echo _translate("Please make sure logo image size of"); ?>:
												<code>80x80</code>
											</div>
										</div>
									</div>
								</div>
								<!-- <div class="col-md-6">
								<div class="form-group">
									<label for="stampLogo" class="col-lg-4 control-label">Stamp Image </label>
									<div class="col-lg-8">
										<div class="fileinput fileinput-new stampLogo" data-provides="fileinput">
											<div class="fileinput-preview thumbnail" data-trigger="fileinput" style="width:200px; height:150px;">

											</div>
											<div>
												<span class="btn btn-default btn-file"><span class="fileinput-new">Select image</span><span class="fileinput-exists">Change</span>
													<input type="file" id="stampLogo" name="stampLogo" title="Please select stamp image">
												</span>
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
										<label for="" class="col-lg-4 control-label">
											<?php echo _translate("Header Text"); ?>
										</label>
										<div class="col-lg-7">
											<input type="text" class="form-control " id="headerText" name="headerText" placeholder="<?php echo _translate('Header Text'); ?>" title="<?php echo _translate('Please enter header text'); ?>" />
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
													<option value="yes"><?php echo _translate("Yes"); ?></option>
													<option value="no"><?php echo _translate("No"); ?></option>
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
													<option value="yes"><?php echo _translate("Yes"); ?></option>
													<option value="no"><?php echo _translate("No"); ?></option>
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
												<input type="number" class="form-control" name="reportTopMargin" id="reportTopMargin" placeholder="<?php echo _translate('Report Top Margin'); ?>" title="<?php echo _translate('Please enter the report top margin'); ?>" value="17">
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
													<option value="aboveFooter"><?php echo _translate("Above Footer"); ?></option>
													<option value="belowPlatformName"><?php echo _translate("Below Platform Name"); ?></option>
												</select>
											</div>
										</div>
									</div>
								</div>
							<?php } ?>
							<div class="row" id="sampleType"></div>
							<div class="row-item labDiv" style="display:none;">
								<hr>
								<h4 class="col-lg-12">
									<?= _translate("The following information is sometimes used to show names and signatures in some reports."); ?>
								</h4>
								<table aria-describedby="table" class="col-lg-12 table table-bordered">
									<thead>
										<tr>
											<th>
												<?php echo _translate("Name of Signatory"); ?>
											</th>
											<th>
												<?php echo _translate("Designation"); ?>
											</th>
											<th>
												<?php echo _translate("Upload Signature (jpg, png)"); ?>
											</th>
											<th>
												<?php echo _translate("Test Types"); ?>
											</th>
											<th>
												<?php echo _translate("Display Order"); ?>
											</th>
											<th>
												<?php echo _translate("Current Status"); ?>
											</th>
											<th>
												<?php echo _translate("Action"); ?>
											</th>
										</tr>
									</thead>
									<tbody id="signDetails">
										<tr>
											<td style="width:14%;"><input type="text" class="form-control" name="signName[]" id="signName1" placeholder="<?php echo _translate('Name'); ?>" title="<?php echo _translate('Please enter the name'); ?>"></td>
											<td style="width:14%;"><input type="text" class="form-control" name="designation[]" id="designation1" placeholder="<?php echo _translate('Designation'); ?>" title="<?php echo _translate('Please enter the Designation'); ?>"></td>
											<td style="width:10%;"><input type="file" name="signature[]" id="signature1" placeholder="<?php echo _translate('Signature'); ?>" title="<?php echo _translate('Please enter the Signature'); ?>"></td>
											<td style="width:14%;">
												<select class="select2 .testSignType" id="testSignType1" name="testSignType[1][]" title="<?php echo _translate('Choose at least one test type'); ?>" multiple>
													<?php if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) { ?>
														<option value="vl">
															<?php echo _translate("Viral Load"); ?>
														</option>
													<?php }
													if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) { ?>
														<option value="eid">
															<?php echo _translate("Early Infant Diagnosis"); ?>
														</option>
													<?php }
													if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) { ?>
														<option value="covid19">
															<?php echo _translate("Covid-19"); ?>
														</option>
													<?php }
													if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) { ?>
														<option value='hepatitis'>
															<?php echo _translate("Hepatitis"); ?>
														</option>
													<?php }
													if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) { ?>
														<option value='tb'>
															<?php echo _translate("TB"); ?>
														</option>
													<?php }
													if (isset(SYSTEM_CONFIG['modules']['generic-tests']) && SYSTEM_CONFIG['modules']['generic-tests'] === true) { ?>
														<option value='generic-tests'>
															<?php echo _translate("Other Lab Tests"); ?>
														</option>
													<?php }
													if (isset(SYSTEM_CONFIG['modules']['cd4']) && SYSTEM_CONFIG['modules']['cd4'] === true) { ?>
														<option value='cd4'>
															<?php echo _translate("CD4"); ?>
														</option>
													<?php } ?>
												</select>
											</td>
											<td style="width:14%;"><input type="number" class="form-control" name="sortOrder[]" id="sortOrder1" placeholder="<?php echo _translate('Display Order'); ?>" title="<?php echo _translate('Please enter the Display Order'); ?>"></td>
											<td style="width:14%;">
												<select class="form-control" id="signStatus1" name="signStatus[]" title="<?php echo _translate('Please select the status'); ?>">
													<option value="active">
														<?php echo _translate("Active"); ?>
													</option>
													<option value="inactive">
														<?php echo _translate("Inactive"); ?>
													</option>
												</select>
											</td>
											<td style="vertical-align:middle;text-align: center;width:10%;">
												<a class="btn btn-xs btn-primary test-name-table" href="javascript:void(0);" onclick="addNewRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
												<a class="btn btn-xs btn-default test-name-table" href="javascript:void(0);" onclick="removeNewRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
											</td>
										</tr>
									</tbody>
								</table>
							</div>

							<div class="row" id="userDetails"></div>
							<div class="row" id="testDetails" style="display:none;"></div>

							<div class="row testTypeFileSection" style="display:none;">
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
										</tbody>
									</table>
								</div>
							</div>
						</div>
						<!-- /.box-body -->
						<div class="box-footer">
							<input type="hidden" name="selectedUser" id="selectedUser" />
							<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">
								<?php echo _translate("Submit"); ?>
							</a>
							<a href="facilities.php" class="btn btn-default">
								<?php echo _translate("Cancel"); ?>
							</a>
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
	let testTypeFileCounter = 1;

	$(document).ready(function() {


		$("#testType").selectize({
			plugins: ["restore_on_backspace", "remove_button", "clear_button"],
		});

		$(".testSignType").select2({
			placeholder: '<?php echo _translate("Select Test Type", true); ?>',
			width: '100%'
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
		// Initialize the test type file section when main test type changes
		$('#testType').on('change', function() {
			updateRowVisibility();
		});

		// Initialize on page load
		updateRowVisibility();
	});

	function validateNow() {
		var selVal = [];
		$('#search_to option').each(function(i, selected) {
			selVal[i] = $(selected).val();
		});
		$("#selectedUser").val(selVal);

		$('#state').val($("#stateId option:selected").text());
		$('#district').val($("#districtId option:selected").text());
		tt = $("#testType").val();
		if (tt == "") {
			alert("Please Choose at least one test type");
			return false;
		}
		flag = deforayValidator.init({
			formId: 'addFacilityForm'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('addFacilityForm').submit();
		}
	}

	/* function showSignature(facilityType) {
		if (facilityType == 2) {
			$(".labDiv").show();
			$("#testSignType1").select2({
				placeholder: '<?php echo _translate("Select Test Type", true); ?>',
				width: '100%'
			});
		} else {
			$(".labDiv").hide();
		}
	} */

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
		} else {
			$("#allowResultUpload option[value=yes]").removeAttr('selected', 'selected');
			$("#allowResultUpload option[value='']").attr('selected', 'selected');
			$('.allowResultsUpload').hide();
			$('#allowResultUpload').removeClass('isRequired');
			$('#allowResultUpload').val('');
		}
	});

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
			$(".testTypeFileSection").show();
		} else {
			$(".logoImage").hide();
			$(".testTypeFileSection").hide();
		}
	}

	function getTestType() {
		var facility = $("#facilityType").val();
		var testType = $("#testType").val();
		if (facility == '2') {
			if (!$("#testType").hasClass('isRequired')) {
				$("#testType").addClass('isRequired');
				$(".test-type").append('<span class="mandatory">*</span>');
			}
		} else {
			$("#testType").removeClass('isRequired');
			$(".test-type").find('span').remove();
		}
		if (testType == 'tb') {
			$('.availablePlatforms').show();
		} else {
			$('.availablePlatforms').hide();
		}
		if (facility && (testType.length > 0) && facility == '2') {
			var div = '<table aria-describedby="table" class="table table-bordered table-striped" aria-hidden="true" ><thead><th> Test Type</th> <th> Monthly Target <span class="mandatory">*</span></th><th>Suppressed Monthly Target <span class="mandatory">*</span></th> </thead><tbody>';
			for (var i = 0; i < testType.length; i++) {
				var testOrg = '';
				if (testType[i] == 'vl') {
					testOrg = 'Viral Load';
					var extraDiv = '<td><input type="text" class=" isRequired" name="supMonTar[]" id ="supMonTar' + i + '" value="" title="<?php echo _translate("Please enter Suppressed monthly target", true); ?>"/></td>';
				} else if (testType[i] == 'eid') {
					testOrg = 'Early Infant Diagnosis';
					var extraDiv = '<td></td>';
				} else if (testType[i] == 'covid19') {
					testOrg = 'Covid-19';
					var extraDiv = '<td></td>';
				} else if (testType[i] == 'hepatitis') {
					testOrg = 'Hepatitis';
					var extraDiv = '<td></td>';
				} else if (testType[i] == 'tb') {
					testOrg = 'TB';
					var extraDiv = '<td></td>';
				} else if (testType[i] == 'cd4') {
					testOrg = 'CD4';
					var extraDiv = '<td></td>';
				} else if (testType[i] == 'generic-tests') {
					testOrg = 'Other Lab Tests';
					var extraDiv = '<td></td>';
				}
				div += '<tr><td>' + testOrg + '<input type="hidden" name="testData[]" id ="testData' + i + '" value="' + testType[i] + '" /></td>';
				div += '<td><input type="text" class=" isRequired" name="monTar[]" id ="monTar' + i + '" value="" title="<?php echo _translate("Please enter monthly target", true); ?>"/></td>';
				div += extraDiv;
				div += '</tr>';
			}
			div += '</tbody></table>';
			// $("#testDetails").html(div); // commented the validation functionality code
		} else {
			$("#testDetails").html('');
		}

		if ($("#testType").val() != '') {
			$.post("/facilities/getSampleType.php", {
					testType: $("#testType").val()
				},
				function(data) {
					$("#sampleType").html(data);
				});
		} else {
			$("#sampleType").html('');
		}
	}

	function addNewRow() {
		testCounter++;
		let rowString = `<tr>
			<td style="width:14%;"><input type="text" class="form-control" name="signName[]" id="signName${testCounter}" placeholder="<?php echo _translate("Name"); ?>" title="<?php echo _translate("Please enter the name", true); ?>"></td>
			<td style="width:14%;"><input type="text" class="form-control" name="designation[]" id="designation${testCounter}" placeholder="<?php echo _translate("Designation", true); ?>" title="<?php echo _translate("Please enter the Designation", true); ?>"></td>
			<td style="width:14%;"><input type="file" name="signature[]" id="signature${testCounter}" placeholder="Signature" title="<?php echo _translate("Please enter the Signature", true); ?>"></td>
			<td style="width:14%;">
				<select class="select2 testSignType" id="testSignType${testCounter}" name="testSignType[${testCounter}][]" title="<?php echo _translate("Choose at least one test type", true); ?>" multiple>
					<option value="vl"><?php echo _translate("Viral Load", true); ?></option>
					<option value="eid"><?php echo _translate("Early Infant Diagnosis", true); ?></option>
					<option value="covid19"><?php echo _translate("Covid-19", true); ?></option>
					<option value='hepatitis'><?php echo _translate("Hepatitis", true); ?></option>
					<option value='tb'><?php echo _translate("TB", true); ?></option>
					<option value='generic-tests'><?php echo _translate("Other Lab Tests", true); ?></option>
					<option value='cd4'><?php echo _translate("CD4", true); ?></option>
				</select>
			</td>
			<td style="width:14%;"><input type="number" class="form-control" name="sortOrder[]" id="sortOrder${testCounter}" placeholder="<?php echo _translate("Display Order", true); ?>" title="<?php echo _translate("Please enter the Display Order", true); ?>"></td>
			<td style="width:14%;">
				<select class="form-control" id="signStatus${testCounter}" name="signStatus[]" title="<?php echo _translate("Please select the status", true); ?>">
					<option value="active"><?php echo _translate("Active"); ?></option>
					<option value="inactive"><?php echo _translate("Inactive"); ?></option>
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
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
