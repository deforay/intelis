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
if (!empty($value) && !empty($fieldName) && !empty($tableName)) {
    try {
        $tableCondition = '';
        $remoteSampleCodeCondition = '';

        if (!empty($fnct) && $fnct != 'null') {
            $table = explode("##", (string) $fnct);
            $tableCondition = "AND " . $table[0] . "!= ?";
        }

        if ($general->isLISInstance()) {
            $remoteSampleCodeCondition = "OR remote_sample_code= ?";
        }

        $sQuery = "SELECT * FROM $tableName WHERE ($fieldName= ? $tableCondition) $remoteSampleCodeCondition";
        $parameters = [$value];

        if (!empty($tableCondition)) {
            $parameters[] = $table[1];
        }

        if (!empty($remoteSampleCodeCondition)) {
            $parameters[] = $value;
        }

        $result = $db->rawQueryOne($sQuery, $parameters);

        if ($result) {
            $data = base64_encode((string) $result['eid_id']) . "##" . $result[$fieldName];
        } else {
            $data = 0;
        }
    } catch (Exception $e) {
        LoggerUtility::logError($e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

echo ($data > 0) ? '1' : '0';
