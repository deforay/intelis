<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Services\InterfacingService;
use App\Utilities\AnalyzerRawText;
use PHPUnit\Framework\TestCase;

/**
 * The assay and reagent lot read from the analyzer message the Interface Tool sends
 * with every result. The messages are the Interface Tool's captures from production
 * analyzers (identifiers replaced), stored the ways its versions have stored them.
 */
final class AnalyzerRawTextTest extends TestCase
{
    /**
     * @param array<string, mixed> $read
     * @return array<string, mixed> the assay, lot and expiry only
     */
    private static function lotOf(array $read): array
    {
        return array_intersect_key($read, ['assay' => 1, 'lot' => 1, 'lotExpiry' => 1]);
    }

    private const M2000 = [
        'H|\^&|||m2000^8.1.9.0^275000001^H1P1O1R1C1L1|||||||P|1|20260112122248',
        'P|1',
        'O|1|VL01250129|VL01250129^HIV120126A^F7|^^^HIV0.6ml^HIV0.6ml|||||||||||||||||||||F',
        'R|1|^^^HIV0.6ml^HIV0.6ml^395139^10004558^^F|Not detected|Copies / mL||||F||admin^Administrator'
            . '||20260112122150|275000001',
        'R|2|^^^HIV0.6ml^HIV0.6ml^395139^10004558^^I|Target not detected|||||F||admin^Administrator'
            . '||20260112122150|275000001',
        'L|1',
    ];

    private const ALINITY = [
        'MSH|^~\&|||||20260112150335+0100||OUL^R22^OUL_R22|MSG-ALM-001|P|2.5.1|||NE|AL||UNICODE UTF-8|||LAB-29^IHE',
        'SPM|1|||PLAS^Plasma^HL70487|||||||P^Patient^HL70369',
        'SAC|||VL00000101|||||||A000001|7||||2^Position^99ABT',
        'OBR||""||1006^HIV-1^99ABT',
        'ORC|SC||||CM',
        'OBX|1|ST|1006^HIV-1^99ABT||< 20|^Copies/mL||""|||F|||||Pat~operator||Alinity m^Abbott~M00001^Abbott'
            . '|20260112142906||||||||||RSLT',
        'INV|1006^^99ABT|NA^Not applicable^HL70383|CA^Calibration^99ABT||||||||||20251202144858|||399444',
        'INV|HIV-1 CAL A^^99ABT|NA^Not applicable^HL70383|RC^Reagent Calibrator^HL70384|004568^^99ABT'
            . '||||||||20260131||||399079',
        'INV|3001-AMP_KIT_REAGENT^^99ABT|NA^Not applicable^HL70383|SR^Single Test Reagent^HL70384|04051^^99ABT'
            . '||||||||20260214||||399444',
        'INV|3002-AMP_KIT_REAGENT^^99ABT|NA^Not applicable^HL70383|SR^Single Test Reagent^HL70384|02460^^99ABT'
            . '||||||||20260301||||399444',
        'INV|LYSIS^^99ABT|NA^Not applicable^HL70383|LI^Measurable Liquid Item^HL70384|||||||||20251214||||990020082',
    ];

    private static function genexpert(string $resultTestId, string $orderTestId = '^^^HIV-1_VL 2 2'): array
    {
        return [
            'H|@^\|GXM-00000000001||EXAMPLE DISTRICT HOSPITAL - 800001^GeneXpert^6.2|||||Example Hospital - 8'
                . '||P|1394-97|20260307161412',
            'P|1|||VL0001|^^^^|||||||||||||||||||||||||||||',
            "O|1|VL0001||$orderTestId|R|20260129114918|||||||||ORH||||||||||F",
            "R|1|$resultTestId|^1234.56|copies/mL|40.00 to 10000000.00|A||F||EXAMPLE OPERAT1|20260129114918"
                . '|20260129115359|Cepheid-1F21001^806911^630912^1113243530^72203^20260825|',
            'L|1|N',
        ];
    }

    public function testAnM2000ResultGivesItsAssayAndAssayLot(): void
    {
        $read = self::lotOf(AnalyzerRawText::read(implode("\r", self::M2000), 'VL01250129'));

        self::assertSame(['assay' => 'HIV0.6ml', 'lot' => '395139', 'lotExpiry' => null], $read);
    }

