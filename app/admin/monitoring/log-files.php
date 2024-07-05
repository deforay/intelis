<?php


use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$title = _translate("Log File Viewer") . " - " . _translate("Admin");

require_once APPLICATION_PATH . '/header.php';

?>
<link rel="stylesheet" type="text/css" href="/assets/css/toastify.min.css">
<style>
	.logViewer {
		/* Hide initially */
		display: none;
		border: 1px solid #ccc;
		padding: 10px;
		white-space: pre-wrap;
		overflow-x: auto;
	}

	.logLine {
		margin: 5px 0;
		padding: 11px;
		background-color: #f9f9f9;
		border: 2px solid #f1f1f1;
		border-left: 3px solid #4CAF50;
		font-family: 'Courier New', Courier, monospace;
		margin-bottom: 2em;
		cursor: pointer;
		transition: background-color 0.3s;
		position: relative;
	}

	.lineNumber {
		user-select: none;
		position: absolute;
		top: -0.5em;
		left: -0.2em;
		z-index: 1;
		font-size: 7em;
		color: rgba(0, 0, 0, 0.15);
	}

	.logLine:hover {
		background-color: #e8f0fe;
	}

	.loading,
	.error {
		color: #007bff;
		text-align: center;
		padding: 20px;
	}
</style>

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
								<input type="text" id="userDate" name="userDate" class="form-control date" placeholder="<?php echo _translate('Select User Date'); ?>" readonly value="<?= date('d-M-Y') ?>" style="width:220px;background:#fff;" />
							</td>
							<td>
								<button onclick="javascript:void(0);" id="viewLogButton" value="Search" class="btn btn-primary btn-sm"><span><?php echo _translate("Search"); ?></span></button>
							</td>
							<td>
								<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?php echo _translate("Clear Search"); ?></span></button>
							</td>
						</tr>
					</table>
					<!-- /.box-header -->
					<div class="box-body">
						<!-- <span><i class="fa fa-trash" style="color: red; background"></i></span> -->
						<div class="logViewer" id="logViewer" style="white-space: pre-wrap;"></div>
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
<script type="text/javascript" src="/assets/js/toastify.js"></script>
<script>
	let start = 0; // Starting index for logs
	let loading = false; // Flag to prevent multiple simultaneous AJAX calls
	let linesPerPage = 3; // Adjust based on what you want per load
	let hasMoreLogs = true; // Flag to check if there are more logs to load

	function resetAndLoadLogs() {
		start = 0; // Reset start index
		hasMoreLogs = true; // Reset log continuation flag
		$('#logViewer').html(''); // Clear existing logs
		loadLogs(); // Load initial logs
	}

	function loadLogs() {
		if (!loading && hasMoreLogs) {
			loading = true;
			$('#logViewer').show();
			$('#logViewer').append('<div class="loading">Loading...</div>');

			$.ajax({
				url: '/admin/monitoring/get-log-files.php?date=' + $('#userDate').val() + '&start=' + start,
				success: function(data) {
					$('.loading').remove(); // Remove loading indicator
					if (data.trim() === '') {
						hasMoreLogs = false;
						if (start === 0) {
							$('#logViewer').html('<div class="logLine">No logs found.</div>'); // Clear and show no logs if first fetch returns none
						} else {
							$('#logViewer').append('<div class="logLine">No more logs.</div>');
						}
					} else {
						$('#logViewer').append(data);
						let linesReturned = (data.match(/class='logLine'/g) || []).length;
						start += linesReturned; // Update the start index based on actual lines returned
					}
					loading = false;
				},
				error: function() {
					$('.loading').remove();
					if (start === 0) {
						$('#logViewer').html('<div class="error">Error loading logs.</div>'); // Clear and show error if first fetch fails
					} else {
						$('#logViewer').append('<div class="error">Error loading more logs.</div>');
					}
					loading = false;
				}
			});
		}
	}

	$(document).ready(function() {
		$('.date').datepicker({
			changeMonth: true,
			changeYear: true,
			dateFormat: '<?= $_SESSION['jsDateFieldFormat'] ?? 'dd-M-yy'; ?>',
			maxDate: new Date(),
			yearRange: '<?= (date('Y') - 100); ?>:<?= date('Y'); ?>'
		});

		// Bind the button to reset logs and load new ones
		$('#viewLogButton').click(resetAndLoadLogs);

		resetAndLoadLogs(); // Load logs when page loads

		// Setup scroll event for infinite scrolling
		$(window).scroll(function() {
			if ($(window).scrollTop() + $(window).height() > $(document).height() - 100 && hasMoreLogs) { // If close to bottom
				loadLogs();
			}
		});
	});
</script>


<?php
require_once APPLICATION_PATH . '/footer.php';
