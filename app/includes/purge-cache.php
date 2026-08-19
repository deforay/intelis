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
// with migrations already applied. FileCacheUtility::clear() always follows the
// adapter with a forceful filesystem sweep; if an entry still refuses to go we
// warn with who owns it and who we are running as (see buildPurgeCacheWarning
// -- almost always this command run as the wrong user), but exit 0 so the
// upgrade completes. The stale cache self-heals on the next request or a manual
// `composer purge-cache`.
$ok = $fileCache->clear();
if ($isCli) {
    if ($ok) {
        MiscUtility::consoleSuccess('Application cache cleared.');
    } else {
        MiscUtility::consoleWarn(buildPurgeCacheWarning($fileCache->getLastClearDiagnostics()));
    }
} elseif (!$ok) {
    http_response_code(500);
}

/**
 * Say WHY the purge fell short, not just that it did. The overwhelmingly common
 * cause is running this as the wrong user: the cache is written by the web
 * server user on every request, and unlinking those files needs write access to
 * the directory that owns them, so `composer purge-cache` as a login user (or
 * as root, leaving root-owned entries behind for the next run) cannot remove
 * them. Naming the two users turns a mystery warning into a one-line fix.
 *
 * @param array{path?:string, runningAs?:string, entries?:int, owners?:array<string,int>, samples?:string[], error?:string}|null $info
 * @return string[]
 */
function buildPurgeCacheWarning(?array $info): array
{
    $lines = ['Could not fully clear the application cache (continuing).'];

    if (empty($info)) {
        $lines[] = 'Some entries may be left behind; clear manually if stale data appears.';
        return $lines;
    }

    if (!empty($info['error'])) {
        $lines[] = 'The cache directory could not be read: ' . $info['error'];
        $lines[] = 'Check permissions on ' . ($info['path'] ?? CACHE_PATH) . '.';
        return $lines;
    }

    $runningAs = $info['runningAs'] ?? 'unknown';
    $owners = $info['owners'] ?? [];
    $entries = (int) ($info['entries'] ?? 0);

    $ownerParts = [];
    foreach ($owners as $owner => $count) {
        $ownerParts[] = $count . ' owned by ' . $owner;
    }

    $lines[] = sprintf(
        '%d cache %s could not be removed while running as %s%s.',
        $entries,
        $entries === 1 ? 'entry' : 'entries',
        $runningAs,
        $ownerParts !== [] ? ' (' . implode(', ', $ownerParts) . ')' : ''
    );

    $foreignOwners = array_keys(array_diff_key($owners, [$runningAs => true]));
    if ($foreignOwners !== []) {
        // A non-root foreign owner IS the web server user (it wrote those
        // entries serving requests); fall back to the fleet default otherwise.
        $webUser = 'www-data';
        foreach ($foreignOwners as $owner) {
            if ($owner !== 'root' && !str_starts_with($owner, 'uid ')) {
                $webUser = $owner;
                break;
            }
        }

        $lines[] = 'This is an ownership mismatch, not a broken cache: the cache is written by the';
        $lines[] = 'web server user, and removing those files needs ownership of their directories.';
        $lines[] = 'Hand the cache back and purge as that user:';
        $lines[] = '  sudo chown -R ' . $webUser . ':' . $webUser . ' ' . dirname((string) ($info['path'] ?? CACHE_PATH));
        $lines[] = '  sudo -u ' . $webUser . ' composer purge-cache';
    } else {
        $lines[] = 'Remove them manually if stale data appears: ' . ($info['path'] ?? CACHE_PATH);
    }

    if (!empty($info['samples'])) {
        $lines[] = 'Example: ' . implode(', ', $info['samples']);
    }

    return $lines;
}
