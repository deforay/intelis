<?php

require_once __DIR__ . "/../../bootstrap.php";

declare(ticks=1);

use App\Services\DatabaseService;
use App\Services\AuditArchiveService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$cliMode = php_sapi_name() === 'cli';

// Create the archive service
$archiveService = new AuditArchiveService($db);

// Progress callback for CLI output
$progress = $cliMode ? function (string $msg) {
    echo $msg . PHP_EOL;
} : null;

// Run with lock enabled
$archiveService->run(
    sampleCode: null,      // null = bulk archive all tables
    progress: $progress,   // CLI output callback
    useLock: true          // Prevent concurrent runs
);
