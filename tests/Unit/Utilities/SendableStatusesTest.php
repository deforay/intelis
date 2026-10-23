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

    public function testAFilterIsAbsentOrValid(): void
    {
        self::assertNull(SampleStatusUtility::sendableStatusFilter(null));
        self::assertNull(SampleStatusUtility::sendableStatusFilter(''));
        self::assertNull(SampleStatusUtility::sendableStatusFilter([]));
        self::assertSame('rejected', SampleStatusUtility::sendableStatusFilter(' Rejected '));
        self::assertSame('rejected,accepted', SampleStatusUtility::sendableStatusFilter(['accepted', 'rejected']));
    }

    /** @return iterable<string, array{mixed}> */
    public static function malformedFilters(): iterable
    {
        yield 'a status number in a list' => [[4]];
        yield 'a number' => [4];
        yield 'only separators' => [' , '];
        yield 'an empty name in a list' => [['']];
    }

    /** Dropped instead of refused, each of these once turned into a resend of every status. */
    #[\PHPUnit\Framework\Attributes\DataProvider('malformedFilters')]
    public function testAMalformedFilterIsRefusedNotDropped(mixed $filter): void
    {
        $this->expectException(InvalidArgumentException::class);
        SampleStatusUtility::sendableStatusFilter($filter);
    }

    public function testTheRemoteResendRefusesAStatusNumber(): void
    {
        $result = (static function (): array {
            $params = ['module' => 'vl', 'days' => 400, 'statuses' => [4]];
            $command = [];
            return require dirname(__DIR__, 3) . '/app/tasks/remote/command-handlers/resend-results.php';
        })();

        self::assertSame('failed', $result['status']);
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
