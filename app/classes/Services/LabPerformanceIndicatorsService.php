<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\SystemException;
use App\Utilities\DateUtility;

/**
 * Query layer for the Lab Performance Indicators report.
 *
 * Works on whichever test module the caller selects; table and column names
 * come from the TestsService registry, never from request input, so a raw
 * request value can never reach a table or column position. Lab scoping is
 * applied inside every query because the report endpoints are reachable
 * directly (AJAX requests bypass the access control layer).
 *
 * Every indicator is measured on the date that belongs to the event it counts,
 * and the selected date range is applied to that date. Samples registered uses
 * the registration date, samples tested uses the test date, rejections use the
 * registration date of the sample rejected. A sample registered in November is
 * therefore counted in November no matter when it was tested, and a test run in
 * November is counted in November no matter when its sample arrived. Nothing on
 * this report silently re-buckets a figure onto a different date.
 */
final class LabPerformanceIndicatorsService
{
    public const GROUPINGS = ['monthly', 'quarterly', 'yearly'];

    /**
     * How a result reached the system. A row is classified by the first rule
     * that matches, so a result imported and later corrected by hand counts as
     * manual -- the markers describe its final state:
     *   manual      manual_result_entry = 'yes'
     *   interface   import_machine_file_name = 'interface' (set by InterfacingService)
     *   file-import any other non-empty import_machine_file_name
     * Rows that match none stay 'unclassified': results recorded before these
     * markers existed. Real installs carry a lot of them (~29% on one large
     * instance), so they are reported as their own bucket rather than being
     * silently folded into any of the above.
     */
    private const ENTRY_MANUAL = "t.manual_result_entry = 'yes'";
    private const ENTRY_INTERFACE = "COALESCE(t.manual_result_entry, 'no') != 'yes' AND t.import_machine_file_name = 'interface'";
    private const ENTRY_FILE_IMPORT = "COALESCE(t.manual_result_entry, 'no') != 'yes' AND COALESCE(t.import_machine_file_name, '') NOT IN ('', 'interface')";

    /**
     * reason_for_sample_rejection is an int FK on most modules but a
     * varchar on these two, sometimes holding free text instead of an id.
     */
    private const TEXT_REJECTION_REASON_MODULES = ['covid19', 'hepatitis'];

    /** The date a sample entered the system, falling back for older rows. */
    private const REGISTERED_ON = "COALESCE(t.sample_collection_date, t.request_created_datetime)";

    /** The date a test was performed on the sample. */
    private const TESTED_ON = "t.sample_tested_datetime";

    public function __construct(
        private readonly DatabaseService $db,
        private readonly CommonService $general
    ) {
    }

    /**
     * Normalizes raw request input into the filter set every query method
     * takes. Throws if the test type is not in the registry.
     *
     * @return array{testKey: string, grouping: string, startDate: string,
     *               endDate: string, labId: int, genericTestTypeId: int}
     */
    public function resolveFilters(array $input): array
    {
        // 'all' drives the cross-test overview; every other value must be a
        // registry key so it can never reach a query as a table name.
        $testKey = strtolower(trim((string) ($input['testType'] ?? '')));
        if ($testKey !== 'all' && !isset(TestsService::getTestTypes()[$testKey])) {
            throw new SystemException('Invalid test type for the indicators report');
        }

        $grouping = (string) ($input['grouping'] ?? '');
        if (!in_array($grouping, self::GROUPINGS, true)) {
            $grouping = 'monthly';
        }

        [$startDate, $endDate] = DateUtility::convertDateRange((string) ($input['dateRange'] ?? ''));

        return [
            'testKey' => $testKey,
            'grouping' => $grouping,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'labId' => (int) ($input['labId'] ?? 0),
            'genericTestTypeId' => (int) ($input['genericTestTypeId'] ?? 0),
        ];
    }

    /** Test module keys the all-tests overview iterates. */
    public function overviewTestKeys(): array
    {
        // recency is an alias onto form_vl; including it would count VL twice
        return array_values(array_diff(TestsService::getActiveTests(), ['recency']));
    }

