<?php

use App\Registries\ContainerRegistry;
use App\Services\Covid19Service;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);
echo $covid19Service->insertSampleCode($_POST);
