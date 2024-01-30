<?php

use App\Services\BatchService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\UsersService;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

if (empty($_GET['type'])) {
    header("Location: /batch/batches.php");
}


$sampleTypeStatus = "status";
$genericHide = "";
if (isset($_GET['type']) && $_GET['type'] == 'vl') {
    $title = "Viral Load";
    $sampleTypeTable = "r_vl_sample_type";
} elseif (isset($_GET['type']) && $_GET['type'] == 'eid') {
    $title = "Early Infant Diagnosis";
    $sampleTypeTable = "r_eid_sample_type";
} elseif (isset($_GET['type']) && $_GET['type'] == 'covid19') {
    $title = "Covid-19";
    $sampleTypeTable = "r_covid19_sample_type";
} elseif (isset($_GET['type']) && $_GET['type'] == 'hepatitis') {
    $title = "Hepatitis";
    $sampleTypeTable = "r_hepatitis_sample_type";
} elseif (isset($_GET['type']) && $_GET['type'] == 'tb') {
    $title = "TB";
    $sampleTypeTable = "r_tb_sample_type";
} elseif (isset($_GET['type']) && $_GET['type'] == 'generic-tests') {
    $title = "Other Lab Tests";
    $sampleTypeTable = "r_generic_sample_types";
    $sampleTypeStatus = "sample_type_status";
    $genericHide = "display:none;";
}

