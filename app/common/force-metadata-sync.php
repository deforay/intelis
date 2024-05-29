<?php
use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$keyFromGlobalConfig = $general->getGlobalConfig('key');

$tbl = $general->decrypt($_POST['table'],base64_decode((string) $keyFromGlobalConfig));
echo $general->updateCurrentDateTime($tbl);