<?php

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


$module = (!empty($_POST['testType'])) ? $_POST['testType'] : $_POST['module'];

$tableName = TestsService::getTestTableName($module);
$query = "SELECT p.manifest_code, p.lab_id, p.request_created_datetime
			FROM specimen_manifests as p
			INNER JOIN $tableName as vl ON vl.sample_package_code = p.manifest_code ";
$where = [];
if (isset($_POST['daterange']) && trim((string) $_POST['daterange']) != '') {
	[$startDate, $endDate] = DateUtility::convertDateRange($_POST['daterange'], includeTime: true);
	$where[] = "p.request_created_datetime BETWEEN '$startDate' AND '$endDate'";
}
if (!empty($_SESSION['facilityMap'])) {
	$where[] = " vl.facility_id IN(" . $_SESSION['facilityMap'] . ")";
}

if (!empty($_POST['testingLab'])) {
	$where[] = " p.lab_id IN(" . $_POST['testingLab'] . ")";
}

if (!empty($_POST['genericTestType'])) {
	$where[] = " vl.test_type =" . $_POST['genericTestType'];
}


if (!empty($where)) {
	$query .= " WHERE " . implode(" AND ", $where);
}
$query .= " GROUP BY p.manifest_code ORDER BY p.last_modified_datetime ASC";

$manifestResults = $db->rawQuery($query);

if (empty($manifestResults)) {
	echo "";
	exit(0);
}

?>
<div class="col-md-9 col-md-offset-1">
	<div class="form-group">
		<div class="col-md-12">
			<div class="col-md-12">
				<div style="width:60%;margin:0 auto;clear:both;">
					<a href="#" id="select-all-packageCode" style="float:left" class="btn btn-info btn-xs">Select All&nbsp;&nbsp;<em class="fa-solid fa-chevron-right"></em></a> <a href='#' id='deselect-all-samplecode' style="float:right" class="btn btn-danger btn-xs"><em class="fa-solid fa-chevron-left"></em>&nbsp;Deselect All</a>
				</div><br /><br />
				<select id="packageCode" name="packageCode[]" multiple="multiple" class="search">
					<?php foreach ($manifestResults as $manifest) { ?>
						<option value="'<?= $manifest['manifest_code']; ?>'"><?= $manifest["manifest_code"] . " (" . DateUtility::humanReadableDateFormat($manifest["request_created_datetime"]) . ")"; ?></option>
					<?php } ?>
				</select>
			</div>
		</div>
	</div>
</div>
<script>
	$(document).ready(function() {
		$('.search').multiSelect({
			selectableHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Manifest Code'>",
			selectionHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Manifest Code'>",
			afterInit: function(ms) {
				var that = this,
					$selectableSearch = that.$selectableUl.prev(),
					$selectionSearch = that.$selectionUl.prev(),
					selectableSearchString = '#' + that.$container.attr('id') + ' .ms-elem-selectable:not(.ms-selected)',
					selectionSearchString = '#' + that.$container.attr('id') + ' .ms-elem-selection.ms-selected';

				that.qs1 = $selectableSearch.quicksearch(selectableSearchString)
					.on('keydown', function(e) {
						if (e.which === 40) {
							that.$selectableUl.focus();
							return false;
						}
					});

				that.qs2 = $selectionSearch.quicksearch(selectionSearchString)
					.on('keydown', function(e) {
						if (e.which == 40) {
							that.$selectionUl.focus();
							return false;
						}
					});
			},
			afterSelect: function() {
				//button disabled/enabled
				if (this.qs2.cache().matchedResultsCount == noOfSamples) {
					alert("You have selected maximum number of samples - " + this.qs2.cache().matchedResultsCount);
					$("#packageSubmit").attr("disabled", false);
					$("#packageSubmit").css("pointer-events", "auto");
				} else if (this.qs2.cache().matchedResultsCount <= noOfSamples) {
					$("#packageSubmit").attr("disabled", false);
					$("#packageSubmit").css("pointer-events", "auto");
				} else if (this.qs2.cache().matchedResultsCount > noOfSamples) {
					alert("You have already selected Maximum no. of sample " + noOfSamples);
					$("#packageSubmit").attr("disabled", true);
					$("#packageSubmit").css("pointer-events", "none");
				}
				this.qs1.cache();
				this.qs2.cache();
			},
			afterDeselect: function() {
				//button disabled/enabled
				if (this.qs2.cache().matchedResultsCount == 0) {
					$("#packageSubmit").attr("disabled", true);
					$("#packageSubmit").css("pointer-events", "none");
				} else if (this.qs2.cache().matchedResultsCount == noOfSamples) {
					alert("You have selected maximum number of samples - " + this.qs2.cache().matchedResultsCount);
					$("#packageSubmit").attr("disabled", false);
					$("#packageSubmit").css("pointer-events", "auto");
				} else if (this.qs2.cache().matchedResultsCount <= noOfSamples) {
					$("#packageSubmit").attr("disabled", false);
					$("#packageSubmit").css("pointer-events", "auto");
				} else if (this.qs2.cache().matchedResultsCount > noOfSamples) {
					$("#packageSubmit").attr("disabled", true);
					$("#packageSubmit").css("pointer-events", "none");
				}
				this.qs1.cache();
				this.qs2.cache();
			}
		});
		$('#select-all-packageCode').click(function() {
			$('#packageCode').multiSelect('select_all');
			return false;
		});
		$('#deselect-all-packageCode').click(function() {
			$('#packageCode').multiSelect('deselect_all');
			$("#packageSubmit").attr("disabled", true);
			$("#packageSubmit").css("pointer-events", "none");
			return false;
		});
	});
</script>