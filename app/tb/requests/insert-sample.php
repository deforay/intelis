<?php

use App\Registries\ContainerRegistry;
use App\Services\TbService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);
echo $tbService->insertSample($_POST);
