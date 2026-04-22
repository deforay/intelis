#!/usr/bin/env php
<?php

// bin/prune-remote-commands.php
//
// Deletes terminal rows from s_lis_remote_commands older than
// global_config.remote_command_retention_days (default 90).
// Prevents unbounded growth. Intended to run daily from ScheduledTasks.
//
// Only terminal statuses are eligible. In-flight rows are never touched
// regardless of age — they get converted to 'failed' by the stale-command
// sweep inside /remote/v2/pending-commands.php first, then become eligible
// for pruning on the next daily run.

require_once __DIR__ . "/../bootstrap.php";

use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

if (PHP_SAPI !== 'cli') {
    exit(CLI\ERROR);
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Only STS accumulates meaningful rows here (LIS table is dormant).
// Still run on both sides — cheap, keeps them tidy.

$retention = (int) $general->getGlobalConfig('remote_command_retention_days');
if ($retention <= 0) {
    $retention = 90;
}

$terminal = ['completed', 'failed', 'expired', 'cancelled'];

try {
    $db->where('status', $terminal, 'IN');
    $db->where("requested_at < DATE_SUB(NOW(), INTERVAL $retention DAY)");
    $deleted = $db->delete('s_lis_remote_commands');

    $deletedCount = is_int($deleted) ? $deleted : ($deleted ? (int) $db->count : 0);

    LoggerUtility::logInfo(
        "prune-remote-commands: pruned {$deletedCount} row(s) older than {$retention} days"
    );

    if (function_exists('fwrite')) {
        fwrite(STDOUT, "Pruned {$deletedCount} terminal command row(s) older than {$retention} days.\n");
    }
} catch (Throwable $e) {
    LoggerUtility::logError('prune-remote-commands failed: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
    ]);
    exit(CLI\ERROR);
}

exit(CLI\OK);
