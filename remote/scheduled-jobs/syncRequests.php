<?php
//this file is get the value from remote and update in lab db

//if (php_sapi_name() == 'cli') {
    require_once(dirname(__FILE__) . "/../../startup.php");
//}

$general = new \Vlsm\Models\General($db);
if (!isset($systemConfig['remoteURL']) || $systemConfig['remoteURL'] == '') {
    echo "Please check your Remote URL";
    die;
}

$systemConfig['remoteURL'] = rtrim($systemConfig['remoteURL'], "/");

$arr = $general->getGlobalConfig();
$sarr = $general->getSystemConfig();

//get remote data
if (empty($sarr['lab_name'])) {
    echo "No Lab ID set in System Config";
    exit(0);
}

$type = $_GET['type'];
$pkg = $_GET['pkg'];
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
        'labName' => $sarr['lab_name'],
        'module' => 'vl',
        "Key" => "vlsm-lab-data--",
    );
    if(isset($type) && trim($type) == "vl" && isset($pkg) && trim($pkg) != ""){
        $data['pkg'] = $pkg;
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
    // execute post
    $curl_response = curl_exec($ch);
    //close connection
    curl_close($ch);
    $apiResult = json_decode($curl_response, true);

    if (!empty($apiResult) && is_array($apiResult) && count($apiResult) > 0) {
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
            'request_created_datetime',
            'request_created_by',
            'last_modified_by',
            'data_sync'
        );

        $columnList = array_diff($columnList, $removeKeys);

        foreach ($apiResult as $key => $remoteData) {
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
                    $id = $db->insert('vl_request_form', $request);
                }
            }
            //}
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
        'labName' => $sarr['lab_name'],
        'module' => 'eid',
        "Key" => "vlsm-lab-data--",
    );
    if(isset($type) && trim($type) == "eid" && isset($pkg) && trim($pkg) != ""){
        $data['pkg'] = $pkg;
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
    // execute post
    $curl_response = curl_exec($ch);

    //close connection
    curl_close($ch);
    $apiResult = json_decode($curl_response, true);

    if (!empty($apiResult) && is_array($apiResult) && count($apiResult) > 0) {
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
            'request_created_by',
            'last_modified_by',
            'request_created_datetime',
            'data_sync'
        );

        $columnList = array_diff($columnList, $removeKeys);

        foreach ($apiResult as $key => $remoteData) {
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
        'labName' => $sarr['lab_name'],
        'module' => 'covid19',
        "Key" => "vlsm-lab-data--",
    );
    if(isset($type) && trim($type) == "covid19" && isset($pkg) && trim($pkg) != ""){
        $data['pkg'] = $pkg;
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
    // execute post
    $curl_response = curl_exec($ch);

    //close connection
    curl_close($ch);
    $apiData = json_decode($curl_response, true);

    $apiResult = !empty($apiData['result']) ? $apiData['result'] : null;

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
        'request_created_by',
        'last_modified_by',
        'request_created_datetime',
        'data_sync'
    );

    $columnList = array_diff($columnList, $removeKeys);

    if (!empty($apiResult) && is_array($apiResult) && count($apiResult) > 0) {
        $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS where TABLE_SCHEMA = '" . $systemConfig['dbName'] . "' AND table_name='form_covid19'";
        $allColResult = $db->rawQuery($allColumns);
        $columnList = array_map('current', $allColResult);
        foreach ($apiResult as $key => $remoteData) {
            $request = array();
            foreach ($columnList as $colName) {
                if (isset($remoteData[$colName])) {
                    $request[$colName] = $remoteData[$colName];
                } else {
                    $request[$colName] = null;
                }
            }

            // before we unset the covid19_id field, let us fetch the
            // test results, comorbidities and symptoms

            $symptoms = (isset($apiData['symptoms'][$request['covid19_id']]) && !empty($apiData['symptoms'][$request['covid19_id']])) ? $apiData['symptoms'][$request['covid19_id']] : array();
            $comorbidities = (isset($apiData['comorbidities'][$request['covid19_id']]) && !empty($apiData['comorbidities'][$request['covid19_id']])) ? $apiData['comorbidities'][$request['covid19_id']] : array();
            $testResults = (isset($apiData['testResults'][$request['covid19_id']]) && !empty($apiData['testResults'][$request['covid19_id']])) ? $apiData['testResults'][$request['covid19_id']] : array();


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
                $id = $db->update('form_covid19', $dataToUpdate);
            } else {
                if (!empty($request['sample_collection_date'])) {
                    $request['request_created_by'] = 0;
                    $request['last_modified_by'] = 0;
                    $request['request_created_datetime'] = $general->getDateTime();
                    //$request['result_status'] = 6;
                    $request['data_sync'] = 0; //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                    $id = $db->insert('form_covid19', $request);
                }
            }


            $db = $db->where('covid19_id', $id);
            $db->delete("covid19_patient_symptoms");
            if (isset($symptoms) && !empty($symptoms)) {

                foreach ($symptoms as $symId => $symValue) {
                    $symptomData = array();
                    $symptomData["covid19_id"] = $id;
                    $symptomData["symptom_id"] = $symId;
                    $symptomData["symptom_detected"] = $symValue;
                    $db->insert("covid19_patient_symptoms", $symptomData);
                }
            }

            $db = $db->where('covid19_id', $id);
            $db->delete("covid19_patient_comorbidities");
            if (isset($comorbidities) && !empty($comorbidities)) {

                foreach ($comorbidities as $comoId => $comoValue) {
                    $comorbidityData = array();
                    $comorbidityData["covid19_id"] = $id;
                    $comorbidityData["comorbidity_id"] = $comoId;
                    $comorbidityData["comorbidity_detected"] = $comoValue;
                    $db->insert("covid19_patient_comorbidities", $comorbidityData);
                }
            }

            $db = $db->where('covid19_id', $id);
            $db->delete("covid19_tests");
            if (isset($testResults) && !empty($testResults)) {
                foreach ($testResults as $testValue) {
                    $covid19TestData = array(
                        'covid19_id'            => $id,
                        'test_name'                => $testValue['test_name'],
                        'facility_id'           => $testValue['facility_id'],
                        'sample_tested_datetime' => $testValue['sample_tested_datetime'],
                        'result'                => $testValue['result'],
                    );
                    $db->insert("covid19_tests", $covid19TestData);
                }
            }
            //}
        }
    }
}

if(isset($type) && trim($type) != "" && isset($pkg) && trim($pkg) != ""){
    return 1;
}