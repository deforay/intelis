#!/usr/bin/env php
<?php

// bin/interface-migrate.php
// Force-run vlsm-interfacing MySQL migrations against the interface database.
// Migrations are fetched from the GitHub repo and executed one statement at a time.
// Errors are logged but do not stop execution (permissive mode).

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit(CLI\ERROR);
}

if (function_exists('pcntl_signal') && function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function (): void {
        echo PHP_EOL . "Migration cancelled by user." . PHP_EOL;
        exit(CLI\SIGINT);
    });
}

use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

ini_set('memory_limit', '-1');
set_time_limit(0);

if (!isset(SYSTEM_CONFIG['interfacing']['enabled']) || SYSTEM_CONFIG['interfacing']['enabled'] === false) {
    echo "Interfacing is not enabled in configuration." . PHP_EOL;
    exit(CLI\ERROR);
}

if (empty(SYSTEM_CONFIG['interfacing']['database']['host']) || empty(SYSTEM_CONFIG['interfacing']['database']['username'])) {
    echo "Interface database settings are not configured." . PHP_EOL;
    exit(CLI\ERROR);
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
$db->addConnection('interface', SYSTEM_CONFIG['interfacing']['database']);

// Test interface DB connection
try {
    $db->connection('interface')->rawQuery("SELECT 1");
    if ($db->connection('interface')->getLastErrno() > 0) {
        throw new RuntimeException($db->connection('interface')->getLastError());
    }
    echo "Connected to interface database: " . SYSTEM_CONFIG['interfacing']['database']['db'] . PHP_EOL;
} catch (Throwable $e) {
    echo "Failed to connect to interface database: " . $e->getMessage() . PHP_EOL;
    exit(CLI\ERROR);
}

// --- Fetch migration file list from GitHub API ---

$repoApiUrl = 'https://api.github.com/repos/deforay/vlsm-interfacing/contents/app/mysql-migrations?ref=master';
$rawBaseUrl = 'https://raw.githubusercontent.com/deforay/vlsm-interfacing/master/app/mysql-migrations/';

$options = getopt("", ["from:"]);
$fromFile = $options['from'] ?? null;

echo "Fetching migration list from GitHub..." . PHP_EOL;

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "User-Agent: InteLIS-Interface-Migrator\r\n",
        'timeout' => 30,
    ]
]);

$apiResponse = @file_get_contents($repoApiUrl, false, $context);
if ($apiResponse === false) {
    echo "Failed to fetch migration list from GitHub." . PHP_EOL;
    echo "You can also place .sql files in a local directory and modify this script." . PHP_EOL;
    exit(CLI\ERROR);
}

$files = json_decode($apiResponse, true);
if (!is_array($files)) {
    echo "Unexpected response from GitHub API." . PHP_EOL;
    exit(CLI\ERROR);
}

// Filter to .sql files and sort by name
$migrationFiles = [];
foreach ($files as $file) {
    if (($file['type'] ?? '') === 'file' && str_ends_with($file['name'], '.sql')) {
        $migrationFiles[] = $file['name'];
    }
}
sort($migrationFiles, SORT_NATURAL);

if (empty($migrationFiles)) {
    echo "No migration files found." . PHP_EOL;
    exit(0);
}

echo "Found " . count($migrationFiles) . " migration file(s): " . implode(', ', $migrationFiles) . PHP_EOL;

// Filter from a specific file if --from is specified
if ($fromFile !== null) {
    $migrationFiles = array_values(array_filter($migrationFiles, fn($f) => $f >= $fromFile));
    echo "Starting from: $fromFile (" . count($migrationFiles) . " file(s) remaining)" . PHP_EOL;
}

echo PHP_EOL;

// --- Run migrations ---

$totalStatements = 0;
$successCount = 0;
$skippedCount = 0;
$errorCount = 0;

// MySQL error codes that are safe to ignore (idempotent DDL)
$benignErrnos = [
    1050, // Table already exists
    1054, // Unknown column (in some contexts)
    1060, // Duplicate column name
    1061, // Duplicate key name
    1068, // Multiple primary key defined
    1091, // Can't DROP; check that column/key exists
    1146, // Table doesn't exist (for DROP IF NOT EXISTS equivalent)
];

