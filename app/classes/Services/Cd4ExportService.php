<?php

namespace App\Services;

use App\Abstracts\AbstractSampleExportService;
use App\Utilities\DateUtility;
use App\Utilities\SampleExportUtility as X;

/**
 * The columns of the CD4 Excel exports (View Requests and Export Results), kept
 * in one list so both exports give the same sheet. The rules are the ones on
 * VlExportService: form fields shown as the form shows them, plus record
 * metadata; lab and audit columns only when asked for.
 */
final class Cd4ExportService extends AbstractSampleExportService
{
    protected function idColumn(): string
    {
        return 'cd4_id';
    }

    /** In the order of the request and result forms. */
    protected function allColumns(): array
    {
        $detail = ['detail' => true];

        return [
            X::column(_translate('S.No.'), static fn(array $r, int $no): int => $no),
            X::text(_translate('Sample ID'), 'sample_code'),
            X::text(_translate('Remote Sample ID'), 'remote_sample_code', ['sts' => true]),
            X::text(_translate('Batch Code'), 'batch_code', $detail),
            X::text(_translate('Testing Lab'), 'lab_name'),
            X::date(_translate('Sample Reception Date'), 'sample_received_at_lab_datetime', true),
            X::text(_translate('Health Facility Name'), 'facility_name'),
            X::text(_translate('Health Facility Code'), 'facility_code'),
            X::text(_translate('District/County'), 'facility_district'),
            X::text(_translate('Province/State'), 'facility_state'),

            X::text(_translate('Unique ART No.'), 'patient_art_no', ['pii' => true]),
            X::text(_translate('Patient Name'), 'patient_name', ['pii' => true]),
            X::date(_translate('Date of Birth'), 'patient_dob'),
            X::text(_translate('Age'), 'patient_age_in_years'),
            X::sex(_translate('Sex'), 'patient_gender'),
            X::text(_translate('Patient Cellphone Number'), 'patient_mobile_number', ['pii' => true]),
            X::date(_translate('Date of Sample Collection'), 'sample_collection_date', true),
            X::text(_translate('Sample Type'), 'sample_name'),
            X::date(_translate('Date of Treatment Initiation'), 'treatment_initiated_date'),
            X::text(_translate('Current Regimen'), 'current_regimen'),
            X::date(_translate('Date of Initiation of Current Regimen'), 'date_of_initiation_of_current_regimen'),
            X::yesNo(_translate('Is Patient Pregnant?'), 'is_patient_pregnant'),
            X::yesNo(_translate('Is Patient Breastfeeding?'), 'is_patient_breastfeeding'),
            X::column(
                _translate('ARV Adherence'),
                static fn(array $r): string => match (trim((string) $r['arv_adherance_percentage'])) {
                    'good' => 'Good >= 95%',
                    'fair' => 'Fair 85-94%',
                    'poor' => 'Poor <85%',
                    default => '',
                }
            ),
            X::withOther(_translate('Indication for CD4 Testing'), 'test_reason_label', 'reason_for_cd4_testing_other'),
            X::text(_translate('Requesting Clinician'), 'request_clinician_name'),
            X::text(_translate('Requesting Clinician Cellphone Number'), 'request_clinician_phone_number'),
            X::date(_translate('Request Date'), 'test_requested_on'),

            ...X::rejectionColumns(),
            X::text(_translate('Recommended Corrective Action'), 'recommended_corrective_action_name'),
            X::date(_translate('Sample Tested On'), 'sample_tested_datetime', true),
            X::text(_translate('Testing Platform'), 'cd4_test_platform', $detail),
            X::text(_translate('CD4 Result'), 'cd4_result'),
            X::date(_translate('Result Printed Date'), 'result_printed_datetime', true),
            X::text(_translate('CD4 Result (%)'), 'cd4_result_percentage'),
            X::text(_translate('Comments'), 'lab_tech_comments'),
            X::text(_translate('Funding Source'), 'funding_source_name'),
            X::text(_translate('Implementing Partner'), 'i_partner_name'),
            X::date(_translate('Request Created On'), 'request_created_datetime', true),
            // Result Printed Date and Request Created On are above, where the sheet always had them.
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
                    s.sample_name,
                    tr.test_reason_name,
                    rs.rejection_reason_name,
                    rca.recommended_corrective_action_name
                FROM form_cd4 AS vl
                {$shared['joins']}
                LEFT JOIN r_cd4_sample_types AS s ON s.sample_id = vl.specimen_type
                LEFT JOIN r_cd4_test_reasons AS tr ON tr.test_reason_id = vl.reason_for_cd4_testing
                LEFT JOIN r_cd4_sample_rejection_reasons AS rs ON rs.rejection_reason_id = vl.reason_for_sample_rejection
                LEFT JOIN r_recommended_corrective_actions AS rca
                    ON rca.recommended_corrective_action_id = vl.recommended_corrective_action
                WHERE " . $this->detailsWhere($ids));

        $details = [];
        foreach ($rows as $row) {
            $row = X::prepareShared($row, 'reason_for_result_changes');
            $names = ['patient_first_name', 'patient_middle_name', 'patient_last_name'];
            $row = X::decryptFields($row, ['patient_art_no', ...$names], $key);
            $row['patient_name'] = X::fullName(...array_map(static fn($name) => $row[$name], $names));
            $row['test_reason_label'] = str_replace('_', ' ', (string) $row['test_reason_name']);

            $age = DateUtility::ageInYearMonthDays($row['patient_dob'] ?? '');
            if (!empty($age) && $age['year'] > 0) {
                $row['patient_age_in_years'] = $age['year'];
            }

            $details[(int) $row['cd4_id']] = $row;
        }
        return $details;
    }
}
