<?php

use App\Services\TestsService;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

$title = _translate("Lab Performance Indicators");
require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

// recency shares form_vl with VL, so a selector entry of its own would only
// repeat the VL numbers.
$selectableTests = array_values(array_diff(TestsService::getActiveTests(), ['recency']));

// Custom tests appear individually so an operator can look at one test's
// indicators, not just the module as a whole.
$customTests = [];
if (TestsService::isTestActive('generic-tests')) {
    $customTests = $db->rawQuery(
        "SELECT test_type_id, test_standard_name
           FROM r_test_types
          WHERE test_status = 'active'
          ORDER BY test_standard_name ASC"
    ) ?: [];
}

$testingLabs = $facilitiesService->getTestingLabs();
?>
<style>
    #lpiReport .lpi-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin: 0 0 18px;
    }

    #lpiReport .lpi-card {
        flex: 1 1 160px;
        padding: 12px 15px;
        background-color: #f8fafb;
        border: 1px solid #e4e8ec;
        border-left: 3px solid #3c8dbc;
        border-radius: 3px;
    }

    #lpiReport .lpi-card.is-alert {
        border-left-color: #c0392b;
    }

    #lpiReport .lpi-card-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #8a9299;
    }

    #lpiReport .lpi-card-value {
        font-size: 22px;
        font-weight: 700;
        color: #444;
        line-height: 1.3;
    }

    .lpi-pill {
        display: inline-block;
        padding: 3px 9px;
        font-size: 11px;
        font-weight: 600;
        line-height: 1.5;
        border-radius: 11px;
    }

    .lpi-pill-warn {
        color: #8a6d1a;
        background-color: #fdf6e3;
        border: 1px solid #f0e0b0;
    }

    .lpi-pill-muted {
        color: #5a6570;
        background-color: #eef1f4;
        border: 1px solid #e0e5ea;
    }

    #lpiReport .nav-tabs>li>a {
        font-weight: 600;
    }

    #lpiReport .tab-pane {
        padding-top: 15px;
    }

    #lpiReport .lpi-chart {
        min-height: 320px;
        margin-bottom: 18px;
    }

    #lpiReport .lpi-toolbar {
        margin: 0 0 10px;
        text-align: right;
    }

    #lpiReport .lpi-toolbar .btn-group {
        position: relative;
    }

    #lpiReport .lpi-toolbar .dropdown-menu {
        right: 0;
        left: auto;
    }

    #lpiReport .lpi-note {
        font-size: 12px;
        color: #8a9299;
        margin: 4px 0 12px;
    }

    #lpiReport .lpi-methodology {
        background-color: #f8fafb;
        border: 1px solid #e4e8ec;
        border-radius: 3px;
        padding: 12px 16px;
        margin: 0 0 15px;
        font-size: 12.5px;
        max-width: 980px;
    }

    #lpiReport .lpi-methodology dt {
        margin-top: 10px;
        color: #444;
    }

    #lpiReport .lpi-methodology dt:first-child {
        margin-top: 0;
    }

    #lpiReport .lpi-methodology dd {
        color: #5a6570;
        margin-left: 0;
    }

    #lpiReport .lpi-methodology ul {
        margin: 6px 0;
        padding-left: 18px;
    }

    #lpiReport table.lpi-table th {
        background-color: #f4f6f8;
    }

    th {
        display: revert !important;
    }
