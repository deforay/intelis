<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
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