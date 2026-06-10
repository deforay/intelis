<?php

/**
 * Shared "Add Samples from Manifest" page.
 *
 * One body for every test module (vl, eid, tb, covid-19, cd4, generic-tests,
 * hepatitis). The thin per-module page under /<module>/requests/ only builds a
 * $manifestPage config array and requires this file; everything below -- header,
 * the manifest-code lookup row, the server-side results grid, the activate
 * controls and the page scripts -- is identical across modules.
 *
 * The flow itself lives elsewhere and is shared already:
 *   verifyManifest()/syncManifestFromSTS()  -> public/assets/js/main.js.php
 *   verify-manifest.php                      -> this folder
 *   getManifestInGridHelper.php              -> per module (grid data source)
 *   activate-samples-from-manifest.php       -> this folder (one shared handler;
 *                                               the test type arrives via POST)
 *
 * $manifestPage keys:
 *   module        string  folder slug under /app, e.g. 'vl', 'covid-19'. Drives
 *                         the default grid/activate URLs.
 *   testType      string  value POSTed to verify/activate, e.g. 'vl', 'covid19'.
 *                         NOTE this is not always the same as `module`.
 *   title         string  already-translated page + breadcrumb title.
 *   breadcrumb    string  already-translated active breadcrumb label.
 *   columns       array   already-translated header labels for the grid columns
 *                         AFTER "Sample ID" and the conditional "Remote Sample
 *                         ID". Order must match the module's getManifestInGridHelper
 *                         $row[] push order. Kept per module on purpose -- the
 *                         displayed columns genuinely differ between test types.
 *   gridHelperUrl string  optional override for the grid data source. Defaults
 *                         to /<module>/requests/getManifestInGridHelper.php
 *                         (hepatitis uses a hyphenated helper name and overrides).
 */

use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var array $manifestPage */
$manifestPage ??= [];

$module        = $manifestPage['module'] ?? '';
$testType      = $manifestPage['testType'] ?? $module;
$title         = $manifestPage['title'] ?? _translate("Add Samples from Manifest");
$breadcrumb    = $manifestPage['breadcrumb'] ?? _translate("Test Request");
$columns       = $manifestPage['columns'] ?? [];
$gridHelperUrl = $manifestPage['gridHelperUrl'] ?? "/$module/requests/getManifestInGridHelper.php";
// One shared activate endpoint for every module; testType is POSTed by the JS below.
$activateUrl   = $manifestPage['activateUrl'] ?? "/specimen-referral-manifest/activate-samples-from-manifest.php";

require_once APPLICATION_PATH . '/header.php';

/** @var CommonService $general */
$general = $general ?? ContainerRegistry::get(CommonService::class);

