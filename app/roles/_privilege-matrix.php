<?php

/**
 * Shared privilege matrix for role add/edit (addRole.php, editRole.php).
 *
 * Renders the per-module tabbed grid of privileges with Yes/No switches, and
 * filters it on the client by the role's Access Type. Each privilege carries a
 * data-show-mode:
 *   lis    = Testing-lab function   (e.g. Add Samples from Manifest, Lab Storage)
 *   sts    = Collection-site function (e.g. create/manage manifests)
 *   always = both
 * applyAccessTypeFilter() shows only the privileges relevant to the selected
 * access type (testing-lab -> lis+always, collection-site -> sts+always, none
 * -> always). Hidden privileges are forced to "deny" so they can never be saved,
 * and a submit guard re-applies that just before the form posts.
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
<ul id="myTab" class="nav nav-tabs" style="font-size:1.4em;">
    <?php $a = 0;
    foreach ($rInfo as $moduleRow) {
        $moduleName = ($moduleRow['module'] == 'generic-tests') ? "Other Lab Tests" : $moduleRow['module'];
        $liClass = $a == 0 ? "active" : ""; ?>
        <li class="<?= $liClass; ?>"><a href="#<?= $moduleRow['module']; ?>" data-toggle="tab" class="bg-primary"><?php echo strtoupper((string) $moduleName); ?> </a></li>
    <?php $a++;
    } ?>
</ul>

<div id="myTabContent" class="tab-content">
    <?php
    $b = 0;
    foreach ($rInfo as $moduleRow) {
        $tabCls = $b == 0 ? "active" : "";
        echo '<div class="tab-pane fade in ' . $tabCls . '" id="' . $moduleRow['module'] . '">';
        echo "<table aria-describedby='table' class='table table-striped responsive-utilities jambo_table'>";

        $moduleResources = explode("##", (string) $moduleRow['module_resources']);
        foreach ($moduleResources as $mRes) {
            $mRes = explode(",", $mRes);

            echo "<tr class='togglerTr'>";
            echo "<th>"; ?>
            <small class="toggler">
                <h4 style="font-weight: bold;"><?= _translate($mRes[1]); ?></h4>
                <div class="super-switch privilege-switch pull-right">
                    <input type='radio' id='all<?= $mRes[0]; ?>' name='<?= $mRes[1]; ?>' onclick='togglePrivilegesForThisResource("<?= $mRes[0]; ?>",true);'>
                    <label for='all<?= $mRes[0]; ?>'><?= _translate("All"); ?></label>
                    <input type='radio' id='none<?= $mRes[0]; ?>' name='<?= $mRes[1]; ?>' onclick='togglePrivilegesForThisResource("<?= $mRes[0]; ?>",false);' <?= $isSuperAdmin ? 'disabled' : ''; ?>>
                    <label for='none<?= $mRes[0]; ?>'><?= _translate("None"); ?></label>
                </div>
            </small>
            <?php
            echo "</th></tr>";

            // Render ALL privileges; the client filters by Access Type via
            // data-show-mode (see applyAccessTypeFilter below).
            $pInfo = $db->rawQuery("SELECT * FROM privileges WHERE resource_id = ? ORDER BY display_order ASC", [$mRes[0]]);
            echo "<tr class='permissionTr'>";
            echo "<td style='text-align:center;vertical-align:middle;' class='privilegesNode' id='" . $mRes[0] . "'>";
            foreach ($pInfo as $privilege) {
                if ($isSuperAdmin) {
                    $allowChecked = " checked='checked' ";
                    $denyChecked = "";
                    $allowStyle = "allow-label";
                    $denyStyle = "";
                } elseif (in_array($privilege['privilege_id'], $priId)) {
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
                echo "<div class='col-lg-3 privilege-div' data-show-mode='" . $showMode . "' data-privilegeid='" . $privilege['privilege_id'] . "' id='div" . $privilege['privilege_id'] . "'>
                        <strong class='privilege-label' data-privilegeid='" . $privilege['privilege_id'] . "' id='label" . $privilege['privilege_id'] . "'>" . _translate($privilege['display_name']) . "</strong>
                        <br>
                        <div class='privilege-switch' data-privilegeid='" . $privilege['privilege_id'] . "' id='switch" . $privilege['privilege_id'] . "' style='margin: 30px 0 36px 90px;'>
                            <input type='radio' class='selectPrivilege' name='resource[" . $privilege['privilege_id'] . "]' value='allow' id='selectPrivilege" . $privilege['privilege_id'] . "' $allowChecked><label for='selectPrivilege" . $privilege['privilege_id'] . "' class='$allowStyle'>Yes</label>
                            <input type='radio' class='unselectPrivilege' name='resource[" . $privilege['privilege_id'] . "]' value='deny' id='unselectPrivilege" . $privilege['privilege_id'] . "' $denyChecked " . ($isSuperAdmin ? "disabled='disabled'" : "") . "> <label for='unselectPrivilege" . $privilege['privilege_id'] . "' class='$denyStyle'> No</label>
                        </div>
                    </div>";
            }
            echo "</td></tr>";
        }
        echo "</table></div>";
        $b++;
    } ?>
</div>

<script>
    // Filter the privilege matrix by the role's Access Type. Registered with a
    // vanilla listener so it is safe regardless of where jQuery is loaded.
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

        function applyAccessTypeFilter() {
            var allowed = allowedModes();
            // Cell-level only: never hide tabs/rows. (Inactive Bootstrap tab-panes
            // are display:none, so :visible can't be used to detect empty tabs --
            // doing so hides every tab but the active one. Every module also has
            // "always" privileges, so no tab is ever genuinely empty.)
            $('.privilege-div').each(function () {
                var sm = ($(this).attr('data-show-mode') || 'always').toString();
                if (allowed.indexOf(sm) !== -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                    denyCell($(this)); // a hidden privilege must never be granted
                }
            });
        }
        window.applyAccessTypeFilter = applyAccessTypeFilter;

        function denyHiddenPrivileges() {
            $('.privilege-div:hidden').each(function () { denyCell($(this)); });
        }

        applyAccessTypeFilter();
        $('#accessType').on('change', applyAccessTypeFilter);

        // A hidden privilege must never end up granted. The "Select All" controls
        // (global #allowAllPrivileges and each resource's all<id> toggle) set
        // every cell to allow, including hidden ones, so re-deny the hidden cells
        // right after such a click. Done here rather than on submit because
        // validateNow() calls the native form.submit(), which does not fire the
        // jQuery submit event.
        $(document).on('click', '#allowAllPrivileges, [id^="all"]', function () {
            setTimeout(denyHiddenPrivileges, 0);
        });
    });
</script>
