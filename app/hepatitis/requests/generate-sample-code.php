<?php

use App\Services\HepatitisService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;


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

try {
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
} catch (Exception | SystemException $exception) {
  error_log("Error while generating Sample Code : " . $exception->getMessage());
}
