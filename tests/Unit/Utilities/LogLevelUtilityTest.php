<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\LogLevelUtility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LogLevelUtilityTest extends TestCase
{
    public static function logLineProvider(): array
    {
        return [
            'lowercase error' => ['2026-01-01 an error occurred', 'error'],
            'uppercase ERROR is still detected' => ['2026-01-01 ERROR occurred', 'error'],
            'exception keyword maps to error' => ['Uncaught Exception in file.php', 'error'],
            'fatal keyword maps to error' => ['Fatal error: undefined function', 'error'],
            'warn keyword maps to warning' => ['Deprecation warn: old syntax', 'warning'],
            'info keyword maps to info' => ['Info: request completed', 'info'],
            'debug keyword maps to debug' => ['Debug: variable dump', 'debug'],
            'no matching keyword defaults to info' => ['Just a plain log line', 'info'],
            'empty string defaults to info' => ['', 'info'],
            'error takes priority when both error and warn are present' => [
                'error: something failed after a warn was logged',
                'error',
            ],
        ];
    }

    #[DataProvider('logLineProvider')]
    public function testDetectLogLevel(string $line, string $expected): void
    {
        $this->assertSame($expected, LogLevelUtility::detectLogLevel($line));
    }
}