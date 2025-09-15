<?php

use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GeoLocationsService;

$title = _translate("View All Requests");
$hidesrcofreq = false;
$dateRange = $labName = $srcOfReq = $srcStatus = null;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

if (!empty($_GET['id'])) {
	$params = explode("##", base64_decode((string) $_GET['id']));
	$dateRange = $params[0];
	$labName = $params[1];
	$srcOfReq = $params[2];
	$srcStatus = $params[3];
	$hidesrcofreq = true;
}
$facilityId = null;
$labId = null;
if (isset($_GET['facilityId']) && $_GET['facilityId'] != "" && isset($_GET['labId']) && $_GET['labId'] != "") {
	$facilityId = base64_decode((string) $_GET['facilityId']);
	$labId = base64_decode((string) $_GET['labId']);
}

require_once APPLICATION_PATH . '/header.php';

$interopConfig = [];
if (file_exists(APPLICATION_PATH . '/../configs/config.interop.php')) {
	$interopConfig = require_once(APPLICATION_PATH . '/../configs/config.interop.php');
}


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);
$state = $geolocationService->getProvinces("yes");
$healthFacilites = $facilitiesService->getHealthFacilities('generic-tests');

$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, $facilityId, "-- Select --");
$testingLabs = $facilitiesService->getTestingLabs('generic-tests');
$testingLabsDropdown = $general->generateSelectOptions($testingLabs, $labId, "-- Select --");

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();

$sQuery = "SELECT * FROM r_generic_sample_types WHERE `sample_type_status`='active'";
$sResult = $db->rawQuery($sQuery);


$sourceOfRequests = $general->getSourcesOfTestRequests('form_generic', asNameValuePair: true);
$srcOfReqList = [];
foreach ($sourceOfRequests as $value => $displayText) {
	$srcOfReqList[$value] = $displayText;
}

