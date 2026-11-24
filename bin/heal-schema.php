#!/usr/bin/env php
<?php

/**
 * Adds the tables and columns sql/init.sql declares and this database lacks.
 *
 *   php bin/heal-schema.php           preview the repairs, change nothing
 *   php bin/heal-schema.php --apply   make the repairs
 *
 * Also runs as `intelis heal-schema` and `intelis heal-schema:apply`.
 *
 * A database that lost a table or column halts the next migration that reads
 * it, and every later migration with it. bin/preflight.php reports that drift.
 * This repairs it, then bin/migrate.php can carry on.
 *
 * It only adds. It never drops, renames or retypes anything, and never touches
 * rows. Where a migration renames or copies an existing table or column into the
 * missing one, the repair is left to that migration, because adding the new name
 * empty would strand the data under the old one.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$arguments = array_slice($argv, 1);
if (array_diff($arguments, ['--apply', '--help']) !== []) {
    fwrite(STDERR, "Usage: php bin/heal-schema.php [--apply]\n");
    exit(2);
}
if (in_array('--help', $arguments, true)) {
    echo "Usage: php bin/heal-schema.php [--apply]\n";
    echo "Previews the tables and columns sql/init.sql declares and this database lacks.\n";
    echo "Use --apply to add them.\n";
    exit(0);
}
$apply = in_array('--apply', $arguments, true);

// Not the application bootstrap. Booting reads configuration tables, and those may
// be among what is missing. Only the SQL parser is needed from vendor/.
$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
require_once __DIR__ . '/lib/SchemaRepairPlan.php';

try {
    $environment = getenv('APPLICATION_ENV') ?: 'production';
    $configFile = $root . "/configs/config.{$environment}.php";
    if (!is_file($configFile)) {
        $configFile = $root . '/configs/config.production.php';
    }
    $systemConfig = is_file($configFile) ? include $configFile : null;
    if (!is_array($systemConfig)) {
        throw new RuntimeException('Cannot read configs/config.production.php.');
    }
    $config = $systemConfig['database'] ?? [];
    $pdo = new PDO(
        sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            ($config['host'] ?? '') ?: '127.0.0.1',
            (int) (($config['port'] ?? 0) ?: 3306),
            (string) ($config['db'] ?? '')
        ),
        (string) ($config['username'] ?? ''),
        (string) ($config['password'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
    $schema = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

    $source = file_get_contents($root . '/sql/init.sql');
    if ($source === false) {
        throw new RuntimeException('Cannot read sql/init.sql.');
    }
    $repair = new SchemaRepairPlan($source);

    $columns = [];
    $statement = $pdo->prepare('SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ?');
    $statement->execute([$schema]);
    foreach ($statement->fetchAll() as $row) {
        $columns[(string) $row['TABLE_NAME']][] = (string) $row['COLUMN_NAME'];
    }

    // What the migrations not yet run here will add is not drift. migrate.php adds
    // it, often with a backfill that adding it here first would skip.
    // A database whose version cannot be read is treated as having run none of
    // them, so only what no migration adds gets repaired.
    try {
        $version = $pdo->query("SELECT `value` FROM `system_config` WHERE `name` = 'sc_version'")->fetchColumn();
    } catch (PDOException $e) {
        echo 'Cannot read sc_version (' . $e->getMessage() . "). Repairing only what no migration adds.\n";
        $version = null;
    }
    $version = is_string($version) && $version !== '' ? $version : null;
    $repair->deferPending(schema_pending_additions($root . '/sys/migrations', $version));

    $migrations = glob($root . '/sys/migrations/*.sql') ?: [];
    usort($migrations, static fn(string $a, string $b): int => version_compare(basename($a, '.sql'), basename($b, '.sql')));
    foreach ($migrations as $file) {
        $migration = file_get_contents($file);
        if ($migration === false) {
            throw new RuntimeException('Cannot read migration ' . basename($file) . '.');
        }
        $repair->deferMigrationTargets($migration, $columns, basename($file, '.sql'));
    }

    $plan = $repair->build($columns);
    echo ($apply ? 'Repairing' : 'Checking') . " database {$schema} against sql/init.sql\n";
    if ($repair->pendingCount() > 0) {
        echo "This database is at " . ($version ?? 'no recorded version') . '. ' . $repair->pendingCount()
            . " missing table(s) and column(s) come with later migrations. php bin/migrate.php adds those.\n";
    }
    foreach ($repair->deferred() as $item) {
        echo "Left alone: {$item['name']}. Migration {$item['migration']} moves the data there from {$item['from']}.\n";
        if ($version !== null && version_compare($item['migration'], $version, '<=')) {
            // The migration already ran here, so nothing will run it again unprompted.
            echo "  This database is past {$item['migration']}. Replay it with: php bin/migrate.php --version={$item['migration']}\n";
        }
    }
    foreach ($repair->manual() as $message) {
        echo "Not added: {$message}\n";
    }
    if ($plan === []) {
        echo "Nothing to add.\n";
        exit($repair->manual() === [] ? 0 : 1);
    }

    $failed = 0;
    foreach ($plan as $step) {
        echo "\n" . $step['sql'] . ";\n";
        if (!$apply) {
            continue;
        }
        try {
            if ($step['create']) {
                // A new table is empty, so its foreign keys can be created before the
                // tables they point to, which may come later in the plan.
                $checks = (int) $pdo->query('SELECT @@SESSION.FOREIGN_KEY_CHECKS')->fetchColumn();
                try {
                    $pdo->exec('SET SESSION FOREIGN_KEY_CHECKS = 0');
                    $pdo->exec($step['sql']);
                } finally {
                    $pdo->exec('SET SESSION FOREIGN_KEY_CHECKS = ' . $checks);
                }
            } else {
                $pdo->exec($step['sql']);
            }
            echo "Done.\n";
        } catch (PDOException $e) {
            $failed++;
            echo 'Failed: ' . $e->getMessage() . "\n";
        }
    }

    echo "\n";
    if (!$apply) {
        echo count($plan) . " repair(s) found. Nothing was changed.\n";
        echo "To make them, run: php bin/heal-schema.php --apply\n";
        exit($repair->manual() === [] ? 0 : 1);
    }
    if ($failed > 0) {
        echo $failed . ' of ' . count($plan) . " repair(s) failed. The others were made.\n";
        echo "Fix the failures above, then run this again. It only does what is still missing.\n";
        exit(1);
    }
    echo count($plan) . " repair(s) made.\n";
    if ($repair->manual() !== []) {
        echo "The columns under \"Not added\" above still need a person.\n";
        exit(1);
    }
    echo "Now run: php bin/migrate.php\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Schema repair failed: ' . $e->getMessage() . "\n");
    exit(1);
}
