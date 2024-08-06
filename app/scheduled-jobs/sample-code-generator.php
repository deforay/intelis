<?php

if (php_sapi_name() === 'cli') {
    require_once(__DIR__ . "/../../bootstrap.php");
}

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var TestRequestsService $testRequestsService */
$testRequestsService = ContainerRegistry::get(TestRequestsService::class);

$maxTries = 5; // Maximum number of tries to generate sample code
$interval = 5; // Interval in seconds to wait between checks

$testRequestsService->processSampleCodeQueue(maxTries: $maxTries, interval: $interval);
