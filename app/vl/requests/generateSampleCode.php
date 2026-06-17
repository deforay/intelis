<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Services\VlService;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;
use App\Utilities\DateUtility;

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
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
    // Reflect the acting user so the preview shows the same series the queue will
    // mint (testing-lab -> lis: no R + lab postfix; collection-site -> sts: R).
    // Cosmetic only -- the authoritative code is minted server-side by the queue.
    $sampleCodeParams['accessType'] = $_SESSION['accessType'] ?? null;
    $sampleCodeParams['labId'] = $_SESSION['labId'] ?? null;
    echo $vlService->getSampleCode($sampleCodeParams);
  }
} catch (Throwable $exception) {
  LoggerUtility::logError("Error while generating Sample ID : " . $exception->getMessage());
}
