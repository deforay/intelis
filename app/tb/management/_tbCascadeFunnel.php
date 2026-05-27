<?php

/**
 * Reusable TB cascade funnel (main cascade + referral branch).
 *
 * Usage on any page:
 *   1) require this partial once — it registers the CSS + JS helpers.
 *   2) call tbCascadeFunnelMarkup('somePrefix') wherever the block should render.
 *   3) populate it client-side:
 *        tbcLoadCascade('somePrefix', '/tb/management/getTbCascadeReport.php', filters)
 *      or, if you already have the summary payload in hand:
 *        tbcRenderCascade('somePrefix', summaryObject)
 *
 * The prefix keeps element IDs unique so several instances can coexist on a page.
 * CSS/JS are emitted only once per request regardless of how many times the
 * markup helper is called.
 */

if (!function_exists('tbCascadeFunnelMarkup')) {
    function tbCascadeFunnelMarkup(string $prefix): void
    {
        ?>
        <div class="tbc-funnel" id="<?= $prefix; ?>_cascadeFunnel">
            <div class="tbc-empty-panel"><?= _translate("Loading…"); ?></div>
        </div>

        <div class="tbc-subfunnel" id="<?= $prefix; ?>_referralBranchWrap">
            <div class="tbc-subfunnel-header">
                <em class="fa-solid fa-code-branch"></em>
                <?= _translate("Referral branch — splits off from Tested"); ?>
                &nbsp;·&nbsp;
                <span id="<?= $prefix; ?>_referralBranchSummary" style="font-weight:400; color:#666;">
                    <?= _translate("checking referral status…"); ?>
                </span>
            </div>
            <div class="tbc-funnel" id="<?= $prefix; ?>_referralBranchFunnel"></div>
            <div id="<?= $prefix; ?>_referralBranchNote" style="display:none; margin-top:6px; font-size:11px; color:#999; text-align:center;">
                <em class="fa-solid fa-circle-info"></em>
                <?= _translate("No referrals recorded in the selected period."); ?>
            </div>
        </div>
        <?php
    }
}

