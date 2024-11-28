<?php

use GuzzleHttp\Client;
use App\Services\TestsService;
use App\Services\UsersService;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

$title = _translate("Audit Trail");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService  $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

try {
	// Sanitized values from request object
	/** @var Laminas\Diactoros\ServerRequest $request */
	$request = AppRegistry::get('request');
	$_POST = _sanitizeInput($request->getParsedBody());

	$activeModules = SystemService::getActiveModules(onlyTests: true);

	// Function to fetch unique_id based on sampleCode from formTable
	function getUniqueIdFromSampleCode($db, $tableName, $sampleCode)
	{
		$query = "SELECT unique_id FROM $tableName WHERE sample_code = ? OR remote_sample_code = ? OR external_sample_code = ?";
		$result = $db->rawQuery($query, [$sampleCode, $sampleCode, $sampleCode]);
		return $result[0]['unique_id'] ?? null; // Return unique_id if found, otherwise null
	}

	// Function to get column names for a specified table
	function getColumns($db, $tableName)
	{
		$columnsSql = "SELECT COLUMN_NAME
						FROM INFORMATION_SCHEMA.COLUMNS
						WHERE TABLE_SCHEMA = ? AND table_name = ?
						ORDER BY ordinal_position";
		return $db->rawQuery($columnsSql, [SYSTEM_CONFIG['database']['db'], $tableName]);
	}

	// Function to get CSV file path based on test type and unique ID
	function getAuditFilePath($testType, $uniqueId)
	{
		return ROOT_PATH . "/audit-trail/{$testType}/{$uniqueId}.csv.gz";
	}

	// Function to read data from CSV file
	function readAuditDataFromCsv($filePath)
	{
		$data = [];
		if (file_exists($filePath)) {
			$fileHandle = gzopen($filePath, 'r');
			if ($fileHandle !== false) {
				$headers = fgetcsv($fileHandle);
				while (($row = fgetcsv($fileHandle)) !== false) {
					$data[] = array_combine($headers, $row);
				}
				gzclose($fileHandle);
			}
		}
		return $data;
	}


	$sampleCode = null;
	if (!empty($_POST)) {
		// Define $sampleCode from POST data
		$request = AppRegistry::get('request');
		$_POST = _sanitizeInput($request->getParsedBody());
		$sampleCode = $_POST['sampleCode'] ?? null;
	}

	if (isset($_POST['testType']) && $sampleCode) {

		$formTable = TestsService::getTestTableName($_POST['testType']);
		// Get scheme (http or https)
		$scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";

		// Get host (domain or IP with port if any)
		$host = $_SERVER['HTTP_HOST'];

		// Build the full URL
		$baseUrl = "{$scheme}://{$host}";

		// Archive latest audit data for this sample
		$client = new Client();
		try {
			$response = $client->get("{$baseUrl}/scheduled-jobs/archive-audit-tables.php?sampleCode=$sampleCode", [
				'headers' => [
					'X-CSRF-Token' => $_SESSION['csrf_token'],
					'X-Requested-With' => 'XMLHttpRequest', // Spoof as AJAX to avoid ACL. Auth etc.
				],
				'verify' => false,
			]);

			if ($response->getStatusCode() === 200) {
				$uniqueId = getUniqueIdFromSampleCode($db, $formTable, $sampleCode);
				MiscUtility::dumpToErrorLog($uniqueId);
			} else {
				echo "<h3 align='center'>Failed to archive the latest audit trail data. Please try again.</h3>";
				$uniqueId = null;
			}
		} catch (Exception $e) {
			LoggerUtility::log('error', 'Request to archive audit data failed: ' . $e->getMessage());
			$uniqueId = null;
		}
	} else {
		$formTable = "";
		$uniqueId = "";
	}

	// Include audit-specific columns explicitly
	$auditColumns = [
		['COLUMN_NAME' => 'action'],
		['COLUMN_NAME' => 'revision'],
		['COLUMN_NAME' => 'dt_datetime']
	];
	$dbColumns = $formTable ? getColumns($db, $formTable) : [];
	$resultColumn = array_merge($auditColumns, $dbColumns); // Merge audit columns with database columns

?>
	<style>
		.current {
			display: block;
			overflow-x: auto;
			white-space: nowrap;
		}
	</style>
	<link href="/assets/css/multi-select.css" rel="stylesheet" />
	<link href="/assets/css/buttons.dataTables.min.css" rel="stylesheet" />

	<!-- Content Wrapper. Contains page content -->
	<div class="content-wrapper">
		<!-- Content Header (Page header) -->
		<section class="content-header">
			<h1><em class="fa-solid fa-clock-rotate-left"></em> <?php echo _translate("Audit Trail"); ?></h1>
			<ol class="breadcrumb">
				<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
				<li class="active"><?php echo _translate("Audit Trail"); ?></li>
			</ol>
		</section>

		<!-- Main content -->
		<section class="content">
			<div class="row">
				<div class="col-xs-12">
					<div class="box">
						<form name="form1" action="audit-trail.php" method="post" id="searchForm">
							<table aria-describedby="table" class="table" aria-hidden="true" style="margin-left:1%;margin-top:20px;width:98%;">
								<tr>
									<td><strong><?php echo _translate("Test Type"); ?>&nbsp;:</strong></td>
									<td>
										<select id="testType" name="testType" class="form-control">
											<option value="">-- Choose Test Type--</option>
											<?php foreach ($activeModules as $module): ?>
												<option value="<?php echo $module; ?>"
													<?php echo (isset($_POST['testType']) && $_POST['testType'] == $module) ? "selected='selected'" : ""; ?>>
													<?php echo strtoupper($module); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</td>
									<td>&nbsp;<strong><?php echo _translate("Sample ID"); ?>&nbsp;:</strong></td>
									<td>
										<input type="text" value="<?= htmlspecialchars($_POST['sampleCode'] ?? ''); ?>" name="sampleCode" id="sampleCode" class="form-control" />
									</td>
								</tr>
								<tr>
									<td colspan="4">
										<input type="submit" value="<?php echo _translate("Submit"); ?>" class="btn btn-success btn-sm">
										<button type="reset" class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span><?= _translate('Reset'); ?></span></button>
									</td>
								</tr>
							</table>
						</form>
					</div>
				</div>

				<?php

				$usernameFields = [
					'tested_by',
					'result_approved_by',
					'result_reviewed_by',
					'revised_by',
					'request_created_by',
					'last_modified_by'
				];

				if (!empty($uniqueId)) {
					$filePath = getAuditFilePath($_POST['testType'], $uniqueId);
					$posts = readAuditDataFromCsv($filePath);

				?>
					<div class="col-xs-12">
						<div class="box">
							<div class="box-body">
								<?php if (!empty($posts)) { ?>
									<h3> Audit Trail for Sample <?php echo htmlspecialchars((string) $sampleCode); ?></h3>
									<select name="auditColumn[]" id="auditColumn" class="" multiple="multiple">
										<?php
										$i = 0;
										foreach ($resultColumn as $col) {
											echo "<option value='$i'>{$col['COLUMN_NAME']}</option>";
											$i++;
										}
										?>
									</select>

									<table aria-describedby="table" id="auditTable" class="table-bordered table table-striped table-hover" aria-hidden="true">
										<thead>
											<tr>
												<?php
												$colArr = [];
												foreach ($resultColumn as $col) {
													$colArr[] = $col['COLUMN_NAME'];
													echo "<th>{$col['COLUMN_NAME']}</th>";
												}
												?>
											</tr>
										</thead>
										<tbody>
											<?php
											// Sort the records by revision ID
											usort($posts, function ($a, $b) {
												return $a['revision'] <=> $b['revision'];
											});

											$userCache = [];

											for ($i = 0; $i < count($posts); $i++) {
												echo "<tr>";
												foreach ($colArr as $j => $colName) {
													$value = $posts[$i][$colName];
													$previousValue = $i > 0 ? $posts[$i - 1][$colName] : null;
													$style = ($j > 3 && $previousValue !== null && $value !== $previousValue) ? 'style="background-color: orange;"' : '';

													if (in_array($colName, $usernameFields)) {
														// Check if the user information is already in the cache
														if (!isset($userCache[$value])) {
															$user = $usersService->getUserInfo($value, ['user_name']);
															$userCache[$value] = $user['user_name'] ?? $value;
														}
														$value = $userCache[$value];
													}

													echo "<td $style>" . htmlspecialchars(stripslashes($value)) . "</td>";
												}
												echo "</tr>";
											}
											?>
										</tbody>
									</table>
								<?php } else {
									echo '<h3 align="center">' . _translate("Records are not available for this Sample ID") . '</h3>';
								}
								?>
							</div>
						</div>
					</div>
					<div class="col-xs-12">
						<div class="box">
							<div class="box-body"></div>
							<?php
							$currentData = $db->rawQuery("SELECT * FROM $formTable WHERE unique_id = ?", [$uniqueId]);
							// Display Current Data if available
							if (!empty($currentData)) { ?>
								<h3> <?= _translate("Current Data for Sample"); ?> <?php echo htmlspecialchars($sampleCode ?? ''); ?></h3>
								<table id="currentDataTable" class="table-bordered table table-striped table-hover" aria-hidden="true">
									<thead>
										<tr>
											<th></th>
											<th></th>
											<th></th>
											<?php
											// Display column headers
											foreach (array_keys($currentData[0]) as $colName) {
												echo "<th>$colName</th>";
											}
											?>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td></td>
											<td></td>
											<td></td>
											<?php
											// Display row data
											foreach ($currentData[0] as $value) {
												if (in_array($colName, $usernameFields)) {
													// Check if the user information is already in the cache
													if (!isset($userCache[$value])) {
														$user = $usersService->getUserInfo($value, ['user_name']);
														$userCache[$value] = $user['user_name'] ?? $value;
													}
													$value = $userCache[$value];
												}
												echo '<td>' . htmlspecialchars(stripslashes($value)) . '</td>';
											}
											?>
										</tr>
									</tbody>
								</table>
						<?php } else {
								echo '<h3 align="center">' . _translate("Records are not available for this Sample ID") . '</h3>';
							}
						} else {
							echo '<h3 align="center">' . _translate("Records are not available for this Sample ID") . '</h3>';
						} ?>
						</div>
					</div>
			</div>
	</div>
	</section>
	</div>

	<script type="text/javascript">
		$(document).ready(function() {

			$("#auditColumn").selectize({
				plugins: ["restore_on_backspace", "remove_button", "clear_button"],
			});

			table = $("#auditTable").DataTable({
				dom: 'Bfrtip',
				buttons: [{
					extend: 'csvHtml5',
					exportOptions: {
						columns: ':visible'
					},
					text: 'Export To CSV',
					title: 'AuditTrailSample-<?php echo $sampleCode; ?>',
					extension: '.csv'
				}],
				scrollY: '250vh',
				scrollX: true,
				scrollCollapse: true,
				paging: false,
				ordering: false, // Make table non-sortable
				order: [
					[1, 'asc']
				], // Order by revision ID (second column) by default
			});

			// Initialize the single row current data table
			ctable = $('#currentDataTable').DataTable({
				paging: false,
				searching: false,
				info: false,
				ordering: false,
				scrollX: true
			});

			ctable.columns([0, 1, 2]).visible(false);


			$('#auditColumn').on("change", function(e) {
				var columns = $(this).val();

				if (columns === "" || columns == null) {
					table.columns().visible(true);
					ctable.columns().visible(true);
				} else {
					table.columns().visible(false);
					table.columns(columns).visible(true);

					ctable.columns().visible(false);
					ctable.columns(columns).visible(true);

				}

			});
		});
	</script>
<?php

} catch (Throwable $e) {
	LoggerUtility::log(
		'error',
		$e->getMessage(),
		[
			'file' => $e->getFile(),
			'line' => $e->getLine(),
			'last_db_query' => $db->getLastQuery(),
			'last_db_error' => $db->getLastError(),
			'trace' => $e
		]
	);
}

require_once APPLICATION_PATH . '/footer.php';
