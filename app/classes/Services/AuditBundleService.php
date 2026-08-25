<?php

declare(strict_types=1);

namespace App\Services;

use Generator;
use ZipArchive;
use App\Utilities\LoggerUtility;

/**
 * Rolls settled per-sample audit files into one archive per test type per month.
 *
 * The audit archive stores one file per sample: var/audit-trail/{testKey}/{uniqueId}.csv.zst.
 * That shape is right for reading — the audit trail view asks for exactly one
 * sample and gets exactly one file — and wrong for everything else at scale. A
 * working lab accumulates one file per sample it has ever run, so the directory
 * reaches hundreds of thousands of entries and audit-trail alone reaches
 * gigabytes. Two costs follow, and neither is about the data:
 *
 *   - Block slack. A measured audit file is 2141 bytes of content occupying 4K
 *     on disk, and a sample with one revision wastes far more. Most of the
 *     gigabytes are the tail of a filesystem block, repeated per sample.
 *
 *   - Per-file cost in the backup. rsync stats every file on both ends to
 *     decide it has nothing to do. Over SMB that is a network round trip each,
 *     so an eight-hourly backup spends most of its time re-confirming that
 *     files which can no longer change still have not changed.
 *
 * Bundling addresses both at once: one archive per month per test type instead
 * of a file per sample.
 *
 * ZIP rather than tar.zst, deliberately. The reader does point lookups by
 * unique_id, and ZipArchive reads a single member by name from the central
 * directory without decompressing the archive; a solidly-compressed tar would
 * have to be unpacked in full to answer one lookup. It also keeps the archive
 * openable by any file manager, which is the property that lets an operator
 * recover a lab from a backup with no InteLIS installed. Compression stays
 * per-member, exactly as it is today with one .zst per sample, so the ratio is
 * unchanged and the win is file count and reclaimed slack.
 *
 * Only settled samples are bundled. AuditArchiveService rewrites a sample's
 * file whenever new audit rows arrive for it, so a file is mutable for as long
 * as the sample is being worked on. Bundling one of those would put the writer
 * and the bundle out of step, so a file is only eligible once it has not been
 * touched for SETTLE_DAYS. Should a bundled sample come back to life,
 * unbundleSample() lifts it out to a loose file again and the next pass
 * re-absorbs it.
 */
final class AuditBundleService
{
    /**
     * How long a sample's audit file must go untouched before it is bundled.
     *
     * Not a guess about how long a sample stays open — it is the window during
     * which a file may still be rewritten. Long enough that re-opening a
     * bundled sample is rare; short enough that the bulk of a lab's history is
     * bundled. Matches the ninety-day window the requests grid already treats
     * as "recent".
     */
    public const SETTLE_DAYS = 90;

    /**
     * Loose files moved per run.
     *
     * The first run on an existing lab has every sample it has ever recorded to
     * get through, and that is not work to do in one pass while the cron slot is
     * held and the audit view is being read. The backlog drains a chunk at a
     * time across runs, the way prune-legacy-audit-tables.php drains its own.
     */
    public const DEFAULT_BATCH = 5000;

    private const BUNDLE_PATTERN = '/^\d{4}-\d{2}\.zip$/';

    private static function archiveRoot(): string
    {
        return VAR_PATH . '/audit-trail';
    }

    /**
     * Bundle settled files for every test type. Returns per-type counts.
     *
     * @param callable|null $progress Optional progress callback(string $msg).
     * @return array{bundled:int, archives:int, skipped:int, errors:int}
     */
    public static function run(?int $batch = null, ?callable $progress = null): array
    {
        $batch = $batch ?? self::DEFAULT_BATCH;
        $totals = ['bundled' => 0, 'archives' => 0, 'skipped' => 0, 'errors' => 0];

        $root = self::archiveRoot();
        if (!is_dir($root)) {
            return $totals;
        }

        $remaining = $batch;

        foreach (self::testTypeDirs() as $testKey => $dir) {
            if ($remaining <= 0) {
                break;
            }

            $result = self::bundleTestType($testKey, $dir, $remaining, $progress);

            $totals['bundled']  += $result['bundled'];
            $totals['archives'] += $result['archives'];
            $totals['skipped']  += $result['skipped'];
            $totals['errors']   += $result['errors'];

            $remaining -= $result['bundled'];
        }

        return $totals;
    }

