<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Utilities\MiscUtility;

$title = _translate("System Configuration");

require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

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

$arr = MiscUtility::arrayEmptyStringsToNull($arr);

// Get locale directory list
$localeLists = $general->getLocaleList((int)($arr['vl_form'] ?? 0));

$facilityReportFormat = (array)json_decode($arr['report_format']);
$vlTestingLabs = $facilitiesService->getTestingLabs('vl');

?>
<!-- <link href="/assets/css/jasny-bootstrap.min.css" rel="stylesheet" /> -->
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
						<!-- <div class="col-sm-6 pull-right">
							<a href="javascript:void(0);" onclick="exportGeneralConfig();" class="btn btn-success pull-right"> <em class="fa-solid fa-file-excel"></em></em> <?php echo _translate("Export Config"); ?></a>
						</div> -->
						<div class="col-sm-6 pull-right">
							<a href="editGlobalConfig.php?e=1" class="btn btn-primary pull-right"> <em class="fa-solid fa-pen-to-square"></em></em> <?php echo _translate("Edit System Configuration"); ?></a>
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
									<div class="col-md-6">
										<div class="form-group">
											<label for="gui_date_format" class="col-lg-4 control-label"><?php echo _translate("Date Format"); ?> <span class="mandatory">*</span></label>
											<div class="col-lg-8">
												<select class="form-control isRequired readPage" name="gui_date_format" id="gui_date_format" title="<?php echo _translate('Please select the date format'); ?>">
													<option value="d-M-Y" <?php echo ('d-M-Y' == $arr['gui_date_format']) ? "selected='selected'" : "" ?>><?php echo date('d-M-Y') . " - DD-MMM-YYYY"; ?></option>
													<option value="d-m-Y" <?php echo ('d-m-Y' == $arr['gui_date_format']) ? "selected='selected'" : "" ?>><?php echo date('d-m-Y') . " - DD-MM-YYYY"; ?></option>
												</select>
											</div>
										</div>
									</div>
									<div class="col-md-6">
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
									<div class="col-md-6">
										<div class="form-group">
											<label for="vl_form" class="col-lg-4 control-label"><?php echo _translate("Country of Installation"); ?> <span class="mandatory">*</span> </label>
											<div class="col-lg-8">
												<select class="form-control isRequired readPage select2" name="vl_form" id="vl_form" title="<?php echo _translate('Please select the country of installation'); ?>" onchange="showExportFormat();">
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

									<div class="col-md-6">
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
									<div class="col-md-6">
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

									<div class="col-md-6">
										<div class="form-group">
											<label for="header" class="col-lg-4 control-label"><?php echo _translate("Header"); ?> </label>
											<div class="col-lg-8">
												<textarea class="form-control readPage" id="header" name="header" placeholder="<?php echo _translate('Header'); ?>" title="<?php echo _translate('Please enter header'); ?>" style="width:100%;min-height:80px;max-height:100px;"><?php echo $arr['header']; ?></textarea>
											</div>
										</div>
									</div>
								</div>

								<div class="row">
									<div class="col-md-6">
										<div class="form-group">
											<label for="" class="col-lg-4 control-label"><?php echo _translate("Logo Image"); ?> </label>
											<div class="col-lg-8">
												<div class="fileinput fileinput-new logo" data-provides="fileinput">
													<div class="fileinput-preview thumbnail" data-trigger="fileinput" style="width:200px; height:100px;">
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

									<div class="col-md-6">
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
									<div class="col-md-6">
										<div class="form-group">
											<label for="training_mode" class="col-lg-4 control-label"><?php echo _translate("Training Mode"); ?> </label>
											<div class="col-lg-8">
												<input type="radio" class="readPage" id="training_mode_yes" name="training_mode" value="yes" <?php echo ($arr['training_mode'] == 'yes') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("Yes"); ?>&nbsp;&nbsp;
												<input type="radio" class="readPage" id="training_mode_no" name="training_mode" value="no" <?php echo ($arr['training_mode'] == 'no') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("No"); ?>
												<input type="text" <?php echo ($arr['training_mode'] == 'yes') ? '' : 'style="display:none"'; ?> value="<?php echo $arr['training_mode_text']; ?>" id="training_mode_text" name="training_mode_text" class="form-control readPage" placeholder="Enter the training mode text" title="Please enter the training mode text here" />
											</div>
										</div>
									</div>

									<div class="col-md-6" style="height:38px;">
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
									<div class="col-md-6">
										<div class="form-group">
											<label for="auto_approval" class="col-lg-4 control-label"><?php echo _translate("Same user can Review and Approve"); ?> </label>
											<div class="col-lg-8">
												<br>
												<input type="radio" class="readPage" id="user_review_yes" name="user_review_approve" value="yes" <?php echo ($arr['user_review_approve'] == 'yes') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("Yes"); ?>&nbsp;&nbsp;
												<input type="radio" class="readPage" id="user_review_no" name="user_review_approve" value="no" <?php echo ($arr['user_review_approve'] == 'no') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("No"); ?>
											</div>
										</div>
									</div>
								</div><br />
								<div class="row">
									<div class="col-md-6">
										<div class="form-group">
											<label for="instance_type" class="col-lg-4 control-label"><?php echo _translate("Sample ID Barcode Label Printing"); ?> <span class="mandatory">*</span> </label>
											<div class="col-lg-8">
												<select class="form-control isRequired readPage" name="bar_code_printing" id="bar_code_printing" title="<?php echo _translate('Please select the barcode printing'); ?>" onchange="showBarcodeFormatMessage(this.value);">
													<option value="off" <?php echo ('off' == $arr['bar_code_printing']) ? "selected='selected'" : "" ?>><?php echo _translate("Off"); ?></option>
													<option value="zebra-printer" <?php echo ('zebra-printer' == $arr['bar_code_printing']) ? "selected='selected'" : "" ?>><?php echo _translate("Zebra Printer"); ?></option>
													<option value="dymo-labelwriter-450" <?php echo ('dymo-labelwriter-450' == $arr['bar_code_printing']) ? "selected='selected'" : "" ?>><?php echo _translate("Dymo LabelWriter 450"); ?></option>
												</select>
											</div>
										</div>
									</div>

									<div class="barcodeFormat">
										<div class="col-md-6">
											<div class="form-group contentDiv">

											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-6">
										<div class="form-group">
											<label for="import_non_matching_sample" class="col-lg-4 control-label"><?php echo _translate("Allow Samples not matching the System Sample IDs while importing results manually"); ?></label>
											<div class="col-lg-8">

												<input class="readPage" type="radio" id="import_non_matching_sample_yes" name="import_non_matching_sample" value="yes" <?php echo ($arr['import_non_matching_sample'] == 'yes') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("Yes"); ?>&nbsp;&nbsp;
												<input class="readPage" type="radio" id="import_non_matching_sample_no" name="import_non_matching_sample" value="no" <?php echo ($arr['import_non_matching_sample'] == 'no') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("No"); ?>

											</div>
										</div>
									</div>

									<div class="col-md-6" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="support_email" class="col-lg-4 control-label"><?php echo _translate("Support Email"); ?></label>
											<div class="col-lg-8">
												<input type="text" class="form-control readPage" id="support_email" name="support_email" placeholder="<?php echo _translate('eg. manager1@example.com, manager2@example.com'); ?>" title="<?php echo _translate('Please enter manager email'); ?>" value="<?php echo $arr['support_email']; ?>" />
											</div>
										</div>
									</div>

								</div>

								<div class="row">
									<div class="col-md-6" style="text-align:center;">
										<code><?php echo _translate("While importing results from CSV/Excel file, should we import results of Sample IDs that do not match the Sample IDs present in System database"); ?></code>
									</div>
									<div class="col-md-6" style="text-align:center;">
										<code><?php echo _translate("You can enter multiple emails by separating them with commas"); ?></code>
									</div>
								</div>
								<br>
								<br>
								<div class="row">
									<div class="col-md-6" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="default_csv_delimiter" class="col-lg-4 control-label"><?php echo _translate("CSV Delimiter"); ?></label>
											<div class="col-lg-8">
												<input type="text" class="form-control" id="default_csv_delimiter" name="default_csv_delimiter" style="max-width:60px;" title="<?php echo _translate('Please enter CSV delimiter'); ?>" value="<?php echo $arr['default_csv_delimiter']; ?>" />
											</div>
										</div>
									</div>
									<div class="col-md-6" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="default_csv_enclosure" class="col-lg-4 control-label"><?php echo _translate("CSV Enclosure"); ?></label>
											<div class="col-lg-8">
												<input type="text" class="form-control" id="default_csv_enclosure" name="default_csv_enclosure" style="max-width:60px;" title="<?php echo _translate('Please enter CSV enclosure'); ?>" value='<?php echo $arr['default_csv_enclosure']; ?>' />
											</div>
										</div>
									</div>
								</div>
								<div class="row">

									<div class="col-md-6" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="support_email" class="col-lg-4 control-label"><?php echo _translate("Default Phone Prefix"); ?></label>
											<div class="col-lg-8">
												<input type="text" class="form-control readPage" id="default_phone_prefix" name="default_phone_prefix" placeholder="<?php echo _translate('e.g. +232, +91'); ?>" title="<?php echo _translate('Please enter manager email'); ?>" value="<?php echo $arr['default_phone_prefix']; ?>" />
											</div>
										</div>
									</div>

									<div class="col-md-6" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="support_email" class="col-lg-4 control-label"><?php echo _translate("Minimum Length of Phone Number"); ?></label>
											<div class="col-lg-8">
												<input type="text" class="form-control forceNumeric readPage isNumeric" id="min_phone_length" name="min_phone_length" placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter minimun length of phone number'); ?>" value="<?php echo ($arr['min_phone_length'] == '') ? '' : $arr['min_phone_length']; ?>" style="max-width:60px;" />
											</div>
										</div>
									</div>

								</div>
								<div class="row">
									<div class="col-md-6" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="support_email" class="col-lg-4 control-label"><?php echo _translate("Maximum Length of Phone Number"); ?></label>
											<div class="col-lg-8">
												<input type="text" class="form-control forceNumeric readPage isNumeric" id="max_phone_length" name="max_phone_length" placeholder="<?php echo _translate('Max'); ?>" title="<?php echo _translate('Please enter maximum length of phone number'); ?>" value="<?php echo ($arr['max_phone_length'] == '') ? '' : $arr['max_phone_length']; ?>" style="max-width:60px;" />
											</div>
										</div>
									</div>
									<div class="col-md-6" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="batch_pdf_layout" class="col-lg-4 control-label"><?php echo _translate("Batch PDF Layout"); ?> </label>
											<div class="col-lg-8">
												<input type="radio" class="readPage" id="standard_batch_pdf_layout" name="batch_pdf_layout" value="standard" <?php echo ($arr['batch_pdf_layout'] == 'standard' || $arr['batch_pdf_layout'] == '') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("Standard"); ?>&nbsp;&nbsp;
												<input type="radio" class="readPage" id="compact_batch_pdf_layout" name="batch_pdf_layout" value="compact" <?php echo ($arr['batch_pdf_layout'] == 'compact') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("Compact"); ?>
											</div>
										</div>
									</div>


								</div>
								<div class="row">
									<div class="col-md-6" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="sample_expiry_after_days" class="col-lg-4 control-label"><?php echo _translate("Sample Expiry Days"); ?> <span class="mandatory">*</span> </label>
											<div class="col-lg-4">
												<input value="<?php echo $arr['sample_expiry_after_days']; ?>" type="text" id="sample_expiry_after_days" name="sample_expiry_after_days" placeholder="<?php echo _translate('Enter the sample expiry days'); ?>" class="form-control forceNumeric readPage isRequired" title="<?php echo _translate('Please enter the sample expiry days'); ?>" onblur="validateLessThanDays('expiry_days_error',90,'Expiry days cannot be less than 90 days')">
											</div>
											<div class="col-lg-4">
												<span id="expiry_days_error" class="text-danger"></span>
											</div>
										</div>
									</div>
									<div class="col-md-6" style="height:38px;">
										<div class="form-group" style="height:38px;">
											<label for="sample_lock_after_days" class="col-lg-4 control-label"><?php echo _translate("Sample Lock Days"); ?> <span class="mandatory">*</span> </label>
											<div class="col-lg-4">
												<input value="<?php echo $arr['sample_lock_after_days']; ?>" type="text" id="sample_lock_after_days" name="sample_lock_after_days" placeholder="<?php echo _translate('Enter the sample lock days'); ?>" class="form-control forceNumeric readPage isRequired" title="<?php echo _translate('Please enter the sample lock days'); ?>" onblur="validateLessThanDays('lock_days_error',7,'Lock days cannot be less than 7 days')">
											</div>
											<div class="col-lg-4">
												<span id="lock_days_error" class="text-danger"></span>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-12">
										<div class="row testTypeFileSection">
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
																$filePath = UPLOAD_PATH . DIRECTORY_SEPARATOR . "labs" . DIRECTORY_SEPARATOR . "report-template" . DIRECTORY_SEPARATOR . $test . DIRECTORY_SEPARATOR . $file->file;  ?>
																<tr>
																	<td>
																		<select class="form-control readPage testTypeFileSelect" name="reportFormat[test_type][<?php echo ($n - 1); ?>]" id="testTypeFile<?php echo $n; ?>" onchange="handleTestTypeChange()">
																			<option value=""><?php echo _translate("Select Test Type"); ?></option>
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
																				<a href="/uploads/labs/report-template/<?php echo $test; ?>/<?php echo $file->file; ?>" class="oldFile<?php echo $n; ?> readPage btn btn-sm btn-primary" title="View / Expend Current File" target="_blank" style="margin-left: -35px; "><i class="fa fa-eye"></i> View</a>
																				<a href="javascript:void(0);" class="btn readPage btn-warning oldFile<?php echo $n; ?>" title="Replace Report Layout" onclick="removeReport(<?php echo $n; ?>);">Replace</a>
																				<a href="javascript:void(0);" class="btn readPage btn-danger oldFile<?php echo $n; ?>" title="Delete Report Template" onclick="deleteReport(<?php echo $n; ?>);removeReport(<?php echo $n; ?>);"><i class="icon-trash"></i> Remove</a>
																				<input type="hidden" name="reportFormat[delete_template][<?php echo ($n - 1); ?>]" id="deleteTemplate<?php echo $n; ?>" />
																				<input type="hidden" value="<?php echo $file->file; ?>" name="reportFormat[old_template][<?php echo ($n - 1); ?>]" id="oldTemplate<?php echo $n; ?>" />
																			</div>
																		<?php } ?>
																		<input <?php echo (isset($file->file) && !empty($file->file) && file_exists($filePath)) ? 'style="display:none;"' : ''; ?> type="file" class="form-control readPage newFile<?php echo $n; ?>" name="reportFormat[report_template][]" id="reportTemplate<?php echo $n; ?>" accept=".pdf" title="<?php echo _translate('Please upload PDF file'); ?>">
																	</td>
																	<td>
																		<input value="<?php echo $file->mtop; ?>" type="text" class="form-control" name="reportFormat[header_margin][<?php echo ($n - 1); ?>]" id="headerMargin<?php echo $n; ?>" placeholder="<?php echo _translate('Top margin'); ?>" title="<?php echo _translate('Please enter the header margin'); ?>">
																	</td>
																	<td style="vertical-align:middle;text-align: center;">
																		<a class="btn btn-xs readPage btn-primary" href="javascript:void(0);" onclick="addTestTypeFileRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
																		<a class="btn btn-xs readPage btn-default" href="javascript:void(0);" onclick="removeTestTypeFileRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
																	</td>
																</tr>
															<?php $n += 1;
															}
														} else { ?>
															<tr>
																<td style="text-align: center;">
																	<select class="form-control readPage testTypeFileSelect" name="reportFormat[test_type][<?php echo ($n - 1); ?>]" id="testTypeFile<?php echo $n; ?>" onchange="handleTestTypeChange()">
																		<option value=""><?php echo _translate("Select Test Type"); ?></option>
																		<?php if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) { ?>
																			<option value="vl"><?php echo _translate("Viral Load"); ?></option>
																		<?php }
																		if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) { ?>
																			<option value="eid"><?php echo _translate("Early Infant Diagnosis"); ?></option>
																		<?php }
																		if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) { ?>
																			<option value="covid19"><?php echo _translate("Covid-19"); ?></option>
																		<?php }
																		if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) { ?>
																			<option value='hepatitis'><?php echo _translate("Hepatitis"); ?></option>
																		<?php }
																		if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) { ?>
																			<option value='tb'><?php echo _translate("TB"); ?></option>
																		<?php }
																		if (isset(SYSTEM_CONFIG['modules']['cd4']) && SYSTEM_CONFIG['modules']['cd4'] === true) { ?>
																			<option value='cd4'><?php echo _translate("CD4"); ?></option>
																		<?php }
																		if (isset(SYSTEM_CONFIG['modules']['generic-tests']) && SYSTEM_CONFIG['modules']['generic-tests'] === true) { ?>
																			<option value='generic-tests'><?php echo _translate("Other Lab Tests"); ?></option>
																		<?php } ?>
																	</select>
																</td>
																<td>
																	<input type="file" class="readPage form-control" name="reportFormat[report_template][]" id="reportTemplate1" accept=".pdf" title="<?php echo _translate('Please upload PDF file'); ?>">
																</td>
																<td>
																	<input type="text" class="readPage form-control" name="reportFormat[header_margin][]" id="headerMargin1" placeholder="<?php echo _translate('Top margin'); ?>" title="<?php echo _translate('Please enter the header margin'); ?>">
																</td>
																<td style="vertical-align:middle;text-align: center;">
																	<a class="btn btn-xs readPage btn-primary" href="javascript:void(0);" onclick="addTestTypeFileRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
																</td>
															</tr>
														<?php } ?>
													</tbody>
												</table>
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
												<label for="viral_load_threshold_limit" class="col-lg-2 control-label"><?php echo _translate("Viral Load Threshold Limit"); ?><span class="mandatory">*</span></label>
												<div class="col-lg-10">
													<div class="input-group" style="max-width:200px;">
														<input type="text" class="form-control readPage forceNumeric isNumeric isRequired" id="viral_load_threshold_limit" name="viral_load_threshold_limit" placeholder="<?php echo _translate('Viral Load Threshold Limit'); ?>" title="<?php echo _translate('Please enter VL threshold limit'); ?>" value="<?php echo $arr['viral_load_threshold_limit']; ?>" />
														<span class="input-group-addon"><?php echo _translate("cp/mL"); ?></span>
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
										<div class="col-md-6">
											<div class="form-group">
												<label for="vl_min_patient_id_length" class="col-lg-4 control-label"><?php echo _translate("Minimum Patient ID Length"); ?></label>
												<div class="col-lg-4">
													<input type="text" class="form-control forceNumeric isNumeric" id="vl_min_patient_id_length" name="vl_min_patient_id_length" placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter minimum length for Patient ID'); ?>" value="<?= $arr['vl_min_patient_id_length'] ?? 3 ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
										<?php
										if ($arr['vl_form'] == COUNTRY\CAMEROON && isset($arr['vl_copy_request_save_and_next']) && $arr['vl_copy_request_save_and_next'] != '') { ?>
											<div class="col-md-6">
												<div class="form-group">
													<label for="vl_copy_request_save_and_next" class="col-lg-4 control-label"><?php echo _translate("Copy Request On Save and Next Form"); ?><span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<select id="vl_copy_request_save_and_next" name="vl_copy_request_save_and_next" type="text" class="form-control readPage" title="<?php echo _translate('Please select copy request on save and next form'); ?>">
															<option value=""><?php echo _translate("--Select--"); ?></option>
															<option value="yes" <?php echo (isset($arr['vl_copy_request_save_and_next']) && $arr['vl_copy_request_save_and_next'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Enable"); ?></option>
															<option value="no" <?php echo (isset($arr['vl_copy_request_save_and_next']) && $arr['vl_copy_request_save_and_next'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("Disable"); ?></option>
														</select>
													</div>
												</div>
											</div>
									</div>


									<div class="row">
									<?php }
										if (isset($arr['vl_suppression_target']) && $arr['vl_suppression_target'] != '') { ?>
										<div class="col-md-6">
											<div class="form-group">
												<label for="vl_suppression_target" class="col-lg-4 control-label"><?php echo _translate("VL Suppression Target"); ?><span class="mandatory ">*</span></label>
												<div class="col-lg-4">
													<select id="vl_suppression_target" name="vl_suppression_target" type="text" class="form-control readPage" title="<?php echo _translate('Please select lock approved sample'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['vl_suppression_target']) && $arr['vl_suppression_target'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Enable"); ?></option>
														<option value="no" <?php echo (isset($arr['vl_suppression_target']) && $arr['vl_suppression_target'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("Disable"); ?></option>
													</select>
												</div>
											</div>
										</div>
									<?php }
										if (isset($arr['vl_monthly_target']) && $arr['vl_monthly_target'] != '') { ?>
										<div class="col-md-6">
											<div class="form-group">
												<label for="vl_monthly_target" class="col-lg-4 control-label"><?php echo _translate("VL Monthly Target"); ?><span class="mandatory ">*</span></label>
												<div class="col-lg-4">
													<select id="vl_monthly_target" name="vl_monthly_target" type="text" class="form-control readPage" title="<?php echo _translate('Please select lock approved sample'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['vl_monthly_target']) && $arr['vl_monthly_target'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Enable"); ?></option>
														<option value="no" <?php echo (isset($arr['vl_monthly_target']) && $arr['vl_monthly_target'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("Disable"); ?></option>
													</select>
												</div>
											</div>
										</div>
									<?php } ?>
									</div>


									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label for="vl_auto_approve_api_results" class="col-lg-4 control-label"><?php echo _translate("VL Auto Approve API Results"); ?></label>
												<div class="col-lg-4">
													<select id="vl_auto_approve_api_results" name="vl_auto_approve_api_results" type="text" class="form-control readPage" title="<?php echo _translate('Please select VL Auto Approve API Results'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['vl_auto_approve_api_results']) && $arr['vl_auto_approve_api_results'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['vl_auto_approve_api_results']) && $arr['vl_auto_approve_api_results'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group">
												<label for="vl_show_participant_name_in_manifest" class="col-lg-4 control-label"><?php echo _translate("VL Show Participant Name in Manifest"); ?></label>
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
										<div class="col-md-6">
											<div class="form-group">
												<label for="vl_interpret_and_convert_results" class="col-lg-4 control-label"><?php echo _translate("Interpret and Convert VL Results"); ?></label>
												<div class="col-lg-4">
													<select id="vl_interpret_and_convert_results" name="vl_interpret_and_convert_results" type="text" class="form-control readPage" title="<?php echo _translate('Please select Interpret and Convert VL Results'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['vl_interpret_and_convert_results']) && $arr['vl_interpret_and_convert_results'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['vl_interpret_and_convert_results']) && $arr['vl_interpret_and_convert_results'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
										<?php if ($arr['vl_form'] == COUNTRY\CAMEROON) { ?>
											<div class="col-md-6">
												<div class="form-group">
													<label for="vl_excel_export_format" class="col-lg-4 control-label"><?php echo _translate("Viral Load Export Format"); ?></label>
													<div class="col-lg-4">
														<select id="vl_excel_export_format" name="vl_excel_export_format" type="text" class="form-control readPage" title="<?php echo _translate('Please select Interpret and Convert VL Results'); ?>">
															<option value=""><?php echo _translate("--Select--"); ?></option>
															<option value="default" <?php echo (isset($arr['vl_excel_export_format']) && $arr['vl_excel_export_format'] == 'default') ? "selected='selected'" : ''; ?>><?php echo _translate("Default Format"); ?></option>
															<option value="cresar" <?php echo (isset($arr['vl_excel_export_format']) && $arr['vl_excel_export_format'] == 'cresar') ? "selected='selected'" : ''; ?>><?php echo _translate("CRESAR Format"); ?></option>
														</select>
													</div>
												</div>
											</div>
										<?php } ?>
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
										<div class="col-md-6">
											<div class="form-group">
												<label for="eid_min_patient_id_length" class="col-lg-4 control-label"><?php echo _translate("Minimum Patient ID Length"); ?></label>
												<div class="col-lg-4">
													<input type="text" class="form-control forceNumeric isNumeric" id="eid_min_patient_id_length" name="eid_min_patient_id_length" placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter minimum length for Patient ID'); ?>" value="<?= $arr['eid_min_patient_id_length'] ?? 3 ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
										<?php if ($arr['vl_form'] == COUNTRY\CAMEROON && isset($arr['eid_copy_request_save_and_next']) && $arr['eid_copy_request_save_and_next'] != '') { ?>
											<div class="col-md-6">
												<div class="form-group">
													<label for="eid_copy_request_save_and_next" class="col-lg-4 control-label"><?php echo _translate("Copy Request On Save and Next Form"); ?><span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<select id="eid_copy_request_save_and_next" name="eid_copy_request_save_and_next" type="text" class="form-control readPage" title="<?php echo _translate('Please select copy request on save and next form'); ?>">
															<option value=""><?php echo _translate("--Select--"); ?></option>
															<option value="yes" <?php echo (isset($arr['eid_copy_request_save_and_next']) && $arr['eid_copy_request_save_and_next'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Enable"); ?></option>
															<option value="no" <?php echo (isset($arr['eid_copy_request_save_and_next']) && $arr['eid_copy_request_save_and_next'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("Disable"); ?></option>
														</select>
													</div>
												</div>
											</div>
										<?php } ?>
									</div>

									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label for="eid_auto_approve_api_results" class="col-lg-4 control-label"><?php echo _translate("EID Auto Approve API Results"); ?></label>
												<div class="col-lg-4">
													<select id="eid_auto_approve_api_results" name="eid_auto_approve_api_results" type="text" class="form-control readPage" title="<?php echo _translate('Please select EID Auto Approve API Results'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['eid_auto_approve_api_results']) && $arr['eid_auto_approve_api_results'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['eid_auto_approve_api_results']) && $arr['eid_auto_approve_api_results'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group">
												<label for="eid_show_participant_name_in_manifest" class="col-lg-4 control-label"><?php echo _translate("EID Show Participant Name in Manifest"); ?></label>
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
												<?php
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
										<div class="col-md-6">
											<div class="form-group">
												<label for="covid19_min_patient_id_length" class="col-lg-4 control-label"><?php echo _translate("Minimum Patient ID Length"); ?></label>
												<div class="col-lg-4">
													<input type="text" class="form-control forceNumeric isNumeric" id="covid19_min_patient_id_length" name="covid19_min_patient_id_length" placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter minimum length for Patient ID'); ?>" value="<?= $arr['covid19_min_patient_id_length'] ?? 3 ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
										<?php
										if ($arr['vl_form'] == COUNTRY\CAMEROON && isset($arr['covid19_copy_request_save_and_next']) && $arr['covid19_copy_request_save_and_next'] != '') { ?>
											<div class="col-md-6">
												<div class="form-group">
													<label for="covid19_copy_request_save_and_next" class="col-lg-4 control-label"><?php echo _translate("Copy Request On Save and Next Form"); ?><span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<select id="covid19_copy_request_save_and_next" name="covid19_copy_request_save_and_next" type="text" class="form-control readPage" title="<?php echo _translate('Please select copy request on save and next form'); ?>">
															<option value=""><?php echo _translate("--Select--"); ?></option>
															<option value="yes" <?php echo (isset($arr['covid19_copy_request_save_and_next']) && $arr['covid19_copy_request_save_and_next'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Enable"); ?></option>
															<option value="no" <?php echo (isset($arr['covid19_copy_request_save_and_next']) && $arr['covid19_copy_request_save_and_next'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("Disable"); ?></option>
														</select>
													</div>
												</div>
											</div>
										<?php } ?>
									</div>

									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label for="covid19_auto_approve_api_results" class="col-lg-4 control-label"><?php echo _translate("COVID-19 Auto Approve API Results"); ?></label>
												<div class="col-lg-4">
													<select id="covid19_auto_approve_api_results" name="covid19_auto_approve_api_results" type="text" class="form-control readPage" title="<?php echo _translate('Please select COVID-19 Auto Approve API Results'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['covid19_auto_approve_api_results']) && $arr['covid19_auto_approve_api_results'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['covid19_auto_approve_api_results']) && $arr['covid19_auto_approve_api_results'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group">
												<label for="eid_show_participant_name_in_manifest" class="col-lg-4 control-label"><?php echo _translate("COVID-19 Show Participant Name in Manifest"); ?></label>
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
												<?php echo _translate("eg. Prefix+Month+Year+Increment Counter (HEP0517999)"); ?>
											</code>
											<code id="hepatitis_auto-sample-code-YY" class="hepatitis_autoSample" style="display:<?php echo ($arr['hepatitis_sample_code'] == 'YY') ? 'block' : 'none'; ?>;">
												<?php echo _translate("eg. Prefix+Year+Increment Counter (HEP17999)"); ?>
											</code>
										</div>
									</div><br />

									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label for="hepatitis_min_patient_id_length" class="col-lg-4 control-label"><?php echo _translate("Minimum Patient ID Length"); ?></label>
												<div class="col-lg-4">
													<input type="text" class="form-control forceNumeric isNumeric" id="hepatitis_min_patient_id_length" name="hepatitis_min_patient_id_length" placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter minimum length for Patient ID'); ?>" value="<?= $arr['hepatitis_min_patient_id_length'] ?? 3 ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
										<?php if ($arr['vl_form'] == COUNTRY\CAMEROON && isset($arr['hepatitis_copy_request_save_and_next']) && $arr['hepatitis_copy_request_save_and_next'] != '') { ?>
											<div class="col-md-6">
												<div class="form-group">
													<label for="hepatitis_copy_request_save_and_next" class="col-lg-4 control-label"><?php echo _translate("Copy Request On Save and Next Form"); ?><span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<select id="hepatitis_copy_request_save_and_next" name="hepatitis_copy_request_save_and_next" type="text" class="form-control readPage" title="<?php echo _translate('Please select copy request on save and next form'); ?>">
															<option value=""><?php echo _translate("--Select--"); ?></option>
															<option value="yes" <?php echo (isset($arr['hepatitis_copy_request_save_and_next']) && $arr['hepatitis_copy_request_save_and_next'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Enable"); ?></option>
															<option value="no" <?php echo (isset($arr['hepatitis_copy_request_save_and_next']) && $arr['hepatitis_copy_request_save_and_next'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("Disable"); ?></option>
														</select>
													</div>
												</div>
											</div>
										<?php } ?>
									</div>


									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label for="hepatitis_show_participant_name_in_manifest" class="col-lg-4 control-label"><?php echo _translate("Hepatitis Show Participant Name in Manifest"); ?></label>
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
										<div class="col-md-6">
											<div class="form-group">
												<label for="tb_min_patient_id_length" class="col-lg-4 control-label"><?php echo _translate("Minimum Patient ID Length"); ?></label>
												<div class="col-lg-4">
													<input type="text" class="form-control forceNumeric isNumeric" id="tb_min_patient_id_length" name="tb_min_patient_id_length" placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter minimum length for Patient ID'); ?>" value="<?= $arr['tb_min_patient_id_length'] ?? 3 ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
										<?php if ($arr['vl_form'] == COUNTRY\CAMEROON && isset($arr['tb_copy_request_save_and_next']) && $arr['tb_copy_request_save_and_next'] != '') { ?>
											<div class="col-md-6">
												<div class="form-group">
													<label for="tb_copy_request_save_and_next" class="col-lg-4 control-label"><?php echo _translate("Copy Request On Save and Next Form"); ?><span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<select id="tb_copy_request_save_and_next" name="tb_copy_request_save_and_next" type="text" class="form-control readPage" title="<?php echo _translate('Please select copy request on save and next form'); ?>">
															<option value=""><?php echo _translate("--Select--"); ?></option>
															<option value="yes" <?php echo (isset($arr['tb_copy_request_save_and_next']) && $arr['tb_copy_request_save_and_next'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Enable"); ?></option>
															<option value="no" <?php echo (isset($arr['tb_copy_request_save_and_next']) && $arr['tb_copy_request_save_and_next'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("Disable"); ?></option>
														</select>
													</div>
												</div>
											</div>
										<?php } ?>
									</div>

									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label for="tb_auto_approve_api_results" class="col-lg-4 control-label"><?php echo _translate("TB Auto Approve API Results"); ?></label>
												<div class="col-lg-4">
													<select id="tb_auto_approve_api_results" name="tb_auto_approve_api_results" type="text" class="form-control readPage" title="<?php echo _translate('Please select TB Auto Approve API Results'); ?>">
														<option value=""><?php echo _translate("--Select--"); ?></option>
														<option value="yes" <?php echo (isset($arr['tb_auto_approve_api_results']) && $arr['tb_auto_approve_api_results'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Yes"); ?></option>
														<option value="no" <?php echo (isset($arr['tb_auto_approve_api_results']) && $arr['tb_auto_approve_api_results'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("No"); ?></option>
													</select>
												</div>
											</div>
										</div>
										<div class="col-md-6">
											<div class="form-group">
												<label for="tb_show_participant_name_in_manifest" class="col-lg-4 control-label"><?php echo _translate("TB Show Participant Name in Manifest"); ?></label>
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
										<div class="col-md-6">
											<div class="form-group">
												<label for="cd4_min_patient_id_length" class="col-lg-4 control-label"><?php echo _translate("Minimum Patient ID Length"); ?></label>
												<div class="col-lg-4">
													<input type="text" class="form-control forceNumeric isNumeric" id="cd4_min_patient_id_length" name="cd4_min_patient_id_length" placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter minimum length for Patient ID'); ?>" value="<?= $arr['cd4_min_patient_id_length'] ?? 3 ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
										<?php
										if ($arr['vl_form'] == COUNTRY\CAMEROON && isset($arr['cd4_copy_request_save_and_next']) && $arr['cd4_copy_request_save_and_next'] != '') { ?>
											<div class="col-md-6">
												<div class="form-group">
													<label for="cd4_copy_request_save_and_next" class="col-lg-4 control-label"><?php echo _translate("Copy Request On Save and Next Form"); ?><span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<select id="cd4_copy_request_save_and_next" name="cd4_copy_request_save_and_next" type="text" class="form-control readPage" title="<?php echo _translate('Please select copy request on save and next form'); ?>">
															<option value=""><?php echo _translate("--Select--"); ?></option>
															<option value="yes" <?php echo (isset($arr['cd4_copy_request_save_and_next']) && $arr['cd4_copy_request_save_and_next'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Enable"); ?></option>
															<option value="no" <?php echo (isset($arr['cd4_copy_request_save_and_next']) && $arr['cd4_copy_request_save_and_next'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("Disable"); ?></option>
														</select>
													</div>
												</div>
											</div>
										<?php } ?>
									</div>


									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label for="cd4_show_participant_name_in_manifest" class="col-lg-4 control-label"><?php echo _translate("cd4 Show Participant Name in Manifest"); ?></label>
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
										<div class="col-md-6">
											<div class="form-group">
												<label for="generic_min_patient_id_length" class="col-lg-4 control-label"><?php echo _translate("Minimum Patient ID Length"); ?></label>
												<div class="col-lg-4">
													<input type="text" class="form-control forceNumeric isNumeric" id="generic_min_patient_id_length" name="generic_min_patient_id_length" placeholder="<?php echo _translate('Min'); ?>" title="<?php echo _translate('Please enter minimum length for Patient ID'); ?>" value="<?= $arr['generic_min_patient_id_length'] ?? 3 ?>" style="max-width:60px;" />
												</div>
											</div>
										</div>
										<?php if ($arr['vl_form'] == COUNTRY\CAMEROON && isset($arr['generic_copy_request_save_and_next']) && $arr['generic_copy_request_save_and_next'] != '') { ?>
											<div class="col-md-6">
												<div class="form-group">
													<label for="generic_copy_request_save_and_next" class="col-lg-4 control-label"><?php echo _translate("Copy Request On Save and Next Form"); ?><span class="mandatory ">*</span></label>
													<div class="col-lg-4">
														<select id="generic_copy_request_save_and_next" name="generic_copy_request_save_and_next" type="text" class="form-control readPage" title="<?php echo _translate('Please select copy request on save and next form'); ?>">
															<option value=""><?php echo _translate("--Select--"); ?></option>
															<option value="yes" <?php echo (isset($arr['generic_copy_request_save_and_next']) && $arr['generic_copy_request_save_and_next'] == 'yes') ? "selected='selected'" : ''; ?>><?php echo _translate("Enable"); ?></option>
															<option value="no" <?php echo (isset($arr['generic_copy_request_save_and_next']) && $arr['generic_copy_request_save_and_next'] == 'no') ? "selected='selected'" : ''; ?>><?php echo _translate("Disable"); ?></option>
														</select>
													</div>
												</div>
											</div>
										<?php } ?>
									</div>

									<div class="row">
										<?php
										if (isset($arr['generic_sample_expiry_after_days']) && $arr['generic_sample_expiry_after_days'] != '') { ?>
											<div class="col-md-6">
												<div class="form-group">
													<label for="generic_sample_expiry_after_days" class="col-lg-4 control-label"><?php echo _translate("Sample Expiry Days"); ?></label>
													<div class="col-lg-4">
														<input value="<?php echo $arr['generic_sample_expiry_after_days']; ?>" type="text" id="generic_sample_expiry_after_days" name="generic_sample_expiry_after_days" placeholder="<?php echo _translate('Enter the sample expiry days'); ?>" class="form-control readPage" title="<?php echo _translate('Please enter the sample expiry days'); ?>">
													</div>
												</div>
											</div>
										<?php } ?>

										<div class="col-md-6">
											<div class="form-group">
												<label for="generic_show_participant_name_in_manifest" class="col-lg-4 control-label"><?php echo _translate("Other Lab Tests Show Participant Name in Manifest"); ?></label>
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
									<div class="col-md-6">
										<div class="form-group">
											<label for="show_smiley" class="col-lg-4 control-label"><?php echo _translate("Show Emoticon/Smiley"); ?> </label>
											<div class="col-lg-8">
												<input type="radio" class="readPage" id="show_smiley_yes" name="show_smiley" value="yes" <?php echo ($arr['show_smiley'] == 'yes') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("Yes"); ?>&nbsp;&nbsp;
												<input type="radio" class="readPage" id="show_smiley_no" name="show_smiley" value="no" <?php echo ($arr['show_smiley'] == 'no' || $arr['show_smiley'] == '') ? 'checked' : ''; ?>>&nbsp;&nbsp;<?php echo _translate("No"); ?>
											</div>
										</div>
									</div>

									<div class="col-md-6">
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
									<div class="col-md-6">
										<div class="form-group">
											<label for="h_vl_msg" class="col-lg-4 control-label"><?php echo _translate("High Viral Load Message"); ?> </label>
											<div class="col-lg-8">
												<textarea class="form-control readPage" id="h_vl_msg" name="h_vl_msg" placeholder="<?php echo _translate('High Viral Load message that will appear for results >= the VL threshold limit'); ?>" title="<?php echo _translate('Please enter high viral load message'); ?>" style="width:100%;min-height:80px;max-height:100px;"><?php echo $arr['h_vl_msg']; ?></textarea>
											</div>
										</div>
									</div>

									<div class="col-md-6">
										<div class="form-group">
											<label for="l_vl_msg" class="col-lg-4 control-label"><?php echo _translate("Low Viral Load Message"); ?> </label>
											<div class="col-lg-8">
												<textarea class="form-control readPage" id="l_vl_msg" name="l_vl_msg" placeholder="<?php echo _translate('Low Viral Load message that will appear for results lesser than the VL threshold limit'); ?>" title="<?php echo _translate('Please enter low viral load message'); ?>" style="width:100%;min-height:80px;max-height:100px;"><?php echo $arr['l_vl_msg']; ?></textarea>
											</div>
										</div>
									</div>
								</div>
								<div class="row">
									<div class="col-md-6">
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

							</div>

						</div>
						<!-- /.box-body -->
						<div class="box-footer hideFooter">
							<input type="hidden" class="readPage" name="removedLogoImage" id="removedLogoImage" />
							<input type="hidden" class="" name="csrf_token" id="csrf_token" value="<?= $_SESSION['csrf_token']; ?>" />
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
<!-- <script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script> -->
<script src="/assets/js/jquery.multi-select.js"></script>
<script src="/assets/js/jquery.quicksearch.js"></script>
<script type="text/javascript">
	$(document).ready(function() {

		showBarcodeFormatMessage($("#bar_code_printing").val());

		$(".select2").select2();

		var editSet = '<?php if (isset($_GET['e'])) {
							echo htmlspecialchars((string) $_GET['e']);
						} ?>'
		// alert(editSet)
		if (editSet == 1) {

		} else {
			$(".readPage").prop('disabled', true);
			$(".readPage").addClass('disabled');
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

		// Add change event handler to existing selects
		$('.testTypeFileSelect').on('change', handleTestTypeChange);

		// Initial update
		// updateDropdownOptions();
		updateAddButtonVisibility();
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

	function getNewImage(img) {
		$("#clearImage").addClass("hide");
		$("#removedLogoImage").val(img);
	}

	function showBarcodeFormatMessage(barcodeVal) {
		$contentMsg = "";
		if (barcodeVal == "zebra-printer") {
			$('.barcodeFormat').show();

			$.post("/global-config/getJsFileContent.php", {
					formatType: barcodeVal
				},
				function(data) {
					$('.contentDiv').html('<label for="contentFormat" class="col-lg-4 control-label"> <?= _translate("Zebra Label Format", true); ?> <span class="mandatory">*</span> </label><div class="col-lg-8"><textarea class="form-control isRequired" placeholder="Enter the Content for Zebra format" id="contentFormat" name="contentFormat" style="width:100%;min-height:80px;max-height:100px;">' + data + '</textarea></div>');
				});
		} else if (barcodeVal == "dymo-labelwriter-450") {
			$('.barcodeFormat').show();

			$.post("/global-config/getJsFileContent.php", {
					formatType: barcodeVal
				},
				function(data) {
					$('.contentDiv').html('<label for="contentFormat" class="col-lg-4 control-label"> <?= _translate("DYMO Label Format", true); ?> <span class="mandatory">*</span> </label><div class="col-lg-8"><textarea class="form-control isRequired" placeholder="Enter the Content for Dymo format" id="contentFormat" name="contentFormat" style="width:100%;min-height:80px;max-height:100px;">' + data + '</textarea></div>');
				});
		} else {
			$('.barcodeFormat').hide();
		}

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
		} else if (this.value == 'auto') {
			$('.autoSample').hide();
			$('#auto-sample-eg').show();
			$('#auto-sample-code').show();
			$('.boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else if (this.value == 'auto2') {
			$('.autoSample').hide();
			$('#auto-sample-eg').show();
			$('#auto-sample-code2').show();
			$('.boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else {
			$('#auto-sample-eg').hide();
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
		} else if (this.value == 'auto') {
			$('.eid_autoSample').hide();
			$('#eid_auto-sample-eg').show();
			$('#eid_auto-sample-code').show();
			$('.eid_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else if (this.value == 'auto2') {
			$('.eid_autoSample').hide();
			$('#eid_auto-sample-eg').show();
			$('#eid_auto-sample-code2').show();
			$('.eid_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else {
			$('#eid_auto-sample-eg').hide();
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
		} else if (this.value == 'auto') {
			$('.covid19_autoSample').hide();
			$('#covid19_auto-sample-eg').show();
			$('#covid19_auto-sample-code').show();
			$('.covid19_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else if (this.value == 'auto2') {
			$('.covid19_autoSample').hide();
			$('#covid19_auto-sample-eg').show();
			$('#covid19_auto-sample-code2').show();
			$('.covid19_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else {
			$('#covid19_auto-sample-eg').hide();
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
		} else if (this.value == 'auto') {
			$('.hepatitis_autoSample').hide();
			$('#hepatitis_auto-sample-eg').show();
			$('#hepatitis_auto-sample-code').show();
			$('.hepatitis_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else if (this.value == 'auto2') {
			$('.hepatitis_autoSample').hide();
			$('#hepatitis_auto-sample-eg').show();
			$('#hepatitis_auto-sample-code2').show();
			$('.hepatitis_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else {
			$('#hepatitis_auto-sample-eg').hide();
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
		} else if (this.value == 'auto') {
			$('.tb_autoSample').hide();
			$('#tb_auto-sample-eg').show();
			$('#tb_auto-sample-code').show();
			$('.tb_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else if (this.value == 'auto2') {
			$('.tb_autoSample').hide();
			$('#tb_auto-sample-eg').show();
			$('#tb_auto-sample-code2').show();
			$('.tb_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else {
			$('#tb_auto-sample-eg').hide();
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
		} else if (this.value == 'auto') {
			$('.generic_autoSample').hide();
			$('#generic_auto-sample-eg').show();
			$('#generic_auto-sample-code').show();
			$('.generic_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else if (this.value == 'auto2') {
			$('.generic_autoSample').hide();
			$('#generic_auto-sample-eg').show();
			$('#generic_auto-sample-code2').show();
			$('.generic_boxWidth').removeClass('isRequired').attr('disabled', true).val('');
		} else {
			$('#generic_auto-sample-eg').hide();
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

	function validateLessThanDays(spanId, days, error) {
		var inputElement = event.target;
		var value = inputElement.value;
		var errorElement = document.getElementById(spanId);

		if (value < days) {
			errorElement.textContent = error;
			inputElement.value = ""; // Clear the input field
		} else {
			errorElement.textContent = "";
		}
	}

	let testTypeFileCounter = <?php echo (count($facilityReportFormat) > 0) ? count($facilityReportFormat) : 1; ?>;

	// Get all available test type options
	function getAvailableTestTypes() {
		availableOptions = [];
		<?php if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) { ?>
			availableOptions.push({
				value: 'vl',
				text: '<?php echo _translate("Viral Load", true); ?>'
			});
		<?php } ?>

		<?php if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) { ?>
			availableOptions.push({
				value: 'eid',
				text: '<?php echo _translate("Early Infant Diagnosis", true); ?>'
			});
		<?php } ?>

		<?php if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) { ?>
			availableOptions.push({
				value: 'covid19',
				text: '<?php echo _translate("Covid-19", true); ?>'
			});
		<?php } ?>

		<?php if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) { ?>
			availableOptions.push({
				value: 'hepatitis',
				text: '<?php echo _translate("Hepatitis", true); ?>'
			});
		<?php } ?>

		<?php if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) { ?>
			availableOptions.push({
				value: 'tb',
				text: '<?php echo _translate("TB", true); ?>'
			});
		<?php } ?>

		<?php if (isset(SYSTEM_CONFIG['modules']['cd4']) && SYSTEM_CONFIG['modules']['cd4'] === true) { ?>
			availableOptions.push({
				value: 'cd4',
				text: '<?php echo _translate("CD4", true); ?>'
			});
		<?php } ?>

		<?php if (isset(SYSTEM_CONFIG['modules']['generic-tests']) && SYSTEM_CONFIG['modules']['generic-tests'] === true) { ?>
			availableOptions.push({
				value: 'generic-tests',
				text: '<?php echo _translate("Other Lab Tests", true); ?>'
			});
		<?php } ?>

		return availableOptions;
	}

	// Get currently selected test types
	function getSelectedTestTypes() {
		const selectedTypes = [];
		$('.testTypeFileSelect').each(function() {
			const value = $(this).val();
			if (value && value !== '') {
				selectedTypes.push(value);
			}
		});
		return selectedTypes;
	}

	// Get available options for new row (excluding already selected ones)
	function getAvailableOptionsForNewRow() {
		const allOptions = getAvailableTestTypes();
		const selectedTypes = getSelectedTestTypes();
		return allOptions.filter(option => !selectedTypes.includes(option.value));
	}

	// Check if we can add more rows
	function canAddMoreRows() {
		const availableOptions = getAvailableOptionsForNewRow();
		return availableOptions.length > 0;
	}

	// Update add button visibility
	function updateAddButtonVisibility() {
		$('.btn-primary[onclick="addTestTypeFileRow();"]').each(function() {
			if (canAddMoreRows()) {
				$(this).show();
			} else {
				$(this).hide();
			}
		});
	}

	// Update dropdown options for all rows
	function updateDropdownOptions() {
		$('.testTypeFileSelect').each(function() {
			const currentSelect = $(this);
			const currentValue = currentSelect.val();
			const selectedTypes = getSelectedTestTypes();

			// Clear and rebuild options
			currentSelect.empty();
			currentSelect.append('<option value=""><?php echo _translate("Select Test Type", true); ?></option>');

			const allOptions = getAvailableTestTypes();
			allOptions.forEach(option => {
				// Show option if it's not selected elsewhere OR if it's the current value of this select
				if (!selectedTypes.includes(option.value) || option.value === currentValue) {
					const selected = option.value === currentValue ? 'selected="selected"' : '';
					currentSelect.append(`<option value="${option.value}" ${selected}>${option.text}</option>`);
				}
			});
		});
	}

	// Function to add new test type file row
	function addTestTypeFileRow() {
		if (!canAddMoreRows()) {
			alert("<?php echo _translate('All available test types have been selected', true); ?>");
			return;
		}

		testTypeFileCounter++;
		let rowString = `<tr>
        <td>
            <select class="form-control readPage testTypeFileSelect" name="reportFormat[test_type][]" id="testTypeFile${testTypeFileCounter}" onchange="handleTestTypeChange()">
                <option value=""><?php echo _translate("Select Test Type", true); ?></option>
            </select>
        </td>
        <td>
            <input type="file" class="readPage form-control" name="reportFormat[report_template][]" id="reportTemplate${testTypeFileCounter}" accept=".pdf" title="<?php echo _translate('Please upload PDF file', true); ?>">
        </td>
        <td>
            <input type="text" class="readPage form-control" name="reportFormat[header_margin][]" id="headerMargin${testTypeFileCounter}" placeholder="<?php echo _translate('Top margin', true); ?>" title="<?php echo _translate('Please enter the header margin', true); ?>">
        </td>
        <td style="vertical-align:middle;text-align: center;">
            <a class="btn btn-xs readPage btn-primary" href="javascript:void(0);" onclick="addTestTypeFileRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;
            <a class="btn btn-xs readPage btn-default" href="javascript:void(0);" onclick="removeTestTypeFileRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
        </td>
    </tr>`;

		$("#testTypeFileDetails").append(rowString);
		updateDropdownOptions();
		updateAddButtonVisibility();
	}

	// Function to remove test type file row
	function removeTestTypeFileRow(el) {
		let rowCount = document.getElementById("testTypeFileDetails").rows.length;
		if (rowCount > 1) {
			$(el).fadeOut("slow", function() {
				$(el).remove();
				updateDropdownOptions();
				updateAddButtonVisibility();
			});
		} else {
			alert("<?php echo _translate('At least one row is required', true); ?>");
		}
	}

	// Handle test type selection change
	function handleTestTypeChange() {
		updateDropdownOptions();
		updateAddButtonVisibility();
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
<?php require_once APPLICATION_PATH . '/footer.php'; ?>