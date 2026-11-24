<?php

declare(strict_types=1);

use PhpMyAdmin\SqlParser\Components\CreateDefinition;
use PhpMyAdmin\SqlParser\Parser;
use PhpMyAdmin\SqlParser\Statement;
use PhpMyAdmin\SqlParser\Statements\AlterStatement;
use PhpMyAdmin\SqlParser\Statements\CreateStatement;
use PhpMyAdmin\SqlParser\Statements\TransactionStatement;

require_once __DIR__ . '/pending-migrations.php';

/**
 * The statements that add what sql/init.sql declares and a database lacks.
 *
 * Reads only the CREATE TABLE definitions in init.sql. It never runs the dump's
 * data, DROP, LOCK or SET statements. It adds missing tables and missing
 * columns, and changes nothing that is already there.
 *
 * bin/heal-schema.php runs it. It lives outside app/classes because it takes
 * the dump as its constructor argument, which the DI container cannot supply.
 *
 * @phpstan-type TableDefinition array{columns: array<string, CreateDefinition>, keys: list<CreateDefinition>, options: string}
 */
final class SchemaRepairPlan
{
    /** @var array<string, TableDefinition> */
    private array $tables = [];

    /** @var array<string, array{from: ?string, migration: string}> */
    private array $deferredTables = [];

    /** @var array<string, array<string, array{from: ?string, migration: string}>> */
    private array $deferredColumns = [];

    /** @var list<string> Missing columns the plan will not add, with the reason. */
    private array $manual = [];

    /** @var list<array{name: string, from: ?string, migration: string}> Missing names left for a migration. */
    private array $skipped = [];

    public function __construct(string $sql)
    {
        $parser = new Parser($sql);
        if ($parser->errors !== []) {
            throw new RuntimeException('Cannot parse init.sql: ' . $parser->errors[0]->getMessage());
        }
        foreach (self::statements($parser->statements) as $statement) {
            if ($statement instanceof CreateStatement && $statement->options?->has('TABLE')) {
                $table = $statement->name?->table;
                if ($table === null || !is_array($statement->fields) || $statement->fields === []) {
                    throw new RuntimeException('Expected an explicit CREATE TABLE definition in init.sql.');
                }
                if ($statement->partitionBy !== null) {
                    throw new RuntimeException("Partitioned table {$table} needs explicit repair support.");
                }
                $this->tables[$table] = [
                    'columns' => [],
                    'keys' => [],
                    'options' => (string) $statement->entityOptions,
                ];
                foreach ($statement->fields as $field) {
                    $this->addDefinition($table, $field);
                }
            } elseif ($statement instanceof AlterStatement) {
                // mysqldump wraps each table's rows in ALTER TABLE ... DISABLE KEYS and
                // ENABLE KEYS. Anything else would be schema this class does not read.
                if (!preg_match('/^ALTER\s+TABLE\s+`?[a-z0-9_]+`?\s+(DISABLE|ENABLE)\s+KEYS$/i', trim($statement->build()))) {
                    throw new RuntimeException('Unexpected ALTER in init.sql. Regenerate it with bin/dev/regenerate-init-sql.php: '
                        . $statement->build());
                }
            }
        }
        if ($this->tables === []) {
            throw new RuntimeException('No table definitions found in init.sql.');
        }
    }

