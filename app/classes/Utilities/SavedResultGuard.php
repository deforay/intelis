<?php

namespace App\Utilities;

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
 * - an empty value never replaces a saved one;
 * - a "received" status never replaces a decided one;
 * - "not rejected" without a result does not undo a rejection.
 * A post that rejects the sample, or carries a result, still goes through as
 * before. Nothing changes for a sample the lab has not decided on.
 */
final class SavedResultGuard
{
    private const UNDECIDED_STATUSES = [RECEIVED_AT_CLINIC, RECEIVED_AT_TESTING_LAB];

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
     * @return array{0: array<string, mixed>, 1: list<string>} the update to write, and the columns kept as saved
     */
    public static function protect(array $update, array $stored): array
    {
        if ($stored === [] || !self::hasLabDecision($stored)) {
            return [$update, []];
        }

        // Rejecting is an explicit decision, and clears the result on purpose.
        if (($update['is_sample_rejected'] ?? null) === 'yes') {
            return [$update, []];
        }
        $postsResult = !self::isEmpty($update['result'] ?? null);

        $kept = [];
        foreach ($update as $column => $value) {
            if (array_key_exists($column, $stored) && self::isEmpty($value) && !self::isEmpty($stored[$column])) {
                $kept[] = $column;
            }
        }

        if (
            !$postsResult
            && array_key_exists('result_status', $update)
            && in_array((int) $update['result_status'], self::UNDECIDED_STATUSES, true)
            && !in_array((int) ($stored['result_status'] ?? 0), [0, ...self::UNDECIDED_STATUSES], true)
        ) {
            $kept[] = 'result_status';
        }

        if (!$postsResult && ($stored['is_sample_rejected'] ?? null) === 'yes') {
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
     * protect(), and a log entry naming what was kept, so the effect can be seen
     * in the field.
     *
     * @param array<string, mixed> $update
     * @param array<string, mixed> $stored
     * @return array<string, mixed>
     */
    public static function protectAndLog(array $update, array $stored, string $testType, ?string $transactionId): array
    {
        [$update, $kept] = self::protect($update, $stored);
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
