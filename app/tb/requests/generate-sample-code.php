<?php

use App\Services\TbService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$tbService = new TbService();

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


echo $tbService->generateTbSampleCode($province, $sampleCollectionDate, $sampleFrom);
