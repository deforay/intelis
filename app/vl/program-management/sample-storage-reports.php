<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\StorageService;
use App\Registries\ContainerRegistry;

$title = _translate("Freezer/Storage Reports");

require_once APPLICATION_PATH . '/header.php';


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var StorageService $storageService */
$storageService = ContainerRegistry::get(StorageService::class);

$storageInfo = $storageService->getLabStorage();
$sQuery = "SELECT * FROM form_vl as vl WHERE sample_code IS NOT NULL ";
$sResult = []; // $db->rawQuery($sQuery);

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
								<div class="bs-example bs-example-tabs">
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
															<?php echo _translate("Sample Code"); ?>
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
													<td>
														<strong>
															<?php echo _translate("Sample Code"); ?>&nbsp;:
														</strong>
													</td>
													<td>
														<select class="form-control" id="sampleUniqueId" name="sampleUniqueId" title="<?php echo _translate('Please select sample code'); ?>" style="width:220px;">
															<option value=""> <?php echo _translate("-- Select --"); ?> </option>
															<?php
															foreach ($sResult as $sample) {
															?>
																<option value="<?php echo $sample['unique_id']; ?>"><?php echo $sample['sample_code']; ?></option>
															<?php
															}
															?>
														</select>
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
															<?php echo _translate("Patient's Name"); ?>
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

		$("#sampleUniqueId").select2({
			placeholder: "<?php echo _translate("Select Sample"); ?>"
		});

		loadStorageData();
		var i = '<?php echo $i; ?>';
		$(".printedData").click(function() {
			loadStorageHistoryData();

			for (colNo = 0; colNo <= i; colNo++) {
				$("#printiCol" + colNo).attr("checked", opTable.fnSettings().aoColumns[parseInt(colNo)].bVisible);
				if (opTable.fnSettings().aoColumns[colNo].bVisible) {
					$("#printiCol" + colNo + "-sort").show();
				} else {
					$("#printiCol" + colNo + "-sort").hide();
				}
			}
		});
	});

	function loadStorageData() {
		$.blockUI();
		oTable = $('#storageDataTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ records per page"
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
				"sLengthMenu": "_MENU_ records per page"
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
					"name": "sampleUniqueId",
					"value": $("#sampleUniqueId").val()
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

	function convertResultToPdf(id, newData) {
		$.blockUI();
		<?php
		$path = '';
		$path = '/vl/results/generate-result-pdf.php';
		?>
		$.post("<?php echo $path; ?>", {
				source: 'print',
				id: id,
				newData: newData
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?= _translate("Unable to generate download", true); ?>");
				} else {
					$.unblockUI();
					oTable.fnDraw();
					//opTable.fnDraw();
					window.open('/download.php?f=' + data, '_blank');
				}
			});
	}

	function convertSearchResultToPdf(id, newData = null) {
		$.blockUI();
		<?php
		$path = '';
		$path = '/vl/results/generate-result-pdf.php';
		?>
		if (newData == null) {
			var rowsLength = selectedRows.length;
			var totalCount = $("#totalSamplesList").val();
			var checkedRow = $("#checkedRows").val();
		} else {
			var rowsLength = selectedPrintedRows.length;
			var totalCount = $("#totalSamplesPrintedList").val();
			var checkedRow = $("#checkedPrintedRows").val();
		}
		if (rowsLength != 0 && rowsLength > 100) {
			$.unblockUI();
			alert("<?= _translate("You have selected", true); ?> " + rowsLength + " <?php echo _translate("results out of the maximum allowed 100 at a time", true); ?>");
			return false;
		} else if (totalCount != 0 && totalCount > 100 && rowsLength == 0) {
			$.unblockUI();
			alert("<?= _translate("Maximum 100 results allowed to print at a time", true); ?>");
			return false;
		} else {
			id = checkedRow;
		}
		$.post("<?php echo $path; ?>", {
				source: 'print',
				id: id,
				newData: newData
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?= _translate("Unable to generate download", true); ?>");
				} else {
					$.unblockUI();
					if (newData == null) {
						selectedRows = [];
						$(".checkRows").prop('checked', false);
						$("#checkRowsData").prop('checked', false);
						oTable.fnDraw();
					} else {
						selectedPrintedRows = [];
						$(".checkPrintedRows").prop('checked', false);
						$("#checkPrintedRowsData").prop('checked', false);
					}
					window.open('/download.php?f=' + data, '_blank');
				}
			});
	}

	function checkedRow(obj) {
		if ($(obj).is(':checked')) {
			if ($.inArray(obj.value, selectedRows) == -1) {
				selectedRows.push(obj.value);
				selectedRowsId.push(obj.id);
			}
		} else {
			selectedRows.splice($.inArray(obj.value, selectedRows), 1);
			selectedRowsId.splice($.inArray(obj.id, selectedRowsId), 1);
			$("#checkRowsData").attr("checked", false);
		}
		$("#checkedRows").val(selectedRows.join());
	}

	function checkedPrintedRow(obj) {
		if ($(obj).is(':checked')) {
			if ($.inArray(obj.value, selectedRows) == -1) {
				selectedPrintedRows.push(obj.value);
				selectedPrintedRowsId.push(obj.id);
			}
		} else {
			selectedPrintedRows.splice($.inArray(obj.value, selectedPrintedRows), 1);
			selectedPrintedRowsId.splice($.inArray(obj.id, selectedPrintedRowsId), 1);
			$("#checkPrintedRowsData").attr("checked", false);
		}
		$("#checkedPrintedRows").val(selectedPrintedRows.join());
	}

	function toggleAllVisible() {
		//alert(tabStatus);
		$(".checkRows").each(function() {
			$(this).prop('checked', false);
			selectedRows.splice($.inArray(this.value, selectedRows), 1);
			selectedRowsId.splice($.inArray(this.id, selectedRowsId), 1);
		});
		if ($("#checkRowsData").is(':checked')) {
			$(".checkRows").each(function() {
				$(this).prop('checked', true);
				selectedRows.push(this.value);
				selectedRowsId.push(this.id);
			});
		} else {
			$(".checkRows").each(function() {
				$(this).prop('checked', false);
				selectedRows.splice($.inArray(this.value, selectedRows), 1);
				selectedRowsId.splice($.inArray(this.id, selectedRowsId), 1);
				$("#status").prop('disabled', true);
			});
		}
		$("#checkedRows").val(selectedRows.join());
	}

	function toggleAllPrintedVisible() {
		//alert(tabStatus);
		$(".checkPrintedRows").each(function() {
			$(this).prop('checked', false);
			selectedPrintedRows.splice($.inArray(this.value, selectedPrintedRows), 1);
			selectedPrintedRowsId.splice($.inArray(this.id, selectedPrintedRowsId), 1);
		});
		if ($("#checkPrintedRowsData").is(':checked')) {
			$(".checkPrintedRows").each(function() {
				$(this).prop('checked', true);
				selectedPrintedRows.push(this.value);
				selectedPrintedRowsId.push(this.id);
			});
		} else {
			$(".checkPrintedRows").each(function() {
				$(this).prop('checked', false);
				selectedPrintedRows.splice($.inArray(this.value, selectedPrintedRows), 1);
				selectedPrintedRowsId.splice($.inArray(this.id, selectedPrintedRowsId), 1);
				$("#status").prop('disabled', true);
			});
		}
		$("#checkedPrintedRows").val(selectedPrintedRows.join());
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
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
