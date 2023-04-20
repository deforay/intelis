<?php

use App\Models\Facilities;
use App\Models\General;

$title = "Covid-19 | DHIS2 Requests";
require_once(APPLICATION_PATH . '/header.php');

$general = new General();
$facilitiesDb = new Facilities();
$healthFacilites = $facilitiesDb->getHealthFacilities('covid19');
/* Global config data */
$arr = $general->getGlobalConfig();

$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");

$formId = $general->getGlobalConfig('vl_form');

$sQuery = "SELECT * FROM r_covid19_sample_type WHERE `status`='active'";
$sResult = $db->rawQuery($sQuery);

$batQuery = "SELECT batch_code FROM batch_details WHERE test_type ='covid19' AND batch_status='completed'";
$batResult = $db->rawQuery($batQuery);
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-pen-to-square"></em> Covid-19 Test Requests - DHIS2</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Test Request</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">

					<table id="filter" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;">
						<tr id="">
							<td>

								<?php
								if (isset($_SESSION['privileges']) && in_array("covid-19-add-request.php", $_SESSION['privileges'])) { ?>
									<?php if ($_SESSION['instanceType'] != 'remoteuser') { ?>
										<a style=" margin: 0px 5px; " href="javascript:receiveDhis2Data();" class="btn btn-success btn-sm pull-right"> <em class="fa-solid fa-download"></em> Receive Test Requests from DHIS2</a>
										<a style=" margin: 0px 5px; " href="javascript:sendDhis2Data();" class="btn btn-warning btn-sm pull-right"> <em class="fa-solid fa-upload"></em> Send Results to DHIS2</a>
								<?php }
								} ?>

							</td>
						</tr>
					</table>

					<!-- /.box-header -->
					<div class="box-body">
						<table id="vlRequestDataTable" class="table table-bordered table-striped" aria-hidden="true" >
							<thead>
								<tr>
									<!--<th><input type="checkbox" id="checkTestsData" onclick="toggleAllVisible()"/></th>-->
									<th>Sample Code</th>
									<?php if ($_SESSION['instanceType'] != 'standalone') { ?>
										<th>Remote Sample <br />Code</th>
									<?php } ?>
									<th>Sample Collection<br /> Date</th>
									<th>Batch Code</th>
									<th>Facility Name</th>
									<?php if ($formId == 1) { ?>
										<th>Case ID</th>
									<?php } else { ?>
										<th>Patient ID</th>
									<?php } ?>
									<th>Patient Name</th>
									<th>Province/State</th>
									<th>District/County</th>
									<th>Result</th>
									<th>Last Modified On</th>
									<th>Status</th>
									<?php if (isset($_SESSION['privileges']) && (in_array("covid-19-edit-request.php", $_SESSION['privileges'])) || (in_array("covid-19-view-request.php", $_SESSION['privileges']))) { ?>
										<th>Action</th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="15" class="dataTables_empty">Loading data from server</td>
								</tr>
							</tbody>
						</table>
						<?php
						if (isset($global['bar_code_printing']) && $global['bar_code_printing'] == 'zebra-printer') {
						?>

							<div id="printer_data_loading" style="display:none"><span id="loading_message">Loading Printer Details...</span><br />
								<div class="progress" style="width:100%">
									<div class="progress-bar progress-bar-striped active" role="progressbar" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100" style="width: 100%">
									</div>
								</div>
							</div> <!-- /printer_data_loading -->
							<div id="printer_details" style="display:none">
								<span id="selected_printer">No printer selected!</span>
								<button type="button" class="btn btn-success" onclick="changePrinter()">Change/Retry</button>
							</div><br /> <!-- /printer_details -->
							<div id="printer_select" style="display:none">
								Zebra Printer Options<br />
								Printer: <select id="printers"></select>
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
		<script src="/assets/js/zebra-browserprint.js"></script>
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


	function receiveDhis2Data() {
		if (!navigator.onLine) {
			alert('Please connect to internet to sync with DHIS2');
			return false;
		}


		$.blockUI({
			message: '<h3>Receiving test requests from DHIS2<br><img src="/assets/img/loading.gif" /></h3>'
		});
		var jqxhr = $.ajax({
				url: "/covid-19/interop/dhis2/covid-19-receive.php",
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
				url: "/covid-19/interop/dhis2/covid-19-send.php",
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

	$(document).ready(function() {
		<?php
		if (isset($_GET['barcode']) && $_GET['barcode'] == 'true') {
			echo "printBarcodeLabel('" . htmlspecialchars($_GET['s']) . "','" . htmlspecialchars($_GET['f']) . "');";
		}
		?>
		$("#facilityName").select2({
			placeholder: "Select Facilities"
		});
		loadVlRequestData();
		$('#sampleCollectionDate').daterangepicker({
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
					'This Month': [moment().startOf('month'), moment().endOf('month')],
					'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
				}
			},
			function(start, end) {
				startDate = start.format('YYYY-MM-DD');
				endDate = end.format('YYYY-MM-DD');
			});
		$('#sampleCollectionDate').val("");

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
				},
				<?php if (isset($_SESSION['privileges']) && (in_array("covid-19-edit-request.php", $_SESSION['privileges'])) || (in_array("covid-19-view-request.php", $_SESSION['privileges']))) { ?> {
						"sClass": "center",
						"bSortable": false
					},
				<?php } ?>
			],
			"aaSorting": [
				[<?php echo ($sarr['sc_user_type'] == 'remoteuser' || $sarr['sc_user_type'] == 'vluser') ? 10 : 9 ?>, "desc"]
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
			"sAjaxSource": "/covid-19/requests/get-request-list.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "source",
					"value": "dhis2"
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
</script>
<?php
require_once(APPLICATION_PATH . '/footer.php');
