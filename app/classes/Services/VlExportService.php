<?php

namespace App\Services;

use App\Abstracts\AbstractSampleExportService;
use App\Utilities\DateUtility;
use App\Utilities\SampleExportUtility as X;
use App\Utilities\SampleRejectionUtility;

use const COUNTRY\DRC;
use const COUNTRY\CAMEROON;

/**
 * The columns of the VL Excel exports (View Requests and Export Results), kept
 * in one list so both exports give the same sheet.
 *
 * A column is a field of the request or result form, shown the way the form
 * shows it (names, not ids; formatted dates), or a piece of record metadata
 * (status, who changed it and when). Database columns that no form shows are
 * not exported.
 *
 * Every country uses this one list. A field that only some country forms have
 * is tagged with those forms ('forms'), and a field those forms leave out is
 * tagged the other way ('exceptForms'), so adding a country's field is one
 * entry here, not another layout. The CRESAR layout is the one exception: a
 * single lab asked for its own sheet, and the 'cresar' export format setting
 * picks it on Cameroon instances.
 */
final class VlExportService extends AbstractSampleExportService
{
    protected function idColumn(): string
    {
        return 'vl_sample_id';
    }

    public function columns(bool $withPatientInfo): array
    {
        $formId = (int) $this->general->getGlobalConfig('vl_form');
        if ($formId === CAMEROON && $this->general->getGlobalConfig('vl_excel_export_format') === 'cresar') {
            return $this->cresarColumns();
        }
        return parent::columns($withPatientInfo);
    }

