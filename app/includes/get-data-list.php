<?php

use App\Services\CommonService;
use App\Services\GeoLocationsService;

$geoDb = new GeoLocationsService($db);
$generalDb = new CommonService($db);
$text = '';
$field = $_GET['fieldName'];
$table = $_GET['tableName'];
$returnField = (isset($_GET['returnField']) && !empty($_GET['returnField'])) ? $_GET['returnField'] : null;
$limit = (isset($_GET['limit']) && !empty($_GET['limit'])) ? $_GET['limit'] : null;
$text = (isset($_GET['q']) && !empty($_GET['q'])) ? $_GET['q'] : null;

if (isset($text) && !empty($text) && $text != "") {
    if (isset($returnField) && $returnField != "") {
        $cQuery = "SELECT DISTINCT $returnField FROM $table WHERE $field like '%" . $text . "%' AND $field is not null";
    } else {
        $cQuery = "SELECT DISTINCT $field FROM $table WHERE $field like '%" . $text . "%' AND $field is not null";
    }
} else {
    if (isset($returnField) && $returnField != "") {
        $cQuery = "SELECT DISTINCT $returnField FROM $table WHERE $field is not null";
    } else {
        $cQuery = "SELECT DISTINCT $field FROM $table WHERE $field is not null";
    }
}
if (isset($limit) && !empty($limit) && $limit > 0) {
    $cQuery .= " limit " . $limit;
}
$cResult = $db->rawQuery($cQuery);
if (isset($returnField) && $returnField != "") {
    echo $cResult[0][$returnField];
} else {
    $echoResult = [];
    if (count($cResult) > 0) {
        foreach ($cResult as $row) {
            $echoResult[] = array("id" => $row[$field], "text" => ($row[$field]));
        }
    } else {
        $echoResult[] = array("id" => $text, 'text' => $text);
    }

    $result = array("result" => $echoResult);
    echo json_encode($result);
}
