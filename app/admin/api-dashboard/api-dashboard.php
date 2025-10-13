<?php
// api-dashboard.php

use App\Services\CommonService;
use App\Services\SystemService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

$title = _translate("API/EMR Dashboard");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$facility = $facilitiesService->getHealthFacilities();
$labNameList = $facilitiesService->getTestingLabs();
$activeModules = SystemService::getActiveModules();
$state = $geolocationService->getProvinces("yes");

?>

<style>
    .box-title {
        font-weight: bold;
    }

    .alert-card {
        border-left: 4px solid #f39c12;
        background-color: #fef9e7;
        margin-bottom: 15px;
        padding: 15px;
    }

    .alert-card.danger {
        border-left-color: #e74c3c;
        background-color: #fdf2f2;
    }

    .alert-card.success {
        border-left-color: #27ae60;
        background-color: #f0f9f0;
    }

    .alert-card.info {
        border-left-color: #3498db;
        background-color: #f0f8ff;
    }

    .alert-card.warning {
        border-left-color: #f39c12;
        background-color: #fef9e7;
    }

    /* Metric box with uniform height */
    .metric-box {
        background: white;
        border: 1px solid #ddd;
        border-radius: 6px;
        padding: 12px;
        margin-bottom: 15px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: box-shadow 0.3s ease;
        height: 120px;
        /* Fixed height for all boxes */
        display: flex;
        flex-direction: column;
    }

    .metric-box:hover {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .metric-header {
        font-size: 13px;
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 8px;
        text-align: center;
        border-bottom: 1px solid #ecf0f1;
        padding-bottom: 5px;
        flex-shrink: 0;
        /* Don't shrink */
    }

    .metric-content {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        /* Align to top instead of center */
        flex: 1;
        /* Take remaining space */
    }

    .metric-main {
        flex: 1;
        display: flex;
        align-items: center;
        /* Center the main number vertically */
    }

    .metric-main-number {
        font-size: 28px;
        font-weight: bold;
        color: #2980b9;
        line-height: 1;
    }

    .metric-breakdown {
        flex: 1;
        text-align: right;
        padding-left: 10px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        /* Center breakdown items vertically */
        min-height: 60px;
        /* Minimum height for breakdown area */
    }

    .metric-breakdown-item {
        display: block;
        font-size: 11px;
        margin-bottom: 2px;
        color: #2c3e50;
        line-height: 1.2;
    }

    .metric-breakdown-number {
        font-weight: 600;
        color: #0d151cff;
    }

    /* Color coding for different metric types */
    .metric-box.requests .metric-main-number {
        color: #3498db;
    }

    .metric-box.api .metric-main-number {
        color: #2ecc71;
    }

    .metric-box.received .metric-main-number {
        color: #9b59b6;
    }

    .metric-box.pending .metric-main-number {
        color: #f39c12;
    }

    .metric-box.duplicates .metric-main-number {
        color: #e74c3c;
    }

    .metric-box.results .metric-main-number {
        color: #1abc9c;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .metric-box {
            height: auto;
            /* Allow auto height on mobile */
            min-height: 120px;
            /* But maintain minimum */
        }

        .metric-content {
            flex-direction: column;
            text-align: center;
            align-items: center;
        }

        .metric-breakdown {
            text-align: center;
            padding-left: 0;
            margin-top: 8px;
        }

        .metric-breakdown-item {
            display: inline-block;
            margin: 0 8px 4px 0;
        }
    }

    .dashboard-section {
        margin-bottom: 30px;
    }

    .section-header {
        background-color: #f8f9fa;
        padding: 10px 15px;
        border-left: 4px solid #007bff;
        margin-bottom: 15px;
        font-weight: bold;
    }
</style>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-tachometer-alt"></em>
            <?php echo _translate("API/EMR Data Exchange Dashboard"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
                    <?php echo _translate("Home"); ?>
                </a></li>
            <li class="active">
                <?php echo _translate("API/EMR Dashboard"); ?>
            </li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <!-- Filters -->
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><?= _translate('Filters'); ?></h3>
                    </div>
                    <table class="table pageFilters" style="margin-left:1%;margin-top:20px;width:98%;">
                        <tr>
                            <td><strong><?= _translate('Test Request Date Range'); ?>&nbsp;:</strong></td>
                            <td>
                                <input type="text" id="dateRange" name="dateRange" class="form-control daterangefield"
                                    placeholder="<?php echo _translate('Enter date range'); ?>" style="width:220px;background:#fff;" />
                            </td>
                            <td><strong><?= _translate('Test Type'); ?>&nbsp;:</strong></td>
                            <td>
                                <select id="testType" name="testType" class="form-control" style="width:150px;">
                                    <?php if (!empty($activeModules) && in_array('vl', $activeModules)) { ?>
                                        <option value="vl"><?= _translate("Viral Load"); ?></option>
                                    <?php }
                                    if (!empty($activeModules) && in_array('eid', $activeModules)) { ?>
                                        <option value="eid"><?= _translate("Early Infant Diagnosis"); ?></option>
                                    <?php }
                                    if (!empty($activeModules) && in_array('covid19', $activeModules)) { ?>
                                        <option value="covid19"><?= _translate("Covid-19"); ?></option>
                                    <?php }
                                    if (!empty($activeModules) && in_array('hepatitis', $activeModules)) { ?>
                                        <option value='hepatitis'><?= _translate("Hepatitis"); ?></option>
                                    <?php }
                                    if (!empty($activeModules) && in_array('tb', $activeModules)) { ?>
                                        <option value='tb'><?= _translate("TB"); ?></option>
                                    <?php }
                                    if (!empty($activeModules) && in_array('cd4', $activeModules)) { ?>
                                        <option value='cd4'><?= _translate("CD4"); ?></option>
                                    <?php } ?>
                                </select>
                            </td>
                            <td><strong><?= _translate('Testing Lab'); ?>&nbsp;:</strong></td>
                            <td>
                                <select class="form-control select2-element" id="labName" name="labName"
                                    title="<?php echo _translate('Please select Testing Lab'); ?>" style="width:180px;" multiple="multiple">
                                    <?= $general->generateSelectOptions($labNameList, null, '--Select--'); ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?= _translate('Province/State'); ?>&nbsp;:</strong></td>
                            <td>
                                <select class="form-control select2-element" id="state" name="state"
                                    onchange="getByProvince()" title="<?php echo _translate('Please select Province/State'); ?>"
                                    style="width:220px;" multiple="multiple">
                                    <?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
                                </select>
                            </td>
                            <td><strong><?= _translate("District/County"); ?>&nbsp;:</strong></td>
                            <td>
                                <select class="form-control select2-element" id="district" name="district"
                                    title="<?php echo _translate('Please select District/County'); ?>"
                                    onchange="getByDistrict(this.value)" style="width:180px;" multiple="multiple">
                                </select>
                            </td>
                            <td><strong><?= _translate("Requesting Facility"); ?>&nbsp;:</strong></td>
                            <td>
                                <select class="form-control select2-element" name="facilityId" id="facilityId"
                                    title="Please choose health facility" style="width:180px;" multiple="multiple">
                                    <?= $general->generateSelectOptions($facility, null, '--Select--'); ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="6">
                                <button onclick="loadDashboard();" class="btn btn-primary btn-sm">
                                    <span><?php echo _translate("Refresh Dashboard"); ?></span>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="resetFilters();">
                                    <span><?php echo _translate("Reset Filters"); ?></span>
                                </button>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Critical Alerts -->
        <div class="row" id="alertsSection">
            <div class="col-xs-12">
                <div class="section-header">
                    <em class="fa-solid fa-exclamation-triangle"></em> <?= _translate('Critical Alerts'); ?>
                </div>
                <div id="alertsContainer">
                    <!-- Alerts will be loaded here via AJAX -->
                </div>
            </div>
        </div>

        <!-- Quick Metrics -->
        <div class="row">
            <div class="col-xs-12">
                <div class="section-header">
                    <em class="fa-solid fa-chart-bar"></em> <?= _translate('Quick Metrics Overview'); ?>
                </div>
            </div>

            <!-- Card 1: Total Requests -->
            <div class="col-lg-2 col-md-4 col-sm-6 col-xs-12">
                <div class="metric-box requests">
                    <div class="metric-header"><?= _translate('Total Requests'); ?></div>
                    <div class="metric-content">
                        <div class="metric-main">
                            <div class="metric-main-number" id="totalRequests">-</div>
                        </div>
                        <div class="metric-breakdown">
                            <span class="metric-breakdown-item">
                                <?= _translate('API/EMR'); ?>: <span class="metric-breakdown-number" id="apiRequestsBreakdown">-</span>
                            </span>
                            <span class="metric-breakdown-item">
                                <?= _translate('Non-API/EMR'); ?>: <span class="metric-breakdown-number" id="nonApiRequestsBreakdown">-</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: API/EMR Requests Status -->
            <div class="col-lg-2 col-md-4 col-sm-6 col-xs-12">
                <div class="metric-box api">
                    <div class="metric-header"><?= _translate('API/EMR Requests'); ?></div>
                    <div class="metric-content">
                        <div class="metric-main">
                            <div class="metric-main-number" id="apiRequests">-</div>
                        </div>
                        <div class="metric-breakdown">
                            <span class="metric-breakdown-item">
                                <?= _translate('Received'); ?>: <span class="metric-breakdown-number" id="apiReceivedBreakdown">-</span>
                            </span>
                            <span class="metric-breakdown-item">
                                <?= _translate('Not Received'); ?>: <span class="metric-breakdown-number" id="apiNotReceivedBreakdown">-</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 3: Received at Lab -->
            <div class="col-lg-2 col-md-4 col-sm-6 col-xs-12">
                <div class="metric-box received">
                    <div class="metric-header"><?= _translate('Received at Lab'); ?></div>
                    <div class="metric-content">
                        <div class="metric-main">
                            <div class="metric-main-number" id="totalReceivedAtLab">-</div>
                        </div>
                        <div class="metric-breakdown">
                            <span class="metric-breakdown-item">
                                <?= _translate('Tested'); ?>: <span class="metric-breakdown-number" id="receivedTestedBreakdown">-</span>
                            </span>
                            <span class="metric-breakdown-item">
                                <?= _translate('Rejected'); ?>: <span class="metric-breakdown-number" id="rejectedSamplesBreakdown">-</span>
                            </span>
                            <span class="metric-breakdown-item">
                                <?= _translate('Pending'); ?>: <span class="metric-breakdown-number" id="receivedPendingBreakdown">-</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 4: Not Received at Lab -->
            <div class="col-lg-2 col-md-4 col-sm-6 col-xs-12">
                <div class="metric-box pending">
                    <div class="metric-header"><?= _translate('Not Received at Lab'); ?></div>
                    <div class="metric-content">
                        <div class="metric-main">
                            <div class="metric-main-number" id="totalNotReceivedAtLab">-</div>
                        </div>
                        <div class="metric-breakdown">
                            <span class="metric-breakdown-item">
                                <?= _translate('≤ 7 days'); ?>: <span class="metric-breakdown-number" id="notReceived7DaysBreakdown">-</span>
                            </span>
                            <span class="metric-breakdown-item">
                                <?= _translate('> 7 days'); ?>: <span class="metric-breakdown-number" id="notReceivedOver7DaysBreakdown">-</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 5: Potential Duplicates -->
            <div class="col-lg-2 col-md-4 col-sm-6 col-xs-12">
                <div class="metric-box duplicates">
                    <div class="metric-header"><?= _translate('Potential Duplicates'); ?></div>
                    <div class="metric-content">
                        <div class="metric-main">
                            <div class="metric-main-number" id="duplicateSuspects">-</div>
                        </div>
                        <div class="metric-breakdown">
                            <span class="metric-breakdown-item" style="font-size: 10px;">
                                <?= _translate('Within 7 days'); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 6: Results Sent -->
            <div class="col-lg-2 col-md-4 col-sm-6 col-xs-12">
                <div class="metric-box results">
                    <div class="metric-header"><?= _translate('Results Sent'); ?></div>
                    <div class="metric-content">
                        <div class="metric-main">
                            <div class="metric-main-number" id="totalResultsSent">-</div>
                        </div>
                        <div class="metric-breakdown">
                            <span class="metric-breakdown-item">
                                <?= _translate('Via API/EMR'); ?>: <span class="metric-breakdown-number" id="resultsApiBreakdown">-</span>
                            </span>
                            <span class="metric-breakdown-item">
                                <?= _translate('Other'); ?>: <span class="metric-breakdown-number" id="resultsOtherBreakdown">-</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>



        <!-- Detailed Reports -->
        <div class="row">
            <!-- Source Analysis with API/EMR Workflow -->
            <div class="col-md-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><?= _translate('Source Distribution by Facility with API/EMR Workflow'); ?></h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-striped table-bordered" id="sourceDistributionTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th rowspan="2"><?= _translate('Facility'); ?></th>
                                    <th colspan="4" class="text-center" style="background-color: #f8f9fa; text-align: center"><?= _translate('Source of Samples'); ?></th>
                                    <th colspan="5" class="text-center" style="background-color: #e3f2fd; text-align: center"><?= _translate('API/EMR Workflow'); ?></th>
                                </tr>
                                <tr>
                                    <th><?= _translate('API/EMR'); ?></th>
                                    <th><?= _translate('STS'); ?></th>
                                    <th><?= _translate('Direct LIS'); ?></th>
                                    <th><?= _translate('API/EMR %'); ?></th>
                                    <th><?= _translate('Registered via API/EMR but Not Received at Testing Lab'); ?></th>
                                    <th><?= _translate('Registered via API/EMR and Received at Testing Lab'); ?></th>
                                    <th><?= _translate('Registered via API/EMR and Tested'); ?></th>
                                    <th><?= _translate('Registered vis API/EMR and Not Yet Tested'); ?></th>
                                    <th><?= _translate('Results Sent back to API/EMR'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data loaded via DataTables AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <!-- API/EMR Requests Without Sample Receipt - Aggregated by Facility -->
            <div class="col-md-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><?= _translate('API/EMR Requests Without Sample Receipt - By Facility'); ?></h3>
                        <div class="box-tools pull-right">
                            <button onclick="viewMissingSamples();" class="btn btn-sm btn-primary">
                                <?= _translate('View All Details'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="box-body">
                        <div class="alert alert-info" style="margin-bottom: 10px; padding: 8px;">
                            <small><em class="fa-solid fa-info-circle"></em>
                                <?= _translate('Facilities with API/EMR requests that have not received samples at the lab and are not rejected.'); ?>
                            </small>
                        </div>
                        <table class="table table-condensed table-striped table-bordered" id="missingSamplesTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th><?= _translate('Facility'); ?></th>
                                    <th class="text-center"><?= _translate('Not Yet Received at Testing Lab'); ?></th>
                                    <th class="text-center"><?= _translate('≤ 7 Days'); ?></th>
                                    <th class="text-center"><?= _translate('> 7 Days'); ?></th>
                                    <th class="text-center"><?= _translate('Avg Days'); ?></th>
                                    <th class="text-center"><?= _translate('Max Days'); ?></th>
                                    <th class="text-center"><?= _translate('Priority'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data loaded via DataTables AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Potential Duplicate Entries - Aggregated by Facility -->
            <div class="col-md-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><?= _translate('Potential Duplicate Entries - By Facility'); ?></h3>
                        <div class="box-tools pull-right">
                            <button onclick="viewDuplicates();" class="btn btn-sm btn-primary">
                                <?= _translate('View Details'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="box-body">
                        <div class="alert alert-info" style="margin-bottom: 10px; padding: 8px;">
                            <small><em class="fa-solid fa-info-circle"></em>
                                <?= _translate('Facilities with samples having same Patient ID/Name within last 7 days (at least one from API/EMR)'); ?>
                            </small>
                        </div>
                        <table class="table table-condensed table-striped table-bordered" id="duplicatesTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th><?= _translate('Facility'); ?></th>
                                    <th class="text-center"><?= _translate('Duplicate Groups'); ?></th>
                                    <th class="text-center"><?= _translate('Total Samples'); ?></th>
                                    <th class="text-center"><?= _translate('High Risk'); ?></th>
                                    <th class="text-center"><?= _translate('Medium Risk'); ?></th>
                                    <th class="text-center"><?= _translate('Low Risk'); ?></th>
                                    <th class="text-center"><?= _translate('Risk Level'); ?></th>
                                    <th class="text-center"><?= _translate('Latest'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data loaded via DataTables AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- <div class="col-md-6">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><?= _translate('Results Sent for Requests Not Originating via API/EMR'); ?></h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-condensed" id="nonOriginatingTable">
                            <thead>
                                <tr>
                                    <th><?= _translate('Sample ID'); ?></th>
                                    <th><?= _translate('Request Source'); ?></th>
                                    <th><?= _translate('Result Sent'); ?></th>
                                    <th><?= _translate('Days Diff'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="nonOriginatingBody">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div> -->

    </section>
</div>
</div>

<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
    $(document).ready(function() {

        $("#lis-body").addClass("sidebar-collapse");
        // Initialize Select2 for multi-select dropdowns
        $('#labName').select2({
            placeholder: "Select Testing Lab"
        });

        $('#state').select2({
            placeholder: "Select Province/State"
        });

        $('#district').select2({
            placeholder: "Select District/County"
        });

        $('#facilityId').select2({
            placeholder: "Select Requesting Facility"
        });

        // Initialize date range picker
        $('#dateRange').daterangepicker({
            locale: {
                cancelLabel: "<?= _translate("Clear", true); ?>",
                format: 'DD-MMM-YYYY',
                separator: ' to ',
            },
            startDate: moment().subtract(30, 'days'),
            endDate: moment(),
            maxDate: moment(),
            ranges: {
                'Today': [moment(), moment()],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'Last 90 Days': [moment().subtract(89, 'days'), moment()],
                'Last 120 Days': [moment().subtract(119, 'days'), moment()],
                'Last 180 Days': [moment().subtract(179, 'days'), moment()],
                'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')],
                'Previous Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
                'Current Year To Date': [moment().startOf('year'), moment()]
            }
        });

        // Load dashboard on page load
        loadDashboard();
    });

    function loadDashboard() {
        $.blockUI();

        const params = {
            dateRange: $("#dateRange").val(),
            testType: $("#testType").val(),
            labName: $("#labName").val(),
            state: $("#state").val(),
            district: $("#district").val(),
            facilityId: $("#facilityId").val()
        };

        // Load all dashboard components
        Promise.all([
            loadQuickMetrics(params),
            loadAlerts(params),
            loadMissingSamples(params),
            loadDuplicates(params),
            loadSourceDistribution(params),
            loadNonOriginatingResults(params)
        ]).finally(() => {
            $.unblockUI();
        });
    }

    function resetFilters() {
        // Reset all filter values
        $("#dateRange").val('').trigger('change');
        $("#testType").val('vl').trigger('change');
        $("#labName").val(null).trigger('change');
        $("#state").val(null).trigger('change');
        $("#district").val(null).trigger('change');
        $("#facilityId").val(null).trigger('change');

        // Reset date range picker to default
        $('#dateRange').data('daterangepicker').setStartDate(moment().subtract(7, 'days'));
        $('#dateRange').data('daterangepicker').setEndDate(moment());

        // Reload dashboard with reset filters
        loadDashboard();
    }

    function getByProvince() {
        const state = $('#state').val();
        $("#district").html('').trigger('change');
        $("#facilityId").html('').trigger('change');

        if (state && state.length > 0) {
            $.post("/common/get-by-province-id.php", {
                provinceId: state,
                districts: true,
                facilities: true
            }, function(data) {
                const obj = $.parseJSON(data);
                $("#district").append(obj['districts']).trigger('change');
                $("#facilityId").append(obj['facilities']).trigger('change');
            });
        }
    }

    function getByDistrict(districtValue) {
        const district = $('#district').val();
        $("#facilityId").html('').trigger('change');

        if (district && district.length > 0) {
            $.post("/common/get-by-district-id.php", {
                districtId: district,
                facilities: true
            }, function(data) {
                const obj = $.parseJSON(data);
                $("#facilityId").append(obj['facilities']).trigger('change');
            });
        }
    }

    function loadQuickMetrics(params) {
        return $.post("/admin/api-dashboard/get-api-dashboard-metrics.php", params)
            .done(function(data) {
                try {
                    const metrics = JSON.parse(data);

                    // Card 1: Total Requests
                    $("#totalRequests").text(metrics.totalRequests || 0);
                    $("#apiRequestsBreakdown").text(metrics.apiRequests || 0);
                    $("#nonApiRequestsBreakdown").text(metrics.nonApiRequests || 0);

                    // Card 2: API/EMR Requests
                    $("#apiRequests").text(metrics.apiRequests || 0);
                    $("#apiReceivedBreakdown").text(metrics.apiReceivedAtLab || 0);
                    $("#apiNotReceivedBreakdown").text(metrics.apiNotReceivedAtLab || 0);

                    // Card 3: Received at Lab
                    $("#totalReceivedAtLab").text(metrics.totalReceivedAtLab || 0);
                    $("#receivedTestedBreakdown").text(metrics.receivedAndTested || 0);
                    $("#rejectedSamplesBreakdown").text(metrics.rejectedSamples || 0);
                    $("#receivedPendingBreakdown").text(metrics.receivedNotTested || 0);

                    // Card 4: Not Received at Lab
                    $("#totalNotReceivedAtLab").text(metrics.totalNotReceivedAtLab || 0);
                    $("#notReceived7DaysBreakdown").text(metrics.notReceivedWithin7Days || 0);
                    $("#notReceivedOver7DaysBreakdown").text(metrics.notReceivedOver7Days || 0);

                    // Card 5: Potential Duplicates
                    $("#duplicateSuspects").text(metrics.duplicateSuspects || 0);

                    // Card 6: Results Sent
                    const totalResultsSent = parseInt(metrics.resultsSentViaApi || 0) + parseInt(metrics.resultsSentOtherMethods || 0);
                    $("#totalResultsSent").text(totalResultsSent);
                    $("#resultsApiBreakdown").text(parseInt(metrics.resultsSentViaApi || 0));
                    $("#resultsOtherBreakdown").text(parseInt(metrics.resultsSentOtherMethods || 0));

                } catch (e) {
                    console.error('Error parsing metrics data:', e);
                    // Reset all metrics to 'Error' on parsing failure
                    const errorElements = [
                        "#totalRequests", "#apiRequestsBreakdown", "#nonApiRequestsBreakdown",
                        "#apiRequests", "#apiReceivedBreakdown", "#apiNotReceivedBreakdown",
                        "#totalReceivedAtLab", "#receivedTestedBreakdown", "#rejectedSamplesBreakdown",
                        "#receivedPendingBreakdown", "#totalNotReceivedAtLab", "#notReceived7DaysBreakdown",
                        "#notReceivedOver7DaysBreakdown", "#duplicateSuspects", "#totalResultsSent",
                        "#resultsApiBreakdown", "#resultsOtherBreakdown"
                    ];
                    errorElements.forEach(el => $(el).text('Error'));
                }
            })
            .fail(function() {
                // Reset all metrics to 'Error' on request failure
                const errorElements = [
                    "#totalRequests", "#apiRequestsBreakdown", "#nonApiRequestsBreakdown",
                    "#apiRequests", "#apiReceivedBreakdown", "#apiNotReceivedBreakdown",
                    "#totalReceivedAtLab", "#receivedTestedBreakdown", "#rejectedSamplesBreakdown",
                    "#receivedPendingBreakdown", "#totalNotReceivedAtLab", "#notReceived7DaysBreakdown",
                    "#notReceivedOver7DaysBreakdown", "#duplicateSuspects", "#totalResultsSent",
                    "#resultsApiBreakdown", "#resultsOtherBreakdown"
                ];
                errorElements.forEach(el => $(el).text('Error'));
            });
    }

    function loadAlerts(params) {
        return $.post("/admin/api-dashboard/get-api-dashboard-alerts.php", params)
            .done(function(data) {
                $("#alertsContainer").html(data);
            });
    }

    function loadMissingSamples(params) {
        // Destroy existing DataTable if it exists
        if ($.fn.DataTable.isDataTable('#missingSamplesTable')) {
            $('#missingSamplesTable').DataTable().destroy();
        }

        // Initialize DataTable with AJAX
        $('#missingSamplesTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "/admin/api-dashboard/get-missing-samples.php",
                type: "POST",
                data: params,
                dataSrc: function(json) {
                    if (json.error) {
                        console.error('Error loading missing samples:', json.error);
                        return [];
                    }
                    return json;
                }
            },
            columns: [
                // Facility Name
                {
                    data: 'facility_name',
                    render: function(data, type, row) {
                        return '<strong>' + data + '</strong><br><small class="text-muted">Oldest: ' + row.oldest_request + '</small>';
                    }
                },
                // Missing Count
                {
                    data: 'missing_count',
                    className: 'text-center'
                },
                // Within 7 Days
                {
                    data: 'within_7_days',
                    className: 'text-center'
                },
                // Over 7 Days
                {
                    data: 'over_7_days',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (data > 0) {
                            return '<span class="text-danger"><strong>' + data + '</strong></span>';
                        }
                        return data;
                    }
                },
                // Average Days
                {
                    data: 'avg_days_pending',
                    className: 'text-center'
                },
                // Max Days
                {
                    data: 'max_days_pending',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (data > 7) {
                            return '<span class="text-danger"><strong>' + data + '</strong></span>';
                        }
                        return data;
                    }
                },
                // Priority
                {
                    data: 'priority',
                    className: 'text-center',
                    render: function(data, type, row) {
                        let priorityClass = 'text-default';
                        if (data === 'Critical') priorityClass = 'text-danger';
                        else if (data === 'High') priorityClass = 'text-warning';
                        else if (data === 'Medium') priorityClass = 'text-info';
                        else priorityClass = 'text-success';

                        return '<strong class="' + priorityClass + '">' + data + '</strong>';
                    }
                }
            ],
            order: [
                [1, 'desc'],
                [5, 'desc']
            ], // Sort by missing count DESC, then max days DESC
            pageLength: 25,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            responsive: true,
            language: {
                search: "<?= _translate('Search facilities'); ?>:",
                lengthMenu: "<?= _translate('Show'); ?> _MENU_ <?= _translate('facilities'); ?>",
                info: "<?= _translate('Showing'); ?> _START_ <?= _translate('to'); ?> _END_ <?= _translate('of'); ?> _TOTAL_ <?= _translate('facilities'); ?>",
                emptyTable: "<?= _translate('No facilities with missing samples found'); ?>",
                zeroRecords: "<?= _translate('No matching facilities found'); ?>",
                paginate: {
                    first: "<?= _translate('First'); ?>",
                    last: "<?= _translate('Last'); ?>",
                    next: "<?= _translate('Next'); ?>",
                    previous: "<?= _translate('Previous'); ?>"
                }
            },
            dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-5"i><"col-sm-7"p>>',
            drawCallback: function(settings) {
                // Re-enable tooltips after table redraw
                $('[data-toggle="tooltip"]').tooltip();
            }
        });
    }

    function loadDuplicates(params) {
        // Destroy existing DataTable if it exists
        if ($.fn.DataTable.isDataTable('#duplicatesTable')) {
            $('#duplicatesTable').DataTable().destroy();
        }

        // Initialize DataTable with AJAX
        $('#duplicatesTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "/admin/api-dashboard/get-duplicate-suspects.php",
                type: "POST",
                data: params,
                dataSrc: function(json) {
                    if (json.error) {
                        console.error('Error loading duplicates:', json.error);
                        return [];
                    }
                    return json;
                }
            },
            columns: [
                // Facility Name
                {
                    data: 'facility_name',
                    render: function(data, type, row) {
                        return '<strong>' + data + '</strong><br><small class="text-muted">Latest: ' + row.latest_duplicate + '</small>';
                    }
                },
                // Duplicate Groups Count
                {
                    data: 'duplicate_groups_count',
                    className: 'text-center'
                },
                // Total Duplicate Samples
                {
                    data: 'total_duplicate_samples',
                    className: 'text-center'
                },
                // High Risk Groups (≤1 day)
                {
                    data: 'high_risk_groups',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (data > 0) {
                            return '<span class="text-danger"><strong>' + data + '</strong></span>';
                        }
                        return data;
                    }
                },
                // Medium Risk Groups (2-3 days)
                {
                    data: 'medium_risk_groups',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (data > 0) {
                            return '<span class="text-warning"><strong>' + data + '</strong></span>';
                        }
                        return data;
                    }
                },
                // Low Risk Groups (4-7 days)
                {
                    data: 'low_risk_groups',
                    className: 'text-center'
                },
                // Overall Risk Level
                {
                    data: 'risk_level',
                    className: 'text-center',
                    render: function(data, type, row) {
                        let riskClass = 'label-default';
                        if (data === 'High') riskClass = 'label-danger';
                        else if (data === 'Medium') riskClass = 'label-warning';
                        else riskClass = 'label-success';

                        return '<span class="label ' + riskClass + '">' + data + '</span>';
                    }
                },
                // Latest Duplicate
                {
                    data: 'latest_duplicate',
                    className: 'text-center',
                    render: function(data, type, row) {
                        return '<small>' + data + '</small>';
                    }
                }
            ],
            order: [
                [1, 'desc'],
                [3, 'desc']
            ], // Sort by duplicate groups DESC, then high risk DESC
            pageLength: 25,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            responsive: true,
            language: {
                search: "<?= _translate('Search facilities'); ?>:",
                lengthMenu: "<?= _translate('Show'); ?> _MENU_ <?= _translate('facilities'); ?>",
                info: "<?= _translate('Showing'); ?> _START_ <?= _translate('to'); ?> _END_ <?= _translate('of'); ?> _TOTAL_ <?= _translate('facilities'); ?>",
                emptyTable: "<?= _translate('No facilities with duplicate suspects found'); ?>",
                zeroRecords: "<?= _translate('No matching facilities found'); ?>",
                paginate: {
                    first: "<?= _translate('First'); ?>",
                    last: "<?= _translate('Last'); ?>",
                    next: "<?= _translate('Next'); ?>",
                    previous: "<?= _translate('Previous'); ?>"
                }
            },
            dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-5"i><"col-sm-7"p>>',
            drawCallback: function(settings) {
                // Re-enable tooltips after table redraw
                $('[data-toggle="tooltip"]').tooltip();
            }
        });
    }

    function loadSourceDistribution(params) {
        // Destroy existing DataTable if it exists
        if ($.fn.DataTable.isDataTable('#sourceDistributionTable')) {
            $('#sourceDistributionTable').DataTable().destroy();
        }

        // Initialize DataTable with AJAX
        $('#sourceDistributionTable').DataTable({
            processing: true,
            serverSide: false,
            ajax: {
                url: "/admin/api-dashboard/get-source-distribution.php",
                type: "POST",
                data: params,
                dataSrc: function(json) {
                    if (json.error) {
                        console.error('Error loading source distribution:', json.error);
                        return [];
                    }
                    return json;
                }
            },
            columns: [
                // Facility Name
                {
                    data: 'facility_name',
                    render: function(data, type, row) {
                        return data || 'Unknown Facility #' + row.facility_id;
                    }
                },
                // API/EMR Count
                {
                    data: 'api_count',
                    className: 'text-center'
                },
                // STS Count
                {
                    data: 'sts_count',
                    className: 'text-center'
                },
                // LIS Count
                {
                    data: 'lis_count',
                    className: 'text-center'
                },
                // API/EMR Percentage
                {
                    data: 'api_percentage',
                    className: 'text-center',
                    render: function(data, type, row) {
                        return data + '%';
                    }
                },
                // API/EMR Not Received
                {
                    data: 'api_not_received',
                    className: 'text-center'
                },
                // API/EMR Received
                {
                    data: 'api_received',
                    className: 'text-center'
                },
                // API/EMR Tested
                {
                    data: 'api_tested',
                    className: 'text-center'
                },
                // API/EMR Not Tested (Pending)
                {
                    data: 'api_not_tested',
                    className: 'text-center'
                },
                // API/EMR Results Sent
                {
                    data: 'api_results_sent',
                    className: 'text-center'
                }
            ],
            order: [
                [1, 'desc']
            ], // Sort by API/EMR count descending
            pageLength: 25,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            responsive: true,
            scrollX: true, // Enable horizontal scrolling for many columns
            language: {
                search: "<?= _translate('Search facilities'); ?>:",
                lengthMenu: "<?= _translate('Show'); ?> _MENU_ <?= _translate('entries', true); ?>",
                info: "<?= _translate('Showing'); ?> _START_ <?= _translate('to', true); ?> _END_ <?= _translate('of', true); ?> _TOTAL_ <?= _translate('facilities', true); ?>",
                emptyTable: "<?= _translate('No data available', true); ?>",
                zeroRecords: "<?= _translate('No matching facilities found', true); ?>",
                paginate: {
                    first: "<?= _translate('First', true); ?>",
                    last: "<?= _translate('Last', true); ?>",
                    next: "<?= _translate('Next', true); ?>",
                    previous: "<?= _translate('Previous', true); ?>"
                }
            },
            dom: '<"row"<"col-sm-6"l><"col-sm-6"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-5"i><"col-sm-7"p>>',
            drawCallback: function(settings) {
                // Re-enable tooltips after table redraw
                $('[data-toggle="tooltip"]').tooltip();
            }
        });
    }

    function loadNonOriginatingResults(params) {
        return $.post("/admin/api-dashboard/get-non-originating-results.php", params)
            .done(function(data) {
                const results = JSON.parse(data);
                let html = '';
                results.slice(0, 10).forEach(result => {
                    html += `<tr>
                    <td>${result.sample_code}</td>
                    <td><span class="label label-info">${result.request_source}</span></td>
                    <td>${result.result_sent_date}</td>
                    <td><span class="label label-warning">${result.days_diff}</span></td>
                </tr>`;
                });
                if (results.length === 0) {
                    html = '<tr><td colspan="4" class="text-center"><?= _translate("No non-originating results found", true); ?></td></tr>';
                }
                $("#nonOriginatingBody").html(html);
            });
    }

    function viewMissingSamples() {
        const params = new URLSearchParams({
            testType: $("#testType").val(),
            dateRange: $("#dateRange").val(),
            labName: $("#labName").val(),
            state: $("#state").val(),
            district: $("#district").val(),
            facilityId: $("#facilityId").val()
        });
        window.open('/admin/api-dashboard/missing-samples-detail.php?' + params.toString(), '_blank');
    }

    function viewDuplicates() {
        const params = new URLSearchParams({
            testType: $("#testType").val(),
            dateRange: $("#dateRange").val(),
            labName: $("#labName").val(),
            state: $("#state").val(),
            district: $("#district").val(),
            facilityId: $("#facilityId").val()
        });
        window.open('/admin/api-dashboard/duplicates-detail.php?' + params.toString(), '_blank');
    }

    function viewPendingTests() {
        const params = new URLSearchParams({
            testType: $("#testType").val(),
            dateRange: $("#dateRange").val(),
            labName: $("#labName").val(),
            state: $("#state").val(),
            district: $("#district").val(),
            facilityId: $("#facilityId").val()
        });
        window.open('/admin/api-dashboard/pending-tests-detail.php?' + params.toString(), '_blank');
    }
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
