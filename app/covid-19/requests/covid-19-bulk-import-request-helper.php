<?php

use App\Models\General;
use App\Models\Users;
use App\Utilities\DateUtils;
use PhpOffice\PhpSpreadsheet\IOFactory;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$arr = [];
$general = new General();
$usersModel = new Users();

$tableName = "form_covid19";
$testTableName = 'covid19_tests';
// echo "<pre>";print_r($_FILES);die;
try {
    $arr = $general->getGlobalConfig();


    $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['requestFile']['name']);
    $fileName = str_replace(" ", "-", $fileName);
    $ranNumber = General::generateRandomString(12);
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $fileName = $ranNumber . "." . $extension;

    if (!file_exists(TEMP_PATH . DIRECTORY_SEPARATOR . "import-request") && !is_dir(TEMP_PATH . DIRECTORY_SEPARATOR . "import-request")) {
        mkdir(TEMP_PATH . DIRECTORY_SEPARATOR . "import-request", 0777, true);
    }
    if (move_uploaded_file($_FILES['requestFile']['tmp_name'], TEMP_PATH . DIRECTORY_SEPARATOR . "import-request" . DIRECTORY_SEPARATOR . $fileName)) {

        $file_info = new finfo(FILEINFO_MIME); // object oriented approach!
        $mime_type = $file_info->buffer(file_get_contents(TEMP_PATH . DIRECTORY_SEPARATOR . "import-request" . DIRECTORY_SEPARATOR . $fileName)); // e.g. gives "image/jpeg"

        $spreadsheet = IOFactory::load(TEMP_PATH . DIRECTORY_SEPARATOR . "import-request" . DIRECTORY_SEPARATOR . $fileName);
        $sheetData   = $spreadsheet->getActiveSheet();
        $sheetData   = $sheetData->toArray(null, true, true, true);
        $returnArray = [];
        $resultArray = array_slice($sheetData, 1);

        foreach ($resultArray as $rowIndex => $rowData) {
            // echo "<pre>";print_r($rowData);die;
            if (isset($rowData['A']) && !empty($rowData['A'])) {
                $sampleCode         = $general->getDuplicateDataFromField('form_covid19', 'sample_code', $rowData['A']);
                $provinceId         = $general->getDuplicateDataFromField('geographical_divisions', 'geo_name', $rowData['D']);
                $facility           = $general->getDuplicateDataFromField('facility_details', 'facility_name', $rowData['F']);
                $testReason         = $general->getDuplicateDataFromField('r_covid19_test_reasons', 'test_reason_name', $rowData['AT']);
                $sampleType         = $general->getDuplicateDataFromField('r_covid19_sample_type', 'sample_name', $rowData['AV']);
                $labName            = $general->getDuplicateDataFromField('facility_details', 'facility_name', $rowData['AY'], 'facility_type');
                $rejectionReason    = $general->getDuplicateDataFromField('r_covid19_sample_rejection_reasons', 'rejection_reason_name', $rowData['BA']);
                $result             = $general->getDuplicateDataFromField('r_covid19_results', 'result', $rowData['BK']);
                $resultStatus       = $general->getDuplicateDataFromField('r_sample_status', 'status_name', $rowData['BO']);
                $labTechnician      = $usersModel->addUserIfNotExists($rowData['BR']);

                $sQuery = "SELECT vlsm_instance_id from s_vlsm_instance";
                $instanceId = $db->rawQueryOne($sQuery);

                if (trim($rowData['AU']) != '') {
                    $sampleCollectionDate = date('Y-m-d H:i:s', strtotime($rowData['AU']));
                } else {
                    $sampleCollectionDate = null;
                }

                if (trim($rowData['AX']) != '') {
                    $sampleReceivedDate = date('Y-m-d H:i:s', strtotime($rowData['AX']));
                } else {
                    $sampleReceivedDate = null;
                }


                $data = array(
                    'vlsm_instance_id'                      => $instanceId['vlsm_instance_id'],
                    'sample_code'                           => $rowData['A'],
                    'unique_id'                             => $general->generateUUID(),
                    'vlsm_country_id'                       => $arr['vl_form'],
                    'source_of_alert'                       => (isset($rowData['B']) && $rowData['B'] != "") ? strtolower(str_replace(" ", "-", $rowData['B'])) : null,
                    'source_of_alert_other'                 => $rowData['C'],
                    'province_id'                           => $provinceId['geo_id'],
                    'facility_id'                           => $facility['facility_id'] ?? null,
                    'patient_name'                          => $rowData['G'],
                    'patient_id'                            => $rowData['H'],
                    'external_sample_code'                  => $rowData['I'],
                    'patient_dob'                           => date('Y-m-d', strtotime($rowData['J'])),
                    'patient_age'                           => $rowData['K'],
                    'patient_gender'                        => strtolower($rowData['L']),
                    'patient_phone_number'                  => $rowData['M'],
                    'patient_email'                         => $rowData['N'],
                    'patient_address'                       => $rowData['O'],
                    'is_patient_pregnant'                   => $rowData['P'],
                    'patient_province'                      => $rowData['Q'],
                    'patient_district'                      => $rowData['R'],
                    'patient_city'                          => $rowData['S'],
                    'patient_nationality'                   => $rowData['T'],
                    'testing_point'                         => $rowData['U'],
                    // 'testing_point'                   => $rowData['V'],
                    'fever_temp'                            => $rowData['W'],
                    'temperature_measurement_method'        => $rowData['X'],
                    // 'testing_point'                   => $rowData['Y'],
                    'medical_history'                       => strtolower($rowData['Z']),
                    'recent_hospitalization'                => $rowData['AB'],
                    'patient_lives_with_children'           => $rowData['AC'],
                    'patient_cares_for_children'            => $rowData['AD'],
                    'close_contacts'                        => $rowData['AE'],
                    'has_recent_travel_history'             => $rowData['AF'],
                    'travel_country_names'                  => $rowData['AG'],
                    'travel_return_date'                    => $rowData['AH'],
                    'flight_airline'                        => $rowData['AI'],
                    'flight_seat_no'                        => $rowData['AJ'],
                    'flight_arrival_datetime'               => $rowData['AK'],
                    'flight_airport_of_departure'           => $rowData['AL'],
                    'flight_transit'                        => $rowData['AM'],
                    'reason_of_visit'                       => $rowData['AN'],
                    'number_of_days_sick'                   => $rowData['AO'],
                    'date_of_symptom_onset'                 => date('Y-m-d', strtotime($rowData['AP'])),
                    'date_of_initial_consultation'          => date('Y-m-d', strtotime($rowData['AQ'])),
                    'sample_registered_at_lab'              => date('Y-m-d', strtotime($rowData['AR'])),
                    'type_of_test_requested'                => $rowData['AS'],
                    'reason_for_covid19_test'               => $testReason['test_reason_id'] ?? null,
                    'sample_collection_date'                => $sampleCollectionDate,
                    'specimen_type'                         => $sampleType['sample_id'] ?? null,
                    'test_number'                           => $rowData['AW'],
                    'sample_received_at_vl_lab_datetime'    => $sampleReceivedDate,
                    'lab_id'                                => $labName['facility_id'] ?? null,
                    'is_sample_rejected'                    => strtolower($rowData['AZ']),
                    'reason_for_sample_rejection'           => (isset($rejectionReason['rejection_reason_id']) && $rejectionReason['rejection_reason_id'] != "") ? $rejectionReason['rejection_reason_id'] : 9999,
                    'rejection_on'                          => date('Y-m-d', strtotime($rowData['BB'])),
                    'result'                                => $result['result_id'],
                    'is_result_authorised'                  => strtolower($rowData['BL']),
                    'authorized_by'                         => ($rowData['BM']),
                    'authorized_on'                         => date('Y-m-d', strtotime($rowData['BN'])),
                    'last_modified_datetime'                => DateUtils::getCurrentDateTime(),
                    'last_modified_by'                      => $_SESSION['userId'],
                    'result_status'                         => $resultStatus['status_id'] ?? null,
                    'sample_condition'                      => strtolower($rowData['BP']),
                    'patient_passport_number'               => $rowData['BQ'],
                    'lab_technician'                        => $labTechnician ?? null,
                );

                // echo "<pre>";print_r($data);die;
                if (!$sampleCode) {
                    $lastId = $db->insert($tableName, $data);
                } else {
                    $lastId = $sampleCode['covid19_id'];
                    $db = $db->where('covid19_id', $lastId);
                    $db->update($tableName, $data);
                }

                $testData[0]['testRequest']     = $rowData['AA'];
                $testData[0]['testDate']        = $rowData['AB'];
                $testData[0]['testingPlatform'] = $rowData['AC'];
                $testData[0]['testResult']      = $rowData['AD'];
                $testData[1]['testRequest']     = $rowData['AE'];
                $testData[1]['testDate']        = $rowData['AF'];
                $testData[1]['testingPlatform'] = $rowData['AG'];
                $testData[1]['testResult']      = $rowData['AH'];
                if (count($testData) > 0) {
                    /* Delete if already exist */
                    $db = $db->where('covid19_id', $lastId);
                    $db->delete($testTableName);

                    foreach ($testData as $testKitName) {
                        if (trim($testKitName['testDate']) != '') {
                            $testDate = date('Y-m-d H:i', strtotime($testKitName['testDate']));
                        } else {
                            $testDate = null;
                        }

                        $covid19TestData = array(
                            'covid19_id'            => $lastId,
                            'test_name'             => $testKitName['testRequest'],
                            'facility_id'           => $labName['facility_id'] ?? null,
                            'testing_platform'      => $testKitName['testingPlatform'],
                            'sample_tested_datetime' => date('Y-m-d H:i:s', strtotime($testDate)),
                            'result'                => strtolower($testKitName['testResult']),
                        );
                        $db->insert($testTableName, $covid19TestData);
                        $covid19Data['sample_tested_datetime'] = date('Y-m-d H:i:s', strtotime($testDate));
                    }
                }
                $db = $db->where('covid19_id', $lastId);
                $id = $db->update($tableName, $covid19Data);
            }
        }
        $_SESSION['alertMsg'] = "Data imported successfully";
    }
    // echo "<pre>";print_r($returnArray);die;
    header("Location:/covid-19/requests/covid-19-requests.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
