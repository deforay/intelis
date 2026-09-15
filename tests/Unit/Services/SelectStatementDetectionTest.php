<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\DatabaseService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * getDataAndCount() only runs SELECT/WITH statements, and strips leading comments
 * before it looks at the first keyword.
 *
 * The "#" comment was stripped as ".*" under the (?s) flag, so it ran to the end of
 * the whole statement: a query opening with a "# note" line lost its SELECT too and
 * was refused as not a SELECT.
 */
final class SelectStatementDetectionTest extends TestCase
{
    private static function strip(string $sql): string
    {
        $db = (new ReflectionClass(DatabaseService::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod(DatabaseService::class, 'sanitizeSqlForSelect');

        return $method->invoke($db, $sql);
    }

    /** @return iterable<string, array{string}> */
    public static function selects(): iterable
    {
        yield 'plain' => ['SELECT 1'];
        yield 'hash comment line first' => ["# samples due today\nSELECT * FROM form_vl"];
        yield 'dash comment line first' => ["-- samples due today\nSELECT * FROM form_vl"];
        yield 'block comment first' => ["/* due\n today */ SELECT * FROM form_vl"];
        yield 'hash inside a literal' => ["SELECT * FROM form_vl WHERE sample_code = '#12'"];
        yield 'parenthesised' => ['(SELECT 1) UNION (SELECT 2)'];
        yield 'cte' => ['WITH a AS (SELECT 1) SELECT * FROM a'];
    }

    #[DataProvider('selects')]
    public function testAStatementOpeningWithSelectIsRecognised(string $sql): void
    {
        self::assertMatchesRegularExpression('/\A(SELECT|WITH)\b/i', self::strip($sql));
    }

    public function testAStatementHiddenBehindCommentsIsNotMistakenForASelect(): void
    {
        self::assertStringStartsWith('DELETE', self::strip("# SELECT\n-- SELECT\n/* SELECT */ DELETE FROM form_vl"));
    }
}
