<?php

use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

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

        if ($_SESSION['instanceType'] == 'vluser') {
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
        error_log($e->getMessage());
        error_log($e->getTraceAsString());
    }
}

echo ($data > 0) ? '1' : '0';
