<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\Covid19Service;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);
$covid19Results = $covid19Service->getCovid19Results();

/* Global config data */
$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();
$sQuery = $_SESSION['covid19RequestSearchResultQuery'];

$rResult = $db->rawQuery($sQuery);


$output = [];

if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
    $headings = array(_("S. No."), _("Sample Code"), _("Remote Sample Code"), _("Testing Lab Name"), _("Testing Point"), _("Lab staff Assigned"), _("Source Of Alert / POE"), _("Health Facility/POE County"), _("Health Facility/POE State"), _("Health Facility/POE"), _("Case ID"), _("Patient Name"), _("Patient DoB"), _("Patient Age"), _("Patient Gender"), _("Is Patient Pregnant"), _("Patient Phone No."), _("Patient Email"), _("Patient Address"), _("Patient State"), _("Patient County"), _("Patient City/Village"), _("Nationality"), _("Fever/Temperature"), _("Temprature Measurement"), _("Symptoms Detected"), _("Medical History"), _("Comorbidities"), _("Recenty Hospitalized?"), _("Patient Lives With Children"), _("Patient Cares for Children"), _("Close Contacts"), _("Has Recent Travel History"), _("Country Names"), _("Travel Return Date"), _("Airline"), _("Seat No."), _("Arrival Date/Time"), _("Departure Airport"), _("Transit"), _("Reason of Visit"), _("Number of Days Sick"), _("Date of Symptoms Onset"), _("Date of Initial Consultation"), _("Sample Collection Date"), _("Reason for Test Request"), _("Date specimen received"), _("Date specimen registered"), _("Specimen Condition"), _("Specimen Status"), _("Specimen Type"), _("Sample Tested Date"), _("Testing Platform"), _("Test Method"), _("Result"), _("Date result released"));
} else {
    $headings = array(_("S. No."), _("Sample Code"), _("Remote Sample Code"), _("Testing Lab Name"), _("Testing Point"), _("Lab staff Assigned"), _("Source Of Alert / POE"), _("Health Facility/POE County"), _("Health Facility/POE State"), _("Health Facility/POE"), _("Patient DoB"), _("Patient Age"), _("Patient Gender"), _("Is Patient Pregnant"), _("Patient Phone No."), _("Patient Email"), _("Patient Address"), _("Patient State"), _("Patient County"), _("Patient City/Village"), _("Nationality"), _("Fever/Temperature"), _("Temprature Measurement"), _("Symptoms Detected"), _("Medical History"), _("Comorbidities"), _("Recenty Hospitalized?"), _("Patient Lives With Children"), _("Patient Cares for Children"), _("Close Contacts"), _("Has Recent Travel History"), _("Country Names"), _("Travel Return Date"), _("Airline"), _("Seat No."), _("Arrival Date/Time"), _("Departure Airport"), _("Transit"), _("Reason of Visit"), _("Number of Days Sick"), _("Date of Symptoms Onset"), _("Date of Initial Consultation"), _("Sample Collection Date"), _("Reason for Test Request"), _("Date specimen received"), _("Date specimen registered"), _("Specimen Condition"), _("Specimen Status"), _("Specimen Type"), _("Sample Tested Date"), _("Testing Platform"), _("Test Method"), _("Result"), _("Date result released"));
}
if ($_SESSION['instanceType'] == 'standalone' && ($key = array_search("Remote Sample Code", $headings)) !== false) {
    unset($headings[$key]);
}


$no = 1;
foreach ($rResult as $aRow) {
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
    $squery = "SELECT s.*, como.* FROM form_covid19 as c19
        INNER JOIN covid19_patient_comorbidities AS como ON c19.covid19_id = como.covid19_id
        INNER JOIN r_covid19_comorbidities AS s ON como.comorbidity_id = s.comorbidity_id
        WHERE como.comorbidity_detected like 'yes' AND c19.covid19_id = ?";
    $result = $db->rawQuery($squery, [$aRow['covid19_id']]);
    foreach ($result as $como) {
        $comorbiditiesList[] = $como['comorbidity_name'];
    }

    $row = [];
    $testPlatform = null;
    $testMethod = null;
    // Get testing platform and test method
    $covid19TestQuery = "SELECT * FROM covid19_tests WHERE covid19_id= ? ORDER BY test_id DESC LIMIT 1";
    $covid19TestInfo = $db->rawQueryOne($covid19TestQuery, [$aRow['covid19_id']]);
    if (!empty($covid19TestInfo)) {
        ob_start();
        var_dump($covid19TestInfo);
        error_log(ob_get_clean());
        foreach ($covid19TestInfo as $indexKey => $rows) {
            $testPlatform = $rows['testing_platform'] ?? null;
            $testMethod = $rows['test_name'] ?? null;
        }
    }

    //set gender
    $gender = '';
    if ($aRow['patient_gender'] == 'male') {
        $gender = 'M';
    } elseif ($aRow['patient_gender'] == 'female') {
        $gender = 'F';
    } elseif ($aRow['patient_gender'] == 'not_recorded') {
        $gender = 'Unreported';
    }

    //set sample rejection
    $sampleRejection = 'No';
    if (trim($aRow['is_sample_rejected']) == 'yes' || ($aRow['reason_for_sample_rejection'] != null && trim($aRow['reason_for_sample_rejection']) != '' && $aRow['reason_for_sample_rejection'] > 0)) {
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
        $sourceOfArtPOE = str_replace("-", " ", $aRow['source_of_alert']);
    } else {
        $sourceOfArtPOE = $aRow['source_of_alert_other'];
    }

    $row[] = $no;
    $row[] = $aRow["sample_code"];
    $row[] = $aRow["remote_sample_code"];
    $row[] = ($aRow['lab_name']);
    $row[] = ($aRow['testing_point']);
    $row[] = ($aRow['labTechnician']);
    $row[] = ($sourceOfArtPOE);
    $row[] = ($aRow['facility_district']);
    $row[] = ($aRow['facility_state']);
    $row[] = ($aRow['facility_name']);
    if (isset($_POST['patientInfo']) && $_POST['patientInfo'] == 'yes') {
        $row[] = $aRow['patient_id'];
        $row[] = $patientFname . " " . $patientLname;
    }
    $row[] = DateUtility::humanReadableDateFormat($aRow['patient_dob']);
    $row[] = ($aRow['patient_age'] != null && trim($aRow['patient_age']) != '' && $aRow['patient_age'] > 0) ? $aRow['patient_age'] : 0;
    $row[] = ($aRow['patient_gender']);
    $row[] = ($aRow['is_patient_pregnant']);
    $row[] = ($aRow['patient_phone_number']);
    $row[] = ($aRow['patient_email']);
    $row[] = ($aRow['patient_address']);
    $row[] = ($aRow['patient_province']);
    $row[] = ($aRow['patient_district']);
    $row[] = ($aRow['patient_city']);
    $row[] = ($aRow['nationality']);
    $row[] = $aRow['fever_temp'];
    $row[] = $aRow['temperature_measurement_method'];
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
    $row[] = DateUtility::humanReadableDateFormat($aRow['sample_collection_date']);
    $row[] = ($aRow['test_reason_name']);
    $row[] = DateUtility::humanReadableDateFormat($aRow['sample_received_at_vl_lab_datetime']);
    $row[] = DateUtility::humanReadableDateFormat($aRow['request_created_datetime']);
    $row[] = ($aRow['sample_condition']);
    $row[] = ($aRow['status_name']);
    $row[] = ($aRow['sample_name']);
    $row[] = DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime']);
    $row[] = ($aRow['covid19_test_platform']);
    $row[] = ($aRow['covid19_test_name']);
    $row[] = $covid19Results[$aRow['result']] ?? $aRow['result'];
    $row[] = DateUtility::humanReadableDateFormat($aRow['result_printed_datetime']);

    $output[] = $row;
    $no++;
}

if (isset($_SESSION['covid19RequestSearchResultQueryCount']) && $_SESSION['covid19RequestSearchResultQueryCount'] > 5000) {

    $fileName = TEMP_PATH . DIRECTORY_SEPARATOR . 'Covid-19-Requests-' . date('d-M-Y-H-i-s') . '.csv';
    $file = new SplFileObject($fileName, 'w');
    $file->setCsvControl(",", "\r\n");
    $file->fputcsv($headings);
    foreach ($output as $row) {
        $file->fputcsv($row);
    }
    // we dont need the $file variable anymore
    $file = null;
    echo base64_encode($fileName);
} else {
    $excel = new Spreadsheet();
    $sheet = $excel->getActiveSheet();


    $colNo = 1;

    $nameValue = '';
    foreach ($_POST as $key => $value) {
        if (trim($value) != '' && trim($value) != '-- Select --') {
            $nameValue .= str_replace("_", " ", $key) . " : " . $value . "&nbsp;&nbsp;";
        }
    }
    $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '1', html_entity_decode($nameValue));
    if ($_POST['withAlphaNum'] == 'yes') {
        foreach ($headings as $field => $value) {
            $string = str_replace(' ', '', $value);
            $value = preg_replace('/[^A-Za-z0-9\-]/', '', $string);
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '3', html_entity_decode($value));
            $colNo++;
        }
    } else {
        foreach ($headings as $field => $value) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . '3', html_entity_decode($value));
            $colNo++;
        }
    }

    foreach ($output as $rowNo => $rowData) {
        $colNo = 1;
        $rRowCount = $rowNo + 4;
        foreach ($rowData as $field => $value) {
            $sheet->setCellValue(Coordinate::stringFromColumnIndex($colNo) . $rRowCount, html_entity_decode($value));
            $colNo++;
        }
    }
    $writer = IOFactory::createWriter($excel, 'Xlsx');
    $filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'Covid-19-Requests-' . date('d-M-Y-H-i-s') . '.xlsx';
    $writer->save($filename);
    echo base64_encode($filename);
}
