<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SystemException;
use App\Utilities\DateUtility;
use App\Utilities\SampleRejectionUtility;
use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\CANCELLED;
use const SAMPLE_STATUS\EXPIRED;
use const SAMPLE_STATUS\LOST_OR_MISSING;
use const SAMPLE_STATUS\ON_HOLD;
use const SAMPLE_STATUS\REORDERED_FOR_TESTING;
use const SAMPLE_STATUS\TEST_FAILED;

/**
 * Query layer for the Sample Flow page: where every sample registered in a
 * period is right now, and how long it has been there.
 *
 * A sample's stage is read off the milestone timestamps the workflow writes
 * (dispatched, received at lab, tested, approved, released), not off
 * result_status alone. Status is what a person or an import last set; the
 * timestamps are what actually happened, and the two disagree on real
 * instances (accepted rows carrying no result, for one). Status decides only
 * the terminal exits -- rejected, expired, lost, cancelled -- and the bench
 * states (failed, on hold, reordered) that put a sample back in the lab queue.
 *
 * Age is measured from the most recent milestone the sample reached, so
 * "30 days at lab" means 30 days since the lab received it, not since it was
 * collected, and a failed test counts from the day it failed.
 *
 * Table and column names come from the TestsService registry, never from a
 * request. Lab scoping is applied inside every query because the endpoint is
 * reachable directly and AJAX requests bypass the access control layer.
 */
final class SampleFlowService
{
    /** Pipeline order. A sample sits in exactly one of these or one exit. */
    public const STAGES = ['atFacility', 'inTransit', 'atLab', 'awaitingApproval', 'awaitingRelease', 'released'];

    /** Ways out of the pipeline that are not a released result. */
    public const EXITS = ['rejected', 'expired', 'lost', 'cancelled'];

    /**
     * Age buckets in days: 0-7, 8-14, 15-30, 31-60, over 60. The keys are the
     * column names the queries produce.
     */
    public const AGE_BUCKETS = [
        'b0' => [0, 7],
        'b1' => [8, 14],
        'b2' => [15, 30],
        'b3' => [31, 60],
        'b4' => [61, null],
    ];

    public const GROUPINGS = ['lab', 'facility', 'province', 'district', 'partner'];

    /** The date a sample entered the system, falling back for older rows. */
    private const REGISTERED_ON = "COALESCE(t.sample_collection_date, t.request_created_datetime)";

    /**
     * Every way a result leaves the system, on every module's table. A lab
     * that e-mails results, or whose facilities pull them over the API, never
     * prints or dispatches, so "released" has to mean any channel or those
     * labs show a permanent backlog that is not there.
     */
    private const RELEASE_CHANNELS = [
        'result_dispatched_datetime',
        'result_printed_datetime',
        'result_printed_on_sts_datetime',
        'result_printed_on_lis_datetime',
        'result_mail_datetime',
        'result_sent_to_source_datetime',
        'result_pulled_via_api_datetime',
    ];

    /** Channels only some modules have; listed per module so no query names a column its table lacks. */
    private const MODULE_RELEASE_CHANNELS = [
        'vl' => ['result_sms_sent_datetime'],
        'cd4' => ['result_sms_sent_datetime'],
        'generic-tests' => ['result_sms_sent_datetime'],
    ];

    public function __construct(
        private readonly DatabaseService $db,
        private readonly CommonService $general
    ) {
    }

    /**
     * Normalizes raw request input into the filter set every query takes.
     * Throws if the test type is not in the registry, so a request value can
     * never reach a query as a table name.
     *
     * @return array{testKey: string, startDate: string, endDate: string, labId: int}
     */
    public function resolveFilters(array $input): array
    {
        $testKey = strtolower(trim((string) ($input['testType'] ?? '')));
        if (!isset(TestsService::getTestTypes()[$testKey])) {
            throw new SystemException('Invalid test type for the sample flow');
        }
        [$startDate, $endDate] = DateUtility::convertDateRange((string) ($input['dateRange'] ?? ''));

        return [
            'testKey' => $testKey,
            'startDate' => (string) $startDate,
            'endDate' => (string) $endDate,
            'labId' => (int) ($input['labId'] ?? 0),
        ];
    }

