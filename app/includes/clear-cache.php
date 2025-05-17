<?php

use App\Services\DatabaseService;
use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var FileCacheUtility $fileCache */
$fileCache = ContainerRegistry::get(FileCacheUtility::class);


// Check if script is run from command line
$isCli = php_sapi_name() === 'cli';

// Require bootstrap file if run from command line
if ($isCli) {
    require_once(__DIR__ . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . ".." . DIRECTORY_SEPARATOR . "bootstrap.php");
}

// If not run from command line and 'instance' is set in session, unset it
if (!$isCli && isset($_SESSION['instance'])) {
    unset($_SESSION['instance']);
}

// If run from command line, clear the DI container cache
if ($isCli) {
    $compiledContainerPath = CACHE_PATH . DIRECTORY_SEPARATOR . 'CompiledContainer.php';
    if (file_exists($compiledContainerPath)) {
        unlink($compiledContainerPath);
    }
}

$db->invalidateSqlCache($fileCache);
// Clear the file cache and echo the result
echo (ContainerRegistry::get(FileCacheUtility::class))->clear();
