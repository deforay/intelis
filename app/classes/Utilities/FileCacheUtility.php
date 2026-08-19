<?php

namespace App\Utilities;

use Exception;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use App\Utilities\LoggerUtility;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class FileCacheUtility
{
    private string $prefix = 'app_cache_';
    private readonly FilesystemAdapter $filesystemAdapter;
    private readonly TagAwareAdapter $tagAwareAdapter;

    /**
     * Details of the last clear() that could not remove everything; null when
     * the last clear succeeded. See getLastClearDiagnostics().
     * @var array{path:string, runningAs:string, entries:int, owners:array<string,int>, samples:string[]}|null
     */
    private ?array $lastClearDiagnostics = null;

    public function __construct()
    {
        $this->filesystemAdapter = new FilesystemAdapter('', 0, CACHE_PATH . DIRECTORY_SEPARATOR . 'file_cache');
        $this->tagAwareAdapter = new TagAwareAdapter($this->filesystemAdapter);
    }

    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    private function applyPrefix(string $key): string
    {
        return $this->prefix . $key;
    }

    public function get(string $key, callable $computeValueCallback, ?array $tags = [], int $expiration = 3600): mixed
    {
        $prefixedKey = $this->applyPrefix($key);
        return $this->tagAwareAdapter->get($prefixedKey, function (ItemInterface $item) use ($computeValueCallback, $tags, $expiration) {
            $value = call_user_func($computeValueCallback, $item);

            $item->set($value);
            $item->expiresAfter($expiration);
            if ($tags !== null && $tags !== []) {
                $item->tag($tags);
            }
            return $value;
        });
    }

    public function set(string $key, $value, ?array $tags = [], int $expiration = 3600): bool
    {
        $prefixedKey = $this->applyPrefix($key);

        try {
            // Use PSR-6 getItem()/save() so this truly OVERWRITES the key. The
            // contracts get() only runs its callback on a cache MISS, so using it
            // here silently no-ops whenever the key already exists (a set() that
            // can't update is not a set()).
            $item = $this->tagAwareAdapter->getItem($prefixedKey);
            $item->set($value);
            $item->expiresAfter($expiration);
            if ($tags !== null && $tags !== []) {
                $item->tag($tags);
            }
            return $this->tagAwareAdapter->save($item);
        } catch (Exception $e) {
            LoggerUtility::logError('Cache set failed', ['key' => $key, 'exception' => $e]);
            return false;
        }
    }

    public function delete(string $key): bool
    {
        $prefixedKey = $this->applyPrefix($key);
        return $this->tagAwareAdapter->delete($prefixedKey);
    }

    public function clear(): bool
    {
        $this->lastClearDiagnostics = null;

        // Let the adapter clear first: it drops its own in-memory state
        // (deferred items, known tag versions) alongside the files, which a
        // filesystem sweep alone cannot do.
        try {
            $this->tagAwareAdapter->clear();
        } catch (Exception $e) {
            LoggerUtility::logError('Cache adapter clear failed', ['exception' => $e]);
        }

        // Then ALWAYS sweep the directory, rather than only when the adapter
        // reports failure. The adapter walks nothing but its own two-level hash
        // shards, so an orphaned tmp file (a write killed mid-rename), entries
        // from an older cache layout and the empty shard dirs it never rmdirs
        // all survive a "successful" clear. The sweep is what makes the cache
        // directory actually empty, so it -- not the adapter -- is the verdict.
        // It is also forgiving by design: a cache clear is non-critical, and
        // inside composer post-update a hard failure would strand the instance
        // in 503/maintenance with migrations already applied.
        return $this->forceFilesystemClear();
    }

    /**
     * Why the last clear() came back false, or null when it succeeded. Callers
     * (purge-cache) turn this into an actionable message instead of a generic
     * "something was left behind".
     *
     * @return array{path:string, runningAs:string, entries:int, owners:array<string,int>, samples:string[]}|null
     */
    public function getLastClearDiagnostics(): ?array
    {
        return $this->lastClearDiagnostics;
    }

    /**
     * Best-effort recursive removal of the on-disk cache. Returns true unless
     * an entry that existed when the sweep reached it genuinely refused to go
     * (typically foreign-owned files this process cannot unlink).
     *
     * Deliberately judged by what the sweep failed to remove, NOT by whether
     * the directory is empty afterwards: on a live instance a web request can
     * repopulate the cache between the sweep and the check, and a re-warmed
     * cache is not a failed purge.
     */
    private function forceFilesystemClear(): bool
    {
        $cacheDir = CACHE_PATH . DIRECTORY_SEPARATOR . 'file_cache';
        if (!is_dir($cacheDir)) {
            return true;
        }

        $stuck = [];

        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($cacheDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
                // CATCH_GET_CHILD is a FLAG (3rd arg), not part of the mode:
                // one unreadable shard directory then skips itself instead of
                // aborting the whole sweep. It is reported below.
                RecursiveIteratorIterator::CATCH_GET_CHILD
            );

            foreach ($iterator as $item) {
                $path = $item->getPathname();

                if ($item->isDir()) {
                    if (@rmdir($path) || !is_dir($path)) {
                        continue;
                    }
                    @chmod($path, 0775);
                    // A directory that still won't go is only interesting if we
                    // could not look inside it; otherwise it is either empty and
                    // racing a concurrent request, or its surviving files are
                    // reported on their own.
                    if (!@rmdir($path) && is_dir($path) && !is_readable($path)) {
                        $stuck[] = $path;
                    }
                    continue;
                }

                if (@unlink($path)) {
                    continue;
                }
                // Only now force the permissions open -- doing it up front costs
                // two syscalls per entry on every purge and needlessly rewrites
                // modes on files about to be deleted. Note unlink needs write on
                // the PARENT directory, not on the file; some Symfony shards
                // land mode 700.
                @chmod(dirname($path), 0775);
                @chmod($path, 0664);
                if (!@unlink($path) && file_exists($path)) {
                    $stuck[] = $path;
                }
            }
        } catch (Exception $e) {
            LoggerUtility::logError('Cache filesystem clear failed', ['exception' => $e]);
            $this->lastClearDiagnostics = [
                'path' => $cacheDir,
                'runningAs' => $this->ownerName(function_exists('posix_geteuid') ? posix_geteuid() : null),
                'entries' => 0,
                'owners' => [],
                'samples' => [],
                'error' => $e->getMessage(),
            ];
            return false;
        }

        if ($stuck === []) {
            return true;
        }

        $this->lastClearDiagnostics = $this->describeStuckEntries($cacheDir, $stuck);
        LoggerUtility::logWarning('Cache clear left entries behind', $this->lastClearDiagnostics);

        return false;
    }

    /**
     * Turn a list of unremovable cache paths into something a human can act on:
     * who this process is, who owns what survived, and a few example paths.
     *
     * @param string[] $paths
     * @return array{path:string, runningAs:string, entries:int, owners:array<string,int>, samples:string[]}
     */
    private function describeStuckEntries(string $cacheDir, array $paths): array
    {
        $owners = [];
        foreach ($paths as $path) {
            $owner = $this->ownerName(@fileowner($path));
            $owners[$owner] = ($owners[$owner] ?? 0) + 1;
        }
        arsort($owners);

        return [
            'path' => $cacheDir,
            'runningAs' => $this->ownerName(function_exists('posix_geteuid') ? posix_geteuid() : null),
            'entries' => count($paths),
            'owners' => $owners,
            'samples' => array_slice($paths, 0, 3),
        ];
    }

    /** Resolve a uid to a login name, degrading gracefully without ext-posix. */
    private function ownerName(int|false|null $uid): string
    {
        if ($uid === false || $uid === null) {
            return 'unknown';
        }
        if (function_exists('posix_getpwuid')) {
            $info = @posix_getpwuid($uid);
            if (!empty($info['name'])) {
                return $info['name'];
            }
        }
        return 'uid ' . $uid;
    }

    public function invalidateTags(array $tags): bool
    {
        return $this->tagAwareAdapter->invalidateTags($tags);
    }

    /**
     * Check if a cache item exists and is not expired
     */
    public function hasItem(string $key): bool
    {
        $prefixedKey = $this->applyPrefix($key);
        return $this->tagAwareAdapter->hasItem($prefixedKey);
    }

    /**
     * Get multiple cache items at once
     */
    public function getMultiple(array $keys): iterable
    {
        $prefixedKeys = array_map([$this, 'applyPrefix'], $keys);
        return $this->tagAwareAdapter->getItems($prefixedKeys);
    }

    /**
     * Prune expired items (if supported by adapter)
     * @return bool
     */
    public function prune(): bool
    {
        try {
            if (method_exists($this->tagAwareAdapter, 'prune')) {
                return $this->tagAwareAdapter->prune();
            }

            // Fallback to filesystem adapter prune
            if (method_exists($this->filesystemAdapter, 'prune')) {
                return $this->filesystemAdapter->prune();
            }

            return true;
        } catch (Exception $e) {
            LoggerUtility::logError('Cache prune failed', ['exception' => $e]);
            return false;
        }
    }

    /**
     * Get cache statistics if available
     */
    public function getStats(): array
    {
        $stats = [
            'adapter' => 'FilesystemAdapter',
            'supports_tags' => true,
            'cache_path' => CACHE_PATH . DIRECTORY_SEPARATOR . 'file_cache'
        ];

        try {
            // Add directory size if possible
            $cachePath = CACHE_PATH . DIRECTORY_SEPARATOR . 'file_cache';
            if (is_dir($cachePath)) {
                $stats['cache_size'] = $this->getDirectorySize($cachePath);
                $stats['file_count'] = $this->getFileCount($cachePath);
            }
        } catch (Exception $e) {
            $stats['stats_error'] = $e->getMessage();
        }

        return $stats;
    }

    private function getDirectorySize(string $directory): int
    {
        $size = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    private function getFileCount(string $directory): int
    {
        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $count++;
            }
        }

        return $count;
    }
}
