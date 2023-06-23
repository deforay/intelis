<?php

use App\Registries\ContainerRegistry;
use App\Services\TbService;


/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$province = $_POST['provinceCode'] ?? $_POST['pName'] ?? null;
$sampleCollectionDate = $_POST['sampleCollectionDate'] ?? $_POST['sDate'] ?? null;

$sampleCodeParams = [];
$sampleCodeParams['sampleCollectionDate'] = $sampleCollectionDate;
$sampleCodeParams['provinceCode'] = $provinceCode;

echo $tbService->generateSampleCode($sampleCodeParams);
