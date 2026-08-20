<?php

// get-sync-status.php

use Carbon\Carbon;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\LabCapabilityService;
use App\Utilities\LoggerUtility;

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody(), nullifyEmptyStrings: true);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$isSTS = $general->isSTSInstance();
$canQueue = $isSTS && _isAllowed('/admin/monitoring/queue-lis-command.php');
$canCancel = $isSTS && _isAllowed('/admin/monitoring/cancel-lis-command.php');
$showActions = $isSTS && ($canQueue || $canCancel);
$colspan = $showActions ? 6 : 5;

// Build parameterized query for better performance and security
$query = "SELECT
    f.facility_id,
    f.facility_name,
    f.facility_attributes->>'$.version' as version,
    f.facility_attributes->>'$.commitSha' as commitSha,
    f.facility_attributes->>'$.lastHeartBeat' as lastHeartBeat,
    f.facility_attributes->>'$.lastResultsSync' as lastResultsSync,
    f.facility_attributes->>'$.lastRequestsSync' as lastRequestsSync,
    f.facility_attributes->>'$.courierHeartbeat' as courierHeartbeat,
    f.facility_attributes->>'$.runnerHeartbeat' as runnerHeartbeat,
    f.facility_attributes->>'$.capabilities' as capabilitiesJson,
    f.facility_attributes->>'$.capabilitiesSeenAt' as capabilitiesSeenAt,
    f.facility_attributes->>'$.commandPlaneSeenAt' as commandPlaneSeenAt,
    f.facility_attributes->>'$.instanceId' as instanceId,
    f.facility_attributes->>'$.previousInstanceId' as previousInstanceId,
    f.facility_attributes->>'$.instanceChangedAt' as instanceChangedAt,
    f.facility_attributes->>'$.instanceChangeCount' as instanceChangeCount,
    tar.last_requested_on,
    GREATEST(
        COALESCE(UNIX_TIMESTAMP(STR_TO_DATE(f.facility_attributes->>'$.lastHeartBeat', '%Y-%m-%d %H:%i:%s')), 0),
        COALESCE(UNIX_TIMESTAMP(STR_TO_DATE(f.facility_attributes->>'$.lastResultsSync', '%Y-%m-%d %H:%i:%s')), 0),
        COALESCE(UNIX_TIMESTAMP(STR_TO_DATE(f.facility_attributes->>'$.lastRequestsSync', '%Y-%m-%d %H:%i:%s')), 0),
        COALESCE(UNIX_TIMESTAMP(tar.last_requested_on), 0)
    ) as latest_timestamp
FROM facility_details f
LEFT JOIN (
    SELECT facility_id, MAX(requested_on) as last_requested_on
    FROM track_api_requests
    GROUP BY facility_id
) tar ON tar.facility_id = f.facility_id
WHERE f.facility_type = 2
    AND f.status = 'active'";

$params = [];

// Add filters with parameterized queries
if (!empty($_POST['labName'])) {
    $query .= " AND f.facility_id = ?";
    $params[] = $_POST['labName'];
}
if (!empty($_POST['province'])) {
    $query .= " AND f.facility_state_id = ?";
    $params[] = $_POST['province'];
}
if (!empty($_POST['district'])) {
    $query .= " AND f.facility_district_id = ?";
    $params[] = $_POST['district'];
}

$query .= " ORDER BY latest_timestamp DESC";

// Store query for export functionality
$_SESSION['labSyncStatus'] = $query;
$_SESSION['labSyncStatusParams'] = $params;

// Render the table body inside a buffer + try/catch so a transient failure
// (a class skew mid-deploy, a malformed row, a DB hiccup) degrades to a single
// logged error row instead of a raw 500 that blanks the whole table.
ob_start();
try {

    $resultSet = $db->rawQueryGenerator($query, $params);

// Calculate thresholds once
$twoWeeksAgo = strtotime('-2 weeks');
$fourWeeksAgo = strtotime('-4 weeks');

// Pre-fetch pending/in-flight commands for the labs in this result set so we
// can badge the row and disable duplicate-queueing client-side.
// Prepared rows are tracked separately so the "Apply prepared upgrade"
// dropdown can show the operator what's ready to apply on each lab.
$pendingCommandsByLab = [];
$preparedByLab = [];
if ($showActions) {
    $inFlight = $db->rawQuery(
        "SELECT command_id, lab_id, command, status, result, requested_at
         FROM s_lis_remote_commands
         WHERE status IN ('pending','picked','running','preparing','prepared','applying')
         ORDER BY requested_at DESC"
    );
    // Tag each row with its command_id in the indexed array so we can show
    // a cancel 'x' on pending badges without adding another query path.
    foreach ($inFlight ?: [] as $row) {
        $pendingCommandsByLab[$row['lab_id']][] = $row;

        if ($row['status'] === 'prepared' && $row['command'] === 'upgrade-prepare') {
            $resultDecoded = [];
            if (!empty($row['result'])) {
                $resultDecoded = json_decode((string) $row['result'], true) ?: [];
            }
            $preparedByLab[$row['lab_id']][] = [
                'commandId' => $row['command_id'],
                'stagedVersion' => $resultDecoded['stagedVersion'] ?? 'unknown',
                'stagingDir' => $resultDecoded['stagingDir'] ?? '',
                'requestedAt' => $row['requested_at'],
            ];
        }
    }
}

// rawQueryGenerator() hands back a Generator, and empty() on an object is
// always false, so the "no data" branch this used to guard was unreachable: a
// query matching no labs rendered an empty table body rather than saying so.
// Count what the loop actually yields instead.
$rowsRendered = 0;
foreach ($resultSet as $aRow) {
    $rowsRendered++;
        // Grade how far we trust this lab for remote commands (see
        // LabCapabilityService::evaluate()):
        //   'full'  -> lab reported commandPlane=true recently: offer every
        //              advertised verb (incl. upgrade/root when allowed).
        //   'basic' -> lab is actively polling the command plane but on a
        //              courier that predates capability reporting: offer only
        //              the safe non-root verbs.
        //   'none'  -> neither signal fresh: queueing disabled.
        $decodedCaps = !empty($aRow['capabilitiesJson'])
            ? json_decode((string) $aRow['capabilitiesJson'], true)
            : null;
        $capEval = LabCapabilityService::evaluate(
            is_array($decodedCaps) ? $decodedCaps : null,
            $aRow['capabilitiesSeenAt'] ?? null,
            $aRow['commandPlaneSeenAt'] ?? null
        );
        $labTier = $capEval['tier'];
        $labSupports = $capEval['supports'];

        // Which installation is answering for this lab, and whether more than
        // one has. The plane cannot distinguish two installations of the same
        // lab, so a command goes to whichever polls first; this is the only
        // signal that says so.
        $clean = static fn($v) => !empty($v) && $v !== 'null' ? (string) $v : null;
        $instanceState = LabCapabilityService::instanceState([
            'instanceId' => $clean($aRow['instanceId'] ?? null),
            'previousInstanceId' => $clean($aRow['previousInstanceId'] ?? null),
            'instanceChangedAt' => $clean($aRow['instanceChangedAt'] ?? null),
            'instanceChangeCount' => (int) ($clean($aRow['instanceChangeCount'] ?? null) ?? 0),
        ]);

        // Determine sync status colour.
        //
        // 'never' is split out from 'red' deliberately. Lumping them together
        // reported 52 of 55 labs as critical, at which point the word had
        // stopped carrying information — an operator cannot triage a list that
        // is almost entirely red. A lab that has never once synced is not an
        // incident; it is a lab that was registered and never brought up, and
        // it needs a different response from one that was working last month
        // and stopped. The second is the one worth looking at today.
        $latestSync = (int) $aRow['latest_timestamp'];
        if ($latestSync <= 0) {
            $color = 'never';
        } elseif ($latestSync > $twoWeeksAgo) {
            $color = 'green';
        } elseif ($latestSync > $fourWeeksAgo) {
            $color = 'yellow';
        } else {
            $color = 'red';
        }

        // Calculate days since last sync for better user understanding
        $daysSinceSync = null;
        if ($latestSync !== 0) {
            $latest = Carbon::createFromTimestamp($latestSync);
            $now = Carbon::now();
            $daysSinceSync = $latest->diffInDays($now, false); // false = return negative if future
            $daysSinceSync = max(0, (int) $daysSinceSync); // Clamp to 0 to avoid negative display
        }

        if ($daysSinceSync !== null) {
            $daysSinceText = $daysSinceSync === 0 ? 'Today' : "$daysSinceSync days ago";
        } else {
            $daysSinceText = 'Never';
        }


        ?>
        <tr class="<?php echo $color; ?>" data-facilityId="<?= base64_encode((string) $aRow['facility_id']); ?>">
            <td>
                <?= htmlspecialchars((string) $aRow['facility_name']); ?>
                <br><small class="text-muted">
                    <span class="sync-indicator <?= $color ?>-indicator"></span>
                    <?= $daysSinceText ?>
                </small>
            </td>
            <td class="text-center">
                <?= $latestSync !== 0 ? DateUtility::humanReadableDateFormat(date('Y-m-d H:i:s', $latestSync), true) : '-'; ?>
            </td>
            <td class="text-center">
                <?= DateUtility::humanReadableDateFormat($aRow['lastResultsSync'] ?? '', true) ?: '-'; ?>
            </td>
            <td class="text-center">
                <?= DateUtility::humanReadableDateFormat($aRow['lastRequestsSync'] ?? '', true) ?: '-'; ?>
            </td>
            <td class="text-center">
                <?php
                $_rowSha = $aRow['commitSha'] ?? null;
                $_rowShaShort = (is_string($_rowSha) && preg_match('/^[0-9a-f]{40}$/', $_rowSha))
                    ? substr($_rowSha, 0, 7)
                    : null;
                ?>
                <?= htmlspecialchars($aRow['version'] ?? '-'); ?><?php if ($_rowShaShort): ?>
                    <small class="text-muted">(<?= htmlspecialchars($_rowShaShort, ENT_QUOTES, 'UTF-8'); ?>)</small>
                <?php endif; ?>
            </td>
            <?php if ($showActions) { ?>
            <!-- Health of the command plane, in its own column. It used to sit
                 under the version, where a red heartbeat block visually
                 outweighed the version it was attached to and read as though
                 the version itself were wrong. They answer different questions:
                 what is installed, versus whether the machine is listening. -->
            <td class="text-center">
                <?php {
                    // Heartbeat freshness — report on the two background loops
                    // that actually drive remote commands. Both are "eventually
                    // consistent" so > 10 min stale is genuinely suspicious.
                    $courierHb = $aRow['courierHeartbeat'] ?? null;
                    $runnerHb = $aRow['runnerHeartbeat'] ?? null;
                    $staleThresholdSec = 15 * 60;

                    // Chips, not blocks. Two saturated full-width labels stacked
                    // on top of each other read as one alarming rectangle, and at
                    // fleet scale the column became a wall of red that said less
                    // than a single dot would. These are one line, muted, and sized
                    // to their content, with the colour carried by a dot.
                    //
                    // Four tiers rather than two. 21 minutes late and nine weeks
                    // dead were both flat red, so the reader could not tell a lab
                    // that is a little behind from one that is gone.
                    $renderHb = static function (?string $iso, string $label): string {
                        // MySQL's ->> renders a JSON null as the four-character
                        // string "null", which is not empty and which strtotime
                        // cannot read. That produced "runner: 496281h ago" — the
                        // age of the Unix epoch, in red, on every lab that had
                        // never reported a runner. It means "never".
                        $ts = (!empty($iso) && $iso !== 'null') ? strtotime($iso) : false;

                        if ($ts === false || $ts <= 0) {
                            return '<span class="plane-chip plane-never" title="'
                                 . htmlspecialchars(_translate('Has never reported')) . '">'
                                 . '<i class="plane-dot"></i>' . htmlspecialchars($label)
                                 . ' <b>' . htmlspecialchars(_translate('never')) . '</b></span>';
                        }

                        $age = time() - $ts;

                        // Hours stop being readable after a day or so: "1531h ago"
                        // is a number the reader has to divide before it means
                        // anything.
                        if ($age < 60) {
                            $ageText = _translate('just now');
                        } elseif ($age < 3600) {
                            $ageText = floor($age / 60) . 'm';
                        } elseif ($age < 86400) {
                            $ageText = floor($age / 3600) . 'h';
                        } else {
                            $ageText = floor($age / 86400) . 'd';
                        }

                        // Both loops are eventually-consistent and tick about every
                        // minute, so a few minutes late is normal, under an hour is
                        // worth noticing, and beyond that something has stopped.
                        if ($age <= 15 * 60) {
                            $cls = 'plane-ok';
                        } elseif ($age <= 3600) {
                            $cls = 'plane-late';
                        } else {
                            $cls = 'plane-stale';
                        }

                        return '<span class="plane-chip ' . $cls . '" title="'
                             . htmlspecialchars($label . ': ' . $iso) . '">'
                             . '<i class="plane-dot"></i>' . htmlspecialchars($label)
                             . ' <b>' . htmlspecialchars($ageText) . '</b></span>';
                    };

                    $everReported = (!empty($courierHb) && $courierHb !== 'null')
                        || (!empty($runnerHb) && $runnerHb !== 'null');

                    // A lab that has never spoken the plane gets one quiet line
                    // rather than two grey badges. In its own column an empty
                    // cell is ambiguous — it could mean "no data" or "nothing
                    // wrong" — so it says which.
                    if (!$everReported) { ?>
                        <span class="text-muted" title="<?= htmlspecialchars(_translate('This lab has never reported the command plane. It is either on a release that predates it, or the courier is not running.')); ?>">&mdash;</span>
                    <?php }
                    if ($everReported) { ?>
                        <span class="plane-chips">
                            <?= $renderHb($courierHb, _translate('courier')); ?><?= $renderHb($runnerHb, _translate('runner')); ?>
                        </span>
                    <?php }

                    // Which installation is answering. Shown only once more than
                    // one ever has: on the overwhelming majority of rows — one
                    // lab, one machine — an instance id is noise, and the point
                    // of the badge is the exception.
                    if ($instanceState['state'] === 'changed' || $instanceState['state'] === 'contested') {
                        $instCls = $instanceState['state'] === 'contested' ? 'plane-stale' : 'plane-late';
                        $instText = $instanceState['state'] === 'contested'
                            ? _translate('multiple installations')
                            : _translate('installation changed'); ?>
                        <span class="plane-chips">
                            <span class="plane-chip <?= $instCls; ?>"
                                  title="<?= htmlspecialchars((string) $instanceState['message']); ?>">
                                <i class="plane-dot"></i><?= htmlspecialchars($instText); ?>
                            </span>
                        </span>
                    <?php }
                } ?>
            </td>
            <?php } ?>
            <?php if ($showActions) {
                $labPending = $pendingCommandsByLab[$aRow['facility_id']] ?? [];
                $labPrepared = $preparedByLab[$aRow['facility_id']] ?? []; ?>
                <td class="text-center no-row-click">
                    <?php if ($canQueue) {
                        // Disable (don't hide) the button when the lab's
                        // courier hasn't recently reported the command plane,
                        // so operators can see *why* queueing isn't available
                        // for this row instead of a silently-missing button.
                        if ($labTier === 'full') {
                            $queueBtnDisabled = '';
                            $queueBtnTitle = _translate('Queue a command for this lab');
                        } elseif ($labTier === 'basic') {
                            $queueBtnDisabled = '';
                            $queueBtnTitle = _translate("Basic command set only — lab hasn't reported full capabilities yet, so upgrade/maintenance commands stay disabled.");
                        } else {
                            $queueBtnDisabled = ' disabled';
                            $queueBtnTitle = _translate("Lab's courier hasn't reported the remote command plane recently — queueing is disabled.");
                        } ?>
                        <button type="button" class="btn btn-sm btn-primary row-action queue-command-btn"
                            data-lab-id="<?= (int) $aRow['facility_id']; ?>"
                            data-lab-name="<?= htmlspecialchars((string) $aRow['facility_name'], ENT_QUOTES); ?>"
                            data-prepared='<?= htmlspecialchars(json_encode($labPrepared), ENT_QUOTES); ?>'
                            data-supports='<?= htmlspecialchars(json_encode($labSupports), ENT_QUOTES); ?>'
                            data-instance-state="<?= htmlspecialchars($instanceState['state'], ENT_QUOTES); ?>"
                            data-instance-warning="<?= htmlspecialchars((string) $instanceState['message'], ENT_QUOTES); ?>"
                            title="<?= htmlspecialchars($queueBtnTitle, ENT_QUOTES); ?>"<?= $queueBtnDisabled; ?>>
                            <i class="fa fa-paper-plane"></i>
                            <?= _translate('Queue'); ?>
                        </button>
                    <?php } ?>
                    <?php if (!empty($labPrepared)) { ?>
                        <div style="margin-top: 4px;">
                            <?php foreach ($labPrepared as $pr) { ?>
                                <span class="label label-info" style="display:inline-block; margin-top:2px;"
                                    title="<?= htmlspecialchars($pr['requestedAt']); ?>">
                                    <i class="fa fa-cube"></i>
                                    <?= _translate('Staged'); ?>: <?= htmlspecialchars($pr['stagedVersion']); ?>
                                </span>
                            <?php } ?>
                        </div>
                    <?php } ?>
                    <?php if (!empty($labPending)) { ?>
                        <div style="margin-top: 4px;">
                            <?php foreach ($labPending as $pc) {
                                if ($pc['status'] === 'prepared' && $pc['command'] === 'upgrade-prepare') {
                                    continue; // Already shown as "Staged:" above
                                }
                                $cancellable = ($pc['status'] === 'pending' && $canCancel); ?>
                                <span class="label label-warning row-action" style="display:inline-block; margin-top:2px;"
                                    title="<?= htmlspecialchars((string) $pc['requested_at']); ?>">
                                    <?= htmlspecialchars($pc['command']); ?>: <?= htmlspecialchars($pc['status']); ?>
                                    <?php if ($cancellable) { ?>
                                        <a href="#" class="cancel-command-link no-row-click"
                                           style="color: white; margin-left: 6px; font-weight: bold; text-decoration: none;"
                                           data-command-id="<?= htmlspecialchars((string) $pc['command_id']); ?>"
                                           data-command="<?= htmlspecialchars((string) $pc['command']); ?>"
                                           title="<?= _translate('Cancel this pending command'); ?>">&times;</a>
                                    <?php } ?>
                                </span>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </td>
            <?php } ?>
        </tr>
        <?php
    }

if ($rowsRendered === 0) {
    echo '<tr><td colspan="' . $colspan . '" class="dataTables_empty">' . _translate("No data available") . '</td></tr>';
}

    echo ob_get_clean();
} catch (Throwable $e) {
    ob_end_clean();
    LoggerUtility::logError('Failed to render lab sync status', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    echo '<tr><td colspan="' . $colspan . '" class="text-center text-danger">'
        . '<i class="fa fa-exclamation-triangle"></i> '
        . _translate('Failed to load sync status. Please try again.')
        . '</td></tr>';
}