$testTypeQuery = "SELECT * FROM r_test_types where test_status='active' ORDER BY test_standard_name ASC";
$testTypeResult = $db->rawQuery($testTypeQuery);
?>
<style>
	.select2-selection__choice {
		color: black !important;
	}

	<?php if (!empty($_GET['id'])) { ?>header {
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
<link rel="stylesheet" type="text/css" href="/assets/css/tooltipster.bundle.min.css" />
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<?php if (!$hidesrcofreq) { ?>
		<!-- Content Header (Page header) -->
		<section class="content-header">
			<h1><em class="fa-solid fa-pen-to-square"></em>
				<?php echo _translate("Laboratory Test Requests"); ?>
			</h1>
			<ol class="breadcrumb">
				<li><a href="/"><em class="fa-solid fa-chart-pie"></em>
						<?php echo _translate("Home"); ?>
					</a></li>
				<li class="active">
					<?php echo _translate("Test Request"); ?>
				</li>
			</ol>
		</section>
	<?php } ?>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:100%;margin-bottom: 0px;">
						<tr>
							<td style="width: 10%;"><strong>
									<?php echo _translate("Test Type"); ?>&nbsp;:
								</strong></td>
							<td style="width: 50%;">
								<select class="form-control" name="testType" id="testType" title="Please choose test type" style="width:100%;">
									<option value=""> -- Select -- </option>
									<?php foreach ($testTypeResult as $testType) { ?>
										<option value="<?php echo $testType['test_type_id'] ?>" data-short="<?php echo $testType['test_short_code']; ?>"><?php echo $testType['test_standard_name'] . ' (' . $testType['test_loinc_code'] . ')' ?></option>
									<?php } ?>
								</select>
							</td>
							<td style="width: 15%;">&nbsp;<input type="button" onclick="searchVlRequestData();" value="<?= _translate('Search'); ?>" class="btn btn-default btn-sm">
								&nbsp;<button class="btn btn-danger btn-sm" onclick="reset();"><span>
										<?= _translate('Reset'); ?>
									</span></button>
							</td>
							<td style="width: 25%;"><a class="btn btn-success btn-sm" href="/generic-tests/requests/add-request.php"><em class="fa-solid fa-add"></em>&nbsp;&nbsp;Add Request</a><a class="btn btn-success btn-sm" href="javascript:void(0);" onclick="exportTestRequests();"><em class="fa-solid fa-cloud-arrow-down"></em>&nbsp;&nbsp;Export Requests</a></td>
						</tr>
					</table>
					<!-- /.box-header -->
					<div class="box-body">
						<table class="table pull-right" aria-hidden="true" style="margin-right:5px;">
						</table>
						<table aria-describedby="table" id="RequestDataTable" class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<!--<th><input type="checkbox" id="checkTestsData" onclick="toggleAllVisible()"/></th>-->
									<th>
										<?php echo _translate("Sample ID"); ?>
									</th>
									<?php if (!$general->isStandaloneInstance()) { ?>
										<th>
											<?php echo _translate("Remote Sample ID"); ?>
										</th>
									<?php } ?>
									<th>
										<?php echo _translate("Test Type"); ?>
									</th>
									<th>
										<?php echo _translate("Sample Collection Date"); ?>
									</th>
									<th>
										<?php echo _translate("Batch Code"); ?>
									</th>
									<th>
										<?php echo _translate("Unique ART No"); ?>
									</th>
									<th>
										<?php echo _translate("Patient's Name"); ?>
									</th>
									<th scope="row">
										<?php echo _translate("Testing Lab"); ?>
									</th>
									<th scope="row">
										<?php echo _translate("Facility Name"); ?>
									</th>
									<th>
										<?php echo _translate("Province/State"); ?>
									</th>
									<th>
										<?php echo _translate("District/County"); ?>
									</th>
									<th>
										<?php echo _translate("Sample Type"); ?>
									</th>
									<th>
										<?php echo _translate("Result"); ?>
									</th>
									<th>
										<?php echo _translate("Last Modified Date"); ?>
									</th>
									<th scope="row">
										<?php echo _translate("Status"); ?>
									</th>
									<th>
										<?php echo _translate("Action"); ?>
									</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="16" class="dataTables_empty">
										<?php echo _translate("Loading data from server"); ?>
									</td>
								</tr>
							</tbody>
						</table>
						<?php
						if (isset($global['bar_code_printing']) && $global['bar_code_printing'] == 'zebra-printer') {
						?>

							<div id="printer_data_loading" style="display:none"><span id="loading_message">
									<?php echo _translate("Loading Printer Details"); ?>...
								</span><br />
								<div class="progress" style="width:100%">
									<div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
									</div>
								</div>
							</div> <!-- /printer_data_loading -->
							<div id="printer_details" style="display:none">
								<span id="selected_printer">
									<?php echo _translate("No printer selected!"); ?>
								</span>
								<button type="button" class="btn btn-success" onclick="changePrinter()">
									<?php echo _translate("Change/Retry"); ?>
								</button>
							</div><br /> <!-- /printer_details -->
							<div id="printer_select" style="display:none">
								<?php echo _translate("Zebra Printer Options"); ?><br />
								<?php echo _translate("Printer"); ?>: <select id="printers"></select>
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
<script type="text/javascript" src="/assets/js/tooltipster.bundle.min.js"></script>

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
		<script src="/assets/js/zebra-browserprint.js?v=<?= filemtime(WEB_ROOT . "/assets/js/zebra-browserprint.js") ?>"></script>
		<script src="/configs/zebra-format.js"></script>
		<script src="/assets/js/zebra-print.js?v=<?= filemtime(WEB_ROOT . "/assets/js/zebra-print.js") ?>"></script>
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
			               $sampleCode = htmlspecialchars($_GET['s']);
               $facilityCode = htmlspecialchars($_GET['f']);
               $patientID = htmlspecialchars($_GET['p']);
               echo "printBarcodeLabel('$sampleCode','$facilityCode','$patientID');";
		}
		?>
		$("#testType").select2({
			width: '100%',
			placeholder: "<?php echo _translate("Select Test Type"); ?>"
		});
		$("#facilityName").select2({
			placeholder: "<?php echo _translate("Select Facilities"); ?>"
		});
		$("#vlLab").select2({
			placeholder: "<?php echo _translate("Select Testing Lab"); ?>"
		});
		$("#batchCode").select2({
			placeholder: "<?php echo _translate("Select Batch Code"); ?>"
		});
		loadRequestData();
		$('#sampleCollectionDate, #sampleReceivedDateAtLab, #sampleTestedDate, #printDate, #requestCreatedDatetime').daterangepicker({
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
					'This Month': [moment().startOf('month'), moment().endOf('month')],
					'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
					'Last 30 Days': [moment().subtract(29, 'days'), moment()],
					'Last 90 Days': [moment().subtract(89, 'days'), moment()],
					'Last 120 Days': [moment().subtract(119, 'days'), moment()],
					'Last 180 Days': [moment().subtract(179, 'days'), moment()],
					'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')],
					'Previous Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
					'Current Year To Date': [moment().startOf('year'), moment()]
				}
			},
			function(start, end) {
				startDate = start.format('YYYY-MM-DD');
				endDate = end.format('YYYY-MM-DD');
			});
		<?php if ((!empty($_GET['daterange']) && isset($_GET['type']) && $_GET['type'] == 'rejection')) { ?>
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
			"aoColumns": [{
					"sClass": "center"
				},
				<?php if (!$general->isStandaloneInstance()) { ?> {
						"sClass": "center"
					},
				<?php } ?> {
					"sClass": "center"
				}, {
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
				[<?php echo ($general->isSTSInstance() || $general->isLISInstance()) ? 13 : 12 ?>, "desc"]
			],
			"fnDrawCallback": function() {
				var checkBoxes = document.getElementsByName("chk[]");
				len = checkBoxes.length;
				for (c = 0; c < len; c++) {
					if (jQuery.inArray(checkBoxes[c].id, selectedTestsId) != -1) {
						checkBoxes[c].setAttribute("checked", true);
					}
				}
				$('.top-tooltip').tooltipster({
					contentAsHTML: true
				});
			},
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/generic-tests/requests/get-request-list.php",
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
			conf = confirm("<?= _translate("Are you sure you want to modify the sample status?", true); ?>");
			if (conf) {
				$.post("/generic-tests/results/update-test-status.php", {
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
							alert("<?php echo _translate("Updated successfully."); ?>");
						}
					});
			}
		} else {
			alert("<?php echo _translate("Please checked atleast one checkbox."); ?>");
		}
	}

	function exportTestRequests() {
		if (searchExecuted === false) {
			searchVlRequestData();
		}
		$.blockUI();
		$.post("/generic-tests/requests/export-generic-tests-requests.php", {
				patientInfo: $('#patientInfo').val(),
			},
			function(data) {
				$.unblockUI();
				if (data === "" || data === null || data === undefined) {
					alert("<?php echo _translate("Unable to generate the export"); ?>");
				} else {
					window.open('/download.php?d=a&f=' + data, '_blank');
				}
			});
	}


	function hideAdvanceSearch(hideId, showId) {
		$("#" + hideId).hide();
		$("#" + showId).show();
	}

	<?php if ($general->isLISInstance()) { ?>
		let remoteURL = '<?php echo $general->getRemoteURL(); ?>';

		function forceResultSync(sampleCode) {
			$.blockUI({
				message: "<h3><?php echo _translate("Trying to sync"); ?> " + sampleCode + "<br><?php echo _translate("Please wait", true); ?>...</h3>"
			});

			if (remoteSync && remoteURL != null && remoteURL != '') {
				var jqxhr = $.ajax({
						url: "/tasks/remote/results-sender.php?sampleCode=" + sampleCode + "&forceSyncModule=vl",
					})
					.done(function(data) {
						////console.log(data);
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
