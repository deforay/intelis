<?php

use App\Registries\ContainerRegistry;
use App\Services\Covid19Service;


if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$c19Model = ContainerRegistry::get(Covid19Service::class);

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


echo $c19Model->generateCovid19SampleCode($province, $sampleCollectionDate, $sampleFrom);
