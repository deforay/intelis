<?php
//this file receives the lab results and updates in the remote db

require_once(dirname(__FILE__) . "/../../../startup.php");

$jsonResponse = file_get_contents('php://input');

$cQuery = "SELECT * FROM global_config";
$cResult = $db->query($cQuery);
$arr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($cResult); $i++) {
    $arr[$cResult[$i]['name']] = $cResult[$i]['value'];
}

$general = new \Vlsm\Models\General();
$usersModel = new \Vlsm\Models\Users();
$app = new \Vlsm\Models\App();

//system config
$systemConfigQuery = "SELECT * from system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
    $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}
//get remote data
if (trim($sarr['sc_testing_lab_id']) == '') {
    $sarr['sc_testing_lab_id'] = "''";
}

if (!empty($jsonResponse) && $jsonResponse != '[]') {


    $allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS where TABLE_SCHEMA = '" . SYSTEM_CONFIG['dbName'] . "' AND table_name='form_eid'";
    $allColResult = $db->rawQuery($allColumns);
    $oneDimensionalArray = array_map('current', $allColResult);
    $sampleCode = array();

    $parsedData = \JsonMachine\JsonMachine::fromString($jsonResponse, "/result");
    $lab = array();
    $counter = 0;
    foreach ($parsedData as $key => $remoteData) {
        $counter++;
        foreach ($oneDimensionalArray as $result) {
            if (isset($remoteData[$result])) {
                $lab[$result] = $remoteData[$result];
            } else {
                $lab[$result] = null;
            }
        }
        //remove result value

        $removeKeys = array(
            'eid_id',
            'sample_package_id',
            'sample_package_code',
            //'last_modified_by',
            'request_created_by',
        );
        foreach ($removeKeys as $keys) {
            unset($lab[$keys]);
        }



        if (isset($remoteData['approved_by_name']) && $remoteData['approved_by_name'] != '') {

            $lab['result_approved_by'] = $usersModel->addUserIfNotExists($remoteData['approved_by_name']);
            $lab['result_approved_datetime'] =  $general->getDateTime();
            // we dont need this now
            //unset($remoteData['approved_by_name']);
        }

        $lab['data_sync'] = 1; //data_sync = 1 means data sync done. data_sync = 0 means sync is not yet done.
        $lab['last_modified_datetime'] = $general->getDateTime();

        unset($lab['request_created_by']);
        unset($lab['last_modified_by']);
        unset($lab['request_created_datetime']);

        if ($lab['result_status'] != 7 && $lab['result_status'] != 4) {
            unset($lab['result']);
            unset($lab['is_sample_rejected']);
            unset($lab['reason_for_sample_rejection']);
        }

        // Checking if Remote Sample Code is set, if not set we will check if Sample Code is set
        if (isset($lab['remote_sample_code']) && $lab['remote_sample_code'] != '') {
            $sQuery = "SELECT eid_id,sample_code,remote_sample_code,remote_sample_code_key FROM form_eid WHERE remote_sample_code='" . $lab['remote_sample_code'] . "'";
        } else if (isset($lab['sample_code']) && !empty($lab['sample_code']) && !empty($lab['facility_id']) && !empty($lab['lab_id'])) {
            $sQuery = "SELECT eid_id,sample_code,remote_sample_code,remote_sample_code_key FROM form_eid WHERE sample_code='" . $lab['sample_code'] . "' AND facility_id = " . $lab['facility_id'];
        } else {

            $sampleCode[] = $lab['sample_code'];
            continue;
        }
        $lab['source_of_request'] = 'vlsts';
        $sResult = $db->rawQuery($sQuery);
        if ($sResult) {
            $db = $db->where('eid_id', $sResult[0]['eid_id']);
            $id = $db->update('form_eid', $lab);
        } else {
            $id = $db->insert('form_eid', $lab);
        }

        if ($id > 0 && isset($lab['sample_code'])) {
            $sampleCode[] = $lab['sample_code'];
        }
    }
    if ($counter > 0) {
        $app->addApiTracking(null, $counter, 'results', 'eid', null, $sarr['sc_testing_lab_id'], 'sync-api');
    }
}

echo json_encode($sampleCode);
