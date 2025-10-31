<?php
$title = _translate("Geographical Divisions");

require_once APPLICATION_PATH . '/header.php';

?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-gears"></em> <?php echo _translate("Geographical Divisions"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("Geographical Divisions"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<div class="box-header with-border">
						<?php if (_isAllowed("add-geographical-divisions.php") && $general->isLISInstance() === false) { ?>
							<a href="add-geographical-divisions.php" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _translate("Add New Geographical Divisions"); ?></a>
						<?php } ?>
					</div>
					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="samTypDataTable" class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<th scope="row"><?php echo _translate("Name"); ?></th>
									<th scope="row"><?php echo _translate("Code"); ?></th>
									<th scope="row"><?php echo _translate("Parent"); ?></th>
									<th scope="row"><?php echo _translate("Status"); ?></th>
									<?php if ($hasAction) { ?>
										<th scope="row"><?php echo _translate("Action"); ?></th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="3" class="dataTables_empty"><?php echo _translate("Loading data from server"); ?></td>
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
		oTable = $('#samTypDataTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ <?= _translate("records per page", true); ?>"
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
				<?php if (_isAllowed("geographical-divisions-details.php") && $general->isLISInstance() === false) { ?> {
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
			"sAjaxSource": "/common/reference/get-geographical-divisions-helper.php",
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
require_once APPLICATION_PATH . '/footer.php';
