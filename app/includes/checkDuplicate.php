<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$tableName = $_POST['tableName'];
$fieldName = $_POST['fieldName'];
$value = trim((string) $_POST['value']);
$fnct = $_POST['fnct'];
$data = 0;
$multiple = [];

if ($value !== '' && $value !== '0' && !empty($fieldName) && !empty($tableName)) {
    $isMultiple = !empty($_POST['type']) && $_POST['type'] == "multiple";
    if ($isMultiple) {
        $value = array_map('trim', explode(",", $value));
    }

    try {
        $inCondition = $isMultiple ? "IN (?)" : "= ?";
        $tableCondition = '';
        $parameters = [$value];

        if (!empty($fnct) && $fnct != 'null') {
            $table = explode("##", (string) $fnct);
            $tableCondition = "AND $table[0] != ?";
            $parameters[] = $table[1];
        }

        $sQuery = "SELECT 1
                    FROM $tableName
                    WHERE $fieldName $inCondition $tableCondition
                    LIMIT 1";

        $result = $db->rawQuery($sQuery, $parameters);
        $data = empty($result) ? 0 : 1;
    } catch (Throwable $e) {
        LoggerUtility::logError($e->getMessage());
        LoggerUtility::logError($e->getTraceAsString());
    }
}

echo ($data > 0) ? '1' : '0';
