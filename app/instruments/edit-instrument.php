<?php

use App\Services\UsersService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\TestsService;

require_once APPLICATION_PATH . '/header.php';

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$vlsmSystemConfig = $general->getSystemConfig();
$labNameList = $facilitiesService->getTestingLabs();
// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;

$sQuery = "SELECT * from instruments where instrument_id=?";
$sInfo = $db->rawQueryOne($sQuery, [$id]);

$configDir = realpath(__DIR__);
$directory = $configDir . DIRECTORY_SEPARATOR . 'vl';
$dir = new DirectoryIterator($directory);
$fileList = [];
foreach ($dir as $fileinfo) {
	if (!$fileinfo->isFile()) {
		continue;
	}
	$fileList[] = $fileinfo->getFilename();
}
sort($fileList);

if (!empty($sInfo['supported_tests'])) {
	$sInfo['supported_tests'] = json_decode((string) $sInfo['supported_tests'], true);
}

if (!empty($sInfo['reviewed_by'])) {
	$sInfo['reviewed_by'] = json_decode((string) $sInfo['reviewed_by'], true);
}

if (!empty($sInfo['approved_by'])) {
	$sInfo['approved_by'] = json_decode((string) $sInfo['approved_by'], true);
}

$configMachineQuery = "SELECT * FROM instrument_machines WHERE instrument_id=?";
$configMachineInfo = $db->rawQuery($configMachineQuery, [$id]);
$configControlQuery = "SELECT * FROM instrument_controls WHERE instrument_id=?";

$configControlInfo = $db->rawQuery($configControlQuery, [$id]);
$configControl = [];
foreach ($configControlInfo as $info) {
	$configControl[$info['test_type']]['noHouseCtrl'] = $info['number_of_in_house_controls'];
	$configControl[$info['test_type']]['noManufacturerCtrl'] = $info['number_of_manufacturer_controls'];
	$configControl[$info['test_type']]['noCalibrators'] = $info['number_of_calibrators'];
}
$sInfo['supported_tests'] = $sInfo['supported_tests'] ?? [];

$vl = in_array('vl', $sInfo['supported_tests']) ? "" : "style='display:none;'";
$eid = in_array('eid', $sInfo['supported_tests']) ? "" : "style='display:none;'";
$covid19 = in_array('covid19', $sInfo['supported_tests']) ? "" : "style='display:none;'";
$hepatitis = in_array('hapatitis', $sInfo['supported_tests']) ? "" : "style='display:none;'";
$tb = in_array('tb', $sInfo['supported_tests']) ? "" : "style='display:none;'";
$cd4 = in_array('cd4', $sInfo['supported_tests']) ? "" : "style='display:none;'";
$genericTests = in_array('generic-tests', $sInfo['supported_tests']) ? "" : "style='display:none;'";
$lowerText = "";
if (in_array('vl', $sInfo['supported_tests']) || in_array('hapatitis', $sInfo['supported_tests'])) {
	$lowerText = "style='display:none;'";
}
$userList = $usersService->getAllUsers(null, 'active', 'drop-down');
$testTypeList = SystemService::getActiveModules(true);

