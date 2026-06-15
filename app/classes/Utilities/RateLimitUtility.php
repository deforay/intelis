<?php

namespace App\Utilities;

use Throwable;
use App\Registries\ContainerRegistry;

final class RateLimitUtility
{
    /**
     * Best-effort fixed-window rate limit backed by the file cache. Returns true if
     * the caller is OVER the limit (should be blocked), false if allowed.
     *
     * The window is anchored to the first hit (TTL = time remaining in the window),
     * so sustained traffic does not slide the window open. Under heavy concurrency
     * the counter may slightly undercount — acceptable for abuse throttling. The
     * limiter FAILS OPEN: any cache error returns false (allowed) so it can never
     * take an endpoint down.
     */
    public static function exceeded(string $bucket, int $maxHits, int $windowSeconds): bool
    {
        if ($maxHits <= 0 || $windowSeconds <= 0) {
            return false;
        }
        try {
            /** @var FileCacheUtility $cache */
            $cache = ContainerRegistry::get(FileCacheUtility::class);
            $key = 'ratelimit_' . hash('sha256', $bucket);
            $now = time();

            $rec = $cache->get($key, static fn(): array => ['start' => $now, 'count' => 0], [], $windowSeconds);
            if (!is_array($rec) || ($now - (int) ($rec['start'] ?? 0)) >= $windowSeconds) {
                $rec = ['start' => $now, 'count' => 0];
            }

            $rec['count'] = (int) $rec['count'] + 1;
            $remaining = max(1, $windowSeconds - ($now - (int) $rec['start']));
            $cache->set($key, $rec, [], $remaining);

            return $rec['count'] > $maxHits;
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Source IP for keying limits. Uses REMOTE_ADDR (the connecting peer), which —
     * unlike X-Forwarded-For — cannot be spoofed by the client. Behind a reverse
     * proxy this is the proxy's IP, so size limits accordingly.
     */
    public static function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
}
