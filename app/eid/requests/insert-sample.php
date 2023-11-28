<?php

use App\Services\EidService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;


/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);

try {
    // Start transaction
    $db->startTransaction();
    $_POST['insertOperation'] = true;
    echo $eidService->insertSample($_POST);
    // Commit transaction
    $db->commit();
} catch (Exception $e) {
    // Rollback transaction in case of error
    $db->rollback();
    throw new SystemException($e->getMessage());
}
