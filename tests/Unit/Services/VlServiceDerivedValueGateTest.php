<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\VlServiceFactory;

/**
 * result_value_log, result_value_absolute and result_value_absolute_decimal describe one
 * measurement three ways, so they can be checked against each other and against the range
 * an assay can report. They are not derived in one place -- fourteen of the twenty VL
 * instrument parsers do their own arithmetic and never pass through interpretation -- so
 * the check is applied wherever these columns are written, whatever produced them.
 *
 * The cases below are the shapes actually found in a client database: an absolute of
 * "< 1.0E+40" from an unbounded exponentiation, "INF" from one that overflowed, "#NUM!"
 * from a spreadsheet formula that errored, a Ct value sitting in the copies column, and a
 * negative sentinel left in place of a real figure.
 */
final class VlServiceDerivedValueGateTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: mixed, 2: mixed, 3: string[]}>
     */
    public static function implausibleValuesProvider(): array
    {
        $log = 'result_value_log';
        $absolute = 'result_value_absolute';
        $decimal = 'result_value_absolute_decimal';

        return [
            'exponentiated absolute'   => [40, '< 1.0E+40', '1e40', [$decimal, $absolute, $log]],
            'overflowed to INF'        => [null, 'INF', 'INF', [$decimal, $absolute]],
            'spreadsheet error in log' => ['#NUM!', null, null, [$log]],
            'negative sentinel'        => [null, '-1', '-1', [$decimal, $absolute]],
            'log beyond any assay'     => ['42', null, null, [$log]],
        ];
    }

    /**
     * @param string[] $expectedDropped
     */
    #[DataProvider('implausibleValuesProvider')]
    public function testImplausibleValuesAreDropped(
        mixed $log,
        mixed $absolute,
        mixed $decimal,
        array $expectedDropped
    ): void {
        $sanitized = VlServiceFactory::build()->sanitizeDerivedVlValues($log, $absolute, $decimal);

        sort($expectedDropped);
        $actual = $sanitized['dropped'];
        sort($actual);

        $this->assertSame($expectedDropped, $actual);
    }

    /**
     * A log and a copies figure that describe different measurements cannot both stand.
     * The log goes, because it is recomputable from the copies figure and the copies
     * figure is what the assay reports. This is the "Ct value in the absolute column"
     * case: result 940, log 2.97, absolute 23.17.
     */
    public function testALogThatDisagreesWithTheCopiesFigureIsDropped(): void
    {
        $sanitized = VlServiceFactory::build()->sanitizeDerivedVlValues('2.97', '23.17', '23.17');

        $this->assertSame(['result_value_log'], $sanitized['dropped']);
        $this->assertNull($sanitized['logVal']);
        $this->assertEqualsWithDelta(23.17, $sanitized['absDecimalVal'], 0.01);
    }

    /**
     * @return array<string, array{0: mixed, 1: mixed, 2: mixed}>
     */
    public static function soundValuesProvider(): array
    {
        return [
            'agreeing set'            => ['3.59', '3892', '3892'],
            'below detection'         => ['1.6', '< 40', '40'],
            'rounding within tolerance' => ['4.19', '15519', '15519'],
            'zero log is one copy'    => ['0', '1', '1'],
            'nothing recorded'        => [null, null, null],
        ];
    }

    #[DataProvider('soundValuesProvider')]
    public function testSoundValuesAreLeftAlone(mixed $log, mixed $absolute, mixed $decimal): void
    {
        $sanitized = VlServiceFactory::build()->sanitizeDerivedVlValues($log, $absolute, $decimal);

        $this->assertSame([], $sanitized['dropped']);
    }

    /**
     * The gate as the write paths use it. `result` is what the lab reported, and no
     * amount of disagreement among the derived columns makes it this code's to change.
     */
    public function testTheReportedResultIsNeverTouched(): void
    {
        $row = [
            'sample_code' => 'VL042036135',
            'result' => '< 40',
            'result_value_text' => '< 40',
            'result_value_log' => '40',
            'result_value_absolute' => '< 1.0E+40',
            'result_value_absolute_decimal' => '1e40',
        ];

        $sanitized = VlServiceFactory::build()->sanitizeResultColumnsForWrite($row, 'test');

        $this->assertSame('< 40', $sanitized['result']);
        $this->assertSame('< 40', $sanitized['result_value_text']);
        $this->assertSame('VL042036135', $sanitized['sample_code']);
        $this->assertNull($sanitized['result_value_log']);
        $this->assertNull($sanitized['result_value_absolute']);
        $this->assertNull($sanitized['result_value_absolute_decimal']);
    }

    /**
     * A partial update must not be turned into a full one: a column the caller did not
     * set stays unset, rather than being written as null.
     */
    public function testColumnsAbsentFromTheRowStayAbsent(): void
    {
        $row = ['result_value_log' => '42'];

        $sanitized = VlServiceFactory::build()->sanitizeResultColumnsForWrite($row, 'test');

        $this->assertArrayNotHasKey('result_value_absolute', $sanitized);
        $this->assertArrayNotHasKey('result_value_absolute_decimal', $sanitized);
        $this->assertNull($sanitized['result_value_log']);
    }

    /**
     * The exponent is part of the number. Reading "1.0" out of "< 1.0E+20" discarded the
     * magnitude and produced one copy per millilitre -- a figure that looks like a
     * deeply suppressed patient and passes every check downstream, which is a far worse
     * outcome than the obviously broken value it replaced. Parsed whole, it is over the
     * ceiling and is dropped instead.
     */
    public function testAResultInScientificNotationIsNotTruncatedToItsMantissa(): void
    {
        $interpreted = VlServiceFactory::build()->interpretViralLoadNumericResult('< 1.0E+20');

        $this->assertNotEquals(1.0, (float) $interpreted['absDecimalVal']);
        $this->assertNull($interpreted['absDecimalVal']);
        $this->assertNull($interpreted['logVal']);
    }

    /**
     * A magnitude an assay can actually report still converts.
     */
    public function testAScientificNotationResultWithinRangeIsKept(): void
    {
        $interpreted = VlServiceFactory::build()->interpretViralLoadNumericResult('1.0E+6');

        $this->assertEqualsWithDelta(1000000.0, (float) $interpreted['absDecimalVal'], 1.0);
        $this->assertEqualsWithDelta(6.0, (float) $interpreted['logVal'], 0.01);
    }

    public function testARowWithNoDerivedColumnsIsReturnedUnchanged(): void
    {
        $row = ['result' => 'Target Not Detected', 'result_status' => 4];

        $this->assertSame($row, VlServiceFactory::build()->sanitizeResultColumnsForWrite($row, 'test'));
    }
}
