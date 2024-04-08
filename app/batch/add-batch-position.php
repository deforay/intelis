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
//Get batch controls order
$newJsonToArray = [];
if (isset($configControl[$testType]['noHouseCtrl']) && trim((string) $configControl[$testType]['noHouseCtrl']) != '' && $configControl[$testType]['noHouseCtrl'] > 0) {
	foreach (range(1, $configControl[$testType]['noHouseCtrl']) as $h) {
		$newJsonToArray[] = "no_of_in_house_controls_" . $h;
	}
}
if (isset($configControl[$testType]['noManufacturerCtrl']) && trim((string) $configControl[$testType]['noManufacturerCtrl']) != '' && $configControl[$testType]['noManufacturerCtrl'] > 0) {
	foreach (range(1, $configControl[$testType]['noManufacturerCtrl']) as $m) {
		$newJsonToArray[] = "no_of_manufacturer_controls_" . $m;
	}
}
if (isset($configControl[$testType]['noCalibrators']) && trim((string) $configControl[$testType]['noCalibrators']) != '' && $configControl[$testType]['noCalibrators'] > 0) {
	foreach (range(1, $configControl[$testType]['noCalibrators']) as $c) {
		$newJsonToArray[] = "no_of_calibrators_" . $c;
	}
}
//Get machine's prev. label order
$machine = $batchInfo[0]['machine'];
$prevLabelQuery = "SELECT label_order from batch_details as b_d WHERE b_d.machine = ? AND b_d.batch_id!= ? ORDER BY b_d.request_created_datetime DESC LIMIT 0,1";
$prevlabelInfo = $db->rawQuery($prevLabelQuery, [$machine, $id]);


