<?php

// add-instrument.php

use App\Services\TestsService;
use App\Services\UsersService;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

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

$activeTests = TestsService::getActiveTests();


$userList = $usersService->getAllUsers(null, 'active', 'drop-down');

$configDir = realpath(__DIR__);
$directory = $configDir . DIRECTORY_SEPARATOR . 'vl';

$dir = new DirectoryIterator($directory);
$fileList = [];

foreach ($dir as $fileinfo) {
	if (!$fileinfo->isFile()) {
		continue;
	}

	// Only add .php files
	if (strtolower($fileinfo->getExtension()) !== 'php') {
		continue;
	}

	$fileList[] = $fileinfo->getFilename();
}

sort($fileList);
?>

<link rel="stylesheet" media="all" type="text/css" href="/assets/css/smart-date-format.css">

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-sharp fa-solid fa-gears"></em>
			<?php echo _translate("Add Instrument"); ?>
		</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em>
					<?php echo _translate("Home"); ?>
				</a></li>
			<li class="active">
				<?php echo _translate("Add Instrument"); ?>
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
				<form class="form-horizontal" method='post' name='instrumentForm' id='instrumentForm' autocomplete="off" action="add-instrument-helper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="configurationName" class="col-lg-4 control-label">
										<?php echo _translate("Instrument Name"); ?> <span class="mandatory">*</span>
									</label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="configurationName" name="configurationName" placeholder='<?php echo _translate("eg. Roche or Abbott"); ?>' title='<?php echo _translate("Please enter instrument name"); ?>' onblur="checkNameValidation('instruments','machine_name',this,null,'<?php echo _translate('This instrument name already exists.Try another name'); ?>',null);" onchange="setConfigFileName();" />
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
											<select class="form-control select2 isRequired" id="testingLab" name="testingLab" title="Please select the testing lab">
												<?php echo $general->generateSelectOptions($labNameList, null, '--Select--'); ?>
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
											<option value=""><?php echo _translate("Select Test Types"); ?></option>
											<?php foreach ($activeTests as $testType) { ?>
												<option value="<?= $testType; ?>"><?php echo TestsService::getTestName($testType); ?></option>
											<?php } ?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="configurationFileName" class="col-lg-4 control-label">
										<?php echo _translate("Instrument File"); ?> <span class="mandatory">*</span>
									</label>
									<div class="col-lg-7">
										<select name="configurationFile" id="configurationFile" class="form-control select2">
											<option value=""><?php echo _translate('Select File'); ?></option>
											<?php
											foreach ($fileList as $fileName) {
											?>
												<option value="<?= $fileName; ?>"><?= $fileName; ?></option>
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
									<label for="configurationFileName" class="col-lg-4 control-label">
										<?php echo _translate("Lower Limit"); ?>
									</label>
									<div class="col-lg-7">
										<input type="text" class="form-control forceNumeric" id="lowerLimit" name="lowerLimit" placeholder='<?php echo _translate("eg. 20"); ?>' title='<?php echo _translate("Please enter lower limit"); ?>' />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="configurationFileName" class="col-lg-4 control-label">
										<?php echo _translate("Higher Limit"); ?>
									</label>
									<div class="col-lg-7">
										<input type="text" class="form-control forceNumeric" id="higherLimit" name="higherLimit" placeholder='<?php echo _translate("eg. 10000000"); ?>' title='<?php echo _translate("Please enter lower limit"); ?>' />
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
										<input type="text" class="form-control forceNumeric isRequired" id="maxNOfSamplesInBatch" name="maxNOfSamplesInBatch" placeholder='<?php echo _translate("Max. no of samples"); ?>' title='<?php echo _translate("Please enter max no of samples in a row"); ?>' />
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
											<textarea class="form-control" id="lowVlResultText" name="lowVlResultText" placeholder='<?php echo _translate("Comma separated Low Viral Load Result Text for eg. Target Not Detected, TND, < 20, < 40"); ?>' title='<?php echo _translate("Low Viral Load Result Text for eg. Target Not Detected, TND, < 20, < 40"); ?>'></textarea>
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
										<textarea class="form-control richtextarea" id="additionalText" name="additionalText" placeholder='<?php echo _translate("Enter Description or Comment to be added in Test Result"); ?>' title='<?php echo _translate("Enter Description or Comment to be added in Test Result"); ?>'></textarea>
									</div>
								</div>
							</div>
						</div>
						<!-- <div class="box-header">
							<h3 class="box-title ">Machine Names</h3>
						</div> -->
						<?php if (SYSTEM_CONFIG['modules']['vl'] || SYSTEM_CONFIG['modules']['eid'] || SYSTEM_CONFIG['modules']['covid19']) { ?>
							<div class="box-body">
								<table aria-describedby="table" border="0" class="user-access table table-striped table-bordered table-condensed" style="width:100%;display:none;">
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
											<tr class="vl-access user-access-form" style="display: none;">
												<td style="text-align:center;">
													<?php echo _translate("VL"); ?><input type="hidden" name="userTestType[]" id="userTestTypeVl" value="vl" />
												</td>
												<td>
													<select name="reviewedBy[]" id="reviewedByVl" class="form-control select2" title='<?php echo _translate("Please enter Reviewed By for VL Test"); ?>' onchange="changeDefaultReviewer(this.value,'vl');">
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
												<td>
													<select name="approvedBy[]" id="approvedByVl" class="form-control select2" title='<?php echo _translate("Please enter Approved By for VL Test"); ?>' onchange="changeDefaultApprover(this.value,'vl');">
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['eid']) { ?>
											<tr class="eid-access user-access-form" style="display: none;">
												<td style="text-align:center;">
													<?php echo _translate("EID"); ?><input type="hidden" name="userTestType[]" id="userTestTypeEid" value="eid" />
												</td>
												<td>
													<select name="reviewedBy[]" id="reviewedByEid" class="form-control select2" title='<?php echo _translate("Please enter Reviewed By for EID Test"); ?>' onchange="changeDefaultReviewer(this.value,'eid');">
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
												<td>
													<select name="approvedBy[]" id="approvedByEid" class="form-control select2" title='<?php echo _translate("Please enter Approved By for EID Test"); ?>' onchange="changeDefaultApprover(this.value,'eid');">
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['covid19']) { ?>
											<tr class="covid19-access user-access-form" style="display: none;">
												<td style="text-align:center;">
													<?php echo _translate("Covid-19"); ?><input type="hidden" name="userTestType[]" id="userTestTypeCovid19" value="covid19" />
												</td>
												<td>
													<select name="reviewedBy[]" id="reviewedByCovid19" class="form-control select2" title='<?php echo _translate("Please enter Reviewed By for Covid19 Test"); ?>' onchange="changeDefaultReviewer(this.value,'covid19');">
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
												<td>
													<select name="approvedBy[]" id="approvedByCovid19" class="form-control select2" title='<?php echo _translate("Please enter Approved By for Covid19 Test"); ?>' onchange="changeDefaultApprover(this.value,'covid19');">
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['hepatitis']) { ?>
											<tr class="hepatitis-access user-access-form" style="display: none;">
												<td style="text-align:center;">
													<?php echo _translate("Hepatitis"); ?><input type="hidden" name="userTestType[]" id="userTestTypeHepatitis" value="hepatitis" />
												</td>
												<td>
													<select name="reviewedBy[]" id="reviewedByHepatitis" class="form-control select2" title='<?php echo _translate("Please enter Reviewed By for Hepatitis Test"); ?>' onchange="changeDefaultReviewer(this.value,'hepatitis');">
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
												<td>
													<select name="approvedBy[]" id="approvedByHepatitis" class="form-control select2" title='<?php echo _translate("Please enter Approved By for Hepatitis Test"); ?>' onchange="changeDefaultApprover(this.value,'hepatitis');">
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['tb']) { ?>
											<tr class="tb-access user-access-form" style="display: none;">
												<td style="text-align:center;">
													<?php echo _translate("TB"); ?><input type="hidden" name="userTestType[]" id="userTestTypeTb" value="tb" />
												</td>
												<td>
													<select name="reviewedBy[]" id="reviewedByTb" class="form-control select2" title='<?php echo _translate("Please enter Reviewed By for TB Test"); ?>' onchange="changeDefaultReviewer(this.value,'tb');">
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
												<td>
													<select name="approvedBy[]" id="approvedByTb" class="form-control select2" title='<?php echo _translate("Please select Approved By for TB Test"); ?>' onchange="changeDefaultApprover(this.value,'tb');">
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['cd4']) { ?>
											<tr class="cd4-access user-access-form" style="display: none;">
												<td style="text-align:center;">
													<?php echo _translate("CD4"); ?><input type="hidden" name="userTestType[]" id="userTestTypeCd4" value="cd4" />
												</td>
												<td>
													<select name="reviewedBy[]" id="reviewedByCd4" class="form-control select2" title='<?php echo _translate("Please enter Reviewed By for CD4 Test"); ?>' onchange="changeDefaultReviewer(this.value,'cd4');">
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
												<td>
													<select name="approvedBy[]" id="approvedByTb" class="form-control select2" title='<?php echo _translate("Please select Approved By for TB Test"); ?>' onchange="changeDefaultApprover(this.value,'cd4');">
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['generic-tests']) { ?>
											<tr class="generic-access user-access-form" style="display: none;">
												<td style="text-align:center;">
													<?php echo _translate("Other Lab Tests"); ?><input type="hidden" name="userTestType[]" id="testType1" value="generic-tests" />
												</td>
												<td>
													<select name="reviewedBy[]" id="reviewedByGeneric" class="form-control user-generic select2" title='<?php echo _translate("Please enter Reviewed By for Other Lab Test"); ?>' onchange="changeDefaultReviewer(this.value,'generic-tests');">
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
												<td>
													<select name="approvedBy[]" id="approvedByGeneric" class="form-control user-generic select2" title='<?php echo _translate("Please enter Approved By for Other Lab Test"); ?>' onchange="changeDefaultApprover(this.value,'generic-tests');">
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
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
										<?php if (SYSTEM_CONFIG['modules']['vl']) { ?>
											<tr id="vlTable" class="ctlCalibrator">
												<td align="left" style="text-align:center;">
													<?php echo _translate("VL"); ?><input type="hidden" name="testType[]" id="testType1" value="vl" />
												</td>
												<td><input type="text" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of In-House Controls in vl"); ?>' title='<?php echo _translate("Please enter No of In-House Controls in vl"); ?>' />
												</td>
												<td><input type="text" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of Manufacturer Controls in vl"); ?>' title='<?php echo _translate("Please enter No of Manufacturer Controls in vl"); ?>' />
												</td>
												<td><input type="text" name="noCalibrators[]" id="noCalibrators1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of Calibrators in vl"); ?>' title='<?php echo _translate("Please enter No of Calibrators in vl"); ?>' /></td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['eid']) { ?>
											<tr id="eidTable" class="ctlCalibrator">
												<td align="left" style="text-align:center;">
													<?php echo _translate("EID"); ?><input type="hidden" name="testType[]" id="testType1" value="eid" />
												</td>
												<td><input type="text" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of In-House Controls in EID"); ?>' title='<?php echo _translate("Please enter No of In-House Controls in EID"); ?>' />
												</td>
												<td><input type="text" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of Manufacturer Controls in EID"); ?>' title='<?php echo _translate("Please enter No of Manufacturer Controls in EID"); ?>' />
												</td>
												<td><input type="text" name="noCalibrators[]" id="noCalibrators1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of Calibrators in EID"); ?>' title='<?php echo _translate("Please enter No of Calibrators in EID"); ?>' />
												</td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['covid19']) { ?>
											<tr id="covid19Table" class="ctlCalibrator">
												<td align="left" style="text-align:center;">
													<?php echo _translate("Covid-19"); ?><input type="hidden" name="testType[]" id="testType1" value="covid19" />
												</td>
												<td><input type="text" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of In-House Controls in covid-19"); ?>' title='<?php echo _translate("Please enter No of In-House Controls in covid-19"); ?>' />
												</td>
												<td><input type="text" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of Manufacturer Controls in covid-19"); ?>' title='<?php echo _translate("Please enter No of Manufacturer Controls in covid-19"); ?>' />
												</td>
												<td><input type="text" name="noCalibrators[]" id="noCalibrators1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of Calibrators in covid-19"); ?>' title='<?php echo _translate("Please enter No of Calibrators in covid-19"); ?>' />
												</td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['hepatitis']) { ?>
											<tr id="hepatitisTable" class="ctlCalibrator">
												<td align="left" style="text-align:center;">
													<?php echo _translate("Hepatitis"); ?><input type="hidden" name="testType[]" id="testType1" value="hepatitis" />
												</td>
												<td><input type="text" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of In-House Controls in Hepatitis"); ?>' title='<?php echo _translate("Please enter No of In-House Controls in Hepatitis"); ?>' />
												</td>
												<td><input type="text" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of Manufacturer Controls in Hepatitis"); ?>' title='<?php echo _translate("Please enter No of Manufacturer Controls in Hepatitis"); ?>' />
												</td>
												<td><input type="text" name="noCalibrators[]" id="noCalibrators1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of Calibrators in Hepatitis"); ?>' title='<?php echo _translate("Please enter No of Calibrators in Hepatitis"); ?>' />
												</td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['tb']) { ?>
											<tr id="tbTable" class="ctlCalibrator">
												<td align="left" style="text-align:center;">
													<?php echo _translate("TB"); ?><input type="hidden" name="testType[]" id="testType1" value="tb" />
												</td>
												<td><input type="text" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of In-House Controls in TB"); ?>' title='<?php echo _translate("Please enter No of In-House Controls in TB"); ?>' />
												</td>
												<td><input type="text" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of Manufacturer Controls in TB"); ?>' title='<?php echo _translate("Please enter No of Manufacturer Controls in TB"); ?>' />
												</td>
												<td><input type="text" name="noCalibrators[]" id="noCalibrators1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of Calibrators in TB"); ?>' title='<?php echo _translate("Please enter No of Calibrators in TB"); ?>' /></td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['cd4']) { ?>
											<tr id="cd4Table" class="ctlCalibrator">
												<td align="left" style="text-align:center;">
													<?php echo _translate("CD4"); ?><input type="hidden" name="testType[]" id="testType1" value="cd4" />
												</td>
												<td><input type="text" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of In-House Controls in CD4"); ?>' title='<?php echo _translate("Please enter No of In-House Controls in CD4"); ?>' />
												</td>
												<td><input type="text" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of Manufacturer Controls in CD4"); ?>' title='<?php echo _translate("Please enter No of Manufacturer Controls in CD4"); ?>' />
												</td>
												<td><input type="text" name="noCalibrators[]" id="noCalibrators1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of Calibrators in CD4"); ?>' title='<?php echo _translate("Please enter No of Calibrators in CD4"); ?>' /></td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['generic-tests']) { ?>
											<tr id="generic-testsTable" class="ctlCalibrator">
												<td align="left" style="text-align:center;">
													<?php echo _translate("Other Lab Tests"); ?><input type="hidden" name="testType[]" id="testType1" value="generic-tests" />
												</td>
												<td><input type="text" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of In-House Controls in Other Lab Tests"); ?>' title='<?php echo _translate("Please enter No of In-House Controls in Other Lab Tests"); ?>' />
												</td>
												<td><input type="text" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of Manufacturer Controls in Other Lab Tests"); ?>' title='<?php echo _translate("Please enter No of Manufacturer Controls in Other Lab Tests"); ?>' />
												</td>
												<td><input type="text" name="noCalibrators[]" id="noCalibrators1" class="form-control forceNumeric" placeholder='<?php echo _translate("No of Calibrators in Other Lab Tests"); ?>' title='<?php echo _translate("Please enter No of Calibrators in Other Lab Tests"); ?>' />
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
							<div class="table-container">
								<table aria-describedby="table" border="0" class="table table-striped table-bordered table-condensed" aria-hidden="true" style="width:100%;">
									<thead>
										<tr>
											<th style="text-align:center; width: 20%;">
												<?= _translate("Machine Name"); ?> <span class="mandatory">*</span>
											</th>
											<th style="text-align:center; width: 35%; min-width: 350px;">
												<?= _translate("Date Format"); ?> <span class="mandatory">*</span>
												<br><small style="font-weight: normal; color: #666; font-size: 11px;">
													📅 Enter sample date to auto-detect format
												</small>
											</th>
											<th style="text-align:center; width: 20%;">
												<?= _translate("Instrument File Name"); ?> <span class="mandatory">*</span>
											</th>
											<th style="text-align:center; width: 5%;">
												<?= _translate("Is this a POC Device?"); ?>
											</th>
											<th style="text-align:center; width: 10%;">
												<?php echo _translate("Action"); ?>
											</th>
										</tr>
									</thead>
									<tbody id="machineTable">
										<?php
										// FOR ADD FORM - single default row
										if (!isset($configMachineInfo) || empty($configMachineInfo)) { ?>
											<tr>
												<td>
													<input type="text" name="configMachineName[]" id="configMachineName1" class="form-control configMachineName isRequired" placeholder='<?php echo _translate("Machine Name"); ?>' title='<?php echo _translate("Please enter machine name"); ?>' onblur="checkDuplication(this, 'configMachineName');" />
												</td>
												<td class="smart-date-cell">
													<!-- Smart Date Format Detection Interface -->
													<div style="margin-bottom: 8px;">
														<input type="text"
															name="sampleDateInput[]"
															id="sampleDateInput1"
															class="form-control"
															placeholder="📅 <?= _translate("Enter sample date (e.g., 06.19.2025 11:19 AM)"); ?>"
															title="<?= _translate("Enter a sample date from your instrument files"); ?>"
															style="border: 2px solid #007bff; font-size: 13px;"
															data-smart-date-format
															data-suggestions-container="formatSuggestions1"
															data-hidden-field="dateFormat1"
															data-manual-input="manualFormatInput1"
															data-row-id="1"
															data-default-format="d/m/Y H:i" />
														<small style="color: #666; font-size: 11px;">💡 <?= _translate("Enter any date from your instrument to auto-detect format"); ?></small>
													</div>

													<!-- Smart Suggestions Container -->
													<div id="formatSuggestions1" class="format-suggestions-container"></div>

													<!-- Hidden field to store selected format (this is what gets submitted) -->
													<input type="hidden" name="dateFormat[]" id="dateFormat1" value="d/m/Y H:i" />

													<!-- Manual Format Input (hidden by default) -->
													<div id="manualFormatInput1" style="display: none; margin-top: 8px;">
														<input type="text"
															class="form-control"
															placeholder="<?= _translate("Manual: d/m/Y H:i", true); ?>"
															title="<?= _translate("Enter date format manually", true); ?>"
															style="border: 1px solid #ccc; font-size: 12px;"
															onchange="document.getElementById('dateFormat1').value = this.value;" />
													</div>
												</td>
												<td>
													<select name="fileName[]" id="fileName1" class="form-control select2 instrumentFile">
														<option value=""><?php echo _translate('Select File'); ?></option>
														<?php foreach ($fileList as $fileName) { ?>
															<option value="<?= $fileName; ?>"><?= $fileName; ?></option>
														<?php } ?>
													</select>
												</td>
												<td>
													<div class="col-md-3">
														<input type="checkbox" id="pocdevice1" name="pocdevice[]" value="" onclick="getLatiLongi(1);">
													</div>
													<div class="latLong1" style="display:none">
														<div class="col-md-4">
															<input type="text" name="latitude[]" id="latitude1" class="form-control" placeholder='<?php echo _translate("Latitude"); ?>' data-placement="bottom" title='<?php echo _translate("Latitude"); ?>' />
														</div>
														<div class="col-md-4">
															<input type="text" name="longitude[]" id="longitude1" class="form-control" placeholder='<?php echo _translate("Longitude"); ?>' data-placement="bottom" title='<?php echo _translate("Longitude"); ?>' />
														</div>
													</div>
												</td>
												<td align="center" style="vertical-align:middle;">
													<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;&nbsp;<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
												</td>
											</tr>
											<?php } else {
											// FOR EDIT FORM - existing machines
											$i = 1;
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
														<input type="text" name="configMachineName[]" id="configMachineName<?php echo $i; ?>" class="form-control configMachineName isRequired" placeholder="<?php echo _translate('Machine Name'); ?>" title="<?php echo _translate('Please enter machine name'); ?>" value="<?php echo $machine['config_machine_name']; ?>" onblur="checkDuplication(this, 'configMachineName');" />
													</td>
													<td class="smart-date-cell">
														<!-- Smart Date Format Detection Interface for Edit -->
														<div style="margin-bottom: 8px;">
															<input type="text"
																name="sampleDateInput[]"
																id="sampleDateInput<?php echo $i; ?>"
																class="form-control"
																placeholder="📅 Enter sample date to change format"
																title="Enter a sample date from your instrument files"
																style="border: 2px solid #007bff; font-size: 13px;"
																oninput="debounceDetection(this.value, <?php echo $i; ?>)" />
															<small style="color: #666; font-size: 11px;">💡 Current: <code><?php echo $machine['date_format'] ?: 'd/m/Y H:i'; ?></code></small>
														</div>

														<!-- Smart Suggestions Container -->
														<div id="formatSuggestions<?php echo $i; ?>" class="format-suggestions-container"></div>

														<!-- Hidden field with current format -->
														<input type="hidden" name="dateFormat[]" id="dateFormat<?php echo $i; ?>" value="<?php echo $machine['date_format'] ?: 'd/m/Y H:i'; ?>" />

														<!-- Manual Format Input (hidden by default) -->
														<div id="manualFormatInput<?php echo $i; ?>" style="display: none; margin-top: 8px;">
															<input type="text"
																class="form-control"
																placeholder="<?= _translate("Manual: d/m/Y H:i", true); ?>"
																title="<?= _translate("Enter date format manually", true); ?>"
																style="border: 1px solid #ccc; font-size: 12px;"
																value="<?php echo $machine['date_format'] ?: 'd/m/Y H:i'; ?>"
																onchange="document.getElementById('dateFormat<?php echo $i; ?>').value = this.value;" />
														</div>

														<!-- Toggle Link -->
														<div style="text-align: center; margin-top: 5px;">
															<a href="javascript:void(0);"
																onclick="toggleManualFormat(<?php echo $i; ?>)"
																style="font-size: 11px; color: #007bff; text-decoration: none;">
																⚙️ Manual entry
															</a>
														</div>
													</td>
													<td>
														<select name="fileName[]" id="fileName<?php echo $i; ?>" class="form-control select2 instrumentFile">
															<option value=""><?php echo _translate('Select File'); ?></option>
															<?php foreach ($fileList as $fileName) { ?>
																<option value="<?= $fileName; ?>" <?php if ($machine['file_name'] == $fileName) echo "selected='selected'"; ?>><?= $fileName; ?></option>
															<?php } ?>
														</select>
													</td>
													<td>
														<div class="col-md-3">
															<input type="checkbox" id="pocdevice<?php echo $i; ?>" name="pocdevice[]" value="" onclick="getLatiLongi(<?php echo $i; ?>);" <?php echo $check; ?>>
														</div>
														<div class="latLong<?php echo $i; ?>" style="<?php echo $style; ?>">
															<div class="col-md-4">
																<input type="text" name="latitude[]" id="latitude<?php echo $i; ?>" value="<?php echo $machine['latitude']; ?>" class="form-control" placeholder="<?php echo _translate('Latitude'); ?>" data-placement="bottom" title="<?php echo _translate('Latitude'); ?>" />
															</div>
															<div class="col-md-4">
																<input type="text" name="longitude[]" id="longitude<?php echo $i; ?>" value="<?php echo $machine['longitude']; ?>" class="form-control" placeholder="<?php echo _translate('Longitude'); ?>" data-placement="bottom" title="<?php echo _translate('Longitude'); ?>" />
															</div>
														</div>
													</td>
													<td align="center" style="vertical-align:middle;">
														<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>
														<?php if ($i > 1) { ?>
															&nbsp;&nbsp;<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
														<?php } ?>
													</td>
												</tr>
										<?php
												$i++;
											}
										} ?>
									</tbody>
								</table>
							</div>
						</div>


					</div>
					<!-- /.box-body -->
					<div class="box-footer">
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


