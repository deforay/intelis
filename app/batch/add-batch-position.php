<?php

use App\Services\TestsService;
use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$sortBy = $_GET['sortBy'] ?? 'sampleCode';

$sortType = match ($_POST['sortType']) {
	'a', 'asc' => 'asc',
	'd', 'desc' => 'desc',
	default => 'asc',
};


$orderBy = match ($sortBy) {
	'sampleCode' => 'sample_code',
	'lastModified' => 'last_modified_datetime',
	'requestCreated' => 'request_created_datetime',
	'labAssignedCode' => 'lab_assigned_code',
	default => 'sample_code',
};

$orderBy = $orderBy . ' ' . $sortType;


$testTableData = TestsService::getAllData($_GET['type']);

$testName = $testTableData['testName'];
$table = $testTableData['tableName'];
$patientIdColumn = $testTableData['patientId'];
$primaryKeyColumn = $testTableData['primaryKey'];
$patientFirstName = $testTableData['patientFirstName'];
$patientLastName = $testTableData['patientLastName'];


$testType = ($_GET['type'] == 'covid19') ? 'covid-19' : $_GET['type'];

$title = _translate($testName . " | Add Batch Position");
require_once APPLICATION_PATH . '/header.php';


$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;

if (!isset($id) || trim($id) == '') {
	header("Location:batches.php?type=" . $_GET['type']);
}

$content = '';
$newContent = '';
$labelNewContent = '';
$displayOrder = [];
$batchQuery = "SELECT * FROM batch_details as b_d
				INNER JOIN instruments as i_c ON i_c.instrument_id=b_d.machine
				WHERE batch_id= ? ";
$batchInfo = $db->rawQuery($batchQuery, [$id]);

// Config control
$configControlQuery = "SELECT * FROM instrument_controls WHERE instrument_id= ? ";
$configControlInfo = $db->rawQuery($configControlQuery, [$batchInfo[0]['machine']]);
$configControl = [];
foreach ($configControlInfo as $info) {
	$configControl[$info['test_type']]['noHouseCtrl'] = $info['number_of_in_house_controls'];
	$configControl[$info['test_type']]['noManufacturerCtrl'] = $info['number_of_manufacturer_controls'];
	$configControl[$info['test_type']]['noCalibrators'] = $info['number_of_calibrators'];
}

if (empty($batchInfo)) {
	header("Location:batches.php?type=" . $_GET['type']);
}
//Get batch controls order
$newJsonToArray = [];
if (isset($configControl[$testType]['noHouseCtrl']) && trim((string) $configControl[$testType]['noHouseCtrl']) != '' && $configControl[$testType]['noHouseCtrl'] > 0) {
	foreach (range(1, $configControl[$testType]['noHouseCtrl']) as $h) {
		$newJsonToArray[] = "in_house_controls_" . $h;
	}
}
if (isset($configControl[$testType]['noManufacturerCtrl']) && trim((string) $configControl[$testType]['noManufacturerCtrl']) != '' && $configControl[$testType]['noManufacturerCtrl'] > 0) {
	foreach (range(1, $configControl[$testType]['noManufacturerCtrl']) as $m) {
		$newJsonToArray[] = "manufacturer_controls_" . $m;
	}
}
if (isset($configControl[$testType]['noCalibrators']) && trim((string) $configControl[$testType]['noCalibrators']) != '' && $configControl[$testType]['noCalibrators'] > 0) {
	foreach (range(1, $configControl[$testType]['noCalibrators']) as $c) {
		$newJsonToArray[] = "calibrators_" . $c;
	}
}

//Get machine's prev. label order
$machine = $batchInfo[0]['machine'];
$prevLabelQuery = "SELECT label_order from batch_details as b_d WHERE b_d.machine = ? AND b_d.batch_id!= ? ORDER BY b_d.request_created_datetime DESC LIMIT 0,1";
$prevlabelInfo = $db->rawQuery($prevLabelQuery, [$machine, $id]);

$prevMachineControlQuery = "SELECT control_names from batch_details WHERE machine = ? AND control_names IS NOT NULL  ORDER BY batch_id DESC LIMIT 0,1";
$prevMachineControlInfo = $db->rawQuery($prevMachineControlQuery, [$machine]);

