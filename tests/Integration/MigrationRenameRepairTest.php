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
 * Whether repairing a missing column keeps the values that column already holds.
 *
 * 5.7.59 repairs four columns that 5.2.9 renamed or added and did not land
 * everywhere -- it was appended to for seven months after its own version was
 * closed, so an instance that upgraded early ran half of it and can never run
 * the rest. Two of the four are renames, and that is what makes the repair
 * delicate rather than routine: on an instance that missed one, the old column
 * is still there holding every value. `intelis check` compares against
 * sql/init.sql, sees only that the new name is absent, and prints an ADD.
 * Running that ADD leaves the data behind in a column nothing reads any more
 * and hands the application an empty one -- every stored received-at-lab date
 * gone, with no error anywhere.
 *
 * So the migration writes each rename as a CHANGE followed by a guarded ADD,
 * and these tests assert the pair by its effect on the data from all three
 * starting states an installation can be in.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class MigrationRenameRepairTest extends TestCase
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
        $this->assertTrue(function_exists('handle_idempotent_ddl'), 'The dispatcher was not loaded.');
    }

    /** Run a statement the way bin/migrate.php runs it. */
    private function dispatch(string $sql): int
    {
        return handle_idempotent_ddl(
            self::$db,
            new SymfonyStyle(new ArrayInput([]), new NullOutput()),
            $sql
        );
    }

    /**
     * A form_generic in one of the states 5.7.59 has to cope with.
     *
     * $column is the name the received-at-lab date is carried under here, or
     * null for a table that has neither -- the state the migration claims
     * cannot occur, kept so the claim is tested rather than asserted.
     */
    private function formGeneric(string $name, ?string $column): string
    {
        self::$db->rawQuery("DROP TABLE IF EXISTS `$name`");
        self::$db->rawQuery(
            "CREATE TABLE `$name` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sample_received_at_hub_datetime DATETIME NULL"
            . ($column === null ? '' : ",\n `$column` DATETIME NULL")
            . ') ENGINE=InnoDB'
        );
        return $name;
    }

    /** The pair of statements 5.7.59 uses, against whichever table. */
    private function repairReceivedAtLab(string $table): array
    {
        return [
            $this->dispatch(
                "ALTER TABLE `$table` CHANGE `sample_received_at_testing_lab_datetime` "
                    . '`sample_received_at_lab_datetime` DATETIME NULL DEFAULT NULL'
            ),
            $this->dispatch(
                "ALTER TABLE `$table` ADD COLUMN `sample_received_at_lab_datetime` "
                    . 'DATETIME NULL DEFAULT NULL AFTER `sample_received_at_hub_datetime`'
            ),
        ];
    }

    private function readReceivedAtLab(string $table): ?string
    {
        $row = self::$db->rawQueryOne("SELECT `sample_received_at_lab_datetime` AS d FROM `$table`");
        return $row['d'] ?? null;
    }

    /**
     * The failure this migration exists to avoid, asserted on the data.
     *
     * An instance that missed 5.2.9's October half still calls the column
     * `sample_received_at_testing_lab_datetime` and has every received-at-lab
     * date in it. After the repair the date has to be readable under the new
     * name. If the ADD had run on its own it would be NULL here, which is the
     * whole point of the test: the metadata looks correct either way.
     */
    public function testARenameCarriesTheExistingValuesToTheNewName(): void
    {
        $table = $this->formGeneric('rename_probe', 'sample_received_at_testing_lab_datetime');
        self::$db->rawQuery(
            "INSERT INTO `$table` (`sample_received_at_testing_lab_datetime`) VALUES (?)",
            ['2024-03-14 09:30:00']
        );

        [$change, $add] = $this->repairReceivedAtLab($table);

        $this->assertSame(MIG_EXECUTED, $change, 'The old name is present, so the rename must run.');
        $this->assertSame(MIG_SKIPPED, $add, 'and the add must then find the column already there.');

        $this->assertSame(
            '2024-03-14 09:30:00',
            $this->readReceivedAtLab($table),
            'The repair added a column instead of renaming one -- every stored date was lost.'
        );
    }

    /** An instance already on the new name is left alone, twice over. */
    public function testAnInstanceAlreadyOnTheNewNameIsUntouched(): void
    {
        $table = $this->formGeneric('already_probe', 'sample_received_at_lab_datetime');
        self::$db->rawQuery(
            "INSERT INTO `$table` (`sample_received_at_lab_datetime`) VALUES (?)",
            ['2025-11-02 16:45:00']
        );

        foreach ([1, 2] as $run) {
            [$change, $add] = $this->repairReceivedAtLab($table);
            $this->assertSame(MIG_SKIPPED, $change, "Run $run: the rename is already applied.");
            $this->assertSame(MIG_SKIPPED, $add, "Run $run: and the column is already there.");
            $this->assertSame(
                '2025-11-02 16:45:00',
                $this->readReceivedAtLab($table),
                "Run $run: a no-op repair must not disturb the value."
            );
        }
    }

    /**
     * With neither name present the pair still lands the column.
     *
     * The migration argues this state cannot occur, because init.sql declares
     * one name and 4.4.9 the other. If that is ever wrong, the CHANGE falls
     * through to raw execution and MySQL answers 1054 -- which the runner does
     * not treat as benign, so the migration halts and the instance is stranded
     * on 5.7.58. The dispatcher does not reach the server in that case, and the
     * ADD behind it is what makes the pair safe to be wrong about.
     */
    public function testNeitherNamePresentStillLeavesTheColumn(): void
    {
        $table = $this->formGeneric('neither_probe', null);

        $this->assertSame(
            MIG_NOT_HANDLED,
            $this->dispatch(
                "ALTER TABLE `$table` CHANGE `sample_received_at_testing_lab_datetime` "
                    . '`sample_received_at_lab_datetime` DATETIME NULL DEFAULT NULL'
            ),
            'With neither end present the runner cannot guess intent and hands the statement back.'
        );
        $this->assertSame(
            MIG_EXECUTED,
            $this->dispatch(
                "ALTER TABLE `$table` ADD COLUMN `sample_received_at_lab_datetime` "
                    . 'DATETIME NULL DEFAULT NULL AFTER `sample_received_at_hub_datetime`'
            ),
            'so the add behind it has to create the column.'
        );
    }

    /**
     * The same pair over lab_storage, where the column is NOT NULL.
     *
     * Worth its own test because the definition differs in the way that bites:
     * `storage_status` is NOT NULL DEFAULT 'active', so an ADD on a populated
     * table succeeds and fills every row with 'active'. A shelf marked
     * inactive would come back online rather than come back empty, which is
     * the same defect wearing a disguise a NULL column cannot wear.
     */
    public function testANotNullRenameDoesNotResetEveryRowToItsDefault(): void
    {
        self::$db->rawQuery('DROP TABLE IF EXISTS `lab_storage_probe`');
        self::$db->rawQuery(
            "CREATE TABLE `lab_storage_probe` (
                storage_id CHAR(50) NOT NULL PRIMARY KEY,
                lab_id INT NOT NULL,
                lab_storage_status VARCHAR(10) NOT NULL DEFAULT 'active'
            ) ENGINE=InnoDB"
        );
        self::$db->rawQuery(
            "INSERT INTO `lab_storage_probe` (storage_id, lab_id, lab_storage_status) VALUES (?, ?, ?)",
            ['shelf-1', 1, 'inactive']
        );

        $change = $this->dispatch(
            'ALTER TABLE `lab_storage_probe` CHANGE `lab_storage_status` `storage_status` '
                . "VARCHAR(10) NOT NULL DEFAULT 'active'"
        );
        $add = $this->dispatch(
            'ALTER TABLE `lab_storage_probe` ADD COLUMN `storage_status` '
                . "VARCHAR(10) NOT NULL DEFAULT 'active' AFTER `lab_id`"
        );

        $this->assertSame(MIG_EXECUTED, $change);
        $this->assertSame(MIG_SKIPPED, $add);

        $row = self::$db->rawQueryOne('SELECT `storage_status` AS s FROM `lab_storage_probe`');
        $this->assertSame(
            'inactive',
            $row['s'] ?? null,
            "The retired shelf came back as 'active' -- the repair reset the column to its default."
        );
    }
}
