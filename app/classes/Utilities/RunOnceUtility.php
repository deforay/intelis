<?php

declare(strict_types=1);

namespace App\Utilities;

use Throwable;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/**
 * Shared harness for run-once/*.php migration scripts.
 *
 * Collapses the boilerplate every run-once script used to repeat: the CLI
 * guard, the -f/--force flag, the s_run_once_scripts_log skip-check, exception
 * logging, the ledger write, and — crucially — the exit-code contract that
 * upgrade.sh's run-once summary relies on:
 *
 *   0 (EXIT_RAN)     -> did work this upgrade
 *   3 (EXIT_SKIPPED) -> already applied on this instance (upgrade.sh stays silent)
 *   1 (EXIT_FAILED)  -> the work threw; see the app log
 *
 * A whole run-once script becomes:
 *
 *   require_once __DIR__ . '/../bootstrap.php';
 *
 *   use App\Utilities\RunOnceUtility;
 *   use App\Services\DatabaseService;
 *
 *   RunOnceUtility::run(__FILE__, function (DatabaseService $db): void {
 *       // migration work; return to finish, throw to signal failure
 *   });
 *
 * The work closure receives the shared DatabaseService. Returning normally
 * marks the script applied (ledger row written so it never re-runs); throwing
 * leaves the ledger untouched so the next upgrade retries.
 *
 * Async opt-in: a long-running script can add a "@run-once-background" marker
 * comment near its top. upgrade.sh / docker/entrypoint.sh detect it and launch
 * the script detached (nohup … &) so it never blocks the upgrade. Such a script
 * still works through this harness normally — its skip-check + ledger write just
 * happen in the detached process, and the loop does not observe its exit code.
 *
 * This method never returns — it always exit()s with the contract code above.
 */
final class RunOnceUtility
{
    public const EXIT_RAN     = 0;
    public const EXIT_FAILED  = 1;
    public const EXIT_SKIPPED = 3;

    public static function run(string $scriptFile, callable $work): void
    {
        // Migration scripts are CLI-only. A web hit is a no-op and must not be
        // counted as a failure by the run-once summary.
        if (PHP_SAPI !== 'cli') {
            exit(self::EXIT_RAN);
        }

        $scriptName = basename($scriptFile);
        $argv = $_SERVER['argv'] ?? [];
        $forceRun = in_array('-f', $argv, true) || in_array('--force', $argv, true);

        /** @var DatabaseService $db */
        $db = ContainerRegistry::get(DatabaseService::class);

        if (!$forceRun) {
            $db->where('script_name', $scriptName);
            if ($db->getOne('s_run_once_scripts_log')) {
                exit(self::EXIT_SKIPPED);
            }
        }

        try {
            $work($db);
        } catch (Throwable $e) {
            LoggerUtility::logError("run-once {$scriptName} failed", [
                'exception'     => $e->getMessage(),
                'trace'         => $e->getTraceAsString(),
                'last_db_query' => $db->getLastQuery(),
                'last_db_error' => $db->getLastError(),
            ]);
            exit(self::EXIT_FAILED);
        }

        echo "{$scriptName} executed and logged successfully" . PHP_EOL;
        $db->setQueryOption('IGNORE')->insert('s_run_once_scripts_log', [
            'script_name'    => $scriptName,
            'execution_date' => DateUtility::getCurrentDateTime(),
            'status'         => $forceRun ? 'forced' : 'executed',
        ]);
        exit(self::EXIT_RAN);
    }
}
