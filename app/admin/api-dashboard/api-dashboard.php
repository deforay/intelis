<?php
// api-dashboard.php

use App\Services\CommonService;
use App\Services\SystemService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

$title = _translate("API Dashboard");
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

    .metric-box {
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 15px;
        text-align: center;
    }

    .metric-number {
        font-size: 24px;
        font-weight: bold;
        color: #2c3e50;
    }

    .metric-label {
        font-size: 12px;
        color: #7f8c8d;
        margin-top: 5px;
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
            <?php echo _translate("API Dashboard"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
                    <?php echo _translate("Home"); ?>
                </a></li>
            <li class="active">
                <?php echo _translate("API Dashboard"); ?>
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
                    <table class="table" style="margin-left:1%;margin-top:20px;width:98%;">
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
            <div class="col-md-2 col-sm-4 col-xs-6">
                <div class="metric-box">
                    <div class="metric-number" id="totalRequests">-</div>
                    <div class="metric-label"><?= _translate('Total Requests'); ?></div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-xs-6">
                <div class="metric-box">
                    <div class="metric-number" id="apiRequests">-</div>
                    <div class="metric-label"><?= _translate('API Requests'); ?></div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-xs-6">
                <div class="metric-box">
                    <div class="metric-number" id="missingReceipts">-</div>
                    <div class="metric-label"><?= _translate('Samples Not Received'); ?></div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-xs-6">
                <div class="metric-box">
                    <div class="metric-number" id="duplicateSuspects">-</div>
                    <div class="metric-label"><?= _translate('Potential Duplicates'); ?></div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-xs-6">
                <div class="metric-box">
                    <div class="metric-number" id="pendingTests">-</div>
                    <div class="metric-label"><?= _translate('Pending Tests'); ?></div>
                </div>
            </div>
            <div class="col-md-2 col-sm-4 col-xs-6">
                <div class="metric-box">
                    <div class="metric-number" id="resultsSent">-</div>
                    <div class="metric-label"><?= _translate('Results Sent'); ?></div>
                </div>
            </div>
        </div>

        <!-- Detailed Reports -->
        <div class="row">
            <div class="col-md-6">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><?= _translate('API Requests Without Sample Receipt'); ?></h3>
                        <div class="box-tools pull-right">
                            <button onclick="viewMissingSamples();" class="btn btn-sm btn-primary">
                                <?= _translate('View All Details'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="box-body">
                        <div class="alert alert-info" style="margin-bottom: 10px; padding: 8px;">
                            <small><em class="fa-solid fa-info-circle"></em>
                                <?= _translate('Showing only API requests that have not received samples at the lab and are not rejected.'); ?>
                            </small>
                        </div>
                        <table class="table table-condensed" id="missingSamplesTable">
                            <thead>
                                <tr>
                                    <th><?= _translate('Sample ID'); ?></th>
                                    <th><?= _translate('Facility'); ?></th>
                                    <th><?= _translate('Days Pending'); ?></th>
                                    <th><?= _translate('Source'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="missingSamplesBody">
                                <!-- Data loaded via AJAX -->
                            </tbody>
                            <tfoot id="missingSamplesFooter" style="display: none;">
                                <tr>
                                    <td colspan="4" class="text-center">
                                        <small class="text-muted">
                                            <?= _translate('Showing first 10 records.'); ?>
                                            <a href="javascript:void(0);" onclick="viewMissingSamples();" class="text-primary">
                                                <?= _translate('View all records'); ?>
                                            </a>
                                        </small>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><?= _translate('Potential Duplicate Entries'); ?></h3>
                        <div class="box-tools pull-right">
                            <button onclick="viewDuplicates();" class="btn btn-sm btn-primary">
                                <?= _translate('View Details'); ?>
                            </button>
                        </div>
                    </div>
                    <div class="box-body">
                        <div class="alert alert-info" style="margin-bottom: 10px; padding: 8px;">
                            <small><em class="fa-solid fa-info-circle"></em>
                                <?= _translate('Samples having same Patient ID/Name at same Lab or Facility within last 7 days are potentially duplicates'); ?>
                            </small>
                        </div>
                        <table class="table table-condensed" id="duplicatesTable">
                            <thead>
                                <tr>
                                    <th><?= _translate('Patient Info'); ?></th>
                                    <th><?= _translate('Sample IDs'); ?></th>
                                    <th><?= _translate('Collection Dates'); ?></th>
                                    <th><?= _translate('Sources'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="duplicatesBody">
                                <!-- Data loaded via AJAX -->
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
                        <h3 class="box-title"><?= _translate('Results Sent for Requests Not Originating via API'); ?></h3>
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
            <!-- Source Analysis -->
            <div class="col-md-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><?= _translate('Source Distribution by Facility'); ?></h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-striped table-bordered" id="sourceDistributionTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th><?= _translate('Facility'); ?></th>
                                    <th><?= _translate('API'); ?></th>
                                    <th><?= _translate('STS'); ?></th>
                                    <th><?= _translate('Direct LIS'); ?></th>
                                    <th><?= _translate('Total'); ?></th>
                                    <th><?= _translate('API %'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data loaded via DataTables AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
    </section>
</div>
</div>

<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
    $(document).ready(function() {
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
                    $("#totalRequests").text(metrics.totalRequests || 0);
                    $("#apiRequests").text(metrics.apiRequests || 0);
                    $("#missingReceipts").text(metrics.missingReceipts || 0);
                    $("#duplicateSuspects").text(metrics.duplicateSuspects || 0);
                    $("#pendingTests").text(metrics.pendingTests || 0);
                    $("#resultsSent").text(metrics.resultsSent || 0);
                } catch (e) {
                    console.error('Error parsing metrics data:', e);
                    $("#totalRequests,#apiRequests,#missingReceipts,#duplicateSuspects,#pendingTests,#resultsSent").text('Error');
                }
            })
            .fail(function() {
                $("#totalRequests,#apiRequests,#missingReceipts,#duplicateSuspects,#pendingTests,#resultsSent").text('Error');
            });
    }

    function loadAlerts(params) {
        return $.post("/admin/api-dashboard/get-api-dashboard-alerts.php", params)
            .done(function(data) {
                $("#alertsContainer").html(data);
            });
    }

    function loadMissingSamples(params) {
        return $.post("/admin/api-dashboard/get-missing-samples.php", params)
            .done(function(data) {
                try {
                    const samples = JSON.parse(data);
                    let html = '';

                    if (Array.isArray(samples) && samples.length > 0) {
                        samples.slice(0, 10).forEach(sample => {
                            html += `<tr>
                            <td>${sample.sample_code}</td>
                            <td>${sample.facility_name}</td>
                            <td><span class="label label-warning">${sample.days_pending}</span></td>
                            <td>${sample.source_of_request}</td>
                        </tr>`;
                        });

                        // Get the actual count from the metrics box instead of samples.length
                        const totalCount = parseInt($("#missingReceipts").text()) || 0;
                        if (totalCount > 10) {
                            const remaining = totalCount - 10;
                            html += `<tr><td colspan="4" class="text-center" style="background-color: #f9f9f9; border-top: 2px solid #ddd;">
                            <small class="text-muted">... ${remaining} more records. <a href="javascript:void(0);" onclick="viewMissingSamples();">View all →</a></small>
                        </td></tr>`;
                        }
                    } else {
                        html = '<tr><td colspan="4" class="text-center"><?= _translate("No missing samples found"); ?></td></tr>';
                    }
                    $("#missingSamplesBody").html(html);
                } catch (e) {
                    console.error('Error parsing missing samples data:', e);
                    $("#missingSamplesBody").html('<tr><td colspan="4" class="text-center text-danger">Error loading missing samples data</td></tr>');
                }
            })
            .fail(function() {
                $("#missingSamplesBody").html('<tr><td colspan="4" class="text-center text-danger">Failed to load missing samples data</td></tr>');
            });
    }

    function loadDuplicates(params) {
        return $.post("/admin/api-dashboard/get-duplicate-suspects.php", params)
            .done(function(data) {
                try {
                    const duplicates = JSON.parse(data);
                    let html = '';

                    // Check if duplicates is an array and has data
                    if (Array.isArray(duplicates) && duplicates.length > 0) {
                        duplicates.slice(0, 10).forEach(dup => {
                            html += `<tr>
                            <td>${dup.patient_info}</td>
                            <td>${dup.sample_ids}</td>
                            <td>${dup.collection_dates}</td>
                            <td>${dup.sources}</td>
                        </tr>`;
                        });

                        // Get the actual count from the metrics box
                        const totalCount = parseInt($("#duplicateSuspects").text()) || 0;
                        if (totalCount > 10) {
                            const remaining = totalCount - 10;
                            html += `<tr><td colspan="4" class="text-center" style="background-color: #f9f9f9; border-top: 2px solid #ddd;">
                            <small class="text-muted">... ${remaining} more records. <a href="javascript:void(0);" onclick="viewDuplicates();">View all →</a></small>
                        </td></tr>`;
                        }
                    } else {
                        html = '<tr><td colspan="4" class="text-center"><?= _translate("No potential duplicates found"); ?></td></tr>';
                    }
                    $("#duplicatesBody").html(html);
                } catch (e) {
                    console.error('Error parsing duplicates data:', e);
                    $("#duplicatesBody").html('<tr><td colspan="4" class="text-center text-danger">Error loading duplicates data</td></tr>');
                }
            })
            .fail(function() {
                $("#duplicatesBody").html('<tr><td colspan="4" class="text-center text-danger">Failed to load duplicates data</td></tr>');
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
            columns: [{
                    data: 'facility_name',
                    render: function(data, type, row) {
                        return data || 'Unknown Facility #' + row.facility_id;
                    }
                },
                {
                    data: 'api_count',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (data > 0) {
                            return '<span class="label label-info">' + data + '</span>';
                        } else {
                            return '<span class="text-muted">0</span>';
                        }
                    }
                },
                {
                    data: 'sts_count',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (data > 0) {
                            return '<span class="label label-success">' + data + '</span>';
                        } else {
                            return '<span class="text-muted">0</span>';
                        }
                    }
                },
                {
                    data: 'lis_count',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (data > 0) {
                            return '<span class="label label-primary">' + data + '</span>';
                        } else {
                            return '<span class="text-muted">0</span>';
                        }
                    }
                },

                {
                    data: 'total_count',
                    className: 'text-center',
                    render: function(data, type, row) {
                        return '<strong>' + data + '</strong>';
                    }
                },
                {
                    data: 'api_percentage',
                    className: 'text-center',
                    render: function(data, type, row) {
                        let badgeClass = 'label-default';
                        if (data >= 80) badgeClass = 'label-success';
                        else if (data >= 50) badgeClass = 'label-info';
                        else if (data >= 10) badgeClass = 'label-warning';
                        else badgeClass = 'label-danger';

                        return '<span class="label ' + badgeClass + '">' + data + '%</span>';
                    }
                }
            ],
            order: [
                [5, 'desc']
            ], // Sort by total count descending
            pageLength: 25,
            lengthMenu: [
                [10, 25, 50, 100, -1],
                [10, 25, 50, 100, "All"]
            ],
            responsive: true,
            language: {
                search: "<?= _translate('Search facilities'); ?>:",
                lengthMenu: "<?= _translate('Show'); ?> _MENU_ <?= _translate('entries'); ?>",
                info: "<?= _translate('Showing'); ?> _START_ <?= _translate('to'); ?> _END_ <?= _translate('of'); ?> _TOTAL_ <?= _translate('facilities'); ?>",
                emptyTable: "<?= _translate('No data available'); ?>",
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
                    html = '<tr><td colspan="4" class="text-center"><?= _translate("No non-originating results found"); ?></td></tr>';
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
