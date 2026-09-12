<?php

use App\Services\CommonService;
use App\Services\FacilitiesService;
use App\Services\GeoLocationsService;
use App\Registries\ContainerRegistry;
use App\Services\QualityMonitoringService;

$title = _translate("EID Quality Monitoring");
require_once APPLICATION_PATH . '/header.php';

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

$provinces = $geolocationService->getProvinces("yes");
$healthFacilities = $facilitiesService->getHealthFacilities('eid');
$testingLabs = $facilitiesService->getTestingLabs('eid');
$partners = $general->getImplementationPartners();

/** @var QualityMonitoringService $qaService */
$qaService = ContainerRegistry::get(QualityMonitoringService::class);
$instrumentsInUse = array_keys($qaService->instrumentsInUse());

$columns = QualityMonitoringService::sampleColumns();
$cascadeLabels = QualityMonitoringService::cascadeLabels();
$cascadeHints = QualityMonitoringService::cascadeHints();
$groupingLabels = QualityMonitoringService::groupingLabels();
// The breakdown table has a column per pending stage, in cascade order.
$breakdownStages = QualityMonitoringService::CASCADE['pending'];

// Which side of the workflow this user answers for. A testing-lab account is
// working the lab queue; everyone else is looking at it from the clinic and
// implementing-partner side. It decides which samples they may add notes to --
// every other sample stays fully readable, because each side needs to see what
// the other has already said before adding anything.
$userSide = (($_SESSION['accessType'] ?? '') === 'testing-lab') ? 'lab' : 'clinic';

$sideLabels = [
    'lab' => _translate('Lab QA Manager'),
    'clinic' => _translate('Implementing Partner / Clinic'),
];

$noteReasons = [
    'clinic' => QualityMonitoringService::noteReasons('clinic'),
    'lab' => QualityMonitoringService::noteReasons('lab'),
];

// What the side holding a sample is being asked, by the stage the sample is in.
$labPrompt = _translate('Why is there no result for this sample yet?');
$notePrompts = [
    'atFacility' => _translate('Why has this sample not reached the testing lab yet?'),
    'atLab' => $labPrompt,
    'awaitingApproval' => $labPrompt,
    'awaitingRelease' => _translate('Why has this result not reached the facility yet?'),
];

// The question and the explanation shown above the grid for each card.
$labDetail = _translate('A lab is holding these samples and no approved result has come out of them yet. Only the lab side can say what is holding them.');
$nodePrompts = [
    'pending' => [
        'question' => '',
        'detail' => _translate('Every pending sample, whichever side is holding it. Each side can add notes only to the samples it is holding.'),
    ],
    'atFacility' => [
        'question' => $notePrompts['atFacility'],
        'detail' => _translate('These samples were registered at a collection point and no lab has recorded receiving them. Only the clinic side can say what is holding them.'),
    ],
    'atTestingLab' => ['question' => $labPrompt, 'detail' => $labDetail],
    'atLab' => ['question' => $labPrompt, 'detail' => $labDetail],
    'awaitingApproval' => ['question' => $labPrompt, 'detail' => $labDetail],
    'awaitingRelease' => [
        'question' => $notePrompts['awaitingRelease'],
        'detail' => _translate('The lab has an approved result for these samples, and it has not been printed, sent or downloaded. Only the lab side can say what is holding them.'),
    ],
];

// One card of the cascade. The layout is fixed below; what each card counts
// comes from QualityMonitoringService::CASCADE. The card itself lists all its
// samples, each part inside it lists that part, and the overdue line at the
// bottom lists only the overdue ones. The overdue label quotes the chosen
// limit, which can change without a reload, so the page fills it in.
$card = static function (string $node, string $class = '', array $parts = []) use ($cascadeLabels, $cascadeHints): string {
    $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES);
    $key = $esc($node);
    $html = '<div class="qa-card ' . $esc($class) . '" data-node="' . $key . '">'
        . '<button type="button" class="qa-card-main" data-node="' . $key . '" aria-pressed="false">'
        . '<span class="qa-card-value" id="qa-node-' . $key . '">&ndash;</span>'
        . '<span class="qa-card-label">' . $esc($cascadeLabels[$node]) . '</span>'
        . '<span class="qa-card-hint">' . $esc($cascadeHints[$node]) . '</span>'
        . '</button>';

    if ($parts !== []) {
        $html .= '<div class="qa-card-parts">';
        foreach ($parts as $part) {
            $partKey = $esc($part);
            $html .= '<button type="button" class="qa-part" data-node="' . $partKey . '" aria-pressed="false"'
                . ' title="' . $esc($cascadeHints[$part]) . '">'
                . '<span class="qa-part-value" id="qa-node-' . $partKey . '">&ndash;</span>'
                . '<span class="qa-part-label">' . $esc($cascadeLabels[$part]) . '</span>'
                . '<span class="qa-part-overdue"><b id="qa-node-overdue-' . $partKey . '">&ndash;</b> '
                . $esc(_translate('overdue')) . '</span>'
                . '</button>';
        }
        $html .= '</div>';
    }

    return $html
        . '<button type="button" class="qa-card-overdue" data-node="' . $key . '">'
        . '<span class="qa-overdue-label"></span>: <b id="qa-node-overdue-' . $key . '">&ndash;</b>'
        . '</button>'
        . '</div>';
};

$currentUser = trim((string) ($_SESSION['userName'] ?? ''));
$currentRole = trim((string) ($_SESSION['roleName'] ?? $_SESSION['roleCode'] ?? ''));
?>
<link rel="stylesheet" media="all" type="text/css" href="/assets/css/tom-select.css" />
<style>
    #qaModule .qa-preview {
        border-left: 4px solid #f0ad4e;
        background: #fcf8e3;
        color: #6b5626;
        padding: 10px 14px;
        margin-bottom: 12px;
        font-size: 13px;
        border-radius: 2px;
    }

    #qaModule .qa-filters .form-group {
        margin-bottom: 12px;
    }

    #qaModule .qa-filters label {
        font-size: 12px;
        color: #666;
        font-weight: 600;
        display: block;
        margin-bottom: 2px;
    }

    /* Select2 sizes itself off the original control, which is display:none here. */
    #qaModule .qa-filters .select2-container {
        width: 100% !important;
    }

    /* Tom Select copies the `.form-control` class onto its wrapper, which then
       draws a second box around the real control. Neutralise the outer box.
       Same fix as reports/sample-referral-network.php. */
    #qaNoteModal .ts-wrapper.form-control,
    #qaNoteModal .ts-wrapper.form-select {
        padding: 0;
        height: auto;
        border: 0;
        box-shadow: none;
    }

    /* What is left is Tom Select's own control, lined up with the Bootstrap
       fields above and below it in the form. */
    #qaNoteModal .ts-wrapper .ts-control {
        border: 1px solid #d2d6de;
        border-radius: 0;
        min-height: 34px;
        padding: 6px 12px;
    }

    #qaNoteModal .ts-wrapper.focus .ts-control {
        border-color: #3c8dbc;
        box-shadow: none;
    }

    #qaModule .qa-filter-actions {
        margin-bottom: 0;
    }

    #qaModule .qa-filter-actions .btn {
        margin-right: 4px;
    }

    #qaModule .qa-viewing-as {
        border-top: 1px dashed #ddd;
        margin-top: 8px;
        padding-top: 10px;
        font-size: 13px;
    }

    #qaModule .qa-viewing-as .text-muted {
        font-size: 12px;
    }

    /* ------------------------------------------------------------ cascade */

    /* The total on the left, and beside it the three places a pending sample
       can be held. The three add up to the total. */
    #qaModule .qa-flow {
        display: grid;
        grid-template-columns: minmax(190px, 1fr) minmax(0, 3.2fr);
        gap: 12px;
        align-items: stretch;
    }

    #qaModule .qa-flow-branches {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        align-items: stretch;
    }

    #qaModule .qa-card {
        display: flex;
        flex-direction: column;
        min-width: 0;
        background: #fff;
        border: 1px solid #e4e7ea;
        border-top: 3px solid #b8c2ca;
        border-radius: 3px;
        transition: box-shadow 0.15s, background-color 0.15s;
    }

    /* The stripe says which side holds the samples, in the colours of the bar. */
    #qaModule .qa-card.qa-accent-clinic {
        border-top-color: #00a65a;
    }

    #qaModule .qa-card.qa-accent-lab {
        border-top-color: #3c8dbc;
    }

    #qaModule .qa-card.qa-accent-release {
        border-top-color: #e08e0b;
    }

    #qaModule .qa-card-total {
        background: #f4f7f9;
        border-top-color: #4a5157;
    }

    #qaModule .qa-card:hover {
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
    }

    /* A card is buttons: the card itself lists its samples, a part lists that
       part, and the overdue line lists only the overdue ones. */
    #qaModule .qa-card-main,
    #qaModule .qa-card-overdue,
    #qaModule .qa-part {
        display: block;
        width: 100%;
        text-align: left;
        background: none;
        border: 0;
        cursor: pointer;
    }

    #qaModule .qa-card-main {
        padding: 12px 14px 8px;
    }

    #qaModule .qa-card-value {
        display: block;
        font-size: 26px;
        font-weight: 600;
        line-height: 1.1;
        color: #263238;
        font-variant-numeric: tabular-nums;
    }

    #qaModule .qa-card-total .qa-card-value {
        font-size: 34px;
    }

    #qaModule .qa-card-label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #333;
        margin-top: 4px;
    }

    #qaModule .qa-card-hint {
        display: block;
        font-size: 11px;
        line-height: 1.4;
        color: #7a848c;
        margin-top: 3px;
    }

    /* The two parts of the testing lab, as compact rows inside its card. */
    #qaModule .qa-card-parts {
        display: flex;
        flex-direction: column;
        gap: 4px;
        padding: 0 10px 8px;
    }

    #qaModule .qa-part {
        display: flex;
        align-items: baseline;
        gap: 8px;
        padding: 5px 8px;
        background: #f5f7f9;
        border: 1px solid transparent;
        border-radius: 3px;
        font-size: 12px;
        color: #444;
    }

    #qaModule .qa-part:hover {
        border-color: #3c8dbc;
    }

    #qaModule .qa-part-value {
        min-width: 2.6em;
        font-weight: 600;
        color: #263238;
        font-variant-numeric: tabular-nums;
    }

    #qaModule .qa-part-label {
        flex: 1 1 auto;
        min-width: 0;
    }

    #qaModule .qa-part-overdue {
        font-size: 11px;
        color: #7a848c;
        white-space: nowrap;
    }

    #qaModule .qa-part-overdue b,
    #qaModule .qa-card-overdue b {
        color: #c9302c;
    }

    /* Pinned to the bottom of the card, so the overdue lines line up across. */
    #qaModule .qa-card-overdue {
        margin-top: auto;
        padding: 8px 14px 10px;
        border-top: 1px solid #eef1f3;
        font-size: 12px;
        color: #7a848c;
    }

    #qaModule .qa-card-overdue:hover .qa-overdue-label {
        text-decoration: underline;
    }

    #qaModule .qa-card-main:focus-visible,
    #qaModule .qa-card-overdue:focus-visible,
    #qaModule .qa-part:focus-visible {
        outline: 2px solid #3c8dbc;
        outline-offset: -2px;
    }

    #qaModule .qa-card.is-active {
        background: #f2f8fc;
        box-shadow: 0 0 0 2px #3c8dbc;
    }

    #qaModule .qa-part.is-active {
        background: #e3f0f8;
        border-color: #3c8dbc;
    }

    /* Anything holding nothing is dimmed and does not respond to a click. */
    #qaModule .qa-card.is-empty,
    #qaModule .qa-part.is-empty {
        opacity: 0.55;
    }

    #qaModule .qa-card.is-empty button,
    #qaModule .qa-part.is-empty {
        cursor: default;
    }

    #qaModule .qa-part.is-empty:hover {
        border-color: transparent;
    }

    /* How the total splits, one segment per stage. */
    #qaModule .qa-flow-bar {
        display: flex;
        height: 10px;
        margin-top: 14px;
        border-radius: 5px;
        overflow: hidden;
        background: #eceff1;
    }

    #qaModule .qa-bar-seg {
        flex: 0 1 0;
        height: 100%;
        transition: flex-grow 0.3s;
    }

    #qaModule .qa-seg-atFacility {
        background: #00a65a;
    }

    #qaModule .qa-seg-atLab {
        background: #3c8dbc;
    }

    #qaModule .qa-seg-awaitingApproval {
        background: #8fbfdc;
    }

    #qaModule .qa-seg-awaitingRelease {
        background: #e08e0b;
    }

    #qaModule .qa-flow-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 18px;
        margin-top: 8px;
        font-size: 12px;
        color: #666;
    }

    #qaModule .qa-legend-item i {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 2px;
        margin-right: 4px;
        vertical-align: -1px;
    }

    #qaModule .qa-legend-item b {
        margin-left: 2px;
        color: #333;
    }

    @media (max-width: 991px) {
        #qaModule .qa-flow {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    @media (max-width: 767px) {
        #qaModule .qa-flow-branches {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    /* ------------------------------------------------------ overdue limit */

    #qaModule .qa-overdue-control {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 6px 8px;
        font-size: 13px;
    }

    #qaModule .qa-overdue-control label {
        margin: 0;
    }

    #qaModule .qa-overdue-control input {
        display: inline-block;
        width: 72px;
    }

    #qaModule .qa-overdue-control .fa-circle-info {
        cursor: help;
    }

    /* ---------------------------------------------------------- breakdown */

    #qaModule .qa-breakdown-head {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 10px 16px;
        margin-bottom: 8px;
    }

    #qaModule .qa-head-group {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px 12px;
    }

    #qaModule .qa-breakdown-head .box-title {
        margin: 0;
    }

    #qaModule table.qa-breakdown {
        font-size: 13px;
    }

    #qaModule table.qa-breakdown th {
        vertical-align: bottom;
    }

    #qaModule table.qa-breakdown a.qa-drill {
        font-weight: 600;
        cursor: pointer;
    }

    #qaModule .qa-zero {
        color: #b3bac0;
    }

    #qaModule table.qa-breakdown a.qa-overdue-count {
        color: #c9302c;
    }

    /* ---------------------------------------------------------------- grid */

    #qaModule .qa-drill-chip {
        display: inline-block;
        background: #eef6fb;
        border: 1px solid #bcd9ea;
        color: #2b6a8f;
        border-radius: 14px;
        padding: 3px 12px;
        font-size: 12px;
        margin-bottom: 10px;
    }

    #qaModule .qa-drill-chip a {
        margin-left: 8px;
        cursor: pointer;
    }

    #qaModule .qa-toolbar {
        display: flex;
        align-items: center;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 10px;
    }

    #qaModule .qa-toolbar .qa-selection-count {
        font-size: 12px;
        color: #7a848c;
    }

    #qaModule .qa-prompt {
        font-size: 13px;
        color: #666;
        margin-bottom: 10px;
    }

    #qaModule table.qa-grid {
        font-size: 13px;
    }

    #qaModule table.qa-grid td,
    #qaModule table.qa-grid th {
        vertical-align: middle;
    }

    #qaModule .qa-days {
        display: inline-block;
        min-width: 34px;
        text-align: center;
        padding: 1px 6px;
        border-radius: 10px;
        font-weight: 600;
        background: #eef1f3;
        color: #4a5157;
    }

    #qaModule .qa-days.is-overdue {
        background: #f8d7d5;
        color: #a02622;
    }

    #qaModule .qa-secondary {
        display: block;
        font-size: 11px;
        color: #99a2a9;
    }

    /* A recorded status that the milestones contradict. Amber, not red: the
       sample is not lost, the record is wrong. */
    #qaModule .qa-conflict {
        display: block;
        font-size: 11px;
        color: #a0740c;
        cursor: help;
    }

    #qaModule .qa-instruments {
        display: block;
        font-size: 11px;
        color: #7a848c;
        margin-top: 2px;
        cursor: help;
    }

    #qaModule .qa-instruments em {
        margin-right: 3px;
        opacity: 0.7;
    }

    #qaModule .qa-note-cell a {
        cursor: pointer;
    }

    #qaModule .qa-note-count {
        display: inline-block;
        background: #3c8dbc;
        color: #fff;
        border-radius: 10px;
        padding: 0 7px;
        font-size: 11px;
        font-weight: 600;
        margin-right: 4px;
    }

    #qaModule .qa-note-none {
        color: #b3bac0;
        font-size: 12px;
    }

    #qaModule .qa-note-add {
        display: inline-block;
        font-size: 12px;
        cursor: pointer;
        white-space: nowrap;
    }

    #qaModule .qa-note-cell a + .qa-note-add {
        margin-left: 10px;
    }

    /* One note in a thread. The bar on the left says which side wrote it. */
    .qa-note {
        border: 1px solid #e4e7ea;
        border-left: 4px solid #3c8dbc;
        border-radius: 3px;
        padding: 10px 12px;
        margin-bottom: 10px;
        background: #fff;
    }

    .qa-note.qa-note-clinic {
        border-left-color: #00a65a;
    }

    .qa-note .qa-note-head {
        display: flex;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 4px 12px;
        margin-bottom: 4px;
    }

    .qa-note .qa-note-reason {
        font-weight: 600;
        color: #333;
    }

    .qa-note .qa-note-meta {
        font-size: 11px;
        color: #99a2a9;
        white-space: nowrap;
    }

    .qa-note .qa-note-text {
        font-size: 13px;
        color: #555;
        white-space: pre-wrap;
    }

    .qa-side-badge {
        display: inline-block;
        font-size: 10px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        padding: 1px 6px;
        border-radius: 2px;
        background: #dceaf3;
        color: #2b6a8f;
        margin-right: 6px;
    }

    .qa-side-badge.qa-side-clinic {
        background: #d9f0e3;
        color: #1f7a4d;
    }

    .qa-sample-chips .label {
        display: inline-block;
        margin: 0 4px 4px 0;
        font-weight: normal;
        font-size: 11px;
    }

    .qa-readonly-hint {
        font-size: 12px;
        color: #99a2a9;
        margin-top: 6px;
    }
</style>

<div class="content-wrapper" id="qaModule">
    <section class="content-header">
        <h1><em class="fa-solid fa-clipboard-check"></em>
            <?= _htmlTranslate("EID Quality Monitoring"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
                    <?= _htmlTranslate("Home"); ?>
                </a></li>
            <li class="active"><?= _htmlTranslate("EID Quality Monitoring"); ?></li>
        </ol>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-body">

                        <div class="qa-preview">
                            <strong><?= _htmlTranslate('Preview'); ?>:</strong>
                            <?= _htmlTranslate('the sample list, the counts and the filters below all run on live data. Notes are the one part that is not built yet: a note added here is shown so the workflow can be reviewed, and it disappears when the page is reloaded.'); ?>
                        </div>

                        <p class="text-muted" id="qa-description">
                            <?= _htmlTranslate('Every EID sample whose result has not yet reached the facility, shown by where it is held. Select a card, or a number in the breakdown, to list those samples.'); ?>
                        </p>

                        <div class="row qa-filters" aria-describedby="qa-description">
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="dateRange"><?= _htmlTranslate('Sample Collection Period'); ?></label>
                                    <input type="text" id="dateRange" class="form-control daterangefield" />
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="provinceId"><?= _htmlTranslate('Province/State'); ?></label>
                                    <select id="provinceId" class="form-control">
                                        <?= $general->generateSelectOptions($provinces, null, _translate('-- All --')); ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="districtId"><?= _htmlTranslate('District/County'); ?></label>
                                    <select id="districtId" class="form-control">
                                        <option value=""><?= _htmlTranslate('-- All --'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="partnerId"><?= _htmlTranslate('Implementing Partner'); ?></label>
                                    <select id="partnerId" class="form-control">
                                        <option value=""><?= _htmlTranslate('-- All --'); ?></option>
                                        <?php foreach ($partners as $partner) { ?>
                                            <option value="<?= (int) $partner['i_partner_id']; ?>">
                                                <?= htmlspecialchars((string) $partner['i_partner_name'], ENT_QUOTES); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <?php // The two multi-selects grow taller as chips are added, so they share a
                            // row of their own rather than dragging the single-line fields out of line. ?>
                            <div class="clearfix visible-md-block visible-lg-block"></div>

                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="facilityId"><?= _htmlTranslate('Collection Facility'); ?></label>
                                    <select id="facilityId" class="form-control" multiple="multiple">
                                        <?= $general->generateSelectOptions($healthFacilities); ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="labId"><?= _htmlTranslate('Testing Lab'); ?></label>
                                    <select id="labId" class="form-control" multiple="multiple">
                                        <?= $general->generateSelectOptions($testingLabs); ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="bucket"><?= _htmlTranslate('Waiting For'); ?></label>
                                    <select id="bucket" class="form-control">
                                        <option value=""><?= _htmlTranslate('-- Any length of time --'); ?></option>
                                        <option value="b0"><?= _htmlTranslate('0 to 7 days'); ?></option>
                                        <option value="b1"><?= _htmlTranslate('8 to 14 days'); ?></option>
                                        <option value="b2"><?= _htmlTranslate('15 to 30 days'); ?></option>
                                        <option value="b3"><?= _htmlTranslate('31 to 60 days'); ?></option>
                                        <option value="b4"><?= _htmlTranslate('Over 60 days'); ?></option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6">
                                <div class="form-group">
                                    <label for="instrument"><?= _htmlTranslate('Instrument'); ?></label>
                                    <select id="instrument" class="form-control">
                                        <option value=""><?= _htmlTranslate('-- All Instruments --'); ?></option>
                                        <?php foreach ($instrumentsInUse as $instrumentName) { ?>
                                            <option value="<?= htmlspecialchars($instrumentName, ENT_QUOTES); ?>">
                                                <?= htmlspecialchars($instrumentName, ENT_QUOTES); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-xs-12">
                                <div class="form-group qa-filter-actions">
                                    <button type="button" class="btn btn-success" onclick="qaApplyFilters();">
                                        <em class="fa-solid fa-magnifying-glass"></em>
                                        <?= _htmlTranslate('Search'); ?>
                                    </button>
                                    <button type="button" class="btn btn-default" onclick="qaResetFilters();">
                                        <?= _htmlTranslate('Reset'); ?>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="qa-viewing-as">
                            <strong><?= _htmlTranslate('Viewing as'); ?>:</strong>
                            <div class="btn-group" data-toggle="buttons" style="margin:0 10px;">
                                <?php foreach ($sideLabels as $sideKey => $sideLabel) { ?>
                                    <label class="btn btn-default btn-sm <?= $sideKey === $userSide ? 'active' : ''; ?>">
                                        <input type="radio" name="qaSide" value="<?= $sideKey; ?>"
                                            <?= $sideKey === $userSide ? 'checked="checked"' : ''; ?> />
                                        <?= htmlspecialchars($sideLabel, ENT_QUOTES); ?>
                                    </label>
                                <?php } ?>
                            </div>
                            <span class="text-muted">
                                <?= _htmlTranslate('In the finished module this comes from the signed-in role. It is a switch here so both sides of the workflow can be seen in one sitting.'); ?>
                            </span>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <?php // Where every pending sample is held. The three cards beside the total add up to it, and the bar shows the split. ?>
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-body" id="qaCascade">
                        <div class="qa-flow">
                            <?= $card('pending', 'qa-card-total'); ?>
                            <div class="qa-flow-branches">
                                <?= $card('atFacility', 'qa-accent-clinic'); ?>
                                <?= $card('atTestingLab', 'qa-accent-lab', ['atLab', 'awaitingApproval']); ?>
                                <?= $card('awaitingRelease', 'qa-accent-release'); ?>
                            </div>
                        </div>
                        <div class="qa-flow-bar" id="qaFlowBar" role="img">
                            <?php foreach ($breakdownStages as $stage) { ?>
                                <span class="qa-bar-seg qa-seg-<?= $stage; ?>" data-seg="<?= $stage; ?>"></span>
                            <?php } ?>
                        </div>
                        <div class="qa-flow-legend" aria-hidden="true">
                            <?php foreach ($breakdownStages as $stage) { ?>
                                <span class="qa-legend-item">
                                    <i class="qa-seg-<?= $stage; ?>"></i><?= htmlspecialchars($cascadeLabels[$stage], ENT_QUOTES); ?>
                                    <b id="qa-share-<?= $stage; ?>">&ndash;</b>
                                </span>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <?php // The same pending samples, one row per lab, facility, province, district or partner. ?>
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-body">
                        <div class="qa-breakdown-head">
                            <div class="qa-head-group">
                                <h3 class="box-title"><?= _htmlTranslate('Pending samples by'); ?></h3>
                                <div class="btn-group btn-group-sm" id="qaGroupBy" role="group">
                                    <?php foreach ($groupingLabels as $groupKey => $groupLabel) { ?>
                                        <button type="button" class="btn btn-default <?= $groupKey === 'lab' ? 'active' : ''; ?>"
                                            data-group-by="<?= $groupKey; ?>">
                                            <?= htmlspecialchars($groupLabel, ENT_QUOTES); ?>
                                        </button>
                                    <?php } ?>
                                </div>
                            </div>
                            <?php // Not a filter: it decides what is counted as overdue on the cards, in this table and in the list. ?>
                            <div class="qa-overdue-control">
                                <label for="qaOverdueDays"><?= _htmlTranslate('Overdue after'); ?></label>
                                <div class="btn-group btn-group-sm" role="group" id="qaOverduePresets">
                                    <?php foreach (QualityMonitoringService::OVERDUE_PRESETS as $presetDays) { ?>
                                        <button type="button" class="btn btn-default" data-days="<?= (int) $presetDays; ?>">
                                            <?= (int) $presetDays; ?>
                                        </button>
                                    <?php } ?>
                                </div>
                                <input type="number" id="qaOverdueDays" class="form-control input-sm" min="1"
                                    max="<?= QualityMonitoringService::MAX_OVERDUE_DAYS; ?>" step="1"
                                    value="<?= QualityMonitoringService::DEFAULT_OVERDUE_DAYS; ?>" />
                                <span><?= _htmlTranslate('days'); ?></span>
                                <em class="fa-solid fa-circle-info text-muted"
                                    title="<?= htmlspecialchars(_translate('Turnaround targets differ by country and by test. Pick the limit that applies, or type any number of days. It sets the overdue counts on the cards above, in this table and in the sample list.'), ENT_QUOTES); ?>"></em>
                            </div>
                        </div>
                        <p class="qa-prompt">
                            <?= _htmlTranslate('Select a number to list those samples below. The filters above apply to this table too.'); ?>
                        </p>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped qa-breakdown" id="qaBreakdown">
                                <thead>
                                    <tr>
                                        <th id="qaBreakdownGroupHeading"><?= htmlspecialchars($groupingLabels['lab'], ENT_QUOTES); ?></th>
                                        <?php foreach ($breakdownStages as $stage) { ?>
                                            <th class="text-right"><?= htmlspecialchars($cascadeLabels[$stage], ENT_QUOTES); ?></th>
                                        <?php } ?>
                                        <th class="text-right"><?= htmlspecialchars($cascadeLabels['pending'], ENT_QUOTES); ?></th>
                                        <th class="text-right" id="qaOverdueHeading"></th>
                                        <th><?= _htmlTranslate('Oldest Collected'); ?></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xs-12">
                <div class="box" id="qaGridBox">
                    <div class="box-header with-border">
                        <h3 class="box-title" id="qaGridTitle"><?= htmlspecialchars($cascadeLabels['pending'], ENT_QUOTES); ?></h3>
                    </div>
                    <div class="box-body">

                        <p class="qa-prompt">
                            <strong id="qaNodeQuestion"></strong>
                            <span id="qaNodeDetail"></span>
                        </p>

                        <div class="qa-drill-chip" id="qaDrillChip" style="display:none;">
                            <em class="fa-solid fa-filter"></em>
                            <span id="qaDrillText"></span>
                            <a onclick="qaClearGroup(true);">
                                <em class="fa-solid fa-xmark"></em> <?= _htmlTranslate('Show all'); ?>
                            </a>
                        </div>

                        <div class="qa-toolbar">
                            <button type="button" class="btn btn-primary btn-sm" id="qaAddNote" disabled="disabled"
                                onclick="qaOpenAddNote();">
                                <em class="fa-solid fa-note-sticky"></em>
                                <?= _htmlTranslate('Add note to selected'); ?>
                            </button>
                            <span class="qa-selection-count" id="qa-selection"></span>
                            <span style="flex:1 1 auto;"></span>
                            <label class="checkbox-inline" style="margin:0 8px 0 0;">
                                <input type="checkbox" id="qaOverdueOnly" />
                                <span class="qa-only-overdue-label"></span>
                            </label>
                            <button type="button" class="btn btn-success btn-sm" onclick="qaExport();">
                                <em class="fa-solid fa-cloud-arrow-down"></em>
                                <?= _htmlTranslate('Export to Excel'); ?>
                            </button>
                        </div>

                        <div class="qa-readonly-hint" id="qa-locked" style="display:none;">
                            <em class="fa-solid fa-lock"></em>
                            <?= _htmlTranslate('These samples are held by the other side of the workflow. Their notes can be read here, but only that side can add to them.'); ?>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-striped qa-grid" id="qaTable">
                                <thead>
                                    <tr>
                                        <?php foreach ($columns as $key => $column) { ?>
                                            <th>
                                                <?php if ($key === 'select') { ?>
                                                    <input type="checkbox" class="qa-check-all"
                                                        title="<?= _translate('Select every sample on this page'); ?>" />
                                                <?php } else { ?>
                                                    <?= htmlspecialchars((string) $column['label'], ENT_QUOTES); ?>
                                                <?php } ?>
                                            </th>
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
    </section>
</div>

<?php // Add a note against one or many selected samples. ?>
<div class="modal fade" id="qaNoteModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title"><?= _htmlTranslate('Add note'); ?></h4>
            </div>
            <div class="modal-body">
                <p><strong id="qaNotePrompt"></strong></p>

                <div class="form-group">
                    <label><?= _htmlTranslate('Applies to'); ?>
                        <span id="qaNoteScope" class="text-muted"></span>
                    </label>
                    <div class="qa-sample-chips" id="qaNoteChips"></div>
                </div>

                <div class="form-group">
                    <label for="qaNoteReason"><?= _htmlTranslate('Reason'); ?>
                        <span class="mandatory">*</span>
                    </label>
                    <select id="qaNoteReason" class="form-control"></select>
                </div>

                <div class="form-group">
                    <label for="qaNoteText"><?= _htmlTranslate('Details'); ?>
                        <span class="text-muted" style="font-weight:normal;">
                            (<?= _htmlTranslate('what exactly is holding it, and what is being done'); ?>)
                        </span>
                    </label>
                    <textarea id="qaNoteText" class="form-control" rows="3"
                        placeholder="<?= _translate('For example: the extraction kits arrived on the 12th and the run is scheduled for Friday.'); ?>"></textarea>
                </div>

                <div class="form-group">
                    <label for="qaNoteExpected"><?= _htmlTranslate('Expected to be resolved by'); ?>
                        <span class="text-muted" style="font-weight:normal;">(<?= _htmlTranslate('optional'); ?>)</span>
                    </label>
                    <input type="text" id="qaNoteExpected" class="form-control" style="max-width:220px;"
                        autocomplete="off" readonly="readonly" />
                </div>

                <p class="qa-readonly-hint">
                    <em class="fa-solid fa-circle-info"></em>
                    <?= _htmlTranslate('Saved against the author name and the time it is saved. A note cannot be edited or removed afterwards; add a follow-up note instead.'); ?>
                    <span id="qaNoteAttribution" class="text-muted"></span>
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <?= _htmlTranslate('Cancel'); ?>
                </button>
                <button type="button" class="btn btn-primary" onclick="qaSaveNote();">
                    <?= _htmlTranslate('Save note'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<?php // Everything both sides have said about one sample. ?>
<div class="modal fade" id="qaThreadModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" id="qaThreadTitle"></h4>
                <div class="text-muted" id="qaThreadSubtitle" style="font-size:12px;"></div>
            </div>
            <div class="modal-body" id="qaThreadBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="qaThreadAdd" onclick="qaAddFromThread();">
                    <em class="fa-solid fa-note-sticky"></em>
                    <?= _htmlTranslate('Add a note'); ?>
                </button>
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <?= _htmlTranslate('Close'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<?php $jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT; ?>
<script src="/assets/js/tom-select.complete.min.js"></script>
<script type="text/javascript">
    var QA_URL = '/eid/qa/get-qa-monitoring-data.php';
    var QA_COLUMNS = <?= json_encode($columns, $jsonFlags); ?>;
    var QA_CASCADE = <?= json_encode(QualityMonitoringService::CASCADE); ?>;
    var QA_CASCADE_LABELS = <?= json_encode($cascadeLabels, $jsonFlags); ?>;
    var QA_NODE_PROMPTS = <?= json_encode($nodePrompts, $jsonFlags); ?>;
    var QA_GROUPING_LABELS = <?= json_encode($groupingLabels, $jsonFlags); ?>;
    var QA_BREAKDOWN_STAGES = <?= json_encode($breakdownStages); ?>;
    var QA_REASONS = <?= json_encode($noteReasons, $jsonFlags); ?>;
    var QA_PROMPTS = <?= json_encode($notePrompts, $jsonFlags); ?>;
    var QA_SIDE_LABELS = <?= json_encode($sideLabels, $jsonFlags); ?>;
    var QA_OVERDUE_DEFAULT = <?= QualityMonitoringService::DEFAULT_OVERDUE_DAYS; ?>;
    var QA_OVERDUE_MAX = <?= QualityMonitoringService::MAX_OVERDUE_DAYS; ?>;
    // Where the chosen limit is remembered, in this browser only.
    var QA_OVERDUE_STORAGE_KEY = 'intelis.eidQualityMonitoring.overdueDays';
    // How many instrument names fit under a lab name before the rest go on hover.
    var QA_LAB_INSTRUMENTS_SHOWN = 3;
    var QA_USER = <?= json_encode($currentUser !== '' ? $currentUser : _translate('You'), $jsonFlags); ?>;
    var QA_ROLE = <?= json_encode($currentRole, $jsonFlags); ?>;

    var QA_LABELS = {
        noData: "<?= _jsTranslate('No samples are pending here for the selected filters'); ?>",
        noBreakdown: "<?= _jsTranslate('No pending samples for the selected filters'); ?>",
        overdue: "<?= _jsTranslate('Overdue (%s+ days)'); ?>",
        onlyOverdue: "<?= _jsTranslate('Only overdue (%s+ days)'); ?>",
        overdueRange: "<?= _jsTranslate('Enter a whole number of days from 1 to %s'); ?>",
        selected: "<?= _jsTranslate('%s selected'); ?>",
        andMore: "<?= _jsTranslate('and %s more'); ?>",
        showingGroup: "<?= _jsTranslate('%s: %s'); ?>",
        chooseReason: "<?= _jsTranslate('-- Choose a reason --'); ?>",
        mixedPrompt: "<?= _jsTranslate('What is holding these samples?'); ?>",
        reasonRequired: "<?= _jsTranslate('Choose a reason before saving'); ?>",
        detailsRequired: "<?= _jsTranslate('Describe the reason in the details box'); ?>",
        notSaved: "<?= _jsTranslate('Notes are not saved yet. This note is shown here so the workflow can be reviewed, and it disappears when the page is reloaded.'); ?>",
        noNotes: "<?= _jsTranslate('Nothing has been recorded about this sample yet.'); ?>",
        expectedBy: "<?= _jsTranslate('Expected to be resolved by %s'); ?>",
        exportFailed: "<?= _jsTranslate('Unable to generate the export file'); ?>",
        days: "<?= _jsTranslate('days'); ?>",
        atThisStep: "<?= _jsTranslate('%s at this step'); ?>",
        sample: "<?= _jsTranslate('Sample'); ?>",
        addNote: "<?= _jsTranslate('Add note'); ?>",
        noneYet: "<?= _jsTranslate('No notes yet'); ?>",
        dob: "<?= _jsTranslate('DoB %s'); ?>",
        age: "<?= _jsTranslate('Age %s'); ?>",
        conflictHint: "<?= _jsTranslate('The recorded status does not match what the sample has actually been through. The stage on the left is read from the dates on the record. This one needs correcting rather than explaining.'); ?>"
    };

    // Notes live only in the browser for now: the saving side of this module is
    // not built. Keyed by record id so a note survives paging, sorting and
    // switching cards within the session.
    var qaNotes = {};
    var qaTable = null;
    var qaBreakdownTable = null;
    // Which rows are ticked. Cleared whenever the grid redraws, so a note is
    // never applied to a sample the user can no longer see.
    var qaSelection = [];
    var qaRowCache = {};
    var qaSide = "<?= $userSide; ?>";
    var qaNoteTarget = { side: null, ids: [] };
    // The card whose samples the grid lists, and the breakdown row it was
    // narrowed to, if any.
    var qaNode = 'pending';
    var qaBreakdownBy = 'lab';
    var qaGroup = { by: '', key: 0, label: '' };
    var qaBreakdownLabels = {};
    var qaSummary = null;
    // The overdue limit in days, and whether the grid lists only what is past it.
    var qaOverdueDays = QA_OVERDUE_DEFAULT;
    var qaOverdueOnly = false;

    // Which side owns a sample, read off the stage it is in. A sample moves
    // between the two over its life, which is why a thread can hold notes from
    // both sides even though each side only ever writes its own.
    var QA_STAGE_VIEW = {};
    (function (views) {
        Object.keys(views).forEach(function (view) {
            views[view].forEach(function (stage) { QA_STAGE_VIEW[stage] = view; });
        });
    })(<?= json_encode(QualityMonitoringService::VIEWS); ?>);

    function qaOwns(stage) {
        return QA_STAGE_VIEW[stage] === qaSide;
    }

    // Safe inside element content and inside a quoted attribute alike.
    function qaEsc(value) {
        return $('<div>').text(value === null || value === undefined ? '' : String(value)).html()
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function qaSprintf(template, value) {
        return String(template).replace('%s', value);
    }

    function qaFilters() {
        return {
            dateRange: $('#dateRange').val() || '',
            provinceId: $('#provinceId').val() || '',
            districtId: $('#districtId').val() || '',
            facilityId: ($('#facilityId').val() || []).join(','),
            labId: ($('#labId').val() || []).join(','),
            partnerId: $('#partnerId').val() || '',
            bucket: $('#bucket').val() || '',
            instrument: $('#instrument').val() || '',
            overdueDays: qaOverdueDays
        };
    }

    // The grid and its export also carry the card and the breakdown row.
    function qaGridParams() {
        return $.extend(qaFilters(), {
            node: qaNode,
            groupBy: qaGroup.by,
            groupKey: qaGroup.key,
            overdueOnly: qaOverdueOnly ? '1' : ''
        });
    }

    // ---------------------------------------------------------------- cascade

    function qaLoadSummary() {
        $.ajax({
            url: QA_URL,
            type: 'POST',
            dataType: 'json',
            data: $.extend({ section: 'summary' }, qaFilters()),
            success: function (json) {
                if (!json || json.error || !json.summary) { return; }
                qaSummary = json.summary.nodes || {};
                Object.keys(QA_CASCADE).forEach(function (node) {
                    var s = qaSummary[node] || { total: 0, overdue: 0 };
                    $('#qa-node-' + node).text(s.total.toLocaleString());
                    $('#qa-node-overdue-' + node).text(s.overdue.toLocaleString());
                    $('.qa-card[data-node="' + node + '"], .qa-part[data-node="' + node + '"]')
                        .toggleClass('is-empty', s.total === 0);
                });
                qaPaintFlowBar();
            }
        });
    }

    // How the total splits between the stages, as one bar and a share per
    // stage under it. The stages are the leaves of the cascade, so together
    // the segments always fill the bar.
    function qaPaintFlowBar() {
        var nodes = qaSummary || {};
        var total = (nodes.pending || {}).total || 0;
        var described = [];
        QA_BREAKDOWN_STAGES.forEach(function (stage) {
            var count = (nodes[stage] || {}).total || 0;
            var share = total > 0 ? count / total * 100 : 0;
            // A stage holding a handful of samples beside a large backlog still
            // gets a sliver, so the bar never shows a stage as empty when it is not.
            $('#qaFlowBar .qa-bar-seg[data-seg="' + stage + '"]')
                .css({ 'flex-grow': share, 'min-width': count > 0 ? '4px' : '0' })
                .attr('title', QA_CASCADE_LABELS[stage] + ': ' + count.toLocaleString());
            var shareText = qaShare(share);
            $('#qa-share-' + stage).text(shareText);
            described.push(QA_CASCADE_LABELS[stage] + ' ' + shareText);
        });
        $('#qaFlowBar').attr('aria-label', described.join(', '));
    }

    function qaShare(percent) {
        if (percent > 0 && percent < 1) { return '<1%'; }
        return Math.round(percent) + '%';
    }

    // Marks the card and says what its samples are waiting on, without
    // fetching anything.
    function qaMarkNode(node) {
        qaNode = node;
        $('.qa-card, .qa-part').removeClass('is-active');
        $('.qa-card-main, .qa-part').attr('aria-pressed', 'false');
        var $selected = $('.qa-card[data-node="' + node + '"], .qa-part[data-node="' + node + '"]').addClass('is-active');
        $selected.filter('.qa-part').add($selected.find('.qa-card-main')).attr('aria-pressed', 'true');
        $('#qaGridTitle').text(QA_CASCADE_LABELS[node] || '');
        var prompt = QA_NODE_PROMPTS[node] || { question: '', detail: '' };
        $('#qaNodeQuestion').text(prompt.question);
        $('#qaNodeDetail').text(prompt.detail);
        qaUpdateLockHint();
    }

    // Every way into the grid says whether it lists all of a card's samples or
    // only the overdue ones, so the checkbox above the grid always tells the
    // truth about what it is showing.
    function qaSelectNode(node, fromBreakdown, overdueOnly) {
        if (!QA_CASCADE[node]) { return; }
        // A card, or its overdue line, holding nothing has nothing to list. A
        // number in the breakdown is never zero, so a drill-down is never
        // refused here.
        var counts = qaSummary ? (qaSummary[node] || { total: 0, overdue: 0 }) : null;
        if (!fromBreakdown && counts && (overdueOnly ? counts.overdue : counts.total) === 0) { return; }
        qaMarkNode(node);
        qaSetOverdueOnly(!!overdueOnly);
        if (qaTable) { qaTable.fnDraw(); }
    }

    // ---------------------------------------------------------- overdue limit

    // Every label that quotes the limit is rewritten together, so no count on
    // the page is ever described with a limit it was not counted against.
    function qaPaintOverdueLabels() {
        var overdue = qaSprintf(QA_LABELS.overdue, qaOverdueDays);
        $('.qa-overdue-label').text(overdue);
        $('#qaOverdueHeading').text(overdue);
        $('.qa-only-overdue-label').text(qaSprintf(QA_LABELS.onlyOverdue, qaOverdueDays));
        $('#qaOverdueDays').val(qaOverdueDays);
        $('#qaOverduePresets button').each(function () {
            $(this).toggleClass('active', parseInt($(this).data('days'), 10) === qaOverdueDays);
        });
    }

    function qaReadStoredOverdue() {
        try {
            var stored = parseInt(window.localStorage.getItem(QA_OVERDUE_STORAGE_KEY), 10);
            return stored >= 1 && stored <= QA_OVERDUE_MAX ? stored : QA_OVERDUE_DEFAULT;
        } catch (e) {
            return QA_OVERDUE_DEFAULT;
        }
    }

    // A new limit changes what every count on the page means, so the cards,
    // the breakdown and the grid are all fetched again.
    function qaSetOverdueDays(value) {
        var days = Number(value);
        if (!(Number.isInteger(days) && days >= 1 && days <= QA_OVERDUE_MAX)) {
            alert(qaSprintf(QA_LABELS.overdueRange, QA_OVERDUE_MAX));
            qaPaintOverdueLabels();
            return;
        }
        if (days === qaOverdueDays) {
            qaPaintOverdueLabels();
            return;
        }
        qaOverdueDays = days;
        try {
            window.localStorage.setItem(QA_OVERDUE_STORAGE_KEY, String(days));
        } catch (e) {
            // A browser that refuses storage still applies the limit to this visit.
        }
        qaPaintOverdueLabels();
        qaLoadSummary();
        qaLoadBreakdown();
        if (qaTable) { qaTable.fnDraw(); }
    }

    function qaSetOverdueOnly(on) {
        qaOverdueOnly = on;
        $('#qaOverdueOnly').prop('checked', on);
    }

    // -------------------------------------------------------------- breakdown

    function qaLoadBreakdown() {
        $.ajax({
            url: QA_URL,
            type: 'POST',
            dataType: 'json',
            data: $.extend({ section: 'breakdown', breakdownBy: qaBreakdownBy }, qaFilters()),
            success: function (json) {
                if (!json || json.error || !json.breakdown) { return; }
                qaRenderBreakdown(json.breakdown);
            }
        });
    }

    function qaRenderBreakdown(rows) {
        if (qaBreakdownTable) {
            qaBreakdownTable.fnDestroy();
            qaBreakdownTable = null;
        }
        qaBreakdownLabels = {};
        rows.forEach(function (row) { qaBreakdownLabels[row.key] = row.label; });

        $('#qaBreakdownGroupHeading').text(QA_GROUPING_LABELS[qaBreakdownBy] || '');
        $('#qaBreakdown tbody').empty();

        qaBreakdownTable = $('#qaBreakdown').dataTable({
            "bAutoWidth": false,
            "aaData": rows,
            "aoColumns": qaBreakdownColumns(),
            // Busiest first: the total column sits after the group and the stages.
            "aaSorting": [[QA_BREAKDOWN_STAGES.length + 1, 'desc']],
            "iDisplayLength": 10,
            "oLanguage": { "sZeroRecords": QA_LABELS.noBreakdown }
        });
    }

    // Every cell sorts on its raw value and displays a link, so numbers sort as
    // numbers and dates as dates rather than as the text they are shown in.
    function qaBreakdownColumns() {
        var columns = [{
            "mData": null,
            "mRender": function (data, type, row) {
                return type === 'display' ? qaEsc(row.label) : row.label;
            }
        }];
        QA_BREAKDOWN_STAGES.forEach(function (stage) {
            columns.push({
                "mData": null,
                "sClass": 'text-right',
                "mRender": function (data, type, row) { return qaDrillCell(row, stage, row.stages[stage], type); }
            });
        });
        columns.push({
            "mData": null,
            "sClass": 'text-right',
            "mRender": function (data, type, row) { return qaDrillCell(row, 'pending', row.total, type); }
        });
        columns.push({
            "mData": null,
            "sClass": 'text-right',
            "mRender": function (data, type, row) { return qaDrillCell(row, 'pending', row.overdue, type, true); }
        });
        columns.push({
            "mData": null,
            "mRender": function (data, type, row) {
                return type === 'display' ? qaEsc(row.oldestCollected) : row.oldestCollectedSort;
            }
        });
        return columns;
    }

    function qaDrillCell(row, node, count, type, overdueOnly) {
        if (type !== 'display') { return count; }
        if (!count) { return '<span class="qa-zero">0</span>'; }
        return '<a class="qa-drill' + (overdueOnly ? ' qa-overdue-count' : '') + '" data-node="' + qaEsc(node) +
            '" data-group-key="' + qaEsc(row.key) + '" data-overdue="' + (overdueOnly ? '1' : '') + '">' +
            count.toLocaleString() + '</a>';
    }

    // A number in the breakdown is one group and one card: the grid lists
    // exactly those samples, and says so above itself until it is cleared.
    function qaDrill(node, key, overdueOnly) {
        qaGroup = { by: qaBreakdownBy, key: parseInt(key, 10) || 0, label: qaBreakdownLabels[key] || '' };
        $('#qaDrillText').text(qaSprintf(qaSprintf(QA_LABELS.showingGroup,
            QA_GROUPING_LABELS[qaGroup.by] || ''), qaGroup.label));
        $('#qaDrillChip').show();
        qaSelectNode(node, true, overdueOnly);
        $('html, body').animate({ scrollTop: $('#qaGridBox').offset().top - 60 }, 200);
    }

    function qaClearGroup(redraw) {
        qaGroup = { by: '', key: 0, label: '' };
        $('#qaDrillChip').hide();
        if (redraw && qaTable) { qaTable.fnDraw(); }
    }

    // ------------------------------------------------------------------ grid

    function qaColumnDefs() {
        return Object.keys(QA_COLUMNS).map(function (key) {
            var column = QA_COLUMNS[key];
            return {
                "mData": null,
                "bSortable": column.sort !== null,
                "sClass": column.numeric ? 'text-center' : '',
                "mRender": function (data, type, row) {
                    return qaRenderCell(key, row);
                }
            };
        });
    }

    function qaRenderCell(key, row) {
        switch (key) {
            case 'select':
                // Only the side holding a sample can write about it, so only
                // its rows can be ticked for a note.
                return qaOwns(row.stage)
                    ? '<input type="checkbox" class="qa-row-check" value="' + row.recordId + '" />'
                    : '';
            case 'sampleCode':
                var code = qaEsc(row.sampleCode || '');
                if (row.remoteSampleCode && row.remoteSampleCode !== row.sampleCode) {
                    code += '<span class="qa-secondary">' + qaEsc(row.remoteSampleCode) + '</span>';
                }
                return code;
            // Identifiers only. Nobody chasing a sample needs the child's or
            // the mother's name to do it -- the id is what gets quoted on the
            // phone -- so no name is put on a screen that is read by both the
            // lab and the implementing partner. The age line is the date of
            // birth when there is one, since an age recorded months ago is only
            // true of the day it was typed in.
            case 'child':
                return qaPerson(row.childId, row.childDob
                    ? qaSprintf(QA_LABELS.dob, row.childDob)
                    : (row.childAge ? qaSprintf(QA_LABELS.age, row.childAge) : ''));
            case 'mother':
                return qaPerson(row.motherId, '');
            case 'age':
                // Days since collection, which is what the child has waited and
                // what the overdue limit is set against, and under it how long
                // the side holding the sample now has had it.
                var days = '<span class="qa-days' + (row.age >= qaOverdueDays ? ' is-overdue' : '') + '">' +
                    row.age + '</span>';
                if (row.stepAge !== undefined && row.stepAge !== row.age) {
                    days += '<span class="qa-secondary">' + qaSprintf(QA_LABELS.atThisStep, row.stepAge) + '</span>';
                }
                return days;
            case 'stage':
                var stage = '<span>' + qaEsc(row.stageLabel) + '</span>';
                if (row.dataIssue) {
                    stage += '<span class="qa-conflict" title="' + qaEsc(QA_LABELS.conflictHint) + '">' +
                        '<em class="fa-solid fa-triangle-exclamation"></em> ' + qaEsc(row.dataIssue) + '</span>';
                } else if (row.status) {
                    stage += '<span class="qa-secondary">' + qaEsc(row.status) + '</span>';
                }
                return stage;
            case 'lab':
                return qaLabCell(row);
            case 'notes':
                return qaRenderNoteCell(row);
            default:
                return qaEsc(row[key] || '');
        }
    }

    // The lab, and under it what it runs EID on. None of these samples has a
    // result, so the instruments come from the lab's finished work over the
    // same period; a lab that has tested nothing in the period simply shows no
    // second line. Only the first few are shown, with the rest on hover, so a
    // lab with a long list does not push every row of the grid taller.
    function qaLabCell(row) {
        var name = qaEsc(row.lab || '');
        var all = String(row.labInstruments || '');
        if (all === '') { return name; }

        var names = all.split(', ');
        var shown = names.slice(0, QA_LAB_INSTRUMENTS_SHOWN).join(', ');
        if (names.length > QA_LAB_INSTRUMENTS_SHOWN) {
            shown += ' ' + qaSprintf(QA_LABELS.andMore, names.length - QA_LAB_INSTRUMENTS_SHOWN);
        }

        return name + '<span class="qa-instruments" title="' + qaEsc(all) + '">' +
            '<em class="fa-solid fa-microscope"></em> ' + qaEsc(shown) + '</span>';
    }

    // An id and an age line, each skipped when empty, so a row carrying only an
    // id is one line rather than two and a row carrying neither says so.
    function qaPerson(id, detail) {
        var lines = [];
        if (id) { lines.push('<span>' + qaEsc(id) + '</span>'); }
        if (detail) { lines.push('<span class="qa-secondary">' + qaEsc(detail) + '</span>'); }
        return lines.length ? lines.join('') : '<span class="qa-note-none">&ndash;</span>';
    }

    function qaRenderNoteCell(row) {
        var notes = qaNotes[row.recordId] || [];
        var mine = qaOwns(row.stage);

        // One row is the common case, so it gets its own link straight into the
        // note form. Ticking boxes is for saying the same thing about many rows
        // at once, not the price of saying anything at all.
        var add = mine
            ? '<a class="qa-note-add" data-add="' + row.recordId + '">' +
              '<em class="fa-solid fa-plus"></em> ' + qaEsc(QA_LABELS.addNote) + '</a>'
            : '';

        if (!notes.length) {
            // The invitation only reads as one to the side that can act on it;
            // the other side is being told there is nothing to read.
            return '<span class="qa-note-cell">' + (mine ? add :
                '<span class="qa-note-none">' + qaEsc(QA_LABELS.noneYet) + '</span>') + '</span>';
        }

        var last = notes[notes.length - 1];
        return '<span class="qa-note-cell"><a data-thread="' + row.recordId + '">' +
            '<span class="qa-note-count">' + notes.length + '</span>' +
            qaEsc(last.reasonLabel) +
            '<span class="qa-secondary">' +
            qaEsc(QA_SIDE_LABELS[last.side]) + ' &middot; ' + qaEsc(last.when) +
            '</span></a>' + add + '</span>';
    }

    function qaInitTable() {
        if (qaTable) { return qaTable; }
        qaTable = $('#qaTable').dataTable({
            "bJQueryUI": false,
            "bAutoWidth": false,
            "bInfo": true,
            "bRetrieve": true,
            "aoColumns": qaColumnDefs(),
            "aaSorting": [],
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": QA_URL,
            "oLanguage": { "sZeroRecords": QA_LABELS.noData },
            "fnServerData": function (sSource, aoData, fnCallback) {
                aoData.push({ "name": "section", "value": "samples" });
                var params = qaGridParams();
                Object.keys(params).forEach(function (name) {
                    aoData.push({ "name": name, "value": params[name] });
                });
                $.ajax({
                    "dataType": 'json',
                    "type": "POST",
                    "url": sSource,
                    "data": aoData,
                    "success": fnCallback
                });
            },
            // Every row carries its record id, including the rows with no
            // checkbox, so a note cell can be repainted wherever it is.
            "fnRowCallback": function (nRow, aData) {
                $(nRow).attr('data-record-id', aData.recordId);
                return nRow;
            },
            "fnDrawCallback": function (settings) {
                // A tick means "this row, as it is on screen". A redraw changes
                // what is on screen, so the ticks go with it.
                qaSelection = [];
                $('#qaTable').find('.qa-check-all').prop('checked', false);
                qaCacheRows(settings);
                qaUpdateSelection();
            }
        });
        return qaTable;
    }

    function qaCacheRows(settings) {
        (settings.aoData || []).forEach(function (entry) {
            var row = entry._aData;
            if (row && row.recordId) { qaRowCache[row.recordId] = row; }
        });
    }

    function qaReasonLabel(side, key) {
        var groups = QA_REASONS[side] || {};
        var label = key;
        Object.keys(groups).forEach(function (group) {
            if (groups[group][key]) { label = groups[group][key]; }
        });
        return label;
    }

    // --------------------------------------------------------------- notes

    function qaAppendNote(recordId, note) {
        if (!qaNotes[recordId]) { qaNotes[recordId] = []; }
        qaNotes[recordId].push(note);
    }

    // Only the notes column changes when a note is added, so the grid is
    // repainted in place rather than re-fetched: a redraw would drop the ticks
    // and lose the reader's place in a long backlog.
    function qaRefreshNoteCells() {
        if (!qaTable) { return; }
        var noteIndex = Object.keys(QA_COLUMNS).indexOf('notes');
        $('#qaTable').find('tbody tr').each(function () {
            var $row = $(this);
            var id = $row.attr('data-record-id');
            if (!id || !qaRowCache[id]) { return; }
            $row.find('td').eq(noteIndex).html(qaRenderNoteCell(qaRowCache[id]));
        });
    }

    function qaOpenAddNote() {
        if (!qaSelection.length) { return; }
        qaShowNoteModal(qaSelection.slice());
    }

    // TomSelect and not the Select2 the filter bar uses. Select2 hangs its
    // dropdown off an ancestor it has to be told about and measures the control
    // it replaces, neither of which survives a Bootstrap modal that has not
    // opened yet; TomSelect renders in place, so a searchable list of twenty
    // grouped reasons works inside the modal without any of that.
    function qaDestroyReasonSelect() {
        var el = document.getElementById('qaNoteReason');
        if (el && el.tomselect) {
            el.tomselect.destroy();
        }
    }

    // The list differs between the two sides, so the widget is rebuilt each
    // time the form is opened rather than kept and re-filled.
    function qaBuildReasonSelect() {
        qaDestroyReasonSelect();
        new TomSelect('#qaNoteReason', {
            create: false,
            allowEmptyOption: true,
            placeholder: QA_LABELS.chooseReason
        });
    }

    // The question depends on where the samples are. Selected rows that are
    // all asked the same thing get that question; a mix gets a general one.
    function qaNotePromptFor(ids) {
        var prompts = [];
        ids.forEach(function (id) {
            var prompt = QA_PROMPTS[(qaRowCache[id] || {}).stage] || '';
            if (prompt !== '' && prompts.indexOf(prompt) === -1) { prompts.push(prompt); }
        });
        return prompts.length === 1 ? prompts[0] : QA_LABELS.mixedPrompt;
    }

    function qaShowNoteModal(ids) {
        qaNoteTarget = { side: qaSide, ids: ids };

        $('#qaNotePrompt').text(qaNotePromptFor(ids));
        $('#qaNoteScope').text('(' + qaSprintf(QA_LABELS.selected, ids.length) + ')');

        var chips = ids.slice(0, 12).map(function (id) {
            var row = qaRowCache[id] || {};
            return '<span class="label label-default">' + qaEsc(row.sampleCode || id) + '</span>';
        }).join('');
        if (ids.length > 12) {
            chips += '<span class="text-muted">' + qaSprintf(QA_LABELS.andMore, ids.length - 12) + '</span>';
        }
        $('#qaNoteChips').html(chips);

        var groups = QA_REASONS[qaSide] || {};
        var options = '<option value="">' + qaEsc(QA_LABELS.chooseReason) + '</option>';
        Object.keys(groups).forEach(function (group) {
            options += '<optgroup label="' + qaEsc(group) + '">';
            Object.keys(groups[group]).forEach(function (key) {
                options += '<option value="' + qaEsc(key) + '">' + qaEsc(groups[group][key]) + '</option>';
            });
            options += '</optgroup>';
        });
        qaDestroyReasonSelect();
        $('#qaNoteReason').html(options).val('');
        qaBuildReasonSelect();

        $('#qaNoteText').val('');
        $('#qaNoteExpected').val('');
        $('#qaNoteAttribution').text(QA_USER + (QA_ROLE ? ' (' + QA_ROLE + ')' : '') +
            ' · ' + moment().format('DD-MMM-YYYY HH:mm'));

        qaShowModal('#qaNoteModal');
    }

    // Bootstrap will not fade one modal in while another is still fading out,
    // so a hand-off waits for the first to finish closing.
    function qaShowModal(selector) {
        var $open = $('.modal.in').not(selector);
        if (!$open.length) {
            $(selector).modal('show');
            return;
        }
        $open.one('hidden.bs.modal', function () {
            $(selector).modal('show');
        }).modal('hide');
    }

    function qaSaveNote() {
        var reasonKey = $('#qaNoteReason').val();
        if (!reasonKey) {
            alert(QA_LABELS.reasonRequired);
            return;
        }
        var text = $.trim($('#qaNoteText').val());
        if (reasonKey === 'other' && text === '') {
            alert(QA_LABELS.detailsRequired);
            return;
        }

        var note = {
            side: qaNoteTarget.side,
            reasonKey: reasonKey,
            reasonLabel: qaReasonLabel(qaNoteTarget.side, reasonKey),
            text: text,
            author: QA_USER,
            role: QA_ROLE || QA_SIDE_LABELS[qaNoteTarget.side],
            when: moment().format('DD-MMM-YYYY HH:mm'),
            expected: $('#qaNoteExpected').val() || ''
        };

        qaNoteTarget.ids.forEach(function (id) {
            qaAppendNote(id, $.extend({}, note));
        });
        // Logged even though the note itself is not stored yet: what a reader
        // did on the page is a separate record from what the page saved, and it
        // is the one that says who was working this queue and when.
        qaLogActivity('added-note');

        $('#qaNoteModal').modal('hide');
        qaRefreshNoteCells();
        alert(QA_LABELS.notSaved);
    }

    // -------------------------------------------------------------- thread

    function qaOpenThread(recordId) {
        var row = qaRowCache[recordId] || {};
        var notes = qaNotes[recordId] || [];

        $('#qaThreadTitle').text(QA_LABELS.sample + ' ' + (row.sampleCode || ''));
        $('#qaThreadSubtitle').text([
            row.facility, row.lab, row.stageLabel,
            (row.age || 0) + ' ' + QA_LABELS.days
        ].filter(Boolean).join(' · '));

        var body = '';
        if (!notes.length) {
            body = '<p class="text-muted">' + qaEsc(QA_LABELS.noNotes) + '</p>';
        } else {
            notes.forEach(function (note) {
                body += '<div class="qa-note qa-note-' + note.side + '">' +
                    '<div class="qa-note-head">' +
                    '<span class="qa-note-reason">' +
                    '<span class="qa-side-badge qa-side-' + note.side + '">' +
                    qaEsc(QA_SIDE_LABELS[note.side] || note.side) + '</span>' +
                    qaEsc(note.reasonLabel) +
                    '</span>' +
                    '<span class="qa-note-meta">' + qaEsc(note.author) +
                    (note.role ? ' · ' + qaEsc(note.role) : '') +
                    ' · ' + qaEsc(note.when) + '</span>' +
                    '</div>' +
                    (note.text ? '<div class="qa-note-text">' + qaEsc(note.text) + '</div>' : '') +
                    (note.expected ? '<div class="qa-secondary">' +
                        qaEsc(qaSprintf(QA_LABELS.expectedBy, note.expected)) + '</div>' : '') +
                    '</div>';
            });
        }
        $('#qaThreadBody').html(body);

        // Each side writes only about the samples it is holding, so the button
        // is there only when this sample is on the user's own side. The other
        // side's notes stay fully readable above it, which is the point of
        // putting both in one thread.
        $('#qaThreadAdd').data('recordId', recordId).toggle(qaOwns(row.stage));
        qaShowModal('#qaThreadModal');
    }

    function qaAddFromThread() {
        var recordId = String($('#qaThreadAdd').data('recordId') || '');
        if (!recordId) { return; }
        qaShowNoteModal([recordId]);
    }

    // ----------------------------------------------------------- selection

    function qaUpdateSelection() {
        var count = qaSelection.length;
        $('#qa-selection').text(count ? qaSprintf(QA_LABELS.selected, count) : '');
        $('#qaAddNote').prop('disabled', count === 0);
    }

    // Said once above the grid when nothing on the card is this side's to
    // explain, rather than leaving the reader to wonder why nothing can be ticked.
    function qaUpdateLockHint() {
        var stages = QA_CASCADE[qaNode] || [];
        $('#qa-locked').toggle(stages.length > 0 && stages.every(function (stage) { return !qaOwns(stage); }));
    }

    // ------------------------------------------------------------- activity

    // What was done on this page, and how long it was open, against the same
    // activity log the rest of the system writes to (Admin > Monitoring >
    // Activity). Only an event key and, for a visit, a number of seconds are
    // sent: the wording of every line is composed on the server, so nothing
    // typed here can be written into a record of what happened.
    function qaLogActivity(event, seconds) {
        var payload = $.extend({ section: 'activity', event: event }, qaFilters());
        if (seconds) { payload.seconds = seconds; }
        $.post(QA_URL, payload).fail(function () {
            // Logging is not the reader's problem. A failed line is lost rather
            // than shown, because a page that stops working because it could
            // not write its own audit trail is worse than one with a gap in it.
        });
    }

    // Time on page, counted only while the tab is actually being looked at: a
    // page left open behind twenty others was not being read. The count is sent
    // when the reader leaves or switches away, and once every quarter of an hour
    // so a tab that is never closed properly still leaves a record.
    var qaVisitStarted = null;
    var qaVisitPending = 0;

    function qaVisitTick() {
        if (qaVisitStarted !== null) {
            qaVisitPending += Math.round((Date.now() - qaVisitStarted) / 1000);
            qaVisitStarted = null;
        }
    }

    function qaVisitFlush(useBeacon) {
        qaVisitTick();
        var seconds = qaVisitPending;
        if (seconds < 5) { return; }
        qaVisitPending = 0;

        // A page being unloaded will not wait for an XHR, so the last flush goes
        // out as a beacon; everything the endpoint reads arrives as form data
        // either way.
        if (useBeacon && navigator.sendBeacon) {
            var form = new FormData();
            form.append('section', 'activity');
            form.append('event', 'visit');
            form.append('seconds', String(seconds));
            var filters = qaFilters();
            Object.keys(filters).forEach(function (key) { form.append(key, filters[key]); });
            navigator.sendBeacon(QA_URL, form);
            return;
        }
        qaLogActivity('visit', seconds);
    }

    function qaWatchVisit() {
        qaVisitStarted = Date.now();

        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                qaVisitFlush(false);
            } else if (qaVisitStarted === null) {
                qaVisitStarted = Date.now();
            }
        });

        // pagehide and not beforeunload: it is the one that fires on a mobile
        // browser putting the page away, which beforeunload does not.
        window.addEventListener('pagehide', function () { qaVisitFlush(true); });

        window.setInterval(function () {
            if (!document.hidden) { qaVisitFlush(false); qaVisitStarted = Date.now(); }
        }, 15 * 60 * 1000);
    }

    function qaApplyFilters() {
        qaLogActivity('searched');
        // The breakdown row a listing was narrowed to may not survive new
        // filters, so the narrowing goes with them.
        qaClearGroup(false);
        qaLoadSummary();
        qaLoadBreakdown();
        if (qaTable) { qaTable.fnDraw(); }
    }

    function qaResetFilters() {
        $('#provinceId, #districtId, #partnerId, #bucket, #instrument').val('').trigger('change');
        $('#facilityId, #labId').val(null).trigger('change');
        $('#districtId').html('<option value=""><?= _jsTranslate('-- All --'); ?></option>');
        qaApplyFilters();
    }

    function qaExport() {
        $.blockUI();
        // The export logs itself where it is written, so the token that comes
        // back is proof the line was recorded alongside the file.
        $.post(QA_URL, $.extend({ section: 'export' }, qaGridParams()), function (token) {
            $.unblockUI();
            token = $.trim(String(token || ''));
            if (token === '' || token.indexOf('{') === 0) {
                alert(QA_LABELS.exportFailed);
                return;
            }
            window.open('/download.php?f=' + token, '_blank');
        }).fail(function () {
            $.unblockUI();
            alert(QA_LABELS.exportFailed);
        });
    }

    // The same presets the Sample Ageing report offers, so a reader moving
    // between the two picks the same period the same way.
    function qaDateRanges() {
        var ranges = {};
        ranges["<?= _jsTranslate('Today'); ?>"] = [moment(), moment()];
        ranges["<?= _jsTranslate('Yesterday'); ?>"] = [moment().subtract(1, 'days'), moment().subtract(1, 'days')];
        ranges["<?= _jsTranslate('Last 7 Days'); ?>"] = [moment().subtract(6, 'days'), moment()];
        ranges["<?= _jsTranslate('Last 14 Days'); ?>"] = [moment().subtract(13, 'days'), moment()];
        ranges["<?= _jsTranslate('This Month'); ?>"] = [moment().startOf('month'), moment().endOf('month')];
        ranges["<?= _jsTranslate('Last Month'); ?>"] = [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')];
        ranges["<?= _jsTranslate('Last 30 Days'); ?>"] = [moment().subtract(29, 'days'), moment()];
        ranges["<?= _jsTranslate('Last 60 Days'); ?>"] = [moment().subtract(59, 'days'), moment()];
        ranges["<?= _jsTranslate('Last 90 Days'); ?>"] = [moment().subtract(89, 'days'), moment()];
        ranges["<?= _jsTranslate('Last 120 Days'); ?>"] = [moment().subtract(119, 'days'), moment()];
        ranges["<?= _jsTranslate('Last 180 Days'); ?>"] = [moment().subtract(179, 'days'), moment()];
        ranges["<?= _jsTranslate('This Quarter'); ?>"] = [moment().startOf('quarter'), moment().endOf('quarter')];
        ranges["<?= _jsTranslate('Last Quarter'); ?>"] = [moment().subtract(1, 'quarter').startOf('quarter'), moment().subtract(1, 'quarter').endOf('quarter')];
        ranges["<?= _jsTranslate('Last 6 Months'); ?>"] = [moment().subtract(6, 'month').startOf('month'), moment().endOf('month')];
        ranges["<?= _jsTranslate('Last 12 Months'); ?>"] = [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')];
        ranges["<?= _jsTranslate('Last 18 Months'); ?>"] = [moment().subtract(18, 'month').startOf('month'), moment().endOf('month')];
        ranges["<?= _jsTranslate('Last 24 Months'); ?>"] = [moment().subtract(24, 'month').startOf('month'), moment().endOf('month')];
        ranges["<?= _jsTranslate('Last 30 Months'); ?>"] = [moment().subtract(30, 'month').startOf('month'), moment().endOf('month')];
        ranges["<?= _jsTranslate('Current Year To Date'); ?>"] = [moment().startOf('year'), moment()];
        ranges["<?= _jsTranslate('Previous Year'); ?>"] = [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')];
        return ranges;
    }

    $(document).ready(function () {
        // A sample that is stuck is old by definition, so the window opens wide.
        $('#dateRange').daterangepicker({
            locale: {
                cancelLabel: "<?= _jsTranslate('Clear'); ?>",
                format: 'DD-MMM-YYYY',
                separator: ' to '
            },
            showDropdowns: true,
            alwaysShowCalendars: true,
            startDate: moment().subtract(6, 'month'),
            endDate: moment(),
            minDate: moment('2013-01-01'),
            maxDate: moment(),
            ranges: qaDateRanges()
        });
        $('#dateRange').on('cancel.daterangepicker', function () {
            $(this).val('');
        });

        $('#qaNoteModal').on('hidden.bs.modal', qaDestroyReasonSelect);

        $('#provinceId, #partnerId, #bucket, #instrument').select2();
        $('#districtId').select2();
        $('#facilityId').select2({ placeholder: "<?= _jsTranslate('-- All Facilities --'); ?>" });
        $('#labId').select2({ placeholder: "<?= _jsTranslate('-- All Labs --'); ?>" });

        // A resolution date is a promise about the future, so this picker is the
        // one date field on the page that looks forward rather than back.
        $('#qaNoteExpected').datepicker({
            changeMonth: true,
            changeYear: true,
            minDate: 0,
            dateFormat: 'dd-M-yy'
        });

        $('#provinceId').on('change', function () {
            var provinceId = $(this).val();
            $('#districtId').html('<option value=""><?= _jsTranslate('-- All --'); ?></option>');
            if (!provinceId) { return; }
            $.post('/common/get-by-province-id.php', { provinceId: provinceId, districts: true },
                function (data) {
                    var parsed = typeof data === 'string' ? $.parseJSON(data) : data;
                    if (parsed && parsed.districts) {
                        $('#districtId').html(parsed.districts);
                    }
                });
        });

        $('input[name="qaSide"]').on('change', function () {
            qaSide = $(this).val();
            qaLogActivity('viewed-' + qaSide);
            qaUpdateLockHint();
            // Which rows can be ticked and written about depends on the side,
            // so the page is redrawn where it is rather than sent back to page 1.
            if (qaTable) { qaTable.fnDraw(false); }
        });

        $('#qaCascade').on('click', '.qa-card-main, .qa-part', function () {
            qaSelectNode(String($(this).data('node')), false, false);
        });

        $('#qaCascade').on('click', '.qa-card-overdue', function () {
            qaSelectNode(String($(this).data('node')), false, true);
        });

        $('#qaOverduePresets').on('click', 'button', function () {
            qaSetOverdueDays($(this).data('days'));
        });

        $('#qaOverdueDays').on('change', function () {
            qaSetOverdueDays($(this).val());
        }).on('keydown', function (e) {
            if (e.key === 'Enter') { $(this).trigger('change'); }
        });

        $('#qaOverdueOnly').on('change', function () {
            qaSetOverdueOnly(this.checked);
            if (qaTable) { qaTable.fnDraw(); }
        });

        $('#qaGroupBy').on('click', 'button', function () {
            qaBreakdownBy = String($(this).data('groupBy'));
            $('#qaGroupBy button').removeClass('active');
            $(this).addClass('active');
            // A row of the old grouping means nothing in the new one.
            qaClearGroup(true);
            qaLoadBreakdown();
        });

        $('#qaBreakdown').on('click', '.qa-drill', function () {
            qaDrill(String($(this).data('node')), String($(this).data('groupKey')),
                String($(this).data('overdue')) === '1');
        });

        $(document).on('change', '.qa-row-check', function () {
            var id = String($(this).val());
            var index = qaSelection.indexOf(id);
            if (this.checked && index === -1) {
                qaSelection.push(id);
            } else if (!this.checked && index !== -1) {
                qaSelection.splice(index, 1);
            }
            qaUpdateSelection();
        });

        $(document).on('change', '.qa-check-all', function () {
            var checked = this.checked;
            $('#qaTable').find('.qa-row-check').prop('checked', checked).each(function () {
                var id = String($(this).val());
                var index = qaSelection.indexOf(id);
                if (checked && index === -1) {
                    qaSelection.push(id);
                } else if (!checked && index !== -1) {
                    qaSelection.splice(index, 1);
                }
            });
            qaUpdateSelection();
        });

        $(document).on('click', '[data-thread]', function () {
            qaLogActivity('read-notes');
            qaOpenThread(String($(this).data('thread')));
        });

        $(document).on('click', '[data-add]', function () {
            qaShowNoteModal([String($(this).data('add'))]);
        });

        // The limit is read before anything is fetched, so the first counts
        // are already against it.
        qaOverdueDays = qaReadStoredOverdue();
        qaPaintOverdueLabels();
        qaMarkNode('pending');
        qaInitTable();
        qaUpdateSelection();
        qaLoadSummary();
        qaLoadBreakdown();
        qaLogActivity('opened');
        qaWatchVisit();
    });
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