    /**
     * The universal layout, in the order of the request and result forms:
     * facility and request, patient, treatment, sample, lab, record.
     */
    protected function allColumns(): array
    {
        $drc = ['forms' => [DRC]];
        $notDrc = ['exceptForms' => [DRC]];

        return [
            X::column(_translate('S.No.'), static fn(array $r, int $no): int => $no),
            X::text(_translate('Sample ID'), 'sample_code'),
            X::text(_translate('Remote Sample ID'), 'remote_sample_code', ['sts' => true]),
            X::text(_translate('Recency ID'), 'external_sample_code', $drc),
            X::text(_translate('Batch Code'), 'batch_code'),
            X::text(_translate('Testing Lab'), 'lab_name'),
            X::text(_translate('Lab Assigned Code'), 'lab_assigned_code', ['forms' => [CAMEROON]]),
            X::date(_translate('Sample Reception Date'), 'sample_received_at_lab_datetime', true),
            X::text(_translate('Health Facility Name'), 'facility_name'),
            X::text(_translate('Health Facility Code'), 'facility_code'),
            X::text(_translate('District/County'), 'facility_district'),
            X::text(_translate('Province/State'), 'facility_state'),
            X::text(_translate('Requesting Clinician'), 'request_clinician_name'),
            X::text(_translate('Clinician Contact Number'), 'request_clinician_phone_number'),
            X::date(_translate('Request Date'), 'test_requested_on', false, $notDrc),
            X::date(_translate('Request Date'), 'date_test_ordered_by_physician', false, $drc),
            X::text(_translate('Funding Source'), 'funding_source_name'),
            X::text(_translate('Implementing Partner'), 'i_partner_name'),

            X::text(_translate('Unique ART No.'), 'patient_art_no', ['pii' => true]),
            X::text(_translate('Patient Name'), 'patient_name', ['pii' => true] + $notDrc),
            X::text(_translate('Patient Contact Number'), 'patient_mobile_number'),
            X::date(_translate('Date of Birth'), 'patient_dob'),
            X::text(_translate('Age'), 'patient_age_in_years'),
            X::text(_translate('Age in Months'), 'patient_age_in_months', $drc),
            X::sex(_translate('Sex'), 'patient_gender'),
            X::text(_translate('Universal Insurance Code'), 'health_insurance_code', ['forms' => [CAMEROON]]),
            X::yesNo(_translate('Is Patient Pregnant?'), 'is_patient_pregnant'),
            X::column(
                _translate('Pregnancy Trimester'),
                static fn(array $r): string => in_array((string) $r['pregnancy_trimester'], ['1', '2', '3'], true)
                    ? (string) $r['pregnancy_trimester']
                    : '',
                $drc
            ),
            X::yesNo(_translate('Is Patient Breastfeeding?'), 'is_patient_breastfeeding'),

            X::yesNo(_translate('Patient on ART?'), 'is_patient_new', $drc),
            X::date(_translate('Date of Treatment Initiation'), 'treatment_initiated_date'),
            X::text(_translate('Current Regimen'), 'current_regimen'),
            X::date(
                _translate('Date of Initiation of Current Regimen'),
                'date_of_initiation_of_current_regimen',
                false,
                $notDrc
            ),
            X::yesNo(_translate('Has Regimen Changed?'), 'has_patient_changed_regimen'),
            X::text(_translate('Reason for Regimen Change'), 'reason_for_regimen_change'),
            X::date(_translate('Date of Regimen Change'), 'regimen_change_date'),
            X::column(
                _translate('ARV Adherence'),
                static fn(array $r): string => match (trim((string) $r['arv_adherance_percentage'])) {
                    'good' => 'Good >= 95%',
                    'fair' => 'Fair 85-94%',
                    'poor' => 'Poor <85%',
                    default => '',
                },
                $notDrc
            ),
            X::withOther(
                _translate('Indication for Viral Load Testing'),
                'test_reason_label',
                'reason_for_vl_testing_other'
            ),
            X::text(_translate('Viral Load Test Number'), 'vl_test_number', $drc),
            X::date(_translate('Last Viral Load Date'), 'last_viral_load_date', false, $drc),
            X::text(_translate('Last Viral Load Result'), 'last_viral_load_result', $drc),

            X::date(_translate('Date of Sample Collection'), 'sample_collection_date', true),
            X::text(_translate('Sample Type'), 'sample_name'),
            X::text(_translate('Plasma Conservation Temperature (°C)'), 'plasma_conservation_temperature', $drc),
            X::text(_translate('Plasma Conservation Duration (Days/Hours)'), 'plasma_conservation_duration', $drc),
            X::date(_translate('Date Sample Sent to Lab'), 'sample_dispatched_datetime', true),
            ...X::storageColumns($drc),

            ...X::rejectionColumns(),
            X::text(_translate('Recommended Corrective Action'), 'recommended_corrective_action_name', $notDrc),
            X::date(_translate('Sample Tested On'), 'sample_tested_datetime', true),
            X::text(_translate('Testing Platform'), 'vl_test_platform'),
            X::text(_translate('Assay'), 'assay_name'),
            X::text(_translate('Result (cp/mL)'), 'result'),
            X::text(_translate('Result (log)'), 'result_log'),
            X::text(_translate('Reason for Failure'), 'failure_reason', $drc),
            X::text(_translate('Comments'), 'lab_tech_comments', $notDrc),
            ...X::recordColumns(),
        ];
    }

