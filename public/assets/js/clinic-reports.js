/*
 * Shared behaviour for the seven clinic report pages (VL, EID, CD4, COVID-19,
 * Hepatitis, TB, Custom Tests).
 *
 * Each of those pages is a stack of tabs, every tab a server-side DataTable
 * over its own endpoint. Three things were wrong with how they drove that:
 *
 *   1. Every table was built on page load and every Search redrew all of them,
 *      so looking at one report cost five queries.
 *   2. $.blockUI() was called immediately before $.unblockUI() around calls
 *      that are asynchronous, so the overlay was gone before the first request
 *      left the browser -- and when it did land it was a second loading state
 *      over a table that already shows its own, blocking the filters and the
 *      tabs to refresh one table. Table draws now show only the table's
 *      indicator; blocking is left to work that really does hold up the page,
 *      such as generating a file.
 *   3. Filters that mean the same thing in every tab had to be re-entered in
 *      each one.
 *
 * This module owns all three. Pages register their tabs and then talk to it
 * instead of to the tables directly.
 */
(function ($) {
    'use strict';

    var tabs = {};
    var pending = 0;
    var waiters = [];

    function settle() {
        var waiting = waiters;
        waiters = [];
        $.each(waiting, function (i, d) {
            d.resolve();
        });
    }

    /* Counts requests in flight so an export can wait for the search it started.
       It deliberately does not block the page: a table draw already has its own
       indicator inside the table, which is the thing that is loading. Blocking
       the whole page on top of that is a second loader for one event, and it
       takes the filters and the tabs away while a page-2 click loads. Whole-page
       work -- generating a file, drawing the chart -- still blocks, because
       there the page really is busy. */
    function startLoading() {
        pending++;
    }

    function endLoading() {
        pending = Math.max(0, pending - 1);
        if (pending === 0) {
            settle();
        }
    }

    /* ---------------------------------------------------------------- tabs */

    function pane(paneId) {
        return $('#' + paneId);
    }

    function activePane() {
        var panes = $('#myTabContent > .tab-pane.active');
        if (!panes.length) {
            panes = $('.tab-content > .tab-pane.active');
        }
        return panes.first().attr('id');
    }

    /* A tab is linked by the readable name on its link (data-tab-name, e.g.
       "sample-rejection"), or by its pane id when it has none. "#tab=" rather
       than a bare id: a hash naming an element makes the browser scroll to it,
       which hides the tabs themselves. */
    function tabName(link) {
        return link.attr('data-tab-name') || String(link.attr('href')).replace(/^#/, '');
    }

    function showTabFromHash() {
        var match = /^#tab=([A-Za-z][\w-]*)$/.exec(window.location.hash);
        if (!match) {
            return;
        }
        var link = $('a[data-toggle="tab"][data-tab-name="' + match[1] + '"]');
        if (!link.length) {
            link = $('a[data-toggle="tab"][href="#' + match[1] + '"]');
        }
        if (link.length && activePane() !== String(link.first().attr('href')).replace(/^#/, '')) {
            link.first().tab('show');
        }
    }

    /* Builds a tab's table the first time it is looked at. Returns true if this
       call did the building, which also issues the tab's first draw. */
    function ensure(paneId) {
        var tab = tabs[paneId];
        if (!tab || tab.ready) {
            return false;
        }
        tab.ready = true;
        if (typeof tab.init === 'function') {
            tab.init();
        }
        return true;
    }

    function draw(paneId) {
        var tab = tabs[paneId];
        if (!tab) {
            return;
        }
        if (ensure(paneId)) {
            return; // init draws once by itself
        }
        if (typeof tab.search === 'function') {
            tab.search();
            return;
        }
        var table = typeof tab.table === 'function' ? tab.table() : null;
        if (table && typeof table.fnDraw === 'function') {
            table.fnDraw();
        }
    }

    /* ------------------------------------------------------- shared filters */

    /* Two controls carry the same filter when they carry the same label, which
       is also the only thing a user has to go on. Nothing extra to keep in step
       in the markup, and filters that only look alike ("Sample Test Date" and
       "Sample Collection Date") stay apart. */
    function labelledControls(paneId) {
        var map = {};
        pane(paneId).find('.filter-panel label.control-label').each(function () {
            var key = $.trim($(this).text());
            var el = $('#' + $(this).attr('for'));
            if (key && el.length) {
                map[key] = el;
            }
        });
        return map;
    }

    function optionsMatch(target, value) {
        if (value === null || value === '' || typeof value === 'undefined') {
            return true;
        }
        var values = $.isArray(value) ? value : [value];
        var ok = true;
        $.each(values, function (i, v) {
            if (target.find('option[value="' + String(v).replace(/"/g, '\\"') + '"]').length === 0) {
                ok = false;
            }
        });
        return ok;
    }

    function applyValue(target, value) {
        var picker = target.data('daterangepicker');
        if (picker) {
            target.val(value);
            var parts = String(value || '').split(' to ');
            if (parts.length === 2 && window.moment) {
                picker.setStartDate(window.moment(parts[0], 'DD-MMM-YYYY'));
                picker.setEndDate(window.moment(parts[1], 'DD-MMM-YYYY'));
            }
            return;
        }
        target.val(value);
        /* Namespaced so select2 redraws but the control's own onchange -- which
           on the province and district selects fires a lookup -- does not. */
        target.trigger('change.select2');
    }

    function touched(paneId) {
        var any = false;
        $.each(labelledControls(paneId), function (key, el) {
            if (el.data('cr-touched')) {
                any = true;
            }
        });
        return any;
    }

    /* Carries the filters the user is working with into a tab they have not set
       up themselves. All or nothing, per tab: filling in only the untouched
       controls can leave a tab holding a combination nobody chose -- a hand-set
       Sex of male beside a copied Pregnant of yes, or one tab's province beside
       another's district. A tab the user has touched is theirs. */
    function shareFilters(fromPane, toPane) {
        if (!fromPane || !toPane || fromPane === toPane || touched(toPane)) {
            return;
        }
        var source = labelledControls(fromPane);
        var target = labelledControls(toPane);
        var copied = false;
        $.each(source, function (key, from) {
            var to = target[key];
            if (!to) {
                return;
            }
            if (setValues(from).length === 0) {
                return;
            }
            var value = from.val();
            if (to.is('select') && !optionsMatch(to, value)) {
                /* District and facility lists are fetched per tab; the fetched
                   markup is the same, so copy it rather than fetching again. */
                to.html(from.html());
            }
            applyValue(to, value);
            copied = true;
        });
        if (!copied) {
            return;
        }
        /* A copied sex has to take the pregnancy controls with it, or the tab
           shows a disabled filter that is still submitted. Safe to call: the
           handler only enables and disables, unlike the province and district
           handlers, which fetch. */
        pane(toPane).find('select[onchange^="hideFemaleDetails"]').each(function () {
            if (typeof this.onchange === 'function') {
                this.onchange();
            }
        });
        /* The copies are made with a namespaced event so the province and
           district lookups do not fire, which also means the page's own change
           handlers do not see them. Without this the page still believes its
           last search matches the filters on screen, and an export would replay
           the query that search stored -- for the high viral load report, that
           is the row set a confirmed "mark as complete" writes to. */
        $(document).trigger('clinicreports:filterschanged', [toPane]);
        updateSummaries(pane(toPane));
        refreshHighlights();
    }

    /* ------------------------------------------------------- filter summary */

    /* The placeholder option carries an empty value, and a multi-select reports
       it as [""] rather than []. Empty values are not filters. */
    function setValues(el) {
        var value = el.val();
        if (value === null || typeof value === 'undefined') {
            return [];
        }
        return $.grep($.isArray(value) ? value : [value], function (v) {
            return $.trim(String(v)) !== '';
        });
    }

    /* The app-wide highlighter tints a filter that holds a value, which is how
       you find the three that are set among twelve. It listens for a plain
       change event, and filters copied between tabs are applied without one. */
    function refreshHighlights() {
        if (window.pageFilterHighlighter && typeof window.pageFilterHighlighter.refresh === 'function') {
            window.pageFilterHighlighter.refresh();
        }
    }

    /* Folding, the summary and the Expand button are the shared FilterPanel in
       main.js.php. */
    function updateSummaries(scope) {
        window.FilterPanel.refresh(scope.find('.filter-panel'));
    }

    /* --------------------------------------------------------- range slider */

    /* An age range is easier to sweep than to type, and easier to type than to
       drag when you want exactly 15 to 24, so the slider and the two boxes are
       the same filter seen twice. jQuery UI is already on every page. */
    function initRangeSliders() {
        if (!$.fn.slider) {
            return;
        }
        $('.range-slider').each(function () {
            var slider = $(this);
            var min = $(slider.data('range-min'));
            var max = $(slider.data('range-max'));
            if (!min.length || !max.length || slider.data('cr-slider')) {
                return;
            }
            var floor = parseInt(slider.data('floor'), 10) || 0;
            var ceiling = parseInt(slider.data('ceiling'), 10) || 120;

            function clamp(value, fallback) {
                var n = parseInt(value, 10);
                if (isNaN(n)) {
                    n = fallback;
                }
                return Math.min(ceiling, Math.max(floor, n));
            }

            /* Setting the handles programmatically fires the widget's own
               change event, so the two directions have to be kept apart. */
            var syncing = false;

            slider.slider({
                range: true,
                min: floor,
                max: ceiling,
                values: [clamp(min.val(), floor), clamp(max.val(), ceiling)],
                slide: function (event, ui) {
                    syncing = true;
                    min.val(ui.values[0]);
                    max.val(ui.values[1]);
                    syncing = false;
                },
                change: function () {
                    if (!syncing) {
                        min.trigger('change');
                        max.trigger('change');
                    }
                }
            });
            slider.data('cr-slider', true);

            min.add(max).on('change keyup', function () {
                if (syncing) {
                    return;
                }
                var lo = clamp(min.val(), floor);
                var hi = clamp(max.val(), ceiling);
                if (lo > hi) {
                    lo = hi;
                }
                syncing = true;
                slider.slider('values', [lo, hi]);
                syncing = false;
            });
        });
    }

    /* ------------------------------------------------------ sample testing */

    /* Stacking order, and the colours the sample status pies use for the
       status each bucket is named after. */
    var TESTING_BUCKETS = [
        { key: 'tested', color: '#639e11' },
        { key: 'awaitingApproval', color: '#7f22e8' },
        { key: 'awaitingTesting', color: '#dda41b' },
        { key: 'notAtLab', color: '#4bc0d9' },
        { key: 'failed', color: '#b5651d' },
        { key: 'rejected', color: '#d8424d' },
        { key: 'other', color: '#999999' }
    ];
    var TESTING_CHART_LIMIT = 20;

    /* Numbers and percentages follow the interface language, so French reads
       "76,9 %" rather than "76.9%". */
    var numberLocale;

    function fmt(n) {
        return Number(n || 0).toLocaleString(numberLocale);
    }

    function pctText(value) {
        return (value / 100).toLocaleString(numberLocale, { style: 'percent', maximumFractionDigits: 1 });
    }

    function pct(part, whole) {
        return whole > 0 ? Math.round((part / whole) * 1000) / 10 : 0;
    }

    function sprintf(template) {
        var args = Array.prototype.slice.call(arguments, 1);
        return String(template).replace(/%s/g, function () {
            return args.length ? args.shift() : '';
        });
    }

    function renderSampleTesting(target, data) {
        var L = data.labels;
        try {
            numberLocale = data.locale ? Intl.NumberFormat.supportedLocalesOf([data.locale])[0] : undefined;
        } catch (e) {
            numberLocale = undefined;
        }
        var rows = data.rows || [];
        target.empty();

        if (!rows.length) {
            target.append($('<div class="clinic-report-note">').append(
                $('<p>').text(L.noData)
            ));
            return;
        }

        var sums = { total: 0 };
        $.each(TESTING_BUCKETS, function (i, b) {
            sums[b.key] = 0;
        });
        $.each(rows, function (i, r) {
            sums.total += r.total;
            $.each(TESTING_BUCKETS, function (j, b) {
                sums[b.key] += r[b.key];
            });
        });
        /* A bucket nobody is in (Not Yet at Lab on a lab instance, say) is
           left out of the legend and the table rather than shown as zeros. */
        var buckets = $.grep(TESTING_BUCKETS, function (b) {
            return sums[b.key] > 0;
        });
        var pending = sums.awaitingApproval + sums.awaitingTesting + sums.notAtLab;

        /* Headline figures */
        var cards = $('<div class="st-cards">');
        function card(label, value, note, color) {
            var c = $('<div class="st-card">').css('border-top-color', color);
            c.append($('<div class="st-card-label">').text(label));
            c.append($('<div class="st-card-value">').text(value));
            if (note) {
                c.append($('<div class="st-card-note">').text(note));
            }
            cards.append(c);
        }
        card(L.total, fmt(sums.total), data.period, '#3c8dbc');
        card(L.tested, fmt(sums.tested), sprintf(L.ofCollected, pctText(pct(sums.tested, sums.total))), '#639e11');
        card(L.pending, fmt(pending), sprintf(L.ofCollected, pctText(pct(pending, sums.total))), '#dda41b');
        card(L.failed, fmt(sums.failed), sprintf(L.ofCollected, pctText(pct(sums.failed, sums.total))), '#b5651d');
        card(L.rejected, fmt(sums.rejected), sprintf(L.ofCollected, pctText(pct(sums.rejected, sums.total))), '#d8424d');
        target.append(cards);

        /* Chart: horizontal, so facility names stay readable, and capped so
           a province with hundreds of facilities still draws legibly. The
           table below holds all of them. */
        var shown = rows.slice(0, TESTING_CHART_LIMIT);
        var chartBox = $('<div class="st-chart">');
        target.append(chartBox);
        if (window.Highcharts) {
            window.Highcharts.chart(chartBox[0], {
                chart: { type: 'bar', height: Math.max(240, shown.length * 28 + 130) },
                title: { text: L.title, align: 'left', style: { fontSize: '18px', fontWeight: '600' } },
                subtitle: {
                    align: 'left',
                    style: { fontSize: '13px' },
                    text: rows.length > shown.length
                        ? sprintf(L.topFacilities, shown.length, fmt(rows.length))
                        : sprintf(L.allFacilities, fmt(rows.length))
                },
                credits: { enabled: false },
                exporting: { sourceWidth: 1200 },
                xAxis: {
                    categories: $.map(shown, function (r) {
                        return r.facility;
                    }),
                    labels: { style: { fontSize: '13px', color: '#333' } }
                },
                yAxis: {
                    min: 0,
                    allowDecimals: false,
                    title: { text: L.samples, style: { fontSize: '13px' } },
                    labels: {
                        style: { fontSize: '12px' },
                        formatter: function () {
                            return fmt(this.value);
                        }
                    },
                    reversedStacks: false,
                    stackLabels: {
                        enabled: true,
                        formatter: function () {
                            return fmt(this.total);
                        },
                        style: { fontSize: '12px', fontWeight: '600', textOutline: 'none', color: '#1f2d3d' }
                    }
                },
                legend: { align: 'left', verticalAlign: 'top', layout: 'horizontal', itemStyle: { fontSize: '13px', fontWeight: '500' } },
                tooltip: {
                    shared: true,
                    useHTML: true,
                    style: { fontSize: '13px' },
                    formatter: function () {
                        var r = shown[this.points[0].point.index];
                        var html = '<b>' + window.Highcharts.escapeHTML(r.facility) + '</b><table class="st-tip">';
                        $.each(this.points, function (i, p) {
                            if (p.y > 0) {
                                html += '<tr><td><span style="color:' + p.color + '">●</span> '
                                    + p.series.name + '</td><td>' + fmt(p.y) + '</td><td>'
                                    + pctText(pct(p.y, r.total)) + '</td></tr>';
                            }
                        });
                        return html + '<tr class="st-tip-total"><td>' + L.total + '</td><td>'
                            + fmt(r.total) + '</td><td></td></tr></table>';
                    }
                },
                plotOptions: {
                    series: { stacking: 'normal', borderWidth: 0, pointPadding: 0.08, groupPadding: 0.08 }
                },
                series: $.map(buckets, function (b) {
                    return {
                        name: L[b.key],
                        color: b.color,
                        data: $.map(shown, function (r) {
                            return r[b.key];
                        })
                    };
                })
            });
        }

        /* Every facility, sortable */
        var table = $('<table class="table table-bordered table-striped table-hover st-table">');
        var head = $('<tr>')
            .append($('<th>').text(L.facility))
            .append($('<th>').text(L.state))
            .append($('<th>').text(L.district))
            .append($('<th class="text-right">').text(L.total));
        $.each(buckets, function (i, b) {
            head.append($('<th class="text-right">').append(
                $('<span class="st-swatch">').css('background', b.color), document.createTextNode(L[b.key])
            ));
        });
        head.append($('<th class="text-right">').text(L.testedRate));
        table.append($('<thead>').append(head));

        var body = $('<tbody>');
        $.each(rows, function (i, r) {
            var rate = pct(r.tested, r.total);
            var tr = $('<tr>')
                .append($('<td>').text(r.facility))
                .append($('<td>').text(r.state))
                .append($('<td>').text(r.district))
                .append($('<td class="text-right">').attr('data-order', r.total).text(fmt(r.total)));
            $.each(buckets, function (j, b) {
                var cell = $('<td class="text-right">').attr('data-order', r[b.key]).text(fmt(r[b.key]));
                if (!r[b.key]) {
                    cell.addClass('st-zero');
                }
                tr.append(cell);
            });
            tr.append($('<td class="text-right st-rate">').attr('data-order', rate).append(
                $('<span class="st-rate-bar">').css('width', rate + '%'),
                $('<span class="st-rate-value">').text(pctText(rate))
            ));
            body.append(tr);
        });
        table.append(body);

        target.append($('<h4 class="st-heading">').text(L.details));
        target.append($('<div class="table-responsive">').append(table));

        if ($.fn.dataTable) {
            table.dataTable({
                aaSorting: [[3, 'desc']],
                iDisplayLength: 25,
                bAutoWidth: false
            });
        }
    }

    /* -------------------------------------------------------------- public */

    var ClinicReports = {

        renderSampleTesting: renderSampleTesting,

        /* DataTables fnServerData. The table's own processing indicator is the
           loading state; this tracks the request so exports can wait on it. */
        serverData: function (sSource, aoData, fnCallback) {
            startLoading();
            return $.ajax({
                dataType: 'json',
                type: 'POST',
                url: sSource,
                data: aoData,
                success: fnCallback,
                complete: endLoading
            });
        },

        registerTab: function (paneId, options) {
            tabs[paneId] = $.extend({ ready: false }, options);
        },

        activePane: activePane,

        /* Searches the tab the user is looking at, and only that one. Resolves
           once the requests it started have come back, so an export can wait
           for the result set it is about to ask the server to replay. */
        searchActive: function () {
            var paneId = activePane();
            draw(paneId);
            var box = pane(paneId).find('.filter-panel').first();
            window.FilterPanel.collapse(box);
            if (pending === 0) {
                return $.Deferred().resolve().promise();
            }
            var deferred = $.Deferred();
            waiters.push(deferred);
            return deferred.promise();
        },

        /* Wires the page up. Call once from $(document).ready(), after the
           filters have been restored and the tabs registered. */
        start: function () {
            /* A link can name a tab (vl-clinic-reports.php#tab=sample-rejection). The
               tab is chosen before anything is built, so only that tab loads. */
            showTabFromHash();
            var previous = activePane();

            $('.filter-panel').on('change', 'select, input, textarea', function () {
                $(this).data('cr-touched', true);
            });

            initRangeSliders();

            /* The pregnancy and breastfeeding filters open enabled with no sex
               chosen and are only disabled once the sex select is touched, so
               the page starts out disagreeing with itself. Settle it on load. */
            $('select[onchange^="hideFemaleDetails"]').each(function () {
                if (typeof this.onchange === 'function') {
                    this.onchange();
                }
            });

            $('a[data-toggle="tab"]').on('shown.bs.tab', function () {
                var current = $($(this).attr('href')).attr('id');
                shareFilters(previous, current);
                previous = current;
                ensure(current);
                /* Replaced rather than pushed, so Back leaves the page instead
                   of stepping through every tab looked at. */
                if (window.history && history.replaceState) {
                    history.replaceState(null, '', '#tab=' + tabName($(this)));
                }
            });

            $(window).on('hashchange', showTabFromHash);

            ensure(activePane());
        }
    };

    window.ClinicReports = ClinicReports;
}(jQuery));
