<?php

// Command handler: refresh-cache
//
// Clears the file cache and (optionally) the compiled DI container.
// Triggered remotely from STS when reference data is updated out-of-band
// and we want the lab to pick up the new values without waiting for TTL.
//
// Expected params:
//   - tags (optional)  array of tags to invalidate; if omitted, clear the
//                      entire file cache
//   - compiledContainer (optional, bool)  also remove the compiled DI
//                      container so next request reloads all bindings

use App\Utilities\FileCacheUtility;
use App\Registries\ContainerRegistry;

/** @var array $params */

$tags = isset($params['tags']) && is_array($params['tags']) ? $params['tags'] : null;
$clearCompiled = !empty($params['compiledContainer']);

$start = microtime(true);
$details = [];

try {
    /** @var FileCacheUtility $cache */
    $cache = ContainerRegistry::get(FileCacheUtility::class);

    if (!empty($tags)) {
        $cache->invalidateTags($tags);
        $details['invalidatedTags'] = $tags;
    } else {
        $cache->clear();
        $details['cleared'] = 'all';
    }

    if ($clearCompiled) {
        $compiled = CACHE_PATH . DIRECTORY_SEPARATOR . 'CompiledContainer.php';
        if (is_file($compiled)) {
            @unlink($compiled);
            $details['compiledContainer'] = 'removed';
        } else {
            $details['compiledContainer'] = 'not-present';
        }

    }

    return [
        'status' => 'completed',
        'durationSeconds' => round(microtime(true) - $start, 2),
        'details' => $details,
    ];
} catch (Throwable $e) {
    return [
        'status' => 'failed',
        'error' => $e->getMessage(),
        'durationSeconds' => round(microtime(true) - $start, 2),
        'details' => $details,
    ];
}
