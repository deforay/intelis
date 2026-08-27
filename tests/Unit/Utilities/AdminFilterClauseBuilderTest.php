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
        $this->assertNull(
            AdminFilterClauseBuilder::dateRangeClause(['dateRange' => '  '], 't.request_created_datetime')
        );
    }

    /**
     * Asserts the dates, not just the shape. Checking only that the string contains
     * "BETWEEN" passes even when both bounds are empty -- which is what the original
     * version of this test did, because its fixture used a hyphen and
     * convertDateRange() separates on "to" (the separator the dashboards post, see
     * api-dashboard.php's daterangepicker). It was asserting BETWEEN '' AND ''.
     */
    public function testDateRangeClauseCarriesTheParsedDates(): void
    {
        $clause = AdminFilterClauseBuilder::dateRangeClause(
            ['dateRange' => '01/15/2026 to 02/20/2026'],
            't.request_created_datetime'
        );

        self::assertSame(
            " t.request_created_datetime BETWEEN '2026-01-15 00:00:00' AND '2026-02-20 23:59:59' ",
            $clause
        );
    }

    /** The end of the range is the end of that day, not its first second. */
    public function testASingleDayRangeSpansTheWholeDay(): void
    {
        $clause = AdminFilterClauseBuilder::dateRangeClause(
            ['dateRange' => '03/01/2026 to 03/01/2026'],
            't.request_created_datetime'
        );

        self::assertSame(
            " t.request_created_datetime BETWEEN '2026-03-01 00:00:00' AND '2026-03-01 23:59:59' ",
            $clause
        );
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

    /**
     * These ids reach an IN() list, which cannot take a placeholder for a
     * variable-length list, so they are concatenated. Dropping everything non-numeric
     * is what makes that safe, and it is the only thing that does.
     */
    public function testNonNumericValuesAreDroppedRatherThanConcatenated(): void
    {
        $clause = AdminFilterClauseBuilder::inClause(
            ['labName' => "1,2) OR 1=1 --"],
            'labName',
            't.lab_id'
        );

        // The injected token is dropped whole, taking its leading "2" with it, so the
        // clause is narrower than the caller asked for rather than wider.
        self::assertSame(' t.lab_id IN (1)', $clause);
    }

    public function testAValueThatIsEntirelyNonNumericYieldsNoClauseAtAll(): void
    {
        self::assertNull(
            AdminFilterClauseBuilder::inClause(['labName' => 'DROP TABLE form_vl'], 'labName', 't.lab_id')
        );
    }

    public function testNonNumericMembersOfAnArrayAreDropped(): void
    {
        $clause = AdminFilterClauseBuilder::inClause(
            ['state' => [4, '5; DELETE FROM form_vl', 6]],
            'state',
            'f.facility_state_id'
        );

        self::assertSame(' f.facility_state_id IN (4,6)', $clause);
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
