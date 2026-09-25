<?php

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Utilities\SampleCountUtility;
use const SAMPLE_STATUS\CANCELLED;
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
$patientFirstName = TestsService::getPatientFirstNameColumn($module);
$patientLastName = TestsService::getPatientLastNameColumn($module);

$query = "SELECT vl.sample_code,vl.remote_sample_code,vl.$patientFirstName,vl.$patientLastName,vl.$testPrimaryKey,vl.$patientId,vl.sample_package_id,vl.is_encrypted,pd.manifest_id
			FROM $testTable as vl
			LEFT JOIN specimen_manifests as pd ON vl.sample_package_id = pd.manifest_id ";

$where = [];
$where[] = " (vl.remote_sample_code IS NOT NULL) ";
// A cancelled sample was called off before testing, so it is not a sample to
// refer. Nothing downstream re-checks this: save-tb-referral-helper.php sets
// REFERRED on whatever it is handed, so the picker is where it has to hold.
$where[] = SampleCountUtility::countableWhere('vl');

// The search filters narrow the samples on offer, never the ones already on the
// manifest being edited: saving rewrites the manifest from the right-hand box,
// so a member a filter hid would be dropped without anyone choosing to.
$filters = [];
if (isset($_POST['daterange']) && trim((string) $_POST['daterange']) !== '') {

	[$startDate, $endDate] = DateUtility::convertDateRange($_POST['daterange'], includeTime: true);

	$filters[] = " vl.sample_collection_date BETWEEN '$startDate' AND '$endDate' ";
}

// Who may see which samples. Also applied to the two lists of samples leaving
// the manifest below, which print sample codes too.
$scope = [];
if (!empty($_SESSION['facilityMap'])) {
	$scope[] = " vl.facility_id IN(" . $_SESSION['facilityMap'] . ")";
}
// Lab isolation (cloud-LIS): scope to this user's lab. No-op unless the session
// carries a lab id, so byte-identical for every existing LIS/STS user.
if ($labScope = $general->labScopeWhere('vl')) {
	$scope[] = $labScope;
}
$where = array_merge($where, $scope);
$scopeSql = $scope === [] ? '' : ' AND ' . implode(' AND ', $scope);

$testingLab = (int) ($_POST['testingLab'] ?? 0);
if ($testingLab > 0) {
	$filters[] = " vl.lab_id = $testingLab ";
}

if (!empty($_POST['testingLab']) && is_numeric($_POST['facility'])) {
	$filters[] = " facility_id = " . (int) $_POST['facility'];
}


if (!empty($_POST['testType'])) {
	$where[] = " test_type = " . (int) $_POST['testType'];
}


if (!empty($_POST['sampleType'])) {
	$filters[] = " specimen_type IN(" . $db->inIntList($_POST['sampleType']) . ") ";
}

$onOffer = " (vl.sample_package_id IS NULL OR vl.sample_package_id = '') ";
if (empty($_POST['pkgId'])) {
	$onOffer .= " AND (remote_sample = 'yes') ";
}
if ($filters !== []) {
	$onOffer .= " AND " . implode(" AND ", $filters);
}
// The manifest's own samples are listed when they are of the chosen lab, so
// after the testing lab is changed only the new lab's samples are listed at
// all. One with no lab yet stays: saving gives it the manifest's lab.
$onManifest = " vl.sample_package_id = " . (int) ($_POST['pkgId'] ?? 0)
	. ($testingLab > 0 ? " AND (vl.lab_id = $testingLab OR vl.lab_id IS NULL)" : '');
$where[] = empty($_POST['pkgId'])
	? " ($onOffer) "
	: " (($onOffer) OR ($onManifest)) ";
if ($where !== []) {
	$query .= " WHERE " . implode(" AND ", $where);
}
$query .= " ORDER BY vl.remote_sample_code ASC, vl.request_created_datetime ASC";

$result = $db->rawQuery($query);

