<?php

use App\Services\Covid19Service;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;

/** @var Covid19Service $covid19Service */
$covid19Service = ContainerRegistry::get(Covid19Service::class);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
$response = "0";
try {
    // Start transaction
    $db->beginTransaction();
    $_POST['insertOperation'] = true;
    $inserted = $covid19Service->insertSample($_POST, true);
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
