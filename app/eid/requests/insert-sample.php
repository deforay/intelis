<?php

use App\Services\EidService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);

try {
    // Start transaction
    $db->beginTransaction();
    $_POST['insertOperation'] = true;
    echo $eidService->insertSample($_POST);
    // Commit transaction
    $db->commitTransaction();
} catch (Exception | SystemException $exception) {
    // Rollback transaction in case of error
    $db->rollbackTransaction();
    LoggerUtility::log('error', $exception->getMessage(), [
        'exception' => $exception,
        'file' => $exception->getFile(), // File where the error occurred
        'line' => $exception->getLine(), // Line number of the error
        'stacktrace' => $exception->getTraceAsString()
    ]);
    echo "0";
}
