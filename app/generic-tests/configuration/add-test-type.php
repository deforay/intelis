<?php

namespace App\Services;

use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;
use App\Services\CommonService;

require_once APPLICATION_PATH . '/header.php';
$general = ContainerRegistry::get(CommonService::class);
$generic = ContainerRegistry::get(GenericTestsService::class);
$sampleTypeInfo = $general->getDataByTableAndFields("r_generic_sample_types", ["sample_type_id", "sample_type_name"], true, "sample_type_status='active'");
$symptomInfo = $general->getDataByTableAndFields("r_generic_symptoms", ["symptom_id", "symptom_name"], true, "symptom_status='active'");
$testResultUnitInfo = $general->getDataByTableAndFields("r_generic_test_result_units", ["unit_id", "unit_name"], true, "unit_status='active'");
$testMethodInfo = $testMethodInfo ?? $general->getDataByTableAndFields("r_generic_test_methods", ["test_method_id", "test_method_name"], true, "test_method_status='active'");
$gtAllMethodsJson = json_encode(
    array_map(
        fn($__mid, $__mname) => ['id' => (string) $__mid, 'name' => (string) $__mname],
        array_keys($testMethodInfo ?? []),
        array_values($testMethodInfo ?? [])
    ),
    JSON_UNESCAPED_UNICODE
) ?: '[]';

/**
 * Render a Result Group's Test Methods picker: a compact dropdown that summarises
 * "X of Y selected" and opens a searchable checkbox list. The checkboxes post as
 * resultConfig[methods][<groupKey>][] directly -- no select2, so nothing desyncs.
 */
