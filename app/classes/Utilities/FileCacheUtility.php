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
            // Fixed: Use tagAwareAdapter for set operations with tags
            $this->tagAwareAdapter->get($prefixedKey, function (ItemInterface $item) use ($value, $tags, $expiration) {
                $item->set($value);
                $item->expiresAfter($expiration);
                if ($tags !== null && $tags !== []) {
                    $item->tag($tags);
                }
                return $value;
            });
            return true;
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
        return $this->tagAwareAdapter->clear();
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