    /**
     * Registered and rejected are bucketed on the registration date; tested,
     * resulted and the entry-mode split on the test date. A period row is the
     * union of both, so "March" shows the samples registered in March next to
     * the tests run in March, each on its own footing.
     */
    public function getVolume(array $f): array
    {
        $table = TestsService::getTestTableName($f['testKey']);
        $resulted = $this->resultedPredicate($f['testKey']);
        $labName = "COALESCE(f.facility_name, '" . $this->db->escape(_translate('Not assigned to a lab')) . "')";
        $join = "LEFT JOIN facility_details AS f ON f.facility_id = t.lab_id";

        $registeredRows = $this->db->rawQuery(
            "SELECT " . $this->periodExpr($f, self::REGISTERED_ON) . " AS period,
                    $labName AS lab_name,
                    COUNT(*) AS registered
               FROM $table AS t $join
              " . $this->buildWhere($f, dateClause: $this->registeredInRange($f)) . "
              GROUP BY period, lab_name"
        ) ?: [];

        $testedRows = $this->db->rawQuery(
            "SELECT " . $this->periodExpr($f, self::TESTED_ON) . " AS period,
                    $labName AS lab_name,
                    COUNT(*) AS sample_tested,
                    SUM($resulted) AS resulted,
                    SUM(NOT ($resulted)) AS tested_pending,
                    SUM(($resulted) AND (" . self::ENTRY_MANUAL . ")) AS manual_entry,
                    SUM(($resulted) AND (" . self::ENTRY_INTERFACE . ")) AS interfaced,
                    SUM(($resulted) AND (" . self::ENTRY_FILE_IMPORT . ")) AS file_imported
               FROM $table AS t $join
              " . $this->buildWhere($f, dateClause: $this->testedInRange($f)) . "
              GROUP BY period, lab_name"
        ) ?: [];

        $merged = [];
        $blank = [
            'registered' => 0, 'sampleTested' => 0, 'resulted' => 0, 'testedPending' => 0,
            'manual' => 0, 'interface' => 0, 'fileImport' => 0,
        ];
        foreach ($registeredRows as $row) {
            $key = $row['period'] . '|' . $row['lab_name'];
            $merged[$key] = array_merge(
                ['period' => (string) $row['period'], 'lab' => (string) $row['lab_name']],
                $blank,
                ['registered' => (int) $row['registered']]
            );
        }
        foreach ($testedRows as $row) {
            $key = $row['period'] . '|' . $row['lab_name'];
            $merged[$key] ??= array_merge(
                ['period' => (string) $row['period'], 'lab' => (string) $row['lab_name']],
                $blank
            );
            $merged[$key]['sampleTested'] = (int) $row['sample_tested'];
            $merged[$key]['resulted'] = (int) $row['resulted'];
            $merged[$key]['testedPending'] = (int) $row['tested_pending'];
            $merged[$key]['manual'] = (int) $row['manual_entry'];
            $merged[$key]['interface'] = (int) $row['interfaced'];
            $merged[$key]['fileImport'] = (int) $row['file_imported'];
        }

        $rows = array_values($merged);
        foreach ($rows as &$row) {
            $classified = $row['manual'] + $row['interface'] + $row['fileImport'];
            $row['unclassified'] = max(0, $row['resulted'] - $classified);
        }
        unset($row);

        usort($rows, static fn(array $a, array $b): int => [$a['period'], $a['lab']] <=> [$b['period'], $b['lab']]);
        return $rows;
    }

