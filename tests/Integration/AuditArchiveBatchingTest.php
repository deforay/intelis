<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\AuditArchiveService;
use App\Services\DatabaseService;
use App\Utilities\ArchiveUtility;
use mysqli;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * How many times archiving rewrites a sample's file, against a real server.
 *
 * Archiving one audit row means decompressing that sample's entire history,
 * appending, and recompressing it. Filing rows one at a time therefore rewrote
 * the file once per revision and re-read everything it had just written, so the
 * cost grew with the square of a sample's revision count and every rewrite cost
 * the next backup a re-copy of the file. The fix files a whole page of rows one
 * sample at a time, and this pins the property that makes it a fix: one write
 * per sample per pass, whatever the revision count.
 *
 * archiveTable() is reached by reflection because the public entry points resolve
 * a test type through TestsService, which wants the application's test_types
 * table and cache. What is under test here is the loop, not the lookup.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class AuditArchiveBatchingTest extends TestCase
{
    private const DATABASE  = 'intelis_audit_archive_test';
    private const TEST_KEY  = 'archive-batching-test';
    private const AUDIT_TBL = 'audit_form_vl';

    private static ?DatabaseService $db = null;

    public static function setUpBeforeClass(): void
    {
        $host = getenv('INTELIS_TEST_DB_HOST');
        $user = getenv('INTELIS_TEST_DB_USER');

        if ($host === false || $host === '' || $user === false || $user === '') {
            return;
        }

        $port     = (int) (getenv('INTELIS_TEST_DB_PORT') ?: 3306);
        $password = (string) (getenv('INTELIS_TEST_DB_PASS') ?: '');

        $bootstrap = new mysqli($host, $user, $password, null, $port);
        $bootstrap->query('DROP DATABASE IF EXISTS `' . self::DATABASE . '`');
        $bootstrap->query('CREATE DATABASE `' . self::DATABASE . '`');
        $bootstrap->select_db(self::DATABASE);
        // The columns the archiver reads by name, the three the single-sample
        // filter searches, and one payload column so a reheader would have
        // something to move.
        $bootstrap->query(
            'CREATE TABLE `' . self::AUDIT_TBL . '` (
                unique_id VARCHAR(64) NOT NULL,
                revision INT NULL,
                dt_datetime DATETIME NOT NULL,
                sample_code VARCHAR(64) NULL,
                remote_sample_code VARCHAR(64) NULL,
                external_sample_code VARCHAR(64) NULL,
                result VARCHAR(64) NULL
            ) ENGINE=InnoDB'
        );
        $bootstrap->close();

        self::$db = new DatabaseService([
            'host'     => $host,
            'username' => $user,
            'password' => $password,
            'db'       => self::DATABASE,
            'port'     => $port,
        ]);
    }

    protected function setUp(): void
    {
        if (self::$db === null) {
            $this->markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run.');
        }

        self::$db->rawQuery('TRUNCATE TABLE `' . self::AUDIT_TBL . '`');

        // A previous run's files would be read as existing history.
        $dir = $this->archiveDir();
        if (is_dir($dir)) {
            foreach (glob($dir . '/*') ?: [] as $f) {
                unlink($f);
            }
        }
        // Bulk mode reads a cursor out of this; a stale one would skip every row.
        $cursor = VAR_PATH . '/metadata/archive.mdata.json';
        if (is_file($cursor)) {
            unlink($cursor);
        }
    }

    /**
     * Twelve revisions of one sample cost one rewrite, not twelve.
     */
    public function testOneWritePerSampleRegardlessOfRevisionCount(): void
    {
        $this->insertRevisions('SAMPLE-A', 12);

        $log = $this->archive();

        $this->assertSame(
            1,
            $this->writeCount($log),
            'Twelve revisions of one sample must be filed in a single rewrite.'
        );

        $rows = $this->archivedRows('SAMPLE-A');
        $this->assertCount(12, $rows);
        $this->assertSame(
            range(1, 12),
            array_map(static fn(array $r): int => (int) $r['revision'], $rows),
            'Revisions must be numbered 1..n in the order they happened.'
        );
        $this->assertSame(
            array_map(static fn(int $i): string => 'r' . $i, range(1, 12)),
            array_map(static fn(array $r): string => (string) $r['result'], $rows),
            'Row payloads must stay in dt_datetime order.'
        );
    }

    /**
     * Each sample in a page gets its own single rewrite.
     */
    public function testEachSampleIsWrittenOnce(): void
    {
        $this->insertRevisions('SAMPLE-A', 4);
        $this->insertRevisions('SAMPLE-B', 6);
        $this->insertRevisions('SAMPLE-C', 1);

        $log = $this->archive();

        $this->assertSame(3, $this->writeCount($log), 'Three samples, three rewrites.');
        $this->assertCount(4, $this->archivedRows('SAMPLE-A'));
        $this->assertCount(6, $this->archivedRows('SAMPLE-B'));
        $this->assertCount(1, $this->archivedRows('SAMPLE-C'));
    }

    /**
     * Re-archiving rows already on disk must not touch the file.
     *
     * The old loop rewrote a sample's file for every new row, and a rewrite is
     * what makes rsync re-copy an archive that has not actually changed. A pass
     * that finds nothing new should leave the bytes, and the mtime, alone.
     */
    public function testAlreadyArchivedRowsDoNotRewriteTheFile(): void
    {
        $this->insertRevisions('SAMPLE-A', 5);
        $this->archive();

        $path = $this->archivedPath('SAMPLE-A');
        $this->assertNotNull($path);
        $before = filemtime($path);

        // Bulk mode advances a cursor past what it filed, so ask for this one
        // sample explicitly to make the archiver look at the same rows again.
        $log = $this->archive('SAMPLE-A');

        $this->assertSame(0, $this->writeCount($log), 'A pass with nothing new must not rewrite.');
        $this->assertSame($before, filemtime($this->archivedPath('SAMPLE-A')));
        $this->assertCount(5, $this->archivedRows('SAMPLE-A'), 'And must not duplicate rows.');
    }

    /**
     * New revisions arriving later continue the numbering already on disk.
     */
    public function testRevisionNumberingContinuesAcrossPasses(): void
    {
        $this->insertRevisions('SAMPLE-A', 3);
        $this->archive();

        $this->insertRevisions('SAMPLE-A', 2, 3);
        $log = $this->archive('SAMPLE-A');

        $this->assertSame(1, $this->writeCount($log));
        $this->assertSame(
            range(1, 5),
            array_map(
                static fn(array $r): int => (int) $r['revision'],
                $this->archivedRows('SAMPLE-A')
            )
        );
    }

    /**
     * A plain .csv left by an older installation is read, extended and normalized.
     *
     * The archiver has written four formats over its life — .csv, .csv.zip,
     * .csv.gz and today's .csv.zst — and an upgrade does not rewrite what is
     * already on disk. Whatever a lab is carrying has to keep being read, and
     * its revision numbering has to continue rather than restart.
     */
    public function testReadsAndExtendsALegacyPlainCsv(): void
    {
        $this->writeLegacyCsv('SAMPLE-A', ['action', 'revision', 'dt_datetime', 'unique_id', 'result'], [
            ['update', '1', '2026-01-01 00:01:00', 'SAMPLE-A', 'r1'],
            ['update', '2', '2026-01-01 00:02:00', 'SAMPLE-A', 'r2'],
        ]);

        // Two rows already archived, one new one behind them.
        $this->insertRevisions('SAMPLE-A', 3);

        $log = $this->archive('SAMPLE-A');

        $this->assertSame(1, $this->writeCount($log));

        $rows = $this->archivedRows('SAMPLE-A');
        $this->assertCount(3, $rows, 'The two legacy rows must survive, not be replaced.');
        $this->assertSame(
            ['r1', 'r2', 'r3'],
            array_map(static fn(array $r): string => (string) $r['result'], $rows)
        );
        $this->assertSame(
            [1, 2, 3],
            array_map(static fn(array $r): int => (int) $r['revision'], $rows),
            'Numbering must continue from what the legacy file already held.'
        );
        $this->assertFileDoesNotExist(
            $this->archiveDir() . '/SAMPLE-A.csv',
            'The legacy plain file must be normalized away, not left beside the new one.'
        );
    }

    /**
     * A legacy file whose columns no longer match the table is remapped, not dropped.
     *
     * Forms gain and lose fields, so an old archive's header rarely matches the
     * audit table as it stands today. Columns are matched by name and a column
     * the form has since dropped keeps its history.
     */
    public function testLegacyColumnsAreRemappedByName(): void
    {
        // Different order from the live table, and one column the form no longer has.
        $this->writeLegacyCsv('SAMPLE-A', ['dt_datetime', 'result', 'revision', 'unique_id', 'retired_field'], [
            ['2026-01-01 00:01:00', 'r1', '1', 'SAMPLE-A', 'kept'],
        ]);

        $this->insertRevisions('SAMPLE-A', 2);

        $this->archive('SAMPLE-A');

        $rows = $this->archivedRows('SAMPLE-A');
        $this->assertCount(2, $rows);
        $this->assertSame('r1', $rows[0]['result'], 'A reordered legacy column must land in its own field.');
        $this->assertSame('r2', $rows[1]['result']);
        $this->assertSame([1, 2], array_map(static fn(array $r): int => (int) $r['revision'], $rows));
    }

    /**
     * A value ending in a backslash survives the archive round trip.
     *
     * Written under PHP's historical CSV escape, a field ending in a backslash
     * became "value\\" — the backslash escaped the closing quote, so on the way
     * back the field swallowed the delimiter and everything after it on the
     * line. One analyzer path in import_machine_file_name was enough to lose the
     * rest of a revision, which for an audit trail is the whole failure.
     */
    public function testTrailingBackslashSurvivesTheRoundTrip(): void
    {
        $value = 'C:\\analyzer\\run\\';
        self::$db->rawQuery(
            'INSERT INTO `' . self::AUDIT_TBL . '` (unique_id, sample_code, dt_datetime, result)
             VALUES (?, ?, ?, ?)',
            ['SAMPLE-A', 'SAMPLE-A', '2026-01-01 00:01:00', $value]
        );

        $this->archive('SAMPLE-A');

        $rows = $this->archivedRows('SAMPLE-A');
        $this->assertCount(1, $rows);
        $this->assertSame($value, $rows[0]['result'], 'The backslash must come back intact.');
        $this->assertSame('SAMPLE-A', $rows[0]['unique_id'], 'And must not have swallowed the next field.');
    }

    /**
     * Quotes, commas, newlines and unicode round trip under the same rule.
     *
     * Disabling the escape changes how a backslash is handled and nothing else,
     * so the characters that were already safe have to stay safe.
     */
    public function testAwkwardValuesSurviveTheRoundTrip(): void
    {
        $values = [
            'quoted' => 'says "hello" twice',
            'comma'  => 'Kinshasa, Gombe',
            'accent' => 'accentué — ünïcode',
            'json'   => '{"result":"<40","unit":"cp/mL"}',
        ];

        $i = 0;
        foreach ($values as $value) {
            $i++;
            self::$db->rawQuery(
                'INSERT INTO `' . self::AUDIT_TBL . '` (unique_id, sample_code, dt_datetime, result)
                 VALUES (?, ?, ?, ?)',
                ['SAMPLE-A', 'SAMPLE-A', sprintf('2026-01-01 00:%02d:00', $i), $value]
            );
        }

        $this->archive('SAMPLE-A');

        $this->assertSame(
            array_values($values),
            array_map(static fn(array $r): string => (string) $r['result'], $this->archivedRows('SAMPLE-A'))
        );
    }

    /**
     * A legacy file whose quoting the old writer broke is still read correctly.
     *
     * The old escape rule suppressed quote-doubling whenever a backslash came
     * first, so a JSON value carrying an escaped quote went to disk as
     * ""he said \"no\"" instead of ""he said \""no\"""". Read back under RFC 4180
     * that row parses one field too wide, because the undoubled quote ends the
     * field early and the comma inside the value becomes a delimiter.
     *
     * The header row is what catches it: every row has to be as wide as the
     * header, and this one is not, so the file is re-read under the rule it was
     * written with. buildRow() json_encodes array and object values, so this is
     * the shape a real archive would carry it in.
     */
    public function testLegacyFileWithBrokenQuotingIsRecovered(): void
    {
        $json = '{"note":"he said \\"no\\", then left"}';

        $this->writeLegacyCsv('SAMPLE-A', ['action', 'revision', 'dt_datetime', 'unique_id', 'result'], [
            ['update', '1', '2026-01-01 00:01:00', 'SAMPLE-A', $json],
        ]);

        $this->insertRevisions('SAMPLE-A', 2);
        $this->archive('SAMPLE-A');

        $rows = $this->archivedRows('SAMPLE-A');
        $this->assertCount(2, $rows, 'The legacy row must survive, not be split or dropped.');
        $this->assertSame($json, $rows[0]['result'], 'The value must be recovered intact.');
        $this->assertSame([1, 2], array_map(static fn(array $r): int => (int) $r['revision'], $rows));
    }

    /**
     * And once rewritten, that same value round trips under the new rule alone.
     *
     * The recovery above is for files already on disk. What the fixed writer
     * produces has to need no recovery at all.
     */
    public function testBrokenQuotingIsNotReintroducedOnRewrite(): void
    {
        $json = '{"note":"he said \\"no\\", then left"}';

        self::$db->rawQuery(
            'INSERT INTO `' . self::AUDIT_TBL . '` (unique_id, sample_code, dt_datetime, result)
             VALUES (?, ?, ?, ?)',
            ['SAMPLE-A', 'SAMPLE-A', '2026-01-01 00:01:00', $json]
        );

        $this->archive('SAMPLE-A');

        // Read the file back with the strict rule only -- no fallback, no header
        // width check -- so a rewrite that reintroduced the old quoting fails here.
        $path = $this->archivedPath('SAMPLE-A');
        $csv  = ArchiveUtility::decompressToString($path);
        $h    = fopen('php://temp', 'r+');
        fwrite($h, $csv);
        rewind($h);
        $headers = fgetcsv($h, escape: '');
        $row     = fgetcsv($h, escape: '');
        fclose($h);

        $this->assertCount(count($headers), $row, 'The rewritten row must be exactly header-wide.');
        $this->assertSame($json, $row[array_search('result', $headers, true)]);
    }

    /* ====================== Helpers ====================== */

    /**
     * Lay down an archive in the oldest format, as an un-upgraded lab would carry.
     *
     * @param string[] $headers
     * @param array<int, string[]> $rows
     */
    private function writeLegacyCsv(string $uniqueId, array $headers, array $rows): void
    {
        $dir = $this->archiveDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $handle = fopen($dir . '/' . $uniqueId . '.csv', 'w');
        fputcsv($handle, $headers, escape: '\\');
        foreach ($rows as $row) {
            fputcsv($handle, $row, escape: '\\');
        }
        fclose($handle);
    }

    private function archiveDir(): string
    {
        return VAR_PATH . '/audit-trail/' . self::TEST_KEY;
    }

    /** @param int $startAfter Revisions already inserted, so timestamps keep advancing. */
    private function insertRevisions(string $uniqueId, int $count, int $startAfter = 0): void
    {
        for ($i = $startAfter + 1; $i <= $startAfter + $count; $i++) {
            self::$db->rawQuery(
                'INSERT INTO `' . self::AUDIT_TBL . '` (unique_id, sample_code, dt_datetime, result)
                 VALUES (?, ?, ?, ?)',
                [$uniqueId, $uniqueId, sprintf('2026-01-01 %02d:%02d:00', intdiv($i, 60), $i % 60), 'r' . $i]
            );
        }
    }

    /** @return string[] Progress lines the archiver emitted. */
    private function archive(?string $sampleCode = null): array
    {
        $log = [];
        $metadata = [];

        // Built by hand so $metadata reaches the by-ref parameter as a reference.
        $args = [self::AUDIT_TBL, self::TEST_KEY, $sampleCode, static function (string $m) use (&$log): void {
            $log[] = $m;
        }];
        $args[4] = &$metadata;

        $method = new ReflectionMethod(AuditArchiveService::class, 'archiveTable');
        $method->invokeArgs(new AuditArchiveService(self::$db), $args);

        return $log;
    }

    /** @param string[] $log */
    private function writeCount(array $log): int
    {
        return count(array_filter($log, static fn(string $m): bool => str_starts_with($m, 'Wrote ')));
    }

    private function archivedPath(string $uniqueId): ?string
    {
        foreach (['zst', 'gz', 'zip'] as $ext) {
            $p = $this->archiveDir() . '/' . $uniqueId . '.csv.' . $ext;
            if (is_file($p)) {
                return $p;
            }
        }
        $plain = $this->archiveDir() . '/' . $uniqueId . '.csv';

        return is_file($plain) ? $plain : null;
    }

    /** @return array<int, array<string, string>> */
    private function archivedRows(string $uniqueId): array
    {
        $path = $this->archivedPath($uniqueId);
        $this->assertNotNull($path, "No archive written for {$uniqueId}.");

        $csv = str_ends_with($path, '.csv')
            ? (string) file_get_contents($path)
            : ArchiveUtility::decompressToString($path);

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csv);
        rewind($handle);

        $headers = fgetcsv($handle, escape: '');
        $rows    = [];
        while (($row = fgetcsv($handle, escape: '')) !== false) {
            $rows[] = array_combine($headers, $row);
        }
        fclose($handle);

        return $rows;
    }
}
