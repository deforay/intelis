<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Services\UsersService;
use App\Services\FacilitiesService;

require_once APPLICATION_PATH . '/header.php';

$usersService = ContainerRegistry::get(UsersService::class);
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$vlsmSystemConfig = $general->getSystemConfig();
$labNameList = $facilitiesService->getTestingLabs();

/** @var SystemService $systemService */
$systemService = ContainerRegistry::get(SystemService::class);

$activeTestModules = $systemService->getActiveTestModules();

$userList = $usersService->getAllUsers(null, null, 'drop-down');
?>
<style>
	.tooltip-inner {
		background-color: #fff;
		color: #000;
		border: 1px solid #000;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-sharp fa-solid fa-gears"></em> <?php echo _("Add Instrument"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Add Instrument"); ?></li>
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
				<form class="form-horizontal" method='post' name='addImportConfigForm' id='addImportConfigForm' autocomplete="off" action="addImportConfigHelper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="configurationName" class="col-lg-4 control-label"><?php echo _("Instrument Name"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="configurationName" name="configurationName" placeholder='<?php echo _("eg. Roche or Abbott"); ?>' title='<?php echo _("Please enter instrument name"); ?>' onblur="checkNameValidation('instruments','machine_name',this,null,'<?php echo _('This instrument name already exists.Try another name'); ?>',null);setConfigFileName();" onkeypress="setConfigFileName();" />
									</div>
								</div>
							</div>
						</div>
						<?php if(isset($vlsmSystemConfig['sc_user_type']) && $vlsmSystemConfig['sc_user_type'] == 'vluser'){ ?>
							<input type="hidden" value="<?php echo $general->getSystemConfig('sc_testing_lab_id');?>" name="testingLab"/>
						<?php  }else{ ?>
								<div class="row">
									<div class="col-md-6">
										<div class="form-group">
											<label for="testingLab" class="col-lg-4 control-label"><?php echo _("Testing Lab"); ?> <span class="mandatory">*</span></label>
											<div class="col-lg-7">
												<select class="form-control select2" id="testingLab" name="testingLab" title="Please select the testing lab">
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
									<label for="supportedTests" class="col-lg-4 control-label"><?php echo _("Supported Tests"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select multiple class="form-control" id="supportedTests" name="supportedTests[]">
											<?php if (!empty($activeTestModules) && in_array('vl', $activeTestModules)) { ?>
												<option value='vl'><?php echo _("Viral Load"); ?></option>
											<?php }
											if (!empty($activeTestModules) && in_array('eid', $activeTestModules)) { ?>
												<option value='eid'><?php echo _("EID"); ?></option>
											<?php }
											if (!empty($activeTestModules) && in_array('covid19', $activeTestModules)) { ?>
												<option value='covid19'><?php echo _("Covid-19"); ?></option>
											<?php }
											if (!empty($activeTestModules) && in_array('hepatitis', $activeTestModules)) { ?>
												<option value='hepatitis'><?php echo _("Hepatitis"); ?></option>
											<?php }
											if (!empty($activeTestModules) && in_array('tb', $activeTestModules)) { ?>
												<option value='tb'><?php echo _("TB"); ?></option>
											<?php } ?> ?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="configurationFileName" class="col-lg-4 control-label"><?php echo _("Instrument File"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="configurationFile" name="configurationFile" placeholder='<?php echo _("eg. roche.php or abbott.php"); ?>' title='<?php echo _("Please enter machine name"); ?>' onblur='checkNameValidation("instruments","import_machine_file_name",this,null,"<?php echo _("This file name already exists.Try another name"); ?>",null)' />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="configurationFileName" class="col-lg-4 control-label"><?php echo _("Lower Limit"); ?></label>
									<div class="col-lg-7">
										<input type="text" class="form-control forceNumeric" id="lowerLimit" name="lowerLimit" placeholder='<?php echo _("eg. 20"); ?>' title='<?php echo _("Please enter lower limit"); ?>' />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="configurationFileName" class="col-lg-4 control-label"><?php echo _("Higher Limit"); ?></label>
									<div class="col-lg-7">
										<input type="text" class="form-control forceNumeric" id="higherLimit" name="higherLimit" placeholder='<?php echo _("eg. 10000000"); ?>' title='<?php echo _("Please enter lower limit"); ?>' />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="maxNOfSamplesInBatch" class="col-lg-4 control-label"><?php echo _("Maximum No. of Samples In a Batch"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control forceNumeric isRequired" id="maxNOfSamplesInBatch" name="maxNOfSamplesInBatch" placeholder='<?php echo _("Max. no of samples"); ?>' title='<?php echo _("Please enter max no of samples in a row"); ?>' />
									</div>
								</div>
							</div>
						</div>
						<?php if (SYSTEM_CONFIG['modules']['vl']) { ?>
							<div class="row lowVlResultText">
								<div class="col-md-12">
									<div class="form-group">
										<label for="lowVlResultText" class="col-lg-2 control-label"><?php echo _("Low VL Result Text"); ?> </label>
										<div class="col-lg-7">
											<textarea class="form-control" id="lowVlResultText" name="lowVlResultText" placeholder='<?php echo _("Comma separated Low Viral Load Result Text for eg. Target Not Detected, TND, < 20, < 40"); ?>' title='<?php echo _("Low Viral Load Result Text for eg. Target Not Detected, TND, < 20, < 40"); ?>'></textarea>
										</div>
									</div>
								</div>
							</div>
						<?php } ?>
						<!-- <div class="box-header">
							<h3 class="box-title ">Machine Names</h3>
						</div> -->
						<?php if (SYSTEM_CONFIG['modules']['vl'] || SYSTEM_CONFIG['modules']['eid'] || SYSTEM_CONFIG['modules']['covid19']) { ?>
							<div class="box-body">
								<table aria-describedby="table" border="0" class="user-access table table-striped table-bordered table-condensed" style="width:100%;display:none;">
									<thead>
										<tr>
											<th style="text-align:center;"><?php echo _("Test Type"); ?></th>
											<th style="text-align:center;"><?php echo _("Default Reviewer"); ?></th>
											<th style="text-align:center;"><?php echo _("Default Approver"); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php if (SYSTEM_CONFIG['modules']['vl']) { ?>
											<tr class="vl-access user-access-form" style="display: none;">
												<td style="text-align:center;"><?php echo _("VL"); ?><input type="hidden" name="userTestType[]" id="userTestTypeVl" value="vl" /></td>
												<td>
													<select name="reviewedBy[]" id="reviewedByVl" class="form-control select2" title='<?php echo _("Please enter Reviewed By for VL Test"); ?>'>
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
												<td>
													<select name="approvedBy[]" id="approvedByVl" class="form-control select2" title='<?php echo _("Please enter Approved By for VL Test"); ?>'>
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['eid']) { ?>
											<tr class="eid-access user-access-form" style="display: none;">
												<td style="text-align:center;"><?php echo _("EID"); ?><input type="hidden" name="userTestType[]" id="userTestTypeEid" value="eid" /></td>
												<td>
													<select name="reviewedBy[]" id="reviewedByEid" class="form-control select2" title='<?php echo _("Please enter Reviewed By for EID Test"); ?>'>
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
												<td>
													<select name="approvedBy[]" id="approvedByEid" class="form-control select2" title='<?php echo _("Please enter Approved By for EID Test"); ?>'>
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['covid19']) { ?>
											<tr class="covid19-access user-access-form" style="display: none;">
												<td style="text-align:center;"><?php echo _("Covid-19"); ?><input type="hidden" name="userTestType[]" id="userTestTypeCovid19" value="covid19" /></td>
												<td>
													<select name="reviewedBy[]" id="reviewedByCovid19" class="form-control select2" title='<?php echo _("Please enter Reviewed By for Covid19 Test"); ?>'>
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
												<td>
													<select name="approvedBy[]" id="approvedByCovid19" class="form-control select2" title='<?php echo _("Please enter Approved By for Covid19 Test"); ?>'>
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['hepatitis']) { ?>
											<tr class="hepatitis-access user-access-form" style="display: none;">
												<td style="text-align:center;"><?php echo _("Hepatitis"); ?><input type="hidden" name="userTestType[]" id="userTestTypeHepatitis" value="hepatitis" /></td>
												<td>
													<select name="reviewedBy[]" id="reviewedByHepatitis" class="form-control select2" title='<?php echo _("Please enter Reviewed By for Hepatitis Test"); ?>'>
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
												<td>
													<select name="approvedBy[]" id="approvedByHepatitis" class="form-control select2" title='<?php echo _("Please enter Approved By for Hepatitis Test"); ?>'>
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['tb']) { ?>
											<tr class="tb-access user-access-form" style="display: none;">
												<td style="text-align:center;"><?php echo _("TB"); ?><input type="hidden" name="userTestType[]" id="userTestTypeTb" value="tb" /></td>
												<td>
													<select name="reviewedBy[]" id="reviewedByTb" class="form-control select2" title='<?php echo _("Please enter Reviewed By for TB Test"); ?>'>
														<?php echo $general->generateSelectOptions($userList, null, '--Select--'); ?>
													</select>
												</td>
												<td>
													<select name="approvedBy[]" id="approvedByTb" class="form-control select2" title='<?php echo _("Please enter Approved By for TB Test"); ?>'>
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
											<th style="text-align:center;"><?php echo _("Test Type"); ?></th>
											<th style="text-align:center;"><?php echo _("Number of In-House Controls"); ?></th>
											<th style="text-align:center;"><?php echo _("Number of Manufacturer Controls"); ?></th>
											<th style="text-align:center;"><?php echo _("No. Of Calibrators"); ?></th>
										</tr>
									</thead>
									<tbody id="testTypesTable">
										<?php if (SYSTEM_CONFIG['modules']['vl']) { ?>
											<tr id="vlTable" class="ctlCalibrator">
												<td align="left" style="text-align:center;"><?php echo _("VL"); ?><input type="hidden" name="testType[]" id="testType1" value="vl" /></td>
												<td><input type="text" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control" placeholder='<?php echo _("No of In-House Controls in vl"); ?>' title='<?php echo _("Please enter No of In-House Controls in vl"); ?>' /></td>
												<td><input type="text" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control" placeholder='<?php echo _("No of Manufacturer Controls in vl"); ?>' title='<?php echo _("Please enter No of Manufacturer Controls in vl"); ?>' /></td>
												<td><input type="text" name="noCalibrators[]" id="noCalibrators1" class="form-control" placeholder='<?php echo _("No of Calibrators in vl"); ?>' title='<?php echo _("Please enter No of Calibrators in vl"); ?>' /></td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['eid']) { ?>
											<tr id="eidTable" class="ctlCalibrator">
												<td align="left" style="text-align:center;"><?php echo _("EID"); ?><input type="hidden" name="testType[]" id="testType1" value="eid" /></td>
												<td><input type="text" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control" placeholder='<?php echo _("No of In-House Controls in eid"); ?>' title='<?php echo _("Please enter No of In-House Controls in eid"); ?>' /></td>
												<td><input type="text" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control" placeholder='<?php echo _("No of Manufacturer Controls in eid"); ?>' title='<?php echo _("Please enter No of Manufacturer Controls in eid"); ?>' /></td>
												<td><input type="text" name="noCalibrators[]" id="noCalibrators1" class="form-control" placeholder='<?php echo _("No of Calibrators in eid"); ?>' title='<?php echo _("Please enter No of Calibrators in eid"); ?>' /></td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['covid19']) { ?>
											<tr id="covid19Table" class="ctlCalibrator">
												<td align="left" style="text-align:center;"><?php echo _("Covid-19"); ?><input type="hidden" name="testType[]" id="testType1" value="covid19" /></td>
												<td><input type="text" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control" placeholder='<?php echo _("No of In-House Controls in covid-19"); ?>' title='<?php echo _("Please enter No of In-House Controls in covid-19"); ?>' /></td>
												<td><input type="text" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control" placeholder='<?php echo _("No of Manufacturer Controls in covid-19"); ?>' title='<?php echo _("Please enter No of Manufacturer Controls in covid-19"); ?>' /></td>
												<td><input type="text" name="noCalibrators[]" id="noCalibrators1" class="form-control" placeholder='<?php echo _("No of Calibrators in covid-19"); ?>' title='<?php echo _("Please enter No of Calibrators in covid-19"); ?>' /></td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['hepatitis']) { ?>
											<tr id="hepatitisTable" class="ctlCalibrator">
												<td align="left" style="text-align:center;"><?php echo _("Hepatitis"); ?><input type="hidden" name="testType[]" id="testType1" value="hepatitis" /></td>
												<td><input type="text" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control" placeholder='<?php echo _("No of In-House Controls in Hepatitis"); ?>' title='<?php echo _("Please enter No of In-House Controls in Hepatitis"); ?>' /></td>
												<td><input type="text" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control" placeholder='<?php echo _("No of Manufacturer Controls in Hepatitis"); ?>' title='<?php echo _("Please enter No of Manufacturer Controls in Hepatitis"); ?>' /></td>
												<td><input type="text" name="noCalibrators[]" id="noCalibrators1" class="form-control" placeholder='<?php echo _("No of Calibrators in Hepatitis"); ?>' title='<?php echo _("Please enter No of Calibrators in Hepatitis"); ?>' /></td>
											</tr>
										<?php }
										if (SYSTEM_CONFIG['modules']['tb']) { ?>
											<tr id="tbTable" class="ctlCalibrator">
												<td align="left" style="text-align:center;"><?php echo _("TB"); ?><input type="hidden" name="testType[]" id="testType1" value="tb" /></td>
												<td><input type="text" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control" placeholder='<?php echo _("No of In-House Controls in TB"); ?>' title='<?php echo _("Please enter No of In-House Controls in TB"); ?>' /></td>
												<td><input type="text" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control" placeholder='<?php echo _("No of Manufacturer Controls in TB"); ?>' title='<?php echo _("Please enter No of Manufacturer Controls in TB"); ?>' /></td>
												<td><input type="text" name="noCalibrators[]" id="noCalibrators1" class="form-control" placeholder='<?php echo _("No of Calibrators in TB"); ?>' title='<?php echo _("Please enter No of Calibrators in TB"); ?>' /></td>
											</tr>
										<?php } ?>
									</tbody>
								</table>
							</div>
						<?php } ?>
						<div class="box-header">
							<h3 class="box-title "><?php echo _("Machine Names"); ?></h3>
						</div>
						<div class="box-body">
							<table aria-describedby="table" border="0" class="table table-striped table-bordered table-condensed" aria-hidden="true" style="width:100%;">
								<thead>
									<tr>
										<th style="text-align:center;"><?php echo _("Machine Name"); ?> <span class="mandatory">*</span></th>
										<th style="text-align:center;"><?php echo _("Date Format"); ?> <span class="mandatory">*</span></th>
										<th style="text-align:center;"><?php echo _("Instrument File Name"); ?> <span class="mandatory">*</span></th>
										<th style="text-align:center;"><?php echo _("Is this a POC Device?"); ?> </th>
										<th style="text-align:center;"><?php echo _("Action"); ?></th>
									</tr>
								</thead>
								<tbody id="machineTable">
									<tr>
										<td>
											<input type="text" name="configMachineName[]" id="configMachineName1" class="form-control configMachineName isRequired" placeholder='<?php echo _("Machine Name"); ?>' title='<?php echo _("Please enter machine name"); ?>' onblur="checkDublicateName(this, 'configMachineName');" />
										</td>
										<td>
											<input type="text" name="dateFormat[]" id="dateFormat1" class="form-control" placeholder='<?php echo _("Date Format"); ?>' title='<?php echo _("Please enter date format"); ?>' value='d/m/Y H:i'/>
										</td>
										<td>
											<input type="text" name="fileName[]" id="fileName1" class="form-control" placeholder='<?php echo _("File Name"); ?>' title='<?php echo _("Please enter file name"); ?>'  onblur="checkDublicateName(this, 'fileName');" />
										</td>
										<td>
											<div class="col-md-3">
												<input type="checkbox" id="pocdevice1" name="pocdevice[]" value="" onclick="getLatiLongi(1);">
											</div>
											<div class="latLong1 " style="display:none">
												<div class="col-md-4">
													<input type="text" name="latitude[]" id="latitude1" class="form-control " placeholder='<?php echo _("Latitude"); ?>' data-placement="bottom" title='<?php echo _("Latitude"); ?>' />
												</div>
												<div class="col-md-4">
													<input type="text" name="longitude[]" id="longitude1" class="form-control " placeholder='<?php echo _("Longitude"); ?>' data-placement="bottom" title='<?php echo _("Longitude"); ?>' />
												</div>
											</div>
										</td>
										<td align="center" style="vertical-align:middle;">
											<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;&nbsp;<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
										</td>
									</tr>
								</tbody>
							</table>
						</div>

					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _("Submit"); ?></a>
						<a href="importConfig.php" class="btn btn-default"> <?php echo _("Cancel"); ?></a>
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
	tableRowId = 2;

	$(document).ready(function() {
		$(".select2").select2({
			width: '100%',
			placeholder: '<?php echo _("Select the options"); ?>'
		});

		$("#supportedTests").select2({
			placeholder: '<?php echo _("Select Test Types"); ?>'
		});
		$('input').tooltip();

		$('#supportedTests').on('select2:select', function(e) {
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

	function validateNow() {
		flag = deforayValidator.init({
			formId: 'addImportConfigForm'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('addImportConfigForm').submit();
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
				$("#configurationFile").val(configFileName);
			}
		} else {
			$("#configurationFile").val("");
		}
	}

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

		b.innerHTML = '<input type="text" name="configMachineName[]" id="configMachineName' + tableRowId + '" class="isRequired configMachineName form-control" placeholder="<?php echo _('Machine Name'); ?>" title="<?php echo _('Please enter machine name'); ?>" onblur="checkDublicateName(this, \'"configMachineName"\');"/ >';
		c.innerHTML = '<input type="text" value="d/m/Y H:i" name="dateFormat[]" id="dateFormat' + tableRowId + '" class="form-control" placeholder="<?php echo _("Date Format"); ?>" title="<?php echo _("Please enter date format"); ?>"  onblur="checkDublicateName(this, \'"dateFormat"\');"/>';
		d.innerHTML = '<input type="text" name="fileName[]" id="fileName' + tableRowId + '" class="form-control" placeholder="<?php echo _("File Name"); ?>" title="<?php echo _("Please enter file name"); ?>"/>';
		e.innerHTML = '<div class="col-md-3" >\
						<input type="checkbox" id="pocdevice' + tableRowId + '" name="pocdevice[]" value="" onclick="getLatiLongi(' + tableRowId + ');">\
						</div>\
						<div class="latLong' + tableRowId + ' " style="display:none">\
							<div class="col-md-4">\
								<input type="text" name="latitude[]" id="latitude' + tableRowId + '" class="form-control " placeholder="<?php echo _('Latitude'); ?>" data-placement="bottom" title="<?php echo _('Latitude'); ?>"/> \
							</div>\
							<div class="col-md-4">\
								<input type="text" name="longitude[]" id="longitude' + tableRowId + '" class="form-control " placeholder="<?php echo _('Longitude'); ?>" data-placement="bottom" title="<?php echo _('Longitude'); ?>"/>\
							</div>\
						</div>';
		f.innerHTML = '<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;&nbsp;<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>';
		$(a).fadeIn(800);
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

	function checkDublicateName(obj, name) {
		dublicateObj = document.getElementsByName(name+"[]");
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
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
