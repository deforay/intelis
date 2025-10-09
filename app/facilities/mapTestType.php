<?php


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */

use App\Registries\AppRegistry;

$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$mappingType = $_GET['type'] ?? 'health-facilities';
$testType = $_GET['test'] ?? null;
if ($mappingType == 'health-facilities') {
	$title = "Health Facilities";
} elseif ($mappingType == 'testing-labs') {
	$title = "Testing Labs";
}

require_once APPLICATION_PATH . '/header.php';



?>
<link href="/assets/css/jasny-bootstrap.min.css" rel="stylesheet" />
<link href="/assets/css/multi-select.css" rel="stylesheet" />
<style>
	.select2-selection__choice {
		color: #000000 !important;
	}

	.boxWidth,
	.eid_boxWidth {
		width: 10%;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-hospital"></em> <?php echo $title; ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"> <?php echo $title; ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">

		<div class="box box-default">
			<!-- /.box-header -->
			<div class="box-body">
				<!-- form start -->
				<form class="form-horizontal" method='post' name='facilityTestMapForm' id='facilityTestMapForm' enctype="multipart/form-data" autocomplete="off" action="mapTestTypeHelper.php">
					<div class="box-body">
						<div class="panel panel-default">
							<div class="panel-heading">
								<h3 class="panel-title"> <?php echo $title; ?></h3>
							</div>
							<div class="panel-body">
								<div class="row">
									<div class="col-md-7">
										<div class="form-group">
											<label for="testType" class="col-lg-4 control-label"><?php echo _translate("Test Type"); ?></label>
											<div class="col-lg-8">
												<select class="form-control" id="testType" name="testType" title="<?= _translate('Choose at least one test type'); ?>" onchange="loadMapTestType();">
													<option value=""><?php echo _translate("--Select--"); ?></option>
													<?php if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) { ?>
														<option value="vl"><?php echo _translate("Viral Load"); ?></option>
													<?php }
													if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) { ?>
														<option value="eid"><?php echo _translate("Early Infant Diagnosis"); ?></option>
													<?php }
													if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) { ?>
														<option value="covid19"><?php echo _translate("Covid-19"); ?></option>
													<?php }
													if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) { ?>
														<option value='hepatitis'><?php echo _translate("Hepatitis"); ?></option>
													<?php }
													if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) { ?>
														<option value='tb'><?php echo _translate("TB"); ?></option>
													<?php }
													if (isset(SYSTEM_CONFIG['modules']['cd4']) && SYSTEM_CONFIG['modules']['cd4'] === true) { ?>
														<option value='cd4'><?php echo _translate("CD4"); ?></option>
													<?php }
													if (isset(SYSTEM_CONFIG['modules']['generic-tests']) && SYSTEM_CONFIG['modules']['generic-tests'] === true) { ?>
														<option value='generic-tests'><?php echo _translate("Other Lab Tests"); ?></option>
													<?php } ?>
												</select>
											</div>
										</div>
									</div>
								</div>

								<div class="row">
									<div class="col-md-12">
										<div class="form-group">
											<label for="facilities" style="margin-left: 30px;" class="control-label"><?php echo _translate("Select the"); ?> <?php echo str_replace("Manage", "", (string) $title); ?> <?php echo _translate("for test type"); ?> </label>
											<div class="col-lg-12">
												<div class="col-md-5">
													<select name="facilities[]" id="search" class="form-control" size="8" multiple="multiple">

													</select>
													<div class="sampleCounterDiv"><?= _translate("Number of unselected facilities"); ?> : <span id="unselectedCount"></span></div>
												</div>

												<div class="col-md-2">
													<button type="button" id="search_rightAll" class="btn btn-block"><em class="fa-solid fa-forward"></em></button>
													<button type="button" id="search_rightSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-right"></em></button>
													<button type="button" id="search_leftSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-left"></em></button>
													<button type="button" id="search_leftAll" class="btn btn-block"><em class="fa-solid fa-backward"></em></button>
												</div>

												<div class="col-md-5">
													<select name="to[]" id="search_to" class="form-control" size="8" multiple="multiple"></select>
													<div class="sampleCounterDiv"><?= _translate("Number of selected facilities"); ?> : <span id="selectedCount"></span></div>
												</div>

											</div>
										</div>
									</div>
								</div>
							</div>
						</div>
						<!-- /.box-body -->
						<div class="box-footer">
							<input type="hidden" name="mappingType" class="form-control" id="mappingType" value="<?= $mappingType; ?>" />
							<input type="hidden" name="selectedFacilities" id="selectedFacilities" />
							<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _translate("Submit"); ?></a>
							<a href="facilities.php" class="btn btn-default"> <?php echo _translate("Cancel"); ?></a>
						</div>
						<!-- /.box-footer -->
					</div>
				</form>
				<!-- /.row -->
			</div>

		</div>
		<!-- /.box -->

	</section>
	<!-- /.content -->
</div>

<script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script>
<script type="text/javascript">
	$(document).ready(function() {
		let testType = "<?= !empty($testType) ? $testType : '' ?>";
		if (testType != "") {
			$("#testType").val(testType);
			selectedTestType();
		}
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

	});

	function validateNow() {



		var selVal = [];
		$('#search_to option').each(function(i, selected) {
			selVal[i] = $(selected).val();
		});
		const sqids = new Sqids()
		$("#selectedFacilities").val(sqids.encode(selVal));
		flag = deforayValidator.init({
			formId: 'facilityTestMapForm'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('facilityTestMapForm').submit();
		}
	}

	function loadMapTestType() {
		window.location.href = "mapTestType.php?type=<?= $mappingType; ?>&test=" + $('#testType').val();
	}

	function selectedTestType() {
		$.blockUI({
			message: '<h3><?= _translate("Trying to get mapped facilities", true); ?> <br><?php echo _translate("Please wait", true); ?>...</h3>'
		});
		$.post("getTestTypeFacilitiesHelper.php", {
				mappingType: "<?= $mappingType; ?>",
				testType: $('#testType').val()
			},
			function(toAppend) {
				if (toAppend != "" && toAppend != null && toAppend != undefined) {
					$('#search').html(toAppend)
					setTimeout(function() {
						$("#search_rightSelected").trigger('click');
					}, 10);
				} else {
					$('#search').html("");
					//alert("<?= _translate("No facilities found for the selected facility type. Please add a new facility or edit an existing facility.", true); ?>");
				}
				$.unblockUI();
			});
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
