<?php

use App\Registries\ContainerRegistry;
use App\Services\TbService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);

$sampleCollectionDate = $province = '';

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

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
