#!/usr/bin/env php
<?php

// Find and repair form_vl rows whose derived viral load columns cannot be true, by
// recomputing them from the result the lab actually reported.
//
// `result` is the record of what an instrument returned or an operator typed, and this
// script never writes to it. result_value_log, result_value_absolute and
// result_value_absolute_decimal are derived from it and are treated as a cache that can
// be rebuilt -- which is the whole reason a repair like this can be run at all.
//
// Only rows that fail the plausibility check are touched. A row whose stored values are
// merely rounded differently from a fresh interpretation is left exactly as it is:
// rewriting those would churn most of the table to no purpose and bury the rows that
// genuinely need attention.
//
// Dry run by default. Nothing is written without --apply.
//
// Usage:
//   php bin/vl-recompute-derived.php                 report what would change
//   php bin/vl-recompute-derived.php --apply         repair the rows
//   php bin/vl-recompute-derived.php --limit 500     stop after 500 candidates
//   php bin/vl-recompute-derived.php --show 40       print 40 examples (default 20)

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    exit(0);
}

require_once __DIR__ . "/../bootstrap.php";

use App\Services\VlService;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

$apply = false;
$limit = null;
$showLimit = 20;
$batchSize = 2000;

$argvRest = array_slice($argv, 1);
for ($i = 0; $i < count($argvRest); $i++) {
    $arg = $argvRest[$i];
    if ($arg === '--apply') {
        $apply = true;
    } elseif ($arg === '--dry-run') {
        $apply = false;
    } elseif ($arg === '--limit') {
        $limit = (int) ($argvRest[++$i] ?? 0);
    } elseif (str_starts_with($arg, '--limit=')) {
        $limit = (int) substr($arg, 8);
    } elseif ($arg === '--show') {
        $showLimit = (int) ($argvRest[++$i] ?? 20);
    } elseif (str_starts_with($arg, '--show=')) {
        $showLimit = (int) substr($arg, 7);
    } elseif ($arg === '--batch-size') {
        $batchSize = max(100, (int) ($argvRest[++$i] ?? 2000));
    } elseif ($arg === '-h' || $arg === '--help') {
        echo "Usage: php bin/vl-recompute-derived.php [--apply] [--limit N] [--show N] [--batch-size N]\n";
        exit(0);
    } else {
        fwrite(STDERR, "Unknown option: {$arg}\n");
        exit(1);
    }
}

$lockTargetFile = __FILE__;
if (!MiscUtility::isLockFileExpired($lockTargetFile)) {
    echo "Another instance is already running. Exiting." . PHP_EOL;
    exit(0);
}
MiscUtility::touchLockFile($lockTargetFile);
MiscUtility::setupSignalHandler($lockTargetFile);

$startTime = microtime(true);
$lastId = 0;
$scanned = 0;
$candidates = 0;
$repaired = 0;
$clearedOnly = 0;
$examples = [];
$droppedByColumn = [];
$clearedReasons = [];
$clearedNumeric = [];
$exitCode = 0;

echo ($apply ? "Repairing" : "Dry run -- reporting only") . ", batch size {$batchSize}." . PHP_EOL . PHP_EOL;

