<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

$title = _translate("API Sync History");
require_once APPLICATION_PATH . '/header.php';


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$syncedTypeResults = $db->rawQuery("SELECT DISTINCT request_type FROM track_api_requests ORDER BY request_type ASC");
foreach ($syncedTypeResults as $synced) {
	$syncedType[$synced['request_type']] = (str_replace("-", " ", (string) $synced['request_type']));
}
?>
<style>
	.select2-selection__choice {
		color: black !important;
	}

	th {
		display: revert !important;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid  fa-circle-nodes"></em>
			<?php echo _translate("API History"); ?>
		</h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em>
					<?php echo _translate("Home"); ?>
				</a></li>
			<li class="active">
				<?php echo _translate("API History"); ?>
			</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<table aria-describedby="table" class="table pageFilters" aria-hidden="true" cellspacing="3"
						style="margin-left:1%;margin-top:20px;width:98%;">
						<tr>
							<td><strong>
									<?= _translate('Date Range'); ?>&nbsp;:
								</strong></td>
							<td>
								<input type="text" id="dateRange" name="dateRange" class="form-control daterangefield"
									placeholder="<?php echo _translate('Enter date range'); ?>"
									style="width:220px;background:#fff;" />
							</td>
							<td><strong>
									<?php echo _translate("Test Type"); ?>&nbsp;:
								</strong></td>
							<td>
								<select id="testType" name="testType" class="form-control"
									placeholder="<?php echo _translate('Please select the Test types'); ?>">
									<option value="">
										<?php echo _translate("-- Select --"); ?>
									</option>
									<?php if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) { ?>
										<option value="vl">
											<?php echo _translate("Viral Load"); ?>
										</option>
									<?php }
									if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) { ?>
										<option value="eid">
											<?php echo _translate("Early Infant Diagnosis"); ?>
										</option>
									<?php }
									if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) { ?>
										<option value="covid19">
											<?php echo _translate("Covid-19"); ?>
										</option>
									<?php }
									if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) { ?>
										<option value='hepatitis'>
											<?php echo _translate("Hepatitis"); ?>
										</option>
									<?php }
									if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) { ?>
										<option value='tb'>
											<?php echo _translate("TB"); ?>
										</option>
									<?php }
									if (isset(SYSTEM_CONFIG['modules']['cd4']) && SYSTEM_CONFIG['modules']['cd4'] === true) { ?>
										<option value='cd4'>
											<?php echo _translate("CD4"); ?>
										</option>
									<?php } ?>
								</select>
							</td>
							<td><strong>
									<?php echo _translate("API Type"); ?>&nbsp;:
								</strong></td>
							<td>
								<select style="width:220px;" class="form-control select2" id="syncedType"
									name="syncedType" title="<?php echo _translate('Please select the API type'); ?>">
									<?php echo $general->generateSelectOptions($syncedType, null, '--Select--'); ?>
								</select>
							</td>
						</tr>
						<tr>
							<td><button onclick="oTable.fnDraw();" value="Search" class="btn btn-primary btn-sm"><span>
										<?php echo _translate("Search"); ?>
									</span></button></td>
						</tr>
					</table>
					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="vlRequestDataTable"
							class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<th>
										<?php echo _translate("Transaction ID"); ?>
									</th>
									<th>
										<?php echo _translate("Number of Records Synced"); ?>
									</th>
									<th>
										<?php echo _translate("Sync Type"); ?>
									</th>
									<th>
										<?php echo _translate("Test Type"); ?>
									</th>
									<th style="width:200px;">
										<?php echo _translate("URL"); ?>
									</th>
									<th>
										<?php echo _translate("Synced On"); ?>
									</th>
									<th>
										<?php echo _translate("Action"); ?>
									</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="7" class="dataTables_empty">
										<?php echo _translate("Loading data from server"); ?>
									</td>
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
<script type="text/javascript">
	var oTable = null;
	$(document).ready(function () {
		$('#dateRange').daterangepicker({
			locale: {
				cancelLabel: "<?= _translate("Clear", true); ?>",
				format: 'DD-MMM-YYYY',
				separator: ' to ',
			},
			startDate: moment().subtract(7, 'days'),
			endDate: moment(),
			maxDate: moment(),
			ranges: {
				'Today': [moment(), moment()],
				'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
				'Last 7 Days': [moment().subtract(6, 'days'), moment()],
				'This Month': [moment().startOf('month'), moment().endOf('month')],
				'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
				'Last 30 Days': [moment().subtract(29, 'days'), moment()],
				'Last 90 Days': [moment().subtract(89, 'days'), moment()],
				'Last 120 Days': [moment().subtract(119, 'days'), moment()],
				'Last 180 Days': [moment().subtract(179, 'days'), moment()],
				'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')],
				'Previous Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
				'Current Year To Date': [moment().startOf('year'), moment()]
			}
		});


		loadVlRequestData();
	});

	function loadVlRequestData() {
		$.blockUI();
		oTable = $('#vlRequestDataTable').dataTable({
			"bJQueryUI": false,
			"bAutoWidth": false,
			"bInfo": true,
			"bScrollCollapse": true,
			//"bStateSave" : true,
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
				"sClass": "center"
			}, {
				"sClass": "center"
			}, {
				"sClass": "center",
				"bSortable": false
			}],
			"aaSorting": [5, "desc"],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/admin/monitoring/get-api-sync-history-list.php",
			"fnServerData": function (sSource, aoData, fnCallback) {
				aoData.push({
					"name": "dateRange",
					"value": $("#dateRange").val()
				});
				aoData.push({
					"name": "testType",
					"value": $("#testType").val()
				});
				aoData.push({
					"name": "syncedType",
					"value": $("#syncedType").val()
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
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
