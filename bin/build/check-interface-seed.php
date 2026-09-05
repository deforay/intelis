#!/usr/bin/env php
<?php
// Live check: sql/interface-init.sql still describes what the Interface Tool's
// migrations build.
//
// The seed lives here and the migrations live in the vlsm-interfacing
// repository, so nothing has ever held the two together. The seed reached
// migration 003 and stopped, and six migrations' worth of schema went missing
// from it without a word: a fresh installation got a schema three years old and
// depended entirely on the migration run to catch up. Nothing failed, which is
// why it went unnoticed.
//
// The check is the question asked directly rather than by reading the files.
// Build a database from the seed, run every migration over it, and see whether
// anything changed. A seed that is current absorbs them all -- every statement
// either succeeds against nothing or comes back with the duplicate-column and
// table-exists errors the migration runner already treats as benign, and the
// schema afterwards is the schema before. Anything that does change is exactly
// what the seed is missing, named.
//
// Needs a MySQL account that can create a database, and a checkout of
// vlsm-interfacing. Skips cleanly without either, because neither is present on
// a lab machine and this is a check for whoever changes the schema.
//
// Usage:
//   php bin/build/check-interface-seed.php
//   php bin/build/check-interface-seed.php --migrations=/path/to/mysql-migrations
//   php bin/build/check-interface-seed.php --keep       leave the scratch database

require_once __DIR__ . '/../../bootstrap.php';


const SEED_OK = 0;
const SEED_BEHIND = 1;
const SEED_SKIPPED = 0;

$argvOpts = $argv ?? [];
$keep = in_array('--keep', $argvOpts, true);
$migrationsDir = null;
foreach ($argvOpts as $arg) {
    if (str_starts_with((string) $arg, '--migrations=')) {
        $migrationsDir = rtrim(substr((string) $arg, 13), '/');
    }
}

$seedPath = ROOT_PATH . '/sql/interface-init.sql';
if (!is_readable($seedPath)) {
    echo "check-interface-seed: sql/interface-init.sql is not readable.\n";
    exit(SEED_BEHIND);
}

// The sibling checkout, where the migrations actually live.
$candidates = $migrationsDir !== null ? [$migrationsDir] : [
    dirname(ROOT_PATH) . '/vlsm-interfacing/app/mysql-migrations',
];
$found = null;
foreach ($candidates as $dir) {
    if (is_dir($dir)) {
        $found = $dir;
        break;
    }
}
if ($found === null) {
    echo "check-interface-seed: no vlsm-interfacing checkout found — nothing to compare against.\n";
    echo "check-interface-seed: pass --migrations=/path/to/app/mysql-migrations to run it.\n";
    exit(SEED_SKIPPED);
}

$files = (array) glob($found . '/*.sql');
usort($files, 'strnatcasecmp');
if ($files === []) {
    echo "check-interface-seed: no migrations in $found.\n";
    exit(SEED_SKIPPED);
}

// A direct connection rather than DatabaseService, which prepares every
// statement it is given. USE cannot be prepared -- MySQL answers "not supported
// in the prepared statement protocol" -- and switching database is the whole
// job here. This builds a throwaway schema and reads information_schema; it
// touches no application data and wants none of the service's machinery.
$config = SYSTEM_CONFIG['database'] ?? [];
$scratch = 'intelis_interface_seed_check';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $link = new mysqli(
        (string) ($config['host'] ?? '127.0.0.1'),
        (string) ($config['username'] ?? ''),
        (string) ($config['password'] ?? ''),
        null,
        (int) ($config['port'] ?? 3306)
    );
} catch (Throwable $e) {
    echo "check-interface-seed: cannot connect — " . trim($e->getMessage()) . "\n";
    exit(SEED_SKIPPED);
}

/** Every column and index, as one comparable list. */
$snapshot = static function (mysqli $link, string $schema): array {
    $out = ['column' => [], 'index' => []];

    $stmt = $link->prepare(
        "SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, COLUMN_NAME"
    );
    $stmt->bind_param('s', $schema);
    $stmt->execute();
    foreach ($stmt->get_result() as $r) {
        $out['column'][] = $r['TABLE_NAME'] . '.' . $r['COLUMN_NAME'];
    }
    $stmt->close();

    $stmt = $link->prepare(
        "SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX"
    );
    $stmt->bind_param('s', $schema);
    $stmt->execute();
    foreach ($stmt->get_result() as $r) {
        $out['index'][$r['TABLE_NAME'] . '.' . $r['INDEX_NAME']][] = $r['COLUMN_NAME'];
    }
    $stmt->close();

    return $out;
};

