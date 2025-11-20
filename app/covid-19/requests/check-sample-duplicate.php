<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;




/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$tableName = $_POST['tableName'];
$fieldName = $_POST['fieldName'];
$value = trim((string) $_POST['value']);
$fnct = $_POST['fnct'];
$data = 0;
if ($value !== '') {

    $tableInfo = [];
    if (!empty($fnct)) {
        $tableInfo = explode("##", (string) $fnct);
    }

    if ($general->isSTSInstance()) {
        $fieldName = 'remote_sample_code';
    }

    $parameters = [$value];

    $sQuery = "SELECT $fieldName FROM $tableName WHERE $fieldName= ?";

    if ($tableInfo !== []) {
        $sQuery .= " AND $tableInfo[0] != ?";
        $parameters[] = $tableInfo[1];
    }
    $result = $db->rawQuery($sQuery, $parameters);

    $data = $result ? base64_encode((string) $result[0]['covid19_id']) . "##" . $result[0][$fieldName] : 0;
}

echo $data;
