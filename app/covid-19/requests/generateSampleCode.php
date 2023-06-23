<?php

use App\Registries\ContainerRegistry;
use App\Services\Covid19Service;


if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);

$provinceCode = $_POST['provinceCode'] ?? $_POST['pName'] ?? null;
$sampleCollectionDate = $_POST['sampleCollectionDate'] ?? $_POST['sDate'] ?? null;

$sampleCodeParams = [];
$sampleCodeParams['sampleCollectionDate'] = $sampleCollectionDate;
$sampleCodeParams['provinceCode'] = $provinceCode;

echo $covid19Service->generateSampleCode($sampleCodeParams);
