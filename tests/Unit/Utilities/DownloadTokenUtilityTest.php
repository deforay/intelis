<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\DownloadTokenUtility;
use PHPUnit\Framework\TestCase;

final class DownloadTokenUtilityTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $_SESSION['userId'] = '42';

        $this->file = TEMP_PATH . DIRECTORY_SEPARATOR . 'HIV-VL-Test-Result-grant-test.pdf';
        file_put_contents($this->file, '%PDF-1.4 test');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        $_SESSION = [];
    }

    public function testAGrantRoundTripsToTheFileItNames(): void
    {
        $token = DownloadTokenUtility::sign($this->file);

        $this->assertNotSame('', $token);
        $this->assertSame(realpath($this->file), DownloadTokenUtility::resolve($token));
    }

    public function testAFileNameRelativeToARootIsAccepted(): void
    {
        // Producers echo both shapes: some an absolute path, some a bare basename.
        $token = DownloadTokenUtility::sign(basename($this->file));

        $this->assertSame(realpath($this->file), DownloadTokenUtility::resolve($token));
    }

    public function testTheTokenSurvivesAQueryStringUnescaped(): void
    {
        // The calling JavaScript concatenates this straight into
        // '/download.php?f=' + data with no encoding, so it has to be URL-safe.
        $token = DownloadTokenUtility::sign($this->file);

        $this->assertSame($token, urlencode($token));
    }

    public function testAnExpiredGrantIsRefused(): void
    {
        $token = DownloadTokenUtility::sign($this->file, -1);

        $this->assertNull(DownloadTokenUtility::resolve($token, $reason));
        $this->assertSame('expired', $reason);
    }

    public function testATamperedPayloadIsRefused(): void
    {
        $token = DownloadTokenUtility::sign($this->file);

        [$prefix, $payload, $signature] = explode('.', $token);
        $forged = json_decode(
            base64_decode(strtr($payload, '-_', '+/'), true),
            true
        );
        $forged['f'] = 'someone-elses-export.xlsx';
        $forgedPayload = rtrim(strtr(base64_encode(json_encode($forged)), '+/', '-_'), '=');

        $this->assertNull(
            DownloadTokenUtility::resolve($prefix . '.' . $forgedPayload . '.' . $signature, $reason)
        );
        $this->assertSame('bad_signature', $reason);
    }

    public function testAGrantIsUselessToAnotherUser(): void
    {
        $token = DownloadTokenUtility::sign($this->file);

        // Same server, same key, different session: a leaked URL stays inert.
        $_SESSION['userId'] = '43';

        $this->assertNull(DownloadTokenUtility::resolve($token, $reason));
        $this->assertSame('wrong_user', $reason);
    }

    public function testAFileOutsideTheTrustedRootsIsNotSigned(): void
    {
        $this->assertSame('', DownloadTokenUtility::sign(ROOT_PATH . '/composer.json'));
        $this->assertSame('', DownloadTokenUtility::sign('/etc/passwd'));
    }

    public function testTraversalCannotEscapeTheRoot(): void
    {
        $this->assertSame('', DownloadTokenUtility::sign('../../composer.json'));
    }

    public function testLegacyValuesAreNotMistakenForGrants(): void
    {
        $this->assertFalse(DownloadTokenUtility::looksLikeToken(
            base64_encode('/var/www/intelis/public/temporary/report.xlsx')
        ));
        $this->assertFalse(DownloadTokenUtility::looksLikeToken('InteLIS-VIRAL-LOAD-Data.xlsx'));
        $this->assertTrue(DownloadTokenUtility::looksLikeToken(
            DownloadTokenUtility::sign($this->file)
        ));
    }

    public function testAGarbledGrantIsRefusedRatherThanThrowing(): void
    {
        $this->assertNull(DownloadTokenUtility::resolve('dl1.not-a-token', $reason));
        $this->assertSame('malformed', $reason);

        $this->assertNull(DownloadTokenUtility::resolve('dl1...', $reason));
        $this->assertSame('malformed', $reason);

        $this->assertNull(DownloadTokenUtility::resolve('dl1.abc.def', $reason));
        $this->assertSame('bad_signature', $reason);
    }

    public function testTheSigningKeyIsNotReadableByOtherUsers(): void
    {
        DownloadTokenUtility::sign($this->file);

        $keyFile = VAR_PATH . '/download-signing.key';
        $this->assertFileExists($keyFile);

        // Anyone who can read this key can forge a grant for any file and any
        // user, so the key must never be group- or world-readable -- including
        // in the window between the file being created and being written.
        clearstatcache(true, $keyFile);
        $this->assertSame(0, fileperms($keyFile) & 0077, 'Signing key is readable by other users');
    }

    public function testAGrantForADeletedFileIsRefused(): void
    {
        $token = DownloadTokenUtility::sign($this->file);
        unlink($this->file);

        $this->assertNull(DownloadTokenUtility::resolve($token, $reason));
        $this->assertSame('missing_file', $reason);
    }
}
