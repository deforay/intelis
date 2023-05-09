<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$size = $_POST['size'] ?? 6;

echo base64_encode($general->generateUUID() . "-" . $general->generateToken($size));
