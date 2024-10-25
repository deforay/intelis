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
$sampleQuery = "SELECT covid.covid19_id FROM form_covid19 as covid WHERE covid.sample_package_code IN
                    (
                        '$sampleCode',
                        (SELECT DISTINCT sample_package_code FROM form_covid19 WHERE remote_sample_code LIKE '$sampleCode')
                    )";

$sampleResult = $db->rawQuery($sampleQuery);

$sampleData = array_column($sampleResult, 'covid19_id');
echo implode(',', $sampleData);
