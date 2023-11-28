<?php

use App\Exceptions\SystemException;
use App\Services\VlService;
use App\Registries\ContainerRegistry;

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
