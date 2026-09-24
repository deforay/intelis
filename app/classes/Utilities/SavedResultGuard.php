<?php

namespace App\Utilities;

use App\Services\DatabaseService;
use App\Services\TestsService;
use App\Services\LabRequestSyncService;

use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;

/**
 * Keeps a lab's decision on a sample when an API client posts it again.
 *
 * Clients re-post their whole dataset. What they hold for a sample is what they
 * last pulled, so it is empty until the lab's result reaches them, and a post made
 * before that sent the result, the testers, the rejection and the status as empty
 * or as "received". The save-request endpoints wrote all of it, so a re-post
 * blanked a result awaiting approval and moved the sample back to received.
 *
 * Once the lab has decided -- a result, a rejection, or any status past received
 * -- a post is taken only for what it says:
 * - an empty value never replaces a saved one in a column the lab fills in
 *   (labColumns()); request details stay the client's to correct, cleared or not;
 * - a "received" status never replaces a decided one;
 * - "not rejected" without a result does not undo a rejection.
 * A post that rejects the sample, or carries a result other than the saved one,
 * still goes through as before. Nothing changes for a sample the lab has not
 * decided on.
 */
final class SavedResultGuard
{
    private const UNDECIDED_STATUSES = [RECEIVED_AT_CLINIC, RECEIVED_AT_TESTING_LAB];

    /**
     * Columns the testing lab fills in, across the VL, EID, COVID-19, TB and
     * Custom Tests endpoints. Named one by one: patient history such as
     * last_viral_load_result is the client's, so no pattern on "result" will do.
     * labColumns() adds what the STS request pull already treats as the lab's.
     */
    public const LAB_COLUMNS = [
        'result', 'result_value_log', 'result_value_absolute', 'result_value_absolute_decimal',
        'result_value_text', 'result_value_hiv_detection', 'vl_result_category', 'xpert_mtb_result',
        'final_result', 'final_result_unit', 'final_result_interpretation', 'result_unit', 'result_type',
        'tested_by', 'sample_tested_datetime', 'lab_technician', 'lab_reception_person',
        'result_reviewed_by', 'result_reviewed_datetime', 'result_approved_by', 'result_approved_datetime',
        'is_result_authorised', 'authorized_by', 'authorized_on',
        'is_sample_rejected', 'reason_for_sample_rejection', 'rejection_on', 'is_result_finalized',
        'lab_tech_comments', 'revised_by', 'revised_on', 'reason_for_changing',
        'reason_for_result_changes', 'reason_for_test_result_changes',
        'sample_received_at_hub_datetime', 'sample_received_at_lab_datetime', 'result_dispatched_datetime',
        'lab_assigned_code',
        'vl_test_platform', 'eid_test_platform', 'testing_platform', 'test_platform',
    ];

    /**
     * The lab's columns for a test type: LAB_COLUMNS, and the columns the STS
     * request pull never takes on an update because the lab owns them
     * (LabRequestSyncService::moduleConfigs()), so the two lists cannot drift.
     *
     * @return list<string>
     */
    public static function labColumns(string $testType): array
    {
        $pulled = LabRequestSyncService::moduleConfigs()[$testType]['excludeUpdateKeys'] ?? [];
        return array_values(array_diff(
            array_unique([...self::LAB_COLUMNS, ...$pulled]),
            // Not values a client re-post blanks: bookkeeping, and the status, ruled on below.
            ['result_status', 'data_sync', 'last_modified_by', 'last_modified_datetime']
        ));
    }

    /** @param array<string, mixed> $stored the sample as saved */
    public static function hasLabDecision(array $stored): bool
    {
        if (!self::isEmpty($stored['result'] ?? null) || ($stored['is_sample_rejected'] ?? null) === 'yes') {
            return true;
        }
        $status = (int) ($stored['result_status'] ?? 0);
        return $status > 0 && !in_array($status, self::UNDECIDED_STATUSES, true);
    }

