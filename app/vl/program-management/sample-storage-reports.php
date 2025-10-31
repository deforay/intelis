<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\StorageService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

$title = _translate("Freezer/Storage Reports");

require_once APPLICATION_PATH . '/header.php';


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var StorageService $storageService */
$storageService = ContainerRegistry::get(StorageService::class);
/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$labId = null;
if (isset($_GET['facilityId']) && $_GET['facilityId'] != "" && isset($_GET['labId']) && $_GET['labId'] != "") {
	$facilityId = base64_decode((string) $_GET['facilityId']);
	$labId = base64_decode((string) $_GET['labId']);
}

$storageInfo = $storageService->getLabStorage();
$testingLabs = $facilitiesService->getTestingLabs('vl');

$testingLabsDropdown = $general->generateSelectOptions($testingLabs, $labId, "-- Select --");

?>
<style>
	.select2-selection__choice {
		color: #000000 !important;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-jar"></em>
			<?php echo _translate("Freezer/Storage Reports"); ?>
		</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em>
					<?php echo _translate("Home"); ?>
				</a></li>
			<li class="active">
				<?php echo _translate("Freezer/Storage Reports"); ?>
			</li>
		</ol>
	</section>
	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<!-- /.box-header -->
					<div class="box-body">
						<div class="widget">
							<div class="widget-content">
								<div class="bs bs-tabs">
									<ul id="myTab" class="nav nav-tabs" style="font-size:1.4em;">
										<li class="active"><a href="#notPrintedData" data-toggle="tab">
												<?php echo _translate("Freezer/Storage Report"); ?>
											</a></li>
										<li><a href="#printedData" data-toggle="tab" class="printedData">
												<?php echo _translate("Sample Storage History"); ?>
											</a></li>
									</ul>
									<div id="myTabContent" class="tab-content">
										<div class="tab-pane fade in active" id="notPrintedData">
											<table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;">
												<tr>
													<?php if ($general->isSTSInstance()) { ?>

														<td><strong>
																<?php echo _translate("Testing Lab"); ?> :
															</strong></td>
														<td>
															<select class="form-control" id="vlLab" name="vlLab" title="<?php echo _translate('Please select Testing Lab'); ?>" style="width:220px;" onchange="getFreezers(this.value);">
																<?= $testingLabsDropdown; ?>
															</select>
														</td>
													<?php } ?>
													<td><strong>
															<?php echo _translate("Freezer/Storage"); ?>&nbsp;:
														</strong></td>
													<td>
														<select type="text" name="freezerId" id="freezerId" class="form-control freezerSelect" style="width:250px;">
															<?= $general->generateSelectOptions($storageInfo, null, '-- Select --') ?>
														</select>
													</td>
												</tr>
												<tr>
													<td colspan="6">&nbsp;<input type="button" onclick="searchVlRequestData();" value="<?= _translate('Search'); ?>" class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>
																<?= _translate('Reset'); ?>
															</span></button>
														&nbsp;<button class="btn btn-primary btn-sm" type="button" onclick="exportStorageData('storage');">
															<span><?php echo _translate("Export to excel"); ?></span></button>

													</td>
												</tr>
											</table>
											<span style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;" id="showhide" class="">
												<div class="row" style="background:#e0e0e0;float: right !important;padding: 15px;margin-top: -30px;">
													<div class="col-md-12">
														<div class="col-md-3">
															<input type="checkbox" onclick="fnShowHide(this.value);" value="1" id="iCol1" data-showhide="sample_code" class="showhideCheckBox" /> <label for="iCol1">
																<?php echo _translate("Sample ID"); ?>
															</label>
														</div>

													</div>
												</div>
											</span>

											<table aria-describedby="table" id="storageDataTable" class="table table-bordered table-striped" aria-hidden="true">
												<thead>
													<tr>
														<th>
															<?php echo _translate("Sample ID"); ?>
														</th>
														<th>
															<?php echo _translate("Storage Date"); ?>
														</th>
														<th scope="row">
															<?php echo _translate("Volume of Sample (ml)"); ?>
														</th>
														<th>
															<?php echo _translate("Rack"); ?>
														</th>
														<th>
															<?php echo _translate("Box"); ?>
														</th>
														<th>
															<?php echo _translate("Position"); ?>
														</th>
														<th>
															<?php echo _translate("Status"); ?>
														</th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="12" class="dataTables_empty">
															<?php echo _translate("Loading data from server"); ?>
														</td>
													</tr>
												</tbody>
											</table>
											<input type="hidden" name="checkedRows" id="checkedRows" />
											<input type="hidden" name="totalSamplesList" id="totalSamplesList" />
										</div>
										<div class="tab-pane fade" id="printedData">
											<table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;">
												<tr>
													<?php if ($general->isSTSInstance()) { ?>
														<td><strong>
																<?php echo _translate("Testing Lab"); ?> :
															</strong></td>
														<td>
															<select class="form-control" id="testingLab" name="testingLab" title="<?php echo _translate('Please select Testing Lab'); ?>" style="width:220px;">
																<?= $testingLabsDropdown; ?>
															</select>
														</td>
													<?php } ?>
													<td>
														<strong>
															<?php echo _translate("Sample ID"); ?>&nbsp;:
														</strong>
													</td>
													<td>
														<input type="text" id="sampleCode" name="sampleCode" class="form-control" placeholder="Please select sample code" style="width:220px;" />

													</td>
												</tr>

												<tr>
													<td colspan="6">&nbsp;<input type="button" onclick="searchPrintedVlRequestData();" value="<?= _translate('Search'); ?>" class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>
																<?= _translate('Reset'); ?>
															</span></button>
														&nbsp;<button class="btn btn-primary btn-sm" type="button" onclick="exportStorageData('history');">
															<span><?php echo _translate("Export to excel"); ?></span></button>

													</td>
												</tr>

											</table>
											<span style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;" id="printShowhide" class="">
												<div class="row" style="background:#e0e0e0;float: right !important;padding: 15px;margin-top: -30px;">
													<div class="col-md-12">
														<div class="col-md-3">
															<input type="checkbox" onclick="printfnShowHide(this.value);" value="1" id="printiCol1" data-showhide="sample_code" class="printShowhideCheckBox" /> <label for="printiCol1">
																<?php echo _translate("Sample ID"); ?>
															</label>
														</div>
													</div>
												</div>
											</span>
											<table aria-describedby="table" id="storageHistoryDataTable" class="table table-bordered table-striped" aria-hidden="true">
												<thead>
													<tr>
														<th>
															<?php echo _translate("Patient ID"); ?>
														</th>
														<th>
															<?php echo _translate("Sample Collection Date"); ?>
														</th>
														<th>
															<?php echo _translate("Freezer/Storage Code"); ?>
														</th>
														<th scope="row">
															<?php echo _translate("Volume of Sample (ml)"); ?>
														</th>
														<th>
															<?php echo _translate("Rack"); ?>
														</th>
														<th>
															<?php echo _translate("Box"); ?>
														</th>
														<th>
															<?php echo _translate("Position"); ?>
														</th>
														<th>
															<?php echo _translate("Date Out"); ?>
														</th>
														<th>
															<?php echo _translate("Comments"); ?>
														</th>
														<th>
															<?php echo _translate("Status"); ?>
														</th>
														<th>
															<?php echo _translate("Removal Reason"); ?>
														</th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="12" class="dataTables_empty">
															<?php echo _translate("Loading data from server"); ?>
														</td>
													</tr>
												</tbody>
											</table>
											<input type="hidden" name="checkedPrintedRows" id="checkedPrintedRows" />
											<input type="hidden" name="totalSamplesPrintedList" id="totalSamplesPrintedList" />
										</div>
									</div>
								</div>
							</div>
						</div><!-- /.box-body -->
						<!-- /.box -->
					</div>
					<!-- /.col -->
				</div>
				<!-- /.row -->
	</section>
	<!-- /.content -->
</div>

<script type="text/javascript">
	var startDate = "";
	var endDate = "";
	var selectedRows = [];
	var selectedRowsId = [];
	var selectedPrintedRows = [];
	var selectedPrintedRowsId = [];
	var oTable = null;
	var opTable = null;

	$(document).ready(function() {

		$(".freezerSelect").select2({
			placeholder: "<?php echo _translate("Select Freezer"); ?>"
		});

		$("#vlLab, #testingLab").select2({
			placeholder: "<?php echo _translate("Select Testing Lab"); ?>"
		});



		loadStorageData();
		$(".printedData").click(function() {
			loadStorageHistoryData();

		});
	});

	function loadStorageData() {
		$.blockUI();
		oTable = $('#storageDataTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ <?= _translate("records per page", true); ?>"
			},
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			//"bStateSave" : true,
			"iDisplayLength": 100,
			"bRetrieve": true,
			"aoColumns": [{
					"sClass": "center",
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
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/vl/program-management/getStorageReportDetails.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "reportType",
					"value": 'storageData'
				});

				aoData.push({
					"name": "freezerId",
					"value": $("#freezerId").val()
				});
				$.ajax({
					"dataType": 'json',
					"type": "POST",
					"url": sSource,
					"data": aoData,
					"success": function(json) {
						$("#totalSamplesList").val(json.iTotalDisplayRecords);
						fnCallback(json);
					}
				});
			}
		});
		$.unblockUI();
	}

	function loadStorageHistoryData() {
		$.blockUI();
		opTable = $('#storageHistoryDataTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ <?= _translate("records per page", true); ?>"
			},
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			//"bStateSave" : true,
			"iDisplayLength": 100,
			"bRetrieve": true,
			"aoColumns": [{
					"sClass": "center",
					"bSortable": false
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
				},
				{
					"sClass": "center"
				},
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/vl/program-management/getStorageReportDetails.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "reportType",
					"value": 'historyData'
				});
				aoData.push({
					"name": "sampleCode",
					"value": $("#sampleCode").val()
				});
				aoData.push({
					"name": "labId",
					"value": $("#testingLab").val()
				});

				$.ajax({
					"dataType": 'json',
					"type": "POST",
					"url": sSource,
					"data": aoData,
					"success": function(json) {
						$("#totalSamplesPrintedList").val(json.iTotalDisplayRecords);
						fnCallback(json);
					}
				});
			}
		});
		$.unblockUI();
	}

	function searchVlRequestData() {
		$.blockUI();
		oTable.fnDraw();
		$.unblockUI();
	}

	function searchPrintedVlRequestData() {
		$.blockUI();
		opTable.fnDraw();
		$.unblockUI();
	}


	function getByProvince(provinceId) {
		$("#district").html('');
		$("#facility").html('');
		$("#labId").html('');
		$.post("/common/get-by-province-id.php", {
				provinceId: provinceId,
				districts: true,
				facilities: true,
				labs: true,
			},
			function(data) {
				Obj = $.parseJSON(data);
				$("#district").html(Obj['districts']);
				$("#facility").html(Obj['facilities']);
				$("#labId").html(Obj['labs']);
			});

	}

	function getByPrintProvince(provinceId) {
		$("#printDistrict").html('');
		$("#printFacility").html('');
		$("#printLabId").html('');
		$.post("/common/get-by-province-id.php", {
				provinceId: provinceId,
				districts: true,
				facilities: true,
				labs: true,
			},
			function(data) {
				Obj = $.parseJSON(data);
				$("#printDistrict").html(Obj['districts']);
				$("#printFacility").html(Obj['facilities']);
				$("#printLabId").html(Obj['labs']);
			});

	}

	function getByDistrict(districtId) {
		$("#facility").html('');
		$("#labId").html('');
		$.post("/common/get-by-district-id.php", {
				districtId: districtId,
				facilities: true,
				labs: true,
			},
			function(data) {
				Obj = $.parseJSON(data);
				$("#facility").html(Obj['facilities']);
				$("#labId").html(Obj['labs']);
			});

	}

	function getByPrintDistrict(districtId) {
		$("#printFacility").html('');
		$("#printLabId").html('');
		$.post("/common/get-by-district-id.php", {
				districtId: districtId,
				facilities: true,
				labs: true,
			},
			function(data) {
				Obj = $.parseJSON(data);
				$("#printFacility").html(Obj['facilities']);
				$("#printLabId").html(Obj['labs']);
			});

	}

	function exportStorageData($data) {

		$.blockUI();
		if ($data == "storage") {
			$.post("/vl/program-management/storageDataToExcel.php", {
					reqSampleType: $('#requestSampleType').val(),
					patientInfo: $('#patientInfo').val(),
				},
				function(data) {
					$.unblockUI();
					if (data === "" || data === null || data === undefined) {
						alert("<?php echo _translate("Unable to generate the excel file"); ?>");
					} else {
						window.open('/download.php?d=a&f=' + data, '_blank');
					}
				});
		} else {
			$.post("/vl/program-management/storageHistoryDataToExcel.php", {
					reqSampleType: $('#requestSampleType').val(),
					patientInfo: $('#patientInfo').val(),
				},
				function(data) {
					$.unblockUI();
					if (data === "" || data === null || data === undefined) {
						alert("<?php echo _translate("Unable to generate the excel file"); ?>");
					} else {
						window.open('/download.php?d=a&f=' + data, '_blank');
					}
				});
		}
	}

	function getFreezers(labId, type) {
		$.blockUI();
		$("#srcRequest").html("");
		$.post("/vl/program-management/get-freezer-list-by-lab.php", {
				labId: labId,
			},
			function(data) {
				$.unblockUI();
				$("#freezerId").html(data);
			});
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
