<?php
//this file gets the requests from the remote server and updates the local database

if (php_sapi_name() == 'cli') {
    require_once(__DIR__ . "/../../../bootstrap.php");
}

use App\Services\ApiService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\DateUtility;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


$transactionId = $general->generateUUID();

$labId = $general->getSystemConfig('sc_testing_lab_id');
$version = VERSION;

$systemConfig = SYSTEM_CONFIG;

if (!isset($systemConfig['remoteURL']) || $systemConfig['remoteURL'] == '') {
    error_log("Please check if Remote URL is set");
    exit(0);
}

$remoteUrl = rtrim($systemConfig['remoteURL'], "/");

$headers = @get_headers($remoteUrl . '/api/version.php?labId=' . $labId . '&version=' . $version);

if (strpos($headers[0], '200') === false) {
    error_log("No internet connectivity while trying remote sync.");
    return false;
}
$arr = $general->getGlobalConfig();


//get remote data
if (empty($labId)) {
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
$request = [];
if (isset($systemConfig['modules']['vl']) && $systemConfig['modules']['vl'] === true) {
    //$remoteSampleCodeList = [];

    $url = $remoteUrl . '/remote/remote/getRequests.php';
    $payload = array(
        'labId' => $labId,
        'module' => 'vl',
        "Key" => "vlsm-lab-data--",
    );
    if (!empty($forceSyncModule) && trim($forceSyncModule) == "vl" && !empty($manifestCode) && trim($manifestCode) != "") {
        $payload['manifestCode'] = $manifestCode;
    }
    $columnList = [];

    $client = new GuzzleHttp\Client();
    $response = $client->post(
        $url,
        [
            GuzzleHttp\RequestOptions::JSON => $payload
        ]
    );

    $jsonResponse = $response->getBody()->getContents();

    if (!empty($jsonResponse) && $jsonResponse != '[]') {

        $options = [
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);

        $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_SCHEMA = ? AND table_name='form_vl'";
        $allColResult = $db->rawQuery($allColumns, [SYSTEM_CONFIG['database']['db']]);
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
            //'request_created_datetime',
            //'request_created_by',
            //'last_modified_by',
            'data_sync'
        );

        $columnList = array_diff($columnList, $removeKeys);
        $counter = 0;
        foreach ($parsedData as $key => $remoteData) {
            $counter++;
            $request = [];
            foreach ($columnList as $colName) {
                if (isset($remoteData[$colName])) {
                    $request[$colName] = $remoteData[$colName];
                } else {
                    $request[$colName] = null;
                }
            }

            $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

            $exsvlQuery = "SELECT vl_sample_id, sample_code
                            FROM form_vl AS vl WHERE remote_sample_code=?";
            $exsvlResult = $db->rawQuery($exsvlQuery, [$request['remote_sample_code']]);
            if (!empty($exsvlResult)) {

                $removeMoreKeys = array(
                    'sample_code',
                    'sample_code_key',
                    'sample_code_format',
                    'sample_batch_id',
                    'lab_id',
                    'vl_test_platform',
                    'sample_received_at_hub_datetime',
                    'sample_received_at_vl_lab_datetime',
                    'sample_tested_datetime',
                    'result_dispatched_datetime',
                    'is_sample_rejected',
                    'reason_for_sample_rejection',
                    'rejection_on',
                    'result_value_absolute',
                    'result_value_absolute_decimal',
                    'result_value_text',
                    'result',
                    'result_value_log',
                    'result_value_hiv_detection',
                    'reason_for_failure',
                    'result_reviewed_by',
                    'result_reviewed_datetime',
                    'vl_focal_person',
                    'vl_focal_person_phone_number',
                    'tested_by',
                    'result_approved_by',
                    'result_approved_datetime',
                    'lab_tech_comments',
                    'reason_for_vl_result_changes',
                    'revised_by',
                    'revised_on',
                    'last_modified_by',
                    'last_modified_datetime',
                    'manual_result_entry',
                    'result_status',
                    'data_sync',
                    'result_printed_datetime',
                    'vl_result_category'
                );

                $request = array_diff_key($request, array_flip($removeMoreKeys));

                $formAttributes = $general->jsonToSetString(
                    $request['form_attributes'],
                    'form_attributes',
                    ['syncTransactionId' => $transactionId]
                );
                $request['form_attributes'] = $db->func($formAttributes);

                $db = $db->where('vl_sample_id', $exsvlResult[0]['vl_sample_id']);
                $id = $db->update('form_vl', $request);
            } else {
                $request['source_of_request'] = 'vlsts';
                if (!empty($request['sample_collection_date'])) {

                    $request['source_of_request'] = "vlsts";
                    $formAttributes = $general->jsonToSetString(
                        $request['form_attributes'],
                        'form_attributes',
                        ['syncTransactionId' => $transactionId]
                    );
                    $request['form_attributes'] = $db->func($formAttributes);
                    //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                    $request['data_sync'] = 0;
                    $id = $db->insert('form_vl', $request);
                }
            }
        }
        $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'receive-requests', 'vl', $url, $payload, $jsonResponse, 'json', $labId);
    }
}


