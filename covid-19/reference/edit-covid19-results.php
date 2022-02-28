<?php
ob_start();
#require_once('../startup.php');
require_once(APPLICATION_PATH . '/header.php');
$id = base64_decode($_GET['id']);
$resultQuery = "SELECT * from r_covid19_results where result_id = '" . $id . "' ";
$resultInfo = $db->query($resultQuery);
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><i><svg style=" width: 20px; " aria-hidden="true" focusable="false" data-prefix="fas" data-icon="viruses" role="img" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 512" class="svg-inline--fa fa-viruses fa-w-20">
          <path fill="currentColor" d="M624,352H611.88c-28.51,0-42.79-34.47-22.63-54.63l8.58-8.57a16,16,0,1,0-22.63-22.63l-8.57,8.58C546.47,294.91,512,280.63,512,252.12V240a16,16,0,0,0-32,0v12.12c0,28.51-34.47,42.79-54.63,22.63l-8.57-8.58a16,16,0,0,0-22.63,22.63l8.58,8.57c20.16,20.16,5.88,54.63-22.63,54.63H368a16,16,0,0,0,0,32h12.12c28.51,0,42.79,34.47,22.63,54.63l-8.58,8.57a16,16,0,1,0,22.63,22.63l8.57-8.58c20.16-20.16,54.63-5.88,54.63,22.63V496a16,16,0,0,0,32,0V483.88c0-28.51,34.47-42.79,54.63-22.63l8.57,8.58a16,16,0,1,0,22.63-22.63l-8.58-8.57C569.09,418.47,583.37,384,611.88,384H624a16,16,0,0,0,0-32ZM480,384a32,32,0,1,1,32-32A32,32,0,0,1,480,384ZM346.51,213.33h16.16a21.33,21.33,0,0,0,0-42.66H346.51c-38,0-57.05-46-30.17-72.84l11.43-11.44A21.33,21.33,0,0,0,297.6,56.23L286.17,67.66c-26.88,26.88-72.84,7.85-72.84-30.17V21.33a21.33,21.33,0,0,0-42.66,0V37.49c0,38-46,57.05-72.84,30.17L86.4,56.23A21.33,21.33,0,0,0,56.23,86.39L67.66,97.83c26.88,26.88,7.85,72.84-30.17,72.84H21.33a21.33,21.33,0,0,0,0,42.66H37.49c38,0,57.05,46,30.17,72.84L56.23,297.6A21.33,21.33,0,1,0,86.4,327.77l11.43-11.43c26.88-26.88,72.84-7.85,72.84,30.17v16.16a21.33,21.33,0,0,0,42.66,0V346.51c0-38,46-57.05,72.84-30.17l11.43,11.43a21.33,21.33,0,0,0,30.17-30.17l-11.43-11.43C289.46,259.29,308.49,213.33,346.51,213.33ZM160,192a32,32,0,1,1,32-32A32,32,0,0,1,160,192Zm80,32a16,16,0,1,1,16-16A16,16,0,0,1,240,224Z" class=""></path>
        </svg></i> Edit Covid-19 Results</h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
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
										<input type="text" readonly class="form-control isRequired" id="resultName" name="resultName" value="<?php echo $resultInfo[0]['result']; ?>" placeholder="Result Name" title="Please enter Result name" onblur="checkNameValidation('r_covid19_results','result',this,'<?php echo "result_id##" . $id; ?>','The Result name that you entered already exists.Enter another name',null)" />
										<input type="hidden" class="form-control" id="resultId" name="resultId" value="<?php echo base64_encode($id); ?>" />
										<input type="hidden" class="form-control" id="oldResultName" name="oldResultName" value="<?php echo $resultInfo[0]['result']; ?>" />
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
?>