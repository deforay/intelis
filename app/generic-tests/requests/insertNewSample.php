<?php

use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);
echo $genericTestsService->insertSampleCodeGenericTest($_POST);
