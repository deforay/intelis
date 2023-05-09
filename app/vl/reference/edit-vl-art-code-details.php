<?php


require_once APPLICATION_PATH . '/header.php';
$artId = base64_decode($_GET['id']);

$artQ = "SELECT * FROM `r_vl_art_regimen` WHERE art_id = $artId";
$result = $db->query($artQ);
$artResult = $result[0];
$artParent = [];
$artQuery = "SELECT DISTINCT art_code, art_id FROM `r_vl_art_regimen` WHERE parent_art = 0 AND art_id != $artId";
$artInfo = $db->query($artQuery);
foreach ($artInfo as $art) {
	$artParent[$art['art_id']] = $art['art_code'];
}

$categoryQuery = "SELECT DISTINCT headings FROM `r_vl_art_regimen` GROUP BY headings";
$categoryInfo = $db->query($categoryQuery);
$categoryData = [];
foreach ($categoryInfo as $category) {
	$categoryData[$category['headings']] = ($category['headings']);
}
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-flask-vial"></em> Edit Viral Load ART Regimen</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Viral Load ART Regimen</li>
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
				<form class="form-horizontal" method='post' name='referenceForm' id='referenceForm' autocomplete="off" enctype="multipart/form-data" action="save-vl-art-code-details-helper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="artCode" class="col-lg-4 control-label">ART Code <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" value="<?php echo $artResult['art_code']; ?>" class="form-control isRequired" id="artCode" name="artCode" placeholder="Enter art code" title="Please enter art code" onblur="checkNameValidation('r_vl_art_regimen','art_code',this,'<?php echo "art_id##" . $artId; ?>','This art code that you entered already exists.Try another art code',null)" />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="category" class="col-lg-4 control-label">Category</label>
									<div class="col-lg-7">
										<select class="form-control select2" id="category" name="category" placeholder="Select category" title="Please select category">
											<?= $general->generateSelectOptions($categoryData, $artResult['headings'], '-- Select --'); ?>
										</select>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="parentArtCode" class="col-lg-4 control-label">Parent ART Code</label>
								<div class="col-lg-7">
									<select class="form-control select2" id="parentArtCode" name="parentArtCode" placeholder="Select parent art code" title="Please select parent art code">
										<?= $general->generateSelectOptions($artParent, $artResult['parent_art'], '-- Select --'); ?>
									</select>
								</div>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="artStatus" class="col-lg-4 control-label">Status <span class="mandatory">*</span></label>
								<div class="col-lg-7">
									<select class="form-control isRequired" id="artStatus" name="artStatus" placeholder="Select art status" title="Please select art status">
										<option value="">--Select--</option>
										<option value="active" <?php echo (isset($artResult['art_status']) && $artResult['art_status'] == 'active') ? "selected='selected'" : ""; ?>>Active</option>
										<option value="inactive" <?php echo (isset($artResult['art_status']) && $artResult['art_status'] == 'inactive') ? "selected='selected'" : ""; ?>>Inactive</option>
									</select>
								</div>
							</div>
						</div>
					</div>
					<br>
			</div>
			<!-- /.box-body -->
			<div class="box-footer">
				<input type="hidden" name="artCodeId" id="artCodeId" value="<?php echo htmlspecialchars($_GET['id']); ?>">
				<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
				<a href="vl-art-code-details.php" class="btn btn-default"> Cancel</a>
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
require_once APPLICATION_PATH . '/footer.php';
