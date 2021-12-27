<?php
//this file is get the value from remote and update in lab db

//if (php_sapi_name() == 'cli') {
require_once(dirname(__FILE__) . "/../../startup.php");
//}

$general = new \Vlsm\Models\General();
$app = new \Vlsm\Models\App();

if (!isset($systemConfig['remoteURL']) || $systemConfig['remoteURL'] == '') {
    echo "Please check your Remote URL";
    die;
}

$systemConfig['remoteURL'] = rtrim($systemConfig['remoteURL'], "/");

$headers = @get_headers($systemConfig['remoteURL'] . '/vlsts-icons/favicon-16x16.png');

if (strpos($headers[0], '200') === false) {
    error_log("No internet connectivity while trying remote sync.");
    return false;
}
$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();

//get remote data
if (empty($sarr['sc_testing_lab_id'])) {
    echo "No Lab ID set in System Config";
    exit(0);
}

$forceSyncModule = !empty($_GET['forceSyncModule']) ? $_GET['forceSyncModule'] : null;
$manifestCode = !empty($_GET['manifestCode']) ? $_GET['manifestCode'] : null;

// if only one module is getting synced, lets only sync that one module
if (!empty($forceSyncModule)) {
    unset($systemConfig['modules']);
    $systemConfig['modules'][$forceSyncModule] = true;
}

/*
 ****************************************************************
 * VIRAL LOAD TEST REQUESTS
 ****************************************************************
 */
$request = array();
if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] == true) {
    //$remoteSampleCodeList = array();

    $url = $systemConfig['remoteURL'] . '/remote/remote/getRequests.php';
    $data = array(
        'labName' => $sarr['sc_testing_lab_id'],
        'module' => 'vl',
        "Key" => "vlsm-lab-data--",
    );
    if (!empty($forceSyncModule) && trim($forceSyncModule) == "vl" && !empty($manifestCode) && trim($manifestCode) != "") {
        $data['manifestCode'] = $manifestCode;
    }
    $columnList = array();
    //open connection
    $ch = curl_init($url);
    $json_data = json_encode($data);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data)
        )
    );

    $jsonResponse = curl_exec($ch);
    curl_close($ch);

    if (!empty($jsonResponse) && $jsonResponse != '[]') {

        $parsedData = \JsonMachine\JsonMachine::fromString($jsonResponse);

        $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '" . $systemConfig['dbName'] . "' AND table_name='vl_request_form'";
        $allColResult = $db->rawQuery($allColumns);
        $columnList = array_map('current', $allColResult);

        $removeKeys = array(
            'vl_sample_id',
            'sample_batch_id',
            'result_value_log',
            'result_value_absolute',
            'result_value_absolute_decimal',
            'result_value_text',
            'result',
            'sample_tested_datetime',
            'sample_received_at_vl_lab_datetime',
            'result_dispatched_datetime',
            'is_sample_rejected',
            'reason_for_sample_rejection',
            'result_approved_by',
            'result_approved_datetime',
            'request_created_datetime',
            'request_created_by',
            'last_modified_by',
            'data_sync'
        );

        $columnList = array_diff($columnList, $removeKeys);
        $counter = 0;
        foreach ($parsedData as $key => $remoteData) {
            $counter++;
            $request = array();
            foreach ($columnList as $colName) {
                if (isset($remoteData[$colName])) {
                    $request[$colName] = $remoteData[$colName];
                } else {
                    $request[$colName] = null;
                }
            }

            //$remoteSampleCodeList[] = $request['remote_sample_code'];
            $request['last_modified_datetime'] = $general->getDateTime();

            //check wheather sample code empty or not
            // if ($request['sample_code'] != '' && $request['sample_code'] != 0 && $request['sample_code'] != null) {
            //     $sQuery = "SELECT vl_sample_id FROM vl_request_form WHERE sample_code='" . $request['sample_code'] . "'";
            //     $sResult = $db->rawQuery($sQuery);
            //     $db = $db->where('vl_sample_id', $sResult[0]['vl_sample_id']);
            //     $id = $db->update('vl_request_form', $request);
            // } else {
            //check exist remote
            $exsvlQuery = "SELECT vl_sample_id,sample_code FROM vl_request_form AS vl WHERE remote_sample_code='" . $request['remote_sample_code'] . "'";
            $exsvlResult = $db->query($exsvlQuery);
            if ($exsvlResult) {

                $dataToUpdate = array();

                $dataToUpdate['sample_package_code'] = $request['sample_package_code'];
                $dataToUpdate['sample_package_id'] = $request['sample_package_id'];

                $db = $db->where('vl_sample_id', $exsvlResult[0]['vl_sample_id']);
                $id = $db->update('vl_request_form', $dataToUpdate);
            } else {
                if ($request['sample_collection_date'] != '' && $request['sample_collection_date'] != null && $request['sample_collection_date'] != '0000-00-00 00:00:00') {
                    $request['request_created_by'] = 0;
                    $request['last_modified_by'] = 0;
                    $request['request_created_datetime'] = $general->getDateTime();
                    //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                    $request['data_sync'] = 0;
                    /* echo "<pre>";
                        print_r($request);
                        die; */
                    $id = $db->insert('vl_request_form', $request);
                }
            }
            //}
        }
        if ($counter > 0) {
            $trackId = $app->addApiTracking(null, $counter, 'requests', 'vl', $url, $sarr['sc_testing_lab_id'], 'sync-api');
        }
    }
}


