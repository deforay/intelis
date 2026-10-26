<?php

declare(strict_types=1);

namespace App\Utilities;

use App\Services\TestsService;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\NO_RESULT;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\TEST_FAILED;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;

/**
 * What a sample status asserts about the row carrying it.
 *
 * Accepted is what the printing, emailing, dispatch and reporting queries all
 * read as "this sample has a result", so a row that holds the status without
 * holding a result drops silently out of every one of them -- it is neither
 * pending work anyone can see nor finished work anyone can send. Several write
 * paths could produce exactly that, each in its own way, which is why the rule
 * is stated once here and applied at each of them rather than restated.
 *
 * DataIssuesService reads the same definition to find the rows already in that
 * state, so what the scan reports and what the write paths refuse cannot drift
 * apart.
 */
final class SampleStatusUtility
{
    /**
     * The statuses a lab can ask the results sender for by name, as in
     * `composer send-results -- 400 --status=rejected,accepted` (composer drops
     * options that are not after `--`). Received at Clinic is left out: the
     * sender never sends it, so asking for it would select nothing.
     */
    public const SENDABLE_STATUS_NAMES = [
        'on-hold' => \SAMPLE_STATUS\ON_HOLD,
        'lost' => \SAMPLE_STATUS\LOST_OR_MISSING,
        'reordered' => \SAMPLE_STATUS\REORDERED_FOR_TESTING,
        'rejected' => REJECTED,
        'failed' => TEST_FAILED,
        'received' => RECEIVED_AT_TESTING_LAB,
        'accepted' => ACCEPTED,
        'pending-approval' => PENDING_APPROVAL,
        'expired' => \SAMPLE_STATUS\EXPIRED,
        'no-result' => NO_RESULT,
        'cancelled' => \SAMPLE_STATUS\CANCELLED,
        'referred' => \SAMPLE_STATUS\REFERRED,
    ];

    /**
     * Status values for a comma-separated list of names from SENDABLE_STATUS_NAMES.
     *
     * An unknown name is refused rather than dropped: a typo that quietly sent
     * nothing would read as "nothing to resend".
     *
     * @return list<int>
     * @throws \InvalidArgumentException
     */
    public static function parseSendableStatuses(string $names): array
    {
        $statuses = [];
        foreach (explode(',', strtolower($names)) as $name) {
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            if (!isset(self::SENDABLE_STATUS_NAMES[$name])) {
                throw new \InvalidArgumentException(sprintf(
                    _translate('Unknown status "%s". Use one or more of: %s'),
                    $name,
                    implode(', ', array_keys(self::SENDABLE_STATUS_NAMES))
                ));
            }
            $statuses[] = self::SENDABLE_STATUS_NAMES[$name];
        }
        if ($statuses === []) {
            throw new \InvalidArgumentException(_translate('No status given.'));
        }
        return array_values(array_unique($statuses));
    }

    /**
     * A status filter as a request or a queued command carries it -- absent, a
     * comma-separated string, or a list of names -- as one validated string, or
     * null for no filter.
     *
     * Anything else is refused. Dropped instead, a malformed filter left the
     * resend unfiltered, sending every status while the command said otherwise.
     *
     * @throws \InvalidArgumentException
     */
    public static function sendableStatusFilter(mixed $filter): ?string
    {
        if ($filter === null || $filter === '' || $filter === []) {
            return null;
        }
        if (is_array($filter)) {
            foreach ($filter as $name) {
                if (!is_string($name)) {
                    throw new \InvalidArgumentException(_translate('Statuses must be given by name.'));
                }
            }
            $filter = implode(',', $filter);
        }
        if (!is_string($filter)) {
            throw new \InvalidArgumentException(_translate('Statuses must be given by name.'));
        }

        $names = array_keys(array_intersect(self::SENDABLE_STATUS_NAMES, self::parseSendableStatuses($filter)));
        return implode(',', $names);
    }

    /**
     * True for a status that claims the test is finished, and so cannot be set
     * on a sample with nothing to show for it.
     *
     * Awaiting Approval is deliberately not here. It is the status a result
     * page writes on its way to approval, and the paths that set it already
     * require a result, so adding it would only refuse work nobody does.
     */
    public static function assertsAResult(int|string|null $status): bool
    {
        return (int) $status === ACCEPTED;
    }

