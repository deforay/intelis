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
 *      left the browser and nothing on screen said the page was working.
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

    function startLoading() {
        if (pending++ === 0) {
            $.blockUI();
        }
    }

    function endLoading() {
        pending = Math.max(0, pending - 1);
        if (pending === 0) {
            $.unblockUI();
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
        pane(paneId).find('.report-filter-box label.control-label').each(function () {
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
            var value = from.val();
            if (value === null || value === '' || ($.isArray(value) && value.length === 0)) {
                return;
            }
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
    }

    /* ------------------------------------------------------- filter summary */

    function controlLabel(el) {
        return $.trim(el.closest('.form-group').find('label.control-label').first().text());
    }

    function displayValue(el) {
        if (el.is('select')) {
            var texts = el.find('option:selected').map(function () {
                return $.trim($(this).text());
            }).get();
            return texts.join(', ');
        }
        return $.trim(el.val());
    }

    /* A filter counts as applied when it holds a value at all: the placeholder
       option is empty, so what is left is what the tab is actually filtered to.
       Comparing against the value the page opened with would hide the date
       range, which is the one filter always in force and worth showing. */
    function isApplied(el) {
        if (el.is(':disabled')) {
            return false;
        }
        var value = el.val();
        if (value === null || typeof value === 'undefined') {
            return false;
        }
        return $.isArray(value) ? value.length > 0 : $.trim(value) !== '';
    }

    function updateSummary(box) {
        var applied = [];
        box.find('select, input[type="text"], input[type="number"], textarea').each(function () {
            var el = $(this);
            if (isApplied(el)) {
                var label = controlLabel(el);
                var value = displayValue(el);
                if (label && value) {
                    applied.push(label + ': ' + value);
                }
            }
        });
        var summary = box.find('.report-filter-summary').first();
        if (applied.length === 0) {
            summary.text('').hide();
            return;
        }
        var shown = applied.slice(0, 2).join(' · ');
        if (applied.length > 2) {
            shown += ' · +' + (applied.length - 2);
        }
        summary.text(shown).attr('title', applied.join('\n')).show();
    }

    function updateSummaries(scope) {
        (scope || $(document)).find('.report-filter-box').each(function () {
            updateSummary($(this));
        });
    }

    function collapse(box) {
        if (!box.hasClass('collapsed-box')) {
            box.find('[data-widget="collapse"]').first().trigger('click');
        }
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

    /* -------------------------------------------------------------- public */

    var ClinicReports = {

        /* DataTables fnServerData that reports progress honestly and always
           lets the page go again, whatever the server does. */
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
            var box = pane(paneId).find('.report-filter-box').first();
            updateSummary(box);
            collapse(box);
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
            var previous = activePane();

            $('.report-filter-box').each(function () {
                var box = $(this);
                box.on('change', 'select, input, textarea', function () {
                    $(this).data('cr-touched', true);
                    updateSummary(box);
                });
                updateSummary(box);
            });

            /* The whole header is the hit area; the chevron keeps working. */
            initRangeSliders();

            /* The pregnancy and breastfeeding filters open enabled with no sex
               chosen and are only disabled once the sex select is touched, so
               the page starts out disagreeing with itself. Settle it on load. */
            $('select[onchange^="hideFemaleDetails"]').each(function () {
                if (typeof this.onchange === 'function') {
                    this.onchange();
                }
            });

            $(document).on('click', '.report-filter-header', function (event) {
                if ($(event.target).closest('.btn-box-tool').length) {
                    return;
                }
                $(this).find('[data-widget="collapse"]').first().trigger('click');
            });

            $('a[data-toggle="tab"]').on('shown.bs.tab', function () {
                var current = $($(this).attr('href')).attr('id');
                shareFilters(previous, current);
                previous = current;
                ensure(current);
            });

            ensure(activePane());
        }
    };

    window.ClinicReports = ClinicReports;
}(jQuery));
