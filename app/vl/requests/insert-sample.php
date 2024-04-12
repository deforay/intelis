<?php

use App\Services\VlService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

$_POST = _sanitizeInput($_POST);

try {
    // Start transaction
    $db->beginTransaction();
    $_POST['insertOperation'] = true;
    echo $vlService->insertSample($_POST);
    // Commit transaction
    $db->commitTransaction();
} catch (Throwable $exception) {
    // Rollback transaction in case of error
    $db->rollbackTransaction();
    if (!empty($db->getLastError())) {
        LoggerUtility::log('error', __FILE__ . ":" . __LINE__ . ":" . $db->getLastErrno() . ":" . $db->getLastError());
        LoggerUtility::log('error', __FILE__ . ":" . __LINE__ . ":" . $db->getLastQuery());
    }
    LoggerUtility::log('error', $exception->getFile() . ':' . $exception->getLine()  . ':' .  $exception->getMessage(), [
        'exception' => $exception,
        'file' => $exception->getFile(), // File where the error occurred
        'line' => $exception->getLine(), // Line number of the error
        'stacktrace' => $exception->getTraceAsString()
    ]);
    echo "0";
}
