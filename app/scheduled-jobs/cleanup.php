#!/usr/bin/env php
<?php

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once(__DIR__ . "/../../bootstrap.php");

use App\Utilities\MiscUtility;
use App\Registries\ContainerRegistry;

// Default duration to delete
$defaultDurationToDelete = 180;

// Get the number of days from command line argument, if provided
$days = $argv[1] ?? $defaultDurationToDelete; // $argv[0] is the script name itself
$durationToDelete = $days * 86400; // Convert days to seconds

$cleanup = [
    APPLICATION_PATH . DIRECTORY_SEPARATOR . 'backups',
    WEB_ROOT . DIRECTORY_SEPARATOR . 'temporary',
];

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

foreach ($cleanup as $folder) {
    if (file_exists($folder)) {
        foreach (new DirectoryIterator($folder) as $fileInfo) {
            // Skip if it's .htaccess or index.php in the temporary directory
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
