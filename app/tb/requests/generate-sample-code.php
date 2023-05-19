<?php

use App\Registries\ContainerRegistry;
use App\Services\TbService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);

$sampleCollectionDate = $province = '';

// Sanitize values before using them below
$_POST = array_map('htmlspecialchars', $_POST);

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
