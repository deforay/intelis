<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use App\Utilities\SampleCountUtility;
use App\Utilities\SampleRejectionUtility;

/**
 * The Testing Target Report of every test type: each testing lab's monthly
 * counts set against the monthly test target recorded for that lab.
 *
 * The target lives on testing_labs, one per lab and test type, so a row is one
 * lab for one month. Each count is taken by its own date:
 *   received  received at the lab that month
 *   rejected  received that month and rejected
 *   tested    tested that month and not rejected
 * Cancelled samples count nowhere.
 *
 * A lab with a target appears for every month of the range, at zero when it did
 * nothing. A month with no tests is the plainest way to miss a target, so
 * leaving it out would hide the lab that needs attention most.
 *
 * Scope is the caller's: it passes the lab ids a user may see and any SQL that
 * narrows the rows further (the STS facility map), already built.
 */
final class TestingTargetReportService
{
    public const TARGET_NOT_MET = 1;
    public const TARGET_MET = 2;
    public const TARGET_ALL = 3;

    /** Tested samples against testing_labs.monthly_target. */
    public const KIND_TESTS = 'tests';
    /** Suppressed VL results against testing_labs.suppressed_monthly_target. */
    public const KIND_SUPPRESSED = 'suppressed';

    private string $kind = self::KIND_TESTS;

    public function __construct(private readonly DatabaseService $db)
    {
    }

    /**
     * The same report for another target. The suppressed kind counts, among the
     * samples tested that month, those whose result category is suppressed,
     * and holds them against the lab's suppressed monthly target. Viral load only.
     */
    public function withKind(string $kind): self
    {
        $copy = clone $this;
        $copy->kind = $kind === self::KIND_SUPPRESSED ? self::KIND_SUPPRESSED : self::KIND_TESTS;
        return $copy;
    }

    /**
     * @param string        $testType   a TestsService key: vl, eid, tb, hepatitis, cd4, covid19
     * @param string        $from       first day of the range, Y-m-d
     * @param string        $to         last day of the range, Y-m-d
     * @param list<int>     $labIds     only these labs; empty means every lab
     * @param list<string>  $scopeWhere extra conditions on the sample alias `t`
     * @param bool          $fillTargetLabs add labs with a target and no samples, at zero
     * @return list<array{labId:int, labName:string, month:string, received:int,
     *     rejected:int, tested:int, suppressed:?int, target:?int, met:?bool}>
     */
    public function rows(
        string $testType,
        string $from,
        string $to,
        array $labIds = [],
        int $targetType = self::TARGET_ALL,
        array $scopeWhere = [],
        bool $fillTargetLabs = true
    ): array {
        $table = TestsService::getTestTableName($testType);
        $suppression = $this->kind === self::KIND_SUPPRESSED;
        $months = self::months($from, $to);
        if ($months === []) {
            return [];
        }
        $labIds = array_values(array_unique(array_map('intval', $labIds)));

        // A row is a whole month, so the range widens to whole months. A range
        // ending on the 10th would otherwise judge a month on ten days of work.
        $start = $months[0] . '-01 00:00:00';
        $lastDay = (new DateTimeImmutable(end($months) . '-01'))->modify('last day of this month');
        $end = $lastDay->format('Y-m-d') . ' 23:59:59';
        $common = [SampleCountUtility::countableWhere('t'), 't.lab_id IS NOT NULL', ...$scopeWhere];
        if ($labIds !== []) {
            $common[] = 't.lab_id IN (' . implode(',', $labIds) . ')';
        }
        $where = implode(' AND ', $common);
        // A NULL is_sample_rejected makes the predicate NULL, and NOT NULL would
        // drop the row from tested: IFNULL below reads it as not rejected.
        $rejected = SampleRejectionUtility::sqlPredicate('t');

        $notRejected = "NOT IFNULL($rejected, 0)";
        $params = [$start, $end, $start, $end, $start, $end];
        $suppressedPart = '';
        if ($suppression) {
            $suppressedPart = "UNION ALL
                    SELECT t.lab_id, DATE_FORMAT(t.sample_tested_datetime, '%Y-%m'), 'suppressed'
                      FROM `$table` t
                     WHERE t.sample_tested_datetime BETWEEN ? AND ? AND $notRejected
                       AND t.vl_result_category = 'suppressed' AND $where";
            $params = [...$params, $start, $end];
        }

        $sql = "SELECT lab_id, month, SUM(kind = 'received') AS received,
                       SUM(kind = 'rejected') AS rejected, SUM(kind = 'tested') AS tested,
                       SUM(kind = 'suppressed') AS suppressed
                  FROM (
                    SELECT t.lab_id, DATE_FORMAT(t.sample_received_at_lab_datetime, '%Y-%m') AS month,
                           'received' AS kind
                      FROM `$table` t
                     WHERE t.sample_received_at_lab_datetime BETWEEN ? AND ? AND $where
                    UNION ALL
                    SELECT t.lab_id, DATE_FORMAT(t.sample_received_at_lab_datetime, '%Y-%m'), 'rejected'
                      FROM `$table` t
                     WHERE t.sample_received_at_lab_datetime BETWEEN ? AND ? AND $rejected AND $where
                    UNION ALL
                    SELECT t.lab_id, DATE_FORMAT(t.sample_tested_datetime, '%Y-%m'), 'tested'
                      FROM `$table` t
                     WHERE t.sample_tested_datetime BETWEEN ? AND ? AND $notRejected AND $where
                    $suppressedPart
                  ) counted
                 GROUP BY lab_id, month";
        $counts = [];
        foreach ($this->db->rawQuery($sql, $params) as $row) {
            $counts[(int) $row['lab_id']][$row['month']] = [
                'received' => (int) $row['received'],
                'rejected' => (int) $row['rejected'],
                'tested' => (int) $row['tested'],
                'suppressed' => $suppression ? (int) $row['suppressed'] : null,
            ];
        }

        $targets = $this->targets($testType, $labIds);
        $labs = array_keys($counts);
        if ($fillTargetLabs) {
            $labs = array_merge($labs, array_keys($targets));
        }
        $labs = array_values(array_unique($labs));
        if ($labs === []) {
            return [];
        }
        $names = $this->labNames($labs);

        $rows = [];
        foreach ($labs as $labId) {
            foreach ($months as $month) {
                $count = $counts[$labId][$month] ?? null;
                if ($count === null && !isset($targets[$labId])) {
                    continue;
                }
                $count ??= ['received' => 0, 'rejected' => 0, 'tested' => 0, 'suppressed' => $suppression ? 0 : null];
                $target = $targets[$labId] ?? null;
                $measured = $suppression ? $count['suppressed'] : $count['tested'];
                $met = $target === null ? null : $measured >= $target;
                if (
                    ($targetType === self::TARGET_NOT_MET && $met !== false)
                    || ($targetType === self::TARGET_MET && $met !== true)
                ) {
                    continue;
                }
                $rows[] = [
                    'labId' => $labId,
                    'labName' => $names[$labId] ?? (string) $labId,
                    'month' => $month,
                    ...$count,
                    'target' => $target,
                    'met' => $met,
                ];
            }
        }

        usort($rows, static fn(array $a, array $b): int
            => [$a['labName'], $a['month']] <=> [$b['labName'], $b['month']]);
        return $rows;
    }

