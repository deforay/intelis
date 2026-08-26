<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\SampleRejectionUtility;
use PHPUnit\Framework\TestCase;

/**
 * Four places used to answer "is this sample rejected" four different ways, and
 * over one nine-month window of VL data they returned 412, 419, 421 and 425 for
 * the same question. These are the cases that pulled those numbers apart.
 *
 * Pure array/string logic, so no database is needed.
 */
final class SampleRejectionTest extends TestCase
{
    /** The ordinary case: both records agree. 410 of the 425 looked like this. */
    public function testFlagAndStatusAgreeing(): void
    {
        $this->assertTrue(SampleRejectionUtility::isRejected([
            'is_sample_rejected' => 'yes',
            'result_status' => 4,
        ]));
    }

    /**
     * The flag is not always written. 8 samples in that window had it NULL while
     * the status said rejected, and every flag-only count missed all of them.
     */
    public function testStatusAloneStillCounts(): void
    {
        $this->assertTrue(SampleRejectionUtility::isRejected([
            'is_sample_rejected' => null,
            'result_status' => 4,
        ]));
    }

    /**
     * result_status is the sample's *current* state, so a rejected sample that
     * was later re-ordered or accepted has moved off REJECTED. The flag is then
     * the only surviving record of the rejection.
     */
    public function testFlagAloneStillCounts(): void
    {
        $this->assertTrue(SampleRejectionUtility::isRejected([
            'is_sample_rejected' => 'yes',
            'result_status' => 7,
        ]));
    }

    /**
     * A rejection reason is a detail of a rejection, not evidence one happened.
     * It is left behind on samples that were un-rejected, which is exactly what
     * made the Excel exports disagree with the report they came from.
     */
    public function testALeftoverReasonIsNotARejection(): void
    {
        $this->assertFalse(SampleRejectionUtility::isRejected([
            'is_sample_rejected' => 'no',
            'result_status' => 7,
            'reason_for_sample_rejection' => 28,
        ]));
    }

    public function testAnAcceptedSampleIsNotRejected(): void
    {
        $this->assertFalse(SampleRejectionUtility::isRejected([
            'is_sample_rejected' => 'no',
            'result_status' => 7,
        ]));
    }

    /** Rows arrive from the database as strings, and 'Yes' from imports. */
    public function testStringStatusAndMixedCaseFlag(): void
    {
        $this->assertTrue(SampleRejectionUtility::isRejected([
            'is_sample_rejected' => ' YES ',
            'result_status' => '7',
        ]));
        $this->assertTrue(SampleRejectionUtility::isRejected([
            'is_sample_rejected' => 'no',
            'result_status' => '4',
        ]));
    }

    /** A row with neither column set must not read as rejected. */
    public function testMissingColumnsAreNotRejected(): void
    {
        $this->assertFalse(SampleRejectionUtility::isRejected([]));
    }

    public function testSqlPredicateUsesTheGivenAlias(): void
    {
        $sql = SampleRejectionUtility::sqlPredicate('vl');
        $this->assertStringContainsString('vl.is_sample_rejected', $sql);
        $this->assertStringContainsString('vl.result_status = 4', $sql);
    }

    /**
     * The flag is normalised in SQL the same way isRejected() normalises it in PHP.
     * Without it the two disagree on a value carrying a stray space, which is a grid
     * disagreeing with its own export -- the thing this class was written to end.
     * That they agree on real rows is checked against MySQL in the integration suite.
     */
    public function testSqlPredicateNormalisesTheFlagTheWayThePhpTestDoes(): void
    {
        $sql = SampleRejectionUtility::sqlPredicate('vl');
        $this->assertStringContainsString("LOWER(TRIM(vl.is_sample_rejected)) = 'yes'", $sql);
    }

    /** Identifiers are interpolated into SQL, so a bad one is refused outright. */
    public function testSqlPredicateRefusesAnUnsafeAlias(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SampleRejectionUtility::sqlPredicate("vl; DROP TABLE form_vl --");
    }

    public function testReasonLabelKeepsARealReason(): void
    {
        $this->assertSame(
            'Mismatched sample and form labeling',
            SampleRejectionUtility::reasonLabel('Mismatched sample and form labeling')
        );
    }

    /**
     * A reason id minted on another install arrives here with no row behind it, so
     * the join yields null. The rejection still happened and must still be named.
     */
    public function testReasonLabelNamesAnUnmatchedReason(): void
    {
        $this->assertSame('Unknown or unreported reason', SampleRejectionUtility::reasonLabel(null));
        $this->assertSame('Unknown or unreported reason', SampleRejectionUtility::reasonLabel(''));
        $this->assertSame('Unknown or unreported reason', SampleRejectionUtility::reasonLabel('   '));
    }
}
