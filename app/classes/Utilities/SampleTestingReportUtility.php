<?php

declare(strict_types=1);

namespace App\Utilities;

use App\Services\TestsService;
use App\Services\CommonService;
use App\Services\DatabaseService;

/**
 * The Sample Testing tab of the seven clinic report pages.
 *
 * Each module used to carry its own copy of this report, and every copy drew
 * four statuses in a stack beside a total that counted all of them. A sample
 * awaiting approval, on hold, failed or referred was in the total and in no
 * bar, so the bars never reached the number printed over them. The buckets
 * below cover every status that counts, once each, so they always sum to the
 * total.
 */
final class SampleTestingReportUtility
{
    /**
     * Report bucket => the statuses it holds. Order is the stacking order.
     * Anything not listed (lost, expired, referred, or a status added later)
     * lands in "other", so a new status can never fall out of the sum.
     */
    public const BUCKETS = [
        'tested' => [\SAMPLE_STATUS\ACCEPTED],
        'awaitingApproval' => [\SAMPLE_STATUS\PENDING_APPROVAL],
        'awaitingTesting' => [
            \SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB,
            \SAMPLE_STATUS\ON_HOLD,
            \SAMPLE_STATUS\REORDERED_FOR_TESTING,
        ],
        'notAtLab' => [\SAMPLE_STATUS\RECEIVED_AT_CLINIC],
        'failed' => [\SAMPLE_STATUS\TEST_FAILED, \SAMPLE_STATUS\NO_RESULT],
        'rejected' => [\SAMPLE_STATUS\REJECTED],
    ];

    private const ALIASES = [
        'vl' => 'vl',
        'eid' => 'eid',
        'covid19' => 'covid19',
        'hepatitis' => 'hepatitis',
        'tb' => 'tb',
        'cd4' => 'cd4',
        'generic-tests' => 'generic',
    ];

    /**
     * Per-facility counts for the filters posted by the tab.
     *
     * @param array<string, mixed> $filters sanitized $_POST
     * @return array{rows: list<array<string, mixed>>, startDate: string, endDate: string}
     */
    public static function fetch(string $testType, array $filters, DatabaseService $db, CommonService $general): array
    {
        $a = self::ALIASES[$testType] ?? throw new \InvalidArgumentException("Unsupported test type: $testType");
        $table = TestsService::getTestTableName($testType);

        [$startDate, $endDate] = self::dateRange((string) ($filters['sampleCollectionDate'] ?? ''));
        $from = $startDate . ' 00:00:00';
        $to = (new \DateTimeImmutable($endDate))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

        // A bare range rather than DATE(col) BETWEEN, which hides the index.
        $where = [
            "$a.sample_collection_date >= '$from'",
            "$a.sample_collection_date < '$to'",
            SampleCountUtility::countableWhere($a),
        ];

        if (!$general->isSTSInstance()) {
            $where[] = "$a.result_status != " . \SAMPLE_STATUS\RECEIVED_AT_CLINIC;
        } elseif (!empty($_SESSION['facilityMap'])) {
            $where[] = "$a.facility_id IN (" . $db->inIntList((string) $_SESSION['facilityMap']) . ")";
        }

        if ($labScope = $general->labScopeWhere($a)) {
            $where[] = $labScope;
        }

        if ($testType === 'vl') {
            // Recency samples live in form_vl but are reported under Recency.
            $where[] = "IFNULL($a.reason_for_vl_testing, 0) != 9999";
        }

        if (trim((string) ($filters['state'] ?? '')) !== '') {
            $where[] = 'f.facility_state_id = ' . (int) $filters['state'];
        }
        if (trim((string) ($filters['district'] ?? '')) !== '') {
            $where[] = 'f.facility_district_id = ' . (int) $filters['district'];
        }
        if (!empty($filters['facilityName'])) {
            $where[] = 'f.facility_id IN (' . $db->inIntList($filters['facilityName']) . ')';
        }
        if (trim((string) ($filters['implementingPartner'] ?? '')) !== '') {
            $where[] = "$a.implementing_partner = '" . $db->escape(base64_decode((string) $filters['implementingPartner'])) . "'";
        }

        $sums = [];
        foreach (self::BUCKETS as $bucket => $statuses) {
            $sums[] = "SUM($a.result_status IN (" . implode(',', $statuses) . ")) AS `$bucket`";
        }

        $sql = "SELECT f.facility_id, f.facility_name, f.facility_state, f.facility_district,
                    COUNT(*) AS total, " . implode(', ', $sums) . "
                FROM $table AS $a
                JOIN facility_details AS f ON f.facility_id = $a.facility_id
                WHERE " . implode(' AND ', $where) . "
                GROUP BY f.facility_id
                ORDER BY total DESC, f.facility_name";

        $rows = [];
        foreach ($db->rawQuery($sql) ?: [] as $row) {
            $out = [
                'facility' => (string) $row['facility_name'],
                'state' => (string) ($row['facility_state'] ?? ''),
                'district' => (string) ($row['facility_district'] ?? ''),
                'total' => (int) $row['total'],
            ];
            $counted = 0;
            foreach (array_keys(self::BUCKETS) as $bucket) {
                $out[$bucket] = (int) $row[$bucket];
                $counted += $out[$bucket];
            }
            $out['other'] = $out['total'] - $counted;
            $rows[] = $out;
        }

        return ['rows' => $rows, 'startDate' => $startDate, 'endDate' => $endDate];
    }

    /** @return array{0: string, 1: string} Y-m-d bounds, last 7 days when unset */
    private static function dateRange(string $posted): array
    {
        [$start, $end] = $posted !== '' ? DateUtility::convertDateRange($posted) : [null, null];
        $valid = static fn($d) => is_string($d) && \DateTimeImmutable::createFromFormat('!Y-m-d', $d) !== false;
        if (!$valid($start) || !$valid($end)) {
            return [date('Y-m-d', strtotime('-7 days')), date('Y-m-d')];
        }
        return [$start, $end];
    }
}