$showRemoteColumn = !$general->isStandaloneInstance();
// Sample ID + (conditional) Remote Sample ID + the per-module columns.
$columnCount = 1 + ($showRemoteColumn ? 1 : 0) + count($columns);
?>
<style>
	.select2-selection__choice {
		color: black !important;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-plus"></em> <?php echo htmlspecialchars($title); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo htmlspecialchars($breadcrumb); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<table aria-describedby="table" class="table" aria-hidden="true"
						style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;display: block;">
						<tr>
							<td style="width:20%;vertical-align:middle;"><strong>
									<?php echo _translate("Enter Sample Manifest Code"); ?> :
								</strong></td>
							<td style="width:70%;vertical-align:middle;">
								<input type="text" id="manifestCode" name="manifestCode" class="form-control"
									placeholder="<?php echo _translate('Sample Manifest Code'); ?>"
									title="<?php echo _translate('Please enter the sample manifest code'); ?>"
									style="background:#fff;" />
								<input type="hidden" id="sampleId" name="sampleId" />
							</td>
							<td style="width:10%;">
								<button class="btn btn-primary btn-sm pull-right" style="margin-right:5px;"
									onclick="verifyManifest('<?php echo htmlspecialchars($testType); ?>');return false;">
									<span><?php echo _translate("Submit"); ?></span>
								</button>
							</td>
						</tr>
						<tr class="activateSample" style="display:none;">
							<th scope="row" style="width:20%;vertical-align:middle;">
								<?php echo _translate("Sample Received at Testing Lab"); ?> :
							</th>
							<td style="width:70%;vertical-align:middle;"><input type="text" name="sampleReceivedOn"
									id="sampleReceivedOn" class="form-control dateTime"
									placeholder="Sample Received at Testing Lab"
									title="Please select when the samples were received at the Testing Lab" readonly />
							</td>
							<td style="width:10%;">
								<a class="btn btn-success btn-sm pull-right activateSample" style="display:none;margin-right:5px;"
									href="javascript:void(0);" onclick="activateSamplesFromManifest();"><em
										class="fa-solid fa-check"></em>&nbsp;<?= _translate("Activate Samples"); ?></a>
							</td>
						</tr>
					</table>

					<div class="container-fluid">
						<span class="pull-right sts-server-reachable">
							<span class="fa-solid fa-circle is-remote-server-reachable"
								style="font-size:1em;display:none;"></span>
							<span class="sts-server-reachable-span"></span>
						</span>
					</div>
					<!-- /.box-header -->
					<div class="box-body table-responsive">
						<table aria-describedby="table" id="manifestDataTable"
							class="table table-bordered table-striped table-vcenter">
							<thead>
								<tr>
									<th><?php echo _translate("Sample ID"); ?></th>
									<?php if ($showRemoteColumn) { ?>
										<th><?php echo _translate("Remote Sample ID"); ?></th>
									<?php } ?>
									<?php foreach ($columns as $columnLabel) { ?>
										<th><?php echo _translate($columnLabel); ?></th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="<?php echo $columnCount; ?>" class="dataTables_empty" style="text-align:center;">
										<?php echo _translate("Please enter a valid Manifest Code to activate", true); ?>
									</td>
								</tr>
							</tbody>
						</table>
					</div>

				</div>
				<!-- /.box -->

			</div>
			<!-- /.col -->
		</div>
		<!-- /.row -->
	</section>
	<!-- /.content -->
</div>
<script type="text/javascript" src="/assets/plugins/daterangepicker/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>

<?= CommonService::barcodeScripts(); ?>

<script type="text/javascript">
	var oTable = null;

	function loadRequestData() {
		$.blockUI();
		if (oTable) {
			$("#manifestDataTable").dataTable().fnDestroy();
		}

		oTable = $('#manifestDataTable').dataTable({
			"bJQueryUI": false,
			"iDisplayLength": 200,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			"bDestroy": true,
			"bStateSave": false,
			"bRetrieve": false,
			"aoColumns": [
				<?php for ($i = 0; $i < $columnCount; $i++) { ?>
					<?php echo $i ? ',' : ''; ?>{ "sClass": "center" }
				<?php } ?>
			],
			"aaSorting": [
				[1, "asc"]
			],
			"fnDrawCallback": function () { },
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "<?php echo htmlspecialchars($gridHelperUrl); ?>",
			"fnServerData": function (sSource, aoData, fnCallback) {
				aoData.push({
					"name": "manifestCode",
					"value": $("#manifestCode").val()
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
	}

	function activateSamplesFromManifest() {
		if ($("#sampleReceivedOn").val() == "") {
			alert("<?= _translate("Please select when the samples were received at the Testing Lab", true); ?>");
			return false;
		}
		$.blockUI();
		$.post("<?php echo htmlspecialchars($activateUrl); ?>", {
			testType: '<?php echo htmlspecialchars($testType); ?>',
			manifestCode: $("#manifestCode").val(),
			sampleId: $("#sampleId").val(),
			sampleReceivedOn: $("#sampleReceivedOn").val()
		},
			function (data) {
				if (data > 0) {
					alert("<?php echo _translate("Samples from this Manifest have been activated", true); ?>");
				}
				$('.activateSample').hide();
				oTable.fnDraw();
				$.unblockUI();
			});
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
