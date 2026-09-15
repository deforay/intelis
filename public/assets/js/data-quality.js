/*
 * Data Quality tab of the clinic report pages: which key fields were not
 * captured, how often, at which facilities, and on which samples.
 *
 * Rendered from /reports/get-data-quality.php. The page supplies the check
 * list and every label through _data-quality-tab.php.
 */
(function ($) {
    'use strict';

    var root, config, L;
    var lastFilters = null;
    var summary = null;
    var requestSeq = 0;
    var numberLocale;

    /* ------------------------------------------------------------ helpers */

    function fmt(n) {
        return Number(n || 0).toLocaleString(numberLocale);
    }

    function pct(part, whole) {
        return whole > 0 ? (part / whole) * 100 : 0;
    }

    function pctText(value) {
        /* One decimal near the ends of the scale, where 99% and 99.8% or 0%
           and 0.4% say different things. */
        var digits = (value > 0 && value < 10) || (value > 90 && value < 100) ? 1 : 0;
        return (value / 100).toLocaleString(numberLocale, {
            style: 'percent',
            maximumFractionDigits: digits
        });
    }

    function sprintf(template) {
        var args = Array.prototype.slice.call(arguments, 1);
        return String(template).replace(/%s/g, function () {
            return args.length ? args.shift() : '';
        });
    }

    function dateText(ymd) {
        if (!ymd) {
            return '';
        }
        var d = new Date(ymd + 'T00:00:00');
        return isNaN(d) ? ymd : d.toLocaleDateString(numberLocale, { day: '2-digit', month: 'short', year: 'numeric' });
    }

    /* Red for gaps that touch many samples, amber for some, yellow for a few. */
    function severity(rate) {
        if (rate >= 10) {
            return 'high';
        }
        return rate >= 2 ? 'medium' : 'low';
    }

    function checkLabel(key) {
        var found = $.grep(config.checks, function (c) {
            return c.key === key;
        });
        return found.length ? found[0].label : key;
    }

    function notCapturedKeys() {
        return $.map(summary ? summary.checks : [], function (c) {
            return c.notCaptured ? c.key : null;
        });
    }

    function post(data) {
        return $.ajax({
            url: config.endpoint,
            type: 'POST',
            dataType: 'json',
            data: $.extend({ testType: config.testType }, lastFilters, data)
        });
    }

    /* ------------------------------------------------------------ filters */

    function defaultRange() {
        return moment().subtract(29, 'days').format('DD-MMM-YYYY') + ' to ' + moment().format('DD-MMM-YYYY');
    }

    function initFilters() {
        var date = $('#dqSampleCollectionDate');
        if ($.fn.daterangepicker && window.moment) {
            var ranges = {};
            ranges[L.ranges.last30] = [moment().subtract(29, 'days'), moment()];
            ranges[L.ranges.last90] = [moment().subtract(89, 'days'), moment()];
            ranges[L.ranges.last180] = [moment().subtract(179, 'days'), moment()];
            ranges[L.ranges.thisMonth] = [moment().startOf('month'), moment()];
            ranges[L.ranges.lastMonth] = [moment().subtract(1, 'month').startOf('month'), moment().subtract(1, 'month').endOf('month')];
            ranges[L.ranges.last12Months] = [moment().subtract(12, 'month').startOf('month'), moment()];
            ranges[L.ranges.yearToDate] = [moment().startOf('year'), moment()];
            ranges[L.ranges.previousYear] = [moment().subtract(1, 'year').startOf('year'), moment().subtract(1, 'year').endOf('year')];
            date.daterangepicker({
                locale: {
                    format: 'DD-MMM-YYYY',
                    separator: ' to ',
                    applyLabel: L.ranges.apply,
                    cancelLabel: L.ranges.cancel,
                    customRangeLabel: L.ranges.custom
                },
                autoUpdateInput: false,
                showDropdowns: true,
                alwaysShowCalendars: true,
                minDate: moment('2013-01-01'),
                maxDate: moment(),
                ranges: ranges
            });
            date.on('apply.daterangepicker', function (ev, picker) {
                $(this).val(picker.startDate.format('DD-MMM-YYYY') + ' to ' + picker.endDate.format('DD-MMM-YYYY')).trigger('change');
            });
        }
        if (!date.val()) {
            date.val(defaultRange());
        }

        if ($.fn.select2) {
            $('#dqState').select2({ placeholder: L.selectProvince, width: '100%' });
            $('#dqDistrict').select2({ placeholder: L.selectDistrict, width: '100%' });
            $('#dqFacilityName').select2({ placeholder: L.selectFacilities, width: '100%' });
        }

        var panel = root.prevAll('.filter-panel').first();
        panel.on('click', '.dq-search', function () {
            ClinicReports.searchActive();
        });
        panel.on('click', '.dq-reset', function () {
            /* change.select2 refreshes the boxes without firing the province
               handler, which would empty the district and facility lists. */
            panel.find('.dqReportFilter').val(null).trigger('change.select2');
            date.val(defaultRange());
            ClinicReports.searchActive();
        });
    }

    function readFilters() {
        return {
            sampleCollectionDate: $('#dqSampleCollectionDate').val() || '',
            state: $('#dqState').val() || '',
            district: $('#dqDistrict').val() || '',
            facilityName: $('#dqFacilityName').val() || [],
            implementingPartner: $('#dqImplementingPartner').val() || ''
        };
    }

    /* ------------------------------------------------------------ summary */

    function search() {
        lastFilters = readFilters();
        var seq = ++requestSeq;
        root.empty().append($('<div class="dq-loading">').append(
            $('<em class="fa-solid fa-spinner fa-spin" aria-hidden="true">'),
            document.createTextNode(' ' + L.loading)
        ));
        post({ action: 'summary' })
            .done(function (data) {
                if (seq !== requestSeq) {
                    return;
                }
                if (!data || data.error) {
                    showError(data && data.error);
                    return;
                }
                summary = data;
                render();
            })
            .fail(function (xhr, status) {
                if (seq === requestSeq && status !== 'abort') {
                    showError();
                }
            });
    }

    function showError(message) {
        root.empty().append($('<div class="alert alert-danger">').text(message || L.error));
    }

    function render() {
        root.empty();
        if (!summary.total) {
            root.append($('<div class="clinic-report-note">').append($('<p>').text(L.noData)));
            return;
        }
        root.append(renderCards());
        root.append($('<div class="dq-samples" hidden>'));
        root.append(renderFields());
        root.append(renderFacilities());
    }

    function renderCards() {
        var cards = $('<div class="st-cards">');
        function card(label, value, note, color) {
            cards.append($('<div class="st-card">').css('border-top-color', color).append(
                $('<div class="st-card-label">').text(label),
                $('<div class="st-card-value">').text(value),
                $('<div class="st-card-note">').text(note)
            ));
        }
        var complete = summary.total - summary.incomplete;
        var affected = $.grep(summary.facilities, function (f) {
            return f.incomplete > 0;
        }).length;

        card(L.checked, fmt(summary.total), summary.period, '#3c8dbc');
        card(L.complete, fmt(complete), sprintf(L.ofSamples, pctText(pct(complete, summary.total))), '#639e11');
        card(L.incomplete, fmt(summary.incomplete), sprintf(L.ofSamples, pctText(pct(summary.incomplete, summary.total))),
            summary.incomplete ? '#d8424d' : '#639e11');
        card(L.facilitiesAffected, fmt(affected), sprintf(L.ofFacilities, fmt(summary.facilities.length)), affected ? '#dda41b' : '#639e11');

        if (summary.incomplete > 0) {
            var all = $('<button type="button" class="btn btn-default btn-sm dq-view-all">').append(
                $('<em class="fa-solid fa-list" aria-hidden="true">'),
                document.createTextNode(' ' + L.listAll)
            ).on('click', function () {
                openSamples(null, null);
            });
            return $('<div>').append(cards, $('<div class="dq-actions">').append(all));
        }
        return cards;
    }

    function renderFields() {
        var section = $('<div class="dq-section">');
        section.append($('<h4 class="st-heading">').text(L.fields));
        section.append($('<p class="dq-hint">').text(L.fieldsHint));

        var grid = $('<div class="dq-groups">');
        $.each(['patient', 'sample', 'lab'], function (i, group) {
            var checks = $.grep(summary.checks, function (c) {
                return c.group === group;
            });
            if (!checks.length) {
                return;
            }
            var box = $('<div class="dq-group">').append($('<div class="dq-group-title">').text(L.groups[group]));
            $.each(checks, function (j, c) {
                box.append(renderField(c));
            });
            grid.append(box);
        });
        return section.append(grid);
    }

    function renderField(c) {
        var rate = pct(c.missing, c.applies);
        var head = $('<div class="dq-field-head">').append($('<span class="dq-field-label">').text(c.label));
        var row;

        if (c.notCaptured) {
            row = $('<div class="dq-field dq-field-muted">');
            return row.append(head, $('<div class="dq-field-note">').text(L.notCaptured));
        }
        if (!c.applies) {
            row = $('<div class="dq-field dq-field-muted">');
            return row.append(head, $('<div class="dq-field-note">').text(L.notExpected));
        }
        if (!c.missing) {
            row = $('<div class="dq-field dq-field-ok">');
            head.append($('<span class="dq-field-rate">').append($('<em class="fa-solid fa-check" aria-hidden="true">')));
            return row.append(head, $('<div class="dq-field-note">').text(L.allCaptured));
        }

        row = $('<button type="button" class="dq-field dq-field-gap">')
            .addClass('dq-' + severity(rate))
            .attr('title', L.view)
            .on('click', function () {
                openSamples(c.key, null);
            });
        head.append($('<span class="dq-field-rate">').text(pctText(rate)));
        return row.append(
            head,
            $('<div class="dq-bar">').append($('<span>').css('width', Math.max(rate, 1.5) + '%')),
            $('<div class="dq-field-note">').text(sprintf(L.ofExpected, fmt(c.missing), fmt(c.applies)))
        );
    }

    function renderFacilities() {
        var section = $('<div class="dq-section">');
        section.append($('<h4 class="st-heading">').text(L.facilities));

        var gaps = $.grep(summary.checks, function (c) {
            return c.missing > 0 && !c.notCaptured;
        });
        if (!gaps.length) {
            return section.append($('<div class="clinic-report-note">').append(
                $('<em class="fa-solid fa-circle-check" aria-hidden="true">'),
                $('<p>').text(L.noGaps)
            ));
        }
        section.append($('<p class="dq-hint">').text(L.facilitiesHint));

        var table = $('<table class="table table-bordered table-hover st-table dq-table">');
        var head = $('<tr>')
            .append($('<th>').text(L.facility))
            .append($('<th class="text-right">').text(L.samples))
            .append($('<th class="text-right">').text(L.withGaps))
            .append($('<th class="text-right">').text(L.completeRate));
        $.each(gaps, function (i, c) {
            head.append($('<th class="text-right dq-col">').text(c.label));
        });
        table.append($('<thead>').append(head));

        var body = $('<tbody>');
        $.each(summary.facilities, function (i, f) {
            var name = f.name || L.noFacility;
            var place = $.grep([f.district, f.state], Boolean).join(', ');
            var complete = pct(f.total - f.incomplete, f.total);

            var nameCell = $('<td>').attr('data-search', name + ' ' + place);
            if (f.incomplete > 0) {
                nameCell.append($('<button type="button" class="dq-link">').text(name).on('click', function () {
                    openSamples(null, f);
                }));
            } else {
                nameCell.append($('<span>').text(name));
            }
            if (place) {
                nameCell.append($('<div class="dq-place">').text(place));
            }

            var tr = $('<tr>').append(
                nameCell,
                $('<td class="text-right">').attr('data-order', f.total).text(fmt(f.total)),
                $('<td class="text-right">').attr('data-order', f.incomplete).text(fmt(f.incomplete)).toggleClass('st-zero', !f.incomplete),
                $('<td class="text-right st-rate">').attr('data-order', complete).append(
                    $('<span class="st-rate-bar">').css('width', complete + '%'),
                    $('<span class="st-rate-value">').text(pctText(complete))
                )
            );
            $.each(gaps, function (j, c) {
                var n = f.missing[c.key] || 0;
                var expected = c.key in f.applies ? f.applies[c.key] : f.total;
                var rate = pct(n, expected);
                var cell = $('<td class="text-right dq-cell">').attr('data-order', rate);
                if (n > 0) {
                    cell.addClass('dq-' + severity(rate)).append(
                        $('<button type="button" class="dq-cell-button">')
                            .attr('title', sprintf(L.ofExpected, fmt(n), fmt(expected)))
                            .text(fmt(n))
                            .on('click', function () {
                                openSamples(c.key, f);
                            })
                    );
                } else {
                    cell.addClass('st-zero').text('·');
                }
                tr.append(cell);
            });
            body.append(tr);
        });
        table.append(body);
        section.append($('<div class="table-responsive">').append(table));

        if ($.fn.dataTable) {
            table.dataTable({
                aaSorting: [[2, 'desc']],
                iDisplayLength: 25,
                bAutoWidth: false
            });
        }
        return section;
    }

    /* ------------------------------------------------------------ samples */

    function openSamples(check, facility) {
        var panel = root.find('.dq-samples');
        var params = {
            check: check || '',
            facilityId: facility ? facility.id : '',
            ignore: notCapturedKeys().join(',')
        };
        var title = check ? sprintf(L.listCheck, checkLabel(check)) : L.listAll;
        if (facility) {
            title += ' ' + sprintf(L.atFacility, facility.name || L.noFacility);
        }

        var header = $('<div class="dq-samples-head">').append(
            $('<h4>').text(title),
            $('<div class="dq-samples-actions">').append(
                $('<button type="button" class="btn btn-success btn-sm">').append(
                    $('<em class="fa-solid fa-cloud-arrow-down" aria-hidden="true">'),
                    document.createTextNode(' ' + L.export)
                ).on('click', function () {
                    exportSamples(params);
                }),
                $('<button type="button" class="btn btn-default btn-sm">').append(
                    $('<em class="fa-solid fa-xmark" aria-hidden="true">'),
                    document.createTextNode(' ' + L.close)
                ).on('click', function () {
                    panel.attr('hidden', true).empty();
                })
            )
        );
        var content = $('<div class="dq-samples-body">').append($('<div class="dq-loading">').append(
            $('<em class="fa-solid fa-spinner fa-spin" aria-hidden="true">'),
            document.createTextNode(' ' + L.loading)
        ));
        panel.empty().append(header, content).removeAttr('hidden');
        panel[0].scrollIntoView({ behavior: 'smooth', block: 'start' });

        var seq = ++requestSeq;
        post($.extend({ action: 'samples' }, params))
            .done(function (data) {
                if (seq !== requestSeq) {
                    return;
                }
                if (!data || data.error) {
                    content.empty().append($('<div class="alert alert-danger">').text((data && data.error) || L.error));
                    return;
                }
                content.empty().append(renderSamples(data, check));
            })
            .fail(function (xhr, status) {
                if (seq === requestSeq && status !== 'abort') {
                    content.empty().append($('<div class="alert alert-danger">').text(L.error));
                }
            });
    }

    function renderSamples(data, check) {
        var wrap = $('<div>');
        if (data.samples.length >= data.limit) {
            wrap.append($('<p class="dq-hint">').text(sprintf(L.showing, fmt(data.limit))));
        }
        var table = $('<table class="table table-bordered table-striped table-hover dq-sample-table">');
        table.append($('<thead>').append($('<tr>').append(
            $('<th>').text(L.sampleCode),
            $('<th>').text(L.patientId),
            $('<th>').text(L.collected),
            $('<th>').text(L.facility),
            $('<th>').text(L.status),
            $('<th>').text(L.missingFields)
        )));
        var body = $('<tbody>');
        $.each(data.samples, function (i, s) {
            var code = $('<td>');
            if (s.editUrl) {
                code.append($('<a target="_blank" rel="noopener">').attr('href', s.editUrl).attr('title', L.edit).append(
                    document.createTextNode(s.sampleCode + ' '),
                    $('<em class="fa-solid fa-pen-to-square" aria-hidden="true">')
                ));
            } else {
                code.text(s.sampleCode);
            }
            var collected = $('<td>').attr('data-order', s.collected || s.requested);
            if (s.collected) {
                collected.text(dateText(s.collected));
            } else if (s.requested) {
                collected.append($('<span class="dq-muted">').text(sprintf(L.requestedOn, dateText(s.requested))));
            }
            var chips = $('<td>');
            $.each(s.missing, function (j, key) {
                chips.append($('<span class="dq-chip">').toggleClass('dq-chip-active', key === check).text(checkLabel(key)));
            });
            body.append($('<tr>').append(
                code,
                $('<td>').text(s.patientId),
                collected,
                $('<td>').text(s.facility),
                $('<td>').text(s.status),
                chips
            ));
        });
        table.append(body);
        wrap.append($('<div class="table-responsive">').append(table));
        if ($.fn.dataTable) {
            table.dataTable({
                aaSorting: [[2, 'desc']],
                iDisplayLength: 10,
                bAutoWidth: false
            });
        }
        return wrap;
    }

    function exportSamples(params) {
        if ($.blockUI) {
            $.blockUI();
        }
        post($.extend({ action: 'export' }, params))
            .done(function (data) {
                if (data && data.download) {
                    window.open('/download.php?f=' + encodeURIComponent(data.download), '_blank');
                } else {
                    alert(L.exportFailed);
                }
            })
            .fail(function () {
                alert(L.exportFailed);
            })
            .always(function () {
                if ($.unblockUI) {
                    $.unblockUI();
                }
            });
    }

    /* ------------------------------------------------------------- public */

    window.DataQuality = {
        init: function (target, options) {
            root = target;
            config = options;
            L = config.labels;
            try {
                numberLocale = config.locale ? Intl.NumberFormat.supportedLocalesOf([config.locale])[0] : undefined;
            } catch (e) {
                numberLocale = undefined;
            }
            initFilters();
            search();
        },
        search: function () {
            if (root) {
                search();
            }
        }
    };
}(jQuery));
