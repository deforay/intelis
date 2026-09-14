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
// The table, the field and the excluded-row column become identifiers in the
// query, so each has to be a plain identifier; anything else is not checked.
$isIdentifier = static fn($name): bool => is_string($name) && preg_match('/^[A-Za-z0-9_]+$/', $name) === 1;
$fnctColumn = (!empty($fnct) && $fnct != 'null') ? explode("##", (string) $fnct)[0] : null;
if (!$isIdentifier($tableName) || !$isIdentifier($fieldName) || ($fnctColumn !== null && !$isIdentifier($fnctColumn))) {
    $tableName = $fieldName = null;
}
if ($value !== '' && $value !== '0' && !empty($fieldName) && !empty($tableName)) {
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

        if ($tableCondition !== '' && $tableCondition !== '0') {
            $parameters[] = $table[1];
        }

        if ($remoteSampleCodeCondition !== '' && $remoteSampleCodeCondition !== '0') {
            $parameters[] = $value;
        }

        $result = $db->rawQueryOne($sQuery, $parameters);

        $data = $result ? base64_encode((string) $result['eid_id']) . "##" . $result[$fieldName] : 0;
    } catch (Exception $e) {
        LoggerUtility::logError($e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

echo ($data > 0) ? '1' : '0';
