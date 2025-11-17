<?php

use Laminas\Diactoros\ServerRequest;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\Covid19Service;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);

$provinceCode = $_POST['provinceCode'] ?? $_POST['pName'] ?? null;
$sampleCollectionDate = $_POST['sampleCollectionDate'] ?? $_POST['sDate'] ?? null;

try {
  if (($sampleCollectionDate || DateUtility::isDateValid($sampleCollectionDate) === false) === false) {
    echo json_encode([]);
  } else {
    $sampleCodeParams = [];
    $sampleCodeParams['sampleCollectionDate'] = $sampleCollectionDate;
    $sampleCodeParams['provinceCode'] = $provinceCode;
    $sampleCodeParams['insertOperation'] = false;
    echo $covid19Service->getSampleCode($sampleCodeParams);
  }
} catch (Throwable $exception) {
  error_log("Error while generating Sample ID : " . $exception->getMessage());
}