    /**
     * How many labs missed their target in at least one completed month the
     * range touches. Each month counts whole, as in rows(). The month in progress
     * is left out: a lab half way through a month has not missed anything yet.
     *
     * @param list<int>    $labIds
     * @param list<string> $scopeWhere
     */
    public function labsBelowTarget(
        string $testType,
        string $from,
        string $to,
        array $labIds = [],
        array $scopeWhere = [],
        ?string $today = null,
        bool $fillTargetLabs = true
    ): int {
        $lastCompleted = (new DateTimeImmutable($today ?? 'today'))
            ->modify('first day of this month')
            ->modify('-1 day');
        if ($to > $lastCompleted->format('Y-m-d')) {
            $to = $lastCompleted->format('Y-m-d');
        }
        if ($to < $from) {
            return 0;
        }
        $rows = $this->rows($testType, $from, $to, $labIds, self::TARGET_NOT_MET, $scopeWhere, $fillTargetLabs);
        return count(array_unique(array_column($rows, 'labId')));
    }

    /**
     * The months a range touches, as Y-m, oldest first.
     *
     * @return list<string>
     */
    public static function months(string $from, string $to): array
    {
        $first = DateTimeImmutable::createFromFormat('!Y-m-d', $from);
        $last = DateTimeImmutable::createFromFormat('!Y-m-d', $to);
        if ($first === false || $last === false || $first > $last) {
            return [];
        }
        $months = [];
        for ($m = $first->modify('first day of this month'); $m <= $last; $m = $m->modify('+1 month')) {
            $months[] = $m->format('Y-m');
        }
        return $months;
    }

    /**
     * Each lab's monthly target for this test type. A blank, zero or
     * non-numeric target means the lab has none.
     *
     * @param list<int> $labIds
     * @return array<int, int>
     */
    private function targets(string $testType, array $labIds): array
    {
        $column = $this->kind === self::KIND_SUPPRESSED ? 'suppressed_monthly_target' : 'monthly_target';
        $sql = "SELECT facility_id, $column AS monthly_target FROM testing_labs WHERE test_type = ?";
        if ($labIds !== []) {
            $sql .= ' AND facility_id IN (' . implode(',', $labIds) . ')';
        }
        $targets = [];
        foreach ($this->db->rawQuery($sql, [$testType]) as $row) {
            $target = trim((string) $row['monthly_target']);
            if (ctype_digit($target) && (int) $target > 0) {
                $targets[(int) $row['facility_id']] = (int) $target;
            }
        }
        return $targets;
    }

    /**
     * @param list<int> $labIds
     * @return array<int, string>
     */
    private function labNames(array $labIds): array
    {
        $names = [];
        $sql = 'SELECT facility_id, facility_name FROM facility_details'
            . ' WHERE facility_id IN (' . implode(',', $labIds) . ')';
        foreach ($this->db->rawQuery($sql) as $row) {
            $names[(int) $row['facility_id']] = (string) $row['facility_name'];
        }
        return $names;
    }
}
