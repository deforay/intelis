<?php

use App\Services\TbService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);

try {
    // Start transaction
    $db->startTransaction();
    $_POST['insertOperation'] = true;
    echo $tbService->insertSample($_POST);
    // Commit transaction
    $db->commit();
} catch (Exception | SystemException $exception) {
    // Rollback transaction in case of error
    $db->rollback();
    LoggerUtility::log('error', $exception->getMessage(), [
        'exception' => $exception,
        'file' => $exception->getFile(), // File where the error occurred
        'line' => $exception->getLine(), // Line number of the error
        'stacktrace' => $exception->getTraceAsString()
    ]);
    echo "0";
}
