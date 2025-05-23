<?php

namespace App\Utilities;

use App\Utilities\LoggerUtility;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class FileCacheUtility
{
    private $prefix = 'app_cache_';
    private FilesystemAdapter $filesystemAdapter;
    private TagAwareAdapter $tagAwareAdapter;

    public function __construct()
    {
        $this->filesystemAdapter = new FilesystemAdapter('', 0, CACHE_PATH . DIRECTORY_SEPARATOR . 'file_cache');
        $this->tagAwareAdapter = new TagAwareAdapter($this->filesystemAdapter);
    }

    public function setPrefix(string $prefix)
    {
        $this->prefix = $prefix;
    }
    private function applyPrefix(string $key): string
    {
        return $this->prefix . $key;
    }

    public function get(string $key, callable $computeValueCallback, ?array $tags = [], int $expiration = 3600)
    {
        $prefixedKey = $this->applyPrefix($key);
        return $this->tagAwareAdapter->get($prefixedKey, function (ItemInterface $item) use ($computeValueCallback, $tags, $expiration) {
            $value = call_user_func($computeValueCallback, $item);

            $item->set($value);
            $item->expiresAfter($expiration);
            if (!empty($tags)) {
                $item->tag($tags);
            }
            return $value;
        });
    }

    public function set(string $key, $value, ?array $tags = [], int $expiration = 3600): bool
    {
        $prefixedKey = $this->applyPrefix($key);
        $callback = function (ItemInterface $item) use ($value, $tags, $expiration) {
            $item->set($value);
            $item->expiresAfter($expiration);
            if (!empty($tags)) {
                $item->tag($tags);
            }
            return $value;
        };

        return $this->filesystemAdapter->get($prefixedKey, $callback);
    }

    public function delete(string $key): bool
    {
        $prefixedKey = $this->applyPrefix($key);
        return $this->filesystemAdapter->delete($prefixedKey);
    }

    public function clear(): bool
    {
        return $this->filesystemAdapter->clear();
    }

    public function invalidateTags(array $tags): bool
    {
        return $this->tagAwareAdapter->invalidateTags($tags);
    }

    /**
     * Prune expired items (if supported by adapter)
     * @return bool
     */
    public function prune(): bool
    {
        try {
            if (method_exists($this->filesystemAdapter, 'prune')) {
                return $this->filesystemAdapter->prune();
            }

            return true;
        } catch (\Exception $e) {
            LoggerUtility::logError('Cache prune failed', ['exception' => $e]);
            return false;
        }
    }
}
