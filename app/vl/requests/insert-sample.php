<?php

use App\Services\VlService;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

try {
    // Start transaction
    $db->startTransaction();
    $_POST['insertOperation'] = true;
    echo $vlService->insertSample($_POST);
    // Commit transaction
    $db->commit();
} catch (Exception $e) {
    // Rollback transaction in case of error
    $db->rollback();
    throw new SystemException($e->getMessage());
}