<?php
require_once APPLICATION_PATH . '/footer.php';
?>
<?php require_once WEB_ROOT . '/assets/js/smart-date-format.js.php'; ?>

<script type="text/javascript">
	let tableRowId = 2;

	$(document).ready(function() {
		// Event delegation for date format detection - works for all current and future inputs
		$(document).on('input', 'input[name="sampleDateInput[]"]', function() {
			const input = $(this);
			const value = input.val();
			const rowIndex = getRowIndexFromInput(input);

			if (value.trim() === '') {
				clearFormatSuggestions(rowIndex);
				return;
			}

			// Debounce the detection
			clearTimeout(input.data('detectTimeout'));
			input.data('detectTimeout', setTimeout(() => {
				detectDateFormatForRow(value, rowIndex);
			}, 600));
		});

		// Handle focus - show existing suggestions if available
		$(document).on('focus', 'input[name="sampleDateInput[]"]', function() {
			const input = $(this);
			const rowIndex = getRowIndexFromInput(input);
			const value = input.val();

			updateInputGuidance(value, rowIndex);
		});

		// Handle format selection clicks
		$(document).on('click', '.format-suggestion', function() {
			const format = $(this).data('format');
			const rowIndex = $(this).data('row-index');
			selectDateFormat(format, rowIndex);
		});

		$(".select2").select2({
			width: '100%',
			placeholder: '<?php echo _translate("-- Select --"); ?>'
		});

		$("#supportedTests").selectize({
			plugins: ["restore_on_backspace", "remove_button", "clear_button"],
		});

		$('input').tooltip();

		$('#supportedTests').on('change', function(e) {
			var data = $('#supportedTests').val();
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

	// Helper function to get row index from input element
	function getRowIndexFromInput(input) {
		const inputId = input.attr('id');
		return inputId.replace('sampleDateInput', '');
	}

	// Detect date format for a specific row
	async function detectDateFormatForRow(inputValue, rowIndex) {
		const container = $(`#formatSuggestions${rowIndex}`);

		if (!container.length) {
			console.error('Format suggestions container not found for row:', rowIndex);
			return;
		}

		showLoadingIndicator(rowIndex);

		try {
			const response = await $.post('/includes/smart-date-format.php', {
				input: inputValue.trim(),
				action: 'smart_detect'
			});

			if (response.success) {
				if (response.input_type === 'format') {
					showFormatStringSuggestions(response.suggestions, rowIndex, inputValue);
				} else if (response.input_type === 'sample' && response.suggestions.length > 0) {
					showFormatSuggestions(response.suggestions, rowIndex);
				} else {
					showNoFormatFound(inputValue, rowIndex);
				}
			} else {
				showNoFormatFound(inputValue, rowIndex);
			}
		} catch (error) {
			console.error('Date format detection failed:', error);
			showDetectionError(inputValue, rowIndex, error);
		}
	}

	// Show loading indicator
	function showLoadingIndicator(rowIndex) {
		const container = $(`#formatSuggestions${rowIndex}`);
		container.html(`
        <div class="format-loading">
            <div class="spinner"></div>
            🔄 Analyzing date format...
        </div>
    `);
	}

	// Show format suggestions
	function showFormatSuggestions(suggestions, rowIndex) {
		const container = $(`#formatSuggestions${rowIndex}`);
		const uniqueSuggestions = removeDuplicates(suggestions, 'format');

		const suggestionsHtml = uniqueSuggestions.map((suggestion, index) => {
			return buildSuggestionHtml(suggestion, index, rowIndex);
		}).join('');

		container.html(suggestionsHtml);
	}

	// Show format string suggestions
	function showFormatStringSuggestions(suggestions, rowIndex, originalInput) {
		const container = $(`#formatSuggestions${rowIndex}`);

		const suggestionsHtml = suggestions.map((suggestion, index) => {
			if (suggestion.error) {
				return buildErrorSuggestionHtml(suggestion);
			}
			return buildSuggestionHtml(suggestion, index, rowIndex);
		}).join('');

		container.html(suggestionsHtml);
	}

	// Build suggestion HTML
	function buildSuggestionHtml(suggestion, index, rowIndex) {
		const confidenceClass = `confidence-${suggestion.confidence}`;
		const isUserFormat = suggestion.is_user_format || false;
		const userFormatBadge = isUserFormat ?
			'<span class="format-badge user-format">YOUR FORMAT</span>' : '';

		const style = isUserFormat ? 'border: 2px solid #007bff; background: #f0f8ff;' : '';

		return `
        <div class="format-suggestion ${confidenceClass}"
             data-format="${suggestion.format}"
             data-row-index="${rowIndex}"
             title="Click to select this format"
             style="${style} cursor: pointer;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div style="flex: 1;">
                    <strong style="color: #333;">${suggestion.name}${userFormatBadge}</strong><br>
                    <code style="background: #f1f3f4; padding: 1px 4px; border-radius: 2px; font-size: 11px;">${suggestion.format}</code><br>
                    <small style="color: #666;">${suggestion.description}</small>
                    ${suggestion.example ?
            `<div style="color: #28a745; font-size: 10px; margin-top: 2px;">✓ Example: ${suggestion.example}</div>` : ''
        }
                    ${isUserFormat ?
            '<div style="color: #007bff; font-size: 10px; margin-top: 2px;">📝 Format you entered</div>' : ''
        }
                </div>
                <span class="format-badge ${suggestion.confidence}">
                    ${suggestion.confidence.toUpperCase()}
                </span>
            </div>
        </div>
    `;
	}

	// Build error suggestion HTML
	function buildErrorSuggestionHtml(suggestion) {
		const correctionsHtml = suggestion.corrections ?
			suggestion.corrections.map(correction =>
				`<div class="format-corrections">
                <div class="correction-item">
                    <strong style="color: #28a745;">Suggested:</strong>
                    <span class="correction-format">${correction.format}</span>
                </div>
                <small style="color: #666;">${correction.description}</small>
            </div>`
			).join('') : '';

		return `
        <div class="format-suggestion confidence-low"
             style="border-left-color: #dc3545 !important; background: #f8d7da;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div style="flex: 1;">
                    <strong style="color: #721c24;">❌ Invalid Format</strong><br>
                    <code style="background: #f5c6cb; padding: 1px 4px; border-radius: 2px; font-size: 11px;">${suggestion.format}</code><br>
                    <small style="color: #721c24;">${suggestion.error}</small>
                    ${correctionsHtml}
                </div>
                <span class="format-badge error">ERROR</span>
            </div>
        </div>
    `;
	}

	// Select a date format
	function selectDateFormat(format, rowIndex) {
		const input = $(`#sampleDateInput${rowIndex}`);
		const hiddenField = $(`#dateFormat${rowIndex}`);
		const container = $(`#formatSuggestions${rowIndex}`);

		// Update hidden field
		hiddenField.val(format);

		// Update input appearance
		input.removeClass('format-detected sample-detected')
			.addClass('format-selected')
			.prop('placeholder', `✅ Format locked: ${format}`)
			.css('border-color', '#28a745');

		if (input.val() && !input.val().includes(' ✓')) {
			input.val(input.val() + ' ✓');
		}

		// Show success message
		container.html(`
        <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; padding: 8px; color: #155724;">
            ✅ <strong>Selected format:</strong> <code>${format}</code>
            <button onclick="clearSelection(${rowIndex})" style="float: right; background: none; border: none; color: #007bff; cursor: pointer;">Change</button>
        </div>
    `);

		console.log(`Format selected for row ${rowIndex}:`, format);
	}

	// Clear selection
	function clearSelection(rowIndex) {
		const input = $(`#sampleDateInput${rowIndex}`);
		const hiddenField = $(`#dateFormat${rowIndex}`);
		const container = $(`#formatSuggestions${rowIndex}`);

		// Reset input
		input.removeClass('format-selected')
			.prop('placeholder', '📅 Enter sample date')
			.css('border-color', '#007bff');

		if (input.val().includes(' ✓')) {
			input.val(input.val().replace(' ✓', ''));
		}

		// Reset hidden field
		hiddenField.val('d/m/Y H:i');

		// Clear container
		container.html('');

		// Trigger detection again if there's still a value
		if (input.val().trim()) {
			detectDateFormatForRow(input.val(), rowIndex);
		}
	}

	// Clear format suggestions
	function clearFormatSuggestions(rowIndex) {
		const container = $(`#formatSuggestions${rowIndex}`);
		container.html('');
	}

	// Show no format found
	function showNoFormatFound(input, rowIndex) {
		const container = $(`#formatSuggestions${rowIndex}`);
		container.html(`
        <div class="no-format-found">
            <strong>🤔 Could not detect format</strong><br>
            <small>Try: 06/19/2025, 19.06.2025 23:19, 2025-06-19</small>
        </div>
    `);
	}

	// Show detection error
	function showDetectionError(input, rowIndex, error) {
		const container = $(`#formatSuggestions${rowIndex}`);
		container.html(`
        <div class="no-format-found">
            <strong>❌ Detection failed</strong><br>
            <small>Network error or server issue</small>
        </div>
    `);
	}

	// Update input guidance
	function updateInputGuidance(input, rowIndex) {
		const inputElement = $(`#sampleDateInput${rowIndex}`);
		const helpText = inputElement.siblings('small').first();

		if (looksLikeDateFormat(input)) {
			inputElement.removeClass('sample-detected format-selected')
				.addClass('format-detected');
			if (helpText.length) {
				helpText.html('📝 Date format detected - analyzing...')
					.css('color', '#9c27b0');
			}
		} else if (input.length > 0) {
			inputElement.removeClass('format-detected format-selected')
				.addClass('sample-detected');
			if (helpText.length) {
				helpText.html('📅 Sample date detected - finding format...')
					.css('color', '#007bff');
			}
		} else {
			inputElement.removeClass('format-detected sample-detected format-selected');
			if (helpText.length) {
				helpText.html('💡 Enter any date from your instrument to auto-detect format')
					.css('color', '#666');
			}
		}
	}

	// Check if input looks like a date format
	function looksLikeDateFormat(input) {
		const formatChars = ['Y', 'y', 'm', 'n', 'd', 'j', 'H', 'G', 'h', 'g', 'i', 's', 'A', 'M'];
		const hasFormatChars = formatChars.some(char => input.includes(char));
		const hasSeparators = /[\/\-\.\s:]/.test(input);
		return hasFormatChars && hasSeparators && input.length > 3;
	}

	// Remove duplicates helper
	function removeDuplicates(array, key) {
		return array.filter((item, index) =>
			array.findIndex(i => i[key] === item[key]) === index
		);
	}

	// Existing functions remain the same
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

	$("input[type='radio']").click(function() {
		var id = $(this).attr('id');
		if (id == 'logAndAbsoluteInSameColumnYes') {
			$("#absRow").hide();
			$("#absoluteValCol,#absoluteValRow").val("");
			$("label[for*='logValCol']").html("Log/Absolute Value");
			$("#logValCol").attr("placeholder", "Log/Absolute Val Column");
			$("#logValCol").attr("title", "Please enter log/absolute val column");
			$("#logValRow").attr("placeholder", "Log/Absolute Val Row");
			$("#logValRow").attr("title", "Please enter log/absolute val row");
		} else {
			$("#absRow").show();
			$("label[for*='logValCol']").html("Log Value");
			$("#logValCol").attr("placeholder", "Log Val Column");
			$("#logValCol").attr("title", "Please enter log val column");
			$("#logValRow").attr("placeholder", "Log Val Row");
			$("#logValRow").attr("title", "Please enter log val row");
		}
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

	<?php
	$fileOptions = '';
	foreach ($fileList as $fileName) {
		$fileOptions .= '<option value="' . $fileName . '">' . $fileName . '</option>';
	}
	?>

	function insRow() {
		const rl = document.getElementById("machineTable").rows.length;
		const a = document.getElementById("machineTable").insertRow(rl);
		a.setAttribute("style", "display:none");
		const b = a.insertCell(0);
		const c = a.insertCell(1); // Smart date format cell
		const d = a.insertCell(2);
		const e = a.insertCell(3);
		const f = a.insertCell(4);
		f.setAttribute("align", "center");
		f.setAttribute("style", "vertical-align:middle");

		b.innerHTML = `<input type="text" name="configMachineName[]" id="configMachineName${tableRowId}" class="isRequired configMachineName form-control" placeholder="<?php echo _translate('Machine Name'); ?>" title="<?php echo _translate('Please enter machine name'); ?>" onblur="checkDuplication(this, 'configMachineName');" />`;

		// Simple date format cell - no complex initialization needed!
		c.innerHTML = `
        <div class="smart-date-cell">
            <div style="margin-bottom: 8px;">
                <input type="text"
                       name="sampleDateInput[]"
                       id="sampleDateInput${tableRowId}"
                       class="form-control"
                       placeholder="📅 Enter sample date"
                       title="Enter a sample date from your instrument files"
                       style="border: 2px solid #007bff; font-size: 13px;" />
                <small style="color: #666; font-size: 11px;">💡 Enter any date from your instrument</small>
            </div>
            <div id="formatSuggestions${tableRowId}" class="format-suggestions-container"></div>
            <input type="hidden" name="dateFormat[]" id="dateFormat${tableRowId}" value="d/m/Y H:i" />
        </div>
    `;

		d.innerHTML = `<select name="fileName[]" id="fileName${tableRowId}" class="form-control select2 instrumentFile"><option value=""><?php echo _translate('Select File'); ?></option><?= $fileOptions; ?></select>`;
		e.innerHTML = `<div class="col-md-3"><input type="checkbox" id="pocdevice${tableRowId}" name="pocdevice[]" value="" onclick="getLatiLongi(${tableRowId});"></div><div class="latLong${tableRowId}" style="display:none"><div class="col-md-4"><input type="text" name="latitude[]" id="latitude${tableRowId}" class="form-control" placeholder="<?php echo _translate('Latitude'); ?>" /></div><div class="col-md-4"><input type="text" name="longitude[]" id="longitude${tableRowId}" class="form-control" placeholder="<?php echo _translate('Longitude'); ?>" /></div></div>`;
		f.innerHTML = `<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;&nbsp;<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>`;

		$(a).fadeIn(800);
		$(`#fileName${tableRowId}`).select2({
			width: '100%'
		});

		// No initialization needed! Event delegation handles everything automatically

		// Auto-populate with configuration file if available
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
							$(`#fileName${tableRowId}`).append(`<option value="${configFileName}">${configFileName}</option>`);
							$(`#fileName${tableRowId}`).select2({
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
			const rl = document.getElementById("machineTable").rows.length;
			if (rl == 0) {
				insRow();
			}
		});
	}

	function checkDuplication(obj, name) {
		const dublicateObj = document.getElementsByName(name + "[]");
		for (let m = 0; m < dublicateObj.length; m++) {
			if (obj.value != '' && obj.id != dublicateObj[m].id && obj.value == dublicateObj[m].value) {
				alert('Duplicate value not allowed');
				$('#' + obj.id).val('');
			}
		}
	}

	function getLatiLongi(id) {
		if ($("#pocdevice" + id).is(':checked')) {
			$(".latLong" + id).css("display", "block");
		} else {
			$(".latLong" + id).css("display", "none");
		}
	}

	function changeDefaultReviewer(value, testType) {
		const userConfirmed = confirm("Do you want to update existing records? ");
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
		const userConfirmed = confirm("Do you want to update existing records? ");
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

	// Form validation with performance monitoring
	function validateNow() {
		const timer = Utilities.timer('Form Validation');

		// Check that all rows have either a detected format or default format
		let allValid = true;

		$('input[name="sampleDateInput[]"]').each(function(index) {
			const rowId = getRowIndexFromInput($(this));
			const sampleDate = $(this).val();
			const selectedFormat = $(`#dateFormat${rowId}`).val();

			// If user entered a sample date but didn't select a format
			if (sampleDate && selectedFormat === 'd/m/Y H:i') {
				if (!confirm(`Row ${parseInt(rowId)}: You entered a sample date but haven't selected a detected format. Use default format 'd/m/Y H:i'?`)) {
					allValid = false;
					return false;
				}
			}
		});

		if (!allValid) {
			timer.stop();
			return false;
		}

		// Auto-fill empty fields
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

		// Run existing validation
		const flag = deforayValidator.init({
			formId: 'instrumentForm'
		});

		if (flag) {
			timer.stop();
			$.blockUI();
			document.getElementById('instrumentForm').submit();
		} else {
			timer.stop();
		}
	}
</script>