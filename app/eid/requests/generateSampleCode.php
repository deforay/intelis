<?php

use App\Services\EidService;
use App\Registries\ContainerRegistry;


/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();


$provinceCode = $_POST['provinceCode'] ?? $_POST['pName'] ?? null;
$sampleCollectionDate = $_POST['sampleCollectionDate'] ?? $_POST['sDate'] ?? null;

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
