<?php
require_once APPLICATION_PATH . '/header.php';

$stQuery = "SELECT * from r_generic_sample_types where sample_type_status='active'";
$sampleTypeInfo = $db->query($stQuery);

$tQuery = "SELECT * from r_generic_test_reasons where test_reason_status='active'";
$testReasonInfo = $db->query($tQuery);

$tfQuery = "SELECT * from r_generic_test_failure_reasons where test_failure_reason_status='active'";
$testFailureReasonInfo = $db->query($tfQuery);

$srQuery = "SELECT * from r_generic_sample_rejection_reasons where rejection_reason_status='active'";
$sampleRejectionReasonInfo = $db->query($srQuery);

$symQuery = "SELECT * from r_generic_symptoms where symptom_status='active'";
$symptomInfo = $db->query($symQuery);
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
		<h1><em class="fa-sharp fa-solid fa-gears"></em> <?php echo _("Add Test Type"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Add Test Type"); ?></li>
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
				<form class="form-horizontal" method='post' name='addTestTypeForm' id='addTestTypeForm' autocomplete="off" action="addTestTypeHelper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="testStandardName" class="col-lg-4 control-label"><?php echo _("Test Standard Name"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="testStandardName" name="testStandardName" placeholder='<?php echo _("Test Standard Name"); ?>' title='<?php echo _("Please enter standard name"); ?>' onblur='checkNameValidation("r_test_types","test_standard_name",this,null,"<?php echo _("This test standard name that you entered already exists.Try another name"); ?>",null)' />
									</div>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="testGenericName" class="col-lg-4 control-label"><?php echo _("Test Generic Name"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="testGenericName" name="testGenericName" placeholder='<?php echo _("Test Generic Name"); ?>' title='<?php echo _("Please enter the test generic name"); ?>' onblur='checkNameValidation("r_test_types","test_generic_name",this,null,"<?php echo _("This test generic name that you entered already exists.Try another name"); ?>",null)' />
									</div>
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="testShortCode" class="col-lg-4 control-label"><?php echo _("Test Short Code"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="testShortCode" name="testShortCode" placeholder='<?php echo _("Test Short Code"); ?>' title='<?php echo _("Please enter short code"); ?>' onblur='checkNameValidation("r_test_types","test_short_code",this,null,"<?php echo _("This test short code that you entered already exists.Try another code"); ?>",null)' />
									</div>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="testLoincCode" class="col-lg-4 control-label"><?php echo _("Test LOINC Code"); ?></label>
									<div class="col-lg-7">
										<input type="text" class="form-control" id="testLoincCode" name="testLoincCode" placeholder='<?php echo _("Test LOINC Code"); ?>' title='<?php echo _("Please enter test loinc code"); ?>' onblur='checkNameValidation("r_test_types","test_loinc_code",this,null,"<?php echo _("This test loinc code that you entered already exists.Try another code"); ?>",null)' />
									</div>
								</div>
							</div>
						</div>

						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="sampleType" class="col-lg-4 control-label"><?php echo _("Sample Type"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='sampleType[]' id='sampleType' title="<?php echo _('Please select the sample type'); ?>" multiple>
											<option value="">--Select--</option>
											<?php
											foreach ($sampleTypeInfo as $sampleType) {
											?>
												<option value="<?php echo $sampleType['sample_type_id']; ?>"><?php echo $sampleType['sample_type_name']; ?></option>
											<?php
											}
											?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="testingReason" class="col-lg-4 control-label"><?php echo _("Testing Reason"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='testingReason[]' id='testingReason' title="<?php echo _('Please select the testing reason'); ?>" multiple>
											<option value="">--Select--</option>
											<?php
											foreach ($testReasonInfo as $testReason) {
											?>
												<option value="<?php echo $testReason['test_reason_id']; ?>"><?php echo $testReason['test_reason']; ?></option>
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
									<label for="testFailureReason" class="col-lg-4 control-label"><?php echo _("Test Failure Reason"); ?> </label>
									<div class="col-lg-7">
										<select class="form-control" name='testFailureReason[]' id='testFailureReason' title="<?php echo _('Please select the test failure reason'); ?>" multiple>
											<option value="">--Select--</option>
											<?php
											foreach ($testFailureReasonInfo as $testFailureReason) {
											?>
												<option value="<?php echo $testFailureReason['test_failure_reason_id']; ?>"><?php echo $testFailureReason['test_failure_reason']; ?></option>
											<?php
											}
											?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="rejectionReason" class="col-lg-4 control-label"><?php echo _("Sample Rejection Reason"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='rejectionReason[]' id='rejectionReason' title="<?php echo _('Please select the sample rejection reason'); ?>" multiple>
											<option value="">--Select--</option>
											<?php
											foreach ($sampleRejectionReasonInfo as $rejectionReason) {
											?>
												<option value="<?php echo $rejectionReason['rejection_reason_id']; ?>"><?php echo $rejectionReason['rejection_reason_name']; ?></option>
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
									<label for="symptoms" class="col-lg-4 control-label"><?php echo _("Symptoms"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='symptoms[]' id='symptoms' title="<?php echo _('Please select the symptoms'); ?>" multiple>
											<option value="">--Select--</option>
											<?php
											foreach ($symptomInfo as $val) {
											?>
												<option value="<?php echo $val['symptom_id']; ?>"><?php echo $val['symptom_name']; ?></option>
											<?php
											}
											?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="status" class="col-lg-4 control-label"><?php echo _("Status"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name='status' id='status' title="<?php echo _('Please select the status'); ?>">
											<option value="active"><?php echo _("Active"); ?></option>
											<option value="inactive"><?php echo _("Inactive"); ?></option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="box-header">
							<h3 class="box-title "><?php echo _("Form Configuration"); ?></h3>
						</div>
						<div class="box-body">
							<table aria-describedby="table" border="0" class="table table-striped table-bordered table-condensed" aria-hidden="true" style="width:100%;">
								<thead>
									<tr>
										<th style="text-align:center;width:30%;"><?php echo _("Field Name"); ?> <span class="mandatory">*</span></th>
										<th style="text-align:center;width:13%;"><?php echo _("Field Type"); ?> <span class="mandatory">*</span></th>
										<th style="text-align:center;width:13%;"><?php echo _("Is it Mandatory?"); ?> <span class="mandatory">*</span></th>
										<th style="text-align:center;width:15%;"><?php echo _("Section"); ?> <span class="mandatory">*</span></th>
										<th style="text-align:center;width:8%;"><?php echo _("Action"); ?></th>
									</tr>
								</thead>
								<tbody id="attributeTable">
									<tr>
										<td>
											<input type="text" name="fieldName[]" id="fieldName1" class="form-control fieldName isRequired" placeholder='<?php echo _("Field Name"); ?>' title='<?php echo _("Please enter field name"); ?>' onblur="checkDublicateName(this, 'fieldName');" />
											<input type="hidden" name="fieldId[]" id="fieldId1" class="form-control isRequired" />
										</td>
										<td>
											<select class="form-control isRequired" name="fieldType[]" id="fieldType1" onchange="changeField(this,'1')" title="<?php echo _('Please select the field type'); ?>">
												<option value=""> <?php echo _("-- Select --"); ?> </option>
												<option value="number"><?php echo _("Number"); ?></option>
												<option value="text"><?php echo _("Text"); ?></option>
												<option value="date"><?php echo _("Date"); ?></option>
												<option value="dropdown"><?php echo _("Dropdown"); ?></option>
												<option value="multiple"><?php echo _("Multiselect Dropdown"); ?></option>
											</select><br>
										<!--	<textarea name="dropDown[]" id="dropDown1" class="form-control" placeholder='<?php echo _("Drop down values as , separated"); ?>' title='<?php echo _("Please drop down values as comma separated"); ?>' style="display:none;"></textarea>-->
												<div class="tag-input dropDown1" style="display:none;">
												<input type="text" name="dropDown[]" id="dropDown1" onkeyup="showTags(event,this,'1')" class="tag-input-field form-control" placeholder="Enter options..." />
												<input type="hidden" class="fdropDown" id="fdropDown1" name="fdropDown[]"/>
												<div class="tag-container container1">
												</div>
												</div>
										</td>
										<td>
											<select class="form-control isRequired" name="mandatoryField[]" id="mandatoryField1" title="<?php echo _('Please select is it mandatory'); ?>">
												<option value="yes"><?php echo _("Yes"); ?></option>
												<option value="no" selected><?php echo _("No"); ?></option>
											</select>
										</td>
										<td>
											<select class="form-control isRequired" name="section[]" id="section1" title="<?php echo _('Please select the section'); ?>" onchange="checkSection('1')">
												<option value=""> <?php echo _("-- Select --"); ?> </option>
												<option value="facilitySection"><?php echo _("Facility"); ?></option>
												<option value="patientSection"><?php echo _("Patient"); ?></option>
												<option value="specimenSection"><?php echo _("Specimen"); ?></option>
												<option value="labSection"><?php echo _("Lab"); ?></option>
												<option value="otherSection"><?php echo _("Other"); ?></option>
											</select>
											<input type="text" name="sectionOther[]" id="sectionOther1" class="form-control" placeholder='<?php echo _("Section Other"); ?>' title='<?php echo _("Please enter section other"); ?>' style="display:none;" />
										</td>
										<td align="center" style="vertical-align:middle;">
											<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;&nbsp;<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>
										</td>
									</tr>
								</tbody>
							</table>
						</div>

						<div class="box-header">
							<h3 class="box-title "><?php echo _("Test Results Configuration"); ?></h3>
						</div>
						<div class="box-body">
							<div class="row">
								<div class="col-md-6">
									<div class="form-group">
										<label for="resultType" class="col-lg-3 control-label"><?php echo _("Result Type"); ?> <span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<select class="form-control isRequired" name='resultConfig[result_type]' id='resultType' onchange="checkResultType();">
												<option value=""> <?php echo _("-- Select --"); ?> </option>
												<option value="qualitative"><?php echo _("Qualitative"); ?></option>
												<option value="quantitative"><?php echo _("Quantitative"); ?></option>
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
												<td><input type="text" name="resultConfig[result][]" id="result1" class="form-control" placeholder="Result" title="Please enter the result 1" /></td>
												<th scope="row">Result Interpretation<span class="mandatory">*</span></th>
												<td><input type="text" id="resultInterpretation1" name="resultConfig[result_interpretation][]" class="form-control" placeholder="Enter result interpretation" title="Please enter result interpretation"></td>
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
									<h4 class="box-title "><?php echo _("Result Range"); ?></h4>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="highValue" class="col-lg-4 control-label"><?php echo _("High Value"); ?> <span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<input type="text" class="form-control forceNumeric quantitativeResult" id="highValue" name="resultConfig[high_value]" placeholder='<?php echo _("Enter High Value"); ?>' title='<?php echo _("Please enter high value"); ?>' value="<?php echo (isset($testResultAttribute['high_value'])) ? $testResultAttribute['high_value'] : "" ?> " />
										</div>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="thresholdValue" class="col-lg-4 control-label"><?php echo _("Threshold Value"); ?> <span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<input type="text" class="form-control forceNumeric quantitativeResult" id="thresholdValue" name="resultConfig[threshold_value]" placeholder='<?php echo _("Enter Threshold Value"); ?>' title='<?php echo _("Please enter threshold value"); ?>' value="<?php echo (isset($testResultAttribute['threshold_value'])) ? $testResultAttribute['threshold_value'] : "" ?>" />
										</div>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="lowValue" class="col-lg-4 control-label"><?php echo _("Low Value"); ?> <span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<input type="text" class="form-control forceNumeric quantitativeResult" id="lowValue" name="resultConfig[low_value]" placeholder='<?php echo _("Enter Low Value"); ?>' title='<?php echo _("Please enter low value"); ?>' value="<?php echo (isset($testResultAttribute['low_value'])) ? $testResultAttribute['low_value'] : "" ?> " />
										</div>
									</div>
								</div>
							</div>
							<div class="row quantitativeDiv" style="display:none;">
								<div class="box-header">
									<h4 class="box-title "><?php echo _("Result Interpretation"); ?></h4>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="belowThreshold" class="col-lg-4 control-label"><?php echo _("Below Threshold"); ?> <span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<input type="text" class="form-control quantitativeResult" id="belowThreshold" name="resultConfig[below_threshold]" placeholder='<?php echo _("Enter below threshold"); ?>' title='<?php echo _("Please enter below threshold"); ?>' value="<?php echo (isset($testResultAttribute['below_threshold'])) ? $testResultAttribute['below_threshold'] : "" ?> " />
										</div>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="atThreshold" class="col-lg-4 control-label"><?php echo _("At Threshold"); ?> <span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<input type="text" class="form-control quantitativeResult" id="atThreshold" name="resultConfig[at_threshold]" placeholder='<?php echo _("Enter at threshold"); ?>' title='<?php echo _("Please enter at threshold"); ?>' value="<?php echo (isset($testResultAttribute['at_threshold'])) ? $testResultAttribute['at_threshold'] : "" ?> " />
										</div>
									</div>
								</div>
								<div class="col-md-4">
									<div class="form-group">
										<label for="aboveThreshold" class="col-lg-4 control-label"><?php echo _("Above Threshold"); ?> <span class="mandatory">*</span></label>
										<div class="col-lg-7">
											<input type="text" class="form-control quantitativeResult" id="aboveThreshold" name="resultConfig[above_threshold]" placeholder='<?php echo _("Enter above threshold"); ?>' title='<?php echo _("Please enter above threshold"); ?>' value="<?php echo (isset($testResultAttribute['above_threshold'])) ? $testResultAttribute['above_threshold'] : "" ?> " />
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
												<td><input type="text" name="resultConfig[quantitative_result][]" id="quantitativeResult1" class="form-control" placeholder="Result" title="Please enter the result" /></td>
												<th scope="row">Result Interpretation<span class="mandatory">*</span></th>
												<td><input type="text" id="quantitativeResultInterpretation1" name="resultConfig[quantitative_result_interpretation][]" class="form-control" placeholder="Enter result interpretation" title="Please enter result interpretation"></td>
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
							<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _("Submit"); ?></a>
							<a href="test-type.php" class="btn btn-default"> <?php echo _("Cancel"); ?></a>
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

	$(document).ready(function() {
		$('input').tooltip();
		generateRandomString('1');
		$("#sampleType").select2({
			placeholder: "<?php echo _("Select Sample Type"); ?>"
		});
		$("#testingReason").select2({
			placeholder: "<?php echo _("Select Testing Reason"); ?>"
		});
		$("#testFailureReason").select2({
			placeholder: "<?php echo _("Select Test Failure Reason"); ?>"
		});
		$("#rejectionReason").select2({
			placeholder: "<?php echo _("Select Rejection Reason"); ?>"
		});
		$("#symptoms").select2({
			placeholder: "<?php echo _("Select Symptoms"); ?>"
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
	curVal = prevVal.replace(htmlVal+',', "");
	$(this).parent().parent().prev(".fdropDown").val(curVal);
    $(this).parent().remove();
  });

	});
function showTags(e,obj,cls)
{
	var options=new Array();
	if (e.key === ',' || e.key === 'Enter') {
		var val = obj.value;
      	if (val.length > 0) {
        var tag = val.split(',')[0].trim();
        $('.container'+cls).append('<div class="tag">' + tag + '<span class="remove-tag">x</span></div>');
		options.push(tag);
		obj.value = "";
		obj.removeClass('isRequired');

      }
	  
    }
	for(let i = 0; i < options.length; i++){
		$('#fdropDown'+cls).val($('#fdropDown'+cls).val()+options[i]+',');
		
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
		f.setAttribute("align", "center");
		f.setAttribute("style", "vertical-align:middle");
		tagClass = 'container'+tableRowId;
		b.innerHTML = '<input type="text" name="fieldName[]" id="fieldName' + tableRowId + '" class="isRequired fieldName form-control" placeholder="<?php echo _('Field Name'); ?>" title="<?php echo _('Please enter field name'); ?>" onblur="checkDublicateName(this, \'fieldName\');"/ ><input type="hidden" name="fieldId[]" id="fieldId' + tableRowId + '" class="form-control isRequired" />';
		c.innerHTML = '<select class="form-control isRequired" name="fieldType[]" id="fieldType' + tableRowId + '" title="<?php echo _('Please select the field type'); ?>" onchange="changeField(this, ' + tableRowId + ')">\
                            <option value=""> <?php echo _("-- Select --"); ?> </option>\
                            <option value="number"><?php echo _("Number"); ?></option>\
                            <option value="text"><?php echo _("Text"); ?></option>\
                            <option value="date"><?php echo _("Date"); ?></option>\
							<option value="dropdown"><?php echo _("Dropdown"); ?></option>\
							<option value="multiple"><?php echo _("Multiselect Dropdown"); ?></option>\
						</select><br>\
						<div class="tag-input dropDown'+tableRowId+'" style="display:none;"><input type="text" name="dropDown[]" id="dropDown'+tableRowId+'" onkeyup="showTags(event,this,'+tableRowId+')" class="tag-input-field form-control" placeholder="Enter options..." /><input type="hidden" class="fdropDown" id="fdropDown'+tableRowId+'" name="fdropDown[]" /><div class="tag-container container'+tableRowId+'"></div></div>';		d.innerHTML = '<select class="form-control isRequired" name="mandatoryField[]" id="mandatoryField' + tableRowId + '" title="<?php echo _('Please select is it mandatory'); ?>">\
                            <option value="yes"><?php echo _("Yes"); ?></option>\
                            <option value="no" selected><?php echo _("No"); ?></option>\
                        </select>';
		e.innerHTML = '<select class="form-control isRequired" name="section[]" id="section' + tableRowId + '" title="<?php echo _('Please select the section'); ?>" onchange="checkSection(' + tableRowId + ')">\
                        <option value=""> <?php echo _("-- Select --"); ?> </option>\
                        <option value="facilitySection"><?php echo _("Facility"); ?></option>\
						<option value="patientSection"><?php echo _("Patient"); ?></option>\
						<option value="specimenSection"><?php echo _("Specimen"); ?></option>\
						<option value="labSection"><?php echo _("Lab"); ?></option>\
						<option value="otherSection"><?php echo _("Other"); ?></option>\
                    </select>\
                    <input type="text" name="sectionOther[]" id="sectionOther' + tableRowId + '" class="form-control" placeholder="<?php echo _("Section Other"); ?>" title="<?php echo _("Please enter section other"); ?>" style="display:none;"/>';
		f.innerHTML = '<a class="btn btn-xs btn-primary" href="javascript:void(0);" onclick="insRow();"><em class="fa-solid fa-plus"></em></a>&nbsp;&nbsp;<a class="btn btn-xs btn-default" href="javascript:void(0);" onclick="removeAttributeRow(this.parentNode.parentNode);"><em class="fa-solid fa-minus"></em></a>';
		$(a).fadeIn(800);
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
				$("#fieldId" + rowId).val(data);
			});
	}

	function addResultRow(table) {
		let rowString = '';

		if (table == 'qualitativeTable') {
			testQualCounter++;
			rowString = `<tr>
				<td class="text-center">` + testQualCounter + `</td>
				<th scope="row">Result<span class="mandatory">*</span></th>
				<td><input type="text" name="resultConfig[result][]" id="result` + testQualCounter + `" class="form-control" placeholder="Result" title="Please enter the result" /></td>
				<th scope="row">Result Interpretation<span class="mandatory">*</span></th>
				<td><input type="text" id="resultInterpretation` + testQualCounter + `" name="resultConfig[result_interpretation][]" class="form-control" placeholder="Enter result interpretation" title="Please enter result interpretation"></td>
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
				<td><input type="text" name="resultConfig[quantitative_result][]" id="quantitativeResult` + testQuanCounter + `" class="form-control" placeholder="Result" title="Please enter the result" /></td>
				<th scope="row">Result Interpretation<span class="mandatory">*</span></th>
				<td><input type="text" id="quantitativeResultInterpretation` + testQuanCounter + `" name="resultConfig[quantitative_result_interpretation][]" class="form-control" placeholder="Enter result interpretation" title="Please enter result interpretation"></td>
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

	function changeField(obj, i){
		(obj.value == 'dropdown' || obj.value == 'multiple') ? ($('.dropDown'+i).show(), $('#dropDown'+i).addClass('isRequired')) : ($('.dropDown'+i).hide(), $('#dropDown'+i).removeClass('isRequired'));
	}
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
