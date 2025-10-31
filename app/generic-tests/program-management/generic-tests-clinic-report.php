<?php

use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;
use App\Services\GeoLocationsService;

$title = _translate("Other Lab Tests | Clinics Report");

require_once APPLICATION_PATH . '/header.php';

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);


$healthFacilites = $facilitiesService->getHealthFacilities('tb');
$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");

$state = $geolocationService->getProvinces("yes");

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
		<h1> <em class="fa-solid fa-book"></em> <?php echo _translate("Clinic Reports"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("Clinic Reports"); ?></li>
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
									<ul id="myTab" class="nav nav-tabs">
										<li class="active"><a href="#sampleTestingReport" data-toggle="tab"><?php echo _translate("Sample Testing Report"); ?></a></li>
										<li><a href="#patientTestHistoryFormReport" data-toggle="tab"><?php echo _translate("Patient Test History"); ?></a></li>
									</ul>
									<div id="myTabContent" class="tab-content">
										<div class="tab-pane fade in active" id="sampleTestingReport">
											<table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td style="width: 14%;"><strong>
															<?php echo _translate("Province/State"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23%;">
														<select class="form-control stReportFilter select2 select2-element" id="stState" onchange="getByProvince('stDistrict','stfacilityName',this.value)" name="stState" title="<?php echo _translate('Please select Province/State'); ?>">
															<?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
														</select>
													</td>

													<td style="width: 14%;"><strong>
															<?php echo _translate("District/County"); ?> :
														</strong></td>
													<td style="width: 23%;">
														<select class="form-control stReportFilter select2 select2-element" id="stDistrict" name="stDistrict" title="<?php echo _translate('Please select District/County'); ?>" onchange="getByDistrict('stfacilityName',this.value)">
														</select>
													</td>
													<td style="width: 14%;"><strong><?php echo _translate("Facility"); ?> :</strong></td>
													<td style="width: 23%;">
														<select class="stReportFilter" id="stfacilityName" name="stfacilityName" title="<?php echo _translate('Please select facility name'); ?>" multiple="multiple" style="width:220px;">
															<?= $facilitiesDropdown; ?>
														</select>
													</td>
												<tr>
													<td style="width: 14%;"><strong>
															<?php echo _translate("Sample Collection Date "); ?>&nbsp;:
														</strong></td>
													<td style="width: 23%;">
														<input type="text" id="stSampleCollectionDate" name="stSampleCollectionDate" class="form-control stReportFilter" placeholder="<?= _translate('Select Sample Collection date'); ?>" style="width:220px;background:#fff;" />
													</td>
													<td colspan="3">&nbsp;<input type="button" onclick="sampleTestingReport();" value="<?= _translate('Search'); ?>" class="searchBtn btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm" onclick="resetFilters('stReportFilter');"><span>
																<?= _translate("Reset"); ?>
															</span></button>
													</td>
												</tr>
											</table>
											<figure class="highcharts-figure">
												<div id="container"></div>
												<div id="sampleTestingResultDetails">
													<p class="highcharts-description">
													</p>
											</figure>
										</div>
										<div class="tab-pane fade" id="patientTestHistoryFormReport">
											<table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Patient ID"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<input type="text" id="patientId" name="patientId" class="form-control patientHistoryFilter" placeholder="<?php echo _translate('Enter Patient ID'); ?>" style="background:#fff;" />
													</td>
													<td style="width: 10%;"><strong>
															<?php echo _translate("Patient Name"); ?>&nbsp;:
														</strong></td>
													<td style="width: 23.33%;">
														<input type="text" id="patientName" name="patientName" class="form-control patientHistoryFilter" placeholder="<?php echo _translate('Enter Patient Name'); ?>" style="background:#fff;" />
													</td>
													<td> <input type="button" onclick="searchVlRequestData();" value="<?= _translate('Search'); ?>" class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm" onclick="resetFilters('patientHistoryFilter');">
															<span><?= _translate('Reset'); ?></span>
														</button>
														<button class="btn btn-success btn-sm" type="button" onclick="exportPatientTesthistoryInexcel()"><em class="fa-solid fa-cloud-arrow-down"></em>
															<?php echo _translate("Export to excel"); ?>
														</button>
													</td>
												</tr>
											</table>
											<table aria-describedby="table" id="patientTestHistoryReport" class="table table-bordered table-striped" aria-hidden="true">
												<thead>
													<tr>
														<th>
															<?php echo _translate("Patient ID"); ?>
														</th>
														<th scope="row">
															<?php echo _translate("Patient Name"); ?>
														</th>
														<th>
															<?php echo _translate("Age"); ?>.
														</th>
														<th>
															<?php echo _translate("DoB"); ?>
														</th>
														<th scope="row">
															<?php echo _translate("Facility Name"); ?>
														</th>
														<th>
															<?php echo _translate("Requesting Clinican"); ?>
														</th>
														<th>
															<?php echo _translate("Sample Collection Date"); ?>
														</th>
														<th>
															<?php echo _translate("Sample Type"); ?>
														</th>
														<th>
															<?php echo _translate("Lab Name"); ?>
														</th>
														<th>
															<?php echo _translate("Sample Tested Date"); ?>
														</th>
														<th>
															<?php echo _translate("Result"); ?>
														</th>
														<th>
															<?php echo _translate("Download PDF"); ?>
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
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script src="/assets/js/highcharts.js"></script>
<script src="/assets/js/highcharts-exporting.js"></script>
<script src="/assets/js/highcharts-offline-exporting.js"></script>
<script src="/assets/js/highcharts-accessibility.js"></script>
<script type="text/javascript">
	let searchExecuted = false;
	var oTablepatientTestHistoryReport = null;
	$(document).ready(function() {
		$("#stState").select2({
			placeholder: "<?php echo _translate("Select Province"); ?>"
		});
		$("#stDistrict").select2({
			placeholder: "<?php echo _translate("Select District"); ?>"
		});
		$("#stfacilityName").selectize({
			plugins: ["restore_on_backspace", "remove_button", "clear_button"],
		});
		$('#stSampleCollectionDate').daterangepicker({
				locale: {
					cancelLabel: "<?= _translate("Clear", true); ?>",
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
					'Last 60 Days': [moment().subtract(59, 'days'), moment()],
					'Last 90 Days': [moment().subtract(89, 'days'), moment()],
					'Last 120 Days': [moment().subtract(119, 'days'), moment()],
					'Last 180 Days': [moment().subtract(179, 'days'), moment()],
					'This Month': [moment().startOf('month'), moment().endOf('month')],
					'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
				}
			},
			function(start, end) {
				startDate = start.format('YYYY-MM-DD');
				endDate = end.format('YYYY-MM-DD');
			});
		getSampleTestingResult();
		patientHistoryReport();
		$("#patientTestHistoryFormReport input").on("change", function() {
			searchExecuted = false;
		});
	});

	function patientHistoryReport() {
		$.blockUI();
		oTablepatientTestHistoryReport = $('#patientTestHistoryReport').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ <?= _translate("records per page", true); ?>"
			},
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			//"bStateSave" : true,
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
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center",
					"bSortable": false
				},
			],
			"aaSorting": [
				[9, "desc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "get-patient-test-history-report.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "patientId",
					"value": $("#patientId").val()
				});
				aoData.push({
					"name": "patientName",
					"value": $("#patientName").val()
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

	function searchVlRequestData() {
		searchExecuted = true;
		$.blockUI();
		oTablepatientTestHistoryReport.fnDraw();
		$.unblockUI();
	}

	function exportPatientTesthistoryInexcel() {
		if (searchExecuted === false) {
			searchVlRequestData();
		}
		$.blockUI();
		$.post("/generic-tests/program-management/generic-patient-test-history-in-excel.php", {
				patient_id: $("#patientId").val(),
				patient_name: $("#patientName").val()
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?php echo _translate("Unable to generate the excel file"); ?>");
				} else {
					$.unblockUI();
					window.open('/download.php?f=' + data, '_blank');
				}
			});
	}

	function getByProvince(districtId, facilityId, provinceId) {
		$("#" + districtId).html('');
		$("#" + facilityId).html('');
		$.post("/common/get-by-province-id.php", {
				provinceId: provinceId,
				districts: true,
				facilities: true,
				facilityCode: true
			},
			function(data) {
				Obj = $.parseJSON(data);
				$("#" + districtId).html(Obj['districts']);
				$("#" + facilityId).html(Obj['facilities']);
			});

	}

	function getByDistrict(facilityId, districtId) {
		$("#" + facilityId).html('');
		$.post("/common/get-by-district-id.php", {
				districtId: districtId,
				facilities: true,
				facilityCode: true
			},
			function(data) {
				Obj = $.parseJSON(data);
				$("#" + facilityId).html(Obj['facilities']);
			});
	}

	function resetFilters(filtersClass) {
		$('.' + filtersClass).val('');
		$('.' + filtersClass).val(null).trigger('change');
		searchVlRequestData();
	}

	function sampleTestingReport() {
		$.when(
				getSampleTestingResult()
			)
			.done(function() {
				$.unblockUI();
				$(window).scroll();
			});

		$(window).on('beforeunload', function() {
			if (currentXHR !== null && currentXHR !== undefined) {
				currentXHR.abort();
			}
		});
	}

	function getSampleTestingResult() {
		currentXHR = $.post("/generic-tests/program-management/generic-tests-sample-testing-report.php", {
				sampleCollectionDate: $("#stSampleCollectionDate").val(),
				state: $('#stState').val(),
				district: $('#stDistrict').val(),
				facilityName: $('#stfacilityName').val(),
			},
			function(data) {
				if (data != '') {
					$("#sampleTestingResultDetails").html(data);
				}
			});
		return currentXHR;
	}

	function generateResultPDF(id) {
		//$.blockUI();
		<?php
		$path = '';
		$path = '/generic-tests/results/generate-result-pdf.php';
		?>
		$.post("<?php echo $path; ?>", {
				source: 'print',
				id: id
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?= _translate("Unable to generate download", true); ?>");
				} else {
					$.unblockUI();
					oTablepatientTestHistoryReport.fnDraw();
					window.open('/download.php?f=' + data, '_blank');
				}
			});
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
