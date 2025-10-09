<?php

use App\Services\TbService;
use App\Services\VlService;
use App\Services\CD4Service;
use App\Services\EidService;
use App\Services\UsersService;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\Covid19Service;
use App\Services\DatabaseService;
use App\Services\HepatitisService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

$title = "Move Manifest";

require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);


/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());
$module = $_GET['t'];

$testingLabs = $facilitiesService->getTestingLabs($module);

$usersList = [];
$users = $usersService->getActiveUsers($_SESSION['facilityMap']);
foreach ($users as $u) {
    $usersList[$u["user_id"]] = $u['user_name'];
}
$facilities = $facilitiesService->getHealthFacilities($module);
$shortCode = strtoupper((string) $module);
if ($module == 'vl') {
    /** @var VlService $vlService */
    $vlService = ContainerRegistry::get(VlService::class);
    $sampleTypes = $vlService->getVlSampleTypes();
} elseif ($module == 'eid') {
    /** @var EidService $eidService */
    $eidService = ContainerRegistry::get(EidService::class);
    $sampleTypes = $eidService->getEidSampleTypes();
} else if ($module == 'covid19') {
    $shortCode = 'C19';
    /** @var Covid19Service $covid19Service */
    $covid19Service = ContainerRegistry::get(Covid19Service::class);
    $sampleTypes = $covid19Service->getCovid19SampleTypes();
} else if ($module == 'hepatitis') {
    $shortCode = 'HEP';
    /** @var HepatitisService $hepDb */
    $hepDb = ContainerRegistry::get(HepatitisService::class);
    $sampleTypes = $hepDb->getHepatitisSampleTypes();
} else if ($module == 'tb') {
    /** @var TbService $tbService */
    $tbService = ContainerRegistry::get(TbService::class);
    $sampleTypes = $tbService->getTbSampleTypes();
} else if ($module == 'cd4') {
    /** @var CD4Service $cd4Service */
    $cd4Service = ContainerRegistry::get(CD4Service::class);
    $sampleTypes = $cd4Service->getCd4SampleTypes();
} else if ($module == 'generic-tests') {
    /** @var GenericTestsService $genericService */
    $genericService = ContainerRegistry::get(GenericTestsService::class);
    $sampleTypes = $genericService->getGenericSampleTypes();
}

$testTypes = array(
    "vl" => "Viral Load",
    "eid" => "Early Infant Diagnosis",
    "covid19" => "Covid-19",
    "hepatitis" => "Hepatitis",
    "tb" => "TB",
    "cd4" => "CD4",
    "generic-tests" => "Generic Tests"
);

