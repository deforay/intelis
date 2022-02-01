<?php
$title = "Export Data";

include_once(APPLICATION_PATH . '/header.php');


$general = new \Vlsm\Models\General();
$facilitiesDb = new \Vlsm\Models\Facilities();
$hepatitisDb = new \Vlsm\Models\Hepatitis();

$tsQuery = "SELECT * FROM r_sample_status";
$tsResult = $db->rawQuery($tsQuery);

$arr = $general->getGlobalConfig();

$sQuery = "SELECT * FROM r_hepatitis_sample_type where status='active'";
$sResult = $db->rawQuery($sQuery);


$healthFacilites = $facilitiesDb->getHealthFacilities('hepatitis');
$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");
$testingLabs = $facilitiesDb->getTestingLabs('hepatitis');
$testingLabsDropdown = $general->generateSelectOptions($testingLabs, null, "-- Select --");

$batQuery = "SELECT batch_code FROM batch_details WHERE test_type ='hepatitis' AND batch_status='completed'";
$batResult = $db->rawQuery($batQuery);
//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);
//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);



$hepatitisResults = $hepatitisDb->getHepatitisResults();
if ((isset($arr['hepatitis_report_type']) && $arr['hepatitis_report_type'] == 'rwanda' && $arr['vl_form'] != 1)) {
	$reportType = 'generate-export-rwanda.php';
} else {
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
		<h1><i class="fa fa-book"></i> <?php echo _("Export Data");?>
			<!--<ol class="breadcrumb">-->
			<!--  <li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>-->
			<!--  <li class="active">Export Result</li>-->
			<!--</ol>-->

		</h1>
	</section>
	<!-- Main content -->
	<section class="content">
		<!-- <pre><?php print_r($arr); ?></pre> -->
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<table class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width:98%;">
						<tr>
							<th><?php echo _("Sample Collection Date");?></th>
							<td>
								<input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="Select Collection Date" readonly style="width:220px;background:#fff;" />
							</td>
							<th><?php echo _("Facility Name");?></th>
							<td>
								<select class="form-control" id="facilityName" name="facilityName" title="Please select facility name" multiple="multiple" style="width:220px;">
									<?= $facilitiesDropdown; ?>
								</select>
							</td>
							<th><?php echo _("Testing Lab");?></th>
							<td>
								<select class="form-control" id="testingLab" name="testingLab" title="Please select vl lab" style="width:220px;">
									<?= $testingLabsDropdown; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><?php echo _("Sample Test Date");?></th>
							<td>
								<input type="text" id="sampleTestDate" name="sampleTestDate" class="form-control" placeholder="Select Sample Test Date" readonly style="width:220px;background:#fff;" />
							</td>

							<th><?php echo _("HCV VL Result");?> </th>
							<td>
								<select class="form-control" id="hcvVLoad" name="hcvVLoad" title="Please select batch code" style="width:220px;">
									<option value=""> -- Select -- </option>
									<?php foreach ($hepatitisResults as $hepatitisResultsKey => $hepatitisResultsValue) { ?>
										<option value="<?php echo $hepatitisResultsKey; ?>"> <?php echo $hepatitisResultsValue; ?> </option>
									<?php } ?>
								</select>
							</td>
							<th><?php echo _("HBV VL Result");?> </th>
							<td>
								<select class="form-control" id="hbvVLoad" name="hbvVLoad" title="Please select batch code" style="width:220px;">
									<option value=""> -- Select -- </option>
									<?php foreach ($hepatitisResults as $hepatitisResultsKey => $hepatitisResultsValue) { ?>
										<option value="<?php echo $hepatitisResultsKey; ?>"> <?php echo $hepatitisResultsValue; ?> </option>
									<?php } ?>
								</select>
							</td>
						</tr>
						<tr>
							<th><?php echo _("Status");?></th>
							<td>
								<select name="status" id="status" class="form-control" title="Please choose status">
									<option value=""> <?php echo _("-- Select --");?> </option>
									<option value="7"><?php echo _("Accepted");?></option>
									<option value="4"><?php echo _("Rejected");?></option>
								</select>
							</td>

							<th><?php echo _("Funding Sources");?></th>
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
							<th><?php echo _("Implementing Partners");?></th>
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
							<th><?php echo _("Last Print Date");?></th>
							<td>
								<input type="text" id="printDate" name="printDate" class="form-control" placeholder="Select Print Date" readonly style="width:220px;background:#fff;" />
							</td>
							<td colspan="6">
								&nbsp;<button onclick="searchVlRequestData();" value="Search" class="btn btn-primary btn-sm"><span><?php echo _("Search");?></span></button>

								&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Clear Search");?></span></button>

								&nbsp;<button class="btn btn-success" type="button" onclick="exportInexcel('<?php echo $reportType; ?>')"><i class="fa fa-cloud-download" aria-hidden="true"></i> <?php echo _("Download");?></button>

								&nbsp;<button class="btn btn-default pull-right" onclick="$('#showhide').fadeToggle();return false;"><span><?php echo _("Manage Columns");?></span></button>
							</td>
						</tr>

					</table>
					<span style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;" id="showhide" class="">
						<div class="row" style="background:#e0e0e0;padding: 15px;margin-top: -25px;">
							<div class="col-md-12">
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="0" id="iCol0" data-showhide="sample_code" class="showhideCheckBox" /> <label for="iCol0"><?php echo _("Sample Code");?></label>
								</div>
								<?php $i = 0;
								if ($sarr['sc_user_type'] != 'standalone') {
									$i = 1; ?>
									<div class="col-md-3">
										<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i; ?>" id="iCol<?php echo $i; ?>" data-showhide="remote_sample_code" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Remote Sample Code");?></label>
									</div>
								<?php } ?>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="batch_code" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Batch Code");?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="patient_id" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Patient ID");?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="patient_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Patient Name");?></label> <br>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="facility_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Facility Name");?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="result" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Result");?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="status_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Status");?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="funding_source" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Funding Source");?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="implementing_partner" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Implementing Partner");?></label>
								</div>
							</div>
						</div>
					</span>

					<!-- /.box-header -->
					<div class="box-body">
						<table id="vlRequestDataTable" class="table table-bordered table-striped">
							<thead>
								<tr>
									<th><?php echo _("Sample Code");?></th>
									<?php if ($sarr['sc_user_type'] != 'standalone') { ?>
										<th><?php echo _("Remote Sample");?> <br /><?php echo _("Code");?></th>
									<?php } ?>
									<th><?php echo _("Batch Code");?></th>
									<th><?php echo _("Patient ID");?></th>
									<th><?php echo _("Patient Name");?></th>
									<th><?php echo _("Facility Name");?></th>
									<th><?php echo _("HCV VL Result");?></th>
									<th><?php echo _("HBV VL Result");?></th>
									<th><?php echo _("Status");?></th>
									<th><?php echo _("Funding Source");?></th>
									<th><?php echo _("Implementing Partner");?></th>
									<th><?php echo _("Action");?></th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="12" class="dataTables_empty"><?php echo _("Loading data from server");?></td>
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
					"sClass": "center",
					"bSortable": false
				},
			],
			"aaSorting": [
				[0, "asc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/hepatitis/management/get-data-export.php",
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
					"name": "testingLab",
					"value": $("#testingLab").val()
				});
				aoData.push({
					"name": "hcvVLoad",
					"value": $("#hcvVLoad").val()
				});
				aoData.push({
					"name": "hbvVLoad",
					"value": $("#hbvVLoad").val()
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
		$path = '/hepatitis/results/generate-result-pdf.php';
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
				HCV_Viral_Load: $("#hcvVLoad  option:selected").text(),
				HBV_Viral_Load: $("#hbvVLoad  option:selected").text(),
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