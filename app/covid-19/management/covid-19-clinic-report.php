<?php
$title = _("Covid-19 | Clinics Report");

require_once(APPLICATION_PATH . '/header.php');

$tsQuery = "SELECT * FROM r_sample_status";
$tsResult = $db->rawQuery($tsQuery);

//$arr = $general->getGlobalConfig();

$facilitiesDb = new \Vlsm\Models\Facilities();


$healthFacilites = $facilitiesDb->getHealthFacilities('covid19');
$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");
$testingLabs = $facilitiesDb->getTestingLabs('covid19');
$testingLabsDropdown = $general->generateSelectOptions($testingLabs, null, "-- Select --");



$sQuery = "SELECT * FROM r_covid19_sample_type WHERE `status`='active'";
$sResult = $db->rawQuery($sQuery);

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
		<h1> <i class="fa-solid fa-book"></i> <?php echo _("Clinic Reports"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa-solid fa-chart-pie"></i> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Clinic Reports"); ?></li>
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
									<ul id="myTab" class="nav nav-tabs">
										<li class="active"><a href="#highViralLoadReport" data-toggle="tab"><?php echo _("Positivity Report"); ?></a></li>
										<li><a href="#sampleRjtReport" data-toggle="tab"><?php echo _("Sample Rejection Report"); ?></a></li>
										<li><a href="#notAvailReport" data-toggle="tab"><?php echo _("Results Not Available Report"); ?></a></li>
										<li><a href="#incompleteFormReport" data-toggle="tab"><?php echo _("Data Quality Check"); ?></a></li>
									</ul>
									<div id="myTabContent" class="tab-content">
										<div class="tab-pane fade in active" id="highViralLoadReport">
											<table class="table" style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td><b><?php echo _("Sample Test Date"); ?>&nbsp;:</b></td>
													<td>
														<input type="text" id="hvlSampleTestDate" name="hvlSampleTestDate" class="form-control stDate" placeholder="<?php echo _('Select Sample Test Date'); ?>" readonly style="width:220px;background:#fff;" onchange="setSampleTestDate(this)" />
													</td>
													<td>&nbsp;<b><?php echo _("Batch Code"); ?>&nbsp;:</b></td>
													<td>
														<select class="form-control" id="hvlBatchCode" name="hvlBatchCode" title="<?php echo _('Please select batch code'); ?>" style="width:220px;">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<?php
															foreach ($batResult as $code) {
															?>
																<option value="<?php echo $code['batch_code']; ?>"><?php echo $code['batch_code']; ?></option>
															<?php
															}
															?>
														</select>
													</td>
													<td>&nbsp;<b><?php echo _("Sample Type"); ?>&nbsp;:</b></td>
													<td>
														<select style="width:220px;" class="form-control" id="hvlSampleType" name="sampleType" title="<?php echo _('Please select sample type'); ?>">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<?php
															foreach ($sResult as $type) {
															?>
																<option value="<?php echo $type['sample_id']; ?>"><?php echo ucwords($type['sample_name']); ?></option>
															<?php
															}
															?>
														</select>
													</td>
												</tr>
												<tr>
													<td>&nbsp;<b><?php echo _("Facility Name & Code"); ?>&nbsp;:</b></td>
													<td>
														<select class="form-control" id="hvlFacilityName" name="hvlFacilityName" title="<?php echo _('Please select facility name'); ?>" multiple="multiple" style="width:220px;">
															<?= $facilitiesDropdown; ?>
														</select>
													</td>
													<td>&nbsp;<b><?php echo _("Contact Status"); ?>&nbsp;:</b></td>
													<td>
														<select class="form-control" id="hvlContactStatus" name="hvlContactStatus" title="<?php echo _('Please select contact status'); ?>" style="width:220px;">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<option value="yes"><?php echo _("Completed"); ?></option>
															<option value="no"><?php echo _("Not Completed"); ?></option>
															<option value="all" selected="selected"><?php echo _("All"); ?></option>
														</select>
													</td>
													<td><b><?php echo _("Gender"); ?>&nbsp;:</b></td>
													<td>
														<select name="hvlGender" id="hvlGender" class="form-control" title="<?php echo _('Please choose gender'); ?>" style="width:220px;" onchange="">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<option value="male"><?php echo _("Male"); ?></option>
															<option value="female"><?php echo _("Female"); ?></option>
															<option value="not_recorded"><?php echo _("Not Recorded"); ?></option>
														</select>
													</td>
												</tr>
												<tr>
													<td></td>
													<td></td>
													<td></td>
													<td></td>
												</tr>
												<tr>
													<td colspan="6">&nbsp;<input type="button" onclick="searchVlRequestData();" value="<?php echo _("Search"); ?>" class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset"); ?></span></button>
														<button class="btn btn-success btn-sm" type="button" onclick="exportHighViralLoadInexcel()"><i class="fa-solid fa-cloud-arrow-down"></i> <?php echo _("Export to excel"); ?></button>
													</td>
												</tr>
											</table>

											<table id="highViralLoadReportTable" class="table table-bordered table-striped">
												<thead>
													<tr>
														<th><?php echo _("Sample Code"); ?></th>
														<?php if ($_SESSION['instanceType'] != 'standalone') { ?>
															<th><?php echo _("Remote Sample"); ?> <br /><?php echo _("Code"); ?></th>
														<?php } ?>
														<th><?php echo _("Facility Name"); ?></th>
														<th><?php echo _("Patient's ID"); ?></th>
														<th><?php echo _("Patient's Name"); ?></th>
														<th><?php echo _("Sample Collection Date"); ?></th>
														<th><?php echo _("Sample Tested Date"); ?></th>
														<th><?php echo _("Testing Lab"); ?></th>
														<th><?php echo _("Result"); ?></th>
														<!--<th><?php echo _("Status"); ?></th>-->
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="7" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
													</tr>
												</tbody>
											</table>
										</div>
										<div class="tab-pane fade" id="sampleRjtReport">
											<table class="table" style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td><b><?php echo _("Sample Test Date"); ?>&nbsp;:</b></td>
													<td>
														<input type="text" id="rjtSampleTestDate" name="rjtSampleTestDate" class="form-control stDate daterange" placeholder="<?php echo _('Select Sample Test Date'); ?>" readonly style="width:220px;background:#fff;" onchange="setSampleTestDate(this)" />
													</td>
													<td>&nbsp;<b><?php echo _("Batch Code"); ?>&nbsp;:</b></td>
													<td>
														<select class="form-control" id="rjtBatchCode" name="rjtBatchCode" title="<?php echo _('Please select batch code'); ?>" style="width:220px;">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<?php
															foreach ($batResult as $code) {
															?>
																<option value="<?php echo $code['batch_code']; ?>"><?php echo $code['batch_code']; ?></option>
															<?php
															}
															?>
														</select>
													</td>
													<td>&nbsp;<b><?php echo _("Sample Type"); ?>&nbsp;:</b></td>
													<td>
														<select style="width:220px;" class="form-control" id="rjtSampleType" name="sampleType" title="<?php echo _('Please select sample type'); ?>">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<?php
															foreach ($sResult as $type) {
															?>
																<option value="<?php echo $type['sample_id']; ?>"><?php echo ucwords($type['sample_name']); ?></option>
															<?php
															}
															?>
														</select>
													</td>
												</tr>
												<tr>
													<td>&nbsp;<b><?php echo _("Facility Name & Code"); ?>&nbsp;:</b></td>
													<td>
														<select class="form-control" id="rjtFacilityName" name="facilityName" title="<?php echo _('Please select facility name'); ?>" multiple="multiple" style="width:220px;">
															<?= $facilitiesDropdown; ?>
														</select>
													</td>
													<td><b><?php echo _("Gender"); ?>&nbsp;:</b></td>
													<td>
														<select name="rjtGender" id="rjtGender" class="form-control" title="<?php echo _('Please choose gender'); ?>" style="width:220px;" onchange="">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<option value="male"><?php echo _("Male"); ?></option>
															<option value="female"><?php echo _("Female"); ?></option>
															<option value="not_recorded"><?php echo _("Not Recorded"); ?></option>
														</select>
													</td>
													<td></td>
													<td></td>
												</tr>
												<tr>
													<td></td>
													<td></td>
												</tr>
												<tr>
													<td colspan="6">&nbsp;<input type="button" onclick="searchVlRequestData();" value="<?php echo _("Search"); ?>" class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset"); ?></span></button>
														<button class="btn btn-success btn-sm" type="button" onclick="exportRejectedResultInexcel()"><i class="fa-solid fa-cloud-arrow-down"></i> <?php echo _("Export to excel"); ?></button>
													</td>
												</tr>
											</table>
											<table id="sampleRjtReportTable" class="table table-bordered table-striped">
												<thead>
													<tr>
														<th><?php echo _("Sample Code"); ?></th>
														<?php if ($_SESSION['instanceType'] != 'standalone') { ?>
															<th><?php echo _("Remote Sample"); ?> <br /><?php echo _("Code"); ?></th>
														<?php } ?>
														<th><?php echo _("Facility Name"); ?></th>
														<th><?php echo _("Patient's ID"); ?></th>
														<th><?php echo _("Patient's Name"); ?></th>
														<th><?php echo _("Sample Collection Date"); ?></th>
														<th><?php echo _("VL Lab Name"); ?></th>
														<th><?php echo _("Rejection Reason"); ?></th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="6" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
													</tr>
												</tbody>
											</table>
										</div>
										<div class="tab-pane fade" id="notAvailReport">
											<table class="table" style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td><b><?php echo _("Sample Collection Date"); ?>&nbsp;:</b></td>
													<td>
														<input type="text" id="noResultSampleTestDate" name="noResultSampleTestDate" class="form-control stDate daterange" placeholder="<?php echo _('Select Sample Collection Date'); ?>" readonly style="width:220px;background:#fff;" onchange="setSampleTestDate(this)" />
													</td>
													<td>&nbsp;<b><?php echo _("Batch Code"); ?>&nbsp;:</b></td>
													<td>
														<select class="form-control" id="noResultBatchCode" name="noResultBatchCode" title="<?php echo _('Please select batch code'); ?>" style="width:220px;">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<?php
															foreach ($batResult as $code) {
															?>
																<option value="<?php echo $code['batch_code']; ?>"><?php echo $code['batch_code']; ?></option>
															<?php
															}
															?>
														</select>
													</td>
													<td>&nbsp;<b><?php echo _("Sample Type"); ?>&nbsp;:</b></td>
													<td>
														<select style="width:220px;" class="form-control" id="noResultSampleType" name="sampleType" title="<?php echo _('Please select sample type'); ?>">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<?php
															foreach ($sResult as $type) {
															?>
																<option value="<?php echo $type['sample_id']; ?>"><?php echo ucwords($type['sample_name']); ?></option>
															<?php
															}
															?>
														</select>
													</td>
												</tr>
												<tr>
													<td>&nbsp;<b><?php echo _("Facility Name & Code"); ?>&nbsp;:</b></td>
													<td>
														<select class="form-control" id="noResultFacilityName" name="facilityName" title="<?php echo _('Please select facility name'); ?>" multiple="multiple" style="width:220px;">
															<?= $facilitiesDropdown; ?>
														</select>
													</td>
													<td><b><?php echo _("Gender"); ?>&nbsp;:</b></td>
													<td>
														<select name="noResultGender" id="noResultGender" class="form-control" title="<?php echo _('Please choose gender'); ?>" style="width:220px;" onchange="">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<option value="male"><?php echo _("Male"); ?></option>
															<option value="female"><?php echo _("Female"); ?></option>
															<option value="not_recorded"><?php echo _("Not Recorded"); ?></option>
														</select>
													</td>
													<td></td>
													<td></td>
												</tr>
												<tr>
													<td></td>
													<td></td>
												</tr>
												<tr>
													<td colspan="6">&nbsp;<input type="button" onclick="searchVlRequestData();" value="<?php echo _("Search"); ?>" class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset"); ?></span></button>
														<button class="btn btn-success btn-sm" type="button" onclick="exportNotAvailableResultInexcel()"><i class="fa-solid fa-cloud-arrow-down"></i> <?php echo _("Export to excel"); ?></button>
													</td>
												</tr>
											</table>
											<table id="notAvailReportTable" class="table table-bordered table-striped">
												<thead>
													<tr>
														<th><?php echo _("Sample Code"); ?></th>
														<?php if ($_SESSION['instanceType'] != 'standalone') { ?>
															<th><?php echo _("Remote Sample"); ?> <br /><?php echo _("Code"); ?></th>
														<?php } ?>
														<th><?php echo _("Facility Name"); ?></th>
														<th><?php echo _("Patient's ID"); ?></th>
														<th><?php echo _("Patient's Name"); ?></th>
														<th><?php echo _("Sample Collection Date"); ?></th>
														<th><?php echo _("VL Lab Name"); ?></th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="4" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
													</tr>
												</tbody>
											</table>
										</div>
										<div class="tab-pane fade" id="incompleteFormReport">
											<table class="table" style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td><b><?php echo _("Sample Collection Date"); ?>&nbsp;:</b></td>
													<td>
														<input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="<?php echo _('Select Sample Collection Date'); ?>" readonly style="width:220px;background:#fff;" />
													</td>
													<td>&nbsp;<b><?php echo _("Fields"); ?>&nbsp;:</b></td>
													<td>
														<select class="form-control" id="formField" name="formField" multiple="multiple" title="<?php echo _('Please fields'); ?>" style="width:220px;">
															<option value=""> <?php echo _("-- Select --"); ?> </option>
															<option value="sample_code"><?php echo _("Sample Code"); ?></option>
															<option value="sample_collection_date"><?php echo _("Sample Collection Date"); ?></option>
															<option value="sample_batch_id"><?php echo _("Batch Code"); ?></option>
															<option value="patient_id"><?php echo _("Patient ID"); ?></option>
															<option value="patient_name"><?php echo _("Patient's Name"); ?></option>
															<!--	<option value="facility_id"><?php echo _("Facility Name"); ?></option>
															<option value="facility_state"><?php echo _("Province"); ?></option>
															<option value="facility_district"><?php echo _("County"); ?></option> 
															<option value="sample_type"><?php echo _("Sample Type"); ?></option>-->
															<option value="result"><?php echo _("Result") ?></option>
															<option value="result_status"><?php echo _("Status"); ?></option>
														</select>
													</td>
												</tr>

												<tr>
													<td colspan="4">&nbsp;<input type="button" onclick="searchVlRequestData();" value="<?php echo _("Search"); ?>" class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset"); ?></span></button>
														<button class="btn btn-success btn-sm" type="button" onclick="exportDataQualityInexcel()"><i class="fa-solid fa-cloud-arrow-down"></i> <?php echo _("Export to excel"); ?></button>
													</td>
												</tr>
											</table>
											<table id="incompleteReport" class="table table-bordered table-striped">
												<thead>
													<tr>
														<th><?php echo _("Sample Code"); ?></th>
														<?php if ($_SESSION['instanceType'] != 'standalone') { ?>
															<th><?php echo _("Remote Sample"); ?> <br /><?php echo _("Code"); ?></th>
														<?php } ?>
														<th><?php echo _("Sample Collection Date"); ?></th>
														<th><?php echo _("Batch Code"); ?></th>
														<th><?php echo _("Patient's Name"); ?></th>
														<th><?php echo _("Facility Name"); ?></th>
														<th><?php echo _("Province/State"); ?></th>
														<th><?php echo _("District/County"); ?></th>
														<th><?php echo _("Sample Type"); ?></th>
														<th><?php echo _("Result"); ?></th>
														<th><?php echo _("Status"); ?></th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="12" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
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
<script type="text/javascript">
	var oTableViralLoad = null;
	var oTableRjtReport = null;
	var oTablenotAvailReport = null;
	var oTableincompleteReport = null;
	$(document).ready(function() {
		$("#hvlFacilityName,#rjtFacilityName,#noResultFacilityName").select2({
			placeholder: "<?php echo _("Select Facilities"); ?>"
		});
		$("#formField").select2({
			placeholder: "<?php echo _("Select Fields"); ?>"
		});
		$('#hvlSampleTestDate,#rjtSampleTestDate,#noResultSampleTestDate,#sampleCollectionDate').daterangepicker({
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
		$('#hvlSampleTestDate,#rjtSampleTestDate,#noResultSampleTestDate,#sampleCollectionDate').val('');
		highViralLoadReport();
		sampleRjtReport();
		notAvailReport();
		incompleteForm();
	});

	function highViralLoadReport() {
		$.blockUI();
		oTableViralLoad = $('#highViralLoadReportTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ records per page"
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
				<?php if ($_SESSION['instanceType'] != 'standalone') { ?> {
						"sClass": "center"
					},
				<?php } ?> {
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
			<?php if ($_SESSION['instanceType'] != 'standalone') { ?> "aaSorting": [
					[6, "desc"]
				],
			<?php } else { ?> "aaSorting": [
					[5, "desc"]
				],
			<?php } ?>
			//aaSorting: [[ 4, "desc" ]],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "getPositiveCovid19ResultDetails.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "hvlBatchCode",
					"value": $("#hvlBatchCode").val()
				});
				aoData.push({
					"name": "hvlSampleTestDate",
					"value": $("#hvlSampleTestDate").val()
				});
				aoData.push({
					"name": "hvlFacilityName",
					"value": $("#hvlFacilityName").val()
				});
				aoData.push({
					"name": "hvlSampleType",
					"value": $("#hvlSampleType").val()
				});
				aoData.push({
					"name": "hvlContactStatus",
					"value": $("#hvlContactStatus").val()
				});
				aoData.push({
					"name": "hvlGender",
					"value": $("#hvlGender").val()
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

	function sampleRjtReport() {
		$.blockUI();
		oTableRjtReport = $('#sampleRjtReportTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ records per page"
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
				<?php if ($_SESSION['instanceType'] != 'standalone') { ?> {
						"sClass": "center"
					},
				<?php } ?> {
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
			<?php if ($_SESSION['instanceType'] != 'standalone') { ?> "aaSorting": [
					[5, "desc"]
				],
			<?php } else { ?> "aaSorting": [
					[4, "desc"]
				],
			<?php } ?>
			//"aaSorting": [[ 3, "desc" ]],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "getSampleRejectionReport.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "rjtBatchCode",
					"value": $("#rjtBatchCode").val()
				});
				aoData.push({
					"name": "rjtSampleTestDate",
					"value": $("#rjtSampleTestDate").val()
				});
				aoData.push({
					"name": "rjtFacilityName",
					"value": $("#rjtFacilityName").val()
				});
				aoData.push({
					"name": "rjtSampleType",
					"value": $("#rjtSampleType").val()
				});
				aoData.push({
					"name": "rjtGender",
					"value": $("#rjtGender").val()
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

	function notAvailReport() {
		$.blockUI();
		oTablenotAvailReport = $('#notAvailReportTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ records per page"
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
				<?php if ($_SESSION['instanceType'] != 'standalone') { ?> {
						"sClass": "center"
					},
				<?php } ?> {
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
			<?php if ($_SESSION['instanceType'] != 'standalone') { ?> "aaSorting": [
					[5, "desc"]
				],
			<?php } else { ?> "aaSorting": [
					[4, "desc"]
				],
			<?php } ?>
			//"aaSorting": [[ 3, "desc" ]],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "getResultNotAvailable.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "noResultBatchCode",
					"value": $("#noResultBatchCode").val()
				});
				aoData.push({
					"name": "noResultSampleTestDate",
					"value": $("#noResultSampleTestDate").val()
				});
				aoData.push({
					"name": "noResultFacilityName",
					"value": $("#noResultFacilityName").val()
				});
				aoData.push({
					"name": "noResultSampleType",
					"value": $("#noResultSampleType").val()
				});
				aoData.push({
					"name": "noResultGender",
					"value": $("#noResultGender").val()
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

	function incompleteForm() {
		$.blockUI();
		oTableincompleteReport = $('#incompleteReport').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ records per page"
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
				<?php if ($_SESSION['instanceType'] != 'standalone') { ?> {
						"sClass": "center"
					},
				<?php } ?> {
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
			<?php if ($_SESSION['instanceType'] != 'standalone') { ?> "aaSorting": [
					[2, "desc"]
				],
			<?php } else { ?> "aaSorting": [
					[1, "desc"]
				],
			<?php } ?>
			//"aaSorting": [[ 1, "desc" ]],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "dataQualityCheck.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "sampleCollectionDate",
					"value": $("#sampleCollectionDate").val()
				});
				aoData.push({
					"name": "formField",
					"value": $("#formField").val()
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
		$.blockUI();
		oTableViralLoad.fnDraw();
		oTableRjtReport.fnDraw();
		oTablenotAvailReport.fnDraw();
		//incompleteForm();
		oTableincompleteReport.fnDraw();
		$.unblockUI();
	}

	function updateStatus(id, value) {
		conf = confirm("<?php echo _("Do you wisht to change the contact completed status?"); ?>");
		if (conf) {
			$.post("/vl/program-management/updateContactCompletedStatus.php", {
					id: id,
					value: value
				},
				function(data) {
					alert("<?php echo _("Status updated successfully"); ?>");
					oTableViralLoad.fnDraw();
				});
		} else {
			oTableViralLoad.fnDraw();
		}
	}

	function exportHighViralLoadInexcel() {
		var markAsComplete = false;
		confm = confirm("<?php echo _("Do you want to mark these as complete ?"); ?>");
		if (confm) {
			var markAsComplete = true;
		}
		$.blockUI();
		$.post("/covid-19/management/covid19ClinicResultExportInExcel.php", {
				Sample_Test_Date: $("#hvlSampleTestDate").val(),
				Batch_Code: $("#hvlBatchCode  option:selected").text(),
				Sample_Type: $("#hvlSampleType  option:selected").text(),
				Facility_Name: $("#hvlFacilityName  option:selected").text(),
				Gender: $("#hvlGender  option:selected").text(),
				markAsComplete: markAsComplete
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

	function exportRejectedResultInexcel() {
		$.blockUI();
		$.post("/covid-19/management/covid19RejectedResultExportInExcel.php", {
				Sample_Test_Date: $("#rjtSampleTestDate").val(),
				Batch_Code: $("#rjtBatchCode  option:selected").text(),
				Sample_Type: $("#rjtSampleType  option:selected").text(),
				Facility_Name: $("#rjtFacilityName  option:selected").text(),
				Gender: $("#rjtGender  option:selected").text()
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

	function exportNotAvailableResultInexcel() {
		$.blockUI();
		$.post("/covid-19/management/covid19NotAvailableResultExportInExcel.php", {
				Sample_Test_Date: $("#noResultSampleTestDate").val(),
				Batch_Code: $("#noResultBatchCode  option:selected").text(),
				Sample_Type: $("#noResultSampleType  option:selected").text(),
				Facility_Name: $("#noResultFacilityName  option:selected").text(),
				Gender: $("#noResultGender  option:selected").text()
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

	function exportDataQualityInexcel() {
		$.blockUI();
		$.post("/covid-19/management/covid19DataQualityExportInExcel.php", {
				Sample_Collection_Date: $("#sampleCollectionDate").val(),
				Field_Name: $("#formField  option:selected").text()
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



	function setSampleTestDate(obj) {
		$(".stDate").val($("#" + obj.id).val());
	}
</script>
<?php
require_once(APPLICATION_PATH . '/footer.php');
?>