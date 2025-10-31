<?php

use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GeoLocationsService;
use App\Services\UsersService;

$title = _translate("Covid-19 | View All Requests");

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$hidesrcofreq = false;
$dateRange = $labName = $srcOfReq = $srcStatus = null;
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

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);


/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);
$state = $geolocationService->getProvinces("yes");

$healthFacilites = $facilitiesService->getHealthFacilities('covid19');
/* Global config data */
$global = $general->getGlobalConfig();

$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, $facilityId, "-- Select --");
$testingLabs = $facilitiesService->getTestingLabs('covid19');
$testingLabsDropdown = $general->generateSelectOptions($testingLabs, $labId, "-- Select --");
$formId = (int) $general->getGlobalConfig('vl_form');

//Funding source list
$fundingSourceList = $general->getFundingSources();

//Implementing partner list
$implementingPartnerList = $general->getImplementationPartners();

$formId = (int) $general->getGlobalConfig('vl_form');

$sQuery = "SELECT * FROM r_covid19_sample_type WHERE `status`='active'";
$sResult = $db->rawQuery($sQuery);

$batQuery = "SELECT batch_code FROM batch_details WHERE test_type ='covid19' AND batch_status='completed'";
$batResult = $db->rawQuery($batQuery);

$sourceOfRequests = $general->getSourcesOfTestRequests('form_covid19', asNameValuePair: true);
$srcOfReqList = [];
foreach ($sourceOfRequests as $value => $displayText) {
	$srcOfReqList[$value] = $displayText;
}

