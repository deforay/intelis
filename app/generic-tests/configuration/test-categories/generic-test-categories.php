<?php
use App\Services\UsersService;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$keyFromGlobalConfig = $general->getGlobalConfig('key');
$title = _translate("Test Categories");
require_once APPLICATION_PATH . '/header.php';
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-gears"></em> <?php echo _translate("Other Lab Test Categories"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("Test Categories"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<div class="box-header with-border">
						<a href="javascript:void(0);" onclick="forceMetadataSync('<?php echo $general->encrypt('r_generic_test_categories', base64_decode((string) $keyFromGlobalConfig)); ?>')" class="btn btn-success pull-right" style="margin-left: 10px;"> <em class="fa-solid fa-refresh"></em></a>
						<?php if (_isAllowed("/generic-tests/configuration/test-categories/generic-add-test-categories.php")) { ?>
							<a href="/generic-tests/configuration/test-categories/generic-add-test-categories.php" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _translate("Add Test Categories"); ?></a>
						<?php } ?>
					</div>
					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="partnerTable" class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<th scope="row"><?php echo _translate("Test Category"); ?></th>
									<th scope="row"><?php echo _translate("Status"); ?></th>
									<th scope="row"><?php echo _translate("Updated On"); ?></th>
									<?php if (_isAllowed("/generic-tests/configuration/test-categories/generic-edit-test-categories.php")) { ?>
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

				<?php if (_isAllowed("/generic-tests/configuration/test-categories/generic-edit-test-categories.php")) { ?> {
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
			"sAjaxSource": "get-test-categories-helper.php",
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
