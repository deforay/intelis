<?php

use App\Registries\ContainerRegistry;
use App\Services\EidService;


if (session_status() == PHP_SESSION_NONE) {
  session_start();
}


/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);


// Sanitize values before using them below
$_POST = array_map('htmlspecialchars', $_POST);


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


echo $eidService->generateEIDSampleCode($province, $sampleCollectionDate, $sampleFrom);