    /** Failures are a property of tests, so this counts tests run in range. */
    public function getFailure(array $f): array
    {
        $table = TestsService::getTestTableName($f['testKey']);
        $resulted = $this->resultedPredicate($f['testKey']);
        $period = $this->periodExpr($f, self::TESTED_ON);
        $where = $this->buildWhere($f, dateClause: $this->testedInRange($f));

        // Denominator = every test with an outcome: a usable result or a
        // recorded test failure. A test still awaiting approval has not
        // resolved either way, so it stays out of the rate.
        $sql = "SELECT $period AS period,
                       COALESCE(f.facility_name, '" . $this->db->escape(_translate('Not assigned to a lab')) . "') AS lab_name,
                       SUM(($resulted) OR t.result_status = " . \SAMPLE_STATUS\TEST_FAILED . ") AS tested,
                       SUM(t.result_status = " . \SAMPLE_STATUS\TEST_FAILED . ") AS failed
                  FROM $table AS t
                  LEFT JOIN facility_details AS f ON f.facility_id = t.lab_id
                 $where
                 GROUP BY period, lab_name
                 ORDER BY period ASC, lab_name ASC";

        $rows = [];
        foreach ($this->db->rawQuery($sql) ?: [] as $row) {
            $tested = (int) $row['tested'];
            $failed = (int) $row['failed'];
            $rows[] = [
                'period' => (string) $row['period'],
                'lab' => (string) $row['lab_name'],
                'tested' => $tested,
                'failed' => $failed,
                'failureRate' => $tested > 0 ? round($failed * 100 / $tested, 2) : null,
            ];
        }
        return $rows;
    }

    /**
     * Failure reasons exist per sample only on VL (reason_for_failure).
     * Other modules express failure solely through result_status, so this
     * returns an empty list for them and the UI hides the breakdown.
     */
    public function getFailureReasons(array $f): array
    {
        if (!in_array($f['testKey'], ['vl', 'recency'], true)) {
            return [];
        }
        $where = $this->buildWhere(
            $f,
            extra: ' t.result_status = ' . \SAMPLE_STATUS\TEST_FAILED . ' ',
            dateClause: $this->testedInRange($f)
        );

        $sql = "SELECT COALESCE(r.failure_reason, '" . $this->db->escape(_translate('Not specified')) . "') AS reason,
                       COUNT(*) AS total
                  FROM form_vl AS t
                  LEFT JOIN r_vl_test_failure_reasons AS r ON r.failure_id = t.reason_for_failure
                 $where
                 GROUP BY reason
                 ORDER BY total DESC";

        return array_map(
            static fn(array $row): array => ['reason' => (string) $row['reason'], 'total' => (int) $row['total']],
            $this->db->rawQuery($sql) ?: []
        );
    }

    /**
     * Rejection happens at registration, before any test, so both sides of the
     * rate are bucketed on the registration date.
     */
    public function getRejection(array $f): array
    {
        $table = TestsService::getTestTableName($f['testKey']);
        $period = $this->periodExpr($f, self::REGISTERED_ON);
        $where = $this->buildWhere($f, dateClause: $this->registeredInRange($f));

        $sql = "SELECT $period AS period,
                       COALESCE(f.facility_name, '" . $this->db->escape(_translate('Not assigned to a lab')) . "') AS lab_name,
                       COUNT(*) AS received,
                       SUM(" . $this->rejectedPredicate() . ") AS rejected
                  FROM $table AS t
                  LEFT JOIN facility_details AS f ON f.facility_id = t.lab_id
                 $where
                 GROUP BY period, lab_name
                 ORDER BY period ASC, lab_name ASC";

        $rows = [];
        foreach ($this->db->rawQuery($sql) ?: [] as $row) {
            $received = (int) $row['received'];
            $rejected = (int) $row['rejected'];
            $rows[] = [
                'period' => (string) $row['period'],
                'lab' => (string) $row['lab_name'],
                'received' => $received,
                'rejected' => $rejected,
                'rejectionRate' => $received > 0 ? round($rejected * 100 / $received, 2) : null,
            ];
        }
        return $rows;
    }