    /**
     * @param array<string, mixed> $update the columns the endpoint is about to write
     * @param array<string, mixed> $stored the sample as saved
     * @param string $testType a TestsService key; covid19, not covid-19
     * @return array{0: array<string, mixed>, 1: list<string>} the update to write, and the columns kept as saved
     */
    public static function protect(array $update, array $stored, string $testType = ''): array
    {
        if ($stored === [] || !self::hasLabDecision($stored)) {
            return [$update, []];
        }

        // A decision goes through as it always did, clearing what it clears.
        if (self::decides($update, $stored)) {
            return [$update, []];
        }
        $storedRejected = ($stored['is_sample_rejected'] ?? null) === 'yes';

        $kept = [];
        foreach (self::labColumns($testType) as $column) {
            if (
                array_key_exists($column, $update) && array_key_exists($column, $stored)
                && self::isEmpty($update[$column]) && !self::isEmpty($stored[$column])
            ) {
                $kept[] = $column;
            }
        }

        if (
            array_key_exists('result_status', $update)
            && in_array((int) $update['result_status'], self::UNDECIDED_STATUSES, true)
            && !in_array((int) ($stored['result_status'] ?? 0), [0, ...self::UNDECIDED_STATUSES], true)
        ) {
            $kept[] = 'result_status';
        }

        if ($storedRejected && ($update['is_sample_rejected'] ?? null) !== 'yes') {
            foreach (['is_sample_rejected', 'reason_for_sample_rejection', 'rejection_on'] as $column) {
                if (array_key_exists($column, $update)) {
                    $kept[] = $column;
                }
            }
        }

        $kept = array_values(array_unique($kept));
        foreach ($kept as $column) {
            unset($update[$column]);
        }
        return [$update, $kept];
    }

    /**
     * Whether a post decides on the sample: it rejects a sample the lab has not
     * rejected, or carries a result other than the saved one. The result or the
     * rejection the lab already saved, posted back as clients re-post what they
     * pulled, decides nothing.
     *
     * @param array<string, mixed> $update the columns the endpoint is about to write
     * @param array<string, mixed> $stored the sample as saved
     */
    public static function decides(array $update, array $stored): bool
    {
        if (($update['is_sample_rejected'] ?? null) === 'yes' && ($stored['is_sample_rejected'] ?? null) !== 'yes') {
            return true;
        }
        $posted = $update['result'] ?? null;
        return !self::isEmpty($posted) && trim((string) $posted) !== trim((string) ($stored['result'] ?? ''));
    }

    /**
     * Whether a client that declared RESULT_VERSION, posting on the current
     * version, leaves the lab's fields and tests as saved: the lab has decided,
     * and the post brings no new result or rejection (decides()). The endpoints
     * ask before writing the tests; guard() applies it to the form.
     *
     * @param array<string, mixed> $stored the sample as saved
     */
    public static function keepsLabFields(
        bool $declared,
        array $stored,
        mixed $postedResult,
        mixed $postedRejected
    ): bool {
        return $declared && self::hasLabDecision($stored)
            && !self::decides(['result' => $postedResult, 'is_sample_rejected' => $postedRejected], $stored);
    }

    /**
     * The sample as saved, locked for the rest of the caller's transaction, so a
     * result the lab saves meanwhile is either seen here or waits for this save.
     *
     * @return array<string, mixed>
     */
    public static function lockedSample(DatabaseService $db, string $table, string $primaryKey, mixed $id): array
    {
        return $db->rawQueryOne("SELECT * FROM `$table` WHERE `$primaryKey` = ? FOR UPDATE", [$id]) ?: [];
    }

    /**
     * Whether a post the guard has let through still replaces the saved result:
     * what the attempt history is kept for.
     *
     * @param array<string, mixed> $update
     * @param array<string, mixed> $stored
     */
    public static function replacesResult(array $update, array $stored): bool
    {
        foreach (['result', 'result_status', 'is_sample_rejected'] as $column) {
            if (array_key_exists($column, $update) && (string) $update[$column] !== (string) ($stored[$column] ?? '')) {
                return true;
            }
        }
        return false;
    }

