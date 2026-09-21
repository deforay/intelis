<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use ArrayIterator;
use ArrayObject;
use JsonMachine\Items;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class IteratorKeyTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        // functions.php defines helpers already stubbed by tests/bootstrap.php.
        // Load the real lookup with the tokenizer, without booting the application.
        if (function_exists('_getIteratorKey')) {
            return;
        }
        $source = (string) file_get_contents(dirname(__DIR__, 3) . '/app/system/functions.php');
        $tokens = token_get_all($source);
        for ($i = 0, $count = count($tokens); $i < $count; $i++) {
            if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_FUNCTION) {
                continue;
            }
            $j = $i + 1;
            while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
                $j++;
            }
            if (!is_array($tokens[$j]) || $tokens[$j][1] !== '_getIteratorKey') {
                continue;
            }
            $body = '';
            $depth = 0;
            $started = false;
            for ($k = $i; $k < $count; $k++) {
                $text = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
                $body .= $text;
                if ($text === '{') {
                    $started = true;
                    $depth++;
                } elseif ($text === '}' && --$depth === 0 && $started) {
                    break;
                }
            }
            $tmp = tempnam(sys_get_temp_dir(), 'intelis-iterator-test-');
            if ($tmp === false) {
                self::fail('Could not create temporary helper file.');
            }
            try {
                file_put_contents($tmp, "<?php\nuse function iter\\toArray as iterToArray;\n" . $body);
                require $tmp;
            } finally {
                unlink($tmp);
            }
            return;
        }
        self::fail('Could not find the production iterator lookup.');
    }

    public function testReadsAppVersionFromTheJsonMachinePointerUsedByLegacyApis(): void
    {
        $items = Items::fromString('{"appVersion":"5.7.73","data":[]}', [
            'pointer' => '/appVersion',
            'decoder' => new ExtJsonDecoder(true),
        ]);
        self::assertSame('5.7.73', _getIteratorKey($items, 'appVersion'));
    }

    public function testOlderClientsCanOmitAppVersion(): void
    {
        $items = Items::fromString('{"data":[]}', [
            'pointer' => '/appVersion',
            'decoder' => new ExtJsonDecoder(true),
        ]);
        self::assertNull(_getIteratorKey($items, 'appVersion'));
    }

    public function testAcceptsIteratorAggregateWithoutDroppingItsKeys(): void
    {
        self::assertSame('5.7.73', _getIteratorKey(new ArrayObject(['appVersion' => '5.7.73']), 'appVersion'));
    }

    public function testAcceptsIteratorWithoutDroppingItsKeys(): void
    {
        self::assertSame('5.7.73', _getIteratorKey(new ArrayIterator(['appVersion' => '5.7.73']), 'appVersion'));
    }

    public function testLookupDoesNotConsumeRecordsAfterTheMatchingKey(): void
    {
        $items = (static function (): \Generator {
            yield 'appVersion' => '5.7.73';
            throw new \LogicException('Lookup consumed data after finding the key.');
        })();
        self::assertSame('5.7.73', _getIteratorKey($items, 'appVersion'));
    }

    #[DataProvider('values')]
    public function testPreservesValuesIncludingFalseAndZero(mixed $value): void
    {
        self::assertSame($value, _getIteratorKey(new ArrayIterator(['key' => $value]), 'key'));
    }

    /** @return list<array{mixed}> */
    public static function values(): array
    {
        return [[null], [false], [0], ['0'], [''], [['nested' => true]]];
    }

    public function testMissingKeyReturnsNull(): void
    {
        self::assertNull(_getIteratorKey(new ArrayIterator(['other' => 'value']), 'appVersion'));
    }

    public function testNumericKeysAreNotRenumbered(): void
    {
        self::assertSame('value', _getIteratorKey(new ArrayIterator([7 => 'value']), 7));
        self::assertSame('value', _getIteratorKey(new ArrayIterator([7 => 'value']), '7'));
    }

    public function testNonIteratorInputKeepsReturningNull(): void
    {
        self::assertNull(_getIteratorKey(null, 'appVersion'));
        self::assertNull(_getIteratorKey('invalid', 'appVersion'));
        self::assertNull(_getIteratorKey(['appVersion' => '5.7.73'], 'appVersion'));
    }

    public function testUnexpectedIteratorFailureIsNotHidden(): void
    {
        $items = (static function (): \Generator {
            yield 'other' => 'value';
            throw new \RuntimeException('source failed');
        })();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('source failed');
        _getIteratorKey($items, 'appVersion');
    }
}
