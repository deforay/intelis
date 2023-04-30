<?php

use App\Registries\ContainerRegistry;
use App\Services\EidService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);
echo $eidService->insertSampleCode($_POST);