    /**
     * What a client declares, under capabilities.supports in its post, to have
     * its result posts checked against the result it last pulled.
     */
    public const RESULT_VERSION = 'result-version';

    /** @param mixed $capabilities the post's "capabilities" value */
    public static function declaresResultVersion(mixed $capabilities): bool
    {
        $supports = is_array($capabilities) ? ($capabilities['supports'] ?? []) : [];
        return is_array($supports) && in_array(self::RESULT_VERSION, $supports, true);
    }

    /**
     * The per-test rows each test type keeps its results in, the columns of them
     * the version covers, and which of the rows are the lab's: every column but
     * keys and bookkeeping, since a save rewrites a row whole and a correction to
     * any of them is the lab's to keep. TB lists fewer because its API save writes
     * no more than those, and leaves out the rows without a lab: those are the
     * client's own (TbTestsService::saveApiTests()). VL and EID keep their
     * results on the form.
     */
    private const TEST_ROWS = [
        'covid19' => ['covid19_tests', 'covid19_id', [
            'facility_id', 'test_name', 'tested_by', 'sample_tested_datetime', 'testing_platform', 'instrument_id',
            'kit_lot_no', 'kit_expiry_date', 'result',
        ]],
        'tb' => [
            'tb_tests', 'tb_id', ['lab_id', 'test_type', 'actual_no', 'test_result', 'is_sample_rejected'],
            'lab_id IS NOT NULL',
        ],
        'generic-tests' => ['generic_test_results', 'generic_id', [
            'facility_id', 'lab_id', 'sub_test_name', 'result_type', 'test_name', 'tested_by', 'sample_tested_datetime',
            'testing_platform', 'kit_lot_no', 'kit_expiry_date', 'result', 'result_unit', 'final_result',
            'final_result_unit', 'final_result_interpretation', 'specimen_type', 'sample_received_at_lab_datetime',
            'is_sample_rejected', 'reason_for_sample_rejection', 'rejection_on', 'result_reviewed_by',
            'result_reviewed_datetime', 'result_approved_by', 'result_approved_datetime', 'revised_by', 'revised_on',
            'reason_for_result_change', 'comments',
        ]],
    ];

    /**
     * Lab columns that move without the lab deciding anything: fetch-results
     * stamps the first four when it hands a result out, after reading the version
     * it hands out with it, and printing stamps the rest. In the version, every
     * fetch or print would make the version handed out stale.
     */
    private const NOT_VERSIONED = [
        'result_dispatched_datetime', 'result_sent_to_source', 'result_sent_to_source_datetime',
        'result_pulled_via_api_datetime',
        'result_printed_datetime', 'result_printed_on_sts_datetime', 'result_printed_on_lis_datetime',
    ];

    /**
     * A short token for the lab's decision on a sample: its status, every lab
     * column the table has (labColumns(): the result, the rejection, who tested and
     * approved it and when, the sample's condition and so on), and its per-test
     * rows. It covers what keepLabDecision() keeps: a client posting on it may
     * write those columns, so a change to any of them, an approval included, makes
     * an older version stale. fetch-results hands it out with each sample; a
     * client that declared RESULT_VERSION sends it back with its post.
     *
     * Not the modified time: that moves on every save, the client's own posts
     * included, which would make every later post look stale. This moves only
     * when the decision does.
     *
     * @param array<string, mixed> $stored the sample as saved
     * @param list<array<string, mixed>> $tests its per-test rows (testRows())
     * @param string $testType a TestsService key; covid19, not covid-19
     */
    public static function resultVersion(array $stored, array $tests = [], string $testType = ''): string
    {
        $decision = [
            trim((string) ($stored['result'] ?? '')),
            ($stored['is_sample_rejected'] ?? null) === 'yes' ? 'yes' : 'no',
            trim((string) ($stored['result_status'] ?? '')),
        ];
        $versioned = array_diff(
            self::labColumns($testType),
            ['result', 'is_sample_rejected', ...self::NOT_VERSIONED]
        );
        sort($versioned);
        foreach ($versioned as $column) {
            if (array_key_exists($column, $stored)) {
                $decision[$column] = trim((string) $stored[$column]);
            }
        }
        if ($tests !== []) {
            $rows = array_map(
                static fn(array $test): string => json_encode(array_map(
                    static fn($value): string => trim((string) $value),
                    array_values($test)
                )),
                $tests
            );
            sort($rows);
            $decision[] = $rows;
        }
        return substr(hash('sha256', json_encode($decision)), 0, 16);
    }

