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

    public function testTheSavedResultPostedBackDecidesNothing(): void
    {
        // A client re-posts the result it pulled, with the lab's other fields empty.
        $post = [
            'result' => ' 1200 ', 'sample_tested_datetime' => null, 'tested_by' => '',
            'result_status' => RECEIVED_AT_TESTING_LAB, 'patient_phone' => '777',
        ];
        $stored = self::RESULTED + ['sample_tested_datetime' => '2026-09-21 11:00:00'];

        [$update, $kept] = SavedResultGuard::protect($post, $stored);

        self::assertSame(['result' => ' 1200 ', 'patient_phone' => '777'], $update);
        self::assertSame(['tested_by', 'sample_tested_datetime', 'result_status'], $kept);
    }

    public function testTheSavedRejectionPostedBackKeepsItsDateAndTakesANewReason(): void
    {
        $rejected = ['result' => null, 'result_status' => REJECTED, 'is_sample_rejected' => 'yes',
            'reason_for_sample_rejection' => '3', 'rejection_on' => '2026-09-01'];
        $post = ['is_sample_rejected' => 'yes', 'reason_for_sample_rejection' => '4', 'rejection_on' => null];

        [$update] = SavedResultGuard::protect($post, $rejected);

        self::assertSame(['is_sample_rejected' => 'yes', 'reason_for_sample_rejection' => '4'], $update);
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

    private const DECLARED = ['supports' => [SavedResultGuard::RESULT_VERSION]];

    public function testOnlyAClientThatDeclaresItGetsTheVersionCheck(): void
    {
        self::assertTrue(SavedResultGuard::declaresResultVersion(self::DECLARED));
        self::assertFalse(SavedResultGuard::declaresResultVersion(null));
        self::assertFalse(SavedResultGuard::declaresResultVersion(['supports' => 'result-version']));
        self::assertFalse(SavedResultGuard::declaresResultVersion([SavedResultGuard::RESULT_VERSION]));
    }

    public function testTheVersionMovesWithTheDecisionAndNothingElse(): void
    {
        $version = SavedResultGuard::resultVersion(self::RESULTED);

        self::assertSame($version, SavedResultGuard::resultVersion(
            ['result' => ' 1200 ', 'patient_phone' => '999'] + self::RESULTED
        ));
        // An approval is a decision too: a client that has not seen it is behind.
        $changed = [
            ['result_status' => ACCEPTED],
            ['result' => '900'],
            ['is_sample_rejected' => 'yes'],
            // So is any other correction the lab makes to a column it owns.
            ['tested_by' => 'other-lab-user'],
            ['result_approved_by' => 'approver'],
        ];
        foreach ($changed as $change) {
            self::assertNotSame($version, SavedResultGuard::resultVersion($change + self::RESULTED));
        }
    }

    public function testTheVersionCoversEveryFieldTheLabOwnsForTheTestType(): void
    {
        $labFields = [
            'vl' => ['vl_focal_person', 'vl_focal_person_phone_number'],
            'eid' => ['import_machine_name'],
            'tb' => ['specimen_quality', 'is_result_finalized'],
            'covid19' => ['sample_condition', 'testing_point', 'other_diseases'],
        ];
        foreach ($labFields as $testType => $columns) {
            $version = SavedResultGuard::resultVersion(self::RESULTED, [], $testType);
            foreach ($columns as $column) {
                self::assertNotSame(
                    $version,
                    SavedResultGuard::resultVersion([$column => 'changed by the lab'] + self::RESULTED, [], $testType),
                    "$testType $column"
                );
            }
        }
        // Stamped by fetch-results and printing, which decide nothing.
        self::assertSame(
            SavedResultGuard::resultVersion(self::RESULTED, [], 'covid19'),
            SavedResultGuard::resultVersion(
                ['result_sent_to_source' => 'sent', 'result_printed_datetime' => '2026-09-24 10:00:00']
                    + self::RESULTED,
                [],
                'covid19'
            )
        );
    }

    public function testAStaleTbPostLeavesTheFinalizedFlagAsSaved(): void
    {
        [$update] = SavedResultGuard::keepLabDecision(
            ['result' => 'MTB detected', 'is_result_finalized' => 'yes', 'patient_id' => 'P-2'],
            'tb'
        );

        self::assertSame(['patient_id' => 'P-2'], $update);
    }

    public function testTheVersionMovesWhenATestResultDoes(): void
    {
        $tests = [['test_name' => 'PCR', 'result' => 'positive'], ['test_name' => 'RDT', 'result' => 'negative']];
        $version = SavedResultGuard::resultVersion(self::RESULTED, $tests);

        self::assertNotSame(SavedResultGuard::resultVersion(self::RESULTED), $version);
        self::assertSame($version, SavedResultGuard::resultVersion(self::RESULTED, array_reverse($tests)));
        $tests[1]['result'] = 'positive';
        self::assertNotSame($version, SavedResultGuard::resultVersion(self::RESULTED, $tests));
        self::assertTrue(SavedResultGuard::isStale(self::RESULTED, $version, $tests));
    }

    public function testAPostOnTheResultTheClientPulledIsNotStale(): void
    {
        self::assertFalse(SavedResultGuard::isStale(self::RESULTED, SavedResultGuard::resultVersion(self::RESULTED)));
    }

    public function testAPostOnAResultTheLabHasSinceRevisedIsStale(): void
    {
        $pulled = SavedResultGuard::resultVersion(self::RESULTED);
        $revised = ['result' => '40'] + self::RESULTED;

        self::assertTrue(SavedResultGuard::isStale($revised, $pulled));
    }

    public function testAPostWithoutAVersionIsStaleOnceTheLabHasDecided(): void
    {
        self::assertTrue(SavedResultGuard::isStale(self::RESULTED, null));
        self::assertTrue(SavedResultGuard::isStale(self::RESULTED, ''));
        self::assertTrue(SavedResultGuard::isStale(self::RESULTED, ['not', 'a', 'string']));
    }

    public function testNothingIsStaleBeforeTheLabDecides(): void
    {
        $undecided = ['result' => '', 'result_status' => RECEIVED_AT_TESTING_LAB, 'is_sample_rejected' => 'no'];

        self::assertFalse(SavedResultGuard::isStale($undecided, null));
        self::assertFalse(SavedResultGuard::isStale([], 'anything'));
    }

    public function testAPostOnAResultPulledBeforeARetestIsStale(): void
    {
        // The lab ordered a retest: the result is cleared and the sample is undecided again.
        $pulled = SavedResultGuard::resultVersion(self::RESULTED);
        $retest = ['result' => null, 'result_status' => RECEIVED_AT_TESTING_LAB] + self::RESULTED;

        self::assertTrue(SavedResultGuard::isStale($retest, $pulled));
        self::assertFalse(SavedResultGuard::isStale($retest, SavedResultGuard::resultVersion($retest)));
    }

    public function testAStalePostKeepsEveryLabColumnAndSavesTheRest(): void
    {
        [$update, $kept] = SavedResultGuard::keepLabDecision([
            'result' => '1200',
            'tested_by' => 'app-user',
            'result_status' => ACCEPTED,
            'is_sample_rejected' => 'yes',
            'reason_for_sample_rejection' => '3',
            'vl_focal_person' => 'Dr App',
            'patient_phone' => '777',
        ], 'vl');

        self::assertSame(['patient_phone' => '777'], $update);
        self::assertEqualsCanonicalizing(
            [
                'result', 'tested_by', 'result_status', 'is_sample_rejected', 'reason_for_sample_rejection',
                'vl_focal_person',
            ],
            $kept
        );
    }

    public function testADeclaredStalePostKeepsTheLabsRevisedResult(): void
    {
        $pulled = SavedResultGuard::resultVersion(self::RESULTED);
        $revised = ['result' => '40', 'result_status' => ACCEPTED] + self::RESULTED;

        [$update, $stale] = SavedResultGuard::guard(
            ['result' => '1200', 'result_status' => PENDING_APPROVAL, 'patient_phone' => '777'],
            $revised,
            'vl',
            null,
            true,
            $pulled
        );

        self::assertTrue($stale);
        self::assertSame(['patient_phone' => '777'], $update);
    }

    public function testADeclaredPostOnTheCurrentResultCanChangeIt(): void
    {
        [$update, $stale] = SavedResultGuard::guard(
            ['result' => '900', 'patient_phone' => '777'],
            self::RESULTED,
            'vl',
            null,
            true,
            SavedResultGuard::resultVersion(self::RESULTED)
        );

        self::assertFalse($stale);
        self::assertSame(['result' => '900', 'patient_phone' => '777'], $update);
    }

    public function testADeclaredPostOnTheCurrentResultWithoutANewOneWritesNoLabField(): void
    {
        // The endpoint fills in its own defaults, such as the poster as technician.
        $post = [
            'result' => '1200', 'lab_technician' => 'app-user', 'tested_by' => 'app-user',
            'result_status' => PENDING_APPROVAL, 'patient_phone' => '777',
        ];

        [$update, $stale] = SavedResultGuard::guard(
            $post,
            self::RESULTED,
            'vl',
            null,
            true,
            SavedResultGuard::resultVersion(self::RESULTED, [], 'vl')
        );

        self::assertFalse($stale);
        self::assertSame(['patient_phone' => '777'], $update);
    }

    public function testAClientThatDeclaresNothingIsGuardedAsBefore(): void
    {
        $revised = ['result' => '40'] + self::RESULTED;

        [$update, $stale] = SavedResultGuard::guard(['result' => '1200'], $revised, 'vl', null, false, null);

        self::assertFalse($stale);
        self::assertSame(['result' => '1200'], $update);
    }
}
