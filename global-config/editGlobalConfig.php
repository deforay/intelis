<?php
ob_start();
$title = "Edit Global Configuration";
#require_once('../startup.php');
include_once(APPLICATION_PATH . '/header.php');


$instanceQuery = "SELECT * from s_vlsm_instance where vlsm_instance_id='" . $_SESSION['instanceId'] . "'";
$instanceResult = $db->query($instanceQuery);
$fType = "SELECT * FROM facility_type";
$fTypeResult = $db->rawQuery($fType);

$formQuery = "SELECT * from form_details";
$formResult = $db->query($formQuery);
$globalConfigQuery = "SELECT * from global_config";
$configResult = $db->query($globalConfigQuery);
$arr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($configResult); $i++) {
	$arr[$configResult[$i]['name']] = $configResult[$i]['value'];
}
$mFieldArray = array();
if (isset($arr['r_mandatory_fields']) && trim($arr['r_mandatory_fields']) != '') {
	$mFieldArray = explode(',', $arr['r_mandatory_fields']);
}
?>
<link href="/assets/css/jasny-bootstrap.min.css" rel="stylesheet" />
<link href="/assets/css/multi-select.css" rel="stylesheet" />
<style>
	.select2-selection__choice {
		color: #000000 !important;
	}

	.boxWidth,
	.eid_boxWidth {
		width: 10%;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1 class="fa fa-gears"> Edit General Configuration</h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
			<li class="active">Manage General Config</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<!-- SELECT2 EXAMPLE -->
		<div class="box box-default">
			<!--<div class="box-header with-border">
          <div class="pull-right" style="font-size:15px;"> </div>
        </div>-->
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='editGlobalConfigForm' id='editGlobalConfigForm' enctype="multipart/form-data" autocomplete="off" action="globalConfigHelper.php">
					<div class="box-body">
						<div class="panel panel-default">
							<div class="panel-heading">
								<h3 class="panel-title">Instance Settings</h3>
							</div>
							<div class="panel-body">
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="fName" class="col-lg-4 control-label">Instance/Facility Name <span class="mandatory">*</span></label>
											<div class="col-lg-8">
												<input type="text" class="form-control isRequired" name="fName" id="fName" title="Please enter instance name" placeholder="Facility/Instance Name" value="<?php echo $instanceResult[0]['instance_facility_name']; ?>" />
											</div>
										</div>
									</div>
									<div class="col-md-7">
										<div class="form-group">
											<label for="fCode" class="col-lg-4 control-label">Instance/Facility Code </label>
											<div class="col-lg-8">
												<input type="text" class="form-control " id="fCode" name="fCode" placeholder="Facility Code" title="Please enter instance/facility code" value="<?php echo $instanceResult[0]['instance_facility_code']; ?>" />
											</div>
										</div>
									</div>
									<div class="col-md-7">
										<div class="form-group">
											<label for="instance_type" class="col-lg-4 control-label">Instance/Facility Type <span class="mandatory">*</span></label>
											<div class="col-lg-8">
												<select class="form-control isRequired" name="instance_type" id="instance_type" title="Please select the instance type">
													<option value="Viral Load Lab" <?php echo ('Viral Load Lab' == $arr['instance_type']) ? "selected='selected'" : "" ?>>Viral Load Lab</option>
													<option value="Clinic/Lab" <?php echo ('Clinic/Lab' == $arr['instance_type']) ? "selected='selected'" : "" ?>>Clinic/Lab</option>
													<option value="Both" <?php echo ('Both' == $arr['instance_type']) ? "selected='selected'" : "" ?>>Both</option>
												</select>
											</div>
										</div>
									</div>
									<div class="row" style="display:none;">
										<div class="col-md-7">
											<div class="form-group">
												<label for="" class="col-lg-4 control-label">Logo Image </label>
												<div class="col-lg-8">
													<div class="fileinput fileinput-new instanceLogo" data-provides="fileinput">
														<div class="fileinput-preview thumbnail" data-trigger="fileinput" style="width:200px; height:150px;">
															<?php
															if (isset($instanceResult[0]['instance_facility_logo']) && trim($instanceResult[0]['instance_facility_logo']) != '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "instance-logo" . DIRECTORY_SEPARATOR . $instanceResult[0]['instance_facility_logo'])) {
															?>
																<img src=".././uploads/instance-logo/<?php echo $instanceResult[0]['instance_facility_logo']; ?>" alt="Logo image">
															<?php } else { ?>
																<img src="http://www.placehold.it/200x150/EFEFEF/AAAAAA&text=No image">
															<?php } ?>
														</div>
														<div>
															<span class="btn btn-default btn-file"><span class="fileinput-new">Select image</span><span class="fileinput-exists">Change</span>
																<input type="file" id="instanceLogo" name="instanceLogo" title="Please select logo image" onchange="getNewInstanceImage('<?php echo $instanceResult[0]['instance_facility_logo']; ?>');">
															</span>
															<?php
															if (isset($instanceResult[0]['instance_facility_logo']) && trim($instanceResult[0]['instance_facility_logo']) != '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "instance-logo" . DIRECTORY_SEPARATOR . $instanceResult[0]['instance_facility_logo'])) {
															?>
																<a id="clearInstanceImage" href="javascript:void(0);" class="btn btn-default" data-dismiss="fileupload" onclick="clearInstanceImage('<?php echo $instanceResult[0]['instance_facility_logo']; ?>')">Clear</a>
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
									</div>
								</div>
							</div>
						</div>
						<div class="panel panel-default">
							<div class="panel-heading">
								<h3 class="panel-title">Global Settings</h3>
							</div>
							<div class="panel-body">
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="vl_form" class="col-lg-4 control-label">Form <span class="mandatory">*</span> </label>
											<div class="col-lg-8">
												<select class="form-control isRequired" name="vl_form" id="vl_form" title="Please select the viral load form">
													<?php
													foreach ($formResult as $val) {
													?>
														<option value="<?php echo $val['vlsm_country_id']; ?>" <?php echo ($val['vlsm_country_id'] == $arr['vl_form']) ? "selected='selected'" : "" ?>><?php echo $val['form_name']; ?></option>
													<?php
													}
													?>
												</select>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="default_time_zone" class="col-lg-4 control-label">Default Time Zone </label>
											<div class="col-lg-8">
												<input type="text" class="form-control" id="default_time_zone" name="default_time_zone" placeholder="eg: Africa/Harare" title="Please enter default time zone" value="<?php echo $arr['default_time_zone']; ?>" />
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="header" class="col-lg-4 control-label">Header </label>
											<div class="col-lg-8">
												<textarea class="form-control" id="header" name="header" placeholder="Header" title="Please enter header" style="width:100%;min-height:80px;max-height:100px;"><?php echo $arr['header']; ?></textarea>
											</div>
										</div>
									</div>
								</div>

								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="" class="col-lg-4 control-label">Logo Image </label>
											<div class="col-lg-8">
												<div class="fileinput fileinput-new logo" data-provides="fileinput">
													<div class="fileinput-preview thumbnail" data-trigger="fileinput" style="width:200px; height:150px;">
														<?php
														if (isset($arr['logo']) && trim($arr['logo']) != '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $arr['logo'])) {
														?>
															<img src=".././uploads/logo/<?php echo $arr['logo']; ?>" alt="Logo image">
														<?php } else { ?>
															<img src="http://www.placehold.it/200x150/EFEFEF/AAAAAA&text=No image">
														<?php } ?>
													</div>
													<div>
														<span class="btn btn-default btn-file"><span class="fileinput-new">Select image</span><span class="fileinput-exists">Change</span>
															<input type="file" id="logo" name="logo" title="Please select logo image" onchange="getNewImage('<?php echo $arr['logo']; ?>');">
														</span>
														<?php
														if (isset($arr['logo']) && trim($arr['logo']) != '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $arr['logo'])) {
														?>
															<a id="clearImage" href="javascript:void(0);" class="btn btn-default" data-dismiss="fileupload" onclick="clearImage('<?php echo $arr['logo']; ?>')">Clear</a>
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
								</div>


								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="edit_profile" class="col-lg-4 control-label">Allow users to Edit Profile </label>
											<div class="col-lg-8">
												<input type="radio" class="" id="edit_profile_yes" name="edit_profile" value="yes" <?php echo ($arr['edit_profile'] == 'yes') ? 'checked' : ''; ?>>&nbsp;&nbsp;Yes&nbsp;&nbsp;
												<input type="radio" class="" id="edit_profile_no" name="edit_profile" value="no" <?php echo ($arr['edit_profile'] == 'no') ? 'checked' : ''; ?>>&nbsp;&nbsp;No
											</div>
										</div>
									</div>
								</div>



								<div class="row">
									<div class="col-md-7" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="barcode_format" class="col-lg-4 control-label">Barcode Format</label>
											<div class="col-lg-8">
												<select class="form-control isRequired" name="barcode_format" id="barcode_format" title="Please select the Barcode type">
													<option value="C39" <?php echo ('C39' == $arr['barcode_format']) ? "selected='selected'" : "" ?>>C39</option>
													<option value="C39+" <?php echo ('C39+' == $arr['barcode_format']) ? "selected='selected'" : "" ?>>C39+</option>
													<option value="C128" <?php echo ('C128' == $arr['barcode_format']) ? "selected='selected'" : "" ?>>C128</option>
												</select>
											</div>
										</div>
									</div>
								</div>




								<div class="row" style="margin-top:10px;">
									<div class="col-md-7">
										<div class="form-group">
											<label for="auto_approval" class="col-lg-4 control-label">Same user can Review and Approve </label>
											<div class="col-lg-8">
												<br>
												<input type="radio" class="" id="user_review_yes" name="user_review_approve" value="yes" <?php echo ($arr['user_review_approve'] == 'yes') ? 'checked' : ''; ?>>&nbsp;&nbsp;Yes&nbsp;&nbsp;
												<input type="radio" class="" id="user_review_no" name="user_review_approve" value="no" <?php echo ($arr['user_review_approve'] == 'no') ? 'checked' : ''; ?>>&nbsp;&nbsp;No
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="manager_email" class="col-lg-4 control-label">Manager Email</label>
											<div class="col-lg-8">
												<input type="text" class="form-control" id="manager_email" name="manager_email" placeholder="eg. manager1@example.com, manager2@example.com" title="Please enter manager email" value="<?php echo $arr['manager_email']; ?>" />
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7" style="text-align:center;">
										<code>You can enter multiple emails by separating them with commas</code>
									</div>
								</div><br />
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="instance_type" class="col-lg-4 control-label">Sample ID Barcode Label Printing <span class="mandatory">*</span> </label>
											<div class="col-lg-8">
												<select class="form-control isRequired" name="bar_code_printing" id="bar_code_printing" title="Please select the barcode printing">
													<option value="off" <?php echo ('off' == $arr['bar_code_printing']) ? "selected='selected'" : "" ?>>Off</option>
													<option value="zebra-printer" <?php echo ('zebra-printer' == $arr['bar_code_printing']) ? "selected='selected'" : "" ?>>Zebra Printer</option>
													<option value="dymo-labelwriter-450" <?php echo ('dymo-labelwriter-450' == $arr['bar_code_printing']) ? "selected='selected'" : "" ?>>Dymo LabelWriter 450</option>
												</select>
											</div>
										</div>
									</div>
								</div>
								<div class="row" style="margin-top:10px;">
									<div class="col-md-7">
										<div class="form-group">
											<label for="import_non_matching_sample" class="col-lg-4 control-label">Allow Samples not matching the VLSM Sample IDs while importing results manually</label>
											<div class="col-lg-8">
												<br>
												<br>
												<input type="radio" id="import_non_matching_sample_yes" name="import_non_matching_sample" value="yes" <?php echo ($arr['import_non_matching_sample'] == 'yes') ? 'checked' : ''; ?>>&nbsp;&nbsp;Yes&nbsp;&nbsp;
												<input type="radio" id="import_non_matching_sample_no" name="import_non_matching_sample" value="no" <?php echo ($arr['import_non_matching_sample'] == 'no') ? 'checked' : ''; ?>>&nbsp;&nbsp;No
												<br><br> <code>While importing results from CSV/Excel file, should we import results of Sample IDs that do not match the Sample IDs present in VLSM database</code>
											</div>
										</div>
									</div>
								</div>

							</div>
						</div>
						<?php if($systemConfig['modules']['vl']){ ?>
							<div class="panel panel-default">
								<div class="panel-heading">
									<h3 class="panel-title">Viral Load Settings</h3>
								</div>
								<div class="panel-body">

									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="show_date" class="col-lg-2 control-label">Date For Patient ART NO. </label>
												<div class="col-lg-10">
													<br>
													<input type="radio" class="" id="show_full_date_yes" name="show_date" value="yes" <?php echo ($arr['show_date'] == 'yes') ? 'checked' : ''; ?>>&nbsp;&nbsp;Full Date&nbsp;&nbsp;
													<input type="radio" class="" id="show_full_date_no" name="show_date" value="no" <?php echo ($arr['show_date'] == 'no' || $arr['show_date'] == '') ? 'checked' : ''; ?>>&nbsp;&nbsp;Month and Year
												</div>
											</div>
										</div>
									</div>
									<!--<div class="row">
					<div class="col-md-7">
						<div class="form-group">
						<label for="auto_approval" class="col-lg-4 control-label">Auto Approval </label>
						<div class="col-lg-8">
							<input type="radio" class="" id="auto_approval_yes" name="auto_approval" value="yes" < ?php echo($arr['auto_approval'] == 'yes')?'checked':''; ?>>&nbsp;&nbsp;Yes&nbsp;&nbsp;
							<input type="radio" class="" id="auto_approval_no" name="auto_approval" value="no" < ?php echo($arr['auto_approval'] == 'no' || $arr['auto_approval'] == '')?'checked':''; ?>>&nbsp;&nbsp;No
						</div>
						</div>
					</div>
					</div>-->
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="viral_load_threshold_limit" class="col-lg-2 control-label">Viral Load Threshold Limit<span class="mandatory">*</span></label>
												<div class="col-lg-10">
													<div class="input-group" style="max-width:200px;">
														<input type="text" class="form-control checkNum isNumeric isRequired" id="viral_load_threshold_limit" name="viral_load_threshold_limit" placeholder="Viral Load Threshold Limit" title="Please enter VL threshold limit" value="<?php echo $arr['viral_load_threshold_limit']; ?>" />
														<span class="input-group-addon">cp/ml</span>
													</div>

												</div>
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-md-12" style="">
											<div class="form-group">
												<label for="auto_approval" class="col-lg-2 control-label">Sample Code<br>Format <span class="mandatory">*</span> </label>
												<div class="col-lg-10">
													<?php
													$sPrefixMMYY = '';
													$sPrefixYY = '';
													$sPrefixMMYYDisplay = 'disabled="disabled"';
													$sPrefixYYDisplay = 'disabled="disabled"';
													if ($arr['sample_code'] == 'MMYY') {
														$sPrefixMMYY = $arr['sample_code_prefix'];
														$sPrefixMMYYDisplay = '';
													} else if ($arr['sample_code'] == 'YY') {
														$sPrefixYY = $arr['sample_code_prefix'];
														$sPrefixYYDisplay = '';
													}
													?>
													<input type="radio" title="Please select the Viral Load Sample Code Format" class="isRequired" id="auto_generate_yy" name="sample_code" value="YY" <?php echo ($arr['sample_code'] == 'YY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixMMYY','prefixYY')">&nbsp;<input <?php echo $sPrefixYYDisplay; ?> type="text" class="boxWidth prefixYY" id="prefixYY" name="sample_code_prefix" title="Enter Prefix" value="<?php echo $sPrefixYY; ?>" /> YY&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" title="Please select the Viral Load Sample Code Format" class="isRequired" id="auto_generate_mmyy" name="sample_code" value="MMYY" <?php echo ($arr['sample_code'] == 'MMYY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixYY','prefixMMYY')">&nbsp;<input <?php echo $sPrefixMMYYDisplay; ?> type="text" class="boxWidth prefixMMYY" id="prefixMMYY" name="sample_code_prefix" title="Enter Prefix" value="<?php echo $sPrefixMMYY; ?>" /> MMYY&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" title="Please select the Viral Load Sample Code Format" class="isRequired" id="auto_generate" name="sample_code" value="auto" <?php echo ($arr['sample_code'] == 'auto') ? 'checked' : ''; ?>><span id="auto1"><?php echo ($arr['vl_form'] == 5) ? ' Auto 1' : ' Auto'; ?> </span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" title="Please select the Viral Load Sample Code Format" class="isRequired" id="auto_generate2" name="sample_code" value="auto2" <?php echo ($arr['sample_code'] == 'auto2') ? 'checked' : ''; ?> style="display:<?php echo ($arr['vl_form'] == 5) ? '' : 'none'; ?>"><span id="auto2" style="display:<?php echo ($arr['vl_form'] == 5) ? '' : 'none'; ?>"> Auto 2 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
													<input type="radio" title="Please select the Viral Load Sample Code Format" class="isRequired" id="numeric" name="sample_code" value="numeric" <?php echo ($arr['sample_code'] == 'numeric') ? 'checked' : ''; ?>> Numeric&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" title="Please select the Viral Load Sample Code Format" class="isRequired" id="alpha_numeric" name="sample_code" value="alphanumeric" <?php echo ($arr['sample_code'] == 'alphanumeric') ? 'checked' : ''; ?>> Alpha Numeric
												</div>
											</div>
										</div>
									</div>

									<div id="auto-sample-eg" class="row" style="display:<?php echo ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'auto2' || 'MMYY' || 'YY') ? 'block' : 'none'; ?>;">
										<div class="col-md-12" style="text-align:center;">
											<code id="auto-sample-code" class="autoSample" style="display:<?php echo ($arr['sample_code'] == 'auto') ? 'block' : 'none'; ?>;">
												eg. Province Code+Year+Month+Date+Increment Counter
											</code>
											<code id="auto-sample-code2" class="autoSample" style="display:<?php echo ($arr['sample_code'] == 'auto2') ? 'block' : 'none'; ?>;">
												eg. R+Year+Province Code+VL+Increment Counter (R18NCDVL0001)
											</code>
											<code id="auto-sample-code-MMYY" class="autoSample" style="display:<?php echo ($arr['sample_code'] == 'MMYY') ? 'block' : 'none'; ?>;">
												eg. Prefix+Month+Year+Increment Counter (VL0517999)
											</code>
											<code id="auto-sample-code-YY" class="autoSample" style="display:<?php echo ($arr['sample_code'] == 'YY') ? 'block' : 'none'; ?>;">
												eg. Prefix+Year+Increment Counter (VL17999)
											</code>
										</div>
									</div><br />
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="min_length" class="col-lg-2 control-label">Minimum Sample Code Length <span class="mandatory " style="display:<?php echo ($arr['sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-10">
													<input type="text" class="form-control checkNum isNumeric <?php echo ($arr['sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="min_length" name="min_length" <?php echo ($arr['sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="Min" title="Please enter sample code min length" value="<?php echo ($arr['sample_code'] == 'auto') ? '' : $arr['min_length']; ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="min_length" class="col-lg-2 control-label">Maximum Sample Code Length <span class="mandatory " style="display:<?php echo ($arr['sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-10">
													<input type="text" class="form-control checkNum isNumeric <?php echo ($arr['sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="max_length" name="max_length" <?php echo ($arr['sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="Max" title="Please enter sample code max length" value="<?php echo ($arr['sample_code'] == 'auto') ? '' : $arr['max_length']; ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						<?php } 
						if($systemConfig['modules']['eid']){ ?>
							<div class="panel panel-default">
								<div class="panel-heading">
									<h3 class="panel-title">EID Settings</h3>
								</div>
								<div class="panel-body">

									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="eid_positive" class="col-lg-2 control-label">EID Positive <span class="mandatory">*</span></label>
												<div class="col-lg-10">
													<input type="text" class="form-control" id="eid_positive" name="eid_positive" placeholder="EID Positive" title="Please enter EID Positive" value="<?php echo (isset($arr['eid_positive']) && !empty($arr['eid_positive']) ? $arr['eid_positive'] : 'Positive'); ?>" style="max-width:200px;" />
												</div>
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="eid_negative" class="col-lg-2 control-label">EID Negative <span class="mandatory">*</span></label>
												<div class="col-lg-10">
													<input type="text" class="form-control" id="eid_negative" name="eid_negative" placeholder="EID Negative" title="Please enter EID Negative" value="<?php echo (isset($arr['eid_negative']) && !empty($arr['eid_negative']) ? $arr['eid_negative'] : 'Negative'); ?>" style="max-width:200px;" />
												</div>
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="eid_indeterminate" class="col-lg-2 control-label">EID Indeterminate <span class="mandatory">*</span></label>
												<div class="col-lg-10">
													<input type="text" class="form-control" id="eid_indeterminate" name="eid_indeterminate" placeholder="EID Indeterminate" title="Please enter EID Indeterminate" value="<?php echo (isset($arr['eid_indeterminate']) && !empty($arr['eid_indeterminate']) ? $arr['eid_indeterminate'] : 'Indeterminate'); ?>" style="max-width:200px;" />
												</div>
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-md-12" style="">
											<div class="form-group">
												<label for="eid_sample_code" class="col-lg-2 control-label">Sample Code<br>Format <span class="mandatory">*</span> </label>
												<div class="col-lg-10">
													<?php
													$sPrefixMMYY = 'EID';
													$sPrefixYY = '';
													$sPrefixMMYYDisplay = 'disabled="disabled"';
													$sPrefixYYDisplay = 'disabled="disabled"';
													if ($arr['eid_sample_code'] == 'MMYY') {
														$sPrefixMMYY = $arr['eid_sample_code_prefix'];
														$sPrefixMMYYDisplay = '';
													} else if ($arr['eid_sample_code'] == 'YY') {
														$sPrefixYY = $arr['eid_sample_code_prefix'];
														$sPrefixYYDisplay = '';
													}
													?>
													<input type="radio" class="isRequired" title="Please select the EID Sample Code Format" id="eid_auto_generate_yy" name="eid_sample_code" value="YY" <?php echo ($arr['eid_sample_code'] == 'YY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixMMYY','prefixYY')">&nbsp;<input <?php echo $sPrefixYYDisplay; ?> type="text" class="eid_boxWidth eid_prefixYY" id="eid_prefixYY" name="eid_sample_code_prefix" title="Enter Prefix" value="<?php echo $sPrefixYY; ?>" /> YY&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired" title="Please select the EID Sample Code Format" id="eid_auto_generate_mmyy" name="eid_sample_code" value="MMYY" <?php echo ($arr['eid_sample_code'] == 'MMYY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixYY','prefixMMYY')">&nbsp;<input <?php echo $sPrefixMMYYDisplay; ?> type="text" class="eid_boxWidth eid_prefixMMYY" id="eid_prefixMMYY" name="eid_sample_code_prefix" title="Enter Prefix" value="<?php echo $sPrefixMMYY; ?>" /> MMYY&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired" title="Please select the EID Sample Code Format" id="eid_auto_generate" name="eid_sample_code" value="auto" <?php echo ($arr['eid_sample_code'] == 'auto') ? 'checked' : ''; ?>><span id="eid_auto1"><?php echo ($arr['vl_form'] == 5) ? ' Auto 1' : ' Auto'; ?> </span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired" title="Please select the EID Sample Code Format" id="eid_auto_generate2" name="eid_sample_code" value="auto2" <?php echo ($arr['eid_sample_code'] == 'auto2') ? 'checked' : ''; ?> style="display:<?php echo ($arr['vl_form'] == 5) ? '' : 'none'; ?>"><span id="eid_auto2" style="display:<?php echo ($arr['vl_form'] == 5) ? '' : 'none'; ?>"> Auto 2 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
													<input type="radio" class="isRequired" title="Please select the EID Sample Code Format" id="eid_numeric" name="eid_sample_code" value="numeric" <?php echo ($arr['eid_sample_code'] == 'numeric') ? 'checked' : ''; ?>> Numeric&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired" title="Please select the EID Sample Code Format" id="eid_alpha_numeric" name="eid_sample_code" value="alphanumeric" <?php echo ($arr['eid_sample_code'] == 'alphanumeric') ? 'checked' : ''; ?>> Alpha Numeric
												</div>
											</div>
										</div>
									</div>

									<div id="eid_auto-sample-eg" class="row" style="display:<?php echo ($arr['eid_sample_code'] == 'auto' || $arr['eid_sample_code'] == 'auto2' || 'MMYY' || 'YY') ? 'block' : 'none'; ?>;">
										<div class="col-md-12" style="text-align:center;">
											<code id="eid_auto-sample-code" class="eid_autoSample" style="display:<?php echo ($arr['eid_sample_code'] == 'auto') ? 'block' : 'none'; ?>;">
												eg. Province Code+Year+Month+Date+Increment Counter
											</code>
											<code id="eid_auto-sample-code2" class="eid_autoSample" style="display:<?php echo ($arr['eid_sample_code'] == 'auto2') ? 'block' : 'none'; ?>;">
												eg. R+Year+Province Code+EID+Increment Counter (R18NCDEID0001)
											</code>
											<code id="eid_auto-sample-code-MMYY" class="eid_autoSample" style="display:<?php echo ($arr['eid_sample_code'] == 'MMYY') ? 'block' : 'none'; ?>;">
												eg. Prefix+Month+Year+Increment Counter (EID0517999)
											</code>
											<code id="eid_auto-sample-code-YY" class="eid_autoSample" style="display:<?php echo ($arr['eid_sample_code'] == 'YY') ? 'block' : 'none'; ?>;">
												eg. Prefix+Year+Increment Counter (EID17999)
											</code>
										</div>
									</div><br />
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="eid_min_length" class="col-lg-2 control-label">Minimum Sample Code Length <span class="mandatory " style="display:<?php echo ($arr['eid_sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-10">
													<input type="text" class="form-control checkNum isNumeric <?php echo ($arr['eid_sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="eid_min_length" name="eid_min_length" <?php echo ($arr['eid_sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="Min" title="Please enter sample code min length" value="<?php echo ($arr['eid_sample_code'] == 'auto') ? '' : $arr['min_length']; ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="eid_max_length" class="col-lg-2 control-label">Maximum Sample Code Length <span class="mandatory " style="display:<?php echo ($arr['eid_sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-10">
													<input type="text" class="form-control checkNum isNumeric <?php echo ($arr['sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="eid_max_length" name="eid_max_length" <?php echo ($arr['eid_sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="Max" title="Please enter sample code max length" value="<?php echo ($arr['eid_sample_code'] == 'auto') ? '' : $arr['max_length']; ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						<?php }
						if ($systemConfig['modules']['covid19']) { ?>
							<div class="panel panel-default">
								<div class="panel-heading">
									<h3 class="panel-title">Covid-19 Settings</h3>
								</div>
								<div class="panel-body">
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<?php if (isset($arr['covid19_report_type']) && $arr['covid19_report_type'] != '') { ?>
													<label for="covid19ReportType" class="col-lg-2 control-label">Covid19 Report Type<span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<select name="covid19ReportType" id="covid19ReportType" class="form-control isRequired" title="Please select covid19 report type">
															<option value="">-- Select --</option>
															<option value='rwanda' <?php echo ($arr['covid19_report_type'] == 'rwanda') ? "selected='selected'" : ""; ?>> Rawanda </option>
															<option value='who' <?php echo ($arr['covid19_report_type'] == 'who') ? "selected='selected'" : ""; ?>> Who </option>
														</select>
													</div>
												<?php }
												if (isset($arr['covid19_positive_confirmatory_tests_required_by_central_lab']) && $arr['covid19_positive_confirmatory_tests_required_by_central_lab'] != '') { ?>
													<label for="covid19PositiveConfirmatoryTestsRequiredByCentralLab" class="col-lg-2 control-label">Covid19 Positive Confirmatory Tests Required By CentralLab<span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<select name="covid19PositiveConfirmatoryTestsRequiredByCentralLab" id="covid19PositiveConfirmatoryTestsRequiredByCentralLab" class="form-control isRequired" title="Please select covid19 report type">
															<option value="">-- Select --</option>
															<option value='yes' <?php echo ($arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
															<option value='no' <?php echo ($arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
														</select>
													</div>
												<?php } ?>
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-md-12" style="">
											<div class="form-group">
												<label for="covid19_sample_code" class="col-lg-2 control-label">Sample Code<br>Format <span class="mandatory">*</span> </label>
												<div class="col-lg-10">
													<?php
													$sPrefixMMYY = 'C19';
													$sPrefixYY = '';
													$sPrefixMMYYDisplay = 'disabled="disabled"';
													$sPrefixYYDisplay = 'disabled="disabled"';
													if ($arr['covid19_sample_code'] == 'MMYY') {
														$sPrefixMMYY = $arr['covid19_sample_code_prefix'];
														$sPrefixMMYYDisplay = '';
													} else if ($arr['covid19_sample_code'] == 'YY') {
														$sPrefixYY = $arr['covid19_sample_code_prefix'];
														$sPrefixYYDisplay = '';
													}
													?>
													<input type="radio" class="isRequired" title="Please select the Covid19 Sample Code Format" id="covid19_auto_generate_yy" name="covid19_sample_code" value="YY" <?php echo ($arr['covid19_sample_code'] == 'YY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixMMYY','prefixYY')">&nbsp;<input <?php echo $sPrefixYYDisplay; ?> type="text" class="covid19_boxWidth covid19_prefixYY" id="covid19_prefixYY" name="covid19_sample_code_prefix" title="Enter Prefix" value="<?php echo $sPrefixYY; ?>" /> YY&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired" title="Please select the Covid19 Sample Code Format" id="covid19_auto_generate_mmyy" name="covid19_sample_code" value="MMYY" <?php echo ($arr['covid19_sample_code'] == 'MMYY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixYY','prefixMMYY')">&nbsp;<input <?php echo $sPrefixMMYYDisplay; ?> type="text" class="covid19_boxWidth covid19_prefixMMYY" id="covid19_prefixMMYY" name="covid19_sample_code_prefix" title="Enter Prefix" value="<?php echo $sPrefixMMYY; ?>" /> MMYY&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired" title="Please select the Covid19 Sample Code Format" id="covid19_auto_generate" name="covid19_sample_code" value="auto" <?php echo ($arr['covid19_sample_code'] == 'auto') ? 'checked' : ''; ?>><span id="covid19_auto1"><?php echo ($arr['vl_form'] == 5) ? ' Auto 1' : ' Auto'; ?> </span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired" title="Please select the Covid19 Sample Code Format" id="covid19_auto_generate2" name="covid19_sample_code" value="auto2" <?php echo ($arr['covid19_sample_code'] == 'auto2') ? 'checked' : ''; ?> style="display:<?php echo ($arr['vl_form'] == 5) ? '' : 'none'; ?>"><span id="covid19_auto2" style="display:<?php echo ($arr['vl_form'] == 5) ? '' : 'none'; ?>"> Auto 2 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
													<input type="radio" class="isRequired" title="Please select the Covid19 Sample Code Format" id="covid19_numeric" name="covid19_sample_code" value="numeric" <?php echo ($arr['covid19_sample_code'] == 'numeric') ? 'checked' : ''; ?>> Numeric&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired" title="Please select the Covid19 Sample Code Format" id="covid19_alpha_numeric" name="covid19_sample_code" value="alphanumeric" <?php echo ($arr['covid19_sample_code'] == 'alphanumeric') ? 'checked' : ''; ?>> Alpha Numeric
												</div>
											</div>
										</div>
									</div>
									
									<div id="covid19_auto-sample-eg" class="row" style="display:<?php echo ($arr['covid19_sample_code'] == 'auto' || $arr['covid19_sample_code'] == 'auto2' || 'MMYY' || 'YY') ? 'block' : 'none'; ?>;">
										<div class="col-md-12" style="text-align:center;">
											<code id="covid19_auto-sample-code" class="covid19_autoSample" style="display:<?php echo ($arr['covid19_sample_code'] == 'auto') ? 'block' : 'none'; ?>;">
												eg. Province Code+Year+Month+Date+Increment Counter
											</code>
											<code id="covid19_auto-sample-code2" class="covid19_autoSample" style="display:<?php echo ($arr['covid19_sample_code'] == 'auto2') ? 'block' : 'none'; ?>;">
												eg. R+Year+Province Code+covid19+Increment Counter (R18NCDC190001)
											</code>
											<code id="covid19_auto-sample-code-MMYY" class="covid19_autoSample" style="display:<?php echo ($arr['covid19_sample_code'] == 'MMYY') ? 'block' : 'none'; ?>;">
												eg. Prefix+Month+Year+Increment Counter (C190517999)
											</code>
											<code id="covid19_auto-sample-code-YY" class="covid19_autoSample" style="display:<?php echo ($arr['covid19_sample_code'] == 'YY') ? 'block' : 'none'; ?>;">
												eg. Prefix+Year+Increment Counter (C1917999)
											</code>
										</div>
									</div><br />

									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="covid19_min_length" class="col-lg-2 control-label">Minimum Sample Code Length <span class="mandatory " style="display:<?php echo ($arr['covid19_sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-4">
													<input type="text" class="form-control checkNum isNumeric <?php echo ($arr['covid19_sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="covid19_min_length" name="covid19_min_length" <?php echo ($arr['covid19_sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="Min" title="Please enter sample code min length" value="<?php echo ($arr['covid19_sample_code'] == 'auto') ? '' : $arr['min_length']; ?>" />
												</div>
												<label for="covid19_max_length" class="col-lg-2 control-label">Maximum Sample Code Length <span class="mandatory " style="display:<?php echo ($arr['covid19_sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-4">
													<input type="text" class="form-control checkNum isNumeric <?php echo ($arr['sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="covid19_max_length" name="covid19_max_length" <?php echo ($arr['covid19_sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="Max" title="Please enter sample code max length" value="<?php echo ($arr['covid19_sample_code'] == 'auto') ? '' : $arr['max_length']; ?>" />
												</div>
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<?php if (isset($arr['covid19_tests_table_in_results_pdf']) && $arr['covid19_tests_table_in_results_pdf'] != '') { ?>
													<label for="covid19TestsTableInResultsPdf" class="col-lg-2 control-label">Covid19 Tests method in Results Pdf<span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<select name="covid19TestsTableInResultsPdf" id="covid19TestsTableInResultsPdf" class="form-control isRequired" title="Please select covid19 Tests method in Results Pdf">
															<option value="">-- Select --</option>
															<option value='yes' <?php echo ($arr['covid19_tests_table_in_results_pdf'] == 'yes') ? "selected='selected'" : ""; ?>> Yes </option>
															<option value='no' <?php echo ($arr['covid19_tests_table_in_results_pdf'] == 'no') ? "selected='selected'" : ""; ?>> No </option>
														</select>
													</div>
												<?php }
												if (isset($arr['covid19_negative']) && $arr['covid19_negative'] != '') { ?>
													<label for="covid19Negative" class="col-lg-2 control-label">Covid-19 Negative<span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<input value="<?php echo $arr['covid19_negative']; ?>" name="covid19Negative" id="covid19Negative" type="text" class="form-control" placeholder="Sample code prefix" title="Please enter sample code prefix" />
													</div>
												<?php }?>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<?php if (isset($arr['covid19_positive']) && $arr['covid19_positive'] != '') { ?>
													<label for="covid19Positive" class="col-lg-2 control-label">Covid-19 Positive<span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<input value="<?php echo $arr['covid19_positive']; ?>" id="covid19Positive" name="covid19Positive" type="text" class="form-control" placeholder="Sample code prefix" title="Please enter sample code prefix" />
													</div>
												<?php }?>
											</div>
										</div>
									</div>
								</div>
							</div>
						<?php } ?>
						<div class="panel panel-default">
							<div class="panel-heading">
								<h3 class="panel-title">Connect</h3>
							</div>
							<div class="panel-body">
								<!-- <div class="row">
                  <div class="col-md-7" style="height:38px;">
                    <div class="form-group" style="height:38px;">
                      <label for="sync_path" class="col-lg-4 control-label">Sync Path (Dropbox or Shared folder)</label>
                      <div class="col-lg-8">
                        <input type="text" class="form-control" id="sync_path" name="sync_path" placeholder="Sync Path" title="Please enter sync path" value="<?php echo $arr['sync_path']; ?>"/>
                      </div>
                    </div>
                   </div>
                </div>
                <div class="row">
                  <div class="col-md-7 col-md-offset-2" style="text-align:center;">
                      <code>Used for Dropbox or shared folder sync using the vlsm-connect module</code>
                  </div>
                </div><br/> -->

								<!-- <div class="row">
                  <div class="col-md-7">
                    <div class="form-group">
                      <label for="data_sync_interval" class="col-lg-4 control-label">VLSTS Data Sync Interval (in Days) <span class="mandatory">*</span> </label>
                      <div class="col-lg-8">
                        <input type="number" min="1" max="1000" class="form-control checkNum" id="data_sync_interval" name="data_sync_interval" placeholder="Data Sync Interval" title="Please enter sync interval" value="<?php echo $arr['data_sync_interval']; ?>"/>
                      </div>
                    </div>
                   </div>
                </div>                 -->

								<!-- <div class="row">
                  <div class="col-md-7">
                    <div class="form-group">
                      <label for="enable_qr_mechanism" class="col-lg-4 control-label">Enable QR Code </label>
                      <div class="col-lg-8">
                        <input type="radio" class="" id="enable_qr_mechanism_yes" name="enable_qr_mechanism" value="yes" <?php echo ($arr['enable_qr_mechanism'] == 'yes') ? 'checked' : ''; ?>>&nbsp;&nbsp;Yes&nbsp;&nbsp;
                        <input type="radio" class="" id="enable_qr_mechanism_no" name="enable_qr_mechanism" value="no" <?php echo ($arr['enable_qr_mechanism'] == 'no' || $arr['enable_qr_mechanism'] == '') ? 'checked' : ''; ?>>&nbsp;&nbsp;No
                      </div>
                    </div>
                  </div>
                </div>   -->

								<div class="row">
									<div class="col-md-7" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="sync_path" class="col-lg-4 control-label">Dashboard URL</label>
											<div class="col-lg-8">
												<input type="text" class="form-control" id="vldashboard_url" name="vldashboard_url" placeholder="https://dashboard.example.org" title="Please enter dashboard URL" value="<?php echo $arr['vldashboard_url']; ?>" />
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>



						<div class="panel panel-default">
							<div class="panel-heading">
								<h3 class="panel-title">Viral Load Result PDF Settings</h3>
							</div>
							<div class="panel-body">
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="show_smiley" class="col-lg-4 control-label">Show Emoticon/Smiley </label>
											<div class="col-lg-8">
												<input type="radio" class="" id="show_smiley_yes" name="show_smiley" value="yes" <?php echo ($arr['show_smiley'] == 'yes') ? 'checked' : ''; ?>>&nbsp;&nbsp;Yes&nbsp;&nbsp;
												<input type="radio" class="" id="show_smiley_no" name="show_smiley" value="no" <?php echo ($arr['show_smiley'] == 'no' || $arr['show_smiley'] == '') ? 'checked' : ''; ?>>&nbsp;&nbsp;No
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="h_vl_msg" class="col-lg-4 control-label">High Viral Load Message </label>
											<div class="col-lg-8">
												<textarea class="form-control" id="h_vl_msg" name="h_vl_msg" placeholder="High Viral Load message that will appear for results >= the VL threshold limit" title="Please enter high viral load message" style="width:100%;min-height:80px;max-height:100px;"><?php echo $arr['h_vl_msg']; ?></textarea>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="l_vl_msg" class="col-lg-4 control-label">Low Viral Load Message </label>
											<div class="col-lg-8">
												<textarea class="form-control" id="l_vl_msg" name="l_vl_msg" placeholder="Low Viral Load message that will appear for results lesser than the VL threshold limit" title="Please enter low viral load message" style="width:100%;min-height:80px;max-height:100px;"><?php echo $arr['l_vl_msg']; ?></textarea>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="patient_name_pdf" class="col-lg-4 control-label">Patient Name Format</label>
											<div class="col-lg-8">
												<select type="text" class="form-control" id="patient_name_pdf" name="patient_name_pdf" title="Choose one option" value="<?php echo $arr['patient_name_pdf']; ?>">
													<option value="flname" <?php echo ('flname' == $arr['patient_name_pdf']) ? "selected='selected'" : "" ?>>First Name + Last Name</option>
													<option value="fullname" <?php echo ('fullname' == $arr['patient_name_pdf']) ? "selected='selected'" : "" ?>>Full Name</option>
													<option value="hidename" <?php echo ('hidename' == $arr['patient_name_pdf']) ? "selected='selected'" : "" ?>>Hide Patient Name</option>
												</select>
											</div>
										</div>
									</div>
								</div>

								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="r_mandatory_fields" class="col-lg-4 control-label">Mandatory Fields for COMPLETED Result PDF: </label>
											<div class="col-lg-8">
												<div class="form-group">
													<div class="col-md-12">
														<div class="row">
															<div class="col-md-12" style="text-align:justify;">
																<code>If any of the selected fields are incomplete, the Result PDF appears with a <strong>DRAFT</strong> watermark. Leave right block blank (Deselect All) to disable this.</code>
															</div>
														</div>
														<div style="width:100%;margin:10px auto;clear:both;">
															<a href="#" id="select-all-field" style="float:left;" class="btn btn-info btn-xs">Select All&nbsp;&nbsp;<i class="icon-chevron-right"></i></a> <a href="#" id="deselect-all-field" style="float:right;" class="btn btn-danger btn-xs"><i class="icon-chevron-left"></i>&nbsp;Deselect All</a>
														</div><br /><br />
														<select id="r_mandatory_fields" name="r_mandatory_fields[]" multiple="multiple" class="search">
															<option value="facility_code" <?php echo (in_array('facility_code', $mFieldArray)) ? 'selected="selected"' : ''; ?>>Facility Code</option>
															<option value="facility_state" <?php echo (in_array('facility_state', $mFieldArray)) ? 'selected="selected"' : ''; ?>>Facility Province</option>
															<option value="facility_district" <?php echo (in_array('facility_district', $mFieldArray)) ? 'selected="selected"' : ''; ?>>Facility District</option>
															<option value="facility_name" <?php echo (in_array('facility_name', $mFieldArray)) ? 'selected="selected"' : ''; ?>>Facility Name</option>
															<option value="sample_code" <?php echo (in_array('sample_code', $mFieldArray)) ? 'selected="selected"' : ''; ?>>Sample Code</option>
															<option value="sample_collection_date" <?php echo (in_array('sample_collection_date', $mFieldArray)) ? 'selected="selected"' : ''; ?>>Sample Collection Date</option>
															<option value="patient_art_no" <?php echo (in_array('patient_art_no', $mFieldArray)) ? 'selected="selected"' : ''; ?>>Patient ART No.</option>
															<option value="sample_received_at_vl_lab_datetime" <?php echo (in_array('sample_received_at_vl_lab_datetime', $mFieldArray)) ? 'selected="selected"' : ''; ?>>Date Sample Received at Testing Lab</option>
															<option value="sample_tested_datetime" <?php echo (in_array('sample_tested_datetime', $mFieldArray)) ? 'selected="selected"' : ''; ?>>Sample Tested Date</option>
															<option value="sample_name" <?php echo (in_array('sample_name', $mFieldArray)) ? 'selected="selected"' : ''; ?>>Sample Type</option>
															<option value="vl_test_platform" <?php echo (in_array('vl_test_platform', $mFieldArray)) ? 'selected="selected"' : ''; ?>>VL Testing Platform</option>
															<option value="result" <?php echo (in_array('result', $mFieldArray)) ? 'selected="selected"' : ''; ?>>VL Result</option>
															<option value="approvedBy" <?php echo (in_array('approvedBy', $mFieldArray)) ? 'selected="selected"' : ''; ?>>Approved By</option>
														</select>

													</div>

												</div>

											</div>

										</div>

									</div>

								</div>

							</div>

						</div>
						<!-- /.box-body -->
						<div class="box-footer">
							<input type="hidden" name="removedLogoImage" id="removedLogoImage" />
							<input type="hidden" name="removedInstanceLogoImage" id="removedInstanceLogoImage" />
							<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
							<a href="globalConfig.php" class="btn btn-default"> Cancel</a>
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
<script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script>
<script src="/assets/js/jquery.multi-select.js"></script>
<script src="/assets/js/jquery.quicksearch.js"></script>
<script type="text/javascript">
	$(document).ready(function() {
		$('.search').multiSelect({
			selectableHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Field Name'>",
			selectionHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Field Name'>",
			afterInit: function(ms) {
				var that = this,
					$selectableSearch = that.$selectableUl.prev(),
					$selectionSearch = that.$selectionUl.prev(),
					selectableSearchString = '#' + that.$container.attr('id') + ' .ms-elem-selectable:not(.ms-selected)',
					selectionSearchString = '#' + that.$container.attr('id') + ' .ms-elem-selection.ms-selected';

				that.qs1 = $selectableSearch.quicksearch(selectableSearchString)
					.on('keydown', function(e) {
						if (e.which === 40) {
							that.$selectableUl.focus();
							return false;
						}
					});

				that.qs2 = $selectionSearch.quicksearch(selectionSearchString)
					.on('keydown', function(e) {
						if (e.which == 40) {
							that.$selectionUl.focus();
							return false;
						}
					});
			},
			afterSelect: function() {
				this.qs1.cache();
				this.qs2.cache();
			},
			afterDeselect: function() {
				this.qs1.cache();
				this.qs2.cache();
			}
		});

		$('#select-all-field').click(function() {
			$('#r_mandatory_fields').multiSelect('select_all');
			return false;
		});
		$('#deselect-all-field').click(function() {
			$('#r_mandatory_fields').multiSelect('deselect_all');
			return false;
		});
	});

	function validateNow() {
		flag = deforayValidator.init({
			formId: 'editGlobalConfigForm'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('editGlobalConfigForm').submit();
		}
	}

	function clearImage(img) {
		$(".logo").fileinput("clear");
		$("#clearImage").addClass("hide");
		$("#removedLogoImage").val(img);
	}

	function clearInstanceImage(img) {
		$(".instanceLogo").fileinput("clear");
		$("#clearInstanceImage").addClass("hide");
		$("#removedInstanceLogoImage").val(img);
	}

	function getNewImage(img) {
		$("#clearImage").addClass("hide");
		$("#removedLogoImage").val(img);
	}

	function getNewInstanceImage(img) {
		$("#clearInstanceImage").addClass("hide");
		$("#removedInstanceLogoImage").val(img);
	}

	$("input:radio[name=sample_code]").click(function() {
		if (this.value == 'MMYY' || this.value == 'YY') {
			$('#auto-sample-eg').show();
			$('.autoSample').hide();
			if (this.value == 'MMYY') {
				$('#auto-sample-code-MMYY').show();
			} else {
				$('#auto-sample-code-YY').show();
			}
			$('#min_length').val('');
			$('.minlth').hide();
			$('#min_length').removeClass('isRequired');
			$('#min_length').prop('readonly', true);
			$('#max_length').val('');
			$('.maxlth').hide();
			$('#max_length').removeClass('isRequired');
			$('#max_length').prop('readonly', true);
		} else if (this.value == 'auto') {
			$('.autoSample').hide();
			$('#auto-sample-eg').show();
			$('#auto-sample-code').show();
			$('#min_length').val('');
			$('.minlth').hide();
			$('#min_length').removeClass('isRequired');
			$('#min_length').prop('readonly', true);
			$('#max_length').val('');
			$('.maxlth').hide();
			$('#max_length').removeClass('isRequired');
			$('#max_length').prop('readonly', true);
			$('.boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else if (this.value == 'auto2') {
			$('.autoSample').hide();
			$('#auto-sample-eg').show();
			$('#auto-sample-code2').show();
			$('#min_length').val('');
			$('.minlth').hide();
			$('#min_length').removeClass('isRequired');
			$('#min_length').prop('readonly', true);
			$('#max_length').val('');
			$('.maxlth').hide();
			$('#max_length').removeClass('isRequired');
			$('#max_length').prop('readonly', true);
			$('.boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else {
			$('#auto-sample-eg').hide();
			$('.minlth').show();
			$('#min_length').addClass('isRequired');
			$('#min_length').prop('readonly', false);
			$('.maxlth').show();
			$('#max_length').addClass('isRequired');
			$('#max_length').prop('readonly', false);
			$('.boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		}
	});

	$("input:radio[name=eid_sample_code]").click(function() {
		if (this.value == 'MMYY' || this.value == 'YY') {
			$('#eid_auto-sample-eg').show();
			$('.eid_autoSample').hide();
			if (this.value == 'MMYY') {
				$('#eid_auto-sample-code-MMYY').show();
			} else {
				$('#eid_auto-sample-code-YY').show();
			}
			$('#eid_min_length').val('');
			$('.eid_minlth').hide();
			$('#eid_min_length').removeClass('isRequired');
			$('#eid_min_length').prop('readonly', true);
			$('#eid_max_length').val('');
			$('.eid_maxlth').hide();
			$('#eid_max_length').removeClass('isRequired');
			$('#eid_max_length').prop('readonly', true);
		} else if (this.value == 'auto') {
			$('.eid_autoSample').hide();
			$('#eid_auto-sample-eg').show();
			$('#eid_auto-sample-code').show();
			$('#eid_min_length').val('');
			$('.eid_minlth').hide();
			$('#eid_min_length').removeClass('isRequired');
			$('#min_length').prop('readonly', true);
			$('#eid_max_length').val('');
			$('.eid_maxlth').hide();
			$('#eid_max_length').removeClass('isRequired');
			$('#eid_max_length').prop('readonly', true);
			$('.eid_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else if (this.value == 'auto2') {
			$('.eid_autoSample').hide();
			$('#eid_auto-sample-eg').show();
			$('#eid_auto-sample-code2').show();
			$('#eid_min_length').val('');
			$('.eid_minlth').hide();
			$('#eid_min_length').removeClass('isRequired');
			$('#eid_min_length').prop('readonly', true);
			$('#eid_max_length').val('');
			$('.eid_maxlth').hide();
			$('#eid_max_length').removeClass('isRequired');
			$('#eid_max_length').prop('readonly', true);
			$('.eid_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else {
			$('#eid_auto-sample-eg').hide();
			$('.eid_minlth').show();
			$('#eid_min_length').addClass('isRequired');
			$('#eid_min_length').prop('readonly', false);
			$('.eid_maxlth').show();
			$('#eid_max_length').addClass('isRequired');
			$('#eid_max_length').prop('readonly', false);
			$('.eid_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		}
	});
	
	$("input:radio[name=covid19_sample_code]").click(function() {
		if (this.value == 'MMYY' || this.value == 'YY') {
			$('#covid19_auto-sample-eg').show();
			$('.covid19_autoSample').hide();
			if (this.value == 'MMYY') {
				$('#covid19_auto-sample-code-MMYY').show();
			} else {
				$('#covid19_auto-sample-code-YY').show();
			}
			$('#covid19_min_length').val('');
			$('.covid19_minlth').hide();
			$('#covid19_min_length').removeClass('isRequired');
			$('#covid19_min_length').prop('readonly', true);
			$('#covid19_max_length').val('');
			$('.covid19_maxlth').hide();
			$('#covid19_max_length').removeClass('isRequired');
			$('#covid19_max_length').prop('readonly', true);
		} else if (this.value == 'auto') {
			$('.covid19_autoSample').hide();
			$('#covid19_auto-sample-eg').show();
			$('#covid19_auto-sample-code').show();
			$('#covid19_min_length').val('');
			$('.covid19_minlth').hide();
			$('#covid19_min_length').removeClass('isRequired');
			$('#min_length').prop('readonly', true);
			$('#covid19_max_length').val('');
			$('.covid19_maxlth').hide();
			$('#covid19_max_length').removeClass('isRequired');
			$('#covid19_max_length').prop('readonly', true);
			$('.covid19_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else if (this.value == 'auto2') {
			$('.covid19_autoSample').hide();
			$('#covid19_auto-sample-eg').show();
			$('#covid19_auto-sample-code2').show();
			$('#covid19_min_length').val('');
			$('.covid19_minlth').hide();
			$('#covid19_min_length').removeClass('isRequired');
			$('#covid19_min_length').prop('readonly', true);
			$('#covid19_max_length').val('');
			$('.covid19_maxlth').hide();
			$('#covid19_max_length').removeClass('isRequired');
			$('#covid19_max_length').prop('readonly', true);
			$('.covid19_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else {
			$('#covid19_auto-sample-eg').hide();
			$('.covid19_minlth').show();
			$('#covid19_min_length').addClass('isRequired');
			$('#covid19_min_length').prop('readonly', false);
			$('.covid19_maxlth').show();
			$('#covid19_max_length').addClass('isRequired');
			$('#covid19_max_length').prop('readonly', false);
			$('.covid19_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		}
	});

	function makeReadonly(id1, id2) {
		$("#" + id1).val('');
		$("#" + id1).attr("disabled", 'disabled').removeClass('isRequired');
		$("#" + id2).attr("disabled", false).addClass('isRequired');
	}

	$("#vl_form").on('change', function() {
		$('input[name="sample_code"]:radio').prop('checked', false);
		$('.autoSample').hide();
		$('#auto-sample-eg').hide();
		if (this.value == 5) {
			$('#auto_generate2,#auto2').show();
			$('#auto1').html('Auto 1');
		} else {
			$('#auto_generate2,#auto2').hide();
			$('#auto1').html('Auto');
		}
	});
</script>
<?php
include(APPLICATION_PATH . '/footer.php');
?>