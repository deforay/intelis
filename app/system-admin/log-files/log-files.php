<?php
$title = _translate("Log File Viewer") . " - " . _translate("System Admin");


use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

require_once(APPLICATION_PATH . '/system-admin/admin-header.php');
$sQuery = "SELECT * FROM user_login_history";
$sResult = $db->rawQuery($sQuery);
?>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1> <em class="fa-solid fa-gears"></em> <?php echo _translate("Log File Viewer"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/system-admin/edit-config/index.php"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
			<li class="active"><?php echo _translate("Manage Log File Viewer"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="row">
			<div class="col-xs-12">
				<div class="box">
					<table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:40%;">
						<tr>
							<td><strong><?php echo _translate("Date"); ?>&nbsp;:</strong></td>
							<td>
								<input type="text" id="userDate" name="userDate" class="form-control date" placeholder="<?php echo _translate('Select User Date'); ?>" readonly style="width:220px;background:#fff;" />
							</td>
							<td>
								&nbsp;<button onclick="viewLogFiles();" value="Search" class="btn btn-primary btn-sm"><span><?php echo _translate("Search"); ?></span></button>

								&nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _translate("Clear Search"); ?></span></button>
							</td>
						</tr>

					</table>
					<!-- /.box-header -->
					<div class="box-body">
						<!-- <span><i class="fa fa-trash" style="color: red; background"></i></span> -->
						<pre class="logViewer hide" id="logViewer" style="white-space: pre-wrap;"></pre>
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
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script>
	$(document).ready(function() {

		$('.date').datepicker({
			changeMonth: true,
			changeYear: true,
			onSelect: function() {
				$(this).change();
			},
			dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
			timeFormat: "HH:mm",
			maxDate: "Today",
			yearRange: <?= (date('Y') - 100); ?> + ":" + "<?= date('Y') ?>"
		}).click(function() {
			$('.ui-datepicker-calendar').show();
		});
		$('#userDate').val("");
	});

	function viewLogFiles() {
		if ($('#userDate').val() != "") {
			$.ajax({
				async: false,
				url: 'get-log-files.php?date=' + $('#userDate').val(),
				dataType: 'text',
				success: function(data) {
					$('#logViewer').html(data);
					$('.logViewer').removeClass('hide');
				}
			});
		}
	}
</script>
<?php
require_once(APPLICATION_PATH . '/system-admin/admin-footer.php');
