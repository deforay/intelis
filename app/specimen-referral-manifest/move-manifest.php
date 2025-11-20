<?php

use App\Services\TestsService;
use App\Services\UsersService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

$title = _translate("Move Manifest");

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
/** @var Psr\Http\Message\ServerRequestInterface $request */
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

// Get sample types and short code for the test type
$sampleTypes = TestsService::getSampleTypes($module);
$shortCode = TestsService::getTestShortCode($module);

// Get active test types with their names
$testTypesNames = [];
$activeTests = TestsService::getActiveTests();
foreach ($activeTests as $testType) {
    $testTypesNames[$testType] = TestsService::getTestName($testType);
}


if ($module == 'generic-tests') {
    $testTypeQuery = "SELECT * FROM r_test_types where test_status='active' ORDER BY test_standard_name ASC";
    $testTypeResult = $db->rawQuery($testTypeQuery);
}
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
        <!-- Search Section -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><em class="fa-solid fa-search"></em> Search Manifests</h3>
            </div>
            <!-- /.box-header -->
            <div class="box-body">
                <?php $hide = "";
                if ($module == 'generic-tests') {
                    $hide = "hide " ?>
                    <div class="row">
                        <div class="col-xs-4 col-md-4">
                            <div class="form-group" style="margin-left:30px; margin-top:30px;">
                                <label for="genericTestType">Test Type</label>
                                <select class="form-control" name="genericTestType" id="genericTestType"
                                    title="Please choose test type" style="width:100%;"
                                    onchange="getManifestCodeForm(this.value)">
                                    <option value=""> <?= _translate('-- Select --'); ?> </option>
                                    <?php foreach ($testTypeResult as $testType) { ?>
                                        <option value="<?php echo $testType['test_type_id'] ?>">
                                            <?php echo $testType['test_standard_name'] ?></option>
                                    <?php } ?>
                                </select>
                            </div>
                        </div>
                    </div>
                <?php } ?>
                <div class="<?php echo $hide; ?> form-horizontal">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="daterange" class="col-lg-4 control-label">
                                    <?= _translate('Date Range'); ?>
                                </label>
                                <div class="col-lg-7">
                                    <input type="text" class="form-control" id="daterange" name="daterange"
                                        placeholder="<?php echo _translate('Date Range'); ?>"
                                        title="Choose one sample collection date range">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="testingLab" class="col-lg-4 control-label">
                                    <?= _translate("Manifest From Testing Lab"); ?> <span class="mandatory">*</span>
                                </label>
                                <div class="col-lg-7">
                                    <select class="form-control select2 isRequired" id="testingLab" name="testingLab"
                                        title="Choose one test lab">
                                        <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="userSelectedTestType" class="col-lg-4 control-label">
                                    <?= _translate("Test Type"); ?>
                                </label>
                                <div class="col-lg-7">
                                    <select class="form-control select2" id="userSelectedTestType"
                                        name="userSelectedTestType" title="Choose Test Type">
                                        <?= $general->generateSelectOptions($testTypesNames, $module, '-- Select --'); ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- /.box-body -->
            <div class="box-footer">
                <div class="text-center">
                    <a class="btn btn-primary" href="javascript:void(0);"
                        onclick="getManifestCodeDetails();return false;">
                        <em class="fa-solid fa-search"></em> Search Manifests
                    </a>
                    <a href="move-manifest.php?t=<?= htmlspecialchars((string) $_GET['t']); ?>" class="btn btn-default">
                        <em class="fa-solid fa-refresh"></em> Clear
                    </a>
                </div>
            </div>
        </div>
        <!-- /.box -->

        <!-- Move Manifests Section (Hidden until search) -->
        <form method="post" name="moveSpecimenReferralManifestForm" id="moveSpecimenReferralManifestForm"
            autocomplete="off" action="moveSpecimenManifestCodeHelper.php">
            <div class="box box-success" id="moveManifestsSection" style="display: none;">
                <div class="box-header with-border">
                    <h3 class="box-title"><em class="fa-solid fa-exchange"></em> Move Selected Manifests</h3>
                    <div class="pull-right" style="font-size:14px;">
                        <span class="mandatory">*</span> indicates required field
                    </div>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="alert alert-info">
                                <em class="fa-solid fa-info-circle"></em>
                                Select the manifests you want to move and assign them to a different testing lab.
                            </div>
                        </div>
                    </div>

                    <div class="row" id="manifestsList">

                    </div>

                    <div class="row" style="margin-top: 30px; background: #f9f9f9; padding: 20px; border-radius: 5px;">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="assignLab" class="col-lg-5 control-label">
                                    <?php echo _translate("Assign to Testing Lab"); ?>
                                    <span class="mandatory">*</span>
                                </label>
                                <div class="col-lg-7">
                                    <select class="form-control select2 isRequired" id="assignLab" name="assignLab"
                                        title="<?= _translate("Choose the Testing Lab where these manifests are being moved to"); ?>"
                                        onchange="checkLab(this);" style="width:100%;">
                                        <?= $general->generateSelectOptions($testingLabs, null, '-- Select --'); ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="reasonForChange" class="col-lg-5 control-label">
                                    <?php echo _translate("Reason for Moving Manifest(s)"); ?>
                                    <span class="mandatory">*</span>
                                </label>
                                <div class="col-lg-7">
                                    <textarea class="form-control isRequired" id="reasonForChange"
                                        name="reasonForChange"
                                        placeholder="<?= _translate('Enter the reason for moving the selected manifest(s)'); ?>"
                                        title="<?= _translate("Enter the reason for moving the selected manifest(s)", true); ?> rows="
                                        3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row" id="alertText" style="font-size:18px; margin-top: 20px;"></div>
                    <input type="hidden" class="form-control isRequired" id="testType" name="testType" placeholder=""
                        title="" readonly value="<?= htmlspecialchars((string) $module); ?>" />
                </div>
                <!-- /.box-body -->
                <div class="box-footer">
                    <div class="text-center">
                        <a id="packageSubmit" class="btn btn-success" href="javascript:void(0);"
                            onclick="validateNow();return false;" style="pointer-events:none;" disabled>
                            <em class="fa-solid fa-save"></em> Save Changes
                        </a>
                        <a href="view-manifests.php?t=<?= $_GET['t']; ?>" class="btn btn-default">
                            <em class="fa-solid fa-times"></em> Cancel
                        </a>
                    </div>
                </div>
            </div>
            <!-- /.box -->
        </form>
    </section>
    <!-- /.content -->
