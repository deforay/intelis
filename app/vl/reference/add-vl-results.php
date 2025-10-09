<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\InstrumentsService;
use App\Registries\ContainerRegistry;



require_once APPLICATION_PATH . '/header.php';
/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var InstrumentsService $instrumentsService */
$instrumentsService = ContainerRegistry::get(InstrumentsService::class);

$activeInstruments = $instrumentsService->getInstruments(testType: null, dropDown: true, withFacility: true);
$instrumentsDropdown = $general->generateSelectOptions($activeInstruments, null);
?>
<link href="/assets/css/jasny-bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="/assets/css/jquery.multiselect.css" type="text/css" />

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-flask-vial"></em>
			<?php echo _translate("Add VL Results"); ?>
		</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em>
					<?php echo _translate("Home"); ?>
				</a></li>
			<li class="active">
				<?php echo _translate("VL Results"); ?>
			</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">

		<div class="box box-default">
			<div class="box-header with-border">
				<div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span>
					<?php echo _translate("indicates required fields"); ?> &nbsp;
				</div>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='addResults' id='addResults' autocomplete="off" enctype="multipart/form-data" action="save-vl-results-helper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="viralLoadResult" class="col-lg-4 control-label">
										<?php echo _translate("Viral Load Result"); ?><span class="mandatory">*</span>
									</label>
									<div class="col-lg-7">
										<input type="text" class="form-control isRequired" id="resultName" name="resultName" placeholder="<?php echo _translate('Viral Load Result'); ?>" title="<?php echo _translate('Please enter Result name'); ?>" onblur='checkNameValidation("r_vl_results","result",this,null,"<?php echo _translate("The Result name that you entered already exists.Enter another name"); ?>",null)' />
									</div>
								</div>
							</div>
							<div class="col-md-6">
								<div class="form-group">
									<label for="resultStatus" class="col-lg-4 control-label">
										<?php echo _translate("Result Status"); ?>
									</label>
									<div class="col-lg-7">
										<select class="form-control isRequired" id="resultStatus" name="resultStatus" placeholder="<?php echo _translate('Result Status'); ?>" title="<?php echo _translate('Please select Result Status'); ?>">
											<option value="active">
												<?php echo _translate("Active"); ?>
											</option>
											<option value="inactive">
												<?php echo _translate("Inactive"); ?>
											</option>
										</select>
									</div>
								</div>
							</div>
						</div>
						<div class="row">
							<div class="col-md-6">
								<div class="form-group">
									<label for="resultName" class="col-lg-4 control-label">
										<?php echo _translate("Interpretation"); ?><span class="mandatory">*</span>
									</label>
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

						</div>
						<div class="row">
							<div class="col-md-12">
								<h4 style="font-weight:bold;"> <?php echo _translate("Available For Instrument"); ?></h4>

								<div class="col-md-5">
									<select name="instruments[]" id="search" class="form-control" size="8" multiple="multiple">
										<?php foreach ($activeInstruments as $key => $ins) {
										?>
											<option value="<?php echo $key; ?>"><?php echo $ins; ?> </option>
										<?php
										} ?>
									</select>
									<div class="sampleCounterDiv"><?= _translate("Number of unselected instruments"); ?> : <span id="unselectedCount"></span></div>
								</div>

								<div class="col-md-2">
									<button type="button" id="search_rightAll" class="btn btn-block"><em class="fa-solid fa-forward"></em></button>
									<button type="button" id="search_rightSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-right"></em></button>
									<button type="button" id="search_leftSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-left"></em></button>
									<button type="button" id="search_leftAll" class="btn btn-block"><em class="fa-solid fa-backward"></em></button>
								</div>

								<div class="col-md-5">
									<select name="to[]" id="search_to" class="form-control" size="8" multiple="multiple">
										<?php foreach ($selectedInstruments as $value) {
											if (!empty($activeInstruments) && array_key_exists($value, $activeInstruments)) { ?>
												<option value="<?php echo $value; ?>"><?php echo $activeInstruments[$value]; ?> </option>
										<?php }
										} ?>
									</select>
									<div class="sampleCounterDiv"><?= _translate("Number of selected instruments"); ?> : <span id="selectedCount"></span></div>
								</div>
							</div>
						</div>
						<br>

					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<input type="hidden" name="selectedInstruments" id="selectedInstruments" />

						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">
							<?php echo _translate("Submit"); ?>
						</a>
						<a href="vl-results.php" class="btn btn-default">
							<?php echo _translate("Cancel"); ?>
						</a>
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
<script type="text/javascript" src="/assets/js/jquery.multiselect.js"></script>

<script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script>
<script type="text/javascript">
	$(document).ready(function() {

		$('#search').deforayDualBox({
			search: {
				left: '<input type="text" name="q" class="form-control" placeholder="<?php echo _translate("Search"); ?>..." />',
				right: '<input type="text" name="q" class="form-control" placeholder="<?php echo _translate("Search"); ?>..." />',
			},
			fireSearch: function(value) {
				return value.length > 2;
			},
			autoSelectNext: true,
			keepRenderingSort: true
		});

		$("#instruments").multipleSelect({
			placeholder: 'Select Instruments',
			width: '100%'
		});


	});


	function validateNow() {
		$("#search").val(""); // THIS IS IMPORTANT. TO REDUCE NUMBER OF PHP VARIABLES
		var selVal = [];
		$('#search_to option').each(function(i, selected) {
			selVal[i] = $(selected).val();
		});
		$("#selectedInstruments").val(selVal);
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
