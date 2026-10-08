<?php

namespace App\Services;

use App\Abstracts\AbstractSampleExportService;
use App\Utilities\SampleExportUtility as X;

use const COUNTRY\DRC;
use const COUNTRY\CAMEROON;

/**
 * The columns of the EID Excel exports (View Requests and Export Results), kept
 * in one list so both exports give the same sheet. The rules are the ones on
 * VlExportService: form fields shown as the form shows them, plus record
 * metadata; one list for every country, with country-only fields tagged.
 */
final class EidExportService extends AbstractSampleExportService
{
    public function __construct(
        DatabaseService $db,
        CommonService $general,
        private readonly EidService $eidService
    ) {
        parent::__construct($db, $general);
    }

    protected function idColumn(): string
    {
        return 'eid_id';
    }

    /**
     * In the order of the request and result forms: facility and request,
     * mother, child, infant care, sample, lab, record.
     */
    protected function allColumns(): array
    {
        $drc = ['forms' => [DRC]];
        $notDrc = ['exceptForms' => [DRC]];
        $eidResults = $this->eidService->getEidResults();

        return [
            X::column(_translate('S.No.'), static fn(array $r, int $no): int => $no),
            X::text(_translate('Sample ID'), 'sample_code'),
            X::text(_translate('Remote Sample ID'), 'remote_sample_code', ['sts' => true]),
            X::text(_translate('Batch Code'), 'batch_code'),
            X::text(_translate('Testing Lab'), 'lab_name'),
            X::text(_translate('Lab Assigned Code'), 'lab_assigned_code', ['forms' => [CAMEROON]]),
            X::date(_translate('Sample Reception Date'), 'sample_received_at_lab_datetime', true),
            X::text(_translate('Health Facility Name'), 'facility_name'),
            X::text(_translate('Health Facility Code'), 'facility_code'),
            X::text(_translate('District/County'), 'facility_district'),
            X::text(_translate('Province/State'), 'facility_state'),
            X::text(_translate('Email'), 'infant_email', $drc),
            X::date(_translate('Request Date'), 'test_request_date', false, $drc),
            X::text(_translate('Requesting Clinician'), 'clinician_name'),
            X::text(_translate("Clinician's Phone Number"), 'request_clinician_phone_number'),
            X::text(_translate('Funding Source'), 'funding_source_name'),
            X::text(_translate('Implementing Partner'), 'i_partner_name'),

            X::text(_translate('Mother ID'), 'mother_id', ['pii' => true]),
            X::text(_translate('Mother Name'), 'mother_full_name', ['pii' => true] + $drc),
            X::date(_translate('Mother Date of Birth'), 'mother_dob', false, $drc),
            X::choice(_translate('Mother Marital Status'), 'mother_marital_status', $drc, [
                'single' => 'Single',
                'married' => 'Married',
                'cohabitating' => 'Cohabitating',
                'cohabitati' => 'Cohabitating',
                'widow' => 'Widow',
                'unknown' => 'Unknown',
            ]),
            X::withOther(_translate('Mother Treatment'), 'mother_treatment', 'mother_treatment_other', $drc),
            X::text(_translate('Mother CD4'), 'mother_cd4', $drc),
            X::text(_translate('Mother Viral Load Result'), 'mother_vl_result', $drc),

            X::text(_translate('Child ID'), 'child_id', ['pii' => true]),
            X::text(_translate('Child Name'), 'child_full_name', ['pii' => true]),
            X::text(_translate('Child Phone Number'), 'infant_phone', $drc),
            X::date(_translate('Child Date of Birth'), 'child_dob'),
            X::text(_translate('Child Age in Months'), 'child_age'),
            X::text(_translate('Child Age in Weeks'), 'child_age_in_weeks', $drc),
            X::text(_translate('Child Age in Days'), 'child_age_in_days', $drc),
            X::sex(_translate('Child Sex'), 'child_gender'),

            X::withOther(_translate('Child Treatment'), 'child_treatment', null, $drc),
            X::choice(_translate('Is Infant Receiving Treatment?'), 'is_infant_receiving_treatment', $drc),
            X::choice(_translate('Specific Infant Treatment'), 'specific_infant_treatment', $drc),
            X::yesNo(_translate('Has Infant Stopped Breastfeeding?'), 'has_infant_stopped_breastfeeding'),
            X::text(_translate('Age Breastfeeding Stopped (Months)'), 'age_breastfeeding_stopped_in_months', $drc),
            X::choice(_translate('Choice of Feeding'), 'choice_of_feeding', $drc),
            X::choice(
                _translate('Is Cotrimoxazole Being Administered to the Infant?'),
                'is_cotrimoxazole_being_administered_to_the_infant',
                $drc,
                ['no' => 'No', 'yes' => 'Yes']
            ),
            X::yesNo(_translate('PCR Test Performed Before'), 'pcr_test_performed_before', $notDrc),
            X::text(_translate('Last PCR Test results'), 'previous_pcr_result', $notDrc),
            X::choice(_translate('Reason For PCR Test'), 'reason_for_pcr'),

            X::date(_translate('Sample Collection Date'), 'sample_collection_date', true),
            X::text(_translate('Sample Type'), 'sample_name'),
            X::text(_translate('Sample Requestor Name'), 'sample_requestor_name', $drc),
            X::text(_translate('Sample Requestor Phone Number'), 'sample_requestor_phone'),
            X::text(_translate('EID Number'), 'eid_number', $notDrc),
            X::yesNo(_translate('Rapid Test Performed?'), 'rapid_test_performed', $drc),
            X::date(_translate('Rapid Test Date'), 'rapid_test_date', false, $drc),
            X::choice(_translate('Rapid Test Result'), 'rapid_test_result', $drc, $eidResults),
            ...X::storageColumns($drc),

            ...X::rejectionColumns(),
            X::text(_translate('Recommended Corrective Action'), 'recommended_corrective_action_name', $notDrc),
            X::text(_translate('Testing Platform'), 'eid_test_platform'),
            X::text(_translate('Assay'), 'assay_name'),
            X::date(_translate('Sample Tested On'), 'sample_tested_datetime', true),
            X::choice(_translate('Result'), 'result', [], $eidResults),
            X::text(_translate('Comments'), 'lab_tech_comments', $notDrc),
            ...X::recordColumns(),
        ];
    }

