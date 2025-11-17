#!/usr/bin/env php
<?php
// bin/cleanup.php
// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once __DIR__ . "/../bootstrap.php";

use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;

// Create output instance
$output = new ConsoleOutput();

// Define custom styles
$output->getFormatter()->setStyle('fire', new OutputFormatterStyle('red', null, ['bold']));
$output->getFormatter()->setStyle('success', new OutputFormatterStyle('green', null, ['bold']));
$output->getFormatter()->setStyle('warning', new OutputFormatterStyle('yellow'));
$output->getFormatter()->setStyle('info', new OutputFormatterStyle('cyan'));

/**
 * Get directory size in bytes
 */
function getDirectorySize(string $path): int
{
    $size = 0;
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
    } catch (Throwable $e) {
        LoggerUtility::logWarning("Failed to calculate directory size for {$path}: " . $e->getMessage());
    }
    return $size;
}

/**
 * Format bytes to human readable
 */
function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= 1024 ** $pow;
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Fast directory cleanup using native Linux commands
 *
 * @param string $folder Directory to clean
 * @param int|null $duration Days to keep (null = no age limit)
 * @param int|null $maxSizeBytes Max folder size in bytes (null = no size limit)
 * @param ConsoleOutput $output Console output
 * @return array Stats: files_deleted, dirs_deleted, bytes_freed, errors
 */
