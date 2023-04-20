<?php

use App\Models\Facilities;
use App\Models\General;
use App\Utilities\DateUtils;

if (session_status() == PHP_SESSION_NONE) {
	session_start();
}



$general = new General();
$facilitiesDb = new Facilities();

$sarr = $general->getSystemConfig();



// $rpQuery="SELECT GROUP_CONCAT(DISTINCT rp.sample_id SEPARATOR ',') as sampleId FROM r_package_details_map as rp";
// $rpResult = $db->rawQuery($rpQuery);
if ($_SESSION['instanceType'] == 'remoteuser') {
	$sCode = 'remote_sample_code';
	$facilityMap = $facilitiesDb->getUserFacilityMap($_SESSION['userId']);
} elseif ($sarr['sc_user_type'] == 'vluser' || $sarr['sc_user_type'] == 'standalone') {
	$sCode = 'sample_code';
}

$module = (isset($_POST['testType']) && !empty($_POST['testType'])) ? $_POST['testType'] : $_POST['module'];
$query = "";
if ($module == 'vl') {
	$query .= "SELECT p.package_code, p.lab_id, vl.sample_code,vl.remote_sample_code,vl.vl_sample_id FROM package_details as p INNER JOIN form_vl as vl ON vl.sample_package_code = p.package_code ";
} elseif ($module == 'eid') {
	$query .= "SELECT p.package_code, p.lab_id, vl.sample_code,vl.remote_sample_code,vl.eid_id FROM package_details as p INNER JOIN form_eid as vl ON vl.sample_package_code = p.package_code ";
} elseif ($module == 'covid19') {
	$query .= "SELECT p.package_code, p.lab_id, vl.sample_code,vl.remote_sample_code,vl.covid19_id FROM package_details as p INNER JOIN form_covid19 as vl ON vl.sample_package_code = p.package_code ";
} elseif ($module == 'hepatitis') {
	$query .= "SELECT p.package_code, p.lab_id, vl.sample_code,vl.remote_sample_code,vl.hepatitis_id FROM package_details as p INNER JOIN form_hepatitis as vl ON vl.sample_package_code = p.package_code ";
} elseif ($module == 'tb') {
	$query .= "SELECT p.package_code, p.lab_id, vl.sample_code,vl.remote_sample_code,vl.tb_id FROM package_details as p INNER JOIN form_tb as vl ON vl.sample_package_code = p.package_code ";
}
$where = [];
$where[] = " (vl.remote_sample_code IS NOT NULL) AND (vl.sample_package_id is not null OR vl.sample_package_id !='') AND (remote_sample = 'yes') ";
if (isset($_POST['daterange']) && trim($_POST['daterange']) != '') {
	$dateRange = explode("to", $_POST['daterange']);
	if (isset($dateRange[0]) && trim($dateRange[0]) != "") {
		$startDate = DateUtils::isoDateFormat(trim($dateRange[0]));
	}
	if (isset($dateRange[1]) && trim($dateRange[1]) != "") {
		$endDate = DateUtils::isoDateFormat(trim($dateRange[1]));
	}

	$where[] = "DATE(p.request_created_datetime) BETWEEN '" . $startDate . "' AND '" . $endDate . "'";
}
if (!empty($facilityMap)) {
	$where[] = " facility_id IN(" . $facilityMap . ")";
}

if (isset($_POST['testingLab']) && !empty($_POST['testingLab'])) {
	$where[] = " (p.lab_id IN(" . $_POST['testingLab'] . ") OR (p.lab_id like '' OR p.lab_id is null OR p.lab_id = 0))";
}

if (isset($_POST['facility']) && !empty($_POST['facility'])) {
	$where[] = " (facility_id IN(" . $_POST['facility'] . ")  OR (facility_id like '' OR facility_id is null OR facility_id = 0))";
}

if (isset($where) && !empty($where)) {
	$query .= " where " . implode(" AND ", $where);
}
$query .= " GROUP BY p.package_code ORDER BY vl.request_created_datetime ASC";
// die($query);
$result = $db->rawQuery($query);

?>
<div class="col-md-9 col-md-offset-1">
	<div class="form-group">
		<div class="col-md-12">
			<div class="col-md-12">
				<div style="width:60%;margin:0 auto;clear:both;">
					<a href="#" id="select-all-packageCode" style="float:left" class="btn btn-info btn-xs">Select All&nbsp;&nbsp;<em class="fa-solid fa-chevron-right"></em></a> <a href='#' id='deselect-all-samplecode' style="float:right" class="btn btn-danger btn-xs"><em class="fa-solid fa-chevron-left"></em>&nbsp;Deselect All</a>
				</div><br /><br />
				<select id="packageCode" name="packageCode[]" multiple="multiple" class="search">
					<?php foreach ($result as $sample) { ?>
						<option value="'<?php echo $sample['package_code']; ?>'"><?php echo ($sample["package_code"]); ?></option>
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