/*
  ****************************************************************
  *  EID TEST REQUESTS
  ****************************************************************
  */

$request = [];
//$remoteSampleCodeList = [];
if (isset($systemConfig['modules']['eid']) && $systemConfig['modules']['eid'] === true) {
    $url = $remoteUrl . '/remote/remote/eid-test-requests.php';
    $data = array(
        'labId' => $labId,
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

        $options = [
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);


        $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_SCHEMA = ? AND table_name='form_eid'";
        $allColResult = $db->rawQuery($allColumns, [SYSTEM_CONFIG['database']['db']]);
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
            //'request_created_by',
            //'last_modified_by',
            //'request_created_datetime',
            'data_sync'
        );

        $columnList = array_diff($columnList, $removeKeys);
        $counter = 0;
        foreach ($parsedData as $key => $remoteData) {
            $counter++;
            $request = [];
            foreach ($columnList as $colName) {
                if (isset($remoteData[$colName])) {
                    $request[$colName] = $remoteData[$colName];
                } else {
                    $request[$colName] = null;
                }
            }


            //$remoteSampleCodeList[] = $request['remote_sample_code'];
            $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

            $exsvlQuery = "SELECT eid_id,sample_code FROM form_eid AS vl
                            WHERE remote_sample_code=?";
            $exsvlResult = $db->rawQuery($exsvlQuery, [$request['remote_sample_code']]);
            if ($exsvlResult) {

                $removeMoreKeys = array(
                    'sample_code',
                    'sample_code_key',
                    'sample_code_format',
                    'sample_batch_id',
                    'sample_received_at_vl_lab_datetime',
                    'eid_test_platform',
                    'import_machine_name',
                    'sample_tested_datetime',
                    'is_sample_rejected',
                    'lab_id',
                    'result',
                    'tested_by',
                    'lab_tech_comments',
                    'result_approved_by',
                    'result_approved_datetime',
                    'revised_by',
                    'revised_on',
                    'result_reviewed_by',
                    'result_reviewed_datetime',
                    'result_dispatched_datetime',
                    'reason_for_changing',
                    'result_status',
                    'data_sync',
                    'reason_for_sample_rejection',
                    'rejection_on',
                    'last_modified_by',
                    'result_printed_datetime',
                    'last_modified_datetime'
                );

                $request = array_diff_key($request, array_flip($removeMoreKeys));

                $formAttributes = $general->jsonToSetString(
                    $request['form_attributes'],
                    'form_attributes',
                    ['syncTransactionId' => $transactionId]
                );
                $request['form_attributes'] = $db->func($formAttributes);
                $db = $db->where('eid_id', $exsvlResult[0]['eid_id']);
                $id = $db->update('form_eid', $request);
            } else {
                if (!empty($request['sample_collection_date'])) {

                    $request['source_of_request'] = "vlsts";
                    $formAttributes = $general->jsonToSetString(
                        $request['form_attributes'],
                        'form_attributes',
                        ['syncTransactionId' => $transactionId]
                    );
                    $request['form_attributes'] = $db->func($formAttributes);
                    //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                    $request['data_sync'] = 0;

                    $id = $db->insert('form_eid', $request);
                }
            }
        }

        $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'receive-requests', 'eid', $url, $json_data, $jsonResponse, 'json', $labId);
    }
}


/*
  ****************************************************************
  *  COVID-19 TEST REQUESTS
  ****************************************************************
  */