if (!empty(SYSTEM_CONFIG['modules'])) {
    foreach (SYSTEM_CONFIG['modules'] as $type => $status) {
        if ($status) {
            $testTypesNames[$type] = $testTypes[$type];
        }
    }
}
$packageNo = strtoupper($shortCode . date('ymd') . MiscUtility::generateRandomString(6));
$testTypeQuery = "SELECT * FROM r_test_types where test_status='active' ORDER BY test_standard_name ASC";
$testTypeResult = $db->rawQuery($testTypeQuery);
?>
<link href="/assets/css/multi-select.css" rel="stylesheet" />
<style>
    .select2-selection__choice {
        color: #000000 !important;
    }

    #ms-packageCode {
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
        <h1><em class="fa-solid fa-angles-right"></em> Move Manifests</h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> Home</a></li>
            <li><a href="/specimen-referral-manifest/view-manifests.php"> Manage Specimen Referral
                    Manifest</a></li>
            <li class="active">Move Manifests</li>
        </ol>
    </section>
    <!-- Main content -->
    <section class="content">
        <div class="box box-default">
            <div class="box-header with-border">
                <div class="pull-right" style="font-size:15px;"><span class="mandatory">*</span> indicates required
                    field &nbsp;
                </div>
            </div>
            <br><br><br><br>
            <!-- /.box-header -->
            <div class="box-body">
                <?php $hide = "";
                if ($module == 'generic-tests') {
                    $hide = "hide " ?>
                    <div class="row">
                        <div class="col-xs-4 col-md-4">
                            <div class="form-group" style="margin-left:30px; margin-top:30px;">
                                <label for="genericTestType">Test Type</label>
                                <select class="form-control" name="genericTestType" id="genericTestType" title="Please choose test type" style="width:100%;" onchange="getManifestCodeForm(this.value)">
                                    <option value=""> -- Select -- </option>
                                    <?php foreach ($testTypeResult as $testType) { ?>
                                        <option value="<?php echo $testType['test_type_id'] ?>"><?php echo $testType['test_standard_name'] ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                <?php } ?>
                <!-- form start -->
                <form class="<?php echo $hide; ?> form-horizontal" method="post" name="moveSpecimenReferralManifestForm" id="moveSpecimenReferralManifestForm" autocomplete="off" action="moveSpecimenManifestCodeHelper.php">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="daterange" class="col-lg-4 control-label">
                                        <?= _translate('Date Range'); ?>
                                    </label>
                                    <div class="col-lg-7" style="margin-left:3%;">
                                        <input type="text" class="form-control" id="daterange" name="daterange" placeholder="<?php echo _translate('Date Range'); ?>" title="Choose one sample collection date range">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="facilityName" class="col-lg-4 control-label">
                                        <?php echo _translate("Facility Name"); ?>
                                        :
                                    </label>
                                    <div class="col-lg-7" style="margin-left:3%;">
                                        <select class="form-control select2" id="facilityName" name="facilityName" title="Choose one facility name">
                                            <?= $general->generateSelectOptions($facilities, null, '-- Select --'); ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="testingLab" class="col-lg-4 control-label">
                                        <?php echo _translate("Testing Lab"); ?> <span class="mandatory">*</span> :
                                    </label>
                                    <div class="col-lg-7" style="margin-left:3%;">
                                        <select class="form-control select2 isRequired" id="testingLab" name="testingLab" title="Choose one test lab">
                                            <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="testType" class="col-lg-4 control-label">
                                        <?php echo _translate("Test Type"); ?>
                                    </label>
                                    <div class="col-lg-7" style="margin-left:3%;">
                                        <select class="form-control select2" id="testType" name="testType" title="Choose Test Type">
                                            <?= $general->generateSelectOptions($testTypesNames, $module, '-- Select --'); ?>
                                        </select>
                                        <input type="hidden" class="form-control isRequired" id="module" name="module" placeholder="" title="" readonly value="<?= htmlspecialchars((string) $module); ?>" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12 text-center">
                                <div class="form-group">
                                    <a class="btn btn-primary" href="javascript:void(0);" title="Please select testing lab" onclick="getManifestCodeDetails();return false;">Search </a>
                                    <a href="move-manifest.php?t=<?= htmlspecialchars((string) $_GET['t']); ?>" class="btn btn-default" onclick=""> Clear</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12" style="text-align:center;padding: 20px;background: aliceblue;">
                        <div class="form-group">
                            <label for="assignLab" class="col-lg-4 control-label">
                                <?php echo _translate("Assign Manifests to Testing Lab"); ?>
                                <span class="mandatory">*</span> :
                            </label>
                            <div class="col-lg-4" style="margin-left:3%;">
                                <select class="form-control select2 isRequired" id="assignLab" name="assignLab" title="Choose one assign lab" onchange="checkLab(this)">
                                    <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="reasonForChange" class="col-lg-4 control-label">
                                <?php echo _translate("Reason for changing manifest"); ?><span class="mandatory">*</span>
                            </label>
                            <div class="col-lg-4" style="margin-left:3%;">
                                <textarea class="form-control isRequired" id="reasonForChange" name="reasonForChange" placeholder="<?php echo _translate('Reason for changing this manifest'); ?>" title="Enter the Reason for changing this manifest"></textarea>
                            </div>
                        </div>
                    </div>

                    <br>

                    <div class="row" id="sampleDetails">
                        <div class="col-md-9 col-md-offset-1">
                            <div class="form-group">
                                <div class="col-md-12">
                                    <div class="col-md-12">
                                        <div style="width:60%;margin:0 auto;clear:both;">
                                            <a href='#' id='select-all-packageCode' style="float:left" class="btn btn-info btn-xs">Select All&nbsp;&nbsp;<em class="fa-solid fa-chevron-right"></em></a> <a href='#' id='deselect-all-manifestCode' style="float:right" class="btn btn-danger btn-xs"><em class="fa-solid fa-chevron-left"></em>&nbsp;Deselect All</a>
                                        </div>
                                        <br /><br />
                                        <select id='packageCode' name="packageCode[]" multiple='multiple' class="search"></select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row" id="alertText" style="font-size:18px;"></div>
            </div>
            <!-- /.box-body -->
            <div class="box-footer">
                <a id="packageSubmit" class="btn btn-primary" href="javascript:void(0);" title="Please select machine" onclick="validateNow();return false;" style="pointer-events:none;" disabled>Save </a>
                <a href="view-manifests.php?t=<?= ($_GET['t']); ?>" class="btn btn-default"> Cancel</a>
            </div>
            <!-- /.box-footer -->
            </form>
            <!-- /.row -->
        </div>
</div>
<!-- /.box -->
<!-- /.content -->
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script src="/assets/js/jquery.multi-select.js"></script>
<script src="/assets/js/jquery.quicksearch.js"></script>
<script type="text/javascript">
    noOfSamples = 100;
    sortedTitle = [];

    function validateNow() {
        flag = deforayValidator.init({
            formId: 'moveSpecimenReferralManifestForm'
        });
        if (flag) {
            $.blockUI();
            document.getElementById('moveSpecimenReferralManifestForm').submit();
        }
    }

    function checkLab(obj) {
        let _assign = $(obj).val();
        let _lab = $('#testingLab').val();
        if (_lab == _assign) {
            confirm("Please choose different lab to assign the package details.");
            $(obj).val(null).trigger('change');
            return false;
        }
    }

    $(document).ready(function() {
        $("#testType").select2({
            width: '100%',
            placeholder: "<?php echo _translate("Select Test Type"); ?>"
        });
        $('#daterange').daterangepicker({
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

        $(".select2").select2();
        $(".select2").select2({
            tags: true
        });

        $('.search').multiSelect({
            selectableHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Manifest Code'>",
            selectionHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Manifest Code'>",
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

        $('#select-all-packageCode').click(function() {
            $('#packageCode').multiSelect('select_all');
            return false;
        });
        $('#deselect-all-packageCode').click(function() {
            $('#packageCode').multiSelect('deselect_all');
            $("#packageSubmit").attr("disabled", true);
            $("#packageSubmit").css("pointer-events", "none");
            return false;
        });
    });

    function getManifestCodeDetails() {
        if ($('#testingLab').val() != '') {
            $.blockUI();

            $.post("/specimen-referral-manifest/get-manifest-package-code.php", {
                    module: $("#module").val(),
                    testingLab: $('#testingLab').val(),
                    facility: $('#facility').val(),
                    daterange: $('#daterange').val(),
                    assignLab: $('#assignLab').val(),
                    testType: $('#testType').val(),
                    genericTestType: $('#genericTestType').val(),
                },
                function(data) {
                    if (data != "") {
                        $("#sampleDetails").html(data);
                        $("#packageSubmit").attr("disabled", true);
                        $("#packageSubmit").css("pointer-events", "none");
                    }
                });
            $.unblockUI();
        } else {
            alert('Please select the testing lab');
        }
    }

    function getManifestCodeForm(value) {
        if (value != "") {
            $("#moveSpecimenReferralManifestForm").removeClass("hide");
        }

    }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
