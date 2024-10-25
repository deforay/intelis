<?php

use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$sampleData = [];
$sampleCode = $_POST['samplePackageCode'];

$sampleQuery = "SELECT eid.eid_id
                    FROM form_eid as eid
                    WHERE eid.sample_package_code IN
                    (
                        '$sampleCode',
                        (SELECT DISTINCT sample_package_code FROM form_eid WHERE remote_sample_code LIKE '$sampleCode')
                    )";

$sampleResult = $db->rawQuery($sampleQuery);
$sampleData = array_column($sampleResult, 'eid_id');
echo implode(',', $sampleData);
