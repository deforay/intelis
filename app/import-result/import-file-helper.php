<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\LoggerUtility;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$machineImportScript = $_POST['fileName'];

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);
$arr = $general->getGlobalConfig();

$type = $_POST['type'];

$directoryMap = [
    'vl' => 'vl',
    'eid' => 'eid',
    'covid19' => 'covid-19',
    'hepatitis' => 'hepatitis',
    'tb' => 'tb',
    'cd4' => 'cd4',
];

// Check upload directory existence and permissions
$uploadDir = UPLOAD_PATH . DIRECTORY_SEPARATOR . "imported-results";
MiscUtility::makeDirectory($uploadDir);
if (!is_dir($uploadDir) || !is_writable($uploadDir)) {
    throw new SystemException(_translate("The upload directory is not available or not writable. Please contact your system administrator."));
}

if (!isset($directoryMap[$type])) {
    throw new SystemException(_translate('Invalid Test Type'));
}

$directoryName = $directoryMap[$type];
$machineImportScript = realpath(APPLICATION_PATH . "/instruments") . "/$directoryName/$machineImportScript";

if (!is_file($machineImportScript) || !is_readable($machineImportScript)) {
    throw new SystemException(_translate("Import Script not found"), 404);
}

try {
    require_once $machineImportScript;
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
        'instrument' => $machineImportScript,
    ]);
    $_SESSION['alertMsg'] = _translate("Import failed") . ": " . $e->getMessage();
    header("Location:/import-result/import-file.php?t=" . urlencode($type));
    exit;
}
