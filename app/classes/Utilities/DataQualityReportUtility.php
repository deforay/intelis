<?php

declare(strict_types=1);

namespace App\Utilities;

use Generator;
use App\Services\TestsService;
use App\Services\CommonService;
use App\Services\DatabaseService;

/**
 * The Data Quality tab of the clinic report pages: samples whose key fields
 * were never captured.
 *
 * Every module used to carry its own copy of a table that listed only the
 * samples missing all of the fields a user thought to pick. Nobody could see
 * how widespread a gap was, or where it came from. This reads every key field
 * at once, per facility, from one definition per test type.
 *
 * A field counts as missing when it is blank, a zero date, or one of the
 * placeholders people type when they have nothing to enter ("NA", "unknown",
 * "-"). Lab fields are only expected once a sample has reached the lab: a
 * sample still at the clinic is not missing its test date.
 */
final class DataQualityReportUtility
{
    /** Rows returned for on-screen review. The export has no limit. */
    public const SAMPLE_LIMIT = 1000;

    /**
     * Samples a field must be expected on before its absence from all of them
     * reads as a form that does not ask for it rather than a handful of gaps.
     */
    private const NOT_CAPTURED_MIN = 20;

    /** Values that record nothing. Compared lowercased and trimmed. */
    private const PLACEHOLDERS = [
        '', '0', '-', '--', '.', '?', '[]', '{}', 'na', 'n/a', 'n.a', 'n.a.', 'nil', 'none', 'null',
        'unknown', 'unreported', 'not_record', 'not recorded', 'not reported', 'not available',
    ];

    /** Statuses a sample only reaches at the lab. */
    private const AT_LAB = [
        \SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB,
        \SAMPLE_STATUS\ON_HOLD,
        \SAMPLE_STATUS\REORDERED_FOR_TESTING,
        \SAMPLE_STATUS\TEST_FAILED,
        \SAMPLE_STATUS\NO_RESULT,
        \SAMPLE_STATUS\PENDING_APPROVAL,
        \SAMPLE_STATUS\ACCEPTED,
    ];

    /** Statuses that carry a result. */
    private const WITH_RESULT = [\SAMPLE_STATUS\PENDING_APPROVAL, \SAMPLE_STATUS\ACCEPTED];

    /**
     * The checks for a test type, in display order.
     *
     * @return array<string, array{group: string, label: string, missing: string, applies: ?string}>
     *         key => group (patient|sample|lab), label, SQL true when missing,
     *         SQL true when the field is expected (null: always)
     */
    public static function checks(string $type): array
    {
        $cfg = ClinicReportUtility::type($type);
        $a = $cfg['alias'];
        $atLab = "$a.result_status IN (" . implode(',', self::AT_LAB) . ")";
        $withResult = "$a.result_status IN (" . implode(',', self::WITH_RESULT) . ")";

        $checks = [];
        $add = static function (string $key, string $group, string $label, string $missing, ?string $applies = null) use (&$checks): void {
            $checks[$key] = ['group' => $group, 'label' => $label, 'missing' => $missing, 'applies' => $applies];
        };

        $add('patientId', 'patient', $type === 'eid' ? _translate('Child ID') : _translate('Patient ID'), self::blankText("$a.{$cfg['id']}"));
        $nameColumns = array_filter([$cfg['first'], $cfg['middle'], $cfg['last']]);
        $add(
            'patientName',
            'patient',
            $type === 'eid' ? _translate("Child's Name") : _translate('Patient Name'),
            '(' . implode(' AND ', array_map(fn($c) => self::blankText("$a.$c"), $nameColumns)) . ')'
        );
        $add('sex', 'patient', _translate('Sex'), self::blankText("$a.{$cfg['sex']}"));
        // Zero is a real age for an infant, so ages are only missing when empty.
        $add(
            'age',
            'patient',
            _translate('Age or Date of Birth'),
            '(' . self::blankDate("$a.{$cfg['dob']}") . ' AND '
                . implode(' AND ', array_map(fn($c) => "($a.$c IS NULL OR TRIM($a.$c) = '')", $cfg['ages'])) . ')'
        );

        $add('facility', 'sample', _translate('Facility'), "IFNULL($a.facility_id, 0) = 0");
        $add('collectionDate', 'sample', _translate('Sample Collection Date'), self::blankDate("$a.sample_collection_date"));
        $add('sampleType', 'sample', _translate('Sample Type'), self::blankText("$a.specimen_type"));
        if ($cfg['reason']) {
            $add('testReason', 'sample', _translate('Reason for Testing'), self::blankText("$a.{$cfg['reason']}"));
        }
        if ($type === 'vl') {
            $add('regimen', 'sample', _translate('Current Regimen'), self::blankText("$a.current_regimen"));
        }

        $add('lab', 'lab', _translate('Testing Lab'), "IFNULL($a.lab_id, 0) = 0", $atLab);
        $add('receivedDate', 'lab', _translate('Sample Received at Lab Date'), self::blankDate("$a.sample_received_at_lab_datetime"), $atLab);
        $add('testedDate', 'lab', _translate('Sample Test Date'), self::blankDate("$a.sample_tested_datetime"), $withResult);
        $add('result', 'lab', _translate('Result'), SampleStatusUtility::noResultSql($type, $a), $withResult);

        return $checks;
    }

