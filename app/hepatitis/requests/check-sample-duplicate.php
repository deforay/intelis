<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Exceptions\SystemException;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;
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
    if ($fnct == '' || $fnct == 'null') {
        $sQuery = "SELECT * from $tableName where $fieldName= ?";
        $parameters = [$value];
        $result = $db->rawQuery($sQuery, $parameters);
        if ($result) {
            $data = base64_encode((string) $result[0]['hepatitis_id']) . "##" . $result[0][$fieldName];
        } elseif ($general->isLISInstance()) {
            $sQuery = "SELECT * FROM $tableName WHERE remote_sample_code= ?";
            $parameters = [$value];
            $result = $db->rawQuery($sQuery, $parameters);
            $data = $result ? base64_encode((string) $result[0]['hepatitis_id']) . "##" . $result[0]['remote_sample_code'] : 0;
        } else {
            $data = 0;
        }
    } else {
        $table = explode("##", (string) $fnct);
        try {
            $sQuery = "SELECT * FROM $tableName WHERE $fieldName= ? and $table[0]!= ?";
            $parameters = [$value, $table[1]];
            $result = $db->rawQuery($sQuery, $parameters);
            if ($result) {
                $data = base64_encode((string) $result[0]['hepatitis_id']) . "##" . $result[0][$fieldName];
            } elseif ($general->isLISInstance()) {
                $sQuery = "SELECT * FROM $tableName where remote_sample_code= ? and $table[0]!= ?";
                $parameters = [$value, $table[1]];
                $result = $db->rawQuery($sQuery, $parameters);
                $data = $result ? base64_encode((string) $result[0]['hepatitis_id']) . "##" . $result[0]['remote_sample_code'] : 0;
            } else {
                $data = 0;
            }
        } catch (Exception $e) {
            throw new SystemException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
echo $data;
