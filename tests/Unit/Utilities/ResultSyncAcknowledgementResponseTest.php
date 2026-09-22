<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\ResultSyncAcknowledgement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Reading the STS's answer to a results push (results-sender.php).
 *
 * Anything that is not an acknowledgment throws, so the sender stops that module
 * and the rows it sent go back to pending, rather than being read as "nothing
 * acknowledged" while the rest of the module is sent to an STS refusing it.
 */
final class ResultSyncAcknowledgementResponseTest extends TestCase
{
    private static function answer(int $status, string $body): array
    {
        return ['httpStatusCode' => $status, 'body' => $body, 'headers' => []];
    }

    public function testAListOfCodesIsTheAcknowledgment(): void
    {
        self::assertSame(['S1', 'S2'], ResultSyncAcknowledgement::fromResponse(self::answer(200, '["S1","S2"]'), 'vl'));
    }

    public function testNothingStoredIsAnEmptyAcknowledgment(): void
    {
        self::assertSame([], ResultSyncAcknowledgement::fromResponse(self::answer(200, '[]'), 'vl'));
    }

    public function testAnOlderStsSendingAnObjectKeyedByPositionStillCounts(): void
    {
        self::assertSame(
            ['S1', 'S3'],
            ResultSyncAcknowledgement::fromResponse(self::answer(200, '{"0":"S1","2":"S3"}'), 'vl')
        );
    }

    public function testABareBodyFromAnOlderCallerIsRead(): void
    {
        self::assertSame(['S1'], ResultSyncAcknowledgement::fromResponse('["S1"]', 'vl'));
    }

    public function testDuplicatesBlanksAndNonStringsAreDropped(): void
    {
        self::assertSame(
            ['S1', 'S2'],
            ResultSyncAcknowledgement::fromResponse(self::answer(200, '["S1","",null,7,["x"],"S2","S1"]'), 'vl')
        );
    }

    /** @return array<string, array{0: array<string, mixed>|string|null}> */
    public static function notAnAcknowledgment(): array
    {
        $error = '{"error":{"code":500,"message":"Unable to process the results"}}';
        return [
            'STS unreachable' => [null],
            'no response (connection refused)' => [['httpStatusCode' => 500, 'body' => null]],
            'server error' => [self::answer(500, $error)],
            'bad token' => [self::answer(401, '{"error":{"code":401,"message":"Unauthorized"}}')],
            'malformed request' => [self::answer(400, $error)],
            'gateway timeout page' => [self::answer(504, '<html>Gateway Timeout</html>')],
            'a list, but on an error status' => [self::answer(500, '["S1"]')],
            'HTML with 200 (captive portal, PHP notice)' => [self::answer(200, '<br><b>Warning</b>: x')],
            'truncated JSON' => [self::answer(200, '["S1","S2')],
            'empty body' => [self::answer(200, '')],
            'a bare string' => [self::answer(200, '"S1"')],
            'a number' => [self::answer(200, '42')],
        ];
    }

    #[DataProvider('notAnAcknowledgment')]
    public function testAnythingElseStopsTheModule(array|string|null $apiResponse): void
    {
        $this->expectException(RuntimeException::class);
        ResultSyncAcknowledgement::fromResponse($apiResponse, 'vl');
    }
}