/* 
  ****************************************************************
  *  EID TEST REQUESTS 
  ****************************************************************
  */

$request = array();
//$remoteSampleCodeList = array();
if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] == true) {
    $url = $systemConfig['remoteURL'] . '/remote/remote/eid-test-requests.php';
    $data = array(
        'labName' => $sarr['sc_testing_lab_id'],
        'module' => 'eid',
        "Key" => "vlsm-lab-data--",
    );
    if (isset($forceSyncModule) && trim($forceSyncModule) == "eid" && isset($manifestCode) && trim($manifestCode) != "") {
        $data['manifestCode'] = $manifestCode;
    }
    //open connection
    $ch = curl_init($url);
    $json_data = json_encode($data);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data)
        )
    );

    $jsonResponse = curl_exec($ch);
    curl_close($ch);

    if (!empty($jsonResponse) && $jsonResponse != '[]') {

        $parsedData = \JsonMachine\JsonMachine::fromString($jsonResponse);


        $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS where TABLE_SCHEMA = '" . $systemConfig['dbName'] . "' AND table_name='eid_form'";
        $allColResult = $db->rawQuery($allColumns);
        $columnList = array_map('current', $allColResult);

        $removeKeys = array(
            'eid_id',
            'sample_batch_id',
            'result',
            'sample_tested_datetime',
            'sample_received_at_vl_lab_datetime',
            'result_dispatched_datetime',
            'is_sample_rejected',
            'reason_for_sample_rejection',
            'result_approved_by',
            'result_approved_datetime',
            'request_created_by',
            'last_modified_by',
            'request_created_datetime',
            'data_sync'
        );

        $columnList = array_diff($columnList, $removeKeys);
        $counter = 0;
        foreach ($parsedData as $key => $remoteData) {
            $counter++;
            $request = array();
            foreach ($columnList as $colName) {
                if (isset($remoteData[$colName])) {
                    $request[$colName] = $remoteData[$colName];
                } else {
                    $request[$colName] = null;
                }
            }


            //$remoteSampleCodeList[] = $request['remote_sample_code'];
            $request['last_modified_datetime'] = $general->getDateTime();

            //check whether sample code empty or not
            // if ($request['sample_code'] != '' && $request['sample_code'] != 0 && $request['sample_code'] != null) {
            //     $sQuery = "SELECT eid_id FROM eid_form WHERE sample_code='" . $request['sample_code'] . "'";
            //     $sResult = $db->rawQuery($sQuery);
            //     $db = $db->where('eid_id', $sResult[0]['eid_id']);
            //     $id = $db->update('eid_form', $request);
            // } else {
            //check exist remote
            $exsvlQuery = "SELECT eid_id,sample_code FROM eid_form AS vl WHERE remote_sample_code='" . $request['remote_sample_code'] . "'";
            $exsvlResult = $db->query($exsvlQuery);
            if ($exsvlResult) {

                $dataToUpdate = array();
                $dataToUpdate['sample_package_code'] = $request['sample_package_code'];
                $dataToUpdate['sample_package_id'] = $request['sample_package_id'];

                $db = $db->where('eid_id', $exsvlResult[0]['eid_id']);
                $id = $db->update('eid_form', $dataToUpdate);
            } else {
                if ($request['sample_collection_date'] != '' && $request['sample_collection_date'] != null && $request['sample_collection_date'] != '0000-00-00 00:00:00') {
                    $request['request_created_by'] = 0;
                    $request['last_modified_by'] = 0;
                    $request['request_created_datetime'] = $general->getDateTime();
                    //$request['result_status'] = 6;
                    $request['data_sync'] = 0; //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                    $id = $db->insert('eid_form', $request);
                }
            }
            //}
        }
        if ($counter > 0) {
            $trackId = $app->addApiTracking(null, $counter, 'requests', 'eid', $url, $sarr['sc_testing_lab_id'], 'sync-api');
        }
    }
}


