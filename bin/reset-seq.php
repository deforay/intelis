#!/usr/bin/env php
<?php

/**
 * Recalculates sequence_counter values from actual table data.
 *
 * Use this script when sample code generation gets stuck or counters
 * become out of sync with actual data.
 *
 * Usage: php bin/reset-seq.php [--dry-run]
 */

declare(ticks=1);

require_once __DIR__ . "/../bootstrap.php";

// Only run from command line
if (PHP_SAPI !== 'cli') {
    exit(CLI\ERROR);
}

use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Output\ConsoleOutput;

$input = new ArgvInput();
$io = new SymfonyStyle($input, new ConsoleOutput());

$dryRun = $input->hasParameterOption(['--dry-run', '-n']);

$io->title('Sequence Counter Recalculation');

if ($dryRun) {
    $io->note('Running in dry-run mode. No changes will be made.');
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

if (!$db->isConnected()) {
    $io->error('Database connection failed.');
    exit(CLI\ERROR);
}

// Define test tables and their corresponding test types
$testTables = [
    'form_vl' => 'vl',
    'form_eid' => 'eid',
    'form_covid19' => 'covid19',
    'form_tb' => 'tb',
    'form_hepatitis' => 'hepatitis',
];

$codeTypes = ['sample_code', 'remote_sample_code'];

// Get current counter values for comparison
$currentCounters = [];
$result = $db->rawQuery("SELECT * FROM sequence_counter");
foreach ($result as $row) {
    $key = "{$row['test_type']}|{$row['year']}|{$row['code_type']}";
    $currentCounters[$key] = (int) $row['max_sequence_number'];
}

$changes = [];
$io->section('Analyzing sequence counters...');

foreach ($testTables as $table => $testType) {
    foreach ($codeTypes as $codeType) {
        $codeKey = "{$codeType}_key";

        // Get actual max values from the table grouped by year
        $query = "SELECT
                    YEAR(sample_collection_date) AS year,
                    MAX($codeKey) AS actual_max
                  FROM $table
                  WHERE sample_collection_date IS NOT NULL
                  GROUP BY YEAR(sample_collection_date)
                  HAVING MAX($codeKey) IS NOT NULL";

        $rows = $db->rawQuery($query);

        foreach ($rows as $row) {
            $year = (int) $row['year'];
            $actualMax = (int) $row['actual_max'];
            $key = "{$testType}|{$year}|{$codeType}";
            $currentMax = $currentCounters[$key] ?? null;

            if ($currentMax === null || $currentMax !== $actualMax) {
                $changes[] = [
                    'test_type' => $testType,
                    'year' => $year,
                    'code_type' => $codeType,
                    'current' => $currentMax ?? '(none)',
                    'actual' => $actualMax,
                    'status' => $currentMax === null ? 'MISSING' : ($currentMax < $actualMax ? 'BEHIND' : 'AHEAD'),
                ];
            }
        }
    }
}

// Handle generic tests separately (they have dynamic test types)
$genericQuery = "SELECT
                    r.test_short_code AS test_type,
                    YEAR(f.sample_collection_date) AS year,
                    'sample_code' AS code_type,
                    MAX(f.sample_code_key) AS actual_max
                 FROM form_generic f
                 INNER JOIN r_test_types r ON r.test_type_id = f.test_type
                 WHERE f.sample_collection_date IS NOT NULL
                 GROUP BY r.test_short_code, YEAR(f.sample_collection_date)
                 HAVING MAX(f.sample_code_key) IS NOT NULL";

$genericRows = $db->rawQuery($genericQuery);
foreach ($genericRows as $row) {
    $testType = $row['test_type'];
    $year = (int) $row['year'];
    $actualMax = (int) $row['actual_max'];
    $key = "{$testType}|{$year}|sample_code";
    $currentMax = $currentCounters[$key] ?? null;

    if ($currentMax === null || $currentMax !== $actualMax) {
        $changes[] = [
            'test_type' => $testType,
            'year' => $year,
            'code_type' => 'sample_code',
            'current' => $currentMax ?? '(none)',
            'actual' => $actualMax,
            'status' => $currentMax === null ? 'MISSING' : ($currentMax < $actualMax ? 'BEHIND' : 'AHEAD'),
        ];
    }
}

// Remote sample code for generic tests
$genericRemoteQuery = "SELECT
                        r.test_short_code AS test_type,
                        YEAR(f.sample_collection_date) AS year,
                        'remote_sample_code' AS code_type,
                        MAX(f.remote_sample_code_key) AS actual_max
                       FROM form_generic f
                       INNER JOIN r_test_types r ON r.test_type_id = f.test_type
                       WHERE f.sample_collection_date IS NOT NULL
                       GROUP BY r.test_short_code, YEAR(f.sample_collection_date)
                       HAVING MAX(f.remote_sample_code_key) IS NOT NULL";

$genericRemoteRows = $db->rawQuery($genericRemoteQuery);
foreach ($genericRemoteRows as $row) {
    $testType = $row['test_type'];
    $year = (int) $row['year'];
    $actualMax = (int) $row['actual_max'];
    $key = "{$testType}|{$year}|remote_sample_code";
    $currentMax = $currentCounters[$key] ?? null;

    if ($currentMax === null || $currentMax !== $actualMax) {
        $changes[] = [
            'test_type' => $testType,
            'year' => $year,
            'code_type' => 'remote_sample_code',
            'current' => $currentMax ?? '(none)',
            'actual' => $actualMax,
            'status' => $currentMax === null ? 'MISSING' : ($currentMax < $actualMax ? 'BEHIND' : 'AHEAD'),
        ];
    }
}

if (empty($changes)) {
    $io->success('All sequence counters are in sync. No changes needed.');
    exit(CLI\SUCCESS);
}

$io->section('Changes detected');
$io->table(
    ['Test Type', 'Year', 'Code Type', 'Current', 'Actual', 'Status'],
    array_map(fn($c) => [
        $c['test_type'],
        $c['year'],
        $c['code_type'],
        $c['current'],
        $c['actual'],
        $c['status'],
    ], $changes)
);

if ($dryRun) {
    $io->note(sprintf('%d counter(s) would be updated. Run without --dry-run to apply changes.', count($changes)));
    exit(CLI\SUCCESS);
}

$io->section('Applying changes...');

$updated = 0;
$errors = 0;

foreach ($changes as $change) {
    $query = "INSERT INTO sequence_counter (test_type, year, code_type, max_sequence_number)
              VALUES (?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE
              max_sequence_number = VALUES(max_sequence_number)";

    try {
        $db->rawQuery($query, [
            $change['test_type'],
            $change['year'],
            $change['code_type'],
            $change['actual'],
        ]);
        $updated++;
    } catch (Throwable $e) {
        $io->error("Failed to update {$change['test_type']}/{$change['year']}/{$change['code_type']}: " . $e->getMessage());
        $errors++;
    }
}

$io->newLine();

if ($errors > 0) {
    $io->warning(sprintf('Completed with %d error(s). %d counter(s) updated successfully.', $errors, $updated));
    exit(CLI\ERROR);
}

$io->success(sprintf('%d sequence counter(s) updated successfully.', $updated));
exit(CLI\SUCCESS);
