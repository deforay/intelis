<?php

ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$arr = array();
$general = new \Vlsm\Models\General($db);
$usersModel = new \Vlsm\Models\Users($db);

$tableName = "eid_form";
// echo "<pre>";print_r($_FILES);die;
try {
    $lock = $general->getGlobalConfig('lock_approved_eid_samples');
    $arr = $general->getGlobalConfig();
    //system config
    $systemConfigQuery = "SELECT * from system_config";
    $systemConfigResult = $db->query($systemConfigQuery);
    $sarr = array();
    // now we create an associative array so that we can easily create view variables
    for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
    $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
    }
    
    $fileName = preg_replace('/[^A-Za-z0-9.]/', '-', $_FILES['requestFile']['name']);
    $fileName = str_replace(" ", "-", $fileName);
    $ranNumber = str_pad(rand(0, pow(10, 6) - 1), 6, '0', STR_PAD_LEFT);
    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    $fileName = $ranNumber . "." . $extension;

    if (!file_exists(TEMP_PATH . DIRECTORY_SEPARATOR . "import-request") && !is_dir(TEMP_PATH . DIRECTORY_SEPARATOR . "import-request")) {
        mkdir(TEMP_PATH . DIRECTORY_SEPARATOR . "import-request", 0777);
    }
    if (move_uploaded_file($_FILES['requestFile']['tmp_name'], TEMP_PATH . DIRECTORY_SEPARATOR . "import-request" . DIRECTORY_SEPARATOR . $fileName)) {

        $file_info = new finfo(FILEINFO_MIME); // object oriented approach!
        $mime_type = $file_info->buffer(file_get_contents(TEMP_PATH . DIRECTORY_SEPARATOR . "import-request" . DIRECTORY_SEPARATOR . $fileName)); // e.g. gives "image/jpeg"

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(TEMP_PATH . DIRECTORY_SEPARATOR . "import-request" . DIRECTORY_SEPARATOR . $fileName);
        $sheetData   = $spreadsheet->getActiveSheet();
        $sheetData   = $sheetData->toArray(null, true, true, true);

        $resultArray = array_slice($sheetData,1);
        // echo "<pre>";print_r($resultArray);die;

        foreach ($resultArray as $rowIndex => $rowData) {
            if (isset($rowData['A']) && !empty($rowData['A'])) {
                $sampleCode = $general->getDublicateDataFromField('eid_form', 'sample_code', $rowData['B']);

                // NOT ADDED
                // $testReason = $general->getDublicateDataFromField('r_covid19_test_reasons', 'test_reason_name', $rowData['P']);
                $sampleType = $general->getDublicateDataFromField('r_eid_sample_type', 'sample_name', $rowData['AF']);
                // ADDED
                $facility = $general->getDublicateDataFromField('facility_details', 'facility_name', $rowData['E']);
                $state = $general->getDublicateDataFromField('province_details', 'province_name', $rowData['C']);
                $labName = $general->getDublicateDataFromField('facility_details', 'facility_name', $rowData['AA'], 'facility_type');
                $rejectionReason = $general->getDublicateDataFromField('r_eid_sample_rejection_reasons', 'rejection_reason_name', $rowData['AC']);
                $result = $general->getDublicateDataFromField('r_eid_results', 'result', $rowData['AE']);
                $resultStatus = $general->getDublicateDataFromField('r_sample_status', 'status_name', $rowData['AK']);

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
                if ($sarr['user_type'] == 'remoteuser') {
                    $status = 9;
                }


                if (isset($rowData['AB']) && strtolower($rowData['AB']) == 'yes') {
                    $result['result_id'] = null;
                    $status = 4;
                }

                $eidData = array(
                    'vlsm_instance_id'                                  => $instanceId,
                    'vlsm_country_id'                                   => 1,
                    'sample_code'                                       => isset($rowData['B']) ? $rowData['B'] : null,
                    'province_id'                                       => isset($state['province_id']) ? $state['province_id'] : null,
                    'facility_id'                                       => isset($facility['facility_id']) ? $facility['facility_id'] : null,
                    'child_id'                                          => isset($rowData['F']) ? $rowData['F'] : null,
                    'child_name'                                        => isset($rowData['G']) ? $rowData['G'] : null,
                    'child_dob'                                         => isset($rowData['H']) ? date('Y-M-d',strtotime($rowData['H'])) : null,
                    'child_gender'                                      => isset($rowData['I']) ? $rowData['I'] : null,
                    'child_age'                                         => isset($rowData['H']) ? $general->ageInMonth($rowData['H']) : null,
                    'mother_id'                                         => isset($rowData['J']) ? $rowData['J'] : null,
                    'caretaker_phone_number'                            => isset($rowData['K']) ? $rowData['K'] : null,
                    'caretaker_address'                                 => isset($rowData['L']) ? $rowData['L'] : null,
                    'mother_hiv_status'                                 => isset($rowData['M']) ? $rowData['M'] : null,
                    'mother_treatment'                                  => isset($rowData['N']) ? implode(",", $rowData['N']) : null,
                    'rapid_test_performed'                              => isset($rowData['O']) ? strtolower($rowData['O']) : null,
                    'rapid_test_date'                                   => isset($rowData['P']) ? date('Y-M-d',strtotime($rowData['P'])) : null,
                    'rapid_test_result'                                 => isset($rowData['Q']) ? strtolower($rowData['Q']) : null,
                    'has_infant_stopped_breastfeeding'                  => isset($rowData['R']) ? strtolower($rowData['R']) : null,
                    'age_breastfeeding_stopped_in_months'               => isset($rowData['S']) ? $rowData['S'] : null,
                    'pcr_test_performed_before'                         => isset($rowData['T']) ? strtolower($rowData['T']) : null,
                    'last_pcr_date'                                     => isset($rowData['U']) ? date('Y-M-d',strtotime($rowData['U'])) : null,
                    'reason_for_pcr'                                    => isset($rowData['V']) ? $rowData['V'] : null,
                    'sample_collection_date'                            => $sampleCollectionDate,
                    'sample_requestor_name'                             => isset($rowData['X']) ? $rowData['X'] : null,
                    'sample_requestor_phone'                            => isset($rowData['Y']) ? $rowData['Y'] : null,
                    'sample_received_at_vl_lab_datetime'                => $sampleReceivedDate,
                    'lab_id'                                            => isset($labName['facility_id']) ? $labName['facility_id'] : null,
                    'sample_tested_datetime'                            => $sampleTestDate,
                    'is_sample_rejected'                                => isset($rowData['AB']) ? strtolower($rowData['AB']) : null,
                    'reason_for_sample_rejection'                       => isset($rejectionReason['rejection_reason_id']) ? $rejectionReason['rejection_reason_id'] : null,
                    'result'                                            => isset($result['result_id']) ? $result['result_id'] : null,
                    'result_status'                                     => $status,
                    'specimen_type'                                     => isset($sampleType['sample_id']) ? $sampleType['sample_id'] : null,
                    'data_sync'                                         => 0,
                    'request_created_by'                                => $_SESSION['userId'],
                    'request_created_datetime'                          => $general->getDateTime(),
                    'sample_registered_at_lab'                          => $general->getDateTime(),
                    'last_modified_by'                                  => $_SESSION['userId'],
                    'last_modified_datetime'                            => $general->getDateTime()
                );
	            if ($status == 7 && $lock == 'yes') {
                    $eidData['locked'] = 'yes';
                }
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
    header("location:/eid/requests/eid-requests.php");
} catch (Exception $exc) {
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
