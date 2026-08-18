#!/usr/bin/env php
<?php

// Find records whose own columns contradict each other and store the counts for
// the "Needs attention" card on the request lists.
//
// This only ever counts. It corrects nothing, on purpose: every contradiction
// it looks for has had its cause closed, so a count above zero means a new
// write path has opened one, and a job that quietly repaired them would erase
// exactly the evidence that says so. Repairing the backlog was a one-time job
// (run-once/reconcile-rejected-with-result.php); noticing is the standing one.
//
// Cron-invoked nightly. Safe to run by hand at any time.
//
// Usage:
//   php bin/flag-data-issues.php            scan the modules that are enabled
//   php bin/flag-data-issues.php eid        scan one, enabled or not
//   php bin/flag-data-issues.php --all      scan every active test type
//   php bin/flag-data-issues.php --full     force a full pass rather than the
//                                           incremental one

require_once __DIR__ . "/../bootstrap.php";

use App\Services\TestsService;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\DataIssuesService;
use App\Registries\ContainerRegistry;

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    exit(0);
}

/** @var DataIssuesService $dataIssues */
$dataIssues = ContainerRegistry::get(DataIssuesService::class);

// Everything here -- the table, the predicates, the scan -- is written per test
// type and works for any module. Only VL is scanned by default while the checks
// earn their keep on real data; adding a module is one entry in this list.
const ENABLED_MODULES = ['vl'];

$args = array_slice($argv, 1);
$forceFull = in_array('--full', $args, true);
$scanAll = in_array('--all', $args, true);
$only = null;
foreach ($args as $arg) {
    if (!str_starts_with($arg, '--')) {
        $only = $arg;
        break;
    }
}

$activeTests = TestsService::getActiveTests();

if ($only !== null) {
    if (!in_array($only, $activeTests, true)) {
        MiscUtility::safeCliEcho("Unknown or inactive test type: $only" . PHP_EOL);
        exit(1);
    }
    $testTypes = [$only];
} elseif ($scanAll) {
    $testTypes = $activeTests;
} else {
    $testTypes = array_values(array_intersect($activeTests, ENABLED_MODULES));
}

if ($testTypes === []) {
    MiscUtility::safeCliEcho("No enabled module is active on this instance." . PHP_EOL);
    exit(0);
}

$grandTotal = 0;
$found = [];
$incomplete = [];

foreach ($testTypes as $testType) {
    try {
        $result = $dataIssues->refresh($testType, $forceFull);
    } catch (Throwable $e) {
        // One module's scan failing must not cost the others theirs.
        LoggerUtility::logError("flag-data-issues: $testType failed — " . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
        MiscUtility::safeCliEcho("  $testType: failed, see the application log" . PHP_EOL);
        continue;
    }

    $grandTotal += $result['flagged'];
    if ($result['mode'] === 'skipped') {
        continue;
    }
    if (!$result['complete']) {
        $incomplete[] = $testType;
    }
    $found[$testType] = $result['flagged'];

    MiscUtility::safeCliEcho(sprintf(
        "  %s: %s scan, %s row(s) examined, %s flagged%s",
        $testType,
        $result['mode'],
        number_format($result['examined']),
        number_format($result['flagged']),
        $result['complete'] ? PHP_EOL : ' (more to do next run)' . PHP_EOL
    ));
}

if ($incomplete !== []) {
    // Says so rather than reading as a finished, clean scan: a chunk budget was
    // reached and the rest resumes on the next run.
    MiscUtility::safeCliEcho(
        'Not finished for: ' . implode(', ', $incomplete) . '. The next run resumes where this stopped.' . PHP_EOL
    );
}

if ($grandTotal > 0) {
    // Logged as well as stored: the card tells whoever opens the page, and this
    // tells whoever reads the logs, which on most instances is the only place a
    // new contradiction would ever be noticed.
    LoggerUtility::logInfo("flag-data-issues: $grandTotal contradictory record(s) found.", ['found' => $found]);
}

MiscUtility::safeCliEcho(
    $grandTotal === 0
        ? "No contradictory records found." . PHP_EOL
        : "$grandTotal contradictory record(s) found." . PHP_EOL
);

exit(0);
