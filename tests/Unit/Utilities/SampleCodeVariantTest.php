<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\SampleCodeVariantUtility;
use PHPUnit\Framework\TestCase;

/**
 * Two instances sending work to one testing lab will eventually mint the same
 * sample code, because the counter is keyed on (test_type, year, code_type) and
 * a VL code minted on a LIS carries no lab. The unique index on
 * (sample_code, lab_id) then refuses the second arrival, and before this the
 * whole record was rolled back and the result was lost.
 *
 * These are the cases that decide which code the arriving sample is stored
 * under. Pure string logic -- no database.
 */
final class SampleCodeVariantTest extends TestCase
{
    /** Nothing holds the code, so the sample keeps the one its lab gave it. */
    public function testUncontestedCodeIsUnchanged(): void
    {
        $this->assertSame('VL02261176', SampleCodeVariantUtility::nextVariant('VL02261176', []));
    }

    public function testFirstConflictTakesVariantOne(): void
    {
        $this->assertSame(
            'VL02261176-1',
            SampleCodeVariantUtility::nextVariant('VL02261176', ['VL02261176'])
        );
    }

    /**
     * Labs here are fed by up to eight instances, so a third and fourth claim
     * on one code is expected. Assuming -1 is free would fail on the third.
     */
    public function testCountsPastExistingVariants(): void
    {
        $this->assertSame(
            'VL02261176-3',
            SampleCodeVariantUtility::nextVariant(
                'VL02261176',
                ['VL02261176', 'VL02261176-1', 'VL02261176-2']
            )
        );
    }

    /** Gaps do not renumber anything: the highest wins, so codes are never reused. */
    public function testSkipsToAboveTheHighestVariant(): void
    {
        $this->assertSame(
            'VL02261176-8',
            SampleCodeVariantUtility::nextVariant('VL02261176', ['VL02261176', 'VL02261176-7'])
        );
    }

    /**
     * A variant can exist without the bare code -- the sample holding it may
     * have been cancelled or corrected since. It still must not be reissued.
     */
    public function testVariantAloneStillCounts(): void
    {
        $this->assertSame(
            'VL02261176-2',
            SampleCodeVariantUtility::nextVariant('VL02261176', ['VL02261176-1'])
        );
    }

    /** A longer code starting with the same characters is a different sample. */
    public function testUnrelatedCodesAreIgnored(): void
    {
        $this->assertSame(
            'VL0226117',
            SampleCodeVariantUtility::nextVariant('VL0226117', ['VL02261176', 'VL0226117X'])
        );
    }

    /** A non-numeric suffix is not a variant -- stsLabPostfix() appends -MONK. */
    public function testLabPostfixIsNotAVariant(): void
    {
        $this->assertFalse(SampleCodeVariantUtility::isVariantOf('VL02261176-MONK', 'VL02261176'));
        $this->assertSame(
            'VL02261176',
            SampleCodeVariantUtility::nextVariant('VL02261176', ['VL02261176-MONK'])
        );
    }

    /**
     * The record keeps the variant it was already given. Without this the lab's
     * next sync renames it back to the bare code, it collides again, and the
     * result fails on every run from then on.
     */
    public function testAnAssignedVariantIsRecognisedAsItsOwn(): void
    {
        $this->assertTrue(SampleCodeVariantUtility::isVariantOf('VL02261176-1', 'VL02261176'));
        $this->assertTrue(SampleCodeVariantUtility::isVariantOf('VL02261176', 'VL02261176'));
        $this->assertFalse(SampleCodeVariantUtility::isVariantOf('VL02261177', 'VL02261176'));
    }

    /** A wildcard in a code must not let it match, and renumber, other samples. */
    public function testLikePatternEscapesWildcards(): void
    {
        $this->assertSame('VL|%26|_11-%', SampleCodeVariantUtility::likePattern('VL%26_11'));
    }
}