function cleanupDirectory(string $folder, ?int $duration, ?int $maxSizeBytes, ConsoleOutput $output): array
{
    $stats = [
        'files_deleted' => 0,
        'dirs_deleted'  => 0,   // <-- add this
        'bytes_freed'   => 0,
        'errors'        => 0,
    ];

    if (!file_exists($folder) || !is_dir($folder)) {
        $output->writeln("<warning>Directory does not exist: {$folder}</warning>");
        return $stats;
    }

    // Get current size with fast du command
    exec("du -sb " . escapeshellarg($folder) . " 2>/dev/null", $duOutput, $exitCode);
    $currentSize = ($exitCode === 0 && (isset($duOutput[0]) && ($duOutput[0] !== '' && $duOutput[0] !== '0')))
        ? (int)explode("\t", $duOutput[0])[0]
        : 0;

    $output->writeln("\n<info>Processing:</info> " . basename($folder));
    $output->writeln("  <comment>Path:</comment> {$folder}");
    $output->writeln("  <comment>Current size:</comment> <info>" . formatBytes($currentSize) . "</info>");

    if ($maxSizeBytes && $currentSize > $maxSizeBytes) {
        $output->writeln("  <fire>⚠ WARNING: Exceeds limit of " . formatBytes($maxSizeBytes) . "</fire>");
    }

    // Determine cleanup strategy
    $needsSizeCleanup = $maxSizeBytes !== null && $currentSize > $maxSizeBytes;
    $needsAgeCleanup  = $duration !== null;

    if (!$needsSizeCleanup && !$needsAgeCleanup) {
        $output->writeln("  <info>ℹ No cleanup needed</info>");
        // Still attempt to prune empty directories
        $stats['dirs_deleted'] += pruneEmptyDirs($folder);
        return $stats;
    }

    if ($needsAgeCleanup && !$needsSizeCleanup) {
        $cutoffTimestamp = time() - ($duration * 86400);
        $output->writeln("  <comment>Deleting files older than {$duration} day(s)...</comment>");

        $startTime = microtime(true);
        $deleted = 0;
        $bytesFreed = 0;
        $errors = 0;

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $fileInfo) {
                if (!$fileInfo->isFile()) {
                    continue;
                }

                $mtime = $fileInfo->getMTime();
                if ($mtime >= $cutoffTimestamp) {
                    continue;
                }

                $path = $fileInfo->getPathname();
                $size = $fileInfo->getSize();

                if (MiscUtility::deleteFile($path)) {
                    $deleted++;
                    $bytesFreed += $size;
                } else {
                    $errors++;
                }
            }
        } catch (Throwable $e) {
            $output->writeln("  <fire>✗ Failed while scanning directory: {$e->getMessage()}</fire>");
            $stats['errors']++;
        }

        $elapsed = microtime(true) - $startTime;

        if ($deleted > 0) {
            $output->writeln("  <success>✓ Deleted:</success> {$deleted} files in " . round($elapsed, 2) . "s");
            $output->writeln("  <success>✓ Freed:</success> " . formatBytes($bytesFreed));
        } else {
            $output->writeln("  <info>ℹ No files to delete</info>");
        }

        if ($errors > 0) {
            $output->writeln("  <fire>✗ Errors:</fire> {$errors}");
        }

        $stats['files_deleted'] += $deleted;
        $stats['bytes_freed'] += $bytesFreed;
        $stats['errors'] += $errors;
        $stats['dirs_deleted'] += pruneEmptyDirs($folder);

        LoggerUtility::logInfo("Age-based cleanup completed for {$folder}", [
            'files_deleted' => $deleted,
            'bytes_freed' => $bytesFreed,
            'duration_seconds' => round($elapsed, 2),
        ]);

        return $stats;
    }

    $filesToDelete = [];
    $bytesToFree   = 0;
    $targetSize    = $maxSizeBytes ? (int)($maxSizeBytes * 0.8) : 0; // Clean to 80% of limit
    $streamingSucceeded = false;

    // Try streaming with find + sort to avoid loading everything into memory
    $findCmd = sprintf(
        'find %s -type f ! -name \'.htaccess\' ! -name \'index.php\' -printf \'%%T@|%%p|%%s\n\' 2>/dev/null | sort -n',
        escapeshellarg($folder)
    );

    $descriptorSpec = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($findCmd, $descriptorSpec, $pipes);
    if (is_resource($process)) {
        fclose($pipes[0]);
        $streamingSucceeded = true;
        while (($line = fgets($pipes[1])) !== false) {
            $parts = explode('|', trim($line));
            if (count($parts) !== 3) {
                continue;
            }

            $mtime = (float)$parts[0];
            $path = $parts[1];
            $size = (int)$parts[2];
            $ageDays = (time() - (int)$mtime) / 86400;

            $shouldDelete = false;
            if ($needsAgeCleanup && $ageDays > $duration) {
                $shouldDelete = true;
            }
            if ($needsSizeCleanup && $currentSize > $targetSize) {
                $shouldDelete = true;
            }

            if ($shouldDelete) {
                $filesToDelete[] = $path;
                $bytesToFree += $size;
                $currentSize -= $size;
            }

            if ($needsSizeCleanup && $currentSize <= $targetSize) {
                break;
            }
        }

        fclose($pipes[1]);
        $errorOutput = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        proc_close($process);

        if ($errorOutput !== '') {
            $streamingSucceeded = false;
        }
    }

    if (!$streamingSucceeded) {
        $files = [];
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if (!$file->isFile()) {
                    continue;
                }
                $basename = $file->getFilename();
                if (in_array($basename, ['.htaccess', 'index.php'], true)) {
                    continue;
                }
                $mtime = $file->getMTime();
                $files[] = [
                    'mtime'     => (float)$mtime,
                    'path'      => $file->getPathname(),
                    'size'      => (int)$file->getSize(),
                    'age_days'  => (time() - (int)$mtime) / 86400,
                ];
            }
        } catch (Throwable $e) {
            $output->writeln("  <fire>✗ Failed to enumerate files: {$e->getMessage()}</fire>");
        }

        if ($files !== []) {
            usort($files, fn($a, $b): int => $a['mtime'] <=> $b['mtime']);
            foreach ($files as $file) {
                $shouldDelete = false;
                if ($needsAgeCleanup && $file['age_days'] > $duration) {
                    $shouldDelete = true;
                }
                if ($needsSizeCleanup && $currentSize > $targetSize) {
                    $shouldDelete = true;
                }

                if ($shouldDelete) {
                    $filesToDelete[] = $file['path'];
                    $bytesToFree += $file['size'];
                    $currentSize  -= $file['size'];
                }

                if ($needsSizeCleanup && $currentSize <= $targetSize) {
                    break;
                }
            }
        }
    }

    if ($filesToDelete === []) {
        $output->writeln("  <info>ℹ No files to delete</info>");
        $stats['dirs_deleted'] += pruneEmptyDirs($folder);
        return $stats;
    }

    $fileCount = count($filesToDelete);
    $output->writeln("  <comment>Deleting {$fileCount} files (" . formatBytes($bytesToFree) . ")...</comment>");

    $startTime = microtime(true);
    $deleted = 0;
    $errors  = 0;

    foreach ($filesToDelete as $filePath) {
        if (MiscUtility::deleteFile($filePath)) {
            $deleted++;
        } else {
            $errors++;
        }
    }

    $elapsed = microtime(true) - $startTime;

    $stats['files_deleted'] = $deleted;
    $stats['bytes_freed']   = $bytesToFree;
    $stats['errors']        = $errors;

    $output->writeln("  <success>✓ Deleted:</success> {$deleted} files in " . round($elapsed, 2) . "s");
    $output->writeln("  <success>✓ Freed:</success> " . formatBytes($bytesToFree));

    if ($errors > 0) {
        $output->writeln("  <fire>✗ Errors:</fire> {$errors}");
    }

    // Prune empty directories and count them
    $stats['dirs_deleted'] += pruneEmptyDirs($folder);

    LoggerUtility::logInfo("Cleanup completed for {$folder}", [
        'files_deleted'     => $deleted,
        'dirs_deleted'      => $stats['dirs_deleted'],
        'bytes_freed'       => $bytesToFree,
        'duration_seconds'  => round($elapsed, 2),
    ]);

    return $stats;
}

