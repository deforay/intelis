<?php
//this file receives the lab data value and updates in the remote db
$data = json_decode(file_get_contents('php://input'), true);

require_once(dirname(__FILE__) . "/../../startup.php");
require_once(APPLICATION_PATH . '/includes/MysqliDb.php');
require_once(APPLICATION_PATH . '/models/General.php');
require_once(APPLICATION_PATH . '/models/Users.php');

$cQuery = "SELECT * FROM global_config";
$cResult = $db->query($cQuery);
$arr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($cResult); $i++) {
    $arr[$cResult[$i]['name']] = $cResult[$i]['value'];
}

$general = new General($db);
$usersModel = new Model_Users($db);

function var_error_log($object = null)
{
    ob_start();
    var_dump($object);
    error_log(ob_get_clean());
}


$allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS where TABLE_SCHEMA = '" . $systemConfig['dbName'] . "' AND table_name='vl_request_form'";
$allColResult = $db->rawQuery($allColumns);
$oneDimensionalArray = array_map('current', $allColResult);

$sampleCode = array();
if (count($data['result']) > 0) {

    foreach ($data['result'] as $key => $remoteData) {
        $lab = array();
        foreach ($oneDimensionalArray as $columnName) {
            if (isset($remoteData[$columnName])) {
                $lab[$columnName] = $remoteData[$columnName];
            } else {
                $lab[$columnName] = null;
            }
        }
        //remove unwanted columns
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


        if (isset($remoteData['approved_by_name']) && $remoteData['approved_by_name'] != '') {

            $lab['result_approved_by'] = $usersModel->addUserIfNotExists($remoteData['approved_by_name']);
            // we dont need this now
            //unset($remoteData['approved_by_name']);
        }


        //data_sync = 1 means data sync done. data_sync = 0 means sync is not yet done.
        $lab['data_sync'] = 1; 
        $lab['last_modified_datetime'] = $general->getDateTime();

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
            $sQuery = "SELECT vl_sample_id,sample_code,remote_sample_code,remote_sample_code_key FROM vl_request_form WHERE remote_sample_code='" . $lab['remote_sample_code'] . "'";
        } else if (isset($lab['sample_code']) && $lab['sample_code'] != '') {
            //error_log("INSIDE LOCAL");
            $sQuery = "SELECT vl_sample_id,sample_code,remote_sample_code,remote_sample_code_key FROM vl_request_form WHERE sample_code='" . $lab['sample_code'] . "' AND facility_id = " . $lab['facility_id'];
            //error_log($sQuery);
        }

        try{
            $sResult = $db->rawQuery($sQuery);

            //$lab['result_printed_datetime'] = null;            
    
            if ($sResult) {
                $db = $db->where('vl_sample_id', $sResult[0]['vl_sample_id']);
                $id = $db->update('vl_request_form', $lab);
            } else {
                $id = $db->insert('vl_request_form', $lab);
            }
        }
        catch(Exception $e){
            continue;
        }

        if ($id > 0 && isset($lab['sample_code'])) {
            $sampleCode[] = $lab['sample_code'];
        }
    }
}

echo json_encode($sampleCode);
