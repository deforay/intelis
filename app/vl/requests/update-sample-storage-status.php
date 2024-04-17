<?php

use App\Registries\AppRegistry;
use App\Services\StorageService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var StorageService $storageService */
$storageService = ContainerRegistry::get(StorageService::class);

if (isset($_POST['storageId'])) {
    $storageId = $_POST['storageId'];
    $sampleUniqueId = $_POST['uniqueId'];
    $status = $_POST['status'];
    $result = $storageService->updateSampleStorageStatus($storageId, $sampleUniqueId, $status);
    echo $result;
}
