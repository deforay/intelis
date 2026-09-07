#!/usr/bin/env php
<?php
// Find indexes that are exact copies of another index on the same table, and
// optionally drop the copies.
//
// An index gets a name, not a definition, so nothing stops the same one being
// created twice under two names. `ALTER TABLE ... ADD KEY` without a guard does
// it every time it runs, MySQL appending _2, _3 and so on, and a migration that
// asks for an index sql/init.sql already ships does it once per install. The
// second copy answers no query the first cannot; it is only another index to
// write on every insert and update, and another to keep in memory.
//
// One DRC instance was carrying 115 redundant indexes across 15 groups: 63
// copies of one index on s_app_menu, 41 on user_details, and six duplicated
// pairs on form_vl, where the indexes had grown larger than the data.
//
// What counts as a copy is exact and nothing else: same columns, in the same
// order, with the same prefix lengths, the same sort direction, the same index
// type, and the same uniqueness. An index over (a, b) is not a copy of one over
// (a) even though MySQL can often use the composite in its place -- dropping the
// narrower one changes which plans the optimiser reaches and is a judgement
// call, not a cleanup. A UNIQUE index is never a copy of a plain one: it carries
// a constraint the other does not.
//
// Everything in that list earns its place by having been got wrong. Comparing
// only columns and uniqueness collapses three pairs of genuinely different
// indexes into one:
//
//   - BTREE and FULLTEXT over the same column look identical, and only the
//     FULLTEXT one can answer MATCH ... AGAINST. Dropping it does not slow a
//     query down, it makes the query an error.
//   - (name) and (name DESC) are different indexes on MySQL 8; the descending
//     one exists to serve an ORDER BY the ascending one cannot.
//   - a functional index reports COLUMN_NAME as NULL and keeps its definition in
//     EXPRESSION, so INDEX((year(d))) and INDEX((month(d))) both reduce to an
//     empty column list and match each other.
//
// EXPRESSION only exists on MySQL 8.0.13 and later. Where it is absent the
// server has no functional indexes to confuse, but an index whose definition
// cannot be read in full is passed over rather than guessed at.
//
// It reports and stops unless told otherwise, because an index is expensive to
// put back on a large table and there is no undo.
//
// Usage:
//   php bin/duplicate-indexes.php           report what is redundant
//   php bin/duplicate-indexes.php --fix     drop the copies
//   php bin/duplicate-indexes.php --table=form_vl   limit to one table

require_once __DIR__ . "/../bootstrap.php";

use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$argvOpts   = $argv ?? [];
$applyFix   = in_array('--fix', $argvOpts, true);
$onlyTable  = null;
foreach ($argvOpts as $arg) {
    if (str_starts_with((string) $arg, '--table=')) {
        $onlyTable = substr((string) $arg, 8);
    }
}

$schema = $db->rawQueryOne('SELECT DATABASE() AS db')['db'] ?? null;
if (!$schema) {
    fwrite(STDERR, "No database selected.\n");
    exit(1);
}

// Functional indexes keep their definition in EXPRESSION and leave COLUMN_NAME
// null. The column arrived in MySQL 8.0.13; on anything older, and on MariaDB,
// selecting it is an error rather than an empty result, so ask first.
$hasExpression = (int) ($db->rawQueryOne(
    "SELECT COUNT(*) AS c FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = 'information_schema'
        AND TABLE_NAME = 'STATISTICS' AND COLUMN_NAME = 'EXPRESSION' LIMIT 1"
)['c'] ?? 0) > 0;

// Every index, as its definition rather than its name. SUB_PART is part of the
// definition: `note`(10) and `note` are different indexes. So is COLLATION,
// which is 'A' ascending, 'D' descending and null for an index that is not
// ordered at all, and so is INDEX_TYPE.
$rows = $db->rawQuery(
    "SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART,
            COLLATION, INDEX_TYPE" . ($hasExpression ? ", EXPRESSION" : "") . "
       FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = ?"
    . ($onlyTable ? " AND TABLE_NAME = ?" : "")
    . " ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX",
    $onlyTable ? [$schema, $onlyTable] : [$schema]
);

/**
 * One key part of an index, written so that two parts compare equal only when
 * they index the same thing the same way.
 *
 * Takes a row of information_schema.STATISTICS. Returns null when the row
 * describes a functional key part whose EXPRESSION this server does not expose,
 * which is the one case where the definition cannot be established: the caller
 * must then leave the index alone rather than compare a partial reading of it.
 */
function index_part_signature(array $part): ?string
{
    if (($part['COLUMN_NAME'] ?? null) === null) {
        if (($part['EXPRESSION'] ?? null) === null) {
            return null;
        }
        $written = '(' . $part['EXPRESSION'] . ')';
    } else {
        $written = $part['COLUMN_NAME']
            . (($part['SUB_PART'] ?? null) !== null ? '(' . $part['SUB_PART'] . ')' : '');
    }

    // Direction belongs to the key part, not to the index: an index may order
    // one column ascending and the next descending.
    return $written . (($part['COLLATION'] ?? null) === 'D' ? ' DESC' : '');
}

/**
 * The whole definition of an index, as the string two indexes must share before
 * either can be called a copy of the other. Null when any part is unreadable.
 */
function index_signature(array $cols, bool $unique, string $type): ?string
{
    foreach ($cols as $c) {
        if ($c === null) {
            return null;
        }
    }
    return implode(',', $cols) . "\0" . ($unique ? 'U' : 'N') . "\0" . $type;
}

