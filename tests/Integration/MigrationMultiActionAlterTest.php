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
        // As above: one action already applied, so the split path is the one
        // under test rather than the whole-statement shortcut.
        $t = $this->freshTable('multi_comma_probe', 'placeholder INT NULL');
        self::$db->rawQuery("ALTER TABLE `$t` ADD `amount` DECIMAL(10,2) NULL DEFAULT NULL");

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

    /**
     * A quoted comma outside any parentheses does not split the statement.
     *
     * ENUM('yes','no') is protected by parenthesis depth alone, so a test using
     * only that still passes with every quote rule deleted. `DEFAULT 'yes,no'`
     * has nothing but the quotes to protect it.
     */
    public function testAQuotedCommaOutsideParenthesesDoesNotSplitTheStatement(): void
    {
        // `other` is already there, so the statement cannot run whole and the
        // splitter is actually reached. Without that the quote handling is
        // never exercised at all.
        $t = $this->freshTable('multi_quoted_probe', 'placeholder INT NULL');
        self::$db->rawQuery("ALTER TABLE `$t` ADD `other` INT NULL");

        $this->dispatch(
            "ALTER TABLE `$t` ADD `label` VARCHAR(20) NOT NULL DEFAULT 'yes,no',"
            . " ADD `other` INT NULL"
        );

        $columns = $this->columnsOf($t);
        $this->assertContains('label', $columns, 'The split cut inside the quoted default.');
        $this->assertContains('other', $columns);

        $this->assertSame(
            'yes,no',
            (string) (self::$db->rawQueryOne(
                "SELECT COLUMN_DEFAULT AS d FROM information_schema.COLUMNS
                   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = 'label' LIMIT 1",
                [$t]
            )['d'] ?? ''),
            'and the default has to survive intact.'
        );
    }

    /**
     * An already-satisfied action must not take the actions after it down.
     *
     * The unguarded ones are the danger: a duplicate FULLTEXT index raises 1061
     * before the loop reaches the column add beside it, and 1061 is benign to
     * the migration loop, so the version advances with the column missing --
     * the exact failure this branch exists to prevent, reintroduced one level
     * down.
     */
    public function testABenignErrorOnOneActionDoesNotStopTheRest(): void
    {
        $t = $this->freshTable('multi_benign_probe', 'body TEXT NULL');
        self::$db->rawQuery("ALTER TABLE `$t` ADD FULLTEXT INDEX `ft` (`body`)");

        $this->dispatch("ALTER TABLE `$t` ADD FULLTEXT INDEX `ft` (`body`), ADD COLUMN `x` INT NULL");

        $this->assertContains(
            'x',
            $this->columnsOf($t),
            'The duplicate index ended the run and the column was never added.'
        );
    }

    /**
     * Nothing applied yet means the statement runs as written.
     *
     * MySQL rebuilds the table for most of these, and 5.2.1 changes form_vl in
     * one ALTER of 51 clauses. Splitting when there is nothing to repair turns
     * a single rebuild of the largest table in the schema into 51 of them, so
     * the split has to stay the exception rather than the rule.
     */
    public function testAnUntouchedMultiActionAlterRunsAsOneStatement(): void
    {
        $t = $this->freshTable('multi_batch_probe', 'a INT NULL');

        $before = (int) (self::$db->rawQueryOne(
            "SHOW SESSION STATUS LIKE 'Com_alter_table'"
        )['Value'] ?? 0);

        $this->assertSame(
            MIG_EXECUTED,
            $this->dispatch("ALTER TABLE `$t` ADD `b` INT NULL, ADD `c` INT NULL, ADD `d` INT NULL")
        );

        $after = (int) (self::$db->rawQueryOne(
            "SHOW SESSION STATUS LIKE 'Com_alter_table'"
        )['Value'] ?? 0);

        $columns = $this->columnsOf($t);
        foreach (['b', 'c', 'd'] as $c) {
            $this->assertContains($c, $columns);
        }
        $this->assertSame(
            1,
            $after - $before,
            'Three columns nothing had yet must cost one ALTER, not three.'
        );
    }

    /**
     * A comma that separates columns of an option, not actions.
     *
     * `ORDER BY c1, c2` is one option carrying a column list. Split on that
     * comma and the second half becomes `ALTER TABLE t c2`, which is not SQL,
     * so a valid migration fails part-applied.
     */
    public function testAnOptionCarryingAColumnListIsNotSplit(): void
    {
        $t = $this->freshTable('multi_orderby_probe', 'a INT NULL, b INT NULL');
        self::$db->rawQuery("INSERT INTO `$t` (a, b) VALUES (2, 1), (1, 2)");

        $before = (int) (self::$db->rawQueryOne("SHOW SESSION STATUS LIKE 'Com_alter_table'")['Value'] ?? 0);

        $this->dispatch("ALTER TABLE `$t` ORDER BY `a`, `b`");

        $after = (int) (self::$db->rawQueryOne("SHOW SESSION STATUS LIKE 'Com_alter_table'")['Value'] ?? 0);

        // Counting the ALTERs, not re-reading the columns: both columns exist
        // before and after whatever happens, so a version that quietly ran
        // nothing at all would satisfy an assertion about them.
        $this->assertSame(
            1,
            $after - $before,
            'The statement has to run, once, rather than be split into halves that are not SQL.'
        );
        $this->assertSame(
            ['a', 'b'],
            array_values(array_intersect(['a', 'b'], $this->columnsOf($t))),
            'and the table has to survive it intact.'
        );
    }

    /**
     * A real failure is still raised, not written off as already applied.
     *
     * Only the codes that mean "already applied" may be swallowed. Treating
     * every error that way would turn the repair loop into the very thing it
     * was built to stop: a migration that reports success having done nothing.
     */
    public function testAGenuineErrorInOneActionStillFails(): void
    {
        $t = $this->freshTable('multi_error_probe', 'c INT NULL');

        $this->expectException(\Throwable::class);

        // Whole, this fails 1060 on the duplicate `c` -- benign, so the split
        // path is entered. Taken apart, the index over `d` runs before the
        // column `d` exists and fails 1072, which is not benign and has to
        // surface rather than be filed as already applied.
        $this->dispatch(
            "ALTER TABLE `$t` ADD `c` INT NULL, ADD INDEX `idx_d` (`d`), ADD COLUMN `d` INT NULL"
        );
    }

    /**
     * A multi-rename interrupted partway still finishes.
     *
     * 4.4.9 renames four system_admin columns in one ALTER. Interrupted after
     * the first, the statement fails 1054 on the next run -- the old first
     * column is gone -- and 1054 is not a benign code. Rethrowing it left the
     * installation stuck on 4.4.9 with no way forward, which is exactly the
     * replay deadlock this guard exists to prevent. So the split has to be
     * reached on any failure, not only on a benign one.
     */
    public function testAPartlyAppliedMultiRenameStillCompletes(): void
    {
        $t = $this->freshTable('multi_rename_probe', 'old_a INT NULL, old_b INT NULL, old_c INT NULL');

        // The interruption: the first rename landed, the rest did not.
        self::$db->rawQuery("ALTER TABLE `$t` CHANGE `old_a` `new_a` INT NULL");

        $this->dispatch(
            "ALTER TABLE `$t` CHANGE `old_a` `new_a` INT NULL,"
            . " CHANGE `old_b` `new_b` INT NULL,"
            . " CHANGE `old_c` `new_c` INT NULL"
        );

        $columns = $this->columnsOf($t);
        foreach (['new_a', 'new_b', 'new_c'] as $expected) {
            $this->assertContains($expected, $columns, "`$expected` was never renamed.");
        }
        foreach (['old_b', 'old_c'] as $gone) {
            $this->assertNotContains($gone, $columns, "`$gone` should have been renamed away.");
        }
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
