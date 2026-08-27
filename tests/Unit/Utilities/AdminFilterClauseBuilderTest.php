<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Registries\ContainerRegistry;
use App\Utilities\AdminFilterClauseBuilder;
use App\Utilities\FileCacheUtility;
use Psr\Container\ContainerInterface;
use PHPUnit\Framework\TestCase;

final class AdminFilterClauseBuilderTest extends TestCase
{
    /**
     * DateUtility::convertDateRange() resolves a cache through MemoUtility, which reads
     * it from the container. Nothing sets a container in a bare unit test, so without
     * this it throws "Container is not set." Register a pass-through cache -- same
     * technique as VlServiceFactory -- so the date parsing runs without a real cache
     * or database.
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

    // --- dateRangeClause ---

    public function testDateRangeClauseIsNullWhenNotSet(): void
    {
        $this->assertNull(AdminFilterClauseBuilder::dateRangeClause([], 't.request_created_datetime'));
    }

    public function testDateRangeClauseIsNullWhenEmptyString(): void
    {
        $this->assertNull(AdminFilterClauseBuilder::dateRangeClause(['dateRange' => '  '], 't.request_created_datetime'));
    }

    public function testDateRangeClauseContainsBetweenAndTheGivenColumn(): void
    {
        $clause = AdminFilterClauseBuilder::dateRangeClause(
            ['dateRange' => date('m/d/Y') . ' - ' . date('m/d/Y', strtotime('+30 days'))],
            't.request_created_datetime'
        );

        $this->assertNotNull($clause);
        $this->assertStringContainsString('BETWEEN', $clause);
        $this->assertStringContainsString('t.request_created_datetime', $clause);
    }

    // --- inClause ---

    public function testInClauseIsNullWhenKeyAbsent(): void
    {
        $this->assertNull(AdminFilterClauseBuilder::inClause([], 'labName', 't.lab_id'));
    }

    public function testInClauseIsNullWhenScalarValueIsEmptyString(): void
    {
        $this->assertNull(AdminFilterClauseBuilder::inClause(['labName' => ''], 'labName', 't.lab_id'));
    }

    public function testInClauseIsNullWhenScalarValueIsWhitespace(): void
    {
        $this->assertNull(AdminFilterClauseBuilder::inClause(['labName' => '   '], 'labName', 't.lab_id'));
    }

    public function testInClauseIsNullWhenArrayValueIsEmpty(): void
    {
        $this->assertNull(AdminFilterClauseBuilder::inClause(['state' => []], 'state', 'f.facility_state_id'));
    }

    public function testInClauseWithScalarLabNameValue(): void
    {
        // labName arrives as a raw comma-separated string, not an array, in the
        // original endpoint files -- this is not escaped, matching current behavior.
        $clause = AdminFilterClauseBuilder::inClause(['labName' => '1,2,3'], 'labName', 't.lab_id');

        $this->assertSame(' t.lab_id IN (1,2,3)', $clause);
    }

    public function testInClauseWithArrayValueIsImploded(): void
    {
        $clause = AdminFilterClauseBuilder::inClause(['state' => [4, 5, 6]], 'state', 'f.facility_state_id');

        $this->assertSame(' f.facility_state_id IN (4,5,6)', $clause);
    }

    public function testInClauseWithSingleElementArray(): void
    {
        $clause = AdminFilterClauseBuilder::inClause(['facilityId' => [42]], 'facilityId', 't.facility_id');

        $this->assertSame(' t.facility_id IN (42)', $clause);
    }

    // --- buildStandardFilters ---

    public function testBuildStandardFiltersReturnsEmptyArrayWhenNoFiltersSet(): void
    {
        $clauses = AdminFilterClauseBuilder::buildStandardFilters([], [
            'labColumn' => 't.lab_id',
            'stateColumn' => 'f.facility_state_id',
        ]);

        $this->assertSame([], $clauses);
    }

    public function testBuildStandardFiltersOnlyIncludesConfiguredColumns(): void
    {
        // facilityId is present in $post, but facilityColumn wasn't passed in $columns,
        // so it must not appear -- callers that don't filter by facility shouldn't get
        // a facility clause just because the POST body happens to contain the key.
        $clauses = AdminFilterClauseBuilder::buildStandardFilters(
            ['labName' => '1,2', 'facilityId' => [9]],
            ['labColumn' => 't.lab_id']
        );

        $this->assertCount(1, $clauses);
        $this->assertSame(' t.lab_id IN (1,2)', $clauses[0]);
    }

    public function testBuildStandardFiltersCombinesMultipleFilters(): void
    {
        $clauses = AdminFilterClauseBuilder::buildStandardFilters(
            ['labName' => '7', 'state' => [1, 2]],
            ['labColumn' => 't.lab_id', 'stateColumn' => 'f.facility_state_id']
        );

        $this->assertCount(2, $clauses);
        $this->assertSame(' t.lab_id IN (7)', $clauses[0]);
        $this->assertSame(' f.facility_state_id IN (1,2)', $clauses[1]);
    }

    // --- needsFacilityJoin ---

    public function testNeedsFacilityJoinIsFalseWhenNeitherStateNorDistrictSet(): void
    {
        $this->assertFalse(AdminFilterClauseBuilder::needsFacilityJoin(['labName' => '1']));
    }

    public function testNeedsFacilityJoinIsTrueWhenStateSet(): void
    {
        $this->assertTrue(AdminFilterClauseBuilder::needsFacilityJoin(['state' => [1]]));
    }

    public function testNeedsFacilityJoinIsTrueWhenDistrictSet(): void
    {
        $this->assertTrue(AdminFilterClauseBuilder::needsFacilityJoin(['district' => [1]]));
    }
}
