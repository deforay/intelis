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

// Clear the file cache. A cache clear is non-critical: when this runs inside
// composer post-update/post-install it must NEVER hard-fail the chain, or a
// single unremovable cache entry strands the whole instance in 503/maintenance
// with migrations already applied. FileCacheUtility::clear() already falls back
// to a forceful filesystem sweep; if something still survives we warn but exit
// 0 so the upgrade completes. The stale cache self-heals on the next request or
// a manual `composer purge-cache`.
$ok = $fileCache->clear();
if ($isCli) {
    if ($ok) {
        MiscUtility::consoleSuccess('Application cache cleared.');
    } else {
        MiscUtility::consoleWarn('Could not fully clear the application cache (continuing). Some entries may be left behind; clear manually if stale data appears.');
    }
} elseif (!$ok) {
    http_response_code(500);
}
