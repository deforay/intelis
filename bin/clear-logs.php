#!/usr/bin/env php
<?php

use App\Utilities\MiscUtility;

// bin/clear-logs.php
// Removes generated log files while keeping sentinel files intact.
// Options:
//   --keep=<number>   Keep the newest N log files (default: 0)
//   --days=<number>   Keep log files newer than N days (default: 0)

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit(0);
}

$options = getopt('', ['keep::', 'days::']);
$keepCount = isset($options['keep']) ? max(0, (int)$options['keep']) : 0;
$keepDays = isset($options['days']) ? max(0, (int)$options['days']) : 0;

$projectRoot = dirname(__DIR__);
$logDir = $projectRoot . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'logs';

if (!is_dir($logDir)) {
    fwrite(STDERR, "[clear-logs] Log directory not found: {$logDir}" . PHP_EOL);
    exit(1);
}

$preserveFiles = ['.hgkeep', '.htaccess'];
$removedFiles = 0;
$removedDirs = 0;
$errors = [];
$files = [];
$directories = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $logDir,
        FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
    ),
    RecursiveIteratorIterator::CHILD_FIRST
);

foreach ($iterator as $entry) {
    $path = $entry->getPathname();

    if ($entry->isDir()) {
        $directories[] = $path;
        continue;
    }

    if (in_array($entry->getFilename(), $preserveFiles, true)) {
        continue;
    }

    $files[] = [
        'path' => $path,
        'mtime' => $entry->getMTime(),
    ];
}

$keepByCount = [];
$keepByDays = [];

if ($keepCount > 0 && !empty($files)) {
    $sortedByMtimeDesc = $files;
    usort($sortedByMtimeDesc, fn($a, $b) => $b['mtime'] <=> $a['mtime']); // newest first
    $filesToKeep = array_slice($sortedByMtimeDesc, 0, $keepCount);
    foreach ($filesToKeep as $fileInfo) {
        $keepByCount[$fileInfo['path']] = true;
    }
}

if ($keepDays > 0) {
    $minTimestamp = time() - ($keepDays * 86400);
    foreach ($files as $fileInfo) {
        if ($fileInfo['mtime'] >= $minTimestamp) {
            $keepByDays[$fileInfo['path']] = true;
        }
    }
}

$pathsToKeep = $keepByCount + $keepByDays;

foreach ($files as $fileInfo) {
    if (isset($pathsToKeep[$fileInfo['path']])) {
        continue;
    }

    if (MiscUtility::deleteFile($fileInfo['path'])) {
        $removedFiles++;
    } else {
        $errors[] = $fileInfo['path'];
    }
}

if (!empty($directories)) {
    usort($directories, fn($a, $b) => strlen($b) <=> strlen($a)); // deepest first
    foreach ($directories as $dirPath) {
        if ($dirPath === $logDir) {
            continue;
        }
        if (@rmdir($dirPath)) {
            $removedDirs++;
        }
    }
}

$summaryParts = [];
if ($keepCount > 0) {
    $summaryParts[] = sprintf("kept %d newest file(s)", count($keepByCount));
}
if ($keepDays > 0) {
    $summaryParts[] = sprintf("kept files from the last %d day(s) (%d file(s))", $keepDays, count($keepByDays));
}

$message = sprintf(
    "[clear-logs] Removed %d file(s)%s.",
    $removedFiles,
    $removedDirs > 0 ? " and pruned {$removedDirs} directories" : ''
);

if (!empty($summaryParts)) {
    $message .= PHP_EOL . "[clear-logs] Preserved " . implode(' and ', $summaryParts) . ".";
}

echo $message . PHP_EOL;

if (!empty($errors)) {
    fwrite(STDERR, "[clear-logs] Failed to remove the following paths:" . PHP_EOL);
    foreach ($errors as $errorPath) {
        fwrite(STDERR, "  - {$errorPath}" . PHP_EOL);
    }
    exit(2);
}

exit(0);
