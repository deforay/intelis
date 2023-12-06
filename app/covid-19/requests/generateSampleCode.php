<?php

use App\Services\Covid19Service;
use App\Registries\ContainerRegistry;


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);

$provinceCode = $_POST['provinceCode'] ?? $_POST['pName'] ?? null;
$sampleCollectionDate = $_POST['sampleCollectionDate'] ?? $_POST['sDate'] ?? null;

if (empty($sampleCollectionDate)) {
  echo json_encode([]);
} else {
  $sampleCodeParams = [];
  $sampleCodeParams['sampleCollectionDate'] = $sampleCollectionDate;
  $sampleCodeParams['provinceCode'] = $provinceCode;
  $sampleCodeParams['insertOperation'] = false;
  echo $covid19Service->getSampleCode($sampleCodeParams);
}
