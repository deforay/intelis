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
     * The common case: the hand-added column was never written to.
     *
     * Nothing is being weighed up here, so nothing needs a person. The empty
     * stand-in goes and the rename runs as written -- which is what carries the
     * values, the primary key and every secondary index across, because MySQL
     * does that for a rename and does not do it for a drop.
     */
    public function testAnEmptyStandInColumnIsRetiredAndTheRenameRunsForReal(): void
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
        self::$db->rawQuery(
            'INSERT INTO `heal_empty_probe` (`test_type`, `config_id`) VALUES ("vl", "a"), ("vl", "b")'
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

        $values = array_column(
            self::$db->rawQuery('SELECT `instrument_id` AS v FROM `heal_empty_probe` ORDER BY `instrument_id`'),
            'v'
        );
        $this->assertSame(['a', 'b'], $values, 'The values came across, not the empty defaults.');

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
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'heal_empty_probe'
                         AND INDEX_NAME = 'idx_cfg'"
                ),
                'COLUMN_NAME'
            ),
            'and so does every secondary index.'
        );
    }

    /** A nullable stand-in nobody wrote to is empty too. */
    public function testAnUnwrittenNullableStandInIsAlsoHealed(): void
    {
        self::$db->rawQuery('DROP TABLE IF EXISTS `heal_null_probe`');
        self::$db->rawQuery(
            'CREATE TABLE `heal_null_probe` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                `sample_received_at_testing_lab_datetime` DATETIME NULL DEFAULT NULL,
                `sample_received_at_lab_datetime` DATETIME NULL DEFAULT NULL
            ) ENGINE=InnoDB'
        );
        self::$db->rawQuery(
            'INSERT INTO `heal_null_probe` (`sample_received_at_testing_lab_datetime`) VALUES
                ("2024-03-01 09:00:00"), ("2024-03-02 09:00:00")'
        );

        $this->assertSame(
            MIG_EXECUTED,
            $this->dispatch(
                'ALTER TABLE `heal_null_probe`
                 CHANGE `sample_received_at_testing_lab_datetime` `sample_received_at_lab_datetime`
                 DATETIME NULL DEFAULT NULL'
            )
        );

        $this->assertSame(
            ['2024-03-01 09:00:00', '2024-03-02 09:00:00'],
            array_column(
                self::$db->rawQuery('SELECT `sample_received_at_lab_datetime` AS v FROM `heal_null_probe` ORDER BY id'),
                'v'
            ),
            'Every stranded value has to arrive under the new name.'
        );
        $this->assertNotContains('sample_received_at_testing_lab_datetime', $this->columnsOf('heal_null_probe'));
    }

    /** The rename already happened; the old column is just a leftover. */
    public function testAnEmptyOldColumnBesideAPopulatedNewOneIsRetired(): void
    {
        self::$db->rawQuery('DROP TABLE IF EXISTS `heal_leftover_probe`');
        self::$db->rawQuery(
            'CREATE TABLE `heal_leftover_probe` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                `old_name` INT NULL DEFAULT NULL,
                `new_name` INT NULL DEFAULT NULL
            ) ENGINE=InnoDB'
        );
        self::$db->rawQuery('INSERT INTO `heal_leftover_probe` (`new_name`) VALUES (4), (5)');

        $this->assertSame(
            MIG_EXECUTED,
            $this->dispatch('ALTER TABLE `heal_leftover_probe` CHANGE `old_name` `new_name` INT NULL')
        );

        $this->assertNotContains('old_name', $this->columnsOf('heal_leftover_probe'));
        $this->assertSame(
            ['4', '5'],
            array_map('strval', array_column(
                self::$db->rawQuery('SELECT `new_name` AS v FROM `heal_leftover_probe` ORDER BY id'),
                'v'
            )),
            'and nothing may disturb the values that are already right.'
        );
    }

    /**
     * Both columns holding values is the one case nobody can decide for you.
     *
     * A row empty on one side may be one nothing ever wrote or one somebody
     * deliberately cleared. So this halts, having touched nothing.
     */
    public function testBothColumnsHoldingValuesHaltsWithoutTouchingAnything(): void
    {
        self::$db->rawQuery('DROP TABLE IF EXISTS `heal_both_probe`');
        self::$db->rawQuery(
            'CREATE TABLE `heal_both_probe` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                `old_name` DATETIME NULL DEFAULT NULL,
                `new_name` DATETIME NULL DEFAULT NULL
            ) ENGINE=InnoDB'
        );
        self::$db->rawQuery(
            'INSERT INTO `heal_both_probe` (`old_name`, `new_name`) VALUES
                ("2024-03-01 09:00:00", NULL),
                (NULL, "2024-11-01 09:00:00")'
        );

        $raised = null;
        try {
            $this->dispatch('ALTER TABLE `heal_both_probe` CHANGE `old_name` `new_name` DATETIME NULL');
        } catch (\Throwable $e) {
            $raised = $e;
        }

        $this->assertNotNull($raised, 'Two populated columns must stop the migration, not be guessed at.');
        $this->assertStringStartsWith(
            '`heal_both_probe` holds BOTH `old_name` and `new_name`, and both carry values',
            $raised->getMessage(),
            'naming the table and both columns.'
        );

        $this->assertContains('old_name', $this->columnsOf('heal_both_probe'), 'Nothing may be dropped.');
        $rows = self::$db->rawQuery('SELECT `old_name` AS o, `new_name` AS n FROM `heal_both_probe` ORDER BY id');
        $this->assertSame('2024-03-01 09:00:00', $rows[0]['o'], 'and no value may move.');
        $this->assertNull($rows[0]['n']);
        $this->assertSame('2024-11-01 09:00:00', $rows[1]['n']);
    }

    /** An empty stand-in that is indexed is more than a stand-in. */
    public function testAnIndexedStandInHalts(): void
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
        self::$db->rawQuery('INSERT INTO `heal_indexed_probe` (`old_name`) VALUES (1)');

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