try {
    $link->query("DROP DATABASE IF EXISTS `$scratch`");
    $link->query("CREATE DATABASE `$scratch`");
    $link->select_db($scratch);
} catch (Throwable $e) {
    echo "check-interface-seed: cannot create a scratch database — " . trim($e->getMessage()) . "\n";
    echo "check-interface-seed: skipped. This needs an account that can CREATE DATABASE.\n";
    exit(SEED_SKIPPED);
}

/**
 * Run a whole SQL file through the server rather than splitting it here.
 *
 * The seed opens with SET SQL_MODE, START TRANSACTION and SET time_zone, and
 * rebuilding those through a parser ran them together into one statement the
 * server would not take. MySQL already knows where a statement ends, so for a
 * file that has to apply as a whole it does the splitting.
 *
 * @return string[] Problems, empty when the file applied.
 */
function applyWholeFile(mysqli $link, string $sql, string $scratch): array
{
    $sql = preg_replace('/CREATE\s+DATABASE\s+(IF\s+NOT\s+EXISTS\s+)?`?interfacing`?/i', "CREATE DATABASE IF NOT EXISTS `$scratch`", $sql) ?? $sql;
    $sql = preg_replace('/^\s*USE\s+`?interfacing`?\s*;/im', "USE `$scratch`;", $sql) ?? $sql;

    $problems = [];
    try {
        $link->multi_query($sql);
        do {
            $result = $link->store_result();
            if ($result instanceof mysqli_result) {
                $result->free();
            }
        } while ($link->more_results() && $link->next_result());
    } catch (Throwable $e) {
        $problems[] = '[' . $e->getCode() . '] ' . trim($e->getMessage());
    }

    return $problems;
}

$applySql = static function (mysqli $link, string $sql, string $scratch): array {
    // The seed names its own database; point it at the scratch one instead.
    $sql = preg_replace('/CREATE\s+DATABASE\s+(IF\s+NOT\s+EXISTS\s+)?`?interfacing`?/i', "CREATE DATABASE IF NOT EXISTS `$scratch`", $sql) ?? $sql;
    $sql = preg_replace('/^\s*USE\s+`?interfacing`?\s*;/im', "USE `$scratch`;", $sql) ?? $sql;

    $benign = [1050, 1054, 1060, 1061, 1068, 1091, 1146, 1826];
    $problems = [];
    foreach ((new PhpMyAdmin\SqlParser\Parser($sql))->statements as $statement) {
        $q = trim((string) $statement->build());
        if ($q === '') {
            continue;
        }
        try {
            $link->query($q);
        } catch (Throwable $e) {
            $code = (int) $e->getCode();
            if (!in_array($code, $benign, true)) {
                $problems[] = "[$code] " . trim($e->getMessage());
            }
        }
    }
    return $problems;
};

$exit = SEED_OK;

try {
    $link->select_db($scratch);
    $seedProblems = applyWholeFile($link, (string) file_get_contents($seedPath), $scratch);
    if ($seedProblems !== []) {
        echo "check-interface-seed: the seed itself did not apply cleanly:\n";
        foreach (array_slice($seedProblems, 0, 5) as $p) {
            echo "  ! $p\n";
        }
        $exit = SEED_BEHIND;
    }

    $link->select_db($scratch);
    $before = $snapshot($link, $scratch);

    foreach ($files as $file) {
        $link->select_db($scratch);
        $problems = $applySql($link, (string) file_get_contents((string) $file), $scratch);
        foreach ($problems as $p) {
            echo "  ! " . basename((string) $file) . ": $p\n";
            $exit = SEED_BEHIND;
        }
    }

    $link->select_db($scratch);
    $after = $snapshot($link, $scratch);

    $newColumns = array_values(array_diff($after['column'] ?? [], $before['column'] ?? []));
    $newIndexes = array_values(array_diff(array_keys($after['index'] ?? []), array_keys($before['index'] ?? [])));

    echo "check-interface-seed: seed + " . count($files) . " migration(s) from " . basename($found) . "\n";

    if ($newColumns === [] && $newIndexes === [] && $exit === SEED_OK) {
        echo "check-interface-seed: the seed already describes everything the migrations build.\n";
    } else {
        foreach ($newColumns as $c) {
            echo "  SEED MISSING COLUMN  $c\n";
        }
        foreach ($newIndexes as $i) {
            echo "  SEED MISSING INDEX   $i\n";
        }
        echo "check-interface-seed: " . count($newColumns) . " column(s) and " . count($newIndexes)
            . " index(es) exist only after the migrations run.\n";
        echo "check-interface-seed: a fresh install gets them late. Fold them into sql/interface-init.sql.\n";
        $exit = SEED_BEHIND;
    }
} finally {
    if (!$keep) {
        try {
            $link->query("DROP DATABASE IF EXISTS `$scratch`");
        } catch (Throwable) {
            // Leaving a scratch database behind is not worth failing over.
        }
    } else {
        echo "check-interface-seed: scratch database `$scratch` kept.\n";
    }
}

exit($exit);
