<?php

use Laminas\Diactoros\ServerRequest;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Registries\ContainerRegistry;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$keyFromGlobalConfig = $general->getGlobalConfig('key');
$tbl = $general->decrypt($_POST['table'], base64_decode($keyFromGlobalConfig));
echo $general->updateCurrentDateTime($tbl);