try {
    do {
        $rows = $db->rawQuery(
            "SELECT vl_sample_id, sample_code, specimen_type, result,
                    result_value_log, result_value_absolute, result_value_absolute_decimal
             FROM form_vl
             WHERE vl_sample_id > ?
               AND (
                    (result_value_log IS NOT NULL AND TRIM(result_value_log) <> '')
                 OR (result_value_absolute IS NOT NULL AND TRIM(result_value_absolute) <> '')
                 OR (result_value_absolute_decimal IS NOT NULL AND TRIM(result_value_absolute_decimal) <> '')
               )
             ORDER BY vl_sample_id
             LIMIT ?",
            [$lastId, $batchSize]
        );

        if ($rows === [] || $rows === false || $rows === null) {
            break;
        }

        foreach ($rows as $row) {
            $lastId = (int) $row['vl_sample_id'];
            $scanned++;

            $check = $vlService->sanitizeDerivedVlValues(
                $row['result_value_log'],
                $row['result_value_absolute'],
                $row['result_value_absolute_decimal']
            );

            // Stored values hold together. Rounding differences against a fresh
            // interpretation are not a defect and are deliberately not chased.
            if ($check['dropped'] === []) {
                continue;
            }

            $candidates++;
            foreach ($check['dropped'] as $column) {
                $droppedByColumn[$column] = ($droppedByColumn[$column] ?? 0) + 1;
            }

            // Rebuild from the reported result. Where that yields nothing usable -- a
            // qualitative result, or one too mangled to read -- the sanitised values
            // stand, which means the impossible figures are removed rather than replaced.
            $rebuilt = null;
            if (trim((string) $row['result']) !== '') {
                $rebuilt = $vlService->interpretViralLoadResult(
                    $row['result'],
                    null,
                    null,
                    $row['specimen_type'] ?? null
                );
            }

            $update = [
                'result_value_log' => $check['logVal'],
                'result_value_absolute' => $check['absVal'],
                'result_value_absolute_decimal' => $check['absDecimalVal'],
            ];

            // A successful interpretation replaces all three columns, nulls included. A
            // qualitative result -- "Target Not Detected" -- has no copies figure and no
            // log, so keeping a stale log of 0 beside a cleared absolute would leave the
            // row asserting one copy per millilitre. The rebuilt set is coherent; a
            // merge of the survivors is not.
            if (!empty($rebuilt)) {
                $fresh = $vlService->sanitizeDerivedVlValues(
                    $rebuilt['logVal'] ?? null,
                    $rebuilt['absVal'] ?? null,
                    $rebuilt['absDecimalVal'] ?? null
                );
                $update['result_value_log'] = $fresh['logVal'];
                $update['result_value_absolute'] = $fresh['absVal'];
                $update['result_value_absolute_decimal'] = $fresh['absDecimalVal'];
            }

            $recovered = $update['result_value_log'] !== null
                || $update['result_value_absolute'] !== null
                || $update['result_value_absolute_decimal'] !== null;
            if ($recovered) {
                $repaired++;
            } else {
                $clearedOnly++;
                // Cleared because the result carries no number to rebuild from is
                // expected. Cleared while the result plainly holds one is not, and is
                // worth a look before this is applied.
                // Judged on what the interpretation made of the result, not on whether
                // the raw text looks numeric: "-1.00" is the Target Not Detected
                // sentinel, so it is qualitative despite passing is_numeric().
                if (trim((string) $row['result']) === '') {
                    $bucket = 'no result recorded';
                } elseif (!empty($rebuilt['txtVal'])) {
                    $bucket = 'qualitative result';
                } else {
                    $bucket = 'NUMERIC RESULT, REVIEW';
                }
                $clearedReasons[$bucket] = ($clearedReasons[$bucket] ?? 0) + 1;
                if ($bucket === 'NUMERIC RESULT, REVIEW' && count($clearedNumeric) < $showLimit) {
                    $clearedNumeric[] = sprintf('%s result=%s', $row['sample_code'], $row['result']);
                }
            }

            if (count($examples) < $showLimit) {
                $examples[] = [
                    'sample' => (string) $row['sample_code'],
                    'result' => (string) $row['result'],
                    'was' => sprintf(
                        'log=%s abs=%s dec=%s',
                        $row['result_value_log'] ?? 'NULL',
                        $row['result_value_absolute'] ?? 'NULL',
                        $row['result_value_absolute_decimal'] ?? 'NULL'
                    ),
                    'now' => sprintf(
                        'log=%s abs=%s dec=%s',
                        $update['result_value_log'] ?? 'NULL',
                        $update['result_value_absolute'] ?? 'NULL',
                        $update['result_value_absolute_decimal'] ?? 'NULL'
                    ),
                ];
            }

            if ($apply) {
                // Reset the sync flag so the correction reaches STS, exactly as the
                // migration that cleared the log column does.
                $update['data_sync'] = 0;
                $db->where('vl_sample_id', $row['vl_sample_id']);
                $db->update('form_vl', $update);
            }

            if ($limit !== null && $limit > 0 && $candidates >= $limit) {
                break 2;
            }
        }

        MiscUtility::touchLockFile($lockTargetFile);
    } while (count($rows) >= $batchSize);
} catch (Throwable $e) {
    LoggerUtility::log('error', 'vl-recompute-derived failed: ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    fwrite(STDERR, "✗ " . $e->getMessage() . PHP_EOL);
    $exitCode = 1;
}

if ($examples !== []) {
    echo "Examples:" . PHP_EOL;
    foreach ($examples as $example) {
        echo sprintf(
            "  %-18s result=%-24s %s  ->  %s%s",
            $example['sample'],
            mb_strimwidth($example['result'], 0, 24, '…'),
            $example['was'],
            $example['now'],
            PHP_EOL
        );
    }
    echo PHP_EOL;
}

if ($clearedReasons !== []) {
    echo "Cleared without a rebuild, by reason:" . PHP_EOL;
    foreach ($clearedReasons as $reason => $count) {
        echo sprintf("  %-28s %d%s", $reason, $count, PHP_EOL);
    }
    foreach ($clearedNumeric as $line) {
        echo "    " . $line . PHP_EOL;
    }
    echo PHP_EOL;
}

if ($droppedByColumn !== []) {
    echo "Implausible values by column:" . PHP_EOL;
    foreach ($droppedByColumn as $column => $count) {
        echo sprintf("  %-32s %d%s", $column, $count, PHP_EOL);
    }
    echo PHP_EOL;
}

echo sprintf(
    "Scanned %d rows with derived values. %d could not be true: %d rebuilt from the reported result, %d cleared.%s",
    $scanned,
    $candidates,
    $repaired,
    $clearedOnly,
    PHP_EOL
);
echo sprintf("Elapsed %s seconds.%s", MiscUtility::elapsedTime($startTime), PHP_EOL);

if (!$apply && $candidates > 0) {
    echo PHP_EOL . "Nothing was written. Re-run with --apply to repair these rows." . PHP_EOL;
}

MiscUtility::deleteLockFile($lockTargetFile);
exit($exitCode);
