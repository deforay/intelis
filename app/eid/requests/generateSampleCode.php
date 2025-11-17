<?php

use Laminas\Diactoros\ServerRequest;
use App\Services\EidService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;


/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);


// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


$provinceCode = $_POST['provinceCode'] ?? $_POST['pName'] ?? null;
$sampleCollectionDate = $_POST['sampleCollectionDate'] ?? $_POST['sDate'] ?? null;
try {
  if (empty($sampleCollectionDate) || DateUtility::isDateValid($sampleCollectionDate) === false) {
    echo json_encode([]);
  } else {
    $sampleCodeParams = [];
    $sampleCodeParams['sampleCollectionDate'] = $sampleCollectionDate;
    $sampleCodeParams['provinceCode'] = $provinceCode;
    $sampleCodeParams['insertOperation'] = false;
    echo $eidService->getSampleCode($sampleCodeParams);
  }
} catch (Throwable $e) {
  LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
  LoggerUtility::logError($e->getMessage(), [
    'file' => $e->getFile(),
    'line' => $e->getLine(),
    'trace' => $e->getTraceAsString(),
  ]);
}
