<?php

use App\Registries\ContainerRegistry;
use App\Services\HepatitisService;


if (session_status() == PHP_SESSION_NONE) {
  session_start();
}


/** @var HepatitisService $hepatitisService */
$hepatitisService = ContainerRegistry::get(HepatitisService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();


$provinceCode = $_POST['provinceCode'] ?? $_POST['pName'] ?? null;
$sampleCollectionDate = $_POST['sampleCollectionDate'] ?? $_POST['sDate'] ?? null;

$prefix = $_POST['prefix'] ?? null;


if (empty($sampleCollectionDate) || empty($prefix)) {
  echo json_encode([]);
} else {
  $sampleCodeParams = [];
  $sampleCodeParams['sampleCollectionDate'] = $sampleCollectionDate;
  $sampleCodeParams['prefix'] = $prefix;
  $sampleCodeParams['provinceCode'] = $provinceCode;
  $sampleCodeParams['insertOperation'] = false;
  echo $hepatitisService->getSampleCode($sampleCodeParams);
}
