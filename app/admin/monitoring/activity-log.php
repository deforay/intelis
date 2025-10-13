<?php

use App\Services\UsersService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

$title = _translate("User Activity Log");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$userNameList = $usersService->getAllUsers(null, null, 'drop-down');

$actions = $db->rawQueryGenerator("SELECT DISTINCT event_type FROM activity_log");

$actionList = [];
foreach ($actions as $list) {
	$actionList[$list['event_type']] = (str_replace("-", " ", (string) $list['event_type']));
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
		<h1><span class="fa-solid fa-file-lines"></span>
			<?php echo _translate("User Activity Log"); ?>
		</h1>
		<ol class="breadcrumb">
			<li><a href="/"><span class="fa-solid fa-chart-pie"></span>
					<?php echo _translate("Home"); ?>
				</a></li>
			<li class="active">
				<?php echo _translate("Audit Trail"); ?>
			</li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<table aria-describedby="table" class="table pageFilters" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;">
						<tr>
							<th scope="row">
								<?= _translate('Date Range'); ?>&nbsp;:
							</th>
							<td>
								<input type="text" id="dateRange" name="dateRange" class="form-control daterangefield" placeholder="<?php echo _translate('Enter date range'); ?>" style="width:220px;background:#fff;" />
							</td>
							<th scope="row">
								<?php echo _translate("Users"); ?>&nbsp;:
							</th>
							<td>
								<select style="width:220px;" class="form-control select2" id="userName" name="userName" title="<?php echo _translate('Please select the user name'); ?>">
									<?php echo $general->generateSelectOptions($userNameList, null, '--Select--'); ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<?php echo _translate("Type of Action"); ?>&nbsp;:
							</th>
							<td>
								<select style="width:220px;" class="form-control" id="typeOfAction" name="typeOfAction" title="<?php echo _translate('Type of Action'); ?>">
									<?php echo $general->generateSelectOptions($actionList, null, '--All--'); ?>
								</select>
							</td>
							<td style=" display: contents; ">
								<button onclick="oTable.fnDraw();" value="Search" class="btn btn-primary btn-sm"><span>
										<?php echo _translate("Search"); ?>
									</span></button>
								<a href="/admin/monitoring/activity-log.php" class="btn btn-danger btn-sm" style=" margin-left: 15px; "><span>
										<?php echo _translate("Clear"); ?>
									</span></a>
							</td>
						</tr>
					</table>
					<!-- /.box-header -->
					<div class="box-body">
						<table aria-describedby="table" id="auditTrailDataTable" class="table table-bordered table-striped" aria-hidden="true">
							<thead>
								<tr>
									<th>
										<?php echo _translate("Audit Log"); ?>
									</th>
									<th>
										<?php echo _translate("Type of Action"); ?>
									</th>
									<th>
										<?php echo _translate("IP Address"); ?>
									</th>
									<th>
										<?php echo _translate("Recorded On"); ?>
									</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="15" class="dataTables_empty">
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
	$(document).ready(function() {
		$('#userName').select2({
			placeholder: "Select user to filter"
		});

		$('#typeOfAction').select2({
			placeholder: "Select action to filter"
		});

		loadVlRequestData();
		$('#dateRange').daterangepicker({
				locale: {
					cancelLabel: "<?= _translate("Clear", true); ?>",
					format: 'DD-MMM-YYYY',
					separator: ' to ',
				},
				showDropdowns: true,
				alwaysShowCalendars: false,
				startDate: moment().subtract(28, 'days'),
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
			},
			function(start, end) {
				startDate = start.format('YYYY-MM-DD');
				endDate = end.format('YYYY-MM-DD');
			});

	});

	function loadVlRequestData() {
		$.blockUI();
		oTable = $('#auditTrailDataTable').dataTable({
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
			}],
			"aaSorting": [3, "desc"],
			"bProcessing": true,
			"bServerSide": true,
			"sAjaxSource": "/admin/monitoring/get-audit-trail-list.php",
			"fnServerData": function(sSource, aoData, fnCallback) {
				aoData.push({
					"name": "dateRange",
					"value": $("#dateRange").val()
				});
				aoData.push({
					"name": "userName",
					"value": $("#userName").val()
				});
				aoData.push({
					"name": "typeOfAction",
					"value": $("#typeOfAction").val()
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
