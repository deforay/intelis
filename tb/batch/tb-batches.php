<?php
ob_start();
$title = "TB | Batches";
#require_once('../../startup.php');
include_once(APPLICATION_PATH . '/header.php');

?>


<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><i class="fa fa-edit"></i> Manage Batches</h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
			<li class="active">Manage Batches</li>
		</ol>
	</section>
	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<span style="display: none;position:absolute;z-index: 9999 !important;color:#000;padding:5px;margin-left: 325px;" id="showhide" class="">
						<div class="row" style="background:#e0e0e0;padding: 15px;">
							<div class="col-md-12">
								<div class="col-md-4">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="0" id="iCol0" data-showhide="batch_code" class="showhideCheckBox" /> <label for="iCol0">Batch Code</label>
								</div>
								<div class="col-md-4">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="1" id="iCol1" data-showhide="''" class="showhideCheckBox" /> <label for="iCol1">No. Of Samples</label>
								</div>
								<div class="col-md-4">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="2" id="iCol2" data-showhide="request_created_datetime" class="showhideCheckBox" /> <label for="iCol2">Created On</label>
								</div>
								<div class="col-md-4">
									<input type="checkbox" onclick="javascript:fnShowHide(this.value);" value="3" id="iCol3" data-showhide="batch_status" class="showhideCheckBox" /> <label for="iCol3">Status</label> <br>
								</div>
							</div>
						</div>
					</span>

					<div class="box-header with-border">
						<?php if (isset($_SESSION['privileges']) && in_array("tb-add-batch.php", $_SESSION['privileges'])) { ?>
							<a href="tb-add-batch.php" class="btn btn-primary pull-right"> <i class="fa fa-plus"></i> Create New Batch</a>
						<?php } ?>
						<!--<button class="btn btn-primary pull-right" style="margin-right: 1%;" onclick="$('#showhide').fadeToggle();return false;"><span>Manage Columns</span></button>-->
					</div>
					<!-- /.box-header -->
					<div class="box-body">
						<table id="batchCodeDataTable" class="table table-bordered table-striped">
							<thead>
								<tr>
									<th>Batch Code</th>
									<th>No. of Samples</th>
									<th>No. of Samples Tested</th>
									<th>Last Tested Date</th>
									<th>Created On</th>
									<?php if (isset($_SESSION['privileges']) && in_array("tb-edit-batch.php", $_SESSION['privileges'])) { ?>
										<th>Action</th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="6" class="dataTables_empty">Loading data from server</td>
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
				<?php if (isset($_SESSION['privileges']) && in_array("tb-edit-batch.php", $_SESSION['privileges'])) { ?> {
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
			"sAjaxSource": "/vl/batch/getBatchCodeDetails.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "type",
					"value": "tb"
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

	function generateQRcode(bId) {
		$.blockUI();
		$.post("/qr-code/generateQRcode.php", {
				id: bId
			},
			function(data) {
				if (data == "" || data == null || data == undefined) {
					alert('Unable to generate QR code');
				} else {
					window.open('/uploads/qrcode/' + data, '_blank');
				}
				$.unblockUI();
			});
	}

	function deleteBatchCode(bId, batchCode) {
		var conf = confirm("Are you sure you want to delete Batch : " + batchCode + "?\nThis action cannot be undone.");
		if (conf) {
			$.post("/vl/batch/deleteBatchCode.php", {
					id: bId,
					type: 'tb'
				},
				function(data) {
					if (data == 1) {
						alert("Batch deleted");
					} else {
						alert("Something went wrong. Please try again!");
					}
					oTable.fnDraw();
				});
		}
	}
</script>


<?php

include_once(APPLICATION_PATH . '/footer.php');
