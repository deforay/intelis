<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\ResendRequestsArgs;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * A queued resend-requests command, as the arguments the lab runs
 * requests-receiver.php with. The days the STS's dialog asks for used to be
 * dropped, so the lab pulled only what the STS still had waiting.
 */
final class ResendRequestsArgsTest extends TestCase
{
    public function testTheDaysArePassedOn(): void
    {
        self::assertSame(['90'], ResendRequestsArgs::from(['days' => 90]));
        self::assertSame(['-t', 'vl', '90'], ResendRequestsArgs::from(['module' => 'vl', 'days' => '90']));
    }

    public function testModuleAndManifestAreKept(): void
    {
        self::assertSame(
            ['-t', 'vl', '-m', 'VL26080519MHWZ'],
            ResendRequestsArgs::from(['module' => 'vl', 'manifest' => 'VL26080519MHWZ'])
        );
        self::assertSame([], ResendRequestsArgs::from([]));
        self::assertSame([], ResendRequestsArgs::from(['days' => '']));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function refused(): iterable
    {
        yield 'unknown module' => [['module' => 'vl; rm -rf /']];
        yield 'bad manifest code' => [['manifest' => 'VL 1']];
        yield 'zero days' => [['days' => 0]];
        yield 'too many days' => [['days' => 3651]];
        yield 'days not a number' => [['days' => '90 --dry-run']];
    }

    /** @param array<string, mixed> $params */
    #[\PHPUnit\Framework\Attributes\DataProvider('refused')]
    public function testAParamTheLabMustNotActOnIsRefused(array $params): void
    {
        $this->expectException(InvalidArgumentException::class);
        ResendRequestsArgs::from($params);
    }
}
