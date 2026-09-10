<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\DurationUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DurationUtilityTest extends TestCase
{
    /** @return array<string, array{0:int, 1:string}> */
    public static function durations(): array
    {
        return [
            'zero' => [0, '-'],
            'negative' => [-10, '-'],
            'seconds' => [38, '38 sec'],
            'exact minute' => [60, '1 min'],
            'minutes' => [750, '12 min 30 sec'],
            'exact hour' => [3600, '1 hr'],
            'hours' => [8115, '2 hr 15 min'],
            'exact day' => [86400, '1 d'],
            'days' => [93600, '1 d 2 hr'],
        ];
    }

    #[DataProvider('durations')]
    public function testHumanReadable(int $seconds, string $expected): void
    {
        $this->assertSame($expected, DurationUtility::humanReadable($seconds));
    }

    public function testClock(): void
    {
        $this->assertSame('02:15:15', DurationUtility::clock(8115));
        $this->assertSame('00:00:00', DurationUtility::clock(-5));
    }
}
