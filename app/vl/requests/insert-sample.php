<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Services\VlService;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Services\TestRequestsService;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var VlService $vlService */
$vlService = ContainerRegistry::get(VlService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());
$response = "0";
try {
    // Start transaction
    $db->beginTransaction();
    $_POST['insertOperation'] = true;
    $inserted = $vlService->insertSample($_POST, true);
    $response = $inserted['id'];
    // Commit transaction
    $db->commitTransaction();
    // Assign the code now, not at the next cron run (see assignCodeNow)
    ContainerRegistry::get(TestRequestsService::class)->assignCodeNow($inserted);
} catch (Throwable $e) {
    // Rollback transaction in case of error
    $db->rollbackTransaction();
    if (!empty($db->getLastError())) {
        LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastErrno() . ":" . $db->getLastError());
        LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastQuery());
    }
    LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ':' . $e->getMessage(), [
        'exception' => $e,
        'file' => $e->getFile(), // File where the error occurred
        'line' => $e->getLine(), // Line number of the error
        'stacktrace' => $e->getTraceAsString()
    ]);
}

echo $response;