    public function getRejectionReasons(array $f): array
    {
        $table = TestsService::getTestTableName($f['testKey']);
        $reasonTable = $this->rejectionReasonTable($f['testKey']);
        $where = $this->buildWhere(
            $f,
            extra: $this->rejectedPredicate(),
            dateClause: $this->registeredInRange($f)
        );

        if (in_array($f['testKey'], self::TEXT_REJECTION_REASON_MODULES, true)) {
            // The varchar column may hold a master-table id or free text; join
            // on the id when it is numeric and fall back to showing the text.
            $join = "ON t.reason_for_sample_rejection REGEXP '^[0-9]+$'
                     AND r.rejection_reason_id = CAST(t.reason_for_sample_rejection AS UNSIGNED)";
            $fallback = "NULLIF(CAST(t.reason_for_sample_rejection AS CHAR), '')";
        } else {
            $join = "ON r.rejection_reason_id = t.reason_for_sample_rejection";
            // A reason id with no master row (deleted, or synced from an
            // instance with different ids) is still worth grouping; the #
            // marks it as an id rather than a reason name.
            $fallback = "CONCAT('#', t.reason_for_sample_rejection)";
        }

        $sql = "SELECT COALESCE(r.rejection_reason_name,
                                $fallback,
                                '" . $this->db->escape(_translate('Not specified')) . "') AS reason,
                       COUNT(*) AS total
                  FROM $table AS t
                  LEFT JOIN $reasonTable AS r $join
                 $where
                 GROUP BY reason
                 ORDER BY total DESC";

