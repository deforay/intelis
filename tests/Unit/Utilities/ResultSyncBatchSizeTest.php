<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\ResultSyncBatchSize;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ResultSyncBatchSizeTest extends TestCase
{
    public static function responses(): iterable
    {
        yield 'old server without hints' => [500, 1000, [], 2.0, 500];
        yield 'server asks for less' => [1000, 1000, ['x-chunk-next' => '500'], 2.0, 500];
        yield 'gradual recovery' => [400, 1000, ['x-chunk-next' => '1000'], 2.0, 500];
        yield 'respect operator ceiling' => [100, 100, ['x-chunk-next' => '1000'], 2.0, 100];
        yield 'slow upload overrides server hint' => [1000, 1000, ['x-chunk-next' => '1000'], 40.0, 500];
        yield 'slow old server' => [100, 1000, [], 40.0, 50];
        yield 'no growth for moderately slow requests' => [500, 1000, ['x-chunk-next' => '1000'], 15.0, 500];
        yield 'invalid hint' => [500, 1000, ['x-chunk-next' => 'garbage'], 2.0, 500];
        yield 'zero hint' => [500, 1000, ['x-chunk-next' => '0'], 2.0, 500];
        yield 'negative hint' => [500, 1000, ['x-chunk-next' => '-1'], 2.0, 500];
        yield 'never zero rows' => [1, 1000, [], 40.0, 1];
    }

    #[DataProvider('responses')]
    public function testNextSize(int $current, int $maximum, array $headers, float $seconds, int $expected): void
    {
        self::assertSame($expected, ResultSyncBatchSize::next($current, $maximum, $headers, $seconds));
    }
}
