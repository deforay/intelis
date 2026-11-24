<?php

/**
 * What the migrations a database has not run yet will add.
 *
 * sql/init.sql is the newest schema. A database that is behind lacks whatever
 * the newer migrations add, and that is not drift: bin/migrate.php adds it, and
 * often does more than add it. 5.8.6 adds instruments.added_on empty, fills it
 * from updated_datetime, then gives it its default. Adding the column ahead of
 * the migration, with init.sql's final default, would date every existing
 * instrument today and leave the fill nothing to do.
 *
 * So bin/preflight.php and bin/heal-schema.php leave these out. Both read this
 * file, so they agree on what drift is.
 *
 * No dependencies, because bin/preflight.php must run without vendor/. A
 * character scanner rather than the SQL parser, and deliberately generous: a
 * name it wrongly counts as pending is only left for the migration, which runs
 * next anyway.
 */

declare(strict_types=1);

/**
 * @return array{tables: array<string, string>, columns: array<string, array<string, string>>}
 *         table => migration version, and table => lower-case column => migration version
 */
function schema_pending_additions(string $migrationsDir, ?string $currentVersion): array
{
    $pending = ['tables' => [], 'columns' => []];
    $files = glob(rtrim($migrationsDir, '/') . '/*.sql') ?: [];
    usort($files, static fn(string $a, string $b): int => version_compare(basename($a, '.sql'), basename($b, '.sql')));

    foreach ($files as $file) {
        $version = basename($file, '.sql');
        if ($currentVersion !== null && $currentVersion !== '' && version_compare($version, $currentVersion, '<=')) {
            continue;
        }
        $sql = @file_get_contents($file);
        if ($sql === false) {
            continue;
        }
        foreach (schema_split_statements($sql) as $statement) {
            schema_note_additions($statement, $version, $pending);
        }
    }

    return $pending;
}

/**
 * @param array{tables: array<string, string>, columns: array<string, array<string, string>>} $pending
 */
function schema_note_additions(string $statement, string $version, array &$pending): void
{
    $name = '`?([A-Za-z0-9_$]+)`?';

    if (preg_match('/^CREATE\s+(?:TEMPORARY\s+)?TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?' . $name . '/i', $statement, $m) === 1) {
        $pending['tables'][$m[1]] ??= $version;
        return;
    }
    if (preg_match('/^RENAME\s+TABLE\s+(.+)$/is', $statement, $m) === 1) {
        foreach (schema_split_top_level($m[1]) as $pair) {
            if (preg_match('/^' . $name . '\s+TO\s+' . $name . '$/i', $pair, $p) === 1) {
                $pending['tables'][$p[2]] ??= $version;
            }
        }
        return;
    }
    if (preg_match('/^ALTER\s+(?:IGNORE\s+)?TABLE\s+' . $name . '\s+(.+)$/is', $statement, $m) !== 1) {
        return;
    }
    $table = $m[1];
    foreach (schema_split_top_level($m[2]) as $clause) {
        if (preg_match('/^RENAME\s+(?:TO\s+|AS\s+)?' . $name . '$/i', $clause, $c) === 1) {
            $pending['tables'][$c[1]] ??= $version;
        } elseif (
            preg_match('/^RENAME\s+COLUMN\s+' . $name . '\s+TO\s+' . $name . '$/i', $clause, $c) === 1
            || preg_match('/^CHANGE\s+(?:COLUMN\s+)?' . $name . '\s+' . $name . '\s/i', $clause, $c) === 1
        ) {
            // CHANGE `x` `x` retypes a column the migration needs to be there
            // already, so only a new name is an addition.
            if (strcasecmp($c[1], $c[2]) !== 0) {
                $pending['columns'][$table][strtolower($c[2])] ??= $version;
            }
        } elseif (preg_match('/^ADD\s+(?:COLUMN\s+)?\((.+)\)$/is', $clause, $c) === 1) {
            foreach (schema_split_top_level($c[1]) as $definition) {
                if (preg_match('/^' . $name . '\s/', $definition, $d) === 1) {
                    $pending['columns'][$table][strtolower($d[1])] ??= $version;
                }
            }
        } elseif (
            preg_match('/^ADD\s+(?:COLUMN\s+)?(?:IF\s+NOT\s+EXISTS\s+)?' . $name . '\s/i', $clause, $c) === 1
            && preg_match('/^(INDEX|KEY|PRIMARY|UNIQUE|FULLTEXT|SPATIAL|CONSTRAINT|FOREIGN|CHECK|PARTITION)$/i', $c[1]) !== 1
        ) {
            $pending['columns'][$table][strtolower($c[1])] ??= $version;
        }
    }
}

/**
 * Splits a SQL file into statements, leaving out comments.
 *
 * A semicolon inside a string or a comment does not end a statement. "--" starts
 * a comment with or without the space after it, because bin/migrate.php treats
 * it that way.
 *
 * @return list<string>
 */
function schema_split_statements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $sql[$i + 1] ?? '';
        if ($char === "'" || $char === '"' || $char === '`') {
            $end = $i + 1;
            while ($end < $length && $sql[$end] !== $char) {
                $end += $sql[$end] === '\\' ? 2 : 1;
            }
            $buffer .= substr($sql, $i, $end - $i + 1);
            $i = $end;
        } elseif (($char === '-' && $next === '-') || $char === '#') {
            $end = strpos($sql, "\n", $i);
            $i = $end === false ? $length : $end;
            $buffer .= "\n";
        } elseif ($char === '/' && $next === '*') {
            $end = strpos($sql, '*/', $i + 2);
            $i = $end === false ? $length : $end + 1;
            $buffer .= ' ';
        } elseif ($char === ';') {
            if (trim($buffer) !== '') {
                $statements[] = trim($buffer);
            }
            $buffer = '';
        } else {
            $buffer .= $char;
        }
    }
    if (trim($buffer) !== '') {
        $statements[] = trim($buffer);
    }

    return $statements;
}

/**
 * Splits at top-level commas. ENUM('yes','no') and DECIMAL(10,2) carry commas
 * that separate nothing.
 *
 * @return list<string>
 */
function schema_split_top_level(string $sql): array
{
    $parts = [];
    $buffer = '';
    $depth = 0;
    $quote = null;
    $length = strlen($sql);
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        if ($quote !== null) {
            $buffer .= $char;
            if ($char === '\\' && $i + 1 < $length) {
                $buffer .= $sql[++$i];
            } elseif ($char === $quote) {
                $quote = null;
            }
            continue;
        }
        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
        } elseif ($char === '(') {
            $depth++;
        } elseif ($char === ')') {
            $depth--;
        } elseif ($char === ',' && $depth === 0) {
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
