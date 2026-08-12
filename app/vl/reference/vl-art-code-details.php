<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$keyFromGlobalConfig = $general->getGlobalConfig('key');
$title = _translate("VL ART Regimen");
require_once APPLICATION_PATH . '/header.php';
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-flask-vial"></em> <?php echo _translate("Viral Load ART Regimen"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("VL ART Regimen"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<div class="box-header with-border">
						<?php if ($general->isSTSInstance()) { ?>
							<a href="javascript:void(0);" onclick="forceMetadataSync('<?php echo CommonService::encrypt('r_vl_art_regimen', base64_decode((string) $keyFromGlobalConfig)); ?>')" class="btn btn-success pull-right" style="margin-left: 10px;"> <em class="fa-solid fa-refresh"></em></a>
						<?php }
						if (_isAllowed("/vl/reference/add-vl-art-code-details.php") && $general->isLISInstance() === false) { ?>
							<a href="/vl/reference/add-vl-art-code-details.php" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _translate("Add VL ART Regimen"); ?></a>
						<?php } ?>
					</div>
					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="comorbiditiesDataTable" class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<th scope="row"><?php echo _translate("ART Code"); ?></th>
									<th scope="row"><?php echo _translate("Category"); ?></th>
									<th scope="row"><?php echo _translate("Source"); ?></th>
									<th scope="row"><?php echo _translate("Maps To (reporting)"); ?></th>
									<th scope="row"><?php echo _translate("Status"); ?></th>
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
	$(function() {});
	$(document).ready(function() {
		$.blockUI();
		oTable = $('#comorbiditiesDataTable').dataTable({
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
					// Assembled from r_vl_art_regimen_alias rather than a column of this
					// table, so there is nothing for the server to sort on.
					"sClass": "center",
					"bSortable": false
				},
				{
					"sClass": "center"
				},
			],
			"order": [
				[4, "desc"],
				[0, "asc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/vl/reference/get-vl-art-code-details-helper.php",
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

	// Records that two codes name the same regimen, for reporting only.
	//
	// Nothing stored changes: no art_code is edited and no sample is rewritten, which is
	// why this page has no Edit button in the first place — current_regimen holds the code
	// text, so altering a code would reinterpret every historical row that carries it.
	//
	// Mapping a code is NOT a reason to then deactivate it. The request and result forms
	// build their dropdown from active codes only, so retiring one that samples still hold
	// leaves those samples showing an empty dropdown.
	function updateAlias(obj) {
		var artId = obj.id.replace('alias_', '');
		var artCode = $(obj).data('art-code');
		var targetLabel = obj.options[obj.selectedIndex].text;
		var msg = (obj.value === '') ?
			"<?php echo _translate("Remove the reporting mapping for"); ?> \"" + artCode + "\"?" :
			"<?php echo _translate("Group"); ?> \"" + artCode + "\" <?php echo _translate("with"); ?> \"" + targetLabel + "\" <?php echo _translate("when reporting"); ?>?";

		if (!confirm(msg)) {
			oTable.fnDraw();
			return;
		}

		$.post("/vl/reference/update-vl-art-code-alias.php", {
				id: artId,
				mapsTo: obj.value
			},
			function(data) {
				if (data !== "") {
					oTable.fnDraw();
					alert("<?php echo _translate("Updated successfully"); ?>.");
				} else {
					alert("<?php echo _translate("Unable to update the mapping. Please try again."); ?>");
					oTable.fnDraw();
				}
			}).fail(function() {
			alert("<?php echo _translate("Unable to update the mapping. Please try again."); ?>");
			oTable.fnDraw();
		});
	}

	function updateStatus(obj, optVal) {
		if (obj.value != '') {
			conf = confirm("<?php echo _translate("Are you sure you want to change the status?"); ?>");
			if (conf) {
				$.post("/vl/reference/update-vl-art-code-status.php", {
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
