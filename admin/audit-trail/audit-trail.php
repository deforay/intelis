<?php
$title = _("Audit Trail Details");
include_once(APPLICATION_PATH . '/header.php');

$general = new \Vlsm\Models\General();
?>
<style>
	.select2-selection__choice {
		color: black !important;
	}

	th {
		display: revert !important;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><i class="fa fa-edit"></i> <?php echo _("Audit Trail Details"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa fa-dashboard"></i> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Audit Trail"); ?></li>
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
								<input type="text" id="dateRange" name="dateRange" class="form-control" placeholder="<?php echo _('Select Collection Date'); ?>" readonly style="background:#fff;" />
							</td>
							<td><b><?php echo _("Batch Code"); ?> :</b></td>
							<td>
								<select class="form-control" id="batchCode" name="batchCode" title="<?php echo _('Please select batch code'); ?>">
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
							<td><b><?php echo _("Req. Sample Type"); ?> :</b></td>
							<td>
								<select class="form-control" id="requestSampleType" name="requestSampleType" title="<?php echo _('Please select request sample type'); ?>">
									<option value=""><?php echo _("All"); ?></option>
									<option value="result"><?php echo _("Sample With Result"); ?></option>
									<option value="noresult"><?php echo _("Sample Without Result"); ?></option>
								</select>
							</td>

						</tr>
						<tr>
							<td><b><?php echo _("Facility Name"); ?>:</b></td>
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
							<td><b><?php echo _("Patient ID"); ?></b></td>
							<td>
								<input type="text" id="patientId" name="patientId" class="form-control" placeholder="<?php echo _('Patient ID'); ?>" title="<?php echo _('Please enter the patient ID to search'); ?>" />
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
						</tr>
						<tr>
							<td><b><?php echo _("Show only Reordered Samples"); ?>&nbsp;:</b></td>
							<td>
								<select name="showReordSample" id="showReordSample" class="form-control" title="<?php echo _('Please choose record sample'); ?>">
									<option value=""> <?php echo _("-- Select --"); ?> </option>
									<option value="yes"><?php echo _("Yes"); ?></option>
									<option value="no" selected="selected"><?php echo _("No"); ?></option>
								</select>
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
							<td colspan="2"><input type="button" onclick="searchVlRequestData();" value="<?php echo _("Search"); ?>" class="btn btn-default btn-sm">
								&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _("Reset"); ?></span></button>
								&nbsp;<button class="btn btn-danger btn-sm" onclick="hideAdvanceSearch('advanceFilter','filter');"><span><?php echo _("Hide Advanced Search"); ?></span></button>
							</td>
							<td colspan="4">
								<?php if (isset($_SESSION['privileges']) && in_array("covid-19-add-request.php", $_SESSION['privileges'])) { ?>
									<a style=" margin: 0px 5px; " href="/covid-19/requests/covid-19-add-request.php" class="btn btn-primary btn-sm pull-right"> <i class="fa fa-plus"></i> <?php echo _("Add new Covid-19 Request"); ?></a>
									<?php if ($arr['vl_form'] == 1 && $_SESSION['instanceType'] != 'remoteuser') { ?>
										<a style=" margin: 0px 5px; " href="/covid-19/requests/covid-19-bulk-import-request.php" class="btn btn-primary btn-sm pull-right"> <i class="fa fa-plus"></i> <?php echo _("Bulk Import Covid-19 Request"); ?></a>
									<?php }
									if ($formId == 1 && $_SESSION['instanceType'] != 'remoteuser') { ?>
										<a style=" margin: 0px 5px; " href="/covid-19/requests/covid-19-quick-add.php" class="btn btn-primary btn-sm pull-right"> <i class="fa fa-plus"></i> <?php echo _("Quick Add Covid-19 Request"); ?></a>
								<?php }
								} ?>
							</td>
						</tr>
					</table>
					<table id="filter" class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;">
						<tr id="">
							<td>

								<?php
								if (isset($_SESSION['privileges']) && in_array("covid-19-add-request.php", $_SESSION['privileges'])) { ?>
									<?php if ($formId == 1 && $_SESSION['instanceType'] != 'remoteuser') { ?>
										<a style=" margin: 0px 5px; " href="/covid-19/requests/covid-19-quick-add.php" class="btn btn-primary btn-sm pull-right"> <i class="fa fa-plus"></i> Quick Add Covid-19 Request</a>
									<?php } ?>

									<a style=" margin: 0px 5px; " href="/covid-19/requests/covid-19-add-request.php" class="btn btn-primary btn-sm pull-right"> <i class="fa fa-plus"></i> Add new Covid-19 Request</a>
									<?php if ($arr['vl_form'] == 1 && $_SESSION['instanceType'] != 'remoteuser') { ?>
										<a style=" margin: 0px 5px; " href="/covid-19/requests/covid-19-bulk-import-request.php" class="btn btn-primary btn-sm pull-right"> <i class="fa fa-plus"></i> Bulk Import Covid-19 Request</a>
								<?php }
								} ?>
								<button style=" margin: 0px 5px; " class="btn btn-primary btn-sm pull-right" style="margin-right:5px;" onclick="hideAdvanceSearch('filter','advanceFilter');"><span>Show Advanced Search</span></button>
							</td>
						</tr>
					</table>

					<!-- /.box-header -->
					<div class="box-body">
						<table id="vlRequestDataTable" class="table table-bordered table-striped">
							<thead>
								<tr>
									<th><?php echo _("Statement"); ?></th>
									<th><?php echo _("Type"); ?></th>
									<th><?php echo _("Resource"); ?></th>
									<th><?php echo _("Created On"); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="15" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
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
			placeholder: "<?php echo _("Select Batch Code"); ?>"
		});
		loadVlRequestData();
		$('#dateRange').daterangepicker({
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
		$('#dateRange').val("");

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
			}, {
				"sClass": "center"
			}, {
				"sClass": "center"
			}, {
				"sClass": "center"
			}],
			"aaSorting": [3, "desc"],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/admin/audit-trail/get-audit-trail-list.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "dateRange",
					"value": $("#dateRange").val()
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
		var remoteUrl = '<?php echo $systemConfig['remoteURL']; ?>';

		function forceResultSync(sampleCode) {
			$.blockUI({
				message: "<h3><?php echo _("Trying to sync"); ?> " + sampleCode + "<br><?php echo _("Please wait"); ?>...</h3>"
			});

			if (remoteSync && remoteUrl != null && remoteUrl != '') {
				var jqxhr = $.ajax({
						url: "/remote/scheduled-jobs/syncResults.php?sampleCode=" + sampleCode + "&forceSyncModule=covid19",
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

	function exportAllPendingVlRequest() {
		$.blockUI();
		var requestSampleType = $('#requestSampleType').val();
		$.post("generate-pending-covid19-request-excel.php", {
				reqSampleType: requestSampleType
			},
			function(data) {
				$.unblockUI();
				if (data === "" || data === null || data === undefined) {
					alert("<?php echo _("Unable to generate the excel file"); ?>");
				} else {
					location.href = '/temporary/' + data;
				}
			});
	}
</script>
<?php
include(APPLICATION_PATH . '/footer.php');
?>