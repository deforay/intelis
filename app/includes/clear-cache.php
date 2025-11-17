#!/usr/bin/env php
<?php

// Check if script is run from command line
$isCli = php_sapi_name() === 'cli';
use App\Services\CommonService;
// Require bootstrap file if run from command line
if ($isCli) {
    require_once __DIR__ . '/../../bootstrap.php';
}


use App\Utilities\MiscUtility;
use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;

/** @var FileCacheUtility $fileCache */
$fileCache = ContainerRegistry::get(FileCacheUtility::class);


// If not run from command line and 'instance' is set in session, unset it
if (!$isCli && CommonService::isSessionActive() && isset($_SESSION['instance'])) {
    unset($_SESSION['instance']);
}

// If run from command line, clear the DI container cache\
if ($isCli) {
    $compiledContainerPath = CACHE_PATH . DIRECTORY_SEPARATOR . 'CompiledContainer.php';
    MiscUtility::deleteFile($compiledContainerPath);
}

// Clear the file cache and echo the result
echo (ContainerRegistry::get(FileCacheUtility::class))->clear();
