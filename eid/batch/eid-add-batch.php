<?php
ob_start();
$title = "EID | Add Batch";
#require_once('../../startup.php');
include_once(APPLICATION_PATH . '/header.php');

$general = new \Vlsm\Models\General();
$facilitiesDb = new \Vlsm\Models\Facilities();
$healthFacilites = $facilitiesDb->getHealthFacilities('eid');

$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");


//Get active machines
$testPlatformResult = $general->getTestingPlatforms('eid');
// $query = "SELECT vl.sample_code,vl.eid_id,vl.facility_id,f.facility_name,f.facility_code FROM eid_form as vl INNER JOIN facility_details as f ON vl.facility_id=f.facility_id where sample_batch_id is NULL OR sample_batch_id='' ORDER BY f.facility_name ASC";
// $result = $db->rawQuery($query);


$start_date = date('Y-m-d');
$end_date = date('Y-m-d');
$maxId = $general->createBatchCode($start_date, $end_date);
//Set last machine label order
$machinesLabelOrder = array();
foreach ($testPlatformResult as $machine) {
    $lastOrderQuery = "SELECT label_order FROM batch_details WHERE machine ='" . $machine['config_id'] . "' ORDER BY request_created_datetime DESC";
    $lastOrderInfo = $db->query($lastOrderQuery);
    if (isset($lastOrderInfo[0]['label_order']) && trim($lastOrderInfo[0]['label_order']) != '') {
        $machinesLabelOrder[$machine['config_id']] = implode(",", json_decode($lastOrderInfo[0]['label_order'], true));
    } else {
        $machinesLabelOrder[$machine['config_id']] = '';
    }
}
//print_r($machinesLabelOrder);
?>
<link href="/assets/css/multi-select.css" rel="stylesheet" />
<style>
    .select2-selection__choice {
        color: #000000 !important;
    }

    #ms-sampleCode {
        width: 110%;
    }

    .showFemaleSection {
        display: none;
    }

    #sortableRow {
        list-style-type: none;
        margin: 30px 0px 30px 0px;
        padding: 0;
        width: 100%;
        text-align: center;
    }

    #sortableRow li {
        color: #333 !important;
        font-size: 16px;
    }

    #alertText {
        text-shadow: 1px 1px #eee;
    }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><i class="fa fa-edit"></i> Create Batch</h1>
        <ol class="breadcrumb">
            <li><a href="/"><i class="fa fa-dashboard"></i> Home</a></li>
            <li class="active">Batch</li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">

        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required field &nbsp;</div>
            </div>
            <table class="table" cellpadding="1" cellspacing="3" style="margin-left:1%;margin-top:20px;width: 100%;">
                <tr>
                    <th>Testing Platform&nbsp;<span class="mandatory">*</span> </th>
                    <td>
                        <select name="machine" id="machine" class="form-control isRequired" title="Please choose machine" style="width:280px;">
                            <option value=""> -- Select -- </option>
                            <?php
                            foreach ($testPlatformResult as $machine) {
                                $labelOrder = $machinesLabelOrder[$machine['config_id']];
                            ?>
                                <option value="<?php echo $machine['config_id']; ?>" data-no-of-samples="<?php echo $machine['max_no_of_samples_in_a_batch']; ?>"><?php echo ($machine['machine_name']); ?></option>
                            <?php } ?>
                        </select>
                    </td>
                    <th>Facility</th>
                    <td>
                        <select style="width: 275px;" class="form-control" id="facilityName" name="facilityName" title="Please select facility name" multiple="multiple">
                            <?= $facilitiesDropdown; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Sample Collection Date</th>
                    <td>
                        <input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control daterange" placeholder="Select Collection Date" readonly style="width:275px;background:#fff;" />
                    </td>
                    <th>Date Sample Receieved at Lab</th>
                    <td>
                        <input type="text" id="sampleReceivedAtLab" name="sampleReceivedAtLab" class="form-control daterange" placeholder="Select Received at Lab Date" readonly style="width:275px;background:#fff;" />
                    </td>
                </tr>
                <tr>
                    <th><?php echo _("Positions"); ?></th>
                    <td>
                        <select id="positions-type" class="form-control" title="Please select the postion">
                            <option value="numeric"><?php echo _("Numeric"); ?></option>
                            <option value="alpha-numeric"><?php echo _("Alpha Numeric"); ?></option>
                        </select>
                    </td>
                    <th></th>
                    <td></td>
                </tr>
                <tr>
                    <td colspan="4">&nbsp;<input type="button" onclick="getSampleCodeDetails();" value="Filter Samples" class="btn btn-success btn-sm">
                        &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>Reset Filters</span></button>
                    </td>
                </tr>
            </table>
            <!-- /.box-header -->
            <div class="box-body">
                <!-- form start -->
                <form class="form-horizontal" method="post" name="addBatchForm" id="addBatchForm" autocomplete="off" action="eid-add-batch-helper.php">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="batchCode" class="col-lg-4 control-label">Batch Code <span class="mandatory">*</span></label>
                                    <div class="col-lg-7" style="margin-left:3%;">
                                        <input type="text" class="form-control isRequired" id="batchCode" name="batchCode" placeholder="Batch Code" title="Please enter batch code" value="<?php echo date('Ymd') . $maxId; ?>" onblur="checkNameValidation('batch_details','batch_code',this,null,'This batch code already exists.Try another batch code',null)" />
                                        <input type="hidden" name="batchCodeKey" id="batchCodeKey" value="<?php echo $maxId; ?>" />
                                        <input type="hidden" name="platform" id="platform" value="" />
                                        <input type="hidden" name="positions" id="positions" value="" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row" id="sampleDetails">
                            <div class="col-md-8">
                                <div class="form-group">
                                    <div class="col-md-12">
                                        <div class="col-md-12">
                                            <div style="width:60%;margin:0 auto;clear:both;">
                                                <a href='#' id='select-all-samplecode' style="float:left" class="btn btn-info btn-xs">Select All&nbsp;&nbsp;<i class="icon-chevron-right"></i></a> <a href='#' id='deselect-all-samplecode' style="float:right" class="btn btn-danger btn-xs"><i class="icon-chevron-left"></i>&nbsp;Deselect All</a>
                                            </div><br /><br />
                                            <select id='sampleCode' name="sampleCode[]" multiple='multiple' class="search"></select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row col-md-12" id="alertText" style="font-size:20px;"></div>
                    </div>
                    <!-- /.box-body -->
                    <div class="box-footer">
                        <a id="batchSubmit" class="btn btn-primary" href="javascript:void(0);" title="Please select machine" onclick="validateNow();return false;" style="pointer-events:none;" disabled>Save and Next</a>
                        <a href="eid-batches.php" class="btn btn-default"> Cancel</a>
                    </div>
                    <!-- /.box-footer -->
                </form>
                <!-- /.row -->
            </div>

        </div>
        <!-- /.box -->

    </section>
    <!-- /.content -->