$request = [];
//$remoteSampleCodeList = [];
if (isset($systemConfig['modules']['covid19']) && $systemConfig['modules']['covid19'] === true) {
    $url = $remoteUrl . '/remote/remote/covid-19-test-requests.php';
    $data = array(
        'labId' => $labId,
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
            //'request_created_by',
            //'last_modified_by',
            //'request_created_datetime',
            'data_sync'
        );


        $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_SCHEMA = ? AND table_name='form_covid19'";
        $allColResult = $db->rawQuery($allColumns, [SYSTEM_CONFIG['database']['db']]);
        $columnList = array_map('current', $allColResult);
        $columnList = array_diff($columnList, $removeKeys);


        $options = [
            'pointer' => '/result',
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);
        $counter = 0;
        foreach ($parsedData as $key => $remoteData) {
            $counter++;
            $request = [];
            $covid19Id = $remoteData['covid19_id'];
            foreach ($columnList as $colName) {
                if (isset($remoteData[$colName])) {
                    $request[$colName] = $remoteData[$colName];
                } else {
                    $request[$colName] = null;
                }
            }


            //$remoteSampleCodeList[] = $request['remote_sample_code'];
            $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

            //check exist remote
            $exsvlQuery = "SELECT covid19_id,sample_code FROM form_covid19 AS vl
                                WHERE remote_sample_code=?";
            $exsvlResult = $db->rawQuery($exsvlQuery, [$request['remote_sample_code']]);
            if ($exsvlResult) {

                $removeMoreKeys = array(
                    'sample_code',
                    'sample_code_key',
                    'sample_code_format',
                    'sample_batch_id',
                    'sample_received_at_vl_lab_datetime',
                    'lab_id',
                    'sample_condition',
                    'lab_technician',
                    'testing_point',
                    'is_sample_rejected',
                    'result',
                    'result_sent_to_source',
                    'other_diseases',
                    'tested_by',
                    'result_approved_by',
                    'result_approved_datetime',
                    'is_result_authorised',
                    'authorized_by',
                    'authorized_on',
                    'revised_by',
                    'revised_on',
                    'result_reviewed_by',
                    'result_reviewed_datetime',
                    'reason_for_changing',
                    'rejection_on',
                    'result_status',
                    'data_sync',
                    'reason_for_sample_rejection',
                    'last_modified_by',
                    'result_printed_datetime',
                    'result_dispatched_datetime',
                    'last_modified_datetime'
                );

                $request = array_diff_key($request, array_flip($removeMoreKeys));

                $formAttributes = $general->jsonToSetString(
                    $request['form_attributes'],
                    'form_attributes',
                    ['syncTransactionId' => $transactionId]
                );
                $request['form_attributes'] = $db->func($formAttributes);

                $db = $db->where('covid19_id', $exsvlResult[0]['covid19_id']);
                $db->update('form_covid19', $request);
                $id = $exsvlResult[0]['covid19_id'];
            } else {
                if (!empty($request['sample_collection_date'])) {
                    $request['source_of_request'] = "vlsts";
                    $formAttributes = $general->jsonToSetString(
                        $request['form_attributes'],
                        'form_attributes',
                        ['syncTransactionId' => $transactionId]
                    );
                    $request['form_attributes'] = $db->func($formAttributes);
                    //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                    $request['data_sync'] = 0;
                    $db->insert('form_covid19', $request);
                    $id = $db->getInsertId();
                }
            }
        }

        $options = [
            'pointer' => '/symptoms',
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);
        foreach ($parsedData as $covid19Id => $symptoms) {
            $db = $db->where('covid19_id', $covid19Id);
            $db->delete("covid19_patient_symptoms");
            foreach ($symptoms as $symId => $symValue) {
                $symptomData = [];
                $symptomData["covid19_id"] = $covid19Id;
                $symptomData["symptom_id"] = $symId;
                $symptomData["symptom_detected"] = $symValue;
                $db->insert("covid19_patient_symptoms", $symptomData);
            }
        }

        $options = [
            'pointer' => '/comorbidities',
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);
        foreach ($parsedData as $covid19Id => $comorbidities) {
            $db = $db->where('covid19_id', $covid19Id);
            $db->delete("covid19_patient_comorbidities");

            foreach ($comorbidities as $comoId => $comorbidityData) {
                $comorbidityData = [];
                $comorbidityData["covid19_id"] = $covid19Id;
                $comorbidityData["comorbidity_id"] = $comoId;
                $comorbidityData["comorbidity_detected"] = $comoValue;
                $db->insert("covid19_patient_comorbidities", $comorbidityData);
            }
        }

        $options = [
            'pointer' => '/testResults',
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);

        foreach ($parsedData as $covid19Id => $testResults) {
            $db = $db->where('covid19_id', $covid19Id);
            $db->delete("covid19_tests");
            foreach ($testResults as $covid19TestData) {
                unset($covid19TestData['test_id']);
                $db->insert("covid19_tests", $covid19TestData);
            }
        }



        $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'receive-requests', 'covid19', $url, $json_data, $jsonResponse, 'json', $labId);
    }
}