/**
 * Remove empty directories under $folder and return how many were deleted.
 */
function pruneEmptyDirs(string $folder): int
{
    $toDelete = [];
    exec("find " . escapeshellarg($folder) . " -type d -empty -print 2>/dev/null", $toDelete);
    $count = is_array($toDelete) ? count($toDelete) : 0;
    if ($count > 0) {
        // Do the actual deletion
        exec("find " . escapeshellarg($folder) . " -type d -empty -delete 2>/dev/null");
    }
    return $count;
}


/**
 * Run db-tools.php clean with retention args and pretty-print its output.
 * Returns the process exit code.
 */
function runDbToolsClean(?int $keep, ?int $days, ConsoleOutput $output): int
{
    $args = [];
    if ($keep !== null) {
        $args[] = '--keep=' . (int)$keep;
    }
    if ($days !== null) {
        $args[] = '--days=' . (int)$days;
    }

    // Fallback: require at least one policy
    if ($args === []) {
        $args[] = '--days=30';
    }

    $script = BIN_PATH . DIRECTORY_SEPARATOR . 'db-tools.php';
    if (!is_file($script)) {
        $output->writeln("<fire>✗ db-tools.php not found at: {$script}</fire>");
        return 1;
    }

    // Build: /usr/bin/php bin/db-tools.php clean --days=30 ...
    $cmd = array_merge([PHP_BINARY, $script, 'clean'], $args);

    $descriptorSpec = [
        0 => ['pipe', 'r'], // stdin
        1 => ['pipe', 'w'], // stdout
        2 => ['pipe', 'w'], // stderr
    ];

    $proc = proc_open($cmd, $descriptorSpec, $pipes, ROOT_PATH, []);
    if (!is_resource($proc)) {
        $output->writeln("<fire>✗ Failed to start db-tools.php clean</fire>");
        return 1;
    }

    // Nothing to send to stdin — cleanup is non-interactive now
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]) ?: '';
    $stderr = stream_get_contents($pipes[2]) ?: '';
    fclose($pipes[1]);
    fclose($pipes[2]);

    $code = proc_close($proc);

    if ($stdout !== '') {
        foreach (explode("\n", rtrim($stdout)) as $line) {
            $output->writeln('  ' . $line);
        }
    }
    if ($stderr !== '') {
        foreach (explode("\n", rtrim($stderr)) as $line) {
            $output->writeln('  <fire>' . $line . '</fire>');
        }
    }

    return (int)$code;
}


