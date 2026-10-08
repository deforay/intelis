<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\InterfacingService;
use App\Services\LabPerformanceIndicatorsService;
use App\Services\TestResultImportService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Small pieces of recording the assay and counting tests by instrument (5.7.82).
 */
final class InstrumentAssayTest extends TestCase
{
    public function testAnAnalyzerResentWithTheSameNumbersHoldsTheSameValues(): void
    {
        // Built from the analyzer, typed; read back from the row, text.
        $built = ['result' => '1250', 'result_value_log' => 3.1, 'result_value_absolute_decimal' => 1250.0];
        $stored = ['result' => '1250', 'result_value_log' => '3.1', 'result_value_absolute_decimal' => '1250'];

        self::assertTrue(InterfacingService::holdsSameValues($built, $stored));
    }

    public function testADifferentValueIsAChange(): void
    {
        self::assertFalse(
            InterfacingService::holdsSameValues(['result_value_log' => 3.1], ['result_value_log' => '3.2'])
        );
        self::assertFalse(InterfacingService::holdsSameValues(['result' => '40'], ['result' => '400']));
        self::assertFalse(InterfacingService::holdsSameValues(['result' => '40'], []), 'a column the row lacks');
    }

    public function testEmptyIsNotTheSameAsNothing(): void
    {
        self::assertFalse(InterfacingService::holdsSameValues(['result' => null], ['result' => '']));
        self::assertFalse(InterfacingService::holdsSameValues(['result' => '0'], ['result' => null]));
        self::assertTrue(InterfacingService::holdsSameValues(['result' => null], ['result' => null]));
    }

    public function testIgnoredKeysAreNotCompared(): void
    {
        self::assertTrue(InterfacingService::holdsSameValues(
            ['result' => '40', 'assay_name' => 'HIV1.0mlDBS'],
            ['result' => '40', 'assay_name' => null],
            ['assay_name']
        ));
    }

    /** @return iterable<string, array{string, string}> */
    public static function reportedTestIds(): iterable
    {
        // As stored by the Interface Tool on lab installs.
        yield 'Abbott m2000 ASTM, with lots and status' => ['HIV0.6ml^HIV0.6ml^392592^10004378^^F', 'HIV0.6ml'];
        yield 'Abbott m2000 ASTM, another lot' => ['HIV0.6ml^HIV0.6ml^395139^10004531^398226^F', 'HIV0.6ml'];
        yield 'Abbott DBS' => ['HIV1mlDBS^HIV1.0mlDBS^512926^10002287^^F', 'HIV1.0mlDBS'];
        yield 'GeneXpert HL7' => ['^SSD-HRL-QUAL^^SSD-HRL-HIV1^Xpert_HIV-1 Qual^2^HIV-1^', 'Xpert_HIV-1 Qual'];
        yield 'plain' => ['HIV-1', 'HIV-1'];
        yield 'code' => ['0BHIV1', '0BHIV1'];
        yield 'digits only' => ['12345', '12345'];
    }

    #[DataProvider('reportedTestIds')]
    public function testTheAssayIsTakenFromTheReportedTestIdentifier(string $testType, string $assay): void
    {
        self::assertSame($assay, InterfacingService::assayName(['test_type' => $testType]));
    }

    public function testTheAssayIsTrimmedAndBlankMeansNone(): void
    {
        self::assertSame(
            'Xpert HIV-1 Viral Load',
            InterfacingService::assayName(['test_type' => ' Xpert HIV-1 Viral Load '])
        );
        self::assertNull(InterfacingService::assayName(['test_type' => '   ']));
        self::assertNull(InterfacingService::assayName([]));
        self::assertSame(255, mb_strlen((string) InterfacingService::assayName(['test_type' => str_repeat('é', 300)])));

        self::assertSame('HIV1.0mlDBS', TestResultImportService::assayFromFile(" HIV1.0mlDBS\t"));
        self::assertNull(TestResultImportService::assayFromFile(null));
        self::assertNull(TestResultImportService::assayFromFile(''));
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function instruments(): iterable
    {
        yield 'file format wins over a misleading name' => ['abbott-ssudan.php', 'Lab machine 2', 'Abbott'];
        yield 'Alinity' => ['abbott-alinity-m.php', 'Alinity', 'Abbott'];
        yield 'Roche file' => ['roche-rwanda.php', 'Cobas 6800 NRL', 'Roche'];
        yield 'Roche by name only' => ['', 'COBAS 4800', 'Roche'];
        yield 'GeneXpert' => ['genexpert.php', 'GX-16', 'GeneXpert'];
        yield 'GeneXpert by name only' => ['', 'GeneXpert.', 'GeneXpert'];
        yield 'Hologic' => ['hologic-panther.php', 'Panther', 'Hologic'];
        yield 'Rotor Gene written with a space' => ['', 'Rotor Gene', 'Qiagen'];
        yield 'unknown' => ['', 'NPHL HRL ABSP 01', 'Other'];
        yield 'nothing recorded' => ['', '', 'Not recorded'];
    }

    public function testTheInstrumentLabelAddsTheMakeOnlyWhenTheNameLacksIt(): void
    {
        self::assertSame('GeneXpert', LabPerformanceIndicatorsService::instrumentLabel('GeneXpert', 'GeneXpert'));
        self::assertSame(
            'Abbott m2000 1',
            LabPerformanceIndicatorsService::instrumentLabel('Abbott m2000 1', 'Abbott')
        );
        self::assertSame('BioRad PCR', LabPerformanceIndicatorsService::instrumentLabel('BioRad PCR', 'Bio-Rad'));
        self::assertSame(
            'Cobas 6800 NRL (Roche)',
            LabPerformanceIndicatorsService::instrumentLabel('Cobas 6800 NRL', 'Roche')
        );
        self::assertSame('Lab PCR', LabPerformanceIndicatorsService::instrumentLabel('Lab PCR', 'Other'));
        self::assertSame('', LabPerformanceIndicatorsService::instrumentLabel('', 'Not recorded'));
    }

    #[DataProvider('instruments')]
    public function testTheInstrumentTypeComesFromItsFileFormatThenItsName(
        string $file,
        string $name,
        string $expected
    ): void {
        self::assertSame($expected, LabPerformanceIndicatorsService::instrumentType($file, $name));
    }
}
