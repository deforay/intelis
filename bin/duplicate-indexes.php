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
// order, with the same prefix lengths, and the same uniqueness. An index over
// (a, b) is not a copy of one over (a) even though MySQL can often use the
// composite in its place -- dropping the narrower one changes which plans the
// optimiser reaches and is a judgement call, not a cleanup. A UNIQUE index is
// never a copy of a plain one: it carries a constraint the other does not.
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

// Every index, as its definition rather than its name. SUB_PART is part of the
// definition: `note`(10) and `note` are different indexes.
$rows = $db->rawQuery(
    "SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME, SUB_PART
       FROM information_schema.STATISTICS
      WHERE TABLE_SCHEMA = ?"
    . ($onlyTable ? " AND TABLE_NAME = ?" : "")
    . " ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX",
    $onlyTable ? [$schema, $onlyTable] : [$schema]
);

$defs = [];
foreach ($rows as $r) {
    $key = $r['TABLE_NAME'] . "\0" . $r['INDEX_NAME'];
    $defs[$key]['table']  = $r['TABLE_NAME'];
    $defs[$key]['name']   = $r['INDEX_NAME'];
    $defs[$key]['unique'] = ((int) $r['NON_UNIQUE']) === 0;
    $defs[$key]['cols'][] = $r['COLUMN_NAME'] . ($r['SUB_PART'] !== null ? '(' . $r['SUB_PART'] . ')' : '');
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

$groups = [];
foreach ($defs as $d) {
    $groups[$d['table'] . "\0" . implode(',', $d['cols']) . "\0" . ($d['unique'] ? 'U' : 'N')][] = $d;
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
        "%s (%s)%s\n  keep %s, %d %s\n",
        $table,
        $cols,
        $keeper['unique'] ? ' UNIQUE' : '',
        $keeper['name'],
        count($copies),
        count($copies) === 1 ? 'copy' : 'copies'
    );

    foreach ($copies as $copy) {
        $leading = strtolower((string) explode('(', $copy['cols'][0])[0]);
        $needed  = isset($fkLeading[$table][$leading]);

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
