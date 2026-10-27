<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\MiscUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

final class MiscUtilityVerbosityTest extends TestCase
{
    /** @return array<string, array{0: list<string>, 1: int}> */
    public static function argvs(): array
    {
        return [
            'no flag' => [['results-sender.php', '400', '--status=rejected'], OutputInterface::VERBOSITY_NORMAL],
            '-v' => [['results-sender.php', '400', '-v'], OutputInterface::VERBOSITY_VERBOSE],
            '--verbose' => [['results-sender.php', '--verbose'], OutputInterface::VERBOSITY_VERBOSE],
            '-vv' => [['results-sender.php', '-vv'], OutputInterface::VERBOSITY_VERY_VERBOSE],
            'the highest wins' => [['results-sender.php', '-vvv', '-v'], OutputInterface::VERBOSITY_DEBUG],
            'a value that only looks like one' => [
                ['results-sender.php', 'v', '-version'],
                OutputInterface::VERBOSITY_NORMAL,
            ],
        ];
    }

    /** @param list<string> $argv */
    #[DataProvider('argvs')]
    public function testVerbosityComesFromTheFlags(array $argv, int $expected): void
    {
        $this->assertSame($expected, MiscUtility::verbosityFromArgv($argv));
    }
}
