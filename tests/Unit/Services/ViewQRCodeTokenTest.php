<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CommonService;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * The token a printed report's QR code carries, back to the sample it names.
 *
 * view.php read a variable it never set, so every scan of a VL, EID, CD4,
 * hepatitis or Custom Tests report answered INVALID REQUEST.
 */
final class ViewQRCodeTokenTest extends TestCase
{
    private const INSTANCE_KEY_BYTES = 32;

    protected function setUp(): void
    {
        if (!defined('SYSTEM_CONFIG')) {
            define('SYSTEM_CONFIG', ['tryCrypt' => 'test-crypt-key16']);
        }
    }

    #[RunInSeparateProcess]
    public function testATokenFromEncryptViewQRCodeResolvesToItsSample(): void
    {
        $token = CommonService::encryptViewQRCode('uid-123');

        $this->assertSame('uid-123', CommonService::uniqueIdFromViewQRCode($token, null));
        // What arrives when the report wrote the token into the link without urlencode().
        $this->assertSame('uid-123', CommonService::uniqueIdFromViewQRCode(urldecode($token), null));
    }

    #[RunInSeparateProcess]
    public function testATokenStartingWithAPlusSurvivesALinkWithoutUrlencode(): void
    {
        do {
            $token = CommonService::encryptViewQRCode('uid-789');
        } while ($token[0] !== '+');

        $this->assertSame('uid-789', CommonService::uniqueIdFromViewQRCode(urldecode($token), null));
    }

    #[RunInSeparateProcess]
    public function testATokenEncryptedWithTheInstanceKeyResolvesToItsSample(): void
    {
        $key = random_bytes(self::INSTANCE_KEY_BYTES);
        $token = CommonService::encrypt('uid-456', $key);

        $this->assertSame('uid-456', CommonService::uniqueIdFromViewQRCode($token, base64_encode($key)));
    }

    #[RunInSeparateProcess]
    public function testAMissingOrForgedTokenResolvesToNothing(): void
    {
        $this->assertNull(CommonService::uniqueIdFromViewQRCode(null, null));
        $this->assertNull(CommonService::uniqueIdFromViewQRCode('', null));
        $this->assertNull(CommonService::uniqueIdFromViewQRCode(
            'forged-token',
            base64_encode(random_bytes(self::INSTANCE_KEY_BYTES))
        ));
    }
}