/* 
  ****************************************************************
  *  COVID-19 TEST REQUESTS 
  ****************************************************************
  */
$request = array();
//$remoteSampleCodeList = array();
if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] == true) {
    $url = $systemConfig['remoteURL'] . '/remote/remote/covid-19-test-requests.php';
    $data = array(
        'labName' => $sarr['sc_testing_lab_id'],
        'module' => 'covid19',
        "Key" => "vlsm-lab-data--",
    );
    if (isset($forceSyncModule) && trim($forceSyncModule) == "covid19" && isset($manifestCode) && trim($manifestCode) != "") {
        $data['manifestCode'] = $manifestCode;
    }
    //open connection
    $ch = curl_init($url);
    $json_data = json_encode($data);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data)
        )
    );

    $jsonResponse = curl_exec($ch);
    curl_close($ch);

    if (!empty($jsonResponse) && $jsonResponse != '[]') {
        $removeKeys = array(
            'covid19_id',
            'sample_batch_id',
            'result',
            'sample_tested_datetime',
            'sample_received_at_vl_lab_datetime',
            'result_dispatched_datetime',
            'is_sample_rejected',
            'reason_for_sample_rejection',
            'result_approved_by',
            'result_approved_datetime',
            'request_created_by',
            'last_modified_by',
            'request_created_datetime',
            'data_sync'
        );


        $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS where TABLE_SCHEMA = '" . $systemConfig['dbName'] . "' AND table_name='form_covid19'";
        $allColResult = $db->rawQuery($allColumns);
        $columnList = array_map('current', $allColResult);
        $columnList = array_diff($columnList, $removeKeys);


        $parsedData = \JsonMachine\JsonMachine::fromString($jsonResponse, "/result");
        $counter = 0;
        foreach ($parsedData as $key => $remoteData) {
            $counter++;
            $request = array();
            $covid19Id = $remoteData['covid19_id'];
            foreach ($columnList as $colName) {
                if (isset($remoteData[$colName])) {
                    $request[$colName] = $remoteData[$colName];
                } else {
                    $request[$colName] = null;
                }
            }


            //$remoteSampleCodeList[] = $request['remote_sample_code'];
            $request['last_modified_datetime'] = $general->getDateTime();

            //check whether sample code empty or not
            // if ($request['sample_code'] != '' && $request['sample_code'] != 0 && $request['sample_code'] != null) {
            //     $sQuery = "SELECT eid_id FROM eid_form WHERE sample_code='" . $request['sample_code'] . "'";
            //     $sResult = $db->rawQuery($sQuery);
            //     $db = $db->where('eid_id', $sResult[0]['eid_id']);
            //     $id = $db->update('eid_form', $request);
            // } else {
            //check exist remote
            $exsvlQuery = "SELECT covid19_id,sample_code FROM form_covid19 AS vl WHERE remote_sample_code='" . $request['remote_sample_code'] . "'";

            $exsvlResult = $db->query($exsvlQuery);
            if ($exsvlResult) {

                $dataToUpdate = array();
                $dataToUpdate['sample_package_code'] = $request['sample_package_code'];
                $dataToUpdate['sample_package_id'] = $request['sample_package_id'];

                $db = $db->where('covid19_id', $exsvlResult[0]['covid19_id']);
                $db->update('form_covid19', $dataToUpdate);
                $id = $exsvlResult[0]['covid19_id'];
            } else {
                if (!empty($request['sample_collection_date'])) {
                    $request['request_created_by'] = 0;
                    $request['last_modified_by'] = 0;
                    $request['request_created_datetime'] = $general->getDateTime();
                    //$request['result_status'] = 6;
                    $request['data_sync'] = 0; //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                    $db->insert('form_covid19', $request);
                    $id = $db->getInsertId();
                }
            }
        }

        $parsedData = \JsonMachine\JsonMachine::fromString($jsonResponse, "/symptoms");
        foreach ($parsedData as $covid19Id => $symptoms) {
            $db = $db->where('covid19_id', $covid19Id);
            $db->delete("covid19_patient_symptoms");
            foreach ($symptoms as $symId => $symValue) {
                $symptomData = array();
                $symptomData["covid19_id"] = $covid19Id;
                $symptomData["symptom_id"] = $symId;
                $symptomData["symptom_detected"] = $symValue;
                $db->insert("covid19_patient_symptoms", $symptomData);
            }
        }

        $parsedData = \JsonMachine\JsonMachine::fromString($jsonResponse, "/comorbidities");
        foreach ($parsedData as $covid19Id => $comorbidities) {
            $db = $db->where('covid19_id', $covid19Id);
            $db->delete("covid19_patient_comorbidities");

            foreach ($comorbidities as $comoId => $comorbidityData) {
                $comorbidityData = array();
                $comorbidityData["covid19_id"] = $covid19Id;
                $comorbidityData["comorbidity_id"] = $comoId;
                $comorbidityData["comorbidity_detected"] = $comoValue;
                $db->insert("covid19_patient_comorbidities", $comorbidityData);
            }
        }

        $parsedData = \JsonMachine\JsonMachine::fromString($jsonResponse, "/testResults");
        foreach ($parsedData as $covid19Id => $testResults) {
            $db = $db->where('covid19_id', $covid19Id);
            $db->delete("covid19_tests");
            foreach ($testResults as $covid19TestData) {
                unset($covid19TestData['test_id']);
                $db->insert("covid19_tests", $covid19TestData);
            }
        }


        if ($counter > 0) {
            $trackId = $app->addApiTracking(null, $counter, 'requests', 'covid19', $url, $sarr['sc_testing_lab_id'], 'sync-api');
        }
    }
}


