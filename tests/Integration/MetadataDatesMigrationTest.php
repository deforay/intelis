<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\DatabaseService;
use PhpMyAdmin\SqlParser\Parser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tests\Support\MigrationRunnerFunctions;

/**
 * 5.7.72 gives every metadata-sync table a date on every row, and keeps it so.
 *
 * A side of the sync asks for rows changed after the newest updated_datetime it
 * holds. Undated rows made that "no date", and the whole table was re-sent on
 * every call (the Rwanda STS sent its five undated CD4 test reasons to every CD4
 * lab, every sync). Once dated, only changed rows travel, so a real change has to
 * be stamped even by the many screens that never set updated_datetime -- except
 * on facility_details, where every sync writes the lab heartbeat and stamping
 * would re-send every facility to every lab.
 *
 * The file is run the way bin/migrate.php runs it: parsed by the same SQL parser,
 * each statement through handle_idempotent_ddl() and raw otherwise.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class MetadataDatesMigrationTest extends TestCase
{
    private const OLD_DATE = '2000-01-01 00:00:00';

    private static ?DatabaseService $db = null;

    public static function setUpBeforeClass(): void
    {
        self::$db = MigrationRunnerFunctions::connect();
    }

    protected function setUp(): void
    {
        if (self::$db === null) {
            $this->markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run.');
        }
    }

    public function testUndatedRowsAreDatedAndChangesStampThemselves(): void
    {
        $db = self::$db;
        $db->rawQuery("INSERT INTO r_cd4_test_reasons (test_reason_name, test_reason_status, updated_datetime)
                       VALUES ('undated', 'active', NULL), ('dated', 'active', '2024-05-01 10:00:00')");
        $db->rawQuery("INSERT INTO facility_details
                           (vlsm_instance_id, facility_name, facility_type, status, updated_datetime)
                       VALUES ('probe', 'Undated Lab', 2, 'active', NULL)");

        $this->runMigration();

        self::assertSame(self::OLD_DATE, $this->reasonDate('undated'));
        self::assertSame('2024-05-01 10:00:00', $this->reasonDate('dated'), 'a row that had a date keeps it');
        self::assertSame(self::OLD_DATE, $db->rawQueryOne(
            "SELECT updated_datetime d FROM facility_details WHERE facility_name = 'Undated Lab'"
        )['d']);

        // An edit that does not set updated_datetime is stamped...
        $db->rawQuery("UPDATE r_cd4_test_reasons SET test_reason_status = 'inactive'
                       WHERE test_reason_name = 'undated'");
        self::assertGreaterThan(self::OLD_DATE, $this->reasonDate('undated'));

        // ...a sync copying a row keeps the source's date...
        $db->rawQuery("UPDATE r_cd4_test_reasons
                       SET test_reason_status = 'active', updated_datetime = '2021-02-03 04:05:06'
                       WHERE test_reason_name = 'undated'");
        self::assertSame('2021-02-03 04:05:06', $this->reasonDate('undated'));

        // ...and a new row gets a date.
        $db->rawQuery("INSERT INTO r_cd4_test_reasons (test_reason_name) VALUES ('new')");
        self::assertNotNull($this->reasonDate('new'));

        // The heartbeat every sync writes must not re-date a facility.
        $db->rawQuery("UPDATE facility_details
                       SET facility_attributes = JSON_SET(COALESCE(facility_attributes, '{}'), '$.lastHeartBeat', NOW())
                       WHERE facility_name = 'Undated Lab'");
        self::assertSame(self::OLD_DATE, $db->rawQueryOne(
            "SELECT updated_datetime d FROM facility_details WHERE facility_name = 'Undated Lab'"
        )['d']);
    }

    public function testEveryListedTableIsCoveredAndRerunIsHarmless(): void
    {
        $this->runMigration();
        $this->runMigration();

        $tables = $this->migratedTables();
        self::assertContains('r_cd4_test_reasons', $tables);
        self::assertContains('facility_details', $tables);
        self::assertCount(36, $tables, 'every nullable metadata-sync table');

        $undated = [];
        foreach ($tables as $table) {
            $column = self::$db->rawQueryOne(
                "SELECT COLUMN_DEFAULT d, EXTRA e FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'updated_datetime'",
                [$table]
            );
            self::assertNotNull($column, "$table has updated_datetime");
            self::assertStringContainsStringIgnoringCase(
                'CURRENT_TIMESTAMP',
                (string) $column['d'],
                "$table default"
            );

            $stampsOnChange = stripos((string) $column['e'], 'on update') !== false;
            $expectStamp = !in_array($table, ['facility_details', 'global_config'], true);
            self::assertSame($expectStamp, $stampsOnChange, "$table ON UPDATE");

            $nulls = (int) self::$db->rawQueryOne(
                "SELECT COUNT(*) n FROM `$table` WHERE updated_datetime IS NULL"
            )['n'];
            if ($nulls > 0) {
                $undated[] = $table;
            }
        }
        self::assertSame([], $undated);
    }

    private function runMigration(): void
    {
        $io = new SymfonyStyle(new ArrayInput([]), new NullOutput());
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/sys/migrations/5.7.72.sql');
        $sql = (string) preg_replace('/^(\s*--)(?=\S)/m', '$1 ', $sql);

        foreach ((new Parser($sql))->statements as $statement) {
            $query = trim($statement->build() ?? '');
            if ($query === '') {
                continue;
            }
            if (handle_idempotent_ddl(self::$db, $io, $query) === MIG_NOT_HANDLED) {
                self::$db->rawQuery($query);
                self::assertSame(0, self::$db->getLastErrno(), self::$db->getLastError() . "\n$query");
            }
        }
    }

    /** @return list<string> */
    private function migratedTables(): array
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 2) . '/sys/migrations/5.7.72.sql');
        preg_match_all('/^ALTER TABLE `(\w+)` MODIFY `updated_datetime`/m', $sql, $m);
        return $m[1];
    }

    private function reasonDate(string $name): ?string
    {
        return self::$db->rawQueryOne(
            'SELECT updated_datetime d FROM r_cd4_test_reasons WHERE test_reason_name = ?',
            [$name]
        )['d'] ?? null;
    }
}
