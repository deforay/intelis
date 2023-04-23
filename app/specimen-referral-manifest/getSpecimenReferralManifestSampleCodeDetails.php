<?php

use App\Services\FacilitiesService;
use App\Services\CommonService;
use App\Utilities\DateUtils;

if (session_status() == PHP_SESSION_NONE) {
	session_start();
}



$general = new CommonService();
$facilitiesDb = new FacilitiesService();

$sarr = $general->getSystemConfig();
//global config
$configQuery = "SELECT `value` FROM global_config WHERE name ='vl_form'";
$configResult = $db->query($configQuery);
$country = $configResult[0]['value'];

// $rpQuery="SELECT GROUP_CONCAT(DISTINCT rp.sample_id SEPARATOR ',') as sampleId FROM r_package_details_map as rp";
// $rpResult = $db->rawQuery($rpQuery);
if ($_SESSION['instanceType'] == 'remoteuser') {
	$sCode = 'remote_sample_code';
	$facilityMap = $facilitiesDb->getUserFacilityMap($_SESSION['userId']);
} else if ($sarr['sc_user_type'] == 'vluser' || $sarr['sc_user_type'] == 'standalone') {
	$sCode = 'sample_code';
}

$module = (isset($_POST['testType']) && !empty($_POST['testType']))?$_POST['testType']:$_POST['module'];
$query = "";
if ($module == 'vl') {
	$query .= "SELECT vl.sample_code,vl.remote_sample_code,vl.vl_sample_id FROM form_vl as vl ";
} else if ($module == 'eid') {
	$query .= "SELECT vl.sample_code,vl.remote_sample_code,vl.eid_id FROM form_eid as vl ";
} else if ($module == 'covid19') {
	$query .= "SELECT vl.sample_code,vl.remote_sample_code,vl.covid19_id FROM form_covid19 as vl ";
} else if ($module == 'hepatitis') {
	$query .= "SELECT vl.sample_code,vl.remote_sample_code,vl.hepatitis_id FROM form_hepatitis as vl ";
} else if ($module == 'tb') {
	$query .= "SELECT vl.sample_code,vl.remote_sample_code,vl.tb_id FROM form_tb as vl ";
}
$where = [];
$where[] = " (vl.remote_sample_code IS NOT NULL) AND (vl.sample_package_id is null OR vl.sample_package_id='') AND (remote_sample = 'yes') ";
if (isset($_POST['daterange']) && trim($_POST['daterange']) != '') {
	$dateRange = explode("to", $_POST['daterange']);
	//print_r($dateRange);die;
	if (isset($dateRange[0]) && trim($dateRange[0]) != "") {
		$startDate = DateUtils::isoDateFormat(trim($dateRange[0]));
	}
	if (isset($dateRange[1]) && trim($dateRange[1]) != "") {
		$endDate = DateUtils::isoDateFormat(trim($dateRange[1]));
	}

	$where[] = "DATE(vl.sample_collection_date) >= '" . $startDate . "' AND DATE(vl.sample_collection_date) <= '" . $endDate . "'";
}

if (!empty($facilityMap)) {
	$where[] = " facility_id IN(" . $facilityMap . ")";
}

if (isset($_POST['testingLab']) && !empty($_POST['testingLab'])) {
	$where[] = " (lab_id IN(" . $_POST['testingLab'] . ") OR (lab_id like '' OR lab_id is null OR lab_id = 0))";
}

if (isset($_POST['facility']) && !empty($_POST['facility'])) {
	$where[] = " (facility_id IN(" . $_POST['facility'] . ")  OR (facility_id like '' OR facility_id is null OR facility_id = 0))";
}

if (isset($_POST['operator']) && !empty($_POST['operator'])) {
	$where[] = " (request_created_by like '" . $_POST['operator'] . "'  OR (request_created_by like '' OR request_created_by is null OR request_created_by = 0))";
}

if (isset($_POST['sampleType']) && !empty($_POST['sampleType']) && $module == 'vl') {
	$where[] = " (sample_type IN(" . $_POST['sampleType'] . ")  OR (sample_type like '' OR sample_type is null OR sample_type = 0))";
} else if (isset($_POST['sampleType']) && $_POST['sampleType'] != "" && $module != 'vl') {
	$where[] = " (specimen_type IN(" . $_POST['sampleType'] . ")  OR (specimen_type like '' OR specimen_type is null OR specimen_type = 0))";
}
if(isset($where) && count($where) > 0){
	$query .= " where ". implode(" AND ", $where);	
}
$query .= " ORDER BY vl.request_created_datetime ASC";
// die($query);
$result = $db->rawQuery($query);

?>
<div class="col-md-9 col-md-offset-1">
	<div class="form-group">
		<div class="col-md-12">
			<div class="col-md-12">
				<div style="width:60%;margin:0 auto;clear:both;">
					<a href="#" id="select-all-samplecode" style="float:left" class="btn btn-info btn-xs">Select All&nbsp;&nbsp;<em class="fa-solid fa-chevron-right"></em></a> <a href='#' id='deselect-all-samplecode' style="float:right" class="btn btn-danger btn-xs"><em class="fa-solid fa-chevron-left"></em>&nbsp;Deselect All</a>
				</div><br /><br />
				<select id="sampleCode" name="sampleCode[]" multiple="multiple" class="search">
					<?php
					foreach ($result as $sample) {
						if ($sample[$sCode] != '') {
							if ($module == 'vl') {
								$sampleId  = $sample['vl_sample_id'];
								//$sampleCode  = $sample['vl_sample_id'];
							} else if ($module == 'eid') {
								$sampleId  = $sample['eid_id'];
							} else if ($module == 'covid19') {
								$sampleId  = $sample['covid19_id'];
							} else if ($module == 'hepatitis') {
								$sampleId  = $sample['hepatitis_id'];
							} else if ($module == 'tb') {
								$sampleId  = $sample['tb_id'];
							}
					?>
							<option value="<?php echo $sampleId; ?>"><?php echo ($sample[$sCode]); ?></option>
					<?php
						}
					}
					?>
				</select>
			</div>
		</div>
	</div>
</div>
<script>
	$(document).ready(function() {
		$('.search').multiSelect({
			selectableHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Sample Code'>",
			selectionHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Sample Code'>",
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
		$('#select-all-samplecode').click(function() {
			$('#sampleCode').multiSelect('select_all');
			return false;
		});
		$('#deselect-all-samplecode').click(function() {
			$('#sampleCode').multiSelect('deselect_all');
			$("#packageSubmit").attr("disabled", true);
			$("#packageSubmit").css("pointer-events", "none");
			return false;
		});
	});
</script>