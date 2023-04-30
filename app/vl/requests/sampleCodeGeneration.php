<?php

use App\Registries\ContainerRegistry;
use App\Services\VlService;






/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

$sampleCollectionDate = $province = '';

if (isset($_POST['provinceCode'])) {
  $province = $_POST['provinceCode'];
} else if (isset($_POST['pName'])) {
  $province = $_POST['pName'];
}

if (isset($_POST['sampleCollectionDate'])) {
  $sampleCollectionDate = $_POST['sampleCollectionDate'];
} else if (isset($_POST['sDate'])) {
  $sampleCollectionDate = $_POST['sDate'];
}

$sampleFrom = $_POST['sampleFrom'] ?? '';

echo $vlService->generateVLSampleID($province, $sampleCollectionDate, $sampleFrom);
