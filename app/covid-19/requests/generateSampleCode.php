<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\Covid19Service;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
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
    // Reflect the acting user so the preview shows the same series the queue will
    // mint (testing-lab -> lis: no R + lab postfix; collection-site -> sts: R).
    // Cosmetic only -- the authoritative code is minted server-side by the queue.
    $sampleCodeParams['accessType'] = $_SESSION['accessType'] ?? null;
    $sampleCodeParams['labId'] = $_SESSION['labId'] ?? null;
    echo $covid19Service->getSampleCode($sampleCodeParams);
  }
} catch (Throwable $exception) {
  error_log("Error while generating Sample ID : " . $exception->getMessage());
}
