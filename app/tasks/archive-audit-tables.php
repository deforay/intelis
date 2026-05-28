<?php
// app/tasks/archive-audit-tables.php

declare(strict_types=1);

declare(ticks=1);

require_once __DIR__ . '/../../bootstrap.php';

use Throwable;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\AuditArchiveService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var AuditArchiveService $auditArchiveService */
$auditArchiveService = ContainerRegistry::get(AuditArchiveService::class);

$cliMode  = php_sapi_name() === 'cli';
$lockFile = MiscUtility::getLockFile(__FILE__);

// Same locking semantics as before
if (!MiscUtility::isLockFileExpired($lockFile)) {
    if ($cliMode) {
        echo "Another instance of the script is already running." . PHP_EOL;
    }
    exit;
}

MiscUtility::touchLockFile($lockFile);
MiscUtility::setupSignalHandler($lockFile);

// Keep original echo style for CLI, silent otherwise
$progress = static function (string $msg) use ($cliMode): void {
    if ($cliMode) {
        echo $msg . PHP_EOL;
    }
};

try {
    // Legacy path: archive any remaining audit_form_* tables. After the Audit
    // Trail v2 cutover + run-once prune drops those tables, this becomes a fast
    // no-op (skipping missing tables internally).
    $auditArchiveService->run(sampleCode: null, progress: $progress, useLock: false);

    // v2 path: drain audit_log → files (precision keying + self-prune). Safe to
    // call even pre-cutover — it no-ops if audit_log doesn't exist yet
    // (instances that haven't reached the 5.5.3 migration). The outer script
    // lock above covers both calls; no second internal lock needed.
    $auditArchiveService->runFromAuditLog(progress: $progress, useLock: false);

    if ($cliMode) {
        echo "Archiving process completed." . PHP_EOL;
    }
} catch (Throwable $e) {
    if ($cliMode) {
        echo "Some or all data could not be archived" . PHP_EOL;
        echo "An internal error occurred. Please check the logs." . PHP_EOL;
    }
    LoggerUtility::logError($e->getMessage(), [
        'file'           => $e->getFile(),
        'line'           => $e->getLine(),
        'last_db_error'  => $db?->getLastError(),
        'last_db_query'  => $db?->getLastQuery(),
        'trace'          => $e->getTraceAsString(),
    ]);
} finally {
    MiscUtility::deleteLockFile(__FILE__);
}
