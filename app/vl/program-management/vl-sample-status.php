<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

$title = _translate("VL | Sample Status Report");

require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$tsQuery = "SELECT * FROM r_sample_status";
$tsResult = $db->rawQuery($tsQuery);

$sQuery = "SELECT * FROM r_vl_sample_type where status='active'";
$sResult = $db->rawQuery($sQuery);


/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$sarr = $general->getSystemConfig();

if ($general->isLISInstance() && !empty($sarr['sc_testing_lab_id'])) {
	$testingLabs = $facilitiesService->getTestingLabs('vl', true, false, "facility_id = " . $sarr['sc_testing_lab_id']);
} else {
	$testingLabs = $facilitiesService->getTestingLabs('vl');
}


$testingLabsDropdown = $general->generateSelectOptions($testingLabs, null, "-- Select --");

$batQuery = "SELECT batch_code FROM batch_details where test_type = 'vl' AND batch_status='completed'";
$batResult = $db->rawQuery($batQuery);
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
		<h1><em class="fa-solid fa-book"></em>
			<?php echo _translate("VL Sample Status Report"); ?>
		</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em>
					<?php echo _translate("Home"); ?>
				</a></li>
			<li class="active">
				<?php echo _translate("VL Sample Status Report"); ?>
			</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box" id="filterDiv">
					<table aria-describedby="table" class="table pageFilters" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;">
						<tr>
							<th style="width:15%;"><strong>
									<?php echo _translate("Sample Collection Date"); ?>&nbsp;:
								</strong></th>
							<td style="width:35%;">
								<input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control daterangefield" placeholder="<?php echo _translate('Select Collection Date'); ?>" />
							</td>
							<th style="width:15%;"><strong>
									<?php echo _translate("Batch Code"); ?>&nbsp;:
								</strong></th>
							<td style="width:35%;">
								<select class="form-control" id="batchCode" name="batchCode" title="<?php echo _translate('Please select batch code'); ?>">
									<option value="">
										<?php echo _translate("-- Select --"); ?>
									</option>
									<?php foreach ($batResult as $code) { ?>
										<option value="<?php echo $code['batch_code']; ?>"><?php echo $code['batch_code']; ?></option>
									<?php } ?>
								</select>
							</td>
						</tr>
						<tr>
							<th>&nbsp;<strong>
									<?php echo _translate("Sample Type"); ?>&nbsp;:
								</strong></th>
							<td>
								<select class="form-control" id="sampleType" name="sampleType" title="<?php echo _translate('Please select sample type'); ?>">
									<option value="">
										<?php echo _translate("-- Select --"); ?>
									</option>
									<?php foreach ($sResult as $type) { ?>
										<option value="<?php echo $type['sample_id']; ?>"><?= $type['sample_name']; ?>
										</option>
									<?php } ?>
								</select>
							</td>
							<th>&nbsp;<strong>
									<?php echo _translate("Testing Lab"); ?> &nbsp;:
								</strong></th>
							<td>
								<select class="form-control" id="labName" name="labName" title="<?php echo _translate('Please select facility name'); ?>">
									<?= $testingLabsDropdown; ?>
								</select>
							</td>
						</tr>
						<tr>
							<td><strong>
									<?php echo _translate("Select Sample Received Date At Lab"); ?> :
								</strong></td>
							<td>
								<input type="text" id="sampleReceivedDateAtLab" name="sampleReceivedDateAtLab" class="form-control" placeholder="<?php echo _translate('Select Sample Received Date At Lab'); ?>" readonly style="background:#fff;" />
							</td>
							<td><strong>
									<?php echo _translate("Sample Tested Date"); ?> :
								</strong></td>
							<td>
								<input type="text" id="sampleTestedDate" name="sampleTestedDate" class="form-control" placeholder="<?php echo _translate('Select Tested Date'); ?>" readonly style="background:#fff;" />
							</td>
						</tr>
						<tr>
							<td colspan="4">
								&nbsp;<input type="button" id="searchBtn" onclick="searchResultData(),reloadTATData();" value="<?= _translate('Search'); ?>" class="btn btn-success btn-sm">
								&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?= _translate('Reset'); ?></span></button>
							</td>
						</tr>

					</table>
				</div>
			</div>

			<!-- /.box-header -->
			<div id="pieChartDiv">

			</div>
			<div class="col-xs-12">
				<div class="box">
					<div class="box-body">
						<button class="btn btn-success pull-right" type="button" onclick="exportInexcel()"><em class="fa-solid fa-cloud-arrow-down"></em> Export to excel</button>
						<table aria-describedby="table" id="vlRequestDataTable" class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<th>
										<?php echo _translate("Sample ID"); ?>
									</th>
									<th>
										<?php echo _translate("Remote Sample ID"); ?>
									</th>
									<th>
										<?php echo _translate("External Sample ID"); ?>
									</th>
									<th scope="row">
										<?php echo _translate("Sample Collection Date"); ?>
									</th>
									<th>
										<?php echo _translate("Sample Dispatch Date"); ?>
									</th>
									<th>
										<?php echo _translate("Sample Received Date in Lab"); ?>
									</th>
									<th scope="row">
										<?php echo _translate("Sample Test Date"); ?>
									</th>
									<th>
										<?php echo _translate("Result Print Date"); ?>
									</th>
									<th>
										<?php echo _translate("STS Result Print Date"); ?>
									</th>
									<th>
										<?php echo _translate("LIS Result Print Date"); ?>
									</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="10" class="dataTables_empty">
										<?php echo _translate("Loading data from server"); ?>
									</td>
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
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>