?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-sharp fa-solid fa-gears"></em>
			<?php echo _translate("Edit Instrument"); ?>
		</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em>
					<?php echo _translate("Home"); ?>
				</a></li>
			<li class="active">
				<?php echo _translate("Edit Instrument"); ?>
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
				<form class="form-horizontal" method='post' name='editImportConfigForm' id='editImportConfigForm' autocomplete="off" action="edit-instrument-helper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="configurationName" class="col-lg-4 control-label">
										<?php echo _translate("Instrument Name"); ?><span class="mandatory">*</span>
									</label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="configurationName" name="configurationName" placeholder="<?php echo _translate('eg. Roche or Abbott'); ?>" title="<?php echo _translate('Please enter instrument name'); ?>" value="<?php echo $sInfo['machine_name']; ?>" onchange="setConfigFileName();" onblur="checkNameValidation('instruments','machine_name',this,'<?php echo "instrument_id##" . $sInfo['instrument_id']; ?>','<?php echo _translate('This instrument name already exists.Try another name'); ?>',null);" />
									</div>
								</div>
							</div>
						</div>
						<?php if ($general->isLISInstance()) { ?>
							<input type="hidden" value="<?php echo $general->getSystemConfig('sc_testing_lab_id'); ?>" name="testingLab" />
						<?php } else { ?>
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label for="testingLab" class="col-lg-4 control-label">
											<?php echo _translate("Testing Lab"); ?> <span class="mandatory">*</span>
										</label>
										<div class="col-lg-7">
											<select class="form-control select2" id="testingLab" name="testingLab" title="Please select the testing lab">
												<?php echo $general->generateSelectOptions($labNameList, $sInfo['lab_id'], '--Select--'); ?>
											</select>
										</div>
									</div>
								</div>
							</div>
						<?php } ?>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="supportedTests" class="col-lg-4 control-label">
										<?php echo _translate("Supported Tests"); ?> <span class="mandatory">*</span>
									</label>
									<div class="col-lg-7">
										<select multiple class="" id="supportedTests" name="supportedTests[]">
											<!--<?php if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) { ?>
												<option value='vl' <?php echo (in_array('vl', $sInfo['supported_tests'])) ? "selected='selected'" : ''; ?>><?php echo _translate("Viral Load"); ?></option>
											<?php }
												if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) { ?>
												<option value='eid' <?php echo (in_array('eid', $sInfo['supported_tests'])) ? "selected='selected'" : ''; ?>><?php echo _translate("EID"); ?></option>
											<?php }
												if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) { ?>
												<option value='covid19' <?php echo (in_array('covid19', $sInfo['supported_tests'])) ? "selected='selected'" : ''; ?>><?php echo _translate("Covid-19"); ?></option>
											<?php }
												if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) { ?>
												<option value='hepatitis' <?php echo (in_array('hepatitis', $sInfo['supported_tests'])) ? "selected='selected'" : ''; ?>><?php echo _translate("Hepatitis"); ?></option>
											<?php }
												if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) { ?>
												<option value='tb' <?php echo (in_array('tb', $sInfo['supported_tests'])) ? "selected='selected'" : ''; ?>><?php echo _translate("TB"); ?></option>
											<?php }
												if (isset(SYSTEM_CONFIG['modules']['generic-tests']) && SYSTEM_CONFIG['modules']['generic-tests'] === true) { ?>
												<option value='generic-tests' <?php echo (in_array('generic-tests', $sInfo['supported_tests'])) ? "selected='selected'" : ''; ?>><?php echo _translate("Other Lab Tests"); ?></option>
											<?php } ?>-->
											<option value=""><?php echo _translate("Select Test Types"); ?></option>

											<?php foreach ($testTypeList as $testType) { ?>
												<option value="<?= $testType; ?>" <?php echo (in_array($testType, $sInfo['supported_tests'])) ? "selected='selected'" : ''; ?>><?php echo TestsService::getTestName($testType); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="configurationName" class="col-lg-4 control-label">
										<?= _translate("Instrument File Name"); ?><span class="mandatory">*</span>
									</label>
									<div class="col-lg-7">
										<!--<input type="text" class="form-control isRequired" id="configurationFile" name="configurationFile" placeholder="<?php echo _translate('eg. roche.php or abbott.php'); ?>" title="<?php echo _translate('Please enter file name'); ?>" value="<?php echo $sInfo['import_machine_file_name']; ?>" />-->
										<select name="configurationFile" id="configurationFile" class="form-control select2">
											<option value=""><?php echo _translate('Select File'); ?></option>
											<?php
											foreach ($fileList as $fileName) {
											?>
												<option value="<?= $fileName; ?>" <?php if ($sInfo['import_machine_file_name'] == $fileName) echo "selected='selected'"; ?>><?= $fileName; ?></option>
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
									<label for="lowerLimit" class="col-lg-4 control-label">
										<?= _translate("Lower Limit"); ?>
									</label>
									<div class="col-lg-7">
										<input type="text" class="form-control forceNumeric" id="lowerLimit" name="lowerLimit" placeholder="<?php echo _translate('eg. 20'); ?>" title="<?php echo _translate('Please enter lower limit'); ?>" value="<?php echo $sInfo['lower_limit']; ?>" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="higherLimit" class="col-lg-4 control-label">
										<?= _translate("Higher Limit"); ?>
									</label>
									<div class="col-lg-7">
										<input type="text" class="form-control forceNumeric" id="higherLimit" name="higherLimit" placeholder="<?php echo _translate('eg. 10000000'); ?>" title="<?php echo _translate('Please enter lower limit'); ?>" value="<?php echo $sInfo['higher_limit']; ?>" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="maxNOfSamplesInBatch" class="col-lg-4 control-label">
										<?php echo _translate("Maximum No. of Samples In a Batch"); ?> <span class="mandatory">*</span>
									</label>
									<div class="col-lg-7">
										<input type="text" class="form-control forceNumeric isRequired" id="maxNOfSamplesInBatch" name="maxNOfSamplesInBatch" placeholder="<?php echo _translate('Max. no of samples'); ?>" title="<?php echo _translate('Please enter max no of samples in a row'); ?>" value="<?php echo $sInfo['max_no_of_samples_in_a_batch']; ?>" />
									</div>
								</div>
							</div>
						</div>
						<?php if (SYSTEM_CONFIG['modules']['vl']) { ?>
							<div class="row lowVlResultText">
								<div class="col-md-12">
									<div class="form-group">
										<label for="lowVlResultText" class="col-lg-2 control-label">
											<?php echo _translate("Low VL Result Text"); ?>
										</label>
										<div class="col-lg-7">
											<textarea class="form-control" id="lowVlResultText" name="lowVlResultText" placeholder="<?php echo _translate('Comma separated Low Viral Load Result Text for eg. Target Not Detected, TND, < 20, < 40'); ?>" title="<?php echo _translate('Low Viral Load Result Text for eg. Target Not Detected, TND, < 20, < 40'); ?>"><?php echo $sInfo['low_vl_result_text']; ?></textarea>
										</div>
									</div>
								</div>
							</div>
						<?php } ?>
						<div class="row additionalText">
							<div class="col-md-12">
								<div class="form-group">
									<label for="additionalText" class="col-lg-2 control-label">
										<?php echo _translate("Description/Comment to add in Test Result"); ?>
									</label>
									<div class="col-lg-7">
										<textarea class="form-control" id="additionalText" name="additionalText" placeholder='<?php echo _translate("Enter Description or Comment to be added in Test Result"); ?>' title='<?php echo _translate("Enter Description or Comment to be added in Test Result"); ?>'><?php echo htmlspecialchars_decode($sInfo['additional_text'], ENT_QUOTES); ?></textarea>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6" style="padding-top:20px;">
								<div class="form-group">
									<label for="status" class="col-lg-4 control-label">
										<?php echo _translate("Status"); ?>
									</label>
									<div class="col-lg-7">
										<select class="form-control" id="status" name="status" title="<?php echo _translate('Please select instrument status'); ?>">
											<option value="active" <?php echo ($sInfo['status'] == 'active') ? 'selected="selected"' : ''; ?>><?php echo _translate("Active"); ?></option>
											<option value="inactive" <?php echo ($sInfo['status'] == 'inactive') ? 'selected="selected"' : ''; ?>><?php echo _translate("Inactive"); ?></option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<?php if (SYSTEM_CONFIG['modules']['vl'] || SYSTEM_CONFIG['modules']['eid'] || SYSTEM_CONFIG['modules']['covid19']) { ?>
							<div class="box-body">
								<?php $ua = "display:none";
								if (in_array('vl', $sInfo['supported_tests']) || in_array('eid', $sInfo['supported_tests']) && in_array('covid19', $sInfo['supported_tests']) || in_array('hepatitis', $sInfo['supported_tests']) || in_array('tb', $sInfo['supported_tests'])) {
									$ua = "";
								} ?>
								<table aria-describedby="table" border="0" class="user-access table table-striped table-bordered table-condensed" style="width:100%;<?php echo $ua; ?>;">
									<thead>
										<tr>
											<th style="text-align:center;">
												<?php echo _translate("Test Type"); ?>
											</th>
											<th style="text-align:center;">
												<?php echo _translate("Default Reviewer"); ?>
											</th>
											<th style="text-align:center;">
												<?php echo _translate("Default Approver"); ?>
											</th>
										</tr>
									</thead>
									<tbody>
										<?php if (SYSTEM_CONFIG['modules']['vl']) { ?>
											<tr class="vl-access user-access-form" style="<?php echo in_array('vl', $sInfo['supported_tests']) ? "" : "display:none;"; ?>">
												<td align="left" style="text-align:center;">
													<?php echo _translate("VL"); ?><input type="hidden" name="userTestType[]" id="testType1" value="vl" />
												</td>
												<td>
													<select name="reviewedBy[]" id="reviewedByVl" class="form-control user-vl select2" title='<?php echo _translate("Please enter Reviewed By for VL Test"); ?>' onchange="changeDefaultReviewer(this.value,'vl');">
														<?php echo $general->generateSelectOptions($userList, $sInfo['reviewed_by']['vl'], '--Select--'); ?>
													</select>
												</td>
												<td>
													<select name="approvedBy[]" id="approvedByVl" class="form-control user-vl select2" title='<?php echo _translate("Please enter Approved By for VL Test"); ?>' onchange="changeDefaultApprover(this.value,'vl');">
														<?php echo $general->generateSelectOptions($userList, $sInfo['approved_by']['vl'], '--Select--'); ?>
													</select>
												</td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['eid']) { ?>
											<tr class="eid-access user-access-form" style="<?php echo in_array('eid', $sInfo['supported_tests']) ? "" : "display:none;"; ?>">
												<td align="left" style="text-align:center;">
													<?php echo _translate("EID"); ?><input type="hidden" name="userTestType[]" id="testType1" value="eid" />
												</td>
												<td>
													<select name="reviewedBy[]" id="reviewedByEid" class="form-control user-eid select2" title='<?php echo _translate("Please enter Reviewed By for EID Test"); ?>' onchange="changeDefaultReviewer(this.value,'eid');">
														<?php echo $general->generateSelectOptions($userList, $sInfo['reviewed_by']['eid'], '--Select--'); ?>
													</select>
												</td>
												<td>
													<select name="approvedBy[]" id="approvedByEid" class="form-control user-eid select2" title='<?php echo _translate("Please enter Approved By for EID Test"); ?>' onchange="changeDefaultApprover(this.value,'eid');">
														<?php echo $general->generateSelectOptions($userList, $sInfo['approved_by']['eid'], '--Select--'); ?>
													</select>
												</td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['covid19']) { ?>
											<tr class="covid19-access user-access-form" style="<?php echo in_array('covid19', $sInfo['supported_tests']) ? "" : "display:none;"; ?>">
												<td align="left" style="text-align:center;">
													<?php echo _translate("Covid-19"); ?><input type="hidden" name="userTestType[]" id="testType1" value="covid19" />
												</td>
												<td>
													<select name="reviewedBy[]" id="reviewedByCovid19" class="form-control user-covid19 select2" title='<?php echo _translate("Please enter Reviewed By for Covid19 Test"); ?>' onchange="changeDefaultReviewer(this.value,'covid19');">
														<?php echo $general->generateSelectOptions($userList, $sInfo['reviewed_by']['covid19'], '--Select--'); ?>
													</select>
												</td>
												<td>
													<select name="approvedBy[]" id="approvedByCovid19" class="form-control user-covid19 select2" title='<?php echo _translate("Please enter Approved By for Covid19 Test"); ?>' onchange="changeDefaultApprover(this.value,'covid19');">
														<?php echo $general->generateSelectOptions($userList, $sInfo['approved_by']['covid19'], '--Select--'); ?>
													</select>
												</td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['hepatitis']) { ?>
											<tr class="hepatitis-access user-access-form" style="<?php echo in_array('hepatitis', $sInfo['supported_tests']) ? "" : "display:none;"; ?>">
												<td align="left" style="text-align:center;">
													<?php echo _translate("Hepatitis"); ?><input type="hidden" name="userTestType[]" id="testType1" value="hepatitis" />
												</td>
												<td>
													<select name="reviewedBy[]" id="reviewedByHepatitis" class="form-control user-hepatitis select2" title='<?php echo _translate("Please enter Reviewed By for Hepatitis Test"); ?>' onchange="changeDefaultReviewer(this.value,'hepatitis');">
														<?php echo $general->generateSelectOptions($userList, $sInfo['reviewed_by']['hepatitis'], '--Select--'); ?>
													</select>
												</td>
												<td>
													<select name="approvedBy[]" id="approvedByHepatitis" class="form-control user-hepatitis select2" title='<?php echo _translate("Please enter Approved By for Hepatitis Test"); ?>' onchange="changeDefaultApprover(this.value,'hepatitis');">
														<?php echo $general->generateSelectOptions($userList, $sInfo['approved_by']['hepatitis'], '--Select--'); ?>
													</select>
												</td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['tb']) { ?>
											<tr class="tb-access user-access-form" style="<?php echo in_array('tb', $sInfo['supported_tests']) ? "" : "display:none;"; ?>">
												<td align="left" style="text-align:center;">
													<?php echo _translate("TB"); ?><input type="hidden" name="userTestType[]" id="testType1" value="tb" />
												</td>
												<td>
													<select name="reviewedBy[]" id="reviewedByTb" class="form-control user-tb select2" title='<?php echo _translate("Please enter Reviewed By for TB Test"); ?>' onchange="changeDefaultReviewer(this.value,'tb');">
														<?php echo $general->generateSelectOptions($userList, $sInfo['reviewed_by']['tb'], '--Select--'); ?>
													</select>
												</td>
												<td>
													<select name="approvedBy[]" id="approvedByTb" class="form-control user-tb select2" title='<?php echo _translate("Please select Approved By for TB Test"); ?>' onchange="changeDefaultApprover(this.value,'tb');">
														<?php echo $general->generateSelectOptions($userList, $sInfo['approved_by']['tb'], '--Select--'); ?>
													</select>
												</td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['cd4']) { ?>
											<tr class="cd4-access user-access-form" style="<?php echo in_array('cd4', $sInfo['supported_tests']) ? "" : "display:none;"; ?>">
												<td align="left" style="text-align:center;">
													<?php echo _translate("CD4"); ?><input type="hidden" name="userTestType[]" id="testType1" value="cd4" />
												</td>
												<td>
													<select name="reviewedBy[]" id="reviewedByCd4" class="form-control user-cd4 select2" title='<?php echo _translate("Please enter Reviewed By for CD4 Test"); ?>' onchange="changeDefaultReviewer(this.value,'cd4');">
														<?php echo $general->generateSelectOptions($userList, $sInfo['reviewed_by']['cd4'], '--Select--'); ?>
													</select>
												</td>
												<td>
													<select name="approvedBy[]" id="approvedByCd4" class="form-control user-cd4 select2" title='<?php echo _translate("Please enter Approved By for CD4 Test"); ?>' onchange="changeDefaultApprover(this.value,'cd4');">
														<?php echo $general->generateSelectOptions($userList, $sInfo['approved_by']['cd4'], '--Select--'); ?>
													</select>
												</td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['generic-tests']) { ?>
											<tr class="generic-access user-access-form" style="<?php echo in_array('generic', $sInfo['supported_tests']) ? "" : "display:none;"; ?>">
												<td align="left" style="text-align:center;">
													<?php echo _translate("Other Lab Tests"); ?><input type="hidden" name="userTestType[]" id="testType1" value="generic-tests" />
												</td>
												<td>
													<select name="reviewedBy[]" id="reviewedByGeneric" class="form-control user-generic select2" title='<?php echo _translate("Please enter Reviewed By for Other Lab Test"); ?>' onchange="changeDefaultReviewer(this.value,'generic-tests');">
														<?php echo $general->generateSelectOptions($userList, $sInfo['reviewed_by']['generic-tests'], '--Select--'); ?>
													</select>
												</td>
												<td>
													<select name="approvedBy[]" id="approvedByGeneric" class="form-control user-generic select2" title='<?php echo _translate("Please enter Approved By for Other Lab Test"); ?>' onchange="changeDefaultApprover(this.value,'generic-tests');">
														<?php echo $general->generateSelectOptions($userList, $sInfo['approved_by']['generic-tests'], '--Select--'); ?>
													</select>
												</td>
											</tr>
										<?php } ?>
									</tbody>
								</table>
								<br>
								<hr>
								<table aria-describedby="table" border="0" class="table table-striped table-bordered table-condensed" aria-hidden="true" style="width:100%;">
									<thead>
										<tr>
											<th style="text-align:center;">
												<?php echo _translate("Test Type"); ?>
											</th>
											<th style="text-align:center;">
												<?php echo _translate("Number of In-House Controls"); ?>
											</th>
											<th style="text-align:center;">
												<?php echo _translate("Number of Manufacturer Controls"); ?>
											</th>
											<th style="text-align:center;">
												<?php echo _translate("No. Of Calibrators"); ?>
											</th>
										</tr>
									</thead>
									<tbody id="testTypesTable">
										<?php if ((SYSTEM_CONFIG['modules']['vl'])) { ?>
											<tr id="vlTable" class="ctlCalibrator" <?php echo $vl; ?>>
												<td align="left">
													<?php echo _translate("VL"); ?><input type="hidden" name="testType[]" id="testType1" value="vl" />
												</td>
												<td><input type="text" value="<?php echo $configControl['vl']['noHouseCtrl']; ?>" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control" placeholder="<?php echo _translate('No of In-House Controls in vl'); ?>" title="<?php echo _translate('Please enter No of In-House Controls in vl'); ?>" />
												</td>
												<td><input type="text" value="<?php echo $configControl['vl']['noManufacturerCtrl']; ?>" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control" placeholder="<?php echo _translate('No of Manufacturer Controls in vl'); ?>" title="<?php echo _translate('Please enter No of Manufacturer Controls in vl'); ?>" />
												</td>
												<td><input type="text" value="<?php echo $configControl['vl']['noCalibrators']; ?>" name="noCalibrators[]" id="noCalibrators1" class="form-control" placeholder="<?php echo _translate('No of Calibrators in vl'); ?>" title="<?php echo _translate('Please enter No of Calibrators in vl'); ?>" /></td>
											</tr>
										<?php }
										if ((SYSTEM_CONFIG['modules']['eid'])) { ?>
											<tr id="eidTable" class="ctlCalibrator" <?php echo $eid; ?>>
												<td align="left">
													<?php echo _translate("EID"); ?><input type="hidden" name="testType[]" id="testType1" value="eid" />
												</td>
												<td><input type="text" value="<?php echo $configControl['eid']['noHouseCtrl']; ?>" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control" placeholder="<?php echo _translate('No of In-House Controls in eid'); ?>" title="<?php echo _translate('Please enter No of In-House Controls in eid'); ?>" />
												</td>
												<td><input type="text" value="<?php echo $configControl['eid']['noManufacturerCtrl']; ?>" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control" placeholder="<?php echo _translate('No of Manufacturer Controls in eid'); ?>" title="<?php echo _translate('Please enter No of Manufacturer Controls in eid'); ?>" />
												</td>
												<td><input type="text" value="<?php echo $configControl['eid']['noCalibrators']; ?>" name="noCalibrators[]" id="noCalibrators1" class="form-control" placeholder="<?php echo _translate('No of Calibrators in eid'); ?>" title="<?php echo _translate('Please enter No of Calibrators in eid'); ?>" />
												</td>
											</tr>
										<?php }
										if ((SYSTEM_CONFIG['modules']['covid19'])) { ?>
											<tr id="covid19Table" class="ctlCalibrator" <?php echo $covid19; ?>>
												<td align="left">
													<?php echo _translate("Covid-19"); ?><input type="hidden" name="testType[]" id="testType1" value="covid-19" />
												</td>
												<td><input type="text" value="<?php echo $configControl['covid-19']['noHouseCtrl']; ?>" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control" placeholder="<?php echo _translate('No of In-House Controls in covid-19'); ?>" title="<?php echo _translate('Please enter No of In-House Controls in covid-19'); ?>" />
												</td>
												<td><input type="text" value="<?php echo $configControl['covid-19']['noManufacturerCtrl']; ?>" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control" placeholder="<?php echo _translate('No of Manufacturer Controls in covid-19'); ?>" title="<?php echo _translate('Please enter No of Manufacturer Controls in covid-19'); ?>" />
												</td>
												<td><input type="text" value="<?php echo $configControl['covid-19']['noCalibrators']; ?>" name="noCalibrators[]" id="noCalibrators1" class="form-control" placeholder="<?php echo _translate('No of Calibrators in covid-19'); ?>" title="<?php echo _translate('Please enter No of Calibrators in covid-19'); ?>" />
												</td>
											</tr>
										<?php }
										if ((SYSTEM_CONFIG['modules']['hepatitis'])) { ?>
											<tr id="hepatitisTable" class="ctlCalibrator" <?php echo $hepatitis; ?>>
												<td align="left">
													<?php echo _translate("Hepatitis"); ?><input type="hidden" name="testType[]" id="testType1" value="hepatitis" />
												</td>
												<td><input type="text" value="<?php echo $configControl['hepatitis']['noHouseCtrl']; ?>" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control" placeholder="<?php echo _translate('No of In-House Controls in hepatitis'); ?>" title="<?php echo _translate('Please enter No of In-House Controls in hepatitis'); ?>" />
												</td>
												<td><input type="text" value="<?php echo $configControl['hepatitis']['noManufacturerCtrl']; ?>" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control" placeholder="<?php echo _translate('No of Manufacturer Controls in hepatitis'); ?>" title="<?php echo _translate('Please enter No of Manufacturer Controls in hepatitis'); ?>" />
												</td>
												<td><input type="text" value="<?php echo $configControl['hepatitis']['noCalibrators']; ?>" name="noCalibrators[]" id="noCalibrators1" class="form-control" placeholder="<?php echo _translate('No of Calibrators in hepatitis'); ?>" title="<?php echo _translate('Please enter No of Calibrators in hepatitis'); ?>" />
												</td>
											</tr>
										<?php }
										if ((SYSTEM_CONFIG['modules']['tb'])) { ?>
											<tr id="tbTable" class="ctlCalibrator" <?php echo $tb; ?>>
												<td align="left">
													<?php echo _translate("tb"); ?><input type="hidden" name="testType[]" id="testType1" value="tb" />
												</td>
												<td><input type="text" value="<?php echo $configControl['tb']['noHouseCtrl']; ?>" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control" placeholder="<?php echo _translate('No of In-House Controls in TB'); ?>" title="<?php echo _translate('Please enter No of In-House Controls in TB'); ?>" />
												</td>
												<td><input type="text" value="<?php echo $configControl['tb']['noManufacturerCtrl']; ?>" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control" placeholder="<?php echo _translate('No of Manufacturer Controls in TB'); ?>" title="<?php echo _translate('Please enter No of Manufacturer Controls in TB'); ?>" />
												</td>
												<td><input type="text" value="<?php echo $configControl['tb']['noCalibrators']; ?>" name="noCalibrators[]" id="noCalibrators1" class="form-control" placeholder="<?php echo _translate('No of Calibrators in TB'); ?>" title="<?php echo _translate('Please enter No of Calibrators in TB'); ?>" /></td>
											</tr>
										<?php }
										if ((SYSTEM_CONFIG['modules']['cd4'])) { ?>
											<tr id="cd4Table" class="ctlCalibrator" <?php echo $cd4; ?>>
												<td align="left">
													<?php echo _translate("CD4"); ?><input type="hidden" name="testType[]" id="testType1" value="cd4" />
												</td>
												<td><input type="text" value="<?php echo $configControl['cd4']['noHouseCtrl']; ?>" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control" placeholder="<?php echo _translate('No of In-House Controls in CD4'); ?>" title="<?php echo _translate('Please enter No of In-House Controls in CD4'); ?>" />
												</td>
												<td><input type="text" value="<?php echo $configControl['cd4']['noManufacturerCtrl']; ?>" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control" placeholder="<?php echo _translate('No of Manufacturer Controls in CD4'); ?>" title="<?php echo _translate('Please enter No of Manufacturer Controls in CD4'); ?>" />
												</td>
												<td><input type="text" value="<?php echo $configControl['cd4']['noCalibrators']; ?>" name="noCalibrators[]" id="noCalibrators1" class="form-control" placeholder="<?php echo _translate('No of Calibrators in CD4'); ?>" title="<?php echo _translate('Please enter No of Calibrators in CD4'); ?>" /></td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['generic-tests']) { ?>
											<tr id="generic-testsTable" class="ctlCalibrator" <?php echo $genericTests; ?>>
												<td align="left">
													<?php echo _translate("Other Lab Tests"); ?><input type="hidden" name="testType[]" id="testType1" value="generic-tests" />
												</td>
												<td><input type="text" value="<?php echo $configControl['generic-tests']['noHouseCtrl']; ?>" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control" placeholder='<?php echo _translate("No of In-House Controls in Other Lab Tests"); ?>' title='<?php echo _translate("Please enter No of In-House Controls in Other Lab Tests"); ?>' />
												</td>
												<td><input type="text" value="<?php echo $configControl['generic-tests']['noManufacturerCtrl']; ?>" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control" placeholder='<?php echo _translate("No of Manufacturer Controls in Other Lab Tests"); ?>' title='<?php echo _translate("Please enter No of Manufacturer Controls in Other Lab Tests"); ?>' />
												</td>
												<td><input type="text" value="<?php echo $configControl['generic-tests']['noCalibrators']; ?>" name="noCalibrators[]" id="noCalibrators1" class="form-control" placeholder='<?php echo _translate("No of Calibrators in Other Lab Tests"); ?>' title='<?php echo _translate("Please enter No of Calibrators in Other Lab Tests"); ?>' />
												</td>
											</tr>
										<?php } ?>
									</tbody>
								</table>
							</div>
						<?php } ?>
						<div class="box-header">
							<h3 class="box-title ">
								<?php echo _translate("Machine Names"); ?>
							</h3>
						</div>
						<div class="box-body">
							<table aria-describedby="table" border="0" class="table table-striped table-bordered table-condensed" aria-hidden="true" style="width:100%;">
								<thead>
									<tr>
										<th style="text-align:center;">
											<?php echo _translate("Machine Name"); ?> <span class="mandatory">*</span>
										</th>
										<th style="text-align:center;">
											<?php echo _translate("Date Format"); ?> <span class="mandatory">*</span>
										</th>
										<th style="text-align:center;">
											<?php echo _translate("Instrument File Name"); ?> <span class="mandatory">*</span>
										</th>
										<th style="text-align:center;">
											<?php echo _translate("Is this a POC Device?"); ?>
										</th>
										<th style="text-align:center;">
											<?php echo _translate("Action"); ?>
										</th>
									</tr>
								</thead>
								<tbody id="machineTable">
									<?php
									$i = 1;
									if (count($configMachineInfo) > 0) {
										foreach ($configMachineInfo as $machine) {
											if (trim($machine['poc_device'] == 'yes')) {
												$style = "display:block";
												$check = "checked";
											} else {
												$style = "display:none";
												$check = "";
											}
									?>
											<tr>
												<td>
													<input type="hidden" name="configMachineId[]" value="<?php echo $machine['config_machine_id']; ?>" />
													<input type="text" name="configMachineName[]" id="configMachineName<?php echo $i; ?>" class="form-control configMachineName isRequired" placeholder="<?php echo _translate('Machine Name'); ?>" title="<?php echo _translate('Please enter machine name'); ?>" value="<?php echo $machine['config_machine_name']; ?>" onblur="checkDuplication(this, 'configMachineName');" ; />
												</td>
												<td>
													<input type="text" value="<?php echo $machine['date_format'] ?: 'd/m/Y H:i'; ?>" name="dateFormat[]" id="dateFormat<?php echo $i; ?>" class="form-control" placeholder='<?php echo _translate("Date Format"); ?>' title='<?php echo _translate("Please enter date format"); ?>' />
												</td>
												<td>
													<select name="fileName[]" id="fileName<?php echo $i; ?>" class="form-control select2 instrumentFile">
														<option value=""><?php echo _translate('Select File'); ?></option>
														<?php
														foreach ($fileList as $fileName) {
														?>
															<option value="<?= $fileName; ?>" <?php if ($machine['file_name'] == $fileName) echo "selected='selected'"; ?>><?= $fileName; ?></option>
														<?php
														}
														?>
													</select>
												</td>
												<td>
													<div class="col-md-3">
														<input type="checkbox" id="pocdevice<?php echo $i; ?>" name="pocdevice[]" value="" onclick="getLatiLongi(<?php echo $i; ?>);" <?php echo $check; ?>>
													</div>
													<div class="latLong<?php echo $i; ?> " style="<?php echo $style; ?>">
														<div class="col-md-4">
															<input type="text" name="latitude[]" id="latitude<?php echo $i; ?>" value="<?php echo $machine['latitude']; ?>" class="form-control " placeholder="<?php echo _translate('Latitude'); ?>" data-placement="bottom" title="<?php echo _translate('Latitude'); ?>" />
														</div>
														<div class="col-md-4">
															<input type="text" name="longitude[]" id="longitude<?php echo $i; ?>" value="<?php echo $machine['longitude']; ?>" class="form-control " placeholder="<?php echo _translate('Longitude'); ?>" data-placement="bottom" title="<?php echo _translate('Longitude'); ?>" />
														</div>
													</div>
												</td>
												<td align="center" style="vertical-align:middle;">
													<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>
												</td>
											</tr>
										<?php
											$i++;
										}
									} else {
										?>
										<tr>
											<td>
												<input type="text" name="configMachineName[]" id="configMachineName0" class="form-control configMachineName isRequired" placeholder="<?php echo _translate('Machine Name'); ?>" title="<?php echo _translate('Please enter machine name'); ?>" onblur="checkDuplication(this, 'configMachineName');" />
											</td>
											<td>
												<input type="text" value="d/m/Y H:i" name="dateFormat[]" id="dateFormat0" class="form-control" placeholder='<?php echo _translate("Date Format"); ?>' title='<?php echo _translate("Please enter date format"); ?>' />
											</td>
											<td>
												<select name="fileName[]" id="fileName0" class="form-control select2 instrumentFile">
													<option value=""><?php echo _translate('Select File'); ?></option>
													<?php
													foreach ($fileList as $fileName) {
													?>
														<option value="<?= $fileName; ?>"><?= $fileName; ?></option>
													<?php
													}
													?>
												</select>
											</td>
											<td>
												<div class="col-md-3">
													<input type="checkbox" id="pocdevice0" name="pocdevice[]" value="" onclick="getLatiLongi(0);">
												</div>
												<div class="latLong0 " style="display:none">
													<div class="col-md-4">
														<input type="text" name="latitude[]" id="latitude0" class="form-control " placeholder="<?php echo _translate('Latitude'); ?>" data-placement="bottom" title="<?php echo _translate('Latitude'); ?>" />
													</div>
													<div class="col-md-4">
														<input type="text" name="longitude[]" id="longitude0" class="form-control " placeholder="<?php echo _translate('Longitude'); ?>" data-placement="bottom" title="<?php echo _translate('Longitude'); ?>" />
													</div>
												</div>
											</td>
											<td align="center" style="vertical-align:middle;">
												<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;&nbsp;<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
											</td>
										</tr>
									<?php } ?>
								</tbody>
							</table>
						</div>
					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<input type="hidden" id="configId" name="configId" value="<?php echo base64_encode((string) $sInfo['instrument_id']); ?>" />
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">
							<?php echo _translate("Submit"); ?>
						</a>
						<a href="/instruments/instruments.php" class="btn btn-default">
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

<script type="text/javascript">
	tableRowId = '<?php echo $i; ?>';

	$(document).ready(function() {
		$('#additionalText').summernote({
			toolbar: [
				// [groupName, [list of button]]
				['style', ['bold', 'italic', 'underline', 'clear']],
				['font', ['strikethrough', 'superscript', 'subscript']],
				['fontsize', ['fontsize']],
				['color', ['color']],
				['para', ['ul', 'ol', 'paragraph']],
				['height', ['height']]
			]
		});

		$('#configurationFile').on('change', function() {
			$('input[name="fileName[]"]').each(function() {
				if ($(this).val() === '') {
					$(this).val($('#configurationFile').val());
				}
			});
		});

		$('input[name="fileName[]"]').each(function() {
			if ($(this).val() === '') {
				$(this).val($('#configurationFile').val());
			}
		});

		$('input[name="configMachineName[]"]').each(function(index) {
			if ($(this).val() === '') {
				$(this).val($('#configurationName').val() + ' - ' + (index + 1));
			}
		});


		$(".select2").select2({
			width: '100%',
			placeholder: '<?php echo _translate("-- Select --"); ?>'
		});

		$("#supportedTests").selectize({
			plugins: ["restore_on_backspace", "remove_button", "clear_button"],
		});

		$('#supportedTests').on('change', function(e) {
			var data = $('#supportedTests').val();
			/* var arr = ['vl', 'eid', 'covid19', 'hepatitis', 'tb'];
			$.each(arr, function(index, value) {
				if (jQuery.inArray(value, data) === -1) {
					$(".user-" + value).val('');
				}
			}); */

			$(".ctlCalibrator, .lowVlResultText, .user-access-form").hide();
			$.each(data, function(key, value) {
				if (value != "") {
					$(".user-access").show();
				}
				if (value == "vl" || value == "hepatitis") {
					$(".lowVlResultText").show();
				}
				$("#" + value + "Table, ." + value + "-access").show();
			});
		});
	});

	function setConfigFileName() {
		var configName = $("#configurationName").val();
		if ($.trim(configName) != '') {
			configName = configName.replace(/[^a-zA-Z0-9 ]/g, "")
			if (configName.length > 0) {
				configName = configName.replace(/\s+/g, ' ');
				configName = configName.replace(/ /g, '-');
				configName = configName.replace(/\-$/, '');
				var configFileName = configName.toLowerCase() + ".php";
				var path = '<?php echo $directory . '/'; ?>' + configFileName;
				$.post("/includes/checkFileExists.php", {
						fileName: path,
					},
					function(data) {
						if (data === 'not exists') {
							$("#configurationFile").append('<option value=' + configFileName + '>' + configFileName + '</option>');
							$(".instrumentFile").append('<option value=' + configFileName + '>' + configFileName + '</option>');
							$('.select2').select2({
								width: '100%'
							});
						}
					});

			}
		} else {
			$("#configurationFile").val("");
		}
	}

	function validateNow() {
		flag = deforayValidator.init({
			formId: 'editImportConfigForm'
		});

		if (flag) {
			document.getElementById('editImportConfigForm').submit();
		}
	}

	function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
		$.post("/includes/checkDuplicate.php", {
				tableName: tableName,
				fieldName: fieldName,
				value: obj.value.trim(),
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
	<?php
	$fileOptions = '';
	foreach ($fileList as $fileName) {
		$fileOptions .= '<option value="' . $fileName . '">' . $fileName . '</option>';
	}
	?>

	function insRow() {

		rl = document.getElementById("machineTable").rows.length;
		var a = document.getElementById("machineTable").insertRow(rl);
		a.setAttribute("style", "display:none");
		var b = a.insertCell(0);
		var c = a.insertCell(1);
		var d = a.insertCell(2);
		var e = a.insertCell(3);
		var f = a.insertCell(4);
		f.setAttribute("align", "center");
		f.setAttribute("style", "vertical-align:middle");

		b.innerHTML = '<input type="text" name="configMachineName[]" id="configMachineName' + tableRowId + '" class="isRequired configMachineName form-control" placeholder="<?php echo _translate('Machine Name'); ?>" title="<?php echo _translate('Please enter machine name'); ?>" onblur="checkDuplication(this, \'configMachineName\');"/ >';
		c.innerHTML = '<input type="text" value="d/m/Y H:i" name="dateFormat[]" id="dateFormat' + tableRowId + '" class="form-control" placeholder="<?php echo _translate("Date Format"); ?>" title="<?php echo _translate("Please enter date format"); ?>" />';
		//d.innerHTML = '<input type="text" name="fileName[]" id="fileName' + tableRowId + '" class="form-control" placeholder="<?php echo _translate("File Name"); ?>" title="<?php echo _translate("Please enter file name"); ?>"/>';
		d.innerHTML = '<select name="fileName[]" id="fileName' + tableRowId + '" class="form-control select2 instrumentFile"><option value=""><?php echo _translate('Select File'); ?></option><?= $fileOptions; ?></select>';
		e.innerHTML = '<div class="col-md-3" >\
						<input type="checkbox" id="pocdevice' + tableRowId + '" name="pocdevice[]" value="" onclick="getLatiLongi(' + tableRowId + ');">\
						</div>\
						<div class="latLong' + tableRowId + ' " style="display:none">\
							<div class="col-md-4">\
								<input type="text" name="latitude[]" id="latitude' + tableRowId + '" class="form-control " placeholder="<?php echo _translate('Latitude'); ?>" data-placement="bottom" title="<?php echo _translate('Latitude'); ?>"/> \
							</div>\
							<div class="col-md-4">\
								<input type="text" name="longitude[]" id="longitude' + tableRowId + '" class="form-control " placeholder="<?php echo _translate('Longitude'); ?>" data-placement="bottom" title="<?php echo _translate('Longitude'); ?>"/>\
							</div>\
						</div>';
		f.innerHTML = '<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;&nbsp;<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>';
		$(a).fadeIn(800);
		$('#fileName' + tableRowId + ', .select2').select2({
			width: '100%'
		});
		var configName = $("#configurationName").val();
		if ($.trim(configName) != '') {
			configName = configName.replace(/[^a-zA-Z0-9 ]/g, "")
			if (configName.length > 0) {
				configName = configName.replace(/\s+/g, ' ');
				configName = configName.replace(/ /g, '-');
				configName = configName.replace(/\-$/, '');
				var configFileName = configName.toLowerCase() + ".php";
				var path = '<?php echo $log_directory . '/'; ?>' + configFileName;

				$.post("/includes/checkFileExists.php", {
						fileName: path,
					},
					function(data) {
						if (data === 'not exists') {
							$('#fileName' + tableRowId).append('<option value="">' + configFileName + '</option>');
							$('#fileName' + tableRowId).select2({
								width: '100%'
							});
						}

					});
			}
		}
		tableRowId++;

	}

	function removeAttributeRow(el) {
		$(el).fadeOut("slow", function() {
			el.parentNode.removeChild(el);
			rl = document.getElementById("machineTable").rows.length;
			if (rl == 0) {
				insRow();
			}
		});
	}

	function checkDuplication(obj, name) {
		dublicateObj = document.getElementsByName(name + "[]");
		for (m = 0; m < dublicateObj.length; m++) {
			if (obj.value != '' && obj.id != dublicateObj[m].id && obj.value == dublicateObj[m].value) {
				alert('Duplicate value not allowed');
				$('#' + obj.id).val('');
			}
		}
	}

	function getLatiLongi(id) {
		if ($("#pocdevice" + id).is(':checked')) {
			$(".latLong" + id).css("display", "block");
			// $("#pocdevice"+id).val('yes');
		} else {
			$(".latLong" + id).css("display", "none");
			// $("#pocdevice"+id).val('no');
		}
	}

	function changeDefaultReviewer(value, testType) {
		var userConfirmed = confirm("Do you want to update existing records? ");

		if (userConfirmed && value != '') {
			$.post("/common/reference/update-default-reviewer.php", {
					defaultReviewer: value,
					testType: testType,
				},
				function(data) {
					alert("<?php echo _translate("Updated successfully"); ?>.");
				});
		}
	}

	function changeDefaultApprover(value, testType) {
		var userConfirmed = confirm("Do you want to update existing records? ");

		if (userConfirmed && value != '') {
			$.post("/common/reference/update-default-approver.php", {
					defaultApprover: value,
					testType: testType,
				},
				function(data) {
					alert("<?php echo _translate("Updated successfully"); ?>.");
				});
		}
	}
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