    public function testAFailedM2000OrderHasNoResultRecordSoNoLot(): void
    {
        $failed = [self::M2000[0], 'P|1', 'O|1|VL1|VL1^RUN^A1|^^^HIV0.6ml^HIV0.6ml|||||||||||||||||||||X',
            'C|1|I|Sample clotted|I', 'L|1'];

        self::assertSame(
            ['assay' => 'HIV0.6ml', 'lot' => null, 'lotExpiry' => null],
            self::lotOf(AnalyzerRawText::read(implode("\r", $failed), 'VL1'))
        );
    }

    public function testOlderToolVersionsStoredTheFramingAndItIsReadThrough(): void
    {
        // As stored in 2024: frame numbers, <CR> markers, and checksums on lines of their own.
        $framed = '';
        foreach (self::M2000 as $i => $record) {
            $framed .= ($i % 8) . $record . "\r5B\r\n";
        }
        $marked = '';
        foreach (self::M2000 as $i => $record) {
            $marked .= '<STX>' . ($i % 8) . $record . '<CR><ETX>A3<CR><LF>';
        }

        self::assertSame('395139', AnalyzerRawText::read($framed, 'VL01250129')['lot']);
        self::assertSame('395139', AnalyzerRawText::read($marked, 'VL01250129')['lot']);
    }

    public function testARecordSplitAcrossFramesIsJoinedBeforeItIsRead(): void
    {
        // GeneXpert fills frames to 240 bytes and cuts wherever that falls, here
        // just before the cartridge; older tool versions stored the frames as sent.
        $records = self::genexpert('^^^HIV-1_VL 2 2^Xpert_HIV-1 Viral Load^2^^');
        [$head, $tail] = explode('Cepheid-', $records[3], 2);
        $frames = ['1' . $records[0], '2' . $records[1], '3' . $records[2]];

        $marked = '';
        foreach ($frames as $frame) {
            $marked .= "<STX>$frame<CR><ETX>4F<CR><LF>";
        }
        $marked .= "<STX>4$head<ETB>A1<CR><LF><STX>5Cepheid-$tail<CR><ETX>3C<CR><LF><STX>6L|1|N<CR><ETX>0A<CR><LF>";
        $bytes = str_replace(
            ['<STX>', '<ETX>', '<ETB>', '<CR>', '<LF>'],
            ["\x02", "\x03", "\x17", "\r", "\n"],
            $marked
        );

        $expected = ['assay' => 'Xpert_HIV-1 Viral Load', 'lot' => '72203', 'lotExpiry' => '2026-08-25'];
        self::assertSame($expected, self::lotOf(AnalyzerRawText::read($marked, 'VL0001')));
        self::assertSame($expected, self::lotOf(AnalyzerRawText::read($bytes, 'VL0001')));
    }

    public function testAnOrderStoredTwiceStillFindsItsResult(): void
    {
        $twice = self::M2000;
        array_splice($twice, 3, 0, [self::M2000[2]]);

        self::assertSame('395139', AnalyzerRawText::read(implode("\r", $twice), 'VL01250129')['lot']);
    }

    public function testInABatchOnlyTheSamplesOwnRecordsAreRead(): void
    {
        $other = [
            'O|2|VL0002|VL0002^RUN^A2|^^^HIV1.0mlDBS^HIV1.0mlDBS|||||||||||||||||||||F',
            'R|1|^^^HIV1.0mlDBS^HIV1.0mlDBS^400001^10009999^^F|1250|Copies / mL||||F||admin||20260112122150|275000001',
        ];
        $batch = implode("\r", [...array_slice(self::M2000, 0, 5), ...$other, 'L|1']);

        self::assertSame(
            ['assay' => 'HIV1.0mlDBS', 'lot' => '400001', 'lotExpiry' => null],
            self::lotOf(AnalyzerRawText::read($batch, 'VL0002'))
        );
        self::assertSame('395139', AnalyzerRawText::read($batch, 'VL01250129')['lot']);
        self::assertSame(
            ['assay' => null, 'lot' => null, 'lotExpiry' => null],
            self::lotOf(AnalyzerRawText::read($batch, 'VL9999')),
            'a sample not in the batch takes nothing from it'
        );
        self::assertSame(
            '400001',
            AnalyzerRawText::read($batch, '', 'VL0002')['lot'],
            'a sample matched by its test ID, with no order ID'
        );
        self::assertSame(
            '400001',
            InterfacingService::runDetails(
                ['order_id' => '', 'test_id' => 'VL0002', 'raw_text' => $batch]
            )['lot_number']
        );
    }

