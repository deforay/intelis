<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;

if (session_status() == PHP_SESSION_NONE) {
	session_start();
}
$title = _("Covid-19 | Sample Status Report");

require_once(APPLICATION_PATH . '/header.php');

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$sarr = $general->getSystemConfig();

if (isset($sarr['sc_user_type']) && $sarr['sc_user_type'] == 'vluser' && !empty($sarr['sc_testing_lab_id'])) {
	$testingLabs = $facilitiesService->getTestingLabs('covid19', true, false, "facility_id = " . $sarr['sc_testing_lab_id']);
} else {
	$testingLabs = $facilitiesService->getTestingLabs('covid19');
}
$testingLabsDropdown = $general->generateSelectOptions($testingLabs, null, "-- Select --");


$batQuery = "SELECT batch_code FROM batch_details WHERE test_type='covid19' AND batch_status='completed'";
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
		<h1><em class="fa-solid fa-book"></em> <?php echo _("Covid-19 Sample Status Report"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Covid-19 Sample Status"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;">
						<tr>
							<td><strong><?php echo _("Sample Collection Date"); ?>&nbsp;:</strong></td>
							<td>
								<input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="<?php echo _('Select Collection Date'); ?>" readonly style="width:220px;background:#fff;" />
							</td>
							<td>&nbsp;<strong><?php echo _("Batch Code"); ?>&nbsp;:</strong></td>
							<td>
								<select class="form-control" id="batchCode" name="batchCode" title="<?php echo _('Please select batch code'); ?>" style="width:220px;">
									<option value=""> <?php echo _("-- Select --"); ?> </option>
									<?php foreach ($batResult as $code) { ?>
										<option value="<?php echo $code['batch_code']; ?>"><?php echo $code['batch_code']; ?></option>
									<?php } ?>
								</select>
							</td>
						</tr>
						<tr>
							<td>&nbsp;<strong><?php echo _("Testing Lab"); ?> &nbsp;:</strong></td>
							<td>
								<select class="form-control" id="labName" name="labName" title="<?php echo _('Please select facility name'); ?>">
									<?= $testingLabsDropdown; ?>
								</select>
							</td>
							<td><strong><?php echo _("Select Sample Received Date At Lab"); ?> :</strong></td>
							<td>
								<input type="text" id="sampleReceivedDateAtLab" name="sampleReceivedDateAtLab" class="form-control" placeholder="<?php echo _('Select Sample Received Date At Lab'); ?>" readonly style="background:#fff;" />
							</td>
						<tr>
							<td><strong><?php echo _("Sample Tested Date"); ?> :</strong></td>
							<td>
								<input type="text" id="sampleTestedDate" name="sampleTestedDate" class="form-control" placeholder="<?php echo _('Select Tested Date'); ?>" readonly style="background:#fff;" />
							</td>
							<td></td>
							<td></td>
						</tr>
						<tr>
							<td colspan="4">&nbsp;<input type="button" onclick="searchResultData(),searchVlTATData();" value="<?php echo _("Search"); ?>" class="btn btn-success btn-sm">
								&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset"); ?></span></button>
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
						<button class="btn btn-success pull-right" type="button" onclick="covid19ExportTAT()"><em class="fa-solid fa-cloud-arrow-down"></em> <?php echo _("Export to excel"); ?></button>
						<table id="covid19RequestDataTable" class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<th><?php echo _("Covid-19 Sample ID"); ?></th>
									<th><?php echo _("Sample Collection Date"); ?></th>
									<th><?php echo _("Sample Received Date in Lab"); ?></th>
									<th><?php echo _("Sample Test Date"); ?></th>
									<th><?php echo _("Sample Print Date"); ?></th>
									<th><?php echo _("Sample Email Date"); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="6" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
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
<script src="/assets/js/highcharts.js"></script>
<script src="/assets/js/exporting.js"></script>
<script src="/assets/js/accessibility.js"></script>
<script>
	$(function() {
		$("#labName").select2({
			placeholder: "<?php echo _("Select Testing Lab"); ?>"
		});
		$('#sampleCollectionDate, #sampleReceivedDateAtLab, #sampleTestedDate').daterangepicker({
				locale: {
					cancelLabel: "<?= _("Clear"); ?>",
					format: 'DD-MMM-YYYY',
					separator: ' to ',
				},
				showDropdowns: true,
				alwaysShowCalendars: false,
				startDate: moment().subtract(28, 'days'),
				endDate: moment(),
				maxDate: moment(),
				ranges: {
					'Today': [moment(), moment()],
					'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
					'Last 7 Days': [moment().subtract(6, 'days'), moment()],
					'Last 30 Days': [moment().subtract(29, 'days'), moment()],
					'This Month': [moment().startOf('month'), moment().endOf('month')],
					'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
				}
			},
			function(start, end) {
				startDate = start.format('YYYY-MM-DD');
				endDate = end.format('YYYY-MM-DD');
			});
		$('#sampleCollectionDate, #sampleReceivedDateAtLab, #sampleTestedDate').val("");
		searchResultData();
		loadVlTATData();

	});

	function searchResultData() {
		$.blockUI();
		$.post("/covid-19/management/getSampleStatus.php", {
				sampleCollectionDate: $("#sampleCollectionDate").val(),
				sampleReceivedDateAtLab: $("#sampleReceivedDateAtLab").val(),
				sampleTestedDate: $("#sampleTestedDate").val(),
				batchCode: $("#batchCode").val(),
				labName: $("#labName").val(),
				sampleType: $("#sampleType").val()
			},
			function(data) {
				if (data != '') {
					$("#pieChartDiv").html(data);
				}
			});
		$.unblockUI();
	}

	function searchVlTATData() {
		$.blockUI();
		oTable.fnDraw();
		$.unblockUI();
	}

	function loadVlTATData() {
		$.blockUI();
		oTable = $('#covid19RequestDataTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ records per page"
			},
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			//"bStateSave" : true,
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
			],
			"aaSorting": [
				[0, "asc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/covid-19/management/getCovid19SampleTATDetails.php",
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

	function covid19ExportTAT() {
		$.blockUI();
		oTable.fnDraw();
		$.post("/covid-19/management/covid19ExportTAT.php", {
				Sample_Collection_Date: $("#sampleCollectionDate").val(),
				sampleReceivedDateAtLab: $("#sampleReceivedDateAtLab").val(),
				sampleTestedDate: $("#sampleTestedDate").val(),
				Batch_Code: $("#batchCode  option:selected").text(),
				Sample_Type: $("#sampleType  option:selected").text(),
				Facility_Name: $("#labName  option:selected").text()
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?php echo _("Unable to generate the excel file"); ?>");
				} else {
					$.unblockUI();
					window.open('/download.php?f=' + data, '_blank');
				}
			});

	}
</script>
<?php
require_once(APPLICATION_PATH . '/footer.php');
