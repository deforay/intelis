<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\DatabaseService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tests\Support\MigrationRunnerFunctions;
use Throwable;

/**
 * A column added to a table at InnoDB's row-size line still lands.
 *
 * form_vl is right at the 8126-byte worst-case limit. A lab on MySQL 8.0 failed
 * 5.7.82 with 1118 "Row size too large" on `ADD assay_name VARCHAR(255)` and was
 * stranded on 5.7.81 until the ALTER was run by hand. The runner now retries
 * table DDL that fails 1118 once with innodb_strict_mode off for its session.
 *
 * The table here is built from CHAR columns because every server counts those
 * in full; short VARCHARs count on 8.0 but not on 8.4, so they would only
 * reproduce the failure on some servers.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class MigrationWideRowTest extends TestCase
{
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
        self::$db->rawQuery('SET SESSION innodb_strict_mode = ON');
    }

    /** A table one CHAR(60) short of the limit: 134 of them fit, 135 do not. */
    private function tableAtTheLimit(string $name): string
    {
        $columns = [];
        for ($i = 1; $i <= 134; $i++) {
            $columns[] = "`c$i` CHAR(60) CHARACTER SET latin1 NULL";
        }
        self::$db->rawQuery("DROP TABLE IF EXISTS `$name`");
        self::$db->rawQuery(
            "CREATE TABLE `$name` (id INT AUTO_INCREMENT PRIMARY KEY, " . implode(', ', $columns) . ') ENGINE=InnoDB'
        );
        return $name;
    }

    private function hasColumn(string $table, string $column): bool
    {
        return (int) self::$db->rawQueryOne(
            "SELECT COUNT(*) AS n FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            [$table, $column]
        )['n'] === 1;
    }

    private function strictMode(): int
    {
        return (int) self::$db->rawQueryOne('SELECT @@SESSION.innodb_strict_mode AS m')['m'];
    }

    /** The failure the lab hit, so the tests below are about the real thing. */
    public function testOneMoreColumnFailsWithRowSizeTooLargeWhenRunPlain(): void
    {
        $t = $this->tableAtTheLimit('wide_plain_probe');

        $code = 0;
        try {
            self::$db->rawQuery("ALTER TABLE `$t` ADD `extra` CHAR(60) CHARACTER SET latin1 NULL");
            $code = self::$db->getLastErrno();
        } catch (Throwable $e) {
            $code = (int) $e->getCode();
        }

        $this->assertSame(1118, $code);
        $this->assertFalse($this->hasColumn($t, 'extra'));
    }

    /** The statement goes through the same guard a migration ADD COLUMN does. */
    public function testTheMigrationAddColumnLandsOnATableAtTheLimit(): void
    {
        $t = $this->tableAtTheLimit('wide_add_probe');

        $status = handle_idempotent_ddl(
            self::$db,
            new SymfonyStyle(new ArrayInput([]), new NullOutput()),
            "ALTER TABLE `$t` ADD `extra` CHAR(60) CHARACTER SET latin1 NULL DEFAULT NULL AFTER `c1`"
        );

        $this->assertSame(MIG_EXECUTED, $status);
        $this->assertTrue($this->hasColumn($t, 'extra'));
        $this->assertSame(1, $this->strictMode(), 'Strict mode must be back on for the rest of the run.');
    }

    /** A different error is not swallowed by the retry, and strict mode is untouched. */
    public function testOtherErrorsStillFail(): void
    {
        $code = 0;
        try {
            run_tolerating_wide_rows(self::$db, 'ALTER TABLE `wide_missing_probe` ADD `x` INT NULL');
            $code = self::$db->getLastErrno();
        } catch (\mysqli_sql_exception $e) {
            $code = (int) $e->getCode();
        }

        $this->assertSame(1146, $code, 'The table does not exist, and that is what must come back.');
        $this->assertSame(1, $this->strictMode());
    }
}
