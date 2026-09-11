<?php

use App\Services\UsersService;
use App\Registries\ContainerRegistry;

$title = _translate("Page Usage") . " - " . _translate("Admin");
require_once APPLICATION_PATH . '/header.php';

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);
$userNameList = $usersService->getAllUsers(null, null, 'drop-down');
?>
<style>
    #pageUsage .pu-summary {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin: 0 0 18px;
    }

    #pageUsage .pu-card {
        flex: 1 1 150px;
        padding: 12px 15px;
        background-color: #f8fafb;
        border: 1px solid #e4e8ec;
        border-left: 3px solid #3c8dbc;
        border-radius: 3px;
    }

    #pageUsage .pu-card-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #8a9299;
    }

    #pageUsage .pu-card-value {
        font-size: 22px;
        font-weight: 700;
        color: #444;
        line-height: 1.3;
        font-variant-numeric: tabular-nums;
    }

    #pageUsage .pu-note {
        font-size: 12px;
        color: #8a9299;
        margin: 0 0 12px;
    }

    #pageUsage .pu-filters {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin: 0 0 12px;
    }

    #pageUsage .pu-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 3px 4px 3px 10px;
        font-size: 12px;
        background-color: #eaf3fa;
        border: 1px solid #c9dfee;
        border-radius: 12px;
        color: #2a6d96;
    }

    #pageUsage .pu-chip a {
        color: #2a6d96;
    }

    #pageUsage .pu-chip button {
        border: 0;
        background: transparent;
        color: #2a6d96;
        padding: 0 6px;
        line-height: 1;
        font-size: 14px;
        cursor: pointer;
    }

    #pageUsage .pu-panel-title {
        font-size: 15px;
        font-weight: 600;
        color: #444;
        margin: 0 0 8px;
    }

    #pageUsage .pu-rank {
        list-style: none;
        margin: 0 0 18px;
        padding: 0;
    }

    #pageUsage .pu-rank li {
        padding: 7px 8px;
        border-bottom: 1px solid #eef1f4;
        cursor: pointer;
    }

    #pageUsage .pu-rank li:hover {
        background-color: #f4f8fb;
    }

    #pageUsage .pu-rank li.is-active {
        background-color: #eaf3fa;
    }

    #pageUsage .pu-rank-head {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        align-items: baseline;
    }

    #pageUsage .pu-rank-name {
        font-weight: 600;
        color: #444;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    #pageUsage .pu-rank-time {
        font-weight: 600;
        color: #444;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
    }

    #pageUsage .pu-rank-meta {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        font-size: 11.5px;
        color: #8a9299;
    }

    #pageUsage .pu-bar {
        height: 4px;
        margin: 4px 0 3px;
        background-color: #eef1f4;
        border-radius: 2px;
        overflow: hidden;
    }

    #pageUsage .pu-bar span {
        display: block;
        height: 100%;
        background-color: #3c8dbc;
    }

    #pageUsage .pu-module {
        display: inline-block;
        padding: 1px 7px;
        font-size: 11px;
        font-weight: 400;
        border-radius: 10px;
        background-color: #eef1f4;
        color: #5a6570;
        white-space: nowrap;
    }

    #pageUsage table.pu-table th {
        background-color: #f4f6f8;
        white-space: nowrap;
    }

    #pageUsage table.pu-table td.num,
    #pageUsage table.pu-table th.num {
        text-align: right;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
    }

    #pageUsage .pu-sub {
        display: block;
        font-size: 11.5px;
        color: #8a9299;
    }

    #pageUsage .pu-session {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 1px 7px;
        font-family: SFMono-Regular, Menlo, Consolas, monospace;
        font-size: 11px;
        border: 1px solid #d5dce2;
        border-radius: 10px;
        color: #5a6570;
        background-color: #fff;
        cursor: pointer;
    }

    #pageUsage .pu-session:hover {
        border-color: #3c8dbc;
        color: #2a6d96;
    }

    #pageUsage .pu-empty {
        padding: 30px 10px;
        text-align: center;
        color: #8a9299;
    }

    #puProgress {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 3px;
        z-index: 2000;
        overflow: hidden;
        display: none;
        pointer-events: none;
        background-color: #dfe6ec;
    }

    #puProgress.is-active {
        display: block;
    }

    #puProgress span {
        display: block;
        width: 35%;
        height: 100%;
        background-color: #3c8dbc;
        animation: puProgressSlide 1.15s ease-in-out infinite;
    }

    @keyframes puProgressSlide {
        0% { margin-left: -35%; }
        100% { margin-left: 100%; }
    }

    @media (prefers-reduced-motion: reduce) {
        #puProgress span {
            width: 100%;
            animation: none;
        }
    }