    /**
     * The one definition of "which stage is this sample in", as a SQL CASE
     * over the alias t. Evaluated top down: exits first, then the pipeline
     * from its end backwards, so a sample is placed at the furthest point its
     * timestamps prove it reached.
     */
    public static function stageExpression(string $testType): string
    {
        $result = 't.' . TestsService::getResultColumn($testType);
        $hasResult = "($result IS NOT NULL AND TRIM($result) <> '')";
        // The sent-to-source flag is set by paths that never wrote its datetime
        // (about 2,000 rows on one instance), so the flag counts on its own.
        $released = '(' . implode(' OR ', array_merge(
            array_map(
                static fn(string $column): string => "t.$column IS NOT NULL",
                self::releaseChannels($testType)
            ),
            ["t.result_sent_to_source = 'sent'"]
        )) . ')';
        $onBench = "t.result_status IN (" . TEST_FAILED . ", " . ON_HOLD . ", " . REORDERED_FOR_TESTING . ")";

        return "CASE
            WHEN t.result_status = " . CANCELLED . " THEN 'cancelled'
            WHEN " . SampleRejectionUtility::sqlPredicate('t') . " THEN 'rejected'
            WHEN t.result_status = " . EXPIRED . " THEN 'expired'
            WHEN t.result_status = " . LOST_OR_MISSING . " THEN 'lost'
            WHEN $hasResult AND $released THEN 'released'
            WHEN $onBench THEN 'atLab'
            WHEN $hasResult AND (t.result_approved_datetime IS NOT NULL OR t.result_status = " . ACCEPTED . ") THEN 'awaitingRelease'
            WHEN t.sample_tested_datetime IS NOT NULL OR $hasResult THEN 'awaitingApproval'
            WHEN t.sample_received_at_lab_datetime IS NOT NULL THEN 'atLab'
            WHEN t.sample_dispatched_datetime IS NOT NULL OR t.sample_received_at_hub_datetime IS NOT NULL THEN 'inTransit'
            ELSE 'atFacility'
        END";
    }

    /** @return string[] column names, all present on this module's table */
    private static function releaseChannels(string $testType): array
    {
        return array_merge(self::RELEASE_CHANNELS, self::MODULE_RELEASE_CHANNELS[$testType] ?? []);
    }

    /**
     * Days since the most recent milestone the sample reached. GREATEST is
     * NULL when any argument is, so each milestone is defaulted to a date
     * older than any real one; registration is never NULL, so the result is
     * always a real milestone. The request creation date only stands in for a
     * missing collection date: it is when the row was typed in, which for a
     * sample entered from a paper backlog is long after anything happened to
     * it. A milestone dated in the future is a mis-set clock, not a negative
     * age, and reads as zero days.
     */
    public static function ageExpression(): string
    {
        $milestones = [
            self::REGISTERED_ON,
            't.sample_dispatched_datetime',
            't.sample_received_at_hub_datetime',
            't.sample_received_at_lab_datetime',
            't.sample_tested_datetime',
            't.result_approved_datetime',
            't.result_dispatched_datetime',
            't.result_printed_datetime',
        ];
        $floored = array_map(static fn(string $col): string => "COALESCE($col, '1000-01-01')", $milestones);

        return "GREATEST(DATEDIFF(NOW(), GREATEST(" . implode(', ', $floored) . ")), 0)";
    }

    /** One SUM per age bucket, over a column named age. */
    private function bucketSelects(): string
    {
        $selects = [];
        foreach (self::AGE_BUCKETS as $key => [$from, $to]) {
            $selects[] = $to === null
                ? "SUM(age >= $from) AS $key"
                : "SUM(age BETWEEN $from AND $to) AS $key";
        }
        return implode(', ', $selects);
    }

    /**
     * Every sample in range placed in its stage, with its age and the ids the
     * breakdowns group by. Everything else selects from this.
     */
    private function placedSamples(array $f): string
    {
        $table = TestsService::getTestTableName($f['testKey']);

        return "SELECT " . self::stageExpression($f['testKey']) . " AS stage,
                       " . self::ageExpression() . " AS age,
                       t.facility_id,
                       t.lab_id,
                       t.implementing_partner
                  FROM $table AS t
                " . $this->buildWhere($f);
    }

