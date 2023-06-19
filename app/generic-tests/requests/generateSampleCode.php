<?php

use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$provinceCode = $_POST['pName'] ?? $_POST['provinceCode'] ?? null;
$sampleCollectionDate = $_POST['sampleCollectionDate'] ?? $_POST['sDate'] ?? null;
$testType = $_POST['testType'] ?? null;

$sampleCodeParams = [];
$sampleCodeParams['sampleCollectionDate'] = $sampleCollectionDate;
$sampleCodeParams['provinceCode'] = $provinceCode;
$sampleCodeParams['testType'] = $testType;

echo $genericTestsService->generateSampleCode($sampleCodeParams);
