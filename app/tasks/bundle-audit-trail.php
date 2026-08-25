<?php
// app/tasks/bundle-audit-trail.php

declare(strict_types=1);

declare(ticks=1);

require_once __DIR__ . '/../../bootstrap.php';

use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\AuditBundleService;

// Rolls settled per-sample audit files into one archive per month.
//
// Runs after the archiving task rather than alongside it: that one writes
// per-sample files, this one tidies away the ones that have stopped changing.
// Deliberately daily and batched — the first run on an established lab has
// every sample it has ever recorded to work through, and the point of this is
// to stop the audit directory being expensive, not to be expensive itself.
//
//   php app/tasks/bundle-audit-trail.php            bundle a batch
//   php app/tasks/bundle-audit-trail.php --stats    report, change nothing
//   php app/tasks/bundle-audit-trail.php --batch=N  bundle at most N files

$cliMode  = php_sapi_name() === 'cli';
$args     = $argv ?? [];
$statsOnly = in_array('--stats', $args, true);

$batch = null;
foreach ($args as $arg) {
    if (preg_match('/^--batch=(\d+)$/', (string) $arg, $m) === 1) {
        $batch = (int) $m[1];
    }
}

$say = static function (string $msg) use ($cliMode): void {
    if ($cliMode) {
        echo $msg . PHP_EOL;
    }
};

if ($statsOnly) {
    $stats = AuditBundleService::stats();
    $say(sprintf(
        "Loose files: %d (%d settled and ready to bundle)\nMonth archives: %d",
        $stats['loose'],
        $stats['settled'],
        $stats['bundles']
    ));
    exit(0);
}

$lockFile = MiscUtility::getLockFile(__FILE__);

if (!MiscUtility::isLockFileExpired($lockFile)) {
    $say("Another instance of the script is already running.");
    exit;
}

MiscUtility::touchLockFile($lockFile);
MiscUtility::setupSignalHandler($lockFile);

try {
    $result = AuditBundleService::run($batch, $say);

    $say(sprintf(
        "Bundled %d file(s) into %d archive(s). %d not settled yet, %d error(s).",
        $result['bundled'],
        $result['archives'],
        $result['skipped'],
        $result['errors']
    ));
} catch (Throwable $e) {
    $say("Audit files could not be bundled. Please check the logs.");
    LoggerUtility::logError($e->getMessage(), [
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
} finally {
    MiscUtility::deleteLockFile(__FILE__);
}
