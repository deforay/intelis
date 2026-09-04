<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Services\TestsService;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Utilities\RunOnceUtility;
use App\Registries\ContainerRegistry;
use App\Services\SampleStatusRepairService;

/*
 * @run-once-background
 *
 * One-time repair for samples recorded as Accepted or Awaiting Approval that
 * hold no result at all. Neither status can be true of a row with nothing to
 * show, and a row carrying both is counted as finished by every report that
 * reads the status and as still pending by every report that reads the
 * milestones -- which is how one lab comes to have two answers to the same
 * question.
 *
 * The reasoning, and the repair itself, live in SampleStatusRepairService: what
 * a sample is put back to is read from its milestones, an invented test date is
 * cleared while a real-looking one is kept, and the approval and review stamps
 * always go, because you cannot approve a result that does not exist.
 *
 * This is the historical pass, over every row of every module. The nightly
 * bin/update-sample-status.php runs the same repair but only over the last few
 * months, so it stays a guard against whatever the write paths produce from now
 * on rather than re-scanning years of settled history every night.
 *
 * Backgrounded on purpose: it walks every form_* table and there is nothing
 * about it an upgrade should wait for.
 *
 * All modules, not EID alone. The defect is EID-shaped in the field -- the write
 * paths that omit the result check are on the EID branches -- but the same bulk
 * status screens exist for every module, so the sweep asks every one of them
 * rather than assuming.
 *
 * Batched, with a pause between batches, because this runs against the database
 * the labs are working on.
 */

RunOnceUtility::run(__FILE__, function (DatabaseService $db): void {
    /** @var SampleStatusRepairService $statusRepair */
    $statusRepair = ContainerRegistry::get(SampleStatusRepairService::class);

    $totalRepaired = 0;
    $totalDatesCleared = 0;
    $perModule = [];

    foreach (TestsService::getActiveTests() as $testKey) {
        // null window: every row, however old. That is the whole point of this
        // pass, and the only thing that separates it from the nightly one.
        $result = $statusRepair->repairAcceptedWithoutResult($testKey, null);

        if (!$result['scanned'] || $result['repaired'] === 0) {
            continue;
        }

        $totalRepaired += $result['repaired'];
        $totalDatesCleared += $result['datesCleared'];
        $perModule[] = "    $testKey: " . $result['repaired'] . " sample(s), "
            . $result['datesCleared'] . " invented test date(s) cleared";
    }

    if ($totalRepaired === 0) {
        MiscUtility::safeCliEcho("Accepted-without-result reconciliation… nothing to repair." . PHP_EOL);
        return;
    }

    MiscUtility::safeCliEcho(
        "Accepted-without-result reconciliation:" . PHP_EOL
            . "  $totalRepaired sample(s) put back to what their own record proves" . PHP_EOL
            . "  $totalDatesCleared of them had an invented test date cleared" . PHP_EOL
            . implode(PHP_EOL, $perModule) . PHP_EOL
            . "  Every cleared value is recoverable from audit_log." . PHP_EOL
    );
});
