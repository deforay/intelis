<?php

use App\Services\TestsService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\LabPerformanceIndicatorsService;

$title = _translate("Instrument Activity");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

// The modules that record the instrument and assay of each test.
$instrumentTests = array_values(array_intersect(
    LabPerformanceIndicatorsService::ASSAY_TEST_KEYS,
    TestsService::getActiveTests()
));
$testingLabs = $facilitiesService->getTestingLabs();

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

    #interfaceActivityReport .nav-tabs {
        margin: 0 10px 15px;
    }

    #interfaceActivityReport .ia-note {
        margin: 0 0 15px;
        color: #6b7580;
        font-size: 12px;
    }

    #interfaceActivityReport .ia-section-title {
        margin: 20px 0 8px;
        font-size: 15px;
        font-weight: 600;
    }

    #interfaceActivityReport td.num,
    #interfaceActivityReport th.num {
        text-align: right;
    }

    #interfaceActivityReport tr.ia-filters th {
        padding: 4px;
        font-weight: normal;
    }

    #interfaceActivityReport tr.ia-total td {
        font-weight: 700;
        background-color: #f4f6f8;
    }

    #interfaceActivityReport .ia-samples-close {
        margin-left: 8px;
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
            <?php echo _htmlTranslate("Instrument Activity"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
                    <?php echo _htmlTranslate("Home"); ?>
                </a></li>
            <li class="active">
                <?php echo _htmlTranslate("Instrument Activity"); ?>
            </li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <ul class="nav nav-tabs" id="iaTabs" role="tablist" style="margin-top:10px;">
                        <?php if (!empty($instrumentTests)) { ?>
                            <li role="presentation" class="active"><a href="#tab-tests" role="tab"
                                    data-toggle="tab"><?= _htmlTranslate('Tests by Instrument'); ?></a></li>
                        <?php } ?>
                        <li role="presentation" class="<?= empty($instrumentTests) ? 'active' : ''; ?>">
                            <a href="#tab-events" role="tab" data-toggle="tab">
                                <?= _htmlTranslate('Interface Tool Events'); ?></a>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <?php if (!empty($instrumentTests)) { ?>
                            <div role="tabpanel" class="tab-pane active" id="tab-tests">
                                <div class="box-body">
                                    <p class="text-muted" id="instrument-tests-description">
                                        <?= _htmlTranslate('Tests run on each instrument and assay.'); ?>
                                    </p>
                                    <table aria-describedby="instrument-tests-description" class="table pageFilters"
                                        aria-hidden="true" cellspacing="3" style="width:100%;">
                                        <tr>
                                            <td><strong><?= _htmlTranslate('Test'); ?>&nbsp;:</strong></td>
                                            <td>
                                                <select id="testsTestType" class="form-control"
                                                    style="width:100%;max-width:200px;">
                                                    <?php foreach ($instrumentTests as $testKey) { ?>
                                                        <option value="<?= htmlspecialchars($testKey, ENT_QUOTES); ?>">
                                                            <?= htmlspecialchars(
                                                                (string) TestsService::getTestName($testKey),
                                                                ENT_QUOTES
                                                            ); ?>
                                                        </option>
                                                    <?php } ?>
                                                </select>
                                            </td>
                                            <td><strong><?= _htmlTranslate('Tested On'); ?>&nbsp;:</strong></td>
                                            <td>
                                                <input type="text" id="testsDateRange"
                                                    class="form-control daterangefield"
                                                    style="width:100%;max-width:260px;" />
                                            </td>
                                            <?php if (!empty($testingLabs)) { ?>
                                                <td><strong><?= _htmlTranslate('Lab'); ?>&nbsp;:</strong></td>
                                                <td>
                                                    <select id="testsLabId" class="form-control"
                                                        style="width:100%;max-width:240px;">
                                                        <option value=""><?= _htmlTranslate('-- All Labs --'); ?></option>
                                                        <?php foreach ($testingLabs as $labId => $labName) { ?>
                                                            <option value="<?= (int) $labId; ?>">
                                                                <?= htmlspecialchars((string) $labName, ENT_QUOTES); ?>
                                                            </option>
                                                        <?php } ?>
                                                    </select>
                                                </td>
                                            <?php } ?>
                                            <td>
                                                <button onclick="iaLoadTests();" class="btn btn-primary btn-sm">
                                                    <span><?= _htmlTranslate("Search"); ?></span>
                                                </button>
                                                <button onclick="iaExport('tests', 'xlsx');" class="btn btn-success btn-sm">
                                                    <em class="fa-solid fa-file-excel"></em>
                                                    <?= _htmlTranslate("Export to Excel"); ?>
                                                </button>
                                            </td>
                                        </tr>
                                    </table>

                                    <div class="ia-section-title"><?= _htmlTranslate('By Instrument Type'); ?></div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped" id="testsByTypeTable"
                                            aria-describedby="instrument-tests-description">
                                            <thead>
                                                <tr>
                                                    <th><?= _htmlTranslate('Instrument Type'); ?></th>
                                                    <th class="num"><?= _htmlTranslate('Tests Run'); ?></th>
                                                    <th class="num"><?= _htmlTranslate('Samples'); ?></th>
                                                    <th class="num"><?= _htmlTranslate('Failed or Invalid'); ?></th>
                                                    <th class="num"><?= _htmlTranslate('Failure Rate (%)'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>

                                    <div class="ia-section-title"><?= _htmlTranslate('By Lab, Instrument and Assay'); ?></div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped" id="testsDetailTable"
                                            aria-describedby="instrument-tests-description">
                                            <thead>
                                                <tr>
                                                    <th><?= _htmlTranslate('Testing Lab'); ?></th>
                                                    <th><?= _htmlTranslate('Instrument'); ?></th>
                                                    <th><?= _htmlTranslate('Assay'); ?></th>
                                                    <th class="num"><?= _htmlTranslate('Tests Run'); ?></th>
                                                    <th class="num"><?= _htmlTranslate('Failed or Invalid'); ?></th>
                                                    <th class="num"><?= _htmlTranslate('Failure Rate (%)'); ?></th>
                                                    <th class="num"><?= _htmlTranslate('Samples'); ?></th>
                                                    <th class="num"><?= _htmlTranslate('Valid First Time'); ?></th>
                                                    <th class="num"><?= _htmlTranslate('Valid After Re-test'); ?></th>
                                                    <th class="num"><?= _htmlTranslate('Still Failed'); ?></th>
                                                </tr>
                                                <?php // One dropdown per text column, filled from the loaded rows. ?>
                                                <tr class="ia-filters">
                                                    <?php for ($column = 0; $column < 3; $column++) { ?>
                                                        <th>
                                                            <select class="form-control input-sm ia-col-filter"
                                                                data-column="<?= $column; ?>">
                                                                <option value=""><?= _htmlTranslate('-- All --'); ?></option>
                                                            </select>
                                                        </th>
                                                    <?php } ?>
                                                    <th colspan="7"></th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                            <tfoot>
                                                <tr class="ia-total">
                                                    <td colspan="3"><?= _htmlTranslate('Total'); ?></td>
                                                    <?php for ($column = 0; $column < 7; $column++) { ?>
                                                        <td class="num"></td>
                                                    <?php } ?>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                    <p class="ia-note">
                                        <?= _htmlTranslate(
                                            'Tests Run counts every run, failed runs that were re-tested included. '
                                            . 'Samples counts each sample once, under its latest run in the range. '
                                            . 'Cancelled and rejected samples are not counted.'
                                        ); ?>
                                    </p>

                                    <?php // The samples behind a count, opened from its link. ?>
                                    <div id="iaSamples" hidden>
                                        <div class="ia-section-title">
                                            <span id="iaSamplesTitle"></span>
                                            <button type="button" class="btn btn-default btn-xs ia-samples-close"
                                                id="iaSamplesClose"><?= _htmlTranslate('Close'); ?></button>
                                        </div>
                                        <p class="ia-note" id="iaSamplesLimited" hidden>
                                            <?= _htmlTranslate('Showing the most recent 5,000 samples.'); ?>
                                        </p>
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-striped" id="iaSamplesTable">
                                                <thead>
                                                    <tr>
                                                        <th><?= _htmlTranslate('Sample ID'); ?></th>
                                                        <th><?= _htmlTranslate('Batch Code'); ?></th>
                                                        <th><?= _htmlTranslate('Tested On'); ?></th>
                                                        <th><?= _htmlTranslate('Result'); ?></th>
                                                        <th class="num"><?= _htmlTranslate('Runs'); ?></th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                        <div role="tabpanel" class="tab-pane <?= empty($instrumentTests) ? 'active' : ''; ?>"
                            id="tab-events">
                    <div class="box-body">
                        <p class="text-muted" id="interface-activity-description">
                            <?= _htmlTranslate(
                                'What the Interface Tool recorded about its instruments: '
                                . 'connection attempts, connection failures and application starts.'
                            ); ?>
                        </p>

                        <div class="ifa-summary">
                            <div class="ifa-card">
                                <div class="ifa-card-label"><?= _htmlTranslate('Events (last 7 days)'); ?></div>
                                <div class="ifa-card-value">
                                    <?= number_format((int) ($summary['total_events'] ?? 0)); ?>
                                </div>
                            </div>
                            <div class="ifa-card <?= (int) ($summary['failures'] ?? 0) > 0 ? 'is-alert' : ''; ?>">
                                <div class="ifa-card-label"><?= _htmlTranslate('Failures (last 7 days)'); ?></div>
                                <div class="ifa-card-value">
                                    <?= number_format((int) ($summary['failures'] ?? 0)); ?>
                                </div>
                            </div>
                            <div class="ifa-card">
                                <div class="ifa-card-label"><?= _htmlTranslate('Instruments reporting'); ?></div>
                                <div class="ifa-card-value">
                                    <?= number_format((int) ($summary['instruments'] ?? 0)); ?>
                                </div>
                            </div>
                            <div class="ifa-card">
                                <div class="ifa-card-label"><?= _htmlTranslate('Last event'); ?></div>
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
                                        : _htmlTranslate('No activity yet'); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <table aria-describedby="interface-activity-description" class="table pageFilters"
                        aria-hidden="true" cellspacing="3" style="margin-left:1%;margin-top:5px;width:98%;">
                        <tr>
                            <td><strong><?= _htmlTranslate('Date Range'); ?>&nbsp;:</strong></td>
                            <td>
                                <input type="text" id="dateRange" name="dateRange"
                                    class="form-control daterangefield" style="width:100%;max-width:260px;" />
                            </td>
                            <td><strong><?= _htmlTranslate('Event'); ?>&nbsp;:</strong></td>
                            <td>
                                <select id="eventType" name="eventType" class="form-control"
                                    style="width:100%;max-width:260px;">
                                    <option value=""><?= _htmlTranslate('-- All --'); ?></option>
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
                            <td><strong><?= _htmlTranslate('Outcome'); ?>&nbsp;:</strong></td>
                            <td>
                                <select id="outcome" name="outcome" class="form-control"
                                    style="width:100%;max-width:180px;">
                                    <option value=""><?= _htmlTranslate('-- All --'); ?></option>
                                    <option value="failed"><?= _htmlTranslate('Failed'); ?></option>
                                    <option value="success"><?= _htmlTranslate('Success'); ?></option>
                                    <option value="started"><?= _htmlTranslate('Started'); ?></option>
                                </select>
                            </td>
                            <td><strong><?= _htmlTranslate('Instrument'); ?>&nbsp;:</strong></td>
                            <td>
                                <input type="text" id="instrument" name="instrument" class="form-control"
                                    placeholder="<?= _htmlTranslate('Instrument name'); ?>"
                                    style="width:100%;max-width:220px;" />
                            </td>
                            <td>
                                <button onclick="oTable.fnDraw();" class="btn btn-primary btn-sm">
                                    <span><?= _htmlTranslate("Search"); ?></span>
                                </button>
                                <button
                                    onclick="$('#dateRange,#instrument').val('');$('#eventType,#outcome').val('').trigger('change');oTable.fnDraw();"
                                    class="btn btn-default btn-sm">
                                    <span><?= _htmlTranslate("Reset"); ?></span>
                                </button>
                                <button onclick="iaExport('events', 'xlsx');" class="btn btn-success btn-sm">
                                    <em class="fa-solid fa-file-excel"></em>
                                    <?= _htmlTranslate("Export to Excel"); ?>
                                </button>
                            </td>
                        </tr>
                    </table>

                    <div class="box-body">
                        <table aria-describedby="interface-activity-description" id="interfaceActivityTable"
                            class="table table-bordered table-striped" aria-hidden="true">
                            <thead>
                                <tr>
                                    <th><?= _htmlTranslate("Occurred On"); ?></th>
                                    <th><?= _htmlTranslate("Lab"); ?></th>
                                    <th><?= _htmlTranslate("Instrument"); ?></th>
                                    <th><?= _htmlTranslate("Event"); ?></th>
                                    <th><?= _htmlTranslate("Outcome"); ?></th>
                                    <th><?= _htmlTranslate("Failure Code"); ?></th>
                                    <th><?= _htmlTranslate("Protocol / Mode"); ?></th>
                                    <th><?= _htmlTranslate("App Version"); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="8" class="dataTables_empty">
                                        <?= _htmlTranslate("Loading data from server"); ?>
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
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="<?= _asset('/assets/plugins/daterangepicker/daterangepicker.js') ?>"></script>
<script type="text/javascript">
    var oTable = null;
    $(document).ready(function () {
        $('#dateRange').daterangepicker({
            locale: {
                cancelLabel: "<?= _jsTranslate("Clear"); ?>",
                format: 'DD-MMM-YYYY',
                separator: ' to ',
            },
            startDate: moment().subtract(7, 'days'),
            endDate: moment(),
            maxDate: moment(),
            ranges: {
                "<?= _jsTranslate('Today'); ?>": [moment(), moment()],
                "<?= _jsTranslate('Yesterday'); ?>": [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                "<?= _jsTranslate('Last 7 Days'); ?>": [moment().subtract(6, 'days'), moment()],
                "<?= _jsTranslate('Last 30 Days'); ?>": [moment().subtract(29, 'days'), moment()],
                "<?= _jsTranslate('This Month'); ?>": [moment().startOf('month'), moment().endOf('month')],
                "<?= _jsTranslate('Last Month'); ?>": [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')]
            }
        });

        // The events grid loads the first time its tab is opened.
        $('#iaTabs a[href="#tab-events"]').on('shown.bs.tab', function () {
            loadInterfaceActivity();
        });

        if ($('#tab-tests').length) {
            $('#testsDateRange').daterangepicker({
                locale: {
                    cancelLabel: "<?= _jsTranslate("Clear"); ?>",
                    format: 'DD-MMM-YYYY',
                    separator: ' to ',
                },
                showDropdowns: true,
                startDate: moment().subtract(2, 'months').startOf('month'),
                endDate: moment(),
                maxDate: moment(),
                ranges: {
                    "<?= _jsTranslate('This Month'); ?>": [moment().startOf('month'), moment()],
                    "<?= _jsTranslate('Last Month'); ?>": [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                    "<?= _jsTranslate('Last 3 Months'); ?>": [moment().subtract(2, 'months').startOf('month'), moment()],
                    "<?= _jsTranslate('This Quarter'); ?>": [moment().startOf('quarter'), moment()],
                    "<?= _jsTranslate('Last Quarter'); ?>": [moment().subtract(1, 'quarter').startOf('quarter'), moment().subtract(1, 'quarter').endOf('quarter')],
                    "<?= _jsTranslate('This Year'); ?>": [moment().startOf('year'), moment()],
                    "<?= _jsTranslate('Last Year'); ?>": [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')]
                }
            });
            if ($('#testsLabId').length) {
                $('#testsLabId').select2({ allowClear: true, placeholder: "<?= _jsTranslate('-- All Labs --'); ?>" });
            }
            $('#testsTestType').on('change', iaLoadTests);
            iaLoadTests();
        } else {
            loadInterfaceActivity();
        }
    });

    var IA_NOT_RECORDED = "<?= _jsTranslate('Not recorded'); ?>";

    function iaTestsFilters() {
        return {
            testType: $('#testsTestType').val(),
            dateRange: $('#testsDateRange').val(),
            labId: $('#testsLabId').length ? ($('#testsLabId').val() || '') : ''
        };
    }

    function iaEventsFilters() {
        return {
            dateRange: $('#dateRange').val(),
            eventType: $('#eventType').val(),
            outcome: $('#outcome').val(),
            instrument: $('#instrument').val(),
            search: oTable ? oTable.fnSettings().oPreviousSearch.sSearch : ''
        };
    }

    function iaEscape(value) {
        return $('<div>').text(value === null || value === undefined ? '' : String(value)).html();
    }

    function iaNumber(value) {
        return Number(value || 0).toLocaleString();
    }

    // A rate is recomputed from the counts it covers, never averaged.
    function iaRate(failed, tested) {
        return tested > 0 ? (Math.round(failed * 10000 / tested) / 100).toFixed(2) : '-';
    }

    // The filters the figures on screen were loaded with: the sample lists and the
    // export follow them, not filters changed since without a new Search. Each
    // search is numbered so only the latest one's figures and filters are kept.
    var iaTestsShown = null;
    var iaTestsRequest = 0;

    function iaLoadTests() {
        var request = ++iaTestsRequest;
        var filters = iaTestsFilters();
        $('#testsByTypeTable tbody').html(
            '<tr><td colspan="5" class="dataTables_empty"><?= _jsTranslate("Loading data from server"); ?></td></tr>'
        );
        // A list opened from the earlier figures no longer matches them.
        iaSamplesRequest++;
        $('#iaSamples').prop('hidden', true);
        $.post('/reports/get-instrument-test-volumes.php', filters, null, 'json')
            .done(function (data) {
                if (request !== iaTestsRequest) {
                    return;
                }
                iaTestsShown = filters;
                if (!data || data.error) {
                    iaRenderTests([], (data && data.error) || "<?= _jsTranslate('Unable to load the instrument figures'); ?>");
                    return;
                }
                iaRenderTests(data.rows || [], null);
            })
            .fail(function () {
                if (request === iaTestsRequest) {
                    iaTestsShown = filters;
                    iaRenderTests([], "<?= _jsTranslate('Unable to load the instrument figures'); ?>");
                }
            });
    }

    function iaRenderTests(rows, error) {
        var byType = {};
        var total = { tested: 0, samples: 0, failed: 0 };

        rows.forEach(function (r) {
            var t = byType[r.instrumentType] = byType[r.instrumentType] || { tested: 0, samples: 0, failed: 0 };
            t.tested += r.tested;
            t.samples += r.samples;
            t.failed += r.failed;
            total.tested += r.tested;
            total.samples += r.samples;
            total.failed += r.failed;
        });

        var typeRows = '';
        Object.keys(byType).sort(function (a, b) { return byType[b].tested - byType[a].tested; }).forEach(function (type) {
            var t = byType[type];
            typeRows += '<tr><td>' + iaEscape(type) + '</td><td class="num">' + iaNumber(t.tested) + '</td>'
                + '<td class="num">' + iaNumber(t.samples) + '</td>'
                + '<td class="num">' + iaNumber(t.failed) + '</td><td class="num">' + iaRate(t.failed, t.tested) + '</td></tr>';
        });
        if (Object.keys(byType).length > 1) {
            typeRows += '<tr class="ia-total"><td>' + iaEscape("<?= _jsTranslate('Total'); ?>") + '</td>'
                + '<td class="num">' + iaNumber(total.tested) + '</td><td class="num">' + iaNumber(total.samples) + '</td>'
                + '<td class="num">' + iaNumber(total.failed) + '</td>'
                + '<td class="num">' + iaRate(total.failed, total.tested) + '</td></tr>';
        }
        var message = error || "<?= _jsTranslate('No tests in the selected range'); ?>";
        $('#testsByTypeTable tbody').html(rows.length ? typeRows
            : '<tr><td colspan="5" class="dataTables_empty">' + iaEscape(message) + '</td></tr>');

        iaRenderDetail(rows.map(function (r) {
            return [
                r.lab, r.instrumentLabel || IA_NOT_RECORDED, r.assay || IA_NOT_RECORDED,
                r.tested, r.failed, r.tested > 0 ? r.failed * 100 / r.tested : null,
                r.samples, r.validFirstTime, r.validAfterRetest, r.stillFailed,
                // Not shown: the row's keys, for its sample lists.
                { labId: r.labId, instrument: r.instrument, assay: r.assay }
            ];
        }), message);
    }

    var iaDetailTable = null;

    // Sortable, filterable by column, with a total over the rows left showing. The
    // rows are already on the page, so all of it runs here, without a server call.
    function iaRenderDetail(data, emptyMessage) {
        if (iaDetailTable === null) {
            var text = function (d, type) { return type === 'display' ? iaEscape(d) : d; };
            var count = function (d, type) { return type === 'display' ? iaNumber(d) : d; };
            // A sample count opens the list of those samples.
            var samples = function (outcome) {
                return function (d, type) {
                    if (type !== 'display' || !d) {
                        return type === 'display' ? iaNumber(d) : d;
                    }
                    return '<a href="#" class="ia-samples" data-outcome="' + outcome + '">' + iaNumber(d) + '</a>';
                };
            };
            iaDetailTable = $('#testsDetailTable').DataTable({
                data: data,
                dom: 'rtip',
                pageLength: 50,
                orderCellsTop: true,
                order: [[0, 'asc'], [3, 'desc']],
                columns: [
                    { render: text }, { render: text }, { render: text },
                    { render: count, className: 'num' },
                    { render: count, className: 'num' },
                    {
                        className: 'num',
                        render: function (d, type) {
                            return type === 'display' ? (d === null ? '-' : d.toFixed(2)) : (d === null ? -1 : d);
                        }
                    },
                    { render: samples(''), className: 'num' },
                    { render: samples('valid_first_time'), className: 'num' },
                    { render: samples('valid_after_retest'), className: 'num' },
                    { render: samples('still_failed'), className: 'num' }
                ],
                footerCallback: function () {
                    var api = this.api();
                    var sum = function (column) {
                        return api.column(column, { search: 'applied' }).data()
                            .reduce(function (a, b) { return a + b; }, 0);
                    };
                    var tested = sum(3);
                    var failed = sum(4);
                    var cells = $(api.table().footer()).find('td.num');
                    cells.eq(0).text(iaNumber(tested));
                    cells.eq(1).text(iaNumber(failed));
                    cells.eq(2).text(iaRate(failed, tested));
                    // Each sample sits on one row only, so these add up across rows.
                    [6, 7, 8, 9].forEach(function (column, i) {
                        cells.eq(3 + i).text(iaNumber(sum(column)));
                    });
                }
            });

            $('#testsDetailTable tbody').on('click', 'a.ia-samples', function (e) {
                e.preventDefault();
                var row = iaDetailTable.row($(this).closest('tr')).data();
                iaLoadSamples(row, $(this).data('outcome'));
            });

            $('#testsDetailTable .ia-col-filter').on('change', function () {
                var value = $(this).val();
                iaDetailTable.column($(this).data('column'))
                    .search(value ? '^' + $.fn.dataTable.util.escapeRegex(value) + '$' : '', true, false);
                iaCascadeFilters();
                iaDetailTable.draw();
            });
        } else {
            iaDetailTable.clear().rows.add(data);
        }

        // A new load starts every filter at All.
        $('#testsDetailTable .ia-col-filter').each(function () {
            $(this).val('');
            iaDetailTable.column($(this).data('column')).search('');
        });
        iaCascadeFilters();

        iaDetailTable.draw();
        $('#testsDetailTable .dataTables_empty').text(emptyMessage);
    }

    var IA_OUTCOMES = {
        '': "<?= _jsTranslate('Samples'); ?>",
        valid_first_time: "<?= _jsTranslate('Valid First Time'); ?>",
        valid_after_retest: "<?= _jsTranslate('Valid After Re-test'); ?>",
        still_failed: "<?= _jsTranslate('Still Failed'); ?>"
    };
    var iaSamplesTable = null;
    // Each list request is numbered, so a reply to one the user has since replaced
    // by another click is dropped rather than mixed into the newer list.
    var iaSamplesRequest = 0;

    function iaLoadSamples(row, outcome) {
        var request = ++iaSamplesRequest;
        var keys = row[10];
        $('#iaSamplesTitle').text(IA_OUTCOMES[outcome] + ': ' + [row[0], row[1], row[2]].join(' / '));
        $('#iaSamplesLimited').prop('hidden', true);
        $('#iaSamples').prop('hidden', false);
        if (iaSamplesTable === null) {
            var text = function (d, type) { return type === 'display' ? iaEscape(d) : d; };
            iaSamplesTable = $('#iaSamplesTable').DataTable({
                data: [],
                dom: 'frtip',
                pageLength: 25,
                order: [[2, 'desc']],
                columns: [
                    { render: text }, { render: text },
                    {
                        render: function (d, type) {
                            return type === 'display' ? iaEscape(d.display) : d.sort;
                        }
                    },
                    { render: text }, { className: 'num' }
                ]
            });
        }
        iaSamplesTable.clear().draw();
        $('#iaSamplesTable .dataTables_empty').text("<?= _jsTranslate('Loading data from server'); ?>");
        $('html, body').animate({ scrollTop: $('#iaSamples').offset().top - 60 }, 200);

        var data = $.extend({}, iaTestsShown, {
            rowLabId: keys.labId === null ? '' : keys.labId,
            instrument: keys.instrument,
            assay: keys.assay,
            outcome: outcome
        });
        $.post('/reports/get-instrument-samples.php', data, null, 'json')
            .done(function (response) {
                if (request !== iaSamplesRequest) {
                    return;
                }
                if (!response || response.error) {
                    $('#iaSamplesTable .dataTables_empty').text(
                        (response && response.error) || "<?= _jsTranslate('Unable to load the samples'); ?>"
                    );
                    return;
                }
                iaSamplesTable.rows.add(response.samples.map(function (sample) {
                    return [
                        sample.sampleCode, sample.batchCode || '-',
                        { display: sample.testedOnDisplay, sort: sample.testedOn },
                        sample.result || '-', sample.runs
                    ];
                })).draw();
                $('#iaSamplesLimited').prop('hidden', !response.limited);
            })
            .fail(function () {
                if (request === iaSamplesRequest) {
                    $('#iaSamplesTable .dataTables_empty').text("<?= _jsTranslate('Unable to load the samples'); ?>");
                }
            });
    }

    $(document).on('click', '#iaSamplesClose', function () {
        iaSamplesRequest++;
        $('#iaSamples').prop('hidden', true);
    });

    // Cascading: each filter offers only the values found in the rows the OTHER
    // filters leave, so picking a lab narrows Instrument and Assay to that lab's.
    function iaCascadeFilters() {
        var selects = $('#testsDetailTable .ia-col-filter');
        var chosen = selects.map(function () { return $(this).val(); }).get();
        var rows = iaDetailTable.rows().data().toArray();

        selects.each(function (index) {
            var select = $(this);
            var column = select.data('column');
            var values = {};
            rows.forEach(function (row) {
                var matchesOthers = selects.toArray().every(function (other, j) {
                    return j === index || chosen[j] === '' || row[$(other).data('column')] === chosen[j];
                });
                if (matchesOthers) {
                    values[row[column]] = true;
                }
            });
            // The current choice stays offered, so it never vanishes from under the user.
            if (chosen[index] !== '') {
                values[chosen[index]] = true;
            }
            select.find('option:not(:first)').remove();
            Object.keys(values).sort().forEach(function (value) {
                select.append($('<option>').val(value).text(value));
            });
            select.val(chosen[index]);
        });
    }

    function iaExport(section, format) {
        var data = $.extend({ section: section, format: format },
            section === 'tests' ? (iaTestsShown || iaTestsFilters()) : iaEventsFilters());
        if (section === 'tests') {
            // The column filters too, so the file holds the rows on screen.
            data.columnFilters = $('#testsDetailTable .ia-col-filter').map(function () {
                return $(this).val();
            }).get();
        }
        // Runs in the background; progress shows in the navbar Exports menu and the
        // file downloads when ready, even if the user has moved to another page.
        IntelisExport.start('/reports/export-instrument-activity.php', data, section === 'tests'
            ? "<?= _jsTranslate('Tests by Instrument'); ?>"
            : "<?= _jsTranslate('Interface Tool Events'); ?>");
    }

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
            "sAjaxSource": "/reports/get-interface-machine-activity.php",
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