$defaultDuration = (isset($argv[1]) && is_numeric($argv[1])) ? (int)$argv[1] : 30;


// ============================================================================
// MAIN SCRIPT
// ============================================================================

$output->writeln('');
$output->writeln('<success>CLEANUP SCRIPT STARTED: ' . date('Y-m-d H:i:s') . '</success>');
$output->writeln(str_repeat('=', 80));
$output->writeln('');

$output->writeln("<info>Default retention:</info> <comment>{$defaultDuration} days</comment>\n");

// DB BACKUP CLEANUP (PITR-aware via db-tools.php)
$output->writeln('<info>DB BACKUP CLEANUP</info>');
$output->writeln(str_repeat('-', 80));

// Choose your policy source (env > defaultDuration)
$keepEnv = getenv('INTELIS_DB_BACKUP_KEEP');
$daysEnv = getenv('INTELIS_DB_BACKUP_DAYS');

// Only pass --keep if explicitly provided; otherwise prefer --days
$keepForDbBackups = is_numeric($keepEnv) ? (int)$keepEnv : null;
$daysForDbBackups = ($keepForDbBackups !== null)
    ? null
    : (is_numeric($daysEnv) ? (int)$daysEnv : $defaultDuration);

$exit = runDbToolsClean($keepForDbBackups, $daysForDbBackups, $output);

if ($exit === 0) {
    $output->writeln("<success>✓ db-tools.php clean completed</success>\n");
} else {
    $output->writeln("<fire>✗ db-tools.php clean exited with code {$exit}</fire>\n");
}



// Directory configurations
$cleanup = [
    LOG_PATH => [
        'duration' => null,
        'max_size_mb' => 1000
    ],
    TEMP_PATH => [
        'duration' => 3,
        'max_size_mb' => 500
    ],
    VAR_PATH . DIRECTORY_SEPARATOR . 'track-api' . DIRECTORY_SEPARATOR . 'requests' => [
        'duration' => 120,
        'max_size_mb' => 1000
    ],
    VAR_PATH . DIRECTORY_SEPARATOR . 'track-api' . DIRECTORY_SEPARATOR . 'responses' => [
        'duration' => 120,
        'max_size_mb' => 1000
    ],
];

$totalStats = [
    'files_deleted' => 0,
    'dirs_deleted' => 0,
    'bytes_freed' => 0,
    'errors' => 0
];

// FILE SYSTEM CLEANUP
$output->writeln('<info>FILE SYSTEM CLEANUP</info>');
$output->writeln(str_repeat('-', 80));


foreach ($cleanup as $folder => $config) {
    $duration = $config['duration'] ?? $defaultDuration;
    $maxSize = isset($config['max_size_mb']) ? $config['max_size_mb'] * 1024 * 1024 : null;

    $stats = cleanupDirectory($folder, $duration, $maxSize, $output);

    $totalStats['files_deleted'] += $stats['files_deleted'];
    $totalStats['dirs_deleted'] += $stats['dirs_deleted'];
    $totalStats['bytes_freed'] += $stats['bytes_freed'];
    $totalStats['errors'] += $stats['errors'];
}

// File System Summary Table
$output->writeln("\n<info>File System Summary:</info>");
$table = new Table($output);
$table->setHeaders(['Metric', 'Value']);
$table->setRows([
    ['Files Deleted', $totalStats['files_deleted']],
    ['Directories Deleted', $totalStats['dirs_deleted']],
    ['Space Freed', formatBytes($totalStats['bytes_freed'])],
    ['Errors', $totalStats['errors'] > 0 ? "<fire>{$totalStats['errors']}</fire>" : "<success>0</success>"],
]);
$table->render();

// DATABASE CLEANUP
$output->writeln("\n<info>DATABASE CLEANUP</info>");
$output->writeln(str_repeat('-', 80));
$output->writeln('');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$tablesToCleanup = [
    'activity_log' => [
        'condition' => 'date_time < NOW() - INTERVAL 365 DAY',
        'description' => 'activity logs older than 365 days'
    ],
    'user_login_history' => [
        'condition' => 'login_attempted_datetime < NOW() - INTERVAL 365 DAY',
        'description' => 'login history older than 365 days'
    ],
    'track_api_requests' => [
        'condition' => 'requested_on < NOW() - INTERVAL 365 DAY',
        'description' => 'API requests older than 365 days'
    ],
];

$dbStats = [
    'tables_processed' => 0,
    'rows_deleted' => 0,
    'errors' => 0
];

foreach ($tablesToCleanup as $table => $config) {
    $output->write("<info>Processing table:</info> <comment>{$table}</comment>... ");

    try {
        $db->where($config['condition']);
        $countBefore = $db->getValue($table, 'COUNT(*)');

        if ($countBefore > 0) {
            $db->where($config['condition']);
            if ($db->delete($table)) {
                $output->writeln("<success>✓ Deleted {$countBefore} rows</success>");
                $dbStats['rows_deleted'] += $countBefore;
                LoggerUtility::logInfo("Deleted {$countBefore} rows from {$table}");
            } else {
                $output->writeln("<fire>✗ ERROR: " . $db->getLastError() . "</fire>");
                $dbStats['errors']++;
                LoggerUtility::logError("Error deleting from {$table}: " . $db->getLastError());
            }
        } else {
            $output->writeln("<info>ℹ No rows to delete</info>");
        }

        $dbStats['tables_processed']++;
    } catch (Throwable $e) {
        $output->writeln("<fire>✗ ERROR: {$e->getMessage()}</fire>");
        $dbStats['errors']++;
        LoggerUtility::logError("Exception cleaning up {$table}: " . $e->getMessage());
    }
}

// Database Summary Table
$output->writeln("\n<info>Database Summary:</info>");
$table = new Table($output);
$table->setHeaders(['Metric', 'Value']);
$table->setRows([
    ['Tables Processed', $dbStats['tables_processed']],
    ['Rows Deleted', $dbStats['rows_deleted']],
    ['Errors', $dbStats['errors'] > 0 ? "<fire>{$dbStats['errors']}</fire>" : "<success>0</success>"],
]);
$table->render();

// FINAL SUMMARY
$output->writeln('');
$output->writeln('<success>CLEANUP COMPLETED: ' . date('Y-m-d H:i:s') . '</success>');
$output->writeln(str_repeat('=', 80));
$output->writeln('');

// Total Summary Table
$output->writeln("<info>Total Summary:</info>");
$table = new Table($output);
$table->setHeaders(['Category', 'Metric', 'Value']);
$table->setRows([
    ['File System', 'Files Deleted', $totalStats['files_deleted']],
    ['File System', 'Directories Deleted', $totalStats['dirs_deleted']],
    ['File System', 'Space Freed', formatBytes($totalStats['bytes_freed'])],
    ['Database', 'Rows Deleted', $dbStats['rows_deleted']],
    ['Overall', 'Total Errors', ($totalStats['errors'] + $dbStats['errors']) > 0
        ? "<fire>" . ($totalStats['errors'] + $dbStats['errors']) . "</fire>"
        : "<success>0</success>"],
]);
$table->render();
$output->writeln('');

LoggerUtility::logInfo("Cleanup script completed", [
    'files_deleted' => $totalStats['files_deleted'],
    'dirs_deleted' => $totalStats['dirs_deleted'],
    'space_freed_bytes' => $totalStats['bytes_freed'],
    'db_rows_deleted' => $dbStats['rows_deleted'],
    'total_errors' => $totalStats['errors'] + $dbStats['errors']
]);

exit(0);