$title = _translate($title . " | Add Batch");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var BatchService $batchService */
$batchService = ContainerRegistry::get(BatchService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$healthFacilites = $facilitiesService->getHealthFacilities($_GET['type']);

$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$userNameList = $usersService->getAllUsers(null, null, 'drop-down');


//Get active machines
$testPlatformResult = $general->getTestingPlatforms($_GET['type']);

[$maxId, $batchCode] = $batchService->createBatchCode();
//Set last machine label order
$machinesLabelOrder = [];
foreach ($testPlatformResult as $machine) {
    $lastOrderQuery = "SELECT label_order   FROM batch_details
                        WHERE machine = ? ORDER BY request_created_datetime DESC";
    $lastOrderInfo = $db->rawQuery($lastOrderQuery, [$machine['instrument_id']]);
    if (isset($lastOrderInfo[0]['label_order']) && trim((string) $lastOrderInfo[0]['label_order']) != '') {
        $machinesLabelOrder[$machine['instrument_id']] = implode(",", json_decode((string) $lastOrderInfo[0]['label_order'], true));
    } else {
        $machinesLabelOrder[$machine['instrument_id']] = '';
    }
}
$testTypeQuery = "SELECT * FROM r_test_types
                    WHERE test_status='active'
                    ORDER BY test_standard_name ASC";
$testTypeResult = $db->rawQuery($testTypeQuery);

$sQuery = "SELECT * FROM $sampleTypeTable where  $sampleTypeStatus='active'";
$sResult = $db->rawQuery($sQuery);

$fundingSourceList = $general->getFundingSources();

?>
<link href="/assets/css/multi-select.css" rel="stylesheet" />
<style>
    .select2-selection__choice {
        color: #000000 !important;
    }

    #ms-sampleCode {
        width: 100%;
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
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-pen-to-square"></em>
            <?php echo _translate("Create Batch"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
                    <?php echo _translate("Home"); ?>
                </a></li>
            <li class="active">
                <?php echo _translate("Batch"); ?>
            </li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="box box-default">
            <?php if (!empty($_GET['type']) && $_GET['type'] == 'generic-tests') { ?>
                <div class="box-header with-border">
                    <div class="row">
                        <div class="col-xs-6 col-md-6">
                            <div class="form-group" style="margin-left:30px; margin-top:30px;">
                                <label for="testType">Test Type</label>
                                <select class="form-control" name="testType" id="testType" title="Please choose test type" style="width:100%;" onchange="getBatchForm(this)">
                                    <option value=""> -- Select -- </option>
                                    <?php foreach ($testTypeResult as $testType) { ?>
                                        <option value="<?php echo $testType['test_type_id']; ?>"><?php echo $testType['test_standard_name'] . ' (' . $testType['test_loinc_code'] . ')' ?></option>
                                    <?php } ?>
                                </select>
                                <span class="batchAlert" style="font-size:1.1em;color: red;">Choose test type to add
                                    relavent sample bacth code</span>
                            </div>
                        </div>
                        <div class="col-xs-6 col-md-6">
                            <div class="box-header with-border">
                                <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span>
                                    <?php echo _translate("indicates required fields"); ?> &nbsp;
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } else { ?>
                <div class="box-header with-border">
                    <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span>
                        <?php echo _translate("indicates required fields"); ?> &nbsp;
                    </div>
                </div>
            <?php } ?>
            <table aria-describedby="table" class="table batchDiv" aria-hidden="true" style="margin-top:20px;width: 100%;<?php echo $genericHide; ?>">
                <tr>
                    <th style="width: 20%;" scope="col">
                        <?php echo _translate("Testing Platform"); ?>&nbsp;<span class="mandatory">*</span>
                    </th>
                    <td style="width: 30%;">
                        <select name="machine" id="machine" class="form-control isRequired" title="<?php echo _translate('Please choose machine'); ?>">
                            <option value="">
                                <?php echo _translate("-- Select --"); ?>
                            </option>
                            <?php foreach ($testPlatformResult as $machine) {
                                $labelOrder = $machinesLabelOrder[$machine['instrument_id']]; ?>
                                <option value="<?php echo $machine['instrument_id']; ?>" data-no-of-samples="<?php echo $machine['max_no_of_samples_in_a_batch']; ?>"><?= $machine['machine_name']; ?></option>
                            <?php } ?>
                        </select>
                    </td>
                    <th style="width: 20%;" scope="col">
                        <?php echo _translate("Facility"); ?>
                    </th>
                    <td style="width: 30%;">
                        <select style="width: 100%;" class="form-control" id="facilityName" name="facilityName" title="<?php echo _translate('Please select facility name'); ?>" multiple="multiple">
                            <?= $facilitiesDropdown; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th style="width: 20%;" scope="col">
                        <?php echo _translate("Sample Collection Date"); ?>
                    </th>
                    <td style="width: 30%;">
                        <input type="text" id="sampleCollectionDate" name="sampleCollectionDate" class="form-control daterange" placeholder="<?php echo _translate('Select Collection Date'); ?>" readonly style="width:100%;background:#fff;" />
                    </td>
                    <th style="width: 20%;" scope="col">
                        <?php echo _translate("Date Sample Receieved at Lab"); ?>
                    </th>
                    <td style="width: 30%;">
                        <input type="text" id="sampleReceivedAtLab" name="sampleReceivedAtLab" class="form-control daterange" placeholder="<?php echo _translate('Select Received at Lab Date'); ?>" readonly style="width:100%;background:#fff;" />
                    </td>
                </tr>
                <tr>
                    <th style="width: 20%;" scope="col">
                        <?php echo _translate("Positions"); ?>
                    </th>
                    <td style="width: 30%;">
                        <select id="positions-type" class="form-control" title="<?php echo _translate('Please select the postion'); ?>">
                            <option value="numeric">
                                <?php echo _translate("Numeric"); ?>
                            </option>
                            <option value="alpha-numeric">
                                <?php echo _translate("Alpha Numeric"); ?>
                            </option>
                        </select>
                    </td>
                    <th scope="col"><?php echo _translate("Sample Type"); ?></th>
                    <td>
                        <select class="form-control" id="sampleType" name="sampleType" title="<?php echo _translate('Please select sample type'); ?>">
                            <option value=""> <?php echo _translate("-- Select --"); ?> </option>
                            <?php
                            foreach ($sResult as $type) {
                            ?>
                                <option value="<?php echo $type['sample_id']; ?>"><?php echo ($type['sample_name']); ?></option>
                            <?php
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td><label for="fundingSource"><?= _translate("Funding Source"); ?></label></td>
                    <td>
                        <select class="form-control" name="fundingSource" id="fundingSource" title="Please choose source de financement" style="width:100%;">
                            <option value=""> -- Select -- </option>
                            <?php
                            foreach ($fundingSourceList as $fundingSource) {
                            ?>
                                <option value="<?php echo $fundingSource['funding_source_id']; ?>"><?= $fundingSource['funding_source_name']; ?></option>
                            <?php } ?>
                        </select>
                    </td>
                    <td><label for="fundingSource"><?= _translate("Samples Entered By"); ?></label></td>
                    <td>
                        <select class="form-control" name="userId" id="userId" title="Please choose source de financement" style="width:100%;">
                            <?php echo $general->generateSelectOptions($userNameList, null, '--Select--'); ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <td colspan="4">&nbsp;<input type="button" onclick="getSampleCodeDetails();" value="<?php echo _translate('Filter Samples'); ?>" class="btn btn-success btn-sm">
                        &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>
                                <?php echo _translate("Reset Filters"); ?>
                            </span></button>
                    </td>
                </tr>
            </table>
            <!-- /.box-header -->
            <div class="box-body batchDiv" style="<?php echo $genericHide; ?>">
                <!-- form start -->
                <form class="form-horizontal" method="post" style="display:none;" name="addBatchForm" id="addBatchForm" autocomplete="off" action="save-batch-helper.php">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-10">
                                <div class="form-group">
                                    <label for="batchCode" class="col-lg-2 control-label">
                                        <?php echo _translate("Batch Code"); ?> <span class="mandatory">*</span>
                                    </label>
                                    <div class="col-lg-7" style="margin-left:3%;">
                                        <input type="text" readonly="readonly" class="form-control isRequired" id="batchCode" name="batchCode" placeholder="<?php echo _translate('Batch Code'); ?>" title="<?php echo _translate('Please enter batch code'); ?>" value="<?= $batchCode; ?>" onblur='checkNameValidation("batch_details","batch_code",this,null,"<?php echo _translate("This batch code already exists.Try another batch code"); ?>",null)' />
                                        <input type="hidden" name="batchCodeKey" id="batchCodeKey" value="<?php echo $maxId; ?>" />
                                        <input type="hidden" name="platform" id="platform" value="" />
                                        <input type="hidden" name="positions" id="positions" value="" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row" id="sampleDetails">
                        </div>
                        <div class="row col-md-12" id="alertText"></div>
                    </div>
                    <!-- /.box-body -->
                    <div class="box-footer">
                        <input type="hidden" name="selectedSample" id="selectedSample" />
                        <input type="hidden" name="type" id="type" value="<?php echo $_GET['type']; ?>" />
                        <a id="batchSubmit" class="btn btn-primary" href="javascript:void(0);" title="<?php echo _translate('Please select machine'); ?>" onclick="validateNow();return false;"><?php echo _translate("Save and Next"); ?></a>
                        <a href="batches.php?type=<?php echo $_GET['type']; ?>" class="btn btn-default"> <?php echo _translate("Cancel"); ?></a>
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
<script type="text/javascript" src="/assets/js/multiselect.min.js"></script>
<script type="text/javascript" src="/assets/js/jasny-bootstrap.js"></script>
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
    var startDate = "";
    var endDate = "";
    noOfSamples = 0;
    sortedTitle = [];
    let batchXhr = null;
    $(document).ready(function() {
        $("#testType").select2({
            width: '100%',
            placeholder: "<?php echo _translate("Select Test Type"); ?>"
        });
        $("#facilityName").select2({
            placeholder: "<?php echo _translate("Select Facilities"); ?>"
        });

        $('.daterange').daterangepicker({
                locale: {
                    cancelLabel: "<?= _translate("Clear", true); ?>",
                    format: 'DD-MMM-YYYY',
                    separator: ' to ',
                },
                showDropdowns: true,
                alwaysShowCalendars: false,
                startDate: moment().subtract(28, 'days'),
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
        var selVal = [];
        $('#search_to option').each(function(i, selected) {
            selVal[i] = $(selected).val();
        });
        $("#selectedSample").val(selVal);
        var selected = $("#machine").find('option:selected');
        noOfSamples = selected.data('no-of-samples');
        if (noOfSamples < selVal.length) {
            alert("<?= _translate("You have selected more than allowed number of samples", true); ?>");
            return false;
        }

        if (selVal == "") {
            alert("<?= _translate("Please select one or more samples", true); ?>");
            return false;
        }

        flag = deforayValidator.init({
            formId: 'addBatchForm'
        });
        if (flag) {
            $("#positions").val($('#positions-type').val());
            $.blockUI();
            document.getElementById('addBatchForm').submit();
        }
    }

    function checkNameValidation(tableName, fieldName, obj, fnct, alrt, callback) {
        let removeDots = obj.value.replace(/\./g, "");
        removeDots = removeDots.replace(/\,/g, "");
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

        if (batchXhr) {
            batchXhr.abort();
        }

        $.blockUI();

        var machine = $("#machine").val();
        if (machine == null || machine == '') {
            $.unblockUI();
            alert("<?= _translate("You have to choose a testing platform to proceed", true); ?>");
            return false;
        }
        var facilityId = $("#facilityName").val();


        batchXhr = $.post("/batch/get-samples-batch.php", {
                sampleCollectionDate: $("#sampleCollectionDate").val(),
                sampleReceivedAtLab: $("#sampleReceivedAtLab").val(),
                type: '<?= $_GET['type']; ?>',
                testType: $('#testType').val(),
                facilityId: facilityId,
                sName: $("#sampleType").val(),
                fundingSource: $("#fundingSource").val(),
                userId: $("#userId").val(),
            },
            function(data) {
                if (data != "") {
                    $("#sampleDetails").html(data);
                    $("#addBatchForm").show();
                }
            });
        $.unblockUI();
    }

    $("#machine").change(function() {
        var self = this.value;
        if (self != '') {
            getSampleCodeDetails();
            $("#platform").val($("#machine").val());
            var selected = $(this).find('option:selected');
            noOfSamples = selected.data('no-of-samples');
            $('#alertText').html("<?= _translate("Maximum number of samples allowed for the selected platform", true); ?> : " + noOfSamples);
        } else {
            $('#alertText').html('');
        }
    });

    function getBatchForm(obj) {
        if (obj.value != "") {
            $(".batchDiv").show();
            $('.batchAlert').hide();
            if ($('#machine').val() != '') {
                getSampleCodeDetails();
            }
        } else {
            $(".batchDiv").hide();
            $('.batchAlert').show();
        }
    }
</script>
<?php require_once APPLICATION_PATH . '/footer.php';
