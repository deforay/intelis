<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Tests\Support\VlServiceFactory;

/**
 * vl_interpret_and_convert_results decides what `result` holds.
 *
 * Off -- the default -- the result is stored exactly as the instrument reported it or
 * the operator typed it, and interpretation only ever populates the columns beside it.
 * On, the interpreted value replaces the entry, which makes our reading of the result
 * the stored record rather than a convenience alongside it.
 *
 * That raises the bar for accepting an interpretation. When the plausibility check has
 * just rejected part of one as impossible, the rest of it is not trustworthy enough to
 * overwrite what the lab reported, so the reported value is kept. An entry that reads
 * oddly is a question someone can answer; a fabricated figure that reads plausibly is
 * not.
 */
final class VlServiceInterpretAndConvertFlagTest extends TestCase
{
    private const FLAG = 'vl_interpret_and_convert_results';

    public function testWithTheFlagOffTheReportedResultIsStoredVerbatim(): void
    {
        $vlService = VlServiceFactory::build([self::FLAG => 'no']);

        $interpreted = $vlService->interpretViralLoadResult('tnd');

        $this->assertSame('tnd', $interpreted['result']);
        // The interpretation still happens, it just does not claim the result column.
        $this->assertSame('Target Not Detected', $interpreted['processedResult']);
    }

    public function testWithTheFlagOnASoundInterpretationReplacesTheEntry(): void
    {
        $vlService = VlServiceFactory::build([self::FLAG => 'yes']);

        $interpreted = $vlService->interpretViralLoadResult('tnd');

        $this->assertSame('Target Not Detected', $interpreted['result']);
    }

    /**
     * The safeguard. A copies figure past anything an assay reports means the reading is
     * wrong somewhere, so with the flag on the entry survives rather than being replaced
     * by a figure derived from that reading.
     */
    public function testWithTheFlagOnAnImplausibleInterpretationKeepsTheReportedResult(): void
    {
        $vlService = VlServiceFactory::build([self::FLAG => 'yes']);

        $interpreted = $vlService->interpretViralLoadResult('12345678901');

        $this->assertSame('12345678901', $interpreted['result']);
        $this->assertNull($interpreted['absDecimalVal']);
        $this->assertNull($interpreted['logVal']);
    }

    /**
     * With the flag off the same input keeps the entry too, by the ordinary rule rather
     * than the safeguard -- but the derived columns are still cleared, because an
     * impossible figure is impossible under either setting.
     */
    public function testWithTheFlagOffAnImplausibleInterpretationStillClearsTheDerivedColumns(): void
    {
        $vlService = VlServiceFactory::build([self::FLAG => 'no']);

        $interpreted = $vlService->interpretViralLoadResult('12345678901');

        $this->assertSame('12345678901', $interpreted['result']);
        $this->assertNull($interpreted['absDecimalVal']);
        $this->assertNull($interpreted['logVal']);
    }

    /**
     * A sound numeric result is unaffected by the flag in the derived columns; only
     * `result` differs, and only in form.
     */
    public function testASoundNumericResultKeepsItsDerivedValuesUnderEitherSetting(): void
    {
        foreach (['no', 'yes'] as $flag) {
            $interpreted = VlServiceFactory::build([self::FLAG => $flag])
                ->interpretViralLoadResult('3892');

            $this->assertEqualsWithDelta(3892.0, (float) $interpreted['absDecimalVal'], 0.01, "flag={$flag}");
            $this->assertEqualsWithDelta(3.59, (float) $interpreted['logVal'], 0.01, "flag={$flag}");
        }
    }
}
