#!/usr/bin/env php
<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once(__DIR__ . "/../../bootstrap.php");

use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

$duration = (isset($argv[1]) && is_numeric($argv[1])) ? (int)$argv[1] : 30;


// Directory specific durations in days (key-value pairs)
$directoryDurations = [
    ROOT_PATH . DIRECTORY_SEPARATOR . 'backups' => $duration, // Use parameter or default for backups
    WEB_ROOT . DIRECTORY_SEPARATOR . 'temporary' => 3,  // Keep fixed duration for temporary files or adjust as needed
    // Adjust or add more directories and their durations here
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
    $days = $directoryDurations[$folder] ?? $duration;
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
