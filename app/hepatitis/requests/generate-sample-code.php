<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\HepatitisService;
use App\Registries\ContainerRegistry;


if (session_status() == PHP_SESSION_NONE) {
  session_start();
}


/** @var HepatitisService $hepatitisService */
$hepatitisService = ContainerRegistry::get(HepatitisService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


$provinceCode = $_POST['provinceCode'] ?? $_POST['pName'] ?? null;
$sampleCollectionDate = $_POST['sampleCollectionDate'] ?? $_POST['sDate'] ?? null;

$prefix = $_POST['prefix'] ?? null;

try {
  if (empty($sampleCollectionDate) || DateUtility::isDateValid($sampleCollectionDate) === false || empty($prefix)) {
    echo json_encode([]);
  } else {
    $sampleCodeParams = [];
    $sampleCodeParams['sampleCollectionDate'] = $sampleCollectionDate;
    $sampleCodeParams['prefix'] = $prefix;
    $sampleCodeParams['provinceCode'] = $provinceCode;
    $sampleCodeParams['insertOperation'] = false;
    echo $hepatitisService->getSampleCode($sampleCodeParams);
  }
} catch (Throwable $exception) {
  error_log("Error while generating Sample Code : " . $exception->getMessage());
}
