<?php

use App\Services\TestsService;
use App\Services\CommonService;
use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;
use App\Utilities\SourcesOfRequestsReportUtility;


$title = _translate("Sources of Requests");
require_once APPLICATION_PATH . '/header.php';

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GeoLocationsService $geolocationService */
$geolocationService = ContainerRegistry::get(GeoLocationsService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$facility = $facilitiesService->getHealthFacilities();
$labNameList = $facilitiesService->getTestingLabs();

$activeTests = TestsService::getActiveTests();
$testTypeLabels = [
    'vl' => _translate("Viral Load"),
    'eid' => _translate("Early Infant Diagnosis"),
    'covid19' => _translate("Covid-19"),
    'hepatitis' => _translate("Hepatitis"),
    'tb' => _translate("TB"),
    'cd4' => _translate("CD4"),
    'generic-tests' => _translate("Custom Tests"),
];
$state = $geolocationService->getProvinces("yes");

$overdueLabel = sprintf(_translate('Not Returned After %d Days'), SourcesOfRequestsReportUtility::OVERDUE_DAYS);
$stages = [
    'received' => _translate("Received at Lab"),
    'notReceived' => _translate("Not Yet Received"),
    'tested' => _translate("Tested"),
    'returned' => _translate("Result Returned"),
    'notReturned' => _translate("Tested, Result Not Returned"),
    'overdue' => $overdueLabel,
];

?>
<style>
    #sourcesOfRequests .sor-section-title {
        margin: 5px 0 4px;
        font-size: 15px;
        font-weight: 600;
    }

    #sourcesOfRequests .sor-note {
        margin: 0 0 10px;
        color: #6b7580;
        font-size: 12px;
    }

    #sourcesOfRequests td.num,
    #sourcesOfRequests th.num {
        text-align: right;
        white-space: nowrap;
    }

    #sourcesOfRequests .sor-percent {
        display: inline-block;
        min-width: 3.5em;
        color: #8a9299;
    }

    #sourcesOfRequests tr.sor-total td {
        font-weight: 700;
        background-color: #f4f6f8;
    }

    #sourcesOfRequests a.sor-drill {
        cursor: pointer;
    }

    #sourcesOfRequests .sor-overdue a,
    #sourcesOfRequests .sor-overdue {
        color: #c0392b;
        font-weight: 600;
    }

    #sourcesOfRequests .tab-content {
        padding-top: 10px;
    }

    #sourcesOfRequests #clinicDrill {
        display: none;
        margin-left: 8px;
        font-size: 12px;
        font-weight: normal;
    }

    #sourcesOfRequests #clinicDrill a {
        margin-left: 4px;
        color: #fff;
        cursor: pointer;
    }
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper" id="sourcesOfRequests">
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1><em class="fa-solid fa-circle-notch"></em>
            <?php echo _htmlTranslate("Sources of Requests Report"); ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em>
                    <?php echo _htmlTranslate("Home"); ?>
                </a></li>
            <li class="active">
                <?php echo _htmlTranslate("Sources of Requests Report"); ?>
            </li>
        </ol>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-body">
                        <div class="box box-default filter-panel filter-panel-collapsed">
                            <div class="box-body pageFilters filter-panel-body">
                                <div class="row">
                                    <div class="col-md-3 col-sm-6">
                                        <div class="form-group">
                                            <label class="control-label" for="dateRange"><?= _htmlTranslate('Request Date'); ?></label>
                                            <input type="text" id="dateRange" name="dateRange" class="form-control daterangefield"
                                                placeholder="<?= _htmlTranslate('Enter date range'); ?>" readonly
                                                style="background:#fff;" />
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="form-group">
                                            <label class="control-label" for="testType"><?= _htmlTranslate("Test Type"); ?></label>
                                            <select id="testType" name="testType" class="form-control"
                                                onchange="getSourceRequest(this.value);">
                                                <?php foreach ($testTypeLabels as $testTypeKey => $testTypeLabel) {
                                                    if (in_array($testTypeKey, $activeTests, true)) { ?>
                                                        <option value="<?= $testTypeKey; ?>"><?= htmlspecialchars($testTypeLabel); ?></option>
                                                <?php }
                                                } ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="form-group">
                                            <label class="control-label" for="originalSourceOfRequest"><?= _htmlTranslate("Source of Request"); ?></label>
                                            <select class="form-control" id="originalSourceOfRequest" name="originalSourceOfRequest"
                                                title="<?= _htmlTranslate('Please select source of request'); ?>"></select>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="form-group">
                                            <label class="control-label" for="stage"><?= _htmlTranslate("Stage"); ?></label>
                                            <select class="form-control" id="stage" name="stage">
                                                <option value=""><?= _htmlTranslate("All"); ?></option>
                                                <?php foreach ($stages as $stageKey => $stageLabel) { ?>
                                                    <option value="<?= $stageKey; ?>"><?= htmlspecialchars($stageLabel); ?></option>
                                                <?php } ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="form-group">
                                            <label class="control-label" for="state"><?= _htmlTranslate('Province/State'); ?></label>
                                            <select class="form-control" id="state" onchange="getByProvince()" name="state"
                                                multiple="multiple">
                                                <?= $general->generateSelectOptions($state, null, _translate("-- Select --")); ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="form-group">
                                            <label class="control-label" for="district"><?= _htmlTranslate("District/County"); ?></label>
                                            <select class="form-control" id="district" name="district" multiple="multiple">
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="form-group">
                                            <label class="control-label" for="facilityId"><?= _htmlTranslate("Name of the Clinic"); ?></label>
                                            <select class="form-control" name="facilityId" id="facilityId" multiple="multiple">
                                                <?= $general->generateSelectOptions($facility, null, _translate("-- Select --")); ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-3 col-sm-6">
                                        <div class="form-group">
                                            <label class="control-label" for="labName"><?= _htmlTranslate("Name of the Testing Lab"); ?></label>
                                            <select class="form-control" id="labName" name="labName" multiple="multiple">
                                                <?= $general->generateSelectOptions($labNameList, null, _translate("-- Select --")); ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="box-footer filter-actions">
                                <button type="button" onclick="searchRequestData();" class="filter-search btn btn-primary btn-sm">
                                    <?= _htmlTranslate("Search"); ?></button>
                                <button type="button" class="btn btn-default btn-sm"
                                    onclick="document.location.href = document.location"><?= _htmlTranslate('Reset'); ?></button>
                                <button type="button" class="filter-export btn btn-success btn-sm" onclick="exportTestRequests();">
                                    <em class="fa-solid fa-file-excel"></em> <?= _htmlTranslate("Export to Excel"); ?></button>
                            </div>
                        </div>

                        <ul class="nav nav-tabs" id="sorTabs" role="tablist">
                            <li role="presentation" class="active"><a href="#sorBySource" role="tab" data-toggle="tab"
                                    data-view="source"><?= _htmlTranslate("By Source"); ?></a></li>
                            <li role="presentation"><a href="#sorByClinic" role="tab" data-toggle="tab"
                                    data-view="clinic"><?= _htmlTranslate("By Clinic"); ?></a></li>
                            <li role="presentation"><a href="#sorTrend" role="tab" data-toggle="tab"
                                    data-view="trend"><?= _htmlTranslate("Trend"); ?></a></li>
                        </ul>
                        <div class="tab-content">
                            <div role="tabpanel" class="tab-pane active" id="sorBySource">
                                <p class="sor-note" id="sourceSummaryNote">
                                    <?= _htmlTranslate("Requests made in the date range. Percentages are of requests. Median days run from sample collection to receipt at the lab and to the result being returned. Click a number to list those samples."); ?>
                                </p>
                                <table aria-describedby="sourceSummaryNote" id="sourceSummary" class="table table-bordered table-condensed">
                                    <thead>
                                        <tr>
                                            <th><?= _htmlTranslate("Source of Request"); ?></th>
                                            <th class="num"><?= _htmlTranslate("Requested"); ?></th>
                                            <th class="num"><?= _htmlTranslate("Received at Lab"); ?></th>
                                            <th class="num"><?= _htmlTranslate("Tested"); ?></th>
                                            <th class="num"><?= _htmlTranslate("Results Returned"); ?></th>
                                            <th class="num"><?= htmlspecialchars($overdueLabel); ?></th>
                                            <th class="num"><?= _htmlTranslate("Median Days to Receipt"); ?></th>
                                            <th class="num"><?= _htmlTranslate("Median Days to Return"); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <div role="tabpanel" class="tab-pane" id="sorByClinic">
                                <p class="sor-note" id="clinicSummaryNote">
                                    <?= _htmlTranslate("Requests from each clinic, by source. Electronic requests are those the lab did not have to enter. Click a number to list those samples."); ?>
                                </p>
                                <table aria-describedby="clinicSummaryNote" id="clinicSummary" class="table table-bordered table-condensed" style="width:100%;"></table>
                            </div>
                            <div role="tabpanel" class="tab-pane" id="sorTrend">
                                <div id="sorTrendChart" style="height:380px;"></div>
                            </div>
                        </div>

                        <h4 class="sor-section-title"><?= _htmlTranslate("Samples"); ?>
                            <span id="clinicDrill" class="label label-info"><span></span><a
                                    title="<?= _htmlTranslate("Show all clinics"); ?>">&times;</a></span>
                        </h4>
                        <table aria-describedby="sourceSummaryNote" id="sampleWiseReport" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th><?= _htmlTranslate("Sample ID"); ?></th>
                                    <th><?= _htmlTranslate("Source of Request"); ?></th>
                                    <th><?= _htmlTranslate("Clinic"); ?></th>
                                    <th><?= _htmlTranslate("Testing Lab"); ?></th>
                                    <th><?= _htmlTranslate("Requested On"); ?></th>
                                    <th><?= _htmlTranslate("Received at Lab"); ?></th>
                                    <th><?= _htmlTranslate("Sample Tested On"); ?></th>
                                    <th><?= _htmlTranslate("Result Returned On"); ?></th>
                                    <th><?= _htmlTranslate("Status"); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="9" class="dataTables_empty"><?= _htmlTranslate("Loading data from server"); ?></td>
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
<script type="text/javascript" src="<?= _asset('/assets/plugins/daterangepicker/daterangepicker.js') ?>"></script>
<script type="text/javascript">
    var oTable = null;
    // The filters as of the last Search. Every draw sends these, not the live
    // controls, so paging after editing a filter cannot show a list the summary
    // above it does not describe.
    var applied = {};
    // The summary changes only with the filters, so paging and sorting the
    // sample list leave it alone.
    var summaryPending = true;
    // Bumped on every Search, so the By Clinic and Trend tabs know when what they
    // show is out of date and load again the next time they are opened.
    var filterVersion = 0;
    var loadedVersion = { clinic: -1, trend: -1 };
    var clinicTable = null;
    var OVERDUE_LABEL = "<?= _jsTranslate($overdueLabel); ?>";

    function applyFilters() {
        applied = {
            dateRange: $("#dateRange").val(),
            testType: $("#testType").val(),
            labName: $("#labName").val(),
            state: $("#state").val(),
            district: $("#district").val(),
            facilityId: $("#facilityId").val(),
            originalSourceOfRequest: $("#originalSourceOfRequest").val(),
            stage: $("#stage").val()
        };
        // A clinic picked from the clinic summary narrows the list until the next Search.
        $('#clinicDrill').hide();
    }

    $(document).ready(function () {
        $('#state').select2({
            width: '100%',
            placeholder: "<?= _jsTranslate("Select Province"); ?>"
        });
        $('#district').select2({
            width: '100%',
            placeholder: "<?= _jsTranslate("Select District"); ?>"
        });
        $('#facilityId').select2({
            width: '100%',
            placeholder: "<?= _jsTranslate("Select Name of the Clinic"); ?>"
        });
        $('#labName').select2({
            width: '100%',
            placeholder: "<?= _jsTranslate("Select Testing Lab"); ?>"
        });

        $('#dateRange').daterangepicker({
            locale: {
                cancelLabel: "<?= _jsTranslate("Clear"); ?>",
                format: 'DD-MMM-YYYY',
                separator: ' to ',
            },
            startDate: moment().subtract(179, 'days'),
            endDate: moment(),
            maxDate: moment(),
            ranges: {
                "<?= _jsTranslate("Today"); ?>": [moment(), moment()],
                "<?= _jsTranslate("Yesterday"); ?>": [moment().subtract(1, 'days'), moment().subtract(1, 'days')],
                "<?= _jsTranslate("Last 7 Days"); ?>": [moment().subtract(6, 'days'), moment()],
                "<?= _jsTranslate("This Month"); ?>": [moment().startOf('month'), moment().endOf('month')],
                "<?= _jsTranslate("Last Month"); ?>": [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')],
                "<?= _jsTranslate("Last 30 Days"); ?>": [moment().subtract(29, 'days'), moment()],
                "<?= _jsTranslate("Last 90 Days"); ?>": [moment().subtract(89, 'days'), moment()],
                "<?= _jsTranslate("Last 180 Days"); ?>": [moment().subtract(179, 'days'), moment()],
                "<?= _jsTranslate("Last 12 Months"); ?>": [moment().subtract(12, 'month').startOf('month'), moment().endOf('month')],
                "<?= _jsTranslate("Previous Year"); ?>": [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')],
                "<?= _jsTranslate("Current Year To Date"); ?>": [moment().startOf('year'), moment()]
            }
        });

        getSourceRequest($('#testType').val());
    });

    // Safe in text and in a quoted attribute: a source is whatever the sender
    // stored, and it lands in data-source="...".
    function escapeText(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function days(value) {
        return value == null ? '&ndash;' : Number(value).toFixed(1);
    }

    // A count that lists the samples behind it when clicked. clinicId is set only
    // from the clinic summary.
    function drillLink(value, drill) {
        var text = Number(value).toLocaleString();
        if (!(value > 0)) {
            return text;
        }
        return '<a class="sor-drill" data-source="' + escapeText(drill.source) + '" data-stage="' + escapeText(drill.stage)
            + '" data-clinic="' + escapeText(drill.clinicId == null ? '' : drill.clinicId) + '" data-clinic-label="'
            + escapeText(drill.clinicLabel || '') + '">' + text + '</a>';
    }

    function drawSummary(summary) {
        var body = $('#sourceSummary tbody').empty();
        if (!summary || !summary.rows.length) {
            body.append('<tr><td colspan="8" class="text-center text-muted"><?= _jsTranslate("No data available"); ?></td></tr>');
            return;
        }

        function count(row, value, stage) {
            var text = drillLink(value, { source: row.source, stage: stage });
            if (stage === '' || stage === 'overdue') {
                return text;
            }
            var percent = row.requested > 0 ? Math.round(value * 100 / row.requested) + '%' : '';
            return text + ' <span class="sor-percent">' + percent + '</span>';
        }

        summary.rows.concat([summary.total]).forEach(function (row, index) {
            var isTotal = index === summary.rows.length;
            body.append('<tr' + (isTotal ? ' class="sor-total"' : '') + '>'
                + '<td>' + escapeText(row.label) + '</td>'
                + '<td class="num">' + count(row, row.requested, '') + '</td>'
                + '<td class="num">' + count(row, row.received, 'received') + '</td>'
                + '<td class="num">' + count(row, row.tested, 'tested') + '</td>'
                + '<td class="num">' + count(row, row.returned, 'returned') + '</td>'
                + '<td class="num' + (row.overdue > 0 ? ' sor-overdue' : '') + '">' + count(row, row.overdue, 'overdue') + '</td>'
                + '<td class="num">' + days(row.receiptDays) + '</td>'
                + '<td class="num">' + days(row.returnDays) + '</td>'
                + '</tr>');
        });
    }

    // A number in a summary lists the samples behind it: same filters, that
    // source and stage, and that clinic when it comes from the clinic summary.
    $(document).on('click', '#sorBySource a.sor-drill, #sorByClinic a.sor-drill', function () {
        var link = $(this);
        var source = link.attr('data-source');
        var select = $('#originalSourceOfRequest');
        if (source !== '' && select.find('option').filter(function () { return this.value === source; }).length === 0) {
            select.append($('<option>').val(source).text(source));
        }
        select.val(source);
        $('#stage').val(link.attr('data-stage'));
        FilterPanel.refresh($('.filter-panel'));
        // Only the source, stage and clinic change: the rest stay as the summary has them.
        applied.originalSourceOfRequest = source;
        applied.stage = link.attr('data-stage');
        applied.clinicId = link.attr('data-clinic');
        if (applied.clinicId !== '') {
            $('#clinicDrill').show().children('span').text(link.attr('data-clinic-label'));
        } else {
            $('#clinicDrill').hide();
        }
        // The summary ignores the search box, so the list must too, or a
        // leftover search could hide the very samples counted. This redraws.
        oTable.fnFilter('');
        $('html, body').animate({ scrollTop: $('#sampleWiseReport').offset().top - 80 }, 200);
    });

    $(document).on('click', '#clinicDrill a', function () {
        applied.clinicId = '';
        $('#clinicDrill').hide();
        oTable.fnDraw();
    });

    // The By Clinic and Trend tabs load when opened, and again after a Search.
    $(document).on('shown.bs.tab', '#sorTabs a', function () {
        loadBreakdown($(this).attr('data-view'));
    });

    function loadBreakdown(view) {
        if ((view !== 'clinic' && view !== 'trend') || loadedVersion[view] === filterVersion) {
            return;
        }
        loadedVersion[view] = filterVersion;
        var version = filterVersion;
        $.post('/admin/monitoring/get-sources-of-requests-breakdown.php', $.extend({}, applied, { view: view }), function (data) {
            // A Search made while this was loading has its own request on the way.
            if (version !== filterVersion) {
                return;
            }
            if (view === 'clinic') {
                drawClinics(data);
            } else {
                drawTrend(data);
            }
        }, 'json').fail(function () {
            loadedVersion[view] = -1;
        });
    }

    function drawClinics(data) {
        if (clinicTable !== null) {
            clinicTable.destroy();
            clinicTable = null;
        }
        var table = $('#clinicSummary').empty();
        var heads = ['<?= _jsTranslate("Clinic"); ?>', '<?= _jsTranslate("Requested"); ?>'];
        data.sources.forEach(function (source) {
            heads.push(source.label);
        });
        heads.push('<?= _jsTranslate("% Electronic"); ?>', '<?= _jsTranslate("Results Returned"); ?>', OVERDUE_LABEL,
            '<?= _jsTranslate("Median Days to Return"); ?>');
        table.append('<thead><tr>' + heads.map(function (head, index) {
            return '<th' + (index > 0 ? ' class="num"' : '') + '>' + escapeText(head) + '</th>';
        }).join('') + '</tr></thead><tbody></tbody><tfoot></tfoot>');

        function cells(row) {
            var drill = { clinicId: row.clinicId, clinicLabel: row.label };
            var list = [escapeText(row.label), drillLink(row.requested, $.extend({ source: '', stage: '' }, drill))];
            data.sources.forEach(function (source) {
                list.push(drillLink(row.bySource[source.source] || 0, $.extend({ source: source.source, stage: '' }, drill)));
            });
            list.push(row.requested > 0 ? Math.round(row.electronic * 100 / row.requested) + '%' : '&ndash;');
            list.push(drillLink(row.returned, $.extend({ source: '', stage: 'returned' }, drill)));
            list.push('<span class="' + (row.overdue > 0 ? 'sor-overdue' : '') + '">'
                + drillLink(row.overdue, $.extend({ source: '', stage: 'overdue' }, drill)) + '</span>');
            list.push(days(row.returnDays));
            return list;
        }

        // Sorting reads the plain number behind each formatted cell.
        function sortValue(row) {
            var values = [row.label, row.requested];
            data.sources.forEach(function (source) {
                values.push(row.bySource[source.source] || 0);
            });
            values.push(row.requested > 0 ? row.electronic / row.requested : 0, row.returned, row.overdue,
                row.returnDays == null ? -1 : row.returnDays);
            return values;
        }

        var total = data.total;
        total.clinicId = '';
        table.find('tfoot').append('<tr class="sor-total">' + cells(total).map(function (cell, index) {
            return '<td' + (index > 0 ? ' class="num"' : '') + '>' + cell + '</td>';
        }).join('') + '</tr>');

        clinicTable = table.DataTable({
            data: data.rows.map(function (row) {
                return { display: cells(row), sort: sortValue(row) };
            }),
            columns: heads.map(function (head, index) {
                return {
                    className: index > 0 ? 'num' : '',
                    render: function (cell, type, row) {
                        return type === 'display' ? row.display[index] : row.sort[index];
                    }
                };
            }),
            order: [[1, 'desc']],
            pageLength: 25,
            language: { emptyTable: "<?= _jsTranslate("No data available"); ?>" }
        });
    }

    function drawTrend(data) {
        Highcharts.chart('sorTrendChart', {
            chart: { type: 'column', style: { fontFamily: 'inherit' } },
            title: {
                text: data.unit === 'week' ? "<?= _jsTranslate("Requests per Week by Source"); ?>"
                    : "<?= _jsTranslate("Requests per Month by Source"); ?>",
                style: { fontSize: '14px', fontWeight: '600' }
            },
            xAxis: { categories: data.periods },
            yAxis: { min: 0, allowDecimals: false, title: { text: "<?= _jsTranslate("Requested"); ?>" } },
            plotOptions: { column: { stacking: 'normal' } },
            tooltip: { shared: true },
            credits: { enabled: false },
            lang: { noData: "<?= _jsTranslate("No data available"); ?>" },
            series: data.series.map(function (series) {
                return { name: series.label, data: series.data };
            })
        });
    }

    function getSourcesOfRequestReport() {
        oTable = $('#sampleWiseReport').dataTable({
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
                { "sClass": "center" },
                { "sClass": "center" }
            ],
            "aaSorting": [[4, "desc"]],
            "bProcessing": true,
            "bServerSide": true,
            "sAjaxSource": "/admin/monitoring/get-samplewise-report.php",
            "fnServerData": function (sSource, aoData, fnCallback) {
                $.each(applied, function (name, value) {
                    aoData.push({ "name": name, "value": value });
                });
                aoData.push({ "name": "withSummary", "value": summaryPending ? 'yes' : 'no' });
                summaryPending = false;
                $.ajax({
                    "dataType": 'json',
                    "type": "POST",
                    "url": sSource,
                    "data": aoData,
                    "success": function (json) {
                        if (json.summary) {
                            drawSummary(json.summary);
                        }
                        fnCallback(json);
                    }
                });
            }
        });
    }

    function getByProvince() {
        $("#district").html('');
        $("#facilityId").html('');
        $("#labName").html('');
        $.post("/common/get-by-province-id.php", {
            provinceId: $('#state').val(),
            districts: true,
            facilities: true,
            labs: true,
        },
            function (data) {
                var Obj = $.parseJSON(data);
                $("#district").append(Obj['districts']);
                $("#facilityId").append(Obj['facilities']);
                $("#labName").append(Obj['labs']);
            });
    }

    function searchRequestData() {
        applyFilters();
        summaryPending = true;
        filterVersion++;
        loadBreakdown($('#sorTabs li.active a').attr('data-view'));
        if (oTable) {
            oTable.fnDraw();
        } else {
            getSourcesOfRequestReport();
        }
    }

    function exportTestRequests() {
        // Runs in the background; progress shows in the navbar Exports menu and the
        // file downloads when ready, even if the user has moved to another page.
        IntelisExport.start("/admin/monitoring/export-samplewise-reports.php", {}, "<?= _jsTranslate("Sources of Requests Export"); ?>");
    }

    function getSourceRequest(testType) {
        $("#originalSourceOfRequest").empty();
        $.post("/admin/monitoring/get-source-request-list.php", {
            testType
        }, function (data) {
            // All sources by default: the report compares them, so it must not
            // open on one.
            $("#originalSourceOfRequest").html(data).val('');
            FilterPanel.refresh($('.filter-panel'));
            searchRequestData();
        });
    }
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