if (isset($prevlabelInfo[0]['label_order']) && trim((string) $prevlabelInfo[0]['label_order']) != '') {
	$jsonToArray = json_decode((string) $prevlabelInfo[0]['label_order'], true);
	$batchControlNames = json_decode((string) $batchInfo[0]['control_names'], true);
	//echo '<pre>'; print_r($jsonToArrayControlNames); die;

	$prevDisplaySampleArray = [];
	for ($j = 0; $j < count($jsonToArray); $j++) {
		$xplodJsonToArray = explode("_", (string) $jsonToArray[$j]);
		if (count($xplodJsonToArray) > 1 && $xplodJsonToArray[0] == "s") {
			$prevDisplaySampleArray[] = $xplodJsonToArray[1];
		}
	}
	
	//Get display sample only
	$displaySampleOrderArray = [];
	$samplesQuery = "SELECT $primaryKeyColumn, $patientIdColumn, sample_code
						FROM $table
						WHERE sample_batch_id= ?
						ORDER BY sample_code ASC";
	$samplesInfo = $db->rawQuery($samplesQuery, [$id]);
	foreach ($samplesInfo as $sample) {
		$displaySampleOrderArray[] = $sample[$primaryKeyColumn];
	}
	//Set content
	$sCount = 0;
	$displayNonSampleArray = [];
	$displaySampleArray = [];
	//echo 
	for ($j = 0; $j < count($jsonToArray); $j++) {
		$xplodJsonToArray = explode("_", (string) $jsonToArray[$j]);
		if (count($xplodJsonToArray) > 1 && $xplodJsonToArray[0] == "s") {
			if (isset($displaySampleOrderArray[$sCount]) && $displaySampleOrderArray[$sCount] > 0) {
				if ($sCount <= $prevDisplaySampleArray) {
					$displayOrder[] = 's_' . $displaySampleOrderArray[$sCount];
					$displaySampleArray[] = $displaySampleOrderArray[$sCount];
					$sampleQuery = "SELECT sample_code, $patientIdColumn from $table WHERE $primaryKeyColumn = ?";
					$sampleResult = $db->rawQuery($sampleQuery, [$displaySampleOrderArray[$sCount]]);
					$label = $sampleResult[0]['sample_code'] . ' - ' . $sampleResult[0][$patientIdColumn];
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
				$content .= '<li class="ui-state-default" id="' . $jsonToArray[$j] . '">' . $label . '</li>';
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
		if(isset($batchControlNames[$remainNewArray[$n]]) && $batchControlNames[$remainNewArray[$n]]!=""){
			$existingValue = $batchControlNames[$remainNewArray[$n]];
			$liLabel = $existingValue;
		}
		else{
			$liLabel = $label;
			$existingValue = "";
		}
		$newContent .= '<li class="ui-state-default" id="' . $remainNewArray[$n] . '">' . $liLabel . '</li>';
		
		$labelNewContent .= ' <tr><th>'.$label.' :</th><td> <input class="form-control" type="text" name="controls['.$remainNewArray[$n].']" value="'.$existingValue.'" placeholder="Enter label name"/></td></tr>';
	}
	//For new samples
	for ($ns = 0; $ns < count($remainSampleNewArray); $ns++) {
		$displayOrder[] = 's_' . $remainSampleNewArray[$ns];
		$sampleQuery = "SELECT sample_code, $patientIdColumn from $table WHERE $primaryKeyColumn = ?";
		$sampleResult = $db->rawQuery($sampleQuery, [$remainSampleNewArray[$ns]]);
		$label = $sampleResult[0]['sample_code'] . ' - ' . $sampleResult[0][$patientIdColumn];
		$newContent .= '<li class="ui-state-default" id="s_' . $remainSampleNewArray[$ns] . '">' . $label . '</li>';
	}
} else {
	if (isset($configControl[$testType]['noHouseCtrl']) && trim((string) $configControl[$testType]['noHouseCtrl']) != '' && $configControl[$testType]['noHouseCtrl'] > 0) {
		foreach (range(1, $configControl[$testType]['noHouseCtrl']) as $h) {
			$displayOrder[] = "no_of_in_house_controls_" . $h;
			$content .= '<li class="ui-state-default" id="no_of_in_house_controls_' . $h . '">In-House Control ' . $h . '</li>';
		}
	}
	if (isset($configControl[$testType]['noManufacturerCtrl']) && trim((string) $configControl[$testType]['noManufacturerCtrl']) != '' && $configControl[$testType]['noManufacturerCtrl'] > 0) {
		foreach (range(1, $configControl[$testType]['noManufacturerCtrl']) as $m) {
			$displayOrder[] = "no_of_manufacturer_controls_" . $m;
			$content .= '<li class="ui-state-default" id="no_of_manufacturer_controls_' . $m . '">Manufacturer Control ' . $m . '</li>';
		}
	}
	if (isset($configControl[$testType]['noCalibrators']) && trim((string) $configControl[$testType]['noCalibrators']) != '' && $configControl[$testType]['noCalibrators'] > 0) {
		foreach (range(1, $configControl[$testType]['noCalibrators']) as $c) {
			$displayOrder[] = "no_of_calibrators_" . $c;
			$content .= '<li class="ui-state-default" id="no_of_calibrators_' . $c . '">Calibrator ' . $c . '</li>';
		}
	}
	$samplesQuery = "SELECT $primaryKeyColumn, $patientIdColumn, sample_code
					FROM  $table
					WHERE sample_batch_id=$id
					ORDER BY sample_code ASC";
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
				<button type="button" id="updateSerialNumbersButton" class="btn btn-primary pull-right" onclick="updateSerialNumbers();return false;">Update Serial Numbers</button>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- <pre><?php print_r($configControl); ?></pre> -->
				<!-- form start -->
				<form class="form-horizontal" method='post' name='addBatchControlsPosition' id='addBatchControlsPosition' autocomplete="off" action="save-batch-position-helper.php">
					<div class="box-body">
						<div class="row" id="displayOrderDetails">
							<div class="col-lg-12">
								<ul id="sortableRow">
									<?php
										echo $content . $newContent;
									?>
								</ul>
								<table class="table" style="width:50%; margin-left:300px;">
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
</script>
<?php


require_once APPLICATION_PATH . '/footer.php';
