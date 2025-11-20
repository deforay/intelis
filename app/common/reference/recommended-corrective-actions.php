<?php

use App\Registries\AppRegistry;

$title = _translate("Recommended Corrective Actions");

require_once APPLICATION_PATH . '/header.php';
$testType = 'eid';


// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

if (!empty($_GET['testType'])) {
	$testType = $_GET['testType'];
}
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-gears"></em> <?php echo _translate("Recommended Corrective Actions"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("Recommended Corrective Actions"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<div class="box-header with-border">
						<?php if (_isAllowed("recommended-corrective-actions.php") && $general->isLISInstance() === false) { ?>
							<a href="add-recommended-corrective-action.php?testType=vl" class="btn btn-primary pull-right">
								<em class="fa-solid fa-plus"></em>
								<?php echo _translate("Add Recommended Corrective Actions"); ?></a>
						<?php } ?>
					</div>
					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="correctiveActionTable"
							class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<th scope="row"><?php echo _translate("Recommended Corrective Action Name"); ?></th>
									<th scope="row"><?php echo _translate("Status"); ?></th>
									<?php if (_isAllowed("recommended-corrective-actions.php") && $general->isLISInstance() === false) { ?>
										<th scope="row">Action</th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="3" class="dataTables_empty">
										<?php echo _translate("Loading data from server"); ?></td>
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

	$(document).ready(function () {
		$.blockUI();
		oTable = $('#correctiveActionTable').dataTable({
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
			],
			"aaSorting": [
				[0, "asc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "get-corrective-actions-helper.php",
			"fnServerData": function (sSource, aoData, fnCallback) {
				aoData.push({
					"name": "testType",
					"value": '<?= $testType; ?>'
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
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
