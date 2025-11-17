<?php

use App\Services\BatchService;
use App\Services\TestsService;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\DatabaseService;
use App\Services\SecurityService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var BatchService $batchService */
$batchService = ContainerRegistry::get(BatchService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$sortBy = $_GET['sortBy'] ?? 'sampleCode';
$sortType = $batchService->getSortType($_GET['sortType'] ?? 'asc');
$orderBy = $batchService->getOrderBy($sortBy, $sortType);

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

$id = (isset($_GET['id'])) ? base64_decode((string)$_GET['id']) : null;
if (!isset($id) || trim($id) === '') {
	MiscUtility::redirect("batches.php?type=" . $_GET['type']);
	exit;
}

$batchInfo = $batchService->getBatchInfo($id);
if (empty($batchInfo)) {
	MiscUtility::redirect("batches.php?type=" . $_GET['type']);
	exit;
}

$configControl = $batchService->getConfigControl($batchInfo['machine']);
$prevBatchControlNames = $batchService->getPreviousBatchControlNames($batchInfo['machine']);
$batchControlNames = $batchService->getBatchControlNames($batchInfo, $prevBatchControlNames);

$samplesResult = $batchService->getSamplesByBatchId($table, $primaryKeyColumn, $patientIdColumn, $id, $orderBy);
$samplesCount = count($samplesResult);

$contentData = $batchService->generateContent($samplesResult, $batchInfo, $batchControlNames, $configControl, $samplesCount, $table, $primaryKeyColumn, $patientIdColumn, $testType, $orderBy, $id);

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
		<h1><em class="fa-solid fa-pen-to-square"></em> <?= _translate("Add Batch Controls Position"); ?></h1>
		<ol class="breadcrumb">
			<li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?= _translate("Home"); ?></a></li>
			<li class="active"><?= _translate("Batch"); ?></li>
		</ol>
	</section>

	<section class="content">
		<div class="box box-default">
			<div class="box-header with-border">
				<h4><strong><?= _translate("Batch Code"); ?>: <?= $batchInfo['batch_code']; ?></strong></h4>
				<div class="row">
					<div class="col-lg-3">
						<select class="form-control" id="sortBy">
							<option <?= $sortBy == 'requestCreated' ? "selected='selected'" : '' ?> value="requestCreated"><?= _translate("Request Created"); ?></option>
							<option <?= $sortBy == 'lastModified' ? "selected='selected'" : '' ?> value="lastModified"><?= _translate("Last Modified"); ?></option>
							<option <?= $sortBy == 'sampleCode' ? "selected='selected'" : '' ?> value="sampleCode"><?= _translate("Sample ID"); ?></option>
							<option <?= $sortBy == 'labAssignedCode' ? "selected='selected'" : '' ?> value="labAssignedCode"><?= _translate("Lab Assigned Code"); ?></option>
						</select>
					</div>
					<div class="col-lg-2">
						<select class="form-control" id="sortType">
							<option <?= $sortType == 'asc' ? "selected='selected'" : '' ?> value="asc"><?= _translate("Ascending"); ?></option>
							<option <?= $sortType == 'desc' ? "selected='selected'" : '' ?> value="desc"><?= _translate("Descending"); ?></option>
						</select>
					</div>
					<div class="col-lg-7">
						<div class="col-lg-4">
							<button type="button" class="btn btn-primary pull-right form-control" onclick="changeSampleOrder();return false;">Change Sample Order</button>
						</div>
						<div class="col-lg-3">
							<button type="button" class="btn btn-danger pull-right form-control" onclick="sortBatch();return false;">Reset to Default</button>
						</div>

					</div>
				</div>
			</div>

			<div class="box-body" style="margin-top:10px;">
				<button style="margin-bottom:30px;" type="button" id="updateSerialNumbersButton" class="btn btn-primary pull-right" onclick="updateSerialNumbers();return false;">Update Serial Numbers</button>
				<form class="form-horizontal" method='post' name='addBatchControlsPosition' id='addBatchControlsPosition' autocomplete="off" action="save-batch-position-helper.php">
					<div class="box-body">
						<div class="row" id="displayOrderDetails">
							<div class="col-lg-12">
								<ul id="sortableRow">
									<?= $contentData['content']; ?>
								</ul>
								<table class="table table-striped" style="width:50%; margin:3em auto;">
									<caption><strong><?= _translate("Labels for Controls/Calibrators") ?></strong></caption>
									<?= $contentData['labelNewContent']; ?>
								</table>
							</div>
						</div>
					</div>
					<div class="box-footer">
						<input type="hidden" name="type" id="type" value="<?= $_GET['type']; ?>" />
						<input type="hidden" name="sortType" id="typeSort" value="<?= $_GET['sortType']; ?>" />
						<input type="hidden" name="sortBy" id="bySort" value="<?= $_GET['sortBy']; ?>" />
						<input type="hidden" name="sortOrders" id="sortOrders" value="<?= implode(",", $contentData['displayOrder']); ?>" />
						<input type="hidden" name="batchId" id="batchId" value="<?= htmlspecialchars($id); ?>" />
						<a class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;">Save</a>
						<a href="/batch/batches.php?type=<?= $_GET['type']; ?>" class="btn btn-default"> Cancel</a>
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
			formId: 'addBatchControlsPosition'
		});

		if (flag) {
			$.blockUI();
			document.getElementById('addBatchControlsPosition').submit();
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

	function sortBatch() {
		conf = confirm("<?= _translate("Are you sure you want to reset the sorting to default? This will reset all previous changes", true); ?>");
		if (conf) {
			let sortType = 'asc'; // Resetting sortType to 'asc'
			let sortBy = 'sampleCode'; // Resetting sortBy to 'sampleCode'

			$("#sortType").val(sortType);
			$("#sortBy").val(sortBy);

			$("#typeSort").val(sortType);
			$("#bySort").val(sortBy);

			let url = new URL(window.location.href);
			let params = new URLSearchParams(url.search);
			params.set('sortBy', sortBy);
			params.set('sortType', sortType);

			url.search = params.toString();
			window.location.href = url.toString();
		}
	}

	function changeSampleOrder() {
		sortType = $("#sortType").val();
		sortBy = $("#sortBy").val();

		$("#sortType").val(sortType);
		$("#sortBy").val(sortBy);

		$("#typeSort").val(sortType);
		$("#bySort").val(sortBy);

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
