<?php

use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$tableName = $_POST['tableName'];
$fieldName = $_POST['fieldName'];
$value = trim($_POST['value']);
$fnct = $_POST['fnct'];
$data = 0; $multiple = array();
if ($value != '') {
    if(!empty($_POST['type']) && $_POST['type'] == "multiple"){
        foreach(explode(",", $value) as $row){
            $multiple[] = "'".trim($row)."'";
        }
        $value = implode(",", $multiple);
    }
    if ($fnct == '' || $fnct == 'null') {
        if(!empty($_POST['type']) && $_POST['type'] == "multiple"){
            $sQuery = "SELECT * from $tableName where $fieldName IN($value)";
            $result = $db->rawQuery($sQuery);
        }else{
            $sQuery = "SELECT * from $tableName where $fieldName= ?";
            $result = $db->rawQuery($sQuery, array($value));
        }
        // $parameters = array($value);
        $data = count($result);
    } else {
        $table = explode("##", $fnct);
        // first trying $table[1] without quotes. If this does not work, then in catch we try with single quotes
        try {
            //$sql = $db->select()->from($tableName)->where($fieldName . "=" . "'$value'")->where($table[0] . "!=" . $table[1])->where("company_id=" . $this->_session->company_id);
            if(!empty($_POST['type']) && $_POST['type'] == "multiple"){
                $sQuery = "SELECT * from $tableName where $fieldName IN($value) and $table[0]!= ?";
                $result = $db->rawQuery($sQuery, array($table[1]));
            }else{
                $sQuery = "SELECT * from $tableName where $fieldName= ? and $table[0]!= ?";
                $parameters = array($value, $table[1]);
                $result = $db->rawQuery($sQuery, $parameters);
            }
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
