<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\InstrumentsService;



require_once APPLICATION_PATH . '/header.php';
// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_GET = $request->getQueryParams();
$id = (isset($_GET['id'])) ? base64_decode($_GET['id']) : null;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$instrumentsDb = new InstrumentsService();
$resultQuery = "SELECT * from r_vl_results where result_id = '" . $id . "' ";
$resultInfo = $db->query($resultQuery);

$activeInstruments = $instrumentsDb->getInstruments(null, true);
$selectedInstruments = json_decode($resultInfo[0]['available_for_instruments'], true);
$instrumentsDropdown = $general->generateSelectOptions($activeInstruments, $selectedInstruments, "-- Select --");
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-flask-vial"></em> Edit VL Results</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">VL Results</li>
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
				<form class="form-horizontal" method='post' name='editresult' id='editresult' autocomplete="off" enctype="multipart/form-data" action="save-vl-results-helper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="viralLoadResult" class="col-lg-4 control-label">Viral Load Result<span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="resultName" name="resultName" value="<?php echo $resultInfo[0]['result']; ?>" placeholder="Viral Load Result" title="Please enter Result name" readonly onblur="checkNameValidation('r_vl_results','result',this,'<?php echo "result_id##" . htmlspecialchars($id); ?>','The Result name that you entered already exists.Enter another name',null)" />
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
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="resultName" class="col-lg-4 control-label"><?php echo _("Interpretation"); ?><span class="mandatory">*</span></label>
									<div class="col-lg-7">
										<select class="form-control isRequired" name="interpretation" id="interpretation">
											<option value="">--Select--</option>
											<option value="suppressed" <?php if ($resultInfo[0]['interpretation'] == "suppressed") echo "selected='selected'"; ?>>Suppressed</option>
											<option value="not suppressed" <?php if ($resultInfo[0]['interpretation'] == "not suppressed") echo "selected='selected'"; ?>>Not Suppressed</option>
											<option value="error" <?php if ($resultInfo[0]['interpretation'] == "error") echo "selected='selected'"; ?>>Error</option>
											<option value="failed" <?php if ($resultInfo[0]['interpretation'] == "failed") echo "selected='selected'"; ?>>Failed</option>
											<option value="no result" <?php if ($resultInfo[0]['interpretation'] == "no result") echo "selected='selected'"; ?>>No Result</option>
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
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
						<a href="vl-results.php" class="btn btn-default"> Cancel</a>
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
		$("#instruments").select2({
			placeholder: "<?php echo _("Select Instruments"); ?>"
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
require_once APPLICATION_PATH . '/footer.php';
