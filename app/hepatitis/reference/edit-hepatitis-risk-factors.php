<?php


require_once APPLICATION_PATH . '/header.php';
$id = base64_decode($_GET['id']);
$riskfactorQuery = "SELECT * from r_hepatitis_risk_factors where riskfactor_id=$id";
$riskfactorInfo = $db->query($riskfactorQuery);
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-square-h"></em> Edit Hepatitis Risk Factors</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Hepatitis Risk Factors</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">

		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='editRiskFactor' id='editRiskFactor' autocomplete="off" enctype="multipart/form-data" action="save-hepatitis-risk-factor-helper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="riskFactorName" class="col-lg-4 control-label">Risk Factor Name<span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="riskFactorName" name="riskFactorName" value="<?php echo $riskfactorInfo[0]['riskfactor_name']; ?>" placeholder="Risk Factor Name" title="Please enter Risk Factor name" onblur="checkNameValidation('r_hepatitis_risk_factors','riskfactor_name',this,'<?php echo "riskfactor_id##" . $id; ?>','The Risk Factor name that you entered already exists.Enter another name',null)" />
										<input type="hidden" class="form-control isRequired" id="riskFactorId" name="riskFactorId" value="<?php echo base64_encode($riskfactorInfo[0]['riskfactor_id']); ?>" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="riskFactorStatus" class="col-lg-4 control-label">Risk Factor Status</label>
									<div class="col-lg-7">
										<select class="form-control isRequired" id="riskFactorStatus" name="riskFactorStatus" placeholder="Risk Factor Status" title="Please select Risk Factor Status">
											<option value="active" <?php echo ($riskfactorInfo[0]['riskfactor_status'] == "active" ? 'selected' : ''); ?>>Active</option>
											<option value="inactive" <?php echo ($riskfactorInfo[0]['riskfactor_status'] == "inactive" ? 'selected' : ''); ?>>Inactive</option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<br>
					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
						<a href="hepatitis-risk-factors.php" class="btn btn-default"> Cancel</a>
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
			formId: 'editRiskFactor'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('editRiskFactor').submit();
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
