<?php

namespace App\Services;

use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;
use App\Services\CommonService;

require_once APPLICATION_PATH . '/header.php';
$general = ContainerRegistry::get(CommonService::class);
$generic = ContainerRegistry::get(GenericTestsService::class);
$sampleTypeInfo = $general->getDataByTableAndFields("r_generic_sample_types", array("sample_type_id", "sample_type_name"), true, "sample_type_status='active'");
$symptomInfo = $general->getDataByTableAndFields("r_generic_symptoms", array("symptom_id", "symptom_name"), true, "symptom_status='active'");
$testResultUnits = $general->getDataByTableAndFields("r_generic_test_result_units", array("unit_id", "unit_name"), true, "unit_status='active'");
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
		<h1><em class="fa-sharp fa-solid fa-gears"></em> <?php echo _translate("Add Test Type"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("Add Test Type"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">

		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _translate("indicates required field"); ?> &nbsp;</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='addTestTypeForm' id='addTestTypeForm' autocomplete="off" action="addTestTypeHelper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="testStandardName" class="col-lg-4 control-label"><?php echo _translate("Test Standard Name"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="testStandardName" name="testStandardName" placeholder='<?php echo _translate("Test Standard Name"); ?>' title='<?php echo _translate("Please enter standard name"); ?>' onblur='checkNameValidation("r_test_types","test_standard_name",this,null,"<?php echo _translate("This test standard name that you entered already exists.Try another name"); ?>",null)' />
									</div>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="testGenericName" class="col-lg-4 control-label"><?php echo _translate("Test Generic Name"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="testGenericName" name="testGenericName" placeholder='<?php echo _translate("Test Generic Name"); ?>' title='<?php echo _translate("Please enter the test generic name"); ?>' onblur='checkNameValidation("r_test_types","test_generic_name",this,null,"<?php echo _translate("This test generic name that you entered already exists.Try another name"); ?>",null)' />
									</div>
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="testShortCode" class="col-lg-4 control-label"><?php echo _translate("Test Short Code"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="testShortCode" name="testShortCode" placeholder='<?php echo _translate("Test Short Code"); ?>' title='<?php echo _translate("Please enter short code"); ?>' onblur='checkNameValidation("r_test_types","test_short_code",this,null,"<?php echo _translate("This test short code that you entered already exists.Try another code"); ?>",null);' onchange="alphanumericValidation(this.value);" />
									</div>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="testLoincCode" class="col-lg-4 control-label"><?php echo _translate("LOINC Codes"); ?></label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="testLoincCode" name="testLoincCode" placeholder='<?php echo _translate("Test LOINC Code"); ?>' title='<?php echo _translate("Please enter test loinc code"); ?>' onblur='checkNameValidation("r_test_types","test_loinc_code",this,null,"<?php echo _translate("This test loinc code that you entered already exists.Try another code"); ?>",null)' />
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

										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="testCategory" class="col-lg-4 control-label"><?php echo _translate("Test Category"); ?> <span class="mandatory">*</span> <em class="fas fa-edit"></em></label>
									<div class="col-lg-7">
										<select class="form-control isRequired editableSelect" name='testCategory' id='testCategory' title="<?php echo _translate('Please select the test categories'); ?>">

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
											<?= $general->generateSelectOptions($sampleTypeInfo, null, '-- Select --') ?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="testingReason" class="col-lg-4 control-label"><?php echo _translate("Reasons for Testing"); ?> <span class="mandatory">*</span> <em class="fas fa-edit"></em></label>
									<div class="col-lg-7">
										<select class="form-control isRequired editableSelect" name='testingReason[]' id='testingReason' title="<?php echo _translate('Please select the testing reason'); ?>" multiple>

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

										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="rejectionReason" class="col-lg-4 control-label"><?php echo _translate("Sample Rejection Reasons"); ?> <span class="mandatory">*</span> <em class="fas fa-edit"></em></label>
									<div class="col-lg-7">
										<select class="form-control isRequired editableSelect" name='rejectionReason[]' id='rejectionReason' title="<?php echo _translate('Please select the sample rejection reason'); ?>" multiple>

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
											<?= $general->generateSelectOptions($symptomInfo, null, '-- Select --') ?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="status" class="col-lg-4 control-label"><?php echo _translate("Status"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='status' id='status' title="<?php echo _translate('Please select the status'); ?>">
											<option value="active"><?php echo _translate("Active"); ?></option>
											<option value="inactive"><?php echo _translate("Inactive"); ?></option>
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
										<th style="text-align:center;width:25%;"><?php echo _translate("Field Name"); ?> <span class="mandatory">*</span></th>
										<th style="text-align:center;width:20%;"><?php echo _translate("Field Type"); ?> <span class="mandatory">*</span></th>
										<th style="text-align:center;width:10%;"><?php echo _translate("Is it Mandatory?"); ?> <span class="mandatory">*</span></th>
										<th style="text-align:center;width:25%;"><?php echo _translate("Section"); ?> <span class="mandatory">*</span></th>
										<th style="text-align:center;width:10%;"><?php echo _translate("Field Order"); ?> </th>
										<th style="text-align:center;width:10%;"><?php echo _translate("Action"); ?></th>
									</tr>
								</thead>
								<tbody id="attributeTable">
									<tr>
										<td>
											<input type="text" name="fieldName[]" id="fieldName1" class="form-control fieldName isRequired" placeholder='<?php echo _translate("Field Name"); ?>' title='<?php echo _translate("Please enter field name"); ?>' onblur="checkDublicateName(this, 'fieldName');" />
											<input type="hidden" name="fieldId[]" id="fieldId1" class="form-control isRequired" />
										</td>
										<td>
											<select class="form-control isRequired" name="fieldType[]" id="fieldType1" onchange="changeField(this,'1')" title="<?php echo _translate('Please select the field type'); ?>">
												<option value=""> <?php echo _translate("-- Select --"); ?> </option>
												<option value="number"><?php echo _translate("Number"); ?></option>
												<option value="text"><?php echo _translate("Text"); ?></option>
												<option value="date"><?php echo _translate("Date"); ?></option>
												<option value="dropdown"><?php echo _translate("Dropdown"); ?></option>
												<option value="multiple"><?php echo _translate("Multiselect Dropdown"); ?></option>
											</select><br>
											<!--	<textarea name="dropDown[]" id="dropDown1" class="form-control" placeholder='<?php echo _translate("Drop down values as , separated"); ?>' title='<?php echo _translate("Please drop down values as comma separated"); ?>' style="display:none;"></textarea>-->
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
												<option value="qualitative"><?php echo _translate("Qualitative"); ?></option>
												<option value="quantitative"><?php echo _translate("Quantitative"); ?></option>
											</select>
										</div>
									</div>
								</div>
							</div>
							<div class="row" id="qualitativeDiv" style="display:none;">
								<div class="col-md-12">
									<table aria-describedby="table" class="table table-bordered table-striped" aria-hidden="true">
										<tbody id="qualitativeTable">
											<tr>
												<td class="text-center">1</td>
												<th scope="row">Result<span class="mandatory">*</span></th>
												<td><input type="text" name="resultConfig[result][]" id="result1" class="form-control qualitativeResult" placeholder="Result" title="Please enter the result 1" /></td>
												<th scope="row">Result Interpretation<span class="mandatory">*</span></th>
												<td><input type="text" id="resultInterpretation1" name="resultConfig[result_interpretation][]" class="form-control qualitativeResult" placeholder="Enter result interpretation" title="Please enter result interpretation"></td>
												<td style="vertical-align:middle;text-align: center;width:100px;">
													<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="addResultRow('qualitativeTable');"><em class="fa-solid fa-plus"></em></a>&nbsp;
													<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeResultRow(this.parentNode.parentNode, 'qualitativeTable');"><em class="fa-solid fa-minus"></em></a>
												</td>
											</tr>
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
											<input type="text" class="form-control forceNumeric quantitativeResult" id="highValue" name="resultConfig[high_value]" placeholder='<?php echo _translate("Enter High Value"); ?>' title='<?php echo _translate("Please enter high value"); ?>' value="<?php echo (isset($testResultAttribute['high_value'])) ? $testResultAttribute['high_value'] : "" ?> " />
										</div>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="thresholdValue" class="col-lg-4 control-label"><?php echo _translate("Threshold Value"); ?> <span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<input type="text" class="form-control forceNumeric quantitativeResult" id="thresholdValue" name="resultConfig[threshold_value]" placeholder='<?php echo _translate("Enter Threshold Value"); ?>' title='<?php echo _translate("Please enter threshold value"); ?>' value="<?php echo (isset($testResultAttribute['threshold_value'])) ? $testResultAttribute['threshold_value'] : "" ?>" />
										</div>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="lowValue" class="col-lg-4 control-label"><?php echo _translate("Low Value"); ?> <span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<input type="text" class="form-control forceNumeric quantitativeResult" id="lowValue" name="resultConfig[low_value]" placeholder='<?php echo _translate("Enter Low Value"); ?>' title='<?php echo _translate("Please enter low value"); ?>' value="<?php echo (isset($testResultAttribute['low_value'])) ? $testResultAttribute['low_value'] : "" ?> " />
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
											<input type="text" class="form-control quantitativeResult" id="belowThreshold" name="resultConfig[below_threshold]" placeholder='<?php echo _translate("Enter below threshold"); ?>' title='<?php echo _translate("Please enter below threshold"); ?>' value="<?php echo (isset($testResultAttribute['below_threshold'])) ? $testResultAttribute['below_threshold'] : "" ?> " />
										</div>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="atThreshold" class="col-lg-4 control-label"><?php echo _translate("At Threshold"); ?> <span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<input type="text" class="form-control quantitativeResult" id="atThreshold" name="resultConfig[at_threshold]" placeholder='<?php echo _translate("Enter at threshold"); ?>' title='<?php echo _translate("Please enter at threshold"); ?>' value="<?php echo (isset($testResultAttribute['at_threshold'])) ? $testResultAttribute['at_threshold'] : "" ?> " />
										</div>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="aboveThreshold" class="col-lg-4 control-label"><?php echo _translate("Above Threshold"); ?> <span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<input type="text" class="form-control quantitativeResult" id="aboveThreshold" name="resultConfig[above_threshold]" placeholder='<?php echo _translate("Enter above threshold"); ?>' title='<?php echo _translate("Please enter above threshold"); ?>' value="<?php echo (isset($testResultAttribute['above_threshold'])) ? $testResultAttribute['above_threshold'] : "" ?> " />
										</div>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="resultUnit" class="col-lg-4 control-label"><?php echo _translate("Test Result Unit"); ?> </label>
										<div class="col-lg-7">
											<select class="form-control quantitativeResult" id="testResultUnit" name="resultConfig[test_result_unit][]" placeholder='<?php echo _translate("Enter test result unit"); ?>' title='<?php echo _translate("Please enter test result unit"); ?>' multiple>
												<?= $general->generateSelectOptions($testResultUnits, null, '-- Select --') ?>
											</select>
										</div>
									</div>
								</div>

							</div>
							<div class="row quantitativeDiv" style="display:none;">
								<div class="col-md-12">
									<table aria-describedby="table" class="table table-bordered table-striped" aria-hidden="true">
										<tbody id="quantitativeTable">
											<tr>
												<td class="text-center">1</td>
												<th scope="row">Result<span class="mandatory">*</span></th>
												<td><input type="text" name="resultConfig[quantitative_result][]" id="quantitativeResult1" class="form-control quantitativeResult" placeholder="Result" title="Please enter the result" /></td>
												<th scope="row">Result Interpretation<span class="mandatory">*</span></th>
												<td><input type="text" id="quantitativeResultInterpretation1" name="resultConfig[quantitative_result_interpretation][]" class="form-control quantitativeResult" placeholder="Enter result interpretation" title="Please enter result interpretation"></td>
												<td style="vertical-align:middle;text-align: center;width:100px;">
													<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="addResultRow('quantitativeTable');"><em class="fa-solid fa-plus"></em></a>&nbsp;
													<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeResultRow(this.parentNode.parentNode, 'quantitativeTable');"><em class="fa-solid fa-minus"></em></a>
												</td>
											</tr>
										</tbody>
									</table>
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
	tableRowId = 2;
	testQualCounter = 1;
	testQuanCounter = 1;
	var otherSectionNames = [];

	function addNewSection(section) {
		if (section != "" && ($.inArray(section, otherSectionNames) == -1))
			otherSectionNames.push(section);
	}
	$(document).ready(function() {
		$(".auto-complete-tbx").autocomplete({
			source: otherSectionNames
		});

		$('input').tooltip();
		generateRandomString('1');
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

		/*	$('.tag-input-field').on('keyup', function(e) {
			if (e.key === ',' || e.key === 'Enter') {
			var val = this.value;
			if (val.length > 0) {
				var tag = val.split(',')[0].trim();
				$(this).closest('.tag-container').append('<div class="tag">' + tag + '<span class="remove-tag">x</span></div>');
				this.value = "";
			}
			}
		});*/

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
		for (let i = 0; i < options.length; i++) {
			$('#fdropDown' + cls).val($('#fdropDown' + cls).val() + options[i] + ',');
		}
	}

	function validateNow() {
		flag = deforayValidator.init({
			formId: 'addTestTypeForm'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('addTestTypeForm').submit();
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

		g.setAttribute("align", "center");
		g.setAttribute("style", "vertical-align:middle");
		tagClass = 'container' + tableRowId;
		b.innerHTML = '<input type="text" name="fieldName[]" id="fieldName' + tableRowId + '" class="isRequired fieldName form-control" placeholder="<?php echo _translate('Field Name'); ?>" title="<?php echo _translate('Please enter field name'); ?>" onblur="checkDublicateName(this, \'fieldName\');"/ ><input type="hidden" name="fieldId[]" id="fieldId' + tableRowId + '" class="form-control isRequired" />';
		c.innerHTML = '<select class="form-control isRequired" name="fieldType[]" id="fieldType' + tableRowId + '" title="<?php echo _translate('Please select the field type'); ?>" onchange="changeField(this, ' + tableRowId + ')">\
                            <option value=""> <?php echo _translate("-- Select --"); ?> </option>\
                            <option value="number"><?php echo _translate("Number"); ?></option>\
                            <option value="text"><?php echo _translate("Text"); ?></option>\
                            <option value="date"><?php echo _translate("Date"); ?></option>\
							<option value="dropdown"><?php echo _translate("Dropdown"); ?></option>\
							<option value="multiple"><?php echo _translate("Multiselect Dropdown"); ?></option>\
						</select><br>\
						<div class="tag-input dropDown' + tableRowId + '" style="display:none;"><input type="text" name="dropDown[]" id="dropDown' + tableRowId + '" onkeyup="showTags(event,this,' + tableRowId + ')" class="tag-input-field form-control" placeholder="Enter options..." /><input type="hidden" class="fdropDown" id="fdropDown' + tableRowId + '" name="fdropDown[]" /><div class="tag-container container' + tableRowId + '"></div></div>';
		d.innerHTML = '<select class="form-control isRequired" name="mandatoryField[]" id="mandatoryField' + tableRowId + '" title="<?php echo _translate('Please select is it mandatory'); ?>">\
                            <option value="yes"><?php echo _translate("Yes"); ?></option>\
                            <option value="no" selected><?php echo _translate("No"); ?></option>\
                        </select>';
		e.innerHTML = '<select class="form-control isRequired" name="section[]" id="section' + tableRowId + '" title="<?php echo _translate('Please select the section'); ?>" onchange="checkSection(' + tableRowId + ')">\
                        <option value=""> <?php echo _translate("-- Select --"); ?> </option>\
                        <option value="facilitySection"><?php echo _translate("Facility"); ?></option>\
						<option value="patientSection"><?php echo _translate("Patient"); ?></option>\
						<option value="specimenSection"><?php echo _translate("Specimen"); ?></option>\
						<option value="labSection"><?php echo _translate("Lab"); ?></option>\
						<option value="otherSection"><?php echo _translate("Other"); ?></option>\
                    </select>\
                    <input type="text" name="sectionOther[]" onchange="addNewSection(this.value)" id="sectionOther' + tableRowId + '" class="form-control auto-complete-tbx" placeholder="<?php echo _translate("Section Other"); ?>" title="<?php echo _translate("Please enter section other"); ?>" style="display:none;"/>';
		f.innerHTML = '<input type="text" name="fieldOrder[]" id="fieldOrder' + tableRowId + '" class="form-control forceNumeric" placeholder="<?php echo _translate("Field Order"); ?>" title="<?php echo _translate("Please enter field order"); ?>" />';
		g.innerHTML = '<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;&nbsp;<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>';
		$(a).fadeIn(800);

		$(".auto-complete-tbx").autocomplete({
			source: otherSectionNames
		});

		generateRandomString(tableRowId);
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

	function checkDublicateName(obj, name) {
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
		}
	}

	function generateRandomString(rowId) {
		$.post("/includes/generateRandomString.php", {
				format: "html"
			},
			function(data) {
				$("#fieldId" + rowId).val("_" + data);
			});
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
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
