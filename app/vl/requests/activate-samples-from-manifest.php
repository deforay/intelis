<?php

use App\Services\TestsService;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\GeoLocationsService;
use App\Services\TestRequestsService;
use App\Abstracts\AbstractTestService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var GeoLocationsService $geoService */
$geoService = ContainerRegistry::get(GeoLocationsService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$testType = $_POST['testType'];
$manifestCode = $_POST['manifestCode'];

$serviceClass = TestsService::getTestServiceClass($testType);

/** @var AbstractTestService $testTypeService */
$testTypeService = ContainerRegistry::get($serviceClass);

$globalConfig = $general->getGlobalConfig();
$sampleCodeFormat = $globalConfig['sample_code'] ?? 'MMYY';
$prefix = $globalConfig['sample_code_prefix'] ?? $testTypeService->shortCode;

/** @var TestRequestsService $testRequestsService */
$testRequestsService = ContainerRegistry::get(TestRequestsService::class);

echo $testRequestsService->activateSamplesFromManifest($testType, $manifestCode, $sampleCodeFormat, $prefix);
