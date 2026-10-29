#!/usr/bin/env php
<?php

// Recursively tar+gzip CONFIG_PATH, runtime info and the uploaded files that
// shape a result report (signatures, report templates, logos) into
// BACKUP_PATH/config. Keeps the newest CONFIG_BACKUP_KEEP archives (default 7).

use App\Utilities\MiscUtility;

require_once __DIR__ . "/../bootstrap.php";

// only run from command line
$isCli = PHP_SAPI === 'cli';
if ($isCli === false) {
    exit(CLI\ERROR);
}


$CONFIG_PATH = rtrim(CONFIG_PATH, '/');
$BACKUP_PATH = rtrim(BACKUP_PATH, '/');
$DEST_DIR = "$BACKUP_PATH/config";
$KEEP = (int) (getenv('CONFIG_BACKUP_KEEP') ?: 7);
$PREFIX = 'config-';
$EXT = '.tgz';

if (!is_dir($CONFIG_PATH)) {
    fwrite(STDERR, "Bad CONFIG_PATH: $CONFIG_PATH\n");
    exit(CLI\ERROR);
}
@mkdir($DEST_DIR, 0775, true);

$ts = date('Y-m-d_H-i-s');
$file = $PREFIX . $ts . $EXT;
$out = $DEST_DIR . '/' . $file;
// Built under a .tmp name (the off-machine backup skips *.tmp) and renamed into
// place, so a failed or interrupted tar never leaves an archive that looks good
// and counts toward retention.
$partial = $out . '.tmp';

// --- collect runtime info into temp (do NOT touch CONFIG_PATH) ---
$tmp = sys_get_temp_dir() . "/cfginfo-$ts-" . bin2hex(random_bytes(3));
@mkdir($tmp, 0700, true);

file_put_contents(
    "$tmp/system-info.txt",
    "### PHP VERSION\n" . (shell_exec('php -v 2>&1') ?: '') .
    "\n### PHP INI\n" . (shell_exec('php --ini 2>&1') ?: '') .
    "\n### MYSQL VERSION\n" . (shell_exec('mysql --version 2>&1') ?: '') .
    "\n### CRONTAB\n" . ((shell_exec('crontab -l 2>&1') ?: "no crontab or not permitted\n"))
);

// Exclusions (edit as needed)
$excludes = [
    '--exclude=.git',
    '--exclude=.DS_Store',
    '--exclude=cache',
    '--exclude=node_modules',
    '--exclude=*.key',
    '--exclude=*.pem',
];

// Uploads that cannot be rebuilt and that every result report needs. They go in
// under uploads/ so they unpack straight back into public/. Only folders that
// exist are listed, because tar fails on a missing one.
$uploadDirs = array_merge(
    [
        'uploads/users-signature',
        'uploads/facility-logo',
        'uploads/logo',
        'uploads/login-logos',
        'uploads/stamps',
        'uploads/labs/report-template',
    ],
    array_map(
        fn($dir): string => substr($dir, strlen(WEB_ROOT) + 1),
        array_merge(
            glob(UPLOAD_PATH . '/labs/*/signatures', GLOB_ONLYDIR) ?: [],
            glob(UPLOAD_PATH . '/labs/*/report-template', GLOB_ONLYDIR) ?: []
        )
    )
);
$uploadDirs = array_values(array_filter($uploadDirs, fn($dir): bool => is_dir(WEB_ROOT . '/' . $dir)));
// Written into every backup, even when no folder exists. A restore that finds
// no uploads/ in the archive then knows whether the lab had none or the backup
// predates them, and says which.
file_put_contents("$tmp/uploads-included.txt", implode("\n", $uploadDirs) . "\n");
$uploadsInput = $uploadDirs === [] ? '' : sprintf(
    ' -C %s %s',
    escapeshellarg(WEB_ROOT),
    implode(' ', array_map('escapeshellarg', $uploadDirs))
);

// Build one archive from CONFIG_PATH (.), $tmp (.) and the upload folders.
// The archive holds the database password, so only its owner may read it.
$cmd = sprintf(
    'umask 077 && tar -czf %s %s -C %s . -C %s .%s',
    escapeshellarg($partial),
    implode(' ', $excludes),
    escapeshellarg($CONFIG_PATH),
    escapeshellarg($tmp),
    $uploadsInput
);

// Run tar and capture status
exec($cmd, $outLines, $code);

// Cleanup temp
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST
);
foreach ($it as $p) {
    $p->isDir() ? @rmdir($p->getPathname()) : MiscUtility::deleteFile($p->getPathname());
}
@rmdir($tmp);

if ($code !== 0 || !file_exists($partial)) {
    MiscUtility::deleteFile($partial);
    fwrite(STDERR, "Backup failed (code=$code). Command:\n$cmd\n");
    exit(CLI\ERROR);
}
@chmod($partial, 0600);
if (!rename($partial, $out)) {
    MiscUtility::deleteFile($partial);
    fwrite(STDERR, "Backup failed: could not move the archive into place at $out\n");
    exit(CLI\ERROR);
}

// Archives written before this change were world-readable.
foreach (glob($DEST_DIR . '/' . $PREFIX . '*' . $EXT) ?: [] as $existing) {
    @chmod($existing, 0600);
}

echo "✅ Config Backed up : $out\n";

// --- retention: keep latest $KEEP matching files, delete the rest ---
$files = glob($DEST_DIR . '/' . $PREFIX . '*' . $EXT);
if ($files !== false && count($files) > $KEEP) {
    // Sort by mtime desc (newest first)
    usort($files, fn($a, $b): int => filemtime($b) <=> filemtime($a));
    $toDelete = array_slice($files, $KEEP);
    foreach ($toDelete as $old) {
        MiscUtility::deleteFile($old);
    }
    echo "ℹ️  Retention: kept $KEEP, removed " . count($toDelete) . " older backup(s).\n";
}

exit(CLI\OK);
