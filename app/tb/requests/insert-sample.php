<?php

use App\Services\TbService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var TbService $tbService */
$tbService = ContainerRegistry::get(TbService::class);
$response = '0';
try {
    // Start transaction
    $db->beginTransaction();
    $_POST['insertOperation'] = true;
    $inserted = $tbService->insertSample($_POST, true);
    $response = $inserted['id'];
    // Commit transaction
    $db->commitTransaction();
    // Assign the code now, not at the next cron run (see assignCodeNow)
    ContainerRegistry::get(TestRequestsService::class)->assignCodeNow($inserted);
} catch (Throwable $exception) {
    // Rollback transaction in case of error
    $db->rollbackTransaction();
    LoggerUtility::logError($exception->getFile() . ':' . $exception->getLine()  . ':' .  $exception->getMessage(), [
        'exception' => $exception,
        'file' => $exception->getFile(), // File where the error occurred
        'line' => $exception->getLine(), // Line number of the error
        'stacktrace' => $exception->getTraceAsString()
    ]);
}

echo $response;
