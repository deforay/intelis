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

// The table, the columns and the status column arrive as identifiers and land in
// the query as such, so each has to be one the pages actually ask for: every
// caller of this endpoint is listed here. A request naming anything else gets
// the empty answer instead of a query.
$lookups = [
    'lab_storage' => ['columns' => ['storage_code', 'storage_id'], 'status' => []],
    'form_vl' => ['columns' => ['request_clinician_name'], 'status' => []],
    'form_eid' => ['columns' => ['clinician_name'], 'status' => []],
    'r_vl_sample_rejection_reasons' => ['columns' => ['rejection_type'], 'status' => []],
    'r_eid_sample_rejection_reasons' => ['columns' => ['rejection_type'], 'status' => []],
    'r_covid19_sample_rejection_reasons' => ['columns' => ['rejection_type'], 'status' => []],
    'r_hepatitis_sample_rejection_reasons' => ['columns' => ['rejection_type'], 'status' => []],
    'r_tb_sample_rejection_reasons' => ['columns' => ['rejection_type'], 'status' => []],
    'r_cd4_sample_rejection_reasons' => ['columns' => ['rejection_type'], 'status' => []],
    'r_generic_sample_rejection_reasons' => [
        'columns' => ['rejection_type', 'rejection_reason_name', 'rejection_reason_id'],
        'status' => ['rejection_reason_status'],
    ],
    'r_generic_test_methods' => ['columns' => ['test_method_name', 'test_method_id'], 'status' => ['test_method_status']],
    'r_generic_test_categories' => ['columns' => ['test_category_name', 'test_category_id'], 'status' => ['test_category_status']],
    'r_generic_test_reasons' => ['columns' => ['test_reason', 'test_reason_id'], 'status' => ['test_reason_status']],
    'r_generic_test_failure_reasons' => ['columns' => ['test_failure_reason', 'test_failure_reason_id'], 'status' => ['test_failure_reason_status']],
];
$lookup = is_string($table) ? ($lookups[$table] ?? null) : null;
$knownColumn = static fn($column): bool => $lookup !== null && is_string($column) && in_array($column, $lookup['columns'], true);
$isKnownLookup = $knownColumn($field)
    && (empty($fieldId) || $knownColumn($fieldId))
    && (!isset($returnField) || $returnField == "" || $knownColumn($returnField))
    && (empty($_GET['status']) || (is_string($_GET['status']) && in_array($_GET['status'], $lookup['status'], true)));
$statusColumn = $isKnownLookup && !empty($_GET['status']) ? (string) $_GET['status'] : null;

// Set value as id
$selectField = $field;
$fieldId = (empty($fieldId)) ? $field : $fieldId;
if (!empty($fieldId)) {
    $selectField = "$field, $fieldId";
}

if (!empty($text) && $text != "") {
    if (isset($returnField) && $returnField != "") {
        $cQuery = "SELECT DISTINCT $returnField FROM $table WHERE $field like '%" . $db->escape((string) $text) . "%' AND $field is not null";
    } else {
        $cQuery = "SELECT DISTINCT $selectField FROM $table WHERE $field like '%" . $db->escape((string) $text) . "%' AND $field is not null";
    }
} elseif (isset($returnField) && $returnField != "") {
    $cQuery = "SELECT DISTINCT $returnField FROM $table WHERE $field is not null";
} else {
    $cQuery = "SELECT DISTINCT $selectField FROM $table WHERE $field is not null";
}
if ($statusColumn !== null) {
    $cQuery .= " AND $statusColumn like 'active' ";
}
if (!empty($_GET['labId'])) {
    $cQuery .= " AND lab_id = " . (int) $_GET['labId'];
}
if (!empty($_GET['facilityId'])) {
    $cQuery .= " AND facility_id = " . (int) $_GET['facilityId'];
}
if (!empty($_GET['group'])) {
    $cQuery .= " GROUP BY '" . $db->escape((string) $_GET['group']) . "'";
}
if (!empty($limit) && $limit > 0) {
    $cQuery .= " limit " . (int) $limit;
}
$cResult = $isKnownLookup ? $db->rawQuery($cQuery) : [];
if (isset($returnField) && $returnField != "") {
    echo $cResult[0][$returnField] ?? '';
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
