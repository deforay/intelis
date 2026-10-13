#!/usr/bin/env php
<?php

// List the "@run-once-background" scripts that still have work to do, so
// upgrade.sh and docker/entrypoint.sh launch (and name) only those.
//
// Without this, every background script was launched on every upgrade and only
// discovered in the detached process that it had nothing to do, so the upgrade
// announced "Launched run-once script in background" for scripts that never ran.
//
// A script is finished when either:
//   - it declares "@run-once-done-flag <name>" and global_config <name> = 'yes'
//     (scripts that resume across upgrades and set their own flag), or
//   - it has a row in s_run_once_scripts_log (scripts using RunOnceUtility).
//
// Output, one line per unfinished script, as "<state> <script>.php":
//   launch  <script>.php   -> start it
//   running <script>.php   -> an earlier launch still holds its lock
// Finished scripts print nothing. Exit 1 means the check itself failed, and the
// caller falls back to launching every background script (each still self-skips).

require_once __DIR__ . '/../bootstrap.php';

use App\Services\DatabaseService;
use App\Utilities\MiscUtility;
use App\Registries\ContainerRegistry;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

try {
    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);

    $applied = [];
    try {
        foreach ($db->rawQuery("SELECT script_name FROM s_run_once_scripts_log") as $row) {
            $applied[(string) $row['script_name']] = true;
        }
    } catch (Throwable) {
        // Ledger absent on very old installs: treat every script as unfinished.
    }

    foreach (glob(ROOT_PATH . '/run-once/*.php') ?: [] as $scriptPath) {
        $source = (string) file_get_contents($scriptPath);
        if (!str_contains($source, '@run-once-background')) {
            continue;
        }
        $scriptName = basename($scriptPath);

        if (preg_match('/@run-once-done-flag\s+(\w+)/', $source, $m)) {
            $flag = null;
            try {
                $flag = $db->rawQueryOne("SELECT value FROM global_config WHERE name = ?", [$m[1]]);
            } catch (Throwable) {
                // global_config unreadable: treat as unfinished.
            }
            if (is_array($flag) && strtolower(trim((string) ($flag['value'] ?? ''))) === 'yes') {
                continue;
            }
        } elseif (isset($applied[$scriptName])) {
            continue;
        }

        $state = MiscUtility::isLockFileExpired($scriptPath) ? 'launch' : 'running';
        echo "{$state} {$scriptName}" . PHP_EOL;
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'run-once-pending: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

exit(0);
