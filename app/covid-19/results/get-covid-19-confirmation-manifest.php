<?php


use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);
$start_date = '';
$end_date = '';
//global config
$configQuery = "SELECT `value` FROM global_config WHERE name ='vl_form'";
$configResult = $db->query($configQuery);
$country = $configResult[0]['value'];

$query = "SELECT vl.sample_code,vl.covid19_id,vl.facility_id,vl.result_status,f.facility_name,f.facility_code FROM form_covid19 as vl INNER JOIN facility_details as f ON vl.facility_id=f.facility_id WHERE (vl.is_sample_rejected IS NULL OR vl.is_sample_rejected = '' OR vl.is_sample_rejected = 'no') AND (vl.reason_for_sample_rejection IS NULL OR vl.reason_for_sample_rejection ='' OR vl.reason_for_sample_rejection = 0) AND vl.result = 'positive' AND vlsm_country_id = $country  AND (vl.positive_test_manifest_id IS NULL OR vl.positive_test_manifest_id = '') AND (vl.positive_test_manifest_code IS NULL OR vl.positive_test_manifest_code = '')";

$query = $query . " ORDER BY vl.last_modified_datetime ASC";
// echo $query;die;
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
                    <?php foreach ($result as $sample) { ?>
                        <option value="<?php echo $sample['covid19_id']; ?>"><?php echo ($sample['sample_code']) . " - " . ($sample['facility_name']); ?></option>
                    <?php } ?>
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