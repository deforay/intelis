<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\MiscUtility;
use PHPUnit\Framework\TestCase;

/**
 * The "Result Modified" chip claims something specific: this sample's result was changed
 * after it had already been decided. The reason column it reads is not that narrow -- the
 * same column also collects legacy "name##message##datetime" rows and the free-text reasons
 * older forms saved on any edit, so treating every entry as a result change put the chip on
 * samples whose results had never moved.
 *
 * These are pure string/array functions, so they run without a database.
 */
final class ResultChangeHistoryTest extends TestCase
{
    private const CHANGE = [
        'usr' => 'user-1',
        'dtime' => '2026-08-17 11:55:00',
        'msg' => 'Corrected after re-run',
        'previousResult' => 'TND',
        'previousResultStatus' => 7,
        'previousRejection' => 'no',
    ];

    public function testAChangedResultCounts(): void
    {
        self::assertTrue(MiscUtility::resultOrRejectionChanged(
            ['result' => 'TND', 'is_sample_rejected' => 'no'],
            ['result' => '<40', 'is_sample_rejected' => 'no']
        ));
    }

    /** Re-saving the same result is not a modification, however many times it is saved. */
    public function testAnUnchangedResultDoesNot(): void
    {
        self::assertFalse(MiscUtility::resultOrRejectionChanged(
            ['result' => 'TND', 'is_sample_rejected' => 'no'],
            ['result' => 'TND', 'is_sample_rejected' => 'no']
        ));
    }

    /** First-time result entry is not a modification: there was nothing there to change. */
    public function testFirstTimeResultEntryDoesNot(): void
    {
        self::assertFalse(MiscUtility::resultOrRejectionChanged(
            ['result' => '', 'is_sample_rejected' => null],
            ['result' => 'TND', 'is_sample_rejected' => 'no']
        ));
    }

    public function testRejectingAnAlreadyResultedSampleCounts(): void
    {
        self::assertTrue(MiscUtility::resultOrRejectionChanged(
            ['result' => 'TND', 'is_sample_rejected' => 'no'],
            ['result' => 'TND', 'is_sample_rejected' => 'yes']
        ));
    }

    /** null and "no" both mean not rejected; the difference is storage noise, not a change. */
    public function testNullToNoIsNotARejectionFlip(): void
    {
        self::assertFalse(MiscUtility::resultOrRejectionChanged(
            ['result' => '', 'is_sample_rejected' => null],
            ['result' => '', 'is_sample_rejected' => 'no']
        ));
    }

    /** The legacy separator format records a reason but never what the result was before. */
    public function testLegacyEntriesProveNothing(): void
    {
        $raw = 'Someone##Sample details corrected##2026-01-02 10:00:00';

        self::assertNotEmpty(MiscUtility::parseResultChangeHistory($raw));
        self::assertSame([], MiscUtility::genuineResultChangeHistory($raw));
        self::assertFalse(MiscUtility::hasGenuineResultChange($raw));
    }

    /** A reason logged without the pre-change state (TB / custom tests write these) is not proof. */
    public function testAReasonWithoutThePreChangeStateProvesNothing(): void
    {
        $raw = json_encode([['usr' => 'user-1', 'dtime' => '2026-08-17 11:55:00', 'msg' => 'Typo in patient name']]);

        self::assertFalse(MiscUtility::hasGenuineResultChange($raw));
    }

    public function testAnEntryCarryingThePreChangeStateIsKept(): void
    {
        $raw = json_encode([self::CHANGE]);

        self::assertTrue(MiscUtility::hasGenuineResultChange($raw));
        self::assertCount(1, MiscUtility::genuineResultChangeHistory($raw));
    }

    /** The pre-audit single-object format is a real result change and must survive the filter. */
    public function testTheLegacySingleObjectFormatIsKept(): void
    {
        $raw = json_encode([
            'user' => 'user-1',
            'dateOfChange' => '2026-01-02 10:00:00',
            'previousResult' => 'TND',
            'previousResultStatus' => 7,
            'reasonForChange' => 'Re-tested',
        ]);

        self::assertTrue(MiscUtility::hasGenuineResultChange($raw));
    }

    /** Mixed history: the unrelated reasons are dropped and the real change is still found. */
    public function testTheLatestChangeSkipsUnrelatedReasons(): void
    {
        $raw = json_encode([
            self::CHANGE,
            ['usr' => 'user-2', 'dtime' => '2026-08-18 09:00:00', 'msg' => 'Facility corrected'],
        ]);

        self::assertCount(1, MiscUtility::genuineResultChangeHistory($raw));
        self::assertSame('Corrected after re-run', MiscUtility::latestResultChangeReason($raw)['msg']);
    }

    public function testNoHistoryProvesNothing(): void
    {
        self::assertFalse(MiscUtility::hasGenuineResultChange(null));
        self::assertFalse(MiscUtility::hasGenuineResultChange(''));
        self::assertSame([], MiscUtility::latestResultChangeReason(null));
    }
}
