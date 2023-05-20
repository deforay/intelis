<?php

use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitize values before using them below
$_POST = array_map('htmlspecialchars', $_POST);

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
        $data = count($result);
    } else {
        $table = explode("##", $fnct);
        // first trying $table[1] without quotes. If this does not work, then in catch we try with single quotes
        try {
            //$sql = $db->select()->from($tableName)->where($fieldName . "=" . "'$value'")->where($table[0] . "!=" . $table[1])->where("company_id=" . $this->_session->company_id);
            $sQuery = "SELECT * from $tableName where $fieldName= ? and $table[0]!= ?";
            $parameters = array($value, $table[1]);
            $result = $db->rawQuery($sQuery, $parameters);
            $data = count($result);
        } catch (Exception $e) {
            error_log($exc->getMessage());
            error_log($exc->getTraceAsString());
            $sQuery = "SELECT * from $tableName where $fieldName= ? and $table[0]!= ?";
            error_log($sQuery);
        }
    }
}

if ($data > 0) {
    $data = '1';
} else {
    $data = '0';
}
echo $data;
