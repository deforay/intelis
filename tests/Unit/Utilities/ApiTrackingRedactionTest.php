<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\ApiTrackingStorageUtility;
use PHPUnit\Framework\TestCase;

/**
 * What a tracked API body keeps of its secrets: nothing.
 *
 * The mobile login's body held the password in plain text and its response the
 * user's API token, and both were kept for 30 days under var/track-api.
 */
final class ApiTrackingRedactionTest extends TestCase
{
    private const R = ApiTrackingStorageUtility::REDACTED;

    public function testLoginRequestLosesItsPassword(): void
    {
        $kept = ApiTrackingStorageUtility::redactSecrets(
            '{"userName":"labtech","password":"s3cret","appVersion":"2.1.2"}'
        );

        self::assertSame(
            ['userName' => 'labtech', 'password' => self::R, 'appVersion' => '2.1.2'],
            json_decode($kept, true)
        );
    }

    public function testLoginResponseLosesItsTokensAtAnyDepth(): void
    {
        $kept = ApiTrackingStorageUtility::redactSecrets([
            'status' => 1,
            'data' => [
                'user' => ['user_id' => 'u1', 'api_token' => 'abc'],
                'api_token' => 'abc',
                'new_token' => 'def',
            ],
            'token' => 'ghi',
            'token_updated' => true,
        ]);

        self::assertSame([
            'status' => 1,
            'data' => [
                'user' => ['user_id' => 'u1', 'api_token' => self::R],
                'api_token' => self::R,
                'new_token' => self::R,
            ],
            'token' => self::R,
            'token_updated' => true,
        ], $kept);
    }

    public function testOtherSecretKeysAreCaughtWhateverTheirCase(): void
    {
        $kept = ApiTrackingStorageUtility::redactSecrets(
            ['newPassword' => 'a', 'client_secret' => 'b', 'authToken' => 'c', 'list' => [['Password' => 'd']]]
        );

        self::assertSame(
            [
                'newPassword' => self::R,
                'client_secret' => self::R,
                'authToken' => self::R,
                'list' => [['Password' => self::R]],
            ],
            $kept
        );
    }

    public function testBodiesWithoutSecretsAreKeptAsTheyCame(): void
    {
        $body = '{"sampleCode":"VL001","result":"<20","tokens":3}';

        self::assertSame($body, ApiTrackingStorageUtility::redactSecrets($body));
        self::assertSame('not json, password', ApiTrackingStorageUtility::redactSecrets('not json, password'));
        self::assertNull(ApiTrackingStorageUtility::redactSecrets(null));
    }

    public function testAnEmptyPasswordStaysEmpty(): void
    {
        self::assertSame(['password' => ''], ApiTrackingStorageUtility::redactSecrets(['password' => '']));
    }
}