    /**
     * @param array<string, list<string>> $columns Existing tables and their columns.
     * @return list<array{table: string, create: bool, sql: string}>
     */
    public function build(array $columns): array
    {
        $this->manual = [];
        $this->skipped = [];
        $existing = [];
        foreach ($columns as $table => $names) {
            $existing[$table] = array_map('strtolower', $names);
        }
        $alter = [];
        $create = [];
        foreach ($this->tables as $table => $definition) {
            if (isset($this->deferredTables[$table]) && !array_key_exists($table, $existing)) {
                $this->skipped[] = ['name' => $table] + $this->deferredTables[$table];
                continue;
            }
            $quotedTable = self::quote($table);
            if (!array_key_exists($table, $existing)) {
                // One CREATE with every key and foreign key, so an interrupted run never
                // leaves a half-built table that the next run would skip. Foreign keys to
                // a deferred table are left out: the migration that creates that table
                // adds them, and a dangling key could block it.
                $keys = array_filter(
                    $definition['keys'],
                    fn(CreateDefinition $key): bool => !isset($this->deferredTables[$key->references?->table->table ?? ''])
                );
                $fields = array_merge(array_values($definition['columns']), array_values($keys));
                $create[] = [
                    'table' => $table,
                    'create' => true,
                    'sql' => "CREATE TABLE {$quotedTable} (\n  "
                        . implode(",\n  ", array_map(static fn(CreateDefinition $field): string => $field->build(), $fields))
                        . "\n) " . $definition['options'],
                ];
                continue;
            }
            $actions = [];
            foreach ($definition['columns'] as $column => $field) {
                // PHP turns a numeric column name into an integer key.
                $column = (string) $column;
                $lower = strtolower($column);
                if (in_array($lower, $existing[$table], true)) {
                    continue;
                }
                if (isset($this->deferredColumns[$table][$lower])) {
                    $this->skipped[] = ['name' => "{$table}.{$column}"] + $this->deferredColumns[$table][$lower];
                    continue;
                }
                if ($field->options?->has('AUTO_INCREMENT')) {
                    // Adding it would number the existing rows afresh, and every row
                    // elsewhere that points at the old ids would point at the wrong row.
                    $this->manual[] = "{$table}.{$column}: an AUTO_INCREMENT key. Adding it would renumber the rows,"
                        . ' so restore it from a backup or by hand.';
                    continue;
                }
                $actions[] = 'ADD COLUMN ' . self::nullableOnExistingRows($field->build());
            }
            if ($actions !== []) {
                $alter[] = ['table' => $table, 'create' => false, 'sql' => "ALTER TABLE {$quotedTable} " . implode(', ', $actions)];
            }
        }
        // Columns on existing tables come first, so new tables can reference them.
        return array_merge($alter, $create);
    }

    /**
     * Missing columns the last build() left out because adding them would damage data.
     *
     * @return list<string>
     */
    public function manual(): array
    {
        return $this->manual;
    }

    /**
     * Leaves out what the migrations this database has not run yet will add.
     *
     * @param array{tables: array<string, string>, columns: array<string, array<string, string>>} $pending
     *        from schema_pending_additions()
     */
    public function deferPending(array $pending): void
    {
        foreach ($pending['tables'] as $table => $migration) {
            $this->deferredTables[$table] ??= ['from' => null, 'migration' => $migration];
        }
        foreach ($pending['columns'] as $table => $columns) {
            foreach ($columns as $column => $migration) {
                $this->deferredColumns[$table][strtolower($column)] ??= ['from' => null, 'migration' => $migration];
            }
        }
    }

