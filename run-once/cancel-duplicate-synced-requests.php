<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Utilities\LoggerUtility;
use App\Utilities\RunOnceUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use const SAMPLE_STATUS\CANCELLED;

/*
 * @run-once-background
 *
 * One-time cleanup for test requests the sync receiver inserted over and over.
 *
 * TestRequestsService::findMatchingLocalRecord() looks for an incoming record
 * by remote_sample_code, by sample_code + lab_id, by unique_id, and by
 * sample_code + facility_id. A record carrying none of those keys matches
 * nothing and the method returns an empty array -- the same value it returns
 * for "searched properly, genuinely new". syncTestRequest() reads that as new
 * and inserts. So a record the matcher can never find again is inserted fresh
 * on every single sync run, without bound, for as long as the source keeps
 * sending it. On the instance this was found on, 141 such records had been
 * re-inserted every ten minutes for weeks, leaving 537,074 copies -- a third of
 * the whole table.
 *
 * The defect itself is still open; this only clears what it produced. Until it
 * is fixed, a source that starts sending an unidentifiable record again will
 * start the pile growing again.
 *
 * WHAT IS CANCELLED, AND WHY IT CANNOT TAKE A REAL SAMPLE WITH IT
 *
 * sample_code is not unique -- on the instance above, 47% of rows share one
 * with some other row -- so "same code" is nowhere near enough to call two rows
 * copies of each other. Three conditions have to hold together, and each one
 * rules out a different way of being wrong:
 *
 *   1. the row is unidentifiable: no unique_id and no remote_sample_code.
 *      This is the defect's own precondition. A row with either key would have
 *      been found and updated rather than re-inserted.
 *
 *   2. the row is byte-identical to a sibling with the same sample_code across
 *      every column except the primary key and the sync/audit bookkeeping.
 *      Two different samples that happen to share a code differ in the patient,
 *      the facility, the collection date, the result -- something. This is what
 *      does the real separating: of 82,563 code-sharing groups on that
 *      instance, exactly one was also content-identical.
 *
 *   3. the row has no facility. facility_id is mandatory on the request form,
 *      so a row without one was never entered by a person; it can only have
 *      arrived down a wire. This is the backstop: even if 1 and 2 somehow held
 *      for real records on some other instance, real records have a facility.
 *
 * A group that satisfies 1 and 2 but has a facility is counted and logged and
 * left alone, because at that point it is a duplicate of something a human
 * plausibly created, and that is a judgement call rather than a repair.
 *
 * The lowest primary key in each group is kept, so nothing disappears: the
 * record stays present, once, and every copy of it becomes cancelled.
 *
 * CANCELLED RATHER THAN DELETED
 *
 * Deleting would be smaller and faster and is the wrong trade. Cancelling is
 * reversible, it leaves the audit trail intact, and cancelled samples are now
 * excluded from every count, report, chart and export, so the rows stop
 * distorting the numbers without anything being destroyed. Expiring them would
 * not have worked -- the request grids do not exclude expired samples, so they
 * would have stayed on screen, merely locked.
 *
 * Cancelling sets data_sync = 0, matching what the application's own
 * cancelSample() does, so the cancellation reaches the other side rather than
 * leaving the two instances permanently disagreeing about the same rows. On a
 * large pile that queues a large number of rows for the next sync; a slow link
 * will take a while to drain them.
 *
 * Run with --dry-run to see the counts without changing anything. The script is
 * idempotent -- already-cancelled rows are excluded -- so it is safe to
 * interrupt and re-run.
 */

const BATCH_SIZE = 500;

/**
 * Columns kept out of the content comparison.
 *
 * Every one of these changes without the record changing. last_modified_*
 * moves on each re-insert -- it is what made the pile visible, ticking along
 * with the sync interval. The *_sync flags are queue state. form_attributes
 * carries a per-sync transaction id, so leaving it in would make every copy
 * look different from every other and find nothing at all.
 */
const IGNORED_COLUMNS = [
    'last_modified_datetime',
    'last_modified_by',
    'form_attributes',
    'result_sent_to_source',
    'result_sent_to_source_datetime',
];

const REQUIRED_COLUMNS = ['sample_code', 'unique_id', 'remote_sample_code', 'facility_id', 'result_status'];

