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
    .pmtct-kpi-row {
        margin-top: 10px;
    }

    .pmtct-kpi-card {
        background: #fff;
        border-left: 4px solid #3c8dbc;
        border-radius: 3px;
        box-shadow: 0 1px 1px rgba(0, 0, 0, .05);
        padding: 12px 14px;
        margin-bottom: 12px;
        min-height: 92px;
    }

    .pmtct-kpi-card .pmtct-kpi-label {
        font-size: 12px;
        color: #888;
        text-transform: uppercase;
        letter-spacing: .4px;
    }

    .pmtct-kpi-card .pmtct-kpi-value {
        font-size: 26px;
        font-weight: 600;
        color: #222;
        line-height: 1.2;
    }

    .pmtct-kpi-card .pmtct-kpi-sub {
        font-size: 11px;
        color: #999;
        margin-top: 4px;
    }

    .pmtct-kpi-card.kpi-warn {
        border-left-color: #f39c12;
    }

    .pmtct-kpi-card.kpi-danger {
        border-left-color: #dd4b39;
    }

    .pmtct-kpi-card.kpi-good {
        border-left-color: #00a65a;
    }

    .pmtct-tabs {
        margin-top: 18px;
    }

    .select2-selection__choice {
        color: black !important;
    }
</style>

<div class="content-wrapper">
    <section class="content-header">
        <h1><em class="fa-solid fa-book"></em> <?= _translate("PMTCT Cascade Report"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?= _translate("Home"); ?></a></li>
            <li class="active"><?= _translate("PMTCT Cascade Report"); ?></li>
        </ol>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box" id="filterDiv">
                    <table aria-describedby="table" class="table pageFilters" aria-hidden="true"
                        style="margin-left:1%;margin-top:20px;width:98%;">
                        <tr>
                            <td><strong><?= _translate("EID Sample Collection Date"); ?>&nbsp;:</strong></td>
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
                                        <option value="<?= $p['province_id']; ?>">
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
                            <td><strong><?= _translate("Match Status"); ?>&nbsp;:</strong></td>
                            <td>
                                <select class="form-control" id="matchStatus" name="matchStatus" style="width:100%;">
                                    <option value="all"><?= _translate("All children"); ?></option>
                                    <option value="matched" selected><?= _translate("Matched (mother has VL test)"); ?>
                                    </option>
                                    <option value="unmatched"><?= _translate("Not matched"); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4">
                                <input type="button" onclick="searchPmtctCascade()" value="<?= _translate("Search"); ?>"
                                    class="btn btn-success btn-sm">
                                &nbsp;<button class="btn btn-danger btn-sm"
                                    onclick="document.location.href = document.location"><span><?= _translate('Reset'); ?></span></button>
                                &nbsp;<button class="btn btn-primary btn-sm pull-right" type="button"
                                    onclick="exportPmtctCascade()"><em
                                        class="fa-solid fa-cloud-arrow-down"></em>&nbsp;<?= _translate("Export to Excel"); ?></button>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Summary KPI cards -->
        <div class="row pmtct-kpi-row">
            <div class="col-md-3 col-sm-6">
                <div class="pmtct-kpi-card">
                    <div class="pmtct-kpi-label"><?= _translate("Children with Matching Mother in VL Data"); ?></div>
                    <div class="pmtct-kpi-value" id="kpiMatchedChildren">&mdash;</div>
                    <div class="pmtct-kpi-sub" id="kpiMatchedChildrenSub">&nbsp;</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="pmtct-kpi-card kpi-good">
                    <div class="pmtct-kpi-label"><?= _translate("Of Those, With EID Test Result"); ?></div>
                    <div class="pmtct-kpi-value" id="kpiTestedChildren">&mdash;</div>
                    <div class="pmtct-kpi-sub" id="kpiTestedChildrenSub">&nbsp;</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="pmtct-kpi-card kpi-danger">
                    <div class="pmtct-kpi-label"><?= _translate("Of Those, HIV Positive"); ?></div>
                    <div class="pmtct-kpi-value" id="kpiPositiveChildren">&mdash;</div>
                    <div class="pmtct-kpi-sub" id="kpiPositiveChildrenSub">&nbsp;</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="pmtct-kpi-card kpi-warn">
                    <div class="pmtct-kpi-label"><?= _translate("Children NOT Matched in VL"); ?></div>
                    <div class="pmtct-kpi-value" id="kpiUnmatchedChildren">&mdash;</div>
                    <div class="pmtct-kpi-sub" id="kpiUnmatchedChildrenSub">&nbsp;</div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-3 col-sm-6">
                <div class="pmtct-kpi-card">
                    <div class="pmtct-kpi-label"><?= _translate("Distinct Mothers Matched"); ?></div>
                    <div class="pmtct-kpi-value" id="kpiDistinctMothers">&mdash;</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="pmtct-kpi-card">
                    <div class="pmtct-kpi-label"><?= _translate("VL Tests on Record for Matched Mothers (all time)"); ?>
                    </div>
                    <div class="pmtct-kpi-value" id="kpiVlTests">&mdash;</div>
                    <div class="pmtct-kpi-sub" id="kpiVlTestsSub">&nbsp;</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="pmtct-kpi-card kpi-good">
                    <div class="pmtct-kpi-label"><?= _translate("Mothers with VL Result Available"); ?></div>
                    <div class="pmtct-kpi-value" id="kpiMothersWithResult">&mdash;</div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6">
                <div class="pmtct-kpi-card kpi-danger">
                    <div class="pmtct-kpi-label"><?= _translate("Mothers with High VL (≥1000 cp/mL)"); ?></div>
                    <div class="pmtct-kpi-value" id="kpiMothersHighVl">&mdash;</div>
                    <div class="pmtct-kpi-sub" id="kpiMothersHighVlSub">&nbsp;</div>
                </div>
            </div>
        </div>

        <!-- Line-level table -->
        <div class="row">
            <div class="col-xs-12">
                <div class="box pmtct-tabs">
                    <div class="box-header">
                        <h3 class="box-title"><?= _translate("Line-Level Records"); ?></h3>
                        <div class="pull-right" style="font-size:12px;color:#666;">
                            <?= _translate("Rows shown follow the selected Match Status filter."); ?>
                        </div>
                    </div>
                    <div class="box-body table-responsive">
                        <table id="pmtctTable" class="table table-bordered table-striped"
                            aria-describedby="pmtct-line-level" aria-hidden="true" style="width:100%;">
                            <thead>
                                <tr>
                                    <th><?= _translate("Child ID"); ?></th>
                                    <th><?= _translate("Child Sex"); ?></th>
                                    <th><?= _translate("Child Age in Months"); ?></th>
                                    <th><?= _translate("Child Age in Weeks"); ?></th>
                                    <th><?= _translate("Mother ID"); ?></th>
                                    <th><?= _translate("EID Sample Code"); ?></th>
                                    <th><?= _translate("Remote EID Sample Code"); ?></th>
                                    <th><?= _translate("EID Sample Collection Date"); ?></th>
                                    <th><?= _translate("EID Test Date"); ?></th>
                                    <th><?= _translate("EID Sample Status"); ?></th>
                                    <th><?= _translate("EID Result"); ?></th>
                                    <th><?= _translate("EID Test Platform"); ?></th>
                                    <th><?= _translate("EID Testing Lab"); ?></th>
                                    <th><?= _translate("VL Sample Code"); ?></th>
                                    <th><?= _translate("VL Sample Collection Date"); ?></th>
                                    <th><?= _translate("VL Test Date"); ?></th>
                                    <th><?= _translate("VL Result"); ?></th>
                                    <th><?= _translate("VL Test Platform"); ?></th>
                                    <th><?= _translate("VL Testing Lab"); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="19" class="dataTables_empty">
                                        <?= _translate("Loading data from server"); ?>
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

    function loadPmtctSummary() {
        $.blockUI();
        $.post("/eid/management/getPmtctCascadeReport.php", $.extend({ action: "summary" }, readFilters()), function (data) {
            $.unblockUI();
            if (!data) { return; }
            try {
                var s = (typeof data === 'string') ? JSON.parse(data) : data;
                $("#kpiMatchedChildren").text(s.matchedChildren);
                $("#kpiMatchedChildrenSub").text(s.totalChildrenWithMotherId + " <?= _jsTranslate('children with a mother ID'); ?>");
                $("#kpiTestedChildren").text(s.testedChildren);
                $("#kpiTestedChildrenSub").text(s.matchedChildren > 0 ? Math.round(100 * s.testedChildren / s.matchedChildren) + "% <?= _jsTranslate('of matched'); ?>" : "");
                $("#kpiPositiveChildren").text(s.positiveChildren);
                $("#kpiPositiveChildrenSub").text(s.testedChildren > 0 ? Math.round(100 * s.positiveChildren / s.testedChildren) + "% <?= _jsTranslate('of tested'); ?>" : "");
                $("#kpiUnmatchedChildren").text(s.unmatchedChildren);
                $("#kpiDistinctMothers").text(s.distinctMothers);
                $("#kpiVlTests").text(s.vlTests);
                var vlPending = Math.max(0, (s.vlTests || 0) - (s.vlTestsWithResult || 0));
                $("#kpiVlTestsSub").text(
                    (s.vlTestsWithResult || 0) + " <?= _jsTranslate('with result'); ?>" +
                    " · " + vlPending + " <?= _jsTranslate('pending'); ?>"
                );
                $("#kpiMothersWithResult").text(s.mothersWithResult);
                $("#kpiMothersHighVl").text(s.mothersHighVl);
                $("#kpiMothersHighVlSub").text(s.mothersWithResult > 0 ? Math.round(100 * s.mothersHighVl / s.mothersWithResult) + "% <?= _jsTranslate('of mothers with VL result'); ?>" : "");
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
        loadPmtctSummary();
        loadPmtctTable();
    });
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
