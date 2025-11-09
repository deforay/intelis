<?php
// duplicates-detail.php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

$title = _translate("Potential Duplicate Entries - Detailed View");
require_once APPLICATION_PATH . '/header.php';


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$testType = $_GET['testType'] ?? 'vl';
$dateRange = $_GET['dateRange'] ?? '';
$labName = $_GET['labName'] ?? '';
$state = $_GET['state'] ?? '';
$district = $_GET['district'] ?? '';
$facilityId = $_GET['facilityId'] ?? '';

?>

<style>
    .risk-high {
        background-color: #ffebee;
    }

    .risk-medium {
        background-color: #fff3e0;
    }

    .risk-low {
        background-color: #f3e5f5;
    }
</style>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-users"></em>
            <?php echo _translate("Potential Duplicate Entries - Detailed Analysis"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li><a href="/admin/api-dashboard/api-dashboard.php"><?php echo _translate("API/EMR Dashboard"); ?></a></li>
            <li class="active"><?php echo _translate("Potential Duplicates"); ?></li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><?= _translate('Potential Duplicate Test Requests'); ?></h3>
                        <div class="box-tools pull-right">
                            <span class="label label-info">Test Type: <?= strtoupper($testType); ?></span>
                            <?php if ($dateRange): ?>
                                <span class="label label-primary">Period: <?= htmlspecialchars($dateRange); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="box-body">
                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-md-12">
                                <div class="alert alert-info">
                                    <strong><em class="fa-solid fa-info-circle"></em> About This Report:</strong><br>
                                    This report identifies potential duplicate test requests based on patient identifiers (name and/or ART number)
                                    and collection dates within a 7-day window. This helps identify:
                                    <ul style="margin-top: 10px;">
                                        <li><strong>High Risk:</strong> Same patient, samples collected within 1 day (likely data entry error)</li>
                                        <li><strong>Medium Risk:</strong> Same patient, samples collected within 2-3 days</li>
                                        <li><strong>Low Risk:</strong> Same patient, samples collected within 4-7 days (may be legitimate follow-up)</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-md-12">
                                <button class="btn btn-success btn-sm pull-right" onclick="exportDuplicates();" style="margin-left: 5px;">
                                    <em class="fa-solid fa-file-excel"></em> <?= _translate('Export to Excel'); ?>
                                </button>
                                <button class="btn btn-primary btn-sm pull-right" onclick="refreshData();">
                                    <em class="fa-solid fa-refresh"></em> <?= _translate('Refresh Data'); ?>
                                </button>
                            </div>
                        </div>

                        <table id="duplicatesDetailTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th style="display: none;"><?= _translate('Sort Value'); ?></th> <!-- Hidden -->
                                    <th><?= _translate('Risk Level'); ?></th>
                                    <th><?= _translate('Patient Information'); ?></th>
                                    <th><?= _translate('Sample IDs'); ?></th>
                                    <th><?= _translate('Collection Dates'); ?></th>
                                    <th><?= _translate('Request Sources'); ?></th>
                                    <th><?= _translate('Count'); ?></th>
                                    <th><?= _translate('Date Span'); ?></th>
                                    <th><?= _translate('Facilities'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Data loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script type="text/javascript">
    var dataTable;

    $(document).ready(function() {
        // Initialize DataTable
        dataTable = $('#duplicatesDetailTable').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "/admin/api-dashboard/get-duplicates-detail.php",
                "type": "POST",
                "data": function(d) {
                    d.testType = "<?= $testType; ?>";
                    d.dateRange = "<?= $dateRange; ?>";
                    d.labName = "<?= $labName; ?>";
                    d.state = "<?= $state; ?>";
                    d.district = "<?= $district; ?>";
                    d.facilityId = "<?= $facilityId; ?>";
                }
            },
            "columns": [{
                    "data": 0,
                    "name": "risk_sort_value",
                    "visible": false,
                    "searchable": false
                }, // Hidden sort column
                {
                    "data": 1,
                    "name": "risk_level",
                    "orderData": [0] // Sort by the hidden column
                },
                {
                    "data": 2,
                    "name": "patient_info",
                    "orderable": false
                },
                {
                    "data": 3,
                    "name": "sample_ids",
                    "orderable": false
                },
                {
                    "data": 4,
                    "name": "collection_dates",
                    "orderable": false
                },
                {
                    "data": 5,
                    "name": "sources",
                    "orderable": false
                },
                {
                    "data": 6,
                    "name": "duplicate_count"
                },
                {
                    "data": 7,
                    "name": "days_span"
                },
                {
                    "data": 8,
                    "name": "facility_names",
                    "orderable": false
                }
            ],
            "order": [
                [0, "desc"]
            ], // Sort by risk_sort_value desc, then days span asc
            "pageLength": 25,
            "responsive": true,
            "dom": 'Bfrtip',
            "buttons": [{
                    extend: 'excel',
                    text: '<em class="fa-solid fa-file-excel"></em> Export Excel',
                    className: 'btn btn-success btn-sm',
                    exportOptions: {
                        columns: [1, 2, 3, 4, 5, 6, 7, 8] // Exclude the hidden sort column
                    }
                },
                {
                    extend: 'pdf',
                    text: '<em class="fa-solid fa-file-pdf"></em> Export PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: {
                        columns: [1, 2, 3, 4, 5, 6, 7, 8] // Exclude the hidden sort column
                    }
                }
            ],
            "createdRow": function(row, data, dataIndex) {
                // Add CSS classes based on risk level (using the visible column)
                const riskLevel = $(data[1]).text().trim();
                if (riskLevel === 'High') {
                    $(row).addClass('risk-high');
                } else if (riskLevel === 'Medium') {
                    $(row).addClass('risk-medium');
                } else if (riskLevel === 'Low') {
                    $(row).addClass('risk-low');
                }
            }
        });
    });

    function refreshData() {
        dataTable.ajax.reload();
    }

    function exportDuplicates() {
        const params = new URLSearchParams({
            testType: "<?= $testType; ?>",
            dateRange: "<?= $dateRange; ?>",
            labName: "<?= $labName; ?>",
            state: "<?= $state; ?>",
            district: "<?= $district; ?>",
            facilityId: "<?= $facilityId; ?>"
        });
        window.open('/admin/api-dashboard/export-duplicates.php?' + params.toString(), '_blank');
    }
</script>

<?php
require_once APPLICATION_PATH . '/footer.php';
