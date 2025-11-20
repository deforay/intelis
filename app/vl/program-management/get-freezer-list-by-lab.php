<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Services\StorageService;
use App\Registries\ContainerRegistry;

/** @var StorageService $general */
$storageService = ContainerRegistry::get(StorageService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

if (isset($_POST['labId'])) {
    $labId = $_POST['labId'];
    $freezerCode = ($_POST['freezerCode']) ?? '';

    $freezerList = $storageService->getFreezerListByLabId($labId);
    $option = "<option value=''>-- Sélectionner --</option>";
    foreach ($freezerList as $list) {
        $selected = "";
        if ($list['storage_id'] == $freezerCode) {
            $selected = "selected='selected'";
        }
        $option .= "<option value='" . $list['storage_id'] . "'>" . $list['storage_code'] . "</option>";
    }
    echo $option;
}
