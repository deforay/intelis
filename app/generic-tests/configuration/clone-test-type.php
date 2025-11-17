<?php

namespace App\Services;

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;
use Laminas\Diactoros\ServerRequest;

require_once APPLICATION_PATH . '/header.php';

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GenericTestsService $generic */
$generic = ContainerRegistry::get(GenericTestsService::class);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;

$tQuery = "SELECT * from r_test_types WHERE test_type_id=?";
$testTypeInfo = $db->rawQueryOne($tQuery, [$id]);
$testAttr = json_decode((string) $testTypeInfo['test_form_config'], true);
$testResultAttribute = json_decode((string) $testTypeInfo['test_results_config'], true);

// $stQuery = "SELECT * from r_generic_sample_types where sample_type_status='active'";
$testMethodInfo = $general->getDataByTableAndFields("r_generic_test_methods", ["test_method_id", "test_method_name"], true, "test_method_status='active'");
$testMethodId = $general->getDataByTableAndFields("generic_test_methods_map", ["test_method_id", "test_method_id"], true, "test_type_id=$id");
$categoryInfo = $general->getDataByTableAndFields("r_generic_test_categories", ["test_category_id", "test_category_name"], true, "test_category_status='active'");

$sampleTypeInfo = $general->getDataByTableAndFields("r_generic_sample_types", ["sample_type_id", "sample_type_name"], true, "sample_type_status='active'");
$testReasonInfo = $general->getDataByTableAndFields("r_generic_test_reasons", ["test_reason_id", "test_reason"], true, "test_reason_status='active'");
$testResultUnitInfo = $general->getDataByTableAndFields("r_generic_test_result_units", ["unit_id", "unit_name"], true, "unit_status='active'");

$testFailureReasonInfo = $general->getDataByTableAndFields("r_generic_test_failure_reasons", ["test_failure_reason_id", "test_failure_reason"], true, "test_failure_reason_status='active'");
$sampleRejectionReasonInfo = $general->getDataByTableAndFields("r_generic_sample_rejection_reasons", ["rejection_reason_id", "rejection_reason_name"], true, "rejection_reason_status='active'");
$symptomInfo = $general->getDataByTableAndFields("r_generic_symptoms", ["symptom_id", "symptom_name"], true, "symptom_status='active'");
$testSampleId = $general->getDataByTableAndFields("generic_test_sample_type_map", ["sample_type_id", "sample_type_id"], true, "test_type_id=$id");
$testReasonId = $general->getDataByTableAndFields("generic_test_reason_map", ["test_reason_id", "test_reason_id"], true, "test_type_id=$id");

$testFailureReasonId = $general->getDataByTableAndFields("generic_test_failure_reason_map", ["test_failure_reason_id", "test_failure_reason_id"], true, "test_type_id=$id");
$rejectionReasonId = $general->getDataByTableAndFields("generic_sample_rejection_reason_map", ["rejection_reason_id", "rejection_reason_id"], true, "test_type_id=$id");
$testSymptomsId = $general->getDataByTableAndFields("generic_test_symptoms_map", ["symptom_id", "symptom_id"], true, "test_type_id=$id");
$testResultUnitId = $general->getDataByTableAndFields("generic_test_result_units_map", ["unit_id", "unit_id"], true, "test_type_id=$id");

