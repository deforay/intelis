<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;

$title = _("Hepatitis | View All Requests");
// echo "<pre>";
// var_dump($_SESSION['privileges']);die;

require_once APPLICATION_PATH . '/header.php';

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$healthFacilites = $facilitiesService->getHealthFacilities('hepatitis');
/* Global config data */
$arr = $general->getGlobalConfig();

$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");
$testingLabs = $facilitiesService->getTestingLabs('hepatitis');
$testingLabsDropdown = $general->generateSelectOptions($testingLabs, null, "-- Select --");
$formId = $general->getGlobalConfig('vl_form');
//Funding source list
$fundingSourceQry = "SELECT * FROM r_funding_sources WHERE funding_source_status='active' ORDER BY funding_source_name ASC";
$fundingSourceList = $db->query($fundingSourceQry);
//Implementing partner list
$implementingPartnerQry = "SELECT * FROM r_implementation_partners WHERE i_partner_status='active' ORDER BY i_partner_name ASC";
$implementingPartnerList = $db->query($implementingPartnerQry);
$batQuery = "SELECT batch_code FROM batch_details WHERE test_type ='hepatitis' AND batch_status='completed'";
$batResult = $db->rawQuery($batQuery);

// Src of alert req
$srcQuery = "SELECT DISTINCT source_of_request from form_hepatitis where source_of_request is not null AND source_of_request not like ''";
$srcResults = $db->rawQuery($srcQuery);
$srcOfReqList = [];
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
		<h1><em class="fa-solid fa-pen-to-square"></em><?php echo _("Hepatitis Test Requests"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Test Request"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<table aria-describedby="table" id="advanceFilter" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;display: none;">
						<tr>
							<td><strong><?php echo _("Sample Collection Date"); ?> :</strong></td>
							<td>
								<input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="<?php echo _('Select Collection Date'); ?>" readonly style="background:#fff;" />
							</td>
							<td><strong><?php echo _("Batch Code"); ?> :</strong></td>
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
							<td><strong><?php echo _("Req. Sample Type"); ?> :</strong></td>
							<td>
								<select class="form-control" id="requestSampleType" name="requestSampleType" title="<?php echo _('Please select request sample type'); ?>">
									<option value=""><?php echo _("All"); ?></option>
									<option value="result"><?php echo _("Sample With Result"); ?></option>
									<option value="noresult"><?php echo _("Sample Without Result"); ?></option>
								</select>
							</td>

						</tr>
						<tr>
							<td><strong><?php echo _("Facility Name"); ?> :</strong></td>
							<td>
								<select class="form-control" id="facilityName" name="facilityName" multiple="multiple" title="<?php echo _('Please select facility name'); ?>" style="width:100%;">
									<?= $facilitiesDropdown; ?>
								</select>
							</td>
							<td><strong><?php echo _("Province/State"); ?>&nbsp;:</strong></td>
							<td>
								<input type="text" id="state" name="state" class="form-control" placeholder="<?php echo _('Enter Province/State'); ?>" style="background:#fff;" onkeyup="loadVlRequestStateDistrict()" />
							</td>
							<td><strong><?php echo _("District/County"); ?> :</strong></td>
							<td>
								<input type="text" id="district" name="district" class="form-control" placeholder="<?php echo _('Enter District/County'); ?>" onkeyup="loadVlRequestStateDistrict()" />
							</td>
						</tr>
						<tr>
							<td><strong><?php echo _("Testing Lab"); ?> :</strong></td>
							<td>
								<select class="form-control" id="vlLab" name="vlLab" title="<?php echo _('Please select vl lab'); ?>" style="width:220px;">
									<?= $testingLabsDropdown; ?>
								</select>
							</td>
							<td><strong><?php echo _("Gender"); ?>&nbsp;:</strong></td>
							<td>
								<select name="gender" id="gender" class="form-control" title="<?php echo _('Please choose gender'); ?>" style="width:220px;" onchange="hideFemaleDetails(this.value)">
									<option value=""> <?php echo _("-- Select --"); ?> </option>
									<option value="male"><?php echo _("Male"); ?></option>
									<option value="female"><?php echo _("Female"); ?></option>
									<option value="not_recorded"><?php echo _("Not Recorded"); ?></option>
								</select>
							</td>
							<td><strong><?php echo _("Show only Reordered Samples"); ?>&nbsp;:</strong></td>
							<td>
								<select name="showReordSample" id="showReordSample" class="form-control" title="<?php echo _('Please choose record sample'); ?>">
									<option value=""> <?php echo _("-- Select --"); ?> </option>
									<option value="yes"><?php echo _("Yes"); ?></option>
									<option value="no"><?php echo _("No"); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<td><strong><?php echo _("Funding Sources"); ?>&nbsp;:</strong></td>
							<td>
								<select class="form-control" name="fundingSource" id="fundingSource" title="<?php echo _('Please choose funding source'); ?>">
									<option value=""> <?php echo _("-- Select --"); ?> </option>
									<?php
									foreach ($fundingSourceList as $fundingSource) {
									?>
										<option value="<?php echo base64_encode($fundingSource['funding_source_id']); ?>"><?= $fundingSource['funding_source_name']; ?></option>
									<?php } ?>
								</select>
							</td>
							<td><strong><?php echo _("Implementing Partners"); ?>&nbsp;:</strong></td>
							<td>
								<select class="form-control" name="implementingPartner" id="implementingPartner" title="<?php echo _('Please choose implementing partner'); ?>">
									<option value=""> <?php echo _("-- Select --"); ?> </option>
									<?php
									foreach ($implementingPartnerList as $implementingPartner) {
									?>
										<option value="<?php echo base64_encode($implementingPartner['i_partner_id']); ?>"><?= $implementingPartner['i_partner_name']; ?></option>
									<?php } ?>
								</select>
							</td>
							<td><strong><?php echo _("Source of Request"); ?> :</strong></td>
							<td>
								<select class="form-control" id="srcOfReq" name="srcOfReq" title="<?php echo _('Please select source of request'); ?>">
									<?= $general->generateSelectOptions($srcOfReqList, null, "--Select--"); ?>
								</select>
							</td>
						</tr>
						<tr>
							<td><strong><?php echo _("Select Sample Received Date At Lab"); ?> :</strong></td>
							<td>
								<input type="text" id="sampleReceivedDateAtLab" name="sampleReceivedDateAtLab" class="form-control" placeholder="<?php echo _('Select Sample Received Date At Lab'); ?>" readonly style="background:#fff;" />
							</td>
							<td><strong><?php echo _("Sample Tested Date"); ?> :</strong></td>
							<td>
								<input type="text" id="sampleTestedDate" name="sampleTestedDate" class="form-control" placeholder="<?php echo _('Select Tested Date'); ?>" readonly style="background:#fff;" />
							</td>
						</tr>
						<tr>
							<td colspan="2"><input type="button" onclick="searchVlRequestData();" value="<?php echo _("Search"); ?>" class="btn btn-default btn-sm">
								&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset"); ?></span></button>
								&nbsp;<button class="btn btn-danger btn-sm" onclick="hideAdvanceSearch('advanceFilter','filter');"><span><?php echo _("Hide Advanced Search Options"); ?></span></button>
							</td>
							<td colspan="4">
								<?php if (isset($_SESSION['privileges']) && in_array("hepatitis-add-request.php", $_SESSION['privileges'])) { ?>
									<a style=" margin: 0px 5px; " href="/hepatitis/requests/hepatitis-add-request.php" class="btn btn-primary btn-sm pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _("Add new Hepatitis Request"); ?></a>
								<?php } ?>
								<?php if (isset($_SESSION['privileges']) && in_array("export-hepatitis-requests.php", $_SESSION['privileges'])) { ?>
									<a class="btn btn-success btn-sm pull-right" href="javascript:void(0);" onclick="exportAllPendingHepatitisRequest();"><span><?php echo _("Export Requests"); ?></span></a>
								<?php } ?>
							</td>
						</tr>
					</table>
					<table aria-describedby="table" id="filter" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;">
						<tr id="">
							<td>
								<?php if (isset($_SESSION['privileges']) && in_array("hepatitis-add-request.php", $_SESSION['privileges'])) { ?>
									<a style=" margin: 0px 5px; " href="/hepatitis/requests/hepatitis-add-request.php" class="btn btn-primary btn-sm pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _("Add new Hepatitis Request"); ?></a>
								<?php }
								if (isset($_SESSION['privileges']) && in_array("export-hepatitis-requests.php", $_SESSION['privileges'])) { ?>
									<button style=" margin: 0px 5px; " class="btn btn-success btn-sm pull-right" style="margin-right:5px;" onclick="exportAllPendingHepatitisRequest();"><span> <?php echo _("Export Requests"); ?></span></button>
								<?php } ?>
								<button style=" margin: 0px 5px; " class="btn btn-primary btn-sm pull-right" style="margin-right:5px;" onclick="hideAdvanceSearch('filter','advanceFilter');"><span><?php echo _("Show Advanced Search Options"); ?></span></button>
							</td>
						</tr>
					</table>

					<table aria-describedby="table" id="filter" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;">
						<tr id="">
							<td>

								<?php
								if (isset($_SESSION['privileges']) && in_array("hepatitis-add-request.php", $_SESSION['privileges'])) { ?>
									
										<a style=" margin: 0px 5px; " href="javascript:receiveDhis2Data();" class="btn btn-success btn-sm pull-right"> <em class="fa-solid fa-download"></em> Receive Test Requests from DHIS2</a>
										<a style=" margin: 0px 5px; " href="javascript:sendDhis2Data();" class="btn btn-warning btn-sm pull-right"> <em class="fa-solid fa-upload"></em> Send Results to DHIS2</a>
								
								<?php } ?>

							</td>
						</tr>
					</table>

					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="vlRequestDataTable" class="table table-bordered table-striped" aria-hidden="true" >
							<thead>
								<tr>
									<!--<th><input type="checkbox" id="checkTestsData" onclick="toggleAllVisible()"/></th>-->
									<th><?php echo _("Sample Code"); ?></th>
									<?php if ($_SESSION['instanceType'] != 'standalone') { ?>
										<th><?php echo _("Remote Sample"); ?> <br /><?php echo _("Code"); ?></th>
									<?php } ?>
									<th><?php echo _("Sample Collection"); ?><br /> <?php echo _("Date"); ?></th>
									<th><?php echo _("Batch Code"); ?></th>
									<th scope="row"><?php echo _("Facility Name"); ?></th>
									<th><?php echo _("Patient ID"); ?></th>
									<th><?php echo _("Patient Name"); ?></th>
									<th><?php echo _("Province/State"); ?></th>
									<th><?php echo _("District/County"); ?></th>
									<th><?php echo _("HCV VL Count"); ?></th>
									<th><?php echo _("HBV VL Count"); ?></th>
									<th><?php echo _("Last Modified On"); ?></th>
									<th scope="row"><?php echo _("Status"); ?></th>
									<?php if (isset($_SESSION['privileges']) && (in_array("hepatitis-edit-request.php", $_SESSION['privileges'])) || (in_array("hepatitis-view-request.php", $_SESSION['privileges']))) { ?>
										<th><?php echo _("Action"); ?></th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="15" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
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
								<span id="selected_printer"><?php echo _("No printer selected"); ?>!</span>
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
			placeholder: "<?php echo _("Select Batch Code"); ?>"
		});
		loadVlRequestData();
		$('#sampleCollectionDate, #sampleReceivedDateAtLab, #sampleTestedDate').daterangepicker({
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
			"aoColumns": [{
					"sClass": "center"
				},
				<?php if ($_SESSION['instanceType'] != 'standalone') { ?> {
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
				<?php if (isset($_SESSION['privileges']) && (in_array("hepatitis-edit-request.php", $_SESSION['privileges'])) || (in_array("hepatitis-view-request.php", $_SESSION['privileges']))) { ?> {
						"sClass": "center",
						"bSortable": false
					},
				<?php } ?>
			],
			"aaSorting": [
				[<?php echo ($sarr['sc_user_type'] == 'remoteuser' || $sarr['sc_user_type'] == 'vluser') ? 11 : 10 ?>, "desc"]
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
			"sAjaxSource": "/hepatitis/requests/get-request-list.php",
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
					"value": 'dhis2'
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

	<?php if (isset($_SESSION['instanceType']) && $_SESSION['instanceType'] == 'vluser') { ?>
		var remoteUrl = '<?php echo SYSTEM_CONFIG['remoteURL']; ?>';

		function forceResultSync(sampleCode) {
			$.blockUI({
				message: "<h3><?php echo _("Trying to sync"); ?> " + sampleCode + "<br><?php echo _("Please wait"); ?>...</h3>"
			});

			if (remoteSync && remoteUrl != null && remoteUrl != '') {
				var jqxhr = $.ajax({
						url: "/scheduled-jobs/remote/resultsSync.php?sampleCode=" + sampleCode + "&forceSyncModule=hepatitis",
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

	function receiveDhis2Data() {
		if (!navigator.onLine) {
			alert('Please connect to internet to sync with DHIS2');
			return false;
		}


		$.blockUI({
			message: '<h3>Receiving test requests from DHIS2<br><img src="/assets/img/loading.gif" /></h3>'
		});
		var jqxhr = $.ajax({
				url: "/hepatitis/interop/dhis2/hepatitis-receive.php",
			})
			.done(function(data) {
				var response = JSON.parse(data);
				if (response.received > 0) {
					msg  = '<h3>No. of Records Received : ' + response.received + ' <br><img src="/assets/img/loading.gif" /></h3>'
				}else{
					msg =  '<h3>No Records received from DHIS2 for the selected date range</h3>'
				}
				setTimeout(function() {
						$.blockUI({
							message: msg
						});
					}, 2500);
				if (response.processed > 0) {
					setTimeout(function() {
						$.blockUI({
							message: '<h3>No. of Records Received : ' + response.received + ' </h3><h3>Successfully Processed : ' + response.processed + ' </h3>'
						});
					}, 6000);
				}
			})
			.fail(function() {
				$.unblockUI();
				alert("Unable to sync with DHIS2. Please contact technical team for assistance.");
			})
			.always(function() {
				setTimeout(function() {
					$.unblockUI();
					window.location.href = window.location;
				}, 9500);
			});
	}

	function sendDhis2Data() {
		if (!navigator.onLine) {
			alert('Please connect to internet to sync with DHIS2');
			return false;
		}

		$.blockUI({
			message: '<h3>Sending Test Results to DHIS2<br><img src="/assets/img/loading.gif" /></h3>'
		});
		var jqxhr = $.ajax({
				url: "/hepatitis/interop/dhis2/hepatitis-send.php",
			})
			.done(function(data) {
				var response = JSON.parse(data);
				if (response.processed > 0) {
					msg = '<h3>No. of Results Successfully Sent : ' + response.processed + ' </h3>';
				} else {
					msg = '<h3>All results already synced with DHIS2</h3>';
				}
				$.blockUI({
					message: msg
				});
			})
			.fail(function() {
				$.unblockUI();
				alert("Unable to sync with DHIS2. Please contact technical team for assistance.");
			})
			.always(function() {

				setTimeout(function() {
					$.unblockUI();
				}, 4500);

			});
	}

	function exportAllPendingHepatitisRequest() {
		// $.blockUI();
		$.post("/hepatitis/requests/generate-pending-hepatitis-request-excel.php", {
				reqSampleType: $('#requestSampleType').val()
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
</script>
<?php
include(APPLICATION_PATH . '/footer.php');
?>