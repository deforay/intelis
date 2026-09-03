<?php

use App\Services\TestsService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

$title = _translate("Sample Ageing Report");
require_once APPLICATION_PATH . '/header.php';

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

// recency shares form_vl with VL and is separated by reason_for_vl_testing, the
// same way the dashboard and the request lists separate them, so it stands as
// its own entry rather than being folded into the VL numbers.
$selectableTests = TestsService::getActiveTests();

// The module menus link here with the module preselected.
$preselectedTest = (string) ($_GET['t'] ?? '');
if (!in_array($preselectedTest, $selectableTests, true)) {
    $preselectedTest = $selectableTests[0] ?? '';
}

$testingLabs = $facilitiesService->getTestingLabs();

$stageLabels = [
    'atFacility' => _translate('At facility'),
    'atLab' => _translate('At lab, awaiting test'),
    'awaitingApproval' => _translate('Tested, awaiting approval'),
    'awaitingRelease' => _translate('Approved, awaiting release'),
    'released' => _translate('Released'),
];
$stageHints = [
    'atFacility' => _translate('Registered, not yet received at a lab'),
    'atLab' => _translate('Received, not yet tested (includes failed, on hold and reordered)'),
    'awaitingApproval' => _translate('Tested, result not yet approved'),
    'awaitingRelease' => _translate('Approved, but no delivery of the result recorded yet'),
    'released' => _translate('Result dispatched, printed, e-mailed, sent to its source or fetched over the API'),
];
$exitLabels = [
    'rejected' => _translate('Rejected'),
    'expired' => _translate('Expired'),
    'lost' => _translate('Lost or missing'),
    'cancelled' => _translate('Cancelled'),
];
?>
<style>
    /* The breakdown of one stage, worst first. */
    #sampleFlow .sf-breakdown-title {
        font-size: 15px;
        font-weight: 600;
        color: #444;
        margin: 0 0 4px;
    }

    #sampleFlow .sf-breakdown-hint {
        font-size: 12px;
        color: #8a9299;
        margin: 0 0 10px;
    }

    /* Stage and breakdown rows both open the level below them. */
    #sampleFlow tr.sf-row {
        cursor: pointer;
    }

    #sampleFlow tr.sf-row.is-empty {
        cursor: default;
        color: #a6adb4;
    }

    #sampleFlow tr.sf-row.is-empty:hover>td {
        background-color: inherit;
    }

    #sampleFlow tr.sf-row:hover>td {
        background-color: #f4f8fb;
    }

    #sampleFlow tr.sf-row.is-active>td {
        background-color: #eaf3fa;
        font-weight: 600;
    }

    #sampleFlow tr.sf-section>td {
        background-color: #f7f7f7;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #8a9299;
    }

    #sampleFlow table.sf-table th {
        background-color: #f4f6f8;
        white-space: nowrap;
    }

    /* Counts sit next to what they count, not at the far edge of the page.
       Bootstrap's .table is width:100%, which stretches the label column and
       strands the numbers against the right margin, so these tables size to
       their content instead and stop at a readable width. */
    #sampleFlow table.sf-table {
        width: 100%;
        max-width: 640px;
    }

    #sampleFlow table.sf-table th.sf-num {
        width: 130px;
        text-align: right;
    }

    #sampleFlow table.sf-table td.num {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    #sampleFlow table.sf-table tfoot th {
        border-top: 2px solid #d5dce2;
        text-align: right;
    }

    /* Every count in the breakdown opens the samples behind it. */
    #sampleFlow table.sf-table td.sf-drill {
        cursor: pointer;
    }

    #sampleFlow table.sf-table td.sf-drill:hover {
        outline: 2px solid #3c8dbc;
        outline-offset: -2px;
    }

    #sampleFlow table.sf-table td.sf-drill.is-active {
        outline: 2px solid #3c8dbc;
        outline-offset: -2px;
        background-color: #e3eef5;
    }

    #sampleFlow .sf-samples {
        margin-top: 22px;
        padding-top: 14px;
        border-top: 1px solid #e4e8ec;
    }

    #sampleFlow .sf-samples-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 8px;
    }

    #sampleFlow .sf-samples-title {
        font-size: 15px;
        font-weight: 600;
        color: #444;
    }

    #sampleFlow .sf-samples-title small {
        display: block;
        font-size: 12px;
        font-weight: 400;
        color: #8a9299;
        margin-top: 2px;
    }

    #sampleFlow #sfSamplesTable td.num {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    #sampleFlow .sf-methodology {
        background-color: #f8fafb;
        border: 1px solid #e4e8ec;
        border-radius: 3px;
        padding: 12px 16px;
        margin: 0 0 15px;
        font-size: 12.5px;
        max-width: 980px;
    }

    #sampleFlow .sf-methodology dt {
        margin-top: 10px;
        color: #444;
    }

    #sampleFlow .sf-methodology dt:first-child {
        margin-top: 0;
    }

    #sampleFlow .sf-methodology dd {
        color: #5a6570;
        margin-left: 0;
    }

    #sampleFlow .sf-methodology ul {
        margin: 6px 0;
        padding-left: 18px;
    }

    /* One thin bar across the top of the window while a request is out. */
    #sfProgress {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        z-index: 2000;
        overflow: hidden;
        display: none;
        pointer-events: none;
        background-color: #dfe6ec;
    }

    #sfProgress.is-active {
        display: block;
    }

    #sfProgress span {
        display: block;
        width: 35%;
        height: 100%;
        background-color: #3c8dbc;
        animation: sfProgressSlide 1.15s ease-in-out infinite;
    }

    @keyframes sfProgressSlide {
        0% { margin-left: -35%; }
        100% { margin-left: 100%; }
    }

    @media (prefers-reduced-motion: reduce) {
        #sfProgress span {
            width: 100%;
            animation: none;
        }
    }

    .daterangepicker .ranges ul {
        max-height: 320px;
        overflow-y: auto;
    }

    th {
        display: revert !important;
    }