?>
<style>
	.tooltip-inner {
		background-color: #fff;
		color: #000;
		border: 1px solid #000;
	}

	.tag-input {
		width: 100%;
		padding: 10px;
		box-sizing: border-box;
		background-color: #f9f9f9;
		border: 1px solid #ccc;
	}

	.tag-input .tag-input-field {
		border: none;
		background-color: transparent;
		width: 100%;
	}

	.tag {
		display: inline-block;
		padding: 5px 10px;
		margin-right: 5px;
		background-color: #007bff;
		color: #fff;
		border-radius: 3px;
		margin-bottom: 5px;
	}

	.remove-tag {
		margin-left: 5px;
		cursor: pointer;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-sharp fa-solid fa-gears"></em> <?php echo _translate("Edit Test Type"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("Edit Test Type"); ?></li>
		</ol>
	</section>
	<!-- Main content -->
	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _translate("indicates required fields"); ?> &nbsp;</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='cloneTestTypeForm' id='cloneTestTypeForm' autocomplete="off" action="addTestTypeHelper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="testStandardName" class="col-lg-4 control-label"><?php echo _translate("Test Standard Name"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="testStandardName" name="testStandardName" placeholder='<?php echo _translate("Test Standard Name"); ?>' title='<?php echo _translate("Please enter standard name"); ?>' onblur="checkNameValidation('r_test_types','test_standard_name',this,'<?php echo "test_type_id##" . $testTypeInfo['test_type_id']; ?>','<?php echo _translate("This test standard name that you entered already exists.Try another name"); ?>',null)" />
										<input type="hidden" name="testTypeId" id="testTypeId" value="<?php echo base64_encode((string) $testTypeInfo['test_type_id']); ?>" />
									</div>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="testGenericName" class="col-lg-4 control-label"><?php echo _translate("Test Generic Name"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="testGenericName" name="testGenericName" placeholder='<?php echo _translate("Test Generic Name"); ?>' title='<?php echo _translate("Please enter the test generic name"); ?>' onblur="checkNameValidation('r_test_types','test_generic_name',this,'<?php echo "test_type_id##" . $testTypeInfo['test_type_id']; ?>','<?php echo _translate("This test generic name that you entered already exists.Try another name"); ?>',null)" />
									</div>
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="testShortCode" class="col-lg-4 control-label"><?php echo _translate("Test Short Code"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="testShortCode" name="testShortCode" placeholder='<?php echo _translate("Test Short Code"); ?>' title='<?php echo _translate("Please enter short code"); ?>' onblur="checkNameValidation('r_test_types','test_short_code',this,'<?php echo "test_type_id##" . $testTypeInfo['test_type_id']; ?>','<?php echo _translate("This test short code that you entered already exists.Try another code"); ?>',null)" onchange="alphanumericValidation(this.value);" />
									</div>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="testLoincCode" class="col-lg-4 control-label"><?php echo _translate("LOINC Codes"); ?></label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="testLoincCode" name="testLoincCode" placeholder='<?php echo _translate("Test LOINC Code"); ?>' title='<?php echo _translate("Please enter test loinc code"); ?>' onblur="checkNameValidation('r_test_types','test_loinc_code',this,'<?php echo "test_type_id##" . $testTypeInfo['test_type_id']; ?>','<?php echo _translate("This test loinc code that you entered already exists.Try another code"); ?>',null)" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="testMethod" class="col-lg-4 control-label"><?php echo _translate("Test Methods"); ?> <span class="mandatory">*</span> <em class="fas fa-edit"></em></label>
									<div class="col-lg-7">
										<select class="form-control isRequired editableSelect" name='testMethod[]' id='testMethod' title="<?php echo _translate('Please select the test methods'); ?>" multiple>
											<?= $general->generateSelectOptions($testMethodInfo, $testMethodId, '-- Select --') ?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="testCategory" class="col-lg-4 control-label"><?php echo _translate("Test Category"); ?> <span class="mandatory">*</span> <em class="fas fa-edit"></em></label>
									<div class="col-lg-7">
										<select class="form-control isRequired editableSelect" name='testCategory' id='testCategory' title="<?php echo _translate('Please select the test categories'); ?>">
											<?= $general->generateSelectOptions($categoryInfo, $testTypeInfo['test_category'], '-- Select --') ?>
										</select>
									</div>
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="sampleType" class="col-lg-4 control-label"><?php echo _translate("Sample/Specimen Types"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='sampleType[]' id='sampleType' title="<?php echo _translate('Please select the sample type'); ?>" multiple>
											<?= $general->generateSelectOptions($sampleTypeInfo, $testSampleId, '-- Select --') ?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="testingReason" class="col-lg-4 control-label"><?php echo _translate("Reasons for Testing"); ?> <span class="mandatory">*</span> <em class="fas fa-edit"></em></label>
									<div class="col-lg-7">
										<select class="form-control isRequired editableSelect" name='testingReason[]' id='testingReason' title="<?php echo _translate('Please select the testing reason'); ?>" multiple>
											<?= $general->generateSelectOptions($testReasonInfo, $testReasonId, '-- Select --') ?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="testFailureReason" class="col-lg-4 control-label"><?php echo _translate("Test Failure Reasons"); ?> <span class="mandatory">*</span> <em class="fas fa-edit"></em></label>
									<div class="col-lg-7">
										<select class="form-control isRequired editableSelect" name='testFailureReason[]' id='testFailureReason' title="<?php echo _translate('Please select the test failure reason'); ?>" multiple>
											<?= $general->generateSelectOptions($testFailureReasonInfo, $testFailureReasonId, '-- Select --') ?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="rejectionReason" class="col-lg-4 control-label"><?php echo _translate("Sample Rejection Reasons"); ?> <span class="mandatory">*</span> <em class="fas fa-edit"></em></label>
									<div class="col-lg-7">
										<select class="form-control isRequired editableSelect" name='rejectionReason[]' id='rejectionReason' title="<?php echo _translate('Please select the sample rejection reason'); ?>" multiple>
											<?= $general->generateSelectOptions($sampleRejectionReasonInfo, $rejectionReasonId, '-- Select --') ?>
										</select>
									</div>
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="symptoms" class="col-lg-4 control-label"><?php echo _translate("Symptoms"); ?></label>
									<div class="col-lg-7">
										<select class="form-control" name='symptoms[]' id='symptoms' title="<?php echo _translate('Please select the symptoms'); ?>" multiple>
											<?= $general->generateSelectOptions($symptomInfo, $testSymptomsId, '-- Select --') ?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="status" class="col-lg-4 control-label"><?php echo _translate("Status"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='status' id='status' title="<?php echo _translate('Please select the status'); ?>">
											<option value="active" <?php echo ($testTypeInfo['test_status'] == 'active') ? "selected='selected'" : "" ?>><?php echo _translate("Active"); ?></option>
											<option value="inactive" <?php echo ($testTypeInfo['test_status'] == 'inactive') ? "selected='selected'" : "" ?>><?php echo _translate("Inactive"); ?></option>
										</select>
									</div>
								</div>
							</div>
						</div>

						<div class="box-header">
							<h3 class="box-title "><?php echo _translate("Form Configuration"); ?></h3>
						</div>

						<div class="box-body">
							<table aria-describedby="table" border="0" class="table table-striped table-bordered table-condensed" aria-hidden="true" style="width:100%;">
								<thead>
									<tr>
										<th style="text-align:center;width:20%;"><?php echo _translate("Field Name"); ?> <span class="mandatory">*</span></th>
										<th style="text-align:center;width:15%;"><?php echo _translate("Field Code"); ?> <span class="mandatory">*</span></th>
										<th style="text-align:center;width:15%;"><?php echo _translate("Field Type"); ?> <span class="mandatory">*</span></th>
										<th style="text-align:center;width:10%;"><?php echo _translate("Is it Mandatory?"); ?> <span class="mandatory">*</span></th>
										<th style="text-align:center;width:20%;"><?php echo _translate("Section"); ?> <span class="mandatory">*</span></th>
										<th style="text-align:center;width:10%;"><?php echo _translate("Field Order"); ?> </th>
										<th style="text-align:center;width:10%;"><?php echo _translate("Action"); ?></th>
									</tr>
								</thead>
								<tbody id="attributeTable">
									<?php
									$arraySection = ['facilitySection', 'patientSection', 'specimenSection', 'labSection'];
									$eCount = count($testAttr);
									if ($eCount > 0) {
										$i = 1;
										foreach ($testAttr as $testAttrkey => $testAttributeDetails) {
											if (in_array($testAttrkey, $arraySection)) {
												foreach ($testAttributeDetails as $testAttributeId => $testAttribute) { ?>
													<tr>
														<td align="center" style="vertical-align:middle;">
															<input type="text" name="fieldName[]" id="fieldName<?php echo $i ?>" class="form-control fieldName isRequired" placeholder='<?php echo _translate("Field Name"); ?>' title='<?php echo _translate("Please enter field name"); ?>' onblur="checkDuplication(this, 'fieldName');" value="<?php echo $testAttribute['field_name']; ?>" />
															<input type="hidden" name="fieldId[]" id="fieldId<?php echo $i ?>" class="form-control isRequired" value="<?php echo $testAttributeId; ?>" />
														</td>
														<td align="center" style="vertical-align:middle;">
															<input type="text" name="fieldCode[]" id="fieldCode<?php echo $i; ?>" class="form-control fieldCode isRequired" placeholder="<?php echo _translate("Field Code"); ?>" title="<?php echo _translate("Please enter field code"); ?>" onblur="checkDuplication(this, \'fieldCode\');" value="<?php echo $testAttribute['field_code']; ?>" onchange="this.value=Utilities.toSnakeCase(this.value)" />
														</td>
														<td align="center" style="vertical-align:middle;padding-top: 20px;">
															<select class="form-control isRequired" name="fieldType[]" id="fieldType<?php echo $i ?>" title="<?php echo _translate('Please select the field type'); ?>" onchange="changeField(this, <?php echo $i ?>)">
																<option value=""> <?php echo _translate("-- Select --"); ?> </option>
																<option value="number" <?php echo ($testAttribute['field_type'] == 'number') ? "selected='selected'" : "" ?>><?php echo _translate("Number"); ?></option>
																<option value="text" <?php echo ($testAttribute['field_type'] == 'text') ? "selected='selected'" : "" ?>><?php echo _translate("Text"); ?></option>
																<option value="date" <?php echo ($testAttribute['field_type'] == 'date') ? "selected='selected'" : "" ?>><?php echo _translate("Date"); ?></option>
																<option value="dropdown" <?php echo ($testAttribute['field_type'] == 'dropdown') ? "selected='selected'" : "" ?>><?php echo _translate("Dropdown"); ?></option>
																<option value="multiple" <?php echo ($testAttribute['field_type'] == 'multiple') ? "selected='selected'" : "" ?>><?php echo _translate("Multiselect Dropdown"); ?></option>
															</select><br>
															<div class="tag-input dropDown<?php echo $i ?>" style="<?php echo ($testAttribute['field_type'] == 'multiple' || $testAttribute['field_type'] == 'dropdown') ? "" : "display:none;" ?>">
																<input type="text" name="dropDown[]" id="dropDown<?php echo $i ?>" onkeyup="showTags(event,this,'<?php echo $i ?>')" class="tag-input-field form-control" placeholder="Enter options..." />
																<input type="hidden" value="<?php echo (empty($testAttribute['dropdown_options'])) ? "" : $testAttribute['dropdown_options'] . ',' ?>" id="fdropDown<?php echo $i ?>" name="fdropDown[]" class="fdropDown" />
																<div class="tag-container container<?php echo $i ?>">
																	<?php
																	if (!empty($testAttribute['dropdown_options'])) {
																		$val = explode(",", (string) $testAttribute['dropdown_options']);
																		foreach ($val as $v) {
																	?>
																			<div class="tag"><?php echo $v; ?><span class="remove-tag">x</span></div>
																	<?php }
																	} ?>
																</div>
															</div>
														</td>
														<td align="center" style="vertical-align:middle;">
															<select class="form-control isRequired" name="mandatoryField[]" id="mandatoryField<?php echo $i ?>" title="<?php echo _translate('Please select is it mandatory'); ?>">
																<option value="yes" <?php echo ($testAttribute['mandatory_field'] == 'yes') ? "selected='selected'" : "" ?>><?php echo _translate("Yes"); ?></option>
																<option value="no" <?php echo ($testAttribute['mandatory_field'] == 'no') ? "selected='selected'" : "" ?>><?php echo _translate("No"); ?></option>
															</select>
														</td>
														<td align="center" style="vertical-align:middle;">
															<select class="form-control isRequired" name="section[]" id="section<?php echo $i ?>" title="<?php echo _translate('Please select the section'); ?>" onchange="checkSection('<?php echo $i ?>')">
																<option value=""> <?php echo _translate("-- Select --"); ?> </option>
																<option value="facilitySection" <?php echo ($testAttribute['section'] == 'facilitySection') ? "selected='selected'" : "" ?>><?php echo _translate("Facility"); ?></option>
																<option value="patientSection" <?php echo ($testAttribute['section'] == 'patientSection') ? "selected='selected'" : "" ?>><?php echo _translate("Patient"); ?></option>
																<option value="specimenSection" <?php echo ($testAttribute['section'] == 'specimenSection') ? "selected='selected'" : "" ?>><?php echo _translate("Specimen"); ?></option>
																<option value="labSection" <?php echo ($testAttribute['section'] == 'labSection') ? "selected='selected'" : "" ?>><?php echo _translate("Lab"); ?></option>
																<option value="otherSection" <?php echo ($testAttribute['section'] == 'otherSection') ? "selected='selected'" : "" ?>><?php echo _translate("Other"); ?></option>
															</select>
															<input type="text" name="sectionOther[]" id="sectionOther<?php echo $i ?>" onchange="addNewSection(this.value)" class="form-control auto-complete-tbx" placeholder='<?php echo _translate("Section Other"); ?>' title='<?php echo _translate("Please enter section other"); ?>' style="<?php echo ($testAttribute['section'] == 'otherSection') ? "" : "display:none;" ?>" value="<?php echo ($testAttribute['section'] == 'otherSection') ? $testAttribute['section_name'] : "" ?>" />
														</td>
														<td align="center" style="vertical-align:middle;">
															<input type="text" name="fieldOrder[]" id="fieldOrder1" class="form-control forceNumeric" placeholder="<?php echo _translate("Field Order"); ?>" title="<?php echo _translate("Please enter field order"); ?>" value="<?php echo $testAttribute['field_order']; ?>" />
														</td>
														<td align="center" style="vertical-align:middle;">
															<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;&nbsp;<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
														</td>
													</tr>

													<?php
													$i++;
												}
											} else {
												foreach ($testAttributeDetails as $key => $otherAttributeDetails) {
													foreach ($otherAttributeDetails as $otherAttributeId => $testAttribute) {
													?>
														<tr>
															<td align="center" style="vertical-align:middle;">
																<input type="text" name="fieldName[]" id="fieldName<?php echo $i ?>" class="form-control fieldName isRequired" placeholder='<?php echo _translate("Field Name"); ?>' title='<?php echo _translate("Please enter field name"); ?>' onblur="checkDuplication(this, 'fieldName');" value="<?php echo $testAttribute['field_name']; ?>" />
																<input type="hidden" name="fieldId[]" id="fieldId<?php echo $i ?>" class="form-control isRequired" value="<?php echo $otherAttributeId; ?>" />
															</td>
															<td align="center" style="vertical-align:middle;">
																<input type="text" name="fieldCode[]" id="fieldCode<?php echo $i; ?>" class="form-control fieldCode isRequired" placeholder="<?php echo _translate("Field Code"); ?>" title="<?php echo _translate("Please enter field code"); ?>" onblur="checkDuplication(this, \'fieldCode\');" value="<?php echo $testAttribute['field_code']; ?>" onchange="this.value=Utilities.toSnakeCase(this.value)" />
															</td>
															<td align="center" style="vertical-align:middle;padding-top: 20px;">
																<select class="form-control isRequired" name="fieldType[]" id="fieldType<?php echo $i ?>" title="<?php echo _translate('Please select the field type'); ?>" onchange="changeField(this, <?php echo $i ?>)">
																	<option value=""> <?php echo _translate("-- Select --"); ?> </option>
																	<option value="number" <?php echo ($testAttribute['field_type'] == 'number') ? "selected='selected'" : "" ?>><?php echo _translate("Number"); ?></option>
																	<option value="text" <?php echo ($testAttribute['field_type'] == 'text') ? "selected='selected'" : "" ?>><?php echo _translate("Text"); ?></option>
																	<option value="date" <?php echo ($testAttribute['field_type'] == 'date') ? "selected='selected'" : "" ?>><?php echo _translate("Date"); ?></option>
																	<option value="dropdown" <?php echo ($testAttribute['field_type'] == 'dropdown') ? "selected='selected'" : "" ?>><?php echo _translate("Dropdown"); ?></option>
																	<option value="multiple" <?php echo ($testAttribute['field_type'] == 'multiple') ? "selected='selected'" : "" ?>><?php echo _translate("Multiselect Dropdown"); ?></option>
																</select><br>
																<div class="tag-input dropDown<?php echo $i ?>" style="<?php echo ($testAttribute['field_type'] == 'multiple' || $testAttribute['field_type'] == 'dropdown') ? "" : "display:none;" ?>">
																	<input type="text" name="dropDown[]" id="dropDown<?php echo $i ?>" onkeyup="showTags(event,this,'<?php echo $i ?>')" class="tag-input-field form-control" placeholder="Enter options..." />
																	<input type="hidden" value="<?php echo (empty($testAttribute['dropdown_options'])) ? "" : $testAttribute['dropdown_options'] . ',' ?>" id="fdropDown<?php echo $i ?>" name="fdropDown[]" class="fdropDown" />
																	<div class="tag-container container<?php echo $i ?>">
																		<?php
																		if (!empty($testAttribute['dropdown_options'])) {
																			$val = explode(",", (string) $testAttribute['dropdown_options']);
																			foreach ($val as $v) {
																		?>
																				<div class="tag"><?php echo $v; ?><span class="remove-tag">x</span></div>
																		<?php }
																		} ?>
																	</div>
																</div>
															</td>
															<td align="center" style="vertical-align:middle;">
																<select class="form-control isRequired" name="mandatoryField[]" id="mandatoryField<?php echo $i ?>" title="<?php echo _translate('Please select is it mandatory'); ?>">
																	<option value="yes" <?php echo ($testAttribute['mandatory_field'] == 'yes') ? "selected='selected'" : "" ?>><?php echo _translate("Yes"); ?></option>
																	<option value="no" <?php echo ($testAttribute['mandatory_field'] == 'no') ? "selected='selected'" : "" ?>><?php echo _translate("No"); ?></option>
																</select>
															</td>
															<td align="center" style="vertical-align:middle;">
																<select class="form-control isRequired" name="section[]" id="section<?php echo $i ?>" title="<?php echo _translate('Please select the section'); ?>" onchange="checkSection('<?php echo $i ?>')">
																	<option value=""> <?php echo _translate("-- Select --"); ?> </option>
																	<option value="facilitySection" <?php echo ($testAttribute['section'] == 'facilitySection') ? "selected='selected'" : "" ?>><?php echo _translate("Facility"); ?></option>
																	<option value="patientSection" <?php echo ($testAttribute['section'] == 'patientSection') ? "selected='selected'" : "" ?>><?php echo _translate("Patient"); ?></option>
																	<option value="specimenSection" <?php echo ($testAttribute['section'] == 'specimenSection') ? "selected='selected'" : "" ?>><?php echo _translate("Specimen"); ?></option>
																	<option value="labSection" <?php echo ($testAttribute['section'] == 'labSection') ? "selected='selected'" : "" ?>><?php echo _translate("Lab"); ?></option>
																	<option value="otherSection" <?php echo ($testAttribute['section'] == 'otherSection') ? "selected='selected'" : "" ?>><?php echo _translate("Other"); ?></option>
																</select>
																<input type="text" name="sectionOther[]" id="sectionOther<?php echo $i ?>" onchange="addNewSection(this.value)" class="form-control auto-complete-tbx" placeholder='<?php echo _translate("Section Other"); ?>' title='<?php echo _translate("Please enter section other"); ?>' style="<?php echo ($testAttribute['section'] == 'otherSection') ? "" : "display:none;" ?>" value="<?php echo ($testAttribute['section'] == 'otherSection') ? $testAttribute['section_name'] : "" ?>" />
															</td>
															<td align="center" style="vertical-align:middle;">
																<input type="text" name="fieldOrder[]" id="fieldOrder1" class="form-control forceNumeric" placeholder="<?php echo _translate("Field Order"); ?>" title="<?php echo _translate("Please enter field order"); ?>" value="<?php echo $testAttribute['field_order']; ?>" />
															</td>
															<td align="center" style="vertical-align:middle;">
																<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;&nbsp;<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
															</td>
														</tr>
										<?php
														$i++;
													}
												}
											}
										}
									} else { ?>
										<tr>
											<td>
												<input type="text" name="fieldName[]" id="fieldName1" class="form-control fieldName isRequired" placeholder='<?php echo _translate("Field Name"); ?>' title='<?php echo _translate("Please enter field name"); ?>' onblur="checkDuplication(this, 'fieldName');" />
												<input type="hidden" name="fieldId[]" id="fieldId1" class="form-control isRequired" />
											</td>
											<td align="center" style="vertical-align:middle;">
												<input type="text" name="fieldCode[]" id="fieldCode1" class="form-control fieldCode isRequired" placeholder="<?php echo _translate("Field Code"); ?>" title="<?php echo _translate("Please enter field code"); ?>" onblur="checkDuplication(this, \'fieldCode\');" onchange="this.value=Utilities.toSnakeCase(this.value)" />
											</td>
											<td>
												<select class="form-control isRequired" name="fieldType[]" id="fieldType1" title="<?php echo _translate('Please select the field type'); ?>" onchange="changeField(this, 1)">
													<option value=""> <?php echo _translate("-- Select --"); ?> </option>
													<option value="number"><?php echo _translate("Number"); ?></option>
													<option value="text"><?php echo _translate("Text"); ?></option>
													<option value="date"><?php echo _translate("Date"); ?></option>
													<option value="dropdown"><?php echo _translate("Dropdown"); ?></option>
													<option value="multiple"><?php echo _translate("Multiselect Dropdown"); ?></option>
												</select><br>
												<!--<textarea name="dropDown[]" id="dropDown1" class="form-control" placeholder='<?php echo _translate("Drop down values as , separated"); ?>' title='<?php echo _translate("Please drop down values as comma separated"); ?>' style="display:none;"></textarea>-->
												<div class="tag-input dropDown1" style="display:none;">
													<input type="text" name="dropDown[]" id="dropDown1" onkeyup="showTags(event,this,'1')" class="tag-input-field form-control" placeholder="Enter options..." />
													<input type="hidden" class="fdropDown" id="fdropDown1" name="fdropDown[]" />
													<div class="tag-container container1">
													</div>
												</div>
											</td>
											<td>
												<select class="form-control isRequired" name="mandatoryField[]" id="mandatoryField1" title="<?php echo _translate('Please select is it mandatory'); ?>">
													<option value="yes"><?php echo _translate("Yes"); ?></option>
													<option value="no" selected><?php echo _translate("No"); ?></option>
												</select>
											</td>
											<td>
												<select class="form-control isRequired" name="section[]" id="section1" title="<?php echo _translate('Please select the section'); ?>" onchange="checkSection('1')">
													<option value=""> <?php echo _translate("-- Select --"); ?> </option>
													<option value="facilitySection"><?php echo _translate("Facility"); ?></option>
													<option value="patientSection"><?php echo _translate("Patient"); ?></option>
													<option value="specimenSection"><?php echo _translate("Specimen"); ?></option>
													<option value="labSection"><?php echo _translate("Lab"); ?></option>
													<option value="otherSection"><?php echo _translate("Other"); ?></option>
												</select>
												<input type="text" name="sectionOther[]" id="sectionOther1" class="form-control auto-complete-tbx" onchange="addNewSection(this.value)" placeholder='<?php echo _translate("Section Other"); ?>' title='<?php echo _translate("Please enter section other"); ?>' style="display:none;" />
											</td>
											<td>
												<input type="text" name="fieldOrder[]" id="fieldOrder1" class="form-control forceNumeric" placeholder="<?php echo _translate("Field Order"); ?>" title="<?php echo _translate("Please enter field order"); ?>" />
											</td>
											<td align="center" style="vertical-align:middle;">
												<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;&nbsp;<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
											</td>
										</tr>
									<?php } ?>
								</tbody>
							</table>
						</div>
						<div class="box-header">
							<h3 class="box-title "><?php echo _translate("Test Results Configuration"); ?></h3>
						</div>
						<div class="box-body">
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label for="resultType" class="col-lg-3 control-label"><?php echo _translate("Result Type"); ?> <span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<select class="form-control isRequired" name='resultConfig[result_type]' id='resultType' onchange="checkResultType();">
												<option value=""> <?php echo _translate("-- Select --"); ?> </option>
												<option value="qualitative" <?php echo ($testResultAttribute['result_type'] == 'qualitative') ? "selected='selected'" : "" ?>><?php echo _translate("Qualitative"); ?></option>
												<option value="quantitative" <?php echo ($testResultAttribute['result_type'] == 'quantitative') ? "selected='selected'" : "" ?>><?php echo _translate("Quantitative"); ?></option>
											</select>
										</div>
									</div>
								</div>
							</div>
							<div class="row" id="qualitativeDiv" style="display:none;">
								<div class="col-md-12">
									<table aria-describedby="table" class="table table-bordered table-striped" aria-hidden="true">
										<tbody id="qualitativeTable">
											<?php
											if (!empty($testResultAttribute['result']) && count($testResultAttribute['result']) > 0) {
												foreach ($testResultAttribute['result'] as $qualKey => $row) { ?>
													<tr>
														<td class="text-center"><?php echo ($qualKey + 1); ?></td>
														<th scope="row">Result<span class="mandatory">*</span></th>
														<td><input type="text" name="resultConfig[result][]" value="<?php echo $row; ?>" id="result<?php echo ($qualKey + 1); ?>" class="form-control qualitativeResult" placeholder="Result" title="Please enter the result" /></td>
														<th scope="row">Result Interpretation<span class="mandatory">*</span></th>
														<td><input type="text" id="resultInterpretation<?php echo ($qualKey + 1); ?>" value="<?php echo $testResultAttribute['result_interpretation'][$qualKey]; ?>" name="resultConfig[result_interpretation][]" class="form-control qualitativeResult" placeholder="Enter result interpretation" title="Please enter result interpretation"></td>
														<td style="vertical-align:middle;text-align: center;width:100px;">
															<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="addResultRow('qualitativeTable');"><em class="fa-solid fa-plus"></em></a>&nbsp;
															<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeResultRow(this.parentNode.parentNode, 'qualitativeTable');"><em class="fa-solid fa-minus"></em></a>
														</td>
													</tr>
												<?php }
											} else { ?>
												<tr>
													<td class="text-center">1</td>
													<th scope="row">Result<span class="mandatory">*</span></th>
													<td><input type="text" name="resultConfig[result][]" id="result1" class="form-control" placeholder="Result" title="Please enter the result" /></td>
													<th scope="row">Result Interpretation<span class="mandatory">*</span></th>
													<td><input type="text" id="resultInterpretation1" name="resultConfig[result_interpretation][]" class="form-control" placeholder="Enter result interpretation" title="Please enter result interpretation"></td>
													<td style="vertical-align:middle;text-align: center;width:100px;">
														<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="addResultRow('qualitativeTable');"><em class="fa-solid fa-plus"></em></a>&nbsp;
														<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeResultRow(this.parentNode.parentNode, 'qualitativeTable');"><em class="fa-solid fa-minus"></em></a>
													</td>
												</tr>
											<?php } ?>
										</tbody>
									</table>
								</div>
							</div>

							<div class="row quantitativeDiv" style="display:none;">
								<div class="box-header">
									<h4 class="box-title "><?php echo _translate("Result Range"); ?></h4>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="highValue" class="col-lg-4 control-label"><?php echo _translate("High Value"); ?> <span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<input type="text" class="form-control forceNumeric quantitativeResult" value="<?php echo $testResultAttribute['high_value']; ?>" id="highValue" name="resultConfig[high_value]" placeholder='<?php echo _translate("Enter High Value"); ?>' title='<?php echo _translate("Please enter high value"); ?>' value="<?php echo $testResultAttribute['high_value'] ?? "" ?> " />
										</div>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="thresholdValue" class="col-lg-4 control-label"><?php echo _translate("Threshold Value"); ?> <span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<input type="text" class="form-control forceNumeric quantitativeResult" value="<?php echo $testResultAttribute['threshold_value']; ?>" id="thresholdValue" name="resultConfig[threshold_value]" placeholder='<?php echo _translate("Enter Threshold Value"); ?>' title='<?php echo _translate("Please enter threshold value"); ?>' value="<?php echo $testResultAttribute['threshold_value'] ?? "" ?>" />
										</div>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="lowValue" class="col-lg-4 control-label"><?php echo _translate("Low Value"); ?> <span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<input type="text" class="form-control forceNumeric quantitativeResult" value="<?php echo $testResultAttribute['low_value']; ?>" id="lowValue" name="resultConfig[low_value]" placeholder='<?php echo _translate("Enter Low Value"); ?>' title='<?php echo _translate("Please enter low value"); ?>' value="<?php echo $testResultAttribute['low_value'] ?? "" ?> " />
										</div>
									</div>
								</div>
							</div>
							<div class="row quantitativeDiv" style="display:none;">
								<div class="box-header">
									<h4 class="box-title "><?php echo _translate("Result Interpretation"); ?></h4>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="belowThreshold" class="col-lg-4 control-label"><?php echo _translate("Below Threshold"); ?> <span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<input type="text" class="form-control quantitativeResult" value="<?php echo $testResultAttribute['below_threshold']; ?>" id="belowThreshold" name="resultConfig[below_threshold]" placeholder='<?php echo _translate("Enter below threshold"); ?>' title='<?php echo _translate("Please enter below threshold"); ?>' value="<?php echo $testResultAttribute['below_threshold'] ?? "" ?> " />
										</div>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="atThreshold" class="col-lg-4 control-label"><?php echo _translate("At Threshold"); ?> <span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<input type="text" class="form-control quantitativeResult" value="<?php echo $testResultAttribute['at_threshold']; ?>" id="atThreshold" name="resultConfig[at_threshold]" placeholder='<?php echo _translate("Enter at threshold"); ?>' title='<?php echo _translate("Please enter at threshold"); ?>' value="<?php echo $testResultAttribute['at_threshold'] ?? "" ?> " />
										</div>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="aboveThreshold" class="col-lg-4 control-label"><?php echo _translate("Above Threshold"); ?> <span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<input type="text" class="form-control quantitativeResult" value="<?php echo $testResultAttribute['above_threshold']; ?>" id="aboveThreshold" name="resultConfig[above_threshold]" placeholder='<?php echo _translate("Enter above threshold"); ?>' title='<?php echo _translate("Please enter above threshold"); ?>' value="<?php echo $testResultAttribute['above_threshold'] ?? "" ?> " />
										</div>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="resultUnit" class="col-lg-4 control-label"><?php echo _translate("Test Result Unit"); ?> </label>
										<div class="col-lg-7">
											<select class="form-control quantitativeResult" id="testResultUnit" name="resultConfig[test_result_unit][]" placeholder='<?php echo _translate("Enter test result unit"); ?>' title='<?php echo _translate("Please enter test result unit"); ?>' multiple>
												<?= $general->generateSelectOptions($testResultUnitInfo, $testResultUnitId, '-- Select --') ?>
											</select>
										</div>
									</div>
								</div>
							</div>
							<div class="row quantitativeDiv" style="display:none;">
								<div class="col-md-12">
									<table aria-describedby="table" class="table table-bordered table-striped" aria-hidden="true">
										<tbody id="quantitativeTable">
											<?php
											if (!empty($testResultAttribute['quantitative_result']) && count($testResultAttribute['quantitative_result']) > 0) {
												foreach ($testResultAttribute['quantitative_result'] as $quanKey => $quanRow) { ?>
													<tr>
														<td class="text-center"><?php echo ($quanKey + 1); ?></td>
														<th scope="row">Result<span class="mandatory">*</span></th>
														<td><input type="text" name="resultConfig[quantitative_result][]" value="<?php echo $quanRow; ?>" id="quantitativeResult<?php echo ($quanKey + 1); ?>" class="form-control quantitativeResult" placeholder="Result" title="Please enter the result" /></td>
														<th scope="row">Result Interpretation<span class="mandatory">*</span></th>
														<td><input type="text" id="quantitativeResultInterpretation<?php echo ($quanKey + 1); ?>" value="<?php echo $testResultAttribute['quantitative_result_interpretation'][$quanKey]; ?>" name="resultConfig[quantitative_result_interpretation][]" class="form-control quantitativeResult" placeholder="Enter result interpretation" title="Please enter result interpretation"></td>
														<td style="vertical-align:middle;text-align: center;width:100px;">
															<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="addResultRow('quantitativeTable');"><em class="fa-solid fa-plus"></em></a>&nbsp;
															<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeResultRow(this.parentNode.parentNode, 'quantitativeTable');"><em class="fa-solid fa-minus"></em></a>
														</td>
													</tr>
												<?php }
											} else { ?>
												<tr>
													<td class="text-center">1</td>
													<th scope="row">Result<span class="mandatory">*</span></th>
													<td><input type="text" name="resultConfig[quantitative_result][]" id="quantitativeResult1" class="form-control quantitativeResult" placeholder="Result" title="Please enter the result" /></td>
													<th scope="row">Result Interpretation<span class="mandatory">*</span></th>
													<td><input type="text" id="quantitativeResultInterpretation1" name="resultConfig[quantitative_result_interpretation][]" class="form-control" placeholder="Enter result interpretation" title="Please enter result interpretation"></td>
													<td style="vertical-align:middle;text-align: center;width:100px;">
														<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="addResultRow('quantitativeTable');"><em class="fa-solid fa-plus"></em></a>&nbsp;
														<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeResultRow(this.parentNode.parentNode, 'quantitativeTable');"><em class="fa-solid fa-minus"></em></a>
													</td>
												</tr>
											<?php } ?>
										</tbody>
									</table>
								</div>
							</div>
						</div>

					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _translate("Submit"); ?></a>
						<a href="test-type.php" class="btn btn-default"> <?php echo _translate("Cancel"); ?></a>
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
	tableRowId = <?php echo $i + 1; ?>;
	testQualCounter = <?php echo count($testResultAttribute['result']); ?>;
	testQuanCounter = <?php echo count($testResultAttribute['quantitative_result']); ?>;
	var otherSectionNames = [];

	function addNewSection(section, rowId) {
		if (section != "" && ($.inArray(section, otherSectionNames) == -1))
			otherSectionNames.push(section);
	}

	$(document).ready(function() {
		addOtherSection();

		function addOtherSection() {
			$(".auto-complete-tbx").each(function() {
				if ($(this).val() != "" && ($.inArray($(this).val(), otherSectionNames) == -1))
					otherSectionNames.push($(this).val());
			});
		}

		$(".auto-complete-tbx").autocomplete({
			source: otherSectionNames
		});

		$('input').tooltip();
		checkResultType();
		$("#sampleType").select2({
			placeholder: "<?php echo _translate("Select Sample Type"); ?>"
		});
		$("#testingReason").select2({
			placeholder: "<?php echo _translate("Select Testing Reason"); ?>"
		});
		$("#testFailureReason").select2({
			placeholder: "<?php echo _translate("Select Test Failure Reason"); ?>"
		});
		$("#rejectionReason").select2({
			placeholder: "<?php echo _translate("Select Rejection Reason"); ?>"
		});
		$("#symptoms").select2({
			placeholder: "<?php echo _translate("Select Symptoms"); ?>"
		});
		$("#testResultUnit").select2({
			placeholder: "<?php echo _translate("Select Test Result Unit"); ?>"
		});

		$(document).on('click', '.remove-tag', function() {
			htmlVal = ($(this).parent().html());
			htmlVal = htmlVal.replace('<span class="remove-tag">x</span>', '');
			prevVal = $(this).parent().parent().prev(".fdropDown").val();
			curVal = prevVal.replace(htmlVal + ',', "");
			$(this).parent().parent().prev(".fdropDown").val(curVal);
			$(this).parent().remove();
		});

		let ajaxSelect = ["testMethod", "testCategory", "testingReason", "testFailureReason", "rejectionReason"];
		let _p = ["test methods", "test categories", "testing reason", "test failure reason", "rejection reason"];
		let _fi = ["test_method_id", "test_category_id", "test_reason_id", "test_failure_reason_id", "rejection_reason_id"];
		let _f = ["test_method_name", "test_category_name", "test_reason", "test_failure_reason", "rejection_reason_name"];
		let _t = ["r_generic_test_methods", "r_generic_test_categories", "r_generic_test_reasons", "r_generic_test_failure_reasons", "r_generic_sample_rejection_reasons"];
		let _as = ["test_method_status", "test_category_status", "test_reason_status", "test_failure_reason_status", "rejection_reason_status"];

		$(ajaxSelect).each(function(index, item) {
			$("#" + item).select2({
				placeholder: "Select " + _p[index],
				minimumInputLength: 0,
				width: '100%',
				allowClear: true,
				id: function(bond) {
					return bond._id;
				},
				ajax: {
					placeholder: "Type one or more character to search",
					url: "/includes/get-data-list-for-generic.php",
					dataType: 'json',
					delay: 250,
					data: function(params) {
						return {
							status: _as[index],
							fieldId: _fi[index],
							fieldName: _f[index],
							tableName: _t[index],
							q: params.term, // search term
							page: params.page
						};
					},
					processResults: function(data, params) {
						params.page = params.page || 1;
						return {
							results: data.result,
							pagination: {
								more: (params.page * 30) < data.total_count
							}
						};
					},
					//cache: true
				},
				escapeMarkup: function(markup) {
					return markup;
				}
			});
		});
	});

	function showTags(e, obj, cls) {
		let options = new Array();
		if (e.key === ',' || e.key === 'Enter') {
			var val = obj.value;
			if (val.length > 0) {
				var tag = val.split(',')[0].trim();
				$('.container' + cls).append('<div class="tag">' + tag + '<span class="remove-tag">x</span></div>');
				options.push(tag);
				obj.value = "";
				//obj.removeClass('isRequired');
			}

		}
		//console.log(options);
		for (let i = 0; i < options.length; i++) {
			$('#fdropDown' + cls).val($('#fdropDown' + cls).val() + options[i] + ',');

		}
	}

	function validateNow() {
		flag = deforayValidator.init({
			formId: 'cloneTestTypeForm'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('cloneTestTypeForm').submit();
		}
	}

	function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
		$.post("/includes/checkDuplicate.php", {
				tableName: tableName,
				fieldName: fieldName,
				value: obj.value.trim(),
				fnct: fnct,
				type: (fieldName == 'test_loinc_code') ? 'multiple' : '',
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
		rl = document.getElementById("attributeTable").rows.length;
		var a = document.getElementById("attributeTable").insertRow(rl);
		a.setAttribute("style", "display:none");
		var b = a.insertCell(0);
		var c = a.insertCell(1);
		var d = a.insertCell(2);
		var e = a.insertCell(3);
		var f = a.insertCell(4);
		var g = a.insertCell(5);
		var h = a.insertCell(6);

		h.setAttribute("align", "center");
		h.setAttribute("style", "vertical-align:middle");

		b.innerHTML = '<input type="text" name="fieldName[]" id="fieldName' + tableRowId + '" class="isRequired fieldName form-control" placeholder="<?php echo _translate('Field Name'); ?>" title="<?php echo _translate('Please enter field name'); ?>" onblur="checkDuplication(this, \'fieldName\');"/ ><input type="hidden" name="fieldId[]" id="fieldId' + tableRowId + '" class="form-control isRequired" />';
		c.innerHTML = '<input type="text" name="fieldCode[]" id="fieldCode' + tableRowId + '" class="form-control fieldCode isRequired" placeholder="<?php echo _translate("Field Code"); ?>" title="<?php echo _translate("Please enter field code"); ?>" onblur="checkDuplication(this, \'fieldCode\');" onchange="this.value=Utilities.toSnakeCase(this.value)"/>';
		d.innerHTML = '<select class="form-control isRequired" name="fieldType[]" id="fieldType' + tableRowId + '" title="<?php echo _translate('Please select the field type'); ?>" onchange="changeField(this, ' + tableRowId + ')">\
							<option value=""> <?php echo _translate("-- Select --"); ?> </option>\
							<option value="number"><?php echo _translate("Number"); ?></option>\
							<option value="text"><?php echo _translate("Text"); ?></option>\
							<option value="date"><?php echo _translate("Date"); ?></option>\
							<option value="dropdown"><?php echo _translate("Dropdown"); ?></option>\
							<option value="multiple"><?php echo _translate("multiple Dropdown"); ?></option>\
						</select><br>\
						<div class="tag-input dropDown' + tableRowId + '" style="display:none;"><input type="text" name="dropDown[]" id="dropDown' + tableRowId + '" onkeyup="showTags(event,this,' + tableRowId + ')" class="tag-input-field form-control" placeholder="Enter options..." /><input type="hidden" class="fdropDown" id="fdropDown' + tableRowId + '" name="fdropDown[]" /><div class="tag-container container' + tableRowId + '"></div></div>';
		e.innerHTML = '<select class="form-control isRequired" name="mandatoryField[]" id="mandatoryField' + tableRowId + '" title="<?php echo _translate('Please select is it mandatory'); ?>">\
                            <option value="yes"><?php echo _translate("Yes"); ?></option>\
                            <option value="no" selected><?php echo _translate("No"); ?></option>\
                        </select>';
		f.innerHTML = '<select class="form-control isRequired" name="section[]" id="section' + tableRowId + '" title="<?php echo _translate('Please select the section'); ?>" onchange="checkSection(' + tableRowId + ')">\
						<option value=""> <?php echo _translate("-- Select --"); ?> </option>\
						<option value="facilitySection"><?php echo _translate("Facility"); ?></option>\
						<option value="patientSection"><?php echo _translate("Patient"); ?></option>\
						<option value="specimenSection"><?php echo _translate("Specimen"); ?></option>\
						<option value="labSection"><?php echo _translate("Lab"); ?></option>\
						<option value="otherSection"><?php echo _translate("Other"); ?></option>\
					</select>\
					<input type="text" name="sectionOther[]" id="sectionOther' + tableRowId + '" class="form-control auto-complete-tbx" onchange="addNewSection(this.value)" placeholder="<?php echo _translate("Section Other"); ?>" title="<?php echo _translate("Please enter section other"); ?>" style="display:none;"/>';
		g.innerHTML = '<input type="text" name="fieldOrder[]" id="fieldOrder' + tableRowId + '" class="form-control forceNumeric" placeholder="<?php echo _translate("Field Order"); ?>" title="<?php echo _translate("Please enter field order"); ?>" />';
		h.innerHTML = '<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;&nbsp;<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>';
		$(a).fadeIn(800);

		$(".auto-complete-tbx").autocomplete({
			source: otherSectionNames
		});

		generateRandomFieldId(tableRowId);
		tableRowId++;
	}

	function removeAttributeRow(el) {
		$(el).fadeOut("slow", function() {
			el.parentNode.removeChild(el);
			rl = document.getElementById("attributeTable").rows.length;
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

	function checkResultType() {
		resultType = $("#resultType").val();
		if (resultType == 'qualitative') {
			$("#qualitativeDiv").show();
			$(".quantitativeDiv").hide();
			$(".qualitativeResult").addClass("isRequired");
			$(".quantitativeResult").removeClass("isRequired");
			$('.quantitativeResult').each(function() {
				$(this).val('');
			});
		} else if (resultType == 'quantitative') {
			$("#qualitativeDiv").hide();
			$(".quantitativeDiv").show();
			$(".qualitativeResult").removeClass("isRequired");
			$(".quantitativeResult").addClass("isRequired");
			$("#qualitativeResult").val('');
		} else {
			$("#qualitativeDiv, .quantitativeDiv").hide();
			$(".qualitativeResult, .quantitativeResult").removeClass("isRequired");
			$("#qualitativeResult, .quantitativeResult").val('');
			$('.quantitativeResult').each(function() {
				$(this).val('');
			});
		}
	}

	function checkSection(rowId) {
		sectionVal = $("#section" + rowId).val();
		if (sectionVal == "otherSection") {
			$("#sectionOther" + rowId).addClass("isRequired");
			$("#sectionOther" + rowId).show();
		} else {
			$("#sectionOther" + rowId).hide();
			$("#sectionOther" + rowId).removeClass("isRequired");
			$("#sectionOther" + rowId).val('');
			addOtherSection();
		}
	}

	function generateRandomFieldId(rowId) {
		$("#fieldId" + rowId).val("_" + Utilities.generateRandomString(16));
	}

	function addResultRow(table) {
		let rowString = '';

		if (table == 'qualitativeTable') {
			testQualCounter++;
			rowString = `<tr>
				<td class="text-center">` + testQualCounter + `</td>
				<th scope="row">Result<span class="mandatory">*</span></th>
				<td><input type="text" name="resultConfig[result][]" id="result` + testQualCounter + `" class="form-control qualitativeResult isRequired" placeholder="Result" title="Please enter the result" /></td>
				<th scope="row">Result Interpretation<span class="mandatory">*</span></th>
				<td><input type="text" id="resultInterpretation` + testQualCounter + `" name="resultConfig[result_interpretation][]" class="form-control qualitativeResult isRequired" placeholder="Enter result interpretation" title="Please enter result interpretation"></td>
				<td style="vertical-align:middle;text-align: center;width:100px;">
					<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="addResultRow('qualitativeTable');"><em class="fa-solid fa-plus"></em></a>&nbsp;
					<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeResultRow(this.parentNode.parentNode, 'qualitativeTable');"><em class="fa-solid fa-minus"></em></a>
				</td>
			</tr>`;
		} else if (table == 'quantitativeTable') {
			testQuanCounter++;
			rowString = `<tr>
				<td class="text-center">` + testQuanCounter + `</td>
				<th scope="row">Result<span class="mandatory">*</span></th>
				<td><input type="text" name="resultConfig[quantitative_result][]" id="quantitativeResult` + testQuanCounter + `" class="form-control quantitativeResult isRequired" placeholder="Result" title="Please enter the result" /></td>
				<th scope="row">Result Interpretation<span class="mandatory">*</span></th>
				<td><input type="text" id="quantitativeResultInterpretation` + testQuanCounter + `" name="resultConfig[quantitative_result_interpretation][]" class="form-control quantitativeResult isRequired" placeholder="Enter result interpretation" title="Please enter result interpretation"></td>
				<td style="vertical-align:middle;text-align: center;width:100px;">
					<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="addResultRow('quantitativeTable');"><em class="fa-solid fa-plus"></em></a>&nbsp;
					<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeResultRow(this.parentNode.parentNode, 'quantitativeTable');"><em class="fa-solid fa-minus"></em></a>
				</td>
			</tr>`;
		}
		$("#" + table).append(rowString);
	}

	function removeResultRow(el, table) {
		$(el).fadeOut("slow", function() {
			el.parentNode.removeChild(el);
			rl = document.getElementById(table).rows.length;
			if (rl == 0) {
				testQuanCounter = 0;
				addResultRow(table);
			}
		});
	}


	function changeField(obj, i) {
		(obj.value == 'dropdown' || obj.value == 'multiple') ? ($('.dropDown' + i).show()) : ($('.dropDown' + i).hide(), $('#dropDown' + i).removeClass('isRequired'));
	}

	function alphanumericValidation(shortCode) {
		/*var regEx = /^[0-9a-zA-Z]+$/;
		if (shortCode.match(regEx)) {
			return true;
		} else {
			alert("Please enter letters and numbers only in short code.");
			return false;
		}*/
		// Convert to uppercase
		shortCode = shortCode.toUpperCase();

		// Remove all special characters and spaces, except hyphens
		shortCode = shortCode.replace(/[^A-Z0-9-]/g, '');

		$('#testShortCode').val(shortCode);
	}
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
