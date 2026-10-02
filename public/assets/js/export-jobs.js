/*
 * Background Excel exports with a progress tray.
 *
 * IntelisExport.start(url, data, label) asks an export endpoint to run as a
 * background job (see ExportJobUtility). The job id is kept in localStorage, so
 * the tray reappears on every page the user opens next and keeps polling; when
 * the file is ready it downloads wherever the user happens to be. The server
 * hands each finished file out only once, so several open tabs do not all
 * download it.
 */
(function (window, $) {
    'use strict';

    var STORAGE_KEY = 'intelisExportJobs.' + (window.IntelisExportOwner || 'anonymous');
    var STATUS_URL = '/common/export-job-status.php';
    var POLL_MS = 2000;
    // Right after an export starts, look sooner: a small export is usually
    // ready within a second, and should not wait out a full POLL_MS.
    var FAST_POLL_MS = [300, 600, 1000];
    // Progress comes from a static file the web server serves without PHP. Only
    // when it has not changed for this long is the server asked, which also
    // lets it notice a worker that died.
    var QUIET_MS = 60000;
    // A download grant lasts 15 minutes; past that, "Download again" for an
    // export that ran in the request has nothing to use.
    var GRANT_REUSE_MS = 14 * 60000;
    // Finished exports clear themselves from the menu after this long.
    var FINISHED_KEEP_MS = 5 * 60000;
    var HIDDEN_POLL_MS = 5000;

    var T = $.extend({
        preparing: 'Preparing export...',
        rows: 'rows',
        ready: 'Download started',
        downloadAgain: 'Download again',
        failed: 'Unable to generate the excel file',
        noData: 'No data available to export. Please change the filters and search again.',
        dismiss: 'Dismiss',
        inProgress: 'An export is in progress. It will download automatically when done, even if you open another page.'
    }, window.IntelisExportStrings || {});

    var timer = null;
    var pruneTimer = null;
    var fastPolls = FAST_POLL_MS.length;

    // Finished one way or another: nothing left to poll for.
    function isFinished(job) {
        return job.status === 'done' || job.status === 'failed' || job.status === 'empty';
    }

    var polling = false;

    function readJobs() {
        try {
            var jobs = JSON.parse(window.localStorage.getItem(STORAGE_KEY) || '[]');
            return Array.isArray(jobs) ? jobs : [];
        } catch (e) {
            return [];
        }
    }

    function writeJobs(jobs) {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify(jobs));
        } catch (e) { /* tray just will not survive a page change */ }
    }

    function updateJob(id, changes) {
        var jobs = readJobs();
        for (var i = 0; i < jobs.length; i++) {
            if (jobs[i].id === id) {
                $.extend(jobs[i], changes);
                if (isFinished(jobs[i]) && !jobs[i].finishedAt) {
                    jobs[i].finishedAt = Date.now();
                }
            }
        }
        writeJobs(jobs);
    }

    // Running exports stay: clearing them would lose a download still on its way.
    function clearFinished() {
        writeJobs(readJobs().filter(function (job) { return !isFinished(job); }));
        render();
    }

    function removeJob(id) {
        writeJobs(readJobs().filter(function (job) { return job.id !== id; }));
        render();
    }

    function downloadUrl(token) {
        return '/download.php?d=a&f=' + encodeURIComponent(token);
    }

    // A hidden iframe downloads without leaving the page and without a popup,
    // which a browser would block outside a click.
    function download(token) {
        var frame = document.createElement('iframe');
        frame.style.display = 'none';
        frame.src = downloadUrl(token);
        document.body.appendChild(frame);
        window.setTimeout(function () { frame.remove(); }, 60000);
    }

    // The navbar menu from header.php when the page has one; otherwise a floating
    // tray, so pages without the standard header still show their exports.
    function inNavbar() {
        return $('#exportJobsList').length > 0;
    }

    function tray() {
        if (inNavbar()) {
            return $('#exportJobsList');
        }
        var $tray = $('#exportJobsTray');
        if (!$tray.length) {
            $tray = $('<div id="exportJobsTray" role="status" aria-live="polite"></div>').css({
                position: 'fixed',
                right: '16px',
                bottom: '16px',
                width: '320px',
                maxWidth: 'calc(100vw - 32px)',
                zIndex: 2000
            }).appendTo('body');
        }
        return $tray;
    }

    function formatNumber(n) {
        return Number(n || 0).toLocaleString();
    }

    // Drops finished cards older than FINISHED_KEEP_MS, and wakes up again for
    // the next one due, so the menu empties itself without a page change.
    function pruneFinished() {
        var now = Date.now();
        var jobs = readJobs();
        var next = null;
        var changed = false;
        var kept = jobs.filter(function (job) {
            if (!isFinished(job)) {
                return true;
            }
            if (!job.finishedAt) {
                job.finishedAt = now;
                changed = true;
            }
            var due = job.finishedAt + FINISHED_KEEP_MS;
            if (due <= now) {
                return false;
            }
            next = next === null ? due : Math.min(next, due);
            return true;
        });
        if (changed || kept.length !== jobs.length) {
            writeJobs(kept);
        }
        window.clearTimeout(pruneTimer);
        pruneTimer = next === null ? null : window.setTimeout(render, next - now + 50);
        return kept;
    }

    function render() {
        var jobs = pruneFinished();
        var $tray = tray();
        $tray.empty();

        if (inNavbar()) {
            var running = jobs.filter(function (job) {
                return !isFinished(job);
            }).length;
            var $menu = $('#exportJobsMenu').toggle(jobs.length > 0);
            if (!jobs.length) {
                $menu.removeClass('open');
            }
            $('#exportJobsCount').text(running || '').toggle(running > 0);
            $('#exportJobsClearAll').toggle(jobs.length > running);
            $('#exportJobsIcon').toggleClass('fa-fade', running > 0);
        } else {
            $tray.toggle(jobs.length > 0);
        }

        jobs.forEach(function (job) {
            var percent = 0;
            var detail = T.preparing;
            var barClass = 'progress-bar progress-bar-striped active';

            if (job.total && job.processed <= job.total) {
                // Held below 100% until the file is actually written.
                percent = Math.min(99, Math.floor((job.processed / job.total) * 100));
                detail = formatNumber(job.processed) + ' / ' + formatNumber(job.total) + ' ' + T.rows + ' (' + percent + '%)';
            } else if (job.processed) {
                // Rows were added since the listing was counted; show what is known.
                percent = 99;
                detail = formatNumber(job.processed) + ' ' + T.rows;
            }
            if (job.status === 'done') {
                percent = 100;
                detail = T.ready;
                barClass = 'progress-bar progress-bar-success';
            } else if (job.status === 'empty') {
                percent = 100;
                detail = job.error || T.noData;
                barClass = 'progress-bar progress-bar-warning';
            } else if (job.status === 'failed') {
                percent = 100;
                detail = job.error || T.failed;
                barClass = 'progress-bar progress-bar-danger';
            }

            var $card = inNavbar()
                ? $('<div style="border-bottom:1px solid #f4f4f4;"></div>')
                : $('<div class="box box-solid" style="margin-bottom:8px;box-shadow:0 2px 8px rgba(0,0,0,.25);"></div>');
            var $body = $('<div class="box-body" style="padding:10px 12px;"></div>').appendTo($card);
            var $title = $('<div style="font-weight:bold;margin-bottom:6px;"></div>')
                .append($('<em class="fa-solid fa-file-excel" style="margin-right:6px;"></em>'))
                .append(document.createTextNode(job.label))
                .appendTo($body);

            if (isFinished(job)) {
                $('<a href="javascript:void(0);" class="pull-right text-muted" style="font-weight:normal;">&times;</a>')
                    .attr('title', T.dismiss)
                    .on('click', function () { removeJob(job.id); })
                    .appendTo($title);
            }

            if (job.status === 'empty') {
                // Not an error and nothing in progress: just say plainly why there is no file.
                $('<div style="font-size:13px;color:#a94442;"></div>')
                    .append($('<em class="fa-solid fa-circle-info" style="margin-right:6px;"></em>'))
                    .append(document.createTextNode(detail))
                    .appendTo($body);
                $tray.append($card);
                return;
            }

            $('<div class="progress" style="margin-bottom:4px;height:10px;"></div>')
                .append($('<div role="progressbar"></div>').attr('class', barClass).css('width', percent + '%')
                    .attr({ 'aria-valuenow': percent, 'aria-valuemin': 0, 'aria-valuemax': 100 }))
                .appendTo($body);

            var $detail = $('<small></small>').text(detail).appendTo($body);
            if (job.status === 'done' && (!job.token || Date.now() - job.tokenAt < GRANT_REUSE_MS)) {
                $detail.append(' &middot; ').append(
                    $('<a href="javascript:void(0);"></a>').text(T.downloadAgain)
                        .on('click', function () { downloadAgain(job.id); })
                );
            }

            $tray.append($card);
        });
    }

    // The grant from the first download is short-lived, so fetch a fresh one,
    // unless the export ran in the request and its own grant is still good.
    function downloadAgain(id) {
        var job = readJobs().filter(function (j) { return j.id === id; })[0];
        if (job && job.token) {
            // An export that ran in the request has only its own grant; once
            // that has expired, redrawing drops the link instead.
            if (Date.now() - job.tokenAt < GRANT_REUSE_MS) {
                download(job.token);
            } else {
                render();
            }
            return;
        }
        $.ajax({
            url: STATUS_URL,
            data: { id: id, again: 1 },
            dataType: 'json',
            cache: false
        }).done(function (status) {
            if (status.token) {
                download(status.token);
            } else {
                updateJob(id, { status: 'failed', error: status.error || T.failed });
                render();
            }
        }).fail(function () {
            updateJob(id, { status: 'failed', error: T.failed });
            render();
        });
    }

    // Asks PHP: to claim a finished file (handed out once across tabs), or to
    // check on a job whose static progress has gone quiet.
    function askServer(job) {
        return $.ajax({
            url: STATUS_URL,
            data: { id: job.id, claim: 1 },
            dataType: 'json',
            cache: false
        }).done(function (status) {
            if (status.status === 'claimed') {
                // Another open tab already downloaded it; the card stays until dismissed.
                updateJob(job.id, { status: 'done' });
                return;
            }
            updateJob(job.id, {
                status: status.status,
                processed: status.processed,
                total: status.total,
                error: status.error,
                seenAt: Date.now()
            });
            if (status.status === 'done' && status.token) {
                download(status.token);
            }
        }).fail(function (xhr) {
            if (xhr.status === 404) {
                // Expired, swept, or started by another user on this browser.
                removeJob(job.id);
            }
        });
    }

    function pollJob(job) {
        if (isFinished(job)) {
            return $.Deferred().resolve().promise();
        }
        if (!job.statusUrl) {
            return askServer(job);
        }
        return $.ajax({ url: job.statusUrl, dataType: 'json', cache: false }).then(function (status) {
            if (status.status === 'done') {
                return askServer(job);
            }
            var now = Date.now();
            var changed = status.updatedAt !== job.updatedAt;
            var seenAt = changed ? now : (job.seenAt || now);
            updateJob(job.id, {
                status: status.status,
                processed: status.processed,
                total: status.total,
                error: status.error,
                updatedAt: status.updatedAt,
                seenAt: seenAt
            });
            if (!isFinished(status) && now - seenAt > QUIET_MS) {
                return askServer(job);
            }
        }, function () {
            // No static copy (yet, or any more): the server knows.
            return askServer(job);
        });
    }

    function poll() {
        timer = null;
        var active = readJobs().filter(function (job) {
            return !isFinished(job);
        });
        if (!active.length || polling) {
            render();
            return;
        }
        polling = true;
        $.when.apply($, active.map(pollJob)).always(function () {
            polling = false;
            render();
            schedule();
        });
    }

    function schedule() {
        if (timer !== null) {
            return;
        }
        var hasActive = readJobs().some(function (job) {
            return !isFinished(job);
        });
        if (hasActive) {
            var delay = document.hidden ? HIDDEN_POLL_MS : POLL_MS;
            if (fastPolls < FAST_POLL_MS.length) {
                delay = FAST_POLL_MS[fastPolls++];
            }
            timer = window.setTimeout(poll, delay);
        }
    }

    function start(url, data, label) {
        return $.post(url, $.extend({}, data, { async: 'yes' }), null, 'json')
            .done(function (response) {
                var jobs = readJobs();
                var now = Date.now();
                if (response && response.token) {
                    // A small export ran in the request itself: done already, same card.
                    jobs.push({
                        id: 'inline-' + now,
                        label: label,
                        status: 'done',
                        token: response.token,
                        tokenAt: now,
                        finishedAt: now
                    });
                    download(response.token);
                } else if (response && response.empty) {
                    // Nothing to export: a card with the reason, under a local-only id.
                    jobs.push({
                        id: 'empty-' + now,
                        label: label,
                        status: 'empty',
                        error: response.error || T.noData,
                        finishedAt: now
                    });
                } else if (!response || !response.jobId) {
                    alert((response && response.error) || T.failed);
                    return;
                } else if (!jobs.some(function (job) { return job.id === response.jobId; })) {
                    // The server hands back the job already running for this export
                    // rather than starting a second one; it already has its card.
                    jobs.push({
                        id: response.jobId,
                        statusUrl: response.statusUrl,
                        label: label,
                        status: 'queued',
                        processed: 0,
                        total: null,
                        seenAt: now
                    });
                }
                writeJobs(jobs);
                render();
                // Start over on the quick checks, even if a slower one for an
                // earlier export is already pending.
                if (timer !== null) {
                    window.clearTimeout(timer);
                    timer = null;
                }
                fastPolls = 0;
                schedule();
                // Open the navbar menu so the user sees the export has started.
                $('#exportJobsMenu').addClass('open');
            })
            .fail(function () {
                alert(T.failed);
            });
    }

    // Keep tabs in step when another tab starts, finishes or dismisses a job.
    window.addEventListener('storage', function (e) {
        if (e.key === STORAGE_KEY) {
            render();
            schedule();
        }
    });

    // Clicks inside the list (dismiss, download again) must not close the menu.
    $(document).on('click', '#exportJobsList', function (e) {
        e.stopPropagation();
    });

    $(document).on('click', '#exportJobsClearAll', function (e) {
        e.preventDefault();
        e.stopPropagation();
        clearFinished();
    });

    $(function () {
        render();
        poll();
    });

    window.IntelisExport = { start: start, strings: T };
})(window, jQuery);