    /**
     * Every column that can hold this test type's result.
     *
     * Hepatitis is the one type with more than one: the result page writes the
     * interpretation to `result`, while the analyzer import writes only the
     * viral load count for whichever of HBV or HCV was ordered. A row resulted
     * by the instrument therefore carries a count and no `result`, and reading
     * `result` alone would call it empty.
     *
     * @return string[]
     */
    public static function resultColumns(string $testType): array
    {
        if ($testType === 'hepatitis') {
            return ['result', 'hbv_vl_count', 'hcv_vl_count'];
        }

        return [TestsService::getResultColumn($testType)];
    }

    /** True when the row holds a result in any column that can carry one. */
    public static function rowHasResult(string $testType, array $row): bool
    {
        foreach (self::resultColumns($testType) as $column) {
            if (trim((string) ($row[$column] ?? '')) !== '') {
                return true;
            }
        }

        return false;
    }

    /** SQL that is true for rows holding a result. */
    public static function hasResultSql(string $testType, string $alias = 'vl'): string
    {
        $clauses = [];
        foreach (self::resultColumns($testType) as $column) {
            $clauses[] = "($alias.$column IS NOT NULL AND TRIM($alias.$column) <> '')";
        }

        return '(' . implode(' OR ', $clauses) . ')';
    }

    /** SQL that is true for rows holding no result at all. */
    public static function noResultSql(string $testType, string $alias = 'vl'): string
    {
        $clauses = [];
        foreach (self::resultColumns($testType) as $column) {
            $clauses[] = "($alias.$column IS NULL OR TRIM($alias.$column) = '')";
        }

        return '(' . implode(' AND ', $clauses) . ')';
    }

    /**
     * The colour a status is drawn in on the sample status pies.
     *
     * Keyed by the status constants: the per-page arrays this replaces were
     * numbered by an older status list, so Awaiting Approval was drawn in the
     * colour meant for "Sent to Lab" and Referred had no colour at all.
     */
    public static function chartColor(int $status): string
    {
        return match ($status) {
            \SAMPLE_STATUS\ON_HOLD => '#dda41b',
            \SAMPLE_STATUS\LOST_OR_MISSING => '#9a1c64',
            \SAMPLE_STATUS\REORDERED_FOR_TESTING => '#8c8c8c',
            \SAMPLE_STATUS\REJECTED => '#d8424d',
            \SAMPLE_STATUS\TEST_FAILED => '#000000',
            \SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB => '#e2d44b',
            ACCEPTED => '#639e11',
            \SAMPLE_STATUS\PENDING_APPROVAL => '#7f22e8',
            \SAMPLE_STATUS\RECEIVED_AT_CLINIC => '#4bc0d9',
            \SAMPLE_STATUS\EXPIRED => '#f0ad4e',
            \SAMPLE_STATUS\NO_RESULT => '#b5651d',
            \SAMPLE_STATUS\CANCELLED => '#20c997',
            \SAMPLE_STATUS\REFERRED => '#1f77b4',
            default => '#999999',
        };
    }

    /**
     * Statuses that record the lab's decision on a sample: a result of some kind,
     * a failed or empty run, or a rejection. A sample with none of these behind it
     * is still waiting to be tested.
     */
    public const DECIDED_STATUSES = [REJECTED, TEST_FAILED, ACCEPTED, PENDING_APPROVAL, NO_RESULT];

    /**
     * The columns that sign off a result: who reviewed and approved it, and when.
     * The same names on every test type's form table.
     */
    public const SIGNOFF_COLUMNS = [
        'result_reviewed_by',
        'result_reviewed_datetime',
        'result_approved_by',
        'result_approved_datetime',
    ];

    public static function isDecided(int|string|null $status): bool
    {
        return in_array((int) $status, self::DECIDED_STATUSES, true);
    }

    /**
     * The status a new request starts in. Only a collection-site user on an STS
     * starts a sample at the clinic; every other role, and every LIS, works the
     * lab side. The rule the request add pages and insertSample() apply.
     */
    public static function initialStatus(bool $isSts, ?string $accessType): int
    {
        return ($isSts && $accessType === 'collection-site') ? RECEIVED_AT_CLINIC : RECEIVED_AT_TESTING_LAB;
    }

    /**
     * The status of a sample that is waiting for a result: received at the lab
     * once the lab has a receipt date for it, otherwise where a new request on
     * this instance would start.
     */
    public static function waitingForTestingStatus(?string $receivedAtLab, bool $isSts, ?string $accessType): int
    {
        if (self::isRealDate($receivedAtLab)) {
            return RECEIVED_AT_TESTING_LAB;
        }
        return self::initialStatus($isSts, $accessType);
    }

