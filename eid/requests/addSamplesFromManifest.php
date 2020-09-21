<?php
$title = "Add Samples from Manifest";
#require_once('../../startup.php');
include_once(APPLICATION_PATH . '/header.php');

$tsQuery = "SELECT * FROM r_sample_status";
$tsResult = $db->rawQuery($tsQuery);
$configFormQuery = "SELECT * FROM global_config WHERE name ='vl_form'";
$configFormResult = $db->rawQuery($configFormQuery);
$sQuery = "SELECT * FROM r_eid_sample_type where status='active'";
$sResult = $db->rawQuery($sQuery);
$fQuery = "SELECT * FROM facility_details where status='active'";
$fResult = $db->rawQuery($fQuery);
$batQuery = "SELECT batch_code FROM batch_details where test_type = 'eid' AND batch_status='completed'";
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
		<h1><i class="fa fa-plus"></i> Add Samples from Manifest</h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
			<li class="active">EID Test Request</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<table class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;display: block;">
						<tr>
							<td style="width:20%;vertical-align:middle;"><b>Enter Sample Manifest Code :</b></td>
							<td>
								<input type="text" id="samplePackageCode" name="samplePackageCode" class="form-control" placeholder="Sample manifest code" title="Please enter the sample manifest code" style="background:#fff;" />
								<input type="hidden" id="sampleId" name="sampleId" />
							</td>
							<td>
								<button class="btn btn-primary btn-sm pull-right" style="margin-right:5px;" onclick="getSampleCode();return false;"><span>Submit</span></button>
							</td>
						</tr>
						<tr>
							<td style="width:100%;" colspan="3">
								<a class="btn btn-success btn-sm pull-right activateSample" style="display:none;margin-right:5px;" href="javascript:void(0);" onclick="activeSampleCode();"><i class="fa fa-fw fa-check-square-o" aria-hidden="true"></i> Activate Samples</a>
							</td>
						</tr>
					</table>
					<!-- /.box-header -->
					<div class="box-body table-responsive">
						<table id="eidManifestDataTable" class="table table-bordered table-striped table-vcenter">
							<thead>
								<tr>
									<th>Sample Code</th>
									<?php if ($sarr['user_type'] != 'standalone') { ?>
										<th>Remote Sample <br />Code</th>
									<?php } ?>
									<th>Sample Collection<br /> Date</th>
									<th>Batch Code</th>
									<th>Facility Name</th>
									<th>Child's ID</th>
									<th>Child's Name</th>
									<th>Mother's ID</th>
									<th>Mother's Name</th>
									<th>Province/State</th>
									<th>District/County</th>
									<th>Result</th>
									<th>Last Modified On</th>
									<th>Status</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="14" class="dataTables_empty" style="text-align:center;">Please enter the manifest code then submit!</td>
								</tr>
							</tbody>
						</table>
					</div>

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

<?php
if (isset($global['bar_code_printing']) && $global['bar_code_printing'] != "off") {
	if ($global['bar_code_printing'] == 'dymo-labelwriter-450') {
?>
		<script src="/assets/js/DYMO.Label.Framework.js"></script>
		<script src="/configs/dymo-format.js"></script>
		<script src="/assets/js/dymo-print.js"></script>
	<?php
	} else if ($global['bar_code_printing'] == 'zebra-printer') {
	?>
		<script src="/assets/js/zebra-browserprint.js.js"></script>
		<script src="/configs/zebra-format.js"></script>
		<script src="/assets/js/zebra-print.js"></script>
<?php
	}
}
?>

<script type="text/javascript">
	var oTable = null;

	function loadEIDRequestData() {
		$.blockUI();
		if (oTable) {
			$("#eidManifestDataTable").dataTable().fnDestroy();
		}
		oTable = $('#eidManifestDataTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ records per page"
			},
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			"bStateSave": true,
			//"bDestroy": true,
			"bRetrieve": true,
			"aoColumns": [{
					"sClass": "center"
				},
				<?php if ($sarr['user_type'] != 'standalone') { ?> {
						"sClass": "center"
					},
				<?php } ?> {
					"sClass": "center"
				}, {
					"sClass": "center"
				}, {
					"sClass": "center"
				}, {
					"sClass": "center"
				}, {
					"sClass": "center"
				}, {
					"sClass": "center"
				}, {
					"sClass": "center"
				}, {
					"sClass": "center"
				}, {
					"sClass": "center"
				}, {
					"sClass": "center"
				}, {
					"sClass": "center"
				}, {
					"sClass": "center"
				}
			],
			"aaSorting": [
				[<?php echo ($sarr['user_type'] == 'remoteuser' || $sarr['user_type'] == 'vluser') ? 11 : 10 ?>, "desc"]
			],
			"fnDrawCallback": function() {},
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/eid/requests/getManifestInGridHelper.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "samplePackageCode",
					"value": $("#samplePackageCode").val()
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

	function getSampleCode() {
		if ($("#samplePackageCode").val() != "") {
			$.blockUI();
			loadEIDRequestData();
			$.post("/eid/requests/getRemoteManifestHelper.php", {
					samplePackageCode: $("#samplePackageCode").val()
				},
				function(data) {
					$.unblockUI();
					if (data != "") {
						$('.activateSample').show(500);
						$('#sampleId').val(data);
					}
				});
		} else {
			alert('Please enter the Sample Manifest Code then hit submit');
		}
	}

	function activeSampleCode() {
		$.blockUI();
		$.post("/eid/requests/addSamplesByPackageHelper.php", {
				sampleId: $("#sampleId").val()
			},
			function(data) {
				if (data > 0) {
					alert('Samples from this Manifest have been activated');
					$('.activateSample').hide();
				}
				oTable.fnDraw();
				$.unblockUI();
			});
	}
</script>
<?php
include(APPLICATION_PATH . '/footer.php');
?>