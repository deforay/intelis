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

// If run from command line, clear the DI container cache
if ($isCli) {
    $compiledContainerPath = CACHE_PATH . DIRECTORY_SEPARATOR . 'CompiledContainer.php';
    MiscUtility::deleteFile($compiledContainerPath);

    // Signal web workers to reset OPcache. Production serves PHP with
    // opcache.validate_timestamps=0, so mod_php/FPM workers never notice the
    // files an upgrade just changed. This CLI process lives in a *separate*
    // OPcache segment and cannot reset theirs directly, so instead we bump a
    // generation token here; the first web request after this (see the OPcache
    // self-heal guard in bootstrap.php) detects the change and calls
    // opcache_reset() exactly once. Written after the DI/file cache is cleared
    // so a stale worker never picks up a half-cleared cache.
    $opcacheGenFile = CACHE_PATH . DIRECTORY_SEPARATOR . 'opcache.gen';
    @file_put_contents($opcacheGenFile, uniqid('', true), LOCK_EX);
} elseif (function_exists('opcache_reset')) {
    // Reached via an HTTP hit to purge-cache: we ARE the web SAPI here, so the
    // OPcache we want gone is our own — reset it directly.
    @opcache_reset();
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
