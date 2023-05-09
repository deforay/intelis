<?php


require_once APPLICATION_PATH . '/header.php';
$testQuery = "SELECT * from r_hepatitis_test_reasons WHERE parent_reason ='0'";
$testInfo = $db->query($testQuery);
foreach ($testInfo as $test) {
	$testParent[$test['test_reason_id']] = $test['test_reason_name'];
}
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-square-h"></em> <?php echo _("Add Hepatitis Test Reasons");?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home");?></a></li>
			<li class="active"><?php echo _("Hepatitis Test Reasons");?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">

		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _("indicates required field");?> &nbsp;</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='addTstForm' id='addTstForm' autocomplete="off" enctype="multipart/form-data" action="save-hepatitis-test-reasons-helper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="testReasonName" class="col-lg-4 control-label"><?php echo _("Test Reason Name");?><span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="testReasonName" name="testReasonName" placeholder="<?php echo _('Test Reason Name');?>" title="<?php echo _('Please enter Test Reason name');?>" onblur='checkNameValidation("r_hepatitis_test_reasons","test_reason_name",this,null,"<?php echo _("The Test Reason name that you entered already exists.Enter another name");?>",null)' />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="parentReason" class="col-lg-4 control-label"><?php echo _("Parent Reason");?></label>
									<div class="col-lg-7">
										<select class="form-control select2" id="parentReason" name="parentReason" placeholder="<?php echo _('Parent Reason');?>" title="<?php echo _('Please enter Parent Reason');?>">
											<?= $general->generateSelectOptions($testParent, null, _("-- Select --")); ?>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="testReasonStatus" class="col-lg-4 control-label"><?php echo _("Test Reason Status");?></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" id="testReasonStatus" name="testReasonStatus" placeholder="<?php echo _('Test Reason Status');?>" title="<?php echo _('Please select Test Reason Status');?>">
											<option value="active"><?php echo _("Active");?></option>
											<option value="inactive"><?php echo _("Inactive");?></option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<br>
					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _("Submit");?></a>
						<a href="hepatitis-test-reasons.php" class="btn btn-default"> <?php echo _("Cancel");?></a>
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
	$(document).ready(function() {
		$(".select2").select2();
		$(".select2").select2({
			tags: true
		});
	});

	function validateNow() {

		flag = deforayValidator.init({
			formId: 'addTstForm'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('addTstForm').submit();
		}
	}

	function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
		var removeDots = obj.value.replace(/\./g, "");
		var removeDots = removeDots.replace(/\,/g, "");
		//str=obj.value;
		removeDots = removeDots.replace(/\s{2,}/g, ' ');

		$.post("/includes/checkDuplicate.php", {
				tableName: tableName,
				fieldName: fieldName,
				value: removeDots.trim(),
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
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
