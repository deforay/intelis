<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// only run from command line
if (php_sapi_name() !== 'cli') {
    exit(0);
}

require_once(__DIR__ . '/../bootstrap.php');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$scriptName = basename(__FILE__);

// Check for force flag (-f or --force)
$forceRun = in_array('-f', $argv) || in_array('--force', $argv);
$scriptSucceeded = false;
if (!$forceRun) {
    $db->where('script_name', $scriptName);
    if ($db->getOne('s_run_once_scripts_log')) {
        exit(0);
    }
}

/*
 * Custom (Generic) Tests moved from a standalone "Test Methods" field to per-result-group
 * methods: each result group in test_results_config now carries the set of methods that
 * produce it (config['methods'][groupKey] = [methodId,...]). Existing tests were configured
 * under the old model, so their config has no 'methods' key.
 *
 * This backfills config['methods'] from the legacy generic_test_methods_map: a test's mapped
 * methods are copied onto its result group(s). For a single-group test all methods go to that
 * one group; for a multi-group test every method is mapped to every group (the only safe
 * assumption without per-group history -- admins can refine afterwards). The map itself is
 * left untouched (it stays the source for getTestMethod/PDF/interop).
 *
 * Idempotent: a test whose config already has a non-empty 'methods' key is skipped, so
 * re-running (or running after admins have re-saved tests) is a no-op.
 */
try {
    $tests = $db->rawQuery("SELECT test_type_id, test_results_config FROM r_test_types");
    $total = is_array($tests) ? count($tests) : 0;
    $updated = 0;
    $skipped = 0;

    if ($total === 0) {
        MiscUtility::safeCliEcho("Backfilling result-group methods… no custom tests found." . PHP_EOL);
    } else {
        $bar = MiscUtility::spinnerStart($total, "Backfilling result-group methods");

        foreach ($tests as $row) {
            $testTypeId = (int) $row['test_type_id'];
            $config = json_decode((string) $row['test_results_config'], true);

            // No usable config, or methods already configured -> nothing to do.
            if (!is_array($config) || (!empty($config['methods']) && is_array($config['methods']))) {
                $skipped++;
                MiscUtility::spinnerAdvance($bar);
                continue;
            }

            // The methods currently mapped to this test (legacy standalone field).
            $mapRows = $db->rawQuery(
                "SELECT test_method_id FROM generic_test_methods_map WHERE test_type_id = ?",
                [$testTypeId]
            );
            $methodIds = [];
            foreach (($mapRows ?: []) as $m) {
                $mid = (int) $m['test_method_id'];
                if ($mid > 0) {
                    $methodIds[$mid] = $mid;
                }
            }
            $methodIds = array_values($methodIds);

            if (empty($methodIds)) {
                $skipped++;
                MiscUtility::spinnerAdvance($bar);
                continue;
            }

            // Result-group keys: whatever keys the config already uses for groups.
            $groupKeys = [];
            foreach (['result_type', 'sub_test_name'] as $dim) {
                if (!empty($config[$dim]) && is_array($config[$dim])) {
                    foreach (array_keys($config[$dim]) as $gk) {
                        $groupKeys[$gk] = $gk;
                    }
                }
            }
            if (empty($groupKeys)) {
                $groupKeys = [1 => 1];
            }

            // Single group -> all methods to it; multiple groups -> all methods to each.
            $config['methods'] = [];
            foreach ($groupKeys as $gk) {
                $config['methods'][$gk] = $methodIds;
            }

            $db->where('test_type_id', $testTypeId);
            $db->update('r_test_types', ['test_results_config' => json_encode($config)]);
            $updated++;

            MiscUtility::spinnerAdvance($bar);
        }

        MiscUtility::spinnerFinish($bar);
        MiscUtility::safeCliEcho("Backfilled {$updated} test(s); skipped {$skipped} of {$total}." . PHP_EOL);
    }

    $scriptSucceeded = true;
} catch (Throwable $e) {
    LoggerUtility::logError('backfill-generic-result-group-methods script failed', [
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'last_db_query' => $db->getLastQuery(),
        'last_db_error' => $db->getLastError(),
    ]);
} finally {
    if ($scriptSucceeded || $forceRun) {
        echo "$scriptName executed and logged successfully" . PHP_EOL;
        $db->setQueryOption('IGNORE')->insert('s_run_once_scripts_log', [
            'script_name' => $scriptName,
            'execution_date' => DateUtility::getCurrentDateTime(),
            'status' => $scriptSucceeded ? 'executed' : 'forced'
        ]);
    }
}
