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
 * Cleanup a directory based on age and/or size constraints
 */
function cleanupDirectory(string $folder, ?int $duration, ?int $maxSizeBytes = null): array
{
    $stats = [
        'files_deleted' => 0,
        'dirs_deleted' => 0,
        'bytes_freed' => 0,
        'errors' => 0
    ];

    if (!file_exists($folder) || !is_dir($folder)) {
        LoggerUtility::logWarning("Directory does not exist: {$folder}");
        return $stats;
    }

    $durationToDelete = $duration * 86400; // Convert days to seconds
    $currentSize = getDirectorySize($folder);

    echo "Processing: {$folder}\n";
    echo "  Current size: " . formatBytes($currentSize) . "\n";

    if ($maxSizeBytes && $currentSize > $maxSizeBytes) {
        echo "  WARNING: Exceeds limit of " . formatBytes($maxSizeBytes) . "\n";
    }

    try {
        $files = [];

        // Collect all files with their info
        foreach (
            new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($folder, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            ) as $fileInfo
        ) {
            // Skip .htaccess and index.php at any level
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

        // Sort by modification time (oldest first)
        usort($files, fn($a, $b) => $a['mtime'] <=> $b['mtime']);

        // Delete files based on age or size
        foreach ($files as $file) {
            $shouldDelete = false;
            $reason = '';

            // Delete if older than duration
            if ($duration && (time() - $file['mtime']) >= $durationToDelete) {
                $shouldDelete = true;
                $reason = "older than {$duration} days";
            }

            // Delete if directory exceeds max size (delete oldest files first)
            if ($maxSizeBytes && $currentSize > $maxSizeBytes) {
                $shouldDelete = true;
                $reason = "directory exceeds size limit";
            }

            if ($shouldDelete) {
                try {
                    if ($file['is_dir']) {
                        if (MiscUtility::removeDirectory($file['path'])) {
                            $stats['dirs_deleted']++;
                            LoggerUtility::logDebug("Deleted directory: {$file['path']} ({$reason})");
                        }
                    } else {
                        if (@unlink($file['path'])) {
                            $stats['files_deleted']++;
                            $stats['bytes_freed'] += $file['size'];
                            $currentSize -= $file['size'];
                            LoggerUtility::logDebug("Deleted file: {$file['path']} ({$reason})");
                        }
                    }
                } catch (Throwable $e) {
                    $stats['errors']++;
                    LoggerUtility::logError("Failed to delete {$file['path']}: " . $e->getMessage());
                }

                // Stop if we're under the size limit
                if ($maxSizeBytes && $currentSize <= ($maxSizeBytes * 0.8)) {
                    break;
                }
            }
        }
    } catch (Throwable $e) {
        $stats['errors']++;
        LoggerUtility::logError("Error processing directory {$folder}: " . $e->getMessage());
    }

    echo "  Deleted: {$stats['files_deleted']} files, {$stats['dirs_deleted']} directories\n";
    echo "  Freed: " . formatBytes($stats['bytes_freed']) . "\n";
    if ($stats['errors'] > 0) {
        echo "  Errors: {$stats['errors']}\n";
    }
    echo "\n";

    return $stats;
}

// Start cleanup
echo "\n";
echo str_repeat("=", 80) . "\n";
echo "CLEANUP SCRIPT STARTED: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 80) . "\n\n";

$defaultDuration = (isset($argv[1]) && is_numeric($argv[1])) ? (int)$argv[1] : 30;
echo "Default retention: {$defaultDuration} days\n\n";

// Directory specific durations in days and size limits
$cleanup = [
    ROOT_PATH . DIRECTORY_SEPARATOR . 'backups' => [
        'duration' => null, // Use default
        'max_size_mb' => 20000, // 20GB warning threshold
        'min_keep_count' => 10,  // Always keep at least 10 most recent backups
        'max_keep_count' => 30, // Keep up to 30 backups if under size threshold
        'strategy' => 'smart_backup' // Use smart backup cleanup logic
    ],
    ROOT_PATH . DIRECTORY_SEPARATOR . 'logs' => [
        'duration' => null, // Use default
        'max_size_mb' => 1000 // 1GB max for logs (matches LoggerUtility)
    ],
    WEB_ROOT . DIRECTORY_SEPARATOR . 'temporary' => [
        'duration' => 3,
        'max_size_mb' => 500 // 500MB max for temporary
    ],
    UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api' . DIRECTORY_SEPARATOR . 'requests' => [
        'duration' => 120,
        'max_size_mb' => 2000 // 2GB max
    ],
    UPLOAD_PATH . DIRECTORY_SEPARATOR . 'track-api' . DIRECTORY_SEPARATOR . 'responses' => [
        'duration' => 120,
        'max_size_mb' => 2000 // 2GB max
    ],
    // Add more directories here as needed
];

$totalStats = [
    'files_deleted' => 0,
    'dirs_deleted' => 0,
    'bytes_freed' => 0,
    'errors' => 0
];

echo "FILE SYSTEM CLEANUP\n";
echo str_repeat("-", 80) . "\n\n";

foreach ($cleanup as $folder => $config) {
    $duration = $config['duration'] ?? $defaultDuration;
    $maxSize = isset($config['max_size_mb']) ? $config['max_size_mb'] * 1024 * 1024 : null;

    $stats = cleanupDirectory($folder, $duration, $maxSize);

    // Aggregate stats
    $totalStats['files_deleted'] += $stats['files_deleted'];
    $totalStats['dirs_deleted'] += $stats['dirs_deleted'];
    $totalStats['bytes_freed'] += $stats['bytes_freed'];
    $totalStats['errors'] += $stats['errors'];
}

