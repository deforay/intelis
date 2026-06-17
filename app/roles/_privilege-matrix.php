<?php

/**
 * Shared privilege matrix for role add/edit (addRole.php, editRole.php).
 *
 * Single-page ACCORDION layout: one collapsible panel per module, each holding
 * its resources and their Yes/No privilege switches. Replaces the old tabbed
 * grid (which buried privileges across tabs and made it hard to see the whole
 * picture at once).
 *
 * Two independent visibility layers, each driven by a CSS class so they never
 * clobber each other:
 *   .mode-hidden   -> applied by applyAccessTypeFilter() to privileges whose
 *                     show_mode is not valid for the selected Access Type.
 *                     Those cells are also forced to "deny" so they can never be
 *                     saved (the server re-enforces this in add/editRolesHelper;
 *                     the JS is UX only).
 *   .search-hidden -> applied by searchPermissions() to privileges / resources /
 *                     modules that don't match the search box.
 * A cell shows only when it carries NEITHER class (see CSS below). Decoupling
 * them is what fixes the old bug where searching un-hid cross-mode cells and the
 * access filter wiped search results.
 *
 * show_mode legend: lis = testing-lab fn, sts = collection-site fn, always = both.
 *
 * Expected in scope (set by the including page before require):
 *   $rInfo        array  module/resource rows (the matrix source)
 *   $db                  DatabaseService
 *   $isSuperAdmin bool   role 1 -> all granted + deny locked (default false)
 *   $priId        int[]  privilege ids already granted (edit); [] for add
 */

$priId = $priId ?? [];
$isSuperAdmin = $isSuperAdmin ?? false;
?>
<style>
    .privilege-accordion {
        margin-bottom: 10px;
    }

    .privilege-module-panel {
        border: 1px solid #d2d6de;
        border-radius: 3px;
        margin-bottom: 8px;
        background: #fff;
    }

    .privilege-module-panel .module-heading {
        cursor: pointer;
        padding: 12px 16px;
        font-size: 1.3em;
        font-weight: bold;
        color: #fff;
        background-color: #3c8dbc;
        border-radius: 3px 3px 0 0;
        text-transform: uppercase;
        user-select: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .privilege-module-panel.collapsed .module-heading {
        border-radius: 3px;
    }

    .privilege-module-panel .module-heading .module-count {
        font-size: 0.65em;
        font-weight: normal;
        opacity: 0.9;
    }

    .privilege-module-panel .module-heading .chevron {
        transition: transform 0.15s ease-in-out;
    }

    .privilege-module-panel.collapsed .module-heading .chevron {
        transform: rotate(-90deg);
    }

    .privilege-module-panel.collapsed .module-body {
        display: none;
    }

    .privilege-module-panel .module-body {
        padding: 6px 16px 16px;
    }

    .resource-block {
        border-bottom: 1px solid #f0f0f0;
        padding: 10px 0 6px;
    }

    .resource-block:last-child {
        border-bottom: 0;
    }

    .resource-block .resource-heading {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        margin-bottom: 8px;
    }

    .resource-block .resource-heading h4 {
        font-weight: bold;
        margin: 0;
        font-size: 15px;
        color: #2c3e50;
    }

    .privilege-div.mode-hidden,
    .privilege-div.search-hidden,
    .resource-block.search-hidden,
    .privilege-module-panel.search-hidden {
        display: none !important;
    }

    .privilege-grid {
        display: flex;
        flex-wrap: wrap;
    }

    /* Compact one-line-per-privilege rows: name on the left (wraps for long
       names), Yes/No toggle pinned to the right. Packs 2-3 per row to use
       horizontal space instead of stacking and wasting vertical space. */
    .privilege-div {
        padding: 4px 6px;
    }

    .privilege-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 6px 10px;
        min-height: 38px;
        border: 1px solid #ececec;
        border-radius: 4px;
        background: #fafafa;
    }

    .privilege-row .privilege-label {
        flex: 1 1 auto;
        text-align: left;
        font-weight: 600;
        font-size: 13px;
        line-height: 1.25;
        word-break: break-word;
    }

    .privilege-row .privilege-label.highlight {
        background-color: #fff3a0;
        border-radius: 2px;
    }

    .privilege-row .privilege-switch {
        flex: 0 0 auto;
        margin: 0;
    }

    /* Tighter Yes/No buttons than the page default (8px 16px). */
    .privilege-row .privilege-switch label {
        padding: 4px 11px;
        font-size: 12px;
    }

    /* Floating Save/Cancel bar: pinned to the viewport bottom while scrolling,
       and it snaps into its natural place once the page bottom is reached. */
    .role-sticky-footer {
        position: sticky;
        bottom: 0;
        z-index: 100;
        background: #fff;
        border-top: 1px solid #d2d6de;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.12);
        padding: 12px 16px;
        margin-top: 10px;
        border-radius: 0 0 3px 3px;
    }
</style>