?>
<style>
	.select2-selection__choice {
		color: black !important;
	}

	th {
		display: revert !important;
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
				<?php echo _translate("Covid-19 Test Requests"); ?>
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
					<table aria-describedby="table" id="advanceFilter" class="table pageFilters" aria-hidden="true" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;display: none;">
						<tr>
							<td><strong>
									<?php echo _translate("Sample Collection Date"); ?> :
								</strong></td>
							<td>
								<input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="<?php echo _translate('Select Collection Date'); ?>" readonly style="background:#fff;" />
							</td>
							<td><strong>
									<?php echo _translate("Select Sample Received Date At Lab"); ?> :
								</strong></td>
							<td>
								<input type="text" id="sampleReceivedDateAtLab" name="sampleReceivedDateAtLab" class="form-control" placeholder="<?php echo _translate('Select Sample Received Date At Lab'); ?>" readonly style="background:#fff;" />
							</td>
							<td><strong>
									<?php echo _translate("Show only Reordered Samples"); ?>&nbsp;:
								</strong></td>
							<td>
								<select name="showReordSample" id="showReordSample" class="form-control" title="<?php echo _translate('Please choose record sample'); ?>">
									<option value="">
										<?php echo _translate("-- Select --"); ?>
									</option>
									<option value="yes">
										<?php echo _translate("Yes"); ?>
									</option>
									<option value="no">
										<?php echo _translate("No"); ?>
									</option>
								</select>
							</td>


						</tr>
						<tr>

							<td><strong>
									<?php echo _translate("Sample Tested Date"); ?> :
								</strong></td>
							<td>
								<input type="text" id="sampleTestedDate" name="sampleTestedDate" class="form-control" placeholder="<?php echo _translate('Select Tested Date'); ?>" readonly style="background:#fff;" />
							</td>
							<td><strong>
									<?php echo _translate("Batch Code"); ?> :
								</strong></td>
							<td>
								<input type="text" id="batchCode" name="batchCode" class="form-control autocomplete" placeholder="<?php echo _translate('Enter Batch Code'); ?>" style="background:#fff;" />

							</td>
							<td><strong>
									<?php echo _translate("Funding Sources"); ?>&nbsp;:
								</strong></td>
							<td>
								<select class="form-control" name="fundingSource" id="fundingSource" title="<?php echo _translate('Please choose funding source'); ?>">
									<option value="">
										<?php echo _translate("-- Select --"); ?>
									</option>
									<?php
									foreach ($fundingSourceList as $fundingSource) {
									?>
										<option value="<?php echo base64_encode((string) $fundingSource['funding_source_id']); ?>">
											<?= $fundingSource['funding_source_name']; ?></option>
									<?php } ?>
								</select>
							</td>
						</tr>
						<tr>

							<td><strong>
									<?php echo _translate("Implementing Partners"); ?>&nbsp;:
								</strong></td>
							<td>
								<select class="form-control" name="implementingPartner" id="implementingPartner" title="<?php echo _translate('Please choose implementing partner'); ?>">
									<option value="">
										<?php echo _translate("-- Select --"); ?>
									</option>
									<?php
									foreach ($implementingPartnerList as $implementingPartner) {
									?>
										<option value="<?php echo base64_encode((string) $implementingPartner['i_partner_id']); ?>">
											<?= $implementingPartner['i_partner_name']; ?></option>
									<?php } ?>
								</select>
							</td>
							<td><strong>
									<?php echo _translate("Req. Sample Type"); ?> :
								</strong></td>
							<td>
								<select class="form-control" id="requestSampleType" name="requestSampleType" title="<?php echo _translate('Please select request sample type'); ?>">
									<option value="">
										<?php echo _translate("All"); ?>
									</option>
									<option value="result">
										<?php echo _translate("Sample With Result"); ?>
									</option>
									<option value="noresult">
										<?php echo _translate("Sample Without Result"); ?>
									</option>
								</select>
							</td>
							<td><strong>
									<?php echo _translate("Source of Request"); ?> :
								</strong></td>
							<td>
								<select class="form-control" id="srcOfReq" name="srcOfReq" title="<?php echo _translate('Please select source of request'); ?>">
									<?= $general->generateSelectOptions($srcOfReqList, null, "--Select--"); ?>
								</select>
							</td>
						</tr>
						<tr>

							<td><strong>
									<?php echo _translate("Sex"); ?>&nbsp;:
								</strong></td>
							<td>
								<select name="gender" id="gender" class="form-control" title="<?php echo _translate('Please select sex'); ?>" style="width:220px;">
									<option value="">
										<?php echo _translate("-- Select --"); ?>
									</option>
									<option value="male">
										<?php echo _translate("Male"); ?>
									</option>
									<option value="female">
										<?php echo _translate("Female"); ?>
									</option>
									<option value="unreported">
										<?php echo _translate("Unreported"); ?>
									</option>
								</select>
							</td>
							<td><strong>
									<?php echo _translate("Status"); ?>&nbsp;:
								</strong></td>
							<td>
								<select name="status" id="status" class="form-control" title="<?php echo _translate('Please choose status'); ?>" onchange="checkSampleCollectionDate();">
									<option value="" selected=selected><?php echo _translate("All Status"); ?></option>
									<option value="7">
										<?php echo _translate("Accepted"); ?>
									</option>
									<option value="4">
										<?php echo _translate("Rejected"); ?>
									</option>
									<option value="8">
										<?php echo _translate("Awaiting Approval"); ?>
									</option>
									<option value="6">
										<?php echo _translate("Registered At Testing Lab"); ?>
									</option>
									<option value="10">
										<?php echo _translate("Expired"); ?>
									</option>
								</select>
							</td>
							<td><strong>
									<?php echo _translate("Province/State"); ?>&nbsp;:
								</strong></td>
							<td>
								<select name="state" id="state" onchange="getByProvince(this.value)" class="form-control" title="<?php echo _translate('Please choose Province/State/Region'); ?>" onkeyup="searchVlRequestData()">
									<?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
								</select>
							</td>
						</tr>
						<tr>


							<td><strong>
									<?php echo _translate("District/County"); ?> :
								</strong></td>
							<td>
								<select class="form-control" id="district" onchange="getByDistrict(this.value)" name="district" title="<?php echo _translate('Please select Province/State'); ?>">
								</select>
							</td>
							<td><strong>
									<?php echo _translate("Facility Name"); ?>:
								</strong></td>
							<td>
								<select class="form-control" id="facilityName" name="facilityName" multiple="multiple" title="<?php echo _translate('Please select facility name'); ?>" style="width:100%;">
									<?= $facilitiesDropdown; ?>
								</select>
							</td>
							<td><strong>
									<?php echo _translate("Testing Lab"); ?> :
								</strong></td>
							<td>
								<select class="form-control" id="vlLab" name="vlLab" title="<?php echo _translate('Please select Testing Lab'); ?>" style="width:220px;">
									<?= $testingLabsDropdown; ?>
								</select>
							</td>
						</tr>
						<tr>

							<td><strong>
									<?php echo _translate("Export with Patient ID and Name"); ?>&nbsp;:
								</strong></td>
							<td>
								<select name="patientInfo" id="patientInfo" class="form-control" title="<?php echo _translate('Please choose community sample'); ?>" style="width:100%;">
									<option value="yes">
										<?php echo _translate("Yes"); ?>
									</option>
									<option value="no">
										<?php echo _translate("No"); ?>
									</option>
								</select>

							</td>
							<td><strong>
									<?php echo _translate("Patient ID"); ?>
								</strong></td>
							<td>
								<input type="text" id="patientId" name="patientId" class="form-control" placeholder="<?php echo _translate('Patient ID'); ?>" title="<?php echo _translate('Please enter the patient ID to search'); ?>" />
							</td>

							<td><strong>
									<?php echo _translate("Patient Name"); ?>&nbsp;:
								</strong></td>
							<td>
								<input type="text" id="patientName" name="patientName" class="form-control" placeholder="<?php echo _translate('Enter Patient Name'); ?>" style="background:#fff;" />
							</td>

						</tr>

						<tr>
							<td colspan="2"><input type="button" onclick="searchVlRequestData();" value="<?php echo _translate("Search"); ?>" class="btn btn-default btn-sm">
								&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>
										<?= _translate('Reset'); ?>
									</span></button>
								&nbsp;<button class="btn btn-danger btn-sm" onclick="hideAdvanceSearch('advanceFilter','filter');"><span>
										<?php echo _translate("Hide Advanced Search Options"); ?>
									</span></button>
							</td>
							<td colspan="4">
								<?php if (_isAllowed("/covid-19/requests/covid-19-add-request.php") && !$hidesrcofreq) { ?>
									<a style=" margin: 0px 5px; " href="/covid-19/requests/covid-19-add-request.php" class="btn btn-primary btn-sm pull-right"> <em class="fa-solid fa-plus"></em>
										<?php echo _translate("Add new Covid-19 Request"); ?>
									</a>
									<?php if ($global['vl_form'] == 1 && !$general->isSTSInstance() && !$hidesrcofreq) { ?>
										<a style=" margin: 0px 5px; " href="/covid-19/requests/covid-19-bulk-import-request.php" class="btn btn-primary btn-sm pull-right"> <em class="fa-solid fa-plus"></em>
											<?php echo _translate("Bulk Import Covid-19 Request"); ?>
										</a>
									<?php }
									if ($formId == COUNTRY\SOUTH_SUDAN && !$general->isSTSInstance() && !$hidesrcofreq) { ?>
										<a style=" margin: 0px 5px; " href="/covid-19/requests/covid-19-quick-add.php" class="btn btn-primary btn-sm pull-right"> <em class="fa-solid fa-plus"></em>
											<?php echo _translate("Quick Add Covid-19 Request"); ?>
										</a>
									<?php }
								}
								if (_isAllowed("/covid-19/requests/export-covid19-requests.php")) { ?>
									<a class="btn btn-success btn-sm" href="javascript:void(0);" style=" float: right; " onclick="exportAllCovid19Requests();"><span>
											<?php echo _translate("Export Requests"); ?>
										</span></a>
								<?php } ?>
							</td>
						</tr>
					</table>
					<table aria-describedby="table" id="filter" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;">
						<tr id="">
							<td>

								<?php
								if (_isAllowed("/covid-19/requests/covid-19-add-request.php") && !$hidesrcofreq) { ?>
									<?php if ($formId == COUNTRY\SOUTH_SUDAN && !$general->isSTSInstance()) { ?>
										<a style=" margin: 0px 5px; " href="/covid-19/requests/covid-19-quick-add.php" class="btn btn-primary btn-sm pull-right"> <em class="fa-solid fa-plus"></em>
											<?= _translate("Quick Add Covid-19 Request"); ?>
										</a>
									<?php } ?>

									<a style=" margin: 0px 5px; " href="/covid-19/requests/covid-19-add-request.php" class="btn btn-primary btn-sm pull-right"> <em class="fa-solid fa-plus"></em>
										<?= _translate("Add new Covid-19 Request"); ?>
									</a>
									<?php if ($global['vl_form'] == 1 && !$general->isSTSInstance()) { ?>
										<a style=" margin: 0px 5px; " href="/covid-19/requests/covid-19-bulk-import-request.php" class="btn btn-primary btn-sm pull-right"> <em class="fa-solid fa-plus"></em>
											<?= _translate("Bulk Import Covid-19 Requests"); ?>
										</a>
									<?php }
								}
								if (_isAllowed("/covid-19/requests/export-covid19-requests.php")) { ?>
									<button style=" margin: 0px 5px; " class="btn btn-success btn-sm pull-right" style="margin-right:5px;" onclick="exportAllCovid19Requests();"><span>
											<?= _translate("Export Requests"); ?>
										</span></button>
								<?php } ?>
								<button style=" margin: 0px 5px; " class="btn btn-primary btn-sm pull-right" style="margin-right:5px;" onclick="hideAdvanceSearch('filter','advanceFilter');"><span>
										<?= _translate("Show Advanced Search Options"); ?>
									</span></button>
							</td>
						</tr>
					</table>

					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="vlRequestDataTable" class="table table-bordered table-striped" aria-hidden="true">
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
										<?php echo _translate("Sample Collection Date"); ?>
									</th>
									<th>
										<?php echo _translate("Batch Code"); ?>
									</th>
									<th scope="row">
										<?php echo _translate("Testing Lab"); ?>
									</th>
									<th scope="row">
										<?php echo _translate("Facility Name"); ?>
									</th>
									<?php if ($formId == COUNTRY\SOUTH_SUDAN) { ?>
										<th>
											<?php echo _translate("Case ID"); ?>
										</th>
									<?php } else { ?>
										<th>
											<?php echo _translate("Patient ID"); ?>
										</th>
									<?php } ?>
									<th>
										<?php echo _translate("Patient Name"); ?>
									</th>
									<th>
										<?php echo _translate("Province/State"); ?>
									</th>
									<th>
										<?php echo _translate("District/County"); ?>
									</th>
									<th>
										<?php echo _translate("Result"); ?>
									</th>
									<th>
										<?php echo _translate("Last Modified On"); ?>
									</th>
									<th scope="row">
										<?php echo _translate("Status"); ?>
									</th>
									<?php if (((_isAllowed("/covid-19/requests/covid-19-edit-request.php")) || (_isAllowed("covid-19-view-request.php"))) && !$hidesrcofreq) { ?>
										<th>
											<?php echo _translate("Action"); ?>
										</th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="15" class="dataTables_empty">
										<?php echo _translate("Loading data from server"); ?>
									</td>
								</tr>
							</tbody>
						</table>
						<?php
						if (isset($global['bar_code_printing']) && $global['bar_code_printing'] == 'zebra-printer') {
						?>

							<div id="printer_data_loading" style="display:none"><span id="loading_message">Loading Printer
									Details...</span><br />
								<div class="progress" style="width:100%">
									<div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
									</div>
								</div>
							</div> <!-- /printer_data_loading -->
							<div id="printer_details" style="display:none">
								<span id="selected_printer">
									<?php echo _translate("No printer selected"); ?>!
								</span>
								<button type="button" class="btn btn-success" onclick="changePrinter()">
									<?php echo _translate("Change/Retry"); ?>
								</button>
							</div><br /> <!-- /printer_details -->
							<div id="printer_select" style="display:none">
								<?php echo _translate("Zebra Printer Options"); ?><br />
								<?php echo _translate("Printer:"); ?> <select id="printers"></select>
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
		<script src="/uploads/barcode-formats/dymo-format.js"></script>
		<script src="/assets/js/dymo-print.js"></script>
	<?php
	} else if ($global['bar_code_printing'] == 'zebra-printer') {
	?>
		<script src="/assets/js/zebra-browserprint.js?v=<?= filemtime(WEB_ROOT . "/assets/js/zebra-browserprint.js") ?>"></script>
		<script src="/uploads/barcode-formats/zebra-format.js?v=<?= filemtime(WEB_ROOT . "/uploads/barcode-formats/zebra-format.js") ?>"></script>
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

		$("#batchCode").autocomplete({
			source: function(request, response) {
				// Fetch data
				$.ajax({
					url: "/batch/getBatchCodeHelper.php",
					type: 'post',
					dataType: "json",
					data: {
						search: request.term,
						type: 'covid19'
					},
					success: function(data) {
						response(data);
					}

				});
			}
		});

		<?php
		if (isset($_GET['barcode']) && $_GET['barcode'] == 'true') {
			$sampleCode = htmlspecialchars($_GET['s']);
			$facilityCode = htmlspecialchars($_GET['f']);
			$patientID = htmlspecialchars($_GET['p']);
			echo "printBarcodeLabel('$sampleCode','$facilityCode','$patientID');";
		}
		?>
		$("#facilityName").select2({
			placeholder: "<?php echo _translate("Select Facilities"); ?>"
		});
		$("#vlLab").select2({
			placeholder: "<?php echo _translate("Select Testing Lab"); ?>"
		});
		loadVlRequestData();
		$('#sampleCollectionDate, #sampleReceivedDateAtLab, #sampleTestedDate').daterangepicker({
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

		$("#advanceFilter input, #advanceFilter select").on("change", function() {
			searchExecuted = false;
		});

	});

	function fnShowHide(iCol) {
		var bVis = oTable.fnSettings().aoColumns[iCol].bVisible;
		oTable.fnSetColumnVis(iCol, bVis ? false : true);
	}

	function loadVlRequestData() {
		$.blockUI();
		oTable = $('#vlRequestDataTable').dataTable({
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
				<?php if (!$general->isStandaloneInstance()) { ?> {
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
				},
				<?php if (((_isAllowed("/covid-19/requests/covid-19-edit-request.php")) || (_isAllowed("covid-19-view-request.php"))) && !$hidesrcofreq) { ?> {
						"sClass": "center action",
						"bSortable": false
					},
				<?php } ?>
			],
			"aaSorting": [
				[<?php echo ($general->isSTSInstance() || $general->isLISInstance()) ? 11 : 10 ?>, "desc"]
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
			"sAjaxSource": "/covid-19/requests/get-request-list.php",
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
					"name": "state",
					"value": $("#state").val()
				});
				aoData.push({
					"name": "reqSampleType",
					"value": $("#requestSampleType").val()
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
					"name": "patientId",
					"value": $("#patientId").val()
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
				aoData.push({
					"name": "patientName",
					"value": $("#patientName").val()
				});
				aoData.push({
					"name": "dateRangeModel",
					"value": '<?php echo $dateRange; ?>'
				});
				aoData.push({
					"name": "labIdModel",
					"value": '<?php echo $labName; ?>'
				});
				aoData.push({
					"name": "srcOfReqModel",
					"value": '<?php echo $srcOfReq; ?>'
				});
				aoData.push({
					"name": "srcStatus",
					"value": '<?php echo $srcStatus; ?>'
				});
				aoData.push({
					"name": "hidesrcofreq",
					"value": '<?php echo $hidesrcofreq; ?>'
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


	function hideAdvanceSearch(hideId, showId) {
		$("#" + hideId).hide();
		$("#" + showId).show();
	}

	<?php if ($general->isLISInstance()) { ?>
		let remoteURL = '<?= $general->getRemoteURL(); ?>';

		function forceResultSync(sampleCode) {
			$.blockUI({
				message: "<h3><?php echo _translate("Trying to sync"); ?> " + sampleCode + "<br><?php echo _translate("Please wait", true); ?>...</h3>"
			});

			if (remoteSync && remoteURL != null && remoteURL != '') {
				var jqxhr = $.ajax({
						url: "/tasks/remote/results-sender.php?sampleCode=" + sampleCode + "&forceSyncModule=covid19",
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

	function exportAllCovid19Requests() {
		if (searchExecuted === false) {
			searchVlRequestData();
		}
		$.blockUI();
		var requestSampleType = $('#requestSampleType').val();
		$.post("/covid-19/requests/export-covid19-requests.php", {
				reqSampleType: requestSampleType,
				patientInfo: $('#patientInfo').val(),
			},
			function(data) {
				$.unblockUI();
				if (data === "" || data === null || data === undefined) {
					alert("<?php echo _translate("Unable to generate the excel file"); ?>");
				} else {
					window.open('/download.php?f=' + data, '_blank');
				}
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
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
