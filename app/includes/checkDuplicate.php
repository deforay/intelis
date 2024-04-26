<?php

use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$tableName = $_POST['tableName'];
$fieldName = $_POST['fieldName'];
$value = trim((string) $_POST['value']);
$fnct = $_POST['fnct'];
$data = 0;
$multiple = [];
if (!empty($value) && !empty($fieldName) && !empty($tableName)) {
    $isMultiple = !empty($_POST['type']) && $_POST['type'] == "multiple";
    if ($isMultiple) {
        $value = implode(",", array_map(function ($row) {
            return "'" . trim($row) . "'";
        }, explode(",", $value)));
    }
    try {
        $inCondition = $isMultiple ? "IN(?)" : "= ?";
        $tableCondition = '';

        if (!empty($fnct) && $fnct != 'null') {
            $table = explode("##", (string) $fnct);
            $tableCondition = "AND $table[0] != ?";
        }

        $sQuery = "SELECT COUNT(*) AS count
                        FROM $tableName
                        WHERE $fieldName $inCondition $tableCondition";
        $parameters = [$value];

        if (!empty($tableCondition)) {
            $parameters[] = $table[1];
        }

        $result = $db->rawQueryOne($sQuery, $parameters);
        $data = $result['count'] ?? 0;
    } catch (Exception $e) {
        LoggerUtility::log('error', $e->getMessage());
        LoggerUtility::log('error', $e->getTraceAsString());
    }
}

echo ($data > 0) ? '1' : '0';
