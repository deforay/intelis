#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Live check: the database has every table and column its recorded version says it should.
 *
 * bin/migrate.php advances sc_version only after a clean run, so a database that is
 * behind the code announces itself -- the footer shows the mismatch and re-running
 * migrations fixes it. The failure this check exists for is the silent one.
 *
 * Migration files stamp their own sc_version, and 20 of them do it mid-file: 5.4.1 at
 * line 18 with 22 lines still to go, 5.2.9 with 512 statement-lines after. Runners
 * before the snapshot-and-restore guard bumped the version regardless of errors, so a
 * migration that died halfway left the database recorded as fully migrated while
 * missing whatever came after the stamp. Nothing surfaces that. The versions agree,
 * the footer stays quiet, and the missing column is found by a query that fails in
 * front of a user months later.
 *
 * So this compares what is actually there against what the migration history says
 * should be there, rather than comparing one version number to another.
 *
 * Scope is tables and columns. Not types, not indexes, not defaults: those differ
 * for legitimate reasons across MySQL versions and collations, and a check that cries
 * wolf is worse than no check. A missing column is unambiguous.
 *
 * Needs a live database, so it is deliberately NOT part of `composer check-invariants`
 * (those are static and run in CI without one).
 *
 * Usage: php bin/build/check-schema-drift.php [--verbose]
 * Exit:  0 no drift, 1 drift or the database is behind, 2 the check could not run
 */

require_once __DIR__ . '/../../bootstrap.php';

use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

const OK = 0;
const DRIFT = 1;
const CANNOT_RUN = 2;

$verbose = in_array('--verbose', $argv, true) || in_array('-v', $argv, true);

/** Identifiers that open a key or constraint rather than a column. */
const NOT_A_COLUMN = ['primary', 'key', 'unique', 'index', 'constraint', 'fulltext', 'spatial', 'foreign', 'check'];

/**
 * Split a comma-separated clause list without cutting inside quotes or parentheses.
 *
 * ENUM('yes','no','') and DECIMAL(10,2) both contain commas that do not separate
 * anything. Splitting naively on "," turns one ADD COLUMN into three nonsense ones,
 * which is how a drift checker starts reporting columns nobody ever wrote.
 *
 * @return list<string>
 */
