/*
 * Patient Test History tab of the clinic report pages: find a patient, then
 * show every sample they have had, across every test type, as one history.
 *
 * The server does the matching and reads each result into an outcome (good,
 * bad, warn, rejected, pending, neutral); this file only draws. All text from
 * the database is inserted with .text() or escaped before it reaches markup.
 */
(function ($) {
    'use strict';

    var OUTCOME_COLORS = {
        good: '#2e8540',
        bad: '#d8424d',
        warn: '#dda41b',
        rejected: '#8e44ad',
        pending: '#3c8dbc',
        neutral: '#7f8c8d'
    };
    var OUTCOME_ORDER = ['good', 'bad', 'warn', 'rejected', 'pending', 'neutral'];
    var VL_FLOOR = 20; // where a below-detection result is drawn on the log axis
    var VL_OVERDUE_MONTHS = 12;
    var STALE_PENDING_MONTHS = 3;

    function PatientTimelineApp(root, config) {
        this.root = root;
        this.cfg = config;
        this.L = config.labels;
        this.results = root.find('.pt-results');
        this.view = root.find('.pt-view');
        this.input = root.find('.pt-search-input');
        this.request = null;
        this.timer = null;
        this.lastTerm = null;
        this.bind();
    }

    PatientTimelineApp.prototype = {

        /* ------------------------------------------------------ utilities */

        sprintf: function (template) {
            var args = Array.prototype.slice.call(arguments, 1);
            return String(template).replace(/%s/g, function () {
                return args.length ? args.shift() : '';
            });
        },

        esc: function (text) {
            return $('<div>').text(text == null ? '' : String(text)).html();
        },

        parseDate: function (ymd) {
            if (!ymd) {
                return null;
            }
            var p = String(ymd).split('-');
            return new Date(Date.UTC(+p[0], +p[1] - 1, +p[2]));
        },

        formatDate: function (ymd) {
            var d = this.parseDate(ymd);
            if (!d) {
                return '';
            }
            try {
                return d.toLocaleDateString(this.cfg.locale, { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC' });
            } catch (e) {
                return ymd;
            }
        },

        formatNumber: function (n) {
            try {
                return Number(n).toLocaleString(this.cfg.locale);
            } catch (e) {
                return String(n);
            }
        },

        daysBetween: function (a, b) {
            var da = this.parseDate(a);
            var db = this.parseDate(b);
            return da && db ? Math.round((db - da) / 86400000) : null;
        },

        monthsSince: function (ymd) {
            var d = this.parseDate(ymd);
            if (!d) {
                return null;
            }
            var now = new Date();
            return (now.getUTCFullYear() - d.getUTCFullYear()) * 12 + (now.getUTCMonth() - d.getUTCMonth());
        },

        typeName: function (type) {
            return (this.cfg.types[type] || {}).name || type;
        },

        typeShort: function (type) {
            return (this.cfg.types[type] || {}).short || type;
        },

        post: function (data) {
            if (this.request) {
                this.request.abort();
            }
            this.request = $.ajax({ url: this.cfg.endpoint, type: 'POST', dataType: 'json', data: data });
            return this.request;
        },

        message: function (target, text, cls) {
            target.empty().append($('<div class="pt-message">').addClass(cls || '').text(text));
        },

        /* --------------------------------------------------------- search */

        bind: function () {
            var self = this;
            this.input.attr('placeholder', this.L.searchPlaceholder);
            this.root.find('.pt-search-button').text(this.L.search);
            this.root.find('.pt-search-hint').text(this.L.searchHint);

            this.root.find('.pt-search-form').on('submit', function (e) {
                e.preventDefault();
                self.search(true);
            });
            this.input.on('input', function () {
                clearTimeout(self.timer);
                self.timer = setTimeout(function () {
                    self.search(false);
                }, 350);
            });
            this.root.on('click', '.pt-back', function () {
                self.showResults();
            });
            this.root.on('click', '.pt-print', function () {
                self.print();
            });
            this.root.on('click', '.pt-print-result', function () {
                self.printResult($(this).data('url'), $(this).data('id'));
            });
            this.root.on('click', '.pt-type-filter', function () {
                $(this).toggleClass('active').attr('aria-pressed', $(this).hasClass('active'));
                self.applyTypeFilter();
            });
        },

        focus: function () {
            this.input.trigger('focus');
        },

        search: function (submitted) {
            var self = this;
            var term = $.trim(this.input.val());
            if (term.length < 2) {
                this.results.empty();
                this.lastTerm = null;
                return;
            }
            if (term === this.lastTerm && !submitted) {
                return;
            }
            this.lastTerm = term;
            this.showResults();
            this.message(this.results, this.L.searching, 'pt-muted');

            this.post({ action: 'search', q: term }).done(function (data) {
                if (!data || data.error) {
                    self.message(self.results, (data && data.error) || self.L.error, 'pt-error');
                    return;
                }
                var patients = data.patients || [];
                var exact = $.grep(patients, function (p) {
                    return p.id.toLowerCase() === term.toLowerCase();
                });
                /* Pressing Enter on a complete ID opens that patient at once. */
                if (submitted && exact.length === 1) {
                    self.open(exact[0].id);
                    return;
                }
                self.renderResults(patients);
            }).fail(function (xhr, status) {
                if (status !== 'abort') {
                    self.message(self.results, self.L.error, 'pt-error');
                }
            });
        },

        renderResults: function (patients) {
            var self = this;
            this.results.empty();
            if (!patients.length) {
                this.message(this.results, this.L.noPatients, 'pt-muted');
                return;
            }
            var list = $('<div class="pt-result-list" role="list">');
            $.each(patients, function (i, p) {
                var item = $('<button type="button" class="pt-result" role="listitem">');
                item.append($('<span class="pt-avatar">').text(self.initials(p.name)));
                var main = $('<span class="pt-result-main">');
                main.append($('<span class="pt-result-name">').text(p.name || self.L.unnamed));
                var meta = $('<span class="pt-result-meta">');
                meta.append($('<span class="pt-code">').text(p.id));
                if (p.sex) {
                    meta.append($('<span>').text(p.sex));
                }
                if (p.dob) {
                    meta.append($('<span>').text(self.L.dob + ' ' + self.formatDate(p.dob)));
                }
                main.append(meta);
                item.append(main);

                var side = $('<span class="pt-result-side">');
                var chips = $('<span class="pt-chips">');
                $.each(p.tests, function (type, count) {
                    chips.append($('<span class="pt-chip">').text(self.typeShort(type) + ' × ' + count));
                });
                side.append(chips);
                if (p.lastCollected) {
                    side.append($('<span class="pt-result-date">').text(self.sprintf(self.L.lastSampleOn, self.formatDate(p.lastCollected.substr(0, 10)))));
                }
                item.append(side);
                item.on('click', function () {
                    self.open(p.id);
                });
                list.append(item);
            });
            this.results.append(list);
            if (patients.length >= 30) {
                this.results.append($('<p class="pt-muted pt-more">').text(this.sprintf(this.L.tooMany, patients.length)));
            }
        },

        initials: function (name) {
            var parts = $.trim(name || '').split(/\s+/);
            var first = parts[0] ? parts[0].charAt(0) : '';
            var last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : '';
            return (first + last).toUpperCase() || '?';
        },

        showResults: function () {
            this.view.prop('hidden', true).empty();
            this.results.prop('hidden', false);
            this.root.find('.pt-search').prop('hidden', false);
        },

        /* ------------------------------------------------------- timeline */

        open: function (patientId) {
            var self = this;
            this.results.prop('hidden', true);
            this.root.find('.pt-search').prop('hidden', true);
            this.view.prop('hidden', false);
            this.message(this.view, this.L.loading, 'pt-muted');

            this.post({ action: 'timeline', patientId: patientId }).done(function (data) {
                if (!data || data.error) {
                    self.message(self.view, (data && data.error) || self.L.error, 'pt-error');
                    return;
                }
                self.data = data;
                self.renderTimeline(data);
            }).fail(function (xhr, status) {
                if (status !== 'abort') {
                    self.message(self.view, self.L.error, 'pt-error');
                }
            });
        },

        renderTimeline: function (data) {
            this.view.empty();
            var toolbar = $('<div class="pt-toolbar">')
                .append($('<button type="button" class="btn btn-default btn-sm pt-back">')
                    .append('<em class="fa-solid fa-arrow-left"></em> ', document.createTextNode(this.L.back)))
                .append($('<button type="button" class="btn btn-default btn-sm pt-print">')
                    .append('<em class="fa-solid fa-print"></em> ', document.createTextNode(this.L.print)));
            this.view.append(toolbar);
            this.view.append(this.renderHeader(data));
            this.view.append(this.renderInsights(data));
            this.renderCharts(data);
            this.view.append(this.renderHistory(data));
            window.scrollTo({ top: this.root.offset().top - 80, behavior: 'smooth' });
        },

        renderHeader: function (data) {
            var self = this;
            var p = data.patient;
            var events = data.events;
            var name = p.names[0] || this.L.unnamed;

            var card = $('<div class="pt-patient">');
            card.append($('<span class="pt-avatar pt-avatar-lg">').text(this.initials(p.names[0])));

            var main = $('<div class="pt-patient-main">');
            main.append($('<h3 class="pt-patient-name">').text(name));
            if (p.names.length > 1) {
                main.append($('<div class="pt-muted">').text(this.L.alsoRecordedAs + ': ' + p.names.slice(1).join(', ')));
            }
            var facts = $('<dl class="pt-facts">');
            function fact(label, value) {
                if (value) {
                    facts.append($('<div>').append($('<dt>').text(label), $('<dd>').text(value)));
                }
            }
            fact(this.L.patientId, p.id);
            fact(this.L.sex, p.sexes.join(' / '));
            fact(this.L.dob, $.map(p.dobs, function (d) {
                return self.formatDate(d);
            }).join(' / '));
            if (!p.dobs.length && p.latestAge) {
                fact(this.L.age, this.sprintf(this.L.ageAt, p.latestAge.age, this.formatDate(p.latestAge.on)));
            }
            if (events.length) {
                fact(this.L.firstSample, this.formatDate(events[0].collected));
                fact(this.L.lastSample, this.formatDate(events[events.length - 1].collected));
            }
            fact(this.L.facilities, p.facilities.join(', '));
            main.append(facts);
            card.append(main);

            var counts = {};
            $.each(events, function (i, e) {
                counts[e.type] = (counts[e.type] || 0) + 1;
            });
            var side = $('<div class="pt-patient-side">');
            side.append($('<div class="pt-patient-total">').text(events.length)
                .append($('<small>').text(this.L.samples)));
            var chips = $('<div class="pt-chips">');
            $.each(counts, function (type, count) {
                chips.append($('<span class="pt-chip">').attr('title', self.typeName(type)).text(self.typeShort(type) + ' × ' + count));
            });
            side.append(chips);
            card.append(side);
            return card;
        },

        /* What a clinician would otherwise work out by reading every row. */
        renderInsights: function (data) {
            var self = this;
            var L = this.L.insights;
            var items = [];
            var events = data.events;
            var p = data.patient;

            function add(level, title, text) {
                items.push({ level: level, title: title, text: text });
            }

            var conflicts = [['names', p.names], ['sexes', p.sexes], ['dobs', $.map(p.dobs, function (d) {
                return self.formatDate(d);
            })]];
            $.each(conflicts, function (i, c) {
                if (c[1].length > 1) {
                    add('warn', null, self.sprintf(L.conflict, L[c[0]], c[1].join(', ')));
                }
            });

            var vl = $.grep(events, function (e) {
                return e.type === 'vl' && (e.outcome === 'good' || e.outcome === 'bad');
            });
            if (vl.length) {
                var last = vl[vl.length - 1];
                var months = this.monthsSince(last.collected);
                var value = last.value !== null ? this.formatNumber(last.value) + ' ' + this.L.copies : last.result;
                add(last.outcome, L.latestVl,
                    (last.outcome === 'good' ? L.suppressed : L.notSuppressed) + ' · ' + value + ' · '
                    + this.formatDate(last.collected) + (months !== null ? ' (' + this.sprintf(L.monthsAgo, months) + ')' : ''));

                var suppressed = $.grep(vl, function (e) {
                    return e.outcome === 'good';
                }).length;
                add(suppressed === vl.length ? 'good' : 'neutral', null, this.sprintf(L.suppressionRate, suppressed, vl.length));

                if (vl.length >= 2) {
                    var prev = vl[vl.length - 2];
                    if (last.outcome === 'bad' && prev.outcome === 'bad') {
                        add('bad', null, L.consecutiveHigh);
                    } else if (last.outcome === 'bad' && prev.outcome === 'good') {
                        add('bad', null, L.rebound);
                    } else if (last.outcome === 'good' && prev.outcome === 'bad') {
                        add('good', null, L.resuppressed);
                    }
                }
                if (months !== null && months > VL_OVERDUE_MONTHS) {
                    add('warn', null, this.sprintf(L.vlOverdue, VL_OVERDUE_MONTHS));
                }
            }

            var cd4 = $.grep(events, function (e) {
                return e.type === 'cd4' && e.value !== null;
            });
            if (cd4.length) {
                var lastCd4 = cd4[cd4.length - 1];
                add(lastCd4.outcome, L.latestCd4, lastCd4.result + ' · ' + this.formatDate(lastCd4.collected));
                if (lastCd4.value < data.cd4Low) {
                    add('bad', null, this.sprintf(L.cd4Low, data.cd4Low));
                }
            }

            var eidPositive = $.grep(events, function (e) {
                return e.type === 'eid' && e.outcome === 'bad';
            });
            if (eidPositive.length) {
                add('bad', null, this.sprintf(L.eidPositive, this.formatDate(eidPositive[0].collected)));
            }

            var pending = $.grep(events, function (e) {
                return e.outcome === 'pending';
            });
            if (pending.length) {
                /* A sample still open months after collection is not work in
                   progress; it is stuck, and says so. */
                var waited = this.monthsSince(pending[0].collected);
                var stale = waited !== null && waited >= STALE_PENDING_MONTHS;
                add(stale ? 'warn' : 'pending', null, (pending.length === 1
                    ? this.sprintf(L.pendingOne, this.formatDate(pending[0].collected))
                    : this.sprintf(L.pending, pending.length, this.formatDate(pending[0].collected)))
                    + (stale ? ' (' + this.sprintf(L.monthsAgo, waited) + ')' : ''));
            }

            var rejected = $.grep(events, function (e) {
                return e.outcome === 'rejected';
            });
            if (rejected.length) {
                var reason = rejected[rejected.length - 1].rejectionReason;
                add('rejected', null, (rejected.length === 1 ? L.rejectedOne : this.sprintf(L.rejected, rejected.length)) + (reason ? ', ' + this.sprintf(L.lastRejection, reason) : ''));
            }

            var box = $('<div class="pt-insights">');
            $.each(items, function (i, item) {
                var el = $('<div class="pt-insight">').addClass('pt-' + item.level)
                    .css('border-left-color', OUTCOME_COLORS[item.level] || OUTCOME_COLORS.neutral);
                if (item.title) {
                    el.append($('<div class="pt-insight-title">').text(item.title));
                }
                el.append($('<div class="pt-insight-text">').text(item.text));
                box.append(el);
            });
            return box;
        },

        renderCharts: function (data) {
            if (!window.Highcharts || !data.events.length) {
                return;
            }
            var self = this;
            var events = data.events;
            var grid = $('<div class="pt-charts">');
            this.view.append(grid);

            this.overviewChart(grid, events);

            var vl = $.grep(events, function (e) {
                return e.type === 'vl' && e.collected && (e.outcome === 'good' || e.outcome === 'bad' || (e.outcome === 'neutral' && e.value !== null));
            });
            if (vl.length) {
                this.vlChart(grid, vl, data.vlThreshold);
            }
            var cd4 = $.grep(events, function (e) {
                return e.type === 'cd4' && e.value !== null && e.collected;
            });
            if (cd4.length) {
                this.cd4Chart(grid, cd4, data.cd4Low);
            }
            /* A lone trend chart gets the whole row rather than half of it. */
            var trends = grid.children('.pt-chart:not(.pt-chart-wide)');
            if (trends.length === 1) {
                trends.addClass('pt-chart-wide');
                $.each(window.Highcharts.charts, function (i, chart) {
                    if (chart && trends[0].contains(chart.renderTo)) {
                        chart.reflow();
                    }
                });
            }
            if (!grid.children().length) {
                grid.remove();
            }
        },

        /* Every sample on one strip per test type. With a single sample there
           is no "over time" to show, and the history list below says it all. */
        overviewChart: function (grid, events) {
            var self = this;
            var dated = $.grep(events, function (e) {
                return !!e.collected;
            });
            if (dated.length < 2) {
                return;
            }
            var first = this.parseDate(dated[0].collected).getTime();
            var last = this.parseDate(dated[dated.length - 1].collected).getTime();
            var pad = Math.max((last - first) * 0.03, 15 * 86400000);
            var types = [];
            $.each(events, function (i, e) {
                if ($.inArray(e.type, types) === -1) {
                    types.push(e.type);
                }
            });
            var series = [];
            $.each(OUTCOME_ORDER, function (i, outcome) {
                var points = [];
                $.each(events, function (j, e) {
                    if (e.outcome === outcome && e.collected) {
                        points.push({ x: self.parseDate(e.collected).getTime(), y: $.inArray(e.type, types), event: e });
                    }
                });
                if (points.length) {
                    series.push({
                        name: self.L.outcomes[outcome],
                        color: OUTCOME_COLORS[outcome],
                        data: points,
                        marker: { symbol: 'circle', radius: 7, lineColor: '#fff', lineWidth: 1.5 }
                    });
                }
            });
            var overview = $('<div class="pt-chart pt-chart-wide">');
            grid.append(overview);
            window.Highcharts.chart(overview[0], {
                chart: { type: 'scatter', height: Math.max(150, 80 + types.length * 46), marginTop: 44, zoomType: 'x' },
                title: { text: this.L.overview, align: 'left', style: { fontSize: '15px', fontWeight: '600' } },
                credits: { enabled: false },
                exporting: { enabled: false },
                xAxis: {
                    type: 'datetime',
                    min: first - pad,
                    max: last + pad,
                    gridLineWidth: 1,
                    gridLineColor: '#eef1f4',
                    dateTimeLabelFormats: { day: '%e %b %Y', week: '%e %b %Y', month: '%b %Y', year: '%Y' }
                },
                yAxis: {
                    title: { text: null },
                    categories: $.map(types, function (t) {
                        return self.typeShort(t);
                    }),
                    min: 0,
                    max: types.length - 1,
                    tickInterval: 1,
                    gridLineColor: '#eef1f4',
                    labels: { style: { fontSize: '12px', fontWeight: '600' } }
                },
                legend: { align: 'right', verticalAlign: 'top', floating: true, y: -4, itemStyle: { fontSize: '12px', fontWeight: '500' } },
                tooltip: {
                    useHTML: true,
                    formatter: function () {
                        return self.tooltip(this.point.event);
                    }
                },
                plotOptions: { scatter: { jitter: { x: 0, y: 0 } } },
                series: series
            });
        },

        vlChart: function (grid, vl, threshold) {
            var self = this;
            var box = $('<div class="pt-chart">');
            grid.append(box);
            var max = threshold;
            var points = $.map(vl, function (e) {
                var y = e.value !== null ? Math.max(e.value, 1) : VL_FLOOR;
                max = Math.max(max, y);
                return {
                    x: self.parseDate(e.collected).getTime(),
                    y: y,
                    event: e,
                    color: OUTCOME_COLORS[e.outcome],
                    marker: e.value === null
                        ? { fillColor: '#fff', lineColor: OUTCOME_COLORS[e.outcome], lineWidth: 2, radius: 5 }
                        : { radius: 6 }
                };
            });
            window.Highcharts.chart(box[0], {
                chart: { height: 300, zoomType: 'x' },
                title: { text: this.L.vlTrend, align: 'left', style: { fontSize: '15px', fontWeight: '600' } },
                subtitle: { text: '○ ' + this.L.notDetected, align: 'left', style: { fontSize: '12px' } },
                credits: { enabled: false },
                exporting: { enabled: false },
                legend: { enabled: false },
                xAxis: { type: 'datetime' },
                yAxis: {
                    type: 'logarithmic',
                    min: 10,
                    max: Math.pow(10, Math.ceil(Math.log10(max * 1.5))),
                    title: { text: this.L.copies },
                    gridLineColor: '#eef1f4',
                    labels: {
                        formatter: function () {
                            return self.formatNumber(this.value);
                        }
                    },
                    plotBands: [{ from: 1, to: threshold, color: 'rgba(46, 133, 64, 0.06)' }],
                    plotLines: [{
                        value: threshold,
                        color: '#d8424d',
                        dashStyle: 'Dash',
                        width: 1.5,
                        zIndex: 3,
                        label: {
                            text: this.sprintf(this.L.threshold, this.formatNumber(threshold) + ' ' + this.L.copies),
                            align: 'right',
                            style: { color: '#d8424d', fontSize: '11px' }
                        }
                    }]
                },
                tooltip: {
                    useHTML: true,
                    formatter: function () {
                        return self.tooltip(this.point.event);
                    }
                },
                series: [{ type: 'line', data: points, color: '#9aa5b1', lineWidth: 1.5 }]
            });
        },

        cd4Chart: function (grid, cd4, low) {
            var self = this;
            var box = $('<div class="pt-chart">');
            grid.append(box);
            window.Highcharts.chart(box[0], {
                chart: { height: 300, zoomType: 'x' },
                title: { text: this.L.cd4Trend, align: 'left', style: { fontSize: '15px', fontWeight: '600' } },
                credits: { enabled: false },
                exporting: { enabled: false },
                legend: { enabled: false },
                xAxis: { type: 'datetime' },
                yAxis: {
                    min: 0,
                    title: { text: this.L.cells },
                    gridLineColor: '#eef1f4',
                    plotLines: [{
                        value: low,
                        color: '#d8424d',
                        dashStyle: 'Dash',
                        width: 1.5,
                        zIndex: 3,
                        label: { text: this.formatNumber(low), align: 'right', style: { color: '#d8424d', fontSize: '11px' } }
                    }]
                },
                tooltip: {
                    useHTML: true,
                    formatter: function () {
                        return self.tooltip(this.point.event);
                    }
                },
                series: [{
                    type: 'line',
                    color: '#9aa5b1',
                    lineWidth: 1.5,
                    data: $.map(cd4, function (e) {
                        return { x: self.parseDate(e.collected).getTime(), y: e.value, event: e, color: OUTCOME_COLORS[e.outcome], marker: { radius: 6 } };
                    })
                }]
            });
        },

        tooltip: function (e) {
            var html = '<div class="pt-tip"><b>' + this.esc(this.typeName(e.type)) + '</b> · ' + this.esc(this.formatDate(e.collected))
                + '<br><span style="color:' + OUTCOME_COLORS[e.outcome] + ';font-weight:600">'
                + this.esc(e.result || e.statusName) + '</span>';
            if (e.code) {
                html += '<br>' + this.esc(this.L.sampleCode) + ': ' + this.esc(e.code);
            }
            if (e.lab) {
                html += '<br>' + this.esc(this.L.lab) + ': ' + this.esc(e.lab);
            }
            return html + '</div>';
        },

        renderHistory: function (data) {
            var self = this;
            var wrap = $('<div class="pt-history">');
            var head = $('<div class="pt-history-head">').append($('<h4>').text(this.L.history));

            var types = [];
            $.each(data.events, function (i, e) {
                if ($.inArray(e.type, types) === -1) {
                    types.push(e.type);
                }
            });
            if (types.length > 1) {
                var filters = $('<div class="pt-type-filters" role="group">');
                $.each(types, function (i, t) {
                    filters.append($('<button type="button" class="pt-type-filter active" aria-pressed="true">')
                        .attr('data-type', t).text(self.typeName(t)));
                });
                head.append(filters);
            }
            wrap.append(head);

            var list = $('<ol class="pt-timeline">');
            var year = null;
            var events = data.events.slice().reverse(); // most recent first
            $.each(events, function (i, e) {
                var y = e.collected ? e.collected.substr(0, 4) : '';
                if (y !== year) {
                    year = y;
                    list.append($('<li class="pt-year">').text(y || '—'));
                }
                list.append(self.renderEvent(e));
            });
            wrap.append(list);
            return wrap;
        },

        renderEvent: function (e) {
            var L = this.L;
            var item = $('<li class="pt-event">').attr('data-type', e.type).addClass('pt-' + e.outcome);
            item.append($('<span class="pt-dot">').css('background', OUTCOME_COLORS[e.outcome]));

            var date = $('<div class="pt-event-date">').text(this.formatDate(e.collected));
            item.append(date);

            var card = $('<div class="pt-event-card">').css('border-left-color', OUTCOME_COLORS[e.outcome]);
            var top = $('<div class="pt-event-top">');
            top.append($('<span class="pt-badge">').attr('title', this.typeName(e.type)).text(this.typeShort(e.type)));
            if (e.type === 'generic-tests' && e.note) {
                top.append($('<span class="pt-event-test">').text(e.note));
            }
            var resultText = e.result || (e.outcome === 'pending' ? L.awaitingResult : e.statusName);
            /* A bare number is a count; say what of. */
            if (e.type === 'vl' && e.value !== null && /^[\d.,\s]+$/.test(e.result)) {
                resultText = this.formatNumber(e.value) + ' ' + L.copies;
            }
            top.append($('<span class="pt-event-result">').css('color', OUTCOME_COLORS[e.outcome]).text(resultText));
            if (e.statusName && e.statusName !== resultText) {
                top.append($('<span class="pt-status">').text(e.statusName));
            }
            if (e.printUrl) {
                top.append($('<button type="button" class="btn btn-link btn-xs pt-print-result">')
                    .attr({ 'data-url': e.printUrl, 'data-id': e.sampleId, title: L.printResult })
                    .append('<em class="fa-solid fa-file-pdf"></em> ', document.createTextNode(L.printResult)));
            }
            card.append(top);

            var meta = $('<div class="pt-event-meta">');
            function part(label, value) {
                if (value) {
                    meta.append($('<span>').append($('<span class="pt-meta-label">').text(label + ': '), document.createTextNode(value)));
                }
            }
            part(L.sampleCode, e.code);
            part(L.sampleType, e.sampleType);
            part(L.facility, e.facility);
            part(L.lab, e.lab);
            if (e.type !== 'generic-tests') {
                part(L.reason, e.note);
            }
            /* Only a sample with a result has a turnaround. One still open can
               carry a tested date from an earlier run that was sent for re-test. */
            var hasResult = e.outcome !== 'pending' && e.outcome !== 'rejected';
            var tat = hasResult ? this.daysBetween(e.collected, e.tested) : null;
            if (e.outcome === 'pending') {
                var open = this.daysBetween(e.collected, new Date().toISOString().substr(0, 10));
                if (open !== null && open > 0) {
                    var months = this.monthsSince(e.collected);
                    meta.append($('<span class="pt-tat">').text(open > 60 && months
                        ? this.sprintf(L.openForMonths, months)
                        : this.sprintf(L.openFor, open)));
                }
            }
            if (tat !== null && tat >= 0) {
                meta.append($('<span class="pt-tat">').text(tat === 0 ? L.resultSameDay : (tat === 1 ? L.resultInOne : this.sprintf(L.resultIn, tat))));
            }
            card.append(meta);
            if (e.rejectionReason) {
                card.append($('<div class="pt-event-rejection">').text(L.rejectionReason + ': ' + e.rejectionReason));
            }
            item.append(card);
            return item;
        },

        applyTypeFilter: function () {
            var active = this.view.find('.pt-type-filter.active').map(function () {
                return $(this).attr('data-type');
            }).get();
            var list = this.view.find('.pt-timeline');
            list.find('.pt-event').each(function () {
                $(this).prop('hidden', $.inArray($(this).attr('data-type'), active) === -1);
            });
            /* A year heading with nothing left under it goes too. */
            list.find('.pt-year').each(function () {
                var next = $(this).nextUntil('.pt-year').filter(':not([hidden])');
                $(this).prop('hidden', next.length === 0);
            });
        },

        printResult: function (url, id) {
            var L = this.L;
            $.blockUI();
            $.post(url, { source: 'print', id: id }, function (data) {
                $.unblockUI();
                if (!data) {
                    alert(L.noDownload);
                    return;
                }
                window.open('/download.php?f=' + data, '_blank');
            }).fail(function () {
                $.unblockUI();
                alert(L.noDownload);
            });
        },

        print: function () {
            $('body').addClass('pt-printing');
            $(window).one('afterprint', function () {
                $('body').removeClass('pt-printing');
            });
            window.print();
            setTimeout(function () {
                $('body').removeClass('pt-printing');
            }, 1000);
        }
    };

    var instance = null;

    window.PatientTimeline = {
        init: function (root, config) {
            if (!instance) {
                instance = new PatientTimelineApp(root, config);
            }
            instance.focus();
            return instance;
        }
    };
}(jQuery));
