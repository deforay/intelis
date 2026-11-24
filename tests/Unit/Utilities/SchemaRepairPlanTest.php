<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SchemaRepairPlan;

/**
 * bin/heal-schema.php's plan, read from the real sql/init.sql and migrations.
 *
 * The plan only adds. These tests pin the three cases where adding the
 * declared table or column would be wrong: a later migration adds it with a
 * backfill, a migration renames the old name into it, and a NOT NULL column
 * with no default lands under existing rows.
 */
final class SchemaRepairPlanTest extends TestCase
{
    private static string $root;

    private static string $init;

    public static function setUpBeforeClass(): void
    {
        self::$root = dirname(__DIR__, 3);
        require_once self::$root . '/bin/lib/SchemaRepairPlan.php';
        self::$init = (string) file_get_contents(self::$root . '/sql/init.sql');
    }

    public function testAnEmptyDatabaseGetsOneCreatePerTableAndNothingElse(): void
    {
        $plan = (new SchemaRepairPlan(self::$init))->build([]);

        $this->assertCount(preg_match_all('/^CREATE TABLE /m', self::$init), $plan);
        foreach ($plan as $step) {
            $parser = new Parser($step['sql']);
            $this->assertSame([], $parser->errors, $step['table']);
            $this->assertCount(1, $parser->statements, 'Each repair is one statement.');
            $this->assertInstanceOf(
                CreateStatement::class,
                $parser->statements[0],
                'Never the dump\'s data or session commands.'
            );
            $this->assertTrue($step['create']);
        }
    }

    public function testANewTableKeepsItsKeysAndForeignKeys(): void
    {
        $plan = $this->byTable((new SchemaRepairPlan(self::$init))->build([]));

        $this->assertStringContainsString('PRIMARY KEY', $plan['form_vl']);
        $this->assertStringContainsString('FOREIGN KEY', $plan['covid19_tests']);
    }

    public function testOnlyTheMissingColumnsAreAdded(): void
    {
        $columns = $this->everyColumn();
        $columns['instruments'] = array_values(array_diff($columns['instruments'], ['instrument_make', 'added_on']));

        $plan = (new SchemaRepairPlan(self::$init))->build($columns);

        $this->assertCount(1, $plan);
        $this->assertSame('instruments', $plan[0]['table']);
        $this->assertFalse($plan[0]['create']);
        $this->assertStringStartsWith('ALTER TABLE `instruments` ADD COLUMN `instrument_make`', $plan[0]['sql']);
        $this->assertStringContainsString('ADD COLUMN `added_on`', $plan[0]['sql']);
    }

    public function testColumnNamesMatchWhateverTheirCase(): void
    {
        $columns = $this->everyColumn();
        $columns['form_vl'] = array_map('strtoupper', $columns['form_vl']);

        $this->assertSame([], (new SchemaRepairPlan(self::$init))->build($columns));
    }

    public function testANotNullColumnWithNoDefaultIsAddedNullable(): void
    {
        $init = "CREATE TABLE `t` (\n  `id` int NOT NULL,\n  `code` varchar(10) NOT NULL,\n"
            . "  `kind` varchar(10) NOT NULL DEFAULT 'a',\n  PRIMARY KEY (`id`)\n) ENGINE=InnoDB;\n";

        $plan = (new SchemaRepairPlan($init))->build(['t' => ['id']]);

        $this->assertStringContainsString(
            'ADD COLUMN `code` varchar(10) NULL',
            $plan[0]['sql'],
            'Existing rows must not read as code "".'
        );
        $this->assertStringContainsString("ADD COLUMN `kind` varchar(10) NOT NULL DEFAULT 'a'", $plan[0]['sql']);
    }

    public function testWhatALaterMigrationAddsIsLeftForIt(): void
    {
        // 5.8.6 adds instruments.added_on empty, fills it from updated_datetime,
        // then sets its default. Adding it first would date every instrument today.
        $columns = $this->everyColumn();
        $columns['instruments'] = array_values(array_diff($columns['instruments'], ['instrument_make', 'added_on']));
        unset($columns['request_pull_state']);

        $repair = new SchemaRepairPlan(self::$init);
        $repair->deferPending(schema_pending_additions(self::$root . '/sys/migrations', '5.8.3'));

        $this->assertSame([], $repair->build($columns));
        $this->assertSame(3, $repair->pendingCount());
    }

    public function testOnlyWhatIsMissingCountsAsPending(): void
    {
        $repair = new SchemaRepairPlan(self::$init);
        $repair->deferPending(schema_pending_additions(self::$root . '/sys/migrations', '5.8.3'));

        $this->assertSame([], $repair->build($this->everyColumn()));
        $this->assertSame(0, $repair->pendingCount(), 'Nothing is missing, so nothing is waiting on a migration.');
    }

    public function testAMigrationAlreadyRunLeavesNothingPending(): void
    {
        $columns = $this->everyColumn();
        $columns['instruments'] = array_values(array_diff($columns['instruments'], ['added_on']));

        $repair = new SchemaRepairPlan(self::$init);
        $repair->deferPending(schema_pending_additions(self::$root . '/sys/migrations', '5.8.10'));

        $this->assertCount(1, $repair->build($columns), 'At 5.8.10 a missing added_on is drift.');
        $this->assertSame(0, $repair->pendingCount());
    }

