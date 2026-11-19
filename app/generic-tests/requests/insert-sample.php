<?php

use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\GenericTestsService;
use App\Services\TestRequestsService;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var GenericTestsService $genericTestsService */
$genericTestsService = ContainerRegistry::get(GenericTestsService::class);

try {
    // Start transaction
    $db->beginTransaction();
    $_POST['insertOperation'] = true;
    $inserted = $genericTestsService->insertSample($_POST, true);
    // Commit transaction
    $db->commitTransaction();
    // Assign the code now, not at the next cron run (see assignCodeNow)
    ContainerRegistry::get(TestRequestsService::class)->assignCodeNow($inserted);
    echo $inserted['id'];
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
