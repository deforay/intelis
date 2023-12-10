<?php

use App\Registries\AppRegistry;
use App\Services\EidService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;


/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = $request->getParsedBody();


$provinceCode = $_POST['provinceCode'] ?? $_POST['pName'] ?? null;
$sampleCollectionDate = $_POST['sampleCollectionDate'] ?? $_POST['sDate'] ?? null;
try {
  if (empty($sampleCollectionDate)) {
    echo json_encode([]);
  } else {

    $sampleFrom = $_POST['sampleFrom'] ?? '';

    $sampleCodeParams = [];
    $sampleCodeParams['sampleCollectionDate'] = $sampleCollectionDate;
    $sampleCodeParams['provinceCode'] = $provinceCode;
    $sampleCodeParams['insertOperation'] = false;
    echo $eidService->getSampleCode($sampleCodeParams);
  }
} catch (Exception | SystemException $exception) {
  error_log("Error while generating Sample Code : " . $exception->getMessage());
}