    protected function fetchDetails(array $ids, string $key): array
    {
        $shared = X::sharedDetailsSql('vl');
        $rows = $this->db->rawQuery("SELECT vl.*, {$shared['select']},
                    s.sample_name,
                    rs.rejection_reason_name,
                    rca.recommended_corrective_action_name
                FROM form_eid AS vl
                {$shared['joins']}
                LEFT JOIN r_eid_sample_type AS s ON s.sample_id = vl.specimen_type
                LEFT JOIN r_eid_sample_rejection_reasons AS rs
                    ON rs.rejection_reason_id = vl.reason_for_sample_rejection
                LEFT JOIN r_recommended_corrective_actions AS rca
                    ON rca.recommended_corrective_action_id = vl.recommended_corrective_action
                WHERE " . $this->detailsWhere($ids));

        $details = [];
        foreach ($rows as $row) {
            $row = X::prepareShared($row, 'reason_for_changing');
            $row = X::decryptFields(
                $row,
                ['child_id', 'child_name', 'child_surname', 'mother_id', 'mother_name', 'mother_surname'],
                $key
            );
            $row['child_full_name'] = X::fullName($row['child_name'], $row['child_surname']);
            $row['mother_full_name'] = X::fullName($row['mother_name'], $row['mother_surname']);
            $details[(int) $row['eid_id']] = $row;
        }
        return $details;
    }
}
