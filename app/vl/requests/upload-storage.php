<?php

use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Services\UsersService;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

require_once APPLICATION_PATH . '/header.php';
/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());


/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$userResult = $usersService->getAllUsers();

$userInfo = [];
foreach ($userResult as $user) {
	$userInfo[$user['user_id']] = ($user['user_name']);
}

$geoLocationParentArray = $geolocationService->fetchActiveGeolocations();
if (isset($_GET['total'])) {
	$addedRecords = $_GET['total'] - $_GET['notAdded'];
}

$spreadsheet = IOFactory::load(WEB_ROOT . '/files/storages/Storage_Bulk_Upload_Excel_Format.xlsx');

$sheet = $spreadsheet->getActiveSheet()->removeRow(2,100);
$writer = IOFactory::createWriter($spreadsheet, IOFactory::READER_XLSX);
$writer->save(WEB_ROOT . '/files/storages/Storage_Bulk_Upload_Excel_Format.xlsx');

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
			<?php echo _translate("Upload Lab Storage in Bulk"); ?>
		</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em>
					<?php echo _translate("Home"); ?>
				</a></li>
			<li class="active">
				<?php echo _translate("Lab Storage"); ?>
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
				<form class="form-horizontal" method='post' name='uploadStorageForm' id='uploadStorageForm' autocomplete="off" enctype="multipart/form-data" action="upload-storage-helper.php">
					<div class="box-body">
						<div class="row">
							<div class="col-md-12">
								<?php if (isset($_GET['total']) && $_GET['total'] > 0) { ?>
									<h3 style="margin-left:100px; color:green;"><?= _translate("Total number of records in file"); ?> : <?= $_GET['total']; ?> | <?= _translate("Number of Lab Storage added"); ?> : <?= $addedRecords; ?> | <?= _translate("Number of Storages not added"); ?> : <?= $_GET['notAdded']; ?></h3>
									<?php if ($_GET['notAdded'] > 0) { ?>
										<a class="text-danger" style="text-decoration:underline;margin-left:104px; margin-bottom:10px; font-weight: bold;" href="/temporary/INCORRECT-STORAGE-ROWS.xlsx" download>Download the Excel Sheet with not uploaded storages</a><br><br>
									<?php } ?>
								<?php } ?>

								<div class="form-group">
									<label for="manifestCode" class="col-lg-2 control-label">
										<?= _translate("Manifest Code"); ?> 
									</label>
									<div class="col-lg-6">
										<input type="text" class="form-control isRequired" id="manifestCode" name="manifestCode" placeholder="<?php echo _translate('Manifest Code'); ?>" title="<?= _translate('Enter Manifest code'); ?>" onchange="getOneCode('manifest',this.value);" />
									</div>
								</div>

								<div class="form-group">
									<label for="batchCode" class="col-lg-2 control-label">
										<?= _translate("Batch Code"); ?> 
									</label>
									<div class="col-lg-6">
										<input type="text" class="form-control isRequired" id="batchCode" name="batchCode" placeholder="<?php echo _translate('Batch Code'); ?>" title="<?= _translate('Enter sample batch code'); ?>" onchange="getOneCode('batch',this.value);" />
									</div>
								</div>


								<div class="form-group">
									<label for="StorageInfo" class="col-lg-2 control-label">
										<?= _translate("Upload File"); ?> <span class="mandatory">*</span>
									</label>
									<div class="col-lg-8">
										<input type="file" class="form-control isRequired" id="storageInfo" name="storageInfo" placeholder="<?php echo _translate('Storage Name'); ?>" title="<?= _translate('Click to upload file'); ?>" />
										<a class="text-primary" style="text-decoration:underline;" href="/files/storages/Storage_Bulk_Upload_Excel_Format.xlsx" download><?= _translate("Click here to download the Excel format for uploading storages in bulk"); ?></a>
									</div>
								</div>
							</div>

						</div>


					</div>

			</div>
			<!-- /.box-body -->
			<div class="box-footer">
				<input type="hidden" name="selectedUser" id="selectedUser" />
				<a class="btn btn-primary" href="javascript:void(0);" onclick="document.getElementById('uploadStorageForm').submit();return false;">
					<?php echo _translate("Submit"); ?>
				</a>
				<a href="vl-requests.php" class="btn btn-default">
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
<script>
	function getOneCode(codeType, codeValue){
		
		if(codeValue != ""){
			if(codeType=="manifest"){
				$("#batchCode").prop("readonly",true);
				$("#manifestCode").prop("readonly",false);
			}
			else if(codeType=="batch"){
				$("#manifestCode").prop("readonly",true);
				$("#batchCode").prop("readonly",false);
			}
			
			$.post("/includes/write-samples-storageTemplate.php", {
				codeType: codeType,
				codeValue: codeValue
			},
			function(data) {
				if (data != "") {
					if ($("#batchId").val() > 0) {
						$("#search").html(data);
						var count = $('#search option').length;
						$("#unselectedCount").html(count);
					} else {
						//$("#sampleDetails").html(data);
					}
				}
			});
		}
		else{
			$("#manifestCode").prop("readonly",false);
			$("#batchCode").prop("readonly",false);
			window.location.reload();

		}
	}
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';