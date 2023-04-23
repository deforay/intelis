<?php


use App\Services\CommonService;
use App\Utilities\DateUtils;

$general = new CommonService();

$lName = $_POST['lName'];
$testType = $_POST['testType'];
$pName = $_POST['pName'];
$dName = $_POST['dName'];
$fName = $_POST['fName'];
$scDate = $_POST['scDate'];

$tableName = "form_vl";
$primaryKey = "vl_sample_id";
if ($testType == "vl") {
	$tableName = "form_vl";
	$primaryKey = "vl_sample_id";
} else if ($testType == "eid") {
	$tableName = "form_eid";
	$primaryKey = "eid_id";
} else if ($testType == "covid19") {
	$tableName = "form_covid19";
	$primaryKey = "covid19_id";
} else if ($testType == "hepatitis") {
	$tableName = "form_hepatitis";
	$primaryKey = "hepatitis_id";
}
//global config
$configQuery = "SELECT `value` FROM global_config WHERE name ='vl_form'";
$configResult = $db->rawQueryOne($configQuery);
$country = $configResult['value'];

$query = "SELECT vl.remote_sample_code,vl.$primaryKey,vl.facility_id FROM $tableName as vl WHERE (vl.result is NULL or vl.result = '') AND vlsm_country_id = $country AND (vl.remote_sample_code IS NOT NULL OR vl.remote_sample_code NOT LIKE '')";

if (trim($lName) != '') {
	$query = $query . " AND vl.lab_id='" . $lName . "'";
}
if ($_POST['fName'] != '') {
	$query = $query . " AND vl.facility_id='" . $fName . "'";
}
if (isset($scDate) && trim($scDate) != '') {
	$s_c_date = explode("to", $scDate);
	//print_r($s_c_date);die;
	if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
		$start_date = DateUtils::isoDateFormat(trim($s_c_date[0]));
	}
	if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
		$end_date = DateUtils::isoDateFormat(trim($s_c_date[1]));
	}

	if (trim($start_date) == trim($end_date)) {
		$query = $query . ' AND DATE(vl.sample_collection_date) = "' . $start_date . '"';
	} else {
		$query = $query . ' AND DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
	}
}

//$query = $query." ORDER BY f.facility_name ASC";
$query = $query . " ORDER BY vl.request_created_datetime ASC";
// die($query);
$result = $db->rawQuery($query);
?>
<div class="col-md-8">
	<div class="form-group">
		<div class="col-md-12">
			<div class="col-md-12">
				<div style="width:60%;margin:0 auto;clear:both;">
					<a href="#" id="select-all-samplecode" style="float:left" class="btn btn-info btn-xs">Select All&nbsp;&nbsp;<em class="fa-solid fa-chevron-right"></em></a> <a href='#' id='deselect-all-samplecode' style="float:right" class="btn btn-danger btn-xs"><em class="fa-solid fa-chevron-left"></em>&nbsp;Deselect All</a>
				</div><br /><br />
				<select id="sampleCode" name="sampleCode[]" multiple="multiple" class="search">
					<?php
					foreach ($result as $sample) {
					?>
						<option value="<?php echo $sample[$primaryKey]; ?>"><?php echo ($sample['remote_sample_code']); ?></option>
					<?php
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
			selectableFooter: "<div style='background-color: #367FA9;color: white;padding:5px;text-align: center;' class='custom-header' id='unselectableCount'>Available samples(<?php echo count($result); ?>)</div>",
			selectionFooter: "<div style='background-color: #367FA9;color: white;padding:5px;text-align: center;' class='custom-header' id='selectableCount'>Selected samples(0)</div>",
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
				if (this.qs2.cache().matchedResultsCount == 0) {
					$("#sampleSubmit").attr("disabled", true);
					$("#sampleSubmit").css("pointer-events", "none");
				} else {
					$("#sampleSubmit").attr("disabled", false);
					$("#sampleSubmit").css("pointer-events", "auto");
				}
				this.qs1.cache();
				this.qs2.cache();
				$("#unselectableCount").html("Available samples(" + this.qs1.cache().matchedResultsCount + ")");
				$("#selectableCount").html("Selected samples(" + this.qs2.cache().matchedResultsCount + ")");
			},
			afterDeselect: function() {
				//button disabled/enabled
				if (this.qs2.cache().matchedResultsCount == 0) {
					$("#sampleSubmit").attr("disabled", true);
					$("#sampleSubmit").css("pointer-events", "none");
				} else {
					$("#sampleSubmit").attr("disabled", false);
					$("#sampleSubmit").css("pointer-events", "auto");
				}
				this.qs1.cache();
				this.qs2.cache();
				$("#unselectableCount").html("Available samples(" + this.qs1.cache().matchedResultsCount + ")");
				$("#selectableCount").html("Selected samples(" + this.qs2.cache().matchedResultsCount + ")");
			}
		});
		$('#select-all-samplecode').click(function() {
			$('#sampleCode').multiSelect('select_all');
			return false;
		});
		$('#deselect-all-samplecode').click(function() {
			$('#sampleCode').multiSelect('deselect_all');
			$("#sampleSubmit").attr("disabled", true);
			$("#sampleSubmit").css("pointer-events", "none");
			return false;
		});
	});
</script>