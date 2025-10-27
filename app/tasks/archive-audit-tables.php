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
    // Delegate to the service; bulk run, no internal lock (we already hold script lock)
    $auditArchiveService->run(sampleCode: null, progress: $progress, useLock: false);

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
