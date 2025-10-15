<?php


use App\Registries\AppRegistry;
use App\Services\UsersService;
use App\Registries\ContainerRegistry;


$title = "Specimen Referral Manifest";

require_once APPLICATION_PATH . '/header.php';


/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$_COOKIE = _sanitizeInput($request->getCookieParams());

?>
<style>
	.center {
		text-align: center;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-gears"></em> Specimen Referral Manifest</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Manage Specimen Referral Manifest</li>
		</ol>
	</section>
	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<div class="box-header with-border">
						<?php if (_isAllowed("/specimen-referral-manifest/move-manifest.php?t=" . $_GET['t'])) { ?>
							<a href="move-manifest.php?t=<?php echo ($_GET['t']); ?>" class="btn btn-primary pull-right" style=" margin-left: 10px; "> <em class="fa-solid fa-angles-right"></em> <?= _translate("Move Manifest"); ?></a>
						<?php }
						if (_isAllowed("/specimen-referral-manifest/add-manifest.php?t=" . $_GET['t'])) { ?>
							<a href="/specimen-referral-manifest/add-manifest.php?t=<?php echo ($_GET['t']); ?>" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _translate("Add Specimen Referral Manifest"); ?></a>
						<?php } ?>
					</div>
					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="specimenReferralManifestDataTable" class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<th><?= _translate("Manifest Code"); ?></th>
									<th><?= _translate("Test Type"); ?></th>
									<th><?= _translate("Testing Lab"); ?></th>
									<th><?= _translate("Number of Samples"); ?></th>
									<th><?= _translate("Manifest Created On"); ?></th>
									<?php if (_isAllowed("/specimen-referral-manifest/edit-manifest.php?t=" . $_GET['t']) || _isAllowed("/specimen-referral-manifest/add-manifest.php?t=" . $_GET['t'])) { ?>
										<th><?= _translate("Action"); ?></th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="6" class="dataTables_empty">Loading data from server</td>
								</tr>
							</tbody>
						</table>
					</div>
					<!-- /.box-body -->
				</div>
				<!-- /.box -->
			</div>
			<!-- /.col -->
		</div>
		<!-- /.row -->
	</section>
	<!-- /.content -->
</div>
<script>
	var oTable = null;
	$(function() {});
	$(document).ready(function() {
		$.blockUI();
		oTable = $('#specimenReferralManifestDataTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ records per page"
			},
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			"iDisplayLength": 100,
			//"bStateSave" : true,
			"bRetrieve": true,
			"aoColumns": [{
					"sClass": ""
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center",
					"bSortable": false
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				<?php if (_isAllowed("/specimen-referral-manifest/edit-manifest.php?t=" . $_GET['t']) || _isAllowed("/specimen-referral-manifest/add-manifest.php?t=" . $_GET['t'])) { ?> {
						"sClass": "center",
						"bSortable": false
					},
				<?php } ?>
			],
			"aaSorting": [
				[4, "desc"]
			],
			"fnDrawCallback": function() {
				// var checkBoxes = document.getElementsByName("chkPackage[]");
				// len = checkBoxes.length;
				// for (c = 0; c < len; c++) {
				//   if (jQuery.inArray(checkBoxes[c].id, selectedPackages) != -1) {
				//     checkBoxes[c].setAttribute("checked", true);
				//   }
				// }
			},
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/specimen-referral-manifest/get-manifests.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "module",
					"value": "<?= $_GET['t']; ?>"
				});
				$.ajax({
					"dataType": 'json',
					"type": "POST",
					"url": sSource,
					"data": aoData,
					"success": fnCallback
				});
			}
		});
		$.unblockUI();
	});

	function generateManifestPDF(pId) {
		var module = '<?= $_GET['t']; ?>';
		if (module == 'vl') {
			manifestFileName = "generateVLManifest.php";
		} else if (module == 'eid') {
			manifestFileName = "generateEIDManifest.php";
		} else if (module == 'covid19') {
			manifestFileName = "generateCovid19Manifest.php";
		} else if (module == 'hepatitis') {
			manifestFileName = "generateHepatitisManifest.php";
		} else if (module == 'tb') {
			manifestFileName = "generateTBManifest.php";
		} else if (module == 'cd4') {
			manifestFileName = "generateCD4Manifest.php";
		} else if (module == 'generic-tests') {
			manifestFileName = "generateGenericManifest.php";
		}
		//alert(manifestFileName);
		$.post(manifestFileName, {
				id: pId
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					alert('Unable to generate manifest PDF');
				} else {
					window.open('/temporary/sample-manifests/' + data, '_blank');
				}

			});
	}
</script>
<?php

require_once APPLICATION_PATH . '/footer.php';
