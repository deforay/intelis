<?php

use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$systemType = $general->getSystemConfig('sc_user_type');

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$tableName = $_POST['tableName'];
$fieldName = $_POST['fieldName'];
$value = trim($_POST['value']);
$fnct = $_POST['fnct'];
$data = 0;
if ($value != '') {
    if ($fnct == '' || $fnct == 'null') {
        $sQuery = "SELECT * from $tableName where $fieldName= ?";
        $parameters = array($value);
        $result = $db->rawQuery($sQuery, $parameters);
        if ($result) {
            $data = base64_encode($result[0]['eid_id']) . "##" . $result[0][$fieldName];
        } else {
            if ($systemType == 'vluser') {
                $sQuery = "SELECT * from $tableName where remote_sample_code= ?";
                $parameters = array($value);
                $result = $db->rawQuery($sQuery, $parameters);
                if ($result) {
                    $data = base64_encode($result[0]['eid_id']) . "##" . $result[0]['remote_sample_code'];
                } else {
                    $data = 0;
                }
            } else {
                $data = 0;
            }
        }
    } else {
        $table = explode("##", $fnct);
        try {
            $sQuery = "SELECT * from $tableName where $fieldName= ? and $table[0]!= ?";
            $parameters = array($value, $table[1]);
            $result = $db->rawQuery($sQuery, $parameters);
            if ($result) {
                $data = base64_encode($result[0]['eid_id']) . "##" . $result[0][$fieldName];
            } else {
                if ($systemType == 'vluser') {
                    $sQuery = "SELECT * from $tableName where remote_sample_code= ? and $table[0]!= ?";
                    $parameters = array($value, $table[1]);
                    $result = $db->rawQuery($sQuery, $parameters);
                    if ($result) {
                        $data = base64_encode($result[0]['eid_id']) . "##" . $result[0]['remote_sample_code'];
                    } else {
                        $data = 0;
                    }
                } else {
                    $data = 0;
                }
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            error_log($e->getTraceAsString());
        }
    }
}
echo $data;
