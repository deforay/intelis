<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

$title = _translate("Lab Sync Status");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$labNameList = $facilitiesService->getTestingLabs();

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);
$stateNameList = $geolocationService->getProvinces("yes");

?>
<style>
    .select2-selection__choice {
        color: black !important;
    }

    th {
        display: revert !important;
    }

    .red {
        background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%) !important;
        border-left: 4px solid #dc3545 !important;
    }

    .green {
        background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%) !important;
        border-left: 4px solid #28a745 !important;
    }

    .yellow {
        background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%) !important;
        border-left: 4px solid #ffc107 !important;
    }

    .center {
        text-align: center;
    }

    #syncStatusTable tr:hover {
        cursor: pointer;
        background: #eee !important;
    }

    /* Enhanced sync status indicators */
    .sync-indicator {
        display: inline-block;
        width: 8px;
        height: 8px;
        border-radius: 50%;
        margin-right: 5px;
    }

    .green-indicator {
        background-color: #28a745;
        box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.3);
    }

    .yellow-indicator {
        background-color: #ffc107;
        box-shadow: 0 0 0 2px rgba(255, 193, 7, 0.3);
    }

    .red-indicator {
        background-color: #dc3545;
        box-shadow: 0 0 0 2px rgba(220, 53, 69, 0.3);
    }

    /* Status summary cards */
    .status-summary {
        margin-bottom: 20px;
    }

    .status-card {
        background: white;
        border-radius: 8px;
        padding: 15px;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        text-align: center;
        border-top: 4px solid;
        transition: transform 0.2s ease;
    }

    .status-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    .status-card.active {
        border-top-color: #28a745;
    }

    .status-card.warning {
        border-top-color: #ffc107;
    }

    .status-card.critical {
        border-top-color: #dc3545;
    }

    .status-card h3 {
        margin: 0;
        font-size: 2em;
        font-weight: bold;
    }

    .status-card p {
        margin: 5px 0 0;
        color: #666;
        font-size: 0.9em;
    }

    /* Loading states */
    .loading-overlay {
        position: relative;
    }

    .loading-overlay::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }

    .loading-spinner {
        border: 3px solid #f3f3f3;
        border-top: 3px solid #3498db;
        border-radius: 50%;
        width: 30px;
        height: 30px;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% {
            transform: rotate(0deg);
        }

        100% {
            transform: rotate(360deg);
        }
    }

    /* Filter section improvements */
    .filter-section {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }

    .filter-row {
        display: flex;
        gap: 15px;
        align-items: end;
        flex-wrap: wrap;
    }

    .filter-group {
        flex: 1;
        min-width: 200px;
    }

    .filter-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        color: #333;
    }

    .filter-actions {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-top: 15px;
    }

    /* Table improvements */
    .table-container {
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }

    #syncStatusDataTable {
        margin-bottom: 0;
    }

    #syncStatusDataTable thead th {
        background: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        font-weight: bold;
        color: #495057;
    }

    /* Responsive improvements */
    @media (max-width: 768px) {
        .filter-row {
            flex-direction: column;
        }

        .filter-group {
            width: 100%;
            min-width: auto;
        }

        .status-summary .col-md-4 {
            margin-bottom: 15px;
        }

        .table-responsive {
            font-size: 0.9em;
        }

        .filter-actions {
            justify-content: center;
        }
    }

    /* Last sync time styling */
    .sync-time {
        font-size: 0.9em;
        color: #666;
    }

    .sync-status-text {
        font-size: 0.8em;
        font-weight: bold;
        text-transform: uppercase;
    }

    /* Auto-refresh indicator */
    .refresh-indicator {
        position: fixed;
        top: 20px;
        right: 20px;
        background: rgba(0, 0, 0, 0.7);
        color: white;
        padding: 10px 15px;
        border-radius: 20px;
        font-size: 0.9em;
        z-index: 1000;
        display: none;
    }

    .refresh-indicator.show {
        display: block;
        animation: fadeInOut 2s ease-in-out;
    }

    @keyframes fadeInOut {

        0%,
        100% {
            opacity: 0;
        }

        50% {
            opacity: 1;
        }
    }
