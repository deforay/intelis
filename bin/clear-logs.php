#!/usr/bin/env php
<?php

/**
 * Remove generated log files from var/logs. Sentinel files (.gitkeep,
 * .hgkeep, .htaccess) are always preserved; empty subdirectories are pruned.
 *
 * Usage:
 *   php bin/clear-logs.php --keep=10        keep the 10 newest log files
 *   php bin/clear-logs.php --days=7         keep files modified in the last 7 days
 *   php bin/clear-logs.php --keep=10 --days=7   union of the two (file kept if EITHER matches)
 *   php bin/clear-logs.php --all            remove ALL logs (must be explicit)
 *   php bin/clear-logs.php --dry-run …      report what would be removed without deleting
 *
 * Refuses to run with no retention argument — pass --all to wipe everything.
 */

require __DIR__ . '/lib/help.php';
bin_help_if_requested(__FILE__);

use App\Utilities\MiscUtility;

require_once __DIR__ . '/../bootstrap.php';

if (PHP_SAPI !== 'cli') {
    exit(CLI\ERROR);
}

$options = getopt('', ['keep:', 'days:', 'all', 'dry-run']);
$keepCount = isset($options['keep']) ? max(0, (int) $options['keep']) : 0;
$keepDays  = isset($options['days']) ? max(0, (int) $options['days']) : 0;
$wipeAll   = isset($options['all']);
$dryRun    = isset($options['dry-run']);

if (!$wipeAll && $keepCount === 0 && $keepDays === 0) {
    MiscUtility::consoleError(
        'Refusing to wipe ALL logs. Pass --all to confirm, or use --keep / --days.'
    );
    exit(CLI\ERROR);
}

$logDir = defined('LOG_PATH') ? LOG_PATH : dirname(__DIR__) . '/var/logs';
if (!is_dir($logDir)) {
    MiscUtility::consoleError("Log directory not found: {$logDir}");
    exit(CLI\ERROR);
}

$preserveFiles = ['.gitkeep', '.hgkeep', '.htaccess'];
$files = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(
        $logDir,
        FilesystemIterator::SKIP_DOTS | FilesystemIterator::CURRENT_AS_FILEINFO
    ),
    RecursiveIteratorIterator::CHILD_FIRST
);

foreach ($iterator as $entry) {
    if ($entry->isDir()) {
        continue;
    }
    if (in_array($entry->getFilename(), $preserveFiles, true)) {
        continue;
    }
    $files[] = [
        'path'  => $entry->getPathname(),
        'mtime' => $entry->getMTime(),
        'size'  => (int) $entry->getSize(),
    ];
}

$keepByCount = [];
$keepByDays  = [];

if ($keepCount > 0 && $files !== []) {
    $sortedByMtimeDesc = $files;
    usort($sortedByMtimeDesc, fn($a, $b): int => $b['mtime'] <=> $a['mtime']);
    foreach (array_slice($sortedByMtimeDesc, 0, $keepCount) as $fileInfo) {
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

$removed    = 0;
$bytesFreed = 0;
$errors     = [];

foreach ($files as $fileInfo) {
    if (isset($pathsToKeep[$fileInfo['path']])) {
        continue;
    }
    if ($dryRun) {
        $removed++;
        $bytesFreed += $fileInfo['size'];
        continue;
    }
    if (MiscUtility::deleteFile($fileInfo['path'])) {
        $removed++;
        $bytesFreed += $fileInfo['size'];
    } else {
        $errors[] = $fileInfo['path'];
    }
}

// Prune empty subdirectories (real-run only). The log dir itself is left alone.
$prunedDirs = 0;
if (!$dryRun) {
    $dirIter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($logDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($dirIter as $entry) {
        if (!$entry->isDir()) {
            continue;
        }
        if (@rmdir($entry->getPathname())) {
            $prunedDirs++;
        }
    }
}

$parts = [
    sprintf('%s %d file(s)', $dryRun ? 'Would remove' : 'Removed', $removed),
    formatBytes($bytesFreed),
];
if ($prunedDirs > 0) {
    $parts[] = sprintf('%d empty dir(s) pruned', $prunedDirs);
}
$summary = implode(', ', $parts) . '.';

if ($errors === []) {
    MiscUtility::consoleSuccess($summary);
    exit(CLI\OK);
}

MiscUtility::consoleError($summary . ' Some files could not be deleted:');
foreach ($errors as $errorPath) {
    fwrite(STDERR, "  - {$errorPath}" . PHP_EOL);
}
exit(CLI\ERROR);

function formatBytes(int $bytes): string
{
    if ($bytes <= 0) {
        return '0 B';
    }
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $pow = min((int) floor(log($bytes) / log(1024)), count($units) - 1);
    return sprintf('%.2f %s', $bytes / (1024 ** $pow), $units[$pow]);
}