echo str_repeat("-", 80) . "\n";
echo "File System Summary:\n";
echo "  Files deleted: {$totalStats['files_deleted']}\n";
echo "  Directories deleted: {$totalStats['dirs_deleted']}\n";
echo "  Space freed: " . formatBytes($totalStats['bytes_freed']) . "\n";
echo "  Errors: {$totalStats['errors']}\n\n";

// DATABASE CLEANUP
echo "DATABASE CLEANUP\n";
echo str_repeat("-", 80) . "\n\n";

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Tables and conditions to cleanup
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
    echo "Processing table: {$table}\n";
    echo "  Condition: {$config['description']}\n";

    try {
        // Count before deletion
        $db->where($config['condition']);
        $countBefore = $db->getValue($table, 'COUNT(*)');

        if ($countBefore > 0) {
            $db->where($config['condition']);
            if ($db->delete($table)) {
                echo "  Deleted: {$countBefore} rows\n";
                $dbStats['rows_deleted'] += $countBefore;
                LoggerUtility::logInfo("Deleted {$countBefore} rows from {$table}: {$config['description']}");
            } else {
                echo "  ERROR: " . $db->getLastError() . "\n";
                $dbStats['errors']++;
                LoggerUtility::logError("Error deleting from {$table}: " . $db->getLastError());
            }
        } else {
            echo "  No rows to delete\n";
        }

        $dbStats['tables_processed']++;
    } catch (Throwable $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
        $dbStats['errors']++;
        LoggerUtility::logError("Exception cleaning up {$table}: " . $e->getMessage());
    }

    echo "\n";
}

// AUDIT TABLES CLEANUP
echo "AUDIT TABLES CLEANUP\n";
echo str_repeat("-", 80) . "\n\n";

$metadataPath = ROOT_PATH . DIRECTORY_SEPARATOR . "metadata" . DIRECTORY_SEPARATOR . "archive.mdata.json";

if (file_exists($metadataPath)) {
    $metadata = json_decode(file_get_contents($metadataPath), true);

    $auditTables = [
        'audit_form_vl' => 'dt_datetime',
        'audit_form_eid' => 'dt_datetime',
        'audit_form_covid19' => 'dt_datetime',
        'audit_form_tb' => 'dt_datetime',
        'audit_form_hepatitis' => 'dt_datetime',
        'audit_form_generic' => 'dt_datetime',
    ];

    foreach ($auditTables as $table => $dateColumn) {
        echo "Processing audit table: {$table}\n";

        if (!empty($metadata[$table]['last_processed_date'])) {
            $lastProcessedDate = $metadata[$table]['last_processed_date'];
            $dateToDeleteBefore = date('Y-m-d H:i:s', strtotime($lastProcessedDate . ' - 3 days'));

            echo "  Last processed: {$lastProcessedDate}\n";
            echo "  Deleting before: {$dateToDeleteBefore}\n";

            try {
                // Count before deletion
                $db->where("{$dateColumn} < ?", [$dateToDeleteBefore]);
                $countBefore = $db->getValue($table, 'COUNT(*)');

                if ($countBefore > 0) {
                    $db->where("{$dateColumn} < ?", [$dateToDeleteBefore]);
                    if ($db->delete($table)) {
                        echo "  Deleted: {$countBefore} rows\n";
                        $dbStats['rows_deleted'] += $countBefore;
                        LoggerUtility::logInfo("Deleted {$countBefore} records from {$table} where {$dateColumn} < {$dateToDeleteBefore}");
                    } else {
                        echo "  ERROR: " . $db->getLastError() . "\n";
                        $dbStats['errors']++;
                        LoggerUtility::logError("Error deleting from {$table}: " . $db->getLastError());
                    }
                } else {
                    echo "  No rows to delete\n";
                }

                $dbStats['tables_processed']++;
            } catch (Throwable $e) {
                echo "  ERROR: " . $e->getMessage() . "\n";
                $dbStats['errors']++;
                LoggerUtility::logError("Exception cleaning up {$table}: " . $e->getMessage());
            }
        } else {
            echo "  Skipped: No last_processed_date in metadata\n";
        }

        echo "\n";
    }
} else {
    echo "Metadata file not found: {$metadataPath}\n";
    echo "Skipping audit table cleanup\n\n";
}

echo str_repeat("-", 80) . "\n";
echo "Database Summary:\n";
echo "  Tables processed: {$dbStats['tables_processed']}\n";
echo "  Rows deleted: {$dbStats['rows_deleted']}\n";
echo "  Errors: {$dbStats['errors']}\n\n";

// FINAL SUMMARY
echo str_repeat("=", 80) . "\n";
echo "CLEANUP COMPLETED: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 80) . "\n\n";

echo "Total Summary:\n";
echo "  Files deleted: {$totalStats['files_deleted']}\n";
echo "  Directories deleted: {$totalStats['dirs_deleted']}\n";
echo "  Space freed: " . formatBytes($totalStats['bytes_freed']) . "\n";
echo "  Database rows deleted: {$dbStats['rows_deleted']}\n";
echo "  Total errors: " . ($totalStats['errors'] + $dbStats['errors']) . "\n\n";

LoggerUtility::logInfo("Cleanup script completed", [
    'files_deleted' => $totalStats['files_deleted'],
    'space_freed_bytes' => $totalStats['bytes_freed'],
    'db_rows_deleted' => $dbStats['rows_deleted'],
    'total_errors' => $totalStats['errors'] + $dbStats['errors']
]);

exit(0);
