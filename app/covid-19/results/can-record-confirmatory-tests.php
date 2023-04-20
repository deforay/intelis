<?php
$title = "Enter Covid-19 Result";

require_once(APPLICATION_PATH . '/header.php');
$tsQuery = "SELECT * FROM r_sample_status";
$tsResult = $db->rawQuery($tsQuery);
$configFormQuery = "SELECT * FROM global_config WHERE name ='vl_form'";
$configFormResult = $db->rawQuery($configFormQuery);
$sQuery = "SELECT * FROM r_covid19_sample_type where status='active'";
$sResult = $db->rawQuery($sQuery);
$fQuery = "SELECT * FROM facility_details where status='active'";
$fResult = $db->rawQuery($fQuery);
$batQuery = "SELECT batch_code FROM batch_details where test_type ='covid19' AND batch_status='completed'";
$batResult = $db->rawQuery($batQuery);
//check filters
$collectionDate = '';
$batchCode = '';
$sampleType = '';
$facilityName = array();
$gender = '';
$status = 'no_result';
$lastUrl1 = '';
$lastUrl2 = '';
if (isset($_SERVER['HTTP_REFERER'])) {
	$lastUrl1 = strpos($_SERVER['HTTP_REFERER'], "updateVlTestResult.php");
	$lastUrl2 = strpos($_SERVER['HTTP_REFERER'], "vlTestResult.php");
}
if ($lastUrl1 != '' || $lastUrl2 != '') {
	$collectionDate = (isset($_COOKIE['collectionDate']) && $_COOKIE['collectionDate'] != '') ? $_COOKIE['collectionDate'] : '';
	$batchCode = (isset($_COOKIE['batchCode']) && $_COOKIE['batchCode'] != '') ? $_COOKIE['batchCode'] : '';

	$facilityName = (isset($_COOKIE['facilityName']) && $_COOKIE['facilityName'] != '') ? explode(',', $_COOKIE['facilityName']) : array();

	$status = (isset($_COOKIE['status']) && $_COOKIE['status'] != '') ? $_COOKIE['status'] : '';
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
		<h1><em class="fa-solid fa-pen-to-square"></em> Record Confirmatory Tests</h1>
		<ol class="breadcrumb">
			<li><a href="/dashboard/index.php"><em class="fa-solid fa-chart-pie"></em> Home </a></li>
			<li class="active">Record Confirmatory Tests</li>
		</ol>
	</section>
	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<table class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;margin-bottom: 0px;">
						<tr>
							<td><strong>Sample Collection Date&nbsp;:</strong></td>
							<td>
								<input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control" placeholder="Select Collection Date" readonly style="width:220px;background:#fff;" value="<?php echo $collectionDate; ?>" />
							</td>
							<td>&nbsp;<strong>Batch Code&nbsp;:</strong></td>
							<td>
								<select class="form-control" id="batchCode" name="batchCode" title="Please select batch code" style="width:220px;">
									<option value=""> -- Select -- </option>
									<?php
									foreach ($batResult as $code) {
									?>
										<option value="<?php echo $code['batch_code']; ?>" <?php echo ($batchCode == $code['batch_code']) ? "selected='selected'" : "" ?>><?php echo $code['batch_code']; ?></option>
									<?php
									}
									?>
								</select>
							</td>


							<td><strong>Facility Name :</strong></td>
							<td>
								<select class="form-control" id="facilityName" name="facilityName" title="Please select facility name" multiple="multiple" style="width:220px;">
									<option value=""> -- Select -- </option>
									<?php
									foreach ($fResult as $name) {
									?>
										<option value="<?php echo $name['facility_id']; ?>" <?php echo (in_array($name['facility_id'], $facilityName)) ? "selected='selected'" : "" ?>><?php echo ($name['facility_name'] . "-" . $name['facility_code']); ?></option>
									<?php
									}
									?>
								</select>
							</td>
						</tr>
						<tr>
							<td colspan="6">&nbsp;<input type="button" onclick="searchVlRequestData();" value="Search" class="btn btn-default btn-sm">
								&nbsp;<button class="btn btn-danger btn-sm" onclick="reset();"><span>Reset</span></button>
								&nbsp;<button class="btn btn-primary btn-sm" onclick="$('#showhide').fadeToggle();return false;"><span>Manage Columns</span></button>
							</td>
						</tr>
					</table>
					<span style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;" id="showhide" class="">
						<div class="row" style="background:#e0e0e0;padding: 15px;margin-top: -5px;">
							<div class="col-md-12">
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);" value="0" id="iCol0" data-showhide="sample_code" class="showhideCheckBox" /> <label for="iCol0">Sample Code</label>
								</div>
								<?php $i = 0;
								if ($_SESSION['instanceType'] != 'standalone') {
									$i = 1; ?>
									<div class="col-md-3">
										<input type="checkbox" onclick="fnShowHide(this.value);" value="<?php echo $i; ?>" id="iCol<?php echo $i; ?>" data-showhide="remote_sample_code" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Remote Sample Code</label>
									</div>
								<?php } ?>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="batch_code" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Batch Code</label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="patient_art_no" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Art No</label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="patient_first_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Patient's Name</label> <br>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="facility_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Facility Name</label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="sample_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Sample Type</label> <br>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="result" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Result</label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="modified_on" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Modified On</label>
								</div>
								<div class="col-md-3">
									<input type="checkbox" onclick="fnShowHide(this.value);" value="<?php echo $i = $i + 1; ?>" id="iCol<?php echo $i; ?>" data-showhide="status_name" class="showhideCheckBox" /> <label for="iCol<?php echo $i; ?>">Status</label>
								</div>

							</div>
						</div>
					</span>
					<!-- /.box-header -->
					<div class="box-body">
						<div>
							<select name="status" id="status" class="form-control" title="Please choose result status" style="width:220px;margin-top:30px;" onchange="searchVlRequestData();">
								<option value=""> -- Select -- </option>
								<option value="no_result" <?php echo ($status == 'no_result') ? "selected='selected'" : "" ?>>Results Not Recorded</option>
								<option value="result" <?php echo ($status == 'result') ? "selected='selected'" : "" ?>>Results Recorded</option>
								<option value="reject" <?php echo ($status == 'reject') ? "selected='selected'" : "" ?>>Rejected Samples</option>
							</select>
						</div><br>
						<table id="vlRequestDataTable" class="table table-bordered table-striped" aria-hidden="true" >
							<thead>
								<tr>
									<th>Sample Code</th>
									<?php if ($_SESSION['instanceType'] != 'standalone') { ?>
										<th>Remote Sample <br />Code</th>
									<?php } ?>
									<th>Batch Code</th>
									<th>Facility Name</th>
									<th>Patient ID</th>
									<th>Patient Name</th>
									<th>Result</th>
									<th>Modified On</th>
									<th>Status</th>
									<th>Action</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="10" class="dataTables_empty">Loading data from server</td>
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
<script src="/assets/js/moment.min.js"></script>
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
		<?php
		if (!isset($_COOKIE['collectionDate']) || $_COOKIE['collectionDate'] == '') {
		?>
			$('#sampleCollectionDate').val("");
		<?php
		} else if (($lastUrl1 != '' || $lastUrl2 != '') && isset($_COOKIE['collectionDate'])) { ?>
			$('#sampleCollectionDate').val("<?= htmlspecialchars($_COOKIE['collectionDate']); ?>");
		<?php } ?>

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
					"sClass": "center",
					"bSortable": false
				},
				{
					"sClass": "center",
					"bSortable": false
				}
			],
			<?php if ($_SESSION['instanceType'] != 'standalone') { ?> "aaSorting": [
					[6, "desc"]
				],
			<?php } else { ?> "aaSorting": [
					[5, "desc"]
				],
			<?php } ?> "bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "get-record-confirmatory-tests.php",
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
					"name": "status",
					"value": $("#status").val()
				});

				aoData.push({
					"name": "from",
					"value": "enterresult"
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
		document.cookie = "collectionDate=" + $("#sampleCollectionDate").val();
		document.cookie = "batchCode=" + $("#batchCode").val();
		document.cookie = "sampleType=" + $("#sampleType").val();
		document.cookie = "facilityName=" + $("#facilityName").val();
		document.cookie = "gender=" + $("#gender").val();
		document.cookie = "status=" + $("#status").val();
		$.unblockUI();
	}



	function reset() {
		document.cookie = "collectionDate=";
		document.cookie = "batchCode=";
		document.cookie = "sampleType=";
		document.cookie = "facilityName=";
		document.cookie = "gender=";
		document.cookie = "status=";
		window.location.reload();
	}
</script>
<?php
require_once(APPLICATION_PATH . '/footer.php');
