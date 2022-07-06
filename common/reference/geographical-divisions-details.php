<?php
$title = _("Geographical Divisions");
 
require_once(APPLICATION_PATH . '/header.php');

?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><i class="fa-solid fa-gears"></i> <?php echo _("Geographical Divisions");?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><i class="fa-solid fa-chart-pie"></i> <?php echo _("Home");?></a></li>
			<li class="active"><?php echo _("Geographical Divisions");?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<div class="box-header with-border">
						<?php if (isset($_SESSION['privileges']) && in_array("add-geographical-divisions.php", $_SESSION['privileges']) && $sarr['sc_user_type'] != 'vluser') { ?>
							<a href="add-geographical-divisions.php" class="btn btn-primary pull-right"> <i class="fa-solid fa-plus"></i> <?php echo _("Add New Geographical Divisions");?></a>
						<?php } ?>
					</div>
					<!-- /.box-header -->
					<div class="box-body">
						<table id="samTypDataTable" class="table table-bordered table-striped">
							<thead>
								<tr>
									<th><?php echo _("Name");?></th>
									<th><?php echo _("Code");?></th>
									<th><?php echo _("Status");?></th>
									<?php if (isset($_SESSION['privileges']) && in_array("geographical-divisions-details.php", $_SESSION['privileges']) && $sarr['sc_user_type'] != 'vluser') { ?>
										<th><?php echo _("Action");?></th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="3" class="dataTables_empty"><?php echo _("Loading data from server");?></td>
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
		oTable = $('#samTypDataTable').dataTable({
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
				<?php if (isset($_SESSION['privileges']) && in_array("geographical-divisions-details.php", $_SESSION['privileges']) && $sarr['sc_user_type'] != 'vluser') { ?> {
						"sClass": "center",
						"bSortable": false
					},
				<?php } ?>
			],
			"aaSorting": [
				[0, "asc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "get-geographical-divisions-helper.php",
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
</script>
<?php
require_once(APPLICATION_PATH . '/footer.php');
?>