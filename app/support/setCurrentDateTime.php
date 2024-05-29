<?php
use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$tbl = base64_decode($_POST['table']);
$general->updateCurrentDateTime((array)$tbl);