/**
 * Builds the expression the content hash is taken over.
 *
 * The column list is read from the live schema rather than written out here, so
 * an instance on an older or newer schema compares its own columns and not a
 * list that was true when this was written. NULL and the empty string have to
 * hash differently, hence the 0x01 stand-in, and the columns are joined with a
 * separator so that neighbouring values cannot run together into the same
 * string.
 */
function dupContentHashExpression(DatabaseService $db, string $table, string $primaryKey): ?string
{
    $ignored = array_merge(IGNORED_COLUMNS, [$primaryKey]);

    $columns = $db->rawQuery(
        "SELECT COLUMN_NAME
           FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
          ORDER BY ORDINAL_POSITION",
        [$table]
    ) ?: [];

    $parts = [];
    foreach ($columns as $column) {
        $name = (string) $column['COLUMN_NAME'];
        if (in_array($name, $ignored, true) || str_ends_with($name, '_sync')) {
            continue;
        }
        $parts[] = "COALESCE(CAST(`$name` AS CHAR), 0x01)";
    }

    if (count($parts) < count(REQUIRED_COLUMNS)) {
        return null;
    }

    return 'MD5(CONCAT_WS(0x02, ' . implode(', ', $parts) . '))';
}

RunOnceUtility::run(__FILE__, function (DatabaseService $db): void {
    $dryRun = in_array('--dry-run', $_SERVER['argv'] ?? [], true);

    $tableColumns = static function (string $table) use ($db): array {
        $rows = $db->rawQuery("SHOW COLUMNS FROM `$table`") ?: [];
        return array_column($rows, 'Field');
    };

    $totalCancelled = 0;
    $totalWithFacility = 0;
    $reportLines = [];

    foreach (TestsService::getActiveTests() as $testKey) {
        $table = TestsService::getTestTableName($testKey);
        $primaryKey = TestsService::getPrimaryColumn($testKey);

        if (empty($table) || empty($primaryKey)) {
            continue;
        }

        $columns = $tableColumns($table);
        if (array_diff(REQUIRED_COLUMNS, $columns) !== []) {
            // A test type that does not carry these columns cannot have been
            // through the sync path this repairs.
            continue;
        }

        $hashExpression = dupContentHashExpression($db, $table, $primaryKey);
        if ($hashExpression === null) {
            continue;
        }

        $db->rawQuery("DROP TEMPORARY TABLE IF EXISTS dup_scan");
        $db->rawQuery("DROP TEMPORARY TABLE IF EXISTS dup_group");

        /*
         * The hash is computed once, in a single pass, into a temporary table.
         * Everything after this works on that table, so the form table -- which
         * is the largest one there is -- is read once rather than once per
         * step, and the grouping never has to compare strings across two
         * tables with possibly different collations.
         *
         * A row already cancelled is left out here, which is what makes a
         * second run cheap and an interrupted run safe to resume. It also means
         * the kept row of a group whose copies were cancelled on an earlier run
         * is no longer part of a group at all, so it is never cancelled itself.
         */
        $db->rawQuery(
            "CREATE TEMPORARY TABLE dup_scan (
                 id BIGINT NOT NULL PRIMARY KEY,
                 sample_code VARCHAR(255),
                 sig CHAR(32),
                 has_facility TINYINT NOT NULL,
                 INDEX idx_sig (sample_code, sig)
             )
             SELECT `$primaryKey` AS id,
                    sample_code,
                    $hashExpression AS sig,
                    (facility_id IS NOT NULL AND facility_id <> '') AS has_facility
               FROM `$table`
              WHERE sample_code IS NOT NULL AND sample_code <> ''
                AND (unique_id IS NULL OR unique_id = '')
                AND (remote_sample_code IS NULL OR remote_sample_code = '')
                AND result_status <> " . CANCELLED
        );

        $db->rawQuery(
            "CREATE TEMPORARY TABLE dup_group (
                 sample_code VARCHAR(255),
                 sig CHAR(32),
                 copies INT NOT NULL,
                 keep_id BIGINT NOT NULL,
                 has_facility TINYINT NOT NULL,
                 INDEX idx_sig (sample_code, sig)
             )
             SELECT sample_code, sig, COUNT(*) AS copies, MIN(id) AS keep_id, MAX(has_facility) AS has_facility
               FROM dup_scan
              GROUP BY sample_code, sig
             HAVING copies > 1"
        );

        $summary = $db->rawQueryOne(
            "SELECT
                 COALESCE(SUM(CASE WHEN has_facility = 0 THEN copies - 1 END), 0) AS cancellable,
                 COALESCE(SUM(CASE WHEN has_facility = 0 THEN 1 END), 0) AS cancellable_groups,
                 COALESCE(SUM(CASE WHEN has_facility = 1 THEN copies - 1 END), 0) AS with_facility,
                 COALESCE(MAX(CASE WHEN has_facility = 0 THEN copies END), 0) AS largest
               FROM dup_group"
        ) ?: [];

        $cancellable = (int) ($summary['cancellable'] ?? 0);
        $withFacility = (int) ($summary['with_facility'] ?? 0);

        if ($cancellable === 0 && $withFacility === 0) {
            continue;
        }

        if ($cancellable > 0) {
            $reportLines[] = sprintf(
                '  %s: %s duplicate row(s) across %s record(s), largest pile %s copies',
                $testKey,
                number_format($cancellable),
                number_format((int) ($summary['cancellable_groups'] ?? 0)),
                number_format((int) ($summary['largest'] ?? 0))
            );
        }

        if ($withFacility > 0) {
            $totalWithFacility += $withFacility;
        }

        if ($dryRun) {
            $totalCancelled += $cancellable;
            continue;
        }

        /*
         * Cancelled in batches, keyed off the primary key, because every one of
         * these updates fires the audit trigger and writes a revision. Paging
         * by id rather than by OFFSET keeps each batch a range scan, and the
         * cursor moves forward even across an interrupted run.
         */
        $lastId = 0;
        while (true) {
            $batch = $db->rawQuery(
                "SELECT s.id
                   FROM dup_scan s
                   JOIN dup_group g ON g.sample_code = s.sample_code AND g.sig = s.sig
                  WHERE g.has_facility = 0
                    AND s.id <> g.keep_id
                    AND s.id > ?
                  ORDER BY s.id
                  LIMIT " . BATCH_SIZE,
                [$lastId]
            ) ?: [];

            if ($batch === []) {
                break;
            }

            $ids = array_map('intval', array_column($batch, 'id'));
            $lastId = (int) end($ids);

            $db->where($primaryKey, $ids, 'IN');
            $db->update($table, [
                'result_status' => CANCELLED,
                // The other side has these copies too, and would otherwise keep
                // counting them.
                'data_sync' => 0,
                'last_modified_datetime' => DateUtility::getCurrentDateTime(),
            ]);

            $totalCancelled += count($ids);
        }

        $db->rawQuery("DROP TEMPORARY TABLE IF EXISTS dup_scan");
        $db->rawQuery("DROP TEMPORARY TABLE IF EXISTS dup_group");
    }

    if ($totalCancelled === 0 && $totalWithFacility === 0) {
        MiscUtility::safeCliEcho("Duplicate synced requests… none found." . PHP_EOL);
        return;
    }

    $verb = $dryRun ? 'would be cancelled' : 'cancelled';
    MiscUtility::safeCliEcho(
        "Duplicate synced requests: " . number_format($totalCancelled) . " row(s) $verb." . PHP_EOL
            . implode(PHP_EOL, $reportLines) . PHP_EOL
    );

    // Same shape, but attached to a record a person could have created, so it
    // is reported for a human to rule on rather than acted on.
    if ($totalWithFacility > 0) {
        MiscUtility::safeCliEcho(
            '  ' . number_format($totalWithFacility)
                . " identical row(s) left untouched — they carry a facility, so they may be real" . PHP_EOL
        );
        LoggerUtility::logInfo(
            "cancel-duplicate-synced-requests: $totalWithFacility content-identical unidentifiable row(s) "
                . "carry a facility and were not cancelled."
        );
    }

    if ($dryRun) {
        MiscUtility::safeCliEcho("  (dry run — nothing was changed)" . PHP_EOL);
        // Nothing was repaired, so this must not be recorded as applied.
        exit(RunOnceUtility::EXIT_SKIPPED);
    }

    LoggerUtility::logInfo("cancel-duplicate-synced-requests: cancelled $totalCancelled duplicate row(s).");
});
