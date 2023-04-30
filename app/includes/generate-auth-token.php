<?php

use App\Registries\ContainerRegistry;
use App\Services\CommonService;


if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
/** @var MysqliDb $db */
/** @var CommonService $general */
$general = \App\Registries\ContainerRegistry::get(CommonService::class);

echo $general->generateToken();