// CSS + JS — emit only once per request, even if the markup helper runs twice.
if (empty($GLOBALS['__tbCascadeFunnelAssetsEmitted'])) {
    $GLOBALS['__tbCascadeFunnelAssetsEmitted'] = true;
    ?>
    <style>
        .tbc-funnel {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: stretch;
            padding: 12px 4px;
        }

        .tbc-funnel-step {
            flex: 1 1 0;
            min-width: 110px;
            background: #f4f6f9;
            border: 1px solid #e1e5eb;
            border-radius: 4px;
            padding: 10px 8px;
            text-align: center;
            position: relative;
        }

        .tbc-funnel-step .tbc-funnel-stage {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: .3px;
            margin-bottom: 4px;
        }

        .tbc-funnel-step .tbc-funnel-count {
            font-size: 22px;
            font-weight: 600;
            color: #222;
        }

        .tbc-funnel-step .tbc-funnel-pct {
            font-size: 11px;
            color: #999;
            margin-top: 2px;
        }

        .tbc-funnel-arrow {
            display: flex;
            align-items: center;
            color: #ccc;
            font-size: 18px;
        }

        .tbc-empty-panel {
            padding: 24px;
            background: #fafbfc;
            border: 1px dashed #d0d6de;
            border-radius: 4px;
            text-align: center;
            color: #888;
            font-size: 13px;
        }

        /* Branch-point marker — visually highlights the funnel stage referrals split off from. */
        .tbc-funnel-step.tbc-branch-origin {
            border-color: #3c8dbc;
            box-shadow: 0 2px 0 #3c8dbc;
        }
        .tbc-funnel-step.tbc-branch-origin::after {
            content: "";
            position: absolute;
            bottom: -14px;
            left: 50%;
            margin-left: -7px;
            width: 0;
            height: 0;
            border-left: 7px solid transparent;
            border-right: 7px solid transparent;
            border-top: 10px solid #3c8dbc;
        }
        .tbc-funnel-step .tbc-branch-badge {
            position: absolute;
            top: -8px;
            right: 4px;
            background: #3c8dbc;
            color: #fff;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
            white-space: nowrap;
        }

        /* Sub-funnel container — visually indented so it reads as a tributary off the main cascade. */
        .tbc-subfunnel {
            position: relative;
            margin: 22px 0 4px 12px;
            padding: 12px 12px 8px 18px;
            background: #f8fbfd;
            border-left: 4px solid #3c8dbc;
            border-radius: 0 4px 4px 0;
        }
        .tbc-subfunnel-header {
            font-size: 12px;
            color: #3c8dbc;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .tbc-subfunnel .tbc-funnel {
            padding: 0;
        }
    </style>
    <script>
        // Generic funnel renderer — takes a container element id and an ordered
        // list of {label, count} stages. Draws drop-off % relative to the previous
        // stage and an optional branch marker on a named stage.
        function tbcRenderFunnel(containerId, stages, opts) {
            opts = opts || {};
            var $c = $('#' + containerId).empty();
            if (!stages || !stages.length) {
                $c.append('<div class="tbc-empty-panel"><?= _jsTranslate('No data in selected period.'); ?></div>');
                return;
            }
            var prevCount = null;
            stages.forEach(function (s, i) {
                var pct = '';
                if (prevCount !== null && prevCount > 0) {
                    pct = Math.round((s.count / prevCount) * 100) + '% <?= _jsTranslate("of previous stage"); ?>';
                }
                var $step = $('<div class="tbc-funnel-step"></div>');
                if (opts.branchAt && opts.branchAt === s.label) {
                    $step.addClass('tbc-branch-origin');
                    if (opts.branchBadge) {
                        $step.append('<span class="tbc-branch-badge"><em class="fa-solid fa-code-branch"></em> ' + opts.branchBadge + '</span>');
                    }
                }
                $step.append('<div class="tbc-funnel-stage">' + s.label + '</div>');
                $step.append('<div class="tbc-funnel-count">' + (s.count || 0).toLocaleString() + '</div>');
                if (pct) { $step.append('<div class="tbc-funnel-pct">' + pct + '</div>'); }
                $c.append($step);
                if (i < stages.length - 1) {
                    $c.append('<div class="tbc-funnel-arrow"><em class="fa-solid fa-chevron-right"></em></div>');
                }
                prevCount = s.count;
            });
        }

        // Render the main cascade funnel + referral branch for one prefixed instance
        // from a summary payload (the JSON returned by getTbCascadeReport action=summary).
        function tbcRenderCascade(prefix, s) {
            s = s || {};
            var dispatched = Math.max(s.printed || 0, s.dispatched || 0);

            tbcRenderFunnel(prefix + '_cascadeFunnel', [
                { label: "<?= _jsTranslate('Registered'); ?>", count: s.total },
                { label: "<?= _jsTranslate('Received at Lab'); ?>", count: (s.total || 0) - (s.atCollectionSite || 0) },
                { label: "<?= _jsTranslate('Tested'); ?>", count: s.tested },
                { label: "<?= _jsTranslate('Final Result Entered'); ?>", count: s.resultEntered },
                { label: "<?= _jsTranslate('Accepted'); ?>", count: s.accepted },
                { label: "<?= _jsTranslate('Dispatched / Printed'); ?>", count: dispatched }
            ], {
                branchAt: "<?= _jsTranslate('Tested'); ?>",
                branchBadge: (s.referred || 0) + " <?= _jsTranslate('referred'); ?>"
            });

            var allZero = (!s.referred && !s.referralReceived && !s.referralTested && !s.referralAccepted);
            var pctOfTested = (s.tested && s.tested > 0) ? Math.round((s.referred / s.tested) * 100) : 0;
            var summary;
            if ((s.referred || 0) === 0) {
                summary = "<?= _jsTranslate('0 samples referred onward.'); ?>";
            } else {
                summary = (s.referred || 0).toLocaleString()
                        + " (" + pctOfTested + "% <?= _jsTranslate('of tested'); ?>) "
                        + "<?= _jsTranslate('went to another lab'); ?>";
            }
            $('#' + prefix + '_referralBranchSummary').text(summary);

            tbcRenderFunnel(prefix + '_referralBranchFunnel', [
                { label: "<?= _jsTranslate('Referred'); ?>", count: s.referred || 0 },
                { label: "<?= _jsTranslate('Received at Target'); ?>", count: s.referralReceived || 0 },
                { label: "<?= _jsTranslate('Tested at Target'); ?>", count: s.referralTested || 0 },
                { label: "<?= _jsTranslate('Accepted at Target'); ?>", count: s.referralAccepted || 0 }
            ]);
            $('#' + prefix + '_referralBranchNote').toggle(allZero);
        }

        // Fetch the summary payload for the given filters and render the cascade.
        // filters: plain object of the report filter fields (sampleCollectionDate,
        // provinceId, labName, facilityName, testType, finalized) — any subset.
        function tbcLoadCascade(prefix, endpoint, filters) {
            return $.post(endpoint, $.extend({ action: "summary" }, filters || {}), function (data) {
                if (!data) return;
                var s = (typeof data === 'string') ? JSON.parse(data) : data;
                if (s && s.error) { return; }
                tbcRenderCascade(prefix, s);
            });
        }
    </script>
    <?php
}
