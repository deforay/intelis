<?php

use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;






/** @var GenericTestsService $genService */
$genService = ContainerRegistry::get(GenericTestsService::class);

$sampleCollectionDate = $province = $testType = '';

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
if (isset($_POST['testType']) && !empty($_POST['testType'])) {
  $testType = $_POST['testType'];
}


$sampleFrom = $_POST['sampleFrom'] ?? '';

echo $genService->generateGenericSampleID(htmlspecialchars($province), $sampleCollectionDate, htmlspecialchars($sampleFrom), '', null, null, $testType);
