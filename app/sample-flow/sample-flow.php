<?php

use App\Services\TestsService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

$title = _translate("Sample Flow");
require_once APPLICATION_PATH . '/header.php';

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

// recency shares form_vl with VL, so a selector entry of its own would only
// repeat the VL numbers.
$selectableTests = array_values(array_diff(TestsService::getActiveTests(), ['recency']));

// The module menus link here with the module preselected.
$preselectedTest = (string) ($_GET['t'] ?? '');
if (!in_array($preselectedTest, $selectableTests, true)) {
    $preselectedTest = $selectableTests[0] ?? '';
}

$testingLabs = $facilitiesService->getTestingLabs();

$stageLabels = [
    'atFacility' => _translate('At facility'),
    'inTransit' => _translate('In transit'),
    'atLab' => _translate('At lab, awaiting test'),
    'awaitingApproval' => _translate('Tested, awaiting approval'),
    'awaitingRelease' => _translate('Approved, awaiting release'),
    'released' => _translate('Released'),
];
$stageHints = [
    'atFacility' => _translate('Registered, not yet dispatched'),
    'inTransit' => _translate('Dispatched, not yet received at a lab'),
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
$bucketLabels = [
    'b0' => _translate('0-7 days'),
    'b1' => _translate('8-14 days'),
    'b2' => _translate('15-30 days'),
    'b3' => _translate('31-60 days'),
    'b4' => _translate('Over 60 days'),
];
$groupLabels = [
    'lab' => _translate('Testing Lab'),
    'facility' => _translate('Collection Facility'),
    'province' => _translate('Province/State'),
    'district' => _translate('District/County'),
    'partner' => _translate('Implementing Partner'),
];
?>
<style>
    #sampleFlow .sf-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin: 0 0 18px;
    }

    #sampleFlow .sf-card {
        flex: 1 1 160px;
        padding: 12px 15px;
        background-color: #f8fafb;
        border: 1px solid #e4e8ec;
        border-left: 3px solid #3c8dbc;
        border-radius: 3px;
    }

    #sampleFlow .sf-card-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #8a9299;
    }

    #sampleFlow .sf-card-value {
        font-size: 22px;
        font-weight: 700;
        color: #444;
        line-height: 1.3;
    }

    #sampleFlow .sf-card-basis {
        font-size: 11px;
        color: #8a9299;
        margin-top: 3px;
    }

    /* The flow strip: one box per stage, in pipeline order, joined by arrows. */
    #sampleFlow .sf-flow {
        display: flex;
        align-items: stretch;
        gap: 0;
        margin: 6px 0 14px;
        overflow-x: auto;
        padding-bottom: 4px;
    }

    #sampleFlow .sf-stage {
        flex: 1 1 0;
        min-width: 150px;
        padding: 12px 12px 10px;
        background-color: #fff;
        border: 1px solid #d5dce2;
        border-radius: 4px;
        cursor: pointer;
        transition: box-shadow .12s, border-color .12s;
    }

    #sampleFlow .sf-stage:hover {
        border-color: #3c8dbc;
        box-shadow: 0 1px 4px rgba(60, 141, 188, .25);
    }

    #sampleFlow .sf-stage.is-active {
        border-color: #3c8dbc;
        box-shadow: inset 0 0 0 2px #3c8dbc;
    }

    #sampleFlow .sf-stage.is-released {
        background-color: #f8fafb;
    }

    #sampleFlow .sf-stage-label {
        font-size: 12px;
        font-weight: 600;
        color: #444;
        line-height: 1.3;
        min-height: 32px;
    }

    #sampleFlow .sf-stage-count {
        font-size: 24px;
        font-weight: 700;
        color: #333;
        line-height: 1.2;
        margin: 4px 0 2px;
    }

    #sampleFlow .sf-stage-old {
        font-size: 11px;
        color: #8a9299;
        min-height: 15px;
    }

    #sampleFlow .sf-stage-old strong {
        color: #c0392b;
    }

    /* Age distribution: five bars, oldest on the right, sized against the
       stage's own busiest bucket so every box reads on its own scale. */
    #sampleFlow .sf-bars {
        display: flex;
        align-items: flex-end;
        gap: 3px;
        height: 34px;
        margin-top: 8px;
    }

    #sampleFlow .sf-bar {
        flex: 1 1 0;
        min-height: 2px;
        border-radius: 2px 2px 0 0;
        background-color: #cfd6dc;
    }

    #sampleFlow .sf-bar.b0 { background-color: #00a65a; }
    #sampleFlow .sf-bar.b1 { background-color: #6fbf73; }
    #sampleFlow .sf-bar.b2 { background-color: #f3c969; }
    #sampleFlow .sf-bar.b3 { background-color: #f39c12; }
    #sampleFlow .sf-bar.b4 { background-color: #c0392b; }

    #sampleFlow .sf-bar.is-empty {
        background-color: #e9edf1;
    }

    #sampleFlow .sf-arrow {
        flex: 0 0 auto;
        align-self: center;
        padding: 0 4px;
        color: #b0b8c0;
        font-size: 18px;
    }

    /* Exits: how samples leave the pipeline without a released result. */
    #sampleFlow .sf-exits {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        margin: 0 0 18px;
        font-size: 12px;
        color: #5a6570;
    }

    #sampleFlow .sf-exit {
        display: inline-block;
        padding: 4px 10px;
        border: 1px solid #e0e5ea;
        border-radius: 12px;
        background-color: #eef1f4;
        cursor: pointer;
        color: #444;
    }

    #sampleFlow .sf-exit:hover,
    #sampleFlow .sf-exit.is-active {
        border-color: #3c8dbc;
        background-color: #e3eef5;
    }

    #sampleFlow .sf-exit strong {
        font-weight: 700;
    }

    #sampleFlow .sf-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        font-size: 11px;
        color: #8a9299;
        margin: -6px 0 14px;
    }

    #sampleFlow .sf-legend span::before {
        content: '';
        display: inline-block;
        width: 10px;
        height: 10px;
        margin-right: 4px;
        border-radius: 2px;
        vertical-align: -1px;
    }

    #sampleFlow .sf-legend .b0::before { background-color: #00a65a; }
    #sampleFlow .sf-legend .b1::before { background-color: #6fbf73; }
    #sampleFlow .sf-legend .b2::before { background-color: #f3c969; }
    #sampleFlow .sf-legend .b3::before { background-color: #f39c12; }
    #sampleFlow .sf-legend .b4::before { background-color: #c0392b; }

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

    #sampleFlow .nav-tabs>li>a {
        font-weight: 600;
    }

    #sampleFlow table.sf-table th {
        background-color: #f4f6f8;
        white-space: nowrap;
    }

    #sampleFlow table.sf-table th.sf-sortable {
        cursor: pointer;
    }

    #sampleFlow table.sf-table th.sf-sorted::after {
        content: ' \25BC';
        font-size: 9px;
        color: #3c8dbc;
    }

    #sampleFlow table.sf-table td.num {
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    #sampleFlow table.sf-table td.num.warm {
        background-color: #fdf6e3;
        color: #8a6d1a;
        font-weight: 600;
    }

    #sampleFlow table.sf-table td.num.hot {
        background-color: #fbeae7;
        color: #a93226;
        font-weight: 700;
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
            <?= _htmlTranslate("Sample Flow"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
                    <?= _htmlTranslate("Home"); ?>
                </a></li>
            <li class="active">
                <?= _htmlTranslate("Sample Flow"); ?>
            </li>
        </ol>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-body">
                        <p class="text-muted" id="sf-description">
                            <?= _htmlTranslate('Where every sample registered in the period is right now, and how long it has been there. Click a stage to see which labs, facilities or partners are holding the oldest samples.'); ?>
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
                                        <li><strong><?= _htmlTranslate('At facility'); ?></strong>: <?= _htmlTranslate('registered, with no dispatch, hub or lab receipt recorded.'); ?></li>
                                        <li><strong><?= _htmlTranslate('In transit'); ?></strong>: <?= _htmlTranslate('dispatched from the facility or received at a hub, not yet received at a lab.'); ?></li>
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
                                            <?php foreach ($testingLabs as $lab) { ?>
                                                <option value="<?= (int) $lab['facility_id']; ?>"><?= htmlspecialchars((string) $lab['facility_name'], ENT_QUOTES); ?></option>
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

                        <div class="sf-summary">
                            <div class="sf-card">
                                <div class="sf-card-label"><?= _htmlTranslate('Registered'); ?></div>
                                <div class="sf-card-value" id="cardRegistered">&ndash;</div>
                                <div class="sf-card-basis"><?= _htmlTranslate('In the selected range'); ?></div>
                            </div>
                            <div class="sf-card">
                                <div class="sf-card-label"><?= _htmlTranslate('Still in the pipeline'); ?></div>
                                <div class="sf-card-value" id="cardOpen">&ndash;</div>
                                <div class="sf-card-basis"><?= _htmlTranslate('No result released yet'); ?></div>
                            </div>
                            <div class="sf-card is-alert">
                                <div class="sf-card-label"><?= _htmlTranslate('Waiting over 30 days'); ?></div>
                                <div class="sf-card-value" id="cardOld">&ndash;</div>
                                <div class="sf-card-basis"><?= _htmlTranslate('In the pipeline, at the current stage'); ?></div>
                            </div>
                            <div class="sf-card">
                                <div class="sf-card-label"><?= _htmlTranslate('Released'); ?></div>
                                <div class="sf-card-value" id="cardReleased">&ndash;</div>
                                <div class="sf-card-basis"><?= _htmlTranslate('A delivery of the result is recorded'); ?></div>
                            </div>
                            <div class="sf-card">
                                <div class="sf-card-label"><?= _htmlTranslate('Left the pipeline'); ?></div>
                                <div class="sf-card-value" id="cardExits">&ndash;</div>
                                <div class="sf-card-basis"><?= _htmlTranslate('Rejected, expired, lost or cancelled'); ?></div>
                            </div>
                        </div>

                        <div class="sf-flow" id="sfFlow">
                            <?php $last = array_key_last($stageLabels); ?>
                            <?php foreach ($stageLabels as $stageKey => $label) { ?>
                                <div class="sf-stage <?= $stageKey === 'released' ? 'is-released' : ''; ?>"
                                    data-stage="<?= $stageKey; ?>"
                                    title="<?= htmlspecialchars($stageHints[$stageKey], ENT_QUOTES); ?>"
                                    onclick="sfSelectStage('<?= $stageKey; ?>');">
                                    <div class="sf-stage-label"><?= htmlspecialchars($label, ENT_QUOTES); ?></div>
                                    <div class="sf-stage-count" id="count-<?= $stageKey; ?>">&ndash;</div>
                                    <div class="sf-stage-old" id="old-<?= $stageKey; ?>"></div>
                                    <div class="sf-bars" id="bars-<?= $stageKey; ?>"></div>
                                </div>
                                <?php if ($stageKey !== $last) { ?>
                                    <div class="sf-arrow" aria-hidden="true">&#10140;</div>
                                <?php } ?>
                            <?php } ?>
                        </div>

                        <div class="sf-legend">
                            <?php foreach ($bucketLabels as $bucketKey => $label) { ?>
                                <span class="<?= $bucketKey; ?>"><?= htmlspecialchars($label, ENT_QUOTES); ?></span>
                            <?php } ?>
                        </div>

                        <div class="sf-exits" id="sfExits">
                            <span><?= _htmlTranslate('Left the pipeline'); ?>:</span>
                            <?php foreach ($exitLabels as $exitKey => $label) { ?>
                                <span class="sf-exit" data-stage="<?= $exitKey; ?>" onclick="sfSelectStage('<?= $exitKey; ?>');">
                                    <?= htmlspecialchars($label, ENT_QUOTES); ?> <strong id="count-<?= $exitKey; ?>">&ndash;</strong>
                                </span>
                            <?php } ?>
                        </div>

                        <div id="sfBreakdown" style="display:none;">
                            <div class="sf-breakdown-title" id="sfBreakdownTitle"></div>
                            <div class="sf-breakdown-hint">
                                <?= _htmlTranslate('Sorted by the column marked with an arrow; click a column heading to sort by it. Click any count to list the samples behind it.'); ?>
                                <a href="javascript:void(0);" onclick="sfDrill('', '', '');"><?= _htmlTranslate('List every sample in this stage'); ?></a>
                            </div>
                            <ul class="nav nav-tabs" id="sfGroupTabs">
                                <?php $first = true; ?>
                                <?php foreach ($groupLabels as $groupKey => $label) { ?>
                                    <li class="<?= $first ? 'active' : ''; ?>">
                                        <a href="javascript:void(0);" data-group="<?= $groupKey; ?>" onclick="sfSelectGroup('<?= $groupKey; ?>');"><?= htmlspecialchars($label, ENT_QUOTES); ?></a>
                                    </li>
                                    <?php $first = false; ?>
                                <?php } ?>
                            </ul>
                            <div class="table-responsive" style="margin-top:12px;">
                                <table class="table table-bordered table-striped sf-table" id="sfTable" aria-describedby="sfBreakdownTitle">
                                    <thead>
                                        <tr>
                                            <th id="sfGroupHeading"></th>
                                            <th class="sf-sortable sf-sorted" data-sort="total" onclick="sfSortBy('total');"><?= _htmlTranslate('Total'); ?></th>
                                            <?php foreach ($bucketLabels as $bucketKey => $label) { ?>
                                                <th class="sf-sortable" data-sort="<?= $bucketKey; ?>" onclick="sfSortBy('<?= $bucketKey; ?>');"><?= htmlspecialchars($label, ENT_QUOTES); ?></th>
                                            <?php } ?>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                    <tfoot>
                                        <tr>
                                            <th style="text-align:left;"><?= _htmlTranslate('Total'); ?></th>
                                            <th id="foot-total"></th>
                                            <?php foreach (array_keys($bucketLabels) as $bucketKey) { ?>
                                                <th id="foot-<?= $bucketKey; ?>"></th>
                                            <?php } ?>
                                        </tr>
                                    </tfoot>
                                </table>
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
        </div>
    </section>
</div>
<script type="text/javascript">
    var SF_STAGES = <?= json_encode(array_keys($stageLabels)); ?>;
    var SF_EXITS = <?= json_encode(array_keys($exitLabels)); ?>;
    var SF_BUCKETS = <?= json_encode(array_keys($bucketLabels)); ?>;
    var SF_STAGE_LABELS = <?= json_encode(array_merge($stageLabels, $exitLabels), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var SF_GROUP_LABELS = <?= json_encode($groupLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var SF_BUCKET_LABELS = <?= json_encode($bucketLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var SF_SAMPLE_COLUMNS = <?= count(\App\Services\SampleFlowService::sampleColumns()); ?>;
    var SF_LABELS = {
        noData: "<?= _jsTranslate('No samples in this stage for the selected filters'); ?>",
        oldest: "<?= _jsTranslate('over 30 days'); ?>",
        breakdownOf: "<?= _jsTranslate('%s by'); ?>",
        samplesIn: "<?= _jsTranslate('Samples: %s'); ?>",
        allAges: "<?= _jsTranslate('all ages'); ?>",
        wholeStage: "<?= _jsTranslate('every %s'); ?>",
        exportFailed: "<?= _jsTranslate('Unable to generate the export file'); ?>"
    };

    var sfFlow = null;
    var sfStage = null;
    var sfGroup = 'lab';
    var sfSort = 'total';
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
            url: '/sample-flow/get-sample-flow.php',
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
        $('.sf-stage, .sf-exit').removeClass('is-active');
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

    function sfBucketSum(counts, keys) {
        return keys.reduce(function (carry, key) { return carry + (counts[key] || 0); }, 0);
    }

    function sfRenderFlow() {
        var registered = 0;
        var open = 0;
        var old = 0;
        var exits = 0;

        SF_STAGES.forEach(function (stage) {
            var counts = sfFlow[stage] || { total: 0 };
            registered += counts.total;
            if (stage !== 'released') {
                open += counts.total;
                old += sfBucketSum(counts, ['b3', 'b4']);
            }
            $('#count-' + stage).text(counts.total.toLocaleString());

            var oldHere = sfBucketSum(counts, ['b3', 'b4']);
            $('#old-' + stage).html(
                (stage !== 'released' && oldHere > 0)
                    ? '<strong>' + oldHere.toLocaleString() + '</strong> ' + esc(SF_LABELS.oldest)
                    : ''
            );

            var peak = Math.max.apply(null, SF_BUCKETS.map(function (b) { return counts[b] || 0; }).concat([1]));
            var bars = '';
            SF_BUCKETS.forEach(function (b) {
                var value = counts[b] || 0;
                var height = value > 0 ? Math.max(8, Math.round(value / peak * 100)) : 2;
                bars += '<div class="sf-bar ' + b + (value > 0 ? '' : ' is-empty') + '" style="height:' + height + '%;" title="'
                    + esc(value.toLocaleString()) + '"></div>';
            });
            $('#bars-' + stage).html(bars);
        });

        SF_EXITS.forEach(function (exit) {
            var counts = sfFlow[exit] || { total: 0 };
            registered += counts.total;
            exits += counts.total;
            $('#count-' + exit).text(counts.total.toLocaleString());
        });

        $('#cardRegistered').text(registered.toLocaleString());
        $('#cardOpen').text(open.toLocaleString());
        $('#cardOld').text(old.toLocaleString());
        $('#cardReleased').text(((sfFlow.released || {}).total || 0).toLocaleString());
        $('#cardExits').text(exits.toLocaleString());
    }

    function sfSelectStage(stage) {
        sfStage = stage;
        sfCloseSamples();
        $('.sf-stage, .sf-exit').removeClass('is-active');
        $('[data-stage="' + stage + '"]').addClass('is-active');
        sfLoadBreakdown();
    }

    function sfSelectGroup(group) {
        sfGroup = group;
        sfCloseSamples();
        $('#sfGroupTabs li').removeClass('active');
        $('#sfGroupTabs a[data-group="' + group + '"]').parent().addClass('active');
        sfLoadBreakdown();
    }

    function sfSortBy(key) {
        sfSort = key;
        $('#sfTable th.sf-sortable').removeClass('sf-sorted');
        $('#sfTable th[data-sort="' + key + '"]').addClass('sf-sorted');
        sfRenderBreakdown();
    }

    function sfLoadBreakdown() {
        if (!sfStage) { return; }
        $('#sfBreakdownTitle').text(SF_LABELS.breakdownOf.replace('%s', SF_STAGE_LABELS[sfStage] || sfStage) + ' ' + (SF_GROUP_LABELS[sfGroup] || sfGroup));
        $('#sfGroupHeading').text(SF_GROUP_LABELS[sfGroup] || sfGroup);
        $('#sfBreakdown').show();
        $('#sfTable tbody').html('<tr><td colspan="7" class="text-center text-muted">&hellip;</td></tr>');
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
            return (b[sfSort] || 0) - (a[sfSort] || 0) || (b.total - a.total) || String(a.label).localeCompare(String(b.label));
        });
        var totals = { total: 0 };
        SF_BUCKETS.forEach(function (b) { totals[b] = 0; });

        var html = '';
        rows.forEach(function (row) {
            var key = String(row.key === undefined || row.key === null ? '' : row.key);
            html += '<tr><td>' + esc(row.label) + '</td>'
                + '<td class="num sf-drill" data-key="' + esc(key) + '" data-bucket="" data-label="' + esc(row.label) + '">'
                + row.total.toLocaleString() + '</td>';
            totals.total += row.total;
            SF_BUCKETS.forEach(function (b) {
                var value = row[b] || 0;
                totals[b] += value;
                var heat = '';
                if (value > 0 && b === 'b4') { heat = ' hot'; }
                if (value > 0 && b === 'b3') { heat = ' warm'; }
                html += '<td class="num' + heat + (value > 0 ? ' sf-drill' : '') + '"'
                    + (value > 0 ? ' data-key="' + esc(key) + '" data-bucket="' + b + '" data-label="' + esc(row.label) + '"' : '')
                    + '>' + (value > 0 ? value.toLocaleString() : '') + '</td>';
            });
            html += '</tr>';
        });
        if (rows.length === 0) {
            html = '<tr><td colspan="7" class="text-center text-muted">' + esc(SF_LABELS.noData) + '</td></tr>';
        }
        $('#sfTable tbody').html(html);
        $('#foot-total').text(totals.total.toLocaleString());
        SF_BUCKETS.forEach(function (b) {
            $('#foot-' + b).text(totals[b] > 0 ? totals[b].toLocaleString() : '');
        });
        sfMarkDrillCell();
    }

    // Level 3: the samples behind one cell. groupKey '' with bucket '' lists
    // the whole stage; a key narrows to one breakdown row; a bucket to one age.
    function sfDrill(groupKey, bucket, label) {
        if (!sfStage) { return; }
        sfDrillSel = {
            stage: sfStage,
            groupBy: groupKey === '' && label === '' ? '' : sfGroup,
            groupKey: groupKey,
            bucket: bucket,
            label: label
        };
        sfMarkDrillCell();

        var stageLabel = SF_STAGE_LABELS[sfStage] || sfStage;
        $('#sfSamplesTitle').text(SF_LABELS.samplesIn.replace('%s', stageLabel));
        var scope = label !== '' ? (SF_GROUP_LABELS[sfGroup] || sfGroup) + ': ' + label : SF_LABELS.wholeStage.replace('%s', stageLabel.toLowerCase());
        $('#sfSamplesSubtitle').text(scope + ' · ' + (bucket !== '' ? (SF_BUCKET_LABELS[bucket] || bucket) : SF_LABELS.allAges));
        $('#sfSamples').show();
        sfLoadSamples();
        document.getElementById('sfSamples').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function sfMarkDrillCell() {
        $('#sfTable td.sf-drill').removeClass('is-active');
        if (!sfDrillSel || sfDrillSel.groupBy === '') { return; }
        $('#sfTable td.sf-drill').filter(function () {
            return $(this).data('key') === sfDrillSel.groupKey && $(this).data('bucket') === sfDrillSel.bucket;
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
            "sAjaxSource": "/sample-flow/get-sample-flow.php",
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
        $.post('/sample-flow/get-sample-flow.php', $.extend({ section: 'export' }, sfDrillParams()), function (data) {
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
            sfDrill(String($(this).data('key')), String($(this).data('bucket')), String($(this).data('label')));
        });

        sfApplyFilters();
    });
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
