<?php

use App\Registries\AppRegistry;
use App\Services\CD4Service;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var CD4Service $cd4Service */
$cd4Service = ContainerRegistry::get(CD4Service::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$provinceCode = $_POST['provinceCode'] ?? $_POST['pName'] ?? null;
$sampleCollectionDate = $_POST['sampleCollectionDate'] ?? $_POST['sDate'] ?? null;
try {
  if (empty($sampleCollectionDate)) {
    echo json_encode([]);
  } else {
    $sampleCodeParams = [];
    $sampleCodeParams['sampleCollectionDate'] = $sampleCollectionDate;
    $sampleCodeParams['provinceCode'] = $provinceCode;
    $sampleCodeParams['insertOperation'] = false;
    echo $cd4Service->getSampleCode($sampleCodeParams);
  }
} catch (Exception | SystemException $exception) {
  error_log("Error while generating Sample Code : " . $exception->getMessage());
}
