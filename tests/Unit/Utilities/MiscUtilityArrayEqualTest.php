<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\MiscUtility;
use PHPUnit\Framework\TestCase;

/**
 * isArrayEqual() is how the syncs decide whether an incoming record changes
 * anything. Saying "changed" wrongly bumps last_modified_datetime (grids sort by
 * it); saying "unchanged" wrongly loses the change.
 */
final class MiscUtilityArrayEqualTest extends TestCase
{
    public function testTheSameValuesInAnotherOrderAreEqual(): void
    {
        self::assertTrue(MiscUtility::isArrayEqual(['a' => 1, 'b' => 'x'], ['b' => 'x', 'a' => 1]));
    }

    public function testTheSmallerArrayIsComparedAgainstTheLarger(): void
    {
        // A table row has more columns than the incoming record.
        self::assertTrue(MiscUtility::isArrayEqual(['a' => 1], ['id' => 9, 'a' => 1, 'b' => 2]));
        self::assertTrue(MiscUtility::isArrayEqual(['id' => 9, 'a' => 1, 'b' => 2], ['a' => 1]));
    }

    public function testAnyDifferentValueIsAChange(): void
    {
        self::assertFalse(MiscUtility::isArrayEqual(['a' => 1, 'b' => 'x'], ['a' => 1, 'b' => 'y', 'c' => 3]));
    }

    public function testTypesCount(): void
    {
        self::assertFalse(MiscUtility::isArrayEqual(['a' => '1'], ['a' => 1, 'b' => 2]));
        self::assertFalse(MiscUtility::isArrayEqual(['a' => null], ['a' => '', 'b' => 2]));
    }

    public function testAKeyMissingFromTheLargerArrayIsAChange(): void
    {
        self::assertFalse(MiscUtility::isArrayEqual(['a' => 1, 'z' => null], ['a' => 1, 'b' => 2, 'c' => 3]));
    }

    public function testExcludedKeysAreIgnored(): void
    {
        self::assertTrue(MiscUtility::isArrayEqual(
            ['a' => 1, 'last_modified_datetime' => 'now'],
            ['a' => 1, 'last_modified_datetime' => 'then'],
            ['last_modified_datetime']
        ));
    }
}
