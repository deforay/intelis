<?php

use App\Registries\ContainerRegistry;
use App\Services\VlService;

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$sampleCollectionDate = $province = '';

if (isset($_POST['provinceCode'])) {
  $province = $_POST['provinceCode'];
} elseif (isset($_POST['pName'])) {
  $province = $_POST['pName'];
}

if (isset($_POST['sampleCollectionDate'])) {
  $sampleCollectionDate = $_POST['sampleCollectionDate'];
} elseif (isset($_POST['sDate'])) {
  $sampleCollectionDate = $_POST['sDate'];
}

$sampleFrom = $_POST['sampleFrom'] ?? '';

echo $vlService->generateVLSampleID(htmlspecialchars($province), $sampleCollectionDate, htmlspecialchars($sampleFrom));