        return array_map(
            static fn(array $row): array => ['reason' => (string) $row['reason'], 'total' => (int) $row['total']],
            $this->db->rawQuery($sql) ?: []
        );
    }

    /**
     * Turnaround is a property of a completed test, so a period holds the
     * tests performed in it and reports how long each leg took to get there.
     */
    public function getTat(array $f): array
    {
        $table = TestsService::getTestTableName($f['testKey']);
        $period = $this->periodExpr($f, self::TESTED_ON);
        $where = $this->buildWhere($f, dateClause: $this->testedInRange($f));

        // A result is "released" when dispatched, or failing that, printed.
        $released = "COALESCE(t.result_dispatched_datetime, t.result_printed_datetime)";

        $stages = [
            'collectionToReceipt' => ['t.sample_collection_date', 't.sample_received_at_lab_datetime'],
            'receiptToTested' => ['t.sample_received_at_lab_datetime', self::TESTED_ON],
            'testedToReleased' => [self::TESTED_ON, $released],
            'collectionToReleased' => ['t.sample_collection_date', $released],
        ];

        $selects = [];
        foreach ($stages as $name => [$from, $to]) {
            // Guard each stage against missing milestones and backwards data
            // entry; AVG ignores the NULLs the CASE leaves behind.
            $valid = "$from IS NOT NULL AND $to IS NOT NULL AND $to >= $from";
            $selects[] = "AVG(CASE WHEN $valid THEN TIMESTAMPDIFF(HOUR, $from, $to) / 24.0 END) AS {$name}_days";
            $selects[] = "SUM($valid) AS {$name}_n";
        }

        $sql = "SELECT $period AS period, COUNT(*) AS samples, " . implode(', ', $selects) . "
                  FROM $table AS t
                 $where
                 GROUP BY period
                 ORDER BY period ASC";

        $rows = [];
        foreach ($this->db->rawQuery($sql) ?: [] as $row) {
            $out = ['period' => (string) $row['period'], 'samples' => (int) $row['samples']];
            foreach (array_keys($stages) as $name) {
                $out[$name] = isset($row["{$name}_days"]) ? round((float) $row["{$name}_days"], 1) : null;
                $out["{$name}N"] = (int) $row["{$name}_n"];
            }
            $rows[] = $out;
        }
        return $rows;
    }

    /**
     * Patients with more than one resulted test in range, with their first
     * and last result. Identity is the module's patient id, falling back to
     * system_patient_code; rows with neither cannot be matched across visits,
     * which is why the summary reports identifier coverage alongside.
     *
     * @return array{summary: array, total: int, rows: array}
     */
    public function getRepeatPatients(array $f, int $offset = 0, int $limit = 25, string $search = ''): array
    {
        $table = TestsService::getTestTableName($f['testKey']);
        $resultCol = 't.' . TestsService::getResultColumn($f['testKey']);
        $resulted = $this->resultedPredicate($f['testKey']);
        $basis = self::TESTED_ON;
        $patientKey = "COALESCE(NULLIF(t." . TestsService::getPatientIdColumn($f['testKey']) . ", ''), t.system_patient_code)";

        $where = $this->buildWhere($f, extra: "($resulted)", dateClause: $this->testedInRange($f));

        $coverage = $this->db->rawQueryOne(
            "SELECT COUNT(*) AS resulted_samples, SUM($patientKey IS NOT NULL) AS with_identifier
               FROM $table AS t $where"
        ) ?: [];

        $groupedWhere = $where . " AND $patientKey IS NOT NULL";
        if ($search !== '') {
            $groupedWhere .= " AND $patientKey LIKE '%" . $this->db->escape($search) . "%'";
        }

        // For the generic module the first/last values come from the summary
        // result on the request row; per-sub-test values live in
        // generic_test_results and are out of scope for this overview.
        $grouped = "SELECT $patientKey AS patient_key,
                           COUNT(*) AS total_tests,
                           MIN(DATE($basis)) AS first_date,
                           MAX(DATE($basis)) AS last_date,
                           SUBSTRING_INDEX(GROUP_CONCAT($resultCol ORDER BY $basis ASC SEPARATOR '|~|'), '|~|', 1) AS first_result,
                           SUBSTRING_INDEX(GROUP_CONCAT($resultCol ORDER BY $basis DESC SEPARATOR '|~|'), '|~|', 1) AS last_result,
                           COUNT(DISTINCT $resultCol) AS distinct_results
                      FROM $table AS t
                     $groupedWhere
                     GROUP BY patient_key
                    HAVING COUNT(*) > 1";

        $summaryRow = $this->db->rawQueryOne(
            "SELECT COUNT(*) AS repeat_patients, SUM(x.distinct_results > 1) AS changed_patients
               FROM ($grouped) AS x"
        ) ?: [];

        $rows = $this->db->rawQuery(
            "SELECT x.* FROM ($grouped) AS x
              ORDER BY x.total_tests DESC, x.last_date DESC
              LIMIT " . max(0, $offset) . ", " . max(1, $limit)
        ) ?: [];

        $resultedSamples = (int) ($coverage['resulted_samples'] ?? 0);
        $withIdentifier = (int) ($coverage['with_identifier'] ?? 0);

        return [
            'summary' => [
                'resultedSamples' => $resultedSamples,
                'withIdentifier' => $withIdentifier,
                'identifierCoverage' => $resultedSamples > 0
                    ? round($withIdentifier * 100 / $resultedSamples, 1) : null,
                'repeatPatients' => (int) ($summaryRow['repeat_patients'] ?? 0),
                'changedPatients' => (int) ($summaryRow['changed_patients'] ?? 0),
            ],
            'total' => (int) ($summaryRow['repeat_patients'] ?? 0),
            'rows' => array_map(static fn(array $row): array => [
                'patient' => (string) $row['patient_key'],
                'tests' => (int) $row['total_tests'],
                'firstDate' => (string) $row['first_date'],
                'firstResult' => (string) ($row['first_result'] ?? ''),
                'lastDate' => (string) $row['last_date'],
                'lastResult' => (string) ($row['last_result'] ?? ''),
                'changed' => ((int) $row['distinct_results']) > 1,
            ], $rows),
        ];
    }

    /**
     * One summary row per active test at test level: every module, and each
     * custom test individually rather than one combined Other Tests lump.
     */
    public function getOverview(array $f): array
    {
        $rows = [];
        foreach ($this->overviewTestKeys() as $testKey) {
            $moduleFilters = array_merge($f, ['testKey' => $testKey, 'genericTestTypeId' => 0]);
            $table = TestsService::getTestTableName($testKey);
            $where = $this->buildWhere($moduleFilters, dateClause: $this->anyEventInRange($moduleFilters));
            $aggregates = $this->overviewAggregates($moduleFilters);

            if ($testKey === 'generic-tests') {
                $grouped = $this->db->rawQuery(
                    "SELECT tt.test_standard_name, t.test_type, $aggregates
                       FROM $table AS t
                       LEFT JOIN r_test_types AS tt ON tt.test_type_id = t.test_type
                      $where
                      GROUP BY t.test_type, tt.test_standard_name
                      ORDER BY tt.test_standard_name ASC"
                ) ?: [];
                foreach ($grouped as $row) {
                    $rows[] = $this->shapeOverviewRow(
                        $testKey,
                        (string) ($row['test_standard_name'] ?? TestsService::getTestName($testKey)),
                        $row,
                        (int) ($row['test_type'] ?? 0)
                    );
                }
                continue;
            }

            $row = $this->db->rawQueryOne("SELECT $aggregates FROM $table AS t $where") ?: [];
            $rows[] = $this->shapeOverviewRow($testKey, TestsService::getTestName($testKey), $row);
        }
        return $rows;
    }

    /**
     * Each figure carries its own date test inside the SUM, so one pass over
     * the rows produces registration-dated and test-dated counts that are
     * each correct for the selected range.
     */
    private function overviewAggregates(array $f): string
    {
        $resulted = $this->resultedPredicate($f['testKey']);
        $registered = $this->registeredInRange($f);
        $tested = $this->testedInRange($f);

        return "SUM($registered) AS registered,
                SUM($tested) AS sample_tested,
                SUM(($tested) AND ($resulted)) AS resulted,
                SUM(($tested) AND NOT ($resulted)) AS tested_pending,
                SUM(($tested) AND (($resulted) AND (" . self::ENTRY_MANUAL . "))) AS manual_entry,
                SUM(($tested) AND (($resulted) AND (" . self::ENTRY_INTERFACE . "))) AS interfaced,
                SUM(($tested) AND (($resulted) AND (" . self::ENTRY_FILE_IMPORT . "))) AS file_imported,
                SUM(($tested) AND (($resulted) OR t.result_status = " . \SAMPLE_STATUS\TEST_FAILED . ")) AS outcomes,
                SUM(($tested) AND t.result_status = " . \SAMPLE_STATUS\TEST_FAILED . ") AS failed,
                SUM(($registered) AND " . $this->rejectedPredicate() . ") AS rejected";
    }

    private function shapeOverviewRow(string $testKey, string $testName, array $row, int $genericTestTypeId = 0): array
    {
        $registered = (int) ($row['registered'] ?? 0);
        $sampleTested = (int) ($row['sample_tested'] ?? 0);
        $testedPending = (int) ($row['tested_pending'] ?? 0);
        $resultedCount = (int) ($row['resulted'] ?? 0);
        $classified = (int) ($row['manual_entry'] ?? 0) + (int) ($row['interfaced'] ?? 0) + (int) ($row['file_imported'] ?? 0);
        $outcomes = (int) ($row['outcomes'] ?? 0);
        $failed = (int) ($row['failed'] ?? 0);
        $rejected = (int) ($row['rejected'] ?? 0);

        return [
            'testKey' => $testKey,
            'genericTestTypeId' => $genericTestTypeId,
            'testName' => $testName,
            'registered' => $registered,
            'sampleTested' => $sampleTested,
            'resulted' => $resultedCount,
            'testedPending' => $testedPending,
            'manual' => (int) ($row['manual_entry'] ?? 0),
            'interface' => (int) ($row['interfaced'] ?? 0),
            'fileImport' => (int) ($row['file_imported'] ?? 0),
            'unclassified' => max(0, $resultedCount - $classified),
            'outcomes' => $outcomes,
            'failed' => $failed,
            'failureRate' => $outcomes > 0 ? round($failed * 100 / $outcomes, 2) : null,
            'rejected' => $rejected,
            'rejectionRate' => $registered > 0 ? round($rejected * 100 / $registered, 2) : null,
        ];
    }

    /** Everything at once, for the machine-readable full export. */
    public function getAllIndicators(array $f): array
    {
        return [
            'filters' => [
                'testType' => $f['testKey'],
                'testName' => TestsService::getTestName($f['testKey']),
                'grouping' => $f['grouping'],
                'startDate' => $f['startDate'],
                'endDate' => $f['endDate'],
            ],
            'turnaroundTime' => $this->getTat($f),
            'testingVolume' => $this->getVolume($f),
            'failureRates' => $this->getFailure($f),
            'failureReasons' => $this->getFailureReasons($f),
            'rejectionRates' => $this->getRejection($f),
            'rejectionReasons' => $this->getRejectionReasons($f),
            'repeatPatients' => $this->getRepeatPatients($f, 0, 1000),
        ];
    }

    /** The sample was registered inside the selected range. */
    private function registeredInRange(array $f): string
    {
        return $this->inRange($f, self::REGISTERED_ON);
    }

    /** A test was performed on the sample inside the selected range. */
    private function testedInRange(array $f): string
    {
        return $this->inRange($f, self::TESTED_ON);
    }

    /**
     * Either event landed in range. Only used to bound the overview scan: the
     * per-figure date tests inside the SUMs still decide what each number
     * counts, this just keeps the query off rows that can match neither.
     */
    private function anyEventInRange(array $f): string
    {
        if (!$this->hasRange($f)) {
            return '';
        }
        return '(' . $this->registeredInRange($f) . ' OR ' . $this->testedInRange($f) . ')';
    }

    private function hasRange(array $f): bool
    {
        return !empty($f['startDate']) && !empty($f['endDate']);
    }

    /**
     * With no range selected the test degrades to "the event happened at all",
     * never to "true", so a figure counting an event is never inflated by rows
     * where the event never occurred.
     */
    private function inRange(array $f, string $column): string
    {
        if (!$this->hasRange($f)) {
            return "($column IS NOT NULL)";
        }
        return "($column IS NOT NULL AND DATE($column) BETWEEN '" . $this->db->escape($f['startDate']) . "'
                    AND '" . $this->db->escape($f['endDate']) . "')";
    }

    private function periodExpr(array $f, string $basis): string
    {
        return match ($f['grouping']) {
            'yearly' => "YEAR($basis)",
            'quarterly' => "CONCAT(YEAR($basis), '-Q', QUARTER($basis))",
            default => "DATE_FORMAT($basis, '%Y-%m')",
        };
    }

    private function resultedPredicate(string $testKey): string
    {
        $resultCol = 't.' . TestsService::getResultColumn($testKey);
        $resulted = "($resultCol IS NOT NULL AND $resultCol != '')";
        if ($testKey === 'generic-tests') {
            // Multi-test custom results live in the child table, with nothing
            // on the request row itself.
            $resulted = "($resulted OR EXISTS (SELECT 1 FROM generic_test_results AS gtr
                            WHERE gtr.generic_id = t.sample_id
                              AND COALESCE(gtr.final_result, gtr.result, '') != ''))";
        }
        return $resulted;
    }

    private function rejectedPredicate(): string
    {
        return "(t.is_sample_rejected = 'yes' OR t.result_status = " . \SAMPLE_STATUS\REJECTED . ")";
    }

    private function rejectionReasonTable(string $testKey): string
    {
        return match ($testKey) {
            'vl', 'recency' => 'r_vl_sample_rejection_reasons',
            'cd4' => 'r_cd4_sample_rejection_reasons',
            'eid' => 'r_eid_sample_rejection_reasons',
            'covid19' => 'r_covid19_sample_rejection_reasons',
            'hepatitis' => 'r_hepatitis_sample_rejection_reasons',
            'tb' => 'r_tb_sample_rejection_reasons',
            'generic-tests' => 'r_generic_sample_rejection_reasons',
            default => throw new SystemException('Invalid test type key'),
        };
    }

    /**
     * Shared WHERE for every indicator: lab filter, lab scope, facility map,
     * plus the date predicate the calling indicator measures itself on.
     */
    private function buildWhere(array $f, string $extra = '', string $dateClause = ''): string
    {
        $clauses = [];

        if ($dateClause !== '') {
            $clauses[] = $dateClause;
        }
        if ($f['labId'] > 0) {
            $clauses[] = " t.lab_id = " . $f['labId'] . " ";
        }
        if ($f['testKey'] === 'generic-tests' && $f['genericTestTypeId'] > 0) {
            $clauses[] = " t.test_type = " . $f['genericTestTypeId'] . " ";
        }
        if ($labScope = $this->general->labScopeWhere('t')) {
            $clauses[] = $labScope;
        }
        if (!empty($_SESSION['facilityMap'])) {
            $clauses[] = " t.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
        }
        if ($extra !== '') {
            $clauses[] = $extra;
        }

        return empty($clauses) ? '' : ' WHERE ' . implode(' AND ', $clauses);
    }
}
