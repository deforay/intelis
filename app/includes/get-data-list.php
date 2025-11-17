<?php

use Laminas\Diactoros\ServerRequest;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\GeoLocationsService;
use App\Services\DatabaseService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var GeoLocationsService $geoDb */
$geoDb = ContainerRegistry::get(GeoLocationsService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$text = '';
$field = $_GET['fieldName'];
$table = $_GET['tableName'];
$returnField = (empty($_GET['returnField'])) ? null : $_GET['returnField'];
$limit = (empty($_GET['limit'])) ? null : $_GET['limit'];
$text = (empty($_GET['q'])) ? null : $_GET['q'];

// Set value as id
if (!empty($text) && $text != "") {
    if (isset($returnField) && $returnField != "") {
        $cQuery = "SELECT DISTINCT $returnField FROM $table WHERE $field like '%" . $text . "%' AND $field is not null";
    } else {
        $cQuery = "SELECT DISTINCT $field FROM $table WHERE $field like '%" . $text . "%' AND $field is not null";
    }
} elseif (isset($returnField) && $returnField != "") {
    $cQuery = "SELECT DISTINCT $returnField FROM $table WHERE $field is not null";
} else {
    $cQuery = "SELECT DISTINCT $field FROM $table WHERE $field is not null";
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
            $echoResult[] = ["id" => $row[$field], "text" => ($row[$field])];
        }
    } else {
        $echoResult[] = ["id" => $text, 'text' => $text];
    }

    $result = ["result" => $echoResult];
    echo json_encode($result);
}
