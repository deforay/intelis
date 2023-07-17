<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GeoLocationsService;

/** @var GeoLocationsService $geoDb */
$geoDb = ContainerRegistry::get(GeoLocationsService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_GET = $request->getQueryParams();

$text = '';
$fieldId = $_GET['fieldId'] ?? null;
$field = $_GET['fieldName'] ?? null;
$table = $_GET['tableName'] ?? null;
$returnField = (!empty($_GET['returnField'])) ? $_GET['returnField'] : null;
$limit = (!empty($_GET['limit'])) ? $_GET['limit'] : null;
$text = (!empty($_GET['q'])) ? $_GET['q'] : null;

// Set value as id
$selectField = $field;
$fieldId = (!empty($fieldId))?$fieldId:$field;
if(!empty($fieldId)){
    $selectField = $field . ', '. $fieldId;
}

if (!empty($text) && $text != "") {
    if (isset($returnField) && $returnField != "") {
        $cQuery = "SELECT DISTINCT $returnField FROM $table WHERE $field like '%" . $text . "%' AND $field is not null";
    } else {
        $cQuery = "SELECT DISTINCT $selectField FROM $table WHERE $field like '%" . $text . "%' AND $field is not null";
    }
} else {
    if (isset($returnField) && $returnField != "") {
        $cQuery = "SELECT DISTINCT $returnField FROM $table WHERE $field is not null";
    } else {
        $cQuery = "SELECT DISTINCT $selectField FROM $table WHERE $field is not null";
    }
}
if(!empty($_GET['status'])){
    $cQuery .= " AND " . $_GET['status'] . " like 'active' ";
}
if (!empty($limit) && $limit > 0) {
    $cQuery .= " limit " . $limit;
}
$cResult = $db->rawQuery($cQuery);
if (isset($returnField) && $returnField != "") {
    echo $cResult[0][$returnField];
} else {
    $echoResult = [];
    if (count($cResult) > 0) {
        foreach ($cResult as $row) {
            $echoResult[] = array("id" => $row[$fieldId], "text" => ucwords($row[$field]));
        }
    } else {
        $echoResult[] = array("id" => $text, 'text' => ucwords($text));
    }

    $result = array("result" => $echoResult);
    echo json_encode($result);
}