// The clause above keeps cancelled samples out of both sides of the dual-box,
// and saving rewrites the manifest from what the right-hand side holds. So a
// sample cancelled after it was added is about to be dropped from this
// manifest, which is right, but not something to do without saying so.
$cancelledOnManifest = [];
if (!empty($_POST['pkgId'])) {
	$cancelledOnManifest = $db->rawQuery(
		"SELECT vl.$sampleCode AS code FROM $testTable AS vl
		 WHERE vl.sample_package_id = ? AND vl.result_status = ? $scopeSql
		 ORDER BY vl.$sampleCode",
		[$_POST['pkgId'], CANCELLED]
	) ?: [];
}
// Same for a changed testing lab: the lab clause keeps samples of the old lab
// out of the dual-box, so saving takes them off this manifest. They stay with
// their own lab; they are not moved to the new one.
$otherLabOnManifest = [];
if (!empty($_POST['pkgId']) && $testingLab > 0) {
	$otherLabOnManifest = $db->rawQuery(
		"SELECT vl.$sampleCode AS code FROM $testTable AS vl
		 WHERE vl.sample_package_id = ? AND vl.result_status != ?
		   AND vl.lab_id IS NOT NULL AND vl.lab_id != ? $scopeSql
		 ORDER BY vl.$sampleCode",
		[$_POST['pkgId'], CANCELLED, $testingLab]
	) ?: [];
}
$key = (string) $general->getGlobalConfig('key');

?>

<script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script>
<?php if ($cancelledOnManifest !== []) { ?>
	<div class="col-md-12">
		<div class="alert alert-warning">
			<strong><?= _htmlTranslate("Cancelled samples will be removed from this manifest"); ?></strong>
			<p style="margin:5px 0 0;">
				<?= _htmlTranslate("These samples were cancelled after being added to this manifest. Saving will remove them from it."); ?>
			</p>
			<ul style="margin:5px 0 0;">
				<?php foreach ($cancelledOnManifest as $cancelledSample) { ?>
					<li><?= htmlspecialchars((string) $cancelledSample["code"], ENT_QUOTES, "UTF-8"); ?></li>
				<?php } ?>
			</ul>
		</div>
	</div>
<?php } ?>
<?php if ($otherLabOnManifest !== []) { ?>
	<div class="col-md-12">
		<div class="alert alert-warning">
			<strong><?= _htmlTranslate("Samples from another testing lab will be removed from this manifest"); ?></strong>
			<p style="margin:5px 0 0;">
				<?= _htmlTranslate("These samples are on this manifest but belong to a different testing lab. Saving will remove them from it. They stay with their own testing lab."); ?>
			</p>
			<ul style="margin:5px 0 0;">
				<?php foreach ($otherLabOnManifest as $otherLabSample) { ?>
					<li><?= htmlspecialchars((string) $otherLabSample["code"], ENT_QUOTES, "UTF-8"); ?></li>
				<?php } ?>
			</ul>
		</div>
	</div>
<?php } ?>
<div class="col-md-5">
<?php
// Patient IDs and names arrive from API clients and synced labs, so they are escaped here.
$optionLabel = static function (array $sample) use ($general, $key, $sampleCode, $patientId, $patientFirstName, $patientLastName): string {
	$id = $sample[$patientId];
	$names = [$sample[$patientFirstName], $sample[$patientLastName]];
	if ($sample['is_encrypted'] == 'yes') {
		$id = $general->crypto('decrypt', $id, $key);
		$names = array_map(static fn($name) => $general->crypto('decrypt', $name, $key), $names);
	}
	$label = $sample[$sampleCode] . ' - ' . $id;
	$name = trim(implode(' ', array_filter(array_map('strval', $names), static fn($n) => trim($n) !== '')));
	if ($name !== '') {
		$label .= ' - ' . $name;
	}
	return htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
};
?>
	<select name="sampleCode[]" id="search" class="form-control" size="8" multiple="multiple">
		<?php foreach ($result as $sample) {
			if (!empty($sample[$sampleCode]) && ((!isset($sample['sample_package_id']) || !isset($sample['manifest_id'])) || ($sample['sample_package_id'] != $sample['manifest_id']))) {
				?>
				<option value="<?= htmlspecialchars((string) $sample[$testPrimaryKey], ENT_QUOTES, 'UTF-8'); ?>"><?= $optionLabel($sample); ?></option>
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
			if (!empty($sample[$sampleCode]) && (isset($sample['manifest_id']) && isset($sample['sample_package_id']) && $sample['sample_package_id'] == $sample['manifest_id'])) {
				?>
				<option value="<?= htmlspecialchars((string) $sample[$testPrimaryKey], ENT_QUOTES, 'UTF-8'); ?>"><?= $optionLabel($sample); ?></option>
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