    /** The sheet one Cameroon lab asked for; its order and headings are theirs. */
    private function cresarColumns(): array
    {
        $blank = static fn(): string => '';
        return [
            X::column(_translate('S.No.'), static fn(array $r, int $no): int => $no),
            X::text(_translate('Sample ID'), 'sample_code'),
            X::text(_translate('Region of sending facility'), 'facility_state'),
            X::text(_translate('District of sending facility'), 'facility_district'),
            X::text(_translate('Sending facility'), 'facility_name'),
            X::text(_translate('Project'), 'funding_source_name'),
            X::text(_translate('Existing ART Code'), 'patient_art_no'),
            X::date(_translate('Date of Birth'), 'patient_dob'),
            X::text(_translate('Age'), 'patient_age_in_years'),
            X::text(_translate('Patient Name'), 'patient_name'),
            X::sex(_translate('Sex'), 'patient_gender'),
            X::text(_translate('Universal Insurance Code'), 'health_insurance_code'),
            X::date(_translate('Sample Creation Date'), 'request_created_datetime'),
            X::text(_translate('Sample Created By'), 'created_by_name'),
            X::date(_translate('Sample collection date'), 'sample_collection_date'),
            X::text(_translate('Sample Type'), 'sample_name'),
            X::text(_translate('Requested by contact'), 'request_clinician_name'),
            X::date(_translate('Treatment start date'), 'treatment_initiated_date'),
            X::column(
                _translate('Treatment Protocol'),
                static fn(array $r): string => match ((string) $r['line_of_treatment']) {
                    '1' => '1st Line',
                    '2' => '2nd Line',
                    '3' => '3rd Line',
                    'n/a' => 'N/A',
                    default => '',
                }
            ),
            X::text(_translate('ARV Protocol'), 'current_regimen'),
            X::text(_translate('CV Number'), 'cv_number'),
            X::text(_translate('Batch Code'), 'batch_code'),
            X::text(_translate('Test Platform'), 'vl_test_platform'),
            X::column(
                _translate('Test platform detection limit'),
                static fn(array $r): string => empty($r['vl_test_platform'])
                    ? ''
                    : $r['lower_limit'] . ' - ' . $r['higher_limit']
            ),
            X::column(
                _translate('Sample Tested'),
                static fn(array $r): string => empty($r['sample_tested_datetime']) ? 'No' : 'Yes'
            ),
            X::date(_translate('Date of test'), 'sample_tested_datetime'),
            X::date(_translate('Date of result sent to facility'), 'result_dispatched_datetime'),
            X::column(_translate('Sample Rejected'), static fn(array $r): string => $r['is_rejected'] ? 'Yes' : 'No'),
            X::column(
                _translate('Communication of rejected samples or high viral load (yes, no or NA)'),
                static fn(array $r): string => $r['is_rejected']
                    ? SampleRejectionUtility::reasonLabel($r['rejection_reason_name'])
                    : ''
            ),
            X::text(_translate('Result Value'), 'result'),
            X::date(_translate('Result Printed Date'), 'result_printed_datetime'),
            X::text(_translate('Result Value Log'), 'result_log'),
            X::text(_translate('Is suppressed'), 'vl_result_category'),
            X::column(_translate('Name of reference Lab'), static fn(): string => 'Reference Lab'),
            X::date(_translate('Sample Reception Date'), 'sample_received_at_lab_datetime'),
            X::column(_translate('Category of testing site'), $blank),
            X::column(_translate('TAT'), $blank),
            X::column(_translate('Age Range'), $blank),
            X::column(_translate('Was sample send to another reference lab'), $blank),
            X::column(_translate('If sample was send to another lab, give name of lab'), $blank),
            X::column(_translate('Invalid test (yes or no)'), $blank),
            X::column(_translate('Invalid sample repeated (yes or no)'), $blank),
            X::column(_translate('Error codes (yes or no)'), $blank),
            X::column(_translate('Error codes values'), $blank),
            X::column(_translate('Tests repeated due to error codes (yes or no)'), $blank),
            X::column(_translate('New CV number'), $blank),
            X::column(_translate('Date of repeat test'), $blank),
            X::column(_translate('Result sent back to facility (yes or no)'), $blank),
            X::column(_translate('Result Type'), $blank),
            X::column(_translate('Observations'), $blank),
        ];
    }

    protected function fetchDetails(array $ids, string $key): array
    {
        $shared = X::sharedDetailsSql('vl');
        $rows = $this->db->rawQuery("SELECT vl.*, {$shared['select']},
                    s.sample_name,
                    tr.test_reason_name,
                    rs.rejection_reason_name,
                    rca.recommended_corrective_action_name,
                    fr.failure_reason
                FROM form_vl AS vl
                {$shared['joins']}
                LEFT JOIN r_vl_sample_type AS s ON s.sample_id = vl.specimen_type
                LEFT JOIN r_vl_test_reasons AS tr ON tr.test_reason_id = vl.reason_for_vl_testing
                LEFT JOIN r_vl_sample_rejection_reasons AS rs ON rs.rejection_reason_id = vl.reason_for_sample_rejection
                LEFT JOIN r_recommended_corrective_actions AS rca
                    ON rca.recommended_corrective_action_id = vl.recommended_corrective_action
                LEFT JOIN r_vl_test_failure_reasons AS fr ON fr.failure_id = vl.reason_for_failure
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
            $row['result_log'] = is_numeric($row['result_value_log'] ?? null)
                ? round((float) $row['result_value_log'], 1)
                : '';

            $details[(int) $row['vl_sample_id']] = $row;
        }
        return $details;
    }
}
