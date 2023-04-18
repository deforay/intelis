<?php
ob_start();

require_once(APPLICATION_PATH . '/header.php');

?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-square-h"></em> <?php echo _("Add Hepatitis Risk Factors");?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home");?></a></li>
			<li class="active"><?php echo _("Hepatitis Risk Factors");?></li>
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
				<form class="form-horizontal" method='post' name='addRiskFactor' id='addRiskFactor' autocomplete="off" enctype="multipart/form-data" action="save-hepatitis-risk-factor-helper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="riskFactorName" class="col-lg-4 control-label"><?php echo _("Risk Factor Name");?><span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="riskFactorName" name="riskFactorName" placeholder="<?php echo _('Risk Factor Name');?>" title="<?php echo _('Please enter Risk Factor name');?>" onblur='checkNameValidation("r_hepatitis_risk_factors","riskfactor_name",this,null,"<?php echo _("The Risk Factor name that you entered already exists.Enter another name");?>",null)' />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="riskFactorStatus" class="col-lg-4 control-label"><?php echo _("Risk Factor Status");?></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" id="riskFactorStatus" name="riskFactorStatus" placeholder="<?php echo _('Risk Factor Status');?>" title="<?php echo _('Please select Risk Factor Status');?>">
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
						<a href="hepatitis-risk-factor.php" class="btn btn-default"> <?php echo _("Cancel");?></a>
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
			formId: 'addRiskFactor'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('addRiskFactor').submit();
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
