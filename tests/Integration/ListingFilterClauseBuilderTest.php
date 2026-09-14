<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Utilities\ListingFilterClauseBuilder as Filters;
use PHPUnit\Framework\TestCase;
use Tests\Support\LegacyAppHarness;

/**
 * The clauses ListingFilterClauseBuilder writes, run against real MySQL.
 *
 * Each kind must match what the hand-written clause it replaces matched for a
 * legitimate value, and a value that tries to leave its quotes must match only
 * rows that literally hold that value.
 *
 * Set INTELIS_TEST_DB_HOST/_PORT/_USER/_PASS to run; skipped without them.
 */
final class ListingFilterClauseBuilderTest extends TestCase
{
    private const DATABASE = 'intelis_listing_filter_test';

    protected function setUp(): void
    {
        if ((getenv('INTELIS_TEST_DB_HOST') ?: '') === '' || (getenv('INTELIS_TEST_DB_USER') ?: '') === '') {
            self::markTestSkipped('Set INTELIS_TEST_DB_HOST and INTELIS_TEST_DB_USER to run integration tests.');
        }
        $db = LegacyAppHarness::boot(self::DATABASE . '_' . getmypid(), []);
        $db->rawQuery('CREATE TABLE t (id INT PRIMARY KEY, code VARCHAR(40), lab_id INT)');
        $db->rawQuery(
            "INSERT INTO t VALUES
                (1, 'B-ONE', 10), (2, 'B-TWO', 20), (3, 'B_ONE', 30), (4, 'it\\'s \"quoted\"', 40)"
        );
    }

    protected function tearDown(): void
    {
        if ((getenv('INTELIS_TEST_DB_HOST') ?: '') !== '') {
            LegacyAppHarness::shutdown();
        }
    }

    /**
     * @param array<string, mixed> $request
     * @param array<string, array{0: string, 1: string}> $filters
     * @return list<int>
     */
    private function ids(array $request, array $filters): array
    {
        $clauses = Filters::clauses(LegacyAppHarness::db(), $request, $filters);
        $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);
        $rows = LegacyAppHarness::db()->rawQuery("SELECT id FROM t$where ORDER BY id");
        return array_map(static fn(array $row): int => (int) $row['id'], $rows);
    }

    public function testBlankAndMissingValuesAddNoClause(): void
    {
        $filters = ['code' => ['code', Filters::EQUALS], 'lab' => ['lab_id', Filters::INT_LIST]];
        $this->assertSame([], Filters::clauses(LegacyAppHarness::db(), ['code' => '  ', 'lab' => []], $filters));
        $this->assertSame([1, 2, 3, 4], $this->ids([], $filters));
    }

    public function testEqualsMatchesTheValueAndOnlyTheValue(): void
    {
        $filters = ['code' => ['code', Filters::EQUALS]];
        $this->assertSame([1], $this->ids(['code' => 'B-ONE'], $filters));
        $this->assertSame([4], $this->ids(['code' => 'it\'s "quoted"'], $filters));
        $this->assertSame([], $this->ids(['code' => 'x" OR "1"="1'], $filters));
    }

    public function testIntListTakesCsvOrArrayAndDropsTheRest(): void
    {
        $filters = ['lab' => ['lab_id', Filters::INT_LIST]];
        $this->assertSame([1, 2], $this->ids(['lab' => '10,20'], $filters));
        $this->assertSame([2, 3], $this->ids(['lab' => ['20', '30']], $filters));
        $this->assertSame([], $this->ids(['lab' => '0) OR (1=1'], $filters));
    }

    public function testIntCastsTheValue(): void
    {
        $filters = ['lab' => ['lab_id', Filters::INT]];
        $this->assertSame([3], $this->ids(['lab' => '30'], $filters));
        $this->assertSame([], $this->ids(['lab' => '1 OR 1=1'], $filters));
    }

    public function testContainsMatchesWildcardsLiterally(): void
    {
        $filters = ['code' => ['code', Filters::CONTAINS]];
        $this->assertSame([1, 3], $this->ids(['code' => 'ONE'], $filters));
        $this->assertSame([3], $this->ids(['code' => '_'], $filters));
        $this->assertSame([], $this->ids(['code' => '%" OR "1"="1'], $filters));
    }

    public function testLikeKeepsTheValuesOwnWildcards(): void
    {
        $filters = ['code' => ['code', Filters::LIKE]];
        $this->assertSame([2], $this->ids(['code' => 'b-two'], $filters));
        $this->assertSame([1, 3], $this->ids(['code' => 'B_ONE'], $filters));
        $this->assertSame([], $this->ids(['code' => 'x" OR "1"="1'], $filters));
    }

    public function testQuotedTextListKeepsCodesWholeAndEscaped(): void
    {
        $db = LegacyAppHarness::db();
        $this->assertSame("'B-ONE','B-TWO'", Filters::quotedTextList($db, "'B-ONE', 'B-TWO'"));
        $this->assertSame("'ABC,123','B-TWO'", Filters::quotedTextList($db, "'ABC,123', 'B-TWO'"));
        $this->assertSame("'B-ONE','B-TWO'", Filters::quotedTextList($db, 'B-ONE, B-TWO'));

        $codes = Filters::quotedTextList($db, "'B-TWO', 'x\\') OR (1=1 -- '");
        $rows = $db->rawQuery("SELECT id FROM t WHERE code IN ($codes) ORDER BY id");
        $this->assertSame([2], array_map(static fn(array $row): int => (int) $row['id'], $rows));
    }

    public function testAnUnknownKindIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Filters::clauses(LegacyAppHarness::db(), ['code' => 'B-ONE'], ['code' => ['code', 'regex']]);
    }
}