/*
****************************************************************
* Hepatitis TEST REQUESTS
****************************************************************
*/
$request = [];
//$remoteSampleCodeList = [];
if (isset($systemConfig['modules']['hepatitis']) && $systemConfig['modules']['hepatitis'] === true) {
    $url = $remoteUrl . '/remote/remote/hepatitis-test-requests.php';
    $data = array(
        'labId' => $labId,
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
            //'request_created_by',
            //'last_modified_by',
            //'request_created_datetime',
            'data_sync'
        );




        $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_SCHEMA = ? AND table_name='form_hepatitis'";
        $allColResult = $db->rawQuery($allColumns, [SYSTEM_CONFIG['database']['db']]);
        $columnList = array_map('current', $allColResult);
        $columnList = array_diff($columnList, $removeKeys);

        $options = [
            'pointer' => '/result',
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);
        $counter = 0;
        foreach ($parsedData as $key => $remoteData) {
            $request = [];
            $hepatitisId = $remoteData['hepatitis_id'];
            foreach ($columnList as $colName) {
                if (isset($remoteData[$colName])) {
                    $request[$colName] = $remoteData[$colName];
                } else {
                    $request[$colName] = null;
                }
            }


            //$remoteSampleCodeList[] = $request['remote_sample_code'];
            $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

            //check exist remote
            $exsvlQuery = "SELECT hepatitis_id,sample_code FROM form_hepatitis AS vl
                WHERE remote_sample_code=?";
            $exsvlResult = $db->rawQuery($exsvlQuery, [$request['remote_sample_code']]);
            if ($exsvlResult) {

                $removeMoreKeys = array(
                    'sample_code',
                    'sample_code_key',
                    'sample_code_format',
                    'sample_batch_id',
                    'sample_received_at_vl_lab_datetime',
                    'lab_id',
                    'sample_condition',
                    'sample_tested_datetime',
                    'vl_testing_site',
                    'is_sample_rejected',
                    'result',
                    'hcv_vl_result',
                    'hbv_vl_result',
                    'hcv_vl_count',
                    'hbv_vl_count',
                    'hepatitis_test_platform',
                    'import_machine_name',
                    'is_result_authorised',
                    'result_reviewed_by',
                    'result_reviewed_datetime',
                    'authorized_by',
                    'authorized_on',
                    'revised_by',
                    'revised_on',
                    'result_status',
                    'result_sent_to_source',
                    'data_sync',
                    'last_modified_by',
                    'last_modified_datetime',
                    'result_printed_datetime',
                    'result_dispatched_datetime',
                    'reason_for_vl_test'
                );

                $request = array_diff_key($request, array_flip($removeMoreKeys));

                $formAttributes = $general->jsonToSetString(
                    $request['form_attributes'],
                    'form_attributes',
                    ['syncTransactionId' => $transactionId]
                );
                $request['form_attributes'] = $db->func($formAttributes);

                $db = $db->where('hepatitis_id', $exsvlResult[0]['hepatitis_id']);
                $db->update('form_hepatitis', $request);
                $id = $exsvlResult[0]['hepatitis_id'];
            } else {
                if (!empty($request['sample_collection_date'])) {
                    $request['source_of_request'] = "vlsts";
                    $formAttributes = $general->jsonToSetString(
                        $request['form_attributes'],
                        'form_attributes',
                        ['syncTransactionId' => $transactionId]
                    );
                    $request['form_attributes'] = $db->func($formAttributes);
                    //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                    $request['data_sync'] = 0;

                    $db->insert('form_hepatitis', $request);
                    $id = $db->getInsertId();
                }
            }
        }

        $options = [
            'pointer' => '/risks',
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);
        foreach ($parsedData as $hepatitisId => $risks) {
            $db = $db->where('hepatitis_id', $hepatitisId);
            $db->delete("hepatitis_risk_factors");

            $rData = [];
            foreach ($risks as  $riskId => $riskValue) {
                $riskFactorsData = [];
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

        $options = [
            'pointer' => '/comorbidities',
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);
        foreach ($parsedData as $hepatitisId => $comorbidities) {
            $db = $db->where('hepatitis_id', $hepatitisId);
            $db->delete("hepatitis_patient_comorbidities");

            $cData = [];
            foreach ($comorbidities as $comoId => $comoValue) {
                $comorbidityData = [];
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


        $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'receive-requests', 'hepatitis', $url, $json_data, $jsonResponse, 'json', $labId);
    }
}

/*
****************************************************************
* TB TEST REQUESTS
****************************************************************
*/
$request = [];
//$remoteSampleCodeList = [];
if (isset($systemConfig['modules']['tb']) && $systemConfig['modules']['tb'] === true) {
    $url = $remoteUrl . '/remote/remote/tb-test-requests.php';
    $data = array(
        'labId' => $labId,
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
            //'request_created_by',
            //'last_modified_by',
            //'request_created_datetime',
            'data_sync'
        );

        $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_SCHEMA = ? AND table_name='form_tb'";
        $allColResult = $db->rawQuery($allColumns, [SYSTEM_CONFIG['database']['db']]);
        $columnList = array_map('current', $allColResult);
        $columnList = array_diff($columnList, $removeKeys);

        $options = [
            'pointer' => '/result',
            'decoder' => new ExtJsonDecoder(true)
        ];
        $parsedData = Items::fromString($jsonResponse, $options);
        $counter = 0;
        foreach ($parsedData as $key => $remoteData) {
            $request = [];
            $tbId = $remoteData['tb_id'];
            foreach ($columnList as $colName) {
                if (isset($remoteData[$colName])) {
                    $request[$colName] = $remoteData[$colName];
                } else {
                    $request[$colName] = null;
                }
            }

            //$remoteSampleCodeList[] = $request['remote_sample_code'];
            $request['last_modified_datetime'] = DateUtility::getCurrentDateTime();

            //check exist remote
            $exsvlQuery = "SELECT tb_id,sample_code FROM form_tb AS vl
                            WHERE remote_sample_code=?";
            $exsvlResult = $db->rawQuery($exsvlQuery, [$request['remote_sample_code']]);
            if ($exsvlResult) {

                $removeMoreKeys = array(
                    'sample_code',
                    'sample_code_key',
                    'sample_code_format',
                    'sample_batch_id',
                    'specimen_quality',
                    'lab_id',
                    'reason_for_tb_test',
                    'tests_requested',
                    'specimen_type',
                    'sample_collection_date',
                    'sample_received_at_lab_datetime',
                    'is_sample_rejected',
                    'result',
                    'xpert_mtb_result',
                    'result_sent_to_source',
                    'result_dispatched_datetime',
                    'result_reviewed_by',
                    'result_reviewed_datetime',
                    'result_approved_by',
                    'result_approved_datetime',
                    'sample_tested_datetime',
                    'tested_by',
                    'rejection_on',
                    'result_status',
                    'data_sync',
                    'reason_for_sample_rejection',
                    'sample_registered_at_lab',
                    'last_modified_by',
                    'last_modified_datetime',
                    'request_created_by',
                    'last_modified_by',
                    'lab_technician'
                );

                $request = array_diff_key($request, array_flip($removeMoreKeys));

                $formAttributes = $general->jsonToSetString(
                    $request['form_attributes'],
                    'form_attributes',
                    ['syncTransactionId' => $transactionId]
                );
                $request['form_attributes'] = $db->func($formAttributes);
                $db = $db->where('tb_id', $exsvlResult[0]['tb_id']);
                $db->update('form_tb', $request);
                $id = $exsvlResult[0]['tb_id'];
            } else {
                if (!empty($request['sample_collection_date'])) {

                    $request['source_of_request'] = "vlsts";
                    $formAttributes = $general->jsonToSetString(
                        $request['form_attributes'],
                        'form_attributes',
                        ['syncTransactionId' => $transactionId]
                    );
                    $request['form_attributes'] = $db->func($formAttributes);
                    //column data_sync value is 1 equal to data_sync done.value 0 is not done.
                    $request['data_sync'] = 0;

                    $db->insert('form_tb', $request);
                    $id = $db->getInsertId();
                }
            }
        }


        $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'receive-requests', 'tb', $url, $json_data, $jsonResponse, 'json', $labId);
    }
}

/* Get instance id for update last_remote_results_sync */
$instanceResult = $db->rawQueryOne("SELECT vlsm_instance_id, instance_facility_name FROM s_vlsm_instance");

/* Update last_remote_results_sync in s_vlsm_instance */
$db = $db->where('vlsm_instance_id', $instanceResult['vlsm_instance_id']);
$id = $db->update('s_vlsm_instance', array('last_remote_requests_sync' => DateUtility::getCurrentDateTime()));

if (isset($forceSyncModule) && trim($forceSyncModule) != "" && isset($manifestCode) && trim($manifestCode) != "") {
    return 1;
}
