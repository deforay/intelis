<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\DatabaseService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tests\Support\MigrationRunnerFunctions;

/**
 * A rename that arrives to find both names already present.
 *
 * MySQL will not CHANGE a column onto a name the table already holds: it raises
 * 1060, which is on the runner's benign list, so the statement was written off
 * as applied. It was not. The values stayed under the old name, the new column
 * kept whatever default it was created with, and the version was stamped as
 * done on top of that -- the reader sees an empty column and no error anywhere.
 *
 * Labs reach this state honestly. Preflight handed out an `ADD` remedy for the
 * missing new column before the rename migration existed, so both columns hold
 * real values: the old one everything written before the remedy, the new one
 * everything since, because the application only ever knew the new name. That
 * is why the old column may only fill gaps and must never overwrite.
 *
 * The two shapes below are 5.7.59's two renames:
 * form_generic.sample_received_at_testing_lab_datetime, nullable, where NULL is
 * an honest gap; and lab_storage.lab_storage_status, NOT NULL DEFAULT 'active',
 * where nothing distinguishes a row written as active from one that merely
 * defaulted, and the shelves that were marked inactive are the rows actually
 * lost.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class MigrationRenameReconcileTest extends TestCase
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
    }

    private function dispatch(string $sql): int
    {
        return handle_idempotent_ddl(
            self::$db,
            new SymfonyStyle(new ArrayInput([]), new NullOutput()),
            $sql
        );
    }

    /** @return string[] */
    private function columnsOf(string $table): array
    {
        return array_column(
            self::$db->rawQuery(
                "SELECT COLUMN_NAME FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
                [$table]
            ),
            'COLUMN_NAME'
        );
    }

    /**
     * The nullable case: 5.7.59's form_generic datetime.
     *
     * Rows written before the remedy have their value under the old name and
     * NULL under the new; rows written after have it the other way round. Both
     * have to survive.
     */
    public function testANullableColumnTakesTheOldValueOnlyWhereItIsEmpty(): void
    {
        self::$db->rawQuery('DROP TABLE IF EXISTS `reconcile_null_probe`');
        self::$db->rawQuery(
            'CREATE TABLE `reconcile_null_probe` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                `sample_received_at_testing_lab_datetime` DATETIME NULL DEFAULT NULL,
                `sample_received_at_lab_datetime` DATETIME NULL DEFAULT NULL
            ) ENGINE=InnoDB'
        );
        self::$db->rawQuery(
            'INSERT INTO `reconcile_null_probe`
                (`sample_received_at_testing_lab_datetime`, `sample_received_at_lab_datetime`) VALUES
                ("2024-03-01 09:00:00", NULL),
                (NULL, "2024-11-01 09:00:00"),
                ("2024-03-02 09:00:00", "2024-11-02 09:00:00"),
                (NULL, NULL)'
        );

        $this->assertSame(
            MIG_EXECUTED,
            $this->dispatch(
                'ALTER TABLE `reconcile_null_probe`
                 CHANGE `sample_received_at_testing_lab_datetime` `sample_received_at_lab_datetime`
                 DATETIME NULL DEFAULT NULL'
            )
        );

        $rows = array_column(
            self::$db->rawQuery(
                'SELECT `sample_received_at_lab_datetime` AS v FROM `reconcile_null_probe` ORDER BY id'
            ),
            'v'
        );

        $this->assertSame('2024-03-01 09:00:00', $rows[0], 'The stranded value has to be carried over.');
        $this->assertSame('2024-11-01 09:00:00', $rows[1], 'A value written since must not be lost.');
        $this->assertSame('2024-11-02 09:00:00', $rows[2], 'and must not be overwritten by the older one.');
        $this->assertNull($rows[3], 'Nothing to carry over stays nothing.');

        $this->assertNotContains(
            'sample_received_at_testing_lab_datetime',
            $this->columnsOf('reconcile_null_probe'),
            'The old name has to be retired, or the next run finds both again.'
        );
    }

    /**
     * The NOT NULL DEFAULT case: 5.7.59's lab_storage status.
     *
     * Every row reads 'active' under the new name whether it was written that
     * way or merely defaulted, so only rows where the old column disagrees can
     * be recovered. Those are the ones that were actually lost.
     */
    public function testANotNullColumnTakesTheOldValueOnlyWhereItStillHoldsItsDefault(): void
    {
        self::$db->rawQuery('DROP TABLE IF EXISTS `reconcile_default_probe`');
        self::$db->rawQuery(
            "CREATE TABLE `reconcile_default_probe` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                `lab_storage_status` VARCHAR(10) NOT NULL DEFAULT 'active',
                `storage_status` VARCHAR(10) NOT NULL DEFAULT 'active'
            ) ENGINE=InnoDB"
        );
        self::$db->rawQuery(
            "INSERT INTO `reconcile_default_probe` (`lab_storage_status`, `storage_status`) VALUES
                ('inactive', 'active'),
                ('active',   'active'),
                ('active',   'inactive'),
                ('inactive', 'inactive')"
        );

        $this->assertSame(
            MIG_EXECUTED,
            $this->dispatch(
                "ALTER TABLE `reconcile_default_probe`
                 CHANGE `lab_storage_status` `storage_status` VARCHAR(10) NOT NULL DEFAULT 'active'"
            )
        );

        $rows = array_column(
            self::$db->rawQuery('SELECT `storage_status` AS v FROM `reconcile_default_probe` ORDER BY id'),
            'v'
        );

        $this->assertSame('inactive', $rows[0], 'The shelf marked inactive under the old name was lost; recover it.');
        $this->assertSame('active', $rows[1], 'Agreeing rows are left alone.');
        $this->assertSame('inactive', $rows[2], 'A value written since must not be overwritten by the default.');
        $this->assertSame('inactive', $rows[3], 'and stays as it is when both agree.');

        $this->assertNotContains('lab_storage_status', $this->columnsOf('reconcile_default_probe'));
    }

    /** Only the old name present is an ordinary rename, untouched by any of this. */
    public function testAPlainRenameStillJustRenames(): void
    {
        self::$db->rawQuery('DROP TABLE IF EXISTS `reconcile_plain_probe`');
        self::$db->rawQuery(
            'CREATE TABLE `reconcile_plain_probe`
                (id INT AUTO_INCREMENT PRIMARY KEY, `old_name` INT NULL) ENGINE=InnoDB'
        );
        self::$db->rawQuery('INSERT INTO `reconcile_plain_probe` (`old_name`) VALUES (7)');

        $this->assertSame(
            MIG_EXECUTED,
            $this->dispatch('ALTER TABLE `reconcile_plain_probe` CHANGE `old_name` `new_name` INT NULL')
        );

        $columns = $this->columnsOf('reconcile_plain_probe');
        $this->assertContains('new_name', $columns);
        $this->assertNotContains('old_name', $columns);
        $this->assertSame(
            '7',
            (string) (self::$db->rawQueryOne('SELECT `new_name` AS v FROM `reconcile_plain_probe` LIMIT 1')['v'] ?? ''),
            'A plain rename carries its data as MySQL always did.'
        );
    }

    /** Only the new name present is already done, and must stay a skip. */
    public function testAnAlreadyAppliedRenameIsStillSkipped(): void
    {
        self::$db->rawQuery('DROP TABLE IF EXISTS `reconcile_done_probe`');
        self::$db->rawQuery(
            'CREATE TABLE `reconcile_done_probe` (id INT AUTO_INCREMENT PRIMARY KEY, `new_name` INT NULL) ENGINE=InnoDB'
        );

        $this->assertSame(
            MIG_SKIPPED,
            $this->dispatch('ALTER TABLE `reconcile_done_probe` CHANGE `old_name` `new_name` INT NULL')
        );
    }

    /**
     * A retype that is not a rename is left alone.
     *
     * `CHANGE a a BIGINT` names one column twice; there is no old value to
     * carry anywhere and nothing to retire.
     */
    public function testARetypeUnderTheSameNameIsNotTreatedAsARename(): void
    {
        self::$db->rawQuery('DROP TABLE IF EXISTS `reconcile_retype_probe`');
        self::$db->rawQuery(
            'CREATE TABLE `reconcile_retype_probe` (id INT AUTO_INCREMENT PRIMARY KEY, `a` INT NULL) ENGINE=InnoDB'
        );
        self::$db->rawQuery('INSERT INTO `reconcile_retype_probe` (`a`) VALUES (3)');

        $this->assertSame(
            MIG_EXECUTED,
            $this->dispatch('ALTER TABLE `reconcile_retype_probe` CHANGE `a` `a` BIGINT NULL')
        );

        $this->assertContains('a', $this->columnsOf('reconcile_retype_probe'));
        $this->assertSame(
            '3',
            (string) (self::$db->rawQueryOne('SELECT `a` AS v FROM `reconcile_retype_probe` LIMIT 1')['v'] ?? ''),
            'The column keeps its data through a retype.'
        );
    }
}