    /**
     * Bundle one test type's settled files, up to $limit of them.
     *
     * @return array{bundled:int, archives:int, skipped:int, errors:int}
     */
    private static function bundleTestType(
        string $testKey,
        string $dir,
        int $limit,
        ?callable $progress = null
    ): array {
        $result = ['bundled' => 0, 'archives' => 0, 'skipped' => 0, 'errors' => 0];

        // Group by the month the file was last written, so a bundle holds one
        // month of settled history.
        $byMonth = [];
        $cutoff  = time() - (self::SETTLE_DAYS * 86400);

        foreach (self::looseFiles($dir) as $path) {
            $mtime = @filemtime($path);
            if ($mtime === false) {
                $result['errors']++;
                continue;
            }

            // Still within the window in which the writer may rewrite it.
            if ($mtime > $cutoff) {
                $result['skipped']++;
                continue;
            }

            $byMonth[date('Y-m', $mtime)][] = $path;
        }

        // Oldest month first, and filled to completion before the next is
        // started. This is about what the backup has to carry, not about tidy
        // ordering. Adding to a ZIP rewrites it, so a bundle that gains members
        // on many consecutive days is a changed file on each of those days —
        // and while rsync's delta transfer would send only the new tail over
        // SSH, it uses --whole-file for a local or mounted destination, so SMB
        // and USB re-copy the entire archive every time. Draining one month at
        // a time means each bundle is written over as few runs as possible and
        // is then never touched again.
        ksort($byMonth, SORT_STRING);

        $budget = $limit;
        foreach ($byMonth as $month => $paths) {
            if ($budget <= 0) {
                $result['skipped'] += count($paths);
                continue;
            }
            if (count($paths) > $budget) {
                // Take part of this month now and the rest next run, rather than
                // starting a second month and leaving two archives unfinished.
                $paths = array_slice($paths, 0, $budget);
            }
            $budget -= count($paths);

            $added = self::addToBundle($dir, $month, $paths);
            if ($added < 0) {
                $result['errors'] += count($paths);
                continue;
            }
            $result['bundled'] += $added;
            $result['archives']++;

            if ($progress !== null) {
                $progress(sprintf('%s/%s.zip: %d file(s) bundled', $testKey, $month, $added));
            }
        }

        return $result;
    }

    /**
     * Add files to one month's archive and delete the originals.
     *
     * Returns the number added, or -1 if the archive could not be written.
     *
     * The originals are removed only after the archive has been closed and
     * reopened and each member confirmed present. Closing is where ZipArchive
     * actually writes, so a failure at that point with the loose files already
     * gone would lose audit history that exists nowhere else — these files ARE
     * the record once prune-legacy-audit-tables.php has dropped the tables.
     */
    private static function addToBundle(string $dir, string $month, array $paths): int
    {
        $bundlePath = $dir . DIRECTORY_SEPARATOR . $month . '.zip';

        $zip = new ZipArchive();
        $flags = file_exists($bundlePath) ? 0 : ZipArchive::CREATE;
        if ($zip->open($bundlePath, $flags) !== true) {
            LoggerUtility::logError('Could not open audit bundle', ['bundle' => $bundlePath]);
            return -1;
        }

        $staged = [];
        foreach ($paths as $path) {
            $member = basename($path);

            // A member of this name may already be here: an earlier run was
            // interrupted after writing but before deleting, or this sample was
            // unbundled, edited, and has now settled again. Either way the loose
            // file is the current one and the member is stale, so it is removed
            // and re-added rather than skipped. Skipping it would delete the
            // newer loose file below and leave the stale copy as the only
            // record — the exact history loss this class exists to protect.
            if ($zip->locateName($member) !== false) {
                $zip->deleteName($member);
            }

            if ($zip->addFile($path, $member) === false) {
                LoggerUtility::logError('Could not add file to audit bundle', [
                    'bundle' => $bundlePath,
                    'member' => $member,
                ]);
                $zip->close();
                return -1;
            }

            // Stored, not deflated. The member is already a .csv.zst or .csv.gz
            // and zstd beats what ZIP's deflate would achieve, so the members
            // keep their own compression and the archive is only a container.
            // Left to itself ZipArchive would deflate them a second time: real
            // CPU spent on bytes that are already incompressible, for a result
            // no smaller and occasionally larger.
            $zip->setCompressionName($member, ZipArchive::CM_STORE);

            $staged[$member] = $path;
        }

        if ($zip->close() !== true) {
            LoggerUtility::logError('Could not write audit bundle', ['bundle' => $bundlePath]);
            return -1;
        }

        // Reopen and confirm before deleting anything.
        $check = new ZipArchive();
        if ($check->open($bundlePath, ZipArchive::CHECKCONS) !== true) {
            LoggerUtility::logError('Audit bundle failed its consistency check', ['bundle' => $bundlePath]);
            return -1;
        }

        $removed = 0;
        foreach ($staged as $member => $path) {
            if ($check->locateName($member) === false) {
                LoggerUtility::logError('Audit bundle is missing a member it should hold', [
                    'bundle' => $bundlePath,
                    'member' => $member,
                ]);
                continue;
            }
            if (@unlink($path)) {
                $removed++;
            }
        }
        $check->close();

        return $removed;
    }

