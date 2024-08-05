<?php

if (php_sapi_name() === 'cli') {
    require_once(__DIR__ . "/../../bootstrap.php");
}

use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$maxTries = 5; // Maximum number of tries to generate sample code
$interval = 5; // Interval in seconds to wait between checks

$general->processSampleCodeQueue(maxTries: $maxTries, interval: $interval);