    /**
     * Counts per stage and exit, each with its age distribution.
     *
     * @return array<string, array{total: int, b0: int, b1: int, b2: int, b3: int, b4: int}>
     *         keyed by every stage and exit, zeros where nothing sits
     */
    public function getFlow(array $f): array
    {
        $rows = $this->db->rawQuery(
            "SELECT stage, COUNT(*) AS total, " . $this->bucketSelects() . "
               FROM (" . $this->placedSamples($f) . ") AS placed
              GROUP BY stage"
        ) ?: [];

        $flow = [];
        foreach (array_merge(self::STAGES, self::EXITS) as $key) {
            $flow[$key] = $this->emptyCounts();
        }
        foreach ($rows as $row) {
            $flow[(string) $row['stage']] = $this->counts($row);
        }
        return $flow;
    }

    /**
     * One stage broken down by lab, facility, province, district or partner,
     * worst first. Names come from the dimension tables; a sample with no
     * lab or partner is a row of its own rather than a row that vanishes.
     *
     * @return list<array{label: string, total: int, b0: int, b1: int, b2: int, b3: int, b4: int}>
     */
    public function getBreakdown(array $f, string $stage, string $groupBy): array
    {
        if (!in_array($stage, array_merge(self::STAGES, self::EXITS), true)) {
            throw new SystemException('Invalid stage for the sample flow');
        }
        if (!in_array($groupBy, self::GROUPINGS, true)) {
            throw new SystemException('Invalid grouping for the sample flow');
        }

        $notAssigned = $this->db->escape(_translate('Not assigned to a lab'));
        $notSpecified = $this->db->escape(_translate('Not specified'));
        $unknownFacility = $this->db->escape(_translate('Unknown facility'));

        $label = match ($groupBy) {
            'lab' => "COALESCE(l.facility_name, '$notAssigned')",
            'facility' => "COALESCE(f.facility_name, '$unknownFacility')",
            'province' => "COALESCE(NULLIF(TRIM(f.facility_state), ''), '$notSpecified')",
            'district' => "COALESCE(NULLIF(TRIM(f.facility_district), ''), '$notSpecified')",
            'partner' => "COALESCE(p.i_partner_name, '$notSpecified')",
        };

        $rows = $this->db->rawQuery(
            "SELECT $label AS label, COUNT(*) AS total, " . $this->bucketSelects() . "
               FROM (" . $this->placedSamples($f) . ") AS placed
               LEFT JOIN facility_details AS f ON f.facility_id = placed.facility_id
               LEFT JOIN facility_details AS l ON l.facility_id = placed.lab_id
               LEFT JOIN r_implementation_partners AS p ON p.i_partner_id = placed.implementing_partner
              WHERE placed.stage = '" . $this->db->escape($stage) . "'
              GROUP BY label
              ORDER BY total DESC, label ASC"
        ) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $out[] = ['label' => (string) $row['label']] + $this->counts($row);
        }
        return $out;
    }

    /** @return array{total: int, b0: int, b1: int, b2: int, b3: int, b4: int} */
    private function counts(array $row): array
    {
        $counts = ['total' => (int) $row['total']];
        foreach (array_keys(self::AGE_BUCKETS) as $key) {
            $counts[$key] = (int) ($row[$key] ?? 0);
        }
        return $counts;
    }

    /** @return array{total: int, b0: int, b1: int, b2: int, b3: int, b4: int} */
    private function emptyCounts(): array
    {
        return $this->counts(['total' => 0]);
    }

    /**
     * The period, the lab filter, and every restriction on what the reader
     * may see. Cancelled samples are NOT excluded here: they are an exit the
     * flow shows, so the stage counts and exits add up to everything
     * registered.
     */
    private function buildWhere(array $f): string
    {
        $clauses = [];

        if ($f['startDate'] !== '' && $f['endDate'] !== '') {
            $clauses[] = "DATE(" . self::REGISTERED_ON . ") BETWEEN '"
                . $this->db->escape($f['startDate']) . "' AND '"
                . $this->db->escape($f['endDate']) . "'";
        }
        if ($f['labId'] > 0) {
            $clauses[] = "t.lab_id = " . $f['labId'];
        }
        if ($labScope = $this->general->labScopeWhere('t')) {
            $clauses[] = $labScope;
        }
        if (!empty($_SESSION['facilityMap'])) {
            $clauses[] = "t.facility_id IN (" . $_SESSION['facilityMap'] . ")";
        }

        return $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);
    }
}
