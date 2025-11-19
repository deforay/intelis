<?php

$title = "Covid-19 | Confirmation Manifest";

require_once APPLICATION_PATH . '/header.php';

use App\Services\CommonService;

?>
<style>
	.select2-selection__choice {
		color: black !important;
	}
</style>

<div class="content-wrapper">
	<section class="content-header">
		<h1><em class="fa-solid fa-check"></em> Confirmation Test Manifest</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
			<li class="active">Covid-19 Confirmation Test Manifest</li>
		</ol>
	</section>

	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<table aria-describedby="table" class="table" aria-hidden="true"
						style="margin-left:1%;margin-top:20px;width: 98%;margin-bottom: 0px;">
						<tr>
							<td>
								<?php if (_isAllowed("covid-19-add-confirmation-manifest.php")) { ?>
									<a href="/covid-19/results/covid-19-add-confirmation-manifest.php"
										class="btn btn-primary btn-sm pull-right"> <em class="fa-solid fa-plus"></em> Add
										new Covid-19 Confirmation Manifest</a>
								<?php } ?>
							</td>
						</tr>
					</table>
					<!-- /.box-header -->
					<div class="box-body table-responsive">
						<table aria-describedby="table" id="covid19ManifestDataTable"
							class="table table-bordered table-striped table-vcenter">
							<thead>
								<tr>
									<th>Manifest Code</th>
									<th>Type of Test</th>
									<th>Number of samples</th>
									<th>Added On</th>
									<?php if (_isAllowed("generate-confirmation-manifest.php")) { ?>
										<th>Action</th>
									<?php } ?>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="5" class="dataTables_empty" style="text-align:center;">Loading data
										from server</td>
								</tr>
							</tbody>
						</table>
					</div>

				</div>
				<!-- /.box -->

			</div>
			<!-- /.col -->
		</div>
		<!-- /.row -->
	</section>
	<!-- /.content -->
</div>
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>

<?= CommonService::barcodeScripts(); ?>

<script type="text/javascript">
	var oTable = null;
	$(document).ready(function () {
		oTable = $('#covid19ManifestDataTable').dataTable({
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			"bStateSave": true,
			//"bDestroy": true,
			"bRetrieve": true,
			"aoColumns": [{
				"sClass": "center"
			}, {
				"sClass": "center"
			}, {
				"sClass": "center"
			}, {
				"sClass": "center"
			}, {
				"sClass": "center",
				"bSortable": false
			}],
			"aaSorting": [
				[1, "desc"]
			],
			"fnDrawCallback": function () { },
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/covid-19/results/getConfirmManifestInGridHelper.php",
			"fnServerData": function (sSource, aoData, fnCallback) {
				$.ajax({
					"dataType": 'json',
					"type": "POST",
					"url": sSource,
					"data": aoData,
					"success": fnCallback
				});
			}
		});
	});
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
