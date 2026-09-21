#!/usr/bin/env php
<?php

// Recursively tar+gzip CONFIG_PATH plus runtime info into BACKUP_PATH/config.
// Keeps last BACKUP_KEEP archives (default 7).

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

// Build one archive with two inputs: CONFIG_PATH (.) and $tmp (.)
// The archive holds the database password, so only its owner may read it.
$cmd = sprintf(
    'umask 077 && tar -czf %s %s -C %s . -C %s .',
    escapeshellarg($partial),
    implode(' ', $excludes),
    escapeshellarg($CONFIG_PATH),
    escapeshellarg($tmp)
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
