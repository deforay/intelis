<?php

use App\Registries\ContainerRegistry;
use App\Services\VlService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);
echo $vlService->insertSampleCodeGenericTest($_POST);
