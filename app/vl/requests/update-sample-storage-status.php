<?php

use App\Registries\ContainerRegistry;
use App\Services\StorageService;


/** @var StorageService $storageService */
$storageService = ContainerRegistry::get(StorageService::class);

if (isset($_POST['storageId'])) {
    $storageId = $_POST['storageId'];
    $sampleUniqueId = $_POST['uniqueId'];
    $status = $_POST['status'];
    $result = $storageService->updateSampleStorageStatus($storageId, $sampleUniqueId, $status);
    echo $result;
}