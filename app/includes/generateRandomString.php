<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Utilities\MiscUtility;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

echo MiscUtility::generateRandomString(16);
