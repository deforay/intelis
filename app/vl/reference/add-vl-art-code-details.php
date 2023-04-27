<?php


require_once(APPLICATION_PATH . '/header.php');
$artQuery = "SELECT DISTINCT art_code, art_id FROM `r_vl_art_regimen` WHERE parent_art = 0";
$artInfo = $db->query($artQuery);
$artParent = [];
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
		<h1><em class="fa-solid fa-flask-vial"></em> <?php echo _("Add Viral Load ART Regimen"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Viral Load ART Regimen"); ?></li>
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
				<form class="form-horizontal" method='post' name='referenceForm' id='referenceForm' autocomplete="off" enctype="multipart/form-data" action="save-vl-art-code-details-helper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="artCode" class="col-lg-4 control-label"><?php echo _("ART Code"); ?> <span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="artCode" name="artCode" placeholder="<?php echo _('Enter art code'); ?>" title="<?php echo _('Please enter art code'); ?>" onblur='checkNameValidation("r_vl_art_regimen","art_code",this,null,"<?php echo _("This art code that you entered already exists.Try another art code"); ?>",null)' />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="category" class="col-lg-4 control-label"><?php echo _("Category"); ?></label>
									<div class="col-lg-7">
										<select class="form-control select2" id="category" name="category" placeholder="<?php echo _('Select category'); ?>" title="<?php echo _('Please select category'); ?>">
											<?= $general->generateSelectOptions($categoryData, null, _("-- Select --")); ?>
										</select>
									</div>
								</div>
							</div>
						</div>
					</div>
					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="parentArtCode" class="col-lg-4 control-label"><?php echo _("Parent ART Code"); ?></label>
								<div class="col-lg-7">
									<select class="form-control select2" id="parentArtCode" name="parentArtCode" placeholder="<?php echo _('Select parent art code'); ?>" title="<?php echo _('Please select parent art code'); ?>">
										<option value=""><?php echo _("--Select--"); ?></option>
										<?= $general->generateSelectOptions($artParent, null, '-- Select --'); ?>
									</select>
								</div>
							</div>
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="artStatus" class="col-lg-4 control-label"><?php echo _("Status"); ?></label>
								<div class="col-lg-7">
									<select class="form-control isRequired" id="artStatus" name="artStatus" placeholder="<?php echo _('Select art status'); ?>" title="<?php echo _('Please select art status'); ?>">
										<option value=""><?php echo _("--Select--"); ?></option>
										<option value="active"><?php echo _("Active"); ?></option>
										<option value="inactive"><?php echo _("Inactive"); ?></option>
									</select>
								</div>
							</div>
						</div>
					</div>
					<br>
			</div>
			<!-- /.box-body -->
			<div class="box-footer">
				<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _("Submit"); ?></a>
				<a href="vl-art-code-details.php" class="btn btn-default"> <?php echo _("Cancel"); ?></a>
			</div>
			<!-- /.box-footer -->

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
require_once(APPLICATION_PATH . '/footer.php');
