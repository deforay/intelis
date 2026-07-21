<?php

declare(strict_types=1);

namespace Tests\Unit\InterfaceApi;

use App\Services\InstrumentUsageStatisticsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Covers what a summary has to be before it reaches the database. The service is
 * built without its constructor and normalization is exercised directly, so these
 * run without a database: a summary that gets past normalization is exactly the row
 * that would be written.
 */
final class InstrumentUsageStatisticsServiceTest extends TestCase
{
    private const VALID_SUMMARY = [
        'aggregate_id' => 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee',
        'activity_date' => '2026-07-21',
        'instrument_id' => 'ANALYZER-1',
        'machine_type' => 'roche-cobas-5800',
        'test_type' => 'HIVVL',
        'total_tests' => 25,
        'successful_tests' => 23,
        'failed_tests' => 2,
        'first_test_at' => '2026-07-21 08:00:00',
        'last_test_at' => '2026-07-21 17:00:00',
        'revision' => 25,
    ];

    public function testAValidSummaryBecomesARowOwnedByTheCallersLab(): void
    {
        $row = $this->normalize(self::VALID_SUMMARY, labId: 7);

        self::assertIsArray($row);
        self::assertSame(7, $row['lab_id']);
        self::assertSame('aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee', $row['aggregate_uid']);
        self::assertSame('2026-07-21', $row['activity_date']);
        self::assertSame(25, $row['total_tests']);
        self::assertSame(25, $row['revision']);
        self::assertSame('2026-07-21 08:00:00', $row['first_test_at']);
    }

    /**
     * A summary carrying a lab of its own does not get to use it: the lab written is
     * the one the caller supplied, from the credential or the system config.
     */
    public function testALabInThePayloadIsIgnored(): void
    {
        $row = $this->normalize(self::VALID_SUMMARY + ['lab_id' => 99], labId: 7);

        self::assertIsArray($row);
        self::assertSame(7, $row['lab_id']);
    }

    /** @param array<string, mixed> $overrides */
    #[DataProvider('unusableSummaryProvider')]
    public function testUnusableSummariesAreRejected(array $overrides): void
    {
        self::assertNull($this->normalize(array_merge(self::VALID_SUMMARY, $overrides), labId: 7));
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function unusableSummaryProvider(): array
    {
        return [
            'aggregate id is not a uuid' => [['aggregate_id' => 'ANALYZER-1-2026-07-21']],
            'aggregate id is empty' => [['aggregate_id' => '']],
            'date is not a calendar date' => [['activity_date' => '21 July 2026']],
            'date is impossible' => [['activity_date' => '2026-13-45']],
            'revision is zero' => [['revision' => 0]],
            'revision is negative' => [['revision' => -1]],
            'revision is not a number' => [['revision' => '1abc']],
            'revision is fractional' => [['revision' => 1.5]],
            'revision is a fractional string' => [['revision' => '3.9']],
            'revision is in exponent form' => [['revision' => '1e3']],
            'revision is boolean' => [['revision' => true]],
            // The column is INT UNSIGNED. A revision past its ceiling would be clamped on
            // the way in, and every later revision would then look stale for good.
            'revision is past the column ceiling' => [['revision' => 4294967296]],
            'a count is in exponent form' => [
                ['total_tests' => '1e3', 'successful_tests' => 1000, 'failed_tests' => 0],
            ],
            // Sent but unreadable is a fault to report, not something to store as absent.
            'first test is malformed' => [['first_test_at' => 'yesterday morning']],
            'last test is malformed' => [['last_test_at' => '2026-07-21T17:00:00Z']],
            'first test is not a scalar' => [['first_test_at' => ['2026-07-21 08:00:00']]],
            'counts do not add up' => [['total_tests' => 25, 'successful_tests' => 20, 'failed_tests' => 2]],
            'a count is negative' => [['total_tests' => -1, 'successful_tests' => -1, 'failed_tests' => 0]],
            'a count is not a number' => [['total_tests' => 'many']],
            'a count is fractional' => [['total_tests' => 2.5]],
            'first test is after the last' => [
                ['first_test_at' => '2026-07-21 17:00:00', 'last_test_at' => '2026-07-21 08:00:00'],
            ],
        ];
    }

    /** MySQL hands every column back as a string, so the importer's rows must survive. */
    public function testDigitStringsFromTheImporterAreAccepted(): void
    {
        $row = $this->normalize(
            array_merge(
                self::VALID_SUMMARY,
                [
                    'total_tests' => '25',
                    'successful_tests' => '23',
                    'failed_tests' => '2',
                    'revision' => '25',
                ]
            ),
            labId: 7
        );

        self::assertIsArray($row);
        self::assertSame(25, $row['total_tests']);
        self::assertSame(25, $row['revision']);
    }

    /** An omitted timestamp is fine; only a supplied-but-unreadable one is a fault. */
    public function testAbsentTimestampsAreAccepted(): void
    {
        $summary = self::VALID_SUMMARY;
        unset($summary['first_test_at'], $summary['last_test_at']);

        $row = $this->normalize($summary, labId: 7);

        self::assertIsArray($row);
        self::assertNull($row['first_test_at']);
        self::assertNull($row['last_test_at']);
    }

    /** A day with no tests at all is a legitimate report, not a rejection. */
    public function testAnEmptyDayIsAccepted(): void
    {
        $row = $this->normalize(
            array_merge(
                self::VALID_SUMMARY,
                ['total_tests' => 0, 'successful_tests' => 0, 'failed_tests' => 0]
            ),
            labId: 7
        );

        self::assertIsArray($row);
        self::assertSame(0, $row['total_tests']);
    }

    /**
     * @param array<string, mixed> $summary
     * @return array<string, mixed>|null
     */
    private function normalize(array $summary, int $labId): ?array
    {
        $service = (new ReflectionClass(InstrumentUsageStatisticsService::class))->newInstanceWithoutConstructor();
        $normalize = new \ReflectionMethod($service, 'normalize');

        return $normalize->invoke($service, $summary, $labId, InstrumentUsageStatisticsService::VIA_API, null);
    }
}