<script>
	let searchExecuted = false;
	let currentRequest = null;
	let isTableLoading = false;
	let currentTableRequest = null; // Track the DataTable AJAX request

	$(function() {
		$("#labName").select2({
			placeholder: "<?php echo _translate("Select Testing Lab"); ?>"
		});

		$("#batchCode").select2({
			placeholder: "<?php echo _translate("Select Batch Code"); ?>"
		});

		$('#sampleCollectionDate, #sampleReceivedDateAtLab, #sampleTestedDate').daterangepicker({
			locale: {
				cancelLabel: "<?= _translate("Clear", true); ?>",
				format: 'DD-MMM-YYYY',
				separator: ' to ',
			},
			startDate: moment().subtract(179, 'days'),
			endDate: moment(),
			maxDate: moment(),
			ranges: {
				'Today': [moment(), moment()],
				'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
				'Last 7 Days': [moment().subtract(6, 'days'), moment()],
				'This Month': [moment().startOf('month'), moment().endOf('month')],
				'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
				'Last 30 Days': [moment().subtract(29, 'days'), moment()],
				'Last 90 Days': [moment().subtract(89, 'days'), moment()],
				'Last 120 Days': [moment().subtract(119, 'days'), moment()],
				'Last 180 Days': [moment().subtract(179, 'days'), moment()],
				'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')],
				'Previous Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
				'Current Year To Date': [moment().startOf('year'), moment()]
			}
		}, function(start, end) {
			startDate = start.format('YYYY-MM-DD');
			endDate = end.format('YYYY-MM-DD');
		});

		loadTATData();
		$('#sampleReceivedDateAtLab, #sampleTestedDate').val("");
		searchResultData();

		$("#filterDiv input, #filterDiv select").on("change", function() {
			searchExecuted = false;
		});
	});

	function searchResultData() {
		// Cancel any existing request
		if (currentRequest) {
			currentRequest.abort();
		}

		searchExecuted = true;

		var $searchBtn = $('#searchBtn');
		$searchBtn.prop('disabled', true)
			.val('Searching...');

		$("#pieChartDiv").html(
			'<div class="col-xs-12">' +
			'<div class="box">' +
			'<div class="box-body text-center" style="padding: 50px;">' +
			'<i class="fa fa-spinner fa-spin fa-3x text-primary"></i>' +
			'<p class="text-muted" style="margin-top: 15px;">Loading chart data, please wait...</p>' +
			'<button class="btn btn-warning btn-sm" style="margin-top: 10px;" onclick="cancelSearch()">' +
			'<i class="fa fa-times"></i> Cancel</button>' +
			'</div>' +
			'</div>' +
			'</div>'
		);

		currentRequest = $.post("/vl/program-management/getSampleStatus.php", {
				sampleCollectionDate: $("#sampleCollectionDate").val(),
				sampleReceivedDateAtLab: $("#sampleReceivedDateAtLab").val(),
				sampleTestedDate: $("#sampleTestedDate").val(),
				batchCode: $("#batchCode").val(),
				labName: $("#labName").val(),
				sampleType: $("#sampleType").val()
			})
			.done(function(data) {
				if (data != '') {
					$("#pieChartDiv").html(data);
				} else {
					$("#pieChartDiv").html(
						'<div class="col-xs-12">' +
						'<div class="box">' +
						'<div class="box-body">' +
						'<div class="alert alert-info">' +
						'<i class="fa fa-info-circle"></i> No data found for the selected filters.' +
						'</div>' +
						'</div>' +
						'</div>' +
						'</div>'
					);
				}
			})
			.fail(function(xhr, status, error) {
				if (status !== 'abort') {
					$("#pieChartDiv").html(
						'<div class="col-xs-12">' +
						'<div class="box">' +
						'<div class="box-body">' +
						'<div class="alert alert-danger">' +
						'<i class="fa fa-exclamation-triangle"></i> Error loading data. Please try again.' +
						'</div>' +
						'</div>' +
						'</div>' +
						'</div>'
					);
				}
			})
			.always(function() {
				// Only re-enable button if table is also done loading
				if (!isTableLoading) {
					$searchBtn.prop('disabled', false)
						.val('<?= _translate("Search"); ?>');
				}
				currentRequest = null;
			});
	}

	function cancelSearch() {
		// Cancel the pie chart request
		if (currentRequest) {
			currentRequest.abort();
			currentRequest = null;
		}

		// Cancel the DataTable AJAX request
		if (currentTableRequest) {
			currentTableRequest.abort();
			currentTableRequest = null;
		}

		// Reset loading flag
		isTableLoading = false;

		// Show cancelled message
		$("#pieChartDiv").html(
			'<div class="col-xs-12">' +
			'<div class="box">' +
			'<div class="box-body text-center text-muted" style="padding: 30px;">' +
			'<i class="fa fa-ban"></i> Search cancelled.' +
			'</div>' +
			'</div>' +
			'</div>'
		);

		// Re-enable the search button
		$('#searchBtn').prop('disabled', false)
			.val('<?= _translate("Search"); ?>');
	}

	function reloadTATData() {
		isTableLoading = true;
		oTable.fnDraw();
	}

	function loadTATData() {
		oTable = $('#vlRequestDataTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ <?= _translate("records per page", true); ?>",
				"sProcessing": '<div style="padding: 20px;"><i class="fa fa-spinner fa-spin fa-2x text-primary"></i><br/><br/>Loading data...</div>'
			},
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			"iDisplayLength": 10,
			"bRetrieve": true,
			"aoColumns": [{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				}
			],
			"aaSorting": [
				[3, "desc"],
				[0, "asc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/vl/program-management/getVlSampleTATDetails.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "batchCode",
					"value": $("#batchCode").val()
				});
				aoData.push({
					"name": "sampleCollectionDate",
					"value": $("#sampleCollectionDate").val()
				});
				aoData.push({
					"name": "labName",
					"value": $("#labName").val()
				});
				aoData.push({
					"name": "sampleType",
					"value": $("#sampleType").val()
				});
				aoData.push({
					"name": "sampleReceivedDateAtLab",
					"value": $("#sampleReceivedDateAtLab").val()
				});
				aoData.push({
					"name": "sampleTestedDate",
					"value": $("#sampleTestedDate").val()
				});

				// Store the current request so it can be cancelled
				isTableLoading = true;
				currentTableRequest = $.ajax({
					"dataType": 'json',
					"type": "POST",
					"url": sSource,
					"data": aoData,
					"success": function(json) {
						fnCallback(json);
						isTableLoading = false;
						currentTableRequest = null;

						// Re-enable button if chart is also done
						if (!currentRequest) {
							$('#searchBtn').prop('disabled', false)
								.val('<?= _translate("Search"); ?>');
						}
					},
					"error": function(xhr, error, thrown) {
						// Don't show error if request was aborted (cancelled)
						if (error !== 'abort') {
							console.error('DataTable error:', error);
							$('#vlRequestDataTable tbody').html(
								'<tr><td colspan="10" class="text-center text-danger">' +
								'<i class="fa fa-exclamation-triangle"></i> Error loading data. Please try again.' +
								'</td></tr>'
							);
						}

						isTableLoading = false;
						currentTableRequest = null;

						// Re-enable button
						if (!currentRequest) {
							$('#searchBtn').prop('disabled', false)
								.val('<?= _translate("Search"); ?>');
						}
					}
				});
			}
		});
	}

	function exportInexcel() {
		if (searchExecuted === false) {
			searchResultData();
		}
		$.blockUI();
		oTable.fnDraw();
		$.post("/vl/program-management/vlSampleTATDetailsExportInExcel.php", {
				Sample_Collection_Date: $("#sampleCollectionDate").val(),
				sampleReceivedDateAtLab: $("#sampleReceivedDateAtLab").val(),
				sampleTestedDate: $("#sampleTestedDate").val(),
				Batch_Code: $("#batchCode  option:selected").text(),
				Sample_Type: $("#sampleType  option:selected").text(),
				Lab_Name: $("#labName option:selected").text(),
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?php echo _translate("Unable to generate excel"); ?>");
				} else {
					$.unblockUI();
					window.open('/download.php?f=' + data, '_blank');
				}
			});
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
