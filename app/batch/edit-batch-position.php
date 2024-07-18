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
$testType = isset($_GET['testType']) ? base64_decode((string)$_GET['testType']) : null;

require_once APPLICATION_PATH . '/header.php';

$id = isset($_GET['id']) ? base64_decode((string)$_GET['id']) : null;

if (!isset($id) || trim($id) == '') {
	header("Location:batches.php?type=$testType");
	exit;
}

$batchInfo = $batchService->getBatchInfo($id);
if (empty($batchInfo)) {
	header("Location:batches.php?type=$testType");
	exit;
}

$batchAttributes = json_decode((string)$batchInfo['batch_attributes']);
$sortBy = $batchAttributes->sort_by ?? 'sampleCode';
$sortType = $batchService->getSortType($batchAttributes->sort_type);
$orderBy = $batchService->getOrderBy($sortBy, $sortType);
$samplesResult = $batchService->getSamplesByBatchId($table, $primaryKeyColumn, $patientIdColumn, $id, $orderBy);
$samplesCount = count($samplesResult);
$configControl = $batchService->getConfigControl($batchInfo['machine']);
$prevBatchControlNames = $batchService->getPreviousBatchControlNames($batchInfo['machine']);
$batchControlNames = $batchService->getBatchControlNames($batchInfo, $prevBatchControlNames);
$content = $batchService->generateContent($samplesResult, $batchInfo, $batchControlNames, $configControl, $samplesCount, $table, $primaryKeyColumn, $patientIdColumn, $testType, $orderBy, $id);

?>

<!-- HTML and JavaScript -->
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
<div class="content-wrapper">
	<section class="content-header">
		<h1><em class="fa-solid fa-pen-to-square"></em> <?= _translate("Edit Batch Controls Position"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?= _translate("Home"); ?></a></li>
			<li class="active"><?= _translate("Batch"); ?></li>
		</ol>
	</section>

	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<h4><strong><?= _translate("Batch Code"); ?> :
						<?php echo (isset($batchInfo['batch_code'])) ? $batchInfo['batch_code'] : ''; ?>
					</strong>
					<button type="button" id="updateSerialNumbersButton" class="btn btn-primary pull-right" onclick="updateSerialNumbers();return false;">Update Serial Numbers
					</button>
				</h4>
			</div>
			<div class="box-body">
				<form class="form-horizontal" method='post' name='editBatchControlsPosition' id='editBatchControlsPosition' autocomplete="off" action="save-batch-position-helper.php">
					<div class="box-body">
						<div class="row" id="displayOrderDetails">
							<div class="col-lg-12">
								<ul id="sortableRow">
									<?php
									echo $content['content'];
									?>
								</ul>
								<table class="table table-striped" style="width:50%; margin:3em auto;">
									<caption><strong><?= _translate("Labels for Controls/Calibrators") ?></strong></caption>
									<?php echo $content['labelNewContent']; ?>
								</table>
							</div>
						</div>
					</div>
					<div class="box-footer">
						<input type="hidden" name="type" id="type" value="<?php echo $type; ?>" />
						<input type="hidden" name="sortOrders" id="sortOrders" value="<?= implode(",", $content['displayOrder']); ?>" />
						<input type="hidden" name="batchId" id="batchId" value="<?php echo htmlspecialchars($id); ?>" />
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Submit</a>
						<a href="batches.php?type=<?php echo $type; ?>" class="btn btn-default"> Cancel</a>
					</div>
				</form>
			</div>
		</div>
	</section>
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
			var existingText = $(this).text();
			var updatedText = (index + 1) + '. ' + existingText.replace(/^\d+\. /, '');
			$(this).text(updatedText);
		});
		$("#updateSerialNumbersButton").hide();
	}
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
