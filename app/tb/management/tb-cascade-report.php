<?php

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;

$title = _translate("TB | Cascade Report");

require_once APPLICATION_PATH . '/header.php';

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);

$labId = $general->getSystemConfig('sc_testing_lab_id') ?? null;
if ($general->isLISInstance() && !empty($labId)) {
    $testingLabs = $facilitiesService->getTestingLabs('tb', true, false, "facility_id = " . $labId);
} else {
    $testingLabs = $facilitiesService->getTestingLabs('tb');
}
$testingLabsDropdown = $general->generateSelectOptions($testingLabs, null, "-- Select --");

$healthFacilites = $facilitiesService->getHealthFacilities('tb');
$facilitiesDropdown = $general->generateSelectOptions($healthFacilites, null, "-- Select --");

$provinces = $db->rawQuery("SELECT province_id, province_name FROM province_details ORDER BY province_name");

$testTypes = $db->rawQuery("SELECT DISTINCT test_type FROM tb_tests WHERE test_type IS NOT NULL AND test_type <> '' ORDER BY test_type");

// Reusable cascade funnel (main cascade + referral branch) — shared with the TB dashboard.
require_once __DIR__ . '/_tbCascadeFunnel.php';
?>
<style>
    .tbc-kpi-row {
        margin-top: 10px;
    }

    .tbc-kpi-card {
        background: #fff;
        border-left: 4px solid #3c8dbc;
        border-radius: 3px;
        box-shadow: 0 1px 1px rgba(0, 0, 0, .05);
        padding: 12px 14px;
        margin-bottom: 12px;
        min-height: 92px;
    }

    .tbc-kpi-card .tbc-kpi-label {
        font-size: 12px;
        color: #888;
        text-transform: uppercase;
        letter-spacing: .4px;
    }

    .tbc-kpi-card .tbc-kpi-value {
        font-size: 26px;
        font-weight: 600;
        color: #222;
        line-height: 1.2;
    }

    .tbc-kpi-card .tbc-kpi-sub {
        font-size: 11px;
        color: #999;
        margin-top: 4px;
    }

    .tbc-kpi-card.kpi-warn {
        border-left-color: #f39c12;
    }

    .tbc-kpi-card.kpi-danger {
        border-left-color: #dd4b39;
    }

    .tbc-kpi-card.kpi-good {
        border-left-color: #00a65a;
    }

    .tbc-kpi-card.kpi-info {
        border-left-color: #00c0ef;
    }

    .tbc-section {
        margin-top: 18px;
    }

    /* Cascade funnel + referral branch styles live in _tbCascadeFunnel.php (shared with the dashboard). */

    .select2-selection__choice {
        color: black !important;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <h1><em class="fa-solid fa-diagram-project"></em> <?= _translate("TB Cascade Report"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?= _translate("Home"); ?></a></li>
            <li class="active"><?= _translate("TB Cascade Report"); ?></li>
        </ol>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box" id="filterDiv">
                    <table aria-describedby="table" class="table pageFilters" aria-hidden="true"
                        style="margin-left:1%;margin-top:20px;width:98%;">
                        <tr>
                            <td><strong><?= _translate("Sample Collection Date"); ?>&nbsp;:</strong></td>
                            <td>
                                <input type="text" id="sampleCollectionDate" name="sampleCollectionDate"
                                    class="form-control daterangefield"
                                    placeholder="<?= _translate('Select Collection Date'); ?>" readonly
                                    style="background:#fff;" />
                            </td>
                            <td><strong><?= _translate("Province"); ?>&nbsp;:</strong></td>
                            <td>
                                <select class="form-control" id="provinceId" name="provinceId" style="width:100%;">
                                    <option value=""><?= _translate("-- Select --"); ?></option>
                                    <?php foreach ($provinces as $p) { ?>
                                        <option value="<?= (int) $p['province_id']; ?>">
                                            <?= htmlspecialchars((string) $p['province_name']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?= _translate("Testing Lab"); ?>&nbsp;:</strong></td>
                            <td>
                                <select class="form-control" id="labName" name="labName" style="width:100%;">
                                    <?= $testingLabsDropdown; ?>
                                </select>
                            </td>
                            <td><strong><?= _translate("Collection Site"); ?>&nbsp;:</strong></td>
                            <td>
                                <select class="form-control" id="facilityName" name="facilityName" multiple="multiple"
                                    style="width:100%;">
                                    <?= $facilitiesDropdown; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?= _translate("Test Type"); ?>&nbsp;:</strong></td>
                            <td>
                                <select class="form-control" id="testType" name="testType" style="width:100%;">
                                    <option value=""><?= _translate("-- Any --"); ?></option>
                                    <?php foreach ($testTypes as $tt) { ?>
                                        <option value="<?= htmlspecialchars((string) $tt['test_type']); ?>">
                                            <?= htmlspecialchars((string) $tt['test_type']); ?>
                                        </option>
                                    <?php } ?>
                                </select>
                            </td>
                            <td><strong><?= _translate("Final Result Entered"); ?>&nbsp;:</strong></td>
                            <td>
                                <select class="form-control" id="finalized" name="finalized" style="width:100%;">
                                    <option value=""><?= _translate("-- Any --"); ?></option>
                                    <option value="yes"><?= _translate("Yes"); ?></option>
                                    <option value="no"><?= _translate("No"); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4">
                                <input type="button" onclick="searchCascade()" value="<?= _translate("Search"); ?>"
                                    class="btn btn-success btn-sm">
                                &nbsp;<button class="btn btn-danger btn-sm"
                                    onclick="document.location.href = document.location"><span><?= _translate('Reset'); ?></span></button>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- KPI cards: single row of 4 — the headline numbers managers care about -->
        <div class="row tbc-kpi-row">
            <div class="col-md-3 col-sm-6">
                <div class="tbc-kpi-card">
                    <div class="tbc-kpi-label"><?= _translate("Total Samples"); ?></div>
                    <div class="tbc-kpi-value" id="kpiTotal">&mdash;</div>
                    <div class="tbc-kpi-sub" id="kpiTotalSub">&nbsp;</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="tbc-kpi-card kpi-warn">
                    <div class="tbc-kpi-label"><?= _translate("In Pipeline"); ?></div>
                    <div class="tbc-kpi-value" id="kpiInPipeline">&mdash;</div>
                    <div class="tbc-kpi-sub" id="kpiInPipelineSub">&nbsp;</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="tbc-kpi-card kpi-good">
                    <div class="tbc-kpi-label"><?= _translate("Accepted"); ?></div>
                    <div class="tbc-kpi-value" id="kpiAccepted">&mdash;</div>
                    <div class="tbc-kpi-sub" id="kpiAcceptedSub">&nbsp;</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="tbc-kpi-card kpi-danger">
                    <div class="tbc-kpi-label"><?= _translate("Rejected / Failed"); ?></div>
                    <div class="tbc-kpi-value" id="kpiRejFail">&mdash;</div>
                    <div class="tbc-kpi-sub" id="kpiRejFailSub">&nbsp;</div>
                </div>
            </div>
        </div>

        <!-- Thin secondary stats strip — TATs, lab/site counts, hygiene anomalies -->
        <div class="row">
            <div class="col-xs-12">
                <div id="tbcSecondaryStats" style="margin: 0 4px 12px 4px; padding: 8px 12px; background: #fff; border-left: 3px solid #d2d6de; border-radius: 3px; font-size: 12px; color: #666;">
                    &nbsp;
                </div>
            </div>
        </div>

        <!-- Cascade funnel + referral branch -->
        <div class="row tbc-section">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><?= _translate("Cascade Funnel"); ?></h3>
                        <div class="pull-right" style="font-size:12px;color:#666;">
                            <?= _translate("Counts at each stage in the selected period. Drop-off % shown below each stage relative to the previous stage."); ?>
                        </div>
                    </div>
                    <div class="box-body">
                        <?php tbCascadeFunnelMarkup('tbc'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Explore toolbar — drilldowns load on demand to keep the default page light -->
        <div class="row tbc-section">
            <div class="col-xs-12">
                <div style="margin: 0 4px; padding: 10px 12px; background: #fff; border-radius: 3px; box-shadow: 0 1px 1px rgba(0,0,0,.05);">
                    <strong style="color:#555;"><?= _translate("Explore:"); ?></strong>
                    &nbsp;
                    <button type="button" class="btn btn-default btn-sm tbc-drill-btn" data-drill="perLab">
                        <em class="fa-solid fa-flask-vial"></em> <?= _translate("Per-Lab Breakdown"); ?>
                    </button>
                    <button type="button" class="btn btn-default btn-sm tbc-drill-btn" data-drill="referralMatrix">
                        <em class="fa-solid fa-share-from-square"></em> <?= _translate("Referral Pairs"); ?>
                    </button>
                    <button type="button" class="btn btn-default btn-sm tbc-drill-btn" data-drill="detail">
                        <em class="fa-solid fa-magnifying-glass"></em> <?= _translate("Detail Samples"); ?>
                    </button>
                    <span style="font-size:11px; color:#999; margin-left:8px;">
                        <em class="fa-solid fa-circle-info"></em> <?= _translate("Tables load on demand. Click a button to open."); ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Per-Lab Breakdown (hidden until clicked) -->
        <div class="row tbc-section tbc-drill-pane" id="drillPane_perLab" style="display:none;">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><?= _translate("Per-Lab Breakdown"); ?></h3>
                        <div class="pull-right" style="font-size:12px;color:#666;">
                            <?= _translate("Originator = samples this lab received as the primary testing lab. Referred-in = samples this lab received from another lab."); ?>
                        </div>
                    </div>
                    <div class="box-body table-responsive">
                        <table id="perLabTable" class="table table-bordered table-striped"
                            aria-describedby="tbc-per-lab" aria-hidden="true" style="width:100%;">
                            <thead>
                                <tr>
                                    <th><?= _translate("Lab"); ?></th>
                                    <th><?= _translate("Originator Samples"); ?></th>
                                    <th><?= _translate("Referred-in Samples"); ?></th>
                                    <th><?= _translate("Tests Performed"); ?></th>
                                    <th><?= _translate("Accepted"); ?></th>
                                    <th><?= _translate("Rejected"); ?></th>
                                    <th><?= _translate("Failed"); ?></th>
                                    <th><?= _translate("Pending at Lab"); ?></th>
                                    <th><?= _translate("Avg Coll → Recv (days)"); ?></th>
                                    <th><?= _translate("Avg Recv → Tested (days)"); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="10" class="dataTables_empty"><?= _translate("Loading data from server"); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Referral Pairs (hidden until clicked) -->
        <div class="row tbc-section tbc-drill-pane" id="drillPane_referralMatrix" style="display:none;">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><?= _translate("Referral Pairs (From → To)"); ?></h3>
                    </div>
                    <div class="box-body table-responsive">
                        <table id="referralMatrixTable" class="table table-bordered table-striped"
                            aria-describedby="tbc-referral-matrix" aria-hidden="true" style="width:100%;">
                            <thead>
                                <tr>
                                    <th><?= _translate("Referring Lab"); ?></th>
                                    <th><?= _translate("Receiving Lab"); ?></th>
                                    <th><?= _translate("Referred"); ?></th>
                                    <th><?= _translate("Awaiting Receipt"); ?></th>
                                    <th><?= _translate("Received, Not Tested"); ?></th>
                                    <th><?= _translate("Tested & Accepted"); ?></th>
                                    <th><?= _translate("Tested & Rejected / Failed"); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="7" class="dataTables_empty"><?= _translate("Loading data from server"); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detail Samples (hidden until clicked) -->
        <div class="row tbc-section tbc-drill-pane" id="drillPane_detail" style="display:none;">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><?= _translate("Detail Samples — One Row per Test"); ?></h3>
                        <div class="pull-right" style="font-size:12px;color:#666;">
                            <?= _translate("Each row is one (sample × test × lab) combination."); ?>
                        </div>
                    </div>
                    <div class="box-body table-responsive">
                        <table id="detailTable" class="table table-bordered table-striped"
                            aria-describedby="tbc-detail" aria-hidden="true" style="width:100%;">
                            <thead>
                                <tr>
                                    <th><?= _translate("Sample Code"); ?></th>
                                    <th><?= _translate("Collection Site"); ?></th>
                                    <th><?= _translate("Primary Lab"); ?></th>
                                    <th><?= _translate("Performing Lab"); ?></th>
                                    <th><?= _translate("Lab Role"); ?></th>
                                    <th><?= _translate("Test Type"); ?></th>
                                    <th><?= _translate("Test Result"); ?></th>
                                    <th><?= _translate("Sample Status"); ?></th>
                                    <th><?= _translate("Collection Date"); ?></th>
                                    <th><?= _translate("Received at Lab"); ?></th>
                                    <th><?= _translate("Tested"); ?></th>
                                    <th><?= _translate("Final Interpretation"); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="12" class="dataTables_empty"><?= _translate("Loading data from server"); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </section>
</div>

<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="/assets/plugins/daterangepicker/daterangepicker.js"></script>
<script>
    let perLabTable = null;
    let referralMatrixTable = null;
    let detailTable = null;

    function readFilters() {
        return {
            sampleCollectionDate: $("#sampleCollectionDate").val(),
            provinceId: $("#provinceId").val(),
            labName: $("#labName").val(),
            facilityName: $("#facilityName").val(),
            testType: $("#testType").val(),
            finalized: $("#finalized").val()
        };
    }

    function searchCascade() {
        loadSummary();
        if (perLabTable) { perLabTable.fnDraw(); }
        if (referralMatrixTable) { referralMatrixTable.fnDraw(); }
        if (detailTable) { detailTable.fnDraw(); }
    }

    function fmtPct(num, denom) {
        if (!denom || denom <= 0) return "";
        return Math.round((num / denom) * 100) + "%";
    }

    // renderFunnel / renderReferralFunnel now live in _tbCascadeFunnel.php as
    // tbcRenderFunnel / tbcRenderCascade (shared with the TB dashboard).

    function loadSummary() {
        $.blockUI();
        $.post("/tb/management/getTbCascadeReport.php", $.extend({ action: "summary" }, readFilters()), function (data) {
            $.unblockUI();
            if (!data) return;
            var s = (typeof data === 'string') ? JSON.parse(data) : data;

            // Total
            $("#kpiTotal").text((s.total || 0).toLocaleString());
            $("#kpiTotalSub").text(s.distinctFacilities + " <?= _jsTranslate('collection sites'); ?> · " + s.distinctLabs + " <?= _jsTranslate('labs'); ?>");

            // In Pipeline = anything still active (at CS + at Lab + result entered but not yet Accepted + referred in transit)
            var inPipeline = (s.atCollectionSite || 0) + (s.atTestingLab || 0) + (s.awaitingApproval || 0) + (s.referred || 0);
            $("#kpiInPipeline").text(inPipeline.toLocaleString());
            $("#kpiInPipelineSub").text(
                (s.atCollectionSite || 0) + " <?= _jsTranslate('at CS'); ?> · " +
                (s.atTestingLab || 0) + " <?= _jsTranslate('at Lab'); ?> · " +
                (s.awaitingApproval || 0) + " <?= _jsTranslate('awaiting approval'); ?>"
            );

            // Accepted
            $("#kpiAccepted").text((s.accepted || 0).toLocaleString());
            $("#kpiAcceptedSub").text(fmtPct(s.accepted, s.total) + " <?= _jsTranslate('of total'); ?>");

            // Rejected / Failed
            var rf = (s.rejected || 0) + (s.testFailed || 0);
            $("#kpiRejFail").text(rf.toLocaleString());
            $("#kpiRejFailSub").text((s.rejected || 0) + " <?= _jsTranslate('rejected'); ?> · " + (s.testFailed || 0) + " <?= _jsTranslate('failed'); ?>");

            // Secondary stats strip (TATs, hygiene anomaly, dispatched count)
            var tatColl = (s.avgDaysCollToRecv === null || s.avgDaysCollToRecv === undefined) ? '—' : Number(s.avgDaysCollToRecv).toFixed(1);
            var tatTest = (s.avgDaysRecvToTested === null || s.avgDaysRecvToTested === undefined) ? '—' : Number(s.avgDaysRecvToTested).toFixed(1);
            var dispatched = Math.max(s.printed || 0, s.dispatched || 0);
            var stripBits = [
                '<strong><?= _jsTranslate("Avg TAT"); ?>:</strong> '
                    + tatColl + ' <?= _jsTranslate("d coll→recv"); ?> · '
                    + tatTest + ' <?= _jsTranslate("d recv→tested"); ?>',
                '<strong>' + dispatched + '</strong> <?= _jsTranslate("results dispatched / printed"); ?>'
            ];
            if ((s.acceptedWithoutResultEntered || 0) > 0) {
                stripBits.push(
                    '<span style="color:#dd4b39;"><em class="fa-solid fa-triangle-exclamation"></em> '
                    + s.acceptedWithoutResultEntered
                    + ' <?= _jsTranslate("Accepted without result entered (data issue)"); ?></span>'
                );
            }
            $('#tbcSecondaryStats').html(stripBits.join(' &nbsp;|&nbsp; '));

            // Main cascade funnel + referral branch (shared renderer)
            tbcRenderCascade('tbc', s);

            // Refresh any open drilldown panes against the new filter context
            if (perLabTable) { perLabTable.fnDraw(); }
            if (referralMatrixTable) { referralMatrixTable.fnDraw(); }
            if (detailTable) { detailTable.fnDraw(); }
        });
    }

    function ensurePerLabTable() {
        if (perLabTable) return;
        perLabTable = $('#perLabTable').dataTable({
            "bJQueryUI": false, "bAutoWidth": false, "bInfo": true, "iDisplayLength": 25,
            "bRetrieve": true, "bProcessing": true, "bServerSide": true,
            "sAjaxSource": "/tb/management/getTbCascadeReport.php",
            "fnServerData": function (sSource, aoData, fnCallback) {
                aoData.push({ "name": "action", "value": "per-lab" });
                $.each(readFilters(), function (k, v) { aoData.push({ "name": k, "value": v }); });
                $.ajax({ "dataType": 'json', "type": "POST", "url": sSource, "data": aoData, "success": fnCallback });
            },
            "aaSorting": [[1, "desc"]]
        });
    }

    function ensureReferralMatrixTable() {
        if (referralMatrixTable) return;
        referralMatrixTable = $('#referralMatrixTable').dataTable({
            "bJQueryUI": false, "bAutoWidth": false, "bInfo": true, "iDisplayLength": 25,
            "bRetrieve": true, "bProcessing": true, "bServerSide": true,
            "sAjaxSource": "/tb/management/getTbCascadeReport.php",
            "fnServerData": function (sSource, aoData, fnCallback) {
                aoData.push({ "name": "action", "value": "referral-matrix" });
                $.each(readFilters(), function (k, v) { aoData.push({ "name": k, "value": v }); });
                $.ajax({ "dataType": 'json', "type": "POST", "url": sSource, "data": aoData, "success": fnCallback });
            },
            "aaSorting": [[2, "desc"]],
            "oLanguage": { "sEmptyTable": "<?= _jsTranslate('No referrals recorded in the selected period.'); ?>" }
        });
    }

    function ensureDetailTable() {
        if (detailTable) return;
        detailTable = $('#detailTable').dataTable({
            "bJQueryUI": false, "bAutoWidth": false, "bInfo": true, "iDisplayLength": 25,
            "bRetrieve": true, "bProcessing": true, "bServerSide": true,
            "sAjaxSource": "/tb/management/getTbCascadeReport.php",
            "fnServerData": function (sSource, aoData, fnCallback) {
                aoData.push({ "name": "action", "value": "detail" });
                $.each(readFilters(), function (k, v) { aoData.push({ "name": k, "value": v }); });
                $.ajax({ "dataType": 'json', "type": "POST", "url": sSource, "data": aoData, "success": fnCallback });
            },
            "aaSorting": [[8, "desc"]]
        });
    }

    function toggleDrill(name) {
        var $pane = $('#drillPane_' + name);
        var willShow = $pane.is(':hidden');
        // Close all other panes — one drilldown at a time keeps the page short
        $('.tbc-drill-pane').hide();
        $('.tbc-drill-btn').removeClass('btn-primary').addClass('btn-default');
        if (willShow) {
            $pane.show();
            $('.tbc-drill-btn[data-drill="' + name + '"]').removeClass('btn-default').addClass('btn-primary');
            if (name === 'perLab') ensurePerLabTable();
            else if (name === 'referralMatrix') ensureReferralMatrixTable();
            else if (name === 'detail') ensureDetailTable();
        }
    }

    $(function () {
        $("#labName, #provinceId, #facilityName, #testType, #finalized").select2({
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
        $('.tbc-drill-btn').on('click', function () {
            toggleDrill($(this).data('drill'));
        });
        loadSummary();
    });
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
