<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;

/** @var GeoLocationsService $geoDb */
$geoDb = ContainerRegistry::get(GeoLocationsService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$text = '';
$fieldId = $_GET['fieldId'] ?? null;
$field = $_GET['fieldName'] ?? null;
$table = $_GET['tableName'] ?? null;
$returnField = (empty($_GET['returnField'])) ? null : $_GET['returnField'];
$limit = (empty($_GET['limit'])) ? null : $_GET['limit'];
$text = (empty($_GET['q'])) ? null : $_GET['q'];

// Set value as id
$selectField = $field;
$fieldId = (empty($fieldId)) ? $field : $fieldId;
if (!empty($fieldId)) {
    $selectField = "$field, $fieldId";
}

if (!empty($text) && $text != "") {
    if (isset($returnField) && $returnField != "") {
        $cQuery = "SELECT DISTINCT $returnField FROM $table WHERE $field like '%" . $text . "%' AND $field is not null";
    } else {
        $cQuery = "SELECT DISTINCT $selectField FROM $table WHERE $field like '%" . $text . "%' AND $field is not null";
    }
} elseif (isset($returnField) && $returnField != "") {
    $cQuery = "SELECT DISTINCT $returnField FROM $table WHERE $field is not null";
} else {
    $cQuery = "SELECT DISTINCT $selectField FROM $table WHERE $field is not null";
}
if (!empty($_GET['status'])) {
    $cQuery .= " AND " . $_GET['status'] . " like 'active' ";
}
if (!empty($_GET['labId'])) {
    $cQuery .= " AND lab_id = " . $_GET['labId'];
}
if (!empty($_GET['facilityId'])) {
    $cQuery .= " AND facility_id = " . $_GET['facilityId'];
}
if (!empty($_GET['group'])) {
    $cQuery .= " GROUP BY '" . $_GET['group'] . "'";
}
if (!empty($limit) && $limit > 0) {
    $cQuery .= " limit $limit";
}
$cResult = $db->rawQuery($cQuery);
if (isset($returnField) && $returnField != "") {
    echo $cResult[0][$returnField];
} else {
    $echoResult = [];
    if (!empty($cResult)) {
        foreach ($cResult as $row) {
            $echoResult[] = ["id" => $row[$fieldId], "text" => ucwords((string) $row[$field])];
        }
    } else {
        $echoResult[] = ["id" => $text, 'text' => ucwords((string) $text)];
    }

    echo json_encode(["result" => $echoResult]);
}
