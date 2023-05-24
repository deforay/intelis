<?php

$title = _("Manage Batch");


require_once APPLICATION_PATH . '/header.php';
$testTypeQuery = "SELECT * FROM r_test_types where test_status='active' ORDER BY test_standard_name ASC";
$testTypeResult = $db->rawQuery($testTypeQuery);
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-pen-to-square"></em> <?php echo _("Manage Batches"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Manage Batches"); ?></li>
		</ol>
	</section>
	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<div class="row">
						<div class="col-xs-4 col-md-4">
							<div class="form-group" style="margin-left:30px; margin-top:30px;">
								<label for="testType">Test Type</label>
								<select class="form-control" name="testType" id="testType" title="Please choose test type" style="width:100%;" onchange="getBatchForm()">
									<option value=""> -- Select -- </option>
									<?php foreach ($testTypeResult as $testType) { ?>
										<option value="<?php echo $testType['test_short_code']; ?>"><?php echo $testType['test_standard_name'] . ' (' . $testType['test_loinc_code'] . ')' ?></option>
									<?php } ?>
								</select>
							</div>
						</div>
					</div>
					<span style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;margin-left: 325px;" id="showhide" class="">
						<div class="row" style="background:#e0e0e0;padding: 15px;">
							<div class="col-md-12">
								<div class="col-md-4">
									<input type="checkbox" onclick="fnShowHide(this.value);" value="0" id="iCol0" data-showhide="batch_code" class="showhideCheckBox" /> <label for="iCol0"><?php echo _("Batch Code"); ?></label>
								</div>
								<div class="col-md-4">
									<input type="checkbox" onclick="fnShowHide(this.value);" value="1" id="iCol1" data-showhide="''" class="showhideCheckBox" /> <label for="iCol1"><?php echo _("No. Of Samples"); ?></label>
								</div>
								<div class="col-md-4">
									<input type="checkbox" onclick="fnShowHide(this.value);" value="2" id="iCol2" data-showhide="request_created_datetime" class="showhideCheckBox" /> <label for="iCol2"><?php echo _("Created On"); ?></label>
								</div>
								<div class="col-md-4">
									<input type="checkbox" onclick="fnShowHide(this.value);" value="3" id="iCol3" data-showhide="batch_status" class="showhideCheckBox" /> <label for="iCol3"><?php echo _("Status"); ?></label> <br>
								</div>
							</div>
						</div>
					</span>

					<div class="box-header with-border batchDiv" style="display:none;">
						<?php if (isset($_SESSION['privileges']) && in_array("addBatch.php", $_SESSION['privileges'])) { ?>
							<a href="#" class="btn btn-primary pull-right" onclick="addBatch()"> <em class="fa-solid fa-plus"></em> <?php echo _("Create New Batch"); ?></a>
						<?php } ?>
						<!--<button class="btn btn-primary pull-right" style="margin-right: 1%;" onclick="$('#showhide').fadeToggle();return false;"><span>Manage Columns</span></button>-->
					</div>
					<!-- /.box-header -->
					<div class="box-body batchDiv" style="display:none;">
						<table aria-describedby="table" id="batchCodeDataTable" class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<th scope="col"><?php echo _("Batch Code"); ?></th>
									<th scope="col"><?php echo _("No. of Samples"); ?></th>
									<th scope="col"><?php echo _("No. of Samples Tested"); ?></th>
									<th scope="col"><?php echo _("Last Tested Date"); ?></th>
									<th scope="col"><?php echo _("Created On"); ?></th>
									<?php if (isset($_SESSION['privileges']) && in_array("editBatch.php", $_SESSION['privileges'])) { ?>
										<th scope="col"><?php echo _("Action"); ?></th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="3" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
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
<script>
	var oTable = null;
	$(document).ready(function() {
		$("#testType").select2({
			placeholder: "<?php echo _("Select Test Type"); ?>"
		});

		$.blockUI();
		oTable = $('#batchCodeDataTable').dataTable({
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
				{
					"sClass": "center",
					"bSortable": false
				},
				{
					"sClass": "center",
					"bSortable": false
				},
				{
					"sClass": "center",
					"bSortable": false
				},
				{
					"sClass": "center"
				},
				<?php if (isset($_SESSION['privileges']) && in_array("editBatch.php", $_SESSION['privileges'])) { ?> {
						"sClass": "center",
						"bSortable": false
					},
				<?php } ?>
			],
			"aaSorting": [
				[4, "desc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "getBatchCodeDetails.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "type",
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
	});

	function generateBarcode(bId) {
		$.post("/generic-tests/requests/batch/generateBarcode.php", {
				id: bId,
				type: 'generic-tests'
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					alert("<?php echo _("Unable to generate barcode"); ?>");
				} else {
					window.open('/uploads/barcode/' + data, '_blank');
				}

			});
	}


	function deleteBatchCode(bId, batchCode) {
		var conf = confirm("<?php echo _("Are you sure you want to delete Batch"); ?> : " + batchCode + "?\n<?php echo _("This action cannot be undone"); ?>.");
		if (conf) {
			$.post("/generic-tests/requests/batch/deleteBatchCode.php", {
					id: bId,
					type: $("#testType").val(),
				},
				function(data) {
					if (data == 1) {
						alert("<?php echo _("Batch deleted"); ?>");
					} else {
						alert("<?php echo _("Something went wrong. Please try again!"); ?>");
					}
					oTable.fnDraw();
				});
		}
	}

	function getBatchForm() {
		$(".batchDiv").show();
		oTable.fnDraw();
	}

	function addBatch() {
		window.location.href = "addBatch.php?code=" + $("#testType").val();
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
