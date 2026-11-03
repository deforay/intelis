<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\SpreadsheetUtility;
use DateTimeImmutable;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class SpreadsheetUtilityTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/intelis-sheet-' . bin2hex(random_bytes(4)) . '.xlsx';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    /** @return array<string, array{0:int, 1:string}> */
    public static function columns(): array
    {
        return [
            'first' => [1, 'A'],
            'last single letter' => [26, 'Z'],
            'first double letter' => [27, 'AA'],
            'end of A block' => [52, 'AZ'],
            'start of B block' => [53, 'BA'],
        ];
    }

    #[DataProvider('columns')]
    public function testColumnLetter(int $column, string $expected): void
    {
        $this->assertSame($expected, SpreadsheetUtility::columnLetter($column));
    }

    public function testReadsRowsKeyedByColumnLetter(): void
    {
        $this->write([
            Row::fromValues(['Sample Code', 'Collected', 'Viral Load']),
            new Row([
                Cell::fromValue(12345),
                Cell::fromValue(
                    new DateTimeImmutable('2026-10-05 14:30:00'),
                    (new Style())->withFormat('yyyy-mm-dd hh:mm:ss')
                ),
                Cell::fromValue(12.5),
            ]),
            Row::fromValues(['', '', '']),
            Row::fromValues(['S-2']),
        ]);

        $rows = SpreadsheetUtility::readRowsByColumnLetter($this->path);

        $this->assertSame([
            ['A' => '12345', 'B' => '2026-10-05 14:30:00', 'C' => '12.5'],
            ['A' => 'S-2', 'B' => null, 'C' => null],
        ], $rows);
    }

    public function testCellsReachPastZ(): void
    {
        $values = array_map(static fn(int $i): string => "v$i", range(1, 53));
        $this->write([Row::fromValues($values), Row::fromValues($values)]);

        $row = SpreadsheetUtility::readRowsByColumnLetter($this->path)[0];

        $this->assertSame('v26', $row['Z']);
        $this->assertSame('v27', $row['AA']);
        $this->assertSame('v52', $row['AZ']);
        $this->assertSame('v53', $row['BA']);
    }

    public function testFormulaCellsReadAsTheirValue(): void
    {
        $this->write([
            Row::fromValues(['Sample Code', 'Patient']),
            new Row([Cell::fromValue('S-1'), new FormulaCell('=UPPER("ana")', null)]),
        ]);
        // The OpenSpout writer saves only the formula; add the value the way Excel does.
        $zip = new ZipArchive();
        $zip->open($this->path);
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $xml = preg_replace('#(<c r="B2")#', '$1 t="str"', $xml);
        $xml = preg_replace('#</f>#', '</f><v>ANA</v>', $xml);
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();

        $rows = SpreadsheetUtility::readRowsByColumnLetter($this->path);

        $this->assertSame([['A' => 'S-1', 'B' => 'ANA']], $rows);
    }

    public function testRejectsAnythingButXlsx(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Save the file as .xlsx and upload it again.');
        SpreadsheetUtility::readRowsByColumnLetter(sys_get_temp_dir() . '/requests.xls');
    }

    /** @param list<Row> $rows */
    private function write(array $rows): void
    {
        $writer = new Writer();
        $writer->openToFile($this->path);
        $writer->addRows($rows);
        $writer->close();
    }
}
