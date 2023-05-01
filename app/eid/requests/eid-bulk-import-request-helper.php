<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\UsersService;
use App\Utilities\DateUtility;
use PhpOffice\PhpSpreadsheet\IOFactory;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ini_set('memory_limit', -1);
ini_set('max_execution_time', -1);
$arr = [];
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var UsersService $usersService */
$usersService = ContainerRegistry::get(UsersService::class);

$tableName = "form_eid";
// echo "<pre>";print_r($_FILES);die;
try {
    $lock = $general->getGlobalConfig('lock_approved_eid_samples');
    $arr = $general->getGlobalConfig();
    //system config
    $systemConfigQuery = "SELECT * from system_config";
    $systemConfigResult = $db->query($systemConfigQuery);
    $sarr = [];
    // now we create an associative array so that we can easily create view variables
    for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
        $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
    }

    $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['requestFile']['name']);
    $fileName = str_replace(" ", "-", $fileName);
    $ranNumber = $general->generateRandomString(12);
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

        $resultArray = array_slice($sheetData, 1);
        // echo "<pre>";print_r($resultArray);die;

        foreach ($resultArray as $rowIndex => $rowData) {
            if (isset($rowData['B']) && !empty($rowData['B'])) {
                $sampleCode = $general->getDuplicateDataFromField('form_eid', 'sample_code', $rowData['B']);

            
                $sampleType = $general->getDuplicateDataFromField('r_eid_sample_type', 'sample_name', $rowData['AF']);
                // ADDED
                $facility = $general->getDuplicateDataFromField('facility_details', 'facility_name', $rowData['E']);
                $state = $general->getDuplicateDataFromField('geographical_divisions', 'geo_name', $rowData['C']);
                $labName = $general->getDuplicateDataFromField('facility_details', 'facility_name', $rowData['AA'], 'facility_type');
                $rejectionReason = $general->getDuplicateDataFromField('r_eid_sample_rejection_reasons', 'rejection_reason_name', $rowData['AC']);
                $result = $general->getDuplicateDataFromField('r_eid_results', 'result', $rowData['AE']);
                $resultStatus = $general->getDuplicateDataFromField('r_sample_status', 'status_name', $rowData['AK']);

                if (trim($rowData['W']) != '') {
                    $sampleCollectionDate = date('Y-m-d H:i:s', strtotime($rowData['W']));
                } else {
                    $sampleCollectionDate = null;
                }

                if (trim($rowData['Z']) != '') {
                    $sampleReceivedDate = date('Y-m-d H:i:s', strtotime($rowData['Z']));
                } else {
                    $sampleReceivedDate = null;
                }

                if (trim($rowData['AD']) != '') {
                    $sampleTestDate = date('Y-m-d H:i:s', strtotime($rowData['AD']));
                } else {
                    $sampleTestDate = null;
                }

                $instanceId = '';
                if (isset($_SESSION['instanceId'])) {
                    $instanceId = $_SESSION['instanceId'];
                }

                $status = 6;
                if ($_SESSION['instanceType'] == 'remoteuser' && $_SESSION['accessType'] == 'collection-site') {
                    $status = 9;
                }


                if (isset($rowData['AB']) && strtolower($rowData['AB']) == 'yes') {
                    $result['result_id'] = null;
                    $status = 4;
                }

                if (!empty($rowData['I'])) {
                    $rowData['I'] = strtolower($rowData['I']);
                    if ($rowData['I'] == 'm' || $rowData['I'] == 'male') {
                        $rowData['I'] = 'male';
                    } else if ($rowData['I'] == 'f' || $rowData['I'] == 'female') {
                        $rowData['I'] = 'female';
                    } else {
                        $rowData['I'] = null;
                    }
                }

                $eidData = array(
                    'vlsm_instance_id'                                  => $instanceId,
                    'vlsm_country_id'                                   => 1,
                    'sample_code'                                       => $rowData['B'] ?? null,
                    'province_id'                                       => $state['geo_id'] ?? null,
                    'facility_id'                                       => $facility['facility_id'] ?? null,
                    'child_id'                                          => $rowData['F'] ?? null,
                    'child_name'                                        => $rowData['G'] ?? null,
                    // 'child_dob'                                         => isset($rowData['H']) ? date('Y-M-d',strtotime($rowData['H'])) : null,
                    'child_gender'                                      => $rowData['I'],
                    'child_age'                                         => $rowData['H'] ?? null,
                    'mother_id'                                         => $rowData['J'] ?? null,
                    'caretaker_phone_number'                            => $rowData['K'] ?? null,
                    'caretaker_address'                                 => $rowData['L'] ?? null,
                    'mother_hiv_status'                                 => $rowData['M'] ?? null,
                    'mother_treatment'                                  => $rowData['N'] ?? null,
                    'rapid_test_performed'                              => isset($rowData['O']) ? strtolower($rowData['O']) : null,
                    'rapid_test_date'                                   => isset($rowData['P']) ? date('Y-M-d', strtotime($rowData['P'])) : null,
                    'rapid_test_result'                                 => isset($rowData['Q']) ? strtolower($rowData['Q']) : null,
                    'has_infant_stopped_breastfeeding'                  => isset($rowData['R']) ? strtolower($rowData['R']) : null,
                    'age_breastfeeding_stopped_in_months'               => $rowData['S'] ?? null,
                    'pcr_test_performed_before'                         => isset($rowData['T']) ? strtolower($rowData['T']) : null,
                    'last_pcr_date'                                     => isset($rowData['U']) ? date('Y-M-d', strtotime($rowData['U'])) : null,
                    'reason_for_pcr'                                    => $rowData['V'] ?? null,
                    'sample_collection_date'                            => $sampleCollectionDate,
                    'sample_requestor_name'                             => $rowData['X'] ?? null,
                    'sample_requestor_phone'                            => $rowData['Y'] ?? null,
                    'sample_received_at_vl_lab_datetime'                => $sampleReceivedDate,
                    'lab_id'                                            => $labName['facility_id'] ?? null,
                    'sample_tested_datetime'                            => $sampleTestDate,
                    'is_sample_rejected'                                => isset($rowData['AB']) ? strtolower($rowData['AB']) : null,
                    'reason_for_sample_rejection'                       => $rejectionReason['rejection_reason_id'] ?? null,
                    'result'                                            => $result['result_id'] ?? null,
                    'result_status'                                     => $status,
                    'specimen_type'                                     => $sampleType['sample_id'] ?? null,
                    'data_sync'                                         => 0,
                    'request_created_by'                                => $_SESSION['userId'],
                    'request_created_datetime'                          => DateUtility::getCurrentDateTime(),
                    'sample_registered_at_lab'                          => DateUtility::getCurrentDateTime(),
                    'last_modified_by'                                  => $_SESSION['userId'],
                    'last_modified_datetime'                            => DateUtility::getCurrentDateTime()
                );

                // echo "<pre>";print_r($sampleCode);die;
                if (!$sampleCode) {
                    $lastId = $db->insert($tableName, $eidData);
                } else {
                    $lastId = $sampleCode['eid_id'];
                    $db = $db->where('eid_id', $lastId);
                    $db->update($tableName, $eidData);
                }
            }
        }
        $_SESSION['alertMsg'] = "Data imported successfully";
    }
    header("Location:/eid/requests/eid-requests.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
