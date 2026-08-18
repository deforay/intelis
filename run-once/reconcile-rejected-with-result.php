<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Services\VlService;
use App\Utilities\MiscUtility;
use App\Services\TestsService;
use App\Utilities\LoggerUtility;
use App\Utilities\RunOnceUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\TEST_FAILED;

/*
 * One-time repair for samples that are recorded as rejected *and* carry a
 * result. A sample is rejected before it is tested, so it should have no result
 * to show, and a row holding both is counted as a rejection by some reports and
 * as a result by others -- which is how the same question came to have four
 * different answers across the app.
 *
 * Rejection lives in two places, and each of the two bugs below wrote one and
 * left the other behind. Both causes are already closed; this repairs what they
 * produced, which nothing else will:
 *
 *   - the file importer took result_status from a positional array while
 *     hardcoding is_sample_rejected = 'no', so a misaligned index could stamp
 *     REJECTED onto a row that nobody had rejected (fixed in 26af22772)
 *   - the analyzer interface wrote a result onto an already-rejected sample
 *     without clearing the rejection flag or reason (fixed in 8f9273941)
 *
 * Nothing else tidies these up. bin/update-sample-status.php derives status
 * from the result only in its failed-samples block, which runs
 * "result_status NOT IN (REJECTED, TEST_FAILED)" and so skips the very rows
 * that need it -- and its locking block has since locked most of them, putting
 * them behind the edit-locked permission for anyone correcting them by hand.
 * accept-failed-result.php only accepts rows whose status is TEST_FAILED.
 *
 * What is repaired, and only ever where the record is unambiguous:
 *
 *   Case A  status REJECTED, flag not 'yes', a genuine result present
 *           The flag is the operator's own record and it says nobody rejected
 *           this. The status is the false one, so it moves to ACCEPTED, or to
 *           TEST_FAILED when the result itself reads as a failure.
 *
 *   Case B  flag 'yes', status already moved off REJECTED, a result present
 *           The status says the sample was tested and the result stands. The
 *           flag is the stale one, so it and the reason are cleared -- exactly
 *           what the interface fix now does at write time.
 *
 * A row where both records agree it was rejected *and* a result is present is
 * genuinely contradictory: neither column can be called the wrong one without
 * guessing. Those are left untouched and reported, so a human can rule on them.
 */

const REPORT_LIMIT = 20;

RunOnceUtility::run(__FILE__, function (DatabaseService $db): void {
    /** @var VlService $vlService */
    $vlService = ContainerRegistry::get(VlService::class);

    $columnExists = static function (string $table, string $column) use ($db): bool {
        $row = $db->rawQueryOne("SHOW COLUMNS FROM `$table` LIKE '" . $db->escape($column) . "'");
        return !empty($row);
    };

    // The same failure markers bin/update-sample-status.php uses, so a result
    // this script calls a failure is one the rest of the app already does.
    $failureSql = "(LOWER(TRIM(%1\$s)) LIKE 'fail%%' OR LOWER(TRIM(%1\$s)) LIKE 'err%%')";

    $totalCaseA = 0;
    $totalCaseB = 0;
    $totalAmbiguous = 0;
    $ambiguousExamples = [];

    foreach (TestsService::getActiveTests() as $testKey) {
        $table = TestsService::getTestTableName($testKey);
        $primaryKey = TestsService::getPrimaryColumn($testKey);
        $resultColumn = TestsService::getResultColumn($testKey);

        if (empty($table) || empty($primaryKey) || empty($resultColumn)) {
            continue;
        }
        // Custom tests keep per-test results in their own child table and have
        // no single result column to reason about here.
        if (!$columnExists($table, $resultColumn) || !$columnExists($table, 'is_sample_rejected')) {
            continue;
        }

        $hasResult = "($table.$resultColumn IS NOT NULL AND TRIM($table.$resultColumn) <> '')";
        $looksFailed = sprintf($failureSql, "$table.$resultColumn");

        // --- Case A: the status is the false record -------------------------
        //
        // Read first rather than blind-updating, because the new status depends
        // on the result and because VL carries a derived category that has to
        // be recomputed from it.
        $caseA = $db->rawQuery(
            "SELECT $primaryKey, $resultColumn AS result_value, $looksFailed AS looks_failed
               FROM $table
              WHERE result_status = " . REJECTED . "
                AND (is_sample_rejected IS NULL OR is_sample_rejected <> 'yes')
                AND $hasResult"
        ) ?: [];

        foreach ($caseA as $row) {
            $newStatus = ((int) $row['looks_failed'] === 1) ? TEST_FAILED : ACCEPTED;

            $update = [
                'result_status' => $newStatus,
                'reason_for_sample_rejection' => null,
                'is_sample_rejected' => 'no',
                // The correction has to reach STS too, or the next sync brings
                // the contradiction back down.
                'data_sync' => 0,
            ];

            if ($testKey === 'vl') {
                $update['vl_result_category'] = $vlService->getVLResultCategory(
                    $newStatus,
                    (string) $row['result_value']
                );
            }

            $db->where($primaryKey, $row[$primaryKey]);
            if ($db->update($table, $update)) {
                $totalCaseA++;
            }
        }

        // --- Case B: the flag is the stale record ---------------------------
        //
        // The status already says what the sample is, so there is nothing
        // per-row to decide. The ids are read first only so the number reported
        // is the number actually changed.
        $caseB = $db->rawQuery(
            "SELECT $primaryKey
               FROM $table
              WHERE is_sample_rejected = 'yes'
                AND result_status <> " . REJECTED . "
                AND $hasResult"
        ) ?: [];

        if ($caseB !== []) {
            $ids = array_column($caseB, $primaryKey);
            $db->where($primaryKey, $ids, 'IN');
            if ($db->update($table, [
                'is_sample_rejected' => 'no',
                'reason_for_sample_rejection' => null,
                'data_sync' => 0,
            ])) {
                $totalCaseB += count($ids);
            }
        }

        // --- Left alone, on purpose -----------------------------------------
        $ambiguous = $db->rawQuery(
            "SELECT sample_code
               FROM $table
              WHERE result_status = " . REJECTED . "
                AND is_sample_rejected = 'yes'
                AND $hasResult
              LIMIT " . REPORT_LIMIT
        ) ?: [];
        $ambiguousCount = $db->rawQuery(
            "SELECT COUNT(*) AS total
               FROM $table
              WHERE result_status = " . REJECTED . "
                AND is_sample_rejected = 'yes'
                AND $hasResult"
        ) ?: [];

        $count = (int) ($ambiguousCount[0]['total'] ?? 0);
        if ($count > 0) {
            $totalAmbiguous += $count;
            foreach ($ambiguous as $row) {
                $ambiguousExamples[] = $testKey . ':' . ($row['sample_code'] ?? '?');
            }
        }
    }

    if ($totalCaseA === 0 && $totalCaseB === 0 && $totalAmbiguous === 0) {
        MiscUtility::safeCliEcho("Rejected-with-result reconciliation… nothing to repair." . PHP_EOL);
        return;
    }

    MiscUtility::safeCliEcho(
        "Rejected-with-result reconciliation:" . PHP_EOL
            . "  $totalCaseA sample(s) had a false Rejected status cleared" . PHP_EOL
            . "  $totalCaseB sample(s) had a stale rejection flag cleared" . PHP_EOL
    );

    // Reported rather than repaired: both records say rejected and a result is
    // present, so there is no column that can be called wrong without guessing.
    if ($totalAmbiguous > 0) {
        MiscUtility::safeCliEcho(
            "  $totalAmbiguous sample(s) left untouched — rejected and resulted, needs a human decision" . PHP_EOL
        );
        LoggerUtility::logInfo(
            "reconcile-rejected-with-result: $totalAmbiguous sample(s) are recorded as rejected and carry a result. "
                . "Both records agree, so neither can be corrected automatically.",
            ['examples' => array_slice($ambiguousExamples, 0, REPORT_LIMIT)]
        );
    }

    LoggerUtility::logInfo(
        "reconcile-rejected-with-result: repaired $totalCaseA false-rejected and $totalCaseB stale-flag sample(s)."
    );
});
