<?php

use App\Services\EidService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var EidService $eidService */
$eidService = ContainerRegistry::get(EidService::class);

$response = "0";
try {
    // Start transaction
    $db->beginTransaction();
    $_POST['insertOperation'] = true;
    $inserted = $eidService->insertSample($_POST, true);
    $response = $inserted['id'];
    // Commit transaction
    $db->commitTransaction();
    // Assign the code now, not at the next cron run (see assignCodeNow)
    ContainerRegistry::get(TestRequestsService::class)->assignCodeNow($inserted);
} catch (Throwable $exception) {
    // Rollback transaction in case of error
    $db->rollbackTransaction();
    LoggerUtility::logError($exception->getMessage(), [
        'exception' => $exception,
        'file' => $exception->getFile(), // File where the error occurred
        'line' => $exception->getLine(), // Line number of the error
        'stacktrace' => $exception->getTraceAsString()
    ]);
}

echo $response;
