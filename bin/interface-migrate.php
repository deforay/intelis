#!/usr/bin/env php
<?php

// bin/interface-migrate.php
// Force-run vlsm-interfacing migrations (MySQL + SQLite) against the local/remote
// interface databases. Migration files are fetched from the GitHub repo and
// executed one statement at a time. Errors are logged but do not stop execution
// (permissive mode).
//
// Two phases:
//   1. MySQL  -- against SYSTEM_CONFIG['interfacing']['database'] (remote or local)
//   2. SQLite -- uses SYSTEM_CONFIG['interfacing']['sqlite3Path'] if set (any OS);
//                otherwise, on Linux only, globs /home/*/.config/vlsm-interfacing/interface.db.
//                Skipped silently if no DB is found.
//
// Usage:
//   php bin/interface-migrate.php                    run all migrations
//   php bin/interface-migrate.php --from=005.sql     start MySQL from a specific file
//   php bin/interface-migrate.php --status           list pending files, exit
//   php bin/interface-migrate.php --dry-run          preview statements without executing

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

$options = getopt("", ["from:", "status", "dry-run"]);
$fromFile = $options['from'] ?? null;
$showStatus = isset($options['status']);
$dryRun = isset($options['dry-run']);

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

if ($showStatus) {
    echo PHP_EOL . "Pending migrations:" . PHP_EOL;
    if (empty($migrationFiles)) {
        echo "  (none)" . PHP_EOL;
    } else {
        foreach ($migrationFiles as $f) {
            echo "  $f" . PHP_EOL;
        }
    }
    exit(0);
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

    if ($dryRun) {
        foreach ($statements as $idx => $sql) {
            $totalStatements++;
            $preview = mb_substr(preg_replace('/\s+/', ' ', $sql), 0, 100);
            echo "  [" . ($idx + 1) . "] WOULD RUN  $preview" . PHP_EOL;
        }
        echo PHP_EOL;
        continue;
    }

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

// --- MySQL Summary ---

echo "====================================" . PHP_EOL;
echo "MySQL Interface Migration Summary" . PHP_EOL;
echo "====================================" . PHP_EOL;
if ($dryRun) {
    echo "Mode:              DRY RUN (no changes made)" . PHP_EOL;
}
echo "Total statements:  $totalStatements" . PHP_EOL;
if (!$dryRun) {
    echo "Successful:        $successCount" . PHP_EOL;
    echo "Skipped (benign):  $skippedCount" . PHP_EOL;
    echo "Errors:            $errorCount" . PHP_EOL;
}
echo "====================================" . PHP_EOL;

// --- SQLite migrations (Ubuntu/Linux only) ---
// The Electron app (vlsm-interfacing) keeps its local DB at
// ~/.config/vlsm-interfacing/interface.db per user. Discover all such DBs on
// the machine and force-apply the latest sqlite-migrations/*.sql from GitHub.

$sqliteErrors = 0;

if (!extension_loaded('pdo_sqlite')) {
    echo PHP_EOL . "SQLite phase skipped: pdo_sqlite extension not loaded." . PHP_EOL;
    echo "  Install with: sudo apt install php-sqlite3" . PHP_EOL;
    exit($errorCount > 0 ? CLI\ERROR : 0);
}

// 1. Prefer explicit path from VLSM config if set (honoured on any OS).
// 2. Otherwise, glob standard Electron userData locations — Linux only.
$sqliteDbCandidates = [];
$configuredSqlitePath = SYSTEM_CONFIG['interfacing']['sqlite3Path'] ?? '';
if (is_string($configuredSqlitePath) && $configuredSqlitePath !== '') {
    if (is_file($configuredSqlitePath)) {
        $sqliteDbCandidates[] = $configuredSqlitePath;
        echo PHP_EOL . "Using SQLite DB from VLSM config: $configuredSqlitePath" . PHP_EOL;
    } else {
        echo PHP_EOL . "WARN: VLSM config sqlite3Path is set but file not found: $configuredSqlitePath" . PHP_EOL;
        if (PHP_OS_FAMILY === 'Linux') {
            echo "      Falling back to filesystem search." . PHP_EOL;
        }
    }
}

if (empty($sqliteDbCandidates) && PHP_OS_FAMILY === 'Linux') {
    $sqliteDbCandidates = array_merge(
        glob('/home/*/.config/vlsm-interfacing/interface.db') ?: [],
        glob('/root/.config/vlsm-interfacing/interface.db') ?: []
    );
}

if (empty($sqliteDbCandidates)) {
    echo PHP_EOL . "SQLite phase skipped: no interface.db found (set interfacing.sqlite3Path in VLSM config or run on Linux where it can be auto-discovered)." . PHP_EOL;
    exit($errorCount > 0 ? CLI\ERROR : 0);
}

echo PHP_EOL . "====================================" . PHP_EOL;
echo "SQLite Interface Migrations" . PHP_EOL;
echo "====================================" . PHP_EOL;
echo "NOTE: Close the vlsm-interfacing Electron app before proceeding - WAL contention risk." . PHP_EOL . PHP_EOL;

echo "Found " . count($sqliteDbCandidates) . " SQLite DB(s):" . PHP_EOL;
foreach ($sqliteDbCandidates as $p) {
    echo "  $p" . PHP_EOL;
}
echo PHP_EOL;

$sqliteRepoApiUrl = 'https://api.github.com/repos/deforay/vlsm-interfacing/contents/app/sqlite-migrations?ref=master';
$sqliteRawBaseUrl = 'https://raw.githubusercontent.com/deforay/vlsm-interfacing/master/app/sqlite-migrations/';

echo "Fetching SQLite migration list from GitHub..." . PHP_EOL;
$sqliteApiResponse = @file_get_contents($sqliteRepoApiUrl, false, $context);
if ($sqliteApiResponse === false) {
    echo "Failed to fetch SQLite migration list from GitHub." . PHP_EOL;
    exit($errorCount > 0 ? CLI\ERROR : 0);
}
$sqliteFilesMeta = json_decode($sqliteApiResponse, true);
if (!is_array($sqliteFilesMeta)) {
    echo "Unexpected response from GitHub API." . PHP_EOL;
    exit($errorCount > 0 ? CLI\ERROR : 0);
}

$sqliteMigrationFiles = [];
foreach ($sqliteFilesMeta as $file) {
    if (($file['type'] ?? '') === 'file' && str_ends_with($file['name'], '.sql')) {
        $sqliteMigrationFiles[] = $file['name'];
    }
}
sort($sqliteMigrationFiles, SORT_NATURAL);

if (empty($sqliteMigrationFiles)) {
    echo "No SQLite migration files found." . PHP_EOL;
    exit($errorCount > 0 ? CLI\ERROR : 0);
}

echo "Found " . count($sqliteMigrationFiles) . " SQLite migration file(s): " . implode(', ', $sqliteMigrationFiles) . PHP_EOL . PHP_EOL;

// Pre-fetch all contents once - avoids N-DBs x M-files HTTP calls
$sqliteContents = [];
foreach ($sqliteMigrationFiles as $file) {
    $content = @file_get_contents($sqliteRawBaseUrl . $file, false, $context);
    if ($content === false) {
        echo "  WARN: Failed to fetch $file, will skip." . PHP_EOL;
        continue;
    }
    $sqliteContents[$file] = $content;
}

$sqliteStmtTotal = 0;
$sqliteSuccess = 0;
$sqliteSkipped = 0;

foreach ($sqliteDbCandidates as $dbPath) {
    echo "--- DB: $dbPath ---" . PHP_EOL;

    try {
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (Throwable $e) {
        echo "  ERROR: Failed to open DB: " . $e->getMessage() . PHP_EOL;
        $sqliteErrors++;
        continue;
    }

    // Ensure versions table exists for bookkeeping
    try {
        $pdo->query('CREATE TABLE IF NOT EXISTS versions (version INTEGER PRIMARY KEY, filename TEXT, applied_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
        try {
            $pdo->query('ALTER TABLE versions ADD COLUMN filename TEXT');
        } catch (Throwable) {
        }
    } catch (Throwable $e) {
        echo "  WARN: Could not prepare versions table: " . $e->getMessage() . PHP_EOL;
    }

    // Always run all migrations from 001.sql regardless of versions table
    $pending = array_values(array_filter(
        $sqliteMigrationFiles,
        fn($f) => isset($sqliteContents[$f])
    ));

    if (empty($pending)) {
        echo "  (no migration files to run)" . PHP_EOL . PHP_EOL;
        $pdo = null;
        continue;
    }

    if ($showStatus) {
        echo "  Migrations:" . PHP_EOL;
        foreach ($pending as $f) {
            echo "    $f" . PHP_EOL;
        }
        echo PHP_EOL;
        $pdo = null;
        continue;
    }

    foreach ($pending as $file) {
        echo "  $file:" . PHP_EOL;
        $sqlContent = $sqliteContents[$file];

        // Same splitter as Electron (main.ts:150-155): strip `--` comment lines, split on `;`
        $lines = explode("\n", $sqlContent);
        $cleanLines = array_filter($lines, fn($l) => !str_starts_with(trim($l), '--'));
        $clean = implode("\n", $cleanLines);
        $statements = array_values(array_filter(
            array_map('trim', explode(';', $clean)),
            fn($s) => $s !== ''
        ));

        if ($dryRun) {
            foreach ($statements as $idx => $s) {
                $sqliteStmtTotal++;
                $preview = mb_substr(preg_replace('/\s+/', ' ', $s), 0, 100);
                echo "    [" . ($idx + 1) . "] WOULD RUN  $preview" . PHP_EOL;
            }
            continue;
        }

        foreach ($statements as $idx => $s) {
            $sqliteStmtTotal++;
            $stmtNum = $idx + 1;
            try {
                $pdo->query($s);
                $sqliteSuccess++;
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                $isBenign =
                    stripos($msg, 'duplicate column') !== false ||
                    stripos($msg, 'already exists') !== false;
                if ($isBenign) {
                    $sqliteSkipped++;
                    echo "    [$stmtNum] SKIPPED (benign): " . mb_substr($msg, 0, 120) . PHP_EOL;
                } else {
                    $sqliteErrors++;
                    echo "    [$stmtNum] ERROR: " . mb_substr($msg, 0, 200) . PHP_EOL;
                    echo "         SQL: " . mb_substr($s, 0, 200) . PHP_EOL;
                    LoggerUtility::logError("SQLite interface migration error in $file ($dbPath): $msg\nSQL: $s");
                }
            }
        }

        $version = (int) $file;
        try {
            $stmt = $pdo->prepare('INSERT OR REPLACE INTO versions (version, filename) VALUES (?, ?)');
            $stmt->execute([$version, $file]);
        } catch (Throwable $e) {
            echo "    WARN: Failed to record applied version: " . $e->getMessage() . PHP_EOL;
        }
    }

    $pdo = null;
    echo PHP_EOL;
}

echo "====================================" . PHP_EOL;
echo "SQLite Migration Summary" . PHP_EOL;
echo "====================================" . PHP_EOL;
if ($dryRun) {
    echo "Mode:              DRY RUN (no changes made)" . PHP_EOL;
}
echo "DBs found:         " . count($sqliteDbCandidates) . PHP_EOL;
echo "Total statements:  $sqliteStmtTotal" . PHP_EOL;
if (!$dryRun) {
    echo "Successful:        $sqliteSuccess" . PHP_EOL;
    echo "Skipped (benign):  $sqliteSkipped" . PHP_EOL;
    echo "Errors:            $sqliteErrors" . PHP_EOL;
}
echo "====================================" . PHP_EOL;

exit(($errorCount + $sqliteErrors) > 0 ? CLI\ERROR : 0);
