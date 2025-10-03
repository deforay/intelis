<?php

use App\Services\TestsService;
use App\Services\UsersService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

$testType = 'tb';

$sampleTypeStatus = "status";
$genericHide = "";
if (isset($testType) && $testType == 'generic-tests') {
    $sampleTypeStatus = "sample_type_status";
    $genericHide = "display:none;";
}


$testShortCode = TestsService::getTestShortCode($testType);
$refTable = TestsService::getTestTableName($testType);
$refPrimaryColumn = TestsService::getPrimaryColumn($testType);
$sampleTypeTable = TestsService::getSpecimenTypeTable($testType);
$patientIdColumn = TestsService::getPatientIdColumn($testType);
$resultColumn = TestsService::getResultColumn($testType);

$title = $testShortCode . " | " . _translate("Add Batch");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$healthFacilites = $facilitiesService->getHealthFacilities($testType);


$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$userNameList = $usersService->getAllUsers(null, 'active', 'drop-down');


$sQuery = "SELECT * FROM $sampleTypeTable where  $sampleTypeStatus='active'";
$sResult = $db->rawQuery($sQuery);

$fundingSourceList = $general->getFundingSources();
$formId = (int) $general->getGlobalConfig('vl_form');

?>
<link href="/assets/css/multi-select.css" rel="stylesheet" />
<style>
    .select2-selection__choice {
        color: #000000 !important;
    }

    #ms-unreferralSamples {
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
            <?php echo _translate("Referral Labs"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
                    <?php echo _translate("Home"); ?>
                </a></li>
            <li class="active">
                <?php echo _translate("Referral Labs"); ?>
            </li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="box box-default">
            &nbsp;<button class="btn btn-primary btn-sm pull-left" style="margin-right:5px;" onclick="hideAdvanceSearch('filter','advanceFilter');"><span>
                    <?php echo _translate("Show Advanced Search Options"); ?>
                </span></button>

            <table aria-describedby="table" id="advanceFilter" class="table referralDiv" aria-hidden="true" style="display: none;margin-top:20px;width: 100%;<?php echo $genericHide; ?>">
                <tr>
                    <th style="width: 20%;" scope="col">
                        <?php echo _translate("Facility"); ?>
                    </th>
                    <td style="width: 30%;">
                        <select style="width: 100%;" class="" id="facilityName" name="facilityName" title="<?php echo _translate('Please select facility name'); ?>" multiple="multiple">
                            <?= $facilitiesDropdown; ?>
                        </select>
                    </td>
                    <td><label for="fundingSource"><?= _translate("Samples Entered By"); ?></label></td>
                    <td>
                        <select class="form-control select2" name="userId" id="userId" title="Please choose source de financement" style="width:100%;">
                            <?php echo $general->generateSelectOptions($userNameList, null, '--Select--'); ?>
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
                        <?php echo _translate("Last Modified"); ?>
                    </th>
                    <td style="width: 30%;">
                        <input type="text" id="lastModifiedDateTime" name="lastModifiedDateTime" class="form-control daterange" placeholder="<?php echo _translate('Last Modified'); ?>" readonly style="width:100%;background:#fff;" />
                    </td>
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
                </tr>
                <tr>
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
                    <th><label for="fundingSource"><?= _translate("Funding Source"); ?></label></th>
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
                </tr>
                <tr>
                    <td><label for="sortBy"><?= _translate("Sort By"); ?></label></td>

                    <td><select class="form-control" id="sortBy" name="sortBy">
                            <option "selected='selected'" value="requestCreated"><?= _translate("Request Created"); ?></option>
                            <option value="lastModified"><?= _translate("Last Modified"); ?></option>
                            <option value="sampleCode"><?= _translate("Sample ID"); ?></option>
                            <option value="labAssignedCode"><?= _translate("Lab Assigned Code"); ?></option>
                        </select></td>
                    <td><label for="sortType"><?= _translate("Sort Type"); ?></label></td>
                    <td>
                        <select class="form-control" id="sortType">
                            <option "selected='selected'" value="asc"><?= _translate("Ascending"); ?></option>
                            <option value="desc"><?= _translate("Descending"); ?></option>
                        </select>
                    </td>
                </tr>

                <tr>
                    <td colspan="4">&nbsp;<input type="button" onclick="getSampleCodeDetails();" value="<?php echo _translate('Filter Samples'); ?>" class="btn btn-success btn-sm">
                        &nbsp;<button class="btn btn-danger btn-sm" onclick="document.location.href = document.location"><span>
                                <?php echo _translate("Reset Filters"); ?>
                            </span></button>
                        &nbsp;<button class="btn btn-danger btn-sm" onclick="hideAdvanceSearch('advanceFilter','filter');"><span>
                                <?php echo _translate("Hide Advanced Search Options"); ?>
                            </span></button>
                    </td>
                </tr>
            </table>

            <!-- /.box-header -->
            <div class="box-body referralDiv" style="<?php echo $genericHide; ?>">
                <!-- form start -->
                <form class="form-horizontal" method="post" style="display:none;" name="referralForm" id="referralForm" autocomplete="off" action="save-tb-referral-helper.php">
                    <div class="box-body">
                        <div class="row" id="sampleDetails">
                        </div>
                        <div class="row col-md-12" id="alertText"></div>
                    </div>
                    <!-- /.box-body -->
                    <div class="box-footer">
                        <input type="hidden" name="referralSamples" id="referralSamples" />
                        <input type="hidden" name="sortBy" class="sortBy" />
                        <input type="hidden" name="sortType" class="sortType" />
                        <input type="hidden" name="type" id="type" value="<?php echo $testType; ?>" />
                        <a id="batchSubmit" class="btn btn-primary" href="javascript:void(0);" onclick="validateNow();return false;"><?php echo _translate("Save and Next"); ?></a>
                        <a href="/tb/results/tb-manual-results.php" class="btn btn-default"> <?php echo _translate("Cancel"); ?></a>
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
    let oTable = null;
    $(document).ready(function() {

        $("#testType").select2({
            width: '100%',
            placeholder: "<?php echo _translate("Select Test Type"); ?>"
        });

        $("#facilityName").selectize({
            plugins: ["restore_on_backspace", "remove_button", "clear_button"],
        });


        $("#userId").select2({
            placeholder: "<?= _translate('Select User', true); ?>"
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
                    'This Month': [moment().startOf('month'), moment().endOf('month')],
                    'Last Month': [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                    'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                    'Last 90 Days': [moment().subtract(89, 'days'), moment()],
                    'Last 120 Days': [moment().subtract(119, 'days'), moment()],
                    'Last 180 Days': [moment().subtract(179, 'days'), moment()],
                    'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')],
                    'Previous Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
                    'Current Year To Date': [moment().startOf('year'), moment()]
                }
            },
            function(start, end) {
                startDate = start.format('YYYY-MM-DD');
                endDate = end.format('YYYY-MM-DD');
            });
        $('.daterange').val("");

        getSampleCodeDetails();
    });

    function validateNow() {

        var selVal = [];
        $('#search_to option').each(function(i, selected) {
            selVal[i] = $(selected).val();
        });
        const sqids = new Sqids()
        $("#referralSamples").val(sqids.encode(selVal));

        if (selVal == "") {
            alert("<?= _translate("Please select at least one sample", true); ?>");
            return false;
        }
        $(".sortBy").val($("#sortBy").val());
        $(".sortType").val($("#sortType").val());

        flag = deforayValidator.init({
            formId: 'referralForm'
        });

        if (flag) {
            $("#positions").val($('#positions-type').val());
            $.blockUI();
            document.getElementById('referralForm').submit();
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

        if (oTable) {
            oTable.abort();
        }

        $.blockUI();

        var facilityId = $("#facilityName").val();

        oTable = $.post("/tb/results/get-samples.php", {
                sampleCollectionDate: $("#sampleCollectionDate").val(),
                sampleReceivedAtLab: $("#sampleReceivedAtLab").val(),
                lastModifiedDateTime: $("#lastModifiedDateTime").val(),
                type: '<?= $testType; ?>',
                testType: $('#testType').val(),
                facilityId: facilityId,
                sName: $("#sampleType").val(),
                fundingSource: $("#fundingSource").val(),
                userId: $("#userId").val(),
                sortBy: $("#sortBy").val(),
                sortType: $("#sortType").val(),
            },
            function(data) {
                if (data != "") {
                    $("#sampleDetails").html(data);
                    $("#referralForm").show();
                }
            });
        $.unblockUI();
    }

    function hideAdvanceSearch(hideId, showId) {
        $("#" + hideId).hide();
        $("#" + showId).show();
    }
</script>
<?php require_once APPLICATION_PATH . '/footer.php';
