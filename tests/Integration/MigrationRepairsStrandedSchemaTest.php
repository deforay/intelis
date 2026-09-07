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
 * 5.7.61 puts back everything 5.2.9 adds, wherever an installation stopped.
 *
 * 5.2.9 stamps its own version on line 2 of 750 and carries seven months of
 * changes after it. An installation that upgraded while the file was shorter
 * recorded 5.2.9 as done and can never replay it, so whatever was appended
 * afterwards is missing there for good -- and there is no single cut-off to
 * repair up to, because every installation stopped wherever its own upgrade
 * happened to fall.
 *
 * This drives the shipped migration file, through the shipped runner, against
 * the schema sql/init.sql actually builds. Every earlier version of this
 * verification lived in a scratch script and was thrown away, which is how a
 * repair reached stable twice with defects in it: once missing sixteen columns
 * that a line-by-line reading of 5.2.9 had skipped, once assuming tables that
 * an instance stranded early does not have.
 *
 * The test is self-restoring by construction: it removes what a stranded
 * instance is missing, and the migration under test is the thing that puts it
 * back. A failure leaves the shared schema short, which is the honest outcome
 * -- the migration did not do its job.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class MigrationRepairsStrandedSchemaTest extends TestCase
{
    private const MIGRATION = '5.7.61';

    /** Identifiers that open a key or constraint rather than a column. */
    private const NOT_A_COLUMN = [
        'primary', 'key', 'unique', 'index', 'constraint', 'fulltext', 'spatial', 'foreign', 'check',
    ];

    /** Columns 5.7.59 renames; re-adding them would restore the old names. */
    private const RENAMED_AWAY_BY_5_7_59 = [
        'sample_received_at_testing_lab_datetime',
        'lab_storage_status',
    ];

    private static ?DatabaseService $db = null;

    public static function setUpBeforeClass(): void
    {
        self::$db = MigrationRunnerFunctions::connect();
        MigrationRunnerFunctions::load();   // split_top_level(), among the rest
    }

    protected function setUp(): void
    {
        if (self::$db === null) {
            $this->markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run.');
        }
    }

    /**
     * The migration's statements, as the runner would receive them.
     *
     * Comments and the version stamp are dropped: the stamp belongs to a real
     * run, and writing it here would tell every later test that this database
     * is a version it is not.
     *
     * @return list<string>
     */
    private function migrationStatements(): array
    {
        $path = dirname(__DIR__, 2) . '/sys/migrations/' . self::MIGRATION . '.sql';
        $this->assertFileExists($path, 'The migration under test has to exist.');

        $lines = array_filter(
            explode("\n", (string) file_get_contents($path)),
            static fn(string $l): bool => !preg_match('/^\s*--/', $l)
        );

        $statements = [];
        $buffer = '';
        $quote = null;
        $text = implode("\n", $lines);

        for ($i = 0, $n = strlen($text); $i < $n; $i++) {
            $char = $text[$i];

            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $i + 1 < $n) {
                    $buffer .= $text[++$i];
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        return array_values(array_filter(
            $statements,
            static fn(string $s): bool => stripos($s, 'UPDATE `system_config`') !== 0
        ));
    }

    /** Run the migration the way bin/migrate.php runs it. */
    private function applyMigration(): void
    {
        $io = new SymfonyStyle(new ArrayInput([]), new NullOutput());

        foreach ($this->migrationStatements() as $statement) {
            $result = handle_idempotent_ddl(self::$db, $io, $statement);
            if ($result === MIG_NOT_HANDLED) {
                self::$db->rawQuery($statement);
                assert_no_errno(self::$db, $statement);
            }
        }
    }

    /**
     * What the repair owes, read from 5.2.9 rather than from the repair.
     *
     * Deriving it from 5.7.61 would have made this test agree with whatever
     * that file happened to say: delete a statement and the assertion covering
     * it disappears with it. Both defects that reached stable were statements
     * that were never written, so the expectation has to come from the source
     * -- and the exclusions have to be stated here, deliberately, rather than
     * inherited.
     *
     * @return array{0: list<string>, 1: list<array{0: string, 1: string}>}
     */
    private function owedBy529(): array
    {
        $path = dirname(__DIR__, 2) . '/sys/migrations/5.2.9.sql';
        $this->assertFileExists($path);

        $tables = [];
        $columns = [];
        $seen = [];

        foreach ($this->statementsIn($path) as $statement) {
            $flat = (string) preg_replace('/\s+/', ' ', $statement);

            if (preg_match('/^CREATE TABLE (?:IF NOT EXISTS )?`?([A-Za-z0-9_]+)`?/i', $flat, $m)) {
                $table = strtolower($m[1]);
                // The per-table audit copies were replaced by audit_log in
                // 5.5.3 and the tables are gone; the current schema is the
                // authority on what still exists.
                if (!str_starts_with($table, 'audit_form_') && $this->tableExists($table)) {
                    $tables[] = $table;
                }
                continue;
            }

            if (!preg_match('/^ALTER TABLE `?([A-Za-z0-9_]+)`? (.+)$/i', $flat, $m)) {
                continue;
            }
            $table = strtolower($m[1]);
            if (str_starts_with($table, 'audit_form_') || !$this->tableExists($table)) {
                continue;
            }

            foreach (split_top_level($m[2]) as $action) {
                if (!preg_match('/^ADD (?:COLUMN )?`?([A-Za-z0-9_]+)`? \S/i', trim($action), $a)) {
                    continue;
                }
                $column = strtolower($a[1]);
                if (in_array($column, self::NOT_A_COLUMN, true)) {
                    continue;
                }
                // 5.7.59 renames these; putting them back would restore the
                // old names over the new ones.
                if (in_array($column, self::RENAMED_AWAY_BY_5_7_59, true)) {
                    continue;
                }
                $key = $table . '.' . $column;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $columns[] = [$table, $column];
            }
        }

        return [$tables, $columns];
    }

    /**
     * A SQL file split into statements.
     *
     * On top-level semicolons, respecting quotes. 5.2.9's longest ALTER runs to
     * 27 lines, and reading it a line at a time is how sixteen of its columns
     * went missing from a repair that claimed to cover them.
     *
     * @return list<string>
     */
    private function statementsIn(string $path): array
    {
        $lines = array_filter(
            explode("\n", (string) file_get_contents($path)),
            static fn(string $l): bool => !preg_match('/^\s*--/', $l)
        );

        $statements = [];
        $buffer = '';
        $quote = null;
        $text = implode("\n", $lines);

        for ($i = 0, $n = strlen($text); $i < $n; $i++) {
            $char = $text[$i];

            if ($quote !== null) {
                $buffer .= $char;
                if ($char === '\\' && $i + 1 < $n) {
                    $buffer .= $text[++$i];
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $buffer .= $char;
                continue;
            }

            if ($char === ';') {
                if (trim($buffer) !== '') {
                    $statements[] = trim($buffer);
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }

    private function columnExists(string $table, string $column): bool
    {
        return (int) (self::$db->rawQueryOne(
            "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1",
            [$table, $column]
        )['c'] ?? 0) > 0;
    }

    private function tableExists(string $table): bool
    {
        return (int) (self::$db->rawQueryOne(
            "SELECT COUNT(*) AS c FROM information_schema.TABLES
               WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1",
            [$table]
        )['c'] ?? 0) > 0;
    }

    /** The migration names something real: a repair of nothing would pass every test below. */
    public function testTheMigrationCoversTablesAndColumnsThatExistInTheSchema(): void
    {
        [$tables, $columns] = $this->owedBy529();

        $this->assertNotEmpty($tables, 'The repair has to create the tables 5.2.9 creates.');
        $this->assertGreaterThan(40, count($columns), 'and restore the columns it adds.');

        foreach ($tables as $table) {
            $this->assertTrue($this->tableExists($table), "sql/init.sql does not build `$table`.");
        }
        foreach ($columns as [$table, $column]) {
            $this->assertTrue(
                $this->columnExists($table, $column),
                "sql/init.sql does not declare `$table`.`$column`, so the repair would invent it."
            );
        }
    }

    /**
     * The hardest case: stranded before 5.2.9 created its tables at all.
     *
     * Nine tables gone and every column with them. An earlier version of this
     * repair assumed the tables were there and raised 1146, which the runner
     * treats as fatal -- so the upgrade halted here and never reached any later
     * version.
     */
    public function testItRepairsAnInstanceStrandedBeforeTheTablesExisted(): void
    {
        [$tables, $columns] = $this->owedBy529();

        self::$db->rawQuery('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            self::$db->rawQuery("DROP TABLE IF EXISTS `$table`");
        }
        foreach ($columns as [$table, $column]) {
            if ($this->tableExists($table) && $this->columnExists($table, $column)) {
                self::$db->rawQuery("ALTER TABLE `$table` DROP COLUMN `$column`");
            }
        }
        self::$db->rawQuery('SET FOREIGN_KEY_CHECKS = 1');

        $this->applyMigration();

        foreach ($tables as $table) {
            $this->assertTrue($this->tableExists($table), "`$table` was not restored.");
        }
        $missing = [];
        foreach ($columns as [$table, $column]) {
            if (!$this->columnExists($table, $column)) {
                $missing[] = "$table.$column";
            }
        }
        $this->assertSame([], $missing, 'Columns the repair left missing.');
    }

    /**
     * Half repaired: some of each table's columns already back.
     *
     * The state grouping could have broken. The runner takes a multi-action
     * ALTER apart only when part of it is already applied, and this is that
     * case -- every column of every group has to arrive whether or not its
     * neighbours were already there.
     */
    public function testItRepairsAnInstanceThatIsHalfRepairedAlready(): void
    {
        [, $columns] = $this->owedBy529();

        foreach ($columns as $i => [$table, $column]) {
            if ($i % 2 === 0 && $this->tableExists($table) && $this->columnExists($table, $column)) {
                self::$db->rawQuery("ALTER TABLE `$table` DROP COLUMN `$column`");
            }
        }

        $this->applyMigration();

        $missing = [];
        foreach ($columns as [$table, $column]) {
            if (!$this->columnExists($table, $column)) {
                $missing[] = "$table.$column";
            }
        }
        $this->assertSame([], $missing, 'A half-repaired instance was left short.');
    }

    /**
     * Running it again changes nothing.
     *
     * Migrations are replayed -- by a retried upgrade, by a repair run, by an
     * operator who is not sure whether the last one finished -- and a repair
     * that is not safe to run twice is a repair nobody can be told to run.
     */
    public function testRunningItAgainIsANoOp(): void
    {
        $this->applyMigration();

        $before = self::$db->rawQuery(
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, COLUMN_NAME"
        );

        $this->applyMigration();

        $after = self::$db->rawQuery(
            "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
               WHERE TABLE_SCHEMA = DATABASE() ORDER BY TABLE_NAME, COLUMN_NAME"
        );

        $this->assertSame($before, $after, 'A second run altered the schema.');
    }
}
