<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\STS\RequestsService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * What the STS sync puts in its WHERE clause.
 *
 * The sync selects the rows a lab has touched since a date. That predicate used
 * to read DATE(last_modified_datetime) >= '...', and a function wrapped around a
 * column puts every index on it out of reach: on the DRC server the query read
 * all 1.46M rows of form_vl every time it ran, 2,524 slow-log entries in one
 * 17.5 hour window. The index added in 5.7.56 is only reachable while the column
 * stays bare, and nothing about a re-introduced wrapper would look wrong -- the
 * answers stay correct, the query just reads the whole table again. Hence a test
 * on the clause itself.
 *
 * buildCondition() is private and the constructor wants a database; the clause is
 * pure string building, so it is reached directly.
 */
final class StsSyncConditionTest extends TestCase
{
    private function buildCondition(
        $labId,
        $facilityMapResult = [],
        $manifestCode = null,
        $syncSinceDate = null,
        string $testType = 'vl',
        int $dataSyncInterval = 30
    ): array {
        $service = (new ReflectionClass(RequestsService::class))->newInstanceWithoutConstructor();

        foreach (['testType' => $testType, 'dataSyncInterval' => $dataSyncInterval] as $prop => $value) {
            $p = new \ReflectionProperty(RequestsService::class, $prop);
            $p->setValue($service, $value);
        }

        $method = new ReflectionMethod(RequestsService::class, 'buildCondition');

        return $method->invoke($service, $labId, $facilityMapResult, $manifestCode, $syncSinceDate);
    }

    /**
     * The column must never be wrapped in a function, whichever branch is taken.
     *
     * This is the assertion that protects the index. Every branch is covered
     * because the wrapper only has to come back in one of them to lose the win.
     */
    public function testTheModifiedColumnIsNeverWrappedInAFunction(): void
    {
        $branches = [
            'sync since a date'   => [86, '1,2,3', null, '2026-09-03'],
            'manifest code'       => [86, '1,2,3', 'MAN-001', null],
            'default interval'    => [86, '1,2,3', null, null],
            'no facility map'     => [86, [], null, '2026-09-03'],
        ];

        foreach ($branches as $label => $args) {
            [$condition] = $this->buildCondition(...$args);

            $this->assertDoesNotMatchRegularExpression(
                '/\b[A-Z_]+\s*\(\s*`?last_modified_datetime`?/i',
                $condition,
                "Branch '$label' wraps last_modified_datetime in a function, which makes its index unusable."
            );
        }
    }

    /** The date is bound rather than pasted into the SQL string. */
    public function testTheSyncDateIsBound(): void
    {
        [$condition, $params] = $this->buildCondition(86, '1,2,3', null, '2026-09-03');

        $this->assertStringContainsString('last_modified_datetime >= ?', $condition);
        $this->assertStringNotContainsString('2026-09-03', $condition, 'The date must not reach the SQL string.');
        $this->assertSame(['2026-09-03 00:00:00'], $params);
    }

    /**
     * A bare date still means from midnight that day.
     *
     * DATE(col) >= '2026-09-03' matched anything on the 3rd, so comparing the
     * datetime has to start at midnight or the first day of a sync would be lost.
     */
    public function testABareDateBecomesMidnight(): void
    {
        [, $params] = $this->buildCondition(86, '1,2,3', null, '2026-09-03');
        $this->assertSame('2026-09-03 00:00:00', $params[0]);
    }

    /** A caller that already passes a time keeps it. */
    public function testAFullDatetimeIsPassedThrough(): void
    {
        [, $params] = $this->buildCondition(86, '1,2,3', null, '2026-09-03 14:30:00');
        $this->assertSame('2026-09-03 14:30:00', $params[0]);
    }

    /** The manifest code is bound too, on both the single and the TB/custom branch. */
    public function testManifestCodeIsBound(): void
    {
        [$condition, $params] = $this->buildCondition(86, '1,2,3', 'MAN-001', null, 'vl');
        $this->assertStringNotContainsString('MAN-001', $condition);
        $this->assertSame(['MAN-001'], $params);

        [$tbCondition, $tbParams] = $this->buildCondition(86, '1,2,3', 'MAN-001', null, 'tb');
        $this->assertStringNotContainsString('MAN-001', $tbCondition);
        $this->assertSame(['MAN-001', 'MAN-001'], $tbParams, 'The TB branch searches two columns, so it binds twice.');
    }

    /** The manifest branch wins over a sync date, as it did before. */
    public function testManifestCodeTakesPrecedenceOverSyncDate(): void
    {
        [$condition] = $this->buildCondition(86, '1,2,3', 'MAN-001', '2026-09-03');
        $this->assertStringContainsString('sample_package_code', $condition);
        $this->assertStringNotContainsString('last_modified_datetime', $condition);
    }
}
