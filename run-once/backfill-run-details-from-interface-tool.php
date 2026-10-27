<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Utilities\RunOnceUtility;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\InterfacingService;

/*
 * @run-once-background
 *
 * One-time pass filling in what the analyzer reported about each VL and EID run
 * imported from the Interface Tool before InteLIS recorded it: the assay (5.7.82),
 * the reagent lot and expiry, and the instrument model and serial, run ID, message
 * and readings (5.7.85).
 *
 * Renamed from backfill-assay-from-interface-tool.php when the 5.7.85 fields were
 * added, so a lab that ran that one runs this again; filling only empty fields, it
 * changes nothing it already filled.
 *
 * The Interface Tool keeps every result it ever read, with the analyzer's message,
 * in its own orders table. On a lab where InteLIS reads that table directly
 * (interfacing database in config), each stored result is read again and its assay
 * and lot are written to the run it came from: the sample's current result when the
 * test times match, or the archived attempt with that test time. Only empty fields
 * are filled, and a lot only together with its expiry, so nothing a user typed is
 * replaced. InterfacingService::backfillFromStoredResults() holds the rules.
 *
 * last_modified_datetime is left alone. Filled results get data_sync = 0, so the
 * next results sync carries them to the STS, which keeps its own timestamp for a
 * change to these columns alone. A lab whose Interface Tool sends over the results
 * API has no such table here and is skipped.
 *
 * Backgrounded: a busy lab's Interface Tool holds tens of thousands of results.
 */

RunOnceUtility::run(__FILE__, function (DatabaseService $db): void {
    $config = SYSTEM_CONFIG['interfacing']['database'] ?? [];
    if (empty($config['host']) || empty($config['username'])) {
        MiscUtility::safeCliEcho('Analyzer details from the Interface Tool… no Interface Tool database here.' . PHP_EOL);
        return;
    }

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);
    $labId = (int) $general->getSystemConfig('sc_testing_lab_id');
    if ($labId <= 0) {
        MiscUtility::safeCliEcho('Analyzer details from the Interface Tool… no testing lab configured here.' . PHP_EOL);
        return;
    }

    // An unreachable Interface Tool database throws, so the next upgrade tries again.
    $db->addConnection('interface', $config);

    /** @var InterfacingService $interfacing */
    $interfacing = ContainerRegistry::get(InterfacingService::class);

    $stored = " FROM orders WHERE (IFNULL(raw_text, '') != '' OR IFNULL(test_type, '') != '')";
    $total = (int) ($db->connection('interface')->rawQueryOne('SELECT COUNT(*) AS n' . $stored)['n'] ?? 0);
    // It ran for hours on a lab with no output at all, and looked hung.
    $bar = MiscUtility::spinnerStart($total, 'Analyzer details from the Interface Tool…');

    $outcomes = [];
    $lastId = 0;
    do {
        // Selected for every query: each one hands the shared handle back to the
        // default connection when it is done.
        $rows = $db->connection('interface')->rawQuery(
            'SELECT id, order_id, test_id, test_type, result_accepted_date_time, raw_text
               FROM orders
              WHERE id > ?
                AND (IFNULL(raw_text, \'\') != \'\' OR IFNULL(test_type, \'\') != \'\')
              ORDER BY id
              LIMIT 500',
            [$lastId]
        ) ?: [];

        // One transaction per batch. Each update committed on its own, and a lab that
        // flushes the log and the binlog at every commit managed 3.5 rows a second.
        // A deadlock now rolls back the whole batch instead of one statement, so the
        // batch runs again: it only fills empty fields, so running it twice is safe.
        for ($attempt = 1;; $attempt++) {
            $db->beginTransaction();
            try {
                $batchOutcomes = $interfacing->backfillFromStoredResults($rows, $labId);
                $db->commitTransaction();
                break;
            } catch (mysqli_sql_exception $e) {
                $db->rollbackTransaction();
                if ((int) $e->getCode() !== 1213 || $attempt >= 3) {
                    throw $e;
                }
            }
        }
        foreach ($batchOutcomes as $outcome) {
            $outcomes[$outcome] = ($outcomes[$outcome] ?? 0) + 1;
        }
        MiscUtility::spinnerAdvance($bar, count($rows));
        $lastId = $rows === [] ? $lastId : (int) end($rows)['id'];
    } while (count($rows) === 500);
    MiscUtility::spinnerFinish($bar);

    $filled = ($outcomes['filled'] ?? 0) + ($outcomes['filled_attempt'] ?? 0);
    MiscUtility::safeCliEcho(
        'Analyzer details from the Interface Tool:' . PHP_EOL
            . '  ' . array_sum($outcomes) . ' stored result(s) read' . PHP_EOL
            . "  $filled result(s) filled in"
            . ' (' . ($outcomes['filled_attempt'] ?? 0) . ' of them on archived re-test runs)' . PHP_EOL
            . '  ' . ($outcomes['already_recorded'] ?? 0) . ' already recorded, '
            . ($outcomes['no_sample'] ?? 0) . ' with no matching VL or EID sample, '
            . (($outcomes['other_run'] ?? 0) + ($outcomes['no_test_time'] ?? 0)) . ' from another run, '
            . ($outcomes['ambiguous'] ?? 0) . ' matching more than one sample, '
            . ($outcomes['nothing_to_read'] ?? 0) . ' with nothing to read' . PHP_EOL
            . '  Every change is recoverable from audit_log.' . PHP_EOL
    );
});
