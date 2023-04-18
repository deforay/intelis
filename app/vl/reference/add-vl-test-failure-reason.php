<?php
ob_start();

require_once(APPLICATION_PATH . '/header.php');
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-flask-vial"></em> <?php echo _("Add VL Test Failure Reasons"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("VL Test Failure Reasons"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">

		<div class="box box-default">
			<!-- /.box-header -->
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> <?php echo _("indicates required field"); ?> &nbsp;</div>
			</div>
			<!-- form start -->
			<form class="form-horizontal" method='post' name='referenceForm' id='referenceForm' autocomplete="off" enctype="multipart/form-data" action="save-vl-test-failure-reason-helper.php">
				<div class="box-body">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="failureReason" class="col-lg-4 control-label"><?php echo _("Test Failure Reason"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="failureReason" name="failureReason" placeholder="<?php echo _('Enter Test Failure Reason'); ?>" title="<?php echo _('Please enter Test Failure Reason'); ?>" onblur='checkNameValidation("r_vl_test_failure_reasons","failure_reason",this,null,"<?php echo _("This failure reason that you entered already exists.Try another failure reason"); ?>",null)' />
									</div>
								</div>
							</div>

							<div class="col-md-6">
								<div class="form-group">
									<label for="status" class="col-lg-4 control-label"><?php echo _("Status"); ?></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" id="status" name="status" placeholder="<?php echo _('Select status'); ?>" title="<?php echo _('Please select art status'); ?>">
											<option value=""><?php echo _("--Select--"); ?></option>
											<option value="active"><?php echo _("Active"); ?></option>
											<option value="inactive"><?php echo _("Inactive"); ?></option>
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
					<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _("Submit"); ?></a>
					<a href="vl-test-failure-reasons.php" class="btn btn-default"> <?php echo _("Cancel"); ?></a>
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
require_once(APPLICATION_PATH . '/footer.php');
