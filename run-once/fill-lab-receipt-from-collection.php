<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Services\TestsService;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Utilities\RunOnceUtility;
use App\Services\LabReceiptService;
use App\Registries\ContainerRegistry;

/*
 * @run-once-background
 *
 * One-time pass giving every sample that reached a lab without a recorded
 * reception date its collection date as the reception date.
 *
 * The rule and its reasoning live in LabReceiptService: only an empty reception
 * date is filled, never one somebody entered; a sample still registered at the
 * health center, or cancelled, is left alone; and a collection-site request
 * counts as received only once it carries a status a lab alone can give it.
 *
 * From now on the receipt triggers apply the rule on every write, and the
 * nightly bin/update-sample-status.php sweeps the last few days for anything
 * saved while the triggers were down during an upgrade. This is the history.
 *
 * Every module, not the ones where the gap was found: every lab-side write path
 * in every module saves "Registered at Testing Lab" without asking for a date.
 *
 * Backgrounded because on a large instance it touches tens of thousands of rows,
 * each firing its audit trigger, and nothing about it should hold up an upgrade.
 * last_modified_datetime and data_sync are left alone: the lab and STS each run
 * this and reach the same value, so there is nothing to send across.
 */

RunOnceUtility::run(__FILE__, function (DatabaseService $db): void {
    /** @var LabReceiptService $labReceipt */
    $labReceipt = ContainerRegistry::get(LabReceiptService::class);

    $seen = [];
    $total = 0;
    $perModule = [];
    $failures = [];

    foreach (TestsService::getActiveTests() as $testKey) {
        $table = TestsService::getTestTableName($testKey);
        $primaryKey = TestsService::getPrimaryColumn($testKey);
        // form_vl serves both VL and Recency.
        if (empty($table) || empty($primaryKey) || isset($seen[$table])) {
            continue;
        }
        $seen[$table] = true;

        // A table that fails does not stop the others; the run fails at the end
        // so the ledger does not record it done and the next upgrade retries.
        try {
            $filled = $labReceipt->stampMissing($table, $primaryKey);
        } catch (Throwable $e) {
            $failures[] = $e->getMessage();
            continue;
        }
        if ($filled > 0) {
            $total += $filled;
            $perModule[] = "    $table: $filled sample(s)";
        }
    }

    if ($failures !== []) {
        throw new RuntimeException(implode('; ', $failures));
    }

    if ($total === 0) {
        MiscUtility::safeCliEcho("Reception date fallback… nothing to fill." . PHP_EOL);
        return;
    }

    MiscUtility::safeCliEcho(
        "Reception date fallback:" . PHP_EOL
            . "  $total lab-registered sample(s) given their collection date as reception date" . PHP_EOL
            . implode(PHP_EOL, $perModule) . PHP_EOL
            . "  Every change is recoverable from audit_log." . PHP_EOL
    );
});
