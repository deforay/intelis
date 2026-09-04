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

$viewLabels = QualityMonitoringService::viewLabels();
$stageLabels = QualityMonitoringService::stageLabels();
// The two views differ by one column, so each carries its own list.
$columns = [];
foreach (array_keys($viewLabels) as $viewKey) {
    $columns[$viewKey] = QualityMonitoringService::sampleColumns($viewKey);
}

// Which side of the workflow this user answers for. A testing-lab account is
// working the lab queue; everyone else is looking at it from the clinic and
// implementing-partner side. It decides which tab they may add notes on -- the
// other tab stays fully readable, because each side needs to see what the
// other has already said before adding anything.
$userSide = (($_SESSION['accessType'] ?? '') === 'testing-lab') ? 'lab' : 'clinic';

$sideLabels = [
    'lab' => _translate('Lab QA Manager'),
    'clinic' => _translate('Implementing Partner / Clinic'),
];

$noteReasons = [
    'clinic' => QualityMonitoringService::noteReasons('clinic'),
    'lab' => QualityMonitoringService::noteReasons('lab'),
];

// What each side is being asked for, shown above its grid and in the note form.
$notePrompts = [
    'clinic' => _translate('Why has this sample not reached the testing lab yet?'),
    'lab' => _translate('Why is there no result for this sample yet?'),
];

$currentUser = trim((string) ($_SESSION['userName'] ?? ''));
$currentRole = trim((string) ($_SESSION['roleName'] ?? $_SESSION['roleCode'] ?? ''));
?>
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

    #qaModule .qa-filters td {
        vertical-align: middle;
        border-top: 0;
        padding: 4px 8px;
    }

    #qaModule .qa-filters label {
        font-size: 12px;
        color: #666;
        font-weight: 600;
        display: block;
        margin-bottom: 2px;
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

    /* The four numbers that say how bad the backlog is on this side. */
    #qaModule .qa-stats {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        margin: 0 0 14px;
    }

    #qaModule .qa-stat {
        flex: 1 1 150px;
        border: 1px solid #e4e7ea;
        border-radius: 3px;
        padding: 10px 14px;
        background: #fbfcfd;
    }

    #qaModule .qa-stat .qa-stat-value {
        font-size: 24px;
        font-weight: 600;
        line-height: 1.1;
        color: #3c8dbc;
    }

    #qaModule .qa-stat.is-late .qa-stat-value {
        color: #e08e0b;
    }

    #qaModule .qa-stat.is-very-late .qa-stat-value {
        color: #c9302c;
    }

    #qaModule .qa-stat .qa-stat-label {
        font-size: 12px;
        color: #7a848c;
        margin-top: 2px;
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

    #qaModule .qa-days.is-late {
        background: #fcefd4;
        color: #8a6100;
    }

    #qaModule .qa-days.is-very-late {
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

    #qaModule .qa-from-vl {
        display: inline-block;
        font-size: 10px;
        color: #7a848c;
        border: 1px dashed #cfd6db;
        border-radius: 2px;
        padding: 0 4px;
        margin-top: 2px;
        cursor: help;
    }

    #qaModule .qa-person-name {
        display: block;
        font-size: 12px;
        color: #5a636a;
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

    .qa-example-badge {
        display: inline-block;
        font-size: 10px;
        padding: 1px 5px;
        border-radius: 2px;
        border: 1px dashed #c9a227;
        color: #8a6100;
        margin-left: 6px;
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
                            <?= _htmlTranslate('the sample list, the counts and the filters below run on live data. Notes are not saved yet, so anything added here is lost when the page is reloaded, and the example notes on a few rows are there to show how a thread reads.'); ?>
                        </div>

                        <p class="text-muted" id="qa-description">
                            <?= _htmlTranslate('Every EID sample that is still waiting, split by the side of the workflow holding it, so the people responsible for each side can record why.'); ?>
                        </p>

                        <table class="table pageFilters qa-filters" aria-describedby="qa-description"
                            cellspacing="3" style="width:100%;">
                            <tr>
                                <td style="width:18%;">
                                    <label for="dateRange"><?= _htmlTranslate('Sample Collection Period'); ?></label>
                                    <input type="text" id="dateRange" class="form-control daterangefield" />
                                </td>
                                <td style="width:14%;">
                                    <label for="provinceId"><?= _htmlTranslate('Province/State'); ?></label>
                                    <select id="provinceId" class="form-control">
                                        <?= $general->generateSelectOptions($provinces, null, _translate('-- All --')); ?>
                                    </select>
                                </td>
                                <td style="width:14%;">
                                    <label for="districtId"><?= _htmlTranslate('District/County'); ?></label>
                                    <select id="districtId" class="form-control">
                                        <option value=""><?= _htmlTranslate('-- All --'); ?></option>
                                    </select>
                                </td>
                                <td style="width:18%;">
                                    <label for="facilityId"><?= _htmlTranslate('Collection Facility'); ?></label>
                                    <select id="facilityId" class="form-control" multiple="multiple">
                                        <?= $general->generateSelectOptions($healthFacilities); ?>
                                    </select>
                                </td>
                                <td style="width:18%;">
                                    <label for="labId"><?= _htmlTranslate('Testing Lab'); ?></label>
                                    <select id="labId" class="form-control" multiple="multiple">
                                        <?= $general->generateSelectOptions($testingLabs); ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <label for="partnerId"><?= _htmlTranslate('Implementing Partner'); ?></label>
                                    <select id="partnerId" class="form-control">
                                        <option value=""><?= _htmlTranslate('-- All --'); ?></option>
                                        <?php foreach ($partners as $partner) { ?>
                                            <option value="<?= (int) $partner['i_partner_id']; ?>">
                                                <?= htmlspecialchars((string) $partner['i_partner_name'], ENT_QUOTES); ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </td>
                                <td>
                                    <label for="bucket"><?= _htmlTranslate('Waiting For'); ?></label>
                                    <select id="bucket" class="form-control">
                                        <option value=""><?= _htmlTranslate('-- Any length of time --'); ?></option>
                                        <option value="b0"><?= _htmlTranslate('0 to 7 days'); ?></option>
                                        <option value="b1"><?= _htmlTranslate('8 to 14 days'); ?></option>
                                        <option value="b2"><?= _htmlTranslate('15 to 30 days'); ?></option>
                                        <option value="b3"><?= _htmlTranslate('31 to 60 days'); ?></option>
                                        <option value="b4"><?= _htmlTranslate('Over 60 days'); ?></option>
                                    </select>
                                </td>
                                <td colspan="3">
                                    <label>&nbsp;</label>
                                    <button type="button" class="btn btn-success btn-sm" onclick="qaApplyFilters();">
                                        <em class="fa-solid fa-magnifying-glass"></em>
                                        <?= _htmlTranslate('Search'); ?>
                                    </button>
                                    <button type="button" class="btn btn-default btn-sm" onclick="qaResetFilters();">
                                        <?= _htmlTranslate('Reset'); ?>
                                    </button>
                                </td>
                            </tr>
                        </table>

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
                                <?= _htmlTranslate('In the finished module this comes from your role. It is a switch here so both sides of the workflow can be seen in one sitting.'); ?>
                            </span>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-xs-12">
                <div class="nav-tabs-custom">
                    <?php // The page opens on the side this user works, not on whichever tab is first. ?>
                    <ul class="nav nav-tabs" id="qaTabs">
                        <?php foreach ($viewLabels as $viewKey => $viewLabel) { ?>
                            <li class="<?= $viewKey === $userSide ? 'active' : ''; ?>">
                                <a href="#qa-tab-<?= $viewKey; ?>" data-toggle="tab" data-view="<?= $viewKey; ?>">
                                    <?= htmlspecialchars($viewLabel, ENT_QUOTES); ?>
                                    <span class="badge" id="qa-tab-count-<?= $viewKey; ?>">&ndash;</span>
                                </a>
                            </li>
                        <?php } ?>
                    </ul>
                    <div class="tab-content">
                        <?php foreach ($viewLabels as $viewKey => $viewLabel) { ?>
                            <div class="tab-pane <?= $viewKey === $userSide ? 'active' : ''; ?>" id="qa-tab-<?= $viewKey; ?>">

                                <p class="qa-prompt">
                                    <strong><?= htmlspecialchars($notePrompts[$viewKey], ENT_QUOTES); ?></strong>
                                    <?php if ($viewKey === 'clinic') { ?>
                                        <?= _htmlTranslate('These samples were registered at a collection point and no lab has recorded receiving them. Only the clinic side can say what is holding them.'); ?>
                                    <?php } else { ?>
                                        <?= _htmlTranslate('A lab is holding these samples and no approved result has come out of them yet. Only the lab side can say what is holding them.'); ?>
                                    <?php } ?>
                                </p>

                                <div class="qa-stats">
                                    <div class="qa-stat">
                                        <div class="qa-stat-value" id="qa-total-<?= $viewKey; ?>">&ndash;</div>
                                        <div class="qa-stat-label"><?= _htmlTranslate('Samples waiting'); ?></div>
                                    </div>
                                    <div class="qa-stat is-late">
                                        <div class="qa-stat-value" id="qa-late-<?= $viewKey; ?>">&ndash;</div>
                                        <div class="qa-stat-label">
                                            <?= htmlspecialchars(sprintf(_translate('Waiting %d days or more'), QualityMonitoringService::LATE_DAYS), ENT_QUOTES); ?>
                                        </div>
                                    </div>
                                    <div class="qa-stat is-very-late">
                                        <div class="qa-stat-value" id="qa-verylate-<?= $viewKey; ?>">&ndash;</div>
                                        <div class="qa-stat-label">
                                            <?= htmlspecialchars(sprintf(_translate('Waiting %d days or more'), QualityMonitoringService::VERY_LATE_DAYS), ENT_QUOTES); ?>
                                        </div>
                                    </div>
                                    <?php foreach (QualityMonitoringService::VIEWS[$viewKey] as $stageKey) { ?>
                                        <div class="qa-stat">
                                            <div class="qa-stat-value" id="qa-stage-<?= $stageKey; ?>">&ndash;</div>
                                            <div class="qa-stat-label">
                                                <?= htmlspecialchars($stageLabels[$stageKey], ENT_QUOTES); ?>
                                            </div>
                                        </div>
                                    <?php } ?>
                                </div>

                                <div class="qa-toolbar">
                                    <button type="button" class="btn btn-primary btn-sm qa-add-note" disabled="disabled"
                                        data-view="<?= $viewKey; ?>" onclick="qaOpenAddNote('<?= $viewKey; ?>');">
                                        <em class="fa-solid fa-note-sticky"></em>
                                        <?= _htmlTranslate('Add note to selected'); ?>
                                    </button>
                                    <span class="qa-selection-count" id="qa-selection-<?= $viewKey; ?>"></span>
                                    <span style="flex:1 1 auto;"></span>
                                    <button type="button" class="btn btn-success btn-sm"
                                        onclick="qaExport('<?= $viewKey; ?>');">
                                        <em class="fa-solid fa-cloud-arrow-down"></em>
                                        <?= _htmlTranslate('Export to Excel'); ?>
                                    </button>
                                </div>

                                <div class="qa-readonly-hint qa-locked-hint" id="qa-locked-<?= $viewKey; ?>"
                                    style="display:none;">
                                    <em class="fa-solid fa-lock"></em>
                                    <?= _htmlTranslate('These notes belong to the other side of the workflow. You can read them, but only that side can add to them.'); ?>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-bordered table-striped qa-grid"
                                        id="qaTable-<?= $viewKey; ?>">
                                        <thead>
                                            <tr>
                                                <?php foreach ($columns[$viewKey] as $key => $column) { ?>
                                                    <th>
                                                        <?php if ($key === 'select') { ?>
                                                            <input type="checkbox" class="qa-check-all"
                                                                data-view="<?= $viewKey; ?>"
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
                        <?php } ?>
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
                    <?= _htmlTranslate('Saved against your name and the time you save it. A note cannot be edited or removed afterwards; add a follow-up note instead.'); ?>
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

<script type="text/javascript">
    var QA_URL = '/eid/qa/get-qa-monitoring-data.php';
    var QA_VIEWS = <?= json_encode(array_keys($viewLabels)); ?>;
    var QA_COLUMNS = <?= json_encode($columns, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var QA_REASONS = <?= json_encode($noteReasons, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var QA_PROMPTS = <?= json_encode($notePrompts, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var QA_SIDE_LABELS = <?= json_encode($sideLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var QA_LATE_DAYS = <?= QualityMonitoringService::LATE_DAYS; ?>;
    var QA_VERY_LATE_DAYS = <?= QualityMonitoringService::VERY_LATE_DAYS; ?>;
    var QA_USER = <?= json_encode($currentUser !== '' ? $currentUser : _translate('You'), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    var QA_ROLE = <?= json_encode($currentRole, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    var QA_LABELS = {
        noData: "<?= _jsTranslate('No samples are waiting on this side for the selected filters'); ?>",
        selected: "<?= _jsTranslate('%s selected'); ?>",
        andMore: "<?= _jsTranslate('and %s more'); ?>",
        chooseReason: "<?= _jsTranslate('-- Choose a reason --'); ?>",
        reasonRequired: "<?= _jsTranslate('Choose a reason before saving'); ?>",
        detailsRequired: "<?= _jsTranslate('Describe the reason in the details box'); ?>",
        notSaved: "<?= _jsTranslate('Notes are not saved yet. This note is shown here so the workflow can be reviewed, and it disappears when the page is reloaded.'); ?>",
        noNotes: "<?= _jsTranslate('Nothing has been recorded about this sample yet.'); ?>",
        readOnly: "<?= _jsTranslate('You can read this note, but only the side that wrote it can add to it.'); ?>",
        expectedBy: "<?= _jsTranslate('Expected to be resolved by %s'); ?>",
        exportFailed: "<?= _jsTranslate('Unable to generate the export file'); ?>",
        days: "<?= _jsTranslate('days'); ?>",
        sample: "<?= _jsTranslate('Sample'); ?>",
        example: "<?= _jsTranslate('Example'); ?>",
        addNote: "<?= _jsTranslate('Add note'); ?>",
        noneYet: "<?= _jsTranslate('No notes yet'); ?>",
        dob: "<?= _jsTranslate('DoB %s'); ?>",
        age: "<?= _jsTranslate('Age %s'); ?>",
        fromVl: "<?= _jsTranslate('from VL record'); ?>",
        fromVlHint: "<?= _jsTranslate('This request recorded no mother name. This one comes from the mother\'s own viral load request, matched on her ART number.'); ?>",
        conflictHint: "<?= _jsTranslate('The recorded status does not match what the sample has actually been through. The stage on the left is read from the dates on the record. This one needs correcting rather than explaining.'); ?>"
    };

    // Notes live only in the browser for now: the saving side of this module is
    // not built. Keyed by record id so a note survives paging and sorting
    // within the session, and so both tabs show the same thread.
    var qaNotes = {};
    var qaTables = {};
    // Which rows are ticked, per view. Cleared whenever the grid redraws, so a
    // note is never applied to a sample the user can no longer see.
    var qaSelection = { clinic: [], lab: [] };
    var qaRowCache = {};
    var qaSide = "<?= $userSide; ?>";
    var qaNoteTarget = { view: null, ids: [] };
    var qaSeeded = {};

    // Which side owns a sample, read off the stage it is in. A sample moves
    // between the two over its life, which is why a thread can hold notes from
    // both sides even though each side only ever writes its own.
    var QA_STAGE_VIEW = {};
    QA_VIEWS.forEach(function (view) {
        (<?= json_encode(QualityMonitoringService::VIEWS); ?>[view] || []).forEach(function (stage) {
            QA_STAGE_VIEW[stage] = view;
        });
    });

    function qaEsc(value) {
        return $('<div>').text(value === null || value === undefined ? '' : String(value)).html();
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
            bucket: $('#bucket').val() || ''
        };
    }

    // ---------------------------------------------------------------- summary

    function qaLoadSummary() {
        $.ajax({
            url: QA_URL,
            type: 'POST',
            dataType: 'json',
            data: $.extend({ section: 'summary' }, qaFilters()),
            success: function (json) {
                if (!json || json.error || !json.summary) { return; }
                QA_VIEWS.forEach(function (view) {
                    var s = json.summary[view] || { total: 0, late: 0, veryLate: 0 };
                    $('#qa-total-' + view).text(s.total.toLocaleString());
                    $('#qa-late-' + view).text(s.late.toLocaleString());
                    $('#qa-verylate-' + view).text(s.veryLate.toLocaleString());
                    $('#qa-tab-count-' + view).text(s.total.toLocaleString());
                });
                Object.keys(json.summary.stages || {}).forEach(function (stage) {
                    $('#qa-stage-' + stage).text(Number(json.summary.stages[stage]).toLocaleString());
                });
            }
        });
    }

    // ------------------------------------------------------------------ grid

    function qaColumnDefs(view) {
        return Object.keys(QA_COLUMNS[view]).map(function (key) {
            var column = QA_COLUMNS[view][key];
            return {
                "mData": null,
                "bSortable": column.sort !== null,
                "sClass": column.numeric ? 'text-center' : '',
                "mRender": function (data, type, row) {
                    return qaRenderCell(key, row, view);
                }
            };
        });
    }

    function qaRenderCell(key, row, view) {
        switch (key) {
            case 'select':
                return '<input type="checkbox" class="qa-row-check" data-view="' + view +
                    '" value="' + row.recordId + '" />';
            case 'sampleCode':
                var code = qaEsc(row.sampleCode || '');
                if (row.remoteSampleCode && row.remoteSampleCode !== row.sampleCode) {
                    code += '<span class="qa-secondary">' + qaEsc(row.remoteSampleCode) + '</span>';
                }
                return code;
            // One person, one cell. The id leads because it is what gets quoted
            // on the phone; the age line is the date of birth when there is one,
            // since an age recorded months ago is only true of the day it was
            // typed in.
            case 'child':
                return qaPerson(row.childId, row.childName, row.childDob
                    ? qaSprintf(QA_LABELS.dob, row.childDob)
                    : (row.childAge ? qaSprintf(QA_LABELS.age, row.childAge) : ''));
            case 'mother':
                if (row.motherName || !row.motherNameFromVl) {
                    return qaPerson(row.motherId, row.motherName, '');
                }
                // Found on her own viral load record, not written on this
                // request. Marked, because on a data quality page the
                // difference between recorded and inferred is the point.
                return qaPerson(row.motherId, row.motherNameFromVl, '') +
                    '<span class="qa-from-vl" title="' + qaEsc(QA_LABELS.fromVlHint) + '">' +
                    qaEsc(QA_LABELS.fromVl) + '</span>';
            case 'age':
                var cls = row.age >= QA_VERY_LATE_DAYS ? ' is-very-late'
                    : (row.age >= QA_LATE_DAYS ? ' is-late' : '');
                return '<span class="qa-days' + cls + '">' + row.age + '</span>';
            case 'stage':
                var stage = '<span>' + qaEsc(row.stageLabel) + '</span>';
                if (row.dataIssue) {
                    stage += '<span class="qa-conflict" title="' + qaEsc(QA_LABELS.conflictHint) + '">' +
                        '<em class="fa-solid fa-triangle-exclamation"></em> ' + qaEsc(row.dataIssue) + '</span>';
                } else if (row.status) {
                    stage += '<span class="qa-secondary">' + qaEsc(row.status) + '</span>';
                }
                return stage;
            case 'notes':
                return qaRenderNoteCell(row);
            default:
                return qaEsc(row[key] || '');
        }
    }

    // An id, a name and an age line, each on its own line and each skipped
    // when empty, so a row with only an id is one line rather than three.
    function qaPerson(id, name, detail) {
        var lines = [];
        if (id) { lines.push('<span>' + qaEsc(id) + '</span>'); }
        if (name) { lines.push('<span class="qa-person-name">' + qaEsc(name) + '</span>'); }
        if (detail) { lines.push('<span class="qa-secondary">' + qaEsc(detail) + '</span>'); }
        return lines.length ? lines.join('') : '<span class="qa-note-none">&ndash;</span>';
    }

    function qaRenderNoteCell(row) {
        var notes = qaNotes[row.recordId] || [];
        var mine = QA_STAGE_VIEW[row.stage] === qaSide;

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

    function qaInitTable(view) {
        if (qaTables[view]) { return qaTables[view]; }
        qaTables[view] = $('#qaTable-' + view).dataTable({
            "bJQueryUI": false,
            "bAutoWidth": false,
            "bInfo": true,
            "bRetrieve": true,
            "aoColumns": qaColumnDefs(view),
            "aaSorting": [],
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": QA_URL,
            "oLanguage": { "sZeroRecords": QA_LABELS.noData },
            "fnServerData": function (sSource, aoData, fnCallback) {
                aoData.push({ "name": "section", "value": "samples" });
                aoData.push({ "name": "view", "value": view });
                var filters = qaFilters();
                Object.keys(filters).forEach(function (name) {
                    aoData.push({ "name": name, "value": filters[name] });
                });
                $.ajax({
                    "dataType": 'json',
                    "type": "POST",
                    "url": sSource,
                    "data": aoData,
                    "success": fnCallback
                });
            },
            "fnDrawCallback": function (settings) {
                // A tick means "this row, as it is on screen". A redraw changes
                // what is on screen, so the ticks go with it.
                qaSelection[view] = [];
                $('#qaTable-' + view).find('.qa-check-all, .qa-row-check').prop('checked', false);
                qaCacheRows(view, settings);
                qaSeedExamples(view, settings);
                qaUpdateSelection(view);
            }
        });
        return qaTables[view];
    }

    function qaCacheRows(view, settings) {
        (settings.aoData || []).forEach(function (entry) {
            var row = entry._aData;
            if (row && row.recordId) { qaRowCache[row.recordId] = row; }
        });
    }

    // ------------------------------------------------------- example notes

    // Two rows on each side start with a thread already on them, so the
    // cross-side conversation is visible without anyone having to type it. They
    // are labelled as examples and go away on reload, like every other note
    // here until the saving side is built.
    function qaSeedExamples(view, settings) {
        if (qaSeeded[view]) { return; }
        var rows = (settings.aoData || []).map(function (entry) { return entry._aData; })
            .filter(function (row) { return row && row.recordId; });
        if (!rows.length) { return; }
        qaSeeded[view] = true;

        var samples = view === 'lab' ? [
            [
                { side: 'lab', key: 'reagent_stockout', text: "<?= _jsTranslate('Extraction kits ran out on the 3rd. The next consignment is confirmed for the end of the week and this sample is first in the queue.'); ?>" },
                { side: 'clinic', key: 'awaiting_transport', text: "<?= _jsTranslate('Noting from our side that the sample sat with us for six days waiting for the courier, so part of the delay is ours.'); ?>" }
            ],
            [
                { side: 'lab', key: 'instrument_breakdown', text: "<?= _jsTranslate('Analyser down since Monday. The engineer has been called and is expected on site this week.'); ?>" }
            ]
        ] : [
            [
                { side: 'clinic', key: 'no_transport', text: "<?= _jsTranslate('No vehicle available on the scheduled run. Sample is stored and will go with the next pick-up.'); ?>" }
            ],
            [
                { side: 'clinic', key: 'supplies_stockout', text: "<?= _jsTranslate('DBS cards ran out at the facility, so packaging was delayed. Supplies have now been received.'); ?>" },
                { side: 'lab', key: 'awaiting_full_run', text: "<?= _jsTranslate('Once it reaches us it will go on the next run; we are holding a slot on Thursday.'); ?>" }
            ]
        ];

        samples.forEach(function (thread, index) {
            var row = rows[index];
            if (!row || qaNotes[row.recordId]) { return; }
            thread.forEach(function (note, offset) {
                qaAppendNote(row.recordId, {
                    side: note.side,
                    reasonKey: note.key,
                    reasonLabel: qaReasonLabel(note.side, note.key),
                    text: note.text,
                    author: note.side === 'lab' ? "<?= _jsTranslate('Lab QA Manager'); ?>" : "<?= _jsTranslate('Partner M&E Officer'); ?>",
                    role: QA_SIDE_LABELS[note.side],
                    when: moment().subtract((thread.length - offset) * 2, 'days').format('DD-MMM-YYYY HH:mm'),
                    expected: '',
                    example: true
                });
            });
        });

        if (qaTables[view]) { qaRefreshNoteCells(view); }
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
    function qaRefreshNoteCells(view) {
        if (!qaTables[view]) { return; }
        var noteIndex = Object.keys(QA_COLUMNS[view]).indexOf('notes');
        $('#qaTable-' + view).find('tbody tr').each(function () {
            var $row = $(this);
            var id = $row.find('.qa-row-check').val();
            if (!id || !qaRowCache[id]) { return; }
            $row.find('td').eq(noteIndex).html(qaRenderNoteCell(qaRowCache[id]));
        });
    }

    function qaOpenAddNote(view) {
        if (view !== qaSide) { return; }
        var ids = qaSelection[view].slice();
        if (!ids.length) { return; }
        qaShowNoteModal(view, ids);
    }

    function qaShowNoteModal(view, ids) {
        qaNoteTarget = { view: view, ids: ids };

        $('#qaNotePrompt').text(QA_PROMPTS[view] || '');
        $('#qaNoteScope').text('(' + qaSprintf(QA_LABELS.selected, ids.length) + ')');

        var chips = ids.slice(0, 12).map(function (id) {
            var row = qaRowCache[id] || {};
            return '<span class="label label-default">' + qaEsc(row.sampleCode || id) + '</span>';
        }).join('');
        if (ids.length > 12) {
            chips += '<span class="text-muted">' + qaSprintf(QA_LABELS.andMore, ids.length - 12) + '</span>';
        }
        $('#qaNoteChips').html(chips);

        var groups = QA_REASONS[view] || {};
        var options = '<option value="">' + qaEsc(QA_LABELS.chooseReason) + '</option>';
        Object.keys(groups).forEach(function (group) {
            options += '<optgroup label="' + qaEsc(group) + '">';
            Object.keys(groups[group]).forEach(function (key) {
                options += '<option value="' + qaEsc(key) + '">' + qaEsc(groups[group][key]) + '</option>';
            });
            options += '</optgroup>';
        });
        $('#qaNoteReason').html(options).val('');

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
            side: qaNoteTarget.view,
            reasonKey: reasonKey,
            reasonLabel: qaReasonLabel(qaNoteTarget.view, reasonKey),
            text: text,
            author: QA_USER,
            role: QA_ROLE || QA_SIDE_LABELS[qaNoteTarget.view],
            when: moment().format('DD-MMM-YYYY HH:mm'),
            expected: $('#qaNoteExpected').val() || '',
            example: false
        };

        qaNoteTarget.ids.forEach(function (id) {
            qaAppendNote(id, $.extend({}, note));
        });

        $('#qaNoteModal').modal('hide');
        QA_VIEWS.forEach(qaRefreshNoteCells);
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
                    (note.example ? '<span class="qa-example-badge">' + qaEsc(QA_LABELS.example) + '</span>' : '') +
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
        $('#qaThreadAdd').data('recordId', recordId)
            .toggle(QA_STAGE_VIEW[row.stage] === qaSide);
        qaShowModal('#qaThreadModal');
    }

    function qaAddFromThread() {
        var recordId = String($('#qaThreadAdd').data('recordId') || '');
        if (!recordId) { return; }
        qaShowNoteModal(qaSide, [recordId]);
    }

    // ----------------------------------------------------------- selection

    function qaUpdateSelection(view) {
        var count = qaSelection[view].length;
        var owned = view === qaSide;
        $('#qa-selection-' + view).text(count ? qaSprintf(QA_LABELS.selected, count) : '');
        $('.qa-add-note[data-view="' + view + '"]')
            .prop('disabled', !owned || count === 0)
            .attr('title', owned ? '' : QA_LABELS.readOnly);
        $('#qa-locked-' + view).toggle(!owned);
    }

    function qaApplyFilters() {
        qaLoadSummary();
        QA_VIEWS.forEach(function (view) {
            if (qaTables[view]) { qaTables[view].fnDraw(); }
        });
    }

    function qaResetFilters() {
        $('#provinceId, #districtId, #partnerId, #bucket').val('').trigger('change');
        $('#facilityId, #labId').val(null).trigger('change');
        $('#districtId').html('<option value=""><?= _jsTranslate('-- All --'); ?></option>');
        qaApplyFilters();
    }

    function qaExport(view) {
        $.blockUI();
        $.post(QA_URL, $.extend({ section: 'export', view: view }, qaFilters()), function (token) {
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

        $('#provinceId, #partnerId, #bucket').select2();
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
            // Switching side is switching job, so it lands on that side's queue.
            $('#qaTabs a[data-view="' + qaSide + '"]').tab('show');
            QA_VIEWS.forEach(qaUpdateSelection);
            QA_VIEWS.forEach(qaRefreshNoteCells);
        });

        $('#qaTabs a[data-toggle="tab"]').on('shown.bs.tab', function () {
            var view = $(this).data('view');
            qaInitTable(view);
            // A tab that was hidden when its table was built has no width to
            // measure, so the columns come out wrong until it is asked again.
            if (qaTables[view]) { qaTables[view].fnAdjustColumnSizing(); }
        });

        $(document).on('change', '.qa-row-check', function () {
            var view = $(this).data('view');
            var id = String($(this).val());
            var index = qaSelection[view].indexOf(id);
            if (this.checked && index === -1) {
                qaSelection[view].push(id);
            } else if (!this.checked && index !== -1) {
                qaSelection[view].splice(index, 1);
            }
            qaUpdateSelection(view);
        });

        $(document).on('change', '.qa-check-all', function () {
            var view = $(this).data('view');
            var checked = this.checked;
            $('#qaTable-' + view).find('.qa-row-check').prop('checked', checked).each(function () {
                var id = String($(this).val());
                var index = qaSelection[view].indexOf(id);
                if (checked && index === -1) {
                    qaSelection[view].push(id);
                } else if (!checked && index !== -1) {
                    qaSelection[view].splice(index, 1);
                }
            });
            qaUpdateSelection(view);
        });

        $(document).on('click', '[data-thread]', function () {
            qaOpenThread(String($(this).data('thread')));
        });

        $(document).on('click', '[data-add]', function () {
            qaShowNoteModal(qaSide, [String($(this).data('add'))]);
        });

        QA_VIEWS.forEach(qaInitTable);
        QA_VIEWS.forEach(qaUpdateSelection);
        qaLoadSummary();
    });
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
