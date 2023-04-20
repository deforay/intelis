<?php

use App\Models\General;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$general = new General();
$systemType = $general->getSystemConfig('sc_user_type');

$tableName = $_POST['tableName'];
$fieldName = $_POST['fieldName'];
$value = trim($_POST['value']);
$fnct = $_POST['fnct'];
$data = 0;
if ($value != '') {

    $tableInfo = [];
    if (!empty($fnct)) {
        $tableInfo = explode("##", $fnct);
    }

    if ($systemType == 'remoteuser') {
        $fieldName = 'remote_sample_code';
    }

    $parameters = array($value);

    $sQuery = "SELECT $fieldName FROM $tableName WHERE $fieldName= ?";

    if (!empty($tableInfo)) {
        $sQuery .= " AND $tableInfo[0] != ?";
        $parameters[] = $tableInfo[1];
    }
    $result = $db->rawQuery($sQuery, $parameters);

    if ($result) {
        $data = base64_encode($result[0]['covid19_id']) . "##" . $result[0][$fieldName];
    } else {
        $data = 0;
    }
}

echo $data;