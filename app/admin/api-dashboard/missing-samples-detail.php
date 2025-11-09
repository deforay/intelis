<?php
// missing-samples-detail.php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

$title = _translate("Missing Sample Receipts - Detailed View");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());


$testType = $_GET['testType'] ?? 'vl';
$dateRange = $_GET['dateRange'] ?? '';

?>

<style>
    .priority-high {
        background-color: #ffebee;
    }

    .priority-medium {
        background-color: #fff3e0;
    }

    .priority-low {
        background-color: #f3e5f5;
    }
</style>

<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-exclamation-triangle"></em>
            <?php echo _translate("Missing Sample Receipts - Detailed Analysis"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?php echo _translate("Home"); ?></a></li>
            <li><a href="/admin/api-dashboard/api-dashboard.php"><?php echo _translate("API/EMR Dashboard"); ?></a></li>
            <li class="active"><?php echo _translate("Missing Sample Receipts"); ?></li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><?= _translate('Test Requests Without Sample Receipt'); ?></h3>
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
                                    This report shows test requests that have been created but no corresponding sample receipt has been recorded in the lab.
                                    This could indicate delays in sample transport, missing samples, or data entry issues.
                                    <ul style="margin-top: 10px;">
                                        <li><strong>High Priority:</strong> Requests older than 7 days</li>
                                        <li><strong>Medium Priority:</strong> Requests 3-7 days old</li>
                                        <li><strong>Low Priority:</strong> Requests less than 3 days old</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-md-6">
                                <div class="info-box">
                                    <span class="info-box-icon bg-red"><em class="fa-solid fa-exclamation-triangle"></em></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text"><?= _translate('High Priority'); ?></span>
                                        <span class="info-box-number" id="highPriorityCount">-</span>
                                        <span class="info-box-more"><?= _translate('Requests > 7 days old'); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box">
                                    <span class="info-box-icon bg-yellow"><em class="fa-solid fa-clock"></em></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text"><?= _translate('Medium Priority'); ?></span>
                                        <span class="info-box-number" id="mediumPriorityCount">-</span>
                                        <span class="info-box-more"><?= _translate('Requests 3-7 days old'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-md-12">
                                <button class="btn btn-success btn-sm pull-right" onclick="exportMissingSamples();" style="margin-left: 5px;">
                                    <em class="fa-solid fa-file-excel"></em> <?= _translate('Export to Excel'); ?>
                                </button>
                                <button class="btn btn-primary btn-sm pull-right" onclick="refreshData();">
                                    <em class="fa-solid fa-refresh"></em> <?= _translate('Refresh Data'); ?>
                                </button>
                            </div>
                        </div>

                        <table id="missingSamplesDetailTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th><?= _translate('Priority'); ?></th>
                                    <th><?= _translate('Sample ID'); ?></th>
                                    <th><?= _translate('Remote ID'); ?></th>
                                    <th><?= _translate('Patient Info'); ?></th>
                                    <th><?= _translate('Facility'); ?></th>
                                    <th><?= _translate('Request Date'); ?></th>
                                    <th><?= _translate('Days Pending'); ?></th>
                                    <th><?= _translate('Source'); ?></th>
                                    <th><?= _translate('Actions'); ?></th>
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
        // Load priority counts
        loadPriorityCounts();

        // Initialize DataTable
        dataTable = $('#missingSamplesDetailTable').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "/admin/api-dashboard/get-missing-samples-detail.php",
                "type": "POST",
                "data": function(d) {
                    d.testType = "<?= $testType; ?>";
                    d.dateRange = "<?= $dateRange; ?>";
                }
            },
            "columns": [{
                    "data": "priority",
                    "render": function(data, type, row) {
                        let className = '';
                        let icon = '';
                        if (data === 'High') {
                            className = 'label-danger';
                            icon = 'fa-solid fa-exclamation-circle';
                        } else if (data === 'Medium') {
                            className = 'label-warning';
                            icon = 'fa-solid fa-clock';
                        } else {
                            className = 'label-info';
                            icon = 'fa-solid fa-info-circle';
                        }
                        return '<span class="label ' + className + '"><em class="' + icon + '"></em> ' + data + '</span>';
                    }
                },
                {
                    "data": "sample_code"
                },
                {
                    "data": "remote_sample_code"
                },
                {
                    "data": "patient_info"
                },
                {
                    "data": "facility_name"
                },
                {
                    "data": "request_date"
                },
                {
                    "data": "days_pending",
                    "render": function(data, type, row) {
                        let className = data > 7 ? 'label-danger' : (data > 3 ? 'label-warning' : 'label-info');
                        return '<span class="label ' + className + '">' + data + ' days</span>';
                    }
                },
                {
                    "data": "source_of_request",
                    "render": function(data, type, row) {
                        let className = data.includes('API') ? 'label-primary' : 'label-default';
                        return '<span class="label ' + className + '">' + data + '</span>';
                    }
                },
                {
                    "data": null,
                    "orderable": false,
                    "render": function(data, type, row) {
                        return '<div class="btn-group">' +
                            '<button class="btn btn-xs btn-primary" onclick="followUpSample(\'' + row.sample_code + '\');" title="Follow Up">' +
                            '<em class="fa-solid fa-search"></em></button>' +
                            '<button class="btn btn-xs btn-info" onclick="addNote(\'' + row.sample_code + '\');" title="Add Note">' +
                            '<em class="fa-solid fa-sticky-note"></em></button>' +
                            '<button class="btn btn-xs btn-warning" onclick="sendAlert(\'' + row.sample_code + '\');" title="Send Alert">' +
                            '<em class="fa-solid fa-bell"></em></button>' +
                            '</div>';
                    }
                }
            ],
            "order": [
                [6, "desc"]
            ], // Sort by days pending, descending
            "pageLength": 25,
            "responsive": true,
            "dom": 'Bfrtip',
            "buttons": [{
                    extend: 'excel',
                    text: '<em class="fa-solid fa-file-excel"></em> Export Excel',
                    className: 'btn btn-success btn-sm',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7] // Exclude actions column
                    }
                },
                {
                    extend: 'pdf',
                    text: '<em class="fa-solid fa-file-pdf"></em> Export PDF',
                    className: 'btn btn-danger btn-sm',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7] // Exclude actions column
                    }
                }
            ]
        });
    });

    function loadPriorityCounts() {
        $.post("/admin/api-dashboard/get-missing-samples-priority-counts.php", {
            testType: "<?= $testType; ?>",
            dateRange: "<?= $dateRange; ?>"
        }, function(data) {
            const counts = JSON.parse(data);
            $("#highPriorityCount").text(counts.high || 0);
            $("#mediumPriorityCount").text(counts.medium || 0);
        }).fail(function() {
            $("#highPriorityCount").text("Error");
            $("#mediumPriorityCount").text("Error");
        });
    }

    function refreshData() {
        loadPriorityCounts();
        dataTable.ajax.reload();
    }

    function followUpSample(sampleCode) {
        if (confirm('Open follow-up for sample: ' + sampleCode + '?')) {
            window.open('/vl/requests/editVlRequest.php?id=' + sampleCode, '_blank');
        }
    }

    function addNote(sampleCode) {
        var note = prompt('Add a note for sample ' + sampleCode + ':');
        if (note && note.trim() !== '') {
            $.post("/admin/api-dashboard/add-sample-note.php", {
                sampleCode: sampleCode,
                testType: "<?= $testType; ?>",
                note: note.trim()
            }, function(response) {
                const result = typeof response === 'string' ? JSON.parse(response) : response;
                if (result.success) {
                    alert('Note added successfully');
                } else {
                    alert('Failed to add note: ' + (result.error || 'Unknown error'));
                }
            }).fail(function() {
                alert('Failed to add note due to connection error');
            });
        }
    }

    function sendAlert(sampleCode) {
        if (confirm('Send alert for missing sample: ' + sampleCode + '?')) {
            $.post("/admin/api-dashboard/send-missing-sample-alert.php", {
                sampleCode: sampleCode,
                testType: "<?= $testType; ?>"
            }, function(response) {
                const result = typeof response === 'string' ? JSON.parse(response) : response;
                if (result.success) {
                    alert('Alert sent successfully');
                } else {
                    alert('Failed to send alert: ' + (result.error || 'Unknown error'));
                }
            }).fail(function() {
                alert('Failed to send alert due to connection error');
            });
        }
    }

    function exportMissingSamples() {
        window.open('/admin/api-dashboard/export-missing-samples.php?testType=<?= $testType; ?>&dateRange=<?= urlencode($dateRange); ?>', '_blank');
    }
</script>

<!-- Follow-up Actions Modal -->
<div class="modal fade" id="followUpModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title"><?= _translate('Follow-up Actions'); ?></h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label><?= _translate('Sample Code'); ?>:</label>
                    <input type="text" id="modalSampleCode" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label><?= _translate('Action Type'); ?>:</label>
                    <select id="actionType" class="form-control">
                        <option value="note"><?= _translate('Add Note'); ?></option>
                        <option value="alert"><?= _translate('Send Alert'); ?></option>
                        <option value="contact"><?= _translate('Contact Facility'); ?></option>
                        <option value="escalate"><?= _translate('Escalate Issue'); ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label><?= _translate('Comments'); ?>:</label>
                    <textarea id="actionComments" class="form-control" rows="4" placeholder="<?= _translate('Enter your comments or notes...'); ?>"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal"><?= _translate('Cancel'); ?></button>
                <button type="button" class="btn btn-primary" onclick="executeFollowUp();"><?= _translate('Execute Action'); ?></button>
            </div>
        </div>
    </div>
</div>

<?php
require_once APPLICATION_PATH . '/footer.php';
