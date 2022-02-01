<?php



$general = new \Vlsm\Models\General();
$start_date = '';
$end_date = '';
$urgent = isset($_POST['urgent']) ? $_POST['urgent'] : null;
$fName = isset($_POST['fName']) ? $_POST['fName'] : null;
$sample = isset($_POST['sName']) ? $_POST['sName'] : null;
$gender = $_POST['gender'];
$pregnant = $_POST['pregnant'];
$breastfeeding = $_POST['breastfeeding'];
//global config
$configQuery = "SELECT `value` FROM `global_config` WHERE `name` ='vl_form'";
$configResult = $db->query($configQuery);
$country = $configResult[0]['value'];
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
	$s_c_date = explode("to", $_POST['sampleCollectionDate']);
	//print_r($s_c_date);die;
	if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
		$start_date = $general->dateFormat(trim($s_c_date[0]));
	}
	if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
		$end_date = $general->dateFormat(trim($s_c_date[1]));
	}
}
if (isset($_POST['sampleReceivedAtLab']) && trim($_POST['sampleReceivedAtLab']) != '') {
	$s_c_date = explode("to", $_POST['sampleReceivedAtLab']);
	//print_r($s_c_date);die;
	if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
		$sampleReceivedStartDate = $general->dateFormat(trim($s_c_date[0]));
	}
	if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
		$sampleReceivedEndDate = $general->dateFormat(trim($s_c_date[1]));
	}
}

$query = "SELECT vl.sample_code,vl.vl_sample_id,vl.facility_id,vl.result_status,f.facility_name,f.facility_code FROM vl_request_form as vl INNER JOIN facility_details as f ON vl.facility_id=f.facility_id WHERE (vl.is_sample_rejected IS NULL OR vl.is_sample_rejected = '' OR vl.is_sample_rejected = 'no') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection ='' OR vl.reason_for_sample_rejection = 0) AND (vl.result is NULL or vl.result = '') AND vlsm_country_id = $country  AND vl.sample_code!=''";
if (isset($_POST['batchId'])) {
	$query = $query . " AND (sample_batch_id = '" . $_POST['batchId'] . "' OR sample_batch_id IS NULL OR sample_batch_id = '')";
} else {
	$query = $query . " AND (sample_batch_id IS NULL OR sample_batch_id='')";
}
if (trim($urgent) != '') {
	$query = $query . " AND vl.test_urgency='" . $urgent . "'";
}
if (!empty($_POST['fName']) && is_array($_POST['fName']) && count($_POST['fName']) > 0) {
	$query = $query . " AND vl.facility_id IN (" . implode(',', $_POST['fName']) . ")";
}
if (trim($sample) != '') {
	$query = $query . " AND vl.sample_type='" . $sample . "'";
}
if (trim($gender) != '') {
	$query = $query . " AND vl.patient_gender='" . $gender . "'";
}
if (trim($pregnant) != '') {
	$query = $query . " AND vl.is_patient_pregnant='" . $pregnant . "'";
}
if (trim($breastfeeding) != '') {
	$query = $query . " AND vl.is_patient_breastfeeding='" . $breastfeeding . "'";
}
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
	if (trim($start_date) == trim($end_date)) {
		$query = $query . ' AND DATE(sample_collection_date) = "' . $start_date . '"';
	} else {
		$query = $query . ' AND DATE(sample_collection_date) >= "' . $start_date . '" AND DATE(sample_collection_date) <= "' . $end_date . '"';
	}
}

