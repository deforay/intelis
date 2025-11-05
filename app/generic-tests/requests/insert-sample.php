<?php

use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

try {
    // Start transaction
    $db->beginTransaction();
    $_POST['insertOperation'] = true;
    echo $genericTestsService->insertSample($_POST);
    // Commit transaction
    $db->commitTransaction();
} catch (Throwable $exception) {
    // Rollback transaction in case of error
    $db->rollbackTransaction();
    LoggerUtility::logError($exception->getMessage(), [
        'exception' => $exception,
        'file' => $exception->getFile(), // File where the error occurred
        'line' => $exception->getLine(), // Line number of the error
        'stacktrace' => $exception->getTraceAsString()
    ]);
    echo "0";
}
