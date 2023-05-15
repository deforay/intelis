<?php

use App\Services\Covid19Service;
use App\Services\EidService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\HepatitisService;
use App\Services\TbService;
use App\Services\UsersService;
use App\Services\VlService;

$title = "Move Manifest";

require_once APPLICATION_PATH . '/header.php';

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);


/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);


$module = isset($_GET['t']) ? base64_decode($_GET['t']) : 'vl';
$testingLabs = $facilitiesService->getTestingLabs($module);

$usersList = [];
$users = $usersService->getActiveUsers($_SESSION['facilityMap']);
foreach ($users as $u) {
    $usersList[$u["user_id"]] = $u['user_name'];
}
$facilities = $facilitiesService->getHealthFacilities($module);
$shortCode = strtoupper($module);
if ($module == 'vl') {
    $vlService = new VlService($db);
    $sampleTypes = $vlService->getVlSampleTypes();
} else if ($module == 'eid') {
    $eidService = new EidService($db);
    $sampleTypes = $eidService->getEidSampleTypes();
} else if ($module == 'covid19') {
    $shortCode = 'C19';
    $covid19Service = new Covid19Service($db);
    $sampleTypes = $covid19Service->getCovid19SampleTypes();
} else if ($module == 'hepatitis') {
    $shortCode = 'HEP';
    $hepDb = new HepatitisService($db);
    $sampleTypes = $hepDb->getHepatitisSampleTypes();
} else if ($module == 'tb') {
    $tbService = new TbService($db);
    $sampleTypes = $tbService->getTbSampleTypes();
}

$testTypes = array(
    "vl" => "Viral Load",
    "eid" => "Early Infant Diagnosis",
    "covid19" => "Covid-19",
    "hepatitis" => "Hepatitis",
    "tb" => "TB"
);
if (!empty(SYSTEM_CONFIG['modules'])) {
    foreach (SYSTEM_CONFIG['modules'] as $type => $status) {
        if ($status) {
            $testTypesNames[$type] = $testTypes[$type];
        }
    }
}
$packageNo = strtoupper($shortCode . date('ymd') . $general->generateRandomString(6));

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
            <li><a href="/specimen-referral-manifest/specimenReferralManifestList.php"> Manage Specimen Referral
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
                <!-- form start -->
                <form class="form-horizontal" method="post" name="moveSpecimenReferralManifestForm" id="moveSpecimenReferralManifestForm" autocomplete="off" action="moveSpecimenManifestCodeHelper.php">
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="daterange" class="col-lg-4 control-label"><?php echo _("Date Range"); ?></label>
                                    <div class="col-lg-7" style="margin-left:3%;">
                                        <input type="text" class="form-control" id="daterange" name="daterange" placeholder="<?php echo _('Date Range'); ?>" title="Choose one sample collection date range">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="facilityName" class="col-lg-4 control-label"><?php echo _("Facility Name"); ?>
                                        :</label>
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
                                    <label for="testingLab" class="col-lg-4 control-label"><?php echo _("Testing Lab"); ?> <span class="mandatory">*</span> :</label>
                                    <div class="col-lg-7" style="margin-left:3%;">
                                        <select class="form-control select2 isRequired" id="testingLab" name="testingLab" title="Choose one test lab">
                                            <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="testType" class="col-lg-4 control-label"><?php echo _("Test Type"); ?></label>
                                    <div class="col-lg-7" style="margin-left:3%;">
                                        <select class="form-control select2" id="testType" name="testType" title="Choose Test Type">
                                            <?= $general->generateSelectOptions($testTypesNames, $module, '-- Select --'); ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12 text-center">
                                <div class="form-group">
                                    <a class="btn btn-primary" href="javascript:void(0);" title="Please select testing lab" onclick="getManifestCodeDetails();return false;">Search </a>
                                    <a href="move-manifest.php?t=<?= htmlspecialchars($_GET['t']); ?>" class="btn btn-default" onclick=""> Clear</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12" style="text-align:center;padding: 20px;background: aliceblue;">
                        <div class="form-group">
                            <label for="assignLab" class="col-lg-4 control-label"><?php echo _("Assign Manifests to Testing Lab"); ?>
                                <span class="mandatory">*</span> :</label>
                            <div class="col-lg-4" style="margin-left:3%;">
                                <select class="form-control select2 isRequired" id="assignLab" name="assignLab" title="Choose one assign lab" onchange="checkLab(this)">
                                    <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                </select>
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
                <a href="specimenReferralManifestList.php?t=<?= htmlspecialchars($_GET['t']); ?>" class="btn btn-default"> Cancel</a>
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

        $('#daterange').daterangepicker({
                locale: {
                    cancelLabel: "<?= _("Clear"); ?>",
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
                    testType: $('#testType').val()
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
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
