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
/** @var Psr\Http\Message\ServerRequestInterface $request */
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

$filePath = '/files/storage/storage-bulk-upload.xlsx';
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
				<div class="box-body">
					<div class="row">
						<!-- Left side -->
						<div class="col-md-6 border-right">
							<div class="form-group">
								<label for="batchOrManifestCode" class="col-lg-4">
									<?= _translate("Batch Code (or) Manifest Code"); ?>
								</label>
								<div class="col-lg-7">
									<input type="text" class="form-control isRequired" id="batchOrManifestCode"
										name="batchOrManifestCode"
										placeholder="<?php echo _translate('Batch or Manifest Code'); ?>"
										title="<?= _translate('Enter Batch or Manifest code'); ?>" />
								</div>
							</div>
							<div class="form-group">
								<div class="col-lg-offset-4 col-lg-5" style="float:left;">
									<button class="btn btn-primary" onclick="getExcelFormatTemplate();"
										title="<?= _translate("Click here to download the Excel format for uploading storages in bulk"); ?>"><?= _translate("Download Excel Format"); ?></button>
								</div>
							</div>
						</div>
						<!-- Right side -->
						<div class="col-md-6">
							<!-- form start -->
							<form class="form-horizontal" method='post' name='uploadStorageForm' id='uploadStorageForm'
								autocomplete="off" enctype="multipart/form-data" action="upload-storage-helper.php">
								<div class="form-group">
									<label for="StorageInfo" class="col-lg-4">
										<?= _translate("Upload File"); ?> <span class="mandatory">*</span>
									</label>
									<div class="col-lg-7">
										<input type="file" class="form-control isRequired" id="storageInfo"
											name="storageInfo" placeholder="<?php echo _translate('Storage Name'); ?>"
											title="<?= _translate('Click to upload file'); ?>" />
									</div>
								</div>
								<div class="form-group">
									<input type="hidden" name="selectedUser" id="selectedUser" />
									<div class="col-lg-7">
										<a class="btn btn-primary" href="javascript:void(0);"
											onclick="validateNow();return false;">
											<?php echo _translate("Submit"); ?>
										</a>
										<a href="/vl/requests/vl-requests.php" class="btn btn-default">
											<?php echo _translate("Cancel"); ?>
										</a>
									</div>
								</div>
							</form>
						</div>
					</div>
				</div>
				<!-- /.box-body -->
			</div>
			<div class="box-body">
				<?php if (isset($_GET['total']) && $_GET['total'] > 0) { ?>
					<h3 style="color:green;"><?= _translate("Total number of records in file"); ?> : <?= $_GET['total']; ?>
						| <?= _translate("Number of Lab Storage added"); ?> : <?= $addedRecords; ?> |
						<?= _translate("Number of Storages not added"); ?> : <?= $_GET['notAdded']; ?></h3>
					<?php if ($_GET['notAdded'] > 0) { ?>
						<a class="text-danger" style="text-decoration:underline;margin-bottom:10px; font-weight: bold;"
							href="/temporary/INCORRECT-STORAGE-ROWS.xlsx" download>Download the Excel Sheet with not uploaded
							storages</a><br><br>
					<?php }
				}
				if (isset($_GET['failedRowCount']) && ($_GET['failedRowCount']) > 0) { ?>
					<h2><?php echo _translate('Unable to Upload following Samples'); ?></h2>
					<table aria-describedby="table" id="failedSamples" class="table table-bordered table-striped"
						aria-hidden="true">
						<thead>
							<tr>
								<th><?php echo _translate("Sample ID"); ?></th>
								<th><?php echo _translate("Freezer Code"); ?></th>
								<th><?php echo _translate("Rack"); ?></th>
								<th><?php echo _translate("Box"); ?></th>
								<th><?php echo _translate("Position"); ?></th>
								<th><?php echo _translate("Volume(ml)"); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php for ($i = 0; $i < $_GET['failedRowCount']; $i++) {
								echo '<tr>';
								foreach ($_GET[$i] as $sample) { ?>
									<td><?php echo $sample; ?></td>
								<?php }
								echo '</tr>';
							} ?>
						</tbody>
					</table>
				<?php } ?>
			</div>
			<!-- /.row -->
		</div>
		<!-- /.box -->
	</section>
	<!-- /.content -->
</div>

<script>
	function validateNow() {
		flag = deforayValidator.init({
			formId: 'uploadStorageForm'
		});
		if (flag) {
			$.blockUI();
			document.getElementById('uploadStorageForm').submit();
		}
	}

	function getExcelFormatTemplate() {
		var batchOrManifestCodeValue = $("#batchOrManifestCode").val();
		if (batchOrManifestCodeValue != "") {
			$.post("/includes/write-samples-storage-template.php", {
				batchOrManifestCodeValue: batchOrManifestCodeValue
			},
				function (data) {
					if (data !== '' && data !== false) {
						window.location.href = data;
					} else {
						window.location.href = '<?php echo $filePath; ?>';
					}
				});
		} else {
			window.location.href = '<?php echo $filePath; ?>';
		}
	}
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
