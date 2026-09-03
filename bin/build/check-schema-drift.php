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

    $out = [];
    foreach (explode(';', $sql) as $chunk) {
        $chunk = trim($chunk);
        if ($chunk !== '') {
            $out[] = $chunk;
        }
    }
    return $out;
}

/**
 * Apply one statement's effect to the expected schema.
 *
 * $schema maps lowercased table name => [lowercased column name => real column name].
 * Returns true when the statement was a schema statement this function understood,
 * false when it was a schema statement it did not. Anything that is not DDL (INSERT,
 * UPDATE, and the rest) returns true, because there is nothing to miss.
 *
 * @param array<string, array<string, string>> $schema
 */
function applyStatement(string $stmt, array &$schema): bool
{
    $head = ltrim($stmt);

    // CREATE TABLE `x` ( ... )
    if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([A-Za-z0-9_$]+)`?\s*\((.*)\)[^)]*$/is', $head, $m)) {
        $table = strtolower($m[1]);
        $columns = [];
        foreach (splitTopLevel($m[2]) as $definition) {
            if (!preg_match('/^`?([A-Za-z0-9_$]+)`?/', trim($definition), $c)) {
                continue;
            }
            if (in_array(strtolower($c[1]), NOT_A_COLUMN, true) && !str_starts_with(trim($definition), '`')) {
                continue;
            }
            $columns[strtolower($c[1])] = $c[1];
        }
        $schema[$table] = $columns;
        return true;
    }

    if (preg_match('/^DROP\s+TABLE\s+(?:IF\s+EXISTS\s+)?(.+)$/is', $head, $m)) {
        foreach (splitTopLevel($m[1]) as $name) {
            unset($schema[strtolower(ident($name))]);
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
$unparsed = [];

foreach (statements($initSql) as $stmt) {
    if (!applyStatement($stmt, $expected)) {
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
        if (!applyStatement($stmt, $expected)) {
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

sort($missingTables);
sort($missingColumns);

$tableCount = count($expected);
$columnCount = array_sum(array_map('count', $expected));

echo "check-schema-drift: baseline $baseline + $applied migration(s) to $dbVersion\n";
echo "check-schema-drift: expects $tableCount tables, $columnCount columns\n";

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

if ($missingTables === [] && $missingColumns === []) {
    echo "check-schema-drift: every expected table and column is present.\n";
    exit(OK);
}

foreach ($missingTables as $table) {
    echo "  MISSING TABLE   $table\n";
}
foreach ($missingColumns as $column) {
    echo "  MISSING COLUMN  $column\n";
}

echo "check-schema-drift: " . count($missingTables) . " missing table(s), " . count($missingColumns) . " missing column(s).\n";
echo "check-schema-drift: the recorded version claims these exist, so a past migration stamped itself done without finishing.\n";
exit(DRIFT);
