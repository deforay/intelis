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
 * Labs reach this state honestly: preflight handed out an ADD remedy for the
 * missing new column before the rename migration existed.
 *
 * Merging the two automatically cannot be made safe. A row whose new column is
 * empty may be one nothing ever wrote or one someone deliberately cleared, and
 * nothing tells them apart; and dropping the old column drops whatever keys it
 * belongs to, which a real CHANGE would have carried across --
 * instrument_controls.config_id is half of a primary key before 5.2.8 renames
 * it. So the runner stops and names the table and columns instead. An upgrade
 * that halts for a stated reason beats one that finishes having kept the wrong
 * data, because it can be seen.
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
     * An empty table has nothing to choose between, so it is healed.
     *
     * The rename runs for real, which is what carries the values, the primary
     * key and every secondary index across -- MySQL does that for a rename and
     * does not do it for a drop.
     */
    public function testAnEmptyTableIsHealedByRenamingForReal(): void
    {
        self::$db->rawQuery('DROP TABLE IF EXISTS `heal_empty_probe`');
        self::$db->rawQuery(
            'CREATE TABLE `heal_empty_probe` (
                `test_type` VARCHAR(50) NOT NULL,
                `config_id` VARCHAR(50) NOT NULL,
                `instrument_id` VARCHAR(50) NOT NULL DEFAULT "",
                PRIMARY KEY (`test_type`, `config_id`),
                KEY `idx_cfg` (`config_id`)
            ) ENGINE=InnoDB'
        );

        $this->assertSame(
            MIG_EXECUTED,
            $this->dispatch(
                'ALTER TABLE `heal_empty_probe` CHANGE `config_id` `instrument_id` VARCHAR(50) NOT NULL'
            )
        );

        $columns = $this->columnsOf('heal_empty_probe');
        $this->assertContains('instrument_id', $columns);
        $this->assertNotContains('config_id', $columns, 'The old name is gone, because it was renamed.');

        // The whole reason for renaming rather than copying and dropping.
        $this->assertSame(
            ['test_type', 'instrument_id'],
            array_column(
                self::$db->rawQuery(
                    "SELECT COLUMN_NAME FROM information_schema.STATISTICS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'heal_empty_probe'
                         AND INDEX_NAME = 'PRIMARY' ORDER BY SEQ_IN_INDEX"
                ),
                'COLUMN_NAME'
            ),
            'The primary key has to follow the column, not shrink.'
        );
        $this->assertSame(
            ['instrument_id'],
            array_column(
                self::$db->rawQuery(
                    "SELECT COLUMN_NAME FROM information_schema.STATISTICS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'heal_empty_probe' AND INDEX_NAME = 'idx_cfg'"
                ),
                'COLUMN_NAME'
            ),
            'and so does every secondary index.'
        );
    }

    /**
     * A column holding only its default is NOT evidence that nobody wrote it.
     *
     * The case that removed the cleverer version of this repair. One shelf,
     * reactivated today: `storage_status` is 'active' because somebody set it,
     * and the stale `lab_storage_status` still says 'inactive'. Reading the new
     * column as empty and preferring the old one restores the stale value over
     * a deliberate edit -- silently, and with the old column then dropped there
     * is nothing left to notice it by.
     */
    public function testADefaultValuedColumnIsNotTreatedAsUnwritten(): void
    {
        self::$db->rawQuery('DROP TABLE IF EXISTS `heal_default_probe`');
        self::$db->rawQuery(
            "CREATE TABLE `heal_default_probe` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                `lab_storage_status` VARCHAR(10) NOT NULL DEFAULT 'active',
                `storage_status` VARCHAR(10) NOT NULL DEFAULT 'active'
            ) ENGINE=InnoDB"
        );
        self::$db->rawQuery(
            "INSERT INTO `heal_default_probe` (`lab_storage_status`, `storage_status`)
             VALUES ('inactive', 'active')"
        );

        $raised = null;
        try {
            $this->dispatch(
                "ALTER TABLE `heal_default_probe`
                 CHANGE `lab_storage_status` `storage_status` VARCHAR(10) NOT NULL DEFAULT 'active'"
            );
        } catch (\Throwable $e) {
            $raised = $e;
        }

        $this->assertNotNull($raised, 'A table with rows must stop, not guess.');
        $this->assertSame(
            'active',
            (string) (self::$db->rawQueryOne(
                'SELECT `storage_status` AS v FROM `heal_default_probe` LIMIT 1'
            )['v'] ?? ''),
            'The deliberate value must survive; restoring the stale one is the failure.'
        );
        $this->assertContains('lab_storage_status', $this->columnsOf('heal_default_probe'));
    }

    /** A nullable column cleared on purpose is not a gap either. */
    public function testADeliberatelyClearedColumnIsNotTreatedAsUnwritten(): void
    {
        self::$db->rawQuery('DROP TABLE IF EXISTS `heal_cleared_probe`');
        self::$db->rawQuery(
            'CREATE TABLE `heal_cleared_probe` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                `old_name` DATETIME NULL DEFAULT NULL,
                `new_name` DATETIME NULL DEFAULT NULL
            ) ENGINE=InnoDB'
        );
        self::$db->rawQuery(
            'INSERT INTO `heal_cleared_probe` (`old_name`, `new_name`) VALUES ("2024-03-01 09:00:00", NULL)'
        );

        $raised = null;
        try {
            $this->dispatch('ALTER TABLE `heal_cleared_probe` CHANGE `old_name` `new_name` DATETIME NULL');
        } catch (\Throwable $e) {
            $raised = $e;
        }

        $this->assertNotNull($raised, 'NULL does not prove the row was never written.');
        $this->assertNull(
            self::$db->rawQueryOne('SELECT `new_name` AS v FROM `heal_cleared_probe` LIMIT 1')['v'],
            'The cleared value must stay cleared.'
        );
    }

    /** An indexed stand-in is not dropped even when the table is empty. */
    public function testAnIndexedStandInHaltsEvenOnAnEmptyTable(): void
    {
        self::$db->rawQuery('DROP TABLE IF EXISTS `heal_indexed_probe`');
        self::$db->rawQuery(
            'CREATE TABLE `heal_indexed_probe` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                `old_name` INT NULL DEFAULT NULL,
                `new_name` INT NULL DEFAULT NULL,
                KEY `idx_new` (`new_name`)
            ) ENGINE=InnoDB'
        );

        $raised = null;
        try {
            $this->dispatch('ALTER TABLE `heal_indexed_probe` CHANGE `old_name` `new_name` INT NULL');
        } catch (\Throwable $e) {
            $raised = $e;
        }

        $this->assertNotNull($raised, 'Dropping an indexed column would take its keys with it.');
        $this->assertStringContainsString('idx_new', $raised->getMessage(), 'naming the key in the way.');
        $this->assertContains('new_name', $this->columnsOf('heal_indexed_probe'));
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
