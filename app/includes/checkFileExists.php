<?php

use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

if (!empty($_POST['fileName'])) {

    try {
        if (file_exists($_POST['fileName'])) {
            echo 'exists';
        } else {
            echo 'not exists';
        }
    } catch (Throwable $e) {
        LoggerUtility::log('error', $e->getMessage());
        LoggerUtility::log('error', $e->getTraceAsString());
    }
}