    public function testTaqmanNamesTheAssayOnlyInTheResultRecord(): void
    {
        $taqman = [
            'H|\^&|||ALTM0000001^Roche^AMPLILINK^3.3.7.1201^Roche ASTM+^TM0000001^192.0.2.10||||||||1|20260314090241',
            'P|1',
            'O|1|TM-0001/26|TM-0001/26|^^^ALL||20260310125523|||||A',
            'R|1|^^^HI2CAP96|Target Not Detected||20^10000000^TiterRanges|N||V||VL-LAB|20260310175015'
                . '|20260310205415|Cobas TaqMan',
            'C|1||Accepted|G',
            'L|1|N',
        ];

        self::assertSame(
            ['assay' => 'HI2CAP96', 'lot' => null, 'lotExpiry' => null],
            self::lotOf(AnalyzerRawText::read(implode("\r", $taqman), 'TM-0001/26'))
        );
    }

    public function testGeneXpertGivesTheAssayNameAndTheCartridgeLot(): void
    {
        $viralLoad = self::genexpert('^^^HIV-1_VL 2 2^Xpert_HIV-1 Viral Load^2^^');
        $qualitative = self::genexpert('^HIV-1_QUAL 2^^^Xpert_HIV-1 Qual^2^HIV-1^', '^^^HIV-1_QUAL 2');

        self::assertSame(
            ['assay' => 'Xpert_HIV-1 Viral Load', 'lot' => '72203', 'lotExpiry' => '2026-08-25'],
            self::lotOf(AnalyzerRawText::read(implode("\r", $viralLoad), 'VL0001'))
        );
        self::assertSame('Xpert_HIV-1 Qual', AnalyzerRawText::read(implode("\r", $qualitative), 'VL0001')['assay']);
    }

    public function testAlinityGivesTheKitLotOfTheAssayAndItsEarliestExpiry(): void
    {
        self::assertSame(
            ['assay' => 'HIV-1', 'lot' => '399444', 'lotExpiry' => '2026-02-14'],
            self::lotOf(AnalyzerRawText::read(implode("\r", self::ALINITY), 'VL00000101'))
        );
    }

    public function testAlinityWithoutTheAssaysInventoryHasNoLot(): void
    {
        $withoutCalibration = array_values(array_filter(
            self::ALINITY,
            static fn(string $segment): bool => !str_starts_with($segment, 'INV|1006^')
        ));

        self::assertNull(AnalyzerRawText::read(implode("\r", $withoutCalibration), 'VL00000101')['lot']);
    }

    public function testCobasMessagesGiveTheAssayAndNoLot(): void
    {
        $cobas5800 = [
            'MSH|^~\&|X800 DM||HOST||20260407131145+0100||OUL^R22^OUL_R22|MSG-5800-04|P|2.5.1|||NE|AL||UNICODE UTF-8',
            'SPM|1|VL00000427&ROCHE||PLAS^plasma^HL70487|||||||P^^HL70369',
            'SAC|||VL00000427|||||||S08|6',
            'OBR||||70241-5^HIV^LN',
            'ORC|SC||||CM',
            'OBX|1|NM|HIV^HIV^99ROC|1|367|10*-1.{copies}/mL^^UCUM||VAL^^99ROC|||F|||||labuser1'
                . '||c5800^Roche~c5800.2709^Roche|20260404180624||5-2709-20260404-1526||||||||RSLT',
        ];
        $cobas4800 = [
            'MSH|^~\&|cobas 4800||LIS||20260612194324||OUL^R22|MSG-4800-01|P|2.5',
            'SPM|1|VL00000501&ROCHE||""|||||||Q^^HL70369',
            'SAC|||VL00000501',
            'INV|NEG-CTRL-01^^99ROC|OK^^HL70383|CO^^HL70384',
            'OBR||""||0BHIV1^0BHIV1^99ROC||20260612162143',
            'OBX|1|DR|RunTimeRange^Run Execution Time Range^99ROC^S_OTHER^Other_Supplemental^IHELAW|1.0'
                . '|20260612163740^20260612194324',
            'OBX|2|ST|0BHIV1^0BHIV1^99ROC|1.1|3.26E+05 cp/mL|1/mL^^UCUM||Full^^99ROC|||F',
        ];

        self::assertSame(
            ['assay' => 'HIV', 'lot' => null, 'lotExpiry' => null],
            self::lotOf(AnalyzerRawText::read(implode("\r", $cobas5800), 'VL00000427'))
        );
        self::assertSame(
            ['assay' => '0BHIV1', 'lot' => null, 'lotExpiry' => null],
            self::lotOf(AnalyzerRawText::read(implode("\r", $cobas4800), 'VL00000501'))
        );
    }

