<?php

namespace App\Services;

use App\Abstracts\AbstractSampleExportService;
use App\Utilities\SampleExportUtility as X;

/**
 * The columns of the Hepatitis Excel exports (View Requests and Export Results),
 * kept in one list so both exports give the same sheet. The rules are the ones
 * on VlExportService: form fields shown as the form shows them, plus record
 * metadata; lab and audit columns only when asked for.
 */
final class HepatitisExportService extends AbstractSampleExportService
{
    protected function idColumn(): string
    {
        return 'hepatitis_id';
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
            X::text(_translate('Testing Lab Name'), 'lab_name'),
            X::date(_translate('Sample Received On'), 'sample_received_at_lab_datetime', true),
            X::text(_translate('Health Facility Name'), 'facility_name'),
            X::text(_translate('Health Facility Code'), 'facility_code'),
            X::text(_translate('District/County'), 'facility_district'),
            X::text(_translate('Province/State'), 'facility_state'),

            X::text(_translate('Patient ID'), 'patient_id', ['pii' => true]),
            X::text(_translate('Patient Name'), 'patient_full_name', ['pii' => true]),
            X::date(_translate('Patient DoB'), 'patient_dob'),
            X::text(_translate('Patient Age'), 'patient_age'),
            X::sex(_translate('Patient Sex'), 'patient_gender'),
            X::date(_translate('Sample Collection Date'), 'sample_collection_date', true),

            ...X::rejectionColumns(),
            X::date(_translate('Sample Tested On'), 'sample_tested_datetime', true),
            X::text(_translate('Testing Platform'), 'hepatitis_test_platform', $detail),
            X::text(_translate('HCV VL Result'), 'hcv_vl_count'),
            X::text(_translate('HBV VL Result'), 'hbv_vl_count'),
            // The heading this sheet has always had; the value is the date the result was printed.
            X::date(_translate('Date Result Dispatched'), 'result_printed_datetime', true),
            X::choice(_translate('Result Status'), 'status_name'),
            X::text(_translate('Comments'), 'lab_tech_comments'),
            X::text(_translate('Funding Source'), 'funding_source_name'),
            X::text(_translate('Implementing Partner'), 'i_partner_name'),
            // The printed date and the status are above, where the sheet always had them.
            ...X::without(
                X::recordColumns(),
                _translate('Result Printed Date'),
                _translate('Sample Status')
            ),
        ];
    }

    protected function fetchDetails(array $ids, string $key): array
    {
        $shared = X::sharedDetailsSql('vl');
        $rows = $this->db->rawQuery("SELECT vl.*, {$shared['select']},
                    rs.rejection_reason_name
                FROM form_hepatitis AS vl
                {$shared['joins']}
                LEFT JOIN r_hepatitis_sample_rejection_reasons AS rs
                    ON rs.rejection_reason_id = vl.reason_for_sample_rejection
                WHERE " . $this->detailsWhere($ids));

        $details = [];
        foreach ($rows as $row) {
            $row = X::prepareShared($row, 'reason_for_changing');
            $row = X::decryptFields($row, ['patient_id', 'patient_name', 'patient_surname'], $key);
            $row['patient_full_name'] = X::fullName($row['patient_name'], $row['patient_surname']);
            $details[(int) $row['hepatitis_id']] = $row;
        }
        return $details;
    }
}
