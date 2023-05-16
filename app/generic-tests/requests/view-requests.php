<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GeoLocationsService;

$title = _("View All Requests");
$hidesrcofreq = false;
$dateRange = $labName = $srcOfReq = $srcStatus = null;
if (isset($_GET['id']) && !empty($_GET['id'])) {
	$params = explode("##", base64_decode($_GET['id']));
	$dateRange = $params[0];
	$labName = $params[1];
	$srcOfReq = $params[2];
	$srcStatus = $params[3];
	$hidesrcofreq = true;
}
$facilityId = null;
$labId = null;
if (isset($_GET['facilityId']) && $_GET['facilityId'] != "" && isset($_GET['labId']) && $_GET['labId'] != "") {
	$facilityId = base64_decode($_GET['facilityId']);
	$labId = base64_decode($_GET['labId']);
}

require_once APPLICATION_PATH . '/header.php';

$interopConfig = [];
if (file_exists(APPLICATION_PATH . '/../configs/config.interop.php')) {
	$interopConfig = require_once(APPLICATION_PATH . '/../configs/config.interop.php');
}


/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);
$state = $geolocationService->getProvinces("yes");
$healthFacilites = $facilitiesService->getHealthFacilities('vl');

$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, $facilityId, "-- Select --");
$testingLabs = $facilitiesService->getTestingLabs('vl');
$testingLabsDropdown = $general->generateSelectOptions($testingLabs, $labId, "-- Select --");

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
$srcQuery = "SELECT DISTINCT source_of_request from form_generic where source_of_request is not null AND source_of_request not like ''";
$srcResults = $db->rawQuery($srcQuery);
$srcOfReqList = [];
foreach ($srcResults as $list) {
	$srcOfReqList[$list['source_of_request']] = strtoupper($list['source_of_request']);
}