</div>
<script src="/assets/js/jquery.multi-select.js"></script>
<script src="/assets/js/jquery.quicksearch.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
    var startDate = "";
    var endDate = "";
    noOfSamples = 0;
    sortedTitle = [];
    $(document).ready(function() {

        $("#facilityName").select2({
            placeholder: "Select Facilities"
        });

        $('.daterange').daterangepicker({
                locale: {
                    cancelLabel: 'Clear'
                },
                format: 'DD-MMM-YYYY',
                separator: ' to ',
                startDate: moment().subtract(29, 'days'),
                endDate: moment(),
                maxDate: moment(),
                ranges: {
                    'Today': [moment(), moment()],
                    'Yesterday': [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                    'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
                }
            },
            function(start, end) {
                startDate = start.format('YYYY-MM-DD');
                endDate = end.format('YYYY-MM-DD');
            });
        $('.daterange').val("");
    });

    function validateNow() {
        flag = deforayValidator.init({
            formId: 'addBatchForm'
        });

        if (flag) {
            $("#positions").val($('#positions-type').val());
            $.blockUI();
            document.getElementById('addBatchForm').submit();
        }
    }

    //$("#auditRndNo").multiselect({height: 100,minWidth: 150});
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
                    alert("You have selected Maximum no. of sample " + this.qs2.cache().matchedResultsCount);
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
            },
            afterDeselect: function() {
                //button disabled/enabled
                if (this.qs2.cache().matchedResultsCount == 0) {
                    $("#batchSubmit").attr("disabled", true);
                    $("#batchSubmit").css("pointer-events", "none");
                } else if (this.qs2.cache().matchedResultsCount == noOfSamples) {
                    alert("You have selected Maximum no. of sample " + this.qs2.cache().matchedResultsCount);
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

    function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
        var removeDots = obj.value.replace(/\./g, "");
        var removeDots = removeDots.replace(/\,/g, "");
        //str=obj.value;
        removeDots = removeDots.replace(/\s{2,}/g, ' ');

        $.post("/includes/checkDuplicate.php", {
                tableName: tableName,
                fieldName: fieldName,
                value: removeDots.trim(),
                fnct: fnct,
                format: "html"
            },
            function(data) {
                if (data === '1') {
                    alert(alrt);
                    duplicateName = false;
                    document.getElementById(obj.id).value = "";
                }
            });
    }

    function getSampleCodeDetails() {

        var machine = $("#machine").val();
        if (machine == null || machine == '') {
            $.unblockUI();
            alert('You have to choose a testing platform to proceed');
            return false;
        }
        var fName = $("#facilityName").val();

        $.blockUI();
        $.post("/eid/batch/get-eid-samples-batch.php", {
                sampleCollectionDate: $("#sampleCollectionDate").val(),
                sampleReceivedAtLab: $("#sampleReceivedAtLab").val(),
                fName: fName
            },
            function(data) {
                if (data != "") {
                    $("#sampleDetails").html(data);
                    $("#batchSubmit").attr("disabled", true);
                    $("#batchSubmit").css("pointer-events", "none");
                }
            });
        $.unblockUI();
    }



    $("#machine").change(function() {
        var self = this.value;
        if (self != '') {
            //getSampleCodeDetails();
            $("#platform").val($("#machine").val());
            var selected = $(this).find('option:selected');
            noOfSamples = selected.data('no-of-samples');
            $('#alertText').html('You have picked ' + $("#machine option:selected").text() + ' testing platform and it has limit of maximum ' + noOfSamples + ' samples per batch');
        } else {
            $('.ms-list').html('');
            $('#alertText').html('');
        }
    });
</script>


<?php

include_once(APPLICATION_PATH . '/footer.php');
