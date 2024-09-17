<?php


require_once APPLICATION_PATH . '/header.php';
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-flask-vial"></em> <?php echo _translate("Add VL Test Failure Reasons"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("VL Test Failure Reasons"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">

		<div class="box box-default">
			<!-- /.box-header -->
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _translate("indicates required fields"); ?> &nbsp;</div>
			</div>
			<!-- form start -->
			<form class="form-horizontal" method='post' name='referenceForm' id='referenceForm' autocomplete="off" enctype="multipart/form-data" action="save-vl-test-failure-reason-helper.php">
				<div class="box-body">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="failureReason" class="col-lg-4 control-label"><?php echo _translate("Test Failure Reason"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="failureReason" name="failureReason" placeholder="<?php echo _translate('Enter Test Failure Reason'); ?>" title="<?php echo _translate('Please enter Test Failure Reason'); ?>" onblur='checkNameValidation("r_vl_test_failure_reasons","failure_reason",this,null,"<?php echo _translate("This failure reason that you entered already exists.Try another failure reason"); ?>",null)' />
									</div>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="status" class="col-lg-4 control-label"><?php echo _translate("Status"); ?></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" id="status" name="status" placeholder="<?php echo _translate('Select status'); ?>" title="<?php echo _translate('Please select art status'); ?>">
											<option value=""><?php echo _translate("--Select--"); ?></option>
											<option value="active"><?php echo _translate("Active"); ?></option>
											<option value="inactive"><?php echo _translate("Inactive"); ?></option>
										</select>
									</div>
								</div>
							</div>
						</div>
					</div>
					<br>
				</div>
				<!-- /.box-body -->
				<div class="box-footer">
					<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _translate("Submit"); ?></a>
					<a href="vl-test-failure-reasons.php" class="btn btn-default"> <?php echo _translate("Cancel"); ?></a>
				</div>
				<!-- /.box-footer -->
			</form>
			<!-- /.form -->
		</div>
	</section>
</div>
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
			formId: 'referenceForm'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('referenceForm').submit();
		}
	}

	function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
		var removeDots = obj.value.replace(/\./g, "");
		removeDots = removeDots.replace(/\,/g, "");
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