foreach ($migrationFiles as $migrationFile) {
    echo "--- Migration: $migrationFile ---" . PHP_EOL;

    $sqlContent = @file_get_contents($rawBaseUrl . $migrationFile, false, $context);
    if ($sqlContent === false) {
        echo "  WARN: Failed to fetch $migrationFile, skipping." . PHP_EOL;
        $errorCount++;
        continue;
    }

    $sqlContent = trim($sqlContent);
    if ($sqlContent === '') {
        echo "  (empty file, skipping)" . PHP_EOL;
        continue;
    }

    // Split into individual statements
    // Using PhpMyAdmin SQL Parser for robust splitting
    $parser = new PhpMyAdmin\SqlParser\Parser($sqlContent);
    $statements = [];
    foreach ($parser->statements as $statement) {
        $built = trim($statement->build() ?? '');
        if ($built !== '') {
            $statements[] = $built;
        }
    }

    if (empty($statements)) {
        echo "  (no executable statements)" . PHP_EOL;
        continue;
    }

    echo "  " . count($statements) . " statement(s)" . PHP_EOL;

    $db->connection('interface')->rawQuery("SET FOREIGN_KEY_CHECKS = 0");

    foreach ($statements as $idx => $sql) {
        $totalStatements++;
        $stmtNum = $idx + 1;

        try {
            $db->connection('interface')->rawQuery($sql);
            $errno = $db->connection('interface')->getLastErrno();

            if ($errno > 0) {
                if (in_array($errno, $benignErrnos, true)) {
                    $skippedCount++;
                    echo "  [$stmtNum] SKIPPED (errno $errno): " . $db->connection('interface')->getLastError() . PHP_EOL;
                } else {
                    $errorCount++;
                    $errorMsg = "  [$stmtNum] ERROR (errno $errno): " . $db->connection('interface')->getLastError();
                    echo $errorMsg . PHP_EOL;
                    echo "       SQL: " . mb_substr($sql, 0, 200) . PHP_EOL;
                    LoggerUtility::logError("Interface migration error in $migrationFile: $errorMsg\nSQL: $sql");
                }
            } else {
                $successCount++;
            }
        } catch (Throwable $e) {
            $errorCount++;
            $msg = $e->getMessage();

            // Check if it's a benign idempotent error in the exception message
            $isBenign =
                stripos($msg, 'Duplicate column name') !== false ||
                stripos($msg, 'Duplicate key name') !== false ||
                stripos($msg, 'Table') !== false && stripos($msg, 'already exists') !== false ||
                (stripos($msg, "Can't DROP") !== false && stripos($msg, 'check that') !== false) ||
                stripos($msg, 'Multiple primary key defined') !== false;

            if ($isBenign) {
                $skippedCount++;
                $errorCount--; // Undo the increment
                echo "  [$stmtNum] SKIPPED (benign): " . mb_substr($msg, 0, 120) . PHP_EOL;
            } else {
                echo "  [$stmtNum] ERROR: " . mb_substr($msg, 0, 200) . PHP_EOL;
                echo "       SQL: " . mb_substr($sql, 0, 200) . PHP_EOL;
                LoggerUtility::logError("Interface migration exception in $migrationFile: $msg\nSQL: $sql");
            }
        }
    }

    $db->connection('interface')->rawQuery("SET FOREIGN_KEY_CHECKS = 1");
    echo PHP_EOL;
}

// --- Summary ---

echo "====================================" . PHP_EOL;
echo "Interface Migration Summary" . PHP_EOL;
echo "====================================" . PHP_EOL;
echo "Total statements:  $totalStatements" . PHP_EOL;
echo "Successful:        $successCount" . PHP_EOL;
echo "Skipped (benign):  $skippedCount" . PHP_EOL;
echo "Errors:            $errorCount" . PHP_EOL;
echo "====================================" . PHP_EOL;

exit($errorCount > 0 ? CLI\ERROR : 0);
