<?php


use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;

/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);
$start_date = '';
$end_date = '';
//global config
$configQuery = "SELECT `value` FROM global_config WHERE name ='vl_form'";
$configResult = $db->query($configQuery);
$country = $configResult[0]['value'];
if (isset($_POST['batchId']) && trim($_POST['batchId']) != '') {
    $batchId = $_POST['batchId'];
}
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
    $s_c_date = explode("to", $_POST['sampleCollectionDate']);
    //print_r($s_c_date);die;
    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $start_date = DateUtility::isoDateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $end_date = DateUtility::isoDateFormat(trim($s_c_date[1]));
    }
}
if (isset($_POST['sampleReceivedAtLab']) && trim($_POST['sampleReceivedAtLab']) != '') {
    $s_c_date = explode("to", $_POST['sampleReceivedAtLab']);
    //print_r($s_c_date);die;
    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $sampleReceivedStartDate = DateUtility::isoDateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $sampleReceivedEndDate = DateUtility::isoDateFormat(trim($s_c_date[1]));
    }
}

$query = "SELECT vl.sample_code, vl.tb_id, vl.facility_id, vl.result_status, vl.sample_batch_id, f.facility_name, f.facility_code FROM form_tb as vl INNER JOIN facility_details as f ON vl.facility_id=f.facility_id WHERE (vl.is_sample_rejected IS NULL OR vl.is_sample_rejected = '' OR vl.is_sample_rejected = 'no') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection ='' OR vl.reason_for_sample_rejection = 0) AND (vl.result is NULL or vl.result = '') AND vl.sample_code!=''";
if (isset($_POST['batchId'])) {
    $query = $query . " AND (sample_batch_id = '" . $_POST['batchId'] . "' OR sample_batch_id IS NULL OR sample_batch_id = '')";
} else {
    $query = $query . " AND (sample_batch_id IS NULL OR sample_batch_id='')";
}

if (isset($_POST['fName']) && is_array($_POST['fName']) && count($_POST['fName']) > 0) {
    $query = $query . " AND vl.facility_id IN (" . implode(',', $_POST['fName']) . ")";
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
$query = $query . " ORDER BY vl.sample_code ASC";

$result = $db->rawQuery($query);
?>
<script type="text/javascript" src="/assets/js/multiselect.min.js"></script>
<script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script>
<div class="row" style="margin: 15px;">
                                   <h4> <?php echo _("Sample Code"); ?></h4>
                                   <div class="col-md-5">
                                        <!-- <div class="col-lg-5"> -->
                                        <select name="sampleCode[]" id="search" class="form-control" size="8" multiple="multiple">
                                        <?php
                                    foreach ($result as $sample) {
                                    ?>
                                        <option value="<?php echo $sample['tb_id']; ?>"><?php echo ($sample['sample_code']) . " - " . ($sample['facility_name']); ?></option>
                                    <?php
                                    }
                                    ?>
                                        </select>
                                   </div>

                                   <div class="col-md-2">
                                        <button type="button" id="search_rightAll" class="btn btn-block"><em class="fa-solid fa-forward"></em></button>
                                        <button type="button" id="search_rightSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-right"></em></button>
                                        <button type="button" id="search_leftSelected" class="btn btn-block"><em class="fa-sharp fa-solid fa-chevron-left"></em></button>
                                        <button type="button" id="search_leftAll" class="btn btn-block"><em class="fa-solid fa-backward"></em></button>
                                   </div>

                                   <div class="col-md-5">
                                        <select name="to[]" id="search_to" class="form-control" size="8" multiple="multiple"></select>
                                   </div>
                              </div>
<script>
    $(document).ready(function() {
        $('#search').multiselect({
               search: {
                    left: '<input type="text" name="q" class="form-control" placeholder="<?php echo _("Search"); ?>..." />',
                    right: '<input type="text" name="q" class="form-control" placeholder="<?php echo _("Search"); ?>..." />',
               },
               fireSearch: function(value) {
                    return value.length > 3;
               }
          });
    });
</script>