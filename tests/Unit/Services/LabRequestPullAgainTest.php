<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\LabRequestSyncService;
use PHPUnit\Framework\TestCase;

/**
 * When the requests receiver pulls again in the same run for requests still
 * waiting on the STS, and when it must stop.
 */
final class LabRequestPullAgainTest extends TestCase
{
    /** @return list<string> */
    private static function decide(
        array $delivered,
        array $remaining,
        array $saved,
        array &$previous = []
    ): array {
        $headers = array_map(static fn(int $n): array => ['x-pending-remaining' => (string) $n], $remaining);
        return LabRequestSyncService::modulesToPullAgain($delivered, $headers, $previous, $saved);
    }

    public function testPullsAgainWhileRequestsWaitAndTheLastBatchSaved(): void
    {
        self::assertSame(['vl'], self::decide(['vl' => true], ['vl' => 120], ['vl' => 500]));
    }

    public function testStopsWhenNothingIsLeft(): void
    {
        self::assertSame([], self::decide(['vl' => true], ['vl' => 0], ['vl' => 500]));
    }

    public function testStopsWhenTheReceiptDidNotGetThrough(): void
    {
        // The STS still has the batch in flight and would send it again.
        self::assertSame([], self::decide(['vl' => false], ['vl' => 120], ['vl' => 500]));
    }

    public function testStopsWhenEveryRequestInTheBatchFailed(): void
    {
        // A lab-side fault would otherwise mark its whole backlog failed in one run.
        self::assertSame([], self::decide(['vl' => true], ['vl' => 120], ['vl' => 0]));
        self::assertSame([], self::decide(['vl' => true], ['vl' => 120], []));
    }

    public function testStopsWhenTheRemainderStopsGoingDown(): void
    {
        $previous = [];
        self::assertSame(['vl'], self::decide(['vl' => true], ['vl' => 7], ['vl' => 500], $previous));
        // Seven that can never be confirmed: the same seven come back every time.
        self::assertSame([], self::decide(['vl' => true], ['vl' => 7], ['vl' => 500], $previous));
    }

    public function testAnStsWithoutTheHeaderIsNotPulledAgain(): void
    {
        // An older STS sends no x-pending-remaining.
        $previous = [];
        self::assertSame(
            [],
            LabRequestSyncService::modulesToPullAgain(['vl' => true], ['vl' => []], $previous, ['vl' => 10])
        );
    }

    public function testEachModuleIsDecidedOnItsOwn(): void
    {
        self::assertSame(
            ['tb'],
            self::decide(
                ['vl' => true, 'tb' => true, 'eid' => false],
                ['vl' => 0, 'tb' => 40, 'eid' => 40],
                ['vl' => 5, 'tb' => 5, 'eid' => 5]
            )
        );
    }
}
