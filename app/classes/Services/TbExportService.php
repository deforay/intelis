<?php

namespace App\Services;

use App\Abstracts\AbstractSampleExportService;
use App\Utilities\SampleExportUtility as X;

/**
 * The columns of the TB Excel exports (View Requests and Export Results), kept
 * in one list so both exports give the same sheet. The rules are the ones on
 * VlExportService: form fields shown as the form shows them, plus record
 * metadata; lab and audit columns only when asked for.
 */
final class TbExportService extends AbstractSampleExportService
{
    public function __construct(
        DatabaseService $db,
        CommonService $general,
        private readonly TbService $tbService
    ) {
        parent::__construct($db, $general);
    }

    protected function idColumn(): string
    {
        return 'tb_id';
    }

    /** In the order of the request and result forms. */
    protected function allColumns(): array
    {
        $detail = ['detail' => true];

        return [
            X::column(_translate('S. No.'), static fn(array $r, int $no): int => $no),
            X::text(_translate('Sample ID'), 'sample_code'),
            X::text(_translate('Remote Sample ID'), 'remote_sample_code', ['sts' => true]),
            X::text(_translate('Batch Code'), 'batch_code', $detail),
            X::text(_translate('Testing Lab Name'), 'lab_name'),
            X::date(_translate('Date specimen Received'), 'sample_received_at_lab_datetime', true),
            X::text(_translate('Lab staff Assigned'), 'tested_by_name'),
            X::text(_translate('Health Facility/POE County'), 'facility_district'),
            X::text(_translate('Health Facility/POE State'), 'facility_state'),
            X::text(_translate('Health Facility/POE'), 'facility_name'),

            X::text(_translate('Case ID'), 'patient_id', ['pii' => true]),
            X::text(_translate('Patient Name'), 'patient_full_name', ['pii' => true]),
            X::date(_translate('Patient DoB'), 'patient_dob'),
            X::text(_translate('Patient Age'), 'patient_age'),
            X::sex(_translate('Patient Sex'), 'patient_gender'),
            X::date(_translate('Date specimen collected'), 'sample_collection_date', true),
            X::text(_translate('Reason for Test Request'), 'test_reason_name'),
            X::date(_translate('Date specimen Entered'), 'request_created_datetime', true),
            X::choice(_translate('Specimen Status'), 'status_name'),
            X::text(_translate('Specimen Type'), 'sample_name'),

            ...X::rejectionColumns(),
            X::text(_translate('Recommended Corrective Action'), 'recommended_corrective_action_name'),
            X::date(_translate('Date specimen Tested'), 'sample_tested_datetime', true),
            X::text(_translate('Testing Platform'), 'tb_test_platform'),
            X::text(_translate('Test Method'), 'test_methods'),
            X::label(_translate('Result'), 'result', $this->tbService->getTbResults()),
            X::date(_translate('Date result released'), 'result_printed_datetime', true),
            // Who tested, the entry date, the status and the printed date are above,
            // under the headings this sheet has always had.
            ...X::without(
                X::recordColumns(),
                _translate('Tested By'),
                _translate('Sample Status'),
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
                FROM form_tb AS vl
                {$shared['joins']}
                LEFT JOIN r_tb_sample_type AS s ON s.sample_id = vl.specimen_type
                LEFT JOIN r_tb_test_reasons AS tr ON tr.test_reason_id = vl.reason_for_tb_test
                LEFT JOIN r_tb_sample_rejection_reasons AS rs ON rs.rejection_reason_id = vl.reason_for_sample_rejection
                LEFT JOIN r_recommended_corrective_actions AS rca
                    ON rca.recommended_corrective_action_id = vl.recommended_corrective_action
                WHERE " . $this->detailsWhere($ids));

        // The method is each test's type, in test order, once each.
        $methods = [];
        foreach ($this->db->rawQuery("SELECT tb_id, test_type FROM tb_tests
                WHERE tb_id IN (" . $this->db->inIntList($ids) . ")
                ORDER BY tb_test_id ASC") as $test) {
            $type = trim((string) $test['test_type']);
            if ($type !== '') {
                $methods[(int) $test['tb_id']][$type] = $type;
            }
        }

        $details = [];
        foreach ($rows as $row) {
            $id = (int) $row['tb_id'];
            $row = X::prepareShared($row, 'reason_for_changing');
            $row = X::decryptFields($row, ['patient_id', 'patient_name', 'patient_surname'], $key);
            $row['patient_full_name'] = X::fullName($row['patient_name'], $row['patient_surname']);
            $row['test_methods'] = implode(', ', $methods[$id] ?? []);
            $details[$id] = $row;
        }
        return $details;
    }
}
