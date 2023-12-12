<?php

use App\Registries\AppRegistry;
use App\Services\BatchService;
use App\Services\CommonService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


/** @var BatchService $batchService */
$batchService = ContainerRegistry::get(BatchService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = $request->getQueryParams();
$title = "Viral Load";
$refTable = "form_vl";
$refPrimaryColumn = "vl_sample_id";
if (isset($_GET['type'])) {
	switch ($_GET['type']) {
		case 'vl':
			$title = "Viral Load";
			$refTable = "form_vl";
			$refPrimaryColumn = "vl_sample_id";
			break;
		case 'eid':
			$title = "Early Infant Diagnosis";
			$refTable = "form_eid";
			$refPrimaryColumn = "eid_id";
			break;
		case 'covid19':
			$title = "Covid-19";
			$refTable = "form_covid19";
			$refPrimaryColumn = "covid19_id";
			break;
		case 'hepatitis':
			$title = "Hepatitis";
			$refTable = "form_hepatitis";
			$refPrimaryColumn = "hepatitis_id";
			break;
		case 'tb':
			$title = "TB";
			$refTable = "form_tb";
			$refPrimaryColumn = "tb_id";
			break;
		case 'generic-tests':
			$title = "Other Lab Tests";
			$refTable = "form_generic";
			$refPrimaryColumn = "sample_id";
			break;
		default:
			throw new SystemException('Invalid test type - ' . $_GET['type'], 500);
	}
}
$_GET['type'] = ($_GET['type'] == 'covid19') ? 'covid-19' : $_GET['type'];
$title = _translate($title . " | Edit Batch Position");
$testType = (isset($_GET['testType'])) ? base64_decode((string) $_GET['testType']) : null;



require_once APPLICATION_PATH . '/header.php';


$id = (isset($_GET['id'])) ? base64_decode((string) $_GET['id']) : null;

if (!isset($id) || trim($id) == '') {
	header("Location:batches.php?type=" . $_GET['type']);
}
$content = '';
$displayOrder = [];
$batchQuery = "SELECT * from batch_details as b_d INNER JOIN instruments as i_c ON i_c.config_id=b_d.machine where batch_id= ? ";
$batchInfo = $db->rawQuery($batchQuery, [$id]);
// Config control
$configControlQuery = "SELECT * from instrument_controls where config_id= ? ";
$configControlInfo = $db->rawQuery($configControlQuery, [$batchInfo[0]['config_id']]);
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
			$sampleQuery = "SELECT sample_code from " . $refTable . " where " . $refPrimaryColumn . "= $xplodJsonToArray[1]";
			if (isset($_GET['type']) && $_GET['type'] == 'generic-tests') {
				$sampleQuery .= " AND test_type = $testType";
			}
			$sampleResult = $db->query($sampleQuery);
			$label = $sampleResult[0]['sample_code'];
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
	$samplesQuery = "SELECT " . $refPrimaryColumn . ",sample_code from " . $refTable . " where sample_batch_id=$id ORDER BY sample_code ASC";
	$samplesInfo = $db->query($samplesQuery);
	foreach ($samplesInfo as $sample) {
		$displayOrder[] = "s_" . $sample[$refPrimaryColumn];
		$content .= '<li class="ui-state-default" id="s_' . $sample[$refPrimaryColumn] . '">' . $sample['sample_code'] . '</li>';
	}
}
?>
<style>
	#sortableRow {
		list-style-type: none;
		margin: 0px 0px 30px 0px;
		padding: 0;
		width: 100%;
		text-align: center;
	}

	#sortableRow li {
		color: #333 !important;
		font-size: 16px;
		border-radius: 10px;
		margin-bottom: 4px;
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
					</strong></h4>
			</div>
			<!-- /.box-header -->
			<div class="box-body">
				<!-- <pre><?php print_r($configControl); ?></pre> -->
				<!-- form start -->
				<form class="form-horizontal" method='post' name='editBatchControlsPosition' id='editBatchControlsPosition' autocomplete="off" action="save-batch-position-helper.php">
					<div class="box-body">
						<div class="row" id="displayOrderDetails">
							<div class="col-md-8">
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

		$("#sortableRow").sortable({
			opacity: 0.6,
			cursor: 'move',
			update: function() {
				sortedTitle = cleanArray($(this).sortable("toArray"));
				$("#sortOrders").val("");
				$("#sortOrders").val(sortedTitle);
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
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
