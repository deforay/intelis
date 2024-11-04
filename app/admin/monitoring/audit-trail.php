<?php

use App\Services\TestsService;
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

	if (isset($_POST['testType'])) {
		$formTable = TestsService::getTestTableName($_POST['testType']);
		$sampleCode = $_POST['sampleCode'];
		$uniqueId = getUniqueIdFromSampleCode($db, $formTable, $sampleCode);
	} else {
		$formTable = "";
		$sampleCode = "";
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
										<input type="text" value="<?= htmlspecialchars((string) $_POST['sampleCode']); ?>" name="sampleCode" id="sampleCode" class="form-control" />
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
				if (!empty($uniqueId)) {
					$filePath = getAuditFilePath($_POST['testType'], $uniqueId);
					$posts = readAuditDataFromCsv($filePath);

					if (!empty($posts)) {
				?>
						<div class="col-xs-12">
							<div class="box">
								<div class="box-body">
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
											// Sort records by revision ID
											usort($posts, function ($a, $b) {
												return $a['revision'] <=> $b['revision'];
											});

											$excludeColumns = ['action', 'revision', 'dt_datetime']; // Columns to exclude from change highlighting

											for ($i = 0; $i < count($posts); $i++) {
												echo "<tr>";
												foreach ($colArr as $j => $colName) {
													$value = $posts[$i][$colName];
													$previousValue = $i > 0 ? $posts[$i - 1][$colName] : null;

													// Check if the column is not in the exclude list before highlighting changes
													$style = (!in_array($colName, $excludeColumns) && $previousValue !== null && $value !== $previousValue)
														? 'style="background-color: orange;"'
														: '';

													echo "<td $style>" . htmlspecialchars($value) . "</td>";
												}
												echo "</tr>";
											}
											?>
										</tbody>
									</table>
								</div>
							</div>
						</div>
				<?php
					} else {
						echo '<h3 align="center">Records are not available for this sample ID. Please enter a valid sample ID.</h3>';
					}

					// Fetch the current row from the form_x table for the given sample
					$currentData = $db->rawQuery("SELECT * FROM $formTable WHERE unique_id = ?", [$uniqueId]);

					if (!empty($currentData)) {
						echo '<div class="col-xs-12"><div class="box"><div class="box-body">';
						echo '<h3>Current Data for Sample ' . htmlspecialchars((string) $sampleCode) . '</h3>';
						echo '<table id="currentDataTable" class="table-bordered table table-striped table-hover" aria-hidden="true"><thead><tr>';

						// Display column headers
						foreach (array_keys($currentData[0]) as $colName) {
							echo "<th>$colName</th>";
						}
						echo '</tr></thead><tbody><tr>';

						// Display row data
						foreach ($currentData[0] as $value) {
							echo '<td>' . htmlspecialchars($value) . '</td>';
						}
						echo '</tr></tbody></table>';

						echo '</div></div></div>';
					} else {
						echo '<h3 align="center">No current data available for this sample ID in the main table.</h3>';
					}
				} else {
					echo '<h3 align="center">No unique ID found for the provided sample ID.</h3>';
				} ?>
			</div>
		</section>
	</div>

	<script src="/assets/js/dataTables.buttons.min.js"></script>
	<script src="/assets/js/jszip.min.js"></script>
	<script type="text/javascript">
		$(document).ready(function() {
			$("#auditColumn").selectize({
				plugins: ["restore_on_backspace", "remove_button", "clear_button"],
			});
			table = $("#auditTable").DataTable({
				ordering: false, // Make table non-sortable
				scrollY: '250vh',
				scrollX: true,
				scrollCollapse: true,
				paging: false,
			});

			// Initialize the single row current data table
			$('#currentDataTable').DataTable({
				paging: false,
				searching: false,
				info: false,
				ordering: false,
				scrollX: true
			});

			$('#auditColumn').on("change", function(e) {
				var columns = $(this).val();
				if (columns === "" || columns == null) {
					table.columns().visible(true);
				} else {
					table.columns().visible(false);
					table.columns(columns).visible(true);
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
