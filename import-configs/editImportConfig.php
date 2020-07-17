<?php
ob_start();
require_once('../startup.php');
include_once(APPLICATION_PATH . '/header.php');
//include_once('startup.php');  include_once(APPLICATION_PATH.'/includes/MysqliDb.php');
$id = base64_decode($_GET['id']);
$sQuery = "SELECT * from import_config where config_id=$id";
$sInfo = $db->query($sQuery);
$configMachineQuery = "SELECT * from import_config_machines where config_id=$id";
$configMachineInfo = $db->query($configMachineQuery);
$configControlQuery = "SELECT * from import_config_controls where config_id=$id";
$configControlInfo = $db->query($configControlQuery);
$configControl = array();
foreach($configControlInfo as $info){
	$configControl[$info['test_type']]['noHouseCtrl'] = $info['number_of_in_house_controls'];
	$configControl[$info['test_type']]['noManufacturerCtrl'] = $info['number_of_manufacturer_controls'];
	$configControl[$info['test_type']]['noCalibrators'] = $info['number_of_calibrators'];
}
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1 class="fa fa-gears"> Edit Import Configuration</h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
			<li class="active">Edit Import Config</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<!-- SELECT2 EXAMPLE -->
		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<!-- <pre><?php print_r($configControl); ?></pre> -->
				<form class="form-horizontal" method='post' name='editImportConfigForm' id='editImportConfigForm' autocomplete="off" action="editImportConfigHelper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="configurationName" class="col-lg-4 control-label">Configuration Name<span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="configurationName" name="configurationName" placeholder="eg. Roche or Abbott" title="Please enter configuration name" value="<?php echo $sInfo[0]['machine_name']; ?>" onblur="checkNameValidation('import_config','machine_name',this,'<?php echo "config_id##" . $sInfo[0]['config_id']; ?>','This configuration name already exists.Try another name',null);" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="configurationName" class="col-lg-4 control-label">Configuration File Name<span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="configurationFile" name="configurationFile" placeholder="eg. roche.php or abbott.php" title="Please enter file name" value="<?php echo $sInfo[0]['import_machine_file_name']; ?>" onblur="checkNameValidation('import_config','import_machine_file_name',this,'<?php echo "config_id##" . $sInfo[0]['config_id']; ?>','This file name already exists.Try another name',null)" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="configurationFileName" class="col-lg-4 control-label">Lower Limit</label>
									<div class="col-lg-7">
										<input type="text" class="form-control checkNum" id="lowerLimit" name="lowerLimit" placeholder="eg. 20" title="Please enter lower limit" value="<?php echo $sInfo[0]['lower_limit']; ?>" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="configurationFileName" class="col-lg-4 control-label">Higher Limit</label>
									<div class="col-lg-7">
										<input type="text" class="form-control checkNum" id="higherLimit" name="higherLimit" placeholder="eg. 10000000" title="Please enter lower limit" value="<?php echo $sInfo[0]['higher_limit']; ?>" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="maxNOfSamplesInBatch" class="col-lg-4 control-label">Maximum No. of Samples In a Batch <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control checkNum isRequired" id="maxNOfSamplesInBatch" name="maxNOfSamplesInBatch" placeholder="Max. no of samples" title="Please enter max no of samples in a row" value="<?php echo $sInfo[0]['max_no_of_samples_in_a_batch']; ?>" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-12">
								<div class="form-group">
									<label for="lowVlResultText" class="col-lg-2 control-label">Low VL Result Text </label>
									<div class="col-lg-7">
										<textarea class="form-control" id="lowVlResultText" name="lowVlResultText" placeholder="Comma separated Low Viral Load Result Text for eg. Target Not Detected, TND, < 20, < 40" title="Low Viral Load Result Text for eg. Target Not Detected, TND, < 20, < 40"><?php echo $sInfo[0]['low_vl_result_text']; ?></textarea>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6" style="padding-top:20px;">
								<div class="form-group">
									<label for="status" class="col-lg-4 control-label">Status</label>
									<div class="col-lg-7">
										<select class="form-control" id="status" name="status" title="Please select import config status">
											<option value="active" <?php echo ($sInfo[0]['status'] == 'active') ? 'selected="selected"' : ''; ?>>Active</option>
											<option value="inactive" <?php echo ($sInfo[0]['status'] == 'inactive') ? 'selected="selected"' : ''; ?>>Inactive</option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<?php if($systemConfig['modules']['vl'] || $systemConfig['modules']['eid'] || $systemConfig['modules']['covid19']){ ?>
						<div class="box-body">
							<table cellpadding="0" cellspacing="0" border="0" class="table table-striped table-bordered table-condensed" style="width:100%;">
								<thead>
									<tr>
										<th style="text-align:center;">Test Type</th>
										<th style="text-align:center;">Number of In-House Controls</th>
										<th style="text-align:center;">Number of Manufacturer Controls</th>
										<th style="text-align:center;">No. Of Calibrators</th>
									</tr>
								</thead>
								<tbody id="testTypesTable">
									<?php if(isset($systemConfig['modules']['vl'])){?>
									<tr>
										<td align="left">VL<input type="hidden" name="testType[]" id="testType1" value="vl"/></td>
										<td><input type="text" value="<?php echo $configControl['vl']['noHouseCtrl'];?>" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control" placeholder="No of In-House Controls in vl" title="Please enter No of In-House Controls in vl" /></td>
										<td><input type="text" value="<?php echo $configControl['vl']['noManufacturerCtrl'];?>" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control" placeholder="No of Manufacturer Controls in vl" title="Please enter No of Manufacturer Controls in vl" /></td>
										<td><input type="text" value="<?php echo $configControl['vl']['noCalibrators'];?>" name="noCalibrators[]" id="noCalibrators1" class="form-control" placeholder="No of Calibrators in vl" title="Please enter No of Calibrators in vl" /></td>
									</tr>
									<?php } if(isset($systemConfig['modules']['eid'])){?>
									<tr>
										<td align="left">EID<input type="hidden" name="testType[]" id="testType1" value="eid"/></td>
										<td><input type="text" value="<?php echo $configControl['eid']['noHouseCtrl'];?>" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control" placeholder="No of In-House Controls in eid" title="Please enter No of In-House Controls in eid" /></td>
										<td><input type="text" value="<?php echo $configControl['eid']['noManufacturerCtrl'];?>" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control" placeholder="No of Manufacturer Controls in eid" in eid title="Please enter No of Manufacturer Controls in eid" /></td>
										<td><input type="text" value="<?php echo $configControl['eid']['noCalibrators'];?>" name="noCalibrators[]" id="noCalibrators1" class="form-control" placeholder="No of Calibrators in eid" title="Please enter No of Calibrators in eid" /></td>
									</tr>
									<?php } if(isset($systemConfig['modules']['covid19'])){?>
									<tr>
										<td align="left">Covid-19<input type="hidden" name="testType[]" id="testType1" value="covid-19"/></td>
										<td><input type="text" value="<?php echo $configControl['covid-19']['noHouseCtrl'];?>" name="noHouseCtrl[]" id="noHouseCtrl1" class="form-control" placeholder="No of In-House Controls in covid-19" title="Please enter No of In-House Controls in covid-19" /></td>
										<td><input type="text" value="<?php echo $configControl['covid-19']['noManufacturerCtrl'];?>" name="noManufacturerCtrl[]" id="noManufacturerCtrl1" class="form-control" placeholder="No of Manufacturer Controls in covid-19" title="Please enter No of Manufacturer Controls in covid-19" /></td>
										<td><input type="text" value="<?php echo $configControl['covid-19']['noCalibrators'];?>" name="noCalibrators[]" id="noCalibrators1" class="form-control" placeholder="No of Calibrators in covid-19" title="Please enter No of Calibrators in covid-19" /></td>
									</tr>
									<?php } ?>
								</tbody>
							</table>
						</div>
						<?php } ?>
						<div class="box-header">
							<h3 class="box-title ">Machine Names</h3>
						</div>
						<div class="box-body">
							<table cellpadding="0" cellspacing="0" border="0" class="table table-striped table-bordered table-condensed" style="width:60%;">
								<thead>
									<tr>
										<th style="text-align:center;">Machine Name <span class="mandatory">*</span></th>
										<th style="text-align:center;">Action</th>
									</tr>
								</thead>
								<tbody id="machineTable">
									<?php
									$i = 1;
									if (count($configMachineInfo) > 0) {
										foreach ($configMachineInfo as $machine) {
									?>
											<tr>
												<td>
													<input type="hidden" name="configMachineId[]" value="<?php echo $machine['config_machine_id']; ?>" />
													<input type="text" name="configMachineName[]" id="configMachineName<?php echo $i; ?>" class="form-control configMachineName isRequired" placeholder="Machine Name" title="Please enter machine name" value="<?php echo $machine['config_machine_name']; ?>" onblur="checkMachineName(this);" ; />
												</td>
												<td align="center" style="vertical-align:middle;">
													<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><i class="fa fa-plus"></i></a>
												</td>
											</tr>
										<?php
											$i++;
										}
									} else {
										?>
										<tr>
											<td>
												<input type="text" name="configMachineName[]" id="configMachineName<?php echo $i; ?>" class="form-control configMachineName isRequired" placeholder="Machine Name" title="Please enter machine name" onblur="checkMachineName(this);" />
											</td>
											<td align="center" style="vertical-align:middle;">
												<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><i class="fa fa-plus"></i></a>&nbsp;&nbsp;<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><i class="fa fa-minus"></i></a>
											</td>
										</tr>
									<?php } ?>
								</tbody>
							</table>
						</div>
					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<input type="hidden" id="configId" name="configId" value="<?php echo base64_encode($sInfo[0]['config_id']); ?>" />
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
						<a href="importConfig.php" class="btn btn-default"> Cancel</a>
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

	function insRow() {
		rl = document.getElementById("machineTable").rows.length;
		var a = document.getElementById("machineTable").insertRow(rl);
		a.setAttribute("style", "display:none");
		var b = a.insertCell(0);
		var c = a.insertCell(1);
		c.setAttribute("align", "center");
		c.setAttribute("style", "vertical-align:middle");

		b.innerHTML = '<input type="text" name="configMachineName[]" id="configMachineName' + tableRowId + '"class="isRequired configMachineName form-control" placeholder="Machine Name" title="Please enter machine name" onblur="checkMachineName(this);"/ >';
		c.innerHTML = '<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><i class="fa fa-plus"></i></a>&nbsp;&nbsp;<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><i class="fa fa-minus"></i></a>';
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

	function checkMachineName(obj) {
		machineObj = document.getElementsByName("configMachineName[]");
		for (m = 0; m < machineObj.length; m++) {
			if (obj.value != '' && obj.id != machineObj[m].id && obj.value == machineObj[m].value) {
				alert('Duplicate value not allowed');
				$('#' + obj.id).val('');
			}
		}
	}
</script>

<?php
include(APPLICATION_PATH . '/footer.php');
?>