    /**
     * Leaves out what a migration fills by renaming or copying an existing table or column.
     *
     * Adding the new name empty would strand the data under the old name: the
     * migration then finds the new name present and does nothing.
     *
     * Reads the statements the way bin/migrate.php does, through the parser and
     * without stopping on its errors, so it sees what the runner would run.
     *
     * @param array<string, list<string>> $columns Existing tables and their columns.
     */
    public function deferMigrationTargets(string $sql, array $columns, string $migration): void
    {
        $existing = [];
        foreach ($columns as $table => $names) {
            $existing[$table] = array_map('strtolower', $names);
        }
        $parser = new Parser(preg_replace('/^(\s*--)(?=\S)/m', '$1 ', $sql) ?? $sql);
        foreach (self::statements($parser->statements) as $statement) {
            $query = trim($statement->build());
            $oldTable = $newTable = null;
            if (
                preg_match('/^RENAME\s+TABLE\s+`?([a-z0-9_]+)`?\s+TO\s+`?([a-z0-9_]+)`?$/i', $query, $m)
                || preg_match('/^ALTER\s+TABLE\s+`?([a-z0-9_]+)`?\s+RENAME\s+(?:TO\s+|AS\s+)?`?([a-z0-9_]+)`?$/i', $query, $m)
            ) {
                [, $oldTable, $newTable] = $m;
            } elseif (
                $statement instanceof CreateStatement && $statement->select !== null
                && preg_match('/\bFROM\s+`?([a-z0-9_]+)`?/i', $statement->select->build(), $m)
            ) {
                $oldTable = $m[1];
                $newTable = $statement->name?->table;
            }
            if ($oldTable !== null && $newTable !== null && isset($existing[$oldTable]) && !isset($existing[$newTable])) {
                $this->deferredTables[$newTable] = ['from' => $oldTable, 'migration' => $migration];
            }
            if (preg_match('/^ALTER\s+TABLE\s+`?([a-z0-9_]+)`?\s+(.+)$/is', $query, $alter)) {
                $table = $alter[1];
                foreach (schema_split_top_level($alter[2]) as $clause) {
                    if (
                        preg_match('/^CHANGE\s+(?:COLUMN\s+)?`?([a-z0-9_]+)`?\s+`?([a-z0-9_]+)`?\s+/i', $clause, $m)
                        || preg_match('/^RENAME\s+COLUMN\s+`?([a-z0-9_]+)`?\s+TO\s+`?([a-z0-9_]+)`?$/i', $clause, $m)
                    ) {
                        $old = strtolower($m[1]);
                        $new = strtolower($m[2]);
                        if (
                            $old !== $new && in_array($old, $existing[$table] ?? [], true)
                            && !in_array($new, $existing[$table] ?? [], true)
                        ) {
                            $this->deferredColumns[$table][$new] = ['from' => $m[1], 'migration' => $migration];
                        }
                    }
                }
            }
        }
    }

    /**
     * Missing names the last build() left for a migration that renames or copies
     * existing data into them.
     *
     * @return list<array{name: string, from: string, migration: string}>
     */
    public function deferred(): array
    {
        $deferred = [];
        foreach ($this->skipped as $item) {
            if ($item['from'] !== null) {
                $deferred[] = ['name' => $item['name'], 'from' => $item['from'], 'migration' => $item['migration']];
            }
        }
        return $deferred;
    }

    /** How many missing tables and columns the last build() left for migrations not yet run. */
    public function pendingCount(): int
    {
        return count(array_filter($this->skipped, static fn(array $item): bool => $item['from'] === null));
    }

    /**
     * A column added under rows that already exist.
     *
     * NOT NULL with no DEFAULT becomes NULL. MySQL would otherwise write '' or 0
     * into every existing row, and those values would then read as real data.
     * NULL says the value was never recorded, which is the truth.
     */
    private static function nullableOnExistingRows(string $definition): string
    {
        if (
            stripos($definition, 'AUTO_INCREMENT') !== false
            || preg_match('/\bDEFAULT\b/i', $definition) === 1
            || preg_match('/\bNOT\s+NULL\b/i', $definition) !== 1
        ) {
            return $definition;
        }
        return preg_replace('/\bNOT\s+NULL\b/i', 'NULL', $definition) ?? $definition;
    }

    /**
     * @param array<Statement> $statements
     * @return Generator<int, Statement>
     */
    private static function statements(array $statements): Generator
    {
        foreach ($statements as $statement) {
            if ($statement instanceof TransactionStatement) {
                yield from self::statements($statement->statements ?? []);
            } else {
                yield $statement;
            }
        }
    }

    private function addDefinition(string $table, CreateDefinition $field): void
    {
        if ($field->type !== null && $field->name !== null) {
            $this->tables[$table]['columns'][$field->name] = $field;
        } elseif ($field->key !== null) {
            $this->tables[$table]['keys'][] = $field;
        } else {
            throw new RuntimeException("Unsupported definition in table {$table}: " . $field->build());
        }
    }

    private static function quote(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }
}
