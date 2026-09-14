<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\DataTableUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DataTablePagingTest extends TestCase
{
    public static function pagingProvider(): array
    {
        return [
            'a normal page' => [['iDisplayStart' => '20', 'iDisplayLength' => '10'], [20, 10]],
            'every row' => [['iDisplayStart' => '0', 'iDisplayLength' => '-1'], [null, null]],
            'no paging sent' => [[], [null, null]],
            'length missing' => [['iDisplayStart' => '0'], [null, null]],
            'SQL in the offset is cast away' => [
                ['iDisplayStart' => '0; DROP TABLE form_vl', 'iDisplayLength' => '10'],
                [0, 10],
            ],
            'SQL in the length is cast away' => [
                ['iDisplayStart' => '0', 'iDisplayLength' => '10 UNION SELECT 1'],
                [0, 10],
            ],
            'negative offset becomes zero' => [['iDisplayStart' => '-5', 'iDisplayLength' => '10'], [0, 10]],
        ];
    }

    #[DataProvider('pagingProvider')]
    public function testPaging(array $request, array $expected): void
    {
        $this->assertSame($expected, DataTableUtility::paging($request));
    }
}
