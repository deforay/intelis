<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\SampleStatusUtility;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * The status names a results resend can be limited to, as in
 * `composer send-results 400 --status=rejected,accepted`.
 *
 * A name the parser does not know is refused: dropped quietly, a typo would
 * resend nothing and read as "nothing to resend".
 */
final class SendableStatusesTest extends TestCase
{
    public function testNamesBecomeStatuses(): void
    {
        self::assertSame([\SAMPLE_STATUS\REJECTED], SampleStatusUtility::parseSendableStatuses('rejected'));
        self::assertSame(
            [\SAMPLE_STATUS\ACCEPTED, \SAMPLE_STATUS\REJECTED],
            SampleStatusUtility::parseSendableStatuses(' Accepted , REJECTED,accepted ')
        );
    }

    public function testAnUnknownNameIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown status "rejcted"');
        SampleStatusUtility::parseSendableStatuses('accepted,rejcted');
    }

    public function testNoNameIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        SampleStatusUtility::parseSendableStatuses(' , ');
    }

    public function testTheRemoteResendRefusesAnUnknownStatusBeforeSendingAnything(): void
    {
        $result = (static function (): array {
            $params = ['module' => 'vl', 'days' => 400, 'statuses' => ['rejected', 'rejcted']];
            $command = [];
            return require dirname(__DIR__, 3) . '/app/tasks/remote/command-handlers/resend-results.php';
        })();

        self::assertSame('failed', $result['status']);
        self::assertStringContainsString('Unknown status "rejcted"', $result['error']);
    }

    public function testEveryStatusTheSenderSendsHasAName(): void
    {
        // The sender never sends Received at Clinic, so it has no name to ask for.
        $expected = array_diff(
            range(\SAMPLE_STATUS\ON_HOLD, \SAMPLE_STATUS\REFERRED),
            [\SAMPLE_STATUS\RECEIVED_AT_CLINIC]
        );
        $named = array_values(SampleStatusUtility::SENDABLE_STATUS_NAMES);
        sort($named);
        self::assertSame(array_values($expected), $named);
    }
}
