#!/usr/bin/env php
<?php

/**
 * Ensure the runtime directories the app writes to exist and are writable.
 *
 * Creates the var/* tree, upload/temp dirs, backups and configs if missing,
 * then (when run as root, e.g. from composer post-install / post-update under
 * sudo) sets owner:group to the invoking user : www-data and makes them
 * group-writable so both the CLI user and the web server can write.
 *
 * This complements the setfacl sweep in scripts/shared-functions.sh
 * (set_permissions), which only fixes permissions on paths that already exist
 * and never creates missing directories.
 *
 * Idempotent and non-fatal: it warns about anything it cannot create or chown
 * but always exits 0, so it is safe at the head of the composer hook chain.
 *
 * Usage:
 *   php bin/provision.php            create + fix permissions
 *   php bin/provision.php --dry-run  report what would change, touch nothing
 */

declare(strict_types=1);

use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\ConsoleOutput;

$io = null;

try {
    require_once __DIR__ . "/../bootstrap.php";

    $io = new SymfonyStyle(new ArgvInput(), new ConsoleOutput());

    if (PHP_SAPI !== 'cli') {
        $io->error("provision can only be run from the command line.");
        exit(CLI\ERROR);
    }

    $flags = array_slice($argv, 1);
    $dryRun = in_array('--dry-run', $flags, true);

    // The web server group. Matches scripts/shared-functions.sh::set_permissions.
    $webGroup = 'www-data';
    // Owner to grant, robust under sudo: invoking user, else current, else root.
    $owner = getenv('SUDO_USER') ?: (getenv('USER') ?: 'root');

    // Directories the app writes to at runtime. Roots are enough — runtime code
    // lazily creates leaf subdirs (audit-trail/<type>, uploads/lab, logs/db…),
    // which succeeds once the parent exists and is group-writable. A few
    // well-known leaves are listed so they get correct ownership up front.
    $required = [
        VAR_PATH,
        CACHE_PATH,
        LOG_PATH,
        VAR_PATH . '/audit-trail',
        VAR_PATH . '/metadata',
        BACKUP_PATH,
        UPLOAD_PATH,
        UPLOAD_PATH . '/track-api',
        TEMP_PATH,
        CONFIG_PATH,
    ];
    // De-dup while preserving order (constants can overlap in odd layouts).
    $required = array_values(array_unique($required));

    $isRoot = function_exists('posix_geteuid') ? posix_geteuid() === 0 : false;
    $haveGroup = function_exists('posix_getgrnam') && posix_getgrnam($webGroup) !== false;

    $io->title('Provision runtime directories');
    if ($dryRun) {
        $io->note('Dry run — nothing will be created or changed.');
    }
    $io->text([
        "Owner  : <info>$owner</info>",
        "Group  : <info>$webGroup</info>" . ($haveGroup ? '' : ' <comment>(not present on this host — ownership skipped)</comment>'),
        "As root: <info>" . ($isRoot ? 'yes' : 'no — permissions left to set_permissions / web server') . "</info>",
    ]);

    $created = 0;
    $problems = [];

    foreach ($required as $dir) {
        $existed = is_dir($dir);

        if (!$existed) {
            if ($dryRun) {
                $io->text("  would create  <info>$dir</info>");
                $created++;
                continue;
            }
            if (!MiscUtility::makeDirectory($dir, 0775)) {
                $problems[] = "could not create $dir";
                continue;
            }
            $created++;
            $io->text("  created       <info>$dir</info>");
        }

        if ($dryRun) {
            continue;
        }

        // Group-writable so the CLI user and www-data can both write.
        @chmod($dir, 0775);

        // We are (usually) root here, so hand ownership to user:www-data.
        if ($isRoot) {
            if (!@chown($dir, $owner)) {
                $problems[] = "could not chown $dir to $owner";
            }
            if ($haveGroup && !@chgrp($dir, $webGroup)) {
                $problems[] = "could not chgrp $dir to $webGroup";
            }
        }

        if (!is_writable($dir)) {
            $problems[] = "$dir is not writable";
        }
    }

    if ($dryRun) {
        $io->success("Dry run complete — $created director" . ($created === 1 ? 'y' : 'ies') . " would be created.");
        exit(CLI\OK);
    }

    if ($problems !== []) {
        // Warn but never fail the hook chain — exit 0 by design.
        MiscUtility::consoleWarn(array_merge(
            ['Provisioning completed with warnings:'],
            array_map(static fn(string $p): string => "  - $p", $problems),
            $isRoot ? [] : ['Re-run as root (or via the composer hook under sudo) to fix ownership.']
        ));
        LoggerUtility::logWarning('provision: completed with warnings', ['problems' => $problems]);
        exit(CLI\OK);
    }

    $io->success($created > 0
        ? "Provisioned runtime directories ($created created)."
        : "Runtime directories already in place — nothing to do.");
    exit(CLI\OK);
} catch (Throwable $e) {
    // Even on unexpected failure, don't break post-install / post-update.
    if ($io !== null) {
        MiscUtility::consoleWarn("Provision skipped: " . $e->getMessage());
    } else {
        fwrite(STDERR, "Provision skipped: " . $e->getMessage() . PHP_EOL);
    }
    LoggerUtility::logError("provision failure: " . $e->getMessage(), [
        'line' => $e->getLine(),
        'file' => $e->getFile(),
        'trace' => $e->getTraceAsString(),
    ]);
    exit(CLI\OK);
}
