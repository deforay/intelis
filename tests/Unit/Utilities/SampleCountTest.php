<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\SampleCountUtility;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

use const SAMPLE_STATUS\CANCELLED;

/**
 * The shape of the clause. That it selects the right rows out of MySQL is checked in
 * the integration suite, which is where the answer can actually differ.
 */
final class SampleCountTest extends TestCase
{
    public function testTheClauseExcludesCancelledAndNothingElse(): void
    {
        $sql = SampleCountUtility::countableWhere('vl');

        self::assertStringContainsString('vl.result_status', $sql);
        self::assertStringContainsString('!= ' . CANCELLED, $sql);
    }

    /**
     * Every caller passes its own alias, and a report joins several tables that each
     * carry result_status. An alias that did not reach the clause would silently
     * constrain the wrong one.
     */
    public function testTheGivenAliasIsTheOneConstrained(): void
    {
        self::assertStringContainsString('eid.result_status', SampleCountUtility::countableWhere('eid'));
        self::assertStringNotContainsString('vl.result_status', SampleCountUtility::countableWhere('eid'));
    }

    public function testTheDefaultAliasIsVl(): void
    {
        self::assertSame(SampleCountUtility::countableWhere('vl'), SampleCountUtility::countableWhere());
    }

    /** Aliases are interpolated into SQL, so a bad one is refused outright. */
    public function testAnUnsafeAliasIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SampleCountUtility::countableWhere('vl; DROP TABLE form_vl --');
    }

    public function testAnEmptyAliasIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SampleCountUtility::countableWhere('');
    }
}
