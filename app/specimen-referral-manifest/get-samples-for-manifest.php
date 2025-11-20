<?php

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object

/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());
$_COOKIE = _sanitizeInput($request->getCookieParams());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);


if ($general->isSTSInstance()) {
	$sampleCode = 'remote_sample_code';
} elseif ($general->isLISInstance() || $general->isStandaloneInstance()) {
	$sampleCode = 'sample_code';
}

$module = (empty($_POST['module'])) ? "" : $_POST['module'];
$testType = (empty($_POST['testType'])) ? "" : $_POST['testType'];


$testTable = TestsService::getTestTableName($module);
$testPrimaryKey = TestsService::getPrimaryColumn($module);
$patientId = TestsService::getPatientIdColumn($module);

$query = "SELECT vl.sample_code,vl.remote_sample_code,vl.$testPrimaryKey,vl.$patientId,vl.sample_package_id,vl.is_encrypted,pd.manifest_id
			FROM $testTable as vl
			LEFT JOIN specimen_manifests as pd ON vl.sample_package_id = pd.manifest_id ";

$where = [];
$where[] = " (vl.remote_sample_code IS NOT NULL) ";
if (isset($_POST['daterange']) && trim((string) $_POST['daterange']) !== '') {

	[$startDate, $endDate] = DateUtility::convertDateRange($_POST['daterange'], includeTime: true);

	$where[] = " vl.sample_collection_date BETWEEN '$startDate' AND '$endDate' ";
}

if (!empty($_SESSION['facilityMap'])) {
	$where[] = " facility_id IN(" . $_SESSION['facilityMap'] . ")";
}

if (!empty($_POST['testingLab']) && $_POST['testingLab'] > 0) {
	$where[] = " vl.lab_id = {$_POST['testingLab']} ";
}

if (!empty($_POST['testingLab']) && is_numeric($_POST['facility'])) {
	$where[] = " facility_id = " . $_POST['facility'];
}


if (!empty($_POST['testType'])) {
	$where[] = " test_type = " . $_POST['testType'];
}


if (!empty($_POST['pkgId'])) {
	$where[] = " (vl.sample_package_id = '{$_POST['pkgId']}' OR vl.sample_package_id IS NULL OR vl.sample_package_id = '')";
} else {
	$where[] = " (vl.sample_package_id is null OR vl.sample_package_id='') AND (remote_sample = 'yes') ";
}
if (!empty($_POST['sampleType'])) {
	$where[] = " specimen_type IN(" . $_POST['sampleType'] . ") ";
}
if ($where !== []) {
	$query .= " WHERE " . implode(" AND ", $where);
}
$query .= " ORDER BY vl.request_created_datetime ASC";

$result = $db->rawQuery($query);
$key = (string) $general->getGlobalConfig('key');

?>

<script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script>
<div class="col-md-5">
	<select name="sampleCode[]" id="search" class="form-control" size="8" multiple="multiple">
		<?php foreach ($result as $sample) {
			if ($sample['is_encrypted'] == 'yes') {
				$sample[$patientId] = $general->crypto('decrypt', $sample[$patientId], $key);
			}
			if (!empty($sample[$sampleCode]) && ((!isset($sample['sample_package_id']) || !isset($sample['manifest_id'])) || ($sample['sample_package_id'] != $sample['manifest_id']))) {
				?>
				<option value="<?php
				echo $sample[$testPrimaryKey];
				?>"><?= $sample[$sampleCode] . ' - ' . $sample[$patientId]; ?></option>
				<?php
			}
		} ?>
	</select>
	<div class="sampleCounterDiv"><?= _translate("Number of unselected samples"); ?> : <span
			id="unselectedCount"></span></div>
</div>

<div class="col-md-2">
	<button type="button" id="search_rightAll" class="btn btn-block"><em class="fa-solid fa-forward"></em></button>
	<button type="button" id="search_rightSelected" class="btn btn-block"><em
			class="fa-sharp fa-solid fa-chevron-right"></em></button>
	<button type="button" id="search_leftSelected" class="btn btn-block"><em
			class="fa-sharp fa-solid fa-chevron-left"></em></button>
	<button type="button" id="search_leftAll" class="btn btn-block"><em class="fa-solid fa-backward"></em></button>
</div>

<div class="col-md-5">
	<select name="to[]" id="search_to" class="form-control" size="8" multiple="multiple">
		<?php foreach ($result as $sample) {
			if ($sample['is_encrypted'] == 'yes') {
				$sample[$patientId] = $general->crypto('decrypt', $sample[$patientId], $key);
			}
			if (!empty($sample[$sampleCode]) && (isset($sample['manifest_id']) && isset($sample['sample_package_id']) && $sample['sample_package_id'] == $sample['manifest_id'])) {
				?>
				<option value="<?php
				echo $sample[$testPrimaryKey];
				?>"><?= $sample[$sampleCode] . ' - ' . $sample[$patientId]; ?></option>
				<?php
			}
		} ?>
	</select>
	<div class="sampleCounterDiv"><?= _translate("Number of selected samples"); ?> : <span id="selectedCount"></span>
	</div>
</div>
<script>
	$(document).ready(function () {

		$('#search').deforayDualBox({
			search: {
				left: '<input type="text" name="q" class="form-control" placeholder="<?php echo _translate("Search"); ?>..." />',
				right: '<input type="text" name="q" class="form-control" placeholder="<?php echo _translate("Search"); ?>..." />',
			},
			fireSearch: function (value) {
				return value.length > 2;
			},
			autoSelectNext: true,
			keepRenderingSort: true
		});

		// Automatically called after init and each move
		$('#search').on('dualbox:updateCounts', function (e, $left, $right) {
			updateCounts($left, $right);
		});

		$('#select-all-samplecode').click(function () {
			$('#sampleCode').multiSelect('select_all');
			return false;
		});
		$('#deselect-all-samplecode').click(function () {
			$('#sampleCode').multiSelect('deselect_all');
			$("#packageSubmit").attr("disabled", true);
			$("#packageSubmit").css("pointer-events", "none");
			return false;
		});
	});

	function updateCounts($left, $right) {
		let selectedCount = $right.find('option').length;
		if (selectedCount > 0) {
			$("#packageSubmit").attr("disabled", false);
			$("#packageSubmit").css("pointer-events", "auto");
		} else {
			$("#packageSubmit").attr("disabled", true);
			$("#packageSubmit").css("pointer-events", "none");
		}
		$("#unselectedCount").html($left.find('option').length);
		$("#selectedCount").html(selectedCount);
	}
</script>