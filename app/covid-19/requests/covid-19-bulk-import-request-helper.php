<?php

use App\Services\UsersService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use PhpOffice\PhpSpreadsheet\IOFactory;




$arr = [];
/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$tableName = "form_covid19";
$testTableName = 'covid19_tests';
// echo "<pre>";print_r($_FILES);die;
try {
    $arr = $general->getGlobalConfig();


    $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', (string) $_FILES['requestFile']['name']);
    $fileName = str_replace(" ", "-", $fileName);
    $ranNumber = MiscUtility::generateRandomString(12);
    $extension = MiscUtility::getFileExtension($fileName);
    $fileName = $ranNumber . "." . $extension;

    MiscUtility::makeDirectory(TEMP_PATH . DIRECTORY_SEPARATOR . "import-request");

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
            if (!empty($rowData['A'])) {
                $sampleCode         = $general->getDataFromOneFieldAndValue('form_covid19', 'sample_code', $rowData['A']);
                $provinceId         = $general->getDataFromOneFieldAndValue('geographical_divisions', 'geo_name', $rowData['D']);
                $facility           = $general->getDataFromOneFieldAndValue('facility_details', 'facility_name', $rowData['F']);
                $testReason         = $general->getDataFromOneFieldAndValue('r_covid19_test_reasons', 'test_reason_name', $rowData['AT']);
                $sampleType         = $general->getDataFromOneFieldAndValue('r_covid19_sample_type', 'sample_name', $rowData['AV']);
                $labName            = $general->getDataFromOneFieldAndValue('facility_details', 'facility_name', $rowData['AY'], 'facility_type = 2');
                $rejectionReason    = $general->getDataFromOneFieldAndValue('r_covid19_sample_rejection_reasons', 'rejection_reason_name', $rowData['BA']);
                $result             = $general->getDataFromOneFieldAndValue('r_covid19_results', 'result', $rowData['BK']);
                $resultStatus       = $general->getDataFromOneFieldAndValue('r_sample_status', 'status_name', $rowData['BO']);
                $labTechnician      = $usersService->getOrCreateUser($rowData['BR']);

                $instanceId = $general->getInstanceId();

                if (trim((string) $rowData['AU']) !== '') {
                    $sampleCollectionDate = DateUtility::isoDateFormat($rowData['AU'] ?? '', true);
                } else {
                    $sampleCollectionDate = null;
                }

                if (trim((string) $rowData['AX']) !== '') {
                    $sampleReceivedDate = DateUtility::isoDateFormat($rowData['AX'] ?? '', true);
                } else {
                    $sampleReceivedDate = null;
                }


                $data = [
                    'vlsm_instance_id'                      => $instanceId,
                    'sample_code'                           => $rowData['A'],
                    'unique_id'                             => MiscUtility::generateULID(),
                    'vlsm_country_id'                       => $arr['vl_form'],
                    'source_of_alert'                       => (isset($rowData['B']) && $rowData['B'] != "") ? strtolower(str_replace(" ", "-", (string) $rowData['B'])) : null,
                    'source_of_alert_other'                 => $rowData['C'],
                    'province_id'                           => $provinceId['geo_id'],
                    'facility_id'                           => $facility['facility_id'] ?? null,
                    'patient_name'                          => $rowData['G'],
                    'patient_id'                            => $rowData['H'],
                    'external_sample_code'                  => $rowData['I'],
                    'patient_dob'                           => DateUtility::isoDateFormat($rowData['J'] ?? ''),
                    'patient_age'                           => $rowData['K'],
                    'patient_gender'                        => strtolower((string) $rowData['L']),
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
                    'medical_history'                       => strtolower((string) $rowData['Z']),
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
                    'date_of_symptom_onset'                 => date('Y-m-d', strtotime((string) $rowData['AP'])),
                    'date_of_initial_consultation'          => date('Y-m-d', strtotime((string) $rowData['AQ'])),
                    'type_of_test_requested'                => $rowData['AS'],
                    'reason_for_covid19_test'               => $testReason['test_reason_id'] ?? null,
                    'sample_collection_date'                => $sampleCollectionDate,
                    'specimen_type'                         => $sampleType['sample_id'] ?? null,
                    'test_number'                           => $rowData['AW'],
                    'sample_received_at_lab_datetime'    => $sampleReceivedDate,
                    'lab_id'                                => $labName['facility_id'] ?? null,
                    'is_sample_rejected'                    => strtolower((string) $rowData['AZ']),
                    'reason_for_sample_rejection'           => (isset($rejectionReason['rejection_reason_id']) && $rejectionReason['rejection_reason_id'] != "") ? $rejectionReason['rejection_reason_id'] : 9999,
                    'rejection_on'                          => date('Y-m-d', strtotime((string) $rowData['BB'])),
                    'result'                                => $result['result_id'],
                    'is_result_authorised'                  => strtolower((string) $rowData['BL']),
                    'authorized_by'                         => ($rowData['BM']),
                    'authorized_on'                         => date('Y-m-d', strtotime((string) $rowData['BN'])),
                    'last_modified_datetime'                => DateUtility::getCurrentDateTime(),
                    'last_modified_by'                      => $_SESSION['userId'],
                    'result_status'                         => $resultStatus['status_id'] ?? null,
                    'sample_condition'                      => strtolower((string) $rowData['BP']),
                    'patient_passport_number'               => $rowData['BQ'],
                    'lab_technician'                        => $labTechnician ?? null,
                ];

                if (empty($sampleCode)) {
                    $lastId = $db->insert($tableName, $data);
                } else {
                    $lastId = $sampleCode['covid19_id'];
                    $db->where('covid19_id', $lastId);
                    $db->update($tableName, $data);
                }

                $testData[0]['testRequest']     = $rowData['BC'];
                $testData[0]['testDate']        = $rowData['BD'];
                $testData[0]['testingPlatform'] = $rowData['BE'];
                $testData[0]['testResult']      = $rowData['BF'];
                $testData[1]['testRequest']     = $rowData['BG'];
                $testData[1]['testDate']        = $rowData['BH'];
                $testData[1]['testingPlatform'] = $rowData['BI'];
                $testData[1]['testResult']      = $rowData['BJ'];
                if (!empty($testData)) {
                    /* Delete if already exist */
                    $db->where('covid19_id', $lastId);
                    $db->delete($testTableName);

                    foreach ($testData as $testKitName) {
                        $covid19TestData = ['covid19_id'            => $lastId, 'test_name'             => $testKitName['testRequest'], 'facility_id'           => $labName['facility_id'] ?? null, 'testing_platform'      => $testKitName['testingPlatform'], 'sample_tested_datetime' => DateUtility::isoDateFormat($testKitName['testDate'] ?? '', true), 'result'                => strtolower((string) $testKitName['testResult'])];
                        $db->insert($testTableName, $covid19TestData);
                        $covid19Data['sample_tested_datetime'] = DateUtility::isoDateFormat($testKitName['testDate'] ?? '', true);
                    }
                }
                $db->where('covid19_id', $lastId);
                $id = $db->update($tableName, $covid19Data);
            }
        }
        $_SESSION['alertMsg'] = "Data imported successfully";
    }
    header("Location:/covid-19/requests/covid-19-requests.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
}