if (isset($_POST['sampleReceivedAtLab']) && trim($_POST['sampleReceivedAtLab']) != '') {
	if (trim($sampleReceivedStartDate) == trim($sampleReceivedEndDate)) {
		$query = $query . ' AND DATE(sample_received_at_vl_lab_datetime) = "' . $sampleReceivedStartDate . '"';
	} else {
		$query = $query . ' AND DATE(sample_received_at_vl_lab_datetime) >= "' . $sampleReceivedStartDate . '" AND DATE(sample_received_at_vl_lab_datetime) <= "' . $sampleReceivedEndDate . '"';
	}
}
//$query = $query." ORDER BY f.facility_name ASC";
//$query = $query . " ORDER BY vl.last_modified_datetime ASC";
$query = $query . " ORDER BY vl.sample_code ASC";
// echo $query;die;
$result = $db->rawQuery($query);
?>
<div class="col-md-8">
	<div class="form-group">
		<div class="col-md-12">
			<div class="col-md-12">
				<div style="width:60%;margin:0 auto;clear:both;">
					<a href="#" id="select-all-samplecode" style="float:left" class="btn btn-info btn-xs"><?php echo _("Select All");?>&nbsp;&nbsp;<i class="icon-chevron-right"></i></a> <a href='#' id='deselect-all-samplecode' style="float:right" class="btn btn-danger btn-xs"><i class="icon-chevron-left"></i>&nbsp;<?php echo _("Deselect All");?></a>
				</div><br /><br />
				<select id="sampleCode" name="sampleCode[]" multiple="multiple" class="search">
					<?php
					foreach ($result as $sample) {
					?>
						<option value="<?php echo $sample['vl_sample_id']; ?>"><?php echo ucwords($sample['sample_code']) . " - " . ucwords($sample['facility_name']); ?></option>
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
			selectableHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='<?php echo _("Enter Sample Code");?>'>",
			selectionHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='<?php echo _("Enter Sample Code");?>'>",
			selectableFooter: "<div style='background-color: #367FA9;color: white;padding:5px;text-align: center;' class='custom-header' id='unselectableCount'><?php echo _("Available samples");?>(<?php echo count($result); ?>)</div>",
			selectionFooter: "<div style='background-color: #367FA9;color: white;padding:5px;text-align: center;' class='custom-header' id='selectableCount'><?php echo _("Selected samples");?>(0)</div>",
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
					alert("<?php echo _('You have selected Maximum no. of sample');?> " + this.qs2.cache().matchedResultsCount);
					$("#batchSubmit").attr("disabled", false);
					$("#batchSubmit").css("pointer-events", "auto");
				} else if (this.qs2.cache().matchedResultsCount <= noOfSamples) {
					$("#batchSubmit").attr("disabled", false);
					$("#batchSubmit").css("pointer-events", "auto");
				} else if (this.qs2.cache().matchedResultsCount > noOfSamples) {
					alert("<?php echo _('You have already selected Maximum no. of sample');?> " + noOfSamples);
					$("#batchSubmit").attr("disabled", true);
					$("#batchSubmit").css("pointer-events", "none");
				}
				this.qs1.cache();
				this.qs2.cache();
				$("#unselectableCount").html("<?php echo _("Available samples");?>(" + this.qs1.cache().matchedResultsCount + ")");
				$("#selectableCount").html("<?php echo _("Selected samples");?>(" + this.qs2.cache().matchedResultsCount + ")");
			},
			afterDeselect: function() {
				//button disabled/enabled
				if (this.qs2.cache().matchedResultsCount == 0) {
					$("#batchSubmit").attr("disabled", true);
					$("#batchSubmit").css("pointer-events", "none");
				} else if (this.qs2.cache().matchedResultsCount == noOfSamples) {
					alert("<?php echo _('You have selected Maximum no. of sample');?> " + this.qs2.cache().matchedResultsCount);
					$("#batchSubmit").attr("disabled", false);
					$("#batchSubmit").css("pointer-events", "auto");
				} else if (this.qs2.cache().matchedResultsCount <= noOfSamples) {
					$("#batchSubmit").attr("disabled", false);
					$("#batchSubmit").css("pointer-events", "auto");
				} else if (this.qs2.cache().matchedResultsCount > noOfSamples) {
					$("#batchSubmit").attr("disabled", true);
					$("#batchSubmit").css("pointer-events", "none");
				}
				this.qs1.cache();
				this.qs2.cache();
				$("#unselectableCount").html("<?php echo _('Available samples');?>(" + this.qs1.cache().matchedResultsCount + ")");
				$("#selectableCount").html("<?php echo _('Selected samples');?>(" + this.qs2.cache().matchedResultsCount + ")");
			}
		});
		$('#select-all-samplecode').click(function() {
			$('#sampleCode').multiSelect('select_all');
			return false;
		});
		$('#deselect-all-samplecode').click(function() {
			$('#sampleCode').multiSelect('deselect_all');
			$("#batchSubmit").attr("disabled", true);
			$("#batchSubmit").css("pointer-events", "none");
			return false;
		});
	});
</script>