    /**
     * Lift one sample back out to a loose file so the writer can rewrite it.
     *
     * Called when audit rows arrive for a sample that has already been bundled.
     * The member is left in the archive rather than deleted: rewriting a ZIP to
     * remove one entry is expensive, the loose file takes precedence on read,
     * and the next bundling pass overwrites the stale member by name.
     *
     * Returns the loose path, or null if the sample is not in any bundle.
     */
    public static function unbundleSample(string $testType, string $uniqueId): ?string
    {
        $dir = self::archiveRoot() . DIRECTORY_SEPARATOR . $testType;
        if (!is_dir($dir)) {
            return null;
        }

        foreach (self::bundles($dir) as $bundlePath) {
            $zip = new ZipArchive();
            if ($zip->open($bundlePath) !== true) {
                continue;
            }

            foreach (self::memberNamesFor($uniqueId) as $member) {
                if ($zip->locateName($member) === false) {
                    continue;
                }

                $contents = $zip->getFromName($member);
                $zip->close();

                if ($contents === false) {
                    return null;
                }

                $loose = $dir . DIRECTORY_SEPARATOR . $member;
                if (file_put_contents($loose, $contents) === false) {
                    LoggerUtility::logError('Could not restore audit file from bundle', [
                        'bundle' => $bundlePath,
                        'member' => $member,
                    ]);
                    return null;
                }

                return $loose;
            }

            $zip->close();
        }

        return null;
    }

    /**
     * Read one sample's audit file out of a bundle, without unpacking the rest.
     *
     * Returns the raw (still compressed) member contents, or null when no
     * bundle holds it. The caller decompresses according to the member's own
     * extension, exactly as it would for a loose file.
     *
     * @return array{name:string, contents:string}|null
     */
    public static function readSample(string $testType, string $uniqueId): ?array
    {
        $dir = self::archiveRoot() . DIRECTORY_SEPARATOR . $testType;
        if (!is_dir($dir)) {
            return null;
        }

        // Newest bundle first: a sample that was recently active is far likelier
        // to sit in a recent month than in one years old.
        foreach (self::bundles($dir) as $bundlePath) {
            $zip = new ZipArchive();
            if ($zip->open($bundlePath) !== true) {
                continue;
            }

            foreach (self::memberNamesFor($uniqueId) as $member) {
                if ($zip->locateName($member) === false) {
                    continue;
                }
                $contents = $zip->getFromName($member);
                $zip->close();
                return $contents === false ? null : ['name' => $member, 'contents' => $contents];
            }

            $zip->close();
        }

        return null;
    }

    /**
     * The member names a sample could be stored under.
     *
     * Which extension a file got depended on what was installed on the machine
     * when it was written, so a single directory holds both and a lookup has to
     * try each.
     */
    private static function memberNamesFor(string $uniqueId): array
    {
        return ["{$uniqueId}.csv.zst", "{$uniqueId}.csv.gz"];
    }

    /**
     * Month archives in a directory, newest first.
     */
    private static function bundles(string $dir): array
    {
        $found = [];
        foreach ((array) @scandir($dir) as $entry) {
            if (is_string($entry) && preg_match(self::BUNDLE_PATTERN, $entry) === 1) {
                $found[] = $dir . DIRECTORY_SEPARATOR . $entry;
            }
        }
        rsort($found, SORT_STRING);
        return $found;
    }

    /**
     * Per-sample audit files in a directory, bundles and dot-files excluded.
     *
     * A generator rather than a glob: the directory this walks is the one with
     * hundreds of thousands of entries in it, and building an array of every
     * path before looking at any of them is the memory spike worth avoiding.
     */
    private static function looseFiles(string $dir): Generator
    {
        $handle = @opendir($dir);
        if ($handle === false) {
            return;
        }

        try {
            while (($entry = readdir($handle)) !== false) {
                if ($entry === '.' || $entry === '..' || $entry[0] === '.') {
                    continue;
                }
                if (!str_ends_with($entry, '.csv.zst') && !str_ends_with($entry, '.csv.gz')) {
                    continue;
                }
                $path = $dir . DIRECTORY_SEPARATOR . $entry;
                if (is_file($path)) {
                    yield $path;
                }
            }
        } finally {
            closedir($handle);
        }
    }

    /**
     * Test type directories under the archive root, keyed by test key.
     */
    private static function testTypeDirs(): array
    {
        $root = self::archiveRoot();
        $dirs = [];

        foreach ((array) @scandir($root) as $entry) {
            if (!is_string($entry) || $entry === '.' || $entry === '..' || $entry[0] === '.') {
                continue;
            }
            $path = $root . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $dirs[$entry] = $path;
            }
        }

        return $dirs;
    }

    /**
     * Counts for reporting: loose files, bundles, and how many are settled.
     *
     * @return array{loose:int, settled:int, bundles:int}
     */
    public static function stats(): array
    {
        $out = ['loose' => 0, 'settled' => 0, 'bundles' => 0];
        $cutoff = time() - (self::SETTLE_DAYS * 86400);

        foreach (self::testTypeDirs() as $dir) {
            $out['bundles'] += count(self::bundles($dir));
            foreach (self::looseFiles($dir) as $path) {
                $out['loose']++;
                $mtime = @filemtime($path);
                if ($mtime !== false && $mtime <= $cutoff) {
                    $out['settled']++;
                }
            }
        }

        return $out;
    }
}
