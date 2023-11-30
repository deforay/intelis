<?php

use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;


/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

try {
    // Start transaction
    $db->startTransaction();
    $_POST['insertOperation'] = true;
    echo $genericTestsService->insertSample($_POST);
    // Commit transaction
    $db->commit();
} catch (Exception $e) {
    // Rollback transaction in case of error
    $db->rollback();
    throw new SystemException($e->getMessage());
}
