<?php

declare(strict_types=1);

namespace App\Utilities;

use App\Services\TestsService;
use App\Services\CommonService;
use App\Services\DatabaseService;

/**
 * One patient's samples across every test type, for the Patient Test History
 * tab of the clinic report pages.
 *
 * A patient is the identifier the request form records -- ART number for VL
 * and CD4, child ID for EID, patient ID elsewhere -- matched exactly across the
 * form tables. Names are deliberately not used to join records: two patients
 * sharing a name would be merged into one clinical history. Names only help
 * find the identifier.
 *
 * Rows stored with encrypted identifiers cannot be matched in SQL (the cipher
 * uses a random nonce), so they are not found by either the search or the
 * timeline.
 */
final class PatientTimelineUtility
{
    public const SEARCH_LIMIT = 30;
    private const TIMELINE_LIMIT = 500;

    /** Advanced HIV disease threshold (WHO), cells/mm3. */
    public const CD4_LOW = 200;

    /**
     * Per test type: the alias, the patient columns and how to read a result.
     * Only types whose clinic report the user can open are ever queried.
     */
    private const TYPES = [
        'vl' => [
            'alias' => 'vl', 'id' => 'patient_art_no', 'pk' => 'vl_sample_id',
            'first' => 'patient_first_name', 'middle' => 'patient_middle_name', 'last' => 'patient_last_name',
            'dob' => 'patient_dob', 'sex' => 'patient_gender', 'age' => 'patient_age_in_years',
            'sampleTypeTable' => 'r_vl_sample_type', 'sampleTypeId' => 'sample_id', 'sampleTypeName' => 'sample_name',
            'page' => '/vl/program-management/vl-clinic-reports.php',
            'print' => '/vl/results/generate-result-pdf.php',
        ],
        'eid' => [
            'alias' => 'eid', 'id' => 'child_id', 'pk' => 'eid_id',
            'first' => 'child_name', 'middle' => null, 'last' => 'child_surname',
            'dob' => 'child_dob', 'sex' => 'child_gender', 'age' => null,
            'sampleTypeTable' => 'r_eid_sample_type', 'sampleTypeId' => 'sample_id', 'sampleTypeName' => 'sample_name',
            'page' => '/eid/management/eid-clinic-reports.php',
            'print' => '/eid/results/generate-result-pdf.php',
        ],
        'covid19' => [
            'alias' => 'covid19', 'id' => 'patient_id', 'pk' => 'covid19_id',
            'first' => 'patient_name', 'middle' => null, 'last' => 'patient_surname',
            'dob' => 'patient_dob', 'sex' => 'patient_gender', 'age' => 'patient_age',
            'sampleTypeTable' => 'r_covid19_sample_type', 'sampleTypeId' => 'sample_id', 'sampleTypeName' => 'sample_name',
            'page' => '/covid-19/management/covid-19-clinic-report.php',
            'print' => '/covid-19/results/generate-result-pdf.php',
        ],
        'hepatitis' => [
            'alias' => 'hepatitis', 'id' => 'patient_id', 'pk' => 'hepatitis_id',
            'first' => 'patient_name', 'middle' => null, 'last' => 'patient_surname',
            'dob' => 'patient_dob', 'sex' => 'patient_gender', 'age' => 'patient_age',
            'sampleTypeTable' => 'r_hepatitis_sample_type', 'sampleTypeId' => 'sample_id', 'sampleTypeName' => 'sample_name',
            'page' => '/hepatitis/management/hepatitis-clinic-report.php',
            'print' => '/hepatitis/results/generate-result-pdf.php',
        ],
        'tb' => [
            'alias' => 'tb', 'id' => 'patient_id', 'pk' => 'tb_id',
            'first' => 'patient_name', 'middle' => null, 'last' => 'patient_surname',
            'dob' => 'patient_dob', 'sex' => 'patient_gender', 'age' => 'patient_age',
            'sampleTypeTable' => 'r_tb_sample_type', 'sampleTypeId' => 'sample_id', 'sampleTypeName' => 'sample_name',
            'page' => '/tb/management/tb-clinic-report.php',
            'print' => '/tb/results/generate-result-pdf.php',
        ],
        'cd4' => [
            'alias' => 'cd4', 'id' => 'patient_art_no', 'pk' => 'cd4_id',
            'first' => 'patient_first_name', 'middle' => 'patient_middle_name', 'last' => 'patient_last_name',
            'dob' => 'patient_dob', 'sex' => 'patient_gender', 'age' => 'patient_age_in_years',
            'sampleTypeTable' => 'r_cd4_sample_types', 'sampleTypeId' => 'sample_id', 'sampleTypeName' => 'sample_name',
            'page' => '/cd4/management/cd4-clinic-report.php',
            'print' => '/cd4/results/generate-result-pdf.php',
        ],
        'generic-tests' => [
            'alias' => 'generic', 'id' => 'patient_id', 'pk' => 'sample_id',
            'first' => 'patient_first_name', 'middle' => 'patient_middle_name', 'last' => 'patient_last_name',
            'dob' => 'patient_dob', 'sex' => 'patient_gender', 'age' => 'patient_age_in_years',
            'sampleTypeTable' => 'r_generic_sample_types', 'sampleTypeId' => 'sample_type_id', 'sampleTypeName' => 'sample_type_name',
            'page' => '/generic-tests/program-management/generic-tests-clinic-report.php',
            'print' => '/generic-tests/results/generate-result-pdf.php',
        ],
    ];

    /** Test types this user may see, in display order. @return list<string> */
    public static function visibleTypes(): array
    {
        $types = [];
        foreach (self::TYPES as $type => $cfg) {
            if (TestsService::isTestActive($type) && _isAllowed($cfg['page'])) {
                $types[] = $type;
            }
        }
        return $types;
    }

    /**
     * Patients whose identifier, or whose names, start with what was typed.
     * Prefix matches only: a leading wildcard scans the whole table.
     *
     * @return list<array<string, mixed>>
     */
    public static function search(string $term, DatabaseService $db, CommonService $general): array
    {
        $term = trim(preg_replace('/\s+/', ' ', $term) ?? '');
        if (mb_strlen($term) < 2) {
            return [];
        }
        // escapeLike() also escapes quotes, so these are safe inside '...'.
        $prefix = $db->escapeLike($term) . '%';

        $patients = [];
        foreach (self::visibleTypes() as $type) {
            $cfg = self::TYPES[$type];
            $a = $cfg['alias'];
            $nameCols = array_filter([$cfg['first'], $cfg['middle'], $cfg['last']]);

            $tokenClauses = [];
            foreach (explode(' ', $term) as $token) {
                $like = $db->escapeLike($token) . '%';
                $tokenClauses[] = '(' . implode(' OR ', array_map(fn($c) => "$a.$c LIKE '$like'", $nameCols)) . ')';
            }
            $match = "($a.{$cfg['id']} LIKE '$prefix' OR (" . implode(' AND ', $tokenClauses) . '))';

            $sql = "SELECT TRIM($a.{$cfg['id']}) AS patient_id,
                        MAX($a.{$cfg['first']}) AS first_name,
                        " . ($cfg['middle'] ? "MAX($a.{$cfg['middle']})" : "''") . " AS middle_name,
                        MAX($a.{$cfg['last']}) AS last_name,
                        MAX($a.{$cfg['dob']}) AS dob,
                        MAX($a.{$cfg['sex']}) AS sex,
                        COUNT(*) AS samples,
                        MAX($a.sample_collection_date) AS last_collected
                    FROM " . TestsService::getTestTableName($type) . " AS $a
                    WHERE $match
                        AND $a.{$cfg['id']} IS NOT NULL AND TRIM($a.{$cfg['id']}) != ''
                        AND IFNULL($a.is_encrypted, 'no') != 'yes'
                        " . self::scopeWhere($type, $general) . "
                    GROUP BY TRIM($a.{$cfg['id']})
                    ORDER BY last_collected DESC
                    LIMIT " . self::SEARCH_LIMIT;

            foreach ($db->rawQuery($sql) ?: [] as $row) {
                $key = mb_strtolower((string) $row['patient_id']);
                $p = $patients[$key] ?? [
                    'id' => (string) $row['patient_id'],
                    'name' => '',
                    'sex' => '',
                    'dob' => '',
                    'tests' => [],
                    'samples' => 0,
                    'lastCollected' => '',
                ];
                $p['name'] = $p['name'] ?: self::fullName($row['first_name'], $row['middle_name'], $row['last_name']);
                $p['sex'] = $p['sex'] ?: self::sexLabel($row['sex']);
                $p['dob'] = $p['dob'] ?: self::dateOnly($row['dob']);
                $p['tests'][$type] = (int) $row['samples'];
                $p['samples'] += (int) $row['samples'];
                $p['lastCollected'] = max($p['lastCollected'], (string) $row['last_collected']);
                $patients[$key] = $p;
            }
        }

        usort($patients, fn($x, $y) => strcmp($y['lastCollected'], $x['lastCollected']));
        return array_slice(array_values($patients), 0, self::SEARCH_LIMIT);
    }

    /**
     * Every sample carrying this identifier, oldest first, with the result read
     * the same way whatever the test.
     *
     * @return array{patient: array<string, mixed>, events: list<array<string, mixed>>, types: array<string, string>, vlThreshold: int, cd4Low: int}
     */
    public static function timeline(string $patientId, DatabaseService $db, CommonService $general): array
    {
        $patientId = trim($patientId);
        $events = [];
        $names = $sexes = $dobs = $facilities = [];
        $ages = [];
        $typeNames = [];

        $vlThreshold = (int) ($general->getGlobalConfig('viral_load_threshold_limit') ?: 1000);

        foreach ($patientId === '' ? [] : self::visibleTypes() as $type) {
            $cfg = self::TYPES[$type];
            $a = $cfg['alias'];
            $table = TestsService::getTestTableName($type);
            $typeNames[$type] = html_entity_decode((string) TestsService::getTestName($type), ENT_QUOTES);

            $extra = match ($type) {
                'vl' => ", $a.vl_result_category, $a.result_value_absolute, $a.result_value_absolute_decimal,
                          $a.reason_for_vl_testing, tr.test_reason_name",
                'eid' => ", er.result AS result_label",
                'covid19' => ", cr.result AS result_label",
                'tb' => ", tbr.result AS result_label",
                'hepatitis' => ", $a.hcv_vl_count, $a.hbv_vl_count, $a.hepatitis_test_type",
                'cd4' => ", $a.cd4_result, $a.cd4_result_percentage",
                'generic-tests' => ", $a.final_result_interpretation, tt.test_standard_name",
                default => '',
            };
            $joins = match ($type) {
                'vl' => "LEFT JOIN r_vl_test_reasons AS tr ON tr.test_reason_id = $a.reason_for_vl_testing",
                'eid' => "LEFT JOIN r_eid_results AS er ON er.result_id = $a.result",
                'covid19' => "LEFT JOIN r_covid19_results AS cr ON cr.result_id = $a.result",
                'tb' => "LEFT JOIN r_tb_results AS tbr ON tbr.result_id = $a.result",
                'generic-tests' => "LEFT JOIN r_test_types AS tt ON tt.test_type_id = $a.test_type",
                default => '',
            };
            $rejectionTable = str_replace('-tests', '', "r_{$type}_sample_rejection_reasons");

            $sql = "SELECT $a.{$cfg['pk']} AS sample_id, $a.sample_code, $a.remote_sample_code,
                        $a.{$cfg['first']} AS first_name,
                        " . ($cfg['middle'] ? "$a.{$cfg['middle']}" : "''") . " AS middle_name,
                        $a.{$cfg['last']} AS last_name,
                        $a.{$cfg['dob']} AS dob, $a.{$cfg['sex']} AS sex,
                        " . ($cfg['age'] ? "$a.{$cfg['age']}" : 'NULL') . " AS age,
                        $a.sample_collection_date, $a.sample_received_at_lab_datetime,
                        $a.sample_tested_datetime, $a.result_approved_datetime,
                        $a.result_status, ss.status_name, $a." . TestsService::getResultColumn($type) . " AS result,
                        f.facility_name, l.facility_name AS lab_name,
                        st.{$cfg['sampleTypeName']} AS sample_type,
                        rr.rejection_reason_name
                        $extra
                    FROM $table AS $a
                    LEFT JOIN facility_details AS f ON f.facility_id = $a.facility_id
                    LEFT JOIN facility_details AS l ON l.facility_id = $a.lab_id
                    LEFT JOIN r_sample_status AS ss ON ss.status_id = $a.result_status
                    LEFT JOIN {$cfg['sampleTypeTable']} AS st ON st.{$cfg['sampleTypeId']} = $a.specimen_type
                    LEFT JOIN $rejectionTable AS rr ON rr.rejection_reason_id = $a.reason_for_sample_rejection
                    $joins
                    WHERE $a.{$cfg['id']} = '" . $db->escape($patientId) . "'
                        AND IFNULL($a.is_encrypted, 'no') != 'yes'
                        " . self::scopeWhere($type, $general) . "
                    ORDER BY $a.sample_collection_date DESC
                    LIMIT " . self::TIMELINE_LIMIT;

            foreach ($db->rawQuery($sql) ?: [] as $row) {
                $name = self::fullName($row['first_name'], $row['middle_name'], $row['last_name']);
                if ($name !== '') {
                    $names[mb_strtolower($name)] = $name;
                }
                if (($sex = self::sexLabel($row['sex'])) !== '') {
                    $sexes[$sex] = $sex;
                }
                if (($dob = self::dateOnly($row['dob'])) !== '') {
                    $dobs[$dob] = $dob;
                }
                if (!empty($row['facility_name'])) {
                    $facilities[$row['facility_name']] = $row['facility_name'];
                }
                if (is_numeric($row['age'] ?? null) && !empty($row['sample_collection_date'])) {
                    $ages[] = ['age' => (float) $row['age'], 'on' => self::dateOnly($row['sample_collection_date'])];
                }
                $events[] = self::event($type, $cfg, $row, $vlThreshold);
            }
        }

        usort($events, fn($x, $y) => strcmp((string) $x['collected'], (string) $y['collected']));

        return [
            'patient' => [
                'id' => $patientId,
                'names' => array_values($names),
                'sexes' => array_values($sexes),
                'dobs' => array_values($dobs),
                'facilities' => array_values($facilities),
                'latestAge' => $ages === [] ? null : array_reduce($ages, fn($c, $x) => $c === null || $x['on'] > $c['on'] ? $x : $c),
            ],
            'events' => $events,
            'types' => $typeNames,
            'vlThreshold' => $vlThreshold,
            'cd4Low' => self::CD4_LOW,
        ];
    }

