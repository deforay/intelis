<?php

require_once(dirname(__FILE__) . "/../../../startup.php");

$general = new \Vlsm\Models\General();
$usersModel = new \Vlsm\Models\Users();
$app = new \Vlsm\Models\App();

try {
    //this file receives the lab results and updates in the remote db
    $jsonResponse = file_get_contents('php://input');


    $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS where TABLE_SCHEMA = '" . SYSTEM_CONFIG['dbName'] . "' AND table_name='form_vl'";
    $allColResult = $db->rawQuery($allColumns);
    $oneDimensionalArray = array_map('current', $allColResult);

    $transactionId = $general->generateUUID();

    $sampleCode = array();
    $labId = null;
    if (!empty($jsonResponse) && $jsonResponse != '[]') {


        $resultData = array();
        $options = [
            'decoder' => new \JsonMachine\JsonDecoder\ExtJsonDecoder(true)
        ];
        $parsedData = \JsonMachine\Items::fromString($jsonResponse, $options);
        foreach ($parsedData as $name => $data) {
            if ($name === 'labId') {
                $labId = $data;
            } elseif ($name === 'result') {
                $resultData = $data;
            }
        }



        $counter = 0;
        foreach ($resultData as $key => $resultRow) {

            $counter++;
            $lab = array();
            foreach ($oneDimensionalArray as $columnName) {
                if (isset($resultRow[$columnName])) {
                    $lab[$columnName] = $resultRow[$columnName];
                } else {
                    $lab[$columnName] = null;
                }
            }
            //remove unwan  ted columns
            $unwantedColumns = array(
                'vl_sample_id',
                'sample_package_id',
                'sample_package_code',
                //'last_modified_by',
                'request_created_by',
                'result_printed_datetime'
            );
            foreach ($unwantedColumns as $removeColumn) {
                unset($lab[$removeColumn]);
            }


            if (isset($resultRow['approved_by_name']) && $resultRow['approved_by_name'] != '') {

                $lab['result_approved_by'] = $usersModel->addUserIfNotExists($resultRow['approved_by_name']);
                $lab['result_approved_datetime'] =  $general->getCurrentDateTime();
                // we dont need this now
                //unset($resultRow['approved_by_name']);
            }


            //data_sync = 1 means data sync done. data_sync = 0 means sync is not yet done.
            $lab['data_sync'] = 1;
            $lab['last_modified_datetime'] = $general->getCurrentDateTime();

            // unset($lab['request_created_by']);
            // unset($lab['last_modified_by']);
            // unset($lab['request_created_datetime']);

            if ($lab['result_status'] != 7 && $lab['result_status'] != 4) {
                unset($lab['result']);
                unset($lab['result_value_log']);
                unset($lab['result_value_absolute']);
                unset($lab['result_value_text']);
                unset($lab['result_value_absolute_decimal']);
                unset($lab['is_sample_rejected']);
                unset($lab['reason_for_sample_rejection']);
            }

            // Checking if Remote Sample Code is set, if not set we will check if Sample Code is set
            if (isset($lab['remote_sample_code']) && $lab['remote_sample_code'] != '') {
                //error_log("INSIDE REMOTE");
                $sQuery = "SELECT vl_sample_id,sample_code,remote_sample_code,remote_sample_code_key FROM form_vl WHERE remote_sample_code='" . $lab['remote_sample_code'] . "'";
            } elseif (isset($lab['sample_code']) && $lab['sample_code'] != '') {
                //error_log("INSIDE LOCAL");
                $sQuery = "SELECT vl_sample_id,sample_code,remote_sample_code,remote_sample_code_key FROM form_vl WHERE sample_code='" . $lab['sample_code'] . "' AND facility_id = " . $lab['facility_id'];
                //error_log($sQuery);
            }

            try {
                //$lab['source_of_request'] = 'vlsts';
                $sResult = $db->rawQuery($sQuery);
                if ($sResult) {
                    $db = $db->where('vl_sample_id', $sResult[0]['vl_sample_id']);
                    $id = $db->update('form_vl', $lab);
                } else {
                    $id = $db->insert('form_vl', $lab);
                }
            } catch (\Exception $e) {
                error_log($db->getLastError());
                error_log($exc->getMessage());
                error_log($exc->getTraceAsString());
                continue;
            }

            if ($id > 0 && isset($lab['sample_code'])) {
                $sampleCode[] = $lab['sample_code'];
            }
        }
    }

    $payload = json_encode($sampleCode);


    $general->addApiTracking($transactionId, 'vlsm-system', $counter, 'results', 'vl', null, $jsonResponse, $payload, 'json', $labId);

    $sql = 'UPDATE facility_details SET facility_attributes = JSON_SET(facility_attributes, "$.lastResultsSync", ?) WHERE facility_id = ?';
    $db->rawQuery($sql, array($general->getCurrentDateTime(), $labId));

    echo $payload;
    
} catch (\Exception $e) {
    error_log($db->getLastError());
    error_log($exc->getMessage());
    error_log($exc->getTraceAsString());
}
