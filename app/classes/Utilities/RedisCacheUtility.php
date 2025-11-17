<?php

namespace App\Utilities;

use Throwable;
use App\Utilities\LoggerUtility;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Component\Cache\Adapter\RedisAdapter;

class RedisCacheUtility
{
    private string $prefix = 'app_cache_';
    private readonly RedisAdapter $redisAdapter;

    public function __construct()
    {
        $client = RedisAdapter::createConnection('redis://localhost');
        $this->redisAdapter = new RedisAdapter($client, $this->prefix);
    }

    public function get(string $key, callable $computeValueCallback, ?int $expiration = 3600): mixed
    {
        try {
            return $this->redisAdapter->get($key, function (ItemInterface $item) use ($computeValueCallback, $expiration) {
                if ($expiration !== null && $expiration > 0) {
                    $item->expiresAfter($expiration);
                }
                return call_user_func($computeValueCallback);
            });
        } catch (Throwable $e) {
            LoggerUtility::logError('Redis cache get failed', ['key' => $key, 'exception' => $e]);
            return call_user_func($computeValueCallback);
        }
    }

    public function set(string $key, $value, ?int $expiration = 3600): bool
    {
        try {
            $cacheItem = $this->redisAdapter->getItem($key);
            $cacheItem->set($value);
            if ($expiration !== null && $expiration > 0) {
                $cacheItem->expiresAfter($expiration);
            }
            return $this->redisAdapter->save($cacheItem);
        } catch (Throwable $e) {
            LoggerUtility::logError('Redis cache set failed', ['key' => $key, 'exception' => $e]);
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            return $this->redisAdapter->deleteItem($key);
        } catch (Throwable $e) {
            LoggerUtility::logError('Redis cache delete failed', ['key' => $key, 'exception' => $e]);
            return false;
        }
    }

    public function clear(): bool
    {
        try {
            return $this->redisAdapter->clear();
        } catch (Throwable $e) {
            LoggerUtility::logError('Redis cache clear failed', ['exception' => $e]);
            return false;
        }
    }

    public function hasItem(string $key): bool
    {
        try {
            return $this->redisAdapter->hasItem($key);
        } catch (Throwable $e) {
            LoggerUtility::logError('Redis cache hasItem check failed', ['key' => $key, 'exception' => $e]);
            return false;
        }
    }
}
