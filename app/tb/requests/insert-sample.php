<?php

use App\Services\TbService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);

try {
    // Start transaction
    $db->startTransaction();
    $_POST['insertOperation'] = true;
    echo $tbService->insertSample($_POST);
    // Commit transaction
    $db->commit();
} catch (Exception $e) {
    // Rollback transaction in case of error
    $db->rollback();
    throw new SystemException($e->getMessage());
}