    /**
     * A sample's per-test rows, reduced to the columns resultVersion() reads, and
     * locked with them as lockedSample() locks the sample: a plain read here would
     * see the transaction's snapshot, older than a test the lab saved since.
     *
     * @return list<array<string, mixed>>
     */
    public static function testRows(DatabaseService $db, string $testType, mixed $id): array
    {
        return self::testRowsFor($db, $testType, [$id], true)[(string) $id] ?? [];
    }

    /**
     * Whether a client that declared RESULT_VERSION posted this sample without
     * having seen the decision now saved: the lab changed its decision since the
     * version the client sent, or the lab decided and the client sent no version.
     * A version sent is compared even on an undecided sample: a retest clears the
     * result, and the result pulled before it is no less out of date for that.
     *
     * @param array<string, mixed> $stored the sample as saved
     * @param list<array<string, mixed>> $tests its per-test rows (testRows())
     * @param string $testType a TestsService key; covid19, not covid-19
     */
    public static function isStale(array $stored, mixed $postedVersion, array $tests = [], string $testType = ''): bool
    {
        if ($stored === []) {
            return false;
        }
        $postedVersion = is_string($postedVersion) ? trim($postedVersion) : '';
        if ($postedVersion === '') {
            return self::hasLabDecision($stored);
        }
        return !hash_equals(self::resultVersion($stored, $tests, $testType), $postedVersion);
    }

    /**
     * For a stale post (isStale()): the update without anything the lab owns, so
     * the saved decision stays whole and the rest of the post is still saved.
     *
     * @param array<string, mixed> $update
     * @return array{0: array<string, mixed>, 1: list<string>} the update to write, and the columns kept as saved
     */
    public static function keepLabDecision(array $update, string $testType = ''): array
    {
        $kept = array_values(array_intersect(
            [...self::labColumns($testType), 'result_status'],
            array_keys($update)
        ));
        foreach ($kept as $column) {
            unset($update[$column]);
        }
        return [$update, $kept];
    }

