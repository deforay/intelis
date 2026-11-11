<?php

namespace App\Services;

use App\Abstracts\AbstractSampleExportService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Utilities\SampleExportUtility as X;

/**
 * The columns of the Custom Tests Excel exports (View Requests and Export
 * Results), kept in one list so both exports give the same sheet. The rules are
 * the ones on VlExportService: form fields shown as the form shows them, plus
 * record metadata; lab and audit columns only when asked for.
 *
 * Each test type adds the fields of its own form. The sheet has a column for
 * every such field of the test types among the exported samples, after the
 * common fields.
 */
final class GenericTestsExportService extends AbstractSampleExportService
{
    /** Ids read per query when finding the exported samples' test types. */
    private const CHUNK_SIZE = 1000;

    /** @var array<string, string> field id => label, for the export being written */
    private array $formFields = [];

    protected function idColumn(): string
    {
        return 'sample_id';
    }

    public function export(
        string $listQuery,
        string $filePrefix,
        bool $withPatientInfo,
        array $preamble = [],
        bool $alphaNumHeadings = false,
        bool $withDetails = false
    ): string {
        $this->formFields = $this->formFieldsOf($listQuery);
        try {
            return parent::export($listQuery, $filePrefix, $withPatientInfo, $preamble, $alphaNumHeadings, $withDetails);
        } finally {
            $this->formFields = [];
        }
    }

    /** In the order of the request and result forms, then the test type's own fields. */
    protected function allColumns(): array
    {
        $detail = ['detail' => true];

        $formFieldColumns = [];
        foreach ($this->formFields as $fieldId => $label) {
            $formFieldColumns[] = X::column($label, static function (array $r) use ($fieldId): string {
                $value = $r['form_values'][$fieldId] ?? '';
                if (is_array($value)) {
                    $value = implode(', ', array_filter(array_map(
                        static fn($part): string => is_scalar($part) ? trim((string) $part) : '',
                        $value
                    ), 'strlen'));
                }
                return is_scalar($value) ? html_entity_decode((string) $value, ENT_QUOTES) : '';
            });
        }

        return [
            X::column(_translate('No.'), static fn(array $r, int $no): int => $no),
            X::text(_translate('Test Type'), 'test_standard_name'),
            X::text(_translate('Sample ID'), 'sample_code'),
            X::text(_translate('Remote Sample ID'), 'remote_sample_code', ['sts' => true]),
            X::text(_translate('Batch Code'), 'batch_code', $detail),
            X::text(_translate('Health Facility Name'), 'facility_name'),
            X::text(_translate('Testing Lab'), 'lab_name'),
            X::date(_translate('Sample Receipt Date'), 'sample_received_at_lab_datetime', true),
            X::text(_translate('Health Facility Code'), 'facility_code'),
            X::text(_translate('District/County'), 'facility_district'),
            X::text(_translate('Province/State'), 'facility_state'),

            X::text(_translate('Patient ID.'), 'patient_id', ['pii' => true]),
            X::text(_translate('Patient Name'), 'patient_name', ['pii' => true]),
            X::date(_translate('Date of Birth'), 'patient_dob'),
            X::text(_translate('Age'), 'patient_age_in_years'),
            X::sex(_translate('Sex'), 'patient_gender'),
            X::date(_translate('Date of Sample Collection'), 'sample_collection_date', true),
            X::text(_translate('Sample Type'), 'sample_type_name'),
            X::date(_translate('Date of Treatment Initiation'), 'treatment_initiated_date'),
            X::yesNo(_translate('Is Patient Pregnant?'), 'is_patient_pregnant'),
            X::yesNo(_translate('Is Patient Breastfeeding?'), 'is_patient_breastfeeding'),
            X::withOther(_translate('Reason for Testing'), 'test_reason_label', 'reason_for_testing_other'),
            X::text(_translate('Requesting Clinician'), 'request_clinician_name'),
            X::date(_translate('Request Date'), 'test_requested_on'),

            ...X::rejectionColumns(),
            X::date(_translate('Sample Tested On'), 'sample_tested_datetime', true),
            X::text(_translate('Testing Platform'), 'test_platform', $detail),
            X::text(_translate('Result'), 'result'),
            // The heading this sheet has always had; the value is the date the result was printed.
            X::date(_translate('Date Result Dispatched'), 'result_printed_datetime', true),
            X::text(_translate('Comments'), 'lab_tech_comments'),
            X::text(_translate('Funding Source'), 'funding_source_name'),
            X::text(_translate('Implementing Partner'), 'i_partner_name'),
            X::date(_translate('Request Created On'), 'request_created_datetime', true),
            ...$formFieldColumns,
            // The printed and creation dates are above, where the sheet always had them.
            ...X::without(
                X::recordColumns(),
                _translate('Result Printed Date'),
                _translate('Request Created On')
            ),
        ];
    }

