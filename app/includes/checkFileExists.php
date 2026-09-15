<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

if (!empty($_POST['fileName'])) {

    try {
        // Only the instrument configuration files are ever asked about, so the
        // answer is limited to that folder; any other path reads as absent.
        $instrumentsFolder = realpath(APPLICATION_PATH . '/instruments');
        $fileName = (string) $_POST['fileName'];
        $folder = realpath(dirname($fileName));
        $insideInstruments = $instrumentsFolder !== false && $folder !== false
            && ($folder === $instrumentsFolder || str_starts_with($folder, $instrumentsFolder . DIRECTORY_SEPARATOR));
        if ($insideInstruments && file_exists($folder . DIRECTORY_SEPARATOR . basename($fileName))) {
            echo 'exists';
        } else {
            echo 'not exists';
        }
    } catch (Throwable $e) {
        LoggerUtility::logError($e->getMessage());
        LoggerUtility::logError($e->getTraceAsString());
    }
}