    /**
     * resultVersion() for each sample, by primary key. A caller that also returns
     * the samples reads them in the same transaction, so each version describes
     * the result handed out with it and not one saved in between.
     *
     * @param string $testType a TestsService key; covid19, not covid-19
     * @param list<int|string> $ids
     * @param bool $lock read the rows as saved now, locking them, and not as the
     *                   transaction's snapshot has them
     * @return array<string, string>
     */
    public static function resultVersions(DatabaseService $db, string $testType, array $ids, bool $lock = false): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn($id) => $id !== null && $id !== '')));
        if ($ids === []) {
            return [];
        }
        $table = TestsService::getTestTableName($testType);
        $primaryKey = TestsService::getPrimaryColumn($testType);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $db->rawQuery(
            "SELECT `$primaryKey` AS id, `$table`.*
                FROM `$table` WHERE `$primaryKey` IN ($placeholders)" . ($lock ? ' FOR UPDATE' : ''),
            $ids
        ) ?: [];
        $tests = self::testRowsFor($db, $testType, $ids, $lock);
        $versions = [];
        foreach ($rows as $row) {
            $versions[(string) $row['id']] = self::resultVersion($row, $tests[(string) $row['id']] ?? [], $testType);
        }
        return $versions;
    }

    /** resultVersion() of one sample as saved now, within the caller's transaction. */
    public static function currentVersion(DatabaseService $db, string $testType, mixed $id): ?string
    {
        return self::resultVersions($db, $testType, [$id], true)[(string) $id] ?? null;
    }

    /**
     * @param list<int|string> $ids
     * @return array<string, list<array<string, mixed>>> per-test rows by sample
     */
    private static function testRowsFor(DatabaseService $db, string $testType, array $ids, bool $lock = false): array
    {
        if (!isset(self::TEST_ROWS[$testType]) || $ids === []) {
            return [];
        }
        [$table, $foreignKey, $columns] = self::TEST_ROWS[$testType];
        $labRows = isset(self::TEST_ROWS[$testType][3]) ? ' AND ' . self::TEST_ROWS[$testType][3] : '';
        $select = implode(', ', array_map(static fn($column) => "`$column`", $columns));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $rows = $db->rawQuery(
            "SELECT `$foreignKey` AS sample_id, $select FROM `$table` WHERE `$foreignKey` IN ($placeholders)$labRows"
                . ($lock ? ' FOR UPDATE' : ''),
            array_values($ids)
        ) ?: [];
        $bySample = [];
        foreach ($rows as $row) {
            $sampleId = (string) $row['sample_id'];
            unset($row['sample_id']);
            $bySample[$sampleId][] = $row;
        }
        return $bySample;
    }

    /**
     * The guard for one post: keepLabDecision() when a client that declared
     * RESULT_VERSION posted stale, else protect(). Logs what was kept.
     *
     * A declared client posting on the current version, without a new decision
     * (decides()), is correcting the request: it re-posts the whole sample with
     * the lab's fields as it pulled them, or empty, or filled with its own
     * defaults. None of them is written, so the version stays as it was.
     *
     * @param array<string, mixed> $update
     * @param array<string, mixed> $stored
     * @param list<array<string, mixed>> $storedTests the sample's per-test rows (testRows())
     * @return array{0: array<string, mixed>, 1: bool} the update to write, and whether the post was stale
     */
    public static function guard(
        array $update,
        array $stored,
        string $testType,
        ?string $transactionId,
        bool $declared,
        mixed $postedVersion,
        array $storedTests = []
    ): array {
        if ($declared && self::isStale($stored, $postedVersion, $storedTests, $testType)) {
            [$update, $kept] = self::keepLabDecision($update, $testType);
            LoggerUtility::logInfo('API post was made on an older result; kept the lab\'s', [
                'test_type' => $testType,
                'unique_id' => $stored['unique_id'] ?? null,
                'sample_code' => $stored['sample_code'] ?? null,
                'columns' => $kept,
                'transaction_id' => $transactionId,
            ]);
            return [$update, true];
        }
        if (
            self::keepsLabFields($declared, $stored, $update['result'] ?? null, $update['is_sample_rejected'] ?? null)
        ) {
            [$update, $kept] = self::keepLabDecision($update, $testType);
            if ($kept !== []) {
                LoggerUtility::logInfo('API post made no new decision; kept the lab\'s fields', [
                    'test_type' => $testType,
                    'unique_id' => $stored['unique_id'] ?? null,
                    'sample_code' => $stored['sample_code'] ?? null,
                    'columns' => $kept,
                    'transaction_id' => $transactionId,
                ]);
            }
            return [$update, false];
        }
        return [self::protectAndLog($update, $stored, $testType, $transactionId), false];
    }

    /**
     * protect(), and a log entry naming what was kept, so the effect can be seen
     * in the field.
     *
     * @param array<string, mixed> $update
     * @param array<string, mixed> $stored
     * @return array<string, mixed>
     */
    public static function protectAndLog(array $update, array $stored, string $testType, ?string $transactionId): array
    {
        [$update, $kept] = self::protect($update, $stored, $testType);
        if ($kept !== []) {
            LoggerUtility::logInfo('API post kept the lab\'s saved values', [
                'test_type' => $testType,
                'unique_id' => $stored['unique_id'] ?? null,
                'sample_code' => $stored['sample_code'] ?? null,
                'columns' => $kept,
                'transaction_id' => $transactionId,
            ]);
        }
        return $update;
    }

    private static function isEmpty(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