$testTypeQuery = "SELECT * FROM r_test_types where test_status='active' ORDER BY test_standard_name ASC";
$testTypeResult = $db->rawQuery($testTypeQuery);
?>
<style>
	.select2-selection__choice {
		color: black !important;
	}

	<?php if (isset($_GET['id']) && !empty($_GET['id'])) { ?>header {
		display: none;
	}

	.main-sidebar {
		z-index: -9;
	}

	.content-wrapper {
		margin-left: 0px;
	}

	<?php } ?>
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<?php if (!$hidesrcofreq) { ?>
		<!-- Content Header (Page header) -->
		<section class="content-header">
			<h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _("Laboratory Test Requests"); ?></h1>
			<ol class="breadcrumb">
				<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
				<li class="active"><?php echo _("Test Request"); ?></li>
			</ol>
		</section>
	<?php } ?>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:50%;margin-bottom: 0px;">
						<tr>
							<td><strong><?php echo _("Test Type"); ?>&nbsp;:</strong></td>
							<td>
								<select class="form-control" name="testType" id="testType" title="Please choose test type" style="width:100%;" onchange="getTestTypeForm()">
									<option value=""> -- Select -- </option>
									<?php foreach ($testTypeResult as $testType) { ?>
										<option value="<?php echo $testType['test_type_id'] ?>"><?php echo $testType['test_standard_name'] ?></option>
									<?php } ?>
								</select>
							</td>
							<td colspan="6">&nbsp;<input type="button" onclick="searchVlRequestData();" value="<?php echo _('Search'); ?>" class="btn btn-default btn-sm">
								&nbsp;<button class="btn btn-danger btn-sm" onclick="reset();"><span><?php echo _("Reset"); ?></span></button>
							</td>
						</tr>
					</table>
					<!-- /.box-header -->
					<div class="box-body">
					<table class="table" aria-hidden="true">
						<tr>
							<td><a class="btn btn-success btn-sm pull-right" style="margin-right:5px;" href="/generic-tests/requests/add-request.php"><em class="fa-solid fa-add"></em>&nbsp;&nbsp;Add Request</a></td>
						</tr>
					</table>
						<table aria-describedby="table" id="RequestDataTable" class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<!--<th><input type="checkbox" id="checkTestsData" onclick="toggleAllVisible()"/></th>-->
									<th><?php echo _("Sample Code"); ?></th>
									<?php if ($_SESSION['instanceType'] != 'standalone') { ?>
										<th><?php echo _("Remote Sample"); ?> <br />Code</th>
									<?php } ?>
									<th><?php echo _("Test Type"); ?></th>
									<th><?php echo _("Sample Collection"); ?><br /> <?php echo _("Date"); ?></th>
									<th><?php echo _("Batch Code"); ?></th>
									<th><?php echo _("Unique ART No"); ?></th>
									<th><?php echo _("Patient's Name"); ?></th>
									<th scope="row"><?php echo _("Testing Lab"); ?></th>
									<th scope="row"><?php echo _("Facility Name"); ?></th>
									<th><?php echo _("Province/State"); ?></th>
									<th><?php echo _("District/County"); ?></th>
									<th><?php echo _("Sample Type"); ?></th>
									<th><?php echo _("Result"); ?></th>
									<th><?php echo _("Last Modified Date"); ?></th>
									<th scope="row"><?php echo _("Status"); ?></th>
									<th><?php echo _("Action"); ?></th>
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
<script src="/assets/js/moment.min.js"></script>
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
	let searchExecuted = false;
	var startDate = "";
	var endDate = "";
	var selectedTests = [];
	var selectedTestsId = [];
	var oTable = null;
	$(document).ready(function() {
		<?php
		if (isset($_GET['barcode']) && $_GET['barcode'] == 'true') {
			echo "printBarcodeLabel('" . htmlspecialchars($_GET['s']) . "','" . htmlspecialchars($_GET['f']) . "');";
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
		loadRequestData();
		$('#sampleCollectionDate, #sampleReceivedDateAtLab, #sampleTestedDate, #printDate, #requestCreatedDatetime').daterangepicker({
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
		<?php if ((isset($_GET['daterange']) && !empty($_GET['daterange']) && isset($_GET['type']) && $_GET['type'] == 'rejection')) { ?>
			$('#sampleReceivedDateAtLab, #sampleTestedDate, #printDate, #requestCreatedDatetime').val("");
			$('#sampleCollectionDate').val('<?php echo $_GET['daterange']; ?>');
		<?php } else { ?>
			$('#sampleCollectionDate, #sampleReceivedDateAtLab, #sampleTestedDate, #printDate, #requestCreatedDatetime').val("");
		<?php } ?>
	});

	function loadRequestData() {
		$.blockUI();
		oTable = $('#RequestDataTable').dataTable({
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
				{
					"sClass": "center"
				},
				<?php if ($_SESSION['instanceType'] != 'standalone') { ?> {
						"sClass": "center"
					},
				<?php } ?> {
					"sClass": "center"
				},{
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
				{
					"sClass": "center",
					"bSortable": false
				},
			],
			"aaSorting": [
				[<?php echo ($_SESSION['instanceType'] ==  'remoteuser' || $_SESSION['instanceType'] ==  'vluser') ? 14 : 13 ?>, "desc"]
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
			"sAjaxSource": "get-request-details.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "testType",
					"value": $("#testType").val()
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
		if (searchExecuted === false) {
			searchVlRequestData();
		}
		$.blockUI();
		$.post("generatePendingVlRequestExcel.php", {
				reqSampleType: $('#requestSampleType').val(),
				patientInfo: $('#patientInfo').val(),
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
						url: "/scheduled-jobs/remote/resultsSync.php?sampleCode=" + sampleCode + "&forceSyncModule=vl",
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


	function getByProvince(provinceId) {
		$("#district").html('');
		$("#facilityName").html('');
		$("#vlLab").html('');
		$.post("/common/get-by-province-id.php", {
				provinceId: provinceId,
				districts: true,
				facilities: true,
				labs: true,
			},
			function(data) {
				Obj = $.parseJSON(data);
				$("#district").html(Obj['districts']);
				$("#facilityName").html(Obj['facilities']);
				$("#vlLab").html(Obj['labs']);
			});
	}

	function getByDistrict(districtId) {
		$("#facilityName").html('');
		$("#vlLab").html('');
		$.post("/common/get-by-district-id.php", {
				districtId: districtId,
				facilities: true,
				labs: true,
			},
			function(data) {
				Obj = $.parseJSON(data);
				$("#facilityName").html(Obj['facilities']);
				$("#vlLab").html(Obj['labs']);
			});
	}

	function reset() {
		window.location.reload();
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
