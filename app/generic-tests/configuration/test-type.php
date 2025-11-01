<?php

use App\Services\UsersService;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$keyFromGlobalConfig = $general->getGlobalConfig('key');
$title = "Test Type Configuration";
require_once APPLICATION_PATH . '/header.php';
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-gears"></em> <?php echo _translate("Test Type Configuration"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("Test Type Configuration"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<div class="box-header with-border">
						<?php if ($general->isSTSInstance()) { ?>
							<a href="javascript:void(0);" onclick="forceMetadataSync('<?php echo $general->encrypt('r_test_types', base64_decode((string) $keyFromGlobalConfig)); ?>')" class="btn btn-success pull-right" style="margin-left: 10px;"> <em class="fa-solid fa-refresh"></em></a>
						<?php }
						if (_isAllowed("/generic-tests/configuration/add-test-type.php") && $general->isLISInstance() === false) { ?>
							<a href="add-test-type.php" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _translate("Add Test Type"); ?></a>
						<?php } ?>
					</div>
					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="testTypeDataTable" class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<th><?php echo _translate("Standard Name"); ?></th>
									<th><?php echo _translate("Generic Name"); ?></th>
									<th><?php echo _translate("Short Code"); ?></th>
									<th><?php echo _translate("LOINC Code"); ?></th>
									<th><?php echo _translate("Status"); ?></th>
									<?php if (_isAllowed("/generic-tests/configuration/edit-test-type.php") && $general->isLISInstance() === false) { ?>
										<th><?php echo _translate("Action"); ?></th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="6" class="dataTables_empty"><?php echo _translate("Loading data from server"); ?></td>
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
<script type="text/javascript">
	var oTable = null;
	$(document).ready(function() {
		$.blockUI();
		oTable = $('#testTypeDataTable').dataTable({
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,

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
				}
				<?php if (_isAllowed("/generic-tests/configuration/edit-test-type.php") && $general->isLISInstance() === false) { ?>,
					{
						"sClass": "center",
						"bSortable": false
					}
				<?php } ?>
			],
			"aaSorting": [
				[0, "asc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "getTestTypeDetails.php",
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
