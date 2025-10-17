#!/usr/bin/env php
<?php
// bin/cleanup.php - Symfony Console Version
// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once __DIR__ . "/../bootstrap.php";

use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\ProgressBar;
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
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Cleanup a directory with progress bar
 */
function cleanupDirectory(string $folder, ?int $duration, ?int $maxSizeBytes, ConsoleOutput $output): array
{
    $stats = [
        'files_deleted' => 0,
        'dirs_deleted' => 0,
        'bytes_freed' => 0,
        'errors' => 0
    ];

    if (!file_exists($folder) || !is_dir($folder)) {
        $output->writeln("<warning>Directory does not exist: {$folder}</warning>");
        LoggerUtility::logWarning("Directory does not exist: {$folder}");
        return $stats;
    }

    $durationToDelete = $duration * 86400;
    $currentSize = getDirectorySize($folder);

    $output->writeln("\n<info>Processing:</info> " . basename($folder));
    $output->writeln("  <comment>Path:</comment> {$folder}");
    $output->writeln("  <comment>Current size:</comment> <info>" . formatBytes($currentSize) . "</info>");

    if ($maxSizeBytes && $currentSize > $maxSizeBytes) {
        $output->writeln("  <fire>⚠ WARNING: Exceeds limit of " . formatBytes($maxSizeBytes) . "</fire>");
    }

    try {
        $files = [];

        // Collect files
        $output->write("  <comment>Scanning files...</comment>");
        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            ) as $fileInfo
        ) {
            if (in_array($fileInfo->getFilename(), ['.htaccess', 'index.php'])) {
                continue;
            }

            $files[] = [
                'path' => $fileInfo->getRealPath(),
                'is_dir' => $fileInfo->isDir(),
                'size' => $fileInfo->isFile() ? $fileInfo->getSize() : 0,
                'mtime' => $fileInfo->getMTime(),
                'age_days' => (time() - $fileInfo->getMTime()) / 86400
            ];
        }
        $output->writeln(" <success>✓</success> Found " . count($files) . " items");

        if (empty($files)) {
            return $stats;
        }

        // Sort by modification time (oldest first)
        usort($files, fn($a, $b) => $a['mtime'] <=> $b['mtime']);

        // Create progress bar
        $progressBar = new ProgressBar($output, count($files));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% | %message%');
        $progressBar->setMessage('Deleting old files...');
        $progressBar->start();

        // Delete files
        foreach ($files as $file) {
            $shouldDelete = false;

            if ($duration && (time() - $file['mtime']) >= $durationToDelete) {
                $shouldDelete = true;
            }

            if ($maxSizeBytes && $currentSize > $maxSizeBytes) {
                $shouldDelete = true;
            }

            if ($shouldDelete) {
                try {
                    if ($file['is_dir']) {
                        if (MiscUtility::removeDirectory($file['path'])) {
                            $stats['dirs_deleted']++;
                        }
                    } else {
                        if (@unlink($file['path'])) {
                            $stats['files_deleted']++;
                            $stats['bytes_freed'] += $file['size'];
                            $currentSize -= $file['size'];
                        }
                    }
                } catch (Throwable $e) {
                    $stats['errors']++;
                    LoggerUtility::logError("Failed to delete {$file['path']}: " . $e->getMessage());
                }

                if ($maxSizeBytes && $currentSize <= ($maxSizeBytes * 0.8)) {
                    break;
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $output->writeln("\n");

        // Summary
        if ($stats['files_deleted'] > 0 || $stats['dirs_deleted'] > 0) {
            $output->writeln("  <success>✓ Deleted:</success> {$stats['files_deleted']} files, {$stats['dirs_deleted']} directories");
            $output->writeln("  <success>✓ Freed:</success> " . formatBytes($stats['bytes_freed']));

            LoggerUtility::logInfo("Cleanup completed for {$folder}", [
                'files_deleted' => $stats['files_deleted'],
                'dirs_deleted' => $stats['dirs_deleted'],
                'bytes_freed' => $stats['bytes_freed']
            ]);
        } else {
            $output->writeln("  <info>ℹ No files to delete</info>");
        }

        if ($stats['errors'] > 0) {
            $output->writeln("  <fire>✗ Errors:</fire> {$stats['errors']}");
        }
    } catch (Throwable $e) {
        $stats['errors']++;
        $output->writeln("\n  <fire>✗ Error: {$e->getMessage()}</fire>");
        LoggerUtility::logError("Error processing directory {$folder}: " . $e->getMessage());
    }

    return $stats;
}

// ============================================================================
// MAIN SCRIPT
// ============================================================================

$output->writeln('');
$output->writeln('<success>CLEANUP SCRIPT STARTED: ' . date('Y-m-d H:i:s') . '</success>');
$output->writeln(str_repeat('=', 80));
$output->writeln('');

$defaultDuration = (isset($argv[1]) && is_numeric($argv[1])) ? (int)$argv[1] : 30;
$output->writeln("<info>Default retention:</info> <comment>{$defaultDuration} days</comment>\n");

// Directory configurations
$cleanup = [
    ROOT_PATH . DIRECTORY_SEPARATOR . 'backups' => [
        'duration' => null,
        'max_size_mb' => 20000,
    ],
    ROOT_PATH . DIRECTORY_SEPARATOR . 'logs' => [
        'duration' => null,
        'max_size_mb' => 1000
    ],
    WEB_ROOT . DIRECTORY_SEPARATOR . 'temporary' => [
        'duration' => 3,
        'max_size_mb' => 500
    ],
    UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api' . DIRECTORY_SEPARATOR . 'requests' => [
        'duration' => 120,
        'max_size_mb' => 2000
    ],
    UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api' . DIRECTORY_SEPARATOR . 'responses' => [
        'duration' => 120,
        'max_size_mb' => 2000
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
    'space_freed_bytes' => $totalStats['bytes_freed'],
    'db_rows_deleted' => $dbStats['rows_deleted'],
    'total_errors' => $totalStats['errors'] + $dbStats['errors']
]);

exit(0);
