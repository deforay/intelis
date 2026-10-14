<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\VlService;
use App\Utilities\SampleStatusUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tests\Support\VlServiceFactory;

use const SAMPLE_STATUS\ACCEPTED;
use const SAMPLE_STATUS\CANCELLED;
use const SAMPLE_STATUS\ON_HOLD;
use const SAMPLE_STATUS\PENDING_APPROVAL;
use const SAMPLE_STATUS\RECEIVED_AT_CLINIC;
use const SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB;
use const SAMPLE_STATUS\REFERRED;
use const SAMPLE_STATUS\REJECTED;
use const SAMPLE_STATUS\TEST_FAILED;

/**
 * What a VL web save does to the status of a sample it leaves with no result.
 *
 * Clearing a result, or undoing a rejection without entering one, used to leave the
 * old status standing: Accepted or Awaiting Approval over an empty result, or
 * Rejected on a sample no longer rejected. And a result could be saved with no
 * testing platform.
 */
final class VlServiceResultStatusOnClearTest extends TestCase
{
    /** A lab receipt date */
    private const AT = '2026-10-01 10:00:00';

    private const POST = ['vlSampleId' => '7', 'sampleCode' => 'VL001', 'labId' => '3'];

    /**
     * @param array<string, mixed> $post
     */
    private static function waitingStatus(
        array $stored,
        array $post,
        ?string $receivedAtLab,
        bool $isSts = false,
        ?string $accessType = 'testing-lab'
    ): ?int {
        $post = self::POST + $post;
        $processed = VlServiceFactory::build()->processViralLoadResultFromForm($post);
        if (!VlService::leavesSampleWaiting($post, $stored, $processed, VlService::mustKeepSavedResult($post))) {
            return null;
        }
        return SampleStatusUtility::waitingForTestingStatus($receivedAtLab, $isSts, $accessType);
    }

    private static function resulted(int $status = ACCEPTED): array
    {
        return ['result' => '1200', 'result_status' => $status, 'is_sample_rejected' => 'no'];
    }

    private static function rejected(): array
    {
        return ['result' => null, 'result_status' => REJECTED, 'is_sample_rejected' => 'yes'];
    }

    public function testAClearedResultOnASampleAtTheLabGoesBackToReceivedAtTheLab(): void
    {
        $post = ['vlResult' => '', 'vlLog' => '', 'isSampleRejected' => 'no'];

        self::assertSame(RECEIVED_AT_TESTING_LAB, self::waitingStatus(self::resulted(), $post, self::AT));
        self::assertSame(
            RECEIVED_AT_TESTING_LAB,
            self::waitingStatus(self::resulted(PENDING_APPROVAL), $post, self::AT, true, 'collection-site')
        );
    }

    public function testAClearedResultWithNoReceiptDateGoesWhereANewRequestStarts(): void
    {
        $post = ['vlResult' => '', 'isSampleRejected' => 'no'];

        // LIS: a new request starts received at the lab
        self::assertSame(RECEIVED_AT_TESTING_LAB, self::waitingStatus(self::resulted(), $post, null));
        // STS, lab user: the lab side as well
        self::assertSame(RECEIVED_AT_TESTING_LAB, self::waitingStatus(self::resulted(), $post, null, true));
        // STS, collection site: at the clinic
        self::assertSame(
            RECEIVED_AT_CLINIC,
            self::waitingStatus(self::resulted(), $post, null, true, 'collection-site')
        );
        // A zero date is no receipt
        self::assertSame(
            RECEIVED_AT_CLINIC,
            self::waitingStatus(self::resulted(), $post, '0000-00-00 00:00:00', true, 'collection-site')
        );
    }

    public function testAFailedResultClearedGoesBackToWaiting(): void
    {
        $stored = ['result' => 'Failed', 'result_status' => TEST_FAILED, 'is_sample_rejected' => 'no'];

        self::assertSame(
            RECEIVED_AT_TESTING_LAB,
            self::waitingStatus($stored, ['vlResult' => ''], self::AT)
        );
    }

    public function testUndoingARejectionWithNoResultGoesBackToWaiting(): void
    {
        // The result box is disabled with no platform chosen, so no result is sent
        self::assertSame(
            RECEIVED_AT_TESTING_LAB,
            self::waitingStatus(self::rejected(), ['isSampleRejected' => 'no'], self::AT)
        );
        // Sent and empty
        self::assertSame(
            RECEIVED_AT_TESTING_LAB,
            self::waitingStatus(self::rejected(), ['isSampleRejected' => 'no', 'vlResult' => ''], self::AT)
        );
        // Rejection left unanswered
        self::assertSame(
            RECEIVED_AT_CLINIC,
            self::waitingStatus(self::rejected(), ['isSampleRejected' => null], null, true, 'collection-site')
        );
        // Flag already off but the status still says Rejected
        self::assertSame(
            RECEIVED_AT_TESTING_LAB,
            self::waitingStatus(
                ['result' => null, 'result_status' => REJECTED, 'is_sample_rejected' => 'no'],
                ['isSampleRejected' => 'no'],
                self::AT
            )
        );
    }

    public function testUndoingARejectionWithAResultLeavesTheStatusToTheResult(): void
    {
        $post = ['isSampleRejected' => 'no', 'vlResult' => '1200', 'testingPlatform' => 'Abbott##20##10000000##1'];
        $processed = VlServiceFactory::build()->processViralLoadResultFromForm(self::POST + $post);
        $keep = VlService::mustKeepSavedResult(self::POST + $post);

        self::assertNull(self::waitingStatus(self::rejected(), $post, self::AT));
        self::assertSame(PENDING_APPROVAL, VlService::resultStatusToWrite($processed, $keep));
    }

