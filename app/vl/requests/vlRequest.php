<?php
$title = _("View All Requests");

require_once(APPLICATION_PATH . '/header.php');

$interopConfig = array();
if (file_exists(APPLICATION_PATH . '/../configs/config.interop.php')) {
	$interopConfig = require_once(APPLICATION_PATH . '/../configs/config.interop.php');
}


$general = new \Vlsm\Models\General();
$facilitiesDb = new \Vlsm\Models\Facilities();
$healthFacilites = $facilitiesDb->getHealthFacilities('vl');

$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");
$testingLabs = $facilitiesDb->getTestingLabs('vl');
$testingLabsDropdown = $general->generateSelectOptions($testingLabs, null, "-- Select --");

//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);
//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);

$sQuery = "SELECT * FROM r_vl_sample_type WHERE `status`='active'";
$sResult = $db->rawQuery($sQuery);

$batQuery = "SELECT batch_code FROM batch_details WHERE test_type = 'vl' AND batch_status='completed'";
$batResult = $db->rawQuery($batQuery);
// Src of alert req
$srcQuery = "SELECT DISTINCT source_of_request from form_vl where source_of_request is not null AND source_of_request not like ''";
$srcResults = $db->rawQuery($srcQuery);
$srcOfReqList = array();
foreach ($srcResults as $list) {
	$srcOfReqList[$list['source_of_request']] = strtoupper($list['source_of_request']);
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
		<h1><i class="fa-solid fa-pen-to-square"></i> <?php echo _("Viral Load Test Requests"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa-solid fa-chart-pie"></i> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Test Request"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<table id="advanceFilter" class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;display: none;">
						<tr>
							<td><b><?php echo _("Sample Collection Date"); ?> :</b></td>
							<td>
								<input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="<?php echo _('Select Collection Date'); ?>" readonly style="background:#fff;" />
							</td>
							<td><b><?php echo _("Batch Code"); ?> :</b></td>
							<td>
								<select class="form-control" id="batchCode" name="batchCode" title="<?php echo _('Please select batch code'); ?>">
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
							<td><b><?php echo _("Sample Type"); ?> :</b></td>
							<td>
								<select class="form-control" id="sampleType" name="sampleType" title="<?php echo _('Please select sample type'); ?>">
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
							<td><b><?php echo _("Facility Name"); ?> :</b></td>
							<td>
								<select class="form-control" id="facilityName" name="facilityName" multiple="multiple" title="<?php echo _('Please select facility name'); ?>" style="width:100%;">
									<?= $facilitiesDropdown; ?>
								</select>
							</td>
							<td><b><?php echo _("Province/State"); ?>&nbsp;:</b></td>
							<td>
								<input type="text" id="state" name="state" class="form-control" placeholder="<?php echo _('Enter Province/State'); ?>" style="background:#fff;" onkeyup="loadVlRequestStateDistrict()" />
							</td>
							<td><b><?php echo _("District/County"); ?> :</b></td>
							<td>
								<input type="text" id="district" name="district" class="form-control" placeholder="<?php echo _('Enter District/County'); ?>" onkeyup="loadVlRequestStateDistrict()" />
							</td>
						</tr>
						<tr>
							<td><b><?php echo _("Testing Lab"); ?> :</b></td>
							<td>
								<select class="form-control" id="vlLab" name="vlLab" title="<?php echo _('Please select vl lab'); ?>" style="width:220px;">
									<?= $testingLabsDropdown; ?>
								</select>
							</td>
							<td><b><?php echo _("Gender"); ?>&nbsp;:</b></td>
							<td>
								<select name="gender" id="gender" class="form-control" title="<?php echo _('Please choose gender'); ?>" style="width:220px;" onchange="hideFemaleDetails(this.value)">
									<option value=""> <?php echo _("-- Select --"); ?> </option>
									<option value="male"><?php echo _("Male"); ?></option>
									<option value="female"><?php echo _("Female"); ?></option>
									<option value="not_recorded"><?php echo _("Not Recorded"); ?></option>
								</select>
							</td>
							<td><b><?php echo _("Show only Reordered Samples"); ?>&nbsp;:</b></td>
							<td>
								<select name="showReordSample" id="showReordSample" class="form-control" title="Please choose record sample">
									<option value=""> -- Select -- </option>
									<option value="yes"><?php echo _("Yes"); ?></option>
									<option value="no"><?php echo _("No"); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<td colspan="2">
								<div class="col-md-12">
									<div class="col-md-6">
										<b><?php echo _("Pregnant"); ?>&nbsp;:</b>
										<select name="patientPregnant" id="patientPregnant" class="form-control" title="<?php echo _('Please choose pregnant option'); ?>">
											<option value=""> <?php echo _("-- Select --"); ?> </option>
											<option value="yes"><?php echo _("Yes"); ?></option>
											<option value="no"><?php echo _("No"); ?></option>
										</select>
									</div>
									<div class="col-md-6">
										<b><?php echo _("Breastfeeding"); ?>&nbsp;:</b>
										<select name="breastFeeding" id="breastFeeding" class="form-control" title="<?php echo _('Please choose pregnant option'); ?>">
											<option value=""> <?php echo _("-- Select --"); ?> </option>
											<option value="yes"><?php echo _("Yes"); ?></option>
											<option value="no"><?php echo _("No"); ?></option>
										</select>
									</div>
								</div>
							</td>
							<td><b><?php echo _("Funding Sources"); ?>&nbsp;:</b></td>
							<td>
								<select class="form-control" name="fundingSource" id="fundingSource" title="<?php echo _('Please choose funding source'); ?>">
									<option value=""> <?php echo _("-- Select --"); ?> </option>
									<?php
									foreach ($fundingSourceList as $fundingSource) {
									?>
										<option value="<?php echo base64_encode($fundingSource['funding_source_id']); ?>"><?php echo ucwords($fundingSource['funding_source_name']); ?></option>
									<?php } ?>
								</select>
							</td>
							<td><b><?php echo _("Implementing Partners"); ?>&nbsp;:</b></td>
							<td>
								<select class="form-control" name="implementingPartner" id="implementingPartner" title="<?php echo _('Please choose implementing partner'); ?>">
									<option value=""> <?php echo _("-- Select --"); ?> </option>
									<?php
									foreach ($implementingPartnerList as $implementingPartner) {
									?>
										<option value="<?php echo base64_encode($implementingPartner['i_partner_id']); ?>"><?php echo ucwords($implementingPartner['i_partner_name']); ?></option>
									<?php } ?>
								</select>
							</td>
						</tr>
						<tr>
							<td><b><?php echo _("Req. Sample Type"); ?> :</b></td>
							<td>
								<select class="form-control" id="requestSampleType" name="requestSampleType" title="<?php echo _('Please select request sample type'); ?>">
									<option value=""><?php echo _("All"); ?></option>
									<option value="result"><?php echo _("Sample With Result"); ?></option>
									<option value="noresult"><?php echo _("Sample Without Result"); ?></option>
								</select>
							</td>
							<td><b><?php echo _("Source of Request"); ?> :</b></td>
							<td>
								<select class="form-control" id="srcOfReq" name="srcOfReq" title="<?php echo _('Please select source of request'); ?>">
									<?= $general->generateSelectOptions($srcOfReqList, null, "--Select--"); ?>
								</select>
							</td>
							<td><b><?php echo _("Select Sample Received Date At Lab"); ?> :</b></td>
							<td>
								<input type="text" id="sampleReceivedDateAtLab" name="sampleReceivedDateAtLab" class="form-control" placeholder="<?php echo _('Select Sample Received Date At Lab'); ?>" readonly style="background:#fff;" />
							</td>
						</tr>
						<tr>
							<td><b><?php echo _("Sample Tested Date"); ?> :</b></td>
							<td>
								<input type="text" id="sampleTestedDate" name="sampleTestedDate" class="form-control" placeholder="<?php echo _('Select Tested Date'); ?>" readonly style="background:#fff;" />
							</td>
						</tr>
						<tr>
							<td colspan="2"><input type="button" onclick="searchVlRequestData();" value="<?php echo _('Search'); ?>" class="btn btn-default btn-sm">
								&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset"); ?></span></button>
								&nbsp;<button class="btn btn-danger btn-sm" onclick="hideAdvanceSearch('advanceFilter','filter');"><span><?php echo _("Hide Advanced Search Options"); ?></span></button>
							</td>
							<td colspan="4">
								<?php
								if (isset($_SESSION['privileges']) && in_array("addVlRequest.php", $_SESSION['privileges'])) { ?>
									<a href="addVlRequest.php" class="btn btn-primary btn-sm pull-right"> <i class="fa-solid fa-plus"></i> <?php echo _("Add VL Request Form"); ?></a>
								<?php }
								?>
								&nbsp;<button class="btn btn-primary btn-sm pull-right" style="margin-right:5px;" onclick="$('#showhide').fadeToggle();return false;"><span><?php echo _("Manage Columns"); ?></span></button>
								&nbsp;
								<?php if (isset($_SESSION['privileges']) && in_array("export-vl-requests.php", $_SESSION['privileges'])) { ?>
									<a class="btn btn-success btn-sm pull-right" style="margin-right:5px;" href="javascript:void(0);" onclick="exportAllPendingVlRequest();"><i class="fa-solid fa-file-excel"></i>&nbsp;&nbsp; <?php echo _("Export Excel"); ?></a>
								<?php } ?>
							</td>
						</tr>
					</table>
					<table id="filter" class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;">
						<tr id="">
							<td>

								<?php
								if (isset($_SESSION['privileges']) && in_array("addVlRequest.php", $_SESSION['privileges'])) { ?>
									<a href="addVlRequest.php" class="btn btn-primary btn-sm pull-right"> <i class="fa-solid fa-plus"></i> <?php echo _("Add VL Request Form"); ?></a>
								<?php }
								?>
								&nbsp;<button class="btn btn-primary btn-sm pull-right" style="margin-right:5px;" onclick="$('#showhide').fadeToggle();return false;"><span><?php echo _("Manage Columns"); ?></span></button>
								<?php if (isset($_SESSION['privileges']) && in_array("export-vl-requests.php", $_SESSION['privileges'])) { ?>
									&nbsp;<a class="btn btn-success btn-sm pull-right" style="margin-right:5px;" href="javascript:void(0);" onclick="exportAllPendingVlRequest();"><i class="fa-solid fa-file-excel"></i>&nbsp;&nbsp;<?php echo _("Export Excel"); ?></a>
								<?php } ?>

								<?php if (isset($interopConfig['FHIR']['url']) && !empty($interopConfig['FHIR']['url'])) { ?>
									&nbsp;<a class="btn btn-warning btn-sm pull-right" style="margin-right:5px;" href="javascript:void(0);" onclick="sendEMRDataToFHIR();"><i class="fa-solid fa-paper-plane"></i> <?php echo _("EMR/FHIR - SEND RESULTS"); ?></a>
									&nbsp;<a class="btn btn-warning btn-sm pull-right" style="margin-right:5px;" href="javascript:void(0);" onclick="receiveEMRDataFromFHIR();"><i class="fa-solid fa-download"></i> <?php echo _("EMR/FHIR - GET TESTS"); ?></a>
								<?php } ?>
								&nbsp;<button class="btn btn-primary btn-sm pull-right" style="margin-right:5px;" onclick="hideAdvanceSearch('filter','advanceFilter');"><span><?php echo _("Show Advanced Search Options"); ?></span></button>
							</td>
						</tr>
					</table>
					<span style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;" id="showhide" class="">
						<div class="row" style="background:#e0e0e0;float: right !important;padding: 15px;">
							<div class="col-md-12">
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="0" id="iCol0" data-showhide="sample_code" class="showhideCheckBox" /> <label for="iCol0"><?php echo _("Sample Code"); ?></label>
								</div>
								<?php $i = 0;
								if ($_SESSION['instanceType'] != 'standalone') {
									$i = 1; ?>
									<div class="col-md-3">
										<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i; ?>" id="iCol<?php echo $i; ?>" data-showhide="remote_sample_code" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Remote Sample Code"); ?></label>
									</div>
								<?php } ?>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="sample_collection_date" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Sample Collection Date"); ?></label> <br>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="batch_code" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Batch Code"); ?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="patient_art_no" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Art No"); ?></label> <br>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="patient_first_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Patient's Name"); ?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="facility_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Facility Name"); ?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="state" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Province/State"); ?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="district" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("District/County"); ?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="sample_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Sample Type"); ?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="result" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Result"); ?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="last_modified_datetime" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Last Modified Date"); ?></label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="status_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>"><?php echo _("Status"); ?></label>
								</div>
							</div>
						</div>
					</span>
					<!-- /.box-header -->
					<div class="box-body">
						<table id="vlRequestDataTable" class="table table-bordered table-striped">
							<thead>
								<tr>
									<!--<th><input type="checkbox" id="checkTestsData" onclick="toggleAllVisible()"/></th>-->
									<th><?php echo _("Sample Code"); ?></th>
									<?php if ($_SESSION['instanceType'] != 'standalone') { ?>
										<th><?php echo _("Remote Sample"); ?> <br />Code</th>
									<?php } ?>
									<th><?php echo _("Sample Collection"); ?><br /> <?php echo _("Date"); ?></th>
									<th><?php echo _("Batch Code"); ?></th>
									<th><?php echo _("Unique ART No"); ?></th>
									<th><?php echo _("Patient's Name"); ?></th>
									<th><?php echo _("Testing Lab"); ?></th>
									<th><?php echo _("Facility Name"); ?></th>
									<th><?php echo _("Province/State"); ?></th>
									<th><?php echo _("District/County"); ?></th>
									<th><?php echo _("Sample Type"); ?></th>
									<th><?php echo _("Result"); ?></th>
									<th><?php echo _("Last Modified Date"); ?></th>
									<th><?php echo _("Status"); ?></th>
									<?php if (isset($_SESSION['privileges']) && (in_array("editVlRequest.php", $_SESSION['privileges']))) { ?>
										<th><?php echo _("Action"); ?></th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="16" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
								</tr>
							</tbody>
						</table>
						<?php
						if (isset($global['bar_code_printing']) && $global['bar_code_printing'] == 'zebra-printer') {
						?>

							<div id="printer_data_loading" style="display:none"><span id="loading_message"><?php echo _("Loading Printer Details"); ?>...</span><br />
								<div class="progress" style="width:100%">
									<div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
									</div>
								</div>
							</div> <!-- /printer_data_loading -->
							<div id="printer_details" style="display:none">
								<span id="selected_printer"><?php echo _("No printer selected!"); ?></span>
								<button type="button" class="btn btn-success" onclick="changePrinter()"><?php echo _("Change/Retry"); ?></button>
							</div><br /> <!-- /printer_details -->
							<div id="printer_select" style="display:none">
								<?php echo _("Zebra Printer Options"); ?><br />
								<?php echo _("Printer"); ?>: <select id="printers"></select>
							</div> <!-- /printer_select -->

						<?php
						}
						?>

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
	var startDate = "";
	var endDate = "";
	var selectedTests = [];
	var selectedTestsId = [];
	var oTable = null;
	$(document).ready(function() {
		<?php
		if (isset($_GET['barcode']) && $_GET['barcode'] == 'true') {
			echo "printBarcodeLabel('" . $_GET['s'] . "','" . $_GET['f'] . "');";
		}
		?>
		$("#facilityName").select2({
			placeholder: "<?php echo _("Select Facilities"); ?>"
		});
		$("#vlLab").select2({
			placeholder: "<?php echo _("Select Vl Lab"); ?>"
		});
		$("#batchCode").select2({
			placeholder: "<?php echo _("Select Batch Code'"); ?>"
		});
		loadVlRequestData();
		$('#sampleCollectionDate, #sampleReceivedDateAtLab, #sampleTestedDate').daterangepicker({
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
		$('#sampleCollectionDate, #sampleReceivedDateAtLab, #sampleTestedDate').val("");

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
			"bRetrieve": true,
			"aoColumns": [
				//{"sClass":"center","bSortable":false},
				{
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
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"
				},
				<?php if (isset($_SESSION['privileges']) && (in_array("editVlRequest.php", $_SESSION['privileges']))) { ?> {
						"sClass": "center",
						"bSortable": false
					},
				<?php } ?>
			],
			"aaSorting": [
				[<?php echo ($sarr['sc_user_type'] == 'remoteuser' || $sarr['sc_user_type'] == 'vluser') ? 12 : 11 ?>, "desc"]
			],
			"fnDrawCallback": function() {
				var checkBoxes = document.getElementsByName("chk[]");
				len = checkBoxes.length;
				for (c = 0; c < len; c++) {
					if (jQuery.inArray(checkBoxes[c].id, selectedTestsId) != -1) {
						checkBoxes[c].setAttribute("checked", true);
					}
				}
			},
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "getVlRequestDetails.php",
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
					"name": "facilityName",
					"value": $("#facilityName").val()
				});
				aoData.push({
					"name": "sampleType",
					"value": $("#sampleType").val()
				});
				aoData.push({
					"name": "district",
					"value": $("#district").val()
				});
				aoData.push({
					"name": "vlLab",
					"value": $("#vlLab").val()
				});
				aoData.push({
					"name": "gender",
					"value": $("#gender").val()
				});
				aoData.push({
					"name": "showReordSample",
					"value": $("#showReordSample").val()
				});
				aoData.push({
					"name": "patientPregnant",
					"value": $("#patientPregnant").val()
				});
				aoData.push({
					"name": "breastFeeding",
					"value": $("#breastFeeding").val()
				});
				aoData.push({
					"name": "fundingSource",
					"value": $("#fundingSource").val()
				});
				aoData.push({
					"name": "implementingPartner",
					"value": $("#implementingPartner").val()
				});
				aoData.push({
					"name": "state",
					"value": $("#state").val()
				});
				aoData.push({
					"name": "reqSampleType",
					"value": $("#requestSampleType").val()
				});
				aoData.push({
					"name": "sampleReceivedDateAtLab",
					"value": $("#sampleReceivedDateAtLab").val()
				});
				aoData.push({
					"name": "sampleTestedDate",
					"value": $("#sampleTestedDate").val()
				});
				aoData.push({
					"name": "srcOfReq",
					"value": $("#srcOfReq").val()
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

	function loadVlRequestStateDistrict() {
		oTable.fnDraw();
	}

	function toggleTest(obj) {
		if ($(obj).is(':checked')) {
			if ($.inArray(obj.value, selectedTests) == -1) {
				selectedTests.push(obj.value);
				selectedTestsId.push(obj.id);
			}
		} else {
			selectedTests.splice($.inArray(obj.value, selectedTests), 1);
			selectedTestsId.splice($.inArray(obj.id, selectedTestsId), 1);
			$("#checkTestsData").attr("checked", false);
		}
		$("#checkedTests").val(selectedTests.join());
		if (selectedTests.length != 0) {
			$("#status").prop('disabled', false);
		} else {
			$("#status").prop('disabled', true);
		}
	}

	function toggleAllVisible() {
		//alert(tabStatus);
		$(".checkTests").each(function() {
			$(this).prop('checked', false);
			selectedTests.splice($.inArray(this.value, selectedTests), 1);
			selectedTestsId.splice($.inArray(this.id, selectedTestsId), 1);
			$("#status").prop('disabled', true);
		});
		if ($("#checkTestsData").is(':checked')) {
			$(".checkTests").each(function() {
				$(this).prop('checked', true);
				selectedTests.push(this.value);
				selectedTestsId.push(this.id);
			});
			$("#status").prop('disabled', false);
		} else {
			$(".checkTests").each(function() {
				$(this).prop('checked', false);
				selectedTests.splice($.inArray(this.value, selectedTests), 1);
				selectedTestsId.splice($.inArray(this.id, selectedTestsId), 1);
				$("#status").prop('disabled', true);
			});
		}
		$("#checkedTests").val(selectedTests.join());
	}

	function submitTestStatus() {
		var stValue = $("#status").val();
		var testIds = $("#checkedTests").val();
		if (stValue != '' && testIds != '') {
			conf = confirm("<?php echo _("Do you wish to change the test status ?"); ?>");
			if (conf) {
				$.post("/vl/results/updateTestStatus.php", {
						status: stValue,
						id: testIds,
						format: "html"
					},
					function(data) {
						if (data != "") {
							$("#checkedTests").val('');
							selectedTests = [];
							selectedTestsId = [];
							$("#checkTestsData").attr("checked", false);
							$("#status").val('');
							$("#status").prop('disabled', true);
							oTable.fnDraw();
							alert("<?php echo _("Updated successfully."); ?>");
						}
					});
			}
		} else {
			alert("<?php echo _("Please checked atleast one checkbox."); ?>");
		}
	}

	function exportAllPendingVlRequest() {
		$.blockUI();
		$.post("generatePendingVlRequestExcel.php", {
				reqSampleType: $('#requestSampleType').val(),
			},
			function(data) {
				$.unblockUI();
				if (data === "" || data === null || data === undefined) {
					alert("<?php echo _("Unable to generate the excel file"); ?>");
				} else {
					window.open('/download.php?f=' + data, '_blank');
				}
			});
	}


	function hideAdvanceSearch(hideId, showId) {
		$("#" + hideId).hide();
		$("#" + showId).show();
	}

	<?php if (isset($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'vluser') { ?>
		var remoteUrl = '<?php echo SYSTEM_CONFIG['remoteURL']; ?>';

		function forceResultSync(sampleCode) {
			$.blockUI({
				message: "<h3><?php echo _("Trying to sync"); ?> " + sampleCode + "<br><?php echo _("Please wait"); ?>...</h3>"
			});

			if (remoteSync && remoteUrl != null && remoteUrl != '') {
				var jqxhr = $.ajax({
						url: "/remote/scheduled-jobs/syncResults.php?sampleCode=" + sampleCode + "&forceSyncModule=vl",
					})
					.done(function(data) {
						//console.log(data);
						//alert( "success" );
					})
					.fail(function() {
						$.unblockUI();
					})
					.always(function() {
						oTable.fnDraw();
						$.unblockUI();
					});
			}
		}
	<?php } ?>

	function receiveEMRDataFromFHIR() {
		$.blockUI({
			message: "<h3><?php echo _("Trying to sync from EMR/FHIR"); ?> " + "<br><?php echo _("Please wait"); ?>...</h3>"
		});


		var jqxhr = $.ajax({
				url: "/vl/interop/fhir/vl-receive.php",
			})
			.done(function(data) {
				//console.log(data);
				//alert( "success" );
				$.unblockUI();
				//alert(data.processed + " records added from EMR/FHIR");
				alert("EMR/FHIR sync completed");
				if (data.error) {
					alert(data.error);
				}
				oTable.fnDraw();
				$.unblockUI();
			})
			.fail(function() {
				$.unblockUI();
			})
			.always(function() {
				oTable.fnDraw();
				$.unblockUI();
			});

	}

	function sendEMRDataToFHIR() {
		$.blockUI({
			message: "<h3><?php echo _("Trying to sync to EMR/FHIR"); ?> " + "<br><?php echo _("Please wait"); ?>...</h3>"
		});

		var jqxhr = $.ajax({
				url: "/vl/interop/fhir/vl-send.php",
			})
			.done(function(data) {
				//console.log(data);
				//alert( "success" );
				$.unblockUI();
				alert(data.processed + " records sent to EMR/FHIR");
				if (data.error) {
					alert(data.error);
				}
				oTable.fnDraw();
				$.unblockUI();
			})
			.fail(function() {
				$.unblockUI();
			})
			.always(function() {
				oTable.fnDraw();
				$.unblockUI();
			});

	}
</script>
<?php
require_once(APPLICATION_PATH . '/footer.php');
