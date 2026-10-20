<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TbPoolService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * How a TB batch's samples are split into GeneXpert pools.
 *
 * Samples are pooled in worksheet order. A sample that was in a pool before is
 * tested on its own, and a last pool of one is no pool. The position page labels
 * the list by the same rule in JavaScript, so the two have to agree.
 */
final class TbPoolGroupingTest extends TestCase
{
    /** @return iterable<string, array{list<int>, list<int>, int, list<list<int>>}> */
    public static function batches(): iterable
    {
        // samples in order, samples that run alone, pool size, pools
        yield 'eight in fours' => [[1, 2, 3, 4, 5, 6, 7, 8], [], 4, [[1, 2, 3, 4], [5, 6, 7, 8]]];
        yield 'order is the worksheet\'s' => [[8, 3, 5, 1], [], 4, [[8, 3, 5, 1]]];
        yield 'a short last pool' => [[1, 2, 3, 4, 5, 6], [], 4, [[1, 2, 3, 4], [5, 6]]];
        yield 'a last pool of one' => [[1, 2, 3, 4, 5], [], 4, [[1, 2, 3, 4]]];
        yield 'pooled before' => [[1, 2, 3, 4, 5], [2], 4, [[1, 3, 4, 5]]];
        yield 'pairs' => [[1, 2, 3, 4, 5], [], 2, [[1, 2], [3, 4]]];
        yield 'no pooling' => [[1, 2, 3, 4], [], 0, []];
        yield 'one per pool is no pooling' => [[1, 2, 3, 4], [], 1, []];
        yield 'never more than four' => [[1, 2, 3, 4, 5, 6], [], 6, [[1, 2, 3, 4], [5, 6]]];
    }

    /**
     * @param list<int> $ordered
     * @param list<int> $runAlone
     * @param list<list<int>> $expected
     */
    #[DataProvider('batches')]
    public function testSamplesArePooledInOrder(array $ordered, array $runAlone, int $size, array $expected): void
    {
        self::assertSame(
            $expected,
            TbPoolService::group($ordered, array_fill_keys($runAlone, true), $size)
        );
    }

    public function testAPoolCodeBelongsOnlyToItsOwnBatch(): void
    {
        self::assertTrue(TbPoolService::belongsToBatch('POOL-TB1-2', 'TB1'));
        self::assertFalse(TbPoolService::belongsToBatch('POOL-TB1-2-1', 'TB1'));
        self::assertTrue(TbPoolService::belongsToBatch('POOL-TB1-2-1', 'TB1-2'));
        self::assertFalse(TbPoolService::belongsToBatch(null, 'TB1'));
    }

    public function testTheWorksheetOrderSkipsControls(): void
    {
        self::assertSame(
            [12, 3],
            TbPoolService::sampleIdsInOrder('{"A1":"in_house_controls_1","A2":"s_12","A3":"s_3"}')
        );
        self::assertSame([5], TbPoolService::sampleIdsInOrder(['calibrators_1', 's_5']));
        self::assertSame([], TbPoolService::sampleIdsInOrder('not json'));
        self::assertSame([], TbPoolService::sampleIdsInOrder(null));
    }

    public function testAPoolCodeNamesItsBatch(): void
    {
        self::assertSame('POOL-20261020001-3', TbPoolService::poolCode(' 20261020001 ', 3));
        self::assertTrue(TbPoolService::isPoolCode('pool-20261020001-3'));
        self::assertFalse(TbPoolService::isPoolCode('TB26-0101'));
    }
}
