<?php

namespace App\Utilities;

use App\Services\DatabaseService;
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
 * A post that rejects the sample, or carries a result, still goes through as
 * before. Nothing changes for a sample the lab has not decided on.
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
        'is_sample_rejected', 'reason_for_sample_rejection', 'rejection_on',
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

        // A rejection, or a result, is the poster deciding: it goes through as it
        // always did, clearing what it clears.
        if (($update['is_sample_rejected'] ?? null) === 'yes' || !self::isEmpty($update['result'] ?? null)) {
            return [$update, []];
        }

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

        if (($stored['is_sample_rejected'] ?? null) === 'yes') {
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
