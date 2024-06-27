<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Utilities\LoggerUtility;
use App\Services\ApiService;

/** @var ApiService $apiService */
$apiService = ContainerRegistry::get(ApiService::class);


// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

if (!empty($_POST['remoteUrl'])) {

    try {
        $url = $_POST['remoteUrl'];
        $activeUrlResult = $apiService->checkConnectivity($url);
        echo $activeUrlResult;
    } catch (Exception $e) {
        LoggerUtility::log('error', $e->getMessage());
        LoggerUtility::log('error', $e->getTraceAsString());
    }
}

