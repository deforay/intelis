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
 * One-time repair for lab samples that reconcile-accepted-without-result.php
 * sent back to Registered at Health Center. That pass read only the reception
 * date, so a sample the lab had registered, but never given a reception date,
 * was put back to the collection site. Reports then listed it as still at the
 * health facility, and the reception-date fallback skips that status, so
 * nothing else would ever move it.
 *
 * SampleStatusRepairService::repairLabSamplesMarkedAtClinic() carries the rule:
 * a sample at Registered at Health Center with no result whose record holds
 * something only a lab writes goes to Registered at Testing Lab, with its
 * collection date as reception date where it has none.
 *
 * Every module, like the pass it corrects. Backgrounded and batched because it
 * runs against the database the labs are working on.
 */

RunOnceUtility::run(__FILE__, function (DatabaseService $db): void {
    /** @var SampleStatusRepairService $statusRepair */
    $statusRepair = ContainerRegistry::get(SampleStatusRepairService::class);

    $totalRepaired = 0;
    $perModule = [];

    foreach (TestsService::getActiveTests() as $testKey) {
        $result = $statusRepair->repairLabSamplesMarkedAtClinic($testKey);

        if (!$result['scanned'] || $result['repaired'] === 0) {
            continue;
        }

        $totalRepaired += $result['repaired'];
        $perModule[] = "    $testKey: " . $result['repaired'] . " sample(s)";
    }

    if ($totalRepaired === 0) {
        MiscUtility::safeCliEcho("Lab samples marked at health center… nothing to repair." . PHP_EOL);
        return;
    }

    MiscUtility::safeCliEcho(
        "Lab samples marked at health center:" . PHP_EOL
            . "  $totalRepaired sample(s) put back to Registered at Testing Lab" . PHP_EOL
            . implode(PHP_EOL, $perModule) . PHP_EOL
    );
});
