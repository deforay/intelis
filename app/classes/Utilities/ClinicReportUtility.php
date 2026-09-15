<?php

declare(strict_types=1);

namespace App\Utilities;

use App\Services\TestsService;
use App\Services\CommonService;
use App\Services\DatabaseService;

/**
 * What the shared tabs of the seven clinic report pages need to know about a
 * test type: where its patient fields live, which page grants access to it, and
 * which samples a user may count.
 *
 * The form tables name the same things differently (ART number, child ID,
 * patient ID; child_gender, patient_gender), so every report that works across
 * test types reads the mapping from here instead of carrying its own copy.
 */
final class ClinicReportUtility
{
    /**
     * Per test type:
     *  - alias, pk: the table alias used in queries and the primary key
     *  - id, first, middle, last, dob, sex: patient columns
     *  - ages: every column that can hold an age
     *  - reason: the reason for testing
     *  - sampleType*: the sample type lookup
     *  - page: the clinic report page whose privilege grants access
     *  - edit, editId: the request edit page and how it encodes the id
     *  - print: the result PDF endpoint
     */
    private const TYPES = [
        'vl' => [
            'alias' => 'vl', 'pk' => 'vl_sample_id', 'id' => 'patient_art_no',
            'first' => 'patient_first_name', 'middle' => 'patient_middle_name', 'last' => 'patient_last_name',
            'dob' => 'patient_dob', 'sex' => 'patient_gender', 'age' => 'patient_age_in_years',
            'ages' => ['patient_age_in_years', 'patient_age_in_months'],
            'reason' => 'reason_for_vl_testing',
            'sampleTypeTable' => 'r_vl_sample_type', 'sampleTypeId' => 'sample_id', 'sampleTypeName' => 'sample_name',
            'page' => '/vl/program-management/vl-clinic-reports.php',
            'edit' => '/vl/requests/editVlRequest.php', 'editId' => 'sqid',
            'print' => '/vl/results/generate-result-pdf.php',
        ],
        'eid' => [
            'alias' => 'eid', 'pk' => 'eid_id', 'id' => 'child_id',
            'first' => 'child_name', 'middle' => null, 'last' => 'child_surname',
            'dob' => 'child_dob', 'sex' => 'child_gender', 'age' => null,
            'ages' => ['child_age', 'child_age_in_weeks'],
            'reason' => 'reason_for_eid_test',
            'sampleTypeTable' => 'r_eid_sample_type', 'sampleTypeId' => 'sample_id', 'sampleTypeName' => 'sample_name',
            'page' => '/eid/management/eid-clinic-reports.php',
            'edit' => '/eid/requests/eid-edit-request.php', 'editId' => 'sqid',
            'print' => '/eid/results/generate-result-pdf.php',
        ],
        'covid19' => [
            'alias' => 'covid19', 'pk' => 'covid19_id', 'id' => 'patient_id',
            'first' => 'patient_name', 'middle' => null, 'last' => 'patient_surname',
            'dob' => 'patient_dob', 'sex' => 'patient_gender', 'age' => 'patient_age',
            'ages' => ['patient_age'],
            'reason' => 'reason_for_covid19_test',
            'sampleTypeTable' => 'r_covid19_sample_type', 'sampleTypeId' => 'sample_id', 'sampleTypeName' => 'sample_name',
            'page' => '/covid-19/management/covid-19-clinic-report.php',
            'edit' => '/covid-19/requests/covid-19-edit-request.php', 'editId' => 'base64',
            'print' => '/covid-19/results/generate-result-pdf.php',
        ],
        'hepatitis' => [
            'alias' => 'hepatitis', 'pk' => 'hepatitis_id', 'id' => 'patient_id',
            'first' => 'patient_name', 'middle' => null, 'last' => 'patient_surname',
            'dob' => 'patient_dob', 'sex' => 'patient_gender', 'age' => 'patient_age',
            'ages' => ['patient_age'],
            'reason' => 'reason_for_hepatitis_test',
            'sampleTypeTable' => 'r_hepatitis_sample_type', 'sampleTypeId' => 'sample_id', 'sampleTypeName' => 'sample_name',
            'page' => '/hepatitis/management/hepatitis-clinic-report.php',
            'edit' => '/hepatitis/requests/hepatitis-edit-request.php', 'editId' => 'base64',
            'print' => '/hepatitis/results/generate-result-pdf.php',
        ],
        'tb' => [
            'alias' => 'tb', 'pk' => 'tb_id', 'id' => 'patient_id',
            'first' => 'patient_name', 'middle' => null, 'last' => 'patient_surname',
            'dob' => 'patient_dob', 'sex' => 'patient_gender', 'age' => 'patient_age',
            'ages' => ['patient_age'],
            'reason' => 'reason_for_tb_test',
            'sampleTypeTable' => 'r_tb_sample_type', 'sampleTypeId' => 'sample_id', 'sampleTypeName' => 'sample_name',
            'page' => '/tb/management/tb-clinic-report.php',
            'edit' => '/tb/requests/tb-edit-request.php', 'editId' => 'base64',
            'print' => '/tb/results/generate-result-pdf.php',
        ],
        'cd4' => [
            'alias' => 'cd4', 'pk' => 'cd4_id', 'id' => 'patient_art_no',
            'first' => 'patient_first_name', 'middle' => 'patient_middle_name', 'last' => 'patient_last_name',
            'dob' => 'patient_dob', 'sex' => 'patient_gender', 'age' => 'patient_age_in_years',
            'ages' => ['patient_age_in_years', 'patient_age_in_months'],
            'reason' => null,
            'sampleTypeTable' => 'r_cd4_sample_types', 'sampleTypeId' => 'sample_id', 'sampleTypeName' => 'sample_name',
            'page' => '/cd4/management/cd4-clinic-report.php',
            'edit' => '/cd4/requests/cd4-edit-request.php', 'editId' => 'base64',
            'print' => '/cd4/results/generate-result-pdf.php',
        ],
        'generic-tests' => [
            'alias' => 'generic', 'pk' => 'sample_id', 'id' => 'patient_id',
            'first' => 'patient_first_name', 'middle' => 'patient_middle_name', 'last' => 'patient_last_name',
            'dob' => 'patient_dob', 'sex' => 'patient_gender', 'age' => 'patient_age_in_years',
            'ages' => ['patient_age_in_years', 'patient_age_in_months'],
            'reason' => 'reason_for_testing',
            'sampleTypeTable' => 'r_generic_sample_types', 'sampleTypeId' => 'sample_type_id', 'sampleTypeName' => 'sample_type_name',
            'page' => '/generic-tests/program-management/generic-tests-clinic-report.php',
            'edit' => '/generic-tests/requests/edit-request.php', 'editId' => 'base64',
            'print' => '/generic-tests/results/generate-result-pdf.php',
        ],
    ];

    /** @return array<string, mixed> */
    public static function type(string $type): array
    {
        return self::TYPES[$type] ?? throw new \InvalidArgumentException("Unsupported test type: $type");
    }

    /** Test types this user may see, in display order. @return list<string> */
    public static function visibleTypes(): array
    {
        return array_values(array_filter(array_keys(self::TYPES), self::canView(...)));
    }

    /**
     * AJAX requests bypass the access control layer, so shared endpoints ask
     * here: a test type is readable by whoever can open its clinic report.
     */
    public static function canView(string $type): bool
    {
        return isset(self::TYPES[$type])
            && TestsService::isTestActive($type)
            && _isAllowed(self::TYPES[$type]['page']);
    }

    public static function canEdit(string $type): bool
    {
        return _isAllowed(self::type($type)['edit']);
    }

    /** Link to the request edit page for one sample. Check canEdit() first. */
    public static function editUrl(string $type, int|string $id): string
    {
        $cfg = self::type($type);
        $encoded = $cfg['editId'] === 'sqid' ? MiscUtility::sqid((int) $id) : base64_encode((string) $id);
        return $cfg['edit'] . '?id=' . rawurlencode($encoded);
    }

    /**
     * Which samples a user may count: not cancelled, not a Recency sample, the
     * user's facilities on STS, never clinic-side samples on a lab, and the
     * user's lab scope.
     *
     * @return list<string> SQL clauses on the type's alias
     */
    public static function scopeClauses(string $type, DatabaseService $db, CommonService $general): array
    {
        $a = self::type($type)['alias'];
        $where = [SampleCountUtility::countableWhere($a)];

        if ($type === 'vl') {
            // Recency samples live in form_vl but are reported under Recency.
            $where[] = "IFNULL($a.reason_for_vl_testing, 0) != 9999";
        }

        if (!$general->isSTSInstance()) {
            $where[] = "$a.result_status != " . \SAMPLE_STATUS\RECEIVED_AT_CLINIC;
        } elseif (!empty($_SESSION['facilityMap'])) {
            $where[] = "$a.facility_id IN (" . $db->inIntList((string) $_SESSION['facilityMap']) . ")";
        }

        if ($labScope = $general->labScopeWhere($a)) {
            $where[] = $labScope;
        }

        return $where;
    }

    /**
     * The location and partner filters of a clinic report tab. Expects the
     * facility joined as `f`.
     *
     * @param array<string, mixed> $filters sanitized request values
     * @return list<string>
     */
    public static function filterClauses(string $type, array $filters, DatabaseService $db): array
    {
        $a = self::type($type)['alias'];
        $where = [];
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
        return $where;
    }

    /**
     * A posted "dd-Mon-YYYY to dd-Mon-YYYY" range as Y-m-d bounds.
     *
     * @return array{0: string, 1: string}
     */
    public static function dateRange(string $posted, string $defaultStart = '-7 days'): array
    {
        [$start, $end] = $posted !== '' ? DateUtility::convertDateRange($posted) : [null, null];
        $valid = static fn($d) => is_string($d) && \DateTimeImmutable::createFromFormat('!Y-m-d', $d) !== false;
        if (!$valid($start) || !$valid($end)) {
            return [date('Y-m-d', strtotime($defaultStart)), date('Y-m-d')];
        }
        return [$start, $end];
    }

    /**
     * Half-open datetime bounds for a Y-m-d range, so a bare column comparison
     * can use the index that DATE(column) BETWEEN hides.
     *
     * @return array{0: string, 1: string}
     */
    public static function datetimeBounds(string $startDate, string $endDate): array
    {
        return [
            $startDate . ' 00:00:00',
            (new \DateTimeImmutable($endDate))->modify('+1 day')->format('Y-m-d') . ' 00:00:00',
        ];
    }
}
