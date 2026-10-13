<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Services\CommonService;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Utilities\RunOnceUtility;
use App\Services\ManifestMoveService;
use App\Registries\ContainerRegistry;

/*
 * One-time repair, on the STS, of samples Move Manifest gave to a new lab and
 * that lab's own results push then gave back to the old lab -- the rule and its
 * limits are on ManifestMoveService::healRevertedMoves().
 *
 * The STS now refuses that push, so this is the history. A lab still on an
 * older release keeps its copies under the old lab until it upgrades; queue
 * resend-requests for it afterwards and its next pull takes them over.
 *
 * Nothing to do on a lab's own install: which lab a sample belongs to is the
 * STS's to decide, and the lab learns it on its next pull.
 */

RunOnceUtility::run(__FILE__, function (DatabaseService $db): void {
    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);
    if (!$general->isSTSInstance()) {
        return;
    }

    /** @var ManifestMoveService $manifestMoveService */
    $manifestMoveService = ContainerRegistry::get(ManifestMoveService::class);
    $healed = $manifestMoveService->healRevertedMoves();

    if ($healed === []) {
        MiscUtility::safeCliEcho("Reverted manifest moves… nothing to put back." . PHP_EOL);
        return;
    }
    $lines = [];
    foreach ($healed as $table => $count) {
        $lines[] = "    $table: $count sample(s)";
    }
    MiscUtility::safeCliEcho(
        "Reverted manifest moves:" . PHP_EOL
            . "  " . array_sum($healed) . " sample(s) given back to the lab their manifest was moved to" . PHP_EOL
            . implode(PHP_EOL, $lines) . PHP_EOL
            . "  Every change is recoverable from audit_log." . PHP_EOL
    );
});
