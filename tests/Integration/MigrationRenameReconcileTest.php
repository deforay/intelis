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
     * Both names present halts, and touches nothing.
     *
     * The important half is what did NOT happen: no value moved, no column was
     * dropped, and the migration did not report itself applied.
     */
    public function testBothNamesPresentHaltsWithoutTouchingAnything(): void
    {
        self::$db->rawQuery('DROP TABLE IF EXISTS `reconcile_both_probe`');
        self::$db->rawQuery(
            'CREATE TABLE `reconcile_both_probe` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                `sample_received_at_testing_lab_datetime` DATETIME NULL DEFAULT NULL,
                `sample_received_at_lab_datetime` DATETIME NULL DEFAULT NULL
            ) ENGINE=InnoDB'
        );
        self::$db->rawQuery(
            'INSERT INTO `reconcile_both_probe`
                (`sample_received_at_testing_lab_datetime`, `sample_received_at_lab_datetime`) VALUES
                ("2024-03-01 09:00:00", NULL),
                (NULL, "2024-11-01 09:00:00")'
        );

        $raised = null;
        try {
            $this->dispatch(
                'ALTER TABLE `reconcile_both_probe`
                 CHANGE `sample_received_at_testing_lab_datetime` `sample_received_at_lab_datetime`
                 DATETIME NULL DEFAULT NULL'
            );
        } catch (\Throwable $e) {
            $raised = $e;
        }

        $this->assertNotNull($raised, 'An ambiguous state must stop the migration, not be guessed at.');

        // Named in place, not merely mentioned somewhere in the text: the
        // operator has to be able to read off which table and which two
        // columns without going looking for them.
        $this->assertStringStartsWith(
            '`reconcile_both_probe` holds BOTH `sample_received_at_testing_lab_datetime` '
            . 'and `sample_received_at_lab_datetime`',
            $raised->getMessage(),
            'The message has to name the table and both columns, in that order.'
        );
        $this->assertStringContainsString(
            'drop `sample_received_at_testing_lab_datetime`',
            $raised->getMessage(),
            'and say what to do about it.'
        );

        $this->assertContains(
            'sample_received_at_testing_lab_datetime',
            $this->columnsOf('reconcile_both_probe'),
            'Nothing may be dropped: the old column can be half of a key.'
        );

        $rows = self::$db->rawQuery(
            'SELECT `sample_received_at_testing_lab_datetime` AS o, `sample_received_at_lab_datetime` AS n
               FROM `reconcile_both_probe` ORDER BY id'
        );
        $this->assertSame('2024-03-01 09:00:00', $rows[0]['o'], 'The old value stays where it was.');
        $this->assertNull($rows[0]['n'], 'and nothing was written over the gap.');
        $this->assertNull($rows[1]['o']);
        $this->assertSame('2024-11-01 09:00:00', $rows[1]['n'], 'A value written since is untouched.');
    }

    /**
     * A column that is half of a key is never dropped on this path.
     *
     * instrument_controls.config_id is half of PRIMARY KEY(test_type,
     * config_id) until 5.2.8 renames it. Dropping it rather than renaming it
     * reduces the primary key instead of moving it.
     */
    public function testAKeyCarryingColumnIsLeftIntact(): void
    {
        self::$db->rawQuery('DROP TABLE IF EXISTS `reconcile_key_probe`');
        self::$db->rawQuery(
            'CREATE TABLE `reconcile_key_probe` (
                `test_type` VARCHAR(50) NOT NULL,
                `config_id` VARCHAR(50) NOT NULL,
                `instrument_id` VARCHAR(50) NOT NULL DEFAULT "",
                PRIMARY KEY (`test_type`, `config_id`)
            ) ENGINE=InnoDB'
        );
        self::$db->rawQuery(
            'INSERT INTO `reconcile_key_probe` (`test_type`, `config_id`) VALUES ("vl", "a"), ("vl", "b")'
        );

        try {
            $this->dispatch(
                'ALTER TABLE `reconcile_key_probe` CHANGE `config_id` `instrument_id` VARCHAR(50) NOT NULL'
            );
        } catch (\Throwable) {
            // Halting is the expected outcome; what matters is the table below.
        }

        $key = array_column(
            self::$db->rawQuery(
                "SELECT COLUMN_NAME FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reconcile_key_probe'
                     AND INDEX_NAME = 'PRIMARY' ORDER BY SEQ_IN_INDEX"
            ),
            'COLUMN_NAME'
        );

        $this->assertSame(
            ['test_type', 'config_id'],
            $key,
            'The primary key must be exactly as it was; a drop here would silently shorten it.'
        );
        $this->assertSame(
            '2',
            (string) (self::$db->rawQueryOne('SELECT COUNT(*) AS c FROM `reconcile_key_probe`')['c'] ?? ''),
            'and both rows must survive.'
        );
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
