<?php


use const COUNTRY\SOUTH_SUDAN;
use App\Services\Covid19Service;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);
$covid19Results = $covid19Service->getCovid19Results();

/* Global config data */
$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();
$key = (string) $general->getGlobalConfig('key');


$delimiter = $arr['default_csv_delimiter'] ?? ',';
$enclosure = $arr['default_csv_enclosure'] ?? '"';

if (isset($_SESSION['covid19ResultQuery']) && trim((string) $_SESSION['covid19ResultQuery']) !== "") {

    $output = [];

    $headings = [_translate("S. No."), _translate("Sample ID"), _translate("Remote Sample ID"), _translate("Testing Lab Name"), _translate("Tested By"), _translate("Number of Times Tested"), _translate("District"), _translate("State"), _translate("Collection Site"), _translate("EPID Number"), _translate("Patient Name"), _translate("Patient DoB"), _translate("Patient Age"), _translate("Patient Sex"), _translate("Is Patient Pregnant"), _translate("Patient Phone Number"), _translate("Patient Email"), _translate("Patient Address"), _translate("Patient Province"), _translate('Commune'), _translate("Nationality"), _translate("Fever/Temperature"), _translate("Temprature Measurement"), _translate("Respiratory Rate"), _translate("Oxygen Saturation"), _translate("Asymptomatic"), _translate("Symptoms Detected"), _translate("Medical History"), _translate("Comorbidities"), _translate("Recenty Hospitalized?"), _translate("Patient Lives With Children"), _translate("Patient Cares for Children"), _translate("Close Contacts"), _translate("Has Recent Travel History"), _translate("Country Names"), _translate("Travel Return Date"), _translate("Airline"), _translate("Seat No."), _translate("Arrival Date/Time"), _translate("Departure Airport"), _translate("Transit"), _translate("Reason of Visit"), _translate("Number of Days Sick"), _translate("Date of Symptoms Onset"), _translate("Date of Initial Consultation"), _translate("Sample Collection Date"), _translate("Reason for Test Request"), _translate("Reason for Test Request"), _translate("Date specimen received"), _translate("Date specimen registered"), _translate("Specimen Condition"), _translate("Specimen Status"), _translate("Specimen Type"), _translate("Sample Tested Date"), _translate("Testing Platform"), _translate("Test Method"), _translate("Result"), _translate("Date result released")];

    if ($general->isStandaloneInstance() && ($key = array_search("Remote Sample ID", $headings)) !== false) {
        unset($headings[$key]);
    }

    if (isset($_POST['patientInfo']) && $_POST['patientInfo'] != 'yes') {
        $headings = array_values(array_diff($headings, [_translate("EPID Number"), _translate("Patient Name")]));
    }

    $no = 1;
    $resultSet = $db->rawQueryGenerator($_SESSION['covid19ResultQuery']);
    foreach ($resultSet as $aRow) {

        $symptomList = [];
        $squery = "SELECT s.*, ps.* FROM form_covid19 as c19
        INNER JOIN covid19_patient_symptoms AS ps ON c19.covid19_id = ps.covid19_id
        INNER JOIN r_covid19_symptoms AS s ON ps.symptom_id = s.symptom_id
        WHERE ps.symptom_detected like 'yes' AND c19.covid19_id = ?";
        $result = $db->rawQuery($squery, [$aRow['covid19_id']]);
        foreach ($result as $symp) {
            $symptomList[] = $symp['symptom_name'];
        }

        $comorbiditiesList = [];
        $squery = "SELECT s.*,como.*
                    FROM form_covid19 as c19
                    INNER JOIN covid19_patient_comorbidities AS como ON c19.covid19_id = como.covid19_id
                    INNER JOIN r_covid19_comorbidities AS s ON como.comorbidity_id = s.comorbidity_id
                    WHERE como.comorbidity_detected like 'yes' AND c19.covid19_id = ?";
        $result = $db->rawQuery($squery, [$aRow['covid19_id']]);
        foreach ($result as $como) {
            $comorbiditiesList[] = $como['comorbidity_name'];
        }

        $subReasonsList = null;
        $squery = "SELECT reas.*
                    FROM form_covid19 as c19
                    INNER JOIN covid19_reasons_for_testing AS reas ON c19.covid19_id = reas.covid19_id
                    WHERE reas.reasons_detected like 'yes'
                    AND c19.covid19_id = ?";
        $result = $db->rawQueryOne($squery[$aRow['covid19_id']]);

        $subReasonsList = json_decode((string) $result['reason_details']);
        $subReasonsList = implode(", ", $subReasonsList);


        $row = [];
        if ($arr['vl_form'] == SOUTH_SUDAN) {
            // Get testing platform and test method
            $covid19TestQuery = "SELECT * FROM covid19_tests
                                    WHERE covid19_id= ? ORDER BY test_id DESC LIMIT 1";
            $covid19TestInfo = $db->rawQueryOne($covid19TestQuery, [$aRow['covid19_id']]);
            foreach ($covid19TestInfo as $indexKey => $rows) {
                $testPlatform = $rows['testing_platform'];
                $testMethod = $rows['test_name'];
            }
        }

        //set gender
        $gender = '';
        if ($aRow['patient_gender'] == 'male') {
            $gender = 'M';
        } elseif ($aRow['patient_gender'] == 'female') {
            $gender = 'F';
        } elseif ($aRow['patient_gender'] == 'unreported') {
            $gender = 'Unreported';
        }

        //set sample rejection
        $sampleRejection = 'No';
        if (trim((string) $aRow['is_sample_rejected']) === 'yes' || ($aRow['reason_for_sample_rejection'] != null && trim((string) $aRow['reason_for_sample_rejection']) !== '' && $aRow['reason_for_sample_rejection'] > 0)) {
            $sampleRejection = 'Yes';
        }

        if (!empty($aRow['patient_name'])) {
            $patientFname = ($general->crypto('doNothing', $aRow['patient_name'], $aRow['patient_id']));
        } else {
            $patientFname = '';
        }
        if (!empty($aRow['patient_surname'])) {
            $patientLname = ($general->crypto('doNothing', $aRow['patient_surname'], $aRow['patient_id']));
        } else {
            $patientLname = '';
        }

        if (isset($aRow['source_of_alert']) && $aRow['source_of_alert'] != "others") {
            $sourceOfArtPOE = str_replace("-", " ", (string) $aRow['source_of_alert']);
        } else {
            $sourceOfArtPOE = $aRow['source_of_alert_other'];
        }
        if (!empty($aRow['is_encrypted']) && $aRow['is_encrypted'] == 'yes') {
            $aRow['patient_id'] = $general->crypto('decrypt', $aRow['patient_id'], $key);
            $patientFname = $general->crypto('decrypt', $patientFname, $key);
            $patientLname = $general->crypto('decrypt', $patientLname, $key);
        }

        $row[] = $no;
        if ($general->isStandaloneInstance()) {
            $row[] = $aRow["sample_code"];
        } else {
            $row[] = $aRow["sample_code"];
            $row[] = $aRow["remote_sample_code"];
        }
        $row[] = $aRow['lab_name'];
        $row[] = $aRow['labTechnician'];
        $row[] = $aRow['test_number'];
        $row[] = $aRow['facility_district'];
        $row[] = $aRow['facility_state'];
        $row[] = $aRow['facility_name'];
        if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
            $row[] = $aRow['patient_id'];
            $row[] = $patientFname . " " . $patientLname;
        }
        $row[] = DateUtility::humanReadableDateFormat($aRow['patient_dob']);
        $row[] = ($aRow['patient_age'] != null && trim((string) $aRow['patient_age']) !== '' && $aRow['patient_age'] > 0) ? $aRow['patient_age'] : 0;
        $row[] = $aRow['patient_gender'];
        $row[] = $aRow['is_patient_pregnant'];
        $row[] = $aRow['patient_phone_number'];
        $row[] = $aRow['patient_email'];
        $row[] = $aRow['patient_address'];
        $row[] = $aRow['patient_province'];
        $row[] = $aRow['patient_district'];
        $row[] = $aRow['nationality'];
        $row[] = $aRow['fever_temp'];
        $row[] = $aRow['temperature_measurement_method'];
        $row[] = $aRow['respiratory_rate'];
        $row[] = $aRow['oxygen_saturation'];
        $row[] = $aRow['asymptomatic'];
        $row[] = implode(", ", $symptomList);
        $row[] = $aRow['medical_history'];
        $row[] = implode(", ", $comorbiditiesList);
        $row[] = $aRow['recent_hospitalization'];
        $row[] = $aRow['patient_lives_with_children'];
        $row[] = $aRow['patient_cares_for_children'];
        $row[] = $aRow['close_contacts'];
        $row[] = $aRow['has_recent_travel_history'];
        $row[] = $aRow['travel_country_names'];
        $row[] = $aRow['travel_return_date'];
        $row[] = $aRow['flight_airline'];
        $row[] = $aRow['flight_seat_no'];
        $row[] = $aRow['flight_arrival_datetime'];
        $row[] = $aRow['flight_airport_of_departure'];
        $row[] = $aRow['flight_transit'];
        $row[] = $aRow['reason_of_visit'];
        $row[] = $aRow['number_of_days_sick'];
        $row[] = DateUtility::humanReadableDateFormat($aRow['date_of_symptom_onset']);
        $row[] = DateUtility::humanReadableDateFormat($aRow['date_of_initial_consultation']);
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date'] ?? '');
        $row[] = ($aRow['test_reason_name']);
        $row[] = $subReasonsList;
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '');
        $row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime']);
        $row[] = ($aRow['sample_condition']);
        $row[] = ($aRow['status_name']);
        $row[] = ($aRow['sample_name']);
        $row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '');
        $row[] = $aRow['covid19_test_platform'];
        $row[] = ($testMethod);
        $row[] = $covid19Results[$aRow['result']] ?? $aRow['result'];
        $row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime'] ?? '');

        $output[] = $row;
        $no++;
    }


    if (isset($_SESSION['covid19ResultQueryCount']) && $_SESSION['covid19ResultQueryCount'] > 50000) {

        $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'Covid-19-Export-Data-' . date('d-M-Y-H-i-s') . '.csv';
        $fileName = MiscUtility::generateCsv($headings, $output, $fileName, $delimiter, $enclosure);
        // we dont need the $output variable anymore
        unset($output);
        echo base64_encode((string) $fileName);
    } else {
        $excel = new Spreadsheet();
        $sheet = $excel->getActiveSheet();

        $sheet->fromArray($headings, null, 'A3');

        foreach ($output as $rowNo => $rowData) {
            $rRowCount = $rowNo + 4;
            $sheet->fromArray($rowData, null, 'A' . $rRowCount);
        }

        $writer = IOFactory::createWriter($excel, IOFactory::READER_XLSX);
        $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'Covid-19-Export-Data-' . date('d-M-Y-H-i-s') . '.xlsx';
        $writer->save($filename);
        echo urlencode(basename($filename));
    }
}