/**
 * Rows of information_schema.STATISTICS, gathered into groups of indexes that
 * are copies of one another. A group with one member in it is an index with no
 * duplicate; only groups of two or more mean anything.
 *
 * This is the whole of the decision the tool acts on, deliberately in one
 * function rather than spread across the script body. Everything above it can
 * be correct while the caller keys its groups on something looser, and the
 * result is still a dropped index -- so this is what the tests drive.
 *
 * Indexes whose definition could not be read are collected into $unreadable and
 * put in no group at all.
 */
function group_indexes(array $rows, array &$unreadable = []): array
{
    $defs = [];
    foreach ($rows as $r) {
        $key = $r['TABLE_NAME'] . "\0" . $r['INDEX_NAME'];
        $defs[$key]['table']  = $r['TABLE_NAME'];
        $defs[$key]['name']   = $r['INDEX_NAME'];
        $defs[$key]['unique'] = ((int) $r['NON_UNIQUE']) === 0;
        $defs[$key]['type']   = (string) $r['INDEX_TYPE'];
        $defs[$key]['cols'][] = index_part_signature($r);

        // Kept as its own field rather than recovered from the formatted column
        // list, which carries prefix lengths and sort direction a column name
        // has no business containing.
        if ((int) $r['SEQ_IN_INDEX'] === 1 && $r['COLUMN_NAME'] !== null) {
            $defs[$key]['leading'] = strtolower((string) $r['COLUMN_NAME']);
        }
    }

    $unreadable = [];
    $groups = [];
    foreach ($defs as $key => $d) {
        $signature = index_signature($d['cols'], $d['unique'], $d['type']);
        if ($signature === null) {
            $unreadable[] = $key;
            continue;
        }
        $groups[$d['table'] . "\0" . $signature][] = $d;
    }

    return $groups;
}


// Columns that a foreign key on this table needs an index for. MySQL refuses to
// drop the last index supporting one (errno 1553); asking first turns that into
// a line of output rather than a failed run.
$fkLeading = [];
foreach (
    $db->rawQuery(
        "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE
      WHERE TABLE_SCHEMA = ? AND REFERENCED_TABLE_NAME IS NOT NULL",
        [$schema]
    ) as $r
) {
    $fkLeading[$r['TABLE_NAME']][strtolower((string) $r['COLUMN_NAME'])] = true;
}

$unreadable = [];
$groups = group_indexes($rows, $unreadable);

foreach ($unreadable as $key) {
    printf("skipping %s: its definition cannot be read on this server\n", str_replace("\0", '.', $key));
}

/**
 * Which copy to keep. PRIMARY always, because it is the table's identity and
 * cannot be recreated by name. Otherwise the shortest name, which is the one
 * that was there before MySQL started appending _2, _3 to it.
 */
$pickKeeper = static function (array $set): array {
    foreach ($set as $i) {
        if ($i['name'] === 'PRIMARY') {
            return $i;
        }
    }
    usort($set, static fn(array $a, array $b): int
        => [strlen($a['name']), $a['name']] <=> [strlen($b['name']), $b['name']]);
    return $set[0];
};

$totalRedundant = 0;
$dropped = 0;
$skipped = 0;

foreach ($groups as $set) {
    if (count($set) < 2) {
        continue;
    }

    $keeper = $pickKeeper($set);
    $table  = $keeper['table'];
    $cols   = implode(', ', $keeper['cols']);
    $copies = array_values(array_filter($set, static fn(array $i): bool => $i['name'] !== $keeper['name']));
    $totalRedundant += count($copies);

    printf(
        "%s (%s)%s%s\n  keep %s, %d %s\n",
        $table,
        $cols,
        $keeper['unique'] ? ' UNIQUE' : '',
        $keeper['type'] !== 'BTREE' ? ' ' . $keeper['type'] : '',
        $keeper['name'],
        count($copies),
        count($copies) === 1 ? 'copy' : 'copies'
    );

    foreach ($copies as $copy) {
        $needed = isset($copy['leading']) && isset($fkLeading[$table][$copy['leading']]);

        if ($copy['name'] === 'PRIMARY') {
            printf("    skip %-32s the primary key\n", $copy['name']);
            $skipped++;
            continue;
        }

        if (!$applyFix) {
            printf("    drop %-32s%s\n", $copy['name'], $needed ? '  (a foreign key uses this column)' : '');
            continue;
        }

        // A foreign key may still be relying on this one even with a sibling
        // present, so let the server have the final say rather than guessing:
        // it raises errno 1553 when the index is the last one supporting a
        // constraint. Caught per index rather than allowed to end the run --
        // this is meant to be safe to point at a database nobody has looked at,
        // where one refusal must not strand the rest half done.
        $sql = sprintf('ALTER TABLE `%s` DROP INDEX `%s`', $table, $copy['name']);
        try {
            $db->rawQuery($sql);
        } catch (Throwable $e) {
            printf("    kept %-32s %s\n", $copy['name'], trim($e->getMessage()));
            $skipped++;
            continue;
        }
        printf("    dropped %-29s\n", $copy['name']);
        $dropped++;
    }
    echo "\n";
}

if ($totalRedundant === 0) {
    echo "No duplicate indexes.\n";
    exit(0);
}

if ($applyFix) {
    printf("Dropped %d, kept %d.\n", $dropped, $skipped);
} else {
    printf(
        "%d redundant %s. Re-run with --fix to drop them.\n",
        $totalRedundant,
        $totalRedundant === 1 ? 'index' : 'indexes'
    );
}
