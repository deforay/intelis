<?php

use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

// Sanitize values before using them below
$_POST = array_map('htmlspecialchars', $_POST);

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

echo $genericTestsService->generateGenericSampleID($province, $sampleCollectionDate, $sampleFrom, '', null, null, $testType);
