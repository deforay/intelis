<?php

use App\Services\HepatitisService;


if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

$hepatitisModel = new HepatitisService();

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

$prefix = $_POST['prefix'] ?? '';


echo $hepatitisModel->generateHepatitisSampleCode($prefix, $province, $sampleCollectionDate, $sampleFrom);
