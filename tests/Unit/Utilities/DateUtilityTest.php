<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Registries\ContainerRegistry;
use App\Utilities\DateUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use RuntimeException;

final class DateUtilityTest extends TestCase
{
    /**
     * Date formatting used to go through a cross-request memo that wrote a
     * cache file for every distinct date. A container that refuses every
     * lookup proves none of these calls reaches a cache any more.
     */
    protected function setUp(): void
    {
        ContainerRegistry::setContainer(new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new RuntimeException("Date utilities must not resolve $id");
            }

            public function has(string $id): bool
            {
                return false;
            }
        });
    }

    protected function tearDown(): void
    {
        unset($_SESSION['phpDateFormat']);
    }

    public static function notDateProvider(): array
    {
        return [
            'MySQL zero date' => ['0000-00-00'],
            'MySQL zero datetime' => ['0000-00-00 00:00:00'],
            'empty' => [''],
            'zero' => ['0'],
            'null' => [null],
            'the word null' => ['null'],
            'an age, not a timestamp' => ['42'],
            'a masked date' => ['__-__-____'],
            'text' => ['not a date'],
        ];
    }

    #[DataProvider('notDateProvider')]
    public function testNonDatesAreNotDates(mixed $value): void
    {
        $this->assertFalse(DateUtility::isDateValid($value));
        $this->assertNull(DateUtility::humanReadableDateFormat($value));
        $this->assertNull(DateUtility::isoDateFormat($value));
        $this->assertNull(DateUtility::ageInYearMonthDays($value));
        $this->assertNull(DateUtility::completedYears($value));
    }

    public function testDatesAndTimestampsAreDates(): void
    {
        $this->assertTrue(DateUtility::isDateValid('2026-08-18'));
        $this->assertTrue(DateUtility::isDateValid('18-Aug-2026 10:00'));
        $this->assertTrue(DateUtility::isDateValid('18-août-2026'));
        $this->assertTrue(DateUtility::isDateValid('1786000000'));
        $this->assertSame('2026-08-18 10:00:00', DateUtility::isoDateFormat('18-Aug-2026 10:00', true));
    }

    public function testDisplayFormatFollowsTheCurrentUser(): void
    {
        $_SESSION['phpDateFormat'] = 'd-M-Y';
        $this->assertSame('18-Aug-2026 10:05', DateUtility::humanReadableDateFormat('2026-08-18 10:05:00', true));

        // The same date for a user with another format: a memo keyed without
        // the format used to hand back the first user's text
        $_SESSION['phpDateFormat'] = 'Y/m/d';
        $this->assertSame('2026/08/18 10:05', DateUtility::humanReadableDateFormat('2026-08-18 10:05:00', true));
        $withSeconds = DateUtility::humanReadableDateFormat('2026-08-18 10:05:00', true, null, true);
        $this->assertSame('2026/08/18 10:05:00', $withSeconds);
        $this->assertSame('18 Aug', DateUtility::humanReadableDateFormat('2026-08-18 10:05:00', false, 'd M'));
    }

    public function testGetDateTime(): void
    {
        $this->assertSame('2026-08-18', DateUtility::getDateTime('18/08/2026', 'Y-m-d', 'd/m/Y'));
        $this->assertNull(DateUtility::getDateTime('2026-08-18', 'Y-m-d', 'd/m/Y'));
        $this->assertSame('2026-08-18', DateUtility::getDateTime('18-Aug-2026', 'Y-m-d', ''));
        $this->assertNull(DateUtility::getDateTime('0000-00-00'));
        $this->assertNull(DateUtility::getDateTime(null));
    }

    public function testLowestAndHighestDateSkipNonDates(): void
    {
        $dates = ['2026-08-18 10:00:00', '0000-00-00', '', '2026-08-01', 'junk', '2026-09-02 08:30:00'];
        $this->assertSame('2026-08-01 00:00:00', DateUtility::getLowestDate(...$dates));
        $this->assertSame('2026-09-02 08:30:00', DateUtility::getHighestDate(...$dates));
        $this->assertNull(DateUtility::getLowestDate('', '0000-00-00'));
        $this->assertNull(DateUtility::getHighestDate());
    }
}
