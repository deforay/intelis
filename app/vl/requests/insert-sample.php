<?php

use App\Services\VlService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

try {
    // Start transaction
    $db->beginTransaction();
    $_POST['insertOperation'] = true;
    echo $vlService->insertSample($_POST);
    // Commit transaction
    $db->commitTransaction();
} catch (Exception | SystemException $exception) {
    // Rollback transaction in case of error
    $db->rollbackTransaction();
    if ($db->getLastErrno() > 0) {
        error_log($db->getLastErrno());
        error_log($db->getLastError());
        error_log($db->getLastQuery());
    }
    LoggerUtility::log('error', $exception->getFile() . ':' . $exception->getLine()  . ':' .  $exception->getMessage(), [
        'exception' => $exception,
        'file' => $exception->getFile(), // File where the error occurred
        'line' => $exception->getLine(), // Line number of the error
        'stacktrace' => $exception->getTraceAsString()
    ]);
    echo "0";
}
