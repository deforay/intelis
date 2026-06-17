<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Utilities\MiscUtility;
use App\Utilities\RunOnceUtility;
use App\Services\DatabaseService;

/*
 * Normalize the per-test "reason for result/rejection change" columns to the canonical JSON array
 * produced by MiscUtility::appendResultChangeReason. Decoding is done with the same tolerant parser
 * the app uses, so every legacy shape (##/vlsm strings, single {user,...} objects) is preserved.
 *
 * Idempotent: a row is only rewritten when its normalized form differs from what is stored, so
 * re-running (or running after the app has already written canonical rows) is a no-op for those rows.
 *
 * Note: form_* tables carry audit triggers, so each rewrite produces one audit_log revision. This is
 * a one-time, legacy-rows-only pass, so the extra revisions are bounded.
 */
RunOnceUtility::run(__FILE__, function (DatabaseService $db): void {
    $targets = [
        ['table' => 'form_vl',       'pk' => 'vl_sample_id', 'col' => 'reason_for_result_changes'],
        ['table' => 'form_eid',      'pk' => 'eid_id',       'col' => 'reason_for_changing'],
        ['table' => 'form_covid19',  'pk' => 'covid19_id',   'col' => 'reason_for_changing'],
        ['table' => 'form_cd4',      'pk' => 'cd4_id',       'col' => 'reason_for_result_changes'],
        ['table' => 'form_hepatitis', 'pk' => 'hepatitis_id', 'col' => 'reason_for_changing'],
        ['table' => 'form_generic',  'pk' => 'sample_id',    'col' => 'reason_for_test_result_changes'],
        ['table' => 'form_tb',       'pk' => 'tb_id',        'col' => 'reason_for_changing'],
        ['table' => 'tb_tests',      'pk' => 'tb_test_id',   'col' => 'reason_for_result_change'],
    ];

    // Pre-count across all target tables so the single bar reflects real
    // overall progress rather than restarting per table.
    $grandTotal = 0;
    foreach ($targets as $t) {
        $countRow = $db->rawQueryOne(
            "SELECT COUNT(*) AS c FROM `{$t['table']}` "
                . "WHERE `{$t['col']}` IS NOT NULL AND TRIM(`{$t['col']}`) <> ''"
        );
        $grandTotal += (int) ($countRow['c'] ?? 0);
    }

    if ($grandTotal === 0) {
        MiscUtility::safeCliEcho("Normalizing change history… nothing to normalize." . PHP_EOL);
        return;
    }

    // spinnerStart renders this message on its own line directly above the
    // bar, so it is the single "what's running" line followed by the bar.
    $bar = MiscUtility::spinnerStart($grandTotal, 'Normalizing change history…');

    $updated = 0;
    foreach ($targets as $t) {
        $rows = $db->rawQuery(
            "SELECT `{$t['pk']}` AS id, `{$t['col']}` AS val FROM `{$t['table']}` "
                . "WHERE `{$t['col']}` IS NOT NULL AND TRIM(`{$t['col']}`) <> ''"
        );

        foreach ($rows as $row) {
            $history = MiscUtility::parseResultChangeHistory($row['val']);
            $normalized = empty($history) ? null : json_encode($history);

            // Rewrite only rows that aren't already in canonical form.
            if ((string) $normalized !== (string) $row['val']) {
                $db->where($t['pk'], $row['id']);
                $db->update($t['table'], [$t['col'] => $normalized]);
                $updated++;
            }

            MiscUtility::spinnerAdvance($bar);
        }
    }

    MiscUtility::spinnerFinish($bar);
    MiscUtility::safeCliEcho("Normalized {$updated} of {$grandTotal} row(s)." . PHP_EOL);
});
