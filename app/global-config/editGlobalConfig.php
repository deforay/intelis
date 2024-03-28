<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;


$title = _translate("Edit General Configuration");

require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$instanceQuery = "SELECT * FROM s_vlsm_instance
					WHERE vlsm_instance_id= ?";
$instanceResult = $db->rawQuery($instanceQuery, [$_SESSION['instanceId']]);


$formQuery = "SELECT * FROM s_available_country_forms
				ORDER by form_name ASC";
$formResult = $db->query($formQuery);
$globalConfigQuery = "SELECT * FROM global_config";
$configResult = $db->query($globalConfigQuery);
$arr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($configResult); $i++) {
	$arr[$configResult[$i]['name']] = $configResult[$i]['value'];
}

// Get locale directory list
$localeLists = $general->getLocaleList((int)($arr['vl_form'] ?? 0));


$mFieldArray = [];
if (isset($arr['r_mandatory_fields']) && trim((string) $arr['r_mandatory_fields']) != '') {
	$mFieldArray = explode(',', (string) $arr['r_mandatory_fields']);
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
		<h1><em class="fa-solid fa-gears"></em> <?php echo _translate("System Configuration"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("System Configuration"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">

		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;">
					<?php if (_isAllowed("editGlobalConfig.php") && !isset($_GET['e'])) { ?>
						<div class="col-sm-6 pull-right">
							<a href="editGlobalConfig.php?e=1" class="btn btn-primary pull-right"> <em class="fa-solid fa-pen-to-square"></em></em> <?php echo _translate("Edit General Config"); ?></a>
						</div>
					<?php } ?>
					<br>
				</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='editGlobalConfigForm' id='editGlobalConfigForm' enctype="multipart/form-data" autocomplete="off" action="globalConfigHelper.php">
					<div class="box-body">
						<div class="panel panel-default">
							<div class="panel-heading">
								<h3 class="panel-title"><?php echo _translate("Instance Settings"); ?></h3>
							</div>
							<div class="panel-body">
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="facilityId" class="col-lg-4 control-label"><?php echo _translate("Instance/Facility Name"); ?> <span class="mandatory">*</span></label>
											<div class="col-lg-8">
												<input type="text" class="form-control isRequired readPage" name="facilityId" id="facilityId" title="<?php echo _translate('Please enter instance name'); ?>" placeholder="<?php echo _translate('Facility/Instance Name'); ?>" value="<?php echo $instanceResult[0]['instance_facility_name']; ?>" />
											</div>
										</div>
									</div>
									<div class="col-md-7">
										<div class="form-group">
											<label for="facilityCode" class="col-lg-4 control-label"><?php echo _translate("Instance/Facility Code"); ?> </label>
											<div class="col-lg-8">
												<input type="text" class="form-control readPage" id="facilityCode" name="facilityCode" placeholder="<?php echo _translate('Facility Code'); ?>" title="<?php echo _translate('Please enter instance/facility code'); ?>" value="<?php echo $instanceResult[0]['instance_facility_code']; ?>" />
											</div>
										</div>
									</div>
									<div class="col-md-7">
										<div class="form-group">
											<label for="instance_type" class="col-lg-4 control-label"><?php echo _translate("Instance/Facility Type"); ?> <span class="mandatory">*</span></label>
											<div class="col-lg-8">
												<select class="form-control isRequired readPage" name="instance_type" id="instance_type" title="<?php echo _translate('Please select the instance type'); ?>">
													<option value="Viral Load Lab" <?php echo ('Viral Load Lab' == $arr['instance_type']) ? "selected='selected'" : "" ?>><?php echo _translate("Viral Load Lab"); ?></option>
													<option value="Clinic/Lab" <?php echo ('Clinic/Lab' == $arr['instance_type']) ? "selected='selected'" : "" ?>><?php echo _translate("Clinic/Lab"); ?></option>
													<option value="Both" <?php echo ('Both' == $arr['instance_type']) ? "selected='selected'" : "" ?>><?php echo _translate("Both"); ?></option>
												</select>
											</div>
										</div>
									</div>
									<div class="row" style="display:none;">
										<div class="col-md-7">
											<div class="form-group">
												<label for="" class="col-lg-4 control-label"><?php echo _translate("Logo Image"); ?> </label>
												<div class="col-lg-8">
													<div class="fileinput fileinput-new instanceLogo" data-provides="fileinput">
														<div class="fileinput-preview thumbnail" data-trigger="fileinput" style="width:200px; height:150px;">
															<?php
															if (isset($instanceResult[0]['instance_facility_logo']) && trim((string) $instanceResult[0]['instance_facility_logo']) != '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "instance-logo" . DIRECTORY_SEPARATOR . $instanceResult[0]['instance_facility_logo'])) {
															?>
																<img src="/uploads/instance-logo/<?php echo $instanceResult[0]['instance_facility_logo']; ?>" alt="Logo image">
															<?php } else { ?>

															<?php } ?>
														</div>
														<div>
															<span class="btn btn-default btn-file"><span class="fileinput-new"><?php echo _translate("Select image"); ?></span><span class="fileinput-exists"><?php echo _translate("Change"); ?></span>
																<input class="readPage" type="file" id="instanceLogo" name="instanceLogo" title="<?php echo _translate('Please select logo image'); ?>" onchange="getNewInstanceImage('<?php echo $instanceResult[0]['instance_facility_logo']; ?>');">
															</span>
															<?php
															if (isset($instanceResult[0]['instance_facility_logo']) && trim((string) $instanceResult[0]['instance_facility_logo']) != '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "instance-logo" . DIRECTORY_SEPARATOR . $instanceResult[0]['instance_facility_logo'])) {
															?>
																<a id="clearInstanceImage" href="javascript:void(0);" class="btn btn-default" data-dismiss="fileupload" onclick="clearInstanceImage('<?php echo $instanceResult[0]['instance_facility_logo']; ?>')"><?php echo _translate("Clear"); ?></a>
															<?php } ?>
															<a href="#" class="btn btn-default fileinput-exists" data-dismiss="fileinput"><?php echo _translate("Remove"); ?></a>
														</div>
													</div>
													<div class="box-body">
														<?php echo _translate("Please make sure logo image size of"); ?>: <code>80x80</code>
													</div>
												</div>
											</div>
										</div>
									</div>
									<div class="col-md-7">
										<div class="form-group">
											<label for="gui_date_format" class="col-lg-4 control-label"><?php echo _translate("Date Format"); ?> <span class="mandatory">*</span></label>
											<div class="col-lg-8">
												<select class="form-control isRequired readPage" name="gui_date_format" id="gui_date_format" title="<?php echo _translate('Please select the date format'); ?>">
													<option value="d-M-Y" <?php echo ('d-M-Y' == $arr['gui_date_format']) ? "selected='selected'" : "" ?>><?php echo date('d-M-Y'); ?></option>
													<option value="d-m-Y" <?php echo ('d-m-Y' == $arr['gui_date_format']) ? "selected='selected'" : "" ?>><?php echo date('d-m-Y'); ?></option>
												</select>
											</div>
										</div>
									</div>
									<div class="col-md-7">
										<div class="form-group">
											<label for="gui_date_format" class="col-lg-4 control-label"><?php echo _translate("Display Encrypt PII Option"); ?> <span class="mandatory">*</span></label>
											<div class="col-lg-8">
												<select class="form-control isRequired readPage" name="display_encrypt_pii_option" id="display_encrypt_pii_option" title="<?php echo _translate('Please select the date format'); ?>">
													<option value="yes" <?php echo ('yes' == $arr['display_encrypt_pii_option']) ? "selected='selected'" : "" ?>><?php echo _translate('Yes'); ?></option>
													<option value="no" <?php echo ('no' == $arr['display_encrypt_pii_option']) ? "selected='selected'" : "" ?>><?php echo _translate('No'); ?></option>
												</select>
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="panel panel-default">
							<div class="panel-heading">
								<h3 class="panel-title"><?php echo _translate("Global Settings"); ?></h3>
							</div>
							<div class="panel-body">
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="vl_form" class="col-lg-4 control-label"><?php echo _translate("Choose Country of Installation"); ?> <span class="mandatory">*</span> </label>
											<div class="col-lg-8">
												<select class="form-control isRequired readPage select2" name="vl_form" id="vl_form" title="<?php echo _translate('Please select the viral load form'); ?>" onchange="showExportFormat();">
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
											<label for="default_time_zone" class="col-lg-4 control-label"><?php echo _translate("Default Time Zone"); ?> </label>
											<div class="col-lg-8">
												<select class="form-control readPage select2 isRequired" id="default_time_zone" name="default_time_zone" placeholder="<?php echo _translate('Timezone'); ?>" title="<?php echo _translate('Please choose Timezone'); ?>">
													<option value=""><?= _translate("-- Select --"); ?></option>
													<?php
													$timezone_identifiers = DateTimeZone::listIdentifiers();

													foreach ($timezone_identifiers as $value) {
													?>
														<option <?= ($arr['default_time_zone'] == $value ? 'selected=selected' : ''); ?> value='<?= $value; ?>'> <?= $value; ?></option>;
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
											<label for="app_locale" class="col-lg-4 control-label"><?php echo _translate("System Locale"); ?> <span class="mandatory">*</span> </label>
											<div class="col-lg-8">
												<select class="form-control isRequired readPage" name="app_locale" id="app_locale" title="<?php echo _translate('Please select the System Locale'); ?>">
													<?php foreach ($localeLists as $locale => $localeName) { ?>
														<option value="<?php echo $locale; ?>" <?php echo (isset($arr['app_locale']) && $arr['app_locale'] == $locale) ? 'selected="selected"' : ''; ?>><?= $localeName; ?></option>
													<?php } ?>
												</select>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="header" class="col-lg-4 control-label"><?php echo _translate("Header"); ?> </label>
											<div class="col-lg-8">
												<textarea class="form-control readPage" id="header" name="header" placeholder="<?php echo _translate('Header'); ?>" title="<?php echo _translate('Please enter header'); ?>" style="width:100%;min-height:80px;max-height:100px;"><?php echo $arr['header']; ?></textarea>
											</div>
										</div>
									</div>
								</div>

								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="" class="col-lg-4 control-label"><?php echo _translate("Logo Image"); ?> </label>
											<div class="col-lg-8">
												<div class="fileinput fileinput-new logo" data-provides="fileinput">
													<div class="fileinput-preview thumbnail" data-trigger="fileinput" style="width:200px; height:150px;">
														<?php
														if (isset($arr['logo']) && trim((string) $arr['logo']) != '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $arr['logo'])) {
														?>
															<img src="/uploads/logo/<?php echo $arr['logo']; ?>" alt="Logo image">
														<?php } else { ?>

														<?php } ?>
													</div>
													<div>
														<span class="btn btn-default btn-file"><span class="fileinput-new"><?php echo _translate("Select image"); ?></span><span class="fileinput-exists"><?php echo _translate("Change"); ?></span>
															<input type="file" class="readPage" id="logo" name="logo" title="<?php echo _translate('Please select logo image'); ?>" onchange="getNewImage('<?php echo $arr['logo']; ?>');">
														</span>
														<?php
														if (isset($arr['logo']) && trim((string) $arr['logo']) != '' && file_exists(UPLOAD_PATH . DIRECTORY_SEPARATOR . "logo" . DIRECTORY_SEPARATOR . $arr['logo'])) {
														?>
															<a id="clearImage" href="javascript:void(0);" class="btn btn-default" data-dismiss="fileupload" onclick="clearImage('<?php echo $arr['logo']; ?>')"><?php echo _translate("Clear"); ?></a>
														<?php } ?>
														<a href="#" class="btn btn-default fileinput-exists" data-dismiss="fileinput"><?php echo _translate("Remove"); ?></a>
													</div>
												</div>
												<div class="box-body">
													<?php echo _translate("Please make sure logo image size of"); ?>: <code>80x80</code>
												</div>
											</div>
										</div>
									</div>
								</div>


								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="edit_profile" class="col-lg-4 control-label"><?php echo _translate("Allow users to Edit Profile"); ?> </label>
											<div class="col-lg-8">
												<input type="radio" class="readPage" id="edit_profile_yes" name="edit_profile" value="yes" <?php echo ($arr['edit_profile'] == 'yes') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("Yes"); ?>&nbsp;&nbsp;
												<input type="radio" class="readPage" id="edit_profile_no" name="edit_profile" value="no" <?php echo ($arr['edit_profile'] == 'no') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("No"); ?>
											</div>
										</div>
									</div>
								</div>

								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="training_mode" class="col-lg-4 control-label"><?php echo _translate("Training Mode"); ?> </label>
											<div class="col-lg-8">
												<input type="radio" class="readPage" id="training_mode_yes" name="training_mode" value="yes" <?php echo ($arr['training_mode'] == 'yes') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("Yes"); ?>&nbsp;&nbsp;
												<input type="radio" class="readPage" id="training_mode_no" name="training_mode" value="no" <?php echo ($arr['training_mode'] == 'no') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("No"); ?>
												<input type="text" <?php echo ($arr['training_mode'] == 'yes') ? '' : 'style="display:none"'; ?> value="<?php echo $arr['training_mode_text']; ?>" id="training_mode_text" name="training_mode_text" class="form-control readPage" placeholder="Enter the training mode text" title="Please enter the training mode text here" />
											</div>
										</div>
									</div>
								</div>

								<div class="row">
									<div class="col-md-7" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="barcode_format" class="col-lg-4 control-label"><?php echo _translate("Barcode Format"); ?></label>
											<div class="col-lg-8">
												<select class="form-control isRequired readPage" name="barcode_format" id="barcode_format" title="<?php echo _translate('Please select the Barcode type'); ?>">
													<option value="C39" <?php echo ('C39' == $arr['barcode_format']) ? "selected='selected'" : "" ?>><?php echo _translate("C39"); ?></option>
													<option value="C39+" <?php echo ('C39+' == $arr['barcode_format']) ? "selected='selected'" : "" ?>><?php echo _translate("C39+"); ?></option>
													<option value="C128" <?php echo ('C128' == $arr['barcode_format']) ? "selected='selected'" : "" ?>><?php echo _translate("C128"); ?></option>
													<option value="QRCODE" <?php echo ('QRCODE' == $arr['barcode_format']) ? "selected='selected'" : "" ?>><?php echo _translate("QRCODE"); ?></option>
												</select>
											</div>
										</div>
									</div>
								</div>

								<div class="row" style="margin-top:10px;">
									<div class="col-md-7">
										<div class="form-group">
											<label for="auto_approval" class="col-lg-4 control-label"><?php echo _translate("Same user can Review and Approve"); ?> </label>
											<div class="col-lg-8">
												<br>
												<input type="radio" class="readPage" id="user_review_yes" name="user_review_approve" value="yes" <?php echo ($arr['user_review_approve'] == 'yes') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("Yes"); ?>&nbsp;&nbsp;
												<input type="radio" class="readPage" id="user_review_no" name="user_review_approve" value="no" <?php echo ($arr['user_review_approve'] == 'no') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("No"); ?>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="manager_email" class="col-lg-4 control-label"><?php echo _translate("Manager Email"); ?></label>
											<div class="col-lg-8">
												<input type="text" class="form-control readPage" id="manager_email" name="manager_email" placeholder="<?php echo _translate('eg. manager1@example.com, manager2@example.com'); ?>" title="<?php echo _translate('Please enter manager email'); ?>" value="<?php echo $arr['manager_email']; ?>" />
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7" style="text-align:center;">
										<code><?php echo _translate("You can enter multiple emails by separating them with commas"); ?></code>
									</div>
								</div><br />
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="instance_type" class="col-lg-4 control-label"><?php echo _translate("Sample ID Barcode Label Printing"); ?> <span class="mandatory">*</span> </label>
											<div class="col-lg-8">
												<select class="form-control isRequired readPage" name="bar_code_printing" id="bar_code_printing" title="<?php echo _translate('Please select the barcode printing'); ?>">
													<option value="off" <?php echo ('off' == $arr['bar_code_printing']) ? "selected='selected'" : "" ?>><?php echo _translate("Off"); ?></option>
													<option value="zebra-printer" <?php echo ('zebra-printer' == $arr['bar_code_printing']) ? "selected='selected'" : "" ?>><?php echo _translate("Zebra Printer"); ?></option>
													<option value="dymo-labelwriter-450" <?php echo ('dymo-labelwriter-450' == $arr['bar_code_printing']) ? "selected='selected'" : "" ?>><?php echo _translate("Dymo LabelWriter 450"); ?></option>
												</select>
											</div>
										</div>
									</div>
								</div>
								<div class="row" style="margin-top:10px;">
									<div class="col-md-7">
										<div class="form-group">
											<label for="import_non_matching_sample" class="col-lg-4 control-label"><?php echo _translate("Allow Samples not matching the System Sample IDs while importing results manually"); ?></label>
											<div class="col-lg-8">
												<br>
												<br>
												<input class="readPage" type="radio" id="import_non_matching_sample_yes" name="import_non_matching_sample" value="yes" <?php echo ($arr['import_non_matching_sample'] == 'yes') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("Yes"); ?>&nbsp;&nbsp;
												<input class="readPage" type="radio" id="import_non_matching_sample_no" name="import_non_matching_sample" value="no" <?php echo ($arr['import_non_matching_sample'] == 'no') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("No"); ?>
												<br><br> <code><?php echo _translate("While importing results from CSV/Excel file, should we import results of Sample IDs that do not match the Sample IDs present in System database"); ?></code>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="support_email" class="col-lg-4 control-label"><?php echo _translate("Support Email"); ?></label>
											<div class="col-lg-8">
												<input type="text" class="form-control readPage" id="support_email" name="support_email" placeholder="<?php echo _translate('eg. manager1@example.com, manager2@example.com'); ?>" title="<?php echo _translate('Please enter manager email'); ?>" value="<?php echo $arr['support_email']; ?>" />
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7" style="text-align:center;">
										<code><?php echo _translate("You can enter multiple emails by separating them with commas"); ?></code>
									</div>
								</div><br />
								<div class="row">
									<div class="col-md-7" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="default_csv_delimiter" class="col-lg-4 control-label"><?php echo _translate("CSV Delimiter"); ?></label>
											<div class="col-lg-8">
												<input type="text" class="form-control" id="default_csv_delimiter" name="default_csv_delimiter" style="max-width:60px;" title="<?php echo _translate('Please enter CSV delimiter'); ?>" value="<?php echo $arr['default_csv_delimiter']; ?>" />
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="default_csv_enclosure" class="col-lg-4 control-label"><?php echo _translate("CSV Enclosure"); ?></label>
											<div class="col-lg-8">
												<input type="text" class="form-control" id="default_csv_enclosure" name="default_csv_enclosure" style="max-width:60px;" title="<?php echo _translate('Please enter CSV enclosure'); ?>" value='<?php echo $arr['default_csv_enclosure']; ?>' />
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="support_email" class="col-lg-4 control-label"><?php echo _translate("Default Phone Prefix"); ?></label>
											<div class="col-lg-8">
												<input type="text" class="form-control readPage" id="default_phone_prefix" name="default_phone_prefix" placeholder="<?php echo _translate('e.g. +232, +91'); ?>" title="<?php echo _translate('Please enter manager email'); ?>" value="<?php echo $arr['default_phone_prefix']; ?>" />
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="support_email" class="col-lg-4 control-label"><?php echo _translate("Minimum Length of Phone Number"); ?></label>
											<div class="col-lg-8">
												<input type="text" class="form-control forceNumeric readPage isNumeric" id="min_phone_length" name="min_phone_length" placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter minimun length of phone number'); ?>" value="<?php echo ($arr['min_phone_length'] == '') ? '' : $arr['min_phone_length']; ?>" style="max-width:60px;" />
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="support_email" class="col-lg-4 control-label"><?php echo _translate("Maximum Length of Phone Number"); ?></label>
											<div class="col-lg-8">
												<input type="text" class="form-control forceNumeric readPage isNumeric" id="max_phone_length" name="max_phone_length" placeholder="<?php echo _translate('Max'); ?>" title="<?php echo _translate('Please enter maximun length of phone number'); ?>" value="<?php echo ($arr['max_phone_length'] == '') ? '' : $arr['max_phone_length']; ?>" style="max-width:60px;" />
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="batch_pdf_layout" class="col-lg-4 control-label"><?php echo _translate("Batch Pdf Layout"); ?> </label>
											<div class="col-lg-8">
												<br>
												<input type="radio" class="readPage" id="standard_batch_pdf_layout" name="batch_pdf_layout" value="standard" <?php echo ($arr['batch_pdf_layout'] == 'standard' || $arr['batch_pdf_layout'] == '') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("Standard"); ?>&nbsp;&nbsp;
												<input type="radio" class="readPage" id="compact_batch_pdf_layout" name="batch_pdf_layout" value="compact" <?php echo ($arr['batch_pdf_layout'] == 'compact' ) ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("Compact"); ?>
											</div>
										</div>
									</div>
								</div>

							</div>
						</div>
						<?php if (SYSTEM_CONFIG['modules']['vl']) { ?>
							<div class="panel panel-default">
								<div class="panel-heading">
									<h3 class="panel-title"><?php echo _translate("Viral Load Settings"); ?></h3>
								</div>
								<div class="panel-body">
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="show_date" class="col-lg-2 control-label"><?php echo _translate("Date For Patient ART NO"); ?>. </label>
												<div class="col-lg-10">
													<br>
													<input type="radio" class="readPage" id="show_full_date_yes" name="show_date" value="yes" <?php echo ($arr['show_date'] == 'yes') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("Full Date"); ?>&nbsp;&nbsp;
													<input type="radio" class="readPage" id="show_full_date_no" name="show_date" value="no" <?php echo ($arr['show_date'] == 'no' || $arr['show_date'] == '') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("Month and Year"); ?>
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
												<label for="viral_load_threshold_limit" class="col-lg-2 control-label"><?php echo _translate("Viral Load Threshold Limit"); ?><span class="mandatory">*</span></label>
												<div class="col-lg-10">
													<div class="input-group" style="max-width:200px;">
														<input type="text" class="form-control readPage forceNumeric isNumeric isRequired" id="viral_load_threshold_limit" name="viral_load_threshold_limit" placeholder="<?php echo _translate('Viral Load Threshold Limit'); ?>" title="<?php echo _translate('Please enter VL threshold limit'); ?>" value="<?php echo $arr['viral_load_threshold_limit']; ?>" />
														<span class="input-group-addon"><?php echo _translate("cp/ml"); ?></span>
													</div>

												</div>
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="auto_approval" class="col-lg-2 control-label"><?php echo _translate("Sample ID"); ?><br><?php echo _translate("Format"); ?> <span class="mandatory">*</span> </label>
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
													<input type="radio" title="<?php echo _translate('Please select the Viral Load Sample ID Format'); ?>" class="isRequired readPage" id="auto_generate_yy" name="sample_code" value="YY" <?php echo ($arr['sample_code'] == 'YY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixMMYY','prefixYY')">&nbsp;<input <?php echo $sPrefixYYDisplay; ?> type="text" class="boxWidth prefixYY readPage" id="prefixYY" name="sample_code_prefix" title="<?php echo _translate('Enter Prefix'); ?>" value="<?php echo $sPrefixYY; ?>" /> <?php echo _translate("YY"); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" title="<?php echo _translate('Please select the Viral Load Sample ID Format'); ?>" class="isRequired readPage" id="auto_generate_mmyy" name="sample_code" value="MMYY" <?php echo ($arr['sample_code'] == 'MMYY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixYY','prefixMMYY')">&nbsp;<input <?php echo $sPrefixMMYYDisplay; ?> type="text" class="boxWidth prefixMMYY readPage" id="prefixMMYY" name="sample_code_prefix" title="<?php echo _translate('Enter Prefix'); ?>" value="<?php echo $sPrefixMMYY; ?>" /> <?php echo _translate("MMYY"); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" title="<?php echo _translate('Please select the Viral Load Sample ID Format'); ?>" class="isRequired readPage" id="auto_generate" name="sample_code" value="auto" <?php echo ($arr['sample_code'] == 'auto') ? 'checked' : ''; ?>><span id="auto1"><?php echo ($arr['vl_form'] == COUNTRY\PNG) ? ' Auto 1' : ' Auto'; ?> </span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" title="<?php echo _translate('Please select the Viral Load Sample ID Format'); ?>" class="isRequired readPage" id="auto_generate2" name="sample_code" value="auto2" <?php echo ($arr['sample_code'] == 'auto2' && $arr['vl_form'] == COUNTRY\PNG) ? 'checked' : ''; ?> style="display:<?php echo ($arr['vl_form'] == COUNTRY\PNG) ? '' : 'none'; ?>"><span id="auto2" style="display:<?php echo ($arr['vl_form'] == COUNTRY\PNG) ? '' : 'none'; ?>"> <?php echo _translate("Auto"); ?> 2 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
													<input type="radio" title="<?php echo _translate('Please select the Viral Load Sample ID Format'); ?>" class="isRequired readPage" id="numeric" name="sample_code" value="numeric" <?php echo ($arr['sample_code'] == 'numeric') ? 'checked' : ''; ?>> <?php echo _translate("Numeric"); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" title="<?php echo _translate('Please select the Viral Load Sample ID Format'); ?>" class="isRequired readPage" id="alpha_numeric" name="sample_code" value="alphanumeric" <?php echo ($arr['sample_code'] == 'alphanumeric') ? 'checked' : ''; ?>> <?php echo _translate("Alpha Numeric"); ?>
												</div>
											</div>
										</div>
									</div>

									<div id="auto-sample-eg" class="row" style="display:<?php echo ($arr['sample_code'] == 'auto' || $arr['sample_code'] == 'auto2' || 'MMYY' || 'YY') ? 'block' : 'none'; ?>;">
										<div class="col-md-12" style="text-align:center;">
											<code id="auto-sample-code" class="autoSample" style="display:<?php echo ($arr['sample_code'] == 'auto') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Province Code+Year+Month+Date+Increment Counter"); ?>
											</code>
											<code id="auto-sample-code2" class="autoSample" style="display:<?php echo ($arr['sample_code'] == 'auto2') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. R+Year+Province Code+VL+Increment Counter (R18NCDVL0001)"); ?>
											</code>
											<code id="auto-sample-code-MMYY" class="autoSample" style="display:<?php echo ($arr['sample_code'] == 'MMYY') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Prefix+Month+Year+Increment Counter (VL0517999)"); ?>
											</code>
											<code id="auto-sample-code-YY" class="autoSample" style="display:<?php echo ($arr['sample_code'] == 'YY') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Prefix+Year+Increment Counter (VL17999)"); ?>
											</code>
										</div>
									</div><br />
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="min_length" class="col-lg-2 control-label"><?php echo _translate("Minimum Sample ID Length"); ?> <span class="mandatory" style="display:<?php echo ($arr['sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-10">
													<input type="text" class="form-control forceNumeric readPage isNumeric <?php echo ($arr['sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="min_length" name="min_length" <?php echo ($arr['sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter sample id min length'); ?>" value="<?php echo ($arr['sample_code'] == 'auto') ? '' : $arr['min_length']; ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="min_length" class="col-lg-2 control-label"><?php echo _translate("Maximum Sample ID Length"); ?> <span class="mandatory" style="display:<?php echo ($arr['sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-10">
													<input type="text" class="form-control forceNumeric readPage isNumeric <?php echo ($arr['sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="max_length" name="max_length" <?php echo ($arr['sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="<?php echo _translate('Max'); ?>" title="<?php echo _translate('Please enter sample id max length'); ?>" value="<?php echo ($arr['sample_code'] == 'auto') ? '' : $arr['max_length']; ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="vl_min_patient_id_length" class="col-lg-2 control-label"><?php echo _translate("Minimum Patient ID Length"); ?></label>
												<div class="col-lg-10">
													<input type="text" class="form-control forceNumeric isNumeric" id="vl_min_patient_id_length" name="vl_min_patient_id_length" placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter patient id min length'); ?>" value="<?php echo ($arr['vl_min_patient_id_length'] == '') ? '' : $arr['vl_min_patient_id_length']; ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
									</div>
									<?php if (isset($arr['lock_approved_vl_samples']) && $arr['lock_approved_vl_samples'] != '') { ?>
										<div class="row">
											<div class="col-md-12">
												<div class="form-group">
													<label for="lockApprovedVlSamples" class="col-lg-2 control-label"><?php echo _translate("Lock Approved VL Samples"); ?><span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<select id="lockApprovedVlSamples" name="lockApprovedVlSamples" type="text" class="form-control readPage" title="<?php echo _translate('Please select lock approved sample'); ?>">
															<option value=""><?php echo _translate("--Select--"); ?></option>
															<option value="yes" <?php echo (isset($arr['lock_approved_vl_samples']) && $arr['lock_approved_vl_samples'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
															<option value="no" <?php echo (isset($arr['lock_approved_vl_samples']) && $arr['lock_approved_vl_samples'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
														</select>
													</div>
												</div>
											</div>
										</div>
									<?php } ?>
									<?php if (isset($arr['vl_suppression_target']) && $arr['vl_suppression_target'] != '') { ?>
										<div class="row">
											<div class="col-md-12">
												<div class="form-group">
													<label for="vl_suppression_target" class="col-lg-2 control-label"><?php echo _translate("VL Suppression Target"); ?><span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<select id="vl_suppression_target" name="vl_suppression_target" type="text" class="form-control readPage" title="<?php echo _translate('Please select lock approved sample'); ?>">
															<option value=""><?php echo _translate("--Select--"); ?></option>
															<option value="yes" <?php echo (isset($arr['vl_suppression_target']) && $arr['vl_suppression_target'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Enable"); ?></option>
															<option value="no" <?php echo (isset($arr['vl_suppression_target']) && $arr['vl_suppression_target'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("Disable"); ?></option>
														</select>
													</div>
												</div>
											</div>
										</div>
									<?php } ?>
									<?php if (isset($arr['vl_monthly_target']) && $arr['vl_monthly_target'] != '') { ?>
										<div class="row">
											<div class="col-md-12">
												<div class="form-group">
													<label for="vl_monthly_target" class="col-lg-2 control-label"><?php echo _translate("VL Monthly Target"); ?><span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<select id="vl_monthly_target" name="vl_monthly_target" type="text" class="form-control readPage" title="<?php echo _translate('Please select lock approved sample'); ?>">
															<option value=""><?php echo _translate("--Select--"); ?></option>
															<option value="yes" <?php echo (isset($arr['vl_monthly_target']) && $arr['vl_monthly_target'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Enable"); ?></option>
															<option value="no" <?php echo (isset($arr['vl_monthly_target']) && $arr['vl_monthly_target'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("Disable"); ?></option>
														</select>
													</div>
												</div>
											</div>
										</div>
									<?php }
									if (isset($arr['vl_sample_expiry_after_days']) && $arr['vl_sample_expiry_after_days'] != '') { ?>
										<div class="row">
											<div class="col-md-12">
												<div class="form-group">
													<label for="vl_sample_expiry_after_days" class="col-lg-2 control-label"><?php echo _translate("Sample Expiry Days"); ?></label>
													<div class="col-lg-4">
														<input value="<?php echo $arr['vl_sample_expiry_after_days']; ?>" type="text" id="vl_sample_expiry_after_days" name="vl_sample_expiry_after_days" placeholder="<?php echo _translate('Enter the sample expiry days'); ?>" class="form-control readPage" title="<?php echo _translate('Please enter the sample expiry days'); ?>">
													</div>
												</div>
											</div>
										</div>
									<?php } ?>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="vl_sample_lock_after_days" class="col-lg-2 control-label"><?php echo _translate("Sample Lock Expiry Days"); ?></label>
												<div class="col-lg-4">
													<input value="<?php echo $arr['vl_sample_lock_after_days']; ?>" type="text" id="vl_sample_lock_after_days" name="vl_sample_lock_after_days" placeholder="<?php echo _translate('Enter the sample lock expiry days'); ?>" class="form-control readPage" title="<?php echo _translate('Please enter the sample lock expiry days'); ?>">
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="vl_auto_approve_api_results" class="col-lg-2 control-label"><?php echo _translate("VL Auto Approve API Results"); ?></label>
												<div class="col-lg-4">
													<select id="vl_auto_approve_api_results" name="vl_auto_approve_api_results" type="text" class="form-control readPage" title="<?php echo _translate('Please select VL Auto Approve API Results'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['vl_auto_approve_api_results']) && $arr['vl_auto_approve_api_results'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['vl_auto_approve_api_results']) && $arr['vl_auto_approve_api_results'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="vl_show_participant_name_in_manifest" class="col-lg-2 control-label"><?php echo _translate("VL Show Participant Name in Manifest"); ?></label>
												<div class="col-lg-4">
													<select id="vl_show_participant_name_in_manifest" name="vl_show_participant_name_in_manifest" type="text" class="form-control readPage" title="<?php echo _translate('Please select VL Participant Name in Manifest'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['vl_show_participant_name_in_manifest']) && $arr['vl_show_participant_name_in_manifest'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['vl_show_participant_name_in_manifest']) && $arr['vl_show_participant_name_in_manifest'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="vl_interpret_and_convert_results" class="col-lg-2 control-label"><?php echo _translate("Interpret and Convert VL Results"); ?></label>
												<div class="col-lg-4">
													<select id="vl_interpret_and_convert_results" name="vl_interpret_and_convert_results" type="text" class="form-control readPage" title="<?php echo _translate('Please select Interpret and Convert VL Results'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['vl_interpret_and_convert_results']) && $arr['vl_interpret_and_convert_results'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['vl_interpret_and_convert_results']) && $arr['vl_interpret_and_convert_results'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="vl_excel_export_format" class="col-lg-2 control-label"><?php echo _translate("Viral Load Export Format"); ?></label>
												<div class="col-lg-4">
													<select id="vl_excel_export_format" name="vl_excel_export_format" type="text" class="form-control readPage" title="<?php echo _translate('Please select Interpret and Convert VL Results'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="default" <?php echo (isset($arr['vl_excel_export_format']) && $arr['vl_excel_export_format'] == 'default') ? "selected='selected'" : ''; ?>><?php echo _translate("Default Format"); ?></option>
														<option value="cresar" <?php echo (isset($arr['vl_excel_export_format']) && $arr['vl_excel_export_format'] == 'cresar') ? "selected='selected'" : ''; ?>><?php echo _translate("CRESAR Format"); ?></option>
													</select>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						<?php }
						if (SYSTEM_CONFIG['modules']['eid']) { ?>
							<div class="panel panel-default">
								<div class="panel-heading">
									<h3 class="panel-title"><?php echo _translate("EID Settings"); ?></h3>
								</div>
								<div class="panel-body">
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="eid_sample_code" class="col-lg-2 control-label"><?php echo _translate("Sample ID"); ?><br><?php echo _translate("Format"); ?> <span class="mandatory">*</span> </label>
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
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the EID Sample ID Format'); ?>" id="eid_auto_generate_yy" name="eid_sample_code" value="YY" <?php echo ($arr['eid_sample_code'] == 'YY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixMMYY','prefixYY')">&nbsp;<input <?php echo $sPrefixYYDisplay; ?> type="text" class="eid_boxWidth eid_prefixYY readPage" id="eid_prefixYY" name="eid_sample_code_prefix" title="<?php echo _translate('Enter Prefix'); ?>" value="<?php echo $sPrefixYY; ?>" /> <?php echo _translate("YY"); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the EID Sample ID Format'); ?>" id="eid_auto_generate_mmyy" name="eid_sample_code" value="MMYY" <?php echo ($arr['eid_sample_code'] == 'MMYY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixYY','prefixMMYY')">&nbsp;<input <?php echo $sPrefixMMYYDisplay; ?> type="text" class="eid_boxWidth eid_prefixMMYY readPage" id="eid_prefixMMYY" name="eid_sample_code_prefix" title="<?php echo _translate('Enter Prefix'); ?>" value="<?php echo $sPrefixMMYY; ?>" /> <?php echo _translate("MMYY"); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the EID Sample ID Format'); ?>" id="eid_auto_generate" name="eid_sample_code" value="auto" <?php echo ($arr['eid_sample_code'] == 'auto') ? 'checked' : ''; ?>><span id="eid_auto1"><?php echo ($arr['vl_form'] == COUNTRY\PNG) ? ' Auto 1' : ' Auto'; ?> </span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the EID Sample ID Format'); ?>" id="eid_auto_generate2" name="eid_sample_code" value="auto2" <?php echo ($arr['eid_sample_code'] == 'auto2' && $arr['vl_form'] == COUNTRY\PNG) ? 'checked' : ''; ?> style="display:<?php echo ($arr['vl_form'] == COUNTRY\PNG) ? '' : 'none'; ?>"><span id="eid_auto2" style="display:<?php echo ($arr['vl_form'] == COUNTRY\PNG) ? '' : 'none'; ?>"> <?php echo _translate("Auto"); ?> 2 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the EID Sample ID Format'); ?>" id="eid_numeric" name="eid_sample_code" value="numeric" <?php echo ($arr['eid_sample_code'] == 'numeric') ? 'checked' : ''; ?>> <?php echo _translate("Numeric"); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the EID Sample ID Format'); ?>" id="eid_alpha_numeric" name="eid_sample_code" value="alphanumeric" <?php echo ($arr['eid_sample_code'] == 'alphanumeric') ? 'checked' : ''; ?>> <?php echo _translate("Alpha Numeric"); ?>
												</div>
											</div>
										</div>
									</div>

									<div id="eid_auto-sample-eg" class="row" style="display:<?php echo ($arr['eid_sample_code'] == 'auto' || $arr['eid_sample_code'] == 'auto2' || 'MMYY' || 'YY') ? 'block' : 'none'; ?>;">
										<div class="col-md-12" style="text-align:center;">
											<code id="eid_auto-sample-code" class="eid_autoSample" style="display:<?php echo ($arr['eid_sample_code'] == 'auto') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Province Code+Year+Month+Date+Increment Counter"); ?>
											</code>
											<code id="eid_auto-sample-code2" class="eid_autoSample" style="display:<?php echo ($arr['eid_sample_code'] == 'auto2') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. R+Year+Province Code+EID+Increment Counter (R18NCDEID0001)"); ?>
											</code>
											<code id="eid_auto-sample-code-MMYY" class="eid_autoSample" style="display:<?php echo ($arr['eid_sample_code'] == 'MMYY') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Prefix+Month+Year+Increment Counter (EID0517999)"); ?>
											</code>
											<code id="eid_auto-sample-code-YY" class="eid_autoSample" style="display:<?php echo ($arr['eid_sample_code'] == 'YY') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Prefix+Year+Increment Counter (EID17999)"); ?>
											</code>
										</div>
									</div><br />
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="eid_min_length" class="col-lg-2 control-label"><?php echo _translate("Minimum Sample ID"); ?> <?php echo _translate("Length"); ?> <span class="mandatory " style="display:<?php echo ($arr['eid_sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-10">
													<input type="text" class="form-control forceNumeric readPage isNumeric <?php echo ($arr['eid_sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="eid_min_length" name="eid_min_length" <?php echo ($arr['eid_sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter sample id min length'); ?>" value="<?php echo ($arr['eid_sample_code'] == 'auto') ? '' : $arr['min_length']; ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="eid_max_length" class="col-lg-2 control-label"><?php echo _translate("Maximum Sample ID Length"); ?> <span class="mandatory " style="display:<?php echo ($arr['eid_sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-10">
													<input type="text" class="form-control readPage forceNumeric isNumeric <?php echo ($arr['eid_sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="eid_max_length" name="eid_max_length" <?php echo ($arr['eid_sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="<?php echo _translate('Max'); ?>" title="<?php echo _translate('Please enter sample id max length'); ?>" value="<?php echo ($arr['eid_sample_code'] == 'auto') ? '' : $arr['max_length']; ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="eid_min_patient_id_length" class="col-lg-2 control-label"><?php echo _translate("Minimum Patient ID Length"); ?></label>
												<div class="col-lg-10">
													<input type="text" class="form-control forceNumeric isNumeric" id="eid_min_patient_id_length" name="eid_min_patient_id_length" placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter patient id min length'); ?>" value="<?php echo ($arr['eid_min_patient_id_length'] == '') ? '' : $arr['eid_min_patient_id_length']; ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
									</div>
									<?php if (isset($arr['lock_approved_eid_samples']) && $arr['lock_approved_eid_samples'] != '') { ?>
										<div class="row">
											<div class="col-md-12">
												<div class="form-group">
													<label for="lockApprovedEidSamples" class="col-lg-2 control-label"><?php echo _translate("Lock Approved EID Samples"); ?><span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<select id="lockApprovedEidSamples" name="lockApprovedEidSamples" type="text" class="form-control readPage" title="<?php echo _translate('Please select lock approved sample'); ?>">
															<option value=""><?php echo _translate("--Select--"); ?></option>
															<option value="yes" <?php echo (isset($arr['lock_approved_eid_samples']) && $arr['lock_approved_eid_samples'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
															<option value="no" <?php echo (isset($arr['lock_approved_eid_samples']) && $arr['lock_approved_eid_samples'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
														</select>
													</div>
												</div>
											</div>
										</div>
									<?php }
									if (isset($arr['eid_sample_expiry_after_days']) && $arr['eid_sample_expiry_after_days'] != '') { ?>
										<div class="row">
											<div class="col-md-12">
												<div class="form-group">
													<label for="eid_sample_expiry_after_days" class="col-lg-2 control-label"><?php echo _translate("Sample Expiry Days"); ?></label>
													<div class="col-lg-4">
														<input value="<?php echo $arr['eid_sample_expiry_after_days']; ?>" type="text" id="eid_sample_expiry_after_days" name="eid_sample_expiry_after_days" placeholder="<?php echo _translate('Enter the sample expiry days'); ?>" class="form-control readPage" title="<?php echo _translate('Please enter the sample expiry days'); ?>">
													</div>
												</div>
											</div>
										</div>
									<?php } ?>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="eid_sample_lock_after_days" class="col-lg-2 control-label"><?php echo _translate("Sample Lock Expiry Days"); ?></label>
												<div class="col-lg-4">
													<input value="<?php echo $arr['eid_sample_lock_after_days']; ?>" type="text" id="eid_sample_lock_after_days" name="eid_sample_lock_after_days" placeholder="<?php echo _translate('Enter the sample lock expiry days'); ?>" class="form-control readPage" title="<?php echo _translate('Please enter the sample lock expiry days'); ?>">
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="eid_auto_approve_api_results" class="col-lg-2 control-label"><?php echo _translate("EID Auto Approve API Results"); ?></label>
												<div class="col-lg-4">
													<select id="eid_auto_approve_api_results" name="eid_auto_approve_api_results" type="text" class="form-control readPage" title="<?php echo _translate('Please select EID Auto Approve API Results'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['eid_auto_approve_api_results']) && $arr['eid_auto_approve_api_results'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['eid_auto_approve_api_results']) && $arr['eid_auto_approve_api_results'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="eid_show_participant_name_in_manifest" class="col-lg-2 control-label"><?php echo _translate("EID Show Participant Name in Manifest"); ?></label>
												<div class="col-lg-4">
													<select id="eid_show_participant_name_in_manifest" name="eid_show_participant_name_in_manifest" type="text" class="form-control readPage" title="<?php echo _translate('Please select EID Participant Name in Manifest'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['eid_show_participant_name_in_manifest']) && $arr['eid_show_participant_name_in_manifest'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['eid_show_participant_name_in_manifest']) && $arr['eid_show_participant_name_in_manifest'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						<?php }
						if (SYSTEM_CONFIG['modules']['covid19']) { ?>
							<div class="panel panel-default">
								<div class="panel-heading">
									<h3 class="panel-title"><?php echo _translate("Covid-19 Settings"); ?></h3>
								</div>
								<div class="panel-body">
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<?php if (isset($arr['covid19_report_type']) && $arr['covid19_report_type'] != '') { ?>
													<label for="covid19ReportType" class="col-lg-2 control-label"><?php echo _translate("Covid-19 Excel Export Report Format"); ?><span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<select name="covid19ReportType" id="covid19ReportType" class="form-control isRequired readPage" title="<?php echo _translate('Please select covid19 report type'); ?>">
															<option value=""><?php echo _translate("-- Select --"); ?></option>
															<option value='who' <?php echo (empty($arr['covid19_report_type']) || $arr['covid19_report_type'] == 'standard') ? "selected='selected'" : ""; ?>> <?php echo _translate("Standard"); ?> </option>
															<option value='rwanda' <?php echo ($arr['covid19_report_type'] == 'rwanda') ? "selected='selected'" : ""; ?>> <?php echo _translate("Rwanda"); ?> </option>
															<option value='drc' <?php echo ($arr['covid19_report_type'] == 'drc') ? "selected='selected'" : ""; ?>> <?php echo _translate("DRC"); ?> </option>
														</select>
													</div>
												<?php }
												if (isset($arr['covid19_positive_confirmatory_tests_required_by_central_lab']) && $arr['covid19_positive_confirmatory_tests_required_by_central_lab'] != '') { ?>
													<label for="covid19PositiveConfirmatoryTestsRequiredByCentralLab" class="col-lg-2 control-label"><?php echo _translate("Covid-19 Positive Confirmatory Tests Required"); ?><span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<select name="covid19PositiveConfirmatoryTestsRequiredByCentralLab" id="covid19PositiveConfirmatoryTestsRequiredByCentralLab" class="form-control readPage isRequired" title="<?php echo _translate('Please select covid19 report type'); ?>">
															<option value=""><?php echo _translate("-- Select --"); ?></option>
															<option value='yes' <?php echo ($arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'yes') ? "selected='selected'" : ""; ?>> <?php echo _translate("Yes"); ?> </option>
															<option value='no' <?php echo ($arr['covid19_positive_confirmatory_tests_required_by_central_lab'] == 'no') ? "selected='selected'" : ""; ?>> <?php echo _translate("No"); ?> </option>
														</select>
													</div>
												<?php } ?>
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="covid19_sample_code" class="col-lg-2 control-label"><?php echo _translate("Sample ID"); ?><br><?php echo _translate("Format"); ?> <span class="mandatory">*</span> </label>
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
													<input type="radio" class="isRequired readPage" title="Please select the Covid-19 Sample ID Format" id="covid19_auto_generate_yy" name="covid19_sample_code" value="YY" <?php echo ($arr['covid19_sample_code'] == 'YY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixMMYY','prefixYY')">&nbsp;<input <?php echo $sPrefixYYDisplay; ?> type="text" class="covid19_boxWidth covid19_prefixYY readPage" id="covid19_prefixYY" name="covid19_sample_code_prefix" title="Enter Prefix" value="<?php echo $sPrefixYY; ?>" /> YY&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="Please select the Covid-19 Sample ID Format" id="covid19_auto_generate_mmyy" name="covid19_sample_code" value="MMYY" <?php echo ($arr['covid19_sample_code'] == 'MMYY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixYY','prefixMMYY')">&nbsp;<input <?php echo $sPrefixMMYYDisplay; ?> type="text" class="covid19_boxWidth covid19_prefixMMYY readPage" id="covid19_prefixMMYY" name="covid19_sample_code_prefix" title="Enter Prefix" value="<?php echo $sPrefixMMYY; ?>" /> MMYY&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="Please select the Covid-19 Sample ID Format" id="covid19_auto_generate" name="covid19_sample_code" value="auto" <?php echo ($arr['covid19_sample_code'] == 'auto') ? 'checked' : ''; ?>><span id="covid19_auto1"><?php echo ($arr['vl_form'] == COUNTRY\PNG) ? ' Auto 1' : ' Auto'; ?> </span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="Please select the Covid-19 Sample ID Format" id="covid19_auto_generate2" name="covid19_sample_code" value="auto2" <?php echo ($arr['covid19_sample_code'] == 'auto2' && $arr['vl_form'] == COUNTRY\PNG) ? 'checked' : ''; ?> style="display:<?php echo ($arr['vl_form'] == COUNTRY\PNG) ? '' : 'none'; ?>"><span id="covid19_auto2" style="display:<?php echo ($arr['vl_form'] == COUNTRY\PNG) ? '' : 'none'; ?>"> Auto 2 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
													<input type="radio" class="isRequired readPage" title="Please select the Covid-19 Sample ID Format" id="covid19_numeric" name="covid19_sample_code" value="numeric" <?php echo ($arr['covid19_sample_code'] == 'numeric') ? 'checked' : ''; ?>> Numeric&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="Please select the Covid-19 Sample ID Format" id="covid19_alpha_numeric" name="covid19_sample_code" value="alphanumeric" <?php echo ($arr['covid19_sample_code'] == 'alphanumeric') ? 'checked' : ''; ?>> Alpha Numeric
												</div>
											</div>
										</div>
									</div>

									<div id="covid19_auto-sample-eg" class="row" style="display:<?php echo ($arr['covid19_sample_code'] == 'auto' || $arr['covid19_sample_code'] == 'auto2' || 'MMYY' || 'YY') ? 'block' : 'none'; ?>;">
										<div class="col-md-12" style="text-align:center;">
											<code id="covid19_auto-sample-code" class="covid19_autoSample" style="display:<?php echo ($arr['covid19_sample_code'] == 'auto') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Province Code+Year+Month+Date+Increment Counter"); ?>
											</code>
											<code id="covid19_auto-sample-code2" class="covid19_autoSample" style="display:<?php echo ($arr['covid19_sample_code'] == 'auto2') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. R+Year+Province Code+covid19+Increment Counter (R18NCDC190001)"); ?>
											</code>
											<code id="covid19_auto-sample-code-MMYY" class="covid19_autoSample" style="display:<?php echo ($arr['covid19_sample_code'] == 'MMYY') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Prefix+Month+Year+Increment Counter (C190517999)"); ?>
											</code>
											<code id="covid19_auto-sample-code-YY" class="covid19_autoSample" style="display:<?php echo ($arr['covid19_sample_code'] == 'YY') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Prefix+Year+Increment Counter (C1917999)"); ?>
											</code>
										</div>
									</div><br />

									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="covid19_min_length" class="col-lg-2 control-label"><?php echo _translate("Minimum Sample ID Length"); ?> <span class="mandatory " style="display:<?php echo ($arr['covid19_sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-4">
													<input type="text" class="form-control readPage forceNumeric isNumeric <?php echo ($arr['covid19_sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="covid19_min_length" name="covid19_min_length" <?php echo ($arr['covid19_sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter sample id min length'); ?>" value="<?php echo ($arr['covid19_sample_code'] == 'auto') ? '' : $arr['min_length']; ?>" />
												</div>
												<label for="covid19_max_length" class="col-lg-2 control-label"><?php echo _translate("Maximum Sample ID Length"); ?> <span class="mandatory " style="display:<?php echo ($arr['covid19_sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-4">
													<input type="text" class="form-control readPage forceNumeric isNumeric <?php echo ($arr['covid19_sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="covid19_max_length" name="covid19_max_length" <?php echo ($arr['covid19_sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="<?php echo _translate('Max'); ?>" title="<?php echo _translate('Please enter sample id max length'); ?>" value="<?php echo ($arr['covid19_sample_code'] == 'auto') ? '' : $arr['max_length']; ?>" />
												</div>
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="covid19_min_patient_id_length" class="col-lg-2 control-label"><?php echo _translate("Minimum Patient ID Length"); ?></label>
												<div class="col-lg-10">
													<input type="text" class="form-control forceNumeric isNumeric" id="covid19_min_patient_id_length" name="covid19_min_patient_id_length" placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter patient id min length'); ?>" value="<?php echo ($arr['covid19_min_patient_id_length'] == '') ? '' : $arr['covid19_min_patient_id_length']; ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
									</div>

									<?php if (isset($arr['covid19_tests_table_in_results_pdf']) && $arr['covid19_tests_table_in_results_pdf'] != '') { ?>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
													<label for="covid19TestsTableInResultsPdf" class="col-lg-2 control-label"><?php echo _translate("Show Covid19 Tests table in Results PDF"); ?><span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<select name="covid19TestsTableInResultsPdf" id="covid19TestsTableInResultsPdf" class="form-control readPage isRequired" title="<?php echo _translate('Please select covid19 Tests method in Results Pdf'); ?>">
															<option value=""><?php echo _translate("-- Select --"); ?></option>
															<option value='yes' <?php echo ($arr['covid19_tests_table_in_results_pdf'] == 'yes') ? "selected='selected'" : ""; ?>> <?php echo _translate("Yes"); ?> </option>
															<option value='no' <?php echo ($arr['covid19_tests_table_in_results_pdf'] == 'no') ? "selected='selected'" : ""; ?>> <?php echo _translate("No"); ?> </option>
														</select>
													</div>
												</div>
											</div>
									</div>
									<?php } ?>
									<div class="row">
										<div class="col-md-12">
											<?php if (isset($arr['lock_approved_covid19_samples']) && $arr['lock_approved_covid19_samples'] != '') { ?>
												<div class="form-group">
													<label for="lockApprovedCovid19Samples" class="col-lg-2 control-label"><?php echo _translate("Lock Approved Covid19 Samples"); ?><span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<select id="lockApprovedCovid19Samples" name="lockApprovedCovid19Samples" type="text" class="form-control readPage" title="<?php echo _translate('Please select lock approved sample'); ?>">
															<option value=""><?php echo _translate("--Select--"); ?></option>
															<option value="yes" <?php echo (isset($arr['lock_approved_covid19_samples']) && $arr['lock_approved_covid19_samples'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
															<option value="no" <?php echo (isset($arr['lock_approved_covid19_samples']) && $arr['lock_approved_covid19_samples'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
														</select>
													</div>
												</div>
											<?php } ?>
										</div>
									</div>

									<?php if (isset($arr['covid19_report_qr_code']) && $arr['covid19_report_qr_code'] != '') { ?>
										<div class="row">
											<div class="col-md-12">
												<div class="form-group">
													<label for="covid19ReportQrCode" class="col-lg-2 control-label"><?php echo _translate("Covid-19 Report QR Code"); ?></label>
													<div class="col-lg-4">
														<select id="covid19ReportQrCode" name="covid19ReportQrCode" type="text" class="form-control readPage" title="<?php echo _translate('Please select report QR code yes/no'); ?>?">
															<option value=""><?php echo _translate("--Select--"); ?></option>
															<option value="yes" <?php echo (isset($arr['covid19_report_qr_code']) && $arr['covid19_report_qr_code'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
															<option value="no" <?php echo (isset($arr['covid19_report_qr_code']) && $arr['covid19_report_qr_code'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
														</select>
													</div>
												</div>
											</div>
										</div>
									<?php }
									if (isset($arr['covid19_sample_expiry_after_days']) && $arr['covid19_sample_expiry_after_days'] != '') { ?>
										<div class="row">
											<div class="col-md-12">
												<div class="form-group">
													<label for="covid19_sample_expiry_after_days" class="col-lg-2 control-label"><?php echo _translate("Sample Expiry Days"); ?></label>
													<div class="col-lg-4">
														<input value="<?php echo $arr['covid19_sample_expiry_after_days']; ?>" type="text" id="covid19_sample_expiry_after_days" name="covid19_sample_expiry_after_days" placeholder="<?php echo _translate('Enter the sample expiry days'); ?>" class="form-control readPage" title="<?php echo _translate('Please enter the sample expiry days'); ?>">
													</div>
												</div>
											</div>
										</div>
									<?php } ?>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="covid19_sample_lock_after_days" class="col-lg-2 control-label"><?php echo _translate("Sample Lock Expiry Days"); ?></label>
												<div class="col-lg-4">
													<input value="<?php echo $arr['covid19_sample_lock_after_days']; ?>" type="text" id="covid19_sample_lock_after_days" name="covid19_sample_lock_after_days" placeholder="<?php echo _translate('Enter the sample lock expiry days'); ?>" class="form-control readPage" title="<?php echo _translate('Please enter the sample lock expiry days'); ?>">
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="covid19_auto_approve_api_results" class="col-lg-2 control-label"><?php echo _translate("COVID-19 Auto Approve API Results"); ?></label>
												<div class="col-lg-4">
													<select id="covid19_auto_approve_api_results" name="covid19_auto_approve_api_results" type="text" class="form-control readPage" title="<?php echo _translate('Please select COVID-19 Auto Approve API Results'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['covid19_auto_approve_api_results']) && $arr['covid19_auto_approve_api_results'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['covid19_auto_approve_api_results']) && $arr['covid19_auto_approve_api_results'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="eid_show_participant_name_in_manifest" class="col-lg-2 control-label"><?php echo _translate("COVID-19 Show Participant Name in Manifest"); ?></label>
												<div class="col-lg-4">
													<select id="covid19_show_participant_name_in_manifest" name="covid19_show_participant_name_in_manifest" type="text" class="form-control readPage" title="<?php echo _translate('Please select COVID-19 Show Participant Name in Manifest'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['covid19_show_participant_name_in_manifest']) && $arr['covid19_show_participant_name_in_manifest'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['covid19_show_participant_name_in_manifest']) && $arr['covid19_show_participant_name_in_manifest'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						<?php }
						if (SYSTEM_CONFIG['modules']['hepatitis'] === true) { ?>
							<div class="panel panel-default">
								<div class="panel-heading">
									<h3 class="panel-title"><?php echo _translate("Hepatitis Settings"); ?></h3>
								</div>
								<div class="panel-body">
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="hepatitis_sample_code" class="col-lg-2 control-label"><?php echo _translate("Sample ID"); ?><br><?php echo _translate("Format"); ?> <span class="mandatory">*</span> </label>
												<div class="col-lg-10">
													<?php
													$sPrefixMMYY = 'C19';
													$sPrefixYY = '';
													$sPrefixMMYYDisplay = 'disabled="disabled"';
													$sPrefixYYDisplay = 'disabled="disabled"';
													if ($arr['hepatitis_sample_code'] == 'MMYY') {
														$sPrefixMMYY = $arr['hepatitis_sample_code_prefix'];
														$sPrefixMMYYDisplay = '';
													} else if ($arr['hepatitis_sample_code'] == 'YY') {
														$sPrefixYY = $arr['hepatitis_sample_code_prefix'];
														$sPrefixYYDisplay = '';
													}
													?>
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the Hepatitis Sample ID Format'); ?>" id="hepatitis_auto_generate_yy" name="hepatitis_sample_code" value="YY" <?php echo ($arr['hepatitis_sample_code'] == 'YY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixMMYY','prefixYY')">&nbsp;<input <?php echo $sPrefixYYDisplay; ?> type="text" class="hepatitis_boxWidth hepatitis_prefixYY" id="hepatitis_prefixYY" name="hepatitis_sample_code_prefix" title="<?php echo _translate('Enter Prefix'); ?>" value="<?php echo $sPrefixYY; ?>" /> <?php echo _translate("YY"); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the Hepatitis Sample ID Format'); ?>" id="hepatitis_auto_generate_mmyy" name="hepatitis_sample_code" value="MMYY" <?php echo ($arr['hepatitis_sample_code'] == 'MMYY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixYY','prefixMMYY')">&nbsp;<input <?php echo $sPrefixMMYYDisplay; ?> type="text" class="hepatitis_boxWidth hepatitis_prefixMMYY" id="hepatitis_prefixMMYY" name="hepatitis_sample_code_prefix" title="<?php echo _translate('Enter Prefix'); ?>" value="<?php echo $sPrefixMMYY; ?>" /> <?php echo _translate("MMYY"); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the Hepatitis Sample ID Format'); ?>" id="hepatitis_auto_generate" name="hepatitis_sample_code" value="auto" <?php echo ($arr['hepatitis_sample_code'] == 'auto') ? 'checked' : ''; ?>><span id="hepatitis_auto1"><?php echo ($arr['vl_form'] == COUNTRY\PNG) ? ' Auto 1' : ' Auto'; ?> </span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the Hepatitis Sample ID Format'); ?>" id="hepatitis_auto_generate2" name="hepatitis_sample_code" value="auto2" <?php echo ($arr['hepatitis_sample_code'] == 'auto2' && $arr['vl_form'] == COUNTRY\PNG) ? 'checked' : ''; ?> style="display:<?php echo ($arr['vl_form'] == COUNTRY\PNG) ? '' : 'none'; ?>"><span id="hepatitis_auto2" style="display:<?php echo ($arr['vl_form'] == COUNTRY\PNG) ? '' : 'none'; ?>"> <?php echo _translate("Auto"); ?> 2 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the Hepatitis Sample ID Format'); ?>" id="hepatitis_numeric" name="hepatitis_sample_code" value="numeric" <?php echo ($arr['hepatitis_sample_code'] == 'numeric') ? 'checked' : ''; ?>> <?php echo _translate("Numeric"); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the Hepatitis Sample ID Format'); ?>" id="hepatitis_alpha_numeric" name="hepatitis_sample_code" value="alphanumeric" <?php echo ($arr['hepatitis_sample_code'] == 'alphanumeric') ? 'checked' : ''; ?>> <?php echo _translate("Alpha Numeric"); ?>
												</div>
											</div>
										</div>
									</div>

									<div id="hepatitis_auto-sample-eg" class="row" style="display:<?php echo ($arr['hepatitis_sample_code'] == 'auto' || $arr['hepatitis_sample_code'] == 'auto2' || 'MMYY' || 'YY') ? 'block' : 'none'; ?>;">
										<div class="col-md-12" style="text-align:center;">
											<code id="hepatitis_auto-sample-code" class="hepatitis_autoSample" style="display:<?php echo ($arr['hepatitis_sample_code'] == 'auto') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Province Code+Year+Month+Date+Increment Counter"); ?>
											</code>
											<code id="hepatitis_auto-sample-code2" class="hepatitis_autoSample" style="display:<?php echo ($arr['hepatitis_sample_code'] == 'auto2') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. R+Year+Province Code+hepatitis+Increment Counter (R18NCDC190001)"); ?>
											</code>
											<code id="hepatitis_auto-sample-code-MMYY" class="hepatitis_autoSample" style="display:<?php echo ($arr['hepatitis_sample_code'] == 'MMYY') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Prefix+Month+Year+Increment Counter (C190517999)"); ?>
											</code>
											<code id="hepatitis_auto-sample-code-YY" class="hepatitis_autoSample" style="display:<?php echo ($arr['hepatitis_sample_code'] == 'YY') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Prefix+Year+Increment Counter (C1917999)"); ?>
											</code>
										</div>
									</div><br />

									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="hepatitis_min_length" class="col-lg-2 control-label"><?php echo _translate("Minimum Sample ID Length"); ?> <span class="mandatory " style="display:<?php echo ($arr['hepatitis_sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-4">
													<input type="text" class="form-control readPage forceNumeric isNumeric <?php echo ($arr['hepatitis_sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="hepatitis_min_length" name="hepatitis_min_length" <?php echo ($arr['hepatitis_sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter sample id min length'); ?>" value="<?php echo ($arr['hepatitis_sample_code'] == 'auto') ? '' : $arr['min_length']; ?>" />
												</div>
												<label for="hepatitis_max_length" class="col-lg-2 control-label"><?php echo _translate("Maximum Sample ID Length"); ?> <span class="mandatory " style="display:<?php echo ($arr['hepatitis_sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-4">
													<input type="text" class="form-control readPage forceNumeric isNumeric <?php echo ($arr['hepatitis_sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="hepatitis_max_length" name="hepatitis_max_length" <?php echo ($arr['hepatitis_sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="<?php echo _translate('Max'); ?>" title="<?php echo _translate('Please enter sample id max length'); ?>" value="<?php echo ($arr['hepatitis_sample_code'] == 'auto') ? '' : $arr['max_length']; ?>" />
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="hepatitis_min_patient_id_length" class="col-lg-2 control-label"><?php echo _translate("Minimum Patient ID Length"); ?></label>
												<div class="col-lg-10">
													<input type="text" class="form-control forceNumeric isNumeric" id="hepatitis_min_patient_id_length" name="hepatitis_min_patient_id_length" placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter patient id min length'); ?>" value="<?php echo ($arr['hepatitis_min_patient_id_length'] == '') ? '' : $arr['hepatitis_min_patient_id_length']; ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
									</div>
									<?php if (isset($arr['hepatitis_sample_expiry_after_days']) && $arr['hepatitis_sample_expiry_after_days'] != '') { ?>
										<div class="row">
											<div class="col-md-12">
												<div class="form-group">
													<label for="hepatitis_sample_expiry_after_days" class="col-lg-2 control-label"><?php echo _translate("Sample Expiry Days"); ?></label>
													<div class="col-lg-4">
														<input value="<?php echo $arr['hepatitis_sample_expiry_after_days']; ?>" type="text" id="hepatitis_sample_expiry_after_days" name="hepatitis_sample_expiry_after_days" placeholder="<?php echo _translate('Enter the sample expiry days'); ?>" class="form-control readPage" title="<?php echo _translate('Please enter the sample expiry days'); ?>">
													</div>
												</div>
											</div>
										</div>
									<?php } ?>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="hepatitis_sample_lock_after_days" class="col-lg-2 control-label"><?php echo _translate("Sample Lock Expiry Days"); ?></label>
												<div class="col-lg-4">
													<input value="<?php echo $arr['hepatitis_sample_lock_after_days']; ?>" type="text" id="hepatitis_sample_lock_after_days" name="hepatitis_sample_lock_after_days" placeholder="<?php echo _translate('Enter the sample lock expiry days'); ?>" class="form-control readPage" title="<?php echo _translate('Please enter the sample lock expiry days'); ?>">
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="hepatitis_auto_approve_api_results" class="col-lg-2 control-label"><?php echo _translate("Hepatitis Auto Approve API Results"); ?></label>
												<div class="col-lg-4">
													<select id="hepatitis_auto_approve_api_results" name="hepatitis_auto_approve_api_results" type="text" class="form-control readPage" title="<?php echo _translate('Please select Hepatitis Auto Approve API Results'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['hepatitis_auto_approve_api_results']) && $arr['hepatitis_auto_approve_api_results'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['hepatitis_auto_approve_api_results']) && $arr['hepatitis_auto_approve_api_results'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="hepatitis_show_participant_name_in_manifest" class="col-lg-2 control-label"><?php echo _translate("Hepatitis Show Participant Name in Manifest"); ?></label>
												<div class="col-lg-4">
													<select id="hepatitis_show_participant_name_in_manifest" name="hepatitis_show_participant_name_in_manifest" type="text" class="form-control readPage" title="<?php echo _translate('Please select Hepatitis Participant Name in Manifest'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['hepatitis_show_participant_name_in_manifest']) && $arr['hepatitis_show_participant_name_in_manifest'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['hepatitis_show_participant_name_in_manifest']) && $arr['hepatitis_show_participant_name_in_manifest'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						<?php }
						if (SYSTEM_CONFIG['modules']['tb']) { ?>
							<div class="panel panel-default">
								<div class="panel-heading">
									<h3 class="panel-title"><?php echo _translate("TB Settings"); ?></h3>
								</div>
								<div class="panel-body">
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="tb_sample_code" class="col-lg-2 control-label"><?php echo _translate("Sample ID"); ?><br><?php echo _translate("Format"); ?> <span class="mandatory">*</span> </label>
												<div class="col-lg-10">
													<?php
													$sPrefixMMYY = 'TB';
													$sPrefixYY = '';
													$sPrefixMMYYDisplay = 'disabled="disabled"';
													$sPrefixYYDisplay = 'disabled="disabled"';
													if ($arr['tb_sample_code'] == 'MMYY') {
														$sPrefixMMYY = $arr['tb_sample_code_prefix'];
														$sPrefixMMYYDisplay = '';
													} else if ($arr['tb_sample_code'] == 'YY') {
														$sPrefixYY = $arr['tb_sample_code_prefix'];
														$sPrefixYYDisplay = '';
													}
													?>
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the TB Sample ID Format'); ?>" id="tb_auto_generate_yy" name="tb_sample_code" value="YY" <?php echo ($arr['tb_sample_code'] == 'YY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixMMYY','prefixYY')">&nbsp;<input <?php echo $sPrefixYYDisplay; ?> type="text" class="tb_boxWidth tb_prefixYY readPage" id="tb_prefixYY" name="tb_sample_code_prefix" title="<?php echo _translate('Enter Prefix'); ?>" value="<?php echo $sPrefixYY; ?>" /> <?php echo _translate("YY"); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the TB Sample ID Format'); ?>" id="tb_auto_generate_mmyy" name="tb_sample_code" value="MMYY" <?php echo ($arr['tb_sample_code'] == 'MMYY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixYY','prefixMMYY')">&nbsp;<input <?php echo $sPrefixMMYYDisplay; ?> type="text" class="tb_boxWidth tb_prefixMMYY readPage" id="tb_prefixMMYY" name="tb_sample_code_prefix" title="<?php echo _translate('Enter Prefix'); ?>" value="<?php echo $sPrefixMMYY; ?>" /> <?php echo _translate("MMYY"); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the TB Sample ID Format'); ?>" id="tb_auto_generate" name="tb_sample_code" value="auto" <?php echo ($arr['tb_sample_code'] == 'auto') ? 'checked' : ''; ?>><span id="tb_auto1"><?php echo ($arr['vl_form'] == COUNTRY\PNG) ? ' Auto 1' : ' Auto'; ?> </span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the TB Sample ID Format'); ?>" id="tb_auto_generate2" name="tb_sample_code" value="auto2" <?php echo ($arr['tb_sample_code'] == 'auto2' && $arr['vl_form'] == COUNTRY\PNG) ? 'checked' : ''; ?> style="display:<?php echo ($arr['vl_form'] == COUNTRY\PNG) ? '' : 'none'; ?>"><span id="tb_auto2" style="display:<?php echo ($arr['vl_form'] == COUNTRY\PNG) ? '' : 'none'; ?>"> <?php echo _translate("Auto"); ?> 2 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the TB Sample ID Format'); ?>" id="tb_numeric" name="tb_sample_code" value="numeric" <?php echo ($arr['tb_sample_code'] == 'numeric') ? 'checked' : ''; ?>> <?php echo _translate("Numeric"); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the TB Sample ID Format'); ?>" id="tb_alpha_numeric" name="tb_sample_code" value="alphanumeric" <?php echo ($arr['tb_sample_code'] == 'alphanumeric') ? 'checked' : ''; ?>> <?php echo _translate("Alpha Numeric"); ?>
												</div>
											</div>
										</div>
									</div>

									<div id="tb_auto-sample-eg" class="row" style="display:<?php echo ($arr['tb_sample_code'] == 'auto' || $arr['tb_sample_code'] == 'auto2' || 'MMYY' || 'YY') ? 'block' : 'none'; ?>;">
										<div class="col-md-12" style="text-align:center;">
											<code id="tb_auto-sample-code" class="tb_autoSample" style="display:<?php echo ($arr['tb_sample_code'] == 'auto') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Province Code+Year+Month+Date+Increment Counter"); ?>
											</code>
											<code id="tb_auto-sample-code2" class="tb_autoSample" style="display:<?php echo ($arr['tb_sample_code'] == 'auto2') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. R+Year+Province Code+tb+Increment Counter (R18NCDC190001)"); ?>
											</code>
											<code id="tb_auto-sample-code-MMYY" class="tb_autoSample" style="display:<?php echo ($arr['tb_sample_code'] == 'MMYY') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Prefix+Month+Year+Increment Counter (C190517999)"); ?>
											</code>
											<code id="tb_auto-sample-code-YY" class="tb_autoSample" style="display:<?php echo ($arr['tb_sample_code'] == 'YY') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Prefix+Year+Increment Counter (C1917999)"); ?>
											</code>
										</div>
									</div><br />

									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="tb_min_length" class="col-lg-2 control-label"><?php echo _translate("Minimum Sample ID Length"); ?> <span class="mandatory " style="display:<?php echo ($arr['tb_sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-4">
													<input type="text" class="form-control readPage forceNumeric isNumeric <?php echo ($arr['tb_sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="tb_min_length" name="tb_min_length" <?php echo ($arr['tb_sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter sample id min length'); ?>" value="<?php echo ($arr['tb_sample_code'] == 'auto') ? '' : $arr['min_length']; ?>" />
												</div>
												<label for="tb_max_length" class="col-lg-2 control-label"><?php echo _translate("Maximum Sample ID Length"); ?> <span class="mandatory " style="display:<?php echo ($arr['tb_sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-4">
													<input type="text" class="form-control readPage forceNumeric isNumeric <?php echo ($arr['tb_sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="tb_max_length" name="tb_max_length" <?php echo ($arr['tb_sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="<?php echo _translate('Max'); ?>" title="<?php echo _translate('Please enter sample id max length'); ?>" value="<?php echo ($arr['tb_sample_code'] == 'auto') ? '' : $arr['max_length']; ?>" />
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="tb_min_patient_id_length" class="col-lg-2 control-label"><?php echo _translate("Minimum Patient ID Length"); ?></label>
												<div class="col-lg-10">
													<input type="text" class="form-control forceNumeric isNumeric" id="tb_min_patient_id_length" name="tb_min_patient_id_length" placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter patient id min length'); ?>" value="<?php echo ($arr['tb_min_patient_id_length'] == '') ? '' : $arr['tb_min_patient_id_length']; ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
									</div>
									<?php if (isset($arr['tb_sample_expiry_after_days']) && $arr['tb_sample_expiry_after_days'] != '') { ?>
										<div class="row">
											<div class="col-md-12">
												<div class="form-group">
													<label for="tb_sample_expiry_after_days" class="col-lg-2 control-label"><?php echo _translate("Sample Expiry Days"); ?></label>
													<div class="col-lg-4">
														<input value="<?php echo $arr['tb_sample_expiry_after_days']; ?>" type="text" id="tb_sample_expiry_after_days" name="tb_sample_expiry_after_days" placeholder="<?php echo _translate('Enter the sample expiry days'); ?>" class="form-control readPage" title="<?php echo _translate('Please enter the sample expiry days'); ?>">
													</div>
												</div>
											</div>
										</div>
									<?php } ?>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="tb_sample_lock_after_days" class="col-lg-2 control-label"><?php echo _translate("Sample Lock Expiry Days"); ?></label>
												<div class="col-lg-4">
													<input value="<?php echo $arr['tb_sample_lock_after_days']; ?>" type="text" id="tb_sample_lock_after_days" name="tb_sample_lock_after_days" placeholder="<?php echo _translate('Enter the sample lock expiry days'); ?>" class="form-control readPage" title="<?php echo _translate('Please enter the sample lock expiry days'); ?>">
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="tb_auto_approve_api_results" class="col-lg-2 control-label"><?php echo _translate("TB Auto Approve API Results"); ?></label>
												<div class="col-lg-4">
													<select id="tb_auto_approve_api_results" name="tb_auto_approve_api_results" type="text" class="form-control readPage" title="<?php echo _translate('Please select TB Auto Approve API Results'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['tb_auto_approve_api_results']) && $arr['tb_auto_approve_api_results'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['tb_auto_approve_api_results']) && $arr['tb_auto_approve_api_results'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="tb_show_participant_name_in_manifest" class="col-lg-2 control-label"><?php echo _translate("TB Show Participant Name in Manifest"); ?></label>
												<div class="col-lg-4">
													<select id="tb_show_participant_name_in_manifest" name="tb_show_participant_name_in_manifest" type="text" class="form-control readPage" title="<?php echo _translate('Please select TB Participant Name in Manifest'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['tb_show_participant_name_in_manifest']) && $arr['tb_show_participant_name_in_manifest'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['tb_show_participant_name_in_manifest']) && $arr['tb_show_participant_name_in_manifest'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						<?php }
						if (SYSTEM_CONFIG['modules']['cd4']) { ?>
							<div class="panel panel-default">
								<div class="panel-heading">
									<h3 class="panel-title"><?php echo _translate("CD4 Settings"); ?></h3>
								</div>
								<div class="panel-body">
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="cd4_sample_code" class="col-lg-2 control-label"><?php echo _translate("Sample ID"); ?><br><?php echo _translate("Format"); ?> <span class="mandatory">*</span> </label>
												<div class="col-lg-10">
													<?php
													$sPrefixMMYY = 'CD4';
													$sPrefixYY = '';
													$sPrefixMMYYDisplay = 'disabled="disabled"';
													$sPrefixYYDisplay = 'disabled="disabled"';
													if ($arr['cd4_sample_code'] == 'MMYY') {
														$sPrefixMMYY = $arr['cd4_sample_code_prefix'];
														$sPrefixMMYYDisplay = '';
													} else if ($arr['cd4_sample_code'] == 'YY') {
														$sPrefixYY = $arr['cd4_sample_code_prefix'];
														$sPrefixYYDisplay = '';
													}
													?>
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the CD4 Sample ID Format'); ?>" id="cd4_auto_generate_yy" name="cd4_sample_code" value="YY" <?php echo ($arr['cd4_sample_code'] == 'YY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixMMYY','prefixYY')">&nbsp;<input <?php echo $sPrefixYYDisplay; ?> type="text" class="cd4_boxWidth cd4_prefixYY readPage" id="cd4_prefixYY" name="cd4_sample_code_prefix" title="<?php echo _translate('Enter Prefix'); ?>" value="<?php echo $sPrefixYY; ?>" /> <?php echo _translate("YY"); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the CD4 Sample ID Format'); ?>" id="cd4_auto_generate_mmyy" name="cd4_sample_code" value="MMYY" <?php echo ($arr['cd4_sample_code'] == 'MMYY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixYY','prefixMMYY')">&nbsp;<input <?php echo $sPrefixMMYYDisplay; ?> type="text" class="cd4_boxWidth cd4_prefixMMYY readPage" id="cd4_prefixMMYY" name="cd4_sample_code_prefix" title="<?php echo _translate('Enter Prefix'); ?>" value="<?php echo $sPrefixMMYY; ?>" /> <?php echo _translate("MMYY"); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the CD4 Sample ID Format'); ?>" id="cd4_auto_generate" name="cd4_sample_code" value="auto" <?php echo ($arr['cd4_sample_code'] == 'auto') ? 'checked' : ''; ?>><span id="cd4_auto1"><?php echo ($arr['vl_form'] == COUNTRY\PNG) ? ' Auto 1' : ' Auto'; ?> </span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the CD4 Sample ID Format'); ?>" id="cd4_auto_generate2" name="cd4_sample_code" value="auto2" <?php echo ($arr['cd4_sample_code'] == 'auto2' && $arr['vl_form'] == COUNTRY\PNG) ? 'checked' : ''; ?> style="display:<?php echo ($arr['vl_form'] == COUNTRY\PNG) ? '' : 'none'; ?>"><span id="cd4_auto2" style="display:<?php echo ($arr['vl_form'] == COUNTRY\PNG) ? '' : 'none'; ?>"> <?php echo _translate("Auto"); ?> 2 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the CD4 Sample ID Format'); ?>" id="cd4_numeric" name="cd4_sample_code" value="numeric" <?php echo ($arr['cd4_sample_code'] == 'numeric') ? 'checked' : ''; ?>> <?php echo _translate("Numeric"); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the CD4 Sample ID Format'); ?>" id="cd4_alpha_numeric" name="cd4_sample_code" value="alphanumeric" <?php echo ($arr['cd4_sample_code'] == 'alphanumeric') ? 'checked' : ''; ?>> <?php echo _translate("Alpha Numeric"); ?>
												</div>
											</div>
										</div>
									</div>

									<div id="cd4_auto-sample-eg" class="row" style="display:<?php echo ($arr['cd4_sample_code'] == 'auto' || $arr['cd4_sample_code'] == 'auto2' || 'MMYY' || 'YY') ? 'block' : 'none'; ?>;">
										<div class="col-md-12" style="text-align:center;">
											<code id="cd4_auto-sample-code" class="cd4_autoSample" style="display:<?php echo ($arr['cd4_sample_code'] == 'auto') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Province Code+Year+Month+Date+Increment Counter"); ?>
											</code>
											<code id="cd4_auto-sample-code2" class="cd4_autoSample" style="display:<?php echo ($arr['cd4_sample_code'] == 'auto2') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. R+Year+Province Code+cd4+Increment Counter (R18NCDC190001)"); ?>
											</code>
											<code id="cd4_auto-sample-code-MMYY" class="cd4_autoSample" style="display:<?php echo ($arr['cd4_sample_code'] == 'MMYY') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Prefix+Month+Year+Increment Counter (C190517999)"); ?>
											</code>
											<code id="cd4_auto-sample-code-YY" class="cd4_autoSample" style="display:<?php echo ($arr['cd4_sample_code'] == 'YY') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Prefix+Year+Increment Counter (C1917999)"); ?>
											</code>
										</div>
									</div><br />

									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="cd4_min_length" class="col-lg-2 control-label"><?php echo _translate("Minimum Sample ID Length"); ?> <span class="mandatory " style="display:<?php echo ($arr['cd4_sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-4">
													<input type="text" class="form-control readPage forceNumeric isNumeric <?php echo ($arr['cd4_sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="cd4_min_length" name="cd4_min_length" <?php echo ($arr['cd4_sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter sample id min length'); ?>" value="<?php echo ($arr['cd4_sample_code'] == 'auto') ? '' : $arr['min_length']; ?>" />
												</div>
												<label for="cd4_max_length" class="col-lg-2 control-label"><?php echo _translate("Maximum Sample ID Length"); ?> <span class="mandatory " style="display:<?php echo ($arr['cd4_sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-4">
													<input type="text" class="form-control readPage forceNumeric isNumeric <?php echo ($arr['cd4_sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="cd4_max_length" name="cd4_max_length" <?php echo ($arr['cd4_sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="<?php echo _translate('Max'); ?>" title="<?php echo _translate('Please enter sample id max length'); ?>" value="<?php echo ($arr['cd4_sample_code'] == 'auto') ? '' : $arr['max_length']; ?>" />
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="cd4_min_patient_id_length" class="col-lg-2 control-label"><?php echo _translate("Minimum Patient ID Length"); ?></label>
												<div class="col-lg-10">
													<input type="text" class="form-control forceNumeric isNumeric" id="cd4_min_patient_id_length" name="cd4_min_patient_id_length" placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter patient id min length'); ?>" value="<?php echo ($arr['cd4_min_patient_id_length'] == '') ? '' : $arr['cd4_min_patient_id_length']; ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
									</div>
									<?php if (isset($arr['cd4_sample_expiry_after_days']) && $arr['cd4_sample_expiry_after_days'] != '') { ?>
										<div class="row">
											<div class="col-md-12">
												<div class="form-group">
													<label for="cd4_sample_expiry_after_days" class="col-lg-2 control-label"><?php echo _translate("Sample Expiry Days"); ?></label>
													<div class="col-lg-4">
														<input value="<?php echo $arr['cd4_sample_expiry_after_days']; ?>" type="text" id="cd4_sample_expiry_after_days" name="cd4_sample_expiry_after_days" placeholder="<?php echo _translate('Enter the sample expiry days'); ?>" class="form-control readPage" title="<?php echo _translate('Please enter the sample expiry days'); ?>">
													</div>
												</div>
											</div>
										</div>
									<?php } ?>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="cd4_sample_lock_after_days" class="col-lg-2 control-label"><?php echo _translate("Sample Lock Expiry Days"); ?></label>
												<div class="col-lg-4">
													<input value="<?php echo $arr['cd4_sample_lock_after_days']; ?>" type="text" id="cd4_sample_lock_after_days" name="cd4_sample_lock_after_days" placeholder="<?php echo _translate('Enter the sample lock expiry days'); ?>" class="form-control readPage" title="<?php echo _translate('Please enter the sample lock expiry days'); ?>">
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="cd4_auto_approve_api_results" class="col-lg-2 control-label"><?php echo _translate("cd4 Auto Approve API Results"); ?></label>
												<div class="col-lg-4">
													<select id="cd4_auto_approve_api_results" name="cd4_auto_approve_api_results" type="text" class="form-control readPage" title="<?php echo _translate('Please select cd4 Auto Approve API Results'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['cd4_auto_approve_api_results']) && $arr['cd4_auto_approve_api_results'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['cd4_auto_approve_api_results']) && $arr['cd4_auto_approve_api_results'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="cd4_show_participant_name_in_manifest" class="col-lg-2 control-label"><?php echo _translate("cd4 Show Participant Name in Manifest"); ?></label>
												<div class="col-lg-4">
													<select id="cd4_show_participant_name_in_manifest" name="cd4_show_participant_name_in_manifest" type="text" class="form-control readPage" title="<?php echo _translate('Please select cd4 Participant Name in Manifest'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['cd4_show_participant_name_in_manifest']) && $arr['cd4_show_participant_name_in_manifest'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['cd4_show_participant_name_in_manifest']) && $arr['cd4_show_participant_name_in_manifest'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						<?php }
						if (SYSTEM_CONFIG['modules']['generic-tests']) { ?>
							<div class="panel panel-default">
								<div class="panel-heading">
									<h3 class="panel-title"><?php echo _translate("Other Lab Tests Settings"); ?></h3>
								</div>
								<div class="panel-body">
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="generic_sample_code" class="col-lg-2 control-label"><?php echo _translate("Sample ID"); ?><br><?php echo _translate("Format"); ?> <span class="mandatory">*</span> </label>
												<div class="col-lg-10">
													<?php
													$sPrefixMMYY = 'LAB';
													$sPrefixYY = '';
													$sPrefixMMYYDisplay = 'disabled="disabled"';
													$sPrefixYYDisplay = 'disabled="disabled"';
													if ($arr['generic_sample_code'] == 'MMYY') {
														$sPrefixMMYY = $arr['generic_sample_code_prefix'] ?? 'T';
														$sPrefixMMYYDisplay = '';
													} elseif ($arr['generic_sample_code'] == 'YY') {
														$sPrefixYY = $arr['generic_sample_code_prefix'] ?? 'T';
														$sPrefixYYDisplay = '';
													}
													?>
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the Generic Test Sample ID Format'); ?>" id="generic_auto_generate_yy" name="generic_sample_code" value="YY" <?php echo ($arr['generic_sample_code'] == 'YY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixMMYY','prefixYY')">&nbsp;<input <?php echo $sPrefixYYDisplay; ?> type="text" class="generic_boxWidth generic_prefixYY readPage" id="generic_prefixYY" name="generic_sample_code_prefix" title="<?php echo _translate('Enter Prefix'); ?>" value="<?php echo $sPrefixYY; ?>" /> <?php echo _translate("YY"); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the Generic Test Sample ID Format'); ?>" id="generic_auto_generate_mmyy" name="generic_sample_code" value="MMYY" <?php echo ($arr['generic_sample_code'] == 'MMYY') ? 'checked' : ''; ?> onclick="makeReadonly('prefixYY','prefixMMYY')">&nbsp;<input <?php echo $sPrefixMMYYDisplay; ?> type="text" class="generic_boxWidth generic_prefixMMYY readPage" id="generic_prefixMMYY" name="generic_sample_code_prefix" title="<?php echo _translate('Enter Prefix'); ?>" value="<?php echo $sPrefixMMYY; ?>" /> <?php echo _translate("MMYY"); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the Generic Test Sample ID Format'); ?>" id="generic_auto_generate" name="generic_sample_code" value="auto" <?php echo ($arr['generic_sample_code'] == 'auto') ? 'checked' : ''; ?>><span id="generic_auto1"><?php echo ($arr['vl_form'] == COUNTRY\PNG) ? ' Auto 1' : ' Auto'; ?> </span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the Generic Test Sample ID Format'); ?>" id="generic_auto_generate2" name="generic_sample_code" value="auto2" <?php echo ($arr['generic_sample_code'] == 'auto2' && $arr['vl_form'] == COUNTRY\PNG) ? 'checked' : ''; ?> style="display:<?php echo ($arr['vl_form'] == COUNTRY\PNG) ? '' : 'none'; ?>"><span id="generic_auto2" style="display:<?php echo ($arr['vl_form'] == COUNTRY\PNG) ? '' : 'none'; ?>"> <?php echo _translate("Auto"); ?> 2 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the Generic Test Sample ID Format'); ?>" id="generic_numeric" name="generic_sample_code" value="numeric" <?php echo ($arr['generic_sample_code'] == 'numeric') ? 'checked' : ''; ?>> <?php echo _translate("Numeric"); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
													<input type="radio" class="isRequired readPage" title="<?php echo _translate('Please select the Generic Test Sample ID Format'); ?>" id="generic_alpha_numeric" name="generic_sample_code" value="alphanumeric" <?php echo ($arr['generic_sample_code'] == 'alphanumeric') ? 'checked' : ''; ?>> <?php echo _translate("Alpha Numeric"); ?>
												</div>
											</div>
										</div>
									</div>

									<div id="generic_auto-sample-eg" class="row" style="display:<?php echo ($arr['generic_sample_code'] == 'auto' || $arr['generic_sample_code'] == 'auto2' || 'MMYY' || 'YY') ? 'block' : 'none'; ?>;">
										<div class="col-md-12" style="text-align:center;">
											<code id="generic_auto-sample-code" class="generic_autoSample" style="display:<?php echo ($arr['generic_sample_code'] == 'auto') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Province Code+Year+Month+Date+Increment Counter"); ?>
											</code>
											<code id="generic_auto-sample-code2" class="generic_autoSample" style="display:<?php echo ($arr['generic_sample_code'] == 'auto2') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. R+Year+Province Code+tb+Increment Counter (R18NCDLAB0001)"); ?>
											</code>
											<code id="generic_auto-sample-code-MMYY" class="generic_autoSample" style="display:<?php echo ($arr['generic_sample_code'] == 'MMYY') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Prefix+Month+Year+Increment Counter (LAB0517999)"); ?>
											</code>
											<code id="generic_auto-sample-code-YY" class="generic_autoSample" style="display:<?php echo ($arr['generic_sample_code'] == 'YY') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Prefix+Year+Increment Counter (LAB17999)"); ?>
											</code>
										</div>
									</div><br />

									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="generic_min_length" class="col-lg-2 control-label"><?php echo _translate("Minimum Sample ID Length"); ?> <span class="mandatory " style="display:<?php echo ($arr['generic_sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-4">
													<input type="text" class="form-control readPage forceNumeric isNumeric <?php echo ($arr['generic_sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="generic_min_length" name="generic_min_length" <?php echo ($arr['generic_sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter sample id min length'); ?>" value="<?php echo ($arr['generic_sample_code'] == 'auto') ? '' : $arr['generic_min_length']; ?>" />
												</div>
												<label for="generic_max_length" class="col-lg-2 control-label"><?php echo _translate("Maximum Sample ID Length"); ?> <span class="mandatory " style="display:<?php echo ($arr['generic_sample_code'] == 'auto') ? 'none' : 'block'; ?>">*</span></label>
												<div class="col-lg-4">
													<input type="text" class="form-control readPage forceNumeric isNumeric <?php echo ($arr['generic_sample_code'] == 'auto' || 'MMYY' || 'YY') ? '' : 'isRequired'; ?>" id="generic_max_length" name="generic_max_length" <?php echo ($arr['generic_sample_code'] == 'auto' || 'MMYY' || 'YY') ? 'readonly' : ''; ?> placeholder="<?php echo _translate('Max'); ?>" title="<?php echo _translate('Please enter sample id max length'); ?>" value="<?php echo ($arr['generic_sample_code'] == 'auto') ? '' : $arr['generic_max_length']; ?>" />
												</div>
											</div>
										</div>
									</div>
									<?php if (isset($arr['generic_tests_table_in_results_pdf']) && $arr['generic_tests_table_in_results_pdf'] != '') { ?>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
													<label for="covid19TestsTableInResultsPdf" class="col-lg-2 control-label"><?php echo _translate("Show Other Tests table in Results PDF"); ?><span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<select name="genericTestsTableInResultsPdf" id="genericTestsTableInResultsPdf" class="form-control readPage isRequired" title="<?php echo _translate('Please select Other Tests method in Results Pdf'); ?>">
															<option value=""><?php echo _translate("-- Select --"); ?></option>
															<option value='yes' <?php echo ($arr['generic_tests_table_in_results_pdf'] == 'yes') ? "selected='selected'" : ""; ?>> <?php echo _translate("Yes"); ?> </option>
															<option value='no' <?php echo ($arr['generic_tests_table_in_results_pdf'] == 'no') ? "selected='selected'" : ""; ?>> <?php echo _translate("No"); ?> </option>
														</select>
													</div>
												</div>
											</div>
									</div>
									<?php } ?>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="generic_min_patient_id_length" class="col-lg-2 control-label"><?php echo _translate("Minimum Patient ID Length"); ?></label>
												<div class="col-lg-10">
													<input type="text" class="form-control forceNumeric isNumeric" id="generic_min_patient_id_length" name="generic_min_patient_id_length" placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter patient id min length'); ?>" value="<?php echo ($arr['generic_min_patient_id_length'] == '') ? '' : $arr['generic_min_patient_id_length']; ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
									</div>
									<?php if (isset($arr['generic_sample_expiry_after_days']) && $arr['generic_sample_expiry_after_days'] != '') { ?>
										<div class="row">
											<div class="col-md-12">
												<div class="form-group">
													<label for="generic_sample_expiry_after_days" class="col-lg-2 control-label"><?php echo _translate("Sample Expiry Days"); ?></label>
													<div class="col-lg-4">
														<input value="<?php echo $arr['generic_sample_expiry_after_days']; ?>" type="text" id="generic_sample_expiry_after_days" name="generic_sample_expiry_after_days" placeholder="<?php echo _translate('Enter the sample expiry days'); ?>" class="form-control readPage" title="<?php echo _translate('Please enter the sample expiry days'); ?>">
													</div>
												</div>
											</div>
										</div>
									<?php } ?>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="generic_sample_lock_after_days" class="col-lg-2 control-label"><?php echo _translate("Sample Lock Expiry Days"); ?></label>
												<div class="col-lg-4">
													<input value="<?php echo $arr['generic_sample_lock_after_days']; ?>" type="text" id="generic_sample_lock_after_days" name="generic_sample_lock_after_days" placeholder="<?php echo _translate('Enter the sample lock expiry days'); ?>" class="form-control readPage" title="<?php echo _translate('Please enter the sample lock expiry days'); ?>">
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="generic_auto_approve_api_results" class="col-lg-2 control-label"><?php echo _translate("Auto Approve API Results"); ?></label>
												<div class="col-lg-4">
													<select id="generic_auto_approve_api_results" name="generic_auto_approve_api_results" type="text" class="form-control readPage" title="<?php echo _translate('Please select Other Lab Tests Auto Approve API Results'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['generic_auto_approve_api_results']) && $arr['generic_auto_approve_api_results'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['generic_auto_approve_api_results']) && $arr['generic_auto_approve_api_results'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
									</div>
									<div class="row">
										<div class="col-md-12">
											<div class="form-group">
												<label for="generic_show_participant_name_in_manifest" class="col-lg-2 control-label"><?php echo _translate("Other Lab Tests Show Participant Name in Manifest"); ?></label>
												<div class="col-lg-4">
													<select id="generic_show_participant_name_in_manifest" name="generic_show_participant_name_in_manifest" type="text" class="form-control readPage" title="<?php echo _translate('Please select Other Lab Tests Participant Name in Manifest'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['generic_show_participant_name_in_manifest']) && $arr['generic_show_participant_name_in_manifest'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['generic_show_participant_name_in_manifest']) && $arr['generic_show_participant_name_in_manifest'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
									</div>
								</div>
							</div>
						<?php } ?>
						<div class="panel panel-default">
							<div class="panel-heading">
								<h3 class="panel-title"><?php echo _translate("Mobile App Settings"); ?></h3>
							</div>
							<div class="panel-body">
								<div class="row">
									<div class="col-md-12">
										<div class="form-group">
											<label for="app_menu_name" class="col-lg-2 control-label"><?php echo _translate("Mobile APP Menu Name"); ?></label>
											<div class="col-lg-4">
												<input type="text" class="form-control readPage" id="app_menu_name" name="app_menu_name" placeholder="<?php echo _translate('Name'); ?>" title="<?php echo _translate('Please enter name'); ?>" value="<?php echo $arr['app_menu_name']; ?>" />
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
						<div class="panel panel-default">
							<div class="panel-heading">
								<h3 class="panel-title"><?php echo _translate("Connect"); ?></h3>
							</div>
							<div class="panel-body">
								<div class="row">
									<div class="col-md-7" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="vldashboard_url" class="col-lg-4 control-label"><?php echo _translate("National Dashboard URL"); ?></label>
											<div class="col-lg-8">
												<input type="text" class="form-control readPage" id="vldashboard_url" name="vldashboard_url" placeholder="https://dashboard.example.org" title="<?php echo _translate('Please enter the National Dashboard URL'); ?>" value="<?php echo $arr['vldashboard_url']; ?>" />
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>



						<div class="panel panel-default">
							<div class="panel-heading">
								<h3 class="panel-title"><?php echo _translate("Viral Load Result PDF Settings"); ?></h3>
							</div>
							<div class="panel-body">
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="show_smiley" class="col-lg-4 control-label"><?php echo _translate("Show Emoticon/Smiley"); ?> </label>
											<div class="col-lg-8">
												<input type="radio" class="readPage" id="show_smiley_yes" name="show_smiley" value="yes" <?php echo ($arr['show_smiley'] == 'yes') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("Yes"); ?>&nbsp;&nbsp;
												<input type="radio" class="readPage" id="show_smiley_no" name="show_smiley" value="no" <?php echo ($arr['show_smiley'] == 'no' || $arr['show_smiley'] == '') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("No"); ?>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="vl_display_log_result" class="col-lg-4 control-label"><?php echo _translate("Display VL Log Result"); ?> </label>
											<div class="col-lg-8">
												<input type="radio" class="readPage" id="vl_display_log_result_yes" name="vl_display_log_result" value="yes" <?php echo ($arr['vl_display_log_result'] == 'yes') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("Yes"); ?>&nbsp;&nbsp;
												<input type="radio" class="readPage" id="vl_display_log_result_no" name="vl_display_log_result" value="no" <?php echo ($arr['vl_display_log_result'] == 'no' || $arr['vl_display_log_result'] == '') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("No"); ?>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="h_vl_msg" class="col-lg-4 control-label"><?php echo _translate("High Viral Load Message"); ?> </label>
											<div class="col-lg-8">
												<textarea class="form-control readPage" id="h_vl_msg" name="h_vl_msg" placeholder="<?php echo _translate('High Viral Load message that will appear for results >= the VL threshold limit'); ?>" title="<?php echo _translate('Please enter high viral load message'); ?>" style="width:100%;min-height:80px;max-height:100px;"><?php echo $arr['h_vl_msg']; ?></textarea>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="l_vl_msg" class="col-lg-4 control-label"><?php echo _translate("Low Viral Load Message"); ?> </label>
											<div class="col-lg-8">
												<textarea class="form-control readPage" id="l_vl_msg" name="l_vl_msg" placeholder="<?php echo _translate('Low Viral Load message that will appear for results lesser than the VL threshold limit'); ?>" title="<?php echo _translate('Please enter low viral load message'); ?>" style="width:100%;min-height:80px;max-height:100px;"><?php echo $arr['l_vl_msg']; ?></textarea>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="patient_name_pdf" class="col-lg-4 control-label"><?php echo _translate("Patient Name Format"); ?></label>
											<div class="col-lg-8">
												<select class="form-control readPage" id="patient_name_pdf" name="patient_name_pdf" title="<?php echo _translate('Choose one option'); ?>" value="<?php echo $arr['patient_name_pdf']; ?>">
													<option value="flname" <?php echo ('flname' == $arr['patient_name_pdf']) ? "selected='selected'" : "" ?>><?php echo _translate("First Name"); ?> + <?php echo _translate("Last Name"); ?></option>
													<option value="fullname" <?php echo ('fullname' == $arr['patient_name_pdf']) ? "selected='selected'" : "" ?>><?php echo _translate("Full Name"); ?></option>
													<option value="hidename" <?php echo ('hidename' == $arr['patient_name_pdf']) ? "selected='selected'" : "" ?>><?php echo _translate("Hide Patient Name"); ?></option>
												</select>
											</div>
										</div>
									</div>
								</div>

								<!-- <div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="r_mandatory_fields" class="col-lg-4 control-label"><?php echo _translate("Mandatory Fields for COMPLETED Result PDF"); ?>: </label>
											<div class="col-lg-8">
												<div class="form-group">
													<div class="col-md-12">
														<div class="row">
															<div class="col-md-12" style="text-align:justify;">
																<code><?php echo _translate("If any of the selected fields are incomplete, the Result PDF appears with a"); ?> <strong><?php echo _translate("DRAFT"); ?></strong> <?php echo _translate("watermark. Leave right block blank (Deselect All) to disable this"); ?>.</code>
															</div>
														</div>
														<div style="width:100%;margin:10px auto;clear:both;">
															<a href="#" id="select-all-field" style="float:left;" class="btn btn-info btn-xs"><?php echo _translate("Select All"); ?>&nbsp;&nbsp;<em class="fa-solid fa-chevron-right"></em></a> <a href="#" id="deselect-all-field" style="float:right;" class="btn btn-danger btn-xs"><em class="fa-solid fa-chevron-left"></em>&nbsp;<?php echo _translate("Deselect All"); ?></a>
														</div><br /><br />
														<select id="r_mandatory_fields" name="r_mandatory_fields[]" multiple="multiple" class="search readPage">
															<option value="facility_code" <?php echo (in_array('facility_code', $mFieldArray)) ? 'selected="selected"' : ''; ?>><?php echo _translate("Facility Code"); ?></option>
															<option value="facility_state" <?php echo (in_array('facility_state', $mFieldArray)) ? 'selected="selected"' : ''; ?>><?php echo _translate("Facility Province"); ?></option>
															<option value="facility_district" <?php echo (in_array('facility_district', $mFieldArray)) ? 'selected="selected"' : ''; ?>><?php echo _translate("Facility District"); ?></option>
															<option value="facility_name" <?php echo (in_array('facility_name', $mFieldArray)) ? 'selected="selected"' : ''; ?>><?php echo _translate("Facility Name"); ?></option>
															<option value="sample_code" <?php echo (in_array('sample_code', $mFieldArray)) ? 'selected="selected"' : ''; ?>><?php echo _translate("Sample ID"); ?></option>
															<option value="sample_collection_date" <?php echo (in_array('sample_collection_date', $mFieldArray)) ? 'selected="selected"' : ''; ?>><?php echo _translate("Sample Collection Date"); ?></option>
															<option value="patient_art_no" <?php echo (in_array('patient_art_no', $mFieldArray)) ? 'selected="selected"' : ''; ?>><?php echo _translate("Patient ART No"); ?>.</option>
															<option value="sample_received_at_lab_datetime" <?php echo (in_array('sample_received_at_lab_datetime', $mFieldArray)) ? 'selected="selected"' : ''; ?>><?php echo _translate("Date Sample Received at Testing Lab"); ?></option>
															<option value="sample_tested_datetime" <?php echo (in_array('sample_tested_datetime', $mFieldArray)) ? 'selected="selected"' : ''; ?>><?php echo _translate("Sample Tested Date"); ?></option>
															<option value="sample_name" <?php echo (in_array('sample_name', $mFieldArray)) ? 'selected="selected"' : ''; ?>><?php echo _translate("Sample Type"); ?></option>
															<option value="vl_test_platform" <?php echo (in_array('vl_test_platform', $mFieldArray)) ? 'selected="selected"' : ''; ?>><?php echo _translate("VL Testing Platform"); ?></option>
															<option value="result" <?php echo (in_array('result', $mFieldArray)) ? 'selected="selected"' : ''; ?>><?php echo _translate("VL Result"); ?></option>
															<option value="approvedBy" <?php echo (in_array('approvedBy', $mFieldArray)) ? 'selected="selected"' : ''; ?>><?php echo _translate("Approved By"); ?></option>
														</select>

													</div>

												</div>

											</div>

										</div>

									</div>

								</div> -->

							</div>

						</div>
						<!-- /.box-body -->
						<div class="box-footer hideFooter">
							<input type="hidden" class="readPage" name="removedLogoImage" id="removedLogoImage" />
							<input type="hidden" class="readPage" name="removedInstanceLogoImage" id="removedInstanceLogoImage" />
							<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _translate("Submit"); ?></a>
							<a href="editGlobalConfig.php" class="btn btn-default"> <?php echo _translate("Cancel"); ?></a>
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


		$(".select2").select2();

		var editSet = '<?php if (isset($_GET['e'])) {
							echo htmlspecialchars((string) $_GET['e']);
						} ?>'
		// alert(editSet)
		if (editSet == 1) {} else {
			$(".readPage").prop('disabled', true);
			$(".hideFooter").css('display', 'none');
		}
		$('.search').multiSelect({
			selectableHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='<?php echo _translate("Enter Field Name", true); ?>'>",
			selectionHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='<?php echo _translate("Enter Field Name", true); ?>'>",
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

	$("input:radio[name=hepatitis_sample_code]").click(function() {
		if (this.value == 'MMYY' || this.value == 'YY') {
			$('#hepatitis_auto-sample-eg').show();
			$('.hepatitis_autoSample').hide();
			if (this.value == 'MMYY') {
				$('#hepatitis_auto-sample-code-MMYY').show();
			} else {
				$('#hepatitis_auto-sample-code-YY').show();
			}
			$('#hepatitis_min_length').val('');
			$('.hepatitis_minlth').hide();
			$('#hepatitis_min_length').removeClass('isRequired');
			$('#hepatitis_min_length').prop('readonly', true);
			$('#hepatitis_max_length').val('');
			$('.hepatitis_maxlth').hide();
			$('#hepatitis_max_length').removeClass('isRequired');
			$('#hepatitis_max_length').prop('readonly', true);
		} else if (this.value == 'auto') {
			$('.hepatitis_autoSample').hide();
			$('#hepatitis_auto-sample-eg').show();
			$('#hepatitis_auto-sample-code').show();
			$('#hepatitis_min_length').val('');
			$('.hepatitis_minlth').hide();
			$('#hepatitis_min_length').removeClass('isRequired');
			$('#min_length').prop('readonly', true);
			$('#hepatitis_max_length').val('');
			$('.hepatitis_maxlth').hide();
			$('#hepatitis_max_length').removeClass('isRequired');
			$('#hepatitis_max_length').prop('readonly', true);
			$('.hepatitis_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else if (this.value == 'auto2') {
			$('.hepatitis_autoSample').hide();
			$('#hepatitis_auto-sample-eg').show();
			$('#hepatitis_auto-sample-code2').show();
			$('#hepatitis_min_length').val('');
			$('.hepatitis_minlth').hide();
			$('#hepatitis_min_length').removeClass('isRequired');
			$('#hepatitis_min_length').prop('readonly', true);
			$('#hepatitis_max_length').val('');
			$('.hepatitis_maxlth').hide();
			$('#hepatitis_max_length').removeClass('isRequired');
			$('#hepatitis_max_length').prop('readonly', true);
			$('.hepatitis_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else {
			$('#hepatitis_auto-sample-eg').hide();
			$('.hepatitis_minlth').show();
			$('#hepatitis_min_length').addClass('isRequired');
			$('#hepatitis_min_length').prop('readonly', false);
			$('.hepatitis_maxlth').show();
			$('#hepatitis_max_length').addClass('isRequired');
			$('#hepatitis_max_length').prop('readonly', false);
			$('.hepatitis_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		}
	});

	$("input:radio[name=tb_sample_code]").click(function() {
		if (this.value == 'MMYY' || this.value == 'YY') {
			$('#tb_auto-sample-eg').show();
			$('.tb_autoSample').hide();
			if (this.value == 'MMYY') {
				$('#tb_auto-sample-code-MMYY').show();
			} else {
				$('#tb_auto-sample-code-YY').show();
			}
			$('#tb_min_length').val('');
			$('.tb_minlth').hide();
			$('#tb_min_length').removeClass('isRequired');
			$('#tb_min_length').prop('readonly', true);
			$('#tb_max_length').val('');
			$('.tb_maxlth').hide();
			$('#tb_max_length').removeClass('isRequired');
			$('#tb_max_length').prop('readonly', true);
		} else if (this.value == 'auto') {
			$('.tb_autoSample').hide();
			$('#tb_auto-sample-eg').show();
			$('#tb_auto-sample-code').show();
			$('#tb_min_length').val('');
			$('.tb_minlth').hide();
			$('#tb_min_length').removeClass('isRequired');
			$('#min_length').prop('readonly', true);
			$('#tb_max_length').val('');
			$('.tb_maxlth').hide();
			$('#tb_max_length').removeClass('isRequired');
			$('#tb_max_length').prop('readonly', true);
			$('.tb_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else if (this.value == 'auto2') {
			$('.tb_autoSample').hide();
			$('#tb_auto-sample-eg').show();
			$('#tb_auto-sample-code2').show();
			$('#tb_min_length').val('');
			$('.tb_minlth').hide();
			$('#tb_min_length').removeClass('isRequired');
			$('#tb_min_length').prop('readonly', true);
			$('#tb_max_length').val('');
			$('.tb_maxlth').hide();
			$('#tb_max_length').removeClass('isRequired');
			$('#tb_max_length').prop('readonly', true);
			$('.tb_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else {
			$('#tb_auto-sample-eg').hide();
			$('.tb_minlth').show();
			$('#tb_min_length').addClass('isRequired');
			$('#tb_min_length').prop('readonly', false);
			$('.tb_maxlth').show();
			$('#tb_max_length').addClass('isRequired');
			$('#tb_max_length').prop('readonly', false);
			$('.tb_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		}
	});

	$("input:radio[name=training_mode]").click(function() {
		if (this.value == 'yes') {
			$('#training_mode_text').show();
		} else {
			$('#training_mode_text').hide();
		}
	});
	$("input:radio[name=generic_sample_code]").click(function() {
		if (this.value == 'MMYY' || this.value == 'YY') {
			$('#generic_auto-sample-eg').show();
			$('.generic_autoSample').hide();
			if (this.value == 'MMYY') {
				$('#generic_auto-sample-code-MMYY').show();
			} else {
				$('#generic_auto-sample-code-YY').show();
			}
			$('#generic_min_length').val('');
			$('.generic_minlth').hide();
			$('#generic_min_length').removeClass('isRequired');
			$('#generic_min_length').prop('readonly', true);
			$('#generic_max_length').val('');
			$('.generic_maxlth').hide();
			$('#generic_max_length').removeClass('isRequired');
			$('#generic_max_length').prop('readonly', true);
		} else if (this.value == 'auto') {
			$('.generic_autoSample').hide();
			$('#generic_auto-sample-eg').show();
			$('#generic_auto-sample-code').show();
			$('#generic_min_length').val('');
			$('.generic_minlth').hide();
			$('#generic_min_length').removeClass('isRequired');
			$('#min_length').prop('readonly', true);
			$('#generic_max_length').val('');
			$('.generic_maxlth').hide();
			$('#generic_max_length').removeClass('isRequired');
			$('#generic_max_length').prop('readonly', true);
			$('.generic_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else if (this.value == 'auto2') {
			$('.generic_autoSample').hide();
			$('#generic_auto-sample-eg').show();
			$('#generic_auto-sample-code2').show();
			$('#generic_min_length').val('');
			$('.generic_minlth').hide();
			$('#generic_min_length').removeClass('isRequired');
			$('#generic_min_length').prop('readonly', true);
			$('#generic_max_length').val('');
			$('.generic_maxlth').hide();
			$('#generic_max_length').removeClass('isRequired');
			$('#generic_max_length').prop('readonly', true);
			$('.generic_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else {
			$('#generic_auto-sample-eg').hide();
			$('.generic_minlth').show();
			$('#generic_min_length').addClass('isRequired');
			$('#generic_min_length').prop('readonly', false);
			$('.generic_maxlth').show();
			$('#generic_max_length').addClass('isRequired');
			$('#generic_max_length').prop('readonly', false);
			$('.generic_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		}
	});

	function makeReadonly(id1, id2) {
		$("#" + id1).val('');
		$("#" + id1).attr("disabled", 'disabled').removeClass('isRequired');
		$("#" + id2).attr("disabled", false).addClass('isRequired');
	}

	function showExportFormat() {
		var formName = $("#vl_form").val();
		if (formName == 3) {

		} else {

		}
	}
</script>
<?php require_once APPLICATION_PATH . '/footer.php'; ?>
