<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$keyFromGlobalConfig = $general->getGlobalConfig('key');
$title = _translate("VL Results");
require_once APPLICATION_PATH . '/header.php';
?>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-flask-vial"></em> <?php echo _translate("Viral Load Results"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("VL Results"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<div class="box-header with-border">
						<?php if ($general->isSTSInstance()) { ?>
							<a href="javascript:void(0);" onclick="forceMetadataSync('<?php echo CommonService::encrypt('r_vl_results', base64_decode((string) $keyFromGlobalConfig)); ?>')" class="btn btn-success pull-right" style="margin-left: 10px;"> <em class="fa-solid fa-refresh"></em></a>
						<?php }
						if (_isAllowed("vl-art-code-details.php")) { ?>
							<a href="add-vl-results.php" class="btn btn-primary pull-right"> <em class="fa-solid fa-plus"></em> <?php echo _translate("Add VL Results"); ?></a>
						<?php } ?>
					</div>
					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="sampTypDataTable" class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<th></th>
									<th scope="row"><?php echo _translate("Viral Load Result"); ?></th>
									<th scope="row"><?php echo _translate("Instruments"); ?></th>
									<th scope="row"><?php echo _translate("Status"); ?></th>
									<?php if (_isAllowed("vl-results.php")) { ?>
										<th scope="row">Action</th>
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


		oTable = $('#sampTypDataTable').DataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ records per page"
			},
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			"bStateSave": false,
			"bRetrieve": false,
			"aoColumns": [{
					"sClass": "center dt-control",
					"bSortable": false
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center",
					"bVisible": false
				},
				{
					"sClass": "center"
				},
				{
					"sClass": "center"

				}
			],
			"aaSorting": [
				[1, "asc"]
			],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/vl/reference/get-vl-results-helper.php",
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
		//oTable.fnSetColumnVis(1, false);

		// Add event listener for opening and closing details
		oTable.on('click', 'td.dt-control', function(e) {
			let tr = e.target.closest('tr');
			let row = oTable.row(tr);

			if (row.child.isShown()) {
				// This row is already open - close it
				row.child.hide();
			} else {
				// Open this row
				row.child(format(row.data())).show();
			}

		});
		$.unblockUI();
	});

	function updateStatus(obj, optVal) {
		if (obj.value != '') {
			conf = confirm("<?php echo _translate("Are you sure you want to change the status?"); ?>");
			if (conf) {
				$.post("update-vl-result-status.php", {
						status: obj.value,
						id: obj.id
					},
					function(data) {
						//console.log(data);
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

	function format(d) {
		//alert(d[2]);
		if (d[2] != null) {
			var ins = d[2].split(",");

			// `d` is the original data object for the row
			return (
				'<dl>' +
				'<dt>Instruments :</dt>' +
				'<dd><ul>' +
				ins.map(i => '<li>' + i + '</li>').join('') +
				'</ul></dd>' +
				'</dl>'
			);
		} else {
			return false;
		}
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
