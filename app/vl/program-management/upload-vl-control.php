<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;

require_once APPLICATION_PATH . '/header.php';

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$testingLabs = $facilitiesService->getTestingLabs('vl');
$testingLabsDropdown = $general->generateSelectOptions($testingLabs, null, "-- Select --");

//Get active machines
$testPlatformResult = $general->getTestingPlatforms('vl');

if (isset($_GET['total'])) {
	$addedRecords = $_GET['total'] - $_GET['notAdded'];
}
$formatFilePath = WEB_ROOT . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'controls' . DIRECTORY_SEPARATOR . 'VL_Controls_Bulk_Upload_Excel_Format.xlsx';

?>
<style>
	.ms-choice {
		border: 0px solid #aaa;
	}
</style>
<link href="/assets/css/jasny-bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="/assets/css/jquery.multiselect.css" type="text/css" />
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-hospital"></em>
			<?php echo _translate("Upload Controls in Bulk"); ?>
		</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em>
					<?php echo _translate("Home"); ?>
				</a></li>
			<li class="active">
				<?php echo _translate("Controls"); ?>
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
				<form class="form-horizontal" method='post' name='uploadControlForm' id='uploadControlForm'
					autocomplete="off" enctype="multipart/form-data" action="upload-vl-control-helper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-12">
								<?php if (isset($_GET['total']) && $_GET['total'] > 0) { ?>
									<h3 style="margin-left:100px; color:green;">
										<?= _translate("Total number of records in file"); ?> : <?= $_GET['total']; ?> |
										<?= _translate("Number of Controls added"); ?> : <?= $addedRecords; ?> |
										<?= _translate("Number of Controls not added"); ?> : <?= $_GET['notAdded']; ?></h3>
									<?php if ($_GET['notAdded'] > 0) { ?>
										<a class="text-danger"
											style="text-decoration:underline;margin-left:104px; margin-bottom:10px; font-weight: bold;"
											href="/temporary/INCORRECT-CONTROLS-ROWS.xlsx" download>Download the Excel Sheet
											with not uploaded controls</a><br><br>
									<?php } ?>
								<?php } ?>
								<div class="form-group">
									<label for="labName" class="col-lg-2 control-label">
										<?= _translate("Lab Name"); ?> <span class="mandatory">*</span>
									</label>
									<div class="col-lg-5">
										<select class="form-control isRequired" id="labName" name="labName"
											title="<?php echo _translate('Please select lab name'); ?>">
											<?= $testingLabsDropdown; ?>
										</select>
									</div>
								</div>
								<div class="form-group">
									<label for="machineName" class="col-lg-2 control-label">
										<?= _translate("Instruments"); ?> <span class="mandatory">*</span>
									</label>
									<div class="col-lg-5">
										<select name="machineName" id="machineName" class="form-control isRequired"
											title="<?php echo _translate('Please choose machine'); ?>">
											<option value="">
												<?php echo _translate("-- Select --"); ?>
											</option>
											<?php foreach ($testPlatformResult as $machine) { ?>
												<option value="<?php echo $machine['instrument_id']; ?>">
													<?= $machine['machine_name']; ?></option>
											<?php } ?>
										</select>
									</div>
								</div>

								<div class="form-group">
									<label for="controlInfo" class="col-lg-2 control-label">
										<?= _translate("Upload File"); ?> <span class="mandatory">*</span>
									</label>
									<div class="col-lg-5">
										<input type="file" class="form-control isRequired" id="controlInfo"
											name="controlInfo" title="<?= _translate('Click to upload file'); ?>" />
										<?php if (file_exists($formatFilePath)) { ?>
											<a class="text-primary" style="text-decoration:underline;"
												href="/files/controls/VL_Controls_Bulk_Upload_Excel_Format.xlsx"
												download><?= _translate("Click here to download the Excel format for uploading vl controls in bulk"); ?></a>
										<?php } ?>
									</div>
								</div>
							</div>

						</div>
					</div>
			</div>
			<!-- /.box-body -->
			<div class="box-footer">
				<input type="hidden" name="selectedUser" id="selectedUser" />
				<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">
					<?php echo _translate("Submit"); ?>
				</a>
				<a href="vlControlReport.php" class="btn btn-default">
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
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
	$(document).ready(function () {
		$("#labName").select2({
			placeholder: "<?php echo _translate("Select Lab"); ?>"
		});
	});

	function validateNow() {
		flag = deforayValidator.init({
			formId: 'uploadControlForm'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('uploadControlForm').submit();
		}
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