    protected function fetchDetails(array $ids, string $key): array
    {
        $shared = X::sharedDetailsSql('vl');
        $rows = $this->db->rawQuery("SELECT vl.*, {$shared['select']},
                    ty.test_standard_name,
                    s.sample_type_name,
                    tr.test_reason,
                    rs.rejection_reason_name
                FROM form_generic AS vl
                {$shared['joins']}
                LEFT JOIN r_test_types AS ty ON ty.test_type_id = vl.test_type
                LEFT JOIN r_generic_sample_types AS s ON s.sample_type_id = vl.specimen_type
                LEFT JOIN r_generic_test_reasons AS tr ON tr.test_reason_id = vl.reason_for_testing
                LEFT JOIN r_generic_sample_rejection_reasons AS rs
                    ON rs.rejection_reason_id = vl.reason_for_sample_rejection
                WHERE " . $this->detailsWhere($ids));

        $details = [];
        foreach ($rows as $row) {
            $row = X::prepareShared($row, 'reason_for_test_result_changes');
            $names = ['patient_first_name', 'patient_middle_name', 'patient_last_name'];
            $row = X::decryptFields($row, ['patient_id', ...$names], $key);
            $row['patient_name'] = X::fullName(...array_map(static fn($name) => $row[$name], $names));
            $row['test_reason_label'] = str_replace('_', ' ', (string) $row['test_reason']);

            $age = DateUtility::ageInYearMonthDays($row['patient_dob'] ?? '');
            if (!empty($age) && $age['year'] > 0) {
                $row['patient_age_in_years'] = $age['year'];
            }

            $form = trim((string) ($row['test_type_form'] ?? ''));
            $values = $form === '' ? [] : JsonUtility::decodeJson($form);
            $row['form_values'] = is_array($values) ? $values : [];

            $details[(int) $row['sample_id']] = $row;
        }
        return $details;
    }

    /**
     * The form fields of the test types among the listed samples, in the order
     * the samples are listed. Samples saved without a test form add none.
     *
     * @return array<string, string> field id => label
     */
    private function formFieldsOf(string $listQuery): array
    {
        $testTypes = [];
        $ids = [];
        $collect = function (array $ids) use (&$testTypes): void {
            $rows = $this->db->rawQuery("SELECT sample_id, test_type FROM form_generic
                    WHERE sample_id IN (" . $this->db->inIntList($ids) . ")
                    AND test_type_form IS NOT NULL AND TRIM(test_type_form) <> ''");
            $typeById = array_column($rows, 'test_type', 'sample_id');
            foreach ($ids as $id) {
                if (isset($typeById[$id])) {
                    $testTypes[(int) $typeById[$id]] = true;
                }
            }
        };
        foreach ($this->db->rawQueryGenerator($listQuery) as $listRow) {
            $ids[] = (int) $listRow['sample_id'];
            if (count($ids) === self::CHUNK_SIZE) {
                $collect($ids);
                $ids = [];
            }
        }
        if ($ids !== []) {
            $collect($ids);
        }
        if ($testTypes === []) {
            return [];
        }

        $configs = array_column($this->db->rawQuery(
            "SELECT test_type_id, test_form_config FROM r_test_types
                WHERE test_type_id IN (" . $this->db->inIntList(array_keys($testTypes)) . ")"
        ), 'test_form_config', 'test_type_id');

        $fields = [];
        foreach (array_keys($testTypes) as $testType) {
            $config = trim((string) ($configs[$testType] ?? ''));
            $config = $config === '' ? [] : JsonUtility::decodeJson($config);
            foreach (is_array($config) ? $config : [] as $section => $sectionFields) {
                // Other sections are grouped one level deeper.
                $groups = $section === 'otherSection' ? $sectionFields : [$sectionFields];
                foreach (is_array($groups) ? $groups : [] as $group) {
                    foreach (is_array($group) ? $group : [] as $fieldId => $field) {
                        $fields[(string) $fieldId] = (string) ($field['field_name'] ?? $fieldId);
                    }
                }
            }
        }
        return $fields;
    }
}
