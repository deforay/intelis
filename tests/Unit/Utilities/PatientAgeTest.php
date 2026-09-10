<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Registries\ContainerRegistry;
use App\Utilities\DateUtility;
use App\Utilities\FileCacheUtility;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * A printed age is a whole count of completed units. Carbon 3 changed
 * diffInYears() from an absolute integer to a signed float, which put values
 * like "-42.60547947705 year" on Cameroon VL result PDFs.
 */
final class PatientAgeTest extends TestCase
{
    /**
     * Date parsing resolves a cache through MemoUtility, which reads it from
     * the container. Nothing sets one in a bare unit test, so register a
     * pass-through cache -- same technique as DateRangeTest.
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

        Carbon::setTestNow(Carbon::parse('2026-09-10 12:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
    }

    public static function ageProvider(): array
    {
        return [
            'the DOB that printed -42.60547947705' => [
                ['patient_dob' => '1984-02-01'],
                '42 years',
            ],
            'the DOB that printed -87.567123301597' => [
                ['patient_dob' => '1939-01-14'],
                '87 years',
            ],
            'an entered age is used ahead of the DOB' => [
                ['patient_dob' => '1984-02-01', 'patient_age_in_years' => 42],
                '42 years',
            ],
            'a birthday still to come this year is not counted' => [
                ['patient_dob' => '1984-12-25'],
                '41 years',
            ],
            'an infant is reported in months, not "0 year"' => [
                ['patient_dob' => '2026-04-10'],
                '5 months',
            ],
            'a newborn is reported in days' => [
                ['patient_dob' => '2026-09-07'],
                '3 days',
            ],
            'born today' => [
                ['patient_dob' => '2026-09-10'],
                '0 days',
            ],
            'exactly one year is singular' => [
                ['patient_dob' => '2025-09-10'],
                '1 year',
            ],
            'a DOB in the future has no age' => [
                ['patient_dob' => '2031-09-10'],
                'Unknown',
            ],
            'a future DOB still falls back to an entered age in months' => [
                ['patient_dob' => '2031-09-10', 'patient_age_in_months' => 7],
                '7 months',
            ],
            'a zero DOB has no age' => [
                ['patient_dob' => '0000-00-00'],
                'Unknown',
            ],
            'nothing to go on' => [
                [],
                'Unknown',
            ],
        ];
    }

    #[DataProvider('ageProvider')]
    public function testAgeIsAWholePositiveCount(array $result, string $expected): void
    {
        $this->assertSame($expected, DateUtility::calculatePatientAge($result));
    }

    public function testAgeNeverPrintsASignOrADecimalPoint(): void
    {
        foreach (['1939-01-14', '1984-02-01', '2026-04-10', '2026-09-07'] as $dob) {
            $age = DateUtility::calculatePatientAge(['patient_dob' => $dob]);
            $this->assertDoesNotMatchRegularExpression('/[-.]/', $age, "DOB $dob produced '$age'");
        }
    }

    public function testAFutureDobHasNoYearMonthDayBreakdown(): void
    {
        $this->assertNull(DateUtility::ageInYearMonthDays('2031-09-10'));
        $this->assertSame(42, DateUtility::ageInYearMonthDays('1984-02-01')['year']);
    }
}
