<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\SampleExportUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use const COUNTRY\DRC;
use const COUNTRY\CAMEROON;

final class SampleExportUtilityTest extends TestCase
{
    public static function storageProvider(): array
    {
        $slot = ['rack' => 'R2', 'box' => 'B7', 'position' => '14', 'volume' => '1.5'];
        return [
            'current saves: an object with storageCode' => [
                json_encode(['storage' => ['storageId' => 'x', 'storageCode' => 'FRZ-1'] + $slot]),
            ],
            'older saves: a JSON string with freezerCode' => [
                json_encode(['storage' => json_encode(['freezer' => null, 'freezerCode' => 'FRZ-1'] + $slot)]),
            ],
        ];
    }

    #[DataProvider('storageProvider')]
    public function testStorageSlotIsReadFromEitherSavedShape(string $formAttributes): void
    {
        $row = SampleExportUtility::prepareShared(['form_attributes' => $formAttributes], 'reason_for_result_changes');
        $slot = ['storage_freezer', 'storage_rack', 'storage_box', 'storage_position', 'storage_volume'];

        $this->assertSame(
            ['FRZ-1', 'R2', 'B7', '14', '1.5'],
            array_map(static fn(string $field) => $row[$field], $slot)
        );
    }

    public function testNoStorageLeavesTheSlotBlank(): void
    {
        foreach ([null, '', '{"ip_address":"1.2.3.4"}', '{"storage":null}'] as $formAttributes) {
            $row = SampleExportUtility::prepareShared(
                ['form_attributes' => $formAttributes],
                'reason_for_result_changes'
            );
            $this->assertSame('', $row['storage_freezer']);
            $this->assertSame('', $row['storage_volume']);
        }
    }

    public function testColumnsFollowTheirFormAndPatientRules(): void
    {
        $columns = [
            SampleExportUtility::text('Everyone', 'a'),
            SampleExportUtility::text('DRC only', 'b', ['forms' => [DRC]]),
            SampleExportUtility::text('Not DRC', 'c', ['exceptForms' => [DRC]]),
            SampleExportUtility::text('Patient', 'd', ['pii' => true]),
            SampleExportUtility::text('STS', 'e', ['sts' => true]),
        ];
        $headings = static fn(array $cols): array => array_column($cols, 'heading');

        $this->assertSame(
            ['Everyone', 'DRC only', 'Patient', 'STS'],
            $headings(SampleExportUtility::forForm($columns, DRC, true, false))
        );
        $this->assertSame(
            ['Everyone', 'Not DRC'],
            $headings(SampleExportUtility::forForm($columns, CAMEROON, false, true))
        );
    }

    public function testAnalyzerReadingsAreWrittenAsOneLine(): void
    {
        $columns = array_column(SampleExportUtility::analyzerColumns(), 'value', 'heading');
        $readings = $columns['Analyzer Readings'];
        $row = ['analyzer_readings' => json_encode([
            ['name' => 'HIV-1 Ct', 'value' => '26.4', 'unit' => null],
            ['name' => '1006.C IC', 'value' => '15.70', 'unit' => 'CN'],
        ])];

        $this->assertSame('HIV-1 Ct: 26.4; 1006.C IC: 15.70 CN', $readings($row));
        $this->assertNull($readings(['analyzer_readings' => null]));
        $this->assertNull($readings([]), 'an export from before the column existed');
    }
}
