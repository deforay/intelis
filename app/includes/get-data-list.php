<?php

use Psr\Http\Message\ServerRequestInterface;
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
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_GET = _sanitizeInput($request->getQueryParams());

$text = '';
$field = $_GET['fieldName'];
$table = $_GET['tableName'];
$returnField = (empty($_GET['returnField'])) ? null : $_GET['returnField'];
$limit = (empty($_GET['limit'])) ? null : $_GET['limit'];
$text = (empty($_GET['q'])) ? null : $_GET['q'];

// The table and the columns arrive as identifiers and land in the query as such,
// so each has to be one the pages actually ask for: every caller of this endpoint
// is listed here, a looked-up column with the one column it may return. A request
// naming anything else gets the empty answer instead of a query.
$lookups = [
    'form_vl' => [
        'request_clinician_name' => 'request_clinician_phone_number',
        'vl_focal_person' => 'vl_focal_person_phone_number',
    ],
    'form_cd4' => [
        'cd4_focal_person' => 'cd4_focal_person_phone_number',
    ],
    'form_generic' => [
        'request_clinician_name' => 'request_clinician_phone_number',
        'testing_lab_focal_person' => 'testing_lab_focal_person_phone_number',
    ],
];
$isKnownLookup = is_string($table) && is_string($field) && isset($lookups[$table][$field])
    && (!isset($returnField) || $returnField == "" || $returnField === $lookups[$table][$field]);

// Set value as id
if (!empty($text) && $text != "") {
    if (isset($returnField) && $returnField != "") {
        $cQuery = "SELECT DISTINCT $returnField FROM $table WHERE $field like '%" . $db->escape((string) $text) . "%' AND $field is not null";
    } else {
        $cQuery = "SELECT DISTINCT $field FROM $table WHERE $field like '%" . $db->escape((string) $text) . "%' AND $field is not null";
    }
} elseif (isset($returnField) && $returnField != "") {
    $cQuery = "SELECT DISTINCT $returnField FROM $table WHERE $field is not null";
} else {
    $cQuery = "SELECT DISTINCT $field FROM $table WHERE $field is not null";
}
if (!empty($limit) && $limit > 0) {
    $cQuery .= " limit " . (int) $limit;
}
$cResult = $isKnownLookup ? $db->rawQuery($cQuery) : [];
if (isset($returnField) && $returnField != "") {
    echo $cResult[0][$returnField] ?? '';
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
