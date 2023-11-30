<?php

use App\Services\Covid19Service;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

try {
    // Start transaction
    $db->startTransaction();
    $_POST['insertOperation'] = true;
    echo $covid19Service->insertSample($_POST);
    // Commit transaction
    $db->commit();
} catch (Exception $e) {
    // Rollback transaction in case of error
    $db->rollback();
    throw new SystemException($e->getMessage());
}
