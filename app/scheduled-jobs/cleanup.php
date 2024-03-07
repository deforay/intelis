#!/usr/bin/env php
<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once(__DIR__ . "/../../bootstrap.php");

use App\Services\DatabaseService;
use App\Utilities\MiscUtility;
use App\Registries\ContainerRegistry;

// Default global duration to delete in days
$defaultDurationToDeleteDays = 30;

// Directory specific durations in days (key-value pairs)
$directoryDurations = [
    ROOT_PATH . DIRECTORY_SEPARATOR . 'backups' => 30, // 30 days for backups
    WEB_ROOT . DIRECTORY_SEPARATOR . 'temporary' => 7,  // 7 days for temporary files
    // Add more directories and their durations here
];

$cleanup = [
    ROOT_PATH . DIRECTORY_SEPARATOR . 'backups',
    WEB_ROOT . DIRECTORY_SEPARATOR . 'temporary',
    // Add more directories here if needed
];

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

foreach ($cleanup as $folder) {
    // Determine the duration for the current directory, or use the default
    $days = $directoryDurations[$folder] ?? $defaultDurationToDeleteDays;
    $durationToDelete = $days * 86400; // Convert days to seconds

    if (file_exists($folder)) {
        foreach (new DirectoryIterator($folder) as $fileInfo) {
            // Skip .htaccess or index.php in the temporary directory
            if (
                $fileInfo->getPathname() === WEB_ROOT . DIRECTORY_SEPARATOR . 'temporary' . DIRECTORY_SEPARATOR . '.htaccess' ||
                $fileInfo->getFilename() === 'index.php'
            ) {
                continue;
            }

            if (!$fileInfo->isDot() && (time() - $fileInfo->getCTime() >= $durationToDelete)) {
                if ($fileInfo->isFile() || $fileInfo->isLink()) {
                    unlink($fileInfo->getRealPath());
                } elseif ($fileInfo->isDir()) {
                    MiscUtility::removeDirectory($fileInfo->getRealPath());
                }
            }
        }
    }
}