    /**
     * Counts per check and per facility.
     *
     * A field missing from every sample that should have it is reported as
     * not captured and left out of the incomplete count: that is a form that
     * does not ask for it, and counting it would mark every record incomplete.
     *
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public static function summary(string $type, array $filters, DatabaseService $db, CommonService $general): array
    {
        $checks = self::checks($type);
        [$from, $where, $startDate, $endDate] = self::baseQuery($type, $filters, $db, $general);
        $a = ClinicReportUtility::type($type)['alias'];

        $columns = [];
        foreach ($checks as $key => $check) {
            $columns[] = 'SUM(' . self::flag($check) . ") AS `m_$key`";
            if ($check['applies'] !== null) {
                $columns[] = "SUM({$check['applies']}) AS `a_$key`";
            }
        }
        $columns[] = 'SUM(' . self::anyFlag($checks) . ') AS incomplete';

        $sql = "SELECT IFNULL($a.facility_id, 0) AS facility_id,
                    MAX(f.facility_name) AS facility_name, MAX(f.facility_state) AS facility_state,
                    MAX(f.facility_district) AS facility_district,
                    COUNT(*) AS total, " . implode(', ', $columns) . "
                $from
                WHERE " . implode(' AND ', $where) . "
                GROUP BY IFNULL($a.facility_id, 0)";
        $rows = $db->rawQuery($sql) ?: [];

        $totals = ['total' => 0];
        foreach ($checks as $key => $check) {
            $totals["m_$key"] = 0;
            $totals["a_$key"] = 0;
        }
        foreach ($rows as $row) {
            $totals['total'] += (int) $row['total'];
            foreach ($checks as $key => $check) {
                $totals["m_$key"] += (int) $row["m_$key"];
                $totals["a_$key"] += (int) ($check['applies'] === null ? $row['total'] : $row["a_$key"]);
            }
        }

        $notCaptured = [];
        foreach ($checks as $key => $check) {
            if ($totals["a_$key"] >= self::NOT_CAPTURED_MIN && $totals["m_$key"] === $totals["a_$key"]) {
                $notCaptured[$key] = true;
            }
        }

        // Recount without the fields no sample carries.
        if ($notCaptured !== [] && $rows !== []) {
            $counted = array_diff_key($checks, $notCaptured);
            $recount = [];
            if ($counted !== []) {
                foreach ($db->rawQuery(
                    "SELECT IFNULL($a.facility_id, 0) AS facility_id, SUM(" . self::anyFlag($counted) . ") AS incomplete
                    $from
                    WHERE " . implode(' AND ', $where) . "
                    GROUP BY IFNULL($a.facility_id, 0)"
                ) ?: [] as $row) {
                    $recount[(int) $row['facility_id']] = (int) $row['incomplete'];
                }
            }
            foreach ($rows as &$row) {
                $row['incomplete'] = $recount[(int) $row['facility_id']] ?? 0;
            }
            unset($row);
        }

        $facilities = [];
        $incomplete = 0;
        foreach ($rows as $row) {
            $missing = $applies = [];
            foreach ($checks as $key => $check) {
                if ((int) $row["m_$key"] > 0) {
                    $missing[$key] = (int) $row["m_$key"];
                }
                if ($check['applies'] !== null) {
                    $applies[$key] = (int) $row["a_$key"];
                }
            }
            $incomplete += (int) $row['incomplete'];
            $facilities[] = [
                'id' => (int) $row['facility_id'],
                'name' => (string) ($row['facility_name'] ?? ''),
                'state' => (string) ($row['facility_state'] ?? ''),
                'district' => (string) ($row['facility_district'] ?? ''),
                'total' => (int) $row['total'],
                'incomplete' => (int) $row['incomplete'],
                'missing' => $missing,
                'applies' => $applies,
            ];
        }
        usort($facilities, fn($x, $y) => [$y['incomplete'], $y['total']] <=> [$x['incomplete'], $x['total']]);

        $checkRows = [];
        foreach ($checks as $key => $check) {
            $checkRows[] = [
                'key' => $key,
                'group' => $check['group'],
                'label' => $check['label'],
                'missing' => $totals["m_$key"],
                'applies' => $totals["a_$key"],
                'notCaptured' => isset($notCaptured[$key]),
            ];
        }

        return [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'total' => $totals['total'],
            'incomplete' => $incomplete,
            'checks' => $checkRows,
            'facilities' => $facilities,
        ];
    }

    /**
     * The samples behind a count: missing one field, or any field, optionally
     * at one facility. Newest first.
     *
     * @param array<string, mixed> $filters also carries `check`, `facilityId`
     *        and `ignore` (comma-separated checks left out of "any field")
     * @return Generator<array<string, mixed>>
     */
    public static function samples(string $type, array $filters, DatabaseService $db, CommonService $general, ?int $limit = self::SAMPLE_LIMIT): Generator
    {
        $cfg = ClinicReportUtility::type($type);
        $a = $cfg['alias'];
        $checks = self::checks($type);
        [$from, $where] = self::baseQuery($type, $filters, $db, $general);

        $check = (string) ($filters['check'] ?? '');
        $ignore = array_filter(explode(',', (string) ($filters['ignore'] ?? '')));
        $counted = array_diff_key($checks, array_flip($ignore));
        $where[] = isset($checks[$check]) ? self::flag($checks[$check]) : self::anyFlag($counted ?: $checks);

        if (isset($filters['facilityId']) && $filters['facilityId'] !== '') {
            $where[] = "IFNULL($a.facility_id, 0) = " . (int) $filters['facilityId'];
        }

        $flags = [];
        foreach ($checks as $key => $c) {
            $flags[] = self::flag($c) . " AS `m_$key`";
        }

        $sql = "SELECT $a.{$cfg['pk']} AS pk, $a.sample_code, $a.remote_sample_code,
                    $a.{$cfg['id']} AS patient_id, $a.is_encrypted,
                    $a.sample_collection_date, $a.request_created_datetime,
                    f.facility_name, ss.status_name, " . implode(', ', $flags) . "
                $from
                LEFT JOIN r_sample_status AS ss ON ss.status_id = $a.result_status
                WHERE " . implode(' AND ', $where) . "
                ORDER BY COALESCE($a.sample_collection_date, $a.request_created_datetime) DESC"
            . ($limit !== null ? ' LIMIT ' . (int) $limit : '');

        $key = (string) $general->getGlobalConfig('key');
        $canEdit = ClinicReportUtility::canEdit($type);
        foreach ($db->rawQueryGenerator($sql) as $row) {
            $patientId = (string) ($row['patient_id'] ?? '');
            if ($patientId !== '' && ($row['is_encrypted'] ?? '') === 'yes') {
                $patientId = (string) $general->crypto('decrypt', $patientId, $key);
            }
            $missing = [];
            foreach (array_keys($checks) as $k) {
                if ((int) $row["m_$k"] === 1 && !in_array($k, $ignore, true)) {
                    $missing[] = $k;
                }
            }
            $collected = self::dateOnly($row['sample_collection_date']);
            yield [
                'sampleCode' => (string) ($row['sample_code'] ?: $row['remote_sample_code']),
                'patientId' => $patientId,
                'collected' => $collected,
                'requested' => $collected === '' ? self::dateOnly($row['request_created_datetime']) : '',
                'facility' => (string) ($row['facility_name'] ?? ''),
                'status' => (string) ($row['status_name'] ?? ''),
                'missing' => $missing,
                'editUrl' => $canEdit ? ClinicReportUtility::editUrl($type, $row['pk']) : null,
            ];
        }
    }

    /**
     * FROM clause and WHERE clauses shared by the summary and the listings.
     *
     * The period is read from the collection date, falling back to when the
     * request was created: filtering on collection date alone would hide every
     * sample that is missing one, which is exactly what this report looks for.
     *
     * @return array{0: string, 1: list<string>, 2: string, 3: string}
     */
    private static function baseQuery(string $type, array $filters, DatabaseService $db, CommonService $general): array
    {
        $a = ClinicReportUtility::type($type)['alias'];
        $table = TestsService::getTestTableName($type);

        [$startDate, $endDate] = ClinicReportUtility::dateRange((string) ($filters['sampleCollectionDate'] ?? ''), '-29 days');
        [$start, $end] = ClinicReportUtility::datetimeBounds($startDate, $endDate);

        $where = array_merge(
            [
                "(($a.sample_collection_date >= '$start' AND $a.sample_collection_date < '$end')
                  OR (" . self::blankDate("$a.sample_collection_date") . "
                      AND $a.request_created_datetime >= '$start' AND $a.request_created_datetime < '$end'))",
            ],
            ClinicReportUtility::scopeClauses($type, $db, $general),
            ClinicReportUtility::filterClauses($type, $filters, $db)
        );

        $from = "FROM $table AS $a LEFT JOIN facility_details AS f ON f.facility_id = $a.facility_id";

        return [$from, $where, $startDate, $endDate];
    }

    /** @param array{missing: string, applies: ?string} $check */
    private static function flag(array $check): string
    {
        return $check['applies'] === null
            ? "IFNULL({$check['missing']}, 0)"
            : "IFNULL(({$check['applies']}) AND ({$check['missing']}), 0)";
    }

    /** @param array<string, array{missing: string, applies: ?string}> $checks */
    private static function anyFlag(array $checks): string
    {
        return '(' . implode(' OR ', array_map(self::flag(...), $checks)) . ')';
    }

    private static function blankText(string $column): string
    {
        $values = implode(',', array_map(fn($v) => "'" . addslashes($v) . "'", self::PLACEHOLDERS));
        return "($column IS NULL OR LOWER(TRIM($column)) IN ($values))";
    }

    private static function blankDate(string $column): string
    {
        return "($column IS NULL OR $column < '1900-01-02')";
    }

    private static function dateOnly(?string $value): string
    {
        $value = (string) $value;
        return ($value === '' || $value < '1900-01-02') ? '' : substr($value, 0, 10);
    }
}
