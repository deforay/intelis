<?php

use App\Services\BatchService;
use App\Services\TestsService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var BatchService $batchService */
$batchService = ContainerRegistry::get(BatchService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$type = $_GET['type'];
$testType = $_GET['type'];

$testTableData = TestsService::getAllData($testType);

$testName = $testTableData['testName'];
$table = $testTableData['tableName'];
$patientIdColumn = $testTableData['patientId'];
$primaryKeyColumn = $testTableData['primaryKey'];
$patientFirstName = $testTableData['patientFirstName'];
$patientLastName = $testTableData['patientLastName'];


$testType = ($testType == 'covid19') ? 'covid-19' : $testType;
$title = _translate($testName . " | Edit Batch Position");
$testType = (isset($_GET['testType'])) ? base64_decode((string) $_GET['testType']) : null;

require_once APPLICATION_PATH . '/header.php';


$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;


if (!isset($id) || trim($id) == '') {
	header("Location:batches.php?type=" . $testType);
}
$content = '';
$displayOrder = [];
$batchQuery = "SELECT * FROM batch_details as b_d
				INNER JOIN instruments as i_c ON i_c.instrument_id=b_d.machine
				WHERE batch_id= ?";
$batchInfo = $db->rawQuery($batchQuery, [$id]);

$batchAttributes = json_decode((string) $batchInfo[0]['batch_attributes']);

$sortBy = $batchAttributes->sort_by ?? 'sampleCode';

$sortType = match ($batchAttributes->sort_type) {
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

$samplesQry = "SELECT sample_code,$patientIdColumn,$primaryKeyColumn FROM form_vl WHERE sample_batch_id = $id";
$samplesResult = $db->rawQuery($samplesQry);
$samplesCount = count($samplesResult);

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
	header("Location:batches.php?type=" . $testType);
}

$prevMachineControlQuery = "SELECT control_names from batch_details WHERE machine = ? AND control_names IS NOT NULL  ORDER BY batch_id DESC LIMIT 0,1";
$prevMachineControlInfo = $db->rawQuery($prevMachineControlQuery, [$machine]);

$prevBatchControlNames = json_decode((string) $prevMachineControlInfo[0]['control_names'], true);
if (!empty($batchInfo[0]['control_names'])) {
	$batchControlNames = json_decode((string) $batchInfo[0]['control_names'], true);
} else {
	$batchControlNames = json_decode((string) $prevMachineControlInfo[0]['control_names'], true);
}
//echo '<pre>'; print_r($batchControlNames); die;
if (isset($batchInfo[0]['label_order']) && trim((string) $batchInfo[0]['label_order']) != '') {
	if (isset($batchInfo[0]['position_type']) && $batchInfo[0]['position_type'] == 'alpha-numeric') {
		foreach ($batchService->excelColumnRange('A', 'H') as $value) {
			foreach (range(1, 12) as $no) {
				$alphaNumeric[] = $value . $no;
			}
		}
	}
	$jsonToArray = json_decode((string) $batchInfo[0]['label_order'], true);
	$labelControls = "";
	if(isset($batchInfo[0]['control_names']) && $batchInfo[0]['control_names'] != 'null'){
		$controlNames = json_decode((string) $batchInfo[0]['control_names'], true);
		$controlsCount = count($controlNames);
	}
	else{
		$labelControls = preg_grep("/^no_of_/i", $jsonToArray);
	}


	if(isset($jsonToArray) && (count($jsonToArray) != ($samplesCount+$controlsCount))){
		foreach($samplesResult as $sample){
			$displayOrder[] = "s_" . $sample[$primaryKeyColumn];
			$label = $sample['sample_code'] . " - " . $sample[$patientIdColumn];
			
			$content .= '<li class="ui-state-default" id="s_' . $sample[$primaryKeyColumn] . '">' . $label . '</li>';
		}
		
			if(isset($controlsCount) && $controlsCount > 0){
				foreach($controlNames as $key=>$value){
					$displayOrder[] = $value;

					$clabel = str_replace("in house", "In-House", $value);
					$clabel = (str_replace("no of ", " ", $clabel));

					if (isset($batchControlNames[$key]) && $batchControlNames[$key] != "") {
						$existingValue = $batchControlNames[$key];
						$liLabel = $existingValue;
					} else {
						$liLabel = $clabel;
						$existingValue = "";
					}

					$content .= '<li class="ui-state-default" id="'.$key.'">' . $liLabel . '</li>';
					$labelNewContent .= ' <tr><th>' . $liLabel . ' :</th><td> <input class="form-control" type="text" name="controls[' . $key . ']" value="' . $existingValue . '" placeholder="Enter label name"/></td></tr>';

				}
			}
			else{
				foreach($labelControls as $value){
					$displayOrder[] = $value;
					$str = str_replace("_", " ", (string) $value);

					if(substr_count($str, 'in house') > 0){
						$clabel = str_replace("in house", "In-House", $value);
						$clabel = (str_replace("no of ", " ", $clabel));
					}
					elseif(substr_count($str, 'manufacturer controls') > 0){
						$clabel = str_replace("manufacturer control", "Manufacturer Control", $value);
						$clabel = (str_replace("no of ", " ", $clabel));
					}
					elseif(substr_count($str, 'calibrator') > 0){
						$clabel = str_replace("calibrator", "Calibrator", $value);
						$clabel = (str_replace("no of ", " ", $clabel));
					}
					$content .= '<li class="ui-state-default" id="'.$value.'">' . $clabel . '</li>';
					$labelNewContent .= ' <tr><th>' . $clabel . ' :</th><td> <input class="form-control" type="text" name="controls[' . $clabel . ']" value="' . $existingValue . '" placeholder="Enter label name"/></td></tr>';

				}
			}
	}
	else{
		for ($j = 0; $j < count($jsonToArray); $j++) {
			if (isset($batchInfo[0]['position_type']) && $batchInfo[0]['position_type'] == 'alpha-numeric') {
				$index = $alphaNumeric[$j];
			} else {
				$index = $j;
			}
			$displayOrder[] = $jsonToArray[$index];
			$xplodJsonToArray = explode("_", (string) $jsonToArray[$index]);
			if (count($xplodJsonToArray) > 1 && $xplodJsonToArray[0] == "s") {
				$sampleQuery = "SELECT sample_code, $patientIdColumn FROM $table WHERE  $primaryKeyColumn = ? ";
				if (isset($testType) && $testType == 'generic-tests') {
					$sampleQuery .= " AND test_type = $testType";
				}
				$sampleResult = $db->rawQuery($sampleQuery, [$xplodJsonToArray[1]]);
				$label = $sampleResult[0]['sample_code'] . " - " . $sampleResult[0][$patientIdColumn];
				$content .= '<li class="ui-state-default" id="' . $jsonToArray[$index] . '">' . $label . '</li>';

			} else {

				$label = str_replace("_", " ", (string) $jsonToArray[$index]);

					if(substr_count($label, 'in house') > 0){
						$label = str_replace("in house", "In-House", $label);
						$label = (str_replace("no of ", " ", $label));
					}
					elseif(substr_count($str, 'manufacturer controls') > 0){
						$label = str_replace("manufacturer control", "Manufacturer Control", $label);
						$label = (str_replace("no of ", " ", $label));
					}
					elseif(substr_count($str, 'calibrator') > 0){
						$label = str_replace("calibrator", "Calibrator", $label);
						$label = (str_replace("no of ", " ", $label));
					}

				if (isset($batchControlNames[$jsonToArray[$index]]) && $batchControlNames[$jsonToArray[$index]] != "") {
					$existingValue = $batchControlNames[$jsonToArray[$index]];
					$liLabel = $existingValue;
				} else {
					$liLabel = $label;
					$existingValue = "";
				}
				$labelNewContent .= ' <tr><th>' . $jsonToArray[$index] . ' :</th><td> <input class="form-control" type="text" name="controls[' . $jsonToArray[$index] . ']" value="' . $existingValue . '" placeholder="Enter label name"/></td></tr>';
				$content .= '<li class="ui-state-default" id="' . $jsonToArray[$index] . '">' . $liLabel . '</li>';

			}

		}
	}
} else {
	if (isset($configControl[$testType]['noHouseCtrl']) && trim((string) $configControl[$testType]['noHouseCtrl']) != '' && $configControl[$testType]['noHouseCtrl'] > 0) {
		foreach (range(1, $configControl[$testType]['noHouseCtrl']) as $h) {
			$displayOrder[] = "in_house_controls_" . $h;
			$content .= '<li class="ui-state-default" id="in_house_controls_' . $h . '">In-House Control ' . $h . '</li>';
		}
	}
	if (isset($configControl[$testType]['noManufacturerCtrl']) && trim((string) $configControl[$testType]['noManufacturerCtrl']) != '' && $configControl[$testType]['noManufacturerCtrl'] > 0) {
		foreach (range(1, $configControl[$testType]['noManufacturerCtrl']) as $m) {
			$displayOrder[] = "manufacturer_controls_" . $m;
			$content .= '<li class="ui-state-default" id="manufacturer_controls_' . $m . '">Manufacturer Control ' . $m . '</li>';
		}
	}
	if (isset($configControl[$testType]['noCalibrators']) && trim((string) $configControl[$testType]['noCalibrators']) != '' && $configControl[$testType]['noCalibrators'] > 0) {
		foreach (range(1, $configControl[$testType]['noCalibrators']) as $c) {
			$displayOrder[] = "calibrators_" . $c;
			$content .= '<li class="ui-state-default" id="calibrators_' . $c . '">Calibrator ' . $c . '</li>';
		}
	}
	$samplesQuery = "SELECT $primaryKeyColumn ,$patientIdColumn, sample_code FROM $table WHERE sample_batch_id=? ORDER BY $orderBy";
	$samplesInfo = $db->rawQuery($samplesQuery, [$id]);
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
				<form class="form-horizontal" method='post' name='editBatchControlsPosition' id='editBatchControlsPosition' autocomplete="off" action="save-batch-position-helper.php">
					<div class="box-body">
						<div class="row" id="displayOrderDetails">
							<div class="col-lg-12">
								<ul id="sortableRow">
									<?php
									echo $content;
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
						<input type="hidden" name="type" id="type" value="<?php echo $type; ?>" />
						<input type="hidden" name="sortOrders" id="sortOrders" value="<?= implode(",", $displayOrder); ?>" />
						<input type="hidden" name="batchId" id="batchId" value="<?php echo htmlspecialchars($id); ?>" />
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
						<a href="batches.php?type=<?php echo $type; ?>" class="btn btn-default"> Cancel</a>
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