    public function testNothingToReadGivesNothing(): void
    {
        $none = ['assay' => null, 'lot' => null, 'lotExpiry' => null];

        self::assertSame($none, self::lotOf(AnalyzerRawText::read(null, 'VL1')));
        self::assertSame($none, self::lotOf(AnalyzerRawText::read('', 'VL1')));
        self::assertSame($none, self::lotOf(AnalyzerRawText::read('not an analyzer message', 'VL1')));
    }

    public function testTheMessageIsReadBeforeTestTypeWhichIsTheFallback(): void
    {
        $taqman = "O|1|TM1|TM1|^^^ALL\rR|1|^^^HI2CAP96|Target Not Detected";
        $assayAndLot = static fn(array $details): array => array_intersect_key(
            $details,
            ['assay_name' => 1, 'lot_number' => 1, 'lot_expiration_date' => 1]
        );

        self::assertSame(
            ['assay_name' => 'HI2CAP96'],
            $assayAndLot(InterfacingService::runDetails(
                ['order_id' => 'TM1', 'test_type' => 'ALL', 'raw_text' => $taqman]
            ))
        );
        self::assertSame(
            ['assay_name' => 'HIV-1'],
            $assayAndLot(InterfacingService::runDetails(
                ['order_id' => 'VL1', 'test_type' => 'HIV-1', 'raw_text' => null]
            )),
            'results sent before raw text was kept'
        );
        self::assertSame(
            ['assay_name' => 'HIV0.6ml', 'lot_number' => '395139', 'lot_expiration_date' => null],
            $assayAndLot(InterfacingService::runDetails(
                ['order_id' => 'VL01250129', 'raw_text' => implode("\r", self::M2000)]
            )),
            'the results API sends no test_type'
        );
    }

    public function testEveryRunColumnIsSetSoANewRunKeepsNothingOfAnEarlierOne(): void
    {
        $details = InterfacingService::runDetails(
            ['order_id' => 'VL01250129', 'raw_text' => implode("\r", self::M2000)]
        );

        self::assertSame('m2000', $details['instrument_model']);
        self::assertSame('275000001', $details['instrument_serial']);
        self::assertSame('HIV120126A', $details['analyzer_run_id']);
        self::assertNull($details['analyzer_message'], 'set, to nothing');
        self::assertSame(
            [['name' => 'Interpretation', 'value' => 'Target not detected', 'unit' => null]],
            json_decode((string) $details['analyzer_readings'], true)
        );
        self::assertSame(
            ['assay_name', 'instrument_model', 'instrument_serial', 'analyzer_run_id', 'analyzer_message',
                'analyzer_readings'],
            array_keys(InterfacingService::runDetails(['order_id' => 'VL1', 'raw_text' => null, 'test_type' => ''])),
            'nothing read: every column still set, so values of an earlier run are cleared'
        );
    }

    /**
     * @param array<string, mixed> $read
     * @return array<string, mixed> what was read about the run
     */
    private static function runOf(array $read): array
    {
        return array_intersect_key($read, ['model' => 1, 'serial' => 1, 'runId' => 1, 'message' => 1]);
    }

    public function testAnM2000RunGivesItsSerialRunAndReadings(): void
    {
        $read = AnalyzerRawText::read(implode("\r", self::M2000), 'VL01250129');

        self::assertSame(
            ['model' => 'm2000', 'serial' => '275000001', 'runId' => 'HIV120126A', 'message' => null],
            self::runOf($read)
        );
        self::assertSame(
            [['name' => 'Interpretation', 'value' => 'Target not detected', 'unit' => null]],
            $read['readings']
        );
    }

    public function testAnM2000FailureGivesTheAnalyzersMessage(): void
    {
        $failed = [self::M2000[0], 'P|1', 'O|1|VL1|VL1^RUN7^A1|^^^HIV0.6ml^HIV0.6ml|||||||||||||||||||||X',
            'C|1|I|4442 : Internal control cycle number is too high. Valid range is [19.73, 23.73].|I', 'L|1'];

        $read = AnalyzerRawText::read(implode("\r", $failed), 'VL1');

        self::assertSame(
            '4442 : Internal control cycle number is too high. Valid range is [19.73, 23.73].',
            $read['message']
        );
        self::assertSame('RUN7', $read['runId']);
    }