    /** @return array<string, mixed> */
    private static function event(string $type, array $cfg, array $row, int $vlThreshold): array
    {
        $status = (int) $row['result_status'];
        $result = trim(html_entity_decode((string) ($row['result'] ?? ''), ENT_QUOTES));
        $value = null;
        $display = $result;
        $outcome = 'neutral';
        $note = null;

        switch ($type) {
            case 'vl':
                $raw = $row['result_value_absolute_decimal'] ?? $row['result_value_absolute'] ?? null;
                $value = is_numeric($raw) ? (float) $raw : null;
                $category = strtolower(trim((string) $row['vl_result_category']));
                $outcome = match (true) {
                    $category === 'suppressed' => 'good',
                    $category === 'not suppressed' => 'bad',
                    in_array($category, ['failed', 'invalid'], true) => 'warn',
                    (bool) preg_match('/not detected|tnd|below|<\s*ldl|bdl/i', $result) => 'good',
                    $value !== null => $value >= $vlThreshold ? 'bad' : 'good',
                    default => 'neutral',
                };
                $note = trim((string) ($row['test_reason_name'] ?? '')) ?: null;
                break;
            case 'eid':
            case 'covid19':
            case 'tb':
                $display = trim((string) ($row['result_label'] ?? '')) ?: $result;
                $outcome = self::readQualitative($display);
                break;
            case 'hepatitis':
                $parts = [];
                foreach (['hcv_vl_count' => 'HCV', 'hbv_vl_count' => 'HBV'] as $col => $label) {
                    if (trim((string) ($row[$col] ?? '')) !== '') {
                        $parts[] = $label . ': ' . $row[$col];
                        $value ??= is_numeric($row[$col]) ? (float) $row[$col] : null;
                    }
                }
                $display = $parts !== [] ? implode(', ', $parts) : $result;
                $outcome = self::readQualitative($result !== '' ? $result : $display);
                break;
            case 'cd4':
                $value = is_numeric($row['cd4_result'] ?? null) ? (float) $row['cd4_result'] : null;
                $display = $value !== null ? $row['cd4_result'] . ' cells/mm³' : (string) ($row['cd4_result'] ?? '');
                if (is_numeric($row['cd4_result_percentage'] ?? null)) {
                    $display .= ' (' . $row['cd4_result_percentage'] . '%)';
                }
                $outcome = $value === null ? 'neutral' : ($value < self::CD4_LOW ? 'bad' : 'good');
                break;
            case 'generic-tests':
                $display = trim((string) ($row['final_result_interpretation'] ?? '')) ?: $result;
                $outcome = self::readQualitative($display);
                $note = $row['test_standard_name'] ?? null;
                break;
        }

        // The status outranks the result: a rejected or unfinished sample says so.
        if ($status === \SAMPLE_STATUS\REJECTED) {
            $outcome = 'rejected';
        } elseif ($status === \SAMPLE_STATUS\CANCELLED) {
            $outcome = 'cancelled';
        } elseif (in_array($status, [\SAMPLE_STATUS\TEST_FAILED, \SAMPLE_STATUS\NO_RESULT], true)) {
            $outcome = 'warn';
        } elseif (!in_array($status, [\SAMPLE_STATUS\ACCEPTED, \SAMPLE_STATUS\PENDING_APPROVAL], true)) {
            $outcome = 'pending';
            $value = null;
        }

        return [
            'type' => $type,
            'sampleId' => (int) $row['sample_id'],
            'code' => (string) ($row['sample_code'] ?: $row['remote_sample_code']),
            'collected' => self::dateOnly($row['sample_collection_date']),
            'received' => self::dateOnly($row['sample_received_at_lab_datetime']),
            'tested' => self::dateOnly($row['sample_tested_datetime']),
            'approved' => self::dateOnly($row['result_approved_datetime']),
            'status' => $status,
            'statusName' => (string) ($row['status_name'] ?? ''),
            'result' => $outcome === 'pending' ? '' : $display,
            'value' => $value,
            'outcome' => $outcome,
            'note' => $note,
            'facility' => (string) ($row['facility_name'] ?? ''),
            'lab' => (string) ($row['lab_name'] ?? ''),
            'sampleType' => (string) ($row['sample_type'] ?? ''),
            'rejectionReason' => $status === \SAMPLE_STATUS\REJECTED ? (string) ($row['rejection_reason_name'] ?? '') : '',
            'printUrl' => $status === \SAMPLE_STATUS\ACCEPTED ? $cfg['print'] : null,
        ];
    }

