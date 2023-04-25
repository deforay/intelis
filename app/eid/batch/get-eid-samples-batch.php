<?php

use App\Services\CommonService;
use App\Utilities\DateUtils;

$general = new CommonService();
$start_date = '';
$end_date = '';

if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
    $s_c_date = explode("to", $_POST['sampleCollectionDate']);
    //print_r($s_c_date);die;
    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $start_date = DateUtils::isoDateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $end_date = DateUtils::isoDateFormat(trim($s_c_date[1]));
    }
}
if (isset($_POST['sampleReceivedAtLab']) && trim($_POST['sampleReceivedAtLab']) != '') {
    $s_c_date = explode("to", $_POST['sampleReceivedAtLab']);
    //print_r($s_c_date);die;
    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $sampleReceivedStartDate = DateUtils::isoDateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $sampleReceivedEndDate = DateUtils::isoDateFormat(trim($s_c_date[1]));
    }
}

$query = "SELECT vl.sample_code,vl.eid_id,vl.facility_id,vl.result_status,f.facility_name,f.facility_code 
            FROM form_eid as vl 
            INNER JOIN facility_details as f ON vl.facility_id=f.facility_id 
            WHERE (vl.is_sample_rejected IS NULL OR vl.is_sample_rejected = '' OR vl.is_sample_rejected = 'no') 
            AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection ='' OR vl.reason_for_sample_rejection = 0) 
            AND (vl.result is NULL or vl.result = '') 
            AND vl.sample_code!='' AND vl.sample_code is not null ";
if (isset($_POST['batchId'])) {
    $query = $query . " AND (sample_batch_id = '" . $_POST['batchId'] . "' OR sample_batch_id IS NULL OR sample_batch_id = '')";
} else {
    $query = $query . " AND (sample_batch_id IS NULL OR sample_batch_id='')";
}

if (!empty($_POST['fName']) && is_array($_POST['fName'])) {
    $query = $query . " AND vl.facility_id IN (" . implode(',', $_POST['fName']) . ")";
}

if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
    if (trim($start_date) == trim($end_date)) {
        $query = $query . ' AND DATE(sample_collection_date) like "' . $start_date . '"';
    } else {
        $query = $query . ' AND DATE(sample_collection_date) >= "' . $start_date . '" AND DATE(sample_collection_date) <= "' . $end_date . '"';
    }
}

if (isset($_POST['sampleReceivedAtLab']) && trim($_POST['sampleReceivedAtLab']) != '') {
    if (trim($sampleReceivedStartDate) == trim($sampleReceivedEndDate)) {
        $query = $query . ' AND DATE(sample_received_at_vl_lab_datetime) like "' . $sampleReceivedStartDate . '"';
    } else {
        $query = $query . ' AND DATE(sample_received_at_vl_lab_datetime) >= "' . $sampleReceivedStartDate . '" AND DATE(sample_received_at_vl_lab_datetime) <= "' . $sampleReceivedEndDate . '"';
    }
}
//$query = $query." ORDER BY f.facility_name ASC";
//$query = $query . " ORDER BY vl.last_modified_datetime ASC";
$query = $query . " ORDER BY vl.sample_code ASC";
//echo $query;die;
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
                        <option value="<?php echo $sample['eid_id']; ?>"><?php echo ($sample['sample_code']) . " - " . ($sample['facility_name']); ?></option>
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
      /*  $('.search').multiSelect({
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
                //button disabled/enabled
                if (this.qs2.cache().matchedResultsCount == noOfSamples) {
                    alert("You have selected maximum number of samples - " + this.qs2.cache().matchedResultsCount);
                    $("#batchSubmit").attr("disabled", false);
                    $("#batchSubmit").css("pointer-events", "auto");
                } else if (this.qs2.cache().matchedResultsCount <= noOfSamples) {
                    $("#batchSubmit").attr("disabled", false);
                    $("#batchSubmit").css("pointer-events", "auto");
                } else if (this.qs2.cache().matchedResultsCount > noOfSamples) {
                    alert("You have already selected Maximum no. of sample " + noOfSamples);
                    $("#batchSubmit").attr("disabled", true);
                    $("#batchSubmit").css("pointer-events", "none");
                }
                this.qs1.cache();
                this.qs2.cache();
                $("#unselectableCount").html("Available samples(" + this.qs1.cache().matchedResultsCount + ")");
                $("#selectableCount").html("Selected samples(" + this.qs2.cache().matchedResultsCount + ")");
            },
            afterDeselect: function() {
                //button disabled/enabled
                if (this.qs2.cache().matchedResultsCount == 0) {
                    $("#batchSubmit").attr("disabled", true);
                    $("#batchSubmit").css("pointer-events", "none");
                } else if (this.qs2.cache().matchedResultsCount == noOfSamples) {
                    alert("You have selected maximum number of samples - " + this.qs2.cache().matchedResultsCount);
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
                $("#unselectableCount").html("Available samples(" + this.qs1.cache().matchedResultsCount + ")");
                $("#selectableCount").html("Selected samples(" + this.qs2.cache().matchedResultsCount + ")");
            }
        });*/
        $('#select-all-samplecode').click(function() {
            $('#sampleCode').multiSelect('select_all');
            return false;
        });
        $('#deselect-all-samplecode').click(function() {
            $('#sampleCode').multiSelect('deselect_all');
           // $("#batchSubmit").attr("disabled", true);
            //$("#batchSubmit").css("pointer-events", "none");
            return false;
        });
    });
</script>