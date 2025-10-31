<?php

use App\Services\UsersService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$keyFromGlobalConfig = $general->getGlobalConfig('key');
$title = _translate("Other Lab Tests Sample Types");
require_once APPLICATION_PATH . '/header.php';
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-gears"></em> <?php echo _translate("Other Lab Tests Sample Types"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("Sample Types"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<div class="box-header with-border">
						<?php if ($general->isSTSInstance()) { ?>
							<a href="javascript:void(0);" onclick="forceMetadataSync('<?php echo $general->encrypt('r_generic_sample_types', base64_decode((string) $keyFromGlobalConfig)); ?>')" class="btn btn-success pull-right" style="margin-left: 10px;"> <em class="fa-solid fa-refresh"></em></a>
						<?php }
						if (_isAllowed("/generic-tests/configuration/sample-types/generic-add-sample-type.php") && $general->isSTSInstance()) { ?>
							<a href="/generic-tests/configuration/sample-types/generic-add-sample-type.php" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _translate("Add Sample Type"); ?></a>
						<?php } ?>
					</div>
					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="partnerTable" class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<th scope="row"><?php echo _translate("Sample Type Name"); ?></th>
									<th scope="row"><?php echo _translate("Sample Type Code"); ?></th>
									<th scope="row"><?php echo _translate("Status"); ?></th>
									<th scope="row"><?php echo _translate("Updated On"); ?></th>
									<?php if (_isAllowed("/generic-tests/configuration/sample-types/generic-edit-sample-type.php") && $general->isSTSInstance()) { ?>
										<th scope="row">Action</th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="5" class="dataTables_empty"><?php echo _translate("Loading data from server"); ?></td>
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
		oTable = $('#partnerTable').dataTable({
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
				<?php if (_isAllowed("/generic-tests/configuration/sample-types/generic-edit-sample-type.php") && $general->isSTSInstance()) { ?> {
						"sClass": "center",
						"bSortable": false
					}
				<?php } ?>
			],
			"aaSorting": [
				[3, "asc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "get-generic-sample-type-helper.php",
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
			conf = confirm('<?php echo _translate("Are you sure you want to change the status"); ?>?');
			if (conf) {
				$.post("update-implementation-status.php", {
						status: obj.value,
						id: obj.id
					},
					function(data) {
						if (data != "") {
							oTable.fnDraw();
							alert("<?php echo _translate("Updated successfully", true); ?>");
						}
					});
			} else {
				window.top.location.href = window.top.location;
			}
		}
	}
</script>
<?php

require_once APPLICATION_PATH . '/footer.php';
