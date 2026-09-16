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

// The table, the field and the excluded-row column are written into the query as
// names (the values are bound). Over a hundred forms call this with their own
// table and column, so each name has to be a plain identifier rather than one of
// a list; anything else is not checked.
$isIdentifier = static fn($name): bool => is_string($name) && preg_match('/^[A-Za-z0-9_]+$/', $name) === 1;
if (!$isIdentifier($tableName) || !$isIdentifier($fieldName)) {
    $tableName = $fieldName = null;
}
if (!empty($fnct) && $fnct != 'null' && !$isIdentifier(explode("##", (string) $fnct)[0])) {
    $tableName = $fieldName = null;
}

if ($value !== '' && $value !== '0' && !empty($fieldName) && !empty($tableName)) {
    $isMultiple = !empty($_POST['type']) && $_POST['type'] == "multiple";
    if ($isMultiple) {
        $value = array_map('trim', explode(",", $value));
    }

    try {
        // One placeholder per value: a single `IN (?)` bound to a list matches only the first one.
        $inCondition = $isMultiple ? "IN (" . implode(',', array_fill(0, count($value), '?')) . ")" : "= ?";
        $tableCondition = '';
        $parameters = $isMultiple ? $value : [$value];

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
