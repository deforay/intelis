<?php

namespace App\Services;

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;
use Psr\Http\Message\ServerRequestInterface;

require_once APPLICATION_PATH . '/header.php';

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GenericTestsService $generic */
$generic = ContainerRegistry::get(GenericTestsService::class);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
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


$i = 0;
foreach ($testResultAttribute['result_type'] as $key => $r) {
	$i++;
}
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
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span>
					<?php echo _translate("indicates required fields"); ?> &nbsp;</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='editTestTypeForm' id='editTestTypeForm'
					autocomplete="off" action="editTestTypeHelper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="testStandardName"
										class="col-lg-4 control-label"><?php echo _translate("Test Standard Name"); ?>
										<span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="testStandardName"
											name="testStandardName"
											placeholder='<?php echo _translate("Test Standard Name"); ?>'
											title='<?php echo _translate("Please enter standard name"); ?>'
											value="<?php echo $testTypeInfo['test_standard_name']; ?>"
											onblur="checkNameValidation('r_test_types','test_standard_name',this,'<?php echo "test_type_id##" . $testTypeInfo['test_type_id']; ?>','<?php echo _translate("This test standard name that you entered already exists.Try another name"); ?>',null)" />
										<input type="hidden" name="testTypeId" id="testTypeId"
											value="<?php echo base64_encode((string) $testTypeInfo['test_type_id']); ?>" />
									</div>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="testGenericName"
										class="col-lg-4 control-label"><?php echo _translate("Test Generic Name"); ?>
										<span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="testGenericName"
											name="testGenericName"
											placeholder='<?php echo _translate("Test Generic Name"); ?>'
											title='<?php echo _translate("Please enter the test generic name"); ?>'
											value="<?php echo $testTypeInfo['test_generic_name']; ?>"
											onblur="checkNameValidation('r_test_types','test_generic_name',this,'<?php echo "test_type_id##" . $testTypeInfo['test_type_id']; ?>','<?php echo _translate("This test generic name that you entered already exists.Try another name"); ?>',null)" />
									</div>
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="testShortCode"
										class="col-lg-4 control-label"><?php echo _translate("Test Short Code"); ?>
										<span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="testShortCode"
											name="testShortCode"
											placeholder='<?php echo _translate("Test Short Code"); ?>'
											title='<?php echo _translate("Please enter short code"); ?>'
											onblur="checkNameValidation('r_test_types','test_short_code',this,'<?php echo "test_type_id##" . $testTypeInfo['test_type_id']; ?>','<?php echo _translate("This test short code that you entered already exists.Try another code"); ?>',null)"
											value="<?php echo $testTypeInfo['test_short_code']; ?>"
											onchange="alphanumericValidation(this.value);" />
									</div>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="testLoincCode"
										class="col-lg-4 control-label"><?php echo _translate("LOINC Codes"); ?></label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="testLoincCode" name="testLoincCode"
											placeholder='<?php echo _translate("Test LOINC Code"); ?>'
											title='<?php echo _translate("Please enter test loinc code"); ?>'
											value="<?php echo $testTypeInfo['test_loinc_code']; ?>"
											onblur="checkNameValidation('r_test_types','test_loinc_code',this,'<?php echo "test_type_id##" . $testTypeInfo['test_type_id']; ?>','<?php echo _translate("This test loinc code that you entered already exists.Try another code"); ?>',null)"
											value="<?php echo $testTypeInfo['test_loinc_code']; ?>" />
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="testMethod"
										class="col-lg-4 control-label"><?php echo _translate("Test Methods"); ?> <span
											class="mandatory">*</span> <em class="fas fa-edit"></em></label>
									<div class="col-lg-7">
										<select class="form-control isRequired editableSelect" name='testMethod[]'
											id='testMethod'
											title="<?php echo _translate('Please select the test methods'); ?>"
											multiple>
											<?= $general->generateSelectOptions($testMethodInfo, $testMethodId, '-- Select --') ?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="testCategory"
										class="col-lg-4 control-label"><?php echo _translate("Test Category"); ?> <span
											class="mandatory">*</span> <em class="fas fa-edit"></em></label>
									<div class="col-lg-7">
										<select class="form-control isRequired editableSelect" name='testCategory'
											id='testCategory'
											title="<?php echo _translate('Please select the test categories'); ?>">
											<?= $general->generateSelectOptions($categoryInfo, $testTypeInfo['test_category'], '-- Select --') ?>
										</select>
									</div>
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="sampleType"
										class="col-lg-4 control-label"><?php echo _translate("Sample/Specimen Types"); ?>
										<span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='sampleType[]' id='sampleType'
											title="<?php echo _translate('Please select the sample type'); ?>" multiple>
											<?= $general->generateSelectOptions($sampleTypeInfo, $testSampleId, '-- Select --') ?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="testingReason"
										class="col-lg-4 control-label"><?php echo _translate("Reasons for Testing"); ?>
										<span class="mandatory">*</span> <em class="fas fa-edit"></em></label>
									<div class="col-lg-7">
										<select class="form-control isRequired editableSelect" name='testingReason[]'
											id='testingReason'
											title="<?php echo _translate('Please select the testing reason'); ?>"
											multiple>
											<?= $general->generateSelectOptions($testReasonInfo, $testReasonId, '-- Select --') ?>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="testFailureReason"
										class="col-lg-4 control-label"><?php echo _translate("Test Failure Reasons"); ?>
										<span class="mandatory">*</span> <em class="fas fa-edit"></em></label>
									<div class="col-lg-7">
										<select class="form-control isRequired editableSelect"
											name='testFailureReason[]' id='testFailureReason'
											title="<?php echo _translate('Please select the test failure reason'); ?>"
											multiple>
											<?= $general->generateSelectOptions($testFailureReasonInfo, $testFailureReasonId, '-- Select --') ?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="rejectionReason"
										class="col-lg-4 control-label"><?php echo _translate("Sample Rejection Reasons"); ?>
										<span class="mandatory">*</span> <em class="fas fa-edit"></em></label>
									<div class="col-lg-7">
										<select class="form-control isRequired editableSelect" name='rejectionReason[]'
											id='rejectionReason'
											title="<?php echo _translate('Please select the sample rejection reason'); ?>"
											multiple>
											<?= $general->generateSelectOptions($sampleRejectionReasonInfo, $rejectionReasonId, '-- Select --') ?>
										</select>
									</div>
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="symptoms"
										class="col-lg-4 control-label"><?php echo _translate("Symptoms"); ?></label>
									<div class="col-lg-7">
										<select name='symptoms[]' id='symptoms'
											title="<?php echo _translate('Please select the symptoms'); ?>" multiple>
											<?= $general->generateSelectOptions($symptomInfo, $testSymptomsId, '-- Select --') ?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="status"
										class="col-lg-4 control-label"><?php echo _translate("Status"); ?> <span
											class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='status' id='status'
											title="<?php echo _translate('Please select the status'); ?>">
											<option value="active" <?php echo ($testTypeInfo['test_status'] == 'active') ? "selected='selected'" : "" ?>><?php echo _translate("Active"); ?>
											</option>
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
							<table aria-describedby="table" border="0"
								class="table table-striped table-bordered table-condensed" aria-hidden="true"
								style="width:100%;">
								<thead>
									<tr>
										<th style="text-align:center;width:20%;"><?php echo _translate("Field Name"); ?>
											<span class="mandatory">*</span>
										</th>
										<th style="text-align:center;width:15%;"><?php echo _translate("Field Code"); ?>
											<span class="mandatory">*</span>
										</th>
										<th style="text-align:center;width:15%;"><?php echo _translate("Field Type"); ?>
											<span class="mandatory">*</span>
										</th>
										<th style="text-align:center;width:10%;">
											<?php echo _translate("Is it Mandatory?"); ?> <span
												class="mandatory">*</span>
										</th>
										<th style="text-align:center;width:20%;"><?php echo _translate("Section"); ?>
											<span class="mandatory">*</span>
										</th>
										<th style="text-align:center;width:10%;">
											<?php echo _translate("Field Order"); ?>
										</th>
										<th style="text-align:center;width:10%;"><?php echo _translate("Action"); ?>
										</th>
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
															<input type="text" name="fieldName[]" id="fieldName<?php echo $i ?>"
																data-attributenumber="<?= $i ?>"
																class="form-control fieldName isRequired"
																placeholder='<?php echo _translate("Field Name"); ?>'
																title='<?php echo _translate("Please enter field name"); ?>'
																onblur="checkDuplication(this, 'fieldName');"
																value="<?php echo $testAttribute['field_name']; ?>" />
															<input type="hidden" name="fieldId[]" id="fieldId<?php echo $i ?>"
																class="form-control isRequired"
																value="<?php echo $testAttributeId; ?>" />
														</td>
														<td align="center" style="vertical-align:middle;">
															<input type="text" name="fieldCode[]" id="fieldCode<?php echo $i; ?>"
																data-attributenumber="<?= $i ?>"
																class="form-control fieldCode isRequired dataClass"
																placeholder="<?php echo _translate("Field Code"); ?>"
																title="<?php echo _translate("Please enter field code"); ?>"
																onblur="checkDuplication(this, \'fieldCode\');"
																value="<?php echo $testAttribute['field_code']; ?>"
																onchange="this.value=Utilities.toSnakeCase(this.value)" />
														</td>
														<td align="center" style="vertical-align:middle;padding-top: 20px;">
															<select class="form-control isRequired" name="fieldType[]"
																id="fieldType<?php echo $i ?>" data-attributenumber="<?= $i ?>"
																title="<?php echo _translate('Please select the field type'); ?>"
																onchange="changeField(this, <?php echo $i ?>)">
																<option value=""> <?php echo _translate("-- Select --"); ?> </option>
																<option value="number" <?php echo ($testAttribute['field_type'] == 'number') ? "selected='selected'" : "" ?>><?php echo _translate("Number"); ?></option>
																<option value="text" <?php echo ($testAttribute['field_type'] == 'text') ? "selected='selected'" : "" ?>><?php echo _translate("Text"); ?>
																</option>
																<option value="date" <?php echo ($testAttribute['field_type'] == 'date') ? "selected='selected'" : "" ?>><?php echo _translate("Date"); ?>
																</option>
																<option value="dropdown" <?php echo ($testAttribute['field_type'] == 'dropdown') ? "selected='selected'" : "" ?>><?php echo _translate("Dropdown"); ?></option>
																<option value="multiple" <?php echo ($testAttribute['field_type'] == 'multiple') ? "selected='selected'" : "" ?>><?php echo _translate("Multiselect Dropdown"); ?></option>
															</select><br>
															<div class="tag-input dropDown<?php echo $i ?>"
																style="<?php echo ($testAttribute['field_type'] == 'multiple' || $testAttribute['field_type'] == 'dropdown') ? "" : "display:none;" ?>">
																<input type="text" name="dropDown[]" id="dropDown<?php echo $i ?>"
																	onkeyup="showTags(event,this,'<?php echo $i ?>')"
																	class="tag-input-field form-control"
																	placeholder="<?php echo _translate('Enter options...'); ?>"
																	title="<?php echo _translate('Please enter the options'); ?>" />
																<input type="hidden"
																	value="<?php echo (empty($testAttribute['dropdown_options'])) ? "" : $testAttribute['dropdown_options'] . ',' ?>"
																	id="fdropDown<?php echo $i ?>" name="fdropDown[]"
																	class="fdropDown" />
																<div class="tag-container container<?php echo $i ?>">
																	<?php
																	if (!empty($testAttribute['dropdown_options'])) {
																		$val = explode(",", (string) $testAttribute['dropdown_options']);
																		foreach ($val as $v) {
																			?>
																			<div class="tag"><?php echo $v; ?><span class="remove-tag">x</span>
																			</div>
																		<?php }
																	} ?>
																</div>
															</div>
														</td>
														<td align="center" style="vertical-align:middle;">
															<select class="form-control isRequired" name="mandatoryField[]"
																id="mandatoryField<?php echo $i ?>"
																title="<?php echo _translate('Please select is it mandatory'); ?>">
																<option value="yes" <?php echo ($testAttribute['mandatory_field'] == 'yes') ? "selected='selected'" : "" ?>><?php echo _translate("Yes"); ?></option>
																<option value="no" <?php echo ($testAttribute['mandatory_field'] == 'no') ? "selected='selected'" : "" ?>><?php echo _translate("No"); ?>
																</option>
															</select>
														</td>
														<td align="center" style="vertical-align:middle;">
															<select class="form-control isRequired" name="section[]"
																id="section<?php echo $i ?>"
																title="<?php echo _translate('Please select the section'); ?>"
																onchange="checkSection('<?php echo $i ?>')">
																<option value=""> <?php echo _translate("-- Select --"); ?> </option>
																<option value="facilitySection" <?php echo ($testAttribute['section'] == 'facilitySection') ? "selected='selected'" : "" ?>><?php echo _translate("Facility"); ?>
																</option>
																<option value="patientSection" <?php echo ($testAttribute['section'] == 'patientSection') ? "selected='selected'" : "" ?>><?php echo _translate("Patient"); ?>
																</option>
																<option value="specimenSection" <?php echo ($testAttribute['section'] == 'specimenSection') ? "selected='selected'" : "" ?>><?php echo _translate("Specimen"); ?>
																</option>
																<option value="labSection" <?php echo ($testAttribute['section'] == 'labSection') ? "selected='selected'" : "" ?>><?php echo _translate("Lab"); ?></option>
																<option value="otherSection" <?php echo ($testAttribute['section'] == 'otherSection') ? "selected='selected'" : "" ?>><?php echo _translate("Other"); ?></option>
															</select>
															<input type="text" name="sectionOther[]" id="sectionOther<?php echo $i ?>"
																onchange="addNewSection(this.value)"
																class="form-control auto-complete-tbx"
																placeholder='<?php echo _translate("Section Other"); ?>'
																title='<?php echo _translate("Please enter section other"); ?>'
																style="<?php echo ($testAttribute['section'] == 'otherSection') ? "" : "display:none;" ?>"
																value="<?php echo ($testAttribute['section'] == 'otherSection') ? $testAttribute['section_name'] : "" ?>" />
														</td>
														<td align="center" style="vertical-align:middle;">
															<input type="text" name="fieldOrder[]" id="fieldOrder1"
																class="form-control forceNumeric"
																placeholder="<?php echo _translate("Field Order"); ?>"
																title="<?php echo _translate("Please enter field order"); ?>"
																value="<?php echo $testAttribute['field_order']; ?>" />
														</td>
														<td align="center" style="vertical-align:middle;">
															<a class="btn btn-xs btn-primary" href="javascript:void(0);"
																onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;&nbsp;<a
																class="btn btn-xs btn-default" href="javascript:void(0);"
																onclick="removeAttributeRow(this.parentNode.parentNode);"><em
																	class="fa-solid fa-minus"></em></a>
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
																<input type="text" name="fieldName[]" data-attributenumber="<?= $i ?>"
																	id="fieldName<?php echo $i ?>" class="form-control fieldName isRequired"
																	placeholder='<?php echo _translate("Field Name"); ?>'
																	title='<?php echo _translate("Please enter field name"); ?>'
																	onblur="checkDuplication(this, 'fieldName');"
																	value="<?php echo $testAttribute['field_name']; ?>" />
																<input type="hidden" name="fieldId[]" id="fieldId<?php echo $i ?>"
																	class="form-control" value="<?php echo $otherAttributeId; ?>" />
															</td>
															<td align="center" style="vertical-align:middle;">
																<input type="text" name="fieldCode[]" data-attributenumber="<?= $i ?>"
																	id="fieldCode<?php echo $i; ?>"
																	class="form-control fieldCode isRequired"
																	placeholder="<?php echo _translate("Field Code"); ?>"
																	title="<?php echo _translate("Please enter field code"); ?>"
																	onblur="checkDuplication(this, \'fieldCode\');"
																	value="<?php echo $testAttribute['field_code']; ?>"
																	onchange="this.value=Utilities.toSnakeCase(this.value)" />
															</td>
															<td align="center" style="vertical-align:middle;padding-top: 20px;">
																<select class="form-control isRequired" data-attributenumber="<?= $i ?>"
																	name="fieldType[]" id="fieldType<?php echo $i ?>"
																	title="<?php echo _translate('Please select the field type'); ?>"
																	onchange="changeField(this, <?php echo $i ?>)">
																	<option value=""> <?php echo _translate("-- Select --"); ?> </option>
																	<option value="number" <?php echo ($testAttribute['field_type'] == 'number') ? "selected='selected'" : "" ?>><?php echo _translate("Number"); ?></option>
																	<option value="text" <?php echo ($testAttribute['field_type'] == 'text') ? "selected='selected'" : "" ?>><?php echo _translate("Text"); ?>
																	</option>
																	<option value="date" <?php echo ($testAttribute['field_type'] == 'date') ? "selected='selected'" : "" ?>><?php echo _translate("Date"); ?>
																	</option>
																	<option value="dropdown" <?php echo ($testAttribute['field_type'] == 'dropdown') ? "selected='selected'" : "" ?>><?php echo _translate("Dropdown"); ?></option>
																	<option value="multiple" <?php echo ($testAttribute['field_type'] == 'multiple') ? "selected='selected'" : "" ?>><?php echo _translate("Multiselect Dropdown"); ?></option>
																</select><br>
																<div class="tag-input dropDown<?php echo $i ?>"
																	style="<?php echo ($testAttribute['field_type'] == 'multiple' || $testAttribute['field_type'] == 'dropdown') ? "" : "display:none;" ?>">
																	<input type="text" name="dropDown[]" id="dropDown<?php echo $i ?>"
																		onkeyup="showTags(event,this,'<?php echo $i ?>')"
																		class="tag-input-field form-control"
																		placeholder="<?php echo _translate('Enter options...'); ?>"
																		title="<?php echo _translate('Please enter the options'); ?>" />
																	<input type="hidden"
																		value="<?php echo (empty($testAttribute['dropdown_options'])) ? "" : $testAttribute['dropdown_options'] . ',' ?>"
																		id="fdropDown<?php echo $i ?>" name="fdropDown[]"
																		class="fdropDown" />
																	<div class="tag-container container<?php echo $i ?>">
																		<?php
																		if (!empty($testAttribute['dropdown_options'])) {
																			$val = explode(",", (string) $testAttribute['dropdown_options']);
																			foreach ($val as $v) {
																				?>
																				<div class="tag"><?php echo $v; ?><span class="remove-tag">x</span>
																				</div>
																			<?php }
																		} ?>
																	</div>
																</div>
															</td>
															<td align="center" style="vertical-align:middle;">
																<select class="form-control isRequired" name="mandatoryField[]"
																	id="mandatoryField<?php echo $i ?>"
																	title="<?php echo _translate('Please select is it mandatory'); ?>">
																	<option value="yes" <?php echo ($testAttribute['mandatory_field'] == 'yes') ? "selected='selected'" : "" ?>><?php echo _translate("Yes"); ?></option>
																	<option value="no" <?php echo ($testAttribute['mandatory_field'] == 'no') ? "selected='selected'" : "" ?>><?php echo _translate("No"); ?>
																	</option>
																</select>
															</td>
															<td align="center" style="vertical-align:middle;">
																<select class="form-control isRequired" name="section[]"
																	id="section<?php echo $i ?>"
																	title="<?php echo _translate('Please select the section'); ?>"
																	onchange="checkSection('<?php echo $i ?>')">
																	<option value=""> <?php echo _translate("-- Select --"); ?> </option>
																	<option value="facilitySection" <?php echo ($testAttribute['section'] == 'facilitySection') ? "selected='selected'" : "" ?>><?php echo _translate("Facility"); ?>
																	</option>
																	<option value="patientSection" <?php echo ($testAttribute['section'] == 'patientSection') ? "selected='selected'" : "" ?>><?php echo _translate("Patient"); ?>
																	</option>
																	<option value="specimenSection" <?php echo ($testAttribute['section'] == 'specimenSection') ? "selected='selected'" : "" ?>><?php echo _translate("Specimen"); ?>
																	</option>
																	<option value="labSection" <?php echo ($testAttribute['section'] == 'labSection') ? "selected='selected'" : "" ?>><?php echo _translate("Lab"); ?></option>
																	<option value="otherSection" <?php echo ($testAttribute['section'] == 'otherSection') ? "selected='selected'" : "" ?>><?php echo _translate("Other"); ?></option>
																</select>
																<input type="text" name="sectionOther[]" id="sectionOther<?php echo $i ?>"
																	onchange="addNewSection(this.value)"
																	class="form-control auto-complete-tbx"
																	placeholder='<?php echo _translate("Section Other"); ?>'
																	title='<?php echo _translate("Please enter section other"); ?>'
																	style="<?php echo ($testAttribute['section'] == 'otherSection') ? "" : "display:none;" ?>"
																	value="<?php echo ($testAttribute['section'] == 'otherSection') ? $testAttribute['section_name'] : "" ?>" />
															</td>
															<td align="center" style="vertical-align:middle;">
																<input type="text" name="fieldOrder[]" id="fieldOrder1"
																	class="form-control forceNumeric"
																	placeholder="<?php echo _translate("Field Order"); ?>"
																	title="<?php echo _translate("Please enter field order"); ?>"
																	value="<?php echo $testAttribute['field_order']; ?>" />
															</td>
															<td align="center" style="vertical-align:middle;">
																<a class="btn btn-xs btn-primary" href="javascript:void(0);"
																	onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;&nbsp;<a
																	class="btn btn-xs btn-default" href="javascript:void(0);"
																	onclick="removeAttributeRow(this.parentNode.parentNode);"><em
																		class="fa-solid fa-minus"></em></a>
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
												<input type="text" name="fieldName[]" id="fieldName1"
													data-attributenumber="<?= $i ?>"
													class="form-control fieldName isRequired"
													placeholder='<?php echo _translate("Field Name"); ?>'
													title='<?php echo _translate("Please enter field name"); ?>'
													onblur="checkDuplication(this, 'fieldName');" />
												<input type="hidden" name="fieldId[]" id="fieldId1" class="form-control" />
											</td>
											<td align="center">
												<input type="text" name="fieldCode[]" id="fieldCode1"
													data-attributenumber="<?= $i ?>"
													class="form-control fieldCode isRequired"
													placeholder="<?php echo _translate("Field Code"); ?>"
													title="<?php echo _translate("Please enter field code"); ?>"
													onblur="checkDuplication(this, \'fieldCode\');"
													onchange="this.value=Utilities.toSnakeCase(this.value)" />
											</td>
											<td>
												<select class="form-control isRequired" data-attributenumber="<?= $i ?>"
													name="fieldType[]" id="fieldType1"
													title="<?php echo _translate('Please select the field type'); ?>"
													onchange="changeField(this, 1)">
													<option value=""> <?php echo _translate("-- Select --"); ?> </option>
													<option value="number"><?php echo _translate("Number"); ?></option>
													<option value="text"><?php echo _translate("Text"); ?></option>
													<option value="date"><?php echo _translate("Date"); ?></option>
													<option value="dropdown"><?php echo _translate("Dropdown"); ?></option>
													<option value="multiple">
														<?php echo _translate("Multiselect Dropdown"); ?>
													</option>
												</select><br>
												<!--<textarea name="dropDown[]" id="dropDown1" class="form-control" placeholder='<?php echo _translate("Drop down values as , separated"); ?>' title='<?php echo _translate("Please drop down values as comma separated"); ?>' style="display:none;"></textarea>-->
												<div class="tag-input dropDown1" style="display:none;">
													<input type="text" name="dropDown[]" id="dropDown1"
														onkeyup="showTags(event,this,'1')"
														class="tag-input-field form-control"
														placeholder="<?php echo _translate('Enter options...'); ?>"
														title="<?php echo _translate('Please enter the options'); ?>" />
													<input type="hidden" class="fdropDown" id="fdropDown1"
														name="fdropDown[]" />
													<div class="tag-container container1">
													</div>
												</div>
											</td>
											<td>
												<select class="form-control isRequired" name="mandatoryField[]"
													id="mandatoryField1"
													title="<?php echo _translate('Please select is it mandatory'); ?>">
													<option value="yes"><?php echo _translate("Yes"); ?></option>
													<option value="no" selected><?php echo _translate("No"); ?></option>
												</select>
											</td>
											<td>
												<select class="form-control isRequired" name="section[]" id="section1"
													title="<?php echo _translate('Please select the section'); ?>"
													onchange="checkSection('1')">
													<option value=""> <?php echo _translate("-- Select --"); ?> </option>
													<option value="facilitySection"><?php echo _translate("Facility"); ?>
													</option>
													<option value="patientSection"><?php echo _translate("Patient"); ?>
													</option>
													<option value="specimenSection"><?php echo _translate("Specimen"); ?>
													</option>
													<option value="labSection"><?php echo _translate("Lab"); ?></option>
													<option value="otherSection"><?php echo _translate("Other"); ?></option>
												</select>
												<input type="text" name="sectionOther[]" id="sectionOther1"
													class="form-control auto-complete-tbx"
													onchange="addNewSection(this.value)"
													placeholder='<?php echo _translate("Section Other"); ?>'
													title='<?php echo _translate("Please enter section other"); ?>'
													style="display:none;" />
											</td>
											<td>
												<input type="text" name="fieldOrder[]" id="fieldOrder1"
													class="form-control forceNumeric"
													placeholder="<?php echo _translate("Field Order"); ?>"
													title="<?php echo _translate("Please enter field order"); ?>" />
											</td>
											<td align="center" style="vertical-align:middle;">
												<a class="btn btn-xs btn-primary" href="javascript:void(0);"
													onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;&nbsp;<a
													class="btn btn-xs btn-default" href="javascript:void(0);"
													onclick="removeAttributeRow(this.parentNode.parentNode);"><em
														class="fa-solid fa-minus"></em></a>
											</td>
										</tr>
									<?php } ?>
								</tbody>
							</table>
						</div>
						<div class="box-header row">
							<div class="col-md-4">
								<h3 class="box-title "><?php echo _translate("Test Results Configuration"); ?></h3>
							</div>
							<div class="col-md-8">
								<label for="resultUnit"
									class="col-lg-4 control-label"><?php echo _translate("Test Result Unit"); ?>
								</label>
								<div class="col-lg-7">
									<!--<select class="quantitativeResult" id="testResultUnit" name="resultConfig[test_result_unit][]" placeholder='< ?php echo _translate("Enter test result unit"); ?>' title='< ?php echo _translate("Please enter test result unit"); ?>' multiple>-->
									<select id="testResultUnit" name="resultConfig[test_result_unit][]"
										placeholder='<?php echo _translate("Enter test result unit"); ?>'
										title='<?php echo _translate("Please enter test result unit"); ?>' multiple>
										<?= $general->generateSelectOptions($testResultUnitInfo, $testResultUnitId, true) ?>
									</select>
								</div>
							</div>
						</div>

						<div class="box-body">
							<table style="width: 100%;margin: 0 auto;" border="1"
								class="table table-bordered table-striped clearfix" id="vlSampleTable">
								<tbody>
									<?php if (isset($testResultAttribute['sub_test_name']) && !empty($testResultAttribute['sub_test_name'])) {
										$label = [];
										$n = count($testResultAttribute['sub_test_name']);
										foreach ($testResultAttribute['sub_test_name'] as $key => $resultName) { ?>
											<tr class="result-type">
												<td>
													<table style="width: 100%;margin: 0 auto;" border="1"
														class="table table-bordered table-striped clearfix">
														<tr>
															<td class="<?php echo (isset($resultName) && !empty($resultName)) ? '' : 'hide'; ?> firstSubTest"
																style="width:20%;">
																<lable for="resultSubGroup<?php echo $key; ?>"
																	class="form-label-control">Enter the test name</lable>
															</td>
															<td class="<?php echo (isset($resultName) && !empty($resultName)) ? '' : 'hide'; ?> firstSubTest"
																style="width:30%;">
																<input type="text"
																	name="resultConfig[sub_test_name][<?php echo $key; ?>]"
																	id="resultSubGroup<?php echo $key; ?>"
																	value="<?php echo (isset($resultName) && !empty($resultName)) ? $resultName : 'Test Level 1'; ?>"
																	class="form-control input-sm firstSubTest-input"
																	placeholder="Enter the sub test name"
																	title="Please ener the sub test name for <?php echo $key; ?> row" />
															</td>
															<td style="width:20%;">
																<lable for="testType<?php echo $key; ?>"
																	class="form-label-control">Select Result Type</lable>
															</td>
															<td style="width:30%;">
																<select type="text"
																	name="resultConfig[result_type][<?php echo $key; ?>]"
																	id="testType<?php echo $key; ?>"
																	class="form-control input-sm"
																	title="Please select the type of result"
																	onchange="setResultType(this.value, <?php echo $key; ?>)">
																	<option value=""> <?= _translate("-- Select --"); ?>
																	</option>
																	<option value="qualitative" <?php echo (isset($testResultAttribute['result_type'][$key]) && !empty($testResultAttribute['result_type'][$key]) && $testResultAttribute['result_type'][$key] == 'qualitative') ? 'selected="selected"' : ''; ?>>
																		<?= _translate("Qualitative"); ?>
																	</option>
																	<option value="quantitative" <?php echo (isset($testResultAttribute['result_type'][$key]) && !empty($testResultAttribute['result_type'][$key]) && $testResultAttribute['result_type'][$key] == 'quantitative') ? 'selected="selected"' : ''; ?>>
																		<?= _translate("Quantitative"); ?>
																	</option>
																</select>
															</td>
														</tr>
														<tr class="qualitative-div <?php echo (isset($testResultAttribute['result_type'][$key]) && !empty($testResultAttribute['result_type'][$key]) && $testResultAttribute['result_type'][$key] == 'qualitative') ? '' : 'hide'; ?>"
															id="qualitativeRow<?php echo $key; ?>">
															<td colspan="4">
																<table style="width:100%;"
																	class="table table-bordered table-striped clearfix">
																	<tr>
																		<th>Expected Result</th>
																		<th>Result Code</th>
																		<th>Sort Order</th>
																		<th>Action</th>
																	</tr>
																	<?php
																	$resultsInnerList = $testResultAttribute[$testResultAttribute['result_type'][$key]];
																	if (isset($resultsInnerList) && !empty($resultsInnerList)) {
																		$n = count($resultsInnerList);
																		foreach ($resultsInnerList['expectedResult'][$key] as $ikey => $value) { ?>
																			<tr>
																				<td><input type="text"
																						value="<?php echo $value ?? null; ?>"
																						name="resultConfig[qualitative][expectedResult][<?php echo $key; ?>][<?php echo $ikey; ?>]"
																						class="form-control qualitative-input-<?php echo $key; ?><?php echo $ikey; ?> input-sm"
																						placeholder="Enter the expected result"
																						title="Please enter the expected result" /></td>
																				<td><input type="text"
																						value="<?php echo $resultsInnerList['resultCode'][$key][$ikey] ?? null; ?>"
																						name="resultConfig[qualitative][resultCode][<?php echo $key; ?>][<?php echo $ikey; ?>]"
																						class="form-control qualitative-input-<?php echo $key; ?><?php echo $ikey; ?> input-sm"
																						placeholder="Enter the result code"
																						title="Please enter the result code" /></td>
																				<td><input type="text"
																						value="<?php echo $resultsInnerList['sortOrder'][$key][$ikey] ?? null; ?>"
																						name="resultConfig[qualitative][sortOrder][<?php echo $key; ?>][<?php echo $ikey; ?>]"
																						class="form-control qualitative-input-<?php echo $key; ?><?php echo $ikey; ?> input-sm"
																						placeholder="Enter the sort order"
																						title="Please enter the sort order" /></td>
																				<td style="text-align:center;"><a
																						href="javascript:void(0);"
																						onclick="addQualitativeRow(this, <?php echo $key; ?>,<?php echo ($ikey + 1); ?>);"
																						class="btn btn-xs btn-info qualitative-insrow-<?php echo $key; ?><?php echo $ikey; ?>"><i
																							class="fa-solid fa-plus"></i></a>&nbsp;&nbsp;<a
																						href="javascript:void(0);"
																						onclick="removeQualitativeRow(this, <?php echo $key; ?>, <?php echo ($ikey + 1); ?>)"
																						class="btn btn-xs btn-danger"
																						title="Remove this row completely"
																						alt="Remove this row completely"><i
																							class="fa-solid fa-minus"></i></a></td>
																	</td>
																</tr>
															<?php }
																	} ?>
													</table>
												</td>
											</tr>
											<tr class="quantitative-div <?php echo (isset($testResultAttribute['result_type'][$key]) && !empty($testResultAttribute['result_type'][$key]) && $testResultAttribute['result_type'][$key] == 'quantitative') ? '' : 'hide'; ?>"
												id="quantitativeRow<?php echo $key; ?>">
												<td colspan="4">
													<table style="width:100%;"
														class="table table-bordered table-striped clearfix">
														<tr>
															<th>High Range</th>
															<th>Threshold Range</th>
															<th>Low Range</th>
														</tr>
														<tr>
															<td>
																<input type="text"
																	value="<?php echo $resultsInnerList['high_range'][$key] ?? null; ?>"
																	name="resultConfig[quantitative][high_range][<?php echo $key; ?>]"
																	class="form-control quantitative-input-<?php echo $key; ?>1 input-sm"
																	placeholder="Enter the high value"
																	title="Please enter the high value" />
															</td>
															<td>
																<input type="text"
																	value="<?php echo $resultsInnerList['threshold_range'][$key] ?? null; ?>"
																	name="resultConfig[quantitative][threshold_range][<?php echo $key; ?>]"
																	class="form-control quantitative-input-<?php echo $key; ?>1 input-sm"
																	placeholder="Enter the threshold value"
																	title="Please enter the threshold value" />
															</td>
															<td>
																<input type="text"
																	value="<?php echo $resultsInnerList['low_range'][$key] ?? null; ?>"
																	name="resultConfig[quantitative][low_range][<?php echo $key; ?>]"
																	class="form-control quantitative-input-<?php echo $key; ?>1 input-sm"
																	placeholder="Enter the low value"
																	title="Please enter the low value" />
															</td>
														</tr>
													</table>
												</td>
											</tr>
									</table>
									</td>
									<td style=" text-align:center;vertical-align: middle;">
										<a href="javascript:void(0);" onclick="addTbRow(this);" class="btn btn-xs btn-info"><i
												class="fa-solid fa-plus"></i></a>&nbsp;&nbsp;<a href="javascript:void(0);"
											onclick="removeRow(this)" class="btn btn-xs btn-danger"
											title="Remove this row completely" alt="Remove this row completely"><i
												class="fa-solid fa-minus"></i></a>
									</td>
									</tr>

								<?php } ?>

							<?php } else { ?>
								<tr class="result-type">
									<td>
										<table style="width: 100%;margin: 0 auto;" border="1"
											class="table table-bordered table-striped clearfix">
											<tr>
												<td class="hide firstSubTest" style="width:20%;">
													<lable for="resultSubGroup1" class="form-label-control">Enter the test
														name</lable>
												</td>
												<td class="hide firstSubTest" style="width:30%;">
													<input type="text" name="resultConfig[sub_test_name][1]"
														id="resultSubGroup1"
														class="form-control input-sm firstSubTest-input"
														placeholder="Enter the sub test name"
														title="Please ener the sub test name for 1st row" />
												</td>
												<td style="width:20%;">
													<lable for="testType1" class="form-label-control">Select Result Type
													</lable>
												</td>
												<td style="width:30%;">
													<select type="text" name="resultConfig[result_type][1]" id="testType1"
														class="form-control input-sm"
														title="Please select the type of result"
														onchange="setResultType(this.value, 1)">
														<option value=""> <?= _translate("-- Select --"); ?> </option>
														<option value="qualitative"><?= _translate("Qualitative"); ?>
														</option>
														<option value="quantitative"><?= _translate("Quantitative"); ?>
														</option>
													</select>
												</td>
											</tr>
											<tr class="qualitative-div hide" id="qualitativeRow1">
												<td colspan="4">
													<table style="width:100%;"
														class="table table-bordered table-striped clearfix">
														<tr>
															<th>Expected Result</th>
															<th>Result Code</th>
															<th>Sort Order</th>
															<th>Action</th>
														</tr>
														<tr>
															<td>
																<input type="text"
																	name="resultConfig[qualitative][expectedResult][1][1]"
																	class="form-control qualitative-input-11 input-sm"
																	placeholder="Enter the expected result"
																	title="Please enter the expected result" />
															</td>
															<td>
																<input type="text"
																	name="resultConfig[qualitative][resultCode][1][1]"
																	class="form-control qualitative-input-11 input-sm"
																	placeholder="Enter the result code"
																	title="Please enter the result code" />
															</td>
															<td>
																<input type="text"
																	name="resultConfig[qualitative][sortOrder][1][1]"
																	class="form-control qualitative-input-11 input-sm"
																	placeholder="Enter the sort order"
																	title="Please enter the sort order" />
															</td>
															<td style="text-align:center;">
																<a href="javascript:void(0);"
																	onclick="addQualitativeRow(this, 1,2);"
																	class="btn btn-xs btn-info qualitative-insrow-11"><i
																		class="fa-solid fa-plus"></i></a>
															</td>
														</tr>
													</table>
												</td>
											</tr>
											<tr class="quantitative-div hide" id="quantitativeRow1">
												<td colspan="4">
													<table style="width:100%;"
														class="table table-bordered table-striped clearfix">
														<tr>
															<th>High Range</th>
															<th>Threshold Range</th>
															<th>Low Range</th>
														</tr>
														<tr>
															<td>
																<input type="text"
																	name="resultConfig[quantitative][high_range][1]"
																	class="form-control quantitative-input-11 input-sm"
																	placeholder="Enter the high value"
																	title="Please enter the high value" />
															</td>
															<td>
																<input type="text"
																	name="resultConfig[quantitative][threshold_range][1]"
																	class="form-control quantitative-input-11 input-sm"
																	placeholder="Enter the threshold value"
																	title="Please enter the threshold value" />
															</td>
															<td>
																<input type="text"
																	name="resultConfig[quantitative][low_range][1]"
																	class="form-control quantitative-input-11 input-sm"
																	placeholder="Enter the low value"
																	title="Please enter the low value" />
															</td>
														</tr>
													</table>
												</td>
											</tr>
										</table>
									</td>
									<td style=" text-align:center;vertical-align: middle;">
										<a href="javascript:void(0);" onclick="addTbRow(this);"
											class="btn btn-xs btn-info"><i class="fa-solid fa-plus"></i></a>
									</td>
								</tr>
							<?php } ?>
							</tbody>
							</table>
						</div>
					</div>

					<!-- /.box-body -->
					<div class="box-footer">
						<a class="btn btn-primary" href="javascript:void(0);"
							onclick="validateNow();return false;"><?php echo _translate("Submit"); ?></a>
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
	var sampleCounter = <?php echo $i; ?>;
	var otherSectionNames = [];

	function addNewSection(section, rowId) {
		if (section != "" && ($.inArray(section, otherSectionNames) == -1))
			otherSectionNames.push(section);
	}

	$(document).ready(function () {
		addOtherSection();
		<?php if ($eCount > 0) { ?>
			generateRandomFieldId('1');
		<?php } ?>
		$(".auto-complete-tbx").autocomplete({
			source: otherSectionNames
		});
		$(".fieldCode").each(function () {
			if ($(this).val() == "") {
				let attributenumber = $(this).data('attributenumber');
				if ($("#fieldName" + attributenumber).val() != "") {
					$(this).val(Utilities.toSnakeCase($("#fieldName" + attributenumber).val()));
				}
			}

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
		$("#symptoms").selectize({
			placeholder: "<?php echo _translate("Select Symptoms"); ?>"
		});
		$("#testResultUnit").selectize({
			plugins: ["restore_on_backspace", "remove_button", "clear_button"],
		});

		$(document).on('click', '.remove-tag', function () {
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

		$(ajaxSelect).each(function (index, item) {
			$("#" + item).select2({
				placeholder: "Select " + _p[index],
				minimumInputLength: 0,
				width: '100%',
				allowClear: true,
				id: function (bond) {
					return bond._id;
				},
				ajax: {
					placeholder: "Type one or more character to search",
					url: "/includes/get-data-list-for-generic.php",
					dataType: 'json',
					delay: 250,
					data: function (params) {
						return {
							status: _as[index],
							fieldId: _fi[index],
							fieldName: _f[index],
							tableName: _t[index],
							q: params.term, // search term
							page: params.page
						};
					},
					processResults: function (data, params) {
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
				escapeMarkup: function (markup) {
					return markup;
				}
			});
		});
	});

	function addOtherSection() {
		$(".auto-complete-tbx").each(function () {
			if ($(this).val() != "" && ($.inArray($(this).val(), otherSectionNames) == -1))
				otherSectionNames.push($(this).val());
		});
	}

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
			formId: 'editTestTypeForm'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('editTestTypeForm').submit();
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
			function (data) {
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

		b.innerHTML = '<input type="text" name="fieldName[]" id="fieldName' + tableRowId + '" class="isRequired fieldName form-control" placeholder="<?php echo _translate('Field Name'); ?>" title="<?php echo _translate('Please enter field name'); ?>" onblur="checkDuplication(this, \'fieldName\');"/ ><input type="hidden" name="fieldId[]" id="fieldId' + tableRowId + '" class="form-control" />';
		c.innerHTML = '<input type="text" name="fieldCode[]" id="fieldCode' + tableRowId + '" class="form-control fieldCode isRequired" placeholder="<?php echo _translate("Field Code"); ?>" title="<?php echo _translate("Please enter field code"); ?>"/>';
		d.innerHTML = '<select class="form-control isRequired" name="fieldType[]" id="fieldType' + tableRowId + '" title="<?php echo _translate('Please select the field type'); ?>" onchange="changeField(this, ' + tableRowId + ')">\
							<option value=""> <?php echo _translate("-- Select --"); ?> </option>\
							<option value="number"><?php echo _translate("Number"); ?></option>\
							<option value="text"><?php echo _translate("Text"); ?></option>\
							<option value="date"><?php echo _translate("Date"); ?></option>\
							<option value="dropdown"><?php echo _translate("Dropdown"); ?></option>\
							<option value="multiple"><?php echo _translate("multiple Dropdown"); ?></option>\
						</select><br>\
						<div class="tag-input dropDown' + tableRowId + '" style="display:none;"><input type="text" name="dropDown[]" id="dropDown' + tableRowId + '" onkeyup="showTags(event,this,' + tableRowId + ')" class="tag-input-field form-control" placeholder="<?php echo _translate('Enter options...'); ?>" title="<?php echo _translate('Please enter the options'); ?>" /><input type="hidden" class="fdropDown" id="fdropDown' + tableRowId + '" name="fdropDown[]" /><div class="tag-container container' + tableRowId + '"></div></div>';
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
		$(el).fadeOut("slow", function () {
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
			$('.quantitativeResult').each(function () {
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
			$('.quantitativeResult').each(function () {
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

	function addQualitativeRow(obj, row1, row2) {
		$(obj).attr('disabled', true);
		var html = '<tr align="center"> \
			<td>\
				<input type="text" name="resultConfig[qualitative][expectedResult][' + row1 + '][' + row2 + ']" class="form-control qualitative-input-' + row1 + row2 + ' input-sm" placeholder="Enter the expected result" title="Please enter the expected result" />\
			</td>\
			<td>\
				<input type="text" name="resultConfig[qualitative][resultCode][' + row1 + '][' + row2 + ']" class="form-control qualitative-input-' + row1 + row2 + ' input-sm" placeholder="Enter the result code" title="Please enter the result code" />\
			</td>\
			<td>\
				<input type="text" name="resultConfig[qualitative][sortOrder][' + row1 + '][' + row2 + ']" class="form-control qualitative-input-' + row1 + row2 + ' input-sm" placeholder="Enter the sort order" title="Please enter the sort order" />\
			</td>\
			<td><a href="javascript:void(0);" onclick="addQualitativeRow(this, ' + row1 + ',' + (row2 + 1) + ');" class="btn btn-xs btn-info qualitative-insrow-' + row1 + row2 + '"><i class="fa-solid fa-plus"></i></a>&nbsp;&nbsp;<a  href="javascript:void(0);" onclick="removeQualitativeRow(this, ' + row1 + ', ' + (row2 - 1) + ')" class="btn btn-xs btn-danger"  title="Remove this row completely" alt="Remove this row completely"><i class="fa-solid fa-minus"></i></a></td> \
		</tr>'
		$(obj.parentNode.parentNode).after(html);
	}

	function addTbRow(obj) {
		$('.firstSubTest').removeClass('hide');
		$('#resultSubGroup1').addClass('isRequired');
		$('.firstSubTest-input').val('');
		sampleCounter++;
		var html = '<tr class="result-type">\
				<td>\
					<table style="width: 100%;margin: 0 auto;" border="1" class="table table-bordered table-striped clearfix">\
						<tr>\
							<td style="width:20%;"><lable for="resultSubGroup' + sampleCounter + '" class="form-label-control">Enter the test name</lable></td>\
							<td style="width:30%;">\
								<input type="text" name="resultConfig[sub_test_name][' + sampleCounter + ']"id="resultSubGroup' + sampleCounter + '" class="form-control isRequired input-sm" placeholder="Enter the sub test name" title="Please ener the sub test name for ' + sampleCounter + ' row"/>\
							</td>\
							<td style="width:20%;"><lable for="testType' + sampleCounter + '" class="form-label-control">Select result type</lable></td>\
							<td style="width:30%;">\
								<select type="text" name="resultConfig[result_type][' + sampleCounter + ']"id="testType' + sampleCounter + '" class="form-control isRequired input-sm" title="Please select the type of result" onchange="setResultType(this.value, ' + sampleCounter + ')">\
									<option value=""> <?= _translate("-- Select --"); ?> </option>\
									<option value="qualitative"><?= _translate("Qualitative"); ?></option>\
									<option value="quantitative"><?= _translate("Quantitative"); ?></option>\
								</select>\
							</td>\
						</tr>\
						<tr class="qualitative-div hide" id="qualitativeRow' + sampleCounter + '">\
							<td colspan="4">\
								<table style="width:100%;" class="table table-bordered table-striped clearfix">\
									<tr>\
										<th>Expected Result</th>\
										<th>Result Code</th>\
										<th>Sort Order</th>\
										<th>Action</th>\
									</tr>\
									<tr>\
										<td>\
											<input type="text" name="resultConfig[qualitative][expectedResult][' + sampleCounter + '][1]" class="form-control qualitative-input-' + sampleCounter + '1 input-sm" placeholder="Enter the expected result" title="Please enter the expected result" />\
										</td>\
										<td>\
											<input type="text" name="resultConfig[qualitative][resultCode][' + sampleCounter + '][1]" class="form-control qualitative-input-' + sampleCounter + '1 input-sm" placeholder="Enter the result code" title="Please enter the result code" />\
										</td>\
										<td>\
											<input type="text" name="resultConfig[qualitative][sortOrder][' + sampleCounter + '][1]" class="form-control qualitative-input-' + sampleCounter + '1 input-sm" placeholder="Enter the sort order" title="Please enter the sort order" />\
										</td>\
										<td style="text-align:center;">\
											<a href="javascript:void(0);" onclick="addQualitativeRow(this, ' + sampleCounter + ', 2);" class="btn btn-xs btn-info qualitative-insrow-' + sampleCounter + '1"><i class="fa-solid fa-plus"></i></a>\
										</td>\
									</tr>\
								</table>\
							</td>\
						</tr>\
						<tr class="quantitative-div hide" id="quantitativeRow' + sampleCounter + '" class="table table-bordered table-striped clearfix">\
							<td colspan="4">\
								<table style="width:100%;" class="table table-bordered table-striped clearfix">\
									<tr>\
										<th>High Range</th>\
										<th>Threshold Range</th>\
										<th>Low Range</th>\
									</tr>\
									<tr>\
										<td>\
											<input type="text" name="resultConfig[quantitative][high_range][' + sampleCounter + ']" class="form-control quantitative-input-' + sampleCounter + '1 input-sm" placeholder="Enter the high value" title="Please enter the high value" />\
										</td>\
										<td>\
											<input type="text" name="resultConfig[quantitative][threshold_range][' + sampleCounter + ']" class="form-control quantitative-input-' + sampleCounter + '1 input-sm" placeholder="Enter the threshold value" title="Please enter the threshold value" />\
										</td>\
										<td>\
											<input type="text" name="resultConfig[quantitative][low_range][' + sampleCounter + ']" class="form-control quantitative-input-' + sampleCounter + '1 input-sm" placeholder="Enter the low value" title="Please enter the low value" />\
										</td>\
									</tr>\
								</table>\
							</td>\
						</tr>\
					</table>\
				</td>\
				<td style=" text-align:center;vertical-align: middle;">\
					<a href="javascript:void(0);" onclick="addTbRow(this);" class="btn btn-xs btn-info"><i class="fa-solid fa-plus"></i></a>&nbsp;&nbsp;<a  href="javascript:void(0);" onclick="removeRow(this)" class="btn btn-xs btn-danger"  title="Remove this row completely" alt="Remove this row completely"><i class="fa-solid fa-minus"></i></a>\
				</td>\
			</tr>';
		$(obj.parentNode.parentNode).after(html);
	}

	function removeQualitativeRow(obj, row1, row2) {
		if (row2 <= 2) {
			$('.qualitative-insrow-' + row1 + row2).attr('disabled', false);
		}
		$(obj.parentNode.parentNode).fadeOut("normal", function () {
			$(this).remove();
		});
	}

	function removeRow(obj) {
		$(obj.parentNode.parentNode).fadeOut("normal", function () {
			$(this).remove();
		});
	}

	function setResultType(id, row) {
		if (id == 'qualitative') {
			$('.quantitative-input' + row).removeClass('isRequired');
			$('#qualitativeRow' + row).removeClass('hide');
			$('.qualitative-input' + row).addClass('isRequired');
			$('#quantitativeRow' + row).addClass('hide');
		} else if (id == 'quantitative') {
			$('.qualitative-input' + row).removeClass('isRequired');
			$('#quantitativeRow' + row).removeClass('hide');
			$('.quantitative-input' + row).addClass('isRequired');
			$('#qualitativeRow' + row).addClass('hide');
		}
	}
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
