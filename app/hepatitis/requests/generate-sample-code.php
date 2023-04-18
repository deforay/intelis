<?php

use App\Models\Hepatitis;

ob_start();
if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

$hepatitisModel = new Hepatitis();

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

if (isset($_POST['sampleFrom'])) {
  $sampleFrom = $_POST['sampleFrom'];
} else {
  $sampleFrom = '';
}

if (isset($_POST['prefix'])) {
  $prefix = $_POST['prefix'];
} else {
  $prefix = '';
}


echo $hepatitisModel->generateHepatitisSampleCode($prefix, $province, $sampleCollectionDate, $sampleFrom);
