<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

$title = _translate("Interface Machine Activity");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$labScope = $general->labAdminScopeWhere('lab_id');
$scopeClause = !empty($labScope) ? " WHERE $labScope " : '';

// Only offer filter values that actually occur, so an operator is never left
// searching for something this instance has never recorded.
$eventTypes = $db->rawQuery(
    "SELECT DISTINCT event_type FROM instrument_activity_log $scopeClause ORDER BY event_type ASC"
) ?: [];

$summary = $db->rawQueryOne(
    "SELECT COUNT(*) AS total_events,
            SUM(outcome = 'failed') AS failures,
            COUNT(DISTINCT instrument_id) AS instruments,
            MAX(occurred_at) AS last_seen
       FROM instrument_activity_log
      WHERE occurred_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
    . (!empty($labScope) ? " AND $labScope " : '')
) ?: [];
?>
<style>
    #interfaceActivityReport .ifa-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin: 0 0 18px;
    }

    #interfaceActivityReport .ifa-card {
        flex: 1 1 160px;
        padding: 12px 15px;
        background-color: #f8fafb;
        border: 1px solid #e4e8ec;
        border-left: 3px solid #3c8dbc;
        border-radius: 3px;
    }

    #interfaceActivityReport .ifa-card.is-alert {
        border-left-color: #c0392b;
    }

    #interfaceActivityReport .ifa-card-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #8a9299;
    }

    #interfaceActivityReport .ifa-card-value {
        font-size: 22px;
        font-weight: 700;
        color: #444;
        line-height: 1.3;
    }

    .ifa-pill {
        display: inline-block;
        padding: 3px 9px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.5;
        border-radius: 11px;
    }

    .ifa-pill-success {
        color: #2e7d46;
        background-color: #e8f5ec;
        border: 1px solid #cfe8d8;
    }

    .ifa-pill-failed {
        color: #b03a2e;
        background-color: #fdecea;
        border: 1px solid #f5c6c0;
    }

    .ifa-pill-muted {
        color: #5a6570;
        background-color: #eef1f4;
        border: 1px solid #e0e5ea;
    }

    th {
        display: revert !important;
    }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper" id="interfaceActivityReport">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-plug"></em>
            <?php echo _translate("Interface Machine Activity"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
                    <?php echo _translate("Home"); ?>
                </a></li>
            <li class="active">
                <?php echo _translate("Interface Machine Activity"); ?>
            </li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-body">
                        <p class="text-muted" id="interface-activity-description">
                            <?= _translate(
                                'What the Interface Tool recorded about its instruments: '
                                . 'connection attempts, connection failures and application starts.'
                            ); ?>
                        </p>

                        <div class="ifa-summary">
                            <div class="ifa-card">
                                <div class="ifa-card-label"><?= _translate('Events (last 7 days)'); ?></div>
                                <div class="ifa-card-value">
                                    <?= number_format((int) ($summary['total_events'] ?? 0)); ?>
                                </div>
                            </div>
                            <div class="ifa-card <?= (int) ($summary['failures'] ?? 0) > 0 ? 'is-alert' : ''; ?>">
                                <div class="ifa-card-label"><?= _translate('Failures (last 7 days)'); ?></div>
                                <div class="ifa-card-value">
                                    <?= number_format((int) ($summary['failures'] ?? 0)); ?>
                                </div>
                            </div>
                            <div class="ifa-card">
                                <div class="ifa-card-label"><?= _translate('Instruments reporting'); ?></div>
                                <div class="ifa-card-value">
                                    <?= number_format((int) ($summary['instruments'] ?? 0)); ?>
                                </div>
                            </div>
                            <div class="ifa-card">
                                <div class="ifa-card-label"><?= _translate('Last event'); ?></div>
                                <div class="ifa-card-value" style="font-size:15px;">
                                    <?= !empty($summary['last_seen'])
                                        ? htmlspecialchars(
                                            \App\Utilities\DateUtility::humanReadableDateFormat(
                                                $summary['last_seen'],
                                                true
                                            ),
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )
                                        : _translate('No activity yet'); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <table aria-describedby="interface-activity-description" class="table pageFilters"
                        aria-hidden="true" cellspacing="3" style="margin-left:1%;margin-top:5px;width:98%;">
                        <tr>
                            <td><strong><?= _translate('Date Range'); ?>&nbsp;:</strong></td>
                            <td>
                                <input type="text" id="dateRange" name="dateRange"
                                    class="form-control daterangefield" style="width:100%;max-width:260px;" />
                            </td>
                            <td><strong><?= _translate('Event'); ?>&nbsp;:</strong></td>
                            <td>
                                <select id="eventType" name="eventType" class="form-control"
                                    style="width:100%;max-width:260px;">
                                    <option value=""><?= _translate('-- All --'); ?></option>
                                    <?php foreach ($eventTypes as $eventType) {
                                        $value = htmlspecialchars(
                                            (string) $eventType['event_type'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ); ?>
                                        <option value="<?= $value; ?>"><?= $value; ?></option>
                                    <?php } ?>
                                </select>
                            </td>
                            <td><strong><?= _translate('Outcome'); ?>&nbsp;:</strong></td>
                            <td>
                                <select id="outcome" name="outcome" class="form-control"
                                    style="width:100%;max-width:180px;">
                                    <option value=""><?= _translate('-- All --'); ?></option>
                                    <option value="failed"><?= _translate('Failed'); ?></option>
                                    <option value="success"><?= _translate('Success'); ?></option>
                                    <option value="started"><?= _translate('Started'); ?></option>
                                </select>
                            </td>
                            <td><strong><?= _translate('Instrument'); ?>&nbsp;:</strong></td>
                            <td>
                                <input type="text" id="instrument" name="instrument" class="form-control"
                                    placeholder="<?= _translate('Instrument name'); ?>"
                                    style="width:100%;max-width:220px;" />
                            </td>
                            <td>
                                <button onclick="oTable.fnDraw();" class="btn btn-primary btn-sm">
                                    <span><?= _translate("Search"); ?></span>
                                </button>
                                <button
                                    onclick="$('#dateRange,#instrument').val('');$('#eventType,#outcome').val('').trigger('change');oTable.fnDraw();"
                                    class="btn btn-default btn-sm">
                                    <span><?= _translate("Reset"); ?></span>
                                </button>
                            </td>
                        </tr>
                    </table>

                    <div class="box-body">
                        <table aria-describedby="interface-activity-description" id="interfaceActivityTable"
                            class="table table-bordered table-striped" aria-hidden="true">
                            <thead>
                                <tr>
                                    <th><?= _translate("Occurred On"); ?></th>
                                    <th><?= _translate("Lab"); ?></th>
                                    <th><?= _translate("Instrument"); ?></th>
                                    <th><?= _translate("Event"); ?></th>
                                    <th><?= _translate("Outcome"); ?></th>
                                    <th><?= _translate("Failure Code"); ?></th>
                                    <th><?= _translate("Protocol / Mode"); ?></th>
                                    <th><?= _translate("App Version"); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="8" class="dataTables_empty">
                                        <?= _translate("Loading data from server"); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
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
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript">
    var oTable = null;
    $(document).ready(function () {
        $('#dateRange').daterangepicker({
            locale: {
                cancelLabel: "<?= _translate("Clear", true); ?>",
                format: 'DD-MMM-YYYY',
                separator: ' to ',
            },
            startDate: moment().subtract(7, 'days'),
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
        });

        loadInterfaceActivity();
    });

    function loadInterfaceActivity() {
        oTable = $('#interfaceActivityTable').dataTable({
            "bJQueryUI": false,
            "bAutoWidth": false,
            "bInfo": true,
            "bScrollCollapse": true,
            "bRetrieve": true,
            "aoColumns": [
                { "sClass": "center" },
                { "sClass": "center" },
                { "sClass": "center" },
                { "sClass": "center" },
                { "sClass": "center" },
                { "sClass": "center" },
                { "sClass": "center" },
                { "sClass": "center" }
            ],
            "aaSorting": [
                [0, "desc"]
            ],
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": "/admin/monitoring/get-interface-machine-activity.php",
            "fnServerData": function (sSource, aoData, fnCallback) {
                aoData.push({ "name": "dateRange", "value": $("#dateRange").val() });
                aoData.push({ "name": "eventType", "value": $("#eventType").val() });
                aoData.push({ "name": "outcome", "value": $("#outcome").val() });
                aoData.push({ "name": "instrument", "value": $("#instrument").val() });
                $.ajax({
                    "dataType": 'json',
                    "type": "POST",
                    "url": sSource,
                    "data": aoData,
                    "success": fnCallback
                });
            }
        });
    }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