    /**
     * Whether a save leaves the sample with no result and not rejected, judged on the
     * row as it will be: the stored row with the columns the save writes laid over it.
     *
     * @param array<string, mixed> $stored the row before the save
     * @param array<string, mixed> $written the columns the save writes
     */
    public static function leavesNoResult(string $testType, array $stored, array $written, bool $rejected): bool
    {
        return !$rejected && !self::rowHasResult($testType, array_merge($stored, $written));
    }

    /**
     * The status a web save writes when it leaves a decided sample with no result
     * and not rejected, or null when no reset is needed.
     *
     * Two saves do that: a result cleared on purpose, and a rejection undone with no
     * result entered. The old status would otherwise stand over a sample with
     * nothing to show for it. A sample never decided keeps its status, so a request
     * still on hold, referred or cancelled does not move.
     *
     * A rejection is undone only by a form that sent the rejection field. A form
     * without one, such as a collection site's request edit, says nothing about it.
     *
     * @param array<string, mixed> $stored the row before the save
     * @param array<string, mixed> $written the columns the save writes
     * @param bool $rejectionPosted whether the form sent the rejection field at all
     */
    public static function waitingStatusAfterSave(
        string $testType,
        array $stored,
        array $written,
        bool $rejected,
        bool $rejectionPosted,
        bool $isSts,
        ?string $accessType
    ): ?int {
        if (!self::leavesNoResult($testType, $stored, $written, $rejected)) {
            return null;
        }
        if (!self::saveUndoesDecision($stored, $rejectionPosted)) {
            return null;
        }
        $receivedAtLab = array_key_exists('sample_received_at_lab_datetime', $written)
            ? $written['sample_received_at_lab_datetime']
            : ($stored['sample_received_at_lab_datetime'] ?? null);

        return self::waitingForTestingStatus($receivedAtLab, $isSts, $accessType);
    }

    /**
     * Whether a save undoes a rejection and leaves a result: the sample then awaits
     * approval of that result. The request edits start from the status the form
     * posts back, which for a rejected sample is Rejected, so without this the
     * sample stayed Rejected with a result and the flag set to no.
     *
     * @param array<string, mixed> $stored the row before the save
     * @param array<string, mixed> $written the columns the save writes
     */
    public static function undoesRejectionWithResult(
        string $testType,
        array $stored,
        array $written,
        bool $rejected,
        bool $rejectionPosted
    ): bool {
        $wasRejected = ($stored['is_sample_rejected'] ?? null) === 'yes'
            || (int) ($stored['result_status'] ?? 0) === REJECTED;
        return !$rejected
            && $rejectionPosted
            && $wasRejected
            && !self::leavesNoResult($testType, $stored, $written, false);
    }

    /**
     * Whether the stored row records a decision that a save leaving no result and no
     * rejection takes back. A rejection counts only when the form sent the
     * rejection field.
     *
     * @param array<string, mixed> $stored the row before the save
     */
    public static function saveUndoesDecision(array $stored, bool $rejectionPosted): bool
    {
        $wasRejected = ($stored['is_sample_rejected'] ?? null) === 'yes'
            || (int) ($stored['result_status'] ?? 0) === REJECTED;
        if ($wasRejected) {
            return $rejectionPosted;
        }
        return self::isDecided($stored['result_status'] ?? null);
    }

    /**
     * Sets the sign-off columns in a form table update to NULL, so an approval
     * cannot outlive the result or rejection it approved.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function withoutSignoff(array $data): array
    {
        foreach (self::SIGNOFF_COLUMNS as $column) {
            $data[$column] = null;
        }
        return $data;
    }

    /**
     * A sample the form says is not rejected keeps no rejection reason or date.
     * A hidden reason select can still post the old reason after a rejection is
     * undone, and the result PDFs print any reason they find. Left alone when
     * the form does not ask the question.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public static function withoutStaleRejection(array $data, array $post): array
    {
        if (array_key_exists('isSampleRejected', $post) && $post['isSampleRejected'] !== 'yes') {
            $data['reason_for_sample_rejection'] = null;
            $data['rejection_on'] = null;
        }
        return $data;
    }

    private static function isRealDate(?string $value): bool
    {
        $value = trim((string) $value);
        return $value !== '' && !str_starts_with($value, '0000-00-00');
    }
}
