<?php

use App\Models\General;
use App\Models\Users;

$title = _("User Activity Log");
require_once(APPLICATION_PATH . '/header.php');

$general = new General();
$userDb = new Users();
$userNameList = $userDb->getAllUsers(null, null, 'drop-down');

$actions = $db->rawQuery("SELECT DISTINCT event_type FROM activity_log");

$actionList = [];
foreach ($actions as $list) {
	$actionList[$list['event_type']] = (str_replace("-", " ", $list['event_type']));
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
		<h1><span class="fa-solid fa-file-lines"></span> <?php echo _("User Activity Log"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><span class="fa-solid fa-chart-pie"></span> <?php echo _("Home"); ?></a></li>
			<li class="active"><?php echo _("Audit Trail"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<table class="table" aria-hidden="true"  style="margin-left:1%;margin-top:20px;width:98%;">
						<tr>
							<th scope="row"><?php echo _("Date Range"); ?>&nbsp;:</th>
							<td>
								<input type="text" id="dateRange" name="dateRange" class="form-control daterangefield" placeholder="<?php echo _('Enter date range'); ?>" style="width:220px;background:#fff;" />
							</td>
							<th scope="row"><?php echo _("Users"); ?>&nbsp;:</th>
							<td>
								<select style="width:220px;" class="form-control select2" id="userName" name="userName" title="<?php echo _('Please select the user name'); ?>">
									<?php echo $general->generateSelectOptions($userNameList, null, '--Select--'); ?>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php echo _("Type of Action"); ?>&nbsp;:</th>
							<td>
								<select style="width:220px;" class="form-control" id="typeOfAction" name="typeOfAction" title="<?php echo _('Type of Action'); ?>">
									<?php echo $general->generateSelectOptions($actionList, null, '--All--'); ?>
								</select>
							</td>
							<td style=" display: contents; ">
								<button onclick="oTable.fnDraw();" value="Search" class="btn btn-primary btn-sm"><span><?php echo _("Search"); ?></span></button>
								<a href="/admin/monitoring/activity-log.php" class="btn btn-danger btn-sm" style=" margin-left: 15px; "><span><?php echo _("Clear"); ?></span></a>
							</td>
						</tr>
					</table>
					<!-- /.box-header -->
					<div class="box-body">
						<table id="auditTrailDataTable" class="table table-bordered table-striped" aria-hidden="true" >
							<thead>
								<tr>
									<th><?php echo _("Audit Log"); ?></th>
									<th><?php echo _("Type of Action"); ?></th>
									<th><?php echo _("IP Address"); ?></th>
									<th><?php echo _("Recorded On"); ?></th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td colspan="15" class="dataTables_empty"><?php echo _("Loading data from server"); ?></td>
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
					cancelLabel: "<?= _("Clear"); ?>",
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
					'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')]
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
require_once(APPLICATION_PATH . '/footer.php');