/*
****************************************************************
* Hepatitis TEST REQUESTS
****************************************************************
*/
$request = array();
//$remoteSampleCodeList = array();
if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] == true) {
    $url = $systemConfig['remoteURL'] . '/remote/remote/hepatitis-test-requests.php';
    $data = array(
        'labName' => $sarr['sc_testing_lab_id'],
        'module' => 'hepatitis',
        "Key" => "vlsm-lab-data--",
    );
    if (isset($forceSyncModule) && trim($forceSyncModule) == "hepatitis" && isset($manifestCode) && trim($manifestCode) != "") {
        $data['manifestCode'] = $manifestCode;
    }
    //open connection
    $ch = curl_init($url);
    $json_data = json_encode($data);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data)
        )
    );

    $jsonResponse = curl_exec($ch);
    curl_close($ch);

    if (!empty($jsonResponse) && $jsonResponse != '[]') {
        $removeKeys = array(
            'hepatitis_id',
            'sample_batch_id',
            'result',
            'hcv_vl_result',
            'hbv_vl_result',
            'hcv_vl_count',
            'hbv_vl_count',
            'sample_tested_datetime',
            'sample_received_at_vl_lab_datetime',
            'result_dispatched_datetime',
            'is_sample_rejected',
            'reason_for_sample_rejection',
            'result_approved_by',
            'result_approved_datetime',
            'request_created_by',
            'last_modified_by',
            'request_created_datetime',
            'data_sync'
        );




        $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS where TABLE_SCHEMA = '" . $systemConfig['dbName'] . "' AND table_name='form_hepatitis'";
        $allColResult = $db->rawQuery($allColumns);
        $columnList = array_map('current', $allColResult);
        $columnList = array_diff($columnList, $removeKeys);

        $parsedData = \JsonMachine\JsonMachine::fromString($jsonResponse, "/result");
        $counter = 0;
        foreach ($parsedData as $key => $remoteData) {
            $request = array();
            $hepatitisId = $remoteData['hepatitis_id'];
            foreach ($columnList as $colName) {
                if (isset($remoteData[$colName])) {
                    $request[$colName] = $remoteData[$colName];
                } else {
                    $request[$colName] = null;
                }
            }


            //$remoteSampleCodeList[] = $request['remote_sample_code'];
            $request['last_modified_datetime'] = $general->getDateTime();

            //check exist remote
            $exsvlQuery = "SELECT hepatitis_id,sample_code FROM form_hepatitis AS vl WHERE remote_sample_code='" . $request['remote_sample_code'] . "'";

            $exsvlResult = $db->query($exsvlQuery);
            if ($exsvlResult) {

                $dataToUpdate = array();
                $dataToUpdate['sample_package_code'] = $request['sample_package_code'];
                $dataToUpdate['sample_package_id'] = $request['sample_package_id'];

                $db = $db->where('hepatitis_id', $exsvlResult[0]['hepatitis_id']);
                $db->update('form_hepatitis', $dataToUpdate);
                $id = $exsvlResult[0]['hepatitis_id'];
            } else {
                if (!empty($request['sample_collection_date'])) {
                    $request['request_created_by'] = 0;
                    $request['last_modified_by'] = 0;
                    $request['request_created_datetime'] = $general->getDateTime();
                    //$request['result_status'] = 6;
                    $request['data_sync'] = 0; //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                    $db->insert('form_hepatitis', $request);
                    $id = $db->getInsertId();
                }
            }
        }

        $parsedData = \JsonMachine\JsonMachine::fromString($jsonResponse, "/risks");
        foreach ($parsedData as $hepatitisId => $risks) {
            $db = $db->where('hepatitis_id', $hepatitisId);
            $db->delete("hepatitis_risk_factors");

            $rData = array();
            foreach ($risks as  $riskId => $riskValue) {
                $riskFactorsData = array();
                $riskFactorsData["hepatitis_id"] = $hepatitisId;
                $riskFactorsData["riskfactors_id"] = $riskId;
                $riskFactorsData["riskfactors_detected"] = $riskValue;
                $rData[] = $riskFactorsData;
                //$db->insert("hepatitis_risk_factors", $riskFactorsData);
            }
            $ids = $db->insertMulti('hepatitis_risk_factors', $rData);
            if (!$ids) {
                error_log('insert failed: ' . $db->getLastError());
            }
        }

        $parsedData = \JsonMachine\JsonMachine::fromString($jsonResponse, "/comorbidities");
        foreach ($parsedData as $hepatitisId => $comorbidities) {
            $db = $db->where('hepatitis_id', $hepatitisId);
            $db->delete("hepatitis_patient_comorbidities");

            $cData = array();
            foreach ($comorbidities as $comoId => $comoValue) {
                $comorbidityData = array();
                $comorbidityData["hepatitis_id"] = $hepatitisId;
                $comorbidityData["comorbidity_id"] = $comoId;
                $comorbidityData["comorbidity_detected"] = $comoValue;
                $cData[] = $comorbidityData;
            }

            $ids = $db->insertMulti('hepatitis_patient_comorbidities', $cData);
            if (!$ids) {
                error_log('insert failed: ' . $db->getLastError());
            }
        }

        if ($counter > 0) {
            $trackId = $app->addApiTracking(null, $counter, 'requests', 'hepatitis', $url, $sarr['sc_testing_lab_id'], 'sync-api');
        }
    }
}

