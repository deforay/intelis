<?php

namespace App\Services;

use App\Abstracts\AbstractSampleExportService;
use App\Utilities\JsonUtility;
use App\Utilities\SampleExportUtility as X;

use const COUNTRY\DRC;
use const COUNTRY\RWANDA;

/**
 * The columns of the COVID-19 Excel exports (View Requests and Export Results),
 * kept in one list so both exports give the same sheet. The rules are the ones
 * on VlExportService: form fields shown as the form shows them, plus record
 * metadata; one list for every country, with country-only fields tagged; lab
 * and audit columns only when asked for.
 *
 * DRC and Rwanda used to have their own Export Results sheets. Their extra
 * columns are tagged with their form here.
 */
final class Covid19ExportService extends AbstractSampleExportService
{
    public function __construct(
        DatabaseService $db,
        CommonService $general,
        private readonly Covid19Service $covid19Service
    ) {
        parent::__construct($db, $general);
    }

    protected function idColumn(): string
    {
        return 'covid19_id';
    }

    /**
     * In the order of the request and result forms: lab and facility, patient,
     * clinical history and travel, sample, lab, record.
     */
    protected function allColumns(): array
    {
        $drc = ['forms' => [DRC]];
        $rwanda = ['forms' => [RWANDA]];
        $detail = ['detail' => true];

        return [
            X::column(_translate('S. No.'), static fn(array $r, int $no): int => $no),
            X::text(_translate('Sample ID'), 'sample_code'),
            X::text(_translate('Remote Sample ID'), 'remote_sample_code', ['sts' => true]),
            X::text(_translate('Batch Code'), 'batch_code', $detail),
            X::text(_translate('Testing Lab Name'), 'lab_name'),
            X::date(_translate('Date specimen received'), 'sample_received_at_lab_datetime', true),
            X::text(_translate('Testing Point'), 'testing_point'),
            X::text(_translate('Lab staff Assigned'), 'lab_technician_name'),
            X::text(_translate('Number of Times Tested'), 'test_number', $drc),
            X::text(_translate('Source Of Alert / POE'), 'source_of_alert_label'),
            X::text(_translate('Health Facility/POE County'), 'facility_district'),
            X::text(_translate('Health Facility/POE State'), 'facility_state'),
            X::text(_translate('Health Facility/POE'), 'facility_name'),
            X::text(_translate('Health Facility Code'), 'facility_code', $rwanda),

            X::text(_translate('Case ID'), 'patient_id', ['pii' => true]),
            X::text(_translate('Patient Name'), 'patient_full_name', ['pii' => true]),
            X::date(_translate('Patient DoB'), 'patient_dob'),
            X::text(_translate('Patient Age'), 'patient_age'),
            X::sex(_translate('Patient Sex'), 'patient_gender'),
            X::yesNo(_translate('Is Patient Pregnant'), 'is_patient_pregnant'),
            X::text(_translate('Patient Phone Number'), 'patient_phone_number'),
            X::text(_translate('Patient Email'), 'patient_email'),
            X::text(_translate('Patient Address'), 'patient_address'),
            X::text(_translate('Patient State'), 'patient_province'),
            X::text(_translate('Patient County'), 'patient_district'),
            X::text(_translate('Patient City/Village'), 'patient_city'),
            X::text(_translate('Nationality'), 'nationality'),

            X::text(_translate('Fever/Temperature'), 'fever_temp'),
            X::text(_translate('Temprature Measurement'), 'temperature_measurement_method'),
            X::text(_translate('Respiratory Rate'), 'respiratory_rate', $drc),
            X::text(_translate('Oxygen Saturation'), 'oxygen_saturation', $drc),
            X::yesNo(_translate('Asymptomatic'), 'asymptomatic', $drc),
            X::text(_translate('Symptoms Detected'), 'symptoms'),
            X::text(_translate('Medical History'), 'medical_history'),
            X::text(_translate('Comorbidities'), 'comorbidities'),
            X::yesNo(_translate('Recenty Hospitalized?'), 'recent_hospitalization'),
            X::yesNo(_translate('Patient Lives With Children'), 'patient_lives_with_children'),
            X::yesNo(_translate('Patient Cares for Children'), 'patient_cares_for_children'),
            X::text(_translate('Close Contacts'), 'close_contacts'),
            X::yesNo(_translate('Has Recent Travel History'), 'has_recent_travel_history'),
            X::text(_translate('Country Names'), 'travel_country_names'),
            X::date(_translate('Travel Return Date'), 'travel_return_date'),
            X::text(_translate('Airline'), 'flight_airline'),
            X::text(_translate('Seat No.'), 'flight_seat_no'),
            X::date(_translate('Arrival Date/Time'), 'flight_arrival_datetime', true),
            X::text(_translate('Departure Airport'), 'flight_airport_of_departure'),
            X::text(_translate('Transit'), 'flight_transit'),
            X::text(_translate('Reason of Visit'), 'reason_of_visit'),
            X::text(_translate('Number of Days Sick'), 'number_of_days_sick'),
            X::date(_translate('Date of Symptoms Onset'), 'date_of_symptom_onset'),
            X::date(_translate('Date of Initial Consultation'), 'date_of_initial_consultation'),

            X::date(_translate('Sample Collection Date'), 'sample_collection_date', true),
            X::text(_translate('Reason for Test Request'), 'test_reason_name'),
            X::text(_translate('Reason for Test Request Details'), 'test_reason_details', $drc),
            X::date(_translate('Date specimen registered'), 'request_created_datetime', true),
            X::text(_translate('Specimen Condition'), 'sample_condition'),
            X::choice(_translate('Specimen Status'), 'status_name'),
            X::text(_translate('Specimen Type'), 'sample_name'),

            ...X::rejectionColumns(['exceptForms' => [DRC]]),
            X::text(_translate('Recommended Corrective Action'), 'recommended_corrective_action_name', $rwanda),
            X::date(_translate('Sample Tested Date'), 'sample_tested_datetime', true),
            X::text(_translate('Testing Platform'), 'test_platform'),
            X::text(_translate('Test Method'), 'test_method'),
            X::label(_translate('Result'), 'result', $this->covid19Service->getCovid19Results()),
            X::date(_translate('Date result released'), 'result_printed_datetime', true),
            X::text(_translate('Comments'), 'lab_tech_comments', $rwanda),
            X::text(_translate('Funding Source'), 'funding_source_name', $rwanda),
            X::text(_translate('Implementing Partner'), 'i_partner_name', $rwanda),
            // The registration date, the status and the printed date are above,
            // under the headings this sheet has always had.
            ...X::without(
                X::recordColumns(),
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
                    rca.recommended_corrective_action_name,
                    c.iso_name AS nationality,
                    u_lab.user_name AS lab_technician_name
                FROM form_covid19 AS vl
                {$shared['joins']}
                LEFT JOIN user_details AS u_lab ON u_lab.user_id = vl.lab_technician
                LEFT JOIN r_covid19_sample_type AS s ON s.sample_id = vl.specimen_type
                LEFT JOIN r_covid19_test_reasons AS tr ON tr.test_reason_id = vl.reason_for_covid19_test
                LEFT JOIN r_covid19_sample_rejection_reasons AS rs
                    ON rs.rejection_reason_id = vl.reason_for_sample_rejection
                LEFT JOIN r_recommended_corrective_actions AS rca
                    ON rca.recommended_corrective_action_id = vl.recommended_corrective_action
                LEFT JOIN r_countries AS c ON c.id = vl.patient_nationality
                WHERE " . $this->detailsWhere($ids));

        $in = $this->db->inIntList($ids);
        $symptoms = $this->namesBySample("SELECT ps.covid19_id, s.symptom_name AS name
                FROM covid19_patient_symptoms AS ps
                INNER JOIN r_covid19_symptoms AS s ON s.symptom_id = ps.symptom_id
                WHERE ps.symptom_detected = 'yes' AND ps.covid19_id IN ($in)
                ORDER BY ps.covid19_id, s.symptom_name");
        $comorbidities = $this->namesBySample("SELECT pc.covid19_id, s.comorbidity_name AS name
                FROM covid19_patient_comorbidities AS pc
                INNER JOIN r_covid19_comorbidities AS s ON s.comorbidity_id = pc.comorbidity_id
                WHERE pc.comorbidity_detected = 'yes' AND pc.covid19_id IN ($in)
                ORDER BY pc.covid19_id, s.comorbidity_name");

        $reasonDetails = [];
        foreach ($this->db->rawQuery("SELECT covid19_id, reason_details FROM covid19_reasons_for_testing
                WHERE reasons_detected = 'yes' AND covid19_id IN ($in)") as $reason) {
            $detailsList = trim((string) $reason['reason_details']);
            $detailsList = $detailsList === '' ? [] : JsonUtility::decodeJson($detailsList);
            foreach (is_array($detailsList) ? $detailsList : [] as $detail) {
                if (is_scalar($detail) && trim((string) $detail) !== '') {
                    $reasonDetails[(int) $reason['covid19_id']][] = trim((string) $detail);
                }
            }
        }

        // Forms that record each test on its own row (South Sudan) leave the
        // sample's platform and method empty: the latest test's stand in.
        $latestTest = [];
        foreach ($this->db->rawQuery("SELECT covid19_id, testing_platform, test_name FROM covid19_tests
                WHERE covid19_id IN ($in) ORDER BY test_id ASC") as $test) {
            $latestTest[(int) $test['covid19_id']] = $test;
        }

        $details = [];
        foreach ($rows as $row) {
            $id = (int) $row['covid19_id'];
            $row = X::prepareShared($row, 'reason_for_changing');
            $row = X::decryptFields($row, ['patient_id', 'patient_name', 'patient_surname'], $key);
            $row['patient_full_name'] = X::fullName($row['patient_name'], $row['patient_surname']);
            $row['source_of_alert_label'] = ($row['source_of_alert'] ?? '') === 'others'
                ? (string) $row['source_of_alert_other']
                : str_replace('-', ' ', (string) $row['source_of_alert']);
            $row['symptoms'] = implode(', ', $symptoms[$id] ?? []);
            $row['comorbidities'] = implode(', ', $comorbidities[$id] ?? []);
            $row['test_reason_details'] = implode(', ', $reasonDetails[$id] ?? []);
            $row['test_platform'] = trim((string) $row['covid19_test_platform']) !== ''
                ? $row['covid19_test_platform']
                : ($latestTest[$id]['testing_platform'] ?? '');
            $row['test_method'] = trim((string) $row['covid19_test_name']) !== ''
                ? $row['covid19_test_name']
                : ($latestTest[$id]['test_name'] ?? '');
            $details[$id] = $row;
        }
        return $details;
    }

    /** @return array<int, list<string>> names keyed by sample id */
    private function namesBySample(string $query): array
    {
        $names = [];
        foreach ($this->db->rawQuery($query) as $row) {
            $names[(int) $row['covid19_id']][] = (string) $row['name'];
        }
        return $names;
    }
}