    public function testAGeneXpertRunGivesItsSystemCartridgeErrorAndCtValues(): void
    {
        $records = self::genexpert('^^^HIV-1_VL 2 2^Xpert_HIV-1 Viral Load^2^^');
        array_splice($records, 4, 0, [
            'C|1|I|Error^2014^Operation terminated^Thermistor B out of range^20260129115359|N',
            'R|2|^^^HIV-1_VL 2 2^^^HIV-1^Ct|^26.4|||',
            'R|3|^^^HIV-1_VL 2 2^^^IQS-H^|PASS^|||',
        ]);

        $read = AnalyzerRawText::read(implode("\r", $records), 'VL0001');

        self::assertSame([
            'model' => 'GeneXpert', 'serial' => 'Cepheid-1F21001', 'runId' => '1113243530',
            'message' => 'Error 2014 Operation terminated Thermistor B out of range',
        ], self::runOf($read));
        self::assertSame([
            ['name' => 'HIV-1 Ct', 'value' => '26.4', 'unit' => null],
            ['name' => 'IQS-H', 'value' => 'PASS', 'unit' => null],
        ], $read['readings']);
    }

    public function testATaqmanRunNamesItsInstrumentAndKeepsItsFlags(): void
    {
        $taqman = [
            'H|\^&|||ALTM0000001^Roche^AMPLILINK^3.3.7.1201^Roche ASTM+^TM0000001^192.0.2.10||||||||1|20260314090241',
            'O|1|TM-1|TM-1|^^^ALL||20260310125523|||||A',
            'R|1|^^^HI2CAP96|Target Not Detected||20^10000000^TiterRanges|N||V||VL-LAB|20260310175015'
                . '|20260310205415|Cobas TaqMan',
            'C|1||Accepted|G',
            'C|2|I|TM40^ STEP_CORR-2|I',
        ];

        self::assertSame(
            ['model' => 'Cobas TaqMan', 'serial' => 'TM0000001', 'runId' => null, 'message' => 'TM40 STEP_CORR-2'],
            self::runOf(AnalyzerRawText::read(implode("\r", $taqman), 'TM-1'))
        );
    }

    public function testAnAlinityRunGivesItsEquipmentAndReadings(): void
    {
        $segments = self::ALINITY;
        $segments[] = 'OBX|2|NM|1006.C^HIV-1^99ABT^S_OTHER^Other Supplemental^IHELAW|IC|15.70|^CN||""|||F';
        $segments[] = 'OBX|3|EI|1006.G^HIV-1^99ABT^S_OTHER^Other Supplemental^IHELAW||11263ee8|||""|||F';

        $read = AnalyzerRawText::read(implode("\r", $segments), 'VL00000101');

        self::assertSame(
            ['model' => 'Alinity m', 'serial' => 'M00001', 'runId' => null, 'message' => null],
            self::runOf($read)
        );
        self::assertSame([['name' => '1006.C IC', 'value' => '15.70', 'unit' => 'CN']], $read['readings'], 'no ids');
    }

    public function testACobasFailureGivesItsCodesAndRun(): void
    {
        $flag = 'U06T^Pipetting anomaly detected during sample aspiration.^99ROC';
        $cobas5800 = [
            'MSH|^~\&|X800 DM||HOST||20260407131145+0100||OUL^R22^OUL_R22|MSG-5800-01|P|2.5.1',
            'SPM|1|VL00000407&ROCHE||PLAS^plasma^HL70487|||||||P^^HL70369',
            'SAC|||VL00000407|||||||S07|4',
            'OBR||||70241-5^HIV^LN',
            "OBX|1||HIV^HIV^99ROC|1||||$flag~$flag|||X|||||||c5800^Roche~c5800.2709^Roche|20260404154651"
                . '||5-2709-20260404-1526||||||||RSLT',
        ];
        $cobas6800Equipment = 'OBX|1|NM|HIV^HIV^99ROC||367|10*-1.{Copies}/mL^^UCUM|||||F|||||op1'
            . '||C6800/8800^Roche^^~Unknown^Roche^^~ID_000000000000000001^IM300-000001^^|20260308121433';

        self::assertSame([
            'model' => 'c5800', 'serial' => 'c5800.2709', 'runId' => '5-2709-20260404-1526',
            'message' => 'U06T Pipetting anomaly detected during sample aspiration.',
        ], self::runOf(AnalyzerRawText::read(implode("\r", $cobas5800), 'VL00000407')));
        self::assertSame(
            ['model' => 'C6800/8800', 'serial' => 'ID_000000000000000001', 'runId' => null, 'message' => null],
            self::runOf(AnalyzerRawText::read(
                implode("\r", [$cobas5800[0], 'SPM||VL1||PLAS', 'OBR|1|||70241-5^HIV^LN', $cobas6800Equipment]),
                'VL1'
            )),
            'a serial of Unknown is skipped'
        );
    }
}