</style>
<div class="content-wrapper" id="sampleFlow">
    <div id="sfProgress" aria-hidden="true"><span></span></div>
    <section class="content-header">
        <h1><em class="fa-solid fa-diagram-project"></em>
            <?= _htmlTranslate("Sample Ageing Report"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
                    <?= _htmlTranslate("Home"); ?>
                </a></li>
            <li class="active">
                <?= _htmlTranslate("Sample Ageing Report"); ?>
            </li>
        </ol>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-body">
                        <p class="text-muted" id="sf-description">
                            <?= _htmlTranslate('Where every sample registered in the period is right now, and how long it has been there.'); ?>
                            <a href="javascript:void(0);" onclick="$('#sfMethodology').slideToggle(150);">
                                <em class="fa-solid fa-circle-info"></em>
                                <?= _htmlTranslate('How is a sample placed in a stage?'); ?>
                            </a>
                        </p>

                        <div id="sfMethodology" class="sf-methodology" style="display:none;">
                            <dl>
                                <dt><?= _htmlTranslate('Which samples'); ?></dt>
                                <dd><?= _htmlTranslate('Every sample whose collection date falls in the selected range, or the date the request was created when no collection date was recorded. The stages and the exits together add up to all of them, so nothing registered goes uncounted.'); ?></dd>

                                <dt><?= _htmlTranslate('Stages'); ?></dt>
                                <dd>
                                    <?= _htmlTranslate('A sample is placed at the furthest point its recorded timestamps prove it reached, read from the workflow dates rather than the status alone. Status is only used for the exits, and for failed, on hold and reordered samples, which are back in the lab queue.'); ?>
                                    <ul>
                                        <li><strong><?= _htmlTranslate('At facility'); ?></strong>: <?= _htmlTranslate('registered, with no lab receipt recorded. Dispatch from the facility is not tracked, so a sample stays here until a lab records receiving it.'); ?></li>
                                        <li><strong><?= _htmlTranslate('At lab, awaiting test'); ?></strong>: <?= _htmlTranslate('received at a lab, not yet tested; also failed, on hold and reordered samples.'); ?></li>
                                        <li><strong><?= _htmlTranslate('Tested, awaiting approval'); ?></strong>: <?= _htmlTranslate('a test date or a result is recorded, but the result is not yet approved.'); ?></li>
                                        <li><strong><?= _htmlTranslate('Approved, awaiting release'); ?></strong>: <?= _htmlTranslate('the result is approved, but no delivery has been recorded.'); ?></li>
                                        <li><strong><?= _htmlTranslate('Released'); ?></strong>: <?= _htmlTranslate('a delivery of the result is recorded: dispatched, printed (here or on the connected system), e-mailed, sent to its source, or fetched over the API.'); ?></li>
                                    </ul>
                                </dd>

                                <dt><?= _htmlTranslate('Age'); ?></dt>
                                <dd><?= _htmlTranslate('Days since the most recent milestone the sample reached: since lab receipt for a sample awaiting a test, since the test date for one awaiting approval, and so on. A sample that has sat 30 days at a lab shows as 30 days, whatever its collection date. Milestones dated in the future count as zero days.'); ?></dd>

                                <dt><?= _htmlTranslate('Exits'); ?></dt>
                                <dd><?= _htmlTranslate('Rejected, expired, lost and cancelled samples have left the pipeline without a released result. They are listed so the total reconciles, and can be broken down like any stage.'); ?></dd>
                            </dl>
                        </div>

                        <table aria-describedby="sf-description" class="table pageFilters" cellspacing="3"
                            style="margin-left:1%;margin-top:5px;width:98%;">
                            <tr>
                                <td><strong><?= _htmlTranslate('Test'); ?>&nbsp;:</strong></td>
                                <td>
                                    <select id="testType" class="form-control" style="width:100%;max-width:280px;">
                                        <?php foreach ($selectableTests as $testKey) { ?>
                                            <option value="<?= htmlspecialchars($testKey, ENT_QUOTES); ?>" <?= $testKey === $preselectedTest ? 'selected="selected"' : ''; ?>>
                                                <?= htmlspecialchars(TestsService::getTestName($testKey), ENT_QUOTES); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </td>
                                <td><strong><?= _htmlTranslate('Registered'); ?>&nbsp;:</strong></td>
                                <td>
                                    <input type="text" id="dateRange" class="form-control daterangefield"
                                        style="width:100%;max-width:240px;" />
                                </td>
                                <?php if (!empty($testingLabs)) { ?>
                                    <td><strong><?= _htmlTranslate('Testing Lab'); ?>&nbsp;:</strong></td>
                                    <td>
                                        <select id="labId" class="form-control" style="width:100%;max-width:260px;">
                                            <option value=""><?= _htmlTranslate('-- All Labs --'); ?></option>
                                            <?php foreach ($testingLabs as $labId => $labName) { ?>
                                                <option value="<?= (int) $labId; ?>"><?= htmlspecialchars((string) $labName, ENT_QUOTES); ?></option>
                                            <?php } ?>
                                        </select>
                                    </td>
                                <?php } ?>
                                <td>
                                    <button type="button" class="btn btn-success btn-sm" onclick="sfApplyFilters();">
                                        <?= _htmlTranslate('Search'); ?>
                                    </button>
                                </td>
                            </tr>
                        </table>


                        <div class="row">
                            <div class="col-md-5">
                                <div class="sf-breakdown-title"><?= _htmlTranslate('All stages'); ?></div>
                                <div class="sf-breakdown-hint">
                                    <?= _htmlTranslate('Click a stage to break it down by testing lab.'); ?>
                                </div>
                                <div class="table-responsive" style="margin-top:12px;">
                                    <table class="table table-bordered table-striped sf-table" id="sfStages"
                                        aria-describedby="sf-description">
                                        <thead>
                                            <tr>
                                                <th><?= _htmlTranslate('Stage'); ?></th>
                                                <th class="sf-num"><?= _htmlTranslate('Samples'); ?></th>
                                                <th class="sf-num"><?= _htmlTranslate('Over 30 days'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($stageLabels as $stageKey => $label) { ?>
                                                <tr class="sf-row" data-stage="<?= $stageKey; ?>"
                                                    title="<?= htmlspecialchars($stageHints[$stageKey], ENT_QUOTES); ?>"
                                                    onclick="sfSelectStage('<?= $stageKey; ?>');">
                                                    <td><?= htmlspecialchars($label, ENT_QUOTES); ?></td>
                                                    <td class="num" id="count-<?= $stageKey; ?>">&ndash;</td>
                                                    <td class="num" id="old-<?= $stageKey; ?>"></td>
                                                </tr>
                                            <?php } ?>
                                            <tr class="sf-section">
                                                <td colspan="3"><?= _htmlTranslate('Left the pipeline'); ?></td>
                                            </tr>
                                            <?php foreach ($exitLabels as $exitKey => $label) { ?>
                                                <tr class="sf-row" data-stage="<?= $exitKey; ?>"
                                                    onclick="sfSelectStage('<?= $exitKey; ?>');">
                                                    <td><?= htmlspecialchars($label, ENT_QUOTES); ?></td>
                                                    <td class="num" id="count-<?= $exitKey; ?>">&ndash;</td>
                                                    <td></td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <th style="text-align:left;"><?= _htmlTranslate('Registered in this period'); ?></th>
                                                <th id="foot-registered"></th>
                                                <th></th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div id="sfBreakdown" style="display:none;">
                                    <div class="sf-breakdown-title" id="sfBreakdownTitle"></div>
                                    <div class="sf-breakdown-hint">
                                        <?= _htmlTranslate('Click a count to list the samples behind it.'); ?>
                                        <a href="javascript:void(0);" onclick="sfDrill('', '');"><?= _htmlTranslate('List every sample in this stage'); ?></a>
                                    </div>
                                    <div class="table-responsive" style="margin-top:12px;">
                                        <table class="table table-bordered table-striped sf-table" id="sfTable" aria-describedby="sfBreakdownTitle">
                                            <thead>
                                                <tr>
                                                    <th><?= _htmlTranslate('Testing Lab'); ?></th>
                                                    <th class="sf-num"><?= _htmlTranslate('Samples'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                            <tfoot>
                                                <tr>
                                                    <th style="text-align:left;"><?= _htmlTranslate('Total'); ?></th>
                                                    <th id="foot-total"></th>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="sf-samples" id="sfSamples" style="display:none;">
                                <div class="sf-samples-head">
                                    <div class="sf-samples-title">
                                        <span id="sfSamplesTitle"></span>
                                        <small id="sfSamplesSubtitle"></small>
                                    </div>
                                    <div>
                                        <button type="button" class="btn btn-success btn-sm" onclick="sfExportSamples();">
                                            <em class="fa-solid fa-cloud-arrow-down"></em>
                                            <?= _htmlTranslate('Export to Excel'); ?>
                                        </button>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped" id="sfSamplesTable" aria-describedby="sfSamplesTitle">
                                        <thead>
                                            <tr>
                                                <?php foreach (\App\Services\SampleFlowService::sampleColumns() as $heading) { ?>
                                                    <th><?= htmlspecialchars($heading, ENT_QUOTES); ?></th>
                                                <?php } ?>
                                            </tr>
                                        </thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
<script type="text/javascript">
    var SF_STAGES = <?= json_encode(array_keys($stageLabels)); ?>;
    var SF_EXITS = <?= json_encode(array_keys($exitLabels)); ?>;
    var SF_STAGE_LABELS = <?= json_encode(array_merge($stageLabels, $exitLabels), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var SF_SAMPLE_COLUMNS = <?= count(\App\Services\SampleFlowService::sampleColumns()); ?>;
    var SF_LABELS = {
        noData: "<?= _jsTranslate('No samples in this stage for the selected filters'); ?>",
        breakdownOf: "<?= _jsTranslate('%s by testing lab'); ?>",
        samplesIn: "<?= _jsTranslate('Samples: %s'); ?>",
        lab: "<?= _jsTranslate('Testing Lab'); ?>",
        wholeStage: "<?= _jsTranslate('every %s'); ?>",
        exportFailed: "<?= _jsTranslate('Unable to generate the export file'); ?>"
    };

    var sfFlow = null;
    var sfStage = null;
    var sfGroup = 'lab';
    var sfRows = [];
    var sfPending = 0;
    // The cell currently listed: group key and age bucket within sfStage.
    var sfDrillSel = null;
    var sfSamplesTable = null;

    function sfProgress(delta) {
        sfPending = Math.max(0, sfPending + delta);
        $('#sfProgress').toggleClass('is-active', sfPending > 0);
    }

    function esc(value) {
        return $('<div>').text(value === null || value === undefined ? '' : String(value)).html();
    }

    function sfFilters() {
        return {
            testType: $('#testType').val(),
            dateRange: $('#dateRange').val(),
            labId: $('#labId').length ? ($('#labId').val() || '') : ''
        };
    }

    function sfPost(section, extra, done) {
        var data = $.extend({ section: section }, sfFilters(), extra || {});
        sfProgress(1);
        return $.ajax({
            url: '/reports/get-sample-ageing.php',
            type: 'POST',
            dataType: 'json',
            data: data,
            success: done
        }).always(function () {
            sfProgress(-1);
        });
    }

    function sfApplyFilters() {
        sfFlow = null;
        sfCloseSamples();
        $('#sfBreakdown').hide();
        $('#sfStages tr.sf-row').removeClass('is-active');
        sfLoadFlow(function () {
            // Keep the reader on the stage they were looking at across a
            // filter change; otherwise land on the busiest open stage.
            if (sfStage && (sfFlow[sfStage] || {}).total > 0) {
                sfSelectStage(sfStage);
            } else {
                sfSelectStage(sfBusiestStage());
            }
        });
    }

    function sfBusiestStage() {
        var best = null;
        var bestCount = -1;
        SF_STAGES.forEach(function (stage) {
            if (stage === 'released') { return; }
            var count = (sfFlow[stage] || {}).total || 0;
            if (count > bestCount) {
                best = stage;
                bestCount = count;
            }
        });
        return best || 'atFacility';
    }

    function sfLoadFlow(done) {
        sfPost('flow', null, function (json) {
            if (!json || json.error || !json.flow) {
                return;
            }
            sfFlow = json.flow;
            sfRenderFlow();
            if (done) { done(); }
        });
    }

    function sfRenderFlow() {
        var registered = 0;

        SF_STAGES.forEach(function (stage) {
            var counts = sfFlow[stage] || { total: 0 };
            registered += counts.total;
            $('#count-' + stage).text(counts.total.toLocaleString());
            sfMarkRow(stage, counts.total);

            // The one age signal kept. Thirty days is the threshold that means
            // something programmatically, and on a wide window nearly every
            // open sample breaches it, so the count alone reads as a copy of
            // the one beside it. The share is what separates one stage from
            // another. The full age breakdown is deliberately not shown.
            var oldHere = (counts.b3 || 0) + (counts.b4 || 0);
            $('#old-' + stage).text(
                (stage !== 'released' && oldHere > 0)
                    ? oldHere.toLocaleString() + ' (' + Math.round(oldHere / counts.total * 100) + '%)'
                    : ''
            );
        });

        SF_EXITS.forEach(function (exit) {
            var total = (sfFlow[exit] || {}).total || 0;
            registered += total;
            $('#count-' + exit).text(total.toLocaleString());
            sfMarkRow(exit, total);
        });

        // Every stage and every exit together account for all of it.
        $('#foot-registered').text(registered.toLocaleString());
    }

    // A stage holding nothing is dimmed and stops responding to a click, so it
    // cannot open an empty breakdown.
    function sfMarkRow(stage, total) {
        $('#sfStages tr[data-stage="' + stage + '"]').toggleClass('is-empty', total === 0);
    }

    function sfSelectStage(stage) {
        if (sfFlow && ((sfFlow[stage] || {}).total || 0) === 0) { return; }
        sfStage = stage;
        sfCloseSamples();
        $('#sfStages tr.sf-row').removeClass('is-active');
        $('#sfStages tr[data-stage="' + stage + '"]').addClass('is-active');
        sfLoadBreakdown();
    }

    function sfLoadBreakdown() {
        if (!sfStage) { return; }
        $('#sfBreakdownTitle').text(SF_LABELS.breakdownOf.replace('%s', SF_STAGE_LABELS[sfStage] || sfStage));
        $('#sfBreakdown').show();
        $('#sfTable tbody').html('<tr><td colspan="2" class="text-center text-muted">&hellip;</td></tr>');
        sfPost('breakdown', { stage: sfStage, groupBy: sfGroup }, function (json) {
            sfRows = (json && json.rows) ? json.rows : [];
            sfRenderBreakdown();
        }).fail(function () {
            sfRows = [];
            sfRenderBreakdown();
        });
    }

    function sfRenderBreakdown() {
        var rows = sfRows.slice().sort(function (a, b) {
            return (b.total - a.total) || String(a.label).localeCompare(String(b.label));
        });
        var total = 0;

        var html = '';
        rows.forEach(function (row) {
            var key = String(row.key === undefined || row.key === null ? '' : row.key);
            total += row.total;
            html += '<tr><td>' + esc(row.label) + '</td>'
                + '<td class="num sf-drill" data-key="' + esc(key) + '" data-label="' + esc(row.label) + '">'
                + row.total.toLocaleString() + '</td></tr>';
        });
        if (rows.length === 0) {
            html = '<tr><td colspan="2" class="text-center text-muted">' + esc(SF_LABELS.noData) + '</td></tr>';
        }
        $('#sfTable tbody').html(html);
        $('#foot-total').text(total.toLocaleString());
        sfMarkDrillCell();
    }

    // The samples behind one count: an empty key lists the whole stage, a key
    // narrows to one testing lab.
    function sfDrill(groupKey, label) {
        if (!sfStage) { return; }
        sfDrillSel = {
            stage: sfStage,
            groupBy: groupKey === '' && label === '' ? '' : sfGroup,
            groupKey: groupKey,
            bucket: '',
            label: label
        };
        sfMarkDrillCell();

        var stageLabel = SF_STAGE_LABELS[sfStage] || sfStage;
        $('#sfSamplesTitle').text(SF_LABELS.samplesIn.replace('%s', stageLabel));
        $('#sfSamplesSubtitle').text(
            label !== '' ? SF_LABELS.lab + ': ' + label : SF_LABELS.wholeStage.replace('%s', stageLabel.toLowerCase())
        );
        $('#sfSamples').show();
        sfLoadSamples();
        document.getElementById('sfSamples').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function sfMarkDrillCell() {
        $('#sfTable td.sf-drill').removeClass('is-active');
        if (!sfDrillSel || sfDrillSel.groupBy === '') { return; }
        $('#sfTable td.sf-drill').filter(function () {
            return $(this).data('key') === sfDrillSel.groupKey;
        }).addClass('is-active');
    }

    function sfCloseSamples() {
        sfDrillSel = null;
        $('#sfSamples').hide();
        $('#sfTable td.sf-drill').removeClass('is-active');
    }

    function sfDrillParams() {
        return $.extend({}, sfFilters(), {
            stage: sfDrillSel.stage,
            groupBy: sfDrillSel.groupBy,
            groupKey: sfDrillSel.groupKey,
            bucket: sfDrillSel.bucket
        });
    }

    function sfLoadSamples() {
        if (sfSamplesTable !== null) {
            sfSamplesTable.fnDraw();
            return;
        }
        var columns = [];
        for (var i = 0; i < SF_SAMPLE_COLUMNS; i++) {
            columns.push({ "bSortable": false, "sClass": i === SF_SAMPLE_COLUMNS - 1 ? 'num' : '' });
        }
        sfSamplesTable = $('#sfSamplesTable').dataTable({
            "bJQueryUI": false,
            "bAutoWidth": false,
            "bInfo": true,
            "bRetrieve": true,
            "aoColumns": columns,
            "aaSorting": [],
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": "/reports/get-sample-ageing.php",
            "fnServerData": function (sSource, aoData, fnCallback) {
                if (!sfDrillSel) { return; }
                aoData.push({ "name": "section", "value": "samples" });
                var p = sfDrillParams();
                Object.keys(p).forEach(function (k) {
                    aoData.push({ "name": k, "value": p[k] });
                });
                sfProgress(1);
                $.ajax({
                    "dataType": 'json',
                    "type": "POST",
                    "url": sSource,
                    "data": aoData,
                    "complete": function () { sfProgress(-1); },
                    "success": fnCallback
                });
            }
        });
    }

    function sfExportSamples() {
        if (!sfDrillSel) { return; }
        $.blockUI();
        $.post('/reports/get-sample-ageing.php', $.extend({ section: 'export' }, sfDrillParams()), function (data) {
            $.unblockUI();
            if (data === '' || data === null || data === undefined || String(data).indexOf('{') === 0) {
                alert(SF_LABELS.exportFailed);
                return;
            }
            window.open('/download.php?f=' + data, '_blank');
        }).fail(function () {
            $.unblockUI();
            alert(SF_LABELS.exportFailed);
        });
    }

    function sfDateRanges() {
        var ranges = {};
        ranges["<?= _jsTranslate('Last 30 Days'); ?>"] = [moment().subtract(29, 'days'), moment()];
        ranges["<?= _jsTranslate('Last 90 Days'); ?>"] = [moment().subtract(89, 'days'), moment()];
        ranges["<?= _jsTranslate('Last 180 Days'); ?>"] = [moment().subtract(179, 'days'), moment()];
        ranges["<?= _jsTranslate('Last 12 Months'); ?>"] = [moment().subtract(12, 'month'), moment()];
        ranges["<?= _jsTranslate('Last 24 Months'); ?>"] = [moment().subtract(24, 'month'), moment()];
        ranges["<?= _jsTranslate('This Year'); ?>"] = [moment().startOf('year'), moment()];
        var m = moment().subtract(1, 'year');
        ranges[m.year()] = [m.clone().startOf('year'), m.clone().endOf('year')];
        return ranges;
    }

    $(document).ready(function () {
        // A stuck sample is old by definition, so the window opens wide.
        $('#dateRange').daterangepicker({
            locale: {
                cancelLabel: "<?= _jsTranslate("Clear"); ?>",
                format: 'DD-MMM-YYYY',
                separator: ' to ',
            },
            showDropdowns: true,
            startDate: moment().subtract(12, 'month'),
            endDate: moment(),
            maxDate: moment(),
            ranges: sfDateRanges()
        });
        $('#dateRange').on('cancel.daterangepicker', function () {
            $(this).val('');
        });

        $('#testType').select2();
        if ($('#labId').length) {
            $('#labId').select2({ allowClear: true, placeholder: "<?= _jsTranslate('-- All Labs --'); ?>" });
        }
        $('#testType').on('change', function () {
            sfApplyFilters();
        });

        $('#sfTable').on('click', 'td.sf-drill', function () {
            sfDrill(String($(this).data('key')), String($(this).data('label')));
        });

        sfApplyFilters();
    });
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
