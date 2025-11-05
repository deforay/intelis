<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$keyFromGlobalConfig = $general->getGlobalConfig('key');
$title = _translate("CD4 Sample Type");
require_once APPLICATION_PATH . '/header.php';

?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-eyedropper"></em> <?php echo _translate("CD4 Sample Type"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("CD4 Sample Type"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<div class="box-header with-border">
						<?php if ($general->isSTSInstance()) { ?>
							<a href="javascript:void(0);" onclick="forceMetadataSync('<?php echo $general->encrypt('r_cd4_sample_types', base64_decode((string) $keyFromGlobalConfig)); ?>')" class="btn btn-success pull-right" style="margin-left: 10px;"> <em class="fa-solid fa-refresh"></em></a>
						<?php }
						if (_isAllowed("cd4-sample-type.php") && $general->isLISInstance() === false) { ?>
							<a href="add-cd4-sample-type.php" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _translate("Add CD4 Sample Type"); ?></a>
						<?php } ?>
					</div>
					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="samTypDataTable" class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<th scope="row"><?php echo _translate("Sample Name"); ?></th>
									<th scope="row"><?php echo _translate("Sample Status"); ?></th>
									<?php if (_isAllowed("cd4-sample-type.php") && $general->isLISInstance() === false) { ?>
										<!-- <th scope="row">Action</th> -->
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
			"sAjaxSource": "get-cd4-sample-type-helper.php",
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
			conf = confirm("<?php echo _translate("Are you sure you want to change the status?"); ?>");
			if (conf) {
				$.post("update-cd4-sample-status.php", {
						status: obj.value,
						id: obj.id
					},
					function(data) {
						if (data != "") {
							oTable.fnDraw();
							alert("<?php echo _translate("Updated successfully"); ?>.");
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