function splitTopLevel(string $clause): array
{
    $parts = [];
    $buffer = '';
    $depth = 0;
    $quote = null;
    $length = strlen($clause);

    for ($i = 0; $i < $length; $i++) {
        $char = $clause[$i];

        if ($quote !== null) {
            $buffer .= $char;
            if ($char === '\\' && $i + 1 < $length) {
                $buffer .= $clause[++$i];
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

        if ($char === '(') {
            $depth++;
        } elseif ($char === ')') {
            $depth--;
        }

        if ($char === ',' && $depth === 0) {
            $parts[] = trim($buffer);
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    if (trim($buffer) !== '') {
        $parts[] = trim($buffer);
    }

    return $parts;
}

/** Strip backticks and surrounding whitespace from an identifier. */
function ident(string $raw): string
{
    return trim(trim(trim($raw), '`'));
}

/**
 * Strip comments and split a SQL file into statements.
 *
 * @return list<string>
 */
function statements(string $sql): array
{
    // Line comments first, so a "--" inside no string can swallow a statement.
    $sql = preg_replace('/^\s*--[^\n]*$/m', '', $sql) ?? $sql;
    $sql = preg_replace('#/\*.*?\*/#s', '', $sql) ?? $sql;

    // Split on semicolons that end a statement, not on ones inside a string.
    // init.sql carries at least one column comment containing a semicolon --
    // instrument_activity_log.installation_id -- and splitting on it blindly cut
    // that CREATE TABLE in half, so its columns were read from the first
    // fragment and its six index declarations, which sit after the comment, were
    // never seen at all. A statement that cannot be read is a table this check
    // silently stops covering.
    $out = [];
    $buffer = '';
    $quote = null;                 // ' " or ` while inside one
    $length = strlen($sql);

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];

        if ($quote !== null) {
            $buffer .= $char;
            if ($char === '\\' && $quote !== '`' && $i + 1 < $length) {
                $buffer .= $sql[++$i];      // escaped character inside a string
                continue;
            }
            if ($char === $quote) {
                // Doubled delimiter is an escaped one, not the end.
                if ($i + 1 < $length && $sql[$i + 1] === $quote) {
                    $buffer .= $sql[++$i];
                    continue;
                }
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
            $chunk = trim($buffer);
            if ($chunk !== '') {
                $out[] = $chunk;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $chunk = trim($buffer);
    if ($chunk !== '') {
        $out[] = $chunk;
    }

    return $out;
}

/**
 * Read an index declaration into [name|null, columns, unique], or null.
 *
 * Covers the shapes the history actually uses: a KEY line inside CREATE TABLE,
 * and ADD INDEX / ADD KEY with or without a name. A name of null means the
 * statement left it to MySQL, which derives one from the first column and
 * appends _2, _3 and onward once that is taken -- so an unnamed declaration
 * cannot be matched to a live index by name and is compared on its columns.
 *
 * Columns come back with any prefix length attached, because `note`(10) and
 * `note` are different indexes. FOREIGN KEY and CHECK are not indexes and are
 * not returned; MySQL creates a backing index for a foreign key on its own, and
 * claiming the history declared one would report every install as drifted.
 *
 * @return array{0: string|null, 1: string[], 2: bool}|null
 */
function parseIndexDeclaration(string $definition): ?array
{
    $definition = trim($definition);

    if (preg_match('/^(?:CONSTRAINT\s+(?:`[^`]+`|[A-Za-z0-9_$]+)\s+)?(?:FOREIGN\s+KEY|CHECK)\b/i', $definition)) {
        return null;
    }

    $re = '/^(?:CONSTRAINT\s+(?:`[^`]+`|[A-Za-z0-9_$]+)\s+)?'
        . '(PRIMARY\s+KEY|UNIQUE(?:\s+(?:KEY|INDEX))?|FULLTEXT(?:\s+(?:KEY|INDEX))?|SPATIAL(?:\s+(?:KEY|INDEX))?|KEY|INDEX)'
        . '\s*(?:`([^`]+)`|([A-Za-z0-9_$]+))?\s*\(([^)]*(?:\([0-9]+\)[^)]*)*)\)/i';
    if (!preg_match($re, $definition, $m)) {
        return null;
    }

    $kind = strtoupper(preg_replace('/\s+/', ' ', $m[1]) ?? '');
    $name = $m[2] !== '' ? $m[2] : ($m[3] ?? '');
    if (str_starts_with($kind, 'PRIMARY')) {
        $name = 'PRIMARY';
    }

    $columns = [];
    foreach (splitTopLevel($m[4]) as $part) {
        $part = trim($part);
        if (!preg_match('/^`?([A-Za-z0-9_$]+)`?(?:\s*\((\d+)\))?/', $part, $c)) {
            return null;
        }
        $columns[] = strtolower($c[1]) . (isset($c[2]) && $c[2] !== '' ? '(' . $c[2] . ')' : '');
    }
    if ($columns === []) {
        return null;
    }

    return [$name !== '' ? $name : null, $columns, str_starts_with($kind, 'PRIMARY') || str_starts_with($kind, 'UNIQUE')];
}

/**
 * Apply one statement's effect to the expected schema.
 *
 * $schema maps lowercased table name => [lowercased column name => real column name].
 * Returns true when the statement was a schema statement this function understood,
 * false when it was a schema statement it did not. Anything that is not DDL (INSERT,
 * UPDATE, and the rest) returns true, because there is nothing to miss.
 *
 * $indexes maps lowercased table name => list of ['name' => string|null,
 * 'cols' => string[], 'unique' => bool]. Kept as a list rather than keyed by
 * name because a declaration may not carry one.
 *
 * @param array<string, array<string, string>> $schema
 * @param array<string, array<int, array{name: string|null, cols: string[], unique: bool}>> $indexes
 */
function applyStatement(string $stmt, array &$schema, array &$indexes = []): bool
{
    $head = ltrim($stmt);

    // CREATE TABLE `x` ( ... )
    if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_$]+)`?\s*\((.*)\)[^)]*$/is', $head, $m)) {
        $table = strtolower($m[1]);
        $columns = [];
        $tableIndexes = [];
        foreach (splitTopLevel($m[2]) as $definition) {
            if (!preg_match('/^`?([A-Za-z0-9_$]+)`?/', trim($definition), $c)) {
                continue;
            }
            if (in_array(strtolower($c[1]), NOT_A_COLUMN, true) && !str_starts_with(trim($definition), '`')) {
                $declared = parseIndexDeclaration($definition);
                if ($declared !== null) {
                    $tableIndexes[] = ['name' => $declared[0], 'cols' => $declared[1], 'unique' => $declared[2]];
                }
                continue;
            }
            $columns[strtolower($c[1])] = $c[1];
        }
        $schema[$table] = $columns;
        $indexes[$table] = $tableIndexes;
        return true;
    }

    if (preg_match('/^DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?(.+)$/is', $head, $m)) {
        foreach (splitTopLevel($m[1]) as $name) {
            unset($schema[strtolower(ident($name))], $indexes[strtolower(ident($name))]);
        }
        return true;
    }

    if (preg_match('/^RENAME\s+TABLE\s+(.+)$/is', $head, $m)) {
        foreach (splitTopLevel($m[1]) as $pair) {
            if (preg_match('/^(.+?)\s+TO\s+(.+)$/is', $pair, $p)) {
                $from = strtolower(ident($p[1]));
                $to = strtolower(ident($p[2]));
                if (isset($schema[$from])) {
                    $schema[$to] = $schema[$from];
                    unset($schema[$from]);
                    $indexes[$to] = $indexes[$from] ?? [];
                    unset($indexes[$from]);
                }
            }
        }
        return true;
    }

    if (preg_match('/^ALTER\s+TABLE\s+`?([A-Za-z0-9_$]+)`?\s+(.*)$/is', $head, $m)) {
        $table = strtolower($m[1]);
        if (!isset($schema[$table])) {
            // Altering a table the history never created: not this check's business,
            // and guessing would invent columns.
            return true;
        }

        $understood = true;
        foreach (splitTopLevel($m[2]) as $action) {
            $action = trim($action);

            if (preg_match('/^ADD\s+(?:COLUMN\s+)?`?([A-Za-z0-9_$]+)`?\s+\S/i', $action, $a)) {
                if (in_array(strtolower($a[1]), NOT_A_COLUMN, true)) {
                    continue; // ADD PRIMARY KEY / ADD INDEX / ADD CONSTRAINT
                }
                $schema[$table][strtolower($a[1])] = $a[1];
                continue;
            }

            if (preg_match('/^DROP\s+(?:COLUMN\s+)?`?([A-Za-z0-9_$]+)`?\s*$/i', $action, $a)) {
                if (in_array(strtolower($a[1]), NOT_A_COLUMN, true)) {
                    continue;
                }
                unset($schema[$table][strtolower($a[1])]);
                continue;
            }

            if (preg_match('/^CHANGE\s+(?:COLUMN\s+)?`?([A-Za-z0-9_$]+)`?\s+`?([A-Za-z0-9_$]+)`?\s+\S/i', $action, $a)) {
                unset($schema[$table][strtolower($a[1])]);
                $schema[$table][strtolower($a[2])] = $a[2];
                continue;
            }

            // MODIFY, RENAME INDEX, ENGINE=, COLLATE, ADD PRIMARY KEY, DROP INDEX:
            // real statements that change no column set.
            if (preg_match('/^(MODIFY|RENAME\s+(INDEX|KEY)|ENGINE|COLLATE|DEFAULT|CONVERT|AUTO_INCREMENT|ORDER\s+BY|DISABLE|ENABLE|ALTER)\b/i', $action)) {
                continue;
            }
            if (preg_match('/^ADD\s+/i', $action)) {
                $declared = parseIndexDeclaration(preg_replace('/^ADD\s+/i', '', $action) ?? '');
                if ($declared !== null) {
                    $indexes[$table][] = ['name' => $declared[0], 'cols' => $declared[1], 'unique' => $declared[2]];
                    continue;
                }
            }

            if (preg_match('/^DROP\s+(?:INDEX|KEY)\s+`?([A-Za-z0-9_$]+)`?\s*$/i', $action, $a)) {
                $indexes[$table] = array_values(array_filter(
                    $indexes[$table] ?? [],
                    static fn(array $i): bool => strcasecmp((string) $i['name'], $a[1]) !== 0
                ));
                continue;
            }

            if (preg_match('/^(ADD|DROP)\s+(PRIMARY|UNIQUE|KEY|INDEX|CONSTRAINT|FOREIGN|FULLTEXT|SPATIAL|CHECK)\b/i', $action)) {
                continue;
            }

            $understood = false;
        }
        return $understood;
    }

    return true; // not DDL
}

/* ------------------------------- run ------------------------------- */

$initPath = ROOT_PATH . '/sql/init.sql';
if (!is_readable($initPath)) {
    fwrite(STDERR, "check-schema-drift: cannot read $initPath\n");
    exit(CANNOT_RUN);
}

$initSql = (string) file_get_contents($initPath);

// The baseline is whatever version init.sql seeds into system_config.
if (!preg_match("/'sc_version'\s*,\s*'([0-9][0-9.]*)'/", $initSql, $m)) {
    fwrite(STDERR, "check-schema-drift: init.sql does not seed an sc_version; cannot establish a baseline\n");
    exit(CANNOT_RUN);
}
$baseline = $m[1];

try {
    /** @var DatabaseService $db */
    $db = ContainerRegistry::get(DatabaseService::class);
    if ($db->isConnected() === false) {
        fwrite(STDERR, "check-schema-drift: no database connection\n");
        exit(CANNOT_RUN);
    }
    $dbVersion = trim((string) ($db->rawQueryOne("SELECT value FROM system_config WHERE name = 'sc_version' LIMIT 1")['value'] ?? ''));
} catch (Throwable $e) {
    fwrite(STDERR, "check-schema-drift: " . $e->getMessage() . "\n");
    exit(CANNOT_RUN);
}

if ($dbVersion === '') {
    fwrite(STDERR, "check-schema-drift: the database records no sc_version\n");
    exit(CANNOT_RUN);
}

// Below the baseline there is no way to build an expected schema: init.sql describes a
// newer database, and migrations only move forward. A database that far back is the
// visible problem the footer already reports, not the silent one this check is for.
if (version_compare($dbVersion, $baseline, '<')) {
    echo "check-schema-drift: database is at $dbVersion, behind init.sql's baseline of $baseline.\n";
    echo "check-schema-drift: run migrations first — drift cannot be judged until then.\n";
    exit(DRIFT);
}

/** @var array<string, array<string, string>> $expected */
$expected = [];
/** @var array<string, array<int, array{name: string|null, cols: string[], unique: bool}>> $expectedIndexes */
$expectedIndexes = [];
$unparsed = [];

foreach (statements($initSql) as $stmt) {
    if (!applyStatement($stmt, $expected, $expectedIndexes)) {
        $unparsed[] = 'init.sql: ' . substr(preg_replace('/\s+/', ' ', $stmt) ?? '', 0, 120);
    }
}

$applied = 0;
$files = (array) glob(ROOT_PATH . '/sys/migrations/*.sql');
$versions = array_map(static fn($f): string => basename((string) $f, '.sql'), $files);
usort($versions, 'version_compare');

foreach ($versions as $version) {
    if (version_compare($version, $baseline, '<=') || version_compare($version, $dbVersion, '>')) {
        continue;
    }
    $applied++;
    foreach (statements((string) file_get_contents(ROOT_PATH . "/sys/migrations/$version.sql")) as $stmt) {
        if (!applyStatement($stmt, $expected, $expectedIndexes)) {
            $unparsed[] = "$version.sql: " . substr(preg_replace('/\s+/', ' ', $stmt) ?? '', 0, 120);
        }
    }
}

// What is actually there.
/** @var array<string, array<string, string>> $actual */
$actual = [];
foreach ($db->rawQuery(
    "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()"
) as $row) {
    $actual[strtolower((string) $row['TABLE_NAME'])][strtolower((string) $row['COLUMN_NAME'])] = (string) $row['COLUMN_NAME'];
}

// Live indexes, as definitions rather than names.
/** @var array<string, array<string, array{cols: string[], unique: bool}>> $actualIndexes */
$actualIndexes = [];
foreach ($db->rawQuery(
    "SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART
       FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = DATABASE()
      ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX"
) as $row) {
    $t = strtolower((string) $row['TABLE_NAME']);
    $n = (string) $row['INDEX_NAME'];
    $actualIndexes[$t][$n]['unique'] = ((int) $row['NON_UNIQUE']) === 0;
    $actualIndexes[$t][$n]['cols'][] = strtolower((string) $row['COLUMN_NAME'])
        . ($row['SUB_PART'] !== null ? '(' . $row['SUB_PART'] . ')' : '');
}

$missingTables = [];
$missingColumns = [];

foreach ($expected as $table => $columns) {
    if (!isset($actual[$table])) {
        $missingTables[] = $table;
        continue;
    }
    foreach ($columns as $key => $column) {
        if (!isset($actual[$table][$key])) {
            $missingColumns[] = "$table.$column";
        }
    }
}

// Indexes the history declares that are nowhere in the database, and indexes
// the database holds that the history never asked for.
//
// Matched on definition, never on name. An unnamed declaration has no name to
// match, a renamed index still does the same job, and the same index under two
// names is the ordinary state of these installs -- `composer duplicate-indexes`
// is where that is reported, so it is not repeated here.
$missingIndexes = [];
$undeclaredIndexes = [];

$definition = static fn(array $i): string
    => implode(',', $i['cols']) . ($i['unique'] ? ':U' : ':N');

foreach ($expectedIndexes as $table => $declared) {
    if (!isset($actual[$table])) {
        continue;   // the missing table is already reported; its indexes follow from that
    }
    $live = array_map($definition, array_values($actualIndexes[$table] ?? []));
    foreach ($declared as $index) {
        if (!in_array($definition($index), $live, true)) {
            $missingIndexes[] = $table . ' (' . implode(', ', $index['cols']) . ')'
                . ($index['unique'] ? ' UNIQUE' : '')
                . ($index['name'] !== null ? ' — declared as ' . $index['name'] : '');
        }
    }
}

foreach ($actualIndexes as $table => $live) {
    if (!isset($expectedIndexes[$table])) {
        continue;   // a table the history never created; not this check's business
    }
    $declaredDefs = array_map($definition, $expectedIndexes[$table]);
    $seen = [];
    foreach ($live as $name => $index) {
        $def = $definition($index);
        if (in_array($def, $declaredDefs, true) || isset($seen[$def])) {
            $seen[$def] = true;   // a second copy is a duplicate, not an undeclared index
            continue;
        }
        $seen[$def] = true;
        $undeclaredIndexes[] = "$table.$name (" . implode(', ', $index['cols']) . ')'
            . ($index['unique'] ? ' UNIQUE' : '');
    }
}

sort($missingTables);
sort($missingColumns);
sort($missingIndexes);
sort($undeclaredIndexes);

$tableCount = count($expected);
$columnCount = array_sum(array_map('count', $expected));
$indexCount = array_sum(array_map('count', $expectedIndexes));

echo "check-schema-drift: baseline $baseline + $applied migration(s) to $dbVersion\n";
echo "check-schema-drift: expects $tableCount tables, $columnCount columns, $indexCount indexes\n";

if ($unparsed !== []) {
    $shown = $verbose ? $unparsed : array_slice($unparsed, 0, 5);
    echo "check-schema-drift: " . count($unparsed) . " DDL statement(s) not understood — coverage is incomplete:\n";
    foreach ($shown as $line) {
        echo "  ? $line\n";
    }
    if (!$verbose && count($unparsed) > count($shown)) {
        echo "  … " . (count($unparsed) - count($shown)) . " more (--verbose to list)\n";
    }
}

// Undeclared indexes are reported and never fail the check. An index the
// history did not ask for is not necessarily wrong: on installs this old it may
// be a hand-applied fix for a slow local report, or a leftover from a module the
// site once ran, and there is no way to tell those from cruft by looking. What
// it is for is visibility -- knowing an install carries them at all.
if ($undeclaredIndexes !== []) {
    $shown = $verbose ? $undeclaredIndexes : array_slice($undeclaredIndexes, 0, 10);
    echo "check-schema-drift: " . count($undeclaredIndexes) . " index(es) the history never declared:\n";
    foreach ($shown as $line) {
        echo "  EXTRA INDEX     $line\n";
    }
    if (!$verbose && count($undeclaredIndexes) > count($shown)) {
        echo "  … " . (count($undeclaredIndexes) - count($shown)) . " more (--verbose to list)\n";
    }
    echo "check-schema-drift: these are reported, not judged. Exact copies of another index are\n";
    echo "check-schema-drift: listed separately by `composer duplicate-indexes`.\n";
}

if ($missingTables === [] && $missingColumns === [] && $missingIndexes === []) {
    echo "check-schema-drift: every expected table, column and index is present.\n";
    exit(OK);
}

foreach ($missingTables as $table) {
    echo "  MISSING TABLE   $table\n";
}
foreach ($missingColumns as $column) {
    echo "  MISSING COLUMN  $column\n";
}
foreach ($missingIndexes as $index) {
    echo "  MISSING INDEX   $index\n";
}

echo "check-schema-drift: " . count($missingTables) . " missing table(s), "
    . count($missingColumns) . " missing column(s), "
    . count($missingIndexes) . " missing index(es).\n";
echo "check-schema-drift: the recorded version claims these exist, so a past migration stamped itself done without finishing.\n";
exit(DRIFT);
