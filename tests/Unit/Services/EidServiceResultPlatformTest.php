<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\EidService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Whether an EID web save writes a result with no testing platform, which the save
 * helpers refuse. The request forms let a result be saved without one.
 */
final class EidServiceResultPlatformTest extends TestCase
{
    private const PLATFORM = 'Abbott##20';

    /** @return array<string, array{array<string, mixed>}> */
    public static function refused(): array
    {
        return [
            'result, no platform field' => [['isSampleRejected' => 'no', 'result' => 'positive']],
            'result, platform empty' => [['isSampleRejected' => 'no', 'result' => 'negative', 'eidPlatform' => '']],
            'result, platform null' => [['isSampleRejected' => 'no', 'result' => 'negative', 'eidPlatform' => null]],
            'result, platform blank' => [['isSampleRejected' => 'no', 'result' => 'negative', 'eidPlatform' => '  ']],
            'rejection unanswered' => [['result' => 'indeterminate', 'eidPlatform' => '']],
            'rejection answer empty' => [['isSampleRejected' => '', 'result' => 'positive']],
        ];
    }

    /** @param array<string, mixed> $post */
    #[DataProvider('refused')]
    public function testRefusesAResultWithoutAPlatform(array $post): void
    {
        self::assertTrue(EidService::refusesResultWithoutPlatform($post));
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function allowed(): array
    {
        return [
            'result with a platform' => [
                ['isSampleRejected' => 'no', 'result' => 'positive', 'eidPlatform' => self::PLATFORM],
            ],
            'rejection without a platform' => [['isSampleRejected' => 'yes', 'result' => null, 'eidPlatform' => '']],
            // The result field still holds a value when the sample is rejected
            'rejection with a result left in the field' => [['isSampleRejected' => 'yes', 'result' => 'positive']],
            'result cleared without a platform' => [['isSampleRejected' => 'no', 'result' => '', 'eidPlatform' => '']],
            'result null without a platform' => [['isSampleRejected' => 'no', 'result' => null]],
            'result blank without a platform' => [['isSampleRejected' => 'no', 'result' => '   ']],
            // A form that did not send the result keeps the stored one
            'result not sent' => [['isSampleRejected' => 'no', 'eidPlatform' => '']],
            'nothing sent' => [[]],
        ];
    }

    /** @param array<string, mixed> $post */
    #[DataProvider('allowed')]
    public function testAllowsEverySaveThatWritesNoResultOrHasAPlatform(array $post): void
    {
        self::assertFalse(EidService::refusesResultWithoutPlatform($post));
    }
}
