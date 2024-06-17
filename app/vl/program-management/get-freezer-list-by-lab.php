<?php

use App\Registries\AppRegistry;
use App\Services\StorageService;
use App\Registries\ContainerRegistry;

/** @var StorageService $general */
$storageService = ContainerRegistry::get(StorageService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

if (isset($_POST['labId'])) {
    $labId = $_POST['labId'];

    $freezerList = $storageService->getFreezerListByLabId($labId);
    $option = "";
    foreach ($freezerList as $list) {
        $option .= "<option value='" . $list['storage_id'] . "'>" . $list['storage_code'] . "</option>";
    }
    echo $option;
}
