<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

if (session_status() == PHP_SESSION_NONE) {
	session_start();
}



/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);


if ($_SESSION['instanceType'] == 'remoteuser') {
	$sampleCode = 'remote_sample_code';
} else if ($_SESSION['instanceType'] == 'vluser' || $_SESSION['instanceType'] == 'standalone') {
	$sampleCode = 'sample_code';
}

$module = (!empty($_POST['module'])) ? $_POST['module'] : "";
$testType = (!empty($_POST['testType'])) ? $_POST['testType'] : "";

$query = "";
if ($module == 'vl') {
	$patientId = 'patient_art_no';
	$sampleId  = 'vl_sample_id';
	$query .= "SELECT vl.sample_code,vl.remote_sample_code,vl.patient_art_no,vl.vl_sample_id,vl.sample_package_id,pd.package_id FROM form_vl as vl ";
} else if ($module == 'eid') {
	$patientId = 'child_id';
	$sampleId  = 'eid_id';
	$query .= "SELECT vl.sample_code,vl.remote_sample_code,vl.eid_id,vl.child_id,vl.sample_package_id,pd.package_id FROM form_eid as vl ";
} else if ($module == 'covid19') {
	$patientId = 'patient_id';
	$sampleId  = 'covid19_id';
	$query .= "SELECT vl.sample_code,vl.remote_sample_code,vl.covid19_id,vl.patient_id,vl.sample_package_id,pd.package_id FROM form_covid19 as vl ";
} else if ($module == 'hepatitis') {
	$patientId = 'patient_id';
	$sampleId  = 'hepatitis_id';
	$query .= "SELECT vl.sample_code,vl.remote_sample_code,vl.hepatitis_id,vl.patient_id,vl.sample_package_id,pd.package_id FROM form_hepatitis as vl ";
} else if ($module == 'tb') {
	$patientId = 'patient_id';
	$sampleId  = 'tb_id';
	$query .= "SELECT vl.sample_code,vl.remote_sample_code,vl.tb_id,vl.sample_vl.patient_id,package_id,pd.package_id FROM form_tb as vl ";
} else if ($module == 'generic-tests') {
	$patientId = 'patient_id';
	$sampleId  = 'sample_id';
	$query .= "SELECT vl.sample_code,vl.remote_sample_code,vl.sample_id,vl.patient_id,vl.sample_package_id,pd.package_id FROM form_generic as vl ";
}
$query .= " LEFT JOIN package_details as pd ON vl.sample_package_id = pd.package_id ";

$where = [];
$where[] = " (vl.remote_sample_code IS NOT NULL) ";
if (isset($_POST['daterange']) && trim($_POST['daterange']) != '') {
	$dateRange = explode("to", $_POST['daterange']);
	//print_r($dateRange);die;
	if (isset($dateRange[0]) && trim($dateRange[0]) != "") {
		$startDate = DateUtility::isoDateFormat(trim($dateRange[0]));
	}
	if (isset($dateRange[1]) && trim($dateRange[1]) != "") {
		$endDate = DateUtility::isoDateFormat(trim($dateRange[1]));
	}

	$where[] = "DATE(vl.sample_collection_date) >= '" . $startDate . "' AND DATE(vl.sample_collection_date) <= '" . $endDate . "'";
}

if (!empty($_SESSION['facilityMap'])) {
	$where[] = " facility_id IN(" . $_SESSION['facilityMap'] . ")";
}

if (!empty($_POST['testingLab'])) {
	$where[] = " (vl.lab_id IN(" . $_POST['testingLab'] . ") OR (vl.lab_id like '' OR vl.lab_id is null OR vl.lab_id = 0))";
}

if (!empty($_POST['facility'])) {
	$where[] = " (facility_id IN(" . $_POST['facility'] . ")  OR (facility_id like '' OR facility_id is null OR facility_id = 0))";
}

if (!empty($_POST['operator'])) {
	// $where[] = " (request_created_by like '" . $_POST['operator'] . "'  OR (request_created_by like '' OR request_created_by is null OR request_created_by = 0))";
}

if (!empty($_POST['testType'])) {
	$where[] = " test_type = " . $_POST['testType'];
}
if (!empty($_POST['pkgId'])) {
    $where[] = " (pd.package_id = '" . $_POST['pkgId'] . "' OR pd.package_id IS NULL OR pd.package_id = '')";
} else{
	$where[] = "(vl.sample_package_id is null OR vl.sample_package_id='') AND (remote_sample = 'yes') ";
}
if (!empty($_POST['sampleType']) && ($module == 'vl' || $module == 'generic-tests')) {
	$where[] = " (sample_type IN(" . $_POST['sampleType'] . ")  OR (sample_type like '' OR sample_type is null OR sample_type = 0))";
} else if (isset($_POST['sampleType']) && $_POST['sampleType'] != "" && $module != 'vl') {
	$where[] = " (specimen_type IN(" . $_POST['sampleType'] . ")  OR (specimen_type like '' OR specimen_type is null OR specimen_type = 0))";
}
if (!empty($where)) {
	$query .= " where " . implode(" AND ", $where);
}
$query .= " ORDER BY vl.request_created_datetime ASC";
// die($query);
$result = $db->rawQuery($query);

?>
<script type="text/javascript" src="/assets/js/multiselect.min.js"></script>
<script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script>
<div class="col-md-5">
	<select name="sampleCode[]" id="search" class="form-control" size="8" multiple="multiple">
		<?php foreach ($result as $sample) { 
			if (!empty($sample[$sampleCode])) { 
				if ((!isset($sample['sample_package_id']) || !isset($sample['package_id'])) || ($sample['sample_package_id'] != $sample['package_id'])) { ?>
					<option value="<?php echo $sample[$sampleId]; ?>"><?php echo ($sample[$sampleCode] . ' - ' . $sample[$patientId]); ?></option>
				<?php } 
			} 
		}?>
	</select>
	<div class="sampleCounterDiv"><?= _translate("Number of unselected samples"); ?> : <span id="unselectedCount"></span></div>
</div>

<div class="col-md-2">
	<button type="button" id="search_rightAll" class="btn btn-block"><em class="fa-solid fa-forward"></em></button>
	<button type="button" id="search_rightSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-right"></em></button>
	<button type="button" id="search_leftSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-left"></em></button>
	<button type="button" id="search_leftAll" class="btn btn-block"><em class="fa-solid fa-backward"></em></button>
</div>

<div class="col-md-5">
	<select name="to[]" id="search_to" class="form-control" size="8" multiple="multiple">
		<?php foreach ($result as $sample) { 
			if (!empty($sample[$sampleCode])) { 
				if (isset($sample['package_id']) && isset($sample['sample_package_id']) && $sample['sample_package_id'] == $sample['package_id']) { ?>
				<option value="<?php echo $sample[$sampleId]; ?>"><?php echo ($sample[$sampleCode] . ' - ' . $sample[$patientId]); ?></option>
				<?php } 
			} 
		}?>
	</select>
	<div class="sampleCounterDiv"><?= _translate("Number of selected samples"); ?> : <span id="selectedCount"></span></div>
</div>
<script>
	$(document).ready(function() {
		
		$('#search').multiselect({
            search: {
                left: '<input type="text" name="q" class="form-control" placeholder="<?php echo _translate("Search"); ?>..." />',
                right: '<input type="text" name="q" class="form-control" placeholder="<?php echo _translate("Search"); ?>..." />',
            },
            fireSearch: function(value) {
                return value.length > 2;
            },
            startUp: function($left, $right) {
                updateCounts($left, $right);
            },
            afterMoveToRight: function($left, $right, $options) {
                updateCounts($left, $right);
            },
            afterMoveToLeft: function($left, $right, $options) {
                updateCounts($left, $right);
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

	function updateCounts($left, $right) {
        let selectedCount = $right.find('option').length;
		if(selectedCount > 0){
			$("#packageSubmit").attr("disabled", false);
			$("#packageSubmit").css("pointer-events", "auto");
		}else{
			$("#packageSubmit").attr("disabled", true);
			$("#packageSubmit").css("pointer-events", "none");
		}
        $("#unselectedCount").html($left.find('option').length);
        $("#selectedCount").html(selectedCount);
    }
</script>
