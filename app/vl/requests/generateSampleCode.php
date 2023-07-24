<?php

use App\Services\VlService;
use App\Registries\ContainerRegistry;

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$provinceCode = $_POST['provinceCode'] ?? $_POST['pName'] ?? null;
$sampleCollectionDate = $_POST['sampleCollectionDate'] ?? $_POST['sDate'] ?? null;

if (empty($sampleCollectionDate)) {
  echo json_encode([]);
} else {
  $sampleCodeParams = [];
  $sampleCodeParams['sampleCollectionDate'] = $sampleCollectionDate;
  $sampleCodeParams['provinceCode'] = $provinceCode;

  echo $vlService->getSampleCode($sampleCodeParams);
}
