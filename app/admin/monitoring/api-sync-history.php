<?php

use App\Services\CommonService;

$title = _("Audit Trail");
require_once(APPLICATION_PATH . '/header.php');

$general = new CommonService();
$syncedTypeResults = $db->rawQuery("SELECT DISTINCT request_type FROM track_api_requests ORDER BY request_type ASC");
foreach ($syncedTypeResults as $synced) {
	$syncedType[$synced['request_type']] = (str_replace("-", " ", $synced['request_type']));
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
		<h1><em class="fa-solid  fa-circle-nodes"></em> <?php echo _("API History"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("API History"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<table class="table" aria-hidden="true"  cellspacing="3" style="margin-left:1%;margin-top:20px;width:98%;">
						<tr>
							<td><strong><?php echo _("Date Range"); ?>&nbsp;:</strong></td>
							<td>
								<input type="text" id="dateRange" name="dateRange" class="form-control daterangefield" placeholder="<?php echo _('Enter date range'); ?>" style="width:220px;background:#fff;" />
							</td>
							<td><strong><?php echo _("Test Type"); ?>&nbsp;:</strong></td>
							<td>
								<select type="text" id="testType" name="testType" class="form-control" placeholder="<?php echo _('Please select the Test types'); ?>">
									<option value=""><?php echo _("-- Select --"); ?></option>
									<?php if (isset(SYSTEM_CONFIG['modules']['vl']) && SYSTEM_CONFIG['modules']['vl'] === true) { ?>
										<option value="vl"><?php echo _("Viral Load"); ?></option>
									<?php }
									if (isset(SYSTEM_CONFIG['modules']['eid']) && SYSTEM_CONFIG['modules']['eid'] === true) { ?>
										<option value="eid"><?php echo _("Early Infant Diagnosis"); ?></option>
									<?php }
									if (isset(SYSTEM_CONFIG['modules']['covid19']) && SYSTEM_CONFIG['modules']['covid19'] === true) { ?>
										<option value="covid19"><?php echo _("Covid-19"); ?></option>
									<?php }
									if (isset(SYSTEM_CONFIG['modules']['hepatitis']) && SYSTEM_CONFIG['modules']['hepatitis'] === true) { ?>
										<option value='hepatitis'><?php echo _("Hepatitis"); ?></option>
									<?php }
									if (isset(SYSTEM_CONFIG['modules']['tb']) && SYSTEM_CONFIG['modules']['tb'] === true) { ?>
										<option value='tb'><?php echo _("TB"); ?></option>
									<?php } ?>
								</select>
							</td>
							<td><strong><?php echo _("API Type"); ?>&nbsp;:</strong></td>
							<td>
								<select style="width:220px;" class="form-control select2" id="syncedType" name="syncedType" title="<?php echo _('Please select the API type'); ?>">
									<?php echo $general->generateSelectOptions($syncedType, null, '--Select--'); ?>
								</select>
							</td>
						</tr>
						<tr>
							<td><button onclick="oTable.fnDraw();" value="Search" class="btn btn-primary btn-sm"><span><?php echo _("Search"); ?></span></button></td>
						</tr>
					</table>
					<!-- /.box-header -->
					<div class="box-body">
						<table id="vlRequestDataTable" class="table table-bordered table-striped" aria-hidden="true" >
							<thead>
								<tr>
									<th><?php echo _("Transaction ID"); ?></th>
									<th><?php echo _("Number of Records Synced"); ?></th>
									<th><?php echo _("Sync Type"); ?></th>
									<th><?php echo _("Test Type"); ?></th>
									<th><?php echo _("URL"); ?></th>
									<th><?php echo _("Synced On"); ?></th>
									<th><?php echo _("Action"); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="7" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
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
	$(document).ready(function() {
		loadVlRequestData();
		$('#dateRange').daterangepicker({
			locale: {
				cancelLabel: "<?= _("Clear"); ?>",
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
				'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')]
			}
		});
	});

	function loadVlRequestData() {
		$.blockUI();
		oTable = $('#vlRequestDataTable').dataTable({
			"oLanguage": {
				"sLengthMenu": "_MENU_ records per page"
			},
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
			"fnServerData": function(sSource, aoData, fnCallback) {
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
require_once(APPLICATION_PATH . '/footer.php');