$prevBatchControlNames = json_decode((string) $prevMachineControlInfo[0]['control_names'], true);
if (!empty($batchInfo[0]['control_names'])) {
	$batchControlNames = json_decode((string) $batchInfo[0]['control_names'], true);
} else {
	$batchControlNames = json_decode((string) $prevMachineControlInfo[0]['control_names'], true);
}
//echo '<pre>'; print_r($batchControlNames); die;
if (isset($prevlabelInfo[0]['label_order']) && trim((string) $prevlabelInfo[0]['label_order']) != '') {

	$jsonToArray = json_decode((string) $prevlabelInfo[0]['label_order'], true);

	$prevDisplaySampleArray = [];
	for ($j = 0; $j < count($jsonToArray); $j++) {
		$xplodJsonToArray = explode("_", (string) $jsonToArray[$j]);
		if (count($xplodJsonToArray) > 1 && $xplodJsonToArray[0] == "s") {
			$prevDisplaySampleArray[] = $xplodJsonToArray[1];
		}
	}

	//Get display sample only
	$displaySampleOrderArray = [];
	$samplesQuery = "SELECT $primaryKeyColumn,
						$patientIdColumn,
						sample_code,
						lab_assigned_code
						FROM $table
						WHERE sample_batch_id= ?
						ORDER BY $orderBy";
	$samplesInfo = $db->rawQuery($samplesQuery, [$id]);
	foreach ($samplesInfo as $sample) {
		$displaySampleOrderArray[] = $sample[$primaryKeyColumn];
	}
	//Set content
	$sCount = 0;
	$displayNonSampleArray = [];
	$displaySampleArray = [];
	for ($j = 0; $j < count($jsonToArray); $j++) {
		$xplodJsonToArray = explode("_", (string) $jsonToArray[$j]);
		if (count($xplodJsonToArray) > 1 && $xplodJsonToArray[0] == "s") {
			if (isset($displaySampleOrderArray[$sCount]) && $displaySampleOrderArray[$sCount] > 0) {
				if ($sCount <= $prevDisplaySampleArray) {
					$displayOrder[] = 's_' . $displaySampleOrderArray[$sCount];
					$displaySampleArray[] = $displaySampleOrderArray[$sCount];
					$sampleQuery = "SELECT sample_code, lab_assigned_code, $patientIdColumn from $table WHERE $primaryKeyColumn = ?";
					$sampleResult = $db->rawQuery($sampleQuery, [$displaySampleOrderArray[$sCount]]);
					$sampleResult[0]['sample_code'] = (isset($sampleResult[0]['lab_assigned_code']) ? $sampleResult[0]['sample_code'] . ' | ' . $sampleResult[0]['lab_assigned_code'] : $sampleResult[0]['sample_code']);
					$label = $sampleResult[0]['sample_code'] . ' | ' . $sampleResult[0][$patientIdColumn];
					$content .= '<li class="ui-state-default" id="s_' . $displaySampleOrderArray[$sCount] . '">' . $label . '</li>';
					$sCount++;
				}
			}
		} else {
			if (in_array($jsonToArray[$j], $newJsonToArray)) {
				$displayOrder[] = $jsonToArray[$j];
				$displayNonSampleArray[] = $jsonToArray[$j];
				$label = str_replace("_", " ", (string) $jsonToArray[$j]);
				$label = str_replace("in house", "In-House", $label);
				$label = (str_replace("no of ", " ", $label));
				if (isset($batchControlNames[$jsonToArray[$j]]) && $batchControlNames[$jsonToArray[$j]] != "") {
					$existingValue = $batchControlNames[$jsonToArray[$j]];
					$liLabel = $existingValue;
				} else {
					$liLabel = $label;
					$existingValue = "";
				}
				$content .= '<li class="ui-state-default" id="' . $jsonToArray[$j] . '">' . $liLabel . '</li>';
				$labelNewContent .= ' <tr><th>' . $label . ' :</th><td> <input class="form-control" type="text" name="controls[' . $jsonToArray[$j] . ']" value="' . $existingValue . '" placeholder="Enter label name"/></td></tr>';
			}
		}
	}
	//Add new content
	$remainNewArray = array_values(array_diff($newJsonToArray, $displayNonSampleArray));
	$remainSampleNewArray = array_values(array_diff($displaySampleOrderArray, $displaySampleArray));
	//For new controls
	for ($n = 0; $n < count($remainNewArray); $n++) {
		$displayOrder[] = $remainNewArray[$n];
		$label = str_replace("_", " ", $remainNewArray[$n]);
		$label = str_replace("in house", "In-House", $label);
		$label = (str_replace("no of ", " ", $label));
		if (isset($batchControlNames[$remainNewArray[$n]]) && $batchControlNames[$remainNewArray[$n]] != "") {
			$existingValue = $batchControlNames[$remainNewArray[$n]];
			$liLabel = $existingValue;
		} else {
			$liLabel = $label;
			$existingValue = "";
		}
		$newContent .= '<li class="ui-state-default" id="' . $remainNewArray[$n] . '">' . $liLabel . '</li>';

		$labelNewContent .= ' <tr><th>' . $label . ' :</th><td> <input class="form-control" type="text" name="controls[' . $remainNewArray[$n] . ']" value="' . $existingValue . '" placeholder="Enter label name"/></td></tr>';
	}
	//For new samples
	for ($ns = 0; $ns < count($remainSampleNewArray); $ns++) {
		$displayOrder[] = 's_' . $remainSampleNewArray[$ns];
		$sampleQuery = "SELECT sample_code, lab_assigned_code, $patientIdColumn from $table WHERE $primaryKeyColumn = ?";
		$sampleResult = $db->rawQuery($sampleQuery, [$remainSampleNewArray[$ns]]);
		$sampleResult[0]['sample_code'] = (isset($sampleResult[0]['lab_assigned_code']) ? $sampleResult[0]['sample_code'] . ' | ' . $sampleResult[0]['lab_assigned_code'] : $sampleResult[0]['sample_code']);
		$label = $sampleResult[0]['sample_code'] . ' | ' . $sampleResult[0][$patientIdColumn];
		$newContent .= '<li class="ui-state-default" id="s_' . $remainSampleNewArray[$ns] . '">' . $label . '</li>';
	}
} else {
	//echo '<pre>'; print_r($batchControlNames); die;
	$existingValue = $batchControlNames;
	if (isset($configControl[$testType]['noHouseCtrl']) && trim((string) $configControl[$testType]['noHouseCtrl']) != '' && $configControl[$testType]['noHouseCtrl'] > 0) {
		foreach (range(1, $configControl[$testType]['noHouseCtrl']) as $h) {
			$displayOrder[] = "in_house_controls_" . $h;
			$label = "";
			if (!empty($batchControlNames) && array_key_exists("in_house_controls_" . $h, $batchControlNames)) {
				$label = $batchControlNames["in_house_controls_" . $h];
			}
			$content .= '<li class="ui-state-default" id="in_house_controls_' . $h . '">In-House Control ' . $h . '</li>';
			$labelNewContent .= ' <tr><th>In-House Control ' . $h . ':</th><td> <input class="form-control" type="text" name="controls[in_house_controls_' . $h . ']" value="' . $existingValue . '" placeholder="Enter label name"/></td></tr>';
		}
	}
	if (isset($configControl[$testType]['noManufacturerCtrl']) && trim((string) $configControl[$testType]['noManufacturerCtrl']) != '' && $configControl[$testType]['noManufacturerCtrl'] > 0) {
		foreach (range(1, $configControl[$testType]['noManufacturerCtrl']) as $m) {
			$displayOrder[] = "manufacturer_controls_" . $m;
			if (!empty($batchControlNames) && array_key_exists("manufacturer_controls_" . $m, $batchControlNames)) {
				$label = $batchControlNames["manufacturer_controls_" . $m];
			}
			$content .= '<li class="ui-state-default" id="manufacturer_controls_' . $m . '"> ' . $label . '</li>';
			$labelNewContent .= ' <tr><th>Manufacturer Control ' . $m . ' :</th><td> <input class="form-control" type="text" name="controls[manufacturer_controls_' . $m . ']" value="' . $label . '" placeholder="Enter label name"/></td></tr>';
		}
	}
	if (isset($configControl[$testType]['noCalibrators']) && trim((string) $configControl[$testType]['noCalibrators']) != '' && $configControl[$testType]['noCalibrators'] > 0) {
		foreach (range(1, $configControl[$testType]['noCalibrators']) as $c) {
			$displayOrder[] = "calibrators_" . $c;
			$content .= '<li class="ui-state-default" id="calibrators_' . $c . '">Calibrator ' . $c . '</li>';
			$labelNewContent .= ' <tr><th>' . $label . ' :</th><td> <input class="form-control" type="text" name="controls[calibrators_' . $c . ']" value="' . $existingValue . '" placeholder="Enter label name"/></td></tr>';
		}
	}
	$samplesQuery = "SELECT $primaryKeyColumn, $patientIdColumn, sample_code
					FROM  $table
					WHERE sample_batch_id=$id
					ORDER BY $orderBy";
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
		<h1><em class="fa-solid fa-pen-to-square"></em> <?= _translate("Add Batch Controls Position"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?= _translate("Home"); ?></a></li>
			<li class="active"><?= _translate("Batch"); ?></li>
		</ol>
	</section>

	<!-- Main content -->
	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<h4><strong><?= _translate("Batch Code"); ?> : <?php echo $batchInfo[0]['batch_code']; ?></strong></h4>
				<div class="row">
					<div class="col-lg-4">
						<select class="form-control" id="sortBy">
							<option <?= $sortBy == 'requestCreated' ? "selected='selected'" : '' ?> value="requestCreated"><?= _translate("Request Created"); ?></option>
							<option <?= $sortBy == 'lastModified' ? "selected='selected'" : '' ?> value="lastModified"><?= _translate("Last Modified"); ?></option>
							<option <?= $sortBy == 'sampleCode' ? "selected='selected'" : '' ?> value="sampleCode"><?= _translate("Sample Code"); ?></option>
							<option <?= $sortBy == 'labAssignedCode' ? "selected='selected'" : '' ?> value="labAssignedCode"><?= _translate("Lab Assigned Code"); ?></option>
						</select>
					</div>
					<div class="col-lg-2">
						<select class="form-control" id="sortType">
							<option <?= $sortType == 'asc' ? "selected='selected'" : '' ?> value="asc"><?= _translate("Ascending"); ?></option>
							<option <?= $sortType == 'desc' ? "selected='selected'" : '' ?> value="desc"><?= _translate("Descending"); ?></option>
						</select>
					</div>
					<div class="col-lg-2 col-md-2 col-xs-2">
						<button type="button" class="btn btn-primary pull-right" onclick="sortBatch();return false;">Reset Sorting</button>
					</div>
				</div>
			</div>


			<!-- /.box-header -->
			<div class="box-body" style="margin-top:10px;">
				<button style="margin-bottom:30px;" type="button" id="updateSerialNumbersButton" class="btn btn-primary pull-right" onclick="updateSerialNumbers();return false;">Update Serial Numbers</button>
				<form class="form-horizontal" method='post' name='addBatchControlsPosition' id='addBatchControlsPosition' autocomplete="off" action="save-batch-position-helper.php">
					<div class="box-body">
						<div class="row" id="displayOrderDetails">
							<div class="col-lg-12">
								<ul id="sortableRow">
									<?php
									echo $content . $newContent;
									?>
								</ul>
								<table class="table table-striped" style="width:50%; margin:3em auto;">
									<caption><strong><?= _translate("Labels for Controls/Calibrators") ?></strong></caption>
									<?php echo $labelNewContent; ?>
								</table>
							</div>
						</div>
					</div>
					<!-- /.box-body -->
					<div class="box-footer">
						<input type="hidden" name="type" id="type" value="<?php echo $_GET['type']; ?>" />
						<input type="hidden" name="sortOrders" id="sortOrders" value="<?php echo implode(",", $displayOrder); ?>" />
						<input type="hidden" name="batchId" id="batchId" value="<?php echo htmlspecialchars($id); ?>" />
						<input type="hidden" name="positions" id="positions" value="<?php echo htmlspecialchars((string) $_GET['position']); ?>" />
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
						<a href="/batch/batches.php?type=<?php echo $_GET['type']; ?>" class="btn btn-default"> Cancel</a>
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
			formId: 'addBatchControlsPosition'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('addBatchControlsPosition').submit();
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

	function sortBatch() {
		let sortBy = $("#sortBy").val();
		let sortType = $("#sortType").val();


		let url = new URL(window.location.href);
		let params = new URLSearchParams(url.search);
		params.set('sortBy', sortBy);
		params.set('sortType', sortType);

		url.search = params.toString();
		window.location.href = url.toString();
	}
</script>
<?php


require_once APPLICATION_PATH . '/footer.php';