</style>
<div class="content-wrapper" id="lpiReport">
    <section class="content-header">
        <h1><em class="fa-solid fa-gauge-high"></em>
            <?php echo _htmlTranslate("Lab Performance Indicators"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
                    <?php echo _htmlTranslate("Home"); ?>
                </a></li>
            <li class="active">
                <?php echo _htmlTranslate("Lab Performance Indicators"); ?>
            </li>
        </ol>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-body">
                        <p class="text-muted" id="lpi-description">
                            <?= _htmlTranslate('Turnaround time, testing volume by entry mode, failure and rejection rates, and repeat patient results for the selected test and period.'); ?>
                            <a href="javascript:void(0);" onclick="$('#lpiMethodology').slideToggle(150);">
                                <em class="fa-solid fa-circle-info"></em>
                                <?= _htmlTranslate('How are these numbers calculated?'); ?>
                            </a>
                        </p>

                        <div id="lpiMethodology" class="lpi-methodology" style="display:none;">
                            <dl>
                                <dt><?= _htmlTranslate('Reporting period'); ?></dt>
                                <dd><?= _htmlTranslate('Every sample is placed in a month, quarter or year using the sample collection date. When the collection date is missing, the date the request was created is used instead.'); ?></dd>

                                <dt><?= _htmlTranslate('Turnaround Time (TAT)'); ?></dt>
                                <dd>
                                    <?= _htmlTranslate('Each stage is the average number of days between two recorded timestamps.'); ?>
                                    <ul>
                                        <li><strong><?= _htmlTranslate('Collection to Lab Receipt'); ?></strong>:
                                            <?= _htmlTranslate('from the sample collection date to the date the lab received the sample.'); ?></li>
                                        <li><strong><?= _htmlTranslate('Lab Receipt to Tested'); ?></strong>:
                                            <?= _htmlTranslate('from lab receipt to the date the test was performed.'); ?></li>
                                        <li><strong><?= _htmlTranslate('Tested to Result Released'); ?></strong>:
                                            <?= _htmlTranslate('from the test date to the result dispatch date, or the result print date when no dispatch date is recorded.'); ?></li>
                                        <li><strong><?= _htmlTranslate('Collection to Result Released'); ?></strong>:
                                            <?= _htmlTranslate('the full journey from sample collection to result release.'); ?></li>
                                    </ul>
                                    <?= _htmlTranslate('A sample is counted in a stage only when both timestamps exist and are in the correct order. The n value next to each average is the number of samples counted for that stage.'); ?>
                                </dd>

                                <dt><?= _htmlTranslate('Testing Volume'); ?></dt>
                                <dd><?= _htmlTranslate('Registered counts all samples in the period. Resulted counts samples that have a result. Each result is classified by how it reached the system: Manual Entry means it was typed in, Analyzer Interface means it was received directly from the instrument, and File Import means it was uploaded from a result file. Results saved before this tracking existed are shown as Unclassified.'); ?></dd>

                                <dt><?= _htmlTranslate('Failure Rate'); ?></dt>
                                <dd><?= _htmlTranslate('Failed tests divided by samples with an outcome, meaning a result or a recorded test failure. Samples that are still pending, or were rejected before testing, are not part of this rate.'); ?></dd>

                                <dt><?= _htmlTranslate('Rejection Rate'); ?></dt>
                                <dd><?= _htmlTranslate('Rejected samples divided by all samples registered in the period.'); ?></dd>

                                <dt><?= _htmlTranslate('Repeat Patients'); ?></dt>
                                <dd><?= _htmlTranslate('Samples are linked into one patient using the patient identifier on the request. A result change is flagged when the linked samples do not all carry the same result. Samples without an identifier cannot be linked, so the identifier coverage is shown next to these numbers.'); ?></dd>
                            </dl>
                        </div>

                        <table aria-describedby="lpi-description" class="table pageFilters" aria-hidden="true"
                            cellspacing="3" style="margin-left:1%;margin-top:5px;width:98%;">
                            <tr>
                                <td><strong><?= _htmlTranslate('Test'); ?>&nbsp;:</strong></td>
                                <td>
                                    <select id="testType" class="form-control" style="width:100%;max-width:280px;">
                                        <option value="all"><?= _htmlTranslate('All Tests (Overview)'); ?></option>
                                        <?php foreach ($selectableTests as $testKey) { ?>
                                            <option value="<?= htmlspecialchars($testKey, ENT_QUOTES); ?>">
                                                <?= htmlspecialchars(TestsService::getTestName($testKey), ENT_QUOTES); ?>
                                            </option>
                                        <?php } ?>
                                        <?php if (!empty($customTests)) { ?>
                                            <optgroup label="<?= _htmlTranslate('Custom Tests'); ?>">
                                                <?php foreach ($customTests as $customTest) { ?>
                                                    <option value="generic-tests:<?= (int) $customTest['test_type_id']; ?>">
                                                        <?= htmlspecialchars((string) $customTest['test_standard_name'], ENT_QUOTES); ?>
                                                    </option>
                                                <?php } ?>
                                            </optgroup>
                                        <?php } ?>
                                    </select>
                                </td>
                                <td><strong><?= _htmlTranslate('Date Range'); ?>&nbsp;:</strong></td>
                                <td>
                                    <input type="text" id="dateRange" class="form-control daterangefield"
                                        style="width:100%;max-width:240px;" />
                                </td>
                                <td><strong><?= _htmlTranslate('View By'); ?>&nbsp;:</strong></td>
                                <td>
                                    <select id="grouping" class="form-control" style="width:100%;max-width:150px;">
                                        <option value="monthly"><?= _htmlTranslate('Monthly'); ?></option>
                                        <option value="quarterly"><?= _htmlTranslate('Quarterly'); ?></option>
                                        <option value="yearly"><?= _htmlTranslate('Yearly'); ?></option>
                                    </select>
                                </td>
                                <?php if (!empty($testingLabs)) { ?>
                                    <td><strong><?= _htmlTranslate('Lab'); ?>&nbsp;:</strong></td>
                                    <td>
                                        <select id="labId" class="form-control" style="width:100%;max-width:240px;">
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
                                    <button onclick="lpiApplyFilters();" class="btn btn-primary btn-sm">
                                        <span><?= _htmlTranslate("Apply"); ?></span>
                                    </button>
                                </td>
                            </tr>
                        </table>

                        <div class="lpi-summary">
                            <div class="lpi-card">
                                <div class="lpi-card-label"><?= _htmlTranslate('Samples registered'); ?></div>
                                <div class="lpi-card-value" id="cardRegistered">--</div>
                            </div>
                            <div class="lpi-card">
                                <div class="lpi-card-label"><?= _htmlTranslate('Results available'); ?></div>
                                <div class="lpi-card-value" id="cardResulted">--</div>
                            </div>
                            <div class="lpi-card" id="cardFailureWrap">
                                <div class="lpi-card-label"><?= _htmlTranslate('Failure rate'); ?></div>
                                <div class="lpi-card-value" id="cardFailure">--</div>
                            </div>
                            <div class="lpi-card" id="cardRejectionWrap">
                                <div class="lpi-card-label"><?= _htmlTranslate('Rejection rate'); ?></div>
                                <div class="lpi-card-value" id="cardRejection">--</div>
                            </div>
                        </div>

                        <div class="lpi-toolbar">
                            <div class="btn-group">
                                <button type="button" class="btn btn-default btn-sm dropdown-toggle"
                                    data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <em class="fa-solid fa-download"></em>
                                    <?= _htmlTranslate('Export'); ?> <span class="caret"></span>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-right">
                                    <li><a href="javascript:void(0);" onclick="lpiExport('csv');">
                                            <?= _htmlTranslate('Current view as CSV'); ?></a></li>
                                    <li><a href="javascript:void(0);" onclick="lpiExport('xlsx');">
                                            <?= _htmlTranslate('Current view as Excel'); ?></a></li>
                                    <li><a href="javascript:void(0);" onclick="lpiExport('json');">
                                            <?= _htmlTranslate('Current view as JSON'); ?></a></li>
                                    <li role="separator" class="divider"></li>
                                    <li><a href="javascript:void(0);" onclick="lpiExportAll();">
                                            <?= _htmlTranslate('All indicators as JSON'); ?></a></li>
                                </ul>
                            </div>
                        </div>

                        <ul class="nav nav-tabs" id="lpiTabs" role="tablist">
                            <li role="presentation" class="lpi-tab-all"><a href="#tab-overview" data-section="overview"
                                    role="tab" data-toggle="tab"><?= _htmlTranslate('Overview'); ?></a></li>
                            <li role="presentation" class="lpi-tab-single"><a href="#tab-tat" data-section="tat"
                                    role="tab" data-toggle="tab"><?= _htmlTranslate('Turnaround Time'); ?></a></li>
                            <li role="presentation" class="lpi-tab-single"><a href="#tab-volume" data-section="volume"
                                    role="tab" data-toggle="tab"><?= _htmlTranslate('Testing Volume'); ?></a></li>
                            <li role="presentation" class="lpi-tab-single"><a href="#tab-failure" data-section="failure"
                                    role="tab" data-toggle="tab"><?= _htmlTranslate('Failures'); ?></a></li>
                            <li role="presentation" class="lpi-tab-single"><a href="#tab-rejection"
                                    data-section="rejection" role="tab" data-toggle="tab"><?= _htmlTranslate('Rejections'); ?></a>
                            </li>
                            <li role="presentation" class="lpi-tab-single"><a href="#tab-patients"
                                    data-section="patients" role="tab" data-toggle="tab"><?= _htmlTranslate('Repeat Patients'); ?></a>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div role="tabpanel" class="tab-pane" id="tab-overview">
                                <div class="lpi-chart" id="chartOverview"></div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped lpi-table" id="tableOverview"
                                        aria-describedby="lpi-description"></table>
                                </div>
                            </div>

                            <div role="tabpanel" class="tab-pane" id="tab-tat">
                                <p class="lpi-note">
                                    <?= _htmlTranslate('Average number of days between milestones. Samples missing a milestone, or with dates recorded out of order, are left out of that stage.'); ?>
                                </p>
                                <div class="lpi-chart" id="chartTat"></div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped lpi-table" id="tableTat"
                                        aria-describedby="lpi-description"></table>
                                </div>
                            </div>

                            <div role="tabpanel" class="tab-pane" id="tab-volume">
                                <p class="lpi-note">
                                    <?= _htmlTranslate('How each result reached the system. Results recorded before entry tracking existed appear as Unclassified.'); ?>
                                </p>
                                <div class="lpi-chart" id="chartVolume"></div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped lpi-table" id="tableVolume"
                                        aria-describedby="lpi-description"></table>
                                </div>
                            </div>

                            <div role="tabpanel" class="tab-pane" id="tab-failure">
                                <div class="lpi-chart" id="chartFailure"></div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped lpi-table" id="tableFailure"
                                        aria-describedby="lpi-description"></table>
                                </div>
                                <div id="failureReasonsWrap" style="display:none;">
                                    <h4><?= _htmlTranslate('Failure Reasons'); ?></h4>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped lpi-table"
                                            id="tableFailureReasons" aria-describedby="lpi-description"></table>
                                    </div>
                                </div>
                            </div>

                            <div role="tabpanel" class="tab-pane" id="tab-rejection">
                                <div class="lpi-chart" id="chartRejection"></div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped lpi-table" id="tableRejection"
                                        aria-describedby="lpi-description"></table>
                                </div>
                                <div id="rejectionReasonsWrap" style="display:none;">
                                    <h4><?= _htmlTranslate('Rejection Reasons'); ?></h4>
                                    <div class="lpi-chart" id="chartRejectionReasons" style="min-height:260px;"></div>
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped lpi-table"
                                            id="tableRejectionReasons" aria-describedby="lpi-description"></table>
                                    </div>
                                </div>
                            </div>

                            <div role="tabpanel" class="tab-pane" id="tab-patients">
                                <p class="lpi-note" id="patientsCoverageNote"></p>
                                <div class="lpi-summary">
                                    <div class="lpi-card">
                                        <div class="lpi-card-label"><?= _htmlTranslate('Patients with repeat tests'); ?></div>
                                        <div class="lpi-card-value" id="cardRepeatPatients">--</div>
                                    </div>
                                    <div class="lpi-card">
                                        <div class="lpi-card-label"><?= _htmlTranslate('Patients with a result change'); ?></div>
                                        <div class="lpi-card-value" id="cardChangedPatients">--</div>
                                    </div>
                                    <div class="lpi-card">
                                        <div class="lpi-card-label"><?= _htmlTranslate('Samples with a patient identifier'); ?></div>
                                        <div class="lpi-card-value" id="cardIdentifierCoverage">--</div>
                                    </div>
                                </div>
                                <table class="table table-bordered table-striped" id="patientsTable"
                                    aria-describedby="lpi-description">
                                    <thead>
                                        <tr>
                                            <th><?= _htmlTranslate('Patient'); ?></th>
                                            <th><?= _htmlTranslate('Tests'); ?></th>
                                            <th><?= _htmlTranslate('First Result'); ?></th>
                                            <th><?= _htmlTranslate('Latest Result'); ?></th>
                                            <th><?= _htmlTranslate('Result Change'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="5" class="dataTables_empty">
                                                <?= _htmlTranslate("Loading data from server"); ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script type="text/javascript" src="/assets/js/highcharts.js"></script>
<script type="text/javascript">
    var lpiCache = {};
    var patientsTable = null;

    var LPI_LABELS = {
        manual: "<?= _jsTranslate('Manual Entry'); ?>",
        interface: "<?= _jsTranslate('Analyzer Interface'); ?>",
        fileImport: "<?= _jsTranslate('File Import'); ?>",
        unclassified: "<?= _jsTranslate('Unclassified'); ?>",
        registered: "<?= _jsTranslate('Registered'); ?>",
        resulted: "<?= _jsTranslate('Resulted'); ?>",
        period: "<?= _jsTranslate('Period'); ?>",
        lab: "<?= _jsTranslate('Lab'); ?>",
        test: "<?= _jsTranslate('Test'); ?>",
        tested: "<?= _jsTranslate('Tested'); ?>",
        failed: "<?= _jsTranslate('Failed'); ?>",
        failureRate: "<?= _jsTranslate('Failure Rate (%)'); ?>",
        received: "<?= _jsTranslate('Samples'); ?>",
        rejected: "<?= _jsTranslate('Rejected'); ?>",
        rejectionRate: "<?= _jsTranslate('Rejection Rate (%)'); ?>",
        reason: "<?= _jsTranslate('Reason'); ?>",
        total: "<?= _jsTranslate('Total'); ?>",
        samples: "<?= _jsTranslate('Samples'); ?>",
        days: "<?= _jsTranslate('days'); ?>",
        collectionToReceipt: "<?= _jsTranslate('Collection to Lab Receipt'); ?>",
        receiptToTested: "<?= _jsTranslate('Lab Receipt to Tested'); ?>",
        testedToReleased: "<?= _jsTranslate('Tested to Result Released'); ?>",
        collectionToReleased: "<?= _jsTranslate('Collection to Result Released'); ?>",
        noData: "<?= _jsTranslate('No data for the selected filters'); ?>",
        loading: "<?= _jsTranslate('Loading...'); ?>"
    };

    Highcharts.setOptions({
        colors: ['#3c8dbc', '#00a65a', '#f39c12', '#8a9299', '#c0392b', '#605ca8'],
        chart: { style: { fontFamily: 'inherit' } },
        title: { style: { fontSize: '14px', fontWeight: '600' } },
        credits: { enabled: false }
    });

    function lpiFilters() {
        var testValue = $('#testType').val() || 'all';
        var testType = testValue;
        var genericTestTypeId = 0;
        if (testValue.indexOf('generic-tests:') === 0) {
            testType = 'generic-tests';
            genericTestTypeId = parseInt(testValue.split(':')[1], 10) || 0;
        }
        return {
            testType: testType,
            genericTestTypeId: genericTestTypeId,
            dateRange: $('#dateRange').val(),
            grouping: $('#grouping').val(),
            labId: $('#labId').length ? ($('#labId').val() || '') : ''
        };
    }

    function lpiCacheKey(section) {
        return section + '|' + JSON.stringify(lpiFilters());
    }

    function lpiActiveSection() {
        return $('#lpiTabs li.active a').data('section');
    }

    function lpiApplyFilters() {
        lpiCache = {};
        lpiToggleTabs();
        lpiLoadSummary();
        lpiLoadSection(lpiActiveSection());
    }

    // All Tests shows only the overview; a single test shows everything else.
    function lpiToggleTabs() {
        var isAll = ($('#testType').val() === 'all');
        $('#lpiTabs .lpi-tab-all').toggle(isAll);
        $('#lpiTabs .lpi-tab-single').toggle(!isAll);
        var active = $('#lpiTabs li.active:visible');
        if (active.length === 0) {
            var first = $('#lpiTabs li:visible').first();
            first.find('a').tab('show');
        }
    }

    function lpiPost(section, extra, done) {
        var data = $.extend({ section: section }, lpiFilters(), extra || {});
        return $.ajax({
            url: '/admin/monitoring/get-lab-performance-indicators.php',
            type: 'POST',
            dataType: 'json',
            data: data,
            success: done
        });
    }

    function lpiLoadSection(section) {
        if (!section) { return; }
        if (section === 'patients') {
            lpiLoadPatients();
            return;
        }
        var key = lpiCacheKey(section);
        if (lpiCache[key]) {
            lpiRenderSection(section, lpiCache[key]);
            return;
        }
        lpiPost(section, null, function (json) {
            if (!json || json.error) { return; }
            lpiCache[key] = json;
            lpiRenderSection(section, json);
        });
    }

    function lpiRenderSection(section, json) {
        if (section === 'overview') { renderOverview(json.rows || []); }
        if (section === 'tat') { renderTat(json.rows || []); }
        if (section === 'volume') { renderVolume(json.rows || []); }
        if (section === 'failure') { renderFailure(json.rows || [], json.reasons || []); }
        if (section === 'rejection') { renderRejection(json.rows || [], json.reasons || []); }
    }

    // The headline cards always come from the same aggregates the overview
    // uses, so the numbers match across tabs.
    function lpiLoadSummary() {
        var f = lpiFilters();
        var section = 'overview';
        lpiPost(section, null, function (json) {
            var rows = (json && json.rows) || [];
            if (f.testType !== 'all') {
                rows = rows.filter(function (r) { return r.testKey === f.testType; });
                if (f.testType === 'generic-tests' && f.genericTestTypeId > 0) {
                    rows = rows.filter(function (r) { return r.genericTestTypeId === f.genericTestTypeId; });
                }
            }
            var registered = 0, resulted = 0, failed = 0, tested = 0, rejected = 0;
            rows.forEach(function (r) {
                registered += r.registered; resulted += r.resulted;
                failed += r.failed; rejected += r.rejected;
                tested += r.resulted + r.failed;
            });
            $('#cardRegistered').text(registered.toLocaleString());
            $('#cardResulted').text(resulted.toLocaleString());
            var failureRate = tested > 0 ? (failed * 100 / tested) : null;
            var rejectionRate = registered > 0 ? (rejected * 100 / registered) : null;
            $('#cardFailure').text(failureRate === null ? '--' : failureRate.toFixed(2) + '%');
            $('#cardRejection').text(rejectionRate === null ? '--' : rejectionRate.toFixed(2) + '%');
            $('#cardFailureWrap').toggleClass('is-alert', failureRate !== null && failureRate > 5);
            $('#cardRejectionWrap').toggleClass('is-alert', rejectionRate !== null && rejectionRate > 5);
        });
    }

    function esc(value) {
        return $('<span>').text(value === null || value === undefined ? '' : String(value)).html();
    }

    function buildTable(tableId, headers, rows) {
        var html = '<thead><tr>';
        headers.forEach(function (h) { html += '<th>' + esc(h) + '</th>'; });
        html += '</tr></thead><tbody>';
        if (rows.length === 0) {
            html += '<tr><td colspan="' + headers.length + '" class="text-center text-muted">'
                + esc(LPI_LABELS.noData) + '</td></tr>';
        }
        rows.forEach(function (row) {
            html += '<tr>';
            row.forEach(function (cell) {
                html += '<td>' + (cell === null || cell === undefined || cell === '' ? '-' : esc(cell)) + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody>';
        $('#' + tableId).html(html);
    }

    function sumByPeriod(rows, fields) {
        var periods = [];
        var byPeriod = {};
        rows.forEach(function (r) {
            if (!byPeriod[r.period]) {
                byPeriod[r.period] = {};
                fields.forEach(function (f) { byPeriod[r.period][f] = 0; });
                periods.push(r.period);
            }
            fields.forEach(function (f) { byPeriod[r.period][f] += (r[f] || 0); });
        });
        return { periods: periods, byPeriod: byPeriod };
    }

    function renderOverview(rows) {
        buildTable('tableOverview',
            [LPI_LABELS.test, LPI_LABELS.registered, LPI_LABELS.resulted, LPI_LABELS.manual,
            LPI_LABELS.interface, LPI_LABELS.fileImport, LPI_LABELS.unclassified,
            LPI_LABELS.failed, LPI_LABELS.failureRate, LPI_LABELS.rejected, LPI_LABELS.rejectionRate],
            rows.map(function (r) {
                return [r.testName, r.registered.toLocaleString(), r.resulted.toLocaleString(),
                r.manual.toLocaleString(), r.interface.toLocaleString(), r.fileImport.toLocaleString(),
                r.unclassified.toLocaleString(), r.failed.toLocaleString(),
                r.failureRate === null ? null : r.failureRate + '%',
                r.rejected.toLocaleString(),
                r.rejectionRate === null ? null : r.rejectionRate + '%'];
            }));

        Highcharts.chart('chartOverview', {
            chart: { type: 'column' },
            title: { text: "<?= _jsTranslate('Results by entry mode, per test'); ?>" },
            xAxis: { categories: rows.map(function (r) { return r.testName; }) },
            yAxis: { min: 0, title: { text: LPI_LABELS.resulted }, stackLabels: { enabled: true } },
            plotOptions: { column: { stacking: 'normal' } },
            series: [
                { name: LPI_LABELS.manual, data: rows.map(function (r) { return r.manual; }) },
                { name: LPI_LABELS.interface, data: rows.map(function (r) { return r.interface; }) },
                { name: LPI_LABELS.fileImport, data: rows.map(function (r) { return r.fileImport; }) },
                { name: LPI_LABELS.unclassified, data: rows.map(function (r) { return r.unclassified; }) }
            ]
        });
    }

    function renderTat(rows) {
        var stages = ['collectionToReceipt', 'receiptToTested', 'testedToReleased', 'collectionToReleased'];
        buildTable('tableTat',
            [LPI_LABELS.period, LPI_LABELS.samples,
            LPI_LABELS.collectionToReceipt, LPI_LABELS.receiptToTested,
            LPI_LABELS.testedToReleased, LPI_LABELS.collectionToReleased],
            rows.map(function (r) {
                return [r.period, r.samples.toLocaleString()].concat(stages.map(function (s) {
                    return r[s] === null ? null : r[s] + ' ' + LPI_LABELS.days + ' (n=' + r[s + 'N'].toLocaleString() + ')';
                }));
            }));

        Highcharts.chart('chartTat', {
            chart: { type: 'line' },
            title: { text: "<?= _jsTranslate('Average turnaround time in days'); ?>" },
            xAxis: { categories: rows.map(function (r) { return String(r.period); }) },
            yAxis: { min: 0, title: { text: LPI_LABELS.days } },
            series: stages.map(function (s) {
                return { name: LPI_LABELS[s], data: rows.map(function (r) { return r[s]; }) };
            })
        });
    }

    function renderVolume(rows) {
        buildTable('tableVolume',
            [LPI_LABELS.period, LPI_LABELS.lab, LPI_LABELS.registered, LPI_LABELS.resulted,
            LPI_LABELS.manual, LPI_LABELS.interface, LPI_LABELS.fileImport, LPI_LABELS.unclassified],
            rows.map(function (r) {
                return [r.period, r.lab, r.registered.toLocaleString(), r.resulted.toLocaleString(),
                r.manual.toLocaleString(), r.interface.toLocaleString(),
                r.fileImport.toLocaleString(), r.unclassified.toLocaleString()];
            }));

        var agg = sumByPeriod(rows, ['manual', 'interface', 'fileImport', 'unclassified']);
        Highcharts.chart('chartVolume', {
            chart: { type: 'column' },
            title: { text: "<?= _jsTranslate('Results by entry mode'); ?>" },
            xAxis: { categories: agg.periods },
            yAxis: { min: 0, title: { text: LPI_LABELS.resulted }, stackLabels: { enabled: true } },
            plotOptions: { column: { stacking: 'normal' } },
            series: ['manual', 'interface', 'fileImport', 'unclassified'].map(function (f) {
                return {
                    name: LPI_LABELS[f],
                    data: agg.periods.map(function (p) { return agg.byPeriod[p][f]; })
                };
            })
        });
    }

    function renderFailure(rows, reasons) {
        buildTable('tableFailure',
            [LPI_LABELS.period, LPI_LABELS.lab, LPI_LABELS.tested, LPI_LABELS.failed, LPI_LABELS.failureRate],
            rows.map(function (r) {
                return [r.period, r.lab, r.tested.toLocaleString(), r.failed.toLocaleString(),
                r.failureRate === null ? null : r.failureRate + '%'];
            }));

        var agg = sumByPeriod(rows, ['tested', 'failed']);
        Highcharts.chart('chartFailure', {
            title: { text: "<?= _jsTranslate('Failed tests and failure rate'); ?>" },
            xAxis: { categories: agg.periods },
            yAxis: [
                { min: 0, title: { text: LPI_LABELS.failed } },
                { min: 0, title: { text: LPI_LABELS.failureRate }, opposite: true }
            ],
            series: [
                {
                    type: 'column', name: LPI_LABELS.failed,
                    data: agg.periods.map(function (p) { return agg.byPeriod[p].failed; })
                },
                {
                    type: 'line', name: LPI_LABELS.failureRate, yAxis: 1,
                    data: agg.periods.map(function (p) {
                        var d = agg.byPeriod[p];
                        return d.tested > 0 ? Math.round(d.failed * 10000 / d.tested) / 100 : null;
                    })
                }
            ]
        });

        $('#failureReasonsWrap').toggle(reasons.length > 0);
        if (reasons.length > 0) {
            buildTable('tableFailureReasons', [LPI_LABELS.reason, LPI_LABELS.total],
                reasons.map(function (r) { return [r.reason, r.total.toLocaleString()]; }));
        }
    }

    function renderRejection(rows, reasons) {
        buildTable('tableRejection',
            [LPI_LABELS.period, LPI_LABELS.lab, LPI_LABELS.received, LPI_LABELS.rejected, LPI_LABELS.rejectionRate],
            rows.map(function (r) {
                return [r.period, r.lab, r.received.toLocaleString(), r.rejected.toLocaleString(),
                r.rejectionRate === null ? null : r.rejectionRate + '%'];
            }));

        var agg = sumByPeriod(rows, ['received', 'rejected']);
        Highcharts.chart('chartRejection', {
            title: { text: "<?= _jsTranslate('Rejected samples and rejection rate'); ?>" },
            xAxis: { categories: agg.periods },
            yAxis: [
                { min: 0, title: { text: LPI_LABELS.rejected } },
                { min: 0, title: { text: LPI_LABELS.rejectionRate }, opposite: true }
            ],
            series: [
                {
                    type: 'column', name: LPI_LABELS.rejected,
                    data: agg.periods.map(function (p) { return agg.byPeriod[p].rejected; })
                },
                {
                    type: 'line', name: LPI_LABELS.rejectionRate, yAxis: 1,
                    data: agg.periods.map(function (p) {
                        var d = agg.byPeriod[p];
                        return d.received > 0 ? Math.round(d.rejected * 10000 / d.received) / 100 : null;
                    })
                }
            ]
        });

        $('#rejectionReasonsWrap').toggle(reasons.length > 0);
        if (reasons.length > 0) {
            buildTable('tableRejectionReasons', [LPI_LABELS.reason, LPI_LABELS.total],
                reasons.map(function (r) { return [r.reason, r.total.toLocaleString()]; }));
            Highcharts.chart('chartRejectionReasons', {
                chart: { type: 'bar' },
                title: { text: "<?= _jsTranslate('Top rejection reasons'); ?>" },
                xAxis: { categories: reasons.slice(0, 10).map(function (r) { return r.reason; }) },
                yAxis: { min: 0, title: { text: LPI_LABELS.total } },
                legend: { enabled: false },
                series: [{ name: LPI_LABELS.total, data: reasons.slice(0, 10).map(function (r) { return r.total; }) }]
            });
        }
    }

    function lpiLoadPatients() {
        if (patientsTable !== null) {
            patientsTable.fnDraw();
            return;
        }
        patientsTable = $('#patientsTable').dataTable({
            "bJQueryUI": false,
            "bAutoWidth": false,
            "bInfo": true,
            "bRetrieve": true,
            "aoColumns": [
                { "sClass": "center", "bSortable": false },
                { "sClass": "center", "bSortable": false },
                { "sClass": "center", "bSortable": false },
                { "sClass": "center", "bSortable": false },
                { "sClass": "center", "bSortable": false }
            ],
            "aaSorting": [],
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": "/admin/monitoring/get-lab-performance-indicators.php",
            "fnServerData": function (sSource, aoData, fnCallback) {
                aoData.push({ "name": "section", "value": "patients" });
                var f = lpiFilters();
                Object.keys(f).forEach(function (k) {
                    aoData.push({ "name": k, "value": f[k] });
                });
                $.ajax({
                    "dataType": 'json',
                    "type": "POST",
                    "url": sSource,
                    "data": aoData,
                    "success": function (json) {
                        if (json && json.summary) {
                            var s = json.summary;
                            $('#cardRepeatPatients').text((s.repeatPatients || 0).toLocaleString());
                            $('#cardChangedPatients').text((s.changedPatients || 0).toLocaleString());
                            $('#cardIdentifierCoverage').text(
                                s.identifierCoverage === null ? '--' : s.identifierCoverage + '%');
                            $('#patientsCoverageNote').text(
                                "<?= _jsTranslate('Repeat visits can only be linked for samples that carry a patient identifier. Coverage below shows how much of the data that is.'); ?>");
                        }
                        fnCallback(json);
                    }
                });
            }
        });
    }

    function lpiExport(format) {
        var section = lpiActiveSection();
        lpiRunExport(section, format);
    }

    function lpiExportAll() {
        lpiRunExport('all', 'json');
    }

    function lpiRunExport(section, format) {
        var data = $.extend({ section: section, format: format }, lpiFilters());
        $.post('/admin/monitoring/export-lab-performance-indicators.php', data, function (fileName) {
            if (fileName) {
                window.location.href = '/download.php?f=' + fileName + '&d=a';
            }
        });
    }

    $(document).ready(function () {
        $('#dateRange').daterangepicker({
            locale: {
                cancelLabel: "<?= _jsTranslate("Clear"); ?>",
                format: 'DD-MMM-YYYY',
                separator: ' to ',
            },
            startDate: moment().startOf('year'),
            endDate: moment(),
            maxDate: moment(),
            ranges: {
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'Last 3 Months': [moment().subtract(3, 'month').startOf('month'), moment()],
                'This Year': [moment().startOf('year'), moment()],
                'Last Year': [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
                'Last 12 Months': [moment().subtract(12, 'month'), moment()]
            }
        });

        $('#testType').select2();
        if ($('#labId').length) {
            $('#labId').select2({ allowClear: true, placeholder: "<?= _jsTranslate('-- All Labs --'); ?>" });
        }

        $('#lpiTabs a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            lpiLoadSection($(e.target).data('section'));
        });

        $('#testType').on('change', function () {
            lpiApplyFilters();
        });

        lpiToggleTabs();
        lpiLoadSummary();
        lpiLoadSection(lpiActiveSection());
    });
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
