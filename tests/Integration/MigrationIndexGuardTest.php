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
 * Whether the migration runner can tell it already has an index.
 *
 * It used to answer that by name alone, so an index over the same column under
 * a different name was invisible: 5.7.56 added `idx_last_modified_datetime`
 * where sql/init.sql already ships `last_modified_datetime`, and every fresh
 * install came out carrying two copies of one index, paying the write
 * maintenance for both. Comparing the column list closes that.
 *
 * The comparison has to stay exact in two directions, which is most of what
 * these tests are for. A composite over (a, b) is not the index (a) and must
 * still be created. A UNIQUE index over the same columns as a plain one carries
 * a constraint the plain one does not, so treating them as the same would drop
 * that constraint silently -- a worse outcome than a redundant index.
 *
 * bin/migrate.php runs its own body on include, so its functions are lifted out
 * with the tokenizer rather than copied here. What runs below is the shipped
 * code, byte for byte.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class MigrationIndexGuardTest extends TestCase
{
    private static ?DatabaseService $db = null;

    public static function setUpBeforeClass(): void
    {
        self::$db = MigrationRunnerFunctions::connect();
        if (self::$db === null) {
            return;
        }

        // The shape sql/init.sql ships: the index is there, under its own name.
        self::$db->rawQuery('DROP TABLE IF EXISTS probe');
        self::$db->rawQuery(
            'CREATE TABLE probe (
                id INT AUTO_INCREMENT PRIMARY KEY,
                a VARCHAR(64) NULL,
                b VARCHAR(64) NULL,
                note VARCHAR(255) NULL,
                last_modified_datetime DATETIME NULL
            ) ENGINE=InnoDB'
        );
        self::$db->rawQuery('ALTER TABLE probe ADD INDEX `last_modified_datetime` (`last_modified_datetime`)');
        self::$db->rawQuery('ALTER TABLE probe ADD INDEX `plain_a` (`a`)');
        self::$db->rawQuery('ALTER TABLE probe ADD INDEX `note_prefix` (`note`(10))');
    }

    protected function setUp(): void
    {
        if (self::$db === null) {
            $this->markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run.');
        }
        $this->assertTrue(function_exists('equivalent_index_exists'), 'Runner functions were not loaded.');
        $this->assertTrue(function_exists('handle_idempotent_ddl'), 'The dispatcher was not loaded.');
    }

    /** How many indexes the table carries over exactly these columns. */
    private function indexesOver(string $table, array $columns): int
    {
        $rows = self::$db->rawQuery(
            "SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
              GROUP BY INDEX_NAME",
            [$table]
        );

        $wanted = implode(',', $columns);
        return count(array_filter($rows, static fn(array $r): bool => $r['cols'] === $wanted));
    }

    /** Whether an index over exactly these columns exists and is UNIQUE. */
    private function hasUniqueIndexOver(string $table, array $columns): bool
    {
        $rows = self::$db->rawQuery(
            "SELECT NON_UNIQUE, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS cols
               FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
              GROUP BY INDEX_NAME, NON_UNIQUE",
            [$table]
        );

        $wanted = implode(',', $columns);
        foreach ($rows as $r) {
            if ($r['cols'] === $wanted && (int) $r['NON_UNIQUE'] === 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether the table refuses a second row carrying the same value.
     *
     * Empties the table on the way out as well as on the way in: when there is
     * no constraint this leaves duplicate rows behind, and a later ADD UNIQUE
     * KEY over that column would then fail on the data rather than tell us
     * anything about the runner.
     */
    private function rejectsDuplicate(string $table, string $column, string $value): bool
    {
        self::$db->rawQuery("DELETE FROM `$table`");
        self::$db->rawQuery("INSERT INTO `$table` (`$column`) VALUES (?)", [$value]);

        $rejected = false;
        try {
            self::$db->rawQuery("INSERT INTO `$table` (`$column`) VALUES (?)", [$value]);
        } catch (\Throwable) {
            $rejected = true;
        }

        self::$db->rawQuery("DELETE FROM `$table`");
        return $rejected;
    }

    /**
     * A table of this test's own, dropped and rebuilt on the spot.
     *
     * The dispatcher tests below create indexes, and sharing `probe` with the
     * tests above would make them depend on the order PHPUnit happens to run
     * them in. Each gets its own table instead.
     */
    private function freshTable(string $name, array $indexes = []): string
    {
        self::$db->rawQuery("DROP TABLE IF EXISTS `$name`");
        self::$db->rawQuery(
            "CREATE TABLE `$name` (
                id INT AUTO_INCREMENT PRIMARY KEY,
                a VARCHAR(64) NULL,
                b VARCHAR(64) NULL,
                last_modified_datetime DATETIME NULL
            ) ENGINE=InnoDB"
        );
        foreach ($indexes as $ddl) {
            self::$db->rawQuery(sprintf($ddl, $name));
        }
        return $name;
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

    /** The case that caused this: same column, different name. */
    public function testAnIndexOverTheSameColumnIsFoundUnderAnotherName(): void
    {
        $this->assertFalse(
            index_exists(self::$db, 'probe', 'idx_last_modified_datetime'),
            'The name is genuinely free, which is why the name check alone let the duplicate through.'
        );
        $this->assertTrue(
            equivalent_index_exists(self::$db, 'probe', ['last_modified_datetime'], false),
            'An index over that column already exists and must be recognised whatever it is called.'
        );
    }

    /** A composite is not the same index as one over its leading column. */
    public function testACompositeIsNotTheSameAsItsLeadingColumn(): void
    {
        $this->assertFalse(
            equivalent_index_exists(self::$db, 'probe', ['a', 'b'], false),
            '(a, b) must still be created when only (a) exists.'
        );
        $this->assertTrue(
            equivalent_index_exists(self::$db, 'probe', ['a'], false),
            'and (a) itself is already there.'
        );
    }

    /** A UNIQUE index carries a constraint a plain one does not. */
    public function testUniquenessMustMatch(): void
    {
        $this->assertFalse(
            equivalent_index_exists(self::$db, 'probe', ['a'], true),
            'A UNIQUE index over (a) must still be created when only a plain (a) exists.'
        );
    }

    /** A prefix length makes two indexes incomparable, so nothing is skipped. */
    public function testPrefixedIndexesAreNeverTreatedAsEquivalent(): void
    {
        $this->assertNull(index_column_list('`note`(10)'), 'A prefix length is not comparable.');
        $this->assertFalse(
            equivalent_index_exists(self::$db, 'probe', ['note'], false),
            'The existing note index is prefixed, so it must not satisfy a request for the whole column.'
        );
    }

    /**
     * An ADD INDEX written without a name is recognised, a named one is not.
     *
     * MySQL names an unnamed index after its first column and appends _2, _3
     * and onward once that name is taken, so two of these in a row leave two
     * indexes and nothing collides to stop it. Twenty-four statements in the
     * migration history are written this way, which is how one instance came to
     * carry 63 copies of a single index. They have no name to compare, so the
     * column check is the only thing that can catch them -- and it only gets the
     * chance if they are routed to it rather than run straight through.
     */
    public function testUnnamedIndexStatementsAreRecognised(): void
    {
        $parsed = parse_unnamed_index_statement('ALTER TABLE `form_vl` ADD INDEX( `remote_sample_code_key`)');
        $this->assertNotNull($parsed, 'The 4.4.3 shape must be routed through the column check.');
        $this->assertSame('form_vl', $parsed[0]);
        $this->assertSame(['remote_sample_code_key'], $parsed[1]);
        $this->assertFalse($parsed[2]);

        $unique = parse_unnamed_index_statement('ALTER TABLE `t` ADD UNIQUE KEY (`a`,`b`)');
        $this->assertNotNull($unique);
        $this->assertSame(['a', 'b'], $unique[1]);
        $this->assertTrue($unique[2], 'Uniqueness has to survive parsing or the comparison is wrong.');

        $this->assertNull(
            parse_unnamed_index_statement('ALTER TABLE `t` ADD INDEX `named` (`a`)'),
            'A named index has its own handler and a name to compare; this must not take it.'
        );
        $this->assertNull(
            parse_unnamed_index_statement('ALTER TABLE `t` ADD COLUMN `a` INT'),
            'Anything that is not an index statement must fall through.'
        );
    }

    /** Column lists are parsed the way the ALTER routes hand them over. */
    public function testColumnListParsing(): void
    {
        $this->assertSame(['a'], index_column_list('`a`'));
        $this->assertSame(['a', 'b'], index_column_list('`a`, `b`'));
        $this->assertSame(['a', 'b'], index_column_list('a,b'));
        $this->assertNull(index_column_list(''), 'Nothing to compare.');
        $this->assertNull(index_column_list('`a` DESC'), 'An ordered index is not compared.');
    }

    /**
     * The invariant itself: replaying a migration cannot accumulate indexes.
     *
     * Everything above tests a helper. This tests the runner, because the
     * helpers being right buys nothing if handle_idempotent_ddl() stops routing
     * statements through them -- and that is precisely the failure that already
     * happened. Twenty-four unnamed ADD INDEX statements in the migration
     * history went straight to the server for years, MySQL appending _2, _3 to
     * each new copy, until one instance held 63 copies of one index.
     *
     * So: run the statement the way the runner runs it, ten times, and count.
     */
    public function testReplayingAnUnnamedAddIndexLeavesExactlyOneIndex(): void
    {
        $table = $this->freshTable('replay_probe');
        $this->assertSame(0, $this->indexesOver($table, ['b']), 'Nothing indexes b yet.');

        $sql = "ALTER TABLE `$table` ADD INDEX( `b`)";

        $this->assertSame(MIG_EXECUTED, $this->dispatch($sql), 'The first run must create it.');
        $this->assertSame(1, $this->indexesOver($table, ['b']));

        for ($i = 0; $i < 9; $i++) {
            $this->assertSame(
                MIG_SKIPPED,
                $this->dispatch($sql),
                'Every replay after the first must be recognised as already done.'
            );
        }

        $this->assertSame(
            1,
            $this->indexesOver($table, ['b']),
            'Ten runs of one statement left more than one index -- the accumulation bug is back.'
        );
    }

    /**
     * An unnamed ADD UNIQUE KEY is not satisfied by a plain index on the column.
     *
     * The dangerous direction. `equivalent_index_exists()` compares uniqueness,
     * but the unnamed route has to hand it the flag it parsed -- and if it ever
     * stops doing so, the runner reports the migration as already applied and
     * the constraint is simply never created. Nothing fails, nothing is logged,
     * and the table quietly accepts duplicates from then on.
     *
     * So this asserts the constraint by its effect, not by its metadata.
     */
    public function testAnUnnamedUniqueKeyIsStillCreatedWhenOnlyAPlainIndexExists(): void
    {
        $table = $this->freshTable('unnamed_unique_probe', ['ALTER TABLE `%s` ADD INDEX( `a`)']);

        $this->assertFalse(
            $this->hasUniqueIndexOver($table, ['a']),
            'Only a plain index is there to begin with.'
        );
        $this->assertFalse(
            $this->rejectsDuplicate($table, 'a', 'same'),
            'so duplicates are accepted to begin with.'
        );

        $this->assertSame(
            MIG_EXECUTED,
            $this->dispatch("ALTER TABLE `$table` ADD UNIQUE KEY (`a`)"),
            'A plain index over the column must not satisfy a request for a UNIQUE one.'
        );
        $this->assertTrue(
            $this->hasUniqueIndexOver($table, ['a']),
            'The UNIQUE index has to exist afterwards.'
        );
        $this->assertTrue(
            $this->rejectsDuplicate($table, 'a', 'same'),
            'and it has to actually constrain the column.'
        );

        // Having created it, the runner must recognise it on the next replay --
        // otherwise the fix for one bug is the other bug.
        $this->assertSame(
            MIG_SKIPPED,
            $this->dispatch("ALTER TABLE `$table` ADD UNIQUE KEY (`a`)"),
            'The second run must see the UNIQUE index it just created.'
        );
    }

    /**
     * The original 5.7.56 case, through the runner.
     *
     * sql/init.sql ships the index as `last_modified_datetime` and the migration
     * asks for `idx_last_modified_datetime`. The name is free, so a name-only
     * check creates a second copy on every fresh install.
     */
    public function testAnAddIndexUnderANewNameIsSkippedWhenTheColumnIsAlreadyIndexed(): void
    {
        $table = $this->freshTable(
            'named_probe',
            ['ALTER TABLE `%s` ADD INDEX `last_modified_datetime` (`last_modified_datetime`)']
        );

        $this->assertSame(
            MIG_SKIPPED,
            $this->dispatch(
                "ALTER TABLE `$table` ADD INDEX `idx_last_modified_datetime` (`last_modified_datetime`)"
            ),
            'The column is indexed already, under the name init.sql gives it.'
        );
        $this->assertSame(
            1,
            $this->indexesOver($table, ['last_modified_datetime']),
            'and no second copy was created.'
        );
    }

    /**
     * The runner must still build what is genuinely missing.
     *
     * A guard that skips everything would pass every test above and leave labs
     * without their indexes, which is the failure mode worth guarding against
     * once the other one is closed.
     */
    public function testTheRunnerStillCreatesAnIndexThatIsGenuinelyMissing(): void
    {
        $table = $this->freshTable('missing_probe', ['ALTER TABLE `%s` ADD INDEX `plain_a` (`a`)']);
        $this->assertSame(0, $this->indexesOver($table, ['a', 'b']), 'The composite is not there.');

        $this->assertSame(
            MIG_EXECUTED,
            $this->dispatch("ALTER TABLE `$table` ADD INDEX `idx_a_b` (`a`, `b`)"),
            '(a, b) is a different index from (a), so it must be created.'
        );
        $this->assertSame(1, $this->indexesOver($table, ['a', 'b']));

        $this->assertSame(
            MIG_EXECUTED,
            $this->dispatch("ALTER TABLE `$table` ADD UNIQUE INDEX `uniq_a` (`a`)"),
            'A UNIQUE index carries a constraint the existing plain one does not.'
        );
        // MIG_EXECUTED only says a statement ran. The route rewrites ADD UNIQUE
        // INDEX into a CREATE INDEX of its own, so whether UNIQUE survived that
        // rewrite is a separate question, and the whole point of the statement.
        $this->assertTrue(
            $this->hasUniqueIndexOver($table, ['a']),
            'The index was created without its UNIQUE constraint.'
        );
        $this->assertTrue(
            $this->rejectsDuplicate($table, 'a', 'same'),
            'and the database still accepts duplicates, so the constraint is not really there.'
        );
    }
}
