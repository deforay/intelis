<?php
$title = "EID Sample Type";
#require_once('../startup.php'); 
include_once(APPLICATION_PATH . '/header.php');
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><i class="fa fa-child"></i> EID Sample Type</h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
			<li class="active">EID Sample Type</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<div class="box-header with-border">
						<?php if (isset($_SESSION['privileges']) && in_array("eid-sample-type.php", $_SESSION['privileges']) && $sarr['sc_user_type'] != 'vluser') { ?>
							<a href="add-eid-sample-type.php" class="btn btn-primary pull-right"> <i class="fa fa-plus"></i> Add EID Sample Type</a>
						<?php } ?>
					</div>
					<!-- /.box-header -->
					<div class="box-body">
						<table id="comorbiditiesDataTable" class="table table-bordered table-striped">
							<thead>
								<tr>
									<th>Sample Name</th>
									<th>Status</th>
									<?php if (isset($_SESSION['privileges']) && in_array("eid-sample-type.php", $_SESSION['privileges']) && $sarr['sc_user_type'] != 'vluser') { ?>
										<!-- <th>Action</th> -->
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="3" class="dataTables_empty">Loading data from server</td>
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
	$(function() {
		//$("#example1").DataTable();
	});
	$(document).ready(function() {
		$.blockUI();
		oTable = $('#comorbiditiesDataTable').dataTable({
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
			],
			"aaSorting": [
				[0, "asc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "get-eid-sample-type-helper.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
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
	function updateStatus(obj, optVal) {
    if (obj.value != '') {
      conf = confirm("Are you sure you want to change the status?");
      if (conf) {
        $.post("update-eid-sample-status.php", {
            status: obj.value,
            id: obj.id
          },
          function(data) {
            if (data != "") {
              oTable.fnDraw();
              alert('Updated successfully.');
            }
          });
      }
	  else {
		window.top.location = window.top.location;
	  }
    }
  }
</script>
<?php
include(APPLICATION_PATH . '/footer.php');
?>