    public function testARenamedColumnStillUnderItsOldNameIsLeftForTheMigration(): void
    {
        // 5.2.9 renames lab_storage.lab_storage_status to storage_status, and
        // 5.7.59 repeats the rename for instances that missed it. Adding
        // storage_status empty would leave every value in the old column.
        $columns = $this->everyColumn();
        $columns['lab_storage'] = array_values(array_diff($columns['lab_storage'], ['storage_status']));
        $columns['lab_storage'][] = 'lab_storage_status';

        $repair = new SchemaRepairPlan(self::$init);
        foreach (glob(self::$root . '/sys/migrations/*.sql') ?: [] as $file) {
            $repair->deferMigrationTargets((string) file_get_contents($file), $columns, basename($file, '.sql'));
        }

        $this->assertSame([], $repair->build($columns));
        $this->assertContains(
            ['name' => 'lab_storage.storage_status', 'from' => 'lab_storage_status', 'migration' => '5.7.59'],
            $repair->deferred()
        );
    }

    public function testAMissingAutoIncrementColumnIsNeverAdded(): void
    {
        // Adding it numbers the existing rows afresh, so every row that points at
        // the old ids points at the wrong row.
        $init = "CREATE TABLE `t` (\n  `id` int NOT NULL AUTO_INCREMENT,\n  `code` int NOT NULL,\n"
            . "  PRIMARY KEY (`id`)\n) ENGINE=InnoDB;\n";
        $repair = new SchemaRepairPlan($init);

        $this->assertSame([], $repair->build(['t' => ['code']]));
        $this->assertCount(1, $repair->manual());
        $this->assertStringStartsWith('t.id: an AUTO_INCREMENT key.', $repair->manual()[0]);
    }

    public function testARetypeUnderTheSameNameIsNotAnAddition(): void
    {
        // 5.2.6 runs CHANGE `patient_code_key` `patient_code_key` on a column it
        // needs to be there. A lost patient_code_key is drift at 5.2.5 too.
        $pending = schema_pending_additions(self::$root . '/sys/migrations', '5.2.5');
        $this->assertArrayNotHasKey('patient_code_key', $pending['columns']['patients'] ?? []);

        $columns = $this->everyColumn();
        $columns['patients'] = array_values(array_diff($columns['patients'], ['patient_code_key']));
        $repair = new SchemaRepairPlan(self::$init);
        $repair->deferPending(schema_pending_additions(self::$root . '/sys/migrations', '5.8.10'));
        $this->assertStringContainsString('ADD COLUMN `patient_code_key`', $repair->build($columns)[0]['sql']);
    }

    public function testAnAlterThatIsNotMysqldumpsKeyToggleIsRefused(): void
    {
        $this->expectException(RuntimeException::class);
        new SchemaRepairPlan("CREATE TABLE `t` (`id` int);\nALTER TABLE `t` ADD COLUMN `x` int;\n");
    }

    public function testInitSqlOnlyCarriesKeyToggleAlters(): void
    {
        foreach ((new Parser(self::$init))->statements as $statement) {
            if ($statement instanceof AlterStatement) {
                $this->assertMatchesRegularExpression('/(DISABLE|ENABLE) KEYS$/', $statement->build());
            }
        }
    }

    public function testPendingAdditionsReadEveryWayAMigrationAddsAName(): void
    {
        $dir = sys_get_temp_dir() . '/intelis-pending-' . getmypid();
        @mkdir($dir);
        file_put_contents("$dir/1.0.0.sql", "CREATE TABLE `old_one` (`id` int);\n");
        file_put_contents("$dir/1.0.1.sql", implode("\n", [
            '-- a comment; with a semicolon',
            '--no space after the dashes; still a comment',
            "CREATE TABLE IF NOT EXISTS new_table (`id` int, note varchar(5) DEFAULT 'a;b');",
            "ALTER TABLE `t` ADD COLUMN `c1` ENUM('a','b') NULL, ADD `c2` int,"
                . ' ADD INDEX `idx` (`c1`), ADD PRIMARY KEY (`id`);',
            'ALTER TABLE `t` ADD CONSTRAINT `fk` FOREIGN KEY (`x`) REFERENCES `y` (`id`);',
            'ALTER TABLE `t` CHANGE `old` `C3` int, RENAME COLUMN `a` TO `c4`, CHANGE `kept` `KEPT` text;',
            'ALTER TABLE t ADD COLUMN (c5 int, c6 int);',
            '/* block; comment */ RENAME TABLE `a` TO `renamed_table`;',
            'ALTER TABLE `b` RENAME TO `moved_table`;',
        ]));

        try {
            $pending = schema_pending_additions($dir, '1.0.0');
        } finally {
            array_map('unlink', glob("$dir/*.sql") ?: []);
            @rmdir($dir);
        }

        $this->assertSame(['new_table', 'renamed_table', 'moved_table'], array_keys($pending['tables']));
        $this->assertSame(['c1', 'c2', 'c3', 'c4', 'c5', 'c6'], array_keys($pending['columns']['t']));
        $this->assertSame('1.0.1', $pending['columns']['t']['c1']);
    }

    /** @return array<string, list<string>> every table and column init.sql declares */
    private function everyColumn(): array
    {
        $columns = [];
        foreach ((new Parser(self::$init))->statements as $statement) {
            if ($statement instanceof CreateStatement && is_array($statement->fields)) {
                foreach ($statement->fields as $field) {
                    if ($field->type !== null && $field->name !== null) {
                        $columns[(string) $statement->name?->table][] = (string) $field->name;
                    }
                }
            }
        }
        return $columns;
    }

    /**
     * @param list<array{table: string, create: bool, sql: string}> $plan
     * @return array<string, string>
     */
    private function byTable(array $plan): array
    {
        return array_column($plan, 'sql', 'table');
    }
}
