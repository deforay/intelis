<?php

use App\Registries\ContainerRegistry;
use App\Services\VlService;

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

// Sanitize values before using them below
$_POST = array_map('htmlspecialchars', $_POST);

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