$gtRenderMethodPicker = function ($groupKey, $selected) use ($testMethodInfo) {
    $sel = array_map('strval', is_array($selected) ? $selected : []);
    $name = 'resultConfig[methods][' . $groupKey . '][]';
    ob_start(); ?>
    <div class="gtMethodPicker">
        <button type="button" class="form-control input-sm gtmp-toggle" aria-haspopup="listbox" aria-expanded="false">
            <span class="gtmp-summary"></span><span class="gtmp-caret">&#9662;</span>
        </button>
        <div class="gtmp-panel" hidden>
            <input type="text" class="form-control input-sm gtmp-search" placeholder="<?php echo _htmlTranslate('Search methods...'); ?>" autocomplete="off">
            <div class="gtmp-actions">
                <a href="javascript:void(0);" class="gtmp-all"><?php echo _htmlTranslate('Select all'); ?></a>
                <span class="gtmp-sep">|</span>
                <a href="javascript:void(0);" class="gtmp-clear"><?php echo _htmlTranslate('Clear all'); ?></a>
            </div>
            <div class="gtmp-list">
                <?php foreach (($testMethodInfo ?? []) as $__mid => $__mname) { ?>
                    <label class="gtmp-option">
                        <input type="checkbox" name="<?php echo htmlspecialchars($name); ?>" value="<?php echo htmlspecialchars((string) $__mid); ?>" <?php echo in_array((string) $__mid, $sel, true) ? 'checked' : ''; ?>>
                        <span><?php echo htmlspecialchars((string) $__mname); ?></span>
                    </label>
                <?php } ?>
            </div>
        </div>
    </div>
<?php return ob_get_clean();
};

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
	.fieldCode[readonly] {
		background-color: #f2f2f2;
		cursor: pointer;
	}

	/* Test Results Configuration -- present each result group as a numbered card */
	#vlSampleTable {
		border: none;
		border-collapse: separate;
		border-spacing: 0 16px;
		counter-reset: resultGroup;
	}
	#vlSampleTable > tbody > tr.result-type > td {
		border: none;
		vertical-align: top;
	}
	#vlSampleTable > tbody > tr.result-type > td:first-child {
		border: 1px solid #cfe0f1;
		border-radius: 6px;
		background: #f7fbff;
		padding: 0 14px 14px;
		box-shadow: 0 1px 3px rgba(0, 0, 0, .05);
	}
	#vlSampleTable > tbody > tr.result-type > td:first-child::before {
		counter-increment: resultGroup;
		content: "Result group " counter(resultGroup);
		display: block;
		margin: 0 -14px 14px;
		padding: 9px 14px;
		background: #e9f2fb;
		border-bottom: 1px solid #cfe0f1;
		border-radius: 6px 6px 0 0;
		font-weight: 600;
		font-size: 13px;
		letter-spacing: .3px;
		color: #34495e;
	}
	#vlSampleTable > tbody > tr.result-type > td:last-child {
		width: 140px;
		text-align: center;
		vertical-align: middle;
		padding-left: 14px;
	}
	#vlSampleTable > tbody > tr.result-type > td:last-child::before {
		content: "Add / remove this whole result group";
		display: block;
		font-size: 11px;
		line-height: 1.3;
		color: #8a9bad;
		margin-bottom: 6px;
	}
	.gtMethodPicker { position: relative; }
	.gtmp-toggle { display: flex; align-items: center; justify-content: space-between; width: 100%; text-align: left; cursor: pointer; background: #fff; }
	.gtmp-caret { margin-left: 8px; color: #6b7280; font-size: 10px; flex: none; }
	.gtmp-summary { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
	.gtmp-summary-empty { color: #8a97a3; }
	.gtmp-panel { position: absolute; z-index: 1000; top: calc(100% + 2px); left: 0; right: 0; background: #fff; border: 1px solid #cbd5e0; border-radius: 4px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12); padding: 8px; }
	.gtmp-search { margin-bottom: 6px; }
	.gtmp-actions { font-size: 12px; padding: 2px 2px 8px; border-bottom: 1px solid #edf2f7; margin-bottom: 6px; }
	.gtmp-actions a { color: #4a6fa5; cursor: pointer; }
	.gtmp-actions a:hover { text-decoration: underline; }
	.gtmp-sep { color: #cbd5e0; margin: 0 4px; }
	.gtmp-list { max-height: 220px; overflow-y: auto; }
	.gtmp-option { display: flex; align-items: flex-start; gap: 8px; font-weight: normal; margin: 0; padding: 6px 4px; border-radius: 4px; cursor: pointer; }
	.gtmp-option:hover { background-color: #f0f7ff; }
	.gtmp-option input { margin-top: 3px; }

	/* Basic-info fields: stack the label on top of its field (the 2-col grid stays via
	   .col-md-6), giving a roomier, less-crowded layout. Scoped to .form-group so the
	   Test Result Unit label (not inside a form-group) keeps its inline layout. */
	#addTestTypeForm .form-group > .control-label,
	#addTestTypeForm .form-group > div[class*="col-lg-"] {
		float: none;
		width: 100%;
	}
	#addTestTypeForm .form-group > .control-label {
		text-align: left;
		padding-top: 0;
		margin-bottom: 5px;
		font-weight: 600;
	}
	#addTestTypeForm .form-group {
		margin-bottom: 18px;
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
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _translate("indicates required fields"); ?> &nbsp;</div>
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
										<select class="isRequired" name='sampleType[]' id='sampleType' title="<?php echo _translate('Please select the sample type'); ?>" multiple>
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
										<select name='symptoms[]' id='symptoms' title="<?php echo _translate('Please select the symptoms'); ?>" multiple>
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
									<tr>
										<td>
											<input type="text" name="fieldName[]" id="fieldName1" class="form-control fieldName isRequired" placeholder='<?php echo _translate("Field Name"); ?>' title='<?php echo _translate("Please enter field name"); ?>' onblur="checkDuplication(this, 'fieldName');" />
											<input type="hidden" name="fieldId[]" id="fieldId1" class="form-control isRequired" />
										</td>
										<td>
											<input type="text" name="fieldCode[]" id="fieldCode1" class="form-control fieldCode isRequired" placeholder='<?php echo _translate("Field Code"); ?>' title='<?php echo _translate("Please enter field code"); ?>' onblur="checkDuplication(this, 'fieldCode');" onchange="this.value=Utilities.toSnakeCase(this.value)" />
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
												<option value="caseInformation"><?php echo _translate("Case Information"); ?></option>
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
						<hr>
						<?php include __DIR__ . '/_advanced-config-box.php'; ?>
						<div class="box-header row">
							<div class="col-md-4">
								<h3 class="box-title "><?php echo _translate("Test Results Configuration"); ?></h3>
							</div>
							<div class="col-md-8" id="testResultUnitWrapper">
								<label for="resultUnit" class="col-lg-4 control-label"><?php echo _translate("Test Result Unit"); ?> <small class="text-muted">(<?php echo _translate("for quantitative results"); ?>)</small> </label>
								<div class="col-lg-7">
									<select class="quantitativeResult" id="testResultUnit" name="resultConfig[test_result_unit][]" placeholder='<?php echo _translate("Enter test result unit"); ?>' title='<?php echo _translate("Please enter test result unit"); ?>' multiple>
										<?= $general->generateSelectOptions($testResultUnitInfo, null, false) ?>
									</select>
								</div>
							</div>
						</div>
						<div class="box-body">
							<p class="text-muted" style="margin:0 0 12px;"><?php echo _translate("Define each result this test reports. Most tests report a single result ". "(e.g. Positive / Negative, Present / Absent) -- that is one result group. A test that reports ". "several assays -- e.g. Ebola by RT-PCR and Antigen -- has one result group per assay: name each ". "group and use the + on the far right to add another. For each group choose Qualitative (a fixed ". "list of answers, each with a short Result Code) or Quantitative (a number with High / Threshold / ". "Low ranges; the Test Result Unit above applies here)."); ?></p>
							<table style="width: 100%;margin: 0 auto;" border="1" class="table table-bordered table-striped clearfix" id="vlSampleTable">
								<tbody>
									<tr class="result-type">
										<td>
											<table style="width: 100%;margin: 0 auto;" border="1" class="table table-bordered table-striped clearfix">
												<tr>
								<td style="width:20%;"><lable class="form-label-control"><?php echo _htmlTranslate("Test Methods"); ?> <span class="mandatory">*</span></lable></td>
								<td colspan="3" style="width:80%;"><?= $gtRenderMethodPicker(1, $testResultAttribute['methods'][1] ?? []) ?></td>
							</tr>
							<tr>
													<td class="hide firstSubTest" style="width:20%;">
														<lable for="resultSubGroup1" class="form-label-control"><?php echo _translate("Result name"); ?></lable>
													</td>
													<td class="hide firstSubTest" style="width:30%;">
														<input type="text" name="resultConfig[sub_test_name][1]" id="resultSubGroup1" class="form-control input-sm" placeholder="<?php echo _translate("Result / assay name, e.g. RT-PCR"); ?>" title="Please ener the sub test name for 1st row" />
													</td>
													<td style="width:20%;">
														<lable for="testType1" class="form-label-control">Select result type</lable>
													</td>
													<td style="width:30%;">
														<select type="text" name="resultConfig[result_type][1]" id="testType1" class="form-control input-sm" title="Please select the type of result" onchange="setResultType(this.value, 1)">
															<option value=""> <?= _translate("-- Select --"); ?> </option>
															<option value="qualitative"><?= _translate("Qualitative"); ?></option>
															<option value="quantitative"><?= _translate("Quantitative"); ?></option>
														</select>
													</td>
												</tr>
												<tr class="qualitative-div hide" id="qualitativeRow1">
													<td colspan="4">
														<table style="width:100%;" class="table table-bordered table-striped clearfix">
															<tr>
																<th>Expected Result</th>
																<th>Result Code</th>
																<th>Sort Order</th>
																<th>Action</th>
															</tr>
															<tr>
																<td>
																	<input type="text" name="resultConfig[qualitative][expectedResult][1][1]" class="form-control qualitative-input-11 input-sm" placeholder="Enter the expected result" title="Please enter the expected result" />
																</td>
																<td>
																	<input type="text" name="resultConfig[qualitative][resultCode][1][1]" class="form-control qualitative-input-11 input-sm" placeholder="Enter the result code" title="Please enter the result code" />
																</td>
																<td>
																	<input type="text" name="resultConfig[qualitative][sortOrder][1][1]" class="form-control qualitative-input-11 input-sm" placeholder="Enter the sort order" title="Please enter the sort order" />
																</td>
																<td style="text-align:center;">
																	<a href="javascript:void(0);" onclick="addQualitativeRow(this, 1,2);" class="btn btn-xs btn-info qualitative-insrow-11"><i class="fa-solid fa-plus"></i></a>
																</td>
															</tr>
														</table>
													</td>
												</tr>
												<tr class="quantitative-div hide" id="quantitativeRow1">
													<td colspan="4">
														<table style="width:100%;" class="table table-bordered table-striped clearfix">
															<tr>
																<th>High Range</th>
																<th>Threshold Range</th>
																<th>Low Range</th>
															</tr>
															<tr>
																<td>
																	<input type="text" name="resultConfig[quantitative][high_range][1]" class="form-control quantitative-input-11 input-sm" placeholder="Enter the high value" title="Please enter the high value" />
																</td>
																<td>
																	<input type="text" name="resultConfig[quantitative][threshold_range][1]" class="form-control quantitative-input-11 input-sm" placeholder="Enter the threshold value" title="Please enter the threshold value" />
																</td>
																<td>
																	<input type="text" name="resultConfig[quantitative][low_range][1]" class="form-control quantitative-input-11 input-sm" placeholder="Enter the low value" title="Please enter the low value" />
																</td>
															</tr>
														</table>
													</td>
												</tr>
											</table>
										</td>
										<td style=" text-align:center;vertical-align: middle;">
											<a href="javascript:void(0);" onclick="addTbRow(this);" class="btn btn-xs btn-info"><i class="fa-solid fa-plus"></i></a>
										</td>
									</tr>
								</tbody>
							</table>
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
	var sampleCounter = 1;
	var otherSectionNames = [];

	function addNewSection(section) {
		if (section != "" && ($.inArray(section, otherSectionNames) == -1))
			otherSectionNames.push(section);
	}
	$(document).ready(function() {
		$("#symptoms").selectize({
			plugins: ["restore_on_backspace", "remove_button", "clear_button"],
		});
		$(".auto-complete-tbx").autocomplete({
			source: otherSectionNames
		});

		$('input').tooltip();
		toggleResultUnit();
		$(document).on('change', 'select[name^="resultConfig[result_type]"]', toggleResultUnit);

		// Auto-generate the field code from the field name as it is typed, until the
		// code is edited directly (or it already has a value).
		$('.fieldCode').each(function () {
			if ($(this).val() !== '') {
				$(this).data('codeTouched', true);
			}
		});
		$(document).on('focusout', '.fieldName', function () {
			var $code = $(this).closest('tr').find('.fieldCode').first();
			if ($code.length && !$code.data('codeTouched')) {
				$code.val(Utilities.toSnakeCase(this.value));
			}
		});
		$(document).on('input', '.fieldCode', function () {
			$(this).data('codeTouched', true);
		});
		// Existing field codes are locked -- they are the shared identifier that
		// links a field to prior samples and to the same field on other test types.
		// Double-click to deliberately change one (e.g. fix a typo, or align codes).
		$(document).on('dblclick', '.fieldCode[readonly]', function () {
			if (confirm('This field code is already in use as a shared identifier across samples and test types. Changing it can break data reuse. Change it anyway?')) {
				$(this).prop('readonly', false).data('codeTouched', true).focus();
			}
		});
		generateRandomFieldId('1');
		$("#testingReason").select2({
			placeholder: "<?php echo _translate("Select Testing Reason"); ?>"
		});
		$("#sampleType").select2({
			width: '100%',
			placeholder: "<?php echo _translate("Select Sample Type"); ?>"
		});
		$("#testFailureReason").select2({
			placeholder: "<?php echo _translate("Select Test Failure Reason"); ?>"
		});
		$("#rejectionReason").select2({
			placeholder: "<?php echo _translate("Select Rejection Reason"); ?>"
		});
		$("#testResultUnit").selectize({
			plugins: ["restore_on_backspace", "remove_button", "clear_button"],
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



		$('.gtMethodPicker').each(function () { gtInitMethodPicker(this); });

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

		if (flag && !gtAllGroupsHaveMethod()) { flag = false; }

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
		var h = a.insertCell(6);

		h.setAttribute("align", "center");
		h.setAttribute("style", "vertical-align:middle");
		tagClass = 'container' + tableRowId;
		b.innerHTML = '<input type="text" name="fieldName[]" id="fieldName' + tableRowId + '" class="isRequired fieldName form-control" placeholder="<?php echo _translate('Field Name'); ?>" title="<?php echo _translate('Please enter field name'); ?>" onblur="checkDuplication(this, \'fieldName\');"/ ><input type="hidden" name="fieldId[]" id="fieldId' + tableRowId + '" class="form-control isRequired" />';
		c.innerHTML = '<input type="text" name="fieldCode[]" id="fieldCode' + tableRowId + '" class="form-control fieldCode isRequired" placeholder="<?php echo _translate("Field Code"); ?>" title="<?php echo _translate("Please enter field code"); ?>" onblur="checkDuplication(this, \'fieldCode\');" onchange="this.value=Utilities.toSnakeCase(this.value)"/>';
		d.innerHTML = '<select class="form-control isRequired" name="fieldType[]" id="fieldType' + tableRowId + '" title="<?php echo _translate('Please select the field type'); ?>" onchange="changeField(this, ' + tableRowId + ')">\
                            <option value=""> <?php echo _translate("-- Select --"); ?> </option>\
                            <option value="number"><?php echo _translate("Number"); ?></option>\
                            <option value="text"><?php echo _translate("Text"); ?></option>\
                            <option value="date"><?php echo _translate("Date"); ?></option>\
							<option value="dropdown"><?php echo _translate("Dropdown"); ?></option>\
							<option value="multiple"><?php echo _translate("Multiselect Dropdown"); ?></option>\
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
						<option value="caseInformation"><?php echo _translate("Case Information"); ?></option>\
						<option value="labSection"><?php echo _translate("Lab"); ?></option>\
						<option value="otherSection"><?php echo _translate("Other"); ?></option>\
                    </select>\
                    <input type="text" name="sectionOther[]" onchange="addNewSection(this.value)" id="sectionOther' + tableRowId + '" class="form-control auto-complete-tbx" placeholder="<?php echo _translate("Section Other"); ?>" title="<?php echo _translate("Please enter section other"); ?>" style="display:none;"/>';
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
		sampleCounter++;
		var html = '<tr class="result-type">\
				<td>\
					<table style="width: 100%;margin: 0 auto;" border="1" class="table table-bordered table-striped clearfix">\
						<tr>\
								<td style="width:20%;"><lable class="form-label-control"><?php echo _jsTranslate("Test Methods"); ?></lable></td>\
								<td colspan="3" style="width:80%;"><div class="gtMethodPicker" data-group="' + sampleCounter + '"></div></td>\
							</tr>\
							<tr>\
							<td style="width:20%;"><lable for="resultSubGroup' + sampleCounter + '" class="form-label-control"><?php echo _translate("Result name"); ?></lable></td>\
							<td style="width:30%;">\
								<input type="text" name="resultConfig[sub_test_name][' + sampleCounter + ']"id="resultSubGroup' + sampleCounter + '" class="form-control isRequired input-sm" placeholder="<?php echo _translate("Result / assay name, e.g. RT-PCR"); ?>" title="Please ener the sub test name for ' + sampleCounter + ' row"/>\
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
		(function () { var c = document.querySelector('.gtMethodPicker[data-group="' + sampleCounter + '"]'); if (c) { gtBuildMethodPicker(c, sampleCounter); gtInitMethodPicker(c); } })();
	}

	function removeQualitativeRow(obj, row1, row2) {
		if (row2 <= 2) {
			$('.qualitative-insrow-' + row1 + row2).attr('disabled', false);
		}
		$(obj.parentNode.parentNode).fadeOut("normal", function() {
			$(this).remove();
		});
	}

	function removeRow(obj) {
		$(obj.parentNode.parentNode).fadeOut("normal", function() {
			$(this).remove();
			toggleResultUnit();
		});
	}

	function toggleResultUnit() {
		var hasQuant = $('select[name^="resultConfig[result_type]"]').filter(function () {
			return this.value === 'quantitative';
		}).length > 0;
		// Units only apply to quantitative results.
		$('#testResultUnitWrapper').toggle(hasQuant);
	}

	// ---- Result Group Test Methods picker (checkbox dropdown; posts methods[k][]) ----
	var gtAllMethods = <?php echo $gtAllMethodsJson; ?>;
	var gtmpSummaryTpl = "<?php echo _jsTranslate('%selected of %total selected'); ?>";
	var gtmpEmptyTpl = "<?php echo _jsTranslate('Select methods'); ?>";
	var gtmpPreviewLimit = 3;

	function gtMethodPickerSummary(container) {
		var boxes = Array.prototype.slice.call(container.querySelectorAll('.gtmp-list input[type="checkbox"]'));
		var checked = boxes.filter(function (c) { return c.checked; });
		var s = container.querySelector('.gtmp-summary');
		if (!s) { return; }
		if (checked.length === 0) {
			s.textContent = gtmpEmptyTpl;
			s.classList.add('gtmp-summary-empty');
			return;
		}
		s.classList.remove('gtmp-summary-empty');
		if (checked.length <= gtmpPreviewLimit) {
			// Few selected -> preview the actual method names.
			s.textContent = checked.map(function (c) {
				var sp = c.parentNode.querySelector('span');
				return sp ? sp.textContent : c.value;
			}).join(', ');
		} else {
			// Too many to read -> fall back to a count.
			s.textContent = gtmpSummaryTpl.replace('%selected', checked.length).replace('%total', boxes.length);
		}
	}

	// Fill a freshly added (empty) picker with all methods, unchecked.
	function gtBuildMethodPicker(container, groupKey) {
		var name = 'resultConfig[methods][' + groupKey + '][]';
		container.innerHTML =
			'<button type="button" class="form-control input-sm gtmp-toggle" aria-haspopup="listbox" aria-expanded="false">'
			+ '<span class="gtmp-summary"></span><span class="gtmp-caret">&#9662;</span></button>'
			+ '<div class="gtmp-panel" hidden>'
			+ '<input type="text" class="form-control input-sm gtmp-search" placeholder="<?php echo _jsTranslate('Search methods...'); ?>" autocomplete="off">'
			+ '<div class="gtmp-actions"><a href="javascript:void(0);" class="gtmp-all"><?php echo _jsTranslate('Select all'); ?></a>'
			+ '<span class="gtmp-sep">|</span><a href="javascript:void(0);" class="gtmp-clear"><?php echo _jsTranslate('Clear all'); ?></a></div>'
			+ '<div class="gtmp-list"></div></div>';
		var list = container.querySelector('.gtmp-list');
		gtAllMethods.forEach(function (m) {
			var label = document.createElement('label');
			label.className = 'gtmp-option';
			var cb = document.createElement('input');
			cb.type = 'checkbox'; cb.name = name; cb.value = m.id;
			var span = document.createElement('span'); span.textContent = m.name;
			label.appendChild(cb); label.appendChild(span);
			list.appendChild(label);
		});
	}

	// Wire one picker's toggle / search / bulk / summary. Idempotent per container.
	function gtInitMethodPicker(container) {
		if (!container || container.getAttribute('data-gtmp-ready') === '1') { return; }
		container.setAttribute('data-gtmp-ready', '1');
		var toggle = container.querySelector('.gtmp-toggle');
		var panel = container.querySelector('.gtmp-panel');
		var search = container.querySelector('.gtmp-search');
		var list = container.querySelector('.gtmp-list');
		if (!toggle || !panel || !list) { return; }
		function boxes() { return Array.prototype.slice.call(list.querySelectorAll('input[type="checkbox"]')); }
		function visible() { return boxes().filter(function (c) { return c.closest('.gtmp-option').style.display !== 'none'; }); }
		function setOpen(open) { panel.hidden = !open; toggle.setAttribute('aria-expanded', open ? 'true' : 'false'); if (open && search) { search.focus(); } }
		toggle.addEventListener('click', function (e) { e.stopPropagation(); setOpen(panel.hidden); });
		document.addEventListener('click', function (e) { if (!container.contains(e.target)) { setOpen(false); } });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { setOpen(false); } });
		if (search) {
			search.addEventListener('input', function () {
				var q = this.value.toLowerCase();
				boxes().forEach(function (c) { var o = c.closest('.gtmp-option'); o.style.display = o.textContent.toLowerCase().indexOf(q) > -1 ? '' : 'none'; });
			});
		}
		var all = container.querySelector('.gtmp-all'), clr = container.querySelector('.gtmp-clear');
		if (all) { all.addEventListener('click', function () { visible().forEach(function (c) { c.checked = true; }); gtMethodPickerSummary(container); }); }
		if (clr) { clr.addEventListener('click', function () { visible().forEach(function (c) { c.checked = false; }); gtMethodPickerSummary(container); }); }
		list.addEventListener('change', function () { gtMethodPickerSummary(container); });
		gtMethodPickerSummary(container);
	}

	// Every Result Group must have at least one Test Method selected. The method
	// checkboxes replaced a required <select>, so enforce it here at submit time.
	function gtAllGroupsHaveMethod() {
		var ok = true, firstBad = null;
		document.querySelectorAll('.gtMethodPicker').forEach(function (c) {
			var any = c.querySelector('.gtmp-list input[type="checkbox"]:checked');
			var toggle = c.querySelector('.gtmp-toggle');
			if (!any) {
				ok = false;
				if (!firstBad) { firstBad = c; }
				if (toggle) { toggle.style.borderColor = '#dd4b39'; }
			} else if (toggle) {
				toggle.style.borderColor = '';
			}
		});
		if (!ok) {
			var msg = "<?php echo _jsTranslate('Please select at least one Test Method for every Result Group'); ?>";
			if (typeof toastr !== 'undefined') { toastr.error(msg); } else { alert(msg); }
			if (firstBad) { firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
		}
		return ok;
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
