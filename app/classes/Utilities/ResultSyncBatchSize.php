<?php

declare(strict_types=1);

namespace App\Utilities;

final class ResultSyncBatchSize
{
    /**
     * Keep requests comfortably below the HTTP timeout, including on slow links.
     * A CLI chunk size is a ceiling, so a server hint cannot undo an operator's limit.
     *
     * @param array<string, string> $headers
     */
    public static function next(int $current, int $maximum, array $headers, float $seconds): int
    {
        $maximum = max(1, $maximum);
        $current = max(1, min($current, $maximum));
        $hint = filter_var($headers['x-chunk-next'] ?? null, FILTER_VALIDATE_INT);
        $target = is_int($hint) && $hint > 0 ? min($hint, $maximum) : $current;

        if ($seconds > 30) {
            return max(1, min($target, (int) floor($current / 2)));
        }
        if ($target <= $current) {
            return max(1, $target);
        }
        // Grow gradually only after a quick response, avoiding repeated large timeouts.
        return $seconds < 10
            ? min($target, $current + max(1, (int) floor($current / 4)))
            : $current;
    }
}
