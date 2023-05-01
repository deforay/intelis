<?php

use App\Registries\ContainerRegistry;
use App\Services\HepatitisService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/** @var HepatitisService $hepatitisService */
$hepatitisService = ContainerRegistry::get(HepatitisService::class);
echo $hepatitisService->insertSampleCode($_POST);
