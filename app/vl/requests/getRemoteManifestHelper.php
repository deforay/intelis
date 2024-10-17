<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;

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
$sampleQuery = "SELECT vl.vl_sample_id
                    FROM form_vl as vl
                    WHERE vl.sample_package_code IN (
                        '$sampleCode',
                        (SELECT DISTINCT sample_package_code FROM form_vl WHERE remote_sample_code LIKE '$sampleCode')
                    )";
                 
$sampleResult = $db->rawQuery($sampleQuery);
$sampleData = array_column($sampleResult, 'vl_sample_id');
echo implode(',', $sampleData);
