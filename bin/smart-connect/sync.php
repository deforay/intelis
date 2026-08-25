#!/usr/bin/env php
<?php

// Smart Connect: push local data to the dashboard.
//
//   php bin/smart-connect/sync.php vl
//   php bin/smart-connect/sync.php eid
//   php bin/smart-connect/sync.php covid19
//   php bin/smart-connect/sync.php metadata [-f|--force]
//
// One entry point for everything this instance pushes upstream. The form
// modules differ only by a row in SmartConnectService::MODULES; metadata is a
// genuinely different shape (whole reference tables, one shared watermark, no
// batching) and so has its own service method behind the same command.
//
// The four scripts this replaces were near-identical copies, and they drifted:
// a debugging `die()` sat in the VL one, uploading nothing on every cron run.
//
// Exit codes: 0 nothing to do or synced, 1 the dashboard rejected the payload
// or the run threw, 2 the argument was wrong.

$cliMode = PHP_SAPI === 'cli';
if ($cliMode) {
    require_once(__DIR__ . "/../../bootstrap.php");
}

use App\Services\SmartConnectService;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 20000);

/** @var SmartConnectService $smartConnect */
$smartConnect = ContainerRegistry::get(SmartConnectService::class);

$output = MiscUtility::console();

$targets = [...SmartConnectService::modules(), 'metadata'];
$module = $argv[1] ?? null;

if ($module === null || !in_array($module, $targets, true)) {
    $output->writeln("<error> Usage: php bin/smart-connect/sync.php <" . implode('|', $targets) . "> [-f|--force] </error>");
    exit(2);
}

try {
    if ($module === 'metadata') {
        // Force drops and rebuilds the dashboard's copy of each table, so it
        // also ignores the watermark and resends every row.
        //
        // Read straight from $argv rather than via getopt(): getopt() stops at
        // the first non-option argument, and the module name is always one, so
        // it returns nothing at all here and every forced run silently ran as
        // an incremental one.
        $force = count(array_intersect(['-f', '--force'], array_slice($argv, 2))) > 0;

        $output->writeln('<info>Smart Connect Metadata Sync</info>');
        if ($force) {
            $output->writeln('<comment>Force sync enabled — tables will be dropped and recreated</comment>');
        }

        $bar = MiscUtility::spinnerStart(count($smartConnect->metadataTables()), 'Collecting table data…');
        $result = $smartConnect->syncMetadata($force, static function () use ($bar) {
            MiscUtility::spinnerAdvance($bar);
        });
        MiscUtility::spinnerFinish($bar);

        $result['sent'] = $result['tables'];
    } else {
        $result = $smartConnect->syncModule($module);
    }

    switch ($result['outcome']) {
        case 'synced':
            $output->writeln(sprintf(
                "<info>%s: synced %d %s, watermark now %s</info>",
                $module,
                $result['sent'],
                $module === 'metadata' ? 'table(s)' : 'record(s)',
                $result['watermark'] ?? 'unchanged'
            ));
            break;

        case 'nothing':
            $output->writeln("<comment>$module: nothing to sync</comment>");
            break;

        case 'skipped':
            // Not an error the lab can act on, and it repeats every cron run,
            // so it stays quiet in the exit code.
            $output->writeln("<comment>$module: skipped - {$result['message']}</comment>");
            break;

        case 'failed':
            $output->writeln(sprintf(
                "<error> %s: dashboard rejected the batch (HTTP %s): %s </error>",
                $module,
                $result['httpStatus'] ?? 'no response',
                $result['message']
            ));
            exit(1);
    }

    exit(0);
} catch (Throwable $exc) {
    $output->writeln("<error> $module: " . $exc->getMessage() . " </error>");
    LoggerUtility::log("error", $exc->getMessage(), [
        'file' => __FILE__,
        'line' => __LINE__,
        'module' => $module,
        'trace' => $exc->getTraceAsString(),
    ]);
    exit(1);
}
