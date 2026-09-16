<?php

declare(strict_types=1);

namespace App\Utilities;

use DateTimeImmutable;
use FilesystemIterator;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Where the request and response bodies of tracked API calls live, and how they
 * are cleared away.
 *
 * Bodies go in var/track-api/{requests,responses}/YYYY/MM/DD/<transaction>.json.zst.
 * They used to go straight into the two flat folders, and on an STS serving dozens
 * of labs those reached several million files each, with a directory index of
 * hundreds of megabytes that ext4 never shrinks. Everything that walked them --
 * housekeeping's find | sort, the permission passes -- kept the disk busy for hours.
 *
 * With one folder per day, nothing is ever large, and expiring old bodies is
 * deleting whole day folders by name without looking inside them. The old flat
 * folders are renamed aside in one step and deleted in the background at idle
 * priority; the database rows are what matter, and are kept for a year anyway.
 */
final class ApiTrackingStorageUtility
{
    public const KINDS = ['requests', 'responses'];

    /** Bodies are kept this long. The track_api_requests rows are kept for 365 days. */
    public const RETENTION_DAYS = 30;

    private const PURGE_MARKER = '.purge-';

    public static function root(): string
    {
        return VAR_PATH . DIRECTORY_SEPARATOR . 'track-api';
    }

    public static function kindRoot(string $kind): string
    {
        return self::root() . DIRECTORY_SEPARATOR . $kind;
    }

    /** The day folder a call made at $requestedOn (Y-m-d H:i:s) keeps its bodies in. */
    public static function dayDirectory(string $kind, string $requestedOn): string
    {
        $date = preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $requestedOn, $m) === 1
            ? [$m[1], $m[2], $m[3]]
            : explode('-', date('Y-m-d'));

        return self::kindRoot($kind) . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $date);
    }

    /**
     * Where to look for a call's body: its day folder, then the flat folder that
     * calls recorded before the dated layout still point at.
     *
     * @return list<string>
     */
    public static function candidateDirectories(string $kind, ?string $requestedOn): array
    {
        $dirs = [];
        if (!empty($requestedOn)) {
            $dirs[] = self::dayDirectory($kind, $requestedOn);
        }
        $dirs[] = self::kindRoot($kind);
        return $dirs;
    }

    /**
     * Day folders older than the retention window, found from their names alone.
     *
     * @return list<string>
     */
    public static function expiredDayDirectories(string $kind, DateTimeImmutable $today, int $retentionDays): array
    {
        $cutoff = $today->setTime(0, 0)->modify("-{$retentionDays} days")->format('Y-m-d');
        $expired = [];

        foreach (self::numberedChildren(self::kindRoot($kind), 4) as $year) {
            foreach (self::numberedChildren($year, 2) as $month) {
                foreach (self::numberedChildren($month, 2) as $day) {
                    $date = basename($year) . '-' . basename($month) . '-' . basename($day);
                    if ($date < $cutoff) {
                        $expired[] = $day;
                    }
                }
            }
        }

        return $expired;
    }

    /** Remove month and year folders left empty once their days are gone. */
    public static function removeEmptyDateFolders(string $kind): void
    {
        foreach (self::numberedChildren(self::kindRoot($kind), 4) as $year) {
            foreach (self::numberedChildren($year, 2) as $month) {
                @rmdir($month); // only succeeds when empty
            }
            @rmdir($year);
        }
    }

    /**
     * Whether a kind folder still holds bodies in the old flat layout.
     *
     * Stops at the first file. The dated layout holds nothing but year folders at
     * this level, so this reads a handful of entries either way -- never the
     * millions a flat folder may hold.
     */
    public static function hasFlatFiles(string $kind): bool
    {
        $dir = self::kindRoot($kind);
        if (!is_dir($dir)) {
            return false;
        }

        try {
            foreach (new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS) as $entry) {
                if ($entry->isFile() && !str_starts_with($entry->getFilename(), '.')) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    /**
     * Rename a flat kind folder aside and put an empty one with the same owner,
     * mode and ACL in its place. A rename is one metadata operation however many
     * files the folder holds, and the new folder starts with a small index again.
     *
     * @return string|null the renamed folder, to delete later; null if nothing moved
     */
    public static function moveAsideFlatFolder(string $kind, DateTimeImmutable $now): ?string
    {
        $dir = self::kindRoot($kind);
        $stat = @stat($dir);
        if ($stat === false) {
            return null;
        }

        $aside = $dir . self::PURGE_MARKER . $now->format('YmdHis');
        if (!@rename($dir, $aside)) {
            return null;
        }

        // A writer may already have recreated it in the moment between the two.
        if (!is_dir($dir)) {
            @mkdir($dir, $stat['mode'] & 0777);
        }
        @chmod($dir, $stat['mode'] & 07777);
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            @chown($dir, $stat['uid']);
            @chgrp($dir, $stat['gid']);
        }
        self::copyAcl($aside, $dir);

        return $aside;
    }

    /**
     * Renamed-aside folders still waiting to be deleted, including any left by a
     * purge that was interrupted.
     *
     * @return list<string>
     */
    public static function pendingPurges(): array
    {
        $found = [];
        foreach (self::KINDS as $kind) {
            foreach (glob(self::kindRoot($kind) . self::PURGE_MARKER . '*', GLOB_ONLYDIR) ?: [] as $dir) {
                $found[] = $dir;
            }
        }
        return $found;
    }

    /**
     * Delete folders at idle I/O priority so the deletion never competes with the
     * application for the disk. Returns the folders that could not be removed.
     *
     * @param list<string> $dirs
     * @return list<string>
     */
    public static function deleteAtLowPriority(array $dirs): array
    {
        $failed = [];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            $command = ['rm', '-rf', '--', $dir];
            if (self::commandExists('nice')) {
                $command = ['nice', '-n', '19', ...$command];
            }
            if (self::commandExists('ionice')) {
                $command = ['ionice', '-c3', ...$command];
            }

            // Explicit cwd: the caller's may be inside a folder that was just renamed.
            $process = new Process($command, '/');
            $process->setTimeout(null);
            $process->run();

            if (is_dir($dir)) {
                $failed[] = $dir;
            }
        }
        return $failed;
    }

    /** @return list<string> subfolders whose names are exactly $digits digits, sorted */
    private static function numberedChildren(string $dir, int $digits): array
    {
        if (!is_dir($dir)) {
            return [];
        }
        $children = [];
        try {
            foreach (new FilesystemIterator($dir, FilesystemIterator::SKIP_DOTS) as $entry) {
                if ($entry->isDir() && preg_match('/^\d{' . $digits . '}$/', $entry->getFilename()) === 1) {
                    $children[] = $entry->getPathname();
                }
            }
        } catch (Throwable) {
            return [];
        }
        sort($children);
        return $children;
    }

    private static function copyAcl(string $from, string $to): void
    {
        if (!self::commandExists('getfacl') || !self::commandExists('setfacl')) {
            return;
        }
        $process = Process::fromShellCommandline(
            'getfacl -p -- "$FROM" 2>/dev/null | setfacl --set-file=- -- "$TO" 2>/dev/null',
            '/'
        );
        $process->run(null, ['FROM' => $from, 'TO' => $to]);
    }

    private static function commandExists(string $name): bool
    {
        static $known = [];
        if (!array_key_exists($name, $known)) {
            $process = Process::fromShellCommandline('command -v "$NAME"', '/');
            $process->run(null, ['NAME' => $name]);
            $known[$name] = $process->isSuccessful();
        }
        return $known[$name];
    }
}
