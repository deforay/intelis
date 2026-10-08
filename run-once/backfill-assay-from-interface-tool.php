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
 * One-time pass filling in the assay and reagent lot of VL and EID results imported
 * from the Interface Tool before InteLIS recorded them (the assay since 5.7.82, the
 * lot and the assay read from the analyzer's message since the release after it).
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
        MiscUtility::safeCliEcho('Assay and lot from the Interface Tool… no Interface Tool database here.' . PHP_EOL);
        return;
    }

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);
    $labId = (int) $general->getSystemConfig('sc_testing_lab_id');
    if ($labId <= 0) {
        MiscUtility::safeCliEcho('Assay and lot from the Interface Tool… no testing lab configured here.' . PHP_EOL);
        return;
    }

    // An unreachable Interface Tool database throws, so the next upgrade tries again.
    $db->addConnection('interface', $config);

    /** @var InterfacingService $interfacing */
    $interfacing = ContainerRegistry::get(InterfacingService::class);

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

        foreach ($interfacing->backfillFromStoredResults($rows, $labId) as $outcome) {
            $outcomes[$outcome] = ($outcomes[$outcome] ?? 0) + 1;
        }
        $lastId = $rows === [] ? $lastId : (int) end($rows)['id'];
    } while (count($rows) === 500);

    $filled = ($outcomes['filled'] ?? 0) + ($outcomes['filled_attempt'] ?? 0);
    MiscUtility::safeCliEcho(
        'Assay and lot from the Interface Tool:' . PHP_EOL
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
