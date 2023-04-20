<?php
ob_start();
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


//system config
$systemConfigQuery = "SELECT * FROM system_config";
$systemConfigResult = $db->query($systemConfigQuery);
$sarr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($systemConfigResult); $i++) {
    $sarr[$systemConfigResult[$i]['name']] = $systemConfigResult[$i]['value'];
}
$tableName = $_POST['tableName'];
$fieldName = $_POST['fieldName'];
$value = trim($_POST['value']);
$fnct = $_POST['fnct'];
$data = 0;
if ($value != '') {
    if ($fnct == '' || $fnct == 'null') {
        $sQuery = "SELECT * FROM $tableName WHERE $fieldName= ?";
        $parameters = array($value);
        $result = $db->rawQuery($sQuery, $parameters);
        if ($result) {
            $data = base64_encode($result[0]['vl_sample_id']) . "##" . $result[0][$fieldName];
        } else {
            if ($sarr['sc_user_type'] == 'vluser') {
                $sQuery = "SELECT * FROM $tableName WHERE remote_sample_code= ?";
                $parameters = array($value);
                $result = $db->rawQuery($sQuery, $parameters);
                if ($result) {
                    $data = base64_encode($result[0]['vl_sample_id']) . "##" . $result[0]['remote_sample_code'];
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
            $sQuery = "SELECT * FROM $tableName WHERE $fieldName= ? AND $table[0]!= ?";
            $parameters = array($value, $table[1]);
            $result = $db->rawQuery($sQuery, $parameters);
            if ($result) {
                $data = base64_encode($result[0]['vl_sample_id']) . "##" . $result[0][$fieldName];
            } else {
                if ($sarr['sc_user_type'] == 'vluser') {
                    $sQuery = "SELECT * from $tableName where remote_sample_code= ? and $table[0]!= ?";
                    $parameters = array($value, $table[1]);
                    $result = $db->rawQuery($sQuery, $parameters);
                    if ($result) {
                        $data = base64_encode($result[0]['vl_sample_id']) . "##" . $result[0]['remote_sample_code'];
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