</style>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-traffic-light"></em>
            <?php echo _translate("Lab Sync Status"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
                    <?php echo _translate("Home"); ?>
                </a></li>
            <li class="active">
                <?php echo _translate("Lab Sync Status"); ?>
            </li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <!-- Status Summary Cards -->
                <div class="status-summary">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="status-card active">
                                <h3 id="activeCount">0</h3>
                                <p><?php echo _translate("Active Labs"); ?></p>
                                <small><?php echo _translate("Synced within 2 weeks"); ?></small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="status-card warning">
                                <h3 id="warningCount">0</h3>
                                <p><?php echo _translate("Warning Labs"); ?></p>
                                <small><?php echo _translate("Synced 2-4 weeks ago"); ?></small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="status-card critical">
                                <h3 id="criticalCount">0</h3>
                                <p><?php echo _translate("Critical Labs"); ?></p>
                                <small><?php echo _translate("Not synced for 4+ weeks"); ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="box">
                    <!-- Filter Section -->
                    <div class="filter-section">
                        <div class="filter-row">
                            <div class="filter-group">
                                <label for="province"><?php echo _translate("Province/State"); ?></label>
                                <select name="province" id="province" onchange="getDistrictByProvince(this.value)"
                                    class="form-control"
                                    title="<?php echo _translate('Please choose Province/State/Region'); ?>">
                                    <?= $general->generateSelectOptions($stateNameList, null, _translate("-- Select --")); ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="district"><?php echo _translate("District/County"); ?></label>
                                <select class="form-control" id="district" name="district"
                                    title="<?php echo _translate('Please select Province/State'); ?>">
                                    <option value=""><?php echo _translate("-- Select Province First --"); ?></option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="labName"><?php echo _translate("Lab Name"); ?></label>
                                <select class="form-control select2" id="labName" name="labName"
                                    title="<?php echo _translate('Please select the Lab name'); ?>">
                                    <?php echo $general->generateSelectOptions($labNameList, null, _translate('-- Select --')); ?>
                                </select>
                            </div>
                        </div>

                        <div class="filter-actions">
                            <button type="button" onclick="loadData();" class="btn btn-primary">
                                <i class="fa fa-search"></i> <?= _translate('Search'); ?>
                            </button>
                            <button type="button" class="btn btn-default" onclick="resetFilters();">
                                <i class="fa fa-refresh"></i> <?= _translate('Reset'); ?>
                            </button>
                            <button type="button" class="btn btn-success" onclick="exportSyncStatus();">
                                <i class="fa-solid fa-file-excel"></i> <?php echo _translate("Export Excel"); ?>
                            </button>
                            <div class="auto-refresh-toggle" style="margin-left: auto;">
                                <label>
                                    <input type="checkbox" id="autoRefresh" checked>
                                    <?php echo _translate("Auto-refresh (5 min)"); ?>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- /.box-header -->
                    <div class="box-body">
                        <div class="table-container">
                            <div class="table-responsive">
                                <table aria-describedby="table" id="syncStatusDataTable"
                                    class="table table-bordered table-striped table-hover" aria-hidden="true">
                                    <thead>
                                        <tr>
                                            <th class="center">
                                                <?php echo _translate("Lab Name"); ?>
                                            </th>
                                            <th class="center">
                                                <?php echo _translate("Last Synced on"); ?>
                                            </th>
                                            <th class="center">
                                                <?php echo _translate("Last Results Sync from Lab"); ?>
                                            </th>
                                            <th class="center">
                                                <?php echo _translate("Last Requests Sync from STS"); ?>
                                            </th>
                                            <th class="center">
                                                <?php echo _translate("Version"); ?>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody id="syncStatusTable">
                                        <tr>
                                            <td colspan="5" class="dataTables_empty center">
                                                <i class="fa fa-spinner fa-spin"></i>
                                                <?php echo _translate("Loading data..."); ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- /.box -->
            </div>
            <!-- /.col -->
        </div>
        <!-- /.row -->
    </section>
    <!-- /.content -->
</div>

<!-- Auto-refresh indicator -->
<div class="refresh-indicator" id="refreshIndicator">
    <i class="fa fa-refresh fa-spin"></i> <?php echo _translate("Auto-refreshing data..."); ?>
</div>

<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
    // Global variables
    let oTable = null;
    let loadDataTimeout;
    let currentRequest;
    let autoRefreshInterval;

    $(document).ready(function () {
        // Initialize select2 with better performance
        $('#labName').select2({
            width: '100%',
            placeholder: "<?php echo _translate('Select Testing Lab'); ?>",
            allowClear: true,
            minimumInputLength: 0
        });

        $('#province').select2({
            width: '100%',
            placeholder: "<?php echo _translate('Select Province'); ?>",
            allowClear: true
        });

        $('#district').select2({
            width: '100%',
            placeholder: "<?php echo _translate('Select District'); ?>",
            allowClear: true
        });

        // Row click handler for drill-down
        $('#syncStatusDataTable tbody').on('click', 'tr', function () {
            let facilityId = $(this).attr('data-facilityId');
            if (facilityId && !$(this).hasClass('dataTables_empty')) {
                let link = "/admin/monitoring/lab-sync-details.php?labId=" + facilityId;
                window.open(link, '_blank');
            }
        });

        // Debounced search on filter changes
        $('#province, #district, #labName').on('change', function () {
            clearTimeout(loadDataTimeout);
            loadDataTimeout = setTimeout(loadData, 500);
        });

        // Auto-refresh toggle
        $('#autoRefresh').on('change', function () {
            if ($(this).is(':checked')) {
                setupAutoRefresh();
            } else {
                clearInterval(autoRefreshInterval);
            }
        });


        // Initial load
        loadData();
        // Auto-refresh setup
        setupAutoRefresh();
    });

    function setupAutoRefresh() {
        // Clear existing interval
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }

        // Set up new interval (5 minutes)
        autoRefreshInterval = setInterval(function () {
            if ($('#autoRefresh').is(':checked')) {
                showRefreshIndicator();
                loadData(true); // Silent refresh
            }
        }, 300000); // 5 minutes
    }

    function showRefreshIndicator() {
        $('#refreshIndicator').addClass('show');
        setTimeout(function () {
            $('#refreshIndicator').removeClass('show');
        }, 2000);
    }

    function loadData(silent = false) {
        // Cancel previous request if still pending
        if (currentRequest) {
            currentRequest.abort();
        }

        if (!silent) {
            showLoading();
        }

        const postData = {
            province: $('#province').val(),
            district: $('#district').val(),
            labName: $('#labName').val()
        };

        currentRequest = $.ajax({
            url: "/admin/monitoring/get-sync-status.php",
            type: "POST",
            data: postData,
            timeout: 30000,
            success: function (data) {
                $("#syncStatusTable").html(data);
                updateStatusSummary();
                hideLoading();

                // Show success message for manual refreshes
                if (!silent) {
                    showNotification('<?php echo _translate("Data loaded successfully"); ?>', 'success');
                }
            },
            error: function (xhr, status, error) {
                if (status !== 'abort') {
                    console.error('Failed to load sync status:', error);
                    $("#syncStatusTable").html(
                        '<tr><td colspan="5" class="text-center text-danger">' +
                        '<i class="fa fa-exclamation-triangle"></i> ' +
                        '<?php echo _translate("Failed to load data. Please try again."); ?>' +
                        '</td></tr>'
                    );
                    hideLoading();
                    showNotification('<?php echo _translate("Failed to load data"); ?>', 'error');
                }
            },
            complete: function () {
                currentRequest = null;
            }
        });
    }

    function showLoading() {
        $('#syncStatusDataTable').addClass('loading-overlay').append(
            '<div class="loading-spinner"></div>'
        );
    }

    function hideLoading() {
        $('#syncStatusDataTable').removeClass('loading-overlay');
        $('.loading-spinner').remove();
    }

    function updateStatusSummary() {
        const activeCount = $('#syncStatusTable .green').length;
        const warningCount = $('#syncStatusTable .yellow').length;
        const criticalCount = $('#syncStatusTable .red').length;

        // Animate counter updates
        animateCounter('#activeCount', activeCount);
        animateCounter('#warningCount', warningCount);
        animateCounter('#criticalCount', criticalCount);
    }

    function animateCounter(selector, targetValue) {
        const element = $(selector);
        const currentValue = parseInt(element.text()) || 0;

        $({
            count: currentValue
        }).animate({
            count: targetValue
        }, {
            duration: 1000,
            step: function () {
                element.text(Math.floor(this.count));
            },
            complete: function () {
                element.text(targetValue);
            }
        });
    }

    function resetFilters() {
        $('#province').val('').trigger('change');
        $('#district').html('<option value=""><?php echo _translate("-- Select Province First --"); ?></option>').val('').trigger('change');
        $('#labName').val('').trigger('change');
        loadData();
    }

    function getDistrictByProvince(provinceId) {
        if (!provinceId) {
            $("#district").html('<option value=""><?php echo _translate("-- Select Province First --"); ?></option>').prop('disabled', false);
            return;
        }

        $("#district").html('<option value=""><?php echo _translate("Loading..."); ?></option>').prop('disabled', true);

        $.ajax({
            url: "/common/get-by-province-id.php",
            type: "POST",
            data: {
                provinceId: provinceId,
                districts: true,
            },
            success: function (data) {
                try {
                    const obj = $.parseJSON(data);
                    $("#district").html(obj['districts']).prop('disabled', false);
                    $('#district').select2('val', ''); // Clear selection
                } catch (e) {
                    console.error('Failed to parse district data:', e);
                    $("#district").html('<option value=""><?php echo _translate("Error loading districts"); ?></option>');
                    showNotification('<?php echo _translate("Error loading districts"); ?>', 'error');
                }
            },
            error: function () {
                $("#district").html('<option value=""><?php echo _translate("Error loading districts"); ?></option>').prop('disabled', false);
                showNotification('<?php echo _translate("Error loading districts"); ?>', 'error');
            }
        });
    }

    function exportSyncStatus() {
        const exportButton = $('[onclick="exportSyncStatus();"]');
        const originalButtonHtml = exportButton.html();

        exportButton.html('<i class="fa fa-spinner fa-spin"></i> <?php echo _translate("Generating..."); ?>').prop('disabled', true);

        $.ajax({
            url: "/admin/monitoring/generate-lab-sync-status-report.php",
            type: "POST",
            data: {
                province: $('#province').val(),
                district: $('#district').val(),
                labName: $('#labName').val()
            },
            timeout: 60000,
            success: function (data) {
                if (data && data.trim()) {
                    window.open('/download.php?f=' + data, '_blank');
                    showNotification('<?php echo _translate("Export generated successfully"); ?>', 'success');
                } else {
                    showNotification('<?php echo _translate("Unable to generate the excel file. Please try again."); ?>', 'error');
                }
            },
            error: function () {
                showNotification('<?php echo _translate("Failed to generate export. Please try again."); ?>', 'error');
            },
            complete: function () {
                exportButton.html(originalButtonHtml).prop('disabled', false);
            }
        });
    }

    function showNotification(message, type = 'info') {
        const alertClass = type === 'success' ? 'alert-success' :
            type === 'error' ? 'alert-danger' : 'alert-info';

        const notification = $(`
            <div class="alert ${alertClass} alert-dismissible" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                ${message}
            </div>
        `);

        $('body').append(notification);

        // Auto-dismiss after 5 seconds
        setTimeout(function () {
            notification.fadeOut(function () {
                $(this).remove();
            });
        }, 5000);
    }

    // Keyboard shortcuts
    $(document).keydown(function (e) {
        // Ctrl+R or F5 for refresh
        if ((e.ctrlKey && e.keyCode === 82) || e.keyCode === 116) {
            e.preventDefault();
            loadData();
        }
        // Ctrl+E for export
        if (e.ctrlKey && e.keyCode === 69) {
            e.preventDefault();
            exportSyncStatus();
        }
    });

    // Cleanup on page unload
    $(window).on('beforeunload', function () {
        if (currentRequest) {
            currentRequest.abort();
        }
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
        }
    });
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
