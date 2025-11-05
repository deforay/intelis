<?php

use App\Services\ApiService;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Registries\ContainerRegistry;

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

if (!empty($_POST['remoteURL'])) {

    try {
        $url = $_POST['remoteURL'];
        echo $apiService->checkConnectivity($url);
    } catch (Throwable $e) {
        LoggerUtility::logError($e->getMessage());
        LoggerUtility::logError($e->getTraceAsString());
    }
}
