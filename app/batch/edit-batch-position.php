<?php

use App\Services\BatchService;
use App\Services\TestsService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var BatchService $batchService */
$batchService = ContainerRegistry::get(BatchService::class);


$testTableData = TestsService::getAllData($_GET['type']);

$testName = $testTableData['testName'];
$table = $testTableData['tableName'];
$patientIdColumn = $testTableData['patientId'];
$primaryKeyColumn = $testTableData['primaryKey'];
$patientFirstName = $testTableData['patientFirstName'];
$patientLastName = $testTableData['patientLastName'];



// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$_GET['type'] = ($_GET['type'] == 'covid19') ? 'covid-19' : $_GET['type'];
$title = _translate($testName . " | Edit Batch Position");
$testType = (isset($_GET['testType'])) ? base64_decode((string) $_GET['testType']) : null;

require_once APPLICATION_PATH . '/header.php';


$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;


if (!isset($id) || trim($id) == '') {
	header("Location:batches.php?type=" . $_GET['type']);
}
$content = '';
$displayOrder = [];
$batchQuery = "SELECT * FROM batch_details as b_d
				INNER JOIN instruments as i_c ON i_c.instrument_id=b_d.machine
				WHERE batch_id= ?";
$batchInfo = $db->rawQuery($batchQuery, [$id]);

// Config control
$configControlQuery = "SELECT * FROM instrument_controls WHERE instrument_id= ? ";
$configControlInfo = $db->rawQuery($configControlQuery, [$batchInfo[0]['instrument_id']]);
$configControl = [];
foreach ($configControlInfo as $info) {
	$configControl[$info['test_type']]['noHouseCtrl'] = $info['number_of_in_house_controls'];
	$configControl[$info['test_type']]['noManufacturerCtrl'] = $info['number_of_manufacturer_controls'];
	$configControl[$info['test_type']]['noCalibrators'] = $info['number_of_calibrators'];
}


if (empty($batchInfo)) {
	header("Location:batches.php?type=" . $_GET['type']);
}
if (isset($batchInfo[0]['label_order']) && trim((string) $batchInfo[0]['label_order']) != '') {
	if (isset($batchInfo[0]['position_type']) && $batchInfo[0]['position_type'] == 'alpha-numeric') {
		foreach ($batchService->excelColumnRange('A', 'H') as $value) {
			foreach (range(1, 12) as $no) {
				$alphaNumeric[] = $value . $no;
			}
		}
	}
	$jsonToArray = json_decode((string) $batchInfo[0]['label_order'], true);
	for ($j = 0; $j < count($jsonToArray); $j++) {
		if (isset($batchInfo[0]['position_type']) && $batchInfo[0]['position_type'] == 'alpha-numeric') {
			$index = $alphaNumeric[$j];
		} else {
			$index = $j;
		}
		$displayOrder[] = $jsonToArray[$index];
		$xplodJsonToArray = explode("_", (string) $jsonToArray[$index]);
		if (count($xplodJsonToArray) > 1 && $xplodJsonToArray[0] == "s") {
			$sampleQuery = "SELECT sample_code, $patientIdColumn FROM $table WHERE  $primaryKeyColumn = ?";
			if (isset($_GET['type']) && $_GET['type'] == 'generic-tests') {
				$sampleQuery .= " AND test_type = $testType";
			}
			$sampleResult = $db->rawQuery($sampleQuery, [$xplodJsonToArray[1]]);
			$label = $sampleResult[0]['sample_code'] . " - " . $sampleResult[0][$patientIdColumn];
		} else {
			$label = str_replace("_", " ", (string) $jsonToArray[$index]);
			$label = str_replace("in house", "In-House", $label);
			$label = (str_replace("no of ", " ", $label));
		}
		$content .= '<li class="ui-state-default" id="' . $jsonToArray[$index] . '">' . $label . '</li>';
	}
} else {
	if (isset($configControl[$_GET['type']]['noHouseCtrl']) && trim((string) $configControl[$_GET['type']]['noHouseCtrl']) != '' && $configControl[$_GET['type']]['noHouseCtrl'] > 0) {
		foreach (range(1, $configControl[$_GET['type']]['noHouseCtrl']) as $h) {
			$displayOrder[] = "no_of_in_house_controls_" . $h;
			$content .= '<li class="ui-state-default" id="no_of_in_house_controls_' . $h . '">In-House Controls ' . $h . '</li>';
		}
	}
	if (isset($configControl[$_GET['type']]['noManufacturerCtrl']) && trim((string) $configControl[$_GET['type']]['noManufacturerCtrl']) != '' && $configControl[$_GET['type']]['noManufacturerCtrl'] > 0) {
		foreach (range(1, $configControl[$_GET['type']]['noManufacturerCtrl']) as $m) {
			$displayOrder[] = "no_of_manufacturer_controls_" . $m;
			$content .= '<li class="ui-state-default" id="no_of_manufacturer_controls_' . $m . '">Manufacturer Controls ' . $m . '</li>';
		}
	}
	if (isset($configControl[$_GET['type']]['noCalibrators']) && trim((string) $configControl[$_GET['type']]['noCalibrators']) != '' && $configControl[$_GET['type']]['noCalibrators'] > 0) {
		foreach (range(1, $configControl[$_GET['type']]['noCalibrators']) as $c) {
			$displayOrder[] = "no_of_calibrators_" . $c;
			$content .= '<li class="ui-state-default" id="no_of_calibrators_' . $c . '">Calibrators ' . $c . '</li>';
		}
	}
	$samplesQuery = "SELECT $primaryKeyColumn ,$patientIdColumn, sample_code from " . $table . " where sample_batch_id=$id ORDER BY sample_code ASC";
	$samplesInfo = $db->query($samplesQuery);
	foreach ($samplesInfo as $sample) {
		$displayOrder[] = "s_" . $sample[$primaryKeyColumn];
		$content .= '<li class="ui-state-default" id="s_' . $sample[$primaryKeyColumn] . '">' . $sample['sample_code'] . ' - ' . $sample[$patientIdColumn] . '</li>';
	}
}
?>
<style>
	#sortableRow {
		list-style-type: none;
		margin: 0 auto;
		padding: 0;
		width: 50%;
		text-align: center;
	}

	#sortableRow li {
		color: #333 !important;
		font-weight: bold;
		padding: 0.2em;
		font-size: 16px;
		border-radius: 10px;
		margin-bottom: 4px;
		cursor: move;
	}

	#sortableRow li:hover,
	#sortableRow li:active {
		background-color: skyblue;
	}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
	<!-- Content Header (Page header) -->
	<section class="content-header">
		<h1><em class="fa-solid fa-pen-to-square"></em> <?= _translate("Edit Batch Controls Position"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?= _translate("Home"); ?></a></li>
			<li class="active"><?= _translate("Batch"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<h4><strong><?= _translate("Batch Code"); ?> :
						<?php echo (isset($batchInfo[0]['batch_code'])) ? $batchInfo[0]['batch_code'] : ''; ?>
					</strong>
					<button type="button" id="updateSerialNumbersButton" class="btn btn-primary pull-right" onclick="updateSerialNumbers();return false;">Update Serial Numbers</button>
				</h4>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- <pre><?php print_r($configControl); ?></pre> -->
				<!-- form start -->
				<form class="form-horizontal" method='post' name='editBatchControlsPosition' id='editBatchControlsPosition' autocomplete="off" action="save-batch-position-helper.php">
					<div class="box-body">
						<div class="row" id="displayOrderDetails">
							<div class="col-lg-12">
								<ul id="sortableRow">
									<?php
									echo $content;
									?>
								</ul>
							</div>
						</div>
					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<input type="hidden" name="type" id="type" value="<?php echo $_GET['type']; ?>" />
						<input type="hidden" name="sortOrders" id="sortOrders" value="<?php echo implode(",", $displayOrder); ?>" />
						<input type="hidden" name="batchId" id="batchId" value="<?php echo htmlspecialchars($id); ?>" />
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
						<a href="batches.php?type=<?php echo $_GET['type']; ?>" class="btn btn-default"> Cancel</a>
					</div>
					<!-- /.box-footer -->
				</form>
				<!-- /.row -->
			</div>

		</div>
		<!-- /.box -->

	</section>
	<!-- /.content -->
</div>
<script>
	sortedTitle = [];
	$(document).ready(function() {
		function cleanArray(actual) {
			var newArray = [];
			for (var i = 0; i < actual.length; i++) {
				if (actual[i]) {
					newArray.push(actual[i]);
				}
			}
			return newArray;
		}
		updateSerialNumbers();
		$("#sortableRow").sortable({
			opacity: 0.6,
			cursor: 'move',
			update: function() {
				sortedTitle = cleanArray($(this).sortable("toArray"));
				$("#sortOrders").val("");
				$("#sortOrders").val(sortedTitle);
				$("#updateSerialNumbersButton").show();
			}
		}).disableSelection();
	});

	function validateNow() {
		flag = deforayValidator.init({
			formId: 'editBatchControlsPosition'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('editBatchControlsPosition').submit();
		}
	}

	function updateSerialNumbers() {
		$('#sortableRow li').each(function(index) {
			// Extract and update the existing item text
			var existingText = $(this).text();
			var updatedText = (index + 1) + '. ' + existingText.replace(/^\d+\. /, ''); // Replace existing serial number if present
			$(this).text(updatedText);
		});
		$("#updateSerialNumbersButton").hide();
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
