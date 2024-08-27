<?php

use App\Services\VlService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
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
} catch (Throwable $e) {
    // Rollback transaction in case of error
    $db->rollbackTransaction();
    if (!empty($db->getLastError())) {
        LoggerUtility::log('error', $e->getFile() . ':' . $e->getLine() . ":" . $db->getLastErrno() . ":" . $db->getLastError());
        LoggerUtility::log('error', $e->getFile() . ':' . $e->getLine() . ":" . $db->getLastQuery());
    }
    LoggerUtility::log('error', $e->getFile() . ':' . $e->getLine()  . ':' .  $e->getMessage(), [
        'exception' => $e,
        'file' => $e->getFile(), // File where the error occurred
        'line' => $e->getLine(), // Line number of the error
        'stacktrace' => $e->getTraceAsString()
    ]);
    echo "0";
}
