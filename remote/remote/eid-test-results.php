<?php
//this file is receive lab data value and update in remote db
$data = json_decode(file_get_contents('php://input'), true);

require_once(dirname(__FILE__) . "/../../startup.php");
require_once(APPLICATION_PATH . '/includes/MysqliDb.php');
//require_once(APPLICATION_PATH . '/models/General.php');
//require_once(APPLICATION_PATH . '/models/Users.php');

$cQuery = "SELECT * FROM global_config";
$cResult = $db->query($cQuery);
$arr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($cResult); $i++) {
    $arr[$cResult[$i]['name']] = $cResult[$i]['value'];
}

$general = new \Vlsm\Models\General($db);
$usersModel = new \Vlsm\Models\Users($db);

$allColumns = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS where TABLE_SCHEMA = '" . $systemConfig['dbName'] . "' AND table_name='eid_form'";
$allColResult = $db->rawQuery($allColumns);
$oneDimensionalArray = array_map('current', $allColResult);
$sampleCode = array();
if (count($data['result']) > 0) {
    $lab = array();
    foreach ($data['result'] as $key => $remoteData) {
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
            $sQuery = "SELECT eid_id,sample_code,remote_sample_code,remote_sample_code_key FROM eid_form WHERE remote_sample_code='" . $lab['remote_sample_code'] . "'";
        } else if (isset($lab['sample_code']) && !empty($lab['sample_code']) && !empty($lab['facility_id']) && !empty($lab['lab_id'])) {
            $sQuery = "SELECT eid_id,sample_code,remote_sample_code,remote_sample_code_key FROM eid_form WHERE sample_code='" . $lab['sample_code'] . "' AND facility_id = " . $lab['facility_id'];
        } else {

            $sampleCode[] = $lab['sample_code'];
            continue;
        }

        $sResult = $db->rawQuery($sQuery);
        if ($sResult) {
            $db = $db->where('eid_id', $sResult[0]['eid_id']);
            $id = $db->update('eid_form', $lab);
        } else {
            $id = $db->insert('eid_form', $lab);
        }

        if ($id > 0 && isset($lab['sample_code'])) {
            $sampleCode[] = $lab['sample_code'];
        }
    }
}

echo json_encode($sampleCode);