/*
****************************************************************
* TB TEST REQUESTS
****************************************************************
*/
$request = array();
//$remoteSampleCodeList = array();
if (isset($systemConfig['modules']['tb']) && $systemConfig['modules']['tb'] == true) {
    $url = $systemConfig['remoteURL'] . '/remote/remote/tb-test-requests.php';
    $data = array(
        'labName' => $sarr['sc_testing_lab_id'],
        'module' => 'tb',
        "Key" => "vlsm-lab-data--",
    );
    if (isset($forceSyncModule) && trim($forceSyncModule) == "tb" && isset($manifestCode) && trim($manifestCode) != "") {
        $data['manifestCode'] = $manifestCode;
    }
    //open connection
    $ch = curl_init($url);
    $json_data = json_encode($data);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $json_data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json_data)
        )
    );

    $jsonResponse = curl_exec($ch);
    curl_close($ch);
    if (!empty($jsonResponse) && $jsonResponse != '[]') {
        $removeKeys = array(
            'tb_id',
            'sample_batch_id',
            'result',
            'xpert_mtb_result',
            'sample_tested_datetime',
            'sample_received_at_vl_lab_datetime',
            'result_dispatched_datetime',
            'is_sample_rejected',
            'reason_for_sample_rejection',
            'result_approved_by',
            'result_approved_datetime',
            'request_created_by',
            'last_modified_by',
            'request_created_datetime',
            'data_sync'
        );

        $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS where TABLE_SCHEMA = '" . $systemConfig['dbName'] . "' AND table_name='form_tb'";
        $allColResult = $db->rawQuery($allColumns);
        $columnList = array_map('current', $allColResult);
        $columnList = array_diff($columnList, $removeKeys);

        $parsedData = \JsonMachine\JsonMachine::fromString($jsonResponse, "/result");
        $counter = 0;
        foreach ($parsedData as $key => $remoteData) {
            $request = array();
            $tbId = $remoteData['tb_id'];
            foreach ($columnList as $colName) {
                if (isset($remoteData[$colName])) {
                    $request[$colName] = $remoteData[$colName];
                } else {
                    $request[$colName] = null;
                }
            }

            //$remoteSampleCodeList[] = $request['remote_sample_code'];
            $request['last_modified_datetime'] = $general->getDateTime();

            //check exist remote
            $exsvlQuery = "SELECT tb_id,sample_code FROM form_tb AS vl WHERE remote_sample_code='" . $request['remote_sample_code'] . "'";

            $exsvlResult = $db->query($exsvlQuery);
            if ($exsvlResult) {

                $dataToUpdate = array();
                $dataToUpdate['sample_package_code'] = $request['sample_package_code'];
                $dataToUpdate['sample_package_id'] = $request['sample_package_id'];

                $db = $db->where('tb_id', $exsvlResult[0]['tb_id']);
                $db->update('form_tb', $dataToUpdate);
                $id = $exsvlResult[0]['tb_id'];
            } else {
                if (!empty($request['sample_collection_date'])) {
                    $request['request_created_by'] = 0;
                    $request['last_modified_by'] = 0;
                    $request['request_created_datetime'] = $general->getDateTime();
                    //$request['result_status'] = 6;
                    $request['data_sync'] = 0; //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                    $db->insert('form_tb', $request);
                    $id = $db->getInsertId();
                }
            }
        }

        if ($counter > 0) {
            $trackId = $app->addApiTracking(null, $counter, 'requests', 'tb', $url, $sarr['sc_testing_lab_id'], 'sync-api');
        }
    }
}

/* Get instance id for update last_remote_results_sync */
$instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");

/* Update last_remote_results_sync in s_vlsm_instance */
$db = $db->where('vlsm_instance_id', $instanceResult['vlsm_instance_id']);
$id = $db->update('s_vlsm_instance', array('last_remote_requests_sync' => $general->getDateTime()));

if (isset($forceSyncModule) && trim($forceSyncModule) != "" && isset($manifestCode) && trim($manifestCode) != "") {
    return 1;
}
