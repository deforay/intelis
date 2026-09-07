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
 * An ALTER carrying several actions applies all of them, not just the first.
 *
 * Every guard in the runner reads the first action of an ALTER and answers for
 * the whole statement. So `ADD a, ADD b` against a table that already has `a`
 * was reported as already applied, and `b` was never added -- no error, nothing
 * in the log, and the version stamped as done afterwards. Letting it run raw
 * instead is no better: MySQL fails the entire ALTER on the duplicate `a`, so
 * `b` is lost either way, and the runner treats 1060 as benign.
 *
 * This is not hypothetical. Three statements in 5.2.9 are written this way, one
 * of them the pair that adds facility_details.sts_token and sts_token_expiry --
 * which is how an installation ends up with the token column and not the expiry
 * column, and TokensService failing on a SELECT of both.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class MigrationMultiActionAlterTest extends TestCase
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

    /** Run a statement the way bin/migrate.php runs it. */
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
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                   ORDER BY ORDINAL_POSITION",
                [$table]
            ),
            'COLUMN_NAME'
        );
    }

    private function freshTable(string $name, string $columns): string
    {
        self::$db->rawQuery("DROP TABLE IF EXISTS `$name`");
        self::$db->rawQuery("CREATE TABLE `$name` (id INT AUTO_INCREMENT PRIMARY KEY, $columns) ENGINE=InnoDB");
        return $name;
    }

    /** Nothing already present: every action applies. */
    public function testEveryActionOfAMultiActionAlterApplies(): void
    {
        $t = $this->freshTable('multi_probe', 'facility_type VARCHAR(10) NULL');

        $this->assertSame(
            MIG_EXECUTED,
            $this->dispatch(
                "ALTER TABLE `$t` ADD `sts_token` VARCHAR(64) NULL DEFAULT NULL AFTER `facility_type`,"
                . " ADD `sts_token_expiry` DATETIME NULL DEFAULT NULL AFTER `sts_token`"
            )
        );

        $this->assertContains('sts_token', $this->columnsOf($t));
        $this->assertContains('sts_token_expiry', $this->columnsOf($t));
    }

    /**
     * The case that loses data: the first column is already there.
     *
     * The partial state a half-finished migration leaves behind, and the one
     * where the whole statement used to be written off as already applied.
     */
    public function testTheRemainingActionsApplyWhenTheFirstIsAlreadySatisfied(): void
    {
        $t = $this->freshTable('multi_partial_probe', 'facility_type VARCHAR(10) NULL');
        self::$db->rawQuery("ALTER TABLE `$t` ADD `sts_token` VARCHAR(64) NULL DEFAULT NULL");

        $this->assertSame(
            MIG_EXECUTED,
            $this->dispatch(
                "ALTER TABLE `$t` ADD `sts_token` VARCHAR(64) NULL DEFAULT NULL AFTER `facility_type`,"
                . " ADD `sts_token_expiry` DATETIME NULL DEFAULT NULL AFTER `sts_token`"
            ),
            'Something still had to be done, so this is not a skip.'
        );

        $this->assertContains(
            'sts_token_expiry',
            $this->columnsOf($t),
            'The second action must not be lost because the first was already applied.'
        );
    }

    /** All actions already satisfied is a skip, and changes nothing. */
    public function testAFullySatisfiedMultiActionAlterIsSkipped(): void
    {
        $t = $this->freshTable('multi_satisfied_probe', 'facility_type VARCHAR(10) NULL');
        self::$db->rawQuery("ALTER TABLE `$t` ADD `a` INT NULL, ADD `b` INT NULL");
        $before = $this->columnsOf($t);

        $this->assertSame(
            MIG_SKIPPED,
            $this->dispatch("ALTER TABLE `$t` ADD `a` INT NULL, ADD `b` INT NULL")
        );
        $this->assertSame($before, $this->columnsOf($t), 'A skip must leave the table alone.');
    }

    /**
     * A comma inside a type or a quoted default does not separate an action.
     *
     * DECIMAL(10,2) and ENUM('yes','no') are one action each. Splitting on
     * every comma would turn one ADD into two halves of nonsense, and the
     * second half would then be run raw.
     */
    public function testCommasInsideTypesAndDefaultsDoNotSplitTheStatement(): void
    {
        $t = $this->freshTable('multi_comma_probe', 'placeholder INT NULL');

        $this->assertSame(
            MIG_EXECUTED,
            $this->dispatch(
                "ALTER TABLE `$t` ADD `amount` DECIMAL(10,2) NULL DEFAULT NULL,"
                . " ADD `flag` ENUM('yes','no') NOT NULL DEFAULT 'no'"
            )
        );

        $columns = $this->columnsOf($t);
        $this->assertContains('amount', $columns);
        $this->assertContains('flag', $columns);
    }

    /** A composite key's own comma is not an action separator either. */
    public function testACompositeKeyIsOneAction(): void
    {
        $t = $this->freshTable('multi_key_probe', 'a INT NULL, b INT NULL');

        $this->assertSame(MIG_EXECUTED, $this->dispatch("ALTER TABLE `$t` ADD INDEX `ab` (`a`, `b`)"));

        $cols = array_column(
            self::$db->rawQuery(
                "SELECT COLUMN_NAME FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = 'ab'
                   ORDER BY SEQ_IN_INDEX",
                [$t]
            ),
            'COLUMN_NAME'
        );
        $this->assertSame(['a', 'b'], $cols, 'The index has to keep both of its columns.');
    }

    /**
     * A single-action ALTER still goes through its own guard.
     *
     * The split must not become the only path, or the guards it was built to
     * preserve stop being reached.
     */
    public function testASingleActionAlterIsStillGuarded(): void
    {
        $t = $this->freshTable('multi_single_probe', 'a INT NULL');

        $this->assertSame(MIG_EXECUTED, $this->dispatch("ALTER TABLE `$t` ADD `b` INT NULL"));
        $this->assertSame(
            MIG_SKIPPED,
            $this->dispatch("ALTER TABLE `$t` ADD `b` INT NULL"),
            'The second run has to be recognised as already applied.'
        );
    }

    /**
     * An action no guard recognises still runs, rather than vanishing with the split.
     *
     * MODIFY has no idempotence guard -- there is nothing to compare -- so on
     * its own the runner executes it raw. Inside a multi-action ALTER it has to
     * keep doing that, or splitting the statement quietly throws away exactly
     * the actions the guards were never able to check.
     */
    public function testAnActionWithNoGuardIsStillExecuted(): void
    {
        $t = $this->freshTable('multi_unguarded_probe', 'a INT NULL');

        $this->dispatch("ALTER TABLE `$t` ADD `c` INT NULL, MODIFY `a` BIGINT NULL");

        $this->assertContains('c', $this->columnsOf($t));

        $type = self::$db->rawQueryOne(
            "SELECT DATA_TYPE FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'a' LIMIT 1",
            [$t]
        )['DATA_TYPE'] ?? null;

        $this->assertSame(
            'bigint',
            strtolower((string) $type),
            'The MODIFY was dropped: no guard understood it, so nothing ran it.'
        );
    }

    /** Mixed actions: a column add beside an index add. */
    public function testAColumnAndAnIndexInOneStatementBothApply(): void
    {
        $t = $this->freshTable('multi_mixed_probe', 'a INT NULL');

        $this->assertSame(
            MIG_EXECUTED,
            $this->dispatch("ALTER TABLE `$t` ADD `c` INT NULL, ADD INDEX `idx_a` (`a`)")
        );

        $this->assertContains('c', $this->columnsOf($t));
        $this->assertNotEmpty(
            self::$db->rawQuery(
                "SELECT INDEX_NAME FROM information_schema.STATISTICS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = 'idx_a'",
                [$t]
            ),
            'The index action has to apply too.'
        );
    }
}
