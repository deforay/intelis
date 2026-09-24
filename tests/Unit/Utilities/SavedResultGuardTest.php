<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\SavedResultGuard;
use PHPUnit\Framework\TestCase;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\CANCELLED;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\REJECTED;

/**
 * An API client re-posting a sample the lab has already decided on.
 */
final class SavedResultGuardTest extends TestCase
{
    private const RESULTED = [
        'unique_id' => 'u-1',
        'result' => '1200',
        'tested_by' => 'lab-user',
        'result_status' => PENDING_APPROVAL,
        'is_sample_rejected' => 'no',
        'reason_for_sample_rejection' => null,
        'patient_phone' => '555',
    ];

    /** What a client that has not pulled the result yet posts. */
    private const STALE_POST = [
        'result' => null,
        'tested_by' => '',
        'result_status' => RECEIVED_AT_CLINIC,
        'is_sample_rejected' => null,
        'patient_phone' => '777',
    ];

    public function testARepostDoesNotBlankTheLabsResultOrMoveTheSampleBack(): void
    {
        [$update, $kept] = SavedResultGuard::protect(self::STALE_POST, self::RESULTED);

        self::assertSame(['patient_phone' => '777'], $update);
        self::assertSame(['result', 'tested_by', 'is_sample_rejected', 'result_status'], $kept);
    }

    public function testALabColumnTheRequestPullProtectsIsKeptToo(): void
    {
        [$update] = SavedResultGuard::protect(
            ['vl_focal_person' => '', 'result' => null],
            self::RESULTED + ['vl_focal_person' => 'Dr Lab'],
            'vl'
        );

        self::assertSame([], $update);
    }

    public function testANewResultReplacesTheApprovalOfTheOldOne(): void
    {
        $post = ['result' => '40', 'result_approved_by' => null, 'result_status' => PENDING_APPROVAL];

        [$update] = SavedResultGuard::protect($post, self::RESULTED + ['result_approved_by' => 'approver']);

        self::assertSame($post, $update);
    }

    public function testTheClientCanStillClearItsOwnRequestDetails(): void
    {
        [$update] = SavedResultGuard::protect(['patient_phone' => '', 'result' => null], self::RESULTED);

        self::assertSame(['patient_phone' => ''], $update);
    }

    public function testARepostDoesNotUndoARejection(): void
    {
        $rejected = ['result' => null, 'result_status' => REJECTED, 'is_sample_rejected' => 'yes',
            'reason_for_sample_rejection' => '3', 'rejection_on' => '2026-09-20'];
        $post = ['result' => null, 'result_status' => RECEIVED_AT_TESTING_LAB, 'is_sample_rejected' => 'no',
            'reason_for_sample_rejection' => null, 'rejection_on' => null];

        [$update] = SavedResultGuard::protect($post, $rejected);

        // Only the empty result, which was empty already.
        self::assertSame(['result' => null], $update);
    }

    public function testACancelledSampleStaysCancelled(): void
    {
        [$update] = SavedResultGuard::protect(
            ['result_status' => RECEIVED_AT_CLINIC],
            ['result' => null, 'result_status' => CANCELLED]
        );

        self::assertSame([], $update);
    }

    public function testANewResultStillGoesThrough(): void
    {
        $post = ['result' => '40', 'tested_by' => 'app-user', 'result_status' => PENDING_APPROVAL];

        [$update, $kept] = SavedResultGuard::protect($post, self::RESULTED);

        self::assertSame($post, $update);
        self::assertSame([], $kept);
    }

    public function testARejectionStillGoesThroughAndClearsTheResult(): void
    {
        $post = ['result' => null, 'result_status' => REJECTED, 'is_sample_rejected' => 'yes'];

        [$update] = SavedResultGuard::protect($post, self::RESULTED);

        self::assertSame($post, $update);
    }

    public function testASampleTheLabHasNotDecidedOnIsWrittenAsPosted(): void
    {
        $received = ['result' => null, 'tested_by' => 'x', 'result_status' => RECEIVED_AT_TESTING_LAB];

        [$update] = SavedResultGuard::protect(self::STALE_POST, $received);

        self::assertSame(self::STALE_POST, $update);
    }

    public function testAnAcceptedStatusIsADecisionEvenWithoutAResult(): void
    {
        self::assertTrue(SavedResultGuard::hasLabDecision(['result' => '', 'result_status' => ACCEPTED]));
        self::assertFalse(SavedResultGuard::hasLabDecision(['result' => '', 'result_status' => RECEIVED_AT_CLINIC]));
        self::assertFalse(SavedResultGuard::hasLabDecision([]));
    }
}
