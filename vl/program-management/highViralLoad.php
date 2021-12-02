<?php
$title = "VL | Clinics Report";
#require_once('../../startup.php'); 
include_once(APPLICATION_PATH . '/header.php');

$tsQuery = "SELECT * FROM r_sample_status";
$tsResult = $db->rawQuery($tsQuery);
//config  query
$configQuery = "SELECT * from global_config";
$configResult = $db->query($configQuery);
$arr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($configResult); $i++) {
	$arr[$configResult[$i]['name']] = $configResult[$i]['value'];
}
$sQuery = "SELECT * FROM r_vl_sample_type where status='active'";
$sResult = $db->rawQuery($sQuery);
$fQuery = "SELECT * FROM facility_details where status='active'";
$fResult = $db->rawQuery($fQuery);
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
		<h1> <i class="fa fa-book"></i> Clinic Reports</h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
			<li class="active">Clinic Reports</li>
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
										<li class="active"><a href="#highViralLoadReport" data-toggle="tab">High Viral Load Report</a></li>
										<li><a href="#sampleRjtReport" data-toggle="tab">Sample Rejection Report</a></li>
										<li><a href="#notAvailReport" data-toggle="tab">Results Not Available Report</a></li>
										<li><a href="#incompleteFormReport" data-toggle="tab">Data Quality Check</a></li>
									</ul>
									<div id="myTabContent" class="tab-content">
										<div class="tab-pane fade in active" id="highViralLoadReport">
											<table class="table" style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td><b>Sample Test Date&nbsp;:</b></td>
													<td>
														<input type="text" id="hvlSampleTestDate" name="hvlSampleTestDate" class="form-control stDate" placeholder="Select Sample Test Date" readonly style="width:220px;background:#fff;" onchange="setSampleTestDate(this)" />
													</td>
													<td>&nbsp;<b>Batch Code&nbsp;:</b></td>
													<td>
														<select class="form-control" id="hvlBatchCode" name="hvlBatchCode" title="Please select batch code" style="width:220px;">
															<option value=""> -- Select -- </option>
															<?php
															foreach ($batResult as $code) {
															?>
																<option value="<?php echo $code['batch_code']; ?>"><?php echo $code['batch_code']; ?></option>
															<?php
															}
															?>
														</select>
													</td>
													<td>&nbsp;<b>Sample Type&nbsp;:</b></td>
													<td>
														<select style="width:220px;" class="form-control" id="hvlSampleType" name="sampleType" title="Please select sample type">
															<option value=""> -- Select -- </option>
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
													<td>&nbsp;<b>Facility Name & Code&nbsp;:</b></td>
													<td>
														<select class="form-control" id="hvlFacilityName" name="hvlFacilityName" title="Please select facility name" multiple="multiple" style="width:220px;">
															<option value=""> -- Select -- </option>
															<?php
															foreach ($fResult as $name) {
															?>
																<option value="<?php echo $name['facility_id']; ?>"><?php echo ucwords($name['facility_name'] . "-" . $name['facility_code']); ?></option>
															<?php
															}
															?>
														</select>
													</td>
													<td>&nbsp;<b>Contact Status&nbsp;:</b></td>
													<td>
														<select class="form-control" id="hvlContactStatus" name="hvlContactStatus" title="Please select contact status" style="width:220px;">
															<option value=""> -- Select -- </option>
															<option value="yes">Completed</option>
															<option value="no">Not Completed</option>
															<option value="all" selected="selected">All</option>
														</select>
													</td>
													<td><b>Gender&nbsp;:</b></td>
													<td>
														<select name="hvlGender" id="hvlGender" class="form-control" title="Please choose gender" style="width:220px;" onchange="hideFemaleDetails(this.value,'hvlPatientPregnant','hvlPatientBreastfeeding');">
															<option value=""> -- Select -- </option>
															<option value="male">Male</option>
															<option value="female">Female</option>
															<option value="not_recorded">Not Recorded</option>
														</select>
													</td>
												</tr>
												<tr>
													<td><b>Pregnant&nbsp;:</b></td>
													<td>
														<select name="hvlPatientPregnant" id="hvlPatientPregnant" class="form-control" title="Please choose pregnant option">
															<option value=""> -- Select -- </option>
															<option value="yes">Yes</option>
															<option value="no">No</option>
														</select>
													</td>
													<td><b>Breastfeeding&nbsp;:</b></td>
													<td>
														<select name="hvlPatientBreastfeeding" id="hvlPatientBreastfeeding" class="form-control" title="Please choose option">
															<option value=""> -- Select -- </option>
															<option value="yes">Yes</option>
															<option value="no">No</option>
														</select>
													</td>
												</tr>
												<tr>
													<td colspan="6">&nbsp;<input type="button" onclick="searchVlRequestData();" value="Search" class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>
														<button class="btn btn-success btn-sm" type="button" onclick="exportHighViralLoadInexcel()"><i class="fa fa-cloud-download" aria-hidden="true"></i> Export to excel</button>
													</td>
												</tr>
											</table>

											<table id="highViralLoadReportTable" class="table table-bordered table-striped">
												<thead>
													<tr>
														<th>Sample Code</th>
														<?php if ($sarr['sc_user_type'] != 'standalone') { ?>
															<th>Remote Sample <br />Code</th>
														<?php } ?>
														<th>Facility Name</th>
														<th>Patient ART no.</th>
														<th>Patient's Name</th>
														<th>Patient Phone no.</th>
														<th>Sample Collection Date</th>
														<th>Sample Tested Date</th>
														<th>Viral Load Lab</th>
														<th>Viral Load (cp/ml)</th>
														<th>Status</th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="7" class="dataTables_empty">Loading data from server</td>
													</tr>
												</tbody>
											</table>
										</div>
										<div class="tab-pane fade" id="sampleRjtReport">
											<table class="table" style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td><b>Sample Test Date&nbsp;:</b></td>
													<td>
														<input type="text" id="rjtSampleTestDate" name="rjtSampleTestDate" class="form-control stDate daterange" placeholder="Select Sample Test Date" readonly style="width:220px;background:#fff;" onchange="setSampleTestDate(this)" />
													</td>
													<td>&nbsp;<b>Batch Code&nbsp;:</b></td>
													<td>
														<select class="form-control" id="rjtBatchCode" name="rjtBatchCode" title="Please select batch code" style="width:220px;">
															<option value=""> -- Select -- </option>
															<?php
															foreach ($batResult as $code) {
															?>
																<option value="<?php echo $code['batch_code']; ?>"><?php echo $code['batch_code']; ?></option>
															<?php
															}
															?>
														</select>
													</td>
													<td>&nbsp;<b>Sample Type&nbsp;:</b></td>
													<td>
														<select style="width:220px;" class="form-control" id="rjtSampleType" name="sampleType" title="Please select sample type">
															<option value=""> -- Select -- </option>
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
													<td>&nbsp;<b>Facility Name & Code&nbsp;:</b></td>
													<td>
														<select class="form-control" id="rjtFacilityName" name="facilityName" title="Please select facility name" multiple="multiple" style="width:220px;">
															<option value=""> -- Select -- </option>
															<?php
															foreach ($fResult as $name) {
															?>
																<option value="<?php echo $name['facility_id']; ?>"><?php echo ucwords($name['facility_name'] . "-" . $name['facility_code']); ?></option>
															<?php
															}
															?>
														</select>
													</td>
													<td><b>Gender&nbsp;:</b></td>
													<td>
														<select name="rjtGender" id="rjtGender" class="form-control" title="Please choose gender" style="width:220px;" onchange="hideFemaleDetails(this.value,'rjtPatientPregnant','rjtPatientBreastfeeding');">
															<option value=""> -- Select -- </option>
															<option value="male">Male</option>
															<option value="female">Female</option>
															<option value="not_recorded">Not Recorded</option>
														</select>
													</td>
													<td><b>Pregnant&nbsp;:</b></td>
													<td>
														<select name="rjtPatientPregnant" id="rjtPatientPregnant" class="form-control" title="Please choose pregnant option">
															<option value=""> -- Select -- </option>
															<option value="yes">Yes</option>
															<option value="no">No</option>
														</select>
													</td>
												</tr>
												<tr>
													<td><b>Breastfeeding&nbsp;:</b></td>
													<td>
														<select name="rjtPatientBreastfeeding" id="rjtPatientBreastfeeding" class="form-control" title="Please choose option">
															<option value=""> -- Select -- </option>
															<option value="yes">Yes</option>
															<option value="no">No</option>
														</select>
													</td>
												</tr>
												<tr>
													<td colspan="6">&nbsp;<input type="button" onclick="searchVlRequestData();" value="Search" class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>
														<button class="btn btn-success btn-sm" type="button" onclick="exportRejectedResultInexcel()"><i class="fa fa-cloud-download" aria-hidden="true"></i> Export to excel</button>
													</td>
												</tr>
											</table>
											<table id="sampleRjtReportTable" class="table table-bordered table-striped">
												<thead>
													<tr>
														<th>Sample Code</th>
														<?php if ($sarr['sc_user_type'] != 'standalone') { ?>
															<th>Remote Sample <br />Code</th>
														<?php } ?>
														<th>Facility Name</th>
														<th>Patient ART no.</th>
														<th>Patient Name</th>
														<th>Sample Collection Date</th>
														<th>VL Lab Name</th>
														<th>Rejection Reason</th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="6" class="dataTables_empty">Loading data from server</td>
													</tr>
												</tbody>
											</table>
										</div>
										<div class="tab-pane fade" id="notAvailReport">
											<table class="table" style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td><b>Sample Collection Date&nbsp;:</b></td>
													<td>
														<input type="text" id="noResultSampleTestDate" name="noResultSampleTestDate" class="form-control stDate daterange" placeholder="Select Sample Collection Date" readonly style="width:220px;background:#fff;" onchange="setSampleTestDate(this)" />
													</td>
													<td>&nbsp;<b>Batch Code&nbsp;:</b></td>
													<td>
														<select class="form-control" id="noResultBatchCode" name="noResultBatchCode" title="Please select batch code" style="width:220px;">
															<option value=""> -- Select -- </option>
															<?php
															foreach ($batResult as $code) {
															?>
																<option value="<?php echo $code['batch_code']; ?>"><?php echo $code['batch_code']; ?></option>
															<?php
															}
															?>
														</select>
													</td>
													<td>&nbsp;<b>Sample Type&nbsp;:</b></td>
													<td>
														<select style="width:220px;" class="form-control" id="noResultSampleType" name="sampleType" title="Please select sample type">
															<option value=""> -- Select -- </option>
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
													<td>&nbsp;<b>Facility Name & Code&nbsp;:</b></td>
													<td>
														<select class="form-control" id="noResultFacilityName" name="facilityName" title="Please select facility name" multiple="multiple" style="width:220px;">
															<option value=""> -- Select -- </option>
															<?php
															foreach ($fResult as $name) {
															?>
																<option value="<?php echo $name['facility_id']; ?>"><?php echo ucwords($name['facility_name'] . "-" . $name['facility_code']); ?></option>
															<?php
															}
															?>
														</select>
													</td>
													<td><b>Gender&nbsp;:</b></td>
													<td>
														<select name="noResultGender" id="noResultGender" class="form-control" title="Please choose gender" style="width:220px;" onchange="hideFemaleDetails(this.value,'noResultPatientPregnant','noResultPatientBreastfeeding');">
															<option value=""> -- Select -- </option>
															<option value="male">Male</option>
															<option value="female">Female</option>
															<option value="not_recorded">Not Recorded</option>
														</select>
													</td>
													<td><b>Pregnant&nbsp;:</b></td>
													<td>
														<select name="noResultPatientPregnant" id="noResultPatientPregnant" class="form-control" title="Please choose pregnant option">
															<option value=""> -- Select -- </option>
															<option value="yes">Yes</option>
															<option value="no">No</option>
														</select>
													</td>
												</tr>
												<tr>
													<td><b>Breastfeeding&nbsp;:</b></td>
													<td>
														<select name="noResultPatientBreastfeeding" id="noResultPatientBreastfeeding" class="form-control" title="Please choose option">
															<option value=""> -- Select -- </option>
															<option value="yes">Yes</option>
															<option value="no">No</option>
														</select>
													</td>
												</tr>
												<tr>
													<td colspan="6">&nbsp;<input type="button" onclick="searchVlRequestData();" value="Search" class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>
														<button class="btn btn-success btn-sm" type="button" onclick="exportNotAvailableResultInexcel()"><i class="fa fa-cloud-download" aria-hidden="true"></i> Export to excel</button>
													</td>
												</tr>
											</table>
											<table id="notAvailReportTable" class="table table-bordered table-striped">
												<thead>
													<tr>
														<th>Sample Code</th>
														<?php if ($sarr['sc_user_type'] != 'standalone') { ?>
															<th>Remote Sample <br />Code</th>
														<?php } ?>
														<th>Facility Name</th>
														<th>Patient ART no.</th>
														<th>Patient Name</th>
														<th>Sample Collection Date</th>
														<th>VL Lab Name</th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="4" class="dataTables_empty">Loading data from server</td>
													</tr>
												</tbody>
											</table>
										</div>
										<div class="tab-pane fade" id="incompleteFormReport">
											<table class="table" style="margin-left:1%;margin-top:20px;width:98%;padding: 3%;">
												<tr>
													<td><b>Sample Collection Date&nbsp;:</b></td>
													<td>
														<input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="Select Sample Collection Date" readonly style="width:220px;background:#fff;" />
													</td>
													<td>&nbsp;<b>Fields&nbsp;:</b></td>
													<td>
														<select class="form-control" id="formField" name="formField" multiple="multiple" title="Please fields" style="width:220px;">
															<option value=""> -- Select -- </option>
															<option value="sample_code">Sample Code</option>
															<option value="sample_collection_date">Sample Collection Date</option>
															<option value="sample_batch_id">Batch Code</option>
															<option value="patient_art_no">Unique ART No.</option>
															<option value="patient_first_name">Patient Name</option>
															<option value="facility_id">Facility Name</option>
															<option value="facility_state">Province</option>
															<option value="facility_district">County</option>
															<option value="sample_type">Sample Type</option>
															<option value="result">Result</option>
															<option value="result_status">Status</option>
														</select>
													</td>
												</tr>

												<tr>
													<td colspan="4">&nbsp;<input type="button" onclick="searchVlRequestData();" value="Search" class="btn btn-success btn-sm">
														&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>
														<button class="btn btn-success btn-sm" type="button" onclick="exportDataQualityInexcel()"><i class="fa fa-cloud-download" aria-hidden="true"></i> Export to excel</button>
													</td>
												</tr>
											</table>
											<table id="incompleteReport" class="table table-bordered table-striped">
												<thead>
													<tr>
														<th>Sample Code</th>
														<?php if ($sarr['sc_user_type'] != 'standalone') { ?>
															<th>Remote Sample <br />Code</th>
														<?php } ?>
														<th>Sample Collection Date</th>
														<th>Batch Code</th>
														<th>Unique ART No</th>
														<th>Patient's Name</th>
														<th>Facility Name</th>
														<th>Province/State</th>
														<th>District/County</th>
														<th>Sample Type</th>
														<th>Result</th>
														<th>Status</th>
													</tr>
												</thead>
												<tbody>
													<tr>
														<td colspan="13" class="dataTables_empty">Loading data from server</td>
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
<script type="text/javascript" src="/assets/plugins/daterangepicker/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
	var oTableViralLoad = null;
	var oTableRjtReport = null;
	var oTablenotAvailReport = null;
	var oTableincompleteReport = null;
	$(document).ready(function() {
		$("#hvlFacilityName,#rjtFacilityName,#noResultFacilityName").select2({
			placeholder: "Select Facilities"
		});
		$("#formField").select2({
			placeholder: "Select Fields"
		});
		$('#hvlSampleTestDate,#rjtSampleTestDate,#noResultSampleTestDate,#sampleCollectionDate').daterangepicker({
				locale: {
					cancelLabel: 'Clear'
				},
				format: 'DD-MMM-YYYY',
				separator: ' to ',
				startDate: moment().subtract(29, 'days'),
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
		$('#hvlSampleTestDate,#rjtSampleTestDate,#noResultSampleTestDate,#sampleCollectionDate').on('cancel.daterangepicker', function(ev, picker) {
			$(this).val('');
		});
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
				<?php if ($sarr['sc_user_type'] != 'standalone') { ?> {
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
			<?php if ($sarr['sc_user_type'] != 'standalone') { ?> "aaSorting": [
					[6, "desc"]
				],
			<?php } else { ?> "aaSorting": [
					[5, "desc"]
				],
			<?php } ?>
			//aaSorting: [[ 4, "desc" ]],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "getHighVlResultDetails.php",
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
				aoData.push({
					"name": "hvlPatientPregnant",
					"value": $("#hvlPatientPregnant").val()
				});
				aoData.push({
					"name": "hvlPatientBreastfeeding",
					"value": $("#hvlPatientBreastfeeding").val()
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
				<?php if ($sarr['sc_user_type'] != 'standalone') { ?> {
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
			<?php if ($sarr['sc_user_type'] != 'standalone') { ?> "aaSorting": [
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
				aoData.push({
					"name": "rjtPatientPregnant",
					"value": $("#rjtPatientPregnant").val()
				});
				aoData.push({
					"name": "rjtPatientBreastfeeding",
					"value": $("#rjtPatientBreastfeeding").val()
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
				<?php if ($sarr['sc_user_type'] != 'standalone') { ?> {
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
			<?php if ($sarr['sc_user_type'] != 'standalone') { ?> "aaSorting": [
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
				aoData.push({
					"name": "noResultPatientPregnant",
					"value": $("#noResultPatientPregnant").val()
				});
				aoData.push({
					"name": "noResultPatientBreastfeeding",
					"value": $("#noResultPatientBreastfeeding").val()
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
				<?php if ($sarr['sc_user_type'] != 'standalone') { ?> {
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
				{
					"sClass": "center"
				},
			],
			<?php if ($sarr['sc_user_type'] != 'standalone') { ?> "aaSorting": [
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
		conf = confirm("Do you wisht to change the contact completed status?");
		if (conf) {
			$.post("/vl/program-management/updateContactCompletedStatus.php", {
					id: id,
					value: value
				},
				function(data) {
					alert("Status updated successfully");
					oTableViralLoad.fnDraw();
				});
		} else {
			oTableViralLoad.fnDraw();
		}
	}

	function exportHighViralLoadInexcel() {
		var markAsComplete = false;
		confm = confirm("Do you want to mark these as complete ?");
		if (confm) {
			var markAsComplete = true;
		}
		$.blockUI();
		$.post("/vl/program-management/vlHighViralLoadResultExportInExcel.php", {
				Sample_Test_Date: $("#hvlSampleTestDate").val(),
				Batch_Code: $("#hvlBatchCode  option:selected").text(),
				Sample_Type: $("#hvlSampleType  option:selected").text(),
				Facility_Name: $("#hvlFacilityName  option:selected").text(),
				Gender: $("#hvlGender  option:selected").text(),
				Pregnant: $("#hvlPatientPregnant  option:selected").text(),
				Breastfeeding: $("#hvlPatientBreastfeeding  option:selected").text(),
				markAsComplete: markAsComplete
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert('Unable to generate the excel file');
				} else {
					$.unblockUI();
					location.href = '/temporary/' + data;
				}
			});
	}

	function exportRejectedResultInexcel() {
		$.blockUI();
		$.post("/vl/program-management/vlRejectedResultExportInExcel.php", {
				Sample_Test_Date: $("#rjtSampleTestDate").val(),
				Batch_Code: $("#rjtBatchCode  option:selected").text(),
				Sample_Type: $("#rjtSampleType  option:selected").text(),
				Facility_Name: $("#rjtFacilityName  option:selected").text(),
				Gender: $("#rjtGender  option:selected").text(),
				Pregnant: $("#rjtPatientPregnant  option:selected").text(),
				Breastfeeding: $("#rjtPatientBreastfeeding  option:selected").text()
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert('Unable to generate the excel file');
				} else {
					$.unblockUI();
					location.href = '/temporary/' + data;
				}
			});
	}

	function exportNotAvailableResultInexcel() {
		$.blockUI();
		$.post("/vl/program-management/vlNotAvailableResultExportInExcel.php", {
				Sample_Test_Date: $("#noResultSampleTestDate").val(),
				Batch_Code: $("#noResultBatchCode  option:selected").text(),
				Sample_Type: $("#noResultSampleType  option:selected").text(),
				Facility_Name: $("#noResultFacilityName  option:selected").text(),
				Gender: $("#noResultGender  option:selected").text(),
				Pregnant: $("#noResultPatientPregnant  option:selected").text(),
				Breastfeeding: $("#noResultPatientBreastfeeding  option:selected").text()
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert('Unable to generate the excel file');
				} else {
					$.unblockUI();
					location.href = '/temporary/' + data;
				}
			});
	}

	function exportDataQualityInexcel() {
		$.blockUI();
		$.post("/vl/program-management/vlDataQualityExportInExcel.php", {
				Sample_Collection_Date: $("#sampleCollectionDate").val(),
				Field_Name: $("#formField  option:selected").text()
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert('Unable to generate the excel file');
				} else {
					$.unblockUI();
					location.href = '/temporary/' + data;
				}
			});
	}

	function hideFemaleDetails(value, pregnant, breastFeeding) {
		if (value == 'female') {
			$("#" + pregnant).attr("disabled", false);
			$("#" + breastFeeding).attr("disabled", false);
		} else {
			$('select#' + pregnant + ' option').removeAttr("selected");
			$('select#' + breastFeeding + ' option').removeAttr("selected");
			$("#" + pregnant).attr("disabled", true);
			$("#" + breastFeeding).attr("disabled", true);
		}
	}

	function setSampleTestDate(obj) {
		$(".stDate").val($("#" + obj.id).val());
	}
</script>
<?php
include(APPLICATION_PATH . '/footer.php');
?>