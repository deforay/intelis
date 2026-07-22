<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

$title = _translate("EID | PMTCT Cascade Report");

require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$labId = $general->getSystemConfig('sc_testing_lab_id') ?? null;

if ($general->isLISInstance() && !empty($labId)) {
    $testingLabs = $facilitiesService->getTestingLabs('eid', true, false, "facility_id = " . $labId);
} else {
    $testingLabs = $facilitiesService->getTestingLabs('eid');
}
$testingLabsDropdown = $general->generateSelectOptions($testingLabs, null, "-- Select --");

$provinces = $db->rawQuery("SELECT province_id, province_name FROM province_details ORDER BY province_name");
?>
<style>
    .pmtct-tabs {
        margin-top: 18px;
    }

    /* ---- PMTCT cascade snapshot ---- */
    .pmtct-snapshot {
        margin-top: 12px;
    }

    .pmtct-hero {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 16px;
    }

    .pmtct-hero-card {
        flex: 1 1 0;
        min-width: 220px;
        border-radius: 6px;
        padding: 14px 18px;
        color: #fff;
    }

    .pmtct-hero-card.hero-good {
        background: linear-gradient(135deg, #00a65a, #008d4c);
    }

    .pmtct-hero-card.hero-danger {
        background: linear-gradient(135deg, #dd4b39, #c0392b);
    }

    /* Deeper red for harm already realised (positive infants), to read as more
       grave than the high-VL risk card beside it. */
    .pmtct-hero-card.hero-critical {
        background: linear-gradient(135deg, #a93226, #7b241c);
    }

    .pmtct-hero-card .pmtct-hero-label {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .4px;
        opacity: .9;
    }

    .pmtct-hero-card .pmtct-hero-value {
        font-size: 34px;
        font-weight: 700;
        line-height: 1.1;
        margin-top: 2px;
    }

    .pmtct-hero-card .pmtct-hero-sub {
        font-size: 12px;
        opacity: .92;
        margin-top: 5px;
    }

    .pmtct-funnel {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        align-items: stretch;
        padding: 6px 4px;
    }

    .pmtct-funnel-step {
        flex: 1 1 0;
        min-width: 120px;
        background: #f4f6f9;
        border: 1px solid #e1e5eb;
        border-radius: 4px;
        padding: 10px 8px;
        text-align: center;
        position: relative;
    }

    .pmtct-funnel-step .pmtct-funnel-stage {
        font-size: 11px;
        color: #666;
        text-transform: uppercase;
        letter-spacing: .3px;
        margin-bottom: 4px;
    }

    .pmtct-funnel-step .pmtct-funnel-count {
        font-size: 22px;
        font-weight: 600;
        color: #222;
    }

    .pmtct-funnel-step .pmtct-funnel-pct {
        font-size: 11px;
        color: #999;
        margin-top: 2px;
    }

    .pmtct-funnel-step.step-danger {
        background: #fdecea;
        border-color: #f5b7b1;
    }

    .pmtct-funnel-step.step-danger .pmtct-funnel-count {
        color: #c0392b;
    }

    .pmtct-funnel-arrow {
        display: flex;
        align-items: center;
        color: #ccc;
        font-size: 18px;
    }

    .pmtct-funnel-tier-label {
        font-size: 12px;
        color: #888;
        text-transform: uppercase;
        letter-spacing: .4px;
        font-weight: 600;
        margin: 4px 0 2px 4px;
    }

    .pmtct-empty-panel {
        padding: 24px;
        background: #fafbfc;
        border: 1px dashed #d0d6de;
        border-radius: 4px;
        text-align: center;
        color: #888;
        font-size: 13px;
    }

    /* Purple = risk-timing signal (high maternal VL before the birth),
       distinct from the red "current risk" and dark-red "harm" cards. */
    .pmtct-hero-card.hero-prebirth {
        background: linear-gradient(135deg, #8e44ad, #6c3483);
    }

    .pmtct-clickable {
        cursor: pointer;
        transition: transform .08s ease, box-shadow .08s ease;
    }

    .pmtct-clickable:hover {
        transform: translateY(-1px);
        box-shadow: 0 3px 8px rgba(0, 0, 0, .18);
    }

    .pmtct-drill-hint {
        font-size: 10px;
        opacity: .75;
        margin-top: 6px;
    }

    .pmtct-modal-dialog {
        width: 94%;
        max-width: 1450px;
    }

    .pmtct-meta-strip {
        display: flex;
        flex-wrap: wrap;
        gap: 18px;
        padding: 8px 12px;
        background: #f4f6f9;
        border: 1px solid #e1e5eb;
        border-radius: 4px;
        margin-bottom: 12px;
        font-size: 12px;
    }

    .pmtct-meta-strip .pm-lbl {
        color: #888;
        text-transform: uppercase;
        font-size: 10px;
        letter-spacing: .3px;
        display: block;
    }

    .pmtct-meta-strip .pm-val {
        font-weight: 600;
        color: #222;
        font-size: 13px;
    }

    .pmtct-badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 10px;
        font-size: 11px;
        font-weight: 600;
        white-space: nowrap;
    }

    .pmtct-badge.b-danger {
        background: #fdecea;
        color: #c0392b;
        border: 1px solid #f5b7b1;
    }

    .pmtct-badge.b-good {
        background: #eafaf1;
        color: #1e8449;
        border: 1px solid #a9dfbf;
    }

    .pmtct-badge.b-muted {
        background: #f0f1f3;
        color: #777;
        border: 1px solid #d8dbe0;
    }

    .pmtct-subhead {
        font-size: 12px;
        color: #888;
        text-transform: uppercase;
        letter-spacing: .4px;
        font-weight: 600;
        margin: 16px 0 6px;
    }

    #pmtctDrillTable td,
    #pmtctDrillTable th,
    .pmtct-history-table td,
    .pmtct-history-table th {
        font-size: 12px;
    }

    .select2-selection__choice {
        color: black !important;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <h1><em class="fa-solid fa-book"></em> <?= _htmlTranslate("PMTCT Cascade Report"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?= _htmlTranslate("Home"); ?></a></li>
            <li class="active"><?= _htmlTranslate("PMTCT Cascade Report"); ?></li>
        </ol>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box" id="filterDiv">
                    <table aria-describedby="table" class="table pageFilters" aria-hidden="true"
                        style="margin-left:1%;margin-top:20px;width:98%;">
                        <tr>
                            <td><strong><?= _htmlTranslate("EID Sample Collection Date"); ?>&nbsp;:</strong></td>
                            <td>
                                <input type="text" id="sampleCollectionDate" name="sampleCollectionDate"
                                    class="form-control daterangefield"
                                    placeholder="<?= _htmlTranslate('Select Collection Date'); ?>" readonly
                                    style="background:#fff;" />
                            </td>
                            <td><strong><?= _htmlTranslate("Province"); ?>&nbsp;:</strong></td>
                            <td>
                                <select class="form-control" id="provinceId" name="provinceId" style="width:100%;">
                                    <option value=""><?= _htmlTranslate("-- Select --"); ?></option>
                                    <?php foreach ($provinces as $p) { ?>
                                        <option value="<?= $p['province_id']; ?>">
                                            <?= htmlspecialchars((string) $p['province_name']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?= _htmlTranslate("Testing Lab"); ?>&nbsp;:</strong></td>
                            <td>
                                <select class="form-control" id="labName" name="labName" style="width:100%;">
                                    <?= $testingLabsDropdown; ?>
                                </select>
                            </td>
                            <td><strong><?= _htmlTranslate("Match Status"); ?>&nbsp;:</strong></td>
                            <td>
                                <select class="form-control" id="matchStatus" name="matchStatus" style="width:100%;">
                                    <option value="all"><?= _htmlTranslate("All children"); ?></option>
                                    <option value="matched" selected><?= _htmlTranslate("Matched (mother has VL test)"); ?>
                                    </option>
                                    <option value="unmatched"><?= _htmlTranslate("Not matched"); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4">
                                <input type="button" onclick="searchPmtctCascade()" value="<?= _htmlTranslate("Search"); ?>"
                                    class="btn btn-success btn-sm">
                                &nbsp;<button class="btn btn-danger btn-sm"
                                    onclick="document.location.href = document.location"><span><?= _htmlTranslate('Reset'); ?></span></button>
                                &nbsp;<button class="btn btn-primary btn-sm pull-right" type="button"
                                    onclick="exportPmtctCascade()"><em
                                        class="fa-solid fa-cloud-arrow-down"></em>&nbsp;<?= _htmlTranslate("Export to Excel"); ?></button>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- PMTCT cascade snapshot — the one-glance picture: how many matched
             mothers are virally suppressed, and how many still have high VL. -->
        <div class="row pmtct-snapshot">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><?= _htmlTranslate("PMTCT Snapshot"); ?></h3>
                    </div>
                    <div class="box-body">
                        <!-- Hero: the two numbers that matter most -->
                        <div class="pmtct-hero" id="pmtctHero">
                            <div class="pmtct-hero-card hero-good">
                                <div class="pmtct-hero-label"><?= _htmlTranslate("Maternal Viral Suppression"); ?></div>
                                <div class="pmtct-hero-value" id="heroSuppressionPct">&mdash;</div>
                                <div class="pmtct-hero-sub" id="heroSuppressionSub">&nbsp;</div>
                            </div>
                            <div class="pmtct-hero-card hero-danger pmtct-clickable" onclick="openHighVlMothersDrill()">
                                <div class="pmtct-hero-label"><?= _htmlTranslate("Mothers with High VL (≥1000 cp/mL)"); ?></div>
                                <div class="pmtct-hero-value" id="heroHighVl">&mdash;</div>
                                <div class="pmtct-hero-sub" id="heroHighVlSub">&nbsp;</div>
                                <div class="pmtct-drill-hint"><em class="fa-solid fa-magnifying-glass"></em> <?= _htmlTranslate("Click to view mothers"); ?></div>
                            </div>
                            <div class="pmtct-hero-card hero-critical pmtct-clickable" onclick="openPositiveChildrenDrill(false)">
                                <div class="pmtct-hero-label"><?= _htmlTranslate("HIV-Positive Infants"); ?></div>
                                <div class="pmtct-hero-value" id="heroPositiveInfants">&mdash;</div>
                                <div class="pmtct-hero-sub" id="heroPositiveInfantsSub">&nbsp;</div>
                                <div class="pmtct-drill-hint"><em class="fa-solid fa-magnifying-glass"></em> <?= _htmlTranslate("Click to view children"); ?></div>
                            </div>
                            <div class="pmtct-hero-card hero-prebirth pmtct-clickable" onclick="openPositiveChildrenDrill(true)">
                                <div class="pmtct-hero-label"><?= _htmlTranslate("Positive Infants — Mother High VL Before Birth"); ?></div>
                                <div class="pmtct-hero-value" id="heroPreBirth">&mdash;</div>
                                <div class="pmtct-hero-sub" id="heroPreBirthSub">&nbsp;</div>
                                <div class="pmtct-drill-hint"><em class="fa-solid fa-magnifying-glass"></em> <?= _htmlTranslate("Click to view children"); ?></div>
                            </div>
                        </div>

                        <!-- Maternal VL risk cascade. Enters in children (Children w/
                             Mother ID), then pivots to distinct mothers at "Matched
                             Mothers" and follows them through to those still at high VL. -->
                        <div class="pmtct-funnel-tier-label"><?= _htmlTranslate("Maternal VL Risk — matched mothers"); ?></div>
                        <div class="pmtct-funnel" id="pmtctMaternalFunnel">
                            <div class="pmtct-empty-panel"><?= _htmlTranslate("Loading…"); ?></div>
                        </div>
                        <div id="pmtctBridgeNote" style="font-size:11px; color:#999; margin:6px 0 0 4px;">&nbsp;</div>

                        <!-- Infant EID outcome cascade (children units): of the matched
                             children, how many were EID-tested and how many are positive. -->
                        <div class="pmtct-funnel-tier-label" style="margin-top:18px;"><?= _htmlTranslate("Infant EID Outcome — matched children"); ?></div>
                        <div class="pmtct-funnel" id="pmtctInfantFunnel"></div>

                        <!-- Operational context: all-time VL test volume + pending queue -->
                        <div id="pmtctVlVolumeStrip"
                            style="margin: 16px 4px 0; padding: 8px 12px; background:#fff; border-left:3px solid #d2d6de; border-radius:3px; font-size:12px; color:#666;">
                            &nbsp;
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Line-level table -->
        <div class="row">
            <div class="col-xs-12">
                <div class="box pmtct-tabs">
                    <div class="box-header">
                        <h3 class="box-title"><?= _htmlTranslate("Line-Level Records"); ?></h3>
                        <div class="pull-right" style="font-size:12px;color:#666;">
                            <?= _htmlTranslate("Rows shown follow the selected Match Status filter."); ?>
                        </div>
                    </div>
                    <div class="box-body table-responsive">
                        <table id="pmtctTable" class="table table-bordered table-striped"
                            aria-describedby="pmtct-line-level" aria-hidden="true" style="width:100%;">
                            <thead>
                                <tr>
                                    <th><?= _htmlTranslate("Child ID"); ?></th>
                                    <th><?= _htmlTranslate("Child Sex"); ?></th>
                                    <th><?= _htmlTranslate("Child Age in Months"); ?></th>
                                    <th><?= _htmlTranslate("Child Age in Weeks"); ?></th>
                                    <th><?= _htmlTranslate("Mother ID"); ?></th>
                                    <th><?= _htmlTranslate("EID Sample Code"); ?></th>
                                    <th><?= _htmlTranslate("Remote EID Sample Code"); ?></th>
                                    <th><?= _htmlTranslate("EID Sample Collection Date"); ?></th>
                                    <th><?= _htmlTranslate("EID Test Date"); ?></th>
                                    <th><?= _htmlTranslate("EID Sample Status"); ?></th>
                                    <th><?= _htmlTranslate("EID Result"); ?></th>
                                    <th><?= _htmlTranslate("EID Test Platform"); ?></th>
                                    <th><?= _htmlTranslate("EID Testing Lab"); ?></th>
                                    <th><?= _htmlTranslate("VL Sample Code"); ?></th>
                                    <th><?= _htmlTranslate("VL Sample Collection Date"); ?></th>
                                    <th><?= _htmlTranslate("VL Test Date"); ?></th>
                                    <th><?= _htmlTranslate("VL Result"); ?></th>
                                    <th><?= _htmlTranslate("VL Test Platform"); ?></th>
                                    <th><?= _htmlTranslate("VL Testing Lab"); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="19" class="dataTables_empty">
                                        <?= _htmlTranslate("Loading data from server"); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<!-- Drilldown modal: list of HIV-positive children OR high-VL mothers -->
<div class="modal fade" id="pmtctDrillModal" tabindex="-1" role="dialog" aria-labelledby="pmtctDrillModalLabel" aria-hidden="true">
    <div class="modal-dialog pmtct-modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="pmtctDrillModalLabel">&nbsp;</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?= _htmlTranslate('Close'); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="pmtct-meta-strip" id="pmtctDrillMeta" style="display:none;"></div>
                <div class="table-responsive" id="pmtctDrillTableWrap"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal"><?= _htmlTranslate("Close"); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- History modal: one child's EID timeline (with mother VL status at each
     test) or one mother's VL timeline (with her children's EID tests). -->
<div class="modal fade" id="pmtctHistoryModal" tabindex="-1" role="dialog" aria-labelledby="pmtctHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog pmtct-modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="pmtctHistoryModalLabel">&nbsp;</h4>
                <button type="button" class="close" data-dismiss="modal" aria-label="<?= _htmlTranslate('Close'); ?>"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body" id="pmtctHistoryBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default btn-sm" id="pmtctHistoryBack" style="display:none;">
                    <em class="fa-solid fa-arrow-left"></em>&nbsp;<?= _htmlTranslate("Back to list"); ?>
                </button>
                <button type="button" class="btn btn-default btn-sm" data-dismiss="modal"><?= _htmlTranslate("Close"); ?></button>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script>
    let pmtctTable = null;

    function readFilters() {
        return {
            sampleCollectionDate: $("#sampleCollectionDate").val(),
            provinceId: $("#provinceId").val(),
            labName: $("#labName").val(),
            matchStatus: $("#matchStatus").val()
        };
    }

    function searchPmtctCascade() {
        loadPmtctSummary();
        if (pmtctTable) {
            pmtctTable.fnDraw();
        }
    }

    // Render a horizontal cascade funnel. Each stage must be a subset of the
    // previous one *in the same unit* so the drop-off % stays meaningful. The
    // maternal and infant funnels are kept separate because they count different
    // units (distinct mothers vs children); the one children → mothers pivot
    // inside the maternal funnel is marked crossUnit so no % is drawn there.
    function renderPmtctFunnel(containerId, stages) {
        var $c = $('#' + containerId).empty();
        if (!stages || !stages.length) {
            $c.append('<div class="pmtct-empty-panel"><?= _jsTranslate('No data in selected period.'); ?></div>');
            return;
        }
        var prevCount = null;
        stages.forEach(function (s, i) {
            var pct = '';
            // crossUnit steps (e.g. children → distinct mothers) are a unit pivot,
            // not a drop-off, so we never draw a "% of previous stage" for them.
            if (!s.crossUnit && prevCount !== null && prevCount > 0) {
                pct = Math.round((s.count / prevCount) * 100) + '% <?= _jsTranslate("of previous stage"); ?>';
            }
            var cls = 'pmtct-funnel-step' + (s.tone ? ' step-' + s.tone : '');
            var $step = $('<div class="' + cls + '"></div>');
            $step.append('<div class="pmtct-funnel-stage">' + s.label + '</div>');
            $step.append('<div class="pmtct-funnel-count">' + (s.count || 0).toLocaleString() + '</div>');
            if (pct) { $step.append('<div class="pmtct-funnel-pct">' + pct + '</div>'); }
            if (s.drill) {
                $step.addClass('pmtct-clickable').attr('title', "<?= _jsTranslate('Click to view the records behind this number'); ?>").on('click', s.drill);
            }
            $c.append($step);
            if (i < stages.length - 1) {
                $c.append('<div class="pmtct-funnel-arrow"><em class="fa-solid fa-chevron-right"></em></div>');
            }
            prevCount = s.count;
        });
    }

    function renderPmtctSnapshot(s) {
        var mWithResult = s.mothersWithResult || 0;
        var mHighVl = s.mothersHighVl || 0;
        // Suppressed = matched mothers with a result but no high-VL reading.
        var mSuppressed = Math.max(0, mWithResult - mHighVl);
        var suppPct = mWithResult > 0 ? Math.round(100 * mSuppressed / mWithResult) : 0;
        var highPct = mWithResult > 0 ? Math.round(100 * mHighVl / mWithResult) : 0;

        $("#heroSuppressionPct").text(mWithResult > 0 ? suppPct + "%" : "—");
        $("#heroSuppressionSub").text(
            mWithResult > 0
                ? "<?= _jsTranslate('%s of %s matched mothers with a VL result are suppressed'); ?>"
                      .replace('%s', mSuppressed.toLocaleString()).replace('%s', mWithResult.toLocaleString())
                : "<?= _jsTranslate('No matched mothers with a VL result yet'); ?>"
        );
        $("#heroHighVl").text(mHighVl.toLocaleString());
        $("#heroHighVlSub").text(
            mWithResult > 0 ? "<?= _jsTranslate('%s% of mothers with a VL result'); ?>".replace('%s', highPct) : ""
        );

        // Outcome hero — infants who tested HIV-positive (the harm PMTCT prevents).
        var posInfants = s.positiveChildren || 0;
        var testedKids = s.testedChildren || 0;
        $("#heroPositiveInfants").text(posInfants.toLocaleString());
        $("#heroPositiveInfantsSub").text(
            testedKids > 0
                ? Math.round(100 * posInfants / testedKids) + "% <?= _jsTranslate('of children with an EID result'); ?>"
                : "<?= _jsTranslate('No EID results yet'); ?>"
        );

        // Maternal cascade. Enters in children (Children w/ Mother ID) then pivots
        // to distinct mothers at "Matched Mothers" (crossUnit: a unit change, not a
        // drop-off, so no % is drawn there). Every step after it stays in
        // distinct-mother units, so those %s are honest. High VL is the actionable
        // endpoint; Suppressed lives in the green hero (complementary outcome).
        // Pre-birth risk hero — of the distinct HIV-positive children, how many
        // had a mother with a documented high VL before the child's birth.
        var posDistinct = s.positiveChildrenDistinct || 0;
        var preBirth = s.preBirthHighVlChildren || 0;
        $("#heroPreBirth").text(preBirth.toLocaleString());
        $("#heroPreBirthSub").text(
            posDistinct > 0
                ? "<?= _jsTranslate('of %s HIV-positive children (needs child DOB on record)'); ?>".replace('%s', posDistinct.toLocaleString())
                : "<?= _jsTranslate('No HIV-positive children in the selected period'); ?>"
        );

        renderPmtctFunnel('pmtctMaternalFunnel', [
            { label: "<?= _jsTranslate('Children w/ Mother ID'); ?>", count: s.totalChildrenWithMotherId || 0 },
            { label: "<?= _jsTranslate('Matched Mothers'); ?>", count: s.distinctMothers || 0, crossUnit: true },
            { label: "<?= _jsTranslate('Mothers w/ VL Result'); ?>", count: mWithResult },
            { label: "<?= _jsTranslate('Mothers w/ High VL ≥1000'); ?>", count: mHighVl, tone: 'danger', drill: function () { openHighVlMothersDrill(); } }
        ]);

        // Pivot note — explain the children → mothers unit change without repeating
        // numbers already in the boxes.
        $("#pmtctBridgeNote").html(
            "<em class='fa-solid fa-circle-info'></em> " +
            "<?= _jsTranslate('Matched Mothers counts distinct mothers (siblings share a mother), so it is a unit change from the children at left — not a drop-off.'); ?>"
        );

        // Infant EID outcome — all in children units: matched children → those with
        // an EID result → those found HIV-positive (the actual transmissions).
        renderPmtctFunnel('pmtctInfantFunnel', [
            { label: "<?= _jsTranslate('Matched Children'); ?>", count: s.matchedChildren || 0 },
            { label: "<?= _jsTranslate('With EID Result'); ?>", count: s.testedChildren || 0 },
            { label: "<?= _jsTranslate('HIV-Positive'); ?>", count: s.positiveChildren || 0, tone: 'danger', drill: function () { openPositiveChildrenDrill(false); } }
        ]);

        // VL test volume strip — test-level (a mother may have many tests over time).
        var vlTests = s.vlTests || 0;
        var vlWithResult = s.vlTestsWithResult || 0;
        var vlPending = Math.max(0, vlTests - vlWithResult);
        $("#pmtctVlVolumeStrip").html(
            "<strong>" + vlTests.toLocaleString() + "</strong> <?= _jsTranslate('VL tests on record for matched mothers (all time)'); ?>" +
            " &nbsp;·&nbsp; <strong>" + vlWithResult.toLocaleString() + "</strong> <?= _jsTranslate('with result'); ?>" +
            " &nbsp;·&nbsp; <strong>" + vlPending.toLocaleString() + "</strong> <?= _jsTranslate('pending'); ?>"
        );
    }

    function loadPmtctSummary() {
        $.blockUI();
        $.post("/eid/management/getPmtctCascadeReport.php", $.extend({ action: "summary" }, readFilters()), function (data) {
            $.unblockUI();
            if (!data) { return; }
            try {
                var s = (typeof data === 'string') ? JSON.parse(data) : data;
                renderPmtctSnapshot(s);
            } catch (e) {
                console.error("PMTCT summary parse error", e);
            }
        });
    }

    function loadPmtctTable() {
        pmtctTable = $('#pmtctTable').dataTable({
            "bJQueryUI": false,
            "bAutoWidth": false,
            "bInfo": true,
            "iDisplayLength": 25,
            "bRetrieve": true,
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": "/eid/management/getPmtctCascadeReport.php",
            "fnServerData": function (sSource, aoData, fnCallback) {
                aoData.push({ "name": "action", "value": "linked" });
                $.each(readFilters(), function (k, v) { aoData.push({ "name": k, "value": v }); });
                $.ajax({
                    "dataType": 'json',
                    "type": "POST",
                    "url": sSource,
                    "data": aoData,
                    "success": fnCallback
                });
            },
            "aaSorting": [[7, "desc"]]
        });
    }

    // =====================================================================
    // Drilldowns & histories
    // =====================================================================

    var pmtctDrillRows = [];          // rows behind the currently open drill list
    var pmtctHistoryFromDrill = false; // whether Back should reopen the drill list

    function escHtml(v) {
        return $('<div></div>').text(v === null || v === undefined ? '' : String(v)).html();
    }

    function fmtIsoDate(iso) {
        if (!iso) { return ''; }
        var m = moment(iso, 'YYYY-MM-DD');
        return m.isValid() ? m.format('DD-MMM-YYYY') : escHtml(iso);
    }

    function vlCategoryBadge(cat) {
        if (cat === 'not suppressed') {
            return '<span class="pmtct-badge b-danger"><?= _jsTranslate('High ≥1000'); ?></span>';
        }
        if (cat === 'suppressed') {
            return '<span class="pmtct-badge b-good"><?= _jsTranslate('Suppressed'); ?></span>';
        }
        return '<span class="pmtct-badge b-muted"><?= _jsTranslate('No result'); ?></span>';
    }

    function preBirthBadge(flag) {
        if (flag === 'yes') {
            return '<span class="pmtct-badge b-danger"><?= _jsTranslate('Yes'); ?></span>';
        }
        if (flag === 'no') {
            return '<span class="pmtct-badge b-good"><?= _jsTranslate('No'); ?></span>';
        }
        return '<span class="pmtct-badge b-muted"><?= _jsTranslate('Unknown (no DOB)'); ?></span>';
    }

    function eidResultBadge(result, isPositive) {
        if (!result) { return '<span class="pmtct-badge b-muted"><?= _jsTranslate('No result'); ?></span>'; }
        var txt = escHtml(result);
        return isPositive ? '<span class="pmtct-badge b-danger">' + txt + '</span>' : txt;
    }

    // Mother's VL status at the time of one EID test (compact cell content).
    function motherVlAtTestCell(at) {
        if (!at) {
            return '<span class="pmtct-badge b-muted"><?= _jsTranslate('No VL result yet'); ?></span>';
        }
        var out = vlCategoryBadge(at.category);
        if (at.result) { out += ' ' + escHtml(at.result); }
        if (at.collectionDate) { out += ' <span style="color:#999;">(' + fmtIsoDate(at.collectionDate) + ')</span>'; }
        return out;
    }

    function freshDrillTable() {
        $('#pmtctDrillTableWrap').html('<table id="pmtctDrillTable" class="table table-bordered table-striped" style="width:100%;"></table>');
        return $('#pmtctDrillTable');
    }

    function renderDrillMeta(items) {
        var $m = $('#pmtctDrillMeta').empty();
        if (!items.length) { $m.hide(); return; }
        items.forEach(function (it) {
            $m.append('<div><span class="pm-lbl">' + it[0] + '</span><span class="pm-val">' + it[1] + '</span></div>');
        });
        $m.show();
    }

    // ---- HIV-positive children drilldown --------------------------------
    function openPositiveChildrenDrill(preBirthOnly) {
        $.blockUI();
        $.post("/eid/management/getPmtctCascadeReport.php",
            $.extend({ action: "positiveChildren", preBirthOnly: preBirthOnly ? 1 : 0 }, readFilters()),
            function (data) {
                $.unblockUI();
                var payload = (typeof data === 'string') ? JSON.parse(data) : data;
                var rows = payload.rows || [];
                pmtctDrillRows = rows;

                $('#pmtctDrillModalLabel').text(preBirthOnly
                    ? "<?= _jsTranslate('HIV-Positive Children — Mother High VL Before Birth'); ?>"
                    : "<?= _jsTranslate('HIV-Positive Children'); ?>");

                // A child recorded under two mother IDs gets one row per pair,
                // so count distinct children separately from the rows.
                var totalTests = 0, yes = 0, no = 0, unknown = 0, childKeys = {};
                rows.forEach(function (r) {
                    totalTests += r.positiveTestsInWindow || 0;
                    childKeys[r.childId || ('#' + r.eidId)] = true;
                    if (r.motherHighVlPreBirth === 'yes') { yes++; }
                    else if (r.motherHighVlPreBirth === 'no') { no++; }
                    else { unknown++; }
                });
                renderDrillMeta([
                    ["<?= _jsTranslate('Distinct children'); ?>", Object.keys(childKeys).length.toLocaleString()],
                    ["<?= _jsTranslate('Positive EID results in period'); ?>", totalTests.toLocaleString()],
                    ["<?= _jsTranslate('Mother high VL before birth'); ?>",
                        yes.toLocaleString() + " <?= _jsTranslate('yes'); ?> / " + no.toLocaleString() + " <?= _jsTranslate('no'); ?> / " + unknown.toLocaleString() + " <?= _jsTranslate('unknown'); ?>"]
                ]);

                var aaData = rows.map(function (r, i) {
                    var actions =
                        '<button type="button" class="btn btn-xs btn-primary pmtct-child-history-btn" data-idx="' + i + '"><em class="fa-solid fa-timeline"></em> <?= _jsTranslate('Child History'); ?></button> ' +
                        '<button type="button" class="btn btn-xs btn-default pmtct-mother-history-btn" data-idx="' + i + '"><em class="fa-solid fa-person-breastfeeding"></em> <?= _jsTranslate('Mother History'); ?></button>';
                    return [
                        escHtml(r.childId || ('#' + r.eidId)),
                        escHtml(r.childSex),
                        fmtIsoDate(r.childDob),
                        escHtml(r.motherId),
                        (r.positiveTestsInWindow || 0),
                        fmtIsoDate(r.latestPositiveDate),
                        (r.vlTests || 0),
                        (r.highVlTests || 0),
                        fmtIsoDate(r.firstHighVlDate),
                        vlCategoryBadge(r.latestVlCategory) + (r.latestVlDate ? ' <span style="color:#999;">(' + fmtIsoDate(r.latestVlDate) + ')</span>' : ''),
                        preBirthBadge(r.motherHighVlPreBirth),
                        actions
                    ];
                });

                freshDrillTable().dataTable({
                    aaData: aaData,
                    aoColumns: [
                        { sTitle: "<?= _jsTranslate('Child ID'); ?>" },
                        { sTitle: "<?= _jsTranslate('Sex'); ?>" },
                        { sTitle: "<?= _jsTranslate('Child DOB'); ?>" },
                        { sTitle: "<?= _jsTranslate('Mother ID'); ?>" },
                        { sTitle: "<?= _jsTranslate('Positive EID Tests'); ?>" },
                        { sTitle: "<?= _jsTranslate('Latest Positive Date'); ?>" },
                        { sTitle: "<?= _jsTranslate('Mother VL Tests'); ?>" },
                        { sTitle: "<?= _jsTranslate('High VL Tests'); ?>" },
                        { sTitle: "<?= _jsTranslate('First High VL Date'); ?>" },
                        { sTitle: "<?= _jsTranslate('Mother Latest VL'); ?>" },
                        { sTitle: "<?= _jsTranslate('High VL Before Birth'); ?>" },
                        { sTitle: "<?= _jsTranslate('Actions'); ?>", bSortable: false }
                    ],
                    bDestroy: true,
                    bAutoWidth: false,
                    iDisplayLength: 25
                });
                $('#pmtctDrillModal').modal('show');
            });
    }

    // ---- High-VL mothers drilldown --------------------------------------
    function openHighVlMothersDrill() {
        $.blockUI();
        $.post("/eid/management/getPmtctCascadeReport.php",
            $.extend({ action: "highVlMothers" }, readFilters()),
            function (data) {
                $.unblockUI();
                var payload = (typeof data === 'string') ? JSON.parse(data) : data;
                var rows = payload.rows || [];
                pmtctDrillRows = rows;

                $('#pmtctDrillModalLabel').text("<?= _jsTranslate('Mothers with High VL (≥1000 cp/mL)'); ?>");

                var vlTests = 0, posKids = 0;
                rows.forEach(function (r) { vlTests += r.vlTests || 0; posKids += r.positiveChildren || 0; });
                renderDrillMeta([
                    ["<?= _jsTranslate('Mothers'); ?>", rows.length.toLocaleString()],
                    ["<?= _jsTranslate('VL tests on record (all time)'); ?>", vlTests.toLocaleString()],
                    ["<?= _jsTranslate('HIV-positive children (in period)'); ?>", posKids.toLocaleString()]
                ]);

                var aaData = rows.map(function (r, i) {
                    var actions = '<button type="button" class="btn btn-xs btn-primary pmtct-mother-history-btn" data-idx="' + i + '"><em class="fa-solid fa-timeline"></em> <?= _jsTranslate('History'); ?></button>';
                    return [
                        escHtml(r.motherId),
                        (r.childrenInWindow || 0),
                        (r.positiveChildren || 0),
                        (r.vlTests || 0),
                        (r.highVlTests || 0),
                        fmtIsoDate(r.firstHighVlDate),
                        vlCategoryBadge(r.latestVlCategory) + (r.latestVlResult ? ' ' + escHtml(r.latestVlResult) : '') + (r.latestVlDate ? ' <span style="color:#999;">(' + fmtIsoDate(r.latestVlDate) + ')</span>' : ''),
                        actions
                    ];
                });

                freshDrillTable().dataTable({
                    aaData: aaData,
                    aoColumns: [
                        { sTitle: "<?= _jsTranslate('Mother ID'); ?>" },
                        { sTitle: "<?= _jsTranslate('Children (in period)'); ?>" },
                        { sTitle: "<?= _jsTranslate('HIV-Positive Children'); ?>" },
                        { sTitle: "<?= _jsTranslate('VL Tests'); ?>" },
                        { sTitle: "<?= _jsTranslate('High VL Tests'); ?>" },
                        { sTitle: "<?= _jsTranslate('First High VL Date'); ?>" },
                        { sTitle: "<?= _jsTranslate('Latest VL'); ?>" },
                        { sTitle: "<?= _jsTranslate('Actions'); ?>", bSortable: false }
                    ],
                    bDestroy: true,
                    bAutoWidth: false,
                    iDisplayLength: 25
                });
                $('#pmtctDrillModal').modal('show');
            });
    }

    // ---- History modal plumbing -----------------------------------------
    function showHistoryModal() {
        if ($('#pmtctDrillModal').hasClass('in')) {
            pmtctHistoryFromDrill = true;
            $('#pmtctDrillModal').one('hidden.bs.modal', function () {
                $('#pmtctHistoryModal').modal('show');
            }).modal('hide');
        } else {
            pmtctHistoryFromDrill = false;
            $('#pmtctHistoryModal').modal('show');
        }
        $('#pmtctHistoryBack').toggle(pmtctHistoryFromDrill);
    }

    function metaStripHtml(items) {
        var h = '<div class="pmtct-meta-strip">';
        items.forEach(function (it) {
            h += '<div><span class="pm-lbl">' + it[0] + '</span><span class="pm-val">' + it[1] + '</span></div>';
        });
        return h + '</div>';
    }

    function vlHistoryTableHtml(vlTests) {
        if (!vlTests || !vlTests.length) {
            return '<div class="pmtct-empty-panel"><?= _jsTranslate('No VL tests on record for this mother.'); ?></div>';
        }
        var h = '<div class="table-responsive"><table class="table table-bordered table-striped pmtct-history-table"><thead><tr>' +
            '<th>#</th>' +
            '<th><?= _jsTranslate('VL Sample Code'); ?></th>' +
            '<th><?= _jsTranslate('Collection Date'); ?></th>' +
            '<th><?= _jsTranslate('Test Date'); ?></th>' +
            '<th><?= _jsTranslate('Result'); ?></th>' +
            '<th><?= _jsTranslate('cp/mL'); ?></th>' +
            '<th><?= _jsTranslate('Log'); ?></th>' +
            '<th><?= _jsTranslate('Category'); ?></th>' +
            '<th><?= _jsTranslate('Platform'); ?></th>' +
            '<th><?= _jsTranslate('Testing Lab'); ?></th>' +
            '<th><?= _jsTranslate('Facility'); ?></th>' +
            '</tr></thead><tbody>';
        vlTests.forEach(function (t, i) {
            h += '<tr>' +
                '<td>' + (i + 1) + '</td>' +
                '<td>' + escHtml(t.sampleCode || t.remoteSampleCode) + '</td>' +
                '<td>' + fmtIsoDate(t.collectionDate) + '</td>' +
                '<td>' + fmtIsoDate(t.testedDate) + '</td>' +
                '<td>' + escHtml(t.result) + '</td>' +
                '<td>' + escHtml(t.resultAbsolute) + '</td>' +
                '<td>' + escHtml(t.resultLog) + '</td>' +
                '<td>' + vlCategoryBadge(t.category) + '</td>' +
                '<td>' + escHtml(t.platform) + '</td>' +
                '<td>' + escHtml(t.testingLab) + '</td>' +
                '<td>' + escHtml(t.facility) + '</td>' +
                '</tr>';
        });
        return h + '</tbody></table></div>';
    }

    function eidHistoryTableHtml(eidTests, showChildCol) {
        if (!eidTests || !eidTests.length) {
            return '<div class="pmtct-empty-panel"><?= _jsTranslate('No EID tests on record.'); ?></div>';
        }
        var h = '<div class="table-responsive"><table class="table table-bordered table-striped pmtct-history-table"><thead><tr>' +
            '<th>#</th>' +
            (showChildCol ? '<th><?= _jsTranslate('Child ID'); ?></th>' : '') +
            '<th><?= _jsTranslate('EID Sample Code'); ?></th>' +
            '<th><?= _jsTranslate('Collection Date'); ?></th>' +
            '<th><?= _jsTranslate('Test Date'); ?></th>' +
            '<th><?= _jsTranslate('Result'); ?></th>' +
            '<th><?= _jsTranslate('Status'); ?></th>' +
            '<th><?= _jsTranslate('Platform'); ?></th>' +
            '<th><?= _jsTranslate('Testing Lab'); ?></th>' +
            '<th><?= _jsTranslate('Mother VL Status at This Test'); ?></th>' +
            '</tr></thead><tbody>';
        eidTests.forEach(function (t, i) {
            h += '<tr>' +
                '<td>' + (i + 1) + '</td>' +
                (showChildCol ? '<td>' + escHtml(t.childId || ('#' + t.eidId)) + '</td>' : '') +
                '<td>' + escHtml(t.sampleCode || t.remoteSampleCode) + '</td>' +
                '<td>' + fmtIsoDate(t.collectionDate) + '</td>' +
                '<td>' + fmtIsoDate(t.testedDate) + '</td>' +
                '<td>' + eidResultBadge(t.result, t.isPositive) + '</td>' +
                '<td>' + escHtml(t.status) + '</td>' +
                '<td>' + escHtml(t.platform) + '</td>' +
                '<td>' + escHtml(t.testingLab) + '</td>' +
                '<td>' + motherVlAtTestCell(t.motherVlAtTest) + '</td>' +
                '</tr>';
        });
        return h + '</tbody></table></div>';
    }

    function openChildHistory(childId, eidId) {
        $.blockUI();
        $.post("/eid/management/getPmtctCascadeReport.php",
            { action: "childHistory", childId: childId || '', eidId: eidId || 0 },
            function (data) {
                $.unblockUI();
                var d = (typeof data === 'string') ? JSON.parse(data) : data;

                $('#pmtctHistoryModalLabel').text("<?= _jsTranslate('Child Test History'); ?>" + (d.childId ? ' — ' + d.childId : ''));

                var html = metaStripHtml([
                    ["<?= _jsTranslate('Child ID'); ?>", escHtml(d.childId || '—')],
                    ["<?= _jsTranslate('Child DOB'); ?>", fmtIsoDate(d.childDob) || '—'],
                    ["<?= _jsTranslate('Mother ID'); ?>", escHtml(d.motherId || '—')],
                    ["<?= _jsTranslate('EID tests'); ?>", (d.eidTests || []).length],
                    ["<?= _jsTranslate('Mother VL tests'); ?>", (d.vlTests || []).length],
                    ["<?= _jsTranslate('Mother high VL before birth'); ?>", preBirthBadge(d.motherHighVlPreBirth)]
                ]);
                html += '<div class="pmtct-subhead"><?= _jsTranslate('EID Test History (all time)'); ?></div>';
                html += eidHistoryTableHtml(d.eidTests, false);
                html += '<div class="pmtct-subhead"><?= _jsTranslate('Mother VL Test History (all time)'); ?></div>';
                html += vlHistoryTableHtml(d.vlTests);

                $('#pmtctHistoryBody').html(html);
                showHistoryModal();
            });
    }

    function openMotherHistory(motherId) {
        $.blockUI();
        $.post("/eid/management/getPmtctCascadeReport.php",
            { action: "motherHistory", motherId: motherId || '' },
            function (data) {
                $.unblockUI();
                var d = (typeof data === 'string') ? JSON.parse(data) : data;

                $('#pmtctHistoryModalLabel').text("<?= _jsTranslate('Mother Test History'); ?>" + (d.motherId ? ' — ' + d.motherId : ''));

                var highTests = (d.vlTests || []).filter(function (t) { return t.category === 'not suppressed'; }).length;
                var kids = {};
                (d.eidTests || []).forEach(function (t) { kids[t.childId || ('#' + t.eidId)] = true; });

                var html = metaStripHtml([
                    ["<?= _jsTranslate('Mother ID'); ?>", escHtml(d.motherId || '—')],
                    ["<?= _jsTranslate('VL tests'); ?>", (d.vlTests || []).length],
                    ["<?= _jsTranslate('High VL tests'); ?>", highTests],
                    ["<?= _jsTranslate('Linked children'); ?>", Object.keys(kids).length]
                ]);
                html += '<div class="pmtct-subhead"><?= _jsTranslate('VL Test History (all time)'); ?></div>';
                html += vlHistoryTableHtml(d.vlTests);
                html += '<div class="pmtct-subhead"><?= _jsTranslate('Children EID Tests (all time)'); ?></div>';
                html += eidHistoryTableHtml(d.eidTests, true);

                $('#pmtctHistoryBody').html(html);
                showHistoryModal();
            });
    }

    function exportPmtctCascade() {
        $.blockUI();
        $.post("/eid/management/pmtctCascadeReportExport.php", readFilters(), function (data) {
            $.unblockUI();
            if (!data) {
                alert("<?= _jsTranslate('Unable to generate the excel file'); ?>");
                return;
            }
            window.open('/download.php?f=' + data, '_blank');
        });
    }

    $(function () {
        $("#labName, #provinceId, #matchStatus").select2({
            placeholder: "<?= _jsTranslate('Select'); ?>"
        });
        $('#sampleCollectionDate').daterangepicker({
            locale: {
                cancelLabel: "<?= _jsTranslate('Clear'); ?>",
                format: 'DD-MMM-YYYY',
                separator: ' to '
            },
            startDate: moment().subtract(11, 'months').startOf('month'),
            endDate: moment(),
            maxDate: moment(),
            ranges: {
                'Today': [moment(), moment()],
                'Last 7 Days': [moment().subtract(6, 'days'), moment()],
                'Last 30 Days': [moment().subtract(29, 'days'), moment()],
                'Last 90 Days': [moment().subtract(89, 'days'), moment()],
                'Last 12 Months': [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')],
                'Current Year To Date': [moment().startOf('year'), moment()]
            }
        });
        // Drill-row action buttons (delegated: rows are re-rendered per drill)
        $(document).on('click', '.pmtct-child-history-btn', function () {
            var r = pmtctDrillRows[$(this).data('idx')] || {};
            openChildHistory(r.childId || '', r.eidId || 0);
        });
        $(document).on('click', '.pmtct-mother-history-btn', function () {
            var r = pmtctDrillRows[$(this).data('idx')] || {};
            openMotherHistory(r.motherId || '');
        });
        $('#pmtctHistoryBack').on('click', function () {
            $('#pmtctHistoryModal').one('hidden.bs.modal', function () {
                $('#pmtctDrillModal').modal('show');
            }).modal('hide');
        });

        loadPmtctSummary();
        loadPmtctTable();
    });
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