    public function testAFormWithoutTheRejectionFieldDoesNotUndoARejection(): void
    {
        // A collection site's request edit carries no lab section
        self::assertNull(self::waitingStatus(self::rejected(), [], self::AT, true, 'collection-site'));
        self::assertNull(self::waitingStatus(self::rejected(), ['vlResult' => ''], self::AT));
    }

    public function testARejectionIsNotUndone(): void
    {
        self::assertNull(self::waitingStatus(self::resulted(), ['isSampleRejected' => 'yes'], self::AT));
        self::assertNull(self::waitingStatus(self::rejected(), ['isSampleRejected' => 'yes'], self::AT));
    }

    public function testAKeptResultKeepsItsStatus(): void
    {
        // The result box was not sent: the stored result stands, and so does its status
        self::assertNull(self::waitingStatus(self::resulted(), ['isSampleRejected' => 'no'], self::AT));
        self::assertNull(self::waitingStatus(self::resulted(), ['vlLog' => '3.08'], self::AT));
        // PNG keeps the CPHL result in its own column
        self::assertNull(self::waitingStatus(
            ['result' => null, 'cphl_vl_result' => '< 20', 'result_status' => PENDING_APPROVAL],
            [],
            self::AT
        ));
    }

    public function testAFirstOrChangedResultIsNotAWaitingSample(): void
    {
        $waiting = ['result' => null, 'result_status' => RECEIVED_AT_TESTING_LAB, 'is_sample_rejected' => null];

        self::assertNull(self::waitingStatus($waiting, ['vlResult' => '1200'], self::AT));
        self::assertNull(self::waitingStatus(self::resulted(), ['vlResult' => '5000'], self::AT));
        // A result made from the log alone is a result
        self::assertNull(self::waitingStatus(self::resulted(), ['vlResult' => '', 'vlLog' => '3.08'], self::AT));
    }

    /**
     * @return array<string, array{int}>
     */
    public static function undecidedStatuses(): array
    {
        return [
            'received at the lab' => [RECEIVED_AT_TESTING_LAB],
            'received at the clinic' => [RECEIVED_AT_CLINIC],
            'on hold' => [ON_HOLD],
            'referred' => [REFERRED],
            'cancelled' => [CANCELLED],
        ];
    }

    #[DataProvider('undecidedStatuses')]
    public function testASampleNeverDecidedKeepsItsStatus(int $status): void
    {
        $stored = ['result' => null, 'result_status' => $status, 'is_sample_rejected' => null];

        self::assertNull(self::waitingStatus($stored, ['vlResult' => ''], self::AT));
        self::assertNull(self::waitingStatus($stored, ['isSampleRejected' => 'no'], null, true, 'collection-site'));
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: bool, 2: bool}>
     */
    public static function platformProvider(): array
    {
        $platform = ['testingPlatform' => 'Abbott##20##10000000##1'];

        return [
            'result without platform' => [['vlResult' => '1200'], false, true],
            'result with platform' => [['vlResult' => '1200'] + $platform, false, false],
            'result from the log alone, no platform' => [['vlResult' => '', 'vlLog' => '3.08'], false, true],
            'detection alone, no platform' => [['vlResult' => '', 'hivDetection' => 'HIV-1 Not Detected'], false, true],
            'rejected without platform' => [['isSampleRejected' => 'yes', 'vlResult' => '1200'], false, false],
            'kept result without platform' => [['isSampleRejected' => 'no'], true, false],
            'cleared result without platform' => [['vlResult' => '', 'vlLog' => ''], false, false],
            'platform posted empty' => [['vlResult' => '1200', 'testingPlatform' => ''], false, true],
            'PNG final result without platform' => [['vlResult' => '400'], false, true],
        ];
    }

    /**
     * @param array<string, mixed> $post
     */
    #[DataProvider('platformProvider')]
    public function testAResultIsRefusedWithoutAPlatform(array $post, bool $keep, bool $refused): void
    {
        $post = self::POST + $post;
        $processed = VlServiceFactory::build()->processViralLoadResultFromForm($post);

        self::assertSame($keep, VlService::mustKeepSavedResult($post));
        self::assertSame($refused, VlService::refusesResultWithoutPlatform($post, $processed, $keep));
    }

    public function testSignoffIsClearedAndNothingElse(): void
    {
        $data = [
            'result_reviewed_by' => 'u1',
            'result_reviewed_datetime' => '2026-10-01 10:00:00',
            'result_approved_by' => 'u2',
            'result_approved_datetime' => '2026-10-01 11:00:00',
            'tested_by' => 'u3',
            'sample_tested_datetime' => '2026-10-01 09:00:00',
        ];

        self::assertSame(
            [
                'result_reviewed_by' => null,
                'result_reviewed_datetime' => null,
                'result_approved_by' => null,
                'result_approved_datetime' => null,
                'tested_by' => 'u3',
                'sample_tested_datetime' => '2026-10-01 09:00:00',
            ],
            SampleStatusUtility::withoutSignoff($data)
        );
    }

    public function testInitialStatusMatchesTheRequestAddRule(): void
    {
        self::assertSame(RECEIVED_AT_TESTING_LAB, SampleStatusUtility::initialStatus(false, 'collection-site'));
        self::assertSame(RECEIVED_AT_TESTING_LAB, SampleStatusUtility::initialStatus(true, 'testing-lab'));
        self::assertSame(RECEIVED_AT_TESTING_LAB, SampleStatusUtility::initialStatus(true, null));
        self::assertSame(RECEIVED_AT_CLINIC, SampleStatusUtility::initialStatus(true, 'collection-site'));
    }
}
