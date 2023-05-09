<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\InstrumentsService;



require_once APPLICATION_PATH . '/header.php';
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$instrumentsDb = new InstrumentsService();
$activeInstruments = $instrumentsDb->getInstruments(null, true);
$instrumentsDropdown = $general->generateSelectOptions($activeInstruments, null, "-- Select --");
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-flask-vial"></em> <?php echo _("Add VL Results"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("VL Results"); ?></li>
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
				<form class="form-horizontal" method='post' name='addResults' id='addResults' autocomplete="off" enctype="multipart/form-data" action="save-vl-results-helper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="viralLoadResult" class="col-lg-4 control-label"><?php echo _("Viral Load Result"); ?><span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="resultName" name="resultName" placeholder="<?php echo _('Viral Load Result'); ?>" title="<?php echo _('Please enter Result name'); ?>" onblur='checkNameValidation("r_vl_results","result",this,null,"<?php echo _("The Result name that you entered already exists.Enter another name"); ?>",null)' />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="resultStatus" class="col-lg-4 control-label"><?php echo _("Result Status"); ?></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" id="resultStatus" name="resultStatus" placeholder="<?php echo _('Result Status'); ?>" title="<?php echo _('Please select Result Status'); ?>">
											<option value="active"><?php echo _("Active"); ?></option>
											<option value="inactive"><?php echo _("Inactive"); ?></option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="resultName" class="col-lg-4 control-label"><?php echo _("Interpretation"); ?><span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name="interpretation" id="interpretation">
											<option value="">--Select--</option>
											<option value="suppressed">Suppressed</option>
											<option value="not suppressed">Not Suppressed</option>
											<option value="error">Error</option>
											<option value="failed">Failed</option>
											<option value="no result">No Result</option>
										</select>
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="resultStatus" class="col-lg-4 control-label"><?php echo _("Available For Instrument"); ?></label>
									<div class="col-lg-7">
										<select style="width: 275px;" class="form-control" id="instruments" name="instruments[]" title="<?php echo _('Please select instruments'); ?>" multiple="multiple">
											<?= $instrumentsDropdown;  ?>
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
						<a href="vl-results.php" class="btn btn-default"> <?php echo _("Cancel"); ?></a>
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
<script type="text/javascript" src="/assets/js/multiselect.min.js"></script>
<script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script>
<script type="text/javascript">
	$(document).ready(function() {
		$("#instruments").select2({
			placeholder: "<?php echo _("Select Instruments"); ?>"
		});


	});

	function validateNow() {

		flag = deforayValidator.init({
			formId: 'addResults'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('addResults').submit();
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
