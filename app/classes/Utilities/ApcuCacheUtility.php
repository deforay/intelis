<?php

namespace App\Utilities;

use App\Utilities\LoggerUtility;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;

class ApcuCacheUtility
{
    private string $prefix = 'app_cache_';
    private ApcuAdapter $apcuAdapter;

    public function __construct()
    {
        $this->apcuAdapter = new ApcuAdapter($this->prefix);
    }

    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
        // Recreate adapter with new namespace
        $this->apcuAdapter = new ApcuAdapter($this->prefix);
    }

    public function get(string $key, callable $computeValueCallback, int $expiration = 3600)
    {
        try {
            return $this->apcuAdapter->get($key, function (ItemInterface $item) use ($computeValueCallback, $expiration) {
                $item->expiresAfter($expiration);
                return call_user_func($computeValueCallback);
            });
        } catch (\Throwable $e) {
            LoggerUtility::logError('APCu cache get failed', ['key' => $key, 'exception' => $e]);
            return call_user_func($computeValueCallback); // fallback
        }
    }

    public function set(string $key, $value, int $expiration = 3600): bool
    {
        try {
            $cacheItem = $this->apcuAdapter->getItem($key);
            $cacheItem->set($value);
            $cacheItem->expiresAfter($expiration);
            return $this->apcuAdapter->save($cacheItem);
        } catch (\Throwable $e) {
            LoggerUtility::logError('APCu cache set failed', ['key' => $key, 'exception' => $e]);
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            return $this->apcuAdapter->deleteItem($key);
        } catch (\Throwable $e) {
            LoggerUtility::logError('APCu cache delete failed', ['key' => $key, 'exception' => $e]);
            return false;
        }
    }

    public function clear(): bool
    {
        try {
            return $this->apcuAdapter->clear();
        } catch (\Throwable $e) {
            LoggerUtility::logError('APCu cache clear failed', ['exception' => $e]);
            return false;
        }
    }

    public function hasItem(string $key): bool
    {
        try {
            return $this->apcuAdapter->hasItem($key);
        } catch (\Throwable $e) {
            LoggerUtility::logError('APCu cache hasItem check failed', ['key' => $key, 'exception' => $e]);
            return false;
        }
    }
}