<div class="form-group" style="margin: 0 0 12px;">
    <a href="javascript:void(0);" class="btn btn-xs btn-default" onclick="expandAllModules(true);">
        <em class="fa-solid fa-chevron-down"></em> <?= _translate("Expand all"); ?>
    </a>
    <a href="javascript:void(0);" class="btn btn-xs btn-default" onclick="expandAllModules(false);">
        <em class="fa-solid fa-chevron-up"></em> <?= _translate("Collapse all"); ?>
    </a>
</div>

<div class="privilege-accordion" id="privilegeAccordion">
    <?php
    $moduleIdx = 0;
    foreach ($rInfo as $moduleRow) {
        $moduleName = ($moduleRow['module'] == 'generic-tests') ? "Other Lab Tests" : $moduleRow['module'];
        // First module open by default; the rest collapsed.
        $collapsedCls = $moduleIdx === 0 ? "" : "collapsed";
        $moduleResources = explode("##", (string) $moduleRow['module_resources']);
        ?>
        <div class="privilege-module-panel <?= $collapsedCls; ?>" data-module="<?= htmlspecialchars((string) $moduleRow['module'], ENT_QUOTES); ?>">
            <div class="module-heading" onclick="toggleModulePanel(this);">
                <span><?= strtoupper((string) _translate($moduleName)); ?>
                    <span class="module-count">(<?= count($moduleResources); ?>)</span>
                </span>
                <em class="fa-solid fa-chevron-down chevron"></em>
            </div>
            <div class="module-body">
                <?php
                foreach ($moduleResources as $mRes) {
                    $mRes = explode(",", $mRes);
                    $resourceId = $mRes[0];
                    $resourceName = $mRes[1] ?? $mRes[0];
                    ?>
                    <div class="resource-block togglerTr">
                        <div class="resource-heading">
                            <h4><?= _translate($resourceName); ?></h4>
                            <div class="super-switch privilege-switch">
                                <input type='radio' id='all<?= $resourceId; ?>' name='<?= $resourceName; ?>' onclick='togglePrivilegesForThisResource("<?= $resourceId; ?>",true);'>
                                <label for='all<?= $resourceId; ?>'><?= _translate("All"); ?></label>
                                <input type='radio' id='none<?= $resourceId; ?>' name='<?= $resourceName; ?>' onclick='togglePrivilegesForThisResource("<?= $resourceId; ?>",false);' <?= $isSuperAdmin ? 'disabled' : ''; ?>>
                                <label for='none<?= $resourceId; ?>'><?= _translate("None"); ?></label>
                            </div>
                        </div>
                        <?php
                        // Render ALL privileges; the client filters by Access Type via
                        // data-show-mode (applyAccessTypeFilter below).
                        $pInfo = $db->rawQuery("SELECT * FROM privileges WHERE resource_id = ? ORDER BY display_order ASC", [$resourceId]);
                        ?>
                        <div class="privilege-grid privilegesNode" id="<?= $resourceId; ?>">
                            <?php
                            foreach ($pInfo as $privilege) {
                                if ($isSuperAdmin || in_array($privilege['privilege_id'], $priId)) {
                                    $allowChecked = " checked='checked' ";
                                    $denyChecked = "";
                                    $allowStyle = "allow-label";
                                    $denyStyle = "";
                                } else {
                                    $denyChecked = " checked='checked' ";
                                    $allowChecked = "";
                                    $denyStyle = "deny-label";
                                    $allowStyle = "";
                                }
                                $showMode = htmlspecialchars((string) ($privilege['show_mode'] ?? 'always'), ENT_QUOTES);
                                $pid = $privilege['privilege_id'];
                                ?>
                                <div class='col-md-6 col-lg-4 privilege-div' data-show-mode='<?= $showMode; ?>' data-privilegeid='<?= $pid; ?>' id='div<?= $pid; ?>'>
                                    <div class='privilege-row'>
                                        <strong class='privilege-label' data-privilegeid='<?= $pid; ?>' id='label<?= $pid; ?>'><?= _translate($privilege['display_name']); ?></strong>
                                        <div class='privilege-switch' data-privilegeid='<?= $pid; ?>' id='switch<?= $pid; ?>'>
                                            <input type='radio' class='selectPrivilege' name='resource[<?= $pid; ?>]' value='allow' id='selectPrivilege<?= $pid; ?>' <?= $allowChecked; ?>><label for='selectPrivilege<?= $pid; ?>' class='<?= $allowStyle; ?>'><?= _translate("Yes"); ?></label>
                                            <input type='radio' class='unselectPrivilege' name='resource[<?= $pid; ?>]' value='deny' id='unselectPrivilege<?= $pid; ?>' <?= $denyChecked; ?> <?= $isSuperAdmin ? "disabled='disabled'" : ""; ?>> <label for='unselectPrivilege<?= $pid; ?>' class='<?= $denyStyle; ?>'><?= _translate("No"); ?></label>
                                        </div>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
        <?php
        $moduleIdx++;
    } ?>
</div>

<script>
    // ---- Accordion expand/collapse ----
    function toggleModulePanel(headingEl) {
        var panel = headingEl.closest('.privilege-module-panel');
        if (panel) { panel.classList.toggle('collapsed'); }
    }

    function expandAllModules(expand) {
        var panels = document.querySelectorAll('.privilege-module-panel');
        for (var i = 0; i < panels.length; i++) {
            if (expand) { panels[i].classList.remove('collapsed'); }
            else { panels[i].classList.add('collapsed'); }
        }
    }

    // ---- Access Type filter + search (decoupled via .mode-hidden / .search-hidden) ----
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.jQuery) { return; }
        var $ = window.jQuery;

        function allowedModes() {
            var at = ($('#accessType').val() || '').toString();
            if (at === 'testing-lab') { return ['lis', 'always', '']; }
            if (at === 'collection-site') { return ['sts', 'always', '']; }
            return []; // nothing selected -> matrix stays empty until an access type is chosen
        }

        function denyCell($cell) {
            var $deny = $cell.find('.unselectPrivilege');
            if (!$deny.prop('disabled')) {
                $cell.find('.selectPrivilege').prop('checked', false);
                $deny.prop('checked', true);
            }
        }

        // Mark privileges not valid for the selected Access Type with .mode-hidden
        // and force them to "deny" so they can never be granted.
        function applyAccessTypeFilter() {
            var allowed = allowedModes();
            $('.privilege-div').each(function () {
                var sm = ($(this).attr('data-show-mode') || 'always').toString();
                if (allowed.indexOf(sm) !== -1) {
                    $(this).removeClass('mode-hidden');
                } else {
                    $(this).addClass('mode-hidden');
                    denyCell($(this)); // a hidden privilege must never be granted
                }
            });
        }
        window.applyAccessTypeFilter = applyAccessTypeFilter;

        function denyHiddenPrivileges() {
            $('.privilege-div.mode-hidden').each(function () { denyCell($(this)); });
        }

        applyAccessTypeFilter();
        $('#accessType').on('change', applyAccessTypeFilter);

        // "Select All" controls set every cell to allow, including mode-hidden ones,
        // so re-deny the hidden cells right after such a click.
        $(document).on('click', '#allowAllPrivileges, [id^="all"]', function () {
            setTimeout(denyHiddenPrivileges, 0);
        });
    });

    // Search across module names, resource (heading) names and privilege labels.
    // Toggles .search-hidden only; .mode-hidden (access-type) is left untouched so
    // cross-mode privileges never reappear via search.
    function searchPermissions() {
        var $ = window.jQuery;
        var filter = ($('#searchInput').val() || '').toUpperCase();

        // While searching, the bulk Select All / per-resource All-None controls are
        // hidden to avoid acting on a filtered subset.
        if (filter) { $('.super-switch').hide(); } else { $('.super-switch').show(); }

        $('.privilege-module-panel').each(function () {
            var $panel = $(this);
            var moduleText = $panel.find('.module-heading').text().toUpperCase();
            var moduleNameMatch = filter && moduleText.indexOf(filter) > -1;
            var moduleHasVisibleChild = false;

            $panel.find('.resource-block').each(function () {
                var $block = $(this);
                var headingText = $block.find('.resource-heading h4').text().toUpperCase();

                if (!filter) {
                    $block.removeClass('search-hidden');
                    $block.find('.privilege-div').removeClass('search-hidden');
                    $block.find('.privilege-label').removeClass('highlight');
                    moduleHasVisibleChild = true;
                    return;
                }

                // A matching module name or resource heading reveals the whole block.
                if (moduleNameMatch || headingText.indexOf(filter) > -1) {
                    $block.removeClass('search-hidden');
                    $block.find('.privilege-div').removeClass('search-hidden');
                    $block.find('.privilege-label').addClass('highlight');
                    moduleHasVisibleChild = true;
                    return;
                }

                var blockHasMatch = false;
                $block.find('.privilege-label').each(function () {
                    var $label = $(this);
                    var $div = $label.closest('.privilege-div');
                    if ($label.text().toUpperCase().indexOf(filter) > -1) {
                        $div.removeClass('search-hidden');
                        $label.addClass('highlight');
                        blockHasMatch = true;
                    } else {
                        $div.addClass('search-hidden');
                        $label.removeClass('highlight');
                    }
                });
                if (blockHasMatch) {
                    $block.removeClass('search-hidden');
                    moduleHasVisibleChild = true;
                } else {
                    $block.addClass('search-hidden');
                }
            });

            if (!filter) {
                $panel.removeClass('search-hidden');
            } else if (moduleHasVisibleChild) {
                $panel.removeClass('search-hidden collapsed'); // auto-expand matches
            } else {
                $panel.addClass('search-hidden');
            }
        });
    }
</script>