</style>
<div class="content-wrapper" id="pageUsage">
    <div id="puProgress" aria-hidden="true"><span></span></div>
    <section class="content-header">
        <h1><em class="fa-solid fa-stopwatch"></em> <?= _htmlTranslate("Page Usage"); ?></h1>
        <ol class="breadcrumb">
            <li><a href="/"><em class="fa-solid fa-chart-pie"></em> <?= _htmlTranslate("Home"); ?></a></li>
            <li class="active"><?= _htmlTranslate("Page Usage"); ?></li>
        </ol>
    </section>
    <section class="content">
        <div class="box box-primary">
            <div class="box-body">
                <div class="row">
                    <div class="col-md-4 form-group">
                        <label for="dateRange"><?= _htmlTranslate("Date Range"); ?></label>
                        <input type="text" class="form-control" id="dateRange" readonly />
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="userId"><?= _htmlTranslate("User"); ?></label>
                        <select class="form-control" id="userId">
                            <option value=""><?= _htmlTranslate("All users"); ?></option>
                            <?php foreach ($userNameList as $userId => $userName) { ?>
                                <option value="<?= htmlspecialchars((string) $userId, ENT_QUOTES, 'UTF-8'); ?>"><?= htmlspecialchars((string) $userName, ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-4 form-group">
                        <label for="search"><?= _htmlTranslate("Search"); ?></label>
                        <input type="search" class="form-control" id="search" placeholder="<?= _htmlTranslate('User or page'); ?>" autocomplete="off" />
                    </div>
                </div>

                <p class="pu-note">
                    <?= _htmlTranslate("Time counts only while the page is the tab in front of the user. It is not the length of the login session."); ?>
                </p>

                <div class="pu-filters" id="puFilters"></div>

                <div class="pu-summary">
                    <div class="pu-card">
                        <div class="pu-card-label"><?= _htmlTranslate("Users"); ?></div>
                        <div class="pu-card-value" id="puUsers">--</div>
                    </div>
                    <div class="pu-card">
                        <div class="pu-card-label"><?= _htmlTranslate("Sessions"); ?></div>
                        <div class="pu-card-value" id="puSessions">--</div>
                    </div>
                    <div class="pu-card">
                        <div class="pu-card-label"><?= _htmlTranslate("Pages Used"); ?></div>
                        <div class="pu-card-value" id="puPages">--</div>
                    </div>
                    <div class="pu-card">
                        <div class="pu-card-label"><?= _htmlTranslate("Page Opens"); ?></div>
                        <div class="pu-card-value" id="puVisits">--</div>
                    </div>
                    <div class="pu-card">
                        <div class="pu-card-label"><?= _htmlTranslate("Time on Pages"); ?></div>
                        <div class="pu-card-value" id="puTime">--</div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <h4 class="pu-panel-title"><?= _htmlTranslate("Most Used Pages"); ?></h4>
                        <ul class="pu-rank" id="puTopPages"></ul>
                    </div>
                    <div class="col-md-6">
                        <h4 class="pu-panel-title"><?= _htmlTranslate("Most Active Users"); ?></h4>
                        <ul class="pu-rank" id="puTopUsers"></ul>
                    </div>
                </div>

                <h4 class="pu-panel-title"><?= _htmlTranslate("By User and Page"); ?></h4>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped pu-table">
                        <thead>
                            <tr>
                                <th><?= _htmlTranslate("User"); ?></th>
                                <th><?= _htmlTranslate("Page"); ?></th>
                                <th><?= _htmlTranslate("Session"); ?></th>
                                <th class="num"><?= _htmlTranslate("Page Opens"); ?></th>
                                <th class="num"><?= _htmlTranslate("Time Spent"); ?></th>
                                <th class="num"><?= _htmlTranslate("Average Per Open"); ?></th>
                                <th><?= _htmlTranslate("Last Seen"); ?></th>
                            </tr>
                        </thead>
                        <tbody id="puRows"></tbody>
                    </table>
                </div>
                <p class="pu-note" id="puTruncated" hidden><?= _htmlTranslate("Showing the 500 rows with the most time. Narrow the filters to see the rest."); ?></p>
            </div>
        </div>
    </section>
</div>
<script src="/assets/js/moment.min.js"></script>
<script type="text/javascript" src="<?= _asset('/assets/plugins/daterangepicker/daterangepicker.js') ?>"></script>
<script type="text/javascript">
    (function () {
        var T = {
            opens: '<?= _jsTranslate("opens"); ?>',
            users: '<?= _jsTranslate("users"); ?>',
            pages: '<?= _jsTranslate("pages"); ?>',
            sessions: '<?= _jsTranslate("sessions"); ?>',
            page: '<?= _jsTranslate("Page"); ?>',
            user: '<?= _jsTranslate("User"); ?>',
            session: '<?= _jsTranslate("Session"); ?>',
            clear: '<?= _jsTranslate("Remove filter"); ?>',
            activityLog: '<?= _jsTranslate("Open in Activity Log"); ?>',
            filterSession: '<?= _jsTranslate("Show only this session"); ?>',
            noData: '<?= _jsTranslate("No page usage recorded for these filters."); ?>',
            failed: '<?= _jsTranslate("Page usage could not be loaded. Please try again."); ?>',
            today: '<?= _jsTranslate("Today"); ?>',
            last7: '<?= _jsTranslate("Last 7 Days"); ?>',
            last30: '<?= _jsTranslate("Last 30 Days"); ?>',
            thisMonth: '<?= _jsTranslate("This Month"); ?>',
            last90: '<?= _jsTranslate("Last 90 Days"); ?>'
        };

        var state = { dateRange: '', userId: '', pageUrl: '', sessionHash: '', search: '' };
        // Names for the filter chips, learned from the rows that set them.
        var labels = { pageUrl: '', userId: '' };
        var pending = null;

        function esc(value) {
            return $('<div/>').text(value == null ? '' : String(value)).html();
        }

        function num(value) {
            return (Number(value) || 0).toLocaleString();
        }

        function bar(seconds, max) {
            var pct = max > 0 ? Math.max(2, Math.round((seconds / max) * 100)) : 0;
            return '<div class="pu-bar"><span style="width:' + pct + '%"></span></div>';
        }

        function renderFilters() {
            var html = '';
            if (state.userId) {
                html += '<span class="pu-chip">' + esc(T.user) + ': <strong>' + esc(labels.userId || $('#userId option:selected').text()) + '</strong>' +
                    '<button type="button" data-clear="userId" aria-label="' + esc(T.clear) + '">&times;</button></span>';
            }
            if (state.pageUrl) {
                html += '<span class="pu-chip">' + esc(T.page) + ': <strong>' + esc(labels.pageUrl || state.pageUrl) + '</strong>' +
                    '<button type="button" data-clear="pageUrl" aria-label="' + esc(T.clear) + '">&times;</button></span>';
            }
            if (state.sessionHash) {
                html += '<span class="pu-chip"><em class="fa-solid fa-fingerprint"></em>' + esc(T.session) + ': <strong>' + esc(state.sessionHash.substring(0, 8)) + '</strong>' +
                    ' &middot; <a href="/admin/monitoring/activity-log.php?sessionHash=' + encodeURIComponent(state.sessionHash) + '">' + esc(T.activityLog) + '</a>' +
                    '<button type="button" data-clear="sessionHash" aria-label="' + esc(T.clear) + '">&times;</button></span>';
            }
            $('#puFilters').html(html);
        }

        function renderTopPages(pages) {
            if (!pages.length) {
                $('#puTopPages').html('<li class="pu-empty">' + esc(T.noData) + '</li>');
                return;
            }
            var max = pages[0].seconds;
            $('#puTopPages').html(pages.map(function (p) {
                return '<li data-page="' + esc(p.pageUrl) + '" data-name="' + esc(p.pageName) + '" title="' + esc(p.pageUrl) + '"' +
                    (state.pageUrl === p.pageUrl ? ' class="is-active"' : '') + '>' +
                    '<div class="pu-rank-head"><span class="pu-rank-name">' + esc(p.pageName) + ' <span class="pu-module">' + esc(p.module) + '</span></span>' +
                    '<span class="pu-rank-time">' + esc(p.timeSpent) + '</span></div>' +
                    bar(p.seconds, max) +
                    '<div class="pu-rank-meta"><span>' + num(p.visits) + ' ' + esc(T.opens) + '</span><span>' + num(p.users) + ' ' + esc(T.users) + '</span></div>' +
                    '</li>';
            }).join(''));
        }

        function renderTopUsers(users) {
            if (!users.length) {
                $('#puTopUsers').html('<li class="pu-empty">' + esc(T.noData) + '</li>');
                return;
            }
            var max = users[0].seconds;
            $('#puTopUsers').html(users.map(function (u) {
                return '<li data-user="' + esc(u.userId) + '" data-name="' + esc(u.userName) + '"' +
                    (state.userId === u.userId ? ' class="is-active"' : '') + '>' +
                    '<div class="pu-rank-head"><span class="pu-rank-name">' + esc(u.userName) + ' <span class="pu-module">' + esc(u.role) + '</span></span>' +
                    '<span class="pu-rank-time">' + esc(u.timeSpent) + '</span></div>' +
                    bar(u.seconds, max) +
                    '<div class="pu-rank-meta"><span>' + num(u.visits) + ' ' + esc(T.opens) + ' &middot; ' + num(u.pages) + ' ' + esc(T.pages) + '</span><span>' + esc(u.lastSeen) + '</span></div>' +
                    '</li>';
            }).join(''));
        }

        function renderRows(rows) {
            if (!rows.length) {
                $('#puRows').html('<tr><td colspan="7" class="pu-empty">' + esc(T.noData) + '</td></tr>');
                return;
            }
            $('#puRows').html(rows.map(function (r) {
                var session = '';
                if (r.lastSession) {
                    session = '<span class="pu-session" data-session="' + esc(r.lastSession) + '" title="' + esc(T.filterSession) + '">' +
                        '<em class="fa-solid fa-fingerprint"></em>' + esc(r.lastSession.substring(0, 8)) + '</span>';
                    if (r.sessions > 1) {
                        session += '<span class="pu-sub">' + num(r.sessions) + ' ' + esc(T.sessions) + '</span>';
                    }
                }
                return '<tr>' +
                    '<td>' + esc(r.userName) + '<span class="pu-sub">' + esc(r.role) + '</span></td>' +
                    '<td title="' + esc(r.pageUrl) + '">' + esc(r.pageName) + ' <span class="pu-module">' + esc(r.module) + '</span></td>' +
                    '<td>' + session + '</td>' +
                    '<td class="num">' + num(r.visits) + '</td>' +
                    '<td class="num" title="' + esc(r.seconds) + '">' + esc(r.timeSpent) + '</td>' +
                    '<td class="num">' + esc(r.average) + '</td>' +
                    '<td>' + esc(r.lastSeen) + '</td>' +
                    '</tr>';
            }).join(''));
        }

        function load() {
            renderFilters();
            if (pending) {
                pending.abort();
            }
            $('#puProgress').addClass('is-active');
            pending = $.post('/admin/monitoring/get-page-usage.php', state, null, 'json')
                .done(function (data) {
                    var totals = (data && data.totals) || {};
                    $('#puUsers').text(num(totals.users));
                    $('#puSessions').text(num(totals.sessions));
                    $('#puPages').text(num(totals.pages));
                    $('#puVisits').text(num(totals.visits));
                    $('#puTime').text(totals.timeSpent || '-');
                    renderTopPages((data && data.pages) || []);
                    renderTopUsers((data && data.users) || []);
                    renderRows((data && data.rows) || []);
                    $('#puTruncated').prop('hidden', !(data && data.truncated));
                })
                .fail(function (xhr, status) {
                    if (status === 'abort') {
                        return;
                    }
                    $('#puRows').html('<tr><td colspan="7" class="pu-empty">' + esc(T.failed) + '</td></tr>');
                })
                .always(function (xhr, status) {
                    if (status !== 'abort') {
                        $('#puProgress').removeClass('is-active');
                        pending = null;
                    }
                });
        }

        $(document).ready(function () {
            var ranges = {};
            ranges[T.today] = [moment(), moment()];
            ranges[T.last7] = [moment().subtract(6, 'days'), moment()];
            ranges[T.last30] = [moment().subtract(29, 'days'), moment()];
            ranges[T.thisMonth] = [moment().startOf('month'), moment().endOf('month')];
            ranges[T.last90] = [moment().subtract(89, 'days'), moment()];

            $('#dateRange').daterangepicker({
                locale: { format: 'DD-MMM-YYYY' },
                showDropdowns: true,
                maxDate: moment(),
                startDate: moment().subtract(29, 'days'),
                endDate: moment(),
                ranges: ranges
            }, function (start, end) {
                state.dateRange = start.format('DD-MMM-YYYY') + ' to ' + end.format('DD-MMM-YYYY');
                $('#dateRange').val(state.dateRange);
                load();
            });
            state.dateRange = moment().subtract(29, 'days').format('DD-MMM-YYYY') + ' to ' + moment().format('DD-MMM-YYYY');
            $('#dateRange').val(state.dateRange);

            $('#userId').on('change', function () {
                state.userId = $(this).val() || '';
                labels.userId = '';
                load();
            });

            var searchTimer;
            $('#search').on('input', function () {
                var value = $(this).val();
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(function () {
                    state.search = value.trim();
                    load();
                }, 300);
            });

            // A ranked row toggles its own filter.
            $('#puTopPages').on('click', 'li[data-page]', function () {
                var page = String($(this).data('page'));
                state.pageUrl = state.pageUrl === page ? '' : page;
                labels.pageUrl = state.pageUrl ? String($(this).data('name')) : '';
                load();
            });
            $('#puTopUsers').on('click', 'li[data-user]', function () {
                var user = String($(this).data('user'));
                state.userId = state.userId === user ? '' : user;
                labels.userId = state.userId ? String($(this).data('name')) : '';
                $('#userId').val(state.userId);
                load();
            });
            $('#puRows').on('click', '.pu-session', function () {
                state.sessionHash = String($(this).data('session'));
                load();
            });
            $('#puFilters').on('click', 'button[data-clear]', function () {
                var key = $(this).data('clear');
                state[key] = '';
                if (key === 'userId') {
                    $('#userId').val('');
                }
                load();
            });

            load();
        });
    })();
</script>
<?php
require_once APPLICATION_PATH . '/footer.php';
