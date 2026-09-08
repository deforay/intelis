<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Registries\ContainerRegistry;
use App\Utilities\DateUtility;
use App\Utilities\FileCacheUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * DateUtility::dayRange() turns a picked range into the two datetimes that
 * bound it, so a report can compare a datetime column directly instead of
 * wrapping it in DATE() and losing the index.
 */
final class DateRangeTest extends TestCase
{
    /**
     * The date parsing resolves a cache through MemoUtility, which reads it from
     * the container. Nothing sets one in a bare unit test, so register a
     * pass-through cache -- same technique as AdminFilterClauseBuilderTest.
     */
    protected function setUp(): void
    {
        $passThroughCache = new class extends FileCacheUtility {
            public function __construct()
            {
            }

            public function get(
                string $key,
                callable $computeValueCallback,
                ?array $tags = [],
                int $expiration = 3600
            ): mixed {
                return $computeValueCallback();
            }
        };

        ContainerRegistry::setContainer(new class ($passThroughCache) implements ContainerInterface {
            public function __construct(private readonly FileCacheUtility $cache)
            {
            }

            public function get(string $id): mixed
            {
                return $this->cache;
            }

            public function has(string $id): bool
            {
                return true;
            }
        });
    }

    public static function rangeProvider(): array
    {
        return [
            'a span covers midnight on the first day to the last second of the last' => [
                '01-Jan-2026 to 31-Jan-2026',
                ['2026-01-01 00:00:00', '2026-01-31 23:59:59'],
            ],
            'both ends the same day still covers that whole day' => [
                '05-Feb-2026 to 05-Feb-2026',
                ['2026-02-05 00:00:00', '2026-02-05 23:59:59'],
            ],
            'a single day with no end bounds that day rather than collapsing to midnight' => [
                '05-Feb-2026',
                ['2026-02-05 00:00:00', '2026-02-05 23:59:59'],
            ],
            'nothing picked filters nothing' => ['', ['', '']],
            'null filters nothing' => [null, ['', '']],
        ];
    }

    #[DataProvider('rangeProvider')]
    public function testDayRange(?string $input, array $expected): void
    {
        $this->assertSame($expected, DateUtility::dayRange($input));
    }

    public function testTheEndIsInclusiveOfTheLastSecondOfTheDay(): void
    {
        // form_* date columns are plain datetimes with no fractional seconds,
        // so 23:59:59 is an exact endpoint, not a near miss.
        [, $end] = DateUtility::dayRange('01-Jan-2026 to 01-Jan-2026');
        $this->assertSame('2026-01-01 23:59:59', $end);
    }
}
