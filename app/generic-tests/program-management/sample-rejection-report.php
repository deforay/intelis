<?php

use App\Registries\ContainerRegistry;
use App\Services\FacilitiesService;
use App\Services\GenericTestsService;

$title = _("Sample Rejection Report");
require_once APPLICATION_PATH . '/header.php';

/** @var GenericTestsService $facilitiesService */
$genericService = ContainerRegistry::get(GenericTestsService::class);
/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);


$healthFacilites = $facilitiesService->getHealthFacilities('generic-tests');
$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");
$testingLabs = $facilitiesService->getTestingLabs('generic-tests');
$testingLabsDropdown = $general->generateSelectOptions($testingLabs, null, "-- Select --");
$sampleTypeDetails = $genericService->getGenericSampleTypes();


?>
<style>
	.select2-selection__choice {
		color: black !important;
	}

	#vlRequestDataTable tr:hover {
		cursor: pointer;
		background: #eee !important;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-book"></em> <?php echo _("Sample Rejection Report"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Rejection Result"); ?></li>
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
							<td>&nbsp;<strong><?php echo _("Lab"); ?> &nbsp;:</strong></td>
							<td>
								<select class="form-control" id="labName" name="labName" title="<?php echo _('Please select lab name'); ?>" style="width:220px;">
									<?= $testingLabsDropdown; ?>
								</select>
							</td>
						</tr>
						<tr>
							<td>&nbsp;<strong><?php echo _("Sample Type"); ?>&nbsp;:</strong></td>
							<td>
								<select style="width:220px;" class="form-control" id="sampleType" name="sampleType" title="<?php echo _('Please select sample type'); ?>">
									<?= $general->generateSelectOptions($sampleTypeDetails, null, '-- Select --'); ?>
								</select>
							</td>

							<td>&nbsp;<strong><?php echo _("Clinic Name"); ?> &nbsp;:</strong></td>
							<td>
								<select class="form-control" id="clinicName" name="clinicName" title="<?php echo _('Please select clinic name'); ?>" multiple="multiple" style="width:220px;">
									<?= $facilitiesDropdown; ?>
								</select>
							</td>

						</tr>
						<tr>
							<td colspan="4">&nbsp;<input type="button" onclick="searchResultData();" value="<?php echo _('Search'); ?>" class="btn btn-success btn-sm">
								&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset"); ?></span></button>
							</td>
						</tr>

					</table>
					<!-- /.box-header -->
					<div class="box-body" id="pieChartDiv">

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
<script>
	$(function() {
		$("#clinicName").select2({
			placeholder: "<?php echo _("Select Clinics"); ?>"
		});
		$("#labName").select2({
			placeholder: "<?php echo _("Select Labs"); ?>"
		});
		$('#sampleCollectionDate').daterangepicker({
				locale: {
					cancelLabel: "<?= _("Clear"); ?>",
					format: 'DD-MMM-YYYY',
					separator: ' to ',
				},
				startDate: moment().subtract('days', 365),
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
					'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')]
				}
			},
			function(start, end) {
				startDate = start.format('YYYY-MM-DD');
				endDate = end.format('YYYY-MM-DD');
			});
		searchResultData();
	});

	function searchResultData() {
		$.blockUI();
		$.post("/generic-tests/program-management/get-rejection-result.php", {
				sampleCollectionDate: $("#sampleCollectionDate").val(),
				labName: $("#labName").val(),
				clinicName: $("#clinicName").val(),
				sampleType: $("#sampleType").val()
			},
			function(data) {
				if (data != '') {
					$("#pieChartDiv").html(data);
				}
			});
		$.unblockUI();
	}

	function exportInexcel() {
		$.blockUI();
		$.post("/generic-tests/program-management/export-sample-rejection-report.php", {
				sampleCollectionDate: $("#sampleCollectionDate").val(),
				lab_name: $("#labName").val(),
				clinic_name: $("#clinicName").val(),
				sample_type: $("#sampleType").val()
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert("<?php echo _("Unable to generate excel"); ?>.");
				} else {
					$.unblockUI();
					location.href = '/temporary/' + data;
				}
			});
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