</div>
<!-- /.content-wrapper -->
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
            confirm("Please choose different lab to assign the manifest.");
            $(obj).val(null).trigger('change');
            return false;
        }
    }

    $(document).ready(function () {
        $("#userSelectedTestType").select2({
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
            function (start, end) {
                startDate = start.format('YYYY-MM-DD');
                endDate = end.format('YYYY-MM-DD');
            });

        $(".select2").select2();
        $(".select2").select2({
            tags: true
        });

        initializeMultiSelect();

        $('#select-all-packageCode').click(function () {
            $('#packageCode').multiSelect('select_all');
            return false;
        });
        $('#deselect-all-packageCode').click(function () {
            $('#packageCode').multiSelect('deselect_all');
            $("#packageSubmit").attr("disabled", true);
            $("#packageSubmit").css("pointer-events", "none");
            return false;
        });
    });

    function initializeMultiSelect() {
        $('.search').multiSelect({
            selectableHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Manifest Code'>",
            selectionHeader: "<input type='text' class='search-input form-control' autocomplete='off' placeholder='Enter Manifest Code'>",
            afterInit: function (ms) {
                var that = this,
                    $selectableSearch = that.$selectableUl.prev(),
                    $selectionSearch = that.$selectionUl.prev(),
                    selectableSearchString = '#' + that.$container.attr('id') + ' .ms-elem-selectable:not(.ms-selected)',
                    selectionSearchString = '#' + that.$container.attr('id') + ' .ms-elem-selection.ms-selected';

                that.qs1 = $selectableSearch.quicksearch(selectableSearchString)
                    .on('keydown', function (e) {
                        if (e.which === 40) {
                            that.$selectableUl.focus();
                            return false;
                        }
                    });

                that.qs2 = $selectionSearch.quicksearch(selectionSearchString)
                    .on('keydown', function (e) {
                        if (e.which == 40) {
                            that.$selectionUl.focus();
                            return false;
                        }
                    });
            },
            afterSelect: function () {
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
            afterDeselect: function () {
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
    }

    function getManifestCodeDetails() {
        if ($('#testingLab').val() != '') {
            $.blockUI();

            $.post("/specimen-referral-manifest/get-manifest-package-code.php", {
                module: $("#module").val(),
                testingLab: $('#testingLab').val(),
                facility: $('#facilityName').val(),
                daterange: $('#daterange').val(),
                userSelectedTestType: $('#userSelectedTestType').val(),
                genericTestType: $('#genericTestType').val(),
            },
                function (data) {
                    $.unblockUI();
                    $("#testType").val($('#userSelectedTestType').val());
                    if (data != "" && data.trim() != "") {
                        // Populate the manifest code select
                        $('#manifestsList').html(data);

                        // Show the move section
                        $("#moveManifestsSection").slideDown();

                        // Scroll to the section
                        $('html, body').animate({
                            scrollTop: $("#moveManifestsSection").offset().top - 20
                        }, 500);

                        $("#packageSubmit").attr("disabled", true);
                        $("#packageSubmit").css("pointer-events", "none");
                    } else {
                        // Hide the move section if it was previously shown
                        $("#moveManifestsSection").slideUp();
                        alert('No manifests found matching the search criteria.');
                    }
                });
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
