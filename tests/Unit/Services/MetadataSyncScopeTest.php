<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\STS\MetadataSyncScope;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What the STS sends back for a lab's metadata sync request.
 *
 * Measured on the Rwanda STS: a VL-only lab received every EID, hepatitis, TB and
 * CD4 reference table in full on every sync (554 rows) and threw all of it away,
 * because a test type the lab did not name was read as one it had never synced.
 * And the dates in the request went into SQL as sent, on an endpoint that takes
 * no login token.
 */
final class MetadataSyncScopeTest extends TestCase
{
    private const STS_MODULES = [
        'vl' => true,
        'eid' => true,
        'hepatitis' => true,
        'tb' => true,
        'cd4' => true,
        'covid19' => false,
        'generic-tests' => false,
    ];

    public function testLabGetsOnlyTheTestTypesItNames(): void
    {
        $vlOnlyLab = [
            'labId' => 562,
            'vlSampleTypesLastModified' => '2022-08-10 10:47:17',
            'vlResultsLastModified' => null,
        ];

        self::assertTrue(MetadataSyncScope::sendsModule($vlOnlyLab, 'vl', self::STS_MODULES));
        foreach (['eid', 'hepatitis', 'tb', 'cd4'] as $module) {
            self::assertFalse(MetadataSyncScope::sendsModule($vlOnlyLab, $module, self::STS_MODULES), $module);
        }
    }

    public function testNeverSyncedTestTypeIsStillSent(): void
    {
        // A lab that has just switched TB on sends its keys with null dates.
        $lab = [
            'labId' => 7,
            'vlSampleTypesLastModified' => '2022-08-10 10:47:17',
            'tbSampleTypesLastModified' => null,
        ];

        self::assertTrue(MetadataSyncScope::sendsModule($lab, 'tb', self::STS_MODULES));
    }

    public function testLabTooOldToNameTestTypesGetsEverythingTheStsRuns(): void
    {
        $legacyLab = ['labId' => 7, 'facilityLastModified' => '2020-01-01 00:00:00'];

        foreach (['vl', 'eid', 'hepatitis', 'tb', 'cd4'] as $module) {
            self::assertTrue(MetadataSyncScope::sendsModule($legacyLab, $module, self::STS_MODULES), $module);
        }
        self::assertFalse(MetadataSyncScope::sendsModule($legacyLab, 'covid19', self::STS_MODULES));
    }

    public function testTestTypeTheStsDoesNotRunIsNeverSent(): void
    {
        $lab = ['covid19SampleTypesLastModified' => null, 'rTestTypesLastModified' => null];

        self::assertFalse(MetadataSyncScope::sendsModule($lab, 'covid19', self::STS_MODULES));
        self::assertFalse(MetadataSyncScope::sendsModule($lab, 'generic-tests', self::STS_MODULES));
        self::assertFalse(MetadataSyncScope::sendsModule($lab, 'unknown', ['unknown' => true]));
    }

    public function testValidDateBecomesCondition(): void
    {
        $request = ['a' => '2024-06-13 11:50:29', 'b' => '2024-06-13'];

        self::assertSame(
            "updated_datetime > '2024-06-13 11:50:29'",
            MetadataSyncScope::sinceCondition($request, 'a')
        );
        self::assertSame(
            "added_on > '2024-06-13 00:00:00'",
            MetadataSyncScope::sinceCondition($request, 'b', 'added_on')
        );
    }

    /** @return iterable<string, array{mixed}> */
    public static function unusableDates(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'injection' => ["2024-01-01' OR '1'='1"];
        yield 'trailing sql' => ["2024-01-01 00:00:00'; DROP TABLE x; -- "];
        yield 'impossible date' => ['2024-02-30 00:00:00'];
        yield 'array' => [['2024-01-01']];
        yield 'number' => [20240101];
    }

    #[DataProvider('unusableDates')]
    public function testUnusableDateSendsWholeTable(mixed $value): void
    {
        self::assertSame([], MetadataSyncScope::sinceCondition(['k' => $value], 'k'));
    }
}
