<?php
$title = "Export Data";
require_once('../../startup.php');
include_once(APPLICATION_PATH . '/header.php');
include_once(APPLICATION_PATH . '/models/General.php');

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
$sQuery = "SELECT * FROM r_covid19_sample_type where status='active'";
$sResult = $db->rawQuery($sQuery);
$fQuery = "SELECT * FROM facility_details where status='active' and facility_type !=2";
$fResult = $db->rawQuery($fQuery);
$vlLabQuery = "SELECT * FROM facility_details where status='active' and facility_type =2";
$vlLabResult = $db->rawQuery($vlLabQuery);
$batQuery = "SELECT batch_code FROM batch_details where batch_status='completed'";
$batResult = $db->rawQuery($batQuery);
//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);
//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);


$general = new General($db);
$covid19Results = $general->getCovid19Results();
if((isset($arr['covid19_report_type']) && $arr['covid19_report_type'] =='rwanda')){
	$reportType = 'generate-export-rwanda.php';
}else{
	$reportType = 'generate-export-data.php';
}

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
		<h1><i class="fa fa-book"></i> Export Data
			<!--<ol class="breadcrumb">-->
			<!--  <li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>-->
			<!--  <li class="active">Export Result</li>-->
			<!--</ol>-->

		</h1>
	</section>
	<!-- Main content -->
	<section class="content">
		<!-- <pre><?php print_r($arr);?></pre> -->
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<table class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width:98%;">
						<tr>
							<th>Sample Collection Date</th>
							<td>
								<input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="Select Collection Date" readonly style="width:220px;background:#fff;" />
							</td>
							<th>Facility Name</th>
							<td>
								<select class="form-control" id="facilityName" name="facilityName" title="Please select facility name" multiple="multiple" style="width:220px;">
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
							<th>Testing Lab</th>
							<td>
								<select class="form-control" id="vlLab" name="vlLab" title="Please select vl lab" style="width:220px;">
									<option value=""> -- Select -- </option>
									<?php
									foreach ($vlLabResult as $vlLab) {
									?>
										<option value="<?php echo $vlLab['facility_id']; ?>"><?php echo ucwords($vlLab['facility_name'] . "-" . $vlLab['facility_code']); ?></option>
									<?php
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<th>Sample Test Date</th>
							<td>
								<input type="text" id="sampleTestDate" name="sampleTestDate" class="form-control" placeholder="Select Sample Test Date" readonly style="width:220px;background:#fff;" />
							</td>

							<th>Result </th>
							<td>
								<select class="form-control" id="vLoad" name="vLoad" title="Please select batch code" style="width:220px;">
									<option value=""> -- Select -- </option>
									<?php foreach ($covid19Results as $covid19ResultKey => $covid19ResultValue) { ?>
										<option value="<?php echo $covid19ResultKey; ?>"> <?php echo $covid19ResultValue; ?> </option>
									<?php } ?>
								</select>
							</td>

							<th>Last Print Date</th>
							<td>
								<input type="text" id="printDate" name="printDate" class="form-control" placeholder="Select Print Date" readonly style="width:220px;background:#fff;" />
							</td>
						</tr>
						<tr>
							<th>Status</th>
							<td>
								<select name="status" id="status" class="form-control" title="Please choose status">
									<option value=""> -- Select -- </option>
									<option value="7">Accepted</option>
									<option value="4">Rejected</option>
									<option value="6">Awaiting Clinic Approval</option>
								</select>
							</td>

							<th>Funding Sources</th>
							<td>
								<select class="form-control" name="fundingSource" id="fundingSource" title="Please choose funding source">
									<option value=""> -- Select -- </option>
									<?php
									foreach ($fundingSourceList as $fundingSource) {
									?>
										<option value="<?php echo base64_encode($fundingSource['funding_source_id']); ?>"><?php echo ucwords($fundingSource['funding_source_name']); ?></option>
									<?php } ?>
								</select>
							</td>
							<th>Implementing Partners</th>
							<td>
								<select class="form-control" name="implementingPartner" id="implementingPartner" title="Please choose implementing partner">
									<option value=""> -- Select -- </option>
									<?php
									foreach ($implementingPartnerList as $implementingPartner) {
									?>
										<option value="<?php echo base64_encode($implementingPartner['i_partner_id']); ?>"><?php echo ucwords($implementingPartner['i_partner_name']); ?></option>
									<?php } ?>
								</select>
							</td>
						</tr>
						<tr>
							<td colspan="6">
								&nbsp;<button onclick="searchVlRequestData();" value="Search" class="btn btn-primary btn-sm"><span>Search</span></button>

								&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Clear Search</span></button>

								&nbsp;<button class="btn btn-success" type="button" onclick="exportInexcel('<?php echo $reportType;?>')"><i class="fa fa-cloud-download" aria-hidden="true"></i> Download</button>

								&nbsp;<button class="btn btn-default pull-right" onclick="$('#showhide').fadeToggle();return false;"><span>Manage Columns</span></button>
							</td>
						</tr>

					</table>
					<span style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;" id="showhide" class="">
						<div class="row" style="background:#e0e0e0;padding: 15px;margin-top: -25px;">
							<div class="col-md-12">
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="0" id="iCol0" data-showhide="sample_code" class="showhideCheckBox" /> <label for="iCol0">Sample Code</label>
								</div>
								<?php $i = 0;
								if ($sarr['user_type'] != 'standalone') {
									$i = 1; ?>
									<div class="col-md-3">
										<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i; ?>" id="iCol<?php echo $i; ?>" data-showhide="remote_sample_code" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Remote Sample Code</label>
									</div>
								<?php } ?>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="batch_code" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Batch Code</label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="patient_id" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Patient ID</label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="patient_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Patient Name</label> <br>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="facility_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Facility Name</label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="result" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Result</label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="status_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Status</label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="funding_source" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Funding Source</label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="implementing_partner" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Implementing Partner</label>
								</div>
							</div>
						</div>
					</span>

					<!-- /.box-header -->
					<div class="box-body">
						<table id="vlRequestDataTable" class="table table-bordered table-striped">
							<thead>
								<tr>
									<th>Sample Code</th>
									<?php if ($sarr['user_type'] != 'standalone') { ?>
										<th>Remote Sample <br />Code</th>
									<?php } ?>
									<th>Batch Code</th>
									<th>Patient ID</th>
									<th>Patient Name</th>
									<th>Facility Name</th>
									<th>Result</th>
									<th>Status</th>
									<th>Funding Source</th>
									<th>Implementing Partner</th>
									<th>Action</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="12" class="dataTables_empty">Loading data from server</td>
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
<script type="text/javascript" src="/assets/plugins/daterangepicker/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
	var startDate = "";
	var endDate = "";
	var selectedTests = [];
	var selectedTestsId = [];
	var oTable = null;
	$(document).ready(function() {
		$("#facilityName").select2({
			placeholder: "Select Facilities"
		});
		$('#sampleCollectionDate,#sampleTestDate,#printDate').daterangepicker({
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

		$('#printDate').val("");
		//$('#sampleCollectionDate').val("");
		$('#sampleTestDate').val("");
		loadVlRequestData();

		$(".showhideCheckBox").change(function() {
			if ($(this).attr('checked')) {
				idpart = $(this).attr('data-showhide');
				$("#" + idpart + "-sort").show();
			} else {
				idpart = $(this).attr('data-showhide');
				$("#" + idpart + "-sort").hide();
			}
		});

		$("#showhide").hover(function() {}, function() {
			$(this).fadeOut('slow')
		});
		var i = '<?php echo $i; ?>';
		for (colNo = 0; colNo <= i; colNo++) {
			$("#iCol" + colNo).attr("checked", oTable.fnSettings().aoColumns[parseInt(colNo)].bVisible);
			if (oTable.fnSettings().aoColumns[colNo].bVisible) {
				$("#iCol" + colNo + "-sort").show();
			} else {
				$("#iCol" + colNo + "-sort").hide();
			}
		}
	});

	function fnShowHide(iCol) {
		var bVis = oTable.fnSettings().aoColumns[iCol].bVisible;
		oTable.fnSetColumnVis(iCol, bVis ? false : true);
	}

	function loadVlRequestData() {
		$.blockUI();
		oTable = $('#vlRequestDataTable').dataTable({
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
					"sClass": "center"
				},
				<?php if ($sarr['user_type'] != 'standalone') { ?> {
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
					"sClass": "center",
					"bSortable": false
				},
			],
			"aaSorting": [
				[0, "asc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/covid-19/management/get-data-export.php",
			"fnServerData": function(sSource, aoData, fnCallback) {

				aoData.push({
					"name": "sampleCollectionDate",
					"value": $("#sampleCollectionDate").val()
				});
				aoData.push({
					"name": "sampleTestDate",
					"value": $("#sampleTestDate").val()
				});
				aoData.push({
					"name": "printDate",
					"value": $("#printDate").val()
				});
				aoData.push({
					"name": "facilityName",
					"value": $("#facilityName").val()
				});
				aoData.push({
					"name": "vlLab",
					"value": $("#vlLab").val()
				});
				aoData.push({
					"name": "vLoad",
					"value": $("#vLoad").val()
				});
				aoData.push({
					"name": "status",
					"value": $("#status").val()
				});
				aoData.push({
					"name": "showReordSample",
					"value": $("#showReordSample").val()
				});
				aoData.push({
					"name": "fundingSource",
					"value": $("#fundingSource").val()
				});
				aoData.push({
					"name": "implementingPartner",
					"value": $("#implementingPartner").val()
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
		oTable.fnDraw();
		$.unblockUI();
	}

	function convertSearchResultToPdf(id) {
		<?php
		$path = '';
		$path = '/covid-19/results/generate-result-pdf.php';
		?>
		$.post("<?php echo $path; ?>", {
				source: 'print',
				id: id
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					alert('Unable to generate download');
				} else {
					window.open('/uploads/' + data, '_blank');
				}
			});
	}

	function exportInexcel(fileName) {
		var withAlphaNum = null;
		$.blockUI();
		oTable.fnDraw();
		$.post(fileName, {
				Sample_Collection_Date: $("#sampleCollectionDate").val(),
				Batch_Code: $("#batchCode  option:selected").text(),
				Facility_Name: $("#facilityName  option:selected").text(),
				sample_Test_Date: $("#sampleTestDate").val(),
				Viral_Load: $("#vLoad  option:selected").text(),
				Print_Date: $("#printDate").val(),
				Status: $("#status  option:selected").text(),
				withAlphaNum: withAlphaNum
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					$.unblockUI();
					alert('Unable to generate excel.');
				} else {
					$.unblockUI();
					location.href = '/temporary/' + data;
				}
			});
	}
</script>
<?php
include(APPLICATION_PATH . '/footer.php');
?>