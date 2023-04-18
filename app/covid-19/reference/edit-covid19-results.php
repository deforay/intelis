<?php
ob_start();

require_once(APPLICATION_PATH . '/header.php');
$id = base64_decode($_GET['id']);
$resultQuery = "SELECT * from r_covid19_results where result_id = '" . $id . "' ";
$resultInfo = $db->query($resultQuery);
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-virus-covid"></em> Edit Covid-19 Results</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Covid-19 Results</li>
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
				<form class="form-horizontal" method='post' name='editresult' id='editresult' autocomplete="off" enctype="multipart/form-data" action="save-covid19-results-helper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="resultName" class="col-lg-4 control-label">Result Name<span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="resultName" name="resultName" value="<?php echo $resultInfo[0]['result']; ?>" placeholder="Result Name" title="Please enter Result name" onblur="checkNameValidation('r_covid19_results','result',this,'<?php echo "result_id##" . htmlspecialchars($id); ?>','The Result name that you entered already exists.Enter another name',null)" />
										<input type="hidden" class="form-control" id="resultId" name="resultId" value="<?php echo base64_encode($id); ?>" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="resultStatus" class="col-lg-4 control-label">Result Status</label>
									<div class="col-lg-7">
										<select class="form-control isRequired" id="resultStatus" name="resultStatus" placeholder="Result Status" title="Please select Result Status">
											<option value="active" <?php echo ($resultInfo[0]['status'] == "active" ? 'selected' : ''); ?>>Active</option>
											<option value="inactive" <?php echo ($resultInfo[0]['status'] == "inactive" ? 'selected' : ''); ?>>Inactive</option>
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
						<a href="covid19-results.php" class="btn btn-default"> Cancel</a>
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
			formId: 'editresult'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('editresult').submit();
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
