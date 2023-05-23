	<?php

	require_once APPLICATION_PATH . '/header.php';
	// Sanitized values from $request object
	/** @var Laminas\Diactoros\ServerRequest $request */
	$request = $GLOBALS['request'];
	$_GET = $request->getQueryParams();
	$id = (isset($_GET['id'])) ? base64_decode($_GET['id']) : null;

	$date = base64_decode($_GET['d']);
	$tsQuery = "SELECT status_name FROM r_sample_status WHERE status_id = '" . $id . "'";
	$tsResult = $db->rawQuery($tsQuery);
	if (empty($tsResult)) {
		header("Location:/dashboard/index.php");
		exit;
	}
	$configFormQuery = "SELECT * FROM global_config WHERE name ='vl_form'";
	$configFormResult = $db->rawQuery($configFormQuery);
	$sQuery = "SELECT * FROM r_vl_sample_type WHERE status='active'";
	$sResult = $db->rawQuery($sQuery);
	$fQuery = "SELECT * FROM facility_details WHERE status='active'";
	$fResult = $db->rawQuery($fQuery);
	$batQuery = "SELECT batch_code FROM batch_details WHERE test_type ='vl' AND batch_status='completed'";
	$batResult = $db->rawQuery($batQuery);
	?>
	<!-- Content Wrapper. Contains page content -->
	<div class="content-wrapper">
		<!-- Content Header (Page header) -->
		<section class="content-header">
			<h1>VL Test Result Status [<?= ($tsResult[0]['status_name']); ?> ]</h1>
			<ol class="breadcrumb">
				<li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
				<li class="active">VL Test Result Status [<?= ($tsResult[0]['status_name']); ?> ]</li>
			</ol>
		</section>

		<!-- Main content -->
		<section class="content">
			<div class="row">
				<div class="col-xs-12">
					<div class="box">
						<table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;">
							<tr>
								<td><strong>Sample Collection Date&nbsp;:</strong></td>
								<td>
									<input type="text" id="sampleCollectionDate" value="<?php echo $date; ?>" name="sampleCollectionDate" class="form-control" placeholder="Select Collection Date" readonly style="width:220px;background:#fff;" />
								</td>
								<td>&nbsp;<strong>Batch Code&nbsp;:</strong></td>
								<td>
									<select class="form-control" id="batchCode" name="batchCode" title="Please select batch code">
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
							</tr>
							<tr>
								<td>&nbsp;<strong>Sample Type&nbsp;:</strong></td>
								<td>
									<select style="width:220px;" class="form-control" id="sampleType" name="sampleType" title="Please select sample type">
										<option value=""> -- Select -- </option>
										<?php
										foreach ($sResult as $type) {
										?>
											<option value="<?php echo $type['sample_id']; ?>"><?= $type['sample_name']; ?></option>
										<?php
										}
										?>
									</select>
								</td>

								<td>&nbsp;<strong>Facility Name & Code&nbsp;:</strong></td>
								<td>
									<select class="form-control" id="facilityName" name="facilityName" title="Please select facility name">
										<option value=""> -- Select -- </option>
										<?php
										foreach ($fResult as $name) {
										?>
											<option value="<?php echo $name['facility_id']; ?>"><?php echo ($name['facility_name'] . "-" . $name['facility_code']); ?></option>
										<?php
										}
										?>
									</select>
								</td>
							</tr>
							<tr>
								<td colspan="3">&nbsp;<input type="button" onclick="searchVlRequestData();" value="Search" class="btn btn-success btn-sm">
									&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset</span></button>
									&nbsp;<button class="btn btn-primary btn-sm" onclick="$('#showhide').fadeToggle();return false;"><span>Manage Columns</span></button>
								</td>
							</tr>
						</table>
						<span style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;" id="showhide" class="">
							<div class="row" style="background:#e0e0e0;float: right !important;padding: 15px;">
								<div class="col-md-12">
									<div class="col-md-3">
										<input type="checkbox" onclick="fnShowHide(this.value);" value="0" id="iCol0" data-showhide="vlsm_country_id" class="showhideCheckBox" /> <label for="iCol0">Sample Code</label>
									</div>
									<div class="col-md-3">
										<input type="checkbox" onclick="fnShowHide(this.value);" value="1" id="iCol1" data-showhide="sample_collection_date" class="showhideCheckBox" /> <label for="iCol1">Sample Collection Date</label>
									</div>
									<div class="col-md-3">
										<input type="checkbox" onclick="fnShowHide(this.value);" value="2" id="iCol2" data-showhide="batch_code" class="showhideCheckBox" /> <label for="iCol2">Batch Code</label> <br>
									</div>
									<div class="col-md-3">
										<input type="checkbox" onclick="fnShowHide(this.value);" value="3" id="iCol3" data-showhide="patient_art_no" class="showhideCheckBox" /> <label for="iCol3">Art No</label>
									</div>
									<div class="col-md-3">
										<input type="checkbox" onclick="fnShowHide(this.value);" value="4" id="iCol4" data-showhide="patient_first_name" class="showhideCheckBox" /> <label for="iCol4">Patient's Name</label> <br>
									</div>
									<div class="col-md-3">
										<input type="checkbox" onclick="fnShowHide(this.value);" value="5" id="iCol5" data-showhide="facility_name" class="showhideCheckBox" /> <label for="iCol5">Facility Name</label>
									</div>
									<div class="col-md-3">
										<input type="checkbox" onclick="fnShowHide(this.value);" value="6" id="iCol6" data-showhide="state" class="showhideCheckBox" /> <label for="iCol6">Province</label>
									</div>
									<div class="col-md-3">
										<input type="checkbox" onclick="fnShowHide(this.value);" value="7" id="iCol7" data-showhide="district" class="showhideCheckBox" /> <label for="iCol7">District</label>
									</div>
									<div class="col-md-3">
										<input type="checkbox" onclick="fnShowHide(this.value);" value="8" id="iCol8" data-showhide="sample_name" class="showhideCheckBox" /> <label for="iCol8">Sample Type</label>
									</div>
									<div class="col-md-3">
										<input type="checkbox" onclick="fnShowHide(this.value);" value="9" id="iCol9" data-showhide="result" class="showhideCheckBox" /> <label for="iCol9">Result</label>
									</div>
								</div>
							</div>
						</span>
						<!-- /.box-header -->
						<div class="box-body">
							<table aria-describedby="table" id="vlTestResultStatusDataTable" class="table table-bordered table-striped" aria-hidden="true">
								<thead>
									<tr>
										<th>Sample Code</th>
										<th>Sample Collection Date</th>
										<th>Batch Code</th>
										<th>Unique ART No</th>
										<th>Patient's Name</th>
										<th>Facility Name</th>
										<th>Province</th>
										<th>District</th>
										<th>Sample Type</th>
										<th>Result</th>
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
		var oTable = null;
		$(document).ready(function() {
			loadResultStatusData();
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
			<?php if (isset($date) && !empty($date)) { ?>
				$('#sampleCollectionDate').val('<?php echo $date; ?>');
			<?php } else { ?>
				$('#sampleCollectionDate').val("");
			<?php } ?>

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
			for (colNo = 0; colNo <= 9; colNo++) {
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

		function loadResultStatusData() {
			$.blockUI();
			oTable = $('#vlTestResultStatusDataTable').dataTable({
				"oLanguage": {
					"sLengthMenu": "_MENU_ records per page"
				},
				"bJQueryUI": false,
				"bAutoWidth": false,
				"bInfo": true,
				"bScrollCollapse": true,
				"bStateSave": true,
				"bRetrieve": true,
				"aoColumns": [{
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
					}
				],
				"bProcessing": true,
				"bServerSide": true,
				"sAjaxSource": "getVLTestResultStatusDetails.php",
				"fnServerData": function(sSource, aoData, fnCallback) {
					aoData.push({
						"name": "status",
						"value": "<?php echo $id; ?>"
					});
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
	require_once APPLICATION_PATH . '/footer.php';
	?>