    private static function readQualitative(string $text): string
    {
        $t = strtolower($text);
        return match (true) {
            $t === '' => 'neutral',
            (bool) preg_match('/invalid|error|indeterminate|contaminated|no[ -]result|inconclusive/', $t) => 'warn',
            (bool) preg_match('/negative|not detected|no growth|non[- ]reactive/', $t) => 'good',
            (bool) preg_match('/positive|detected|reactive|mtb|afb/', $t) => 'bad',
            default => 'neutral',
        };
    }

    /** Access scope shared by the search and the timeline. */
    private static function scopeWhere(string $type, CommonService $general): string
    {
        $a = self::TYPES[$type]['alias'];
        $where = ' AND ' . SampleCountUtility::countableWhere($a);
        if ($type === 'vl') {
            $where .= " AND IFNULL($a.reason_for_vl_testing, 0) != 9999";
        }
        if ($general->isSTSInstance()) {
            if (!empty($_SESSION['facilityMap'])) {
                $ids = implode(',', array_map('intval', explode(',', (string) $_SESSION['facilityMap'])));
                $where .= " AND $a.facility_id IN ($ids)";
            }
        } else {
            $where .= " AND $a.result_status != " . \SAMPLE_STATUS\RECEIVED_AT_CLINIC;
        }
        if ($labScope = $general->labScopeWhere($a)) {
            $where .= " AND $labScope";
        }
        return $where;
    }

    private static function fullName(?string ...$parts): string
    {
        return trim(preg_replace('/\s+/', ' ', implode(' ', array_map(fn($p) => trim((string) $p), $parts))) ?? '');
    }

    private static function sexLabel(?string $sex): string
    {
        return match (strtolower(trim((string) $sex))) {
            'male', 'm' => _translate('Male'),
            'female', 'f' => _translate('Female'),
            '', 'unreported', 'not recorded' => '',
            default => ucfirst(trim((string) $sex)),
        };
    }

    private static function dateOnly(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || str_starts_with($value, '0000')) {
            return '';
        }
        return substr($value, 0, 10);
    }
}
