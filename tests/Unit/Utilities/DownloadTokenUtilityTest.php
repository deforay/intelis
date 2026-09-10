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

    public function testDownloadsStillWorkWhenTheKeyFileCannotBeOpened(): void
    {
        // A key file left behind by another account -- root, or a developer
        // whose own composer run touched it -- cannot be opened by the web
        // server, and every export used to 500 on it. Ownership cannot be
        // faked here without root, so a directory in the file's place stands in
        // for "exists, cannot be opened".
        //
        // The key is cached per process, so this has to be two fresh processes:
        // the property under test is that they agree without a file to agree
        // through, which is the only reason a derived key is usable at all.
        $keyFile = VAR_PATH . '/download-signing.key';
        $backup = is_file($keyFile) ? (string) file_get_contents($keyFile) : null;

        @unlink($keyFile);
        mkdir($keyFile);

        try {
            $token = trim($this->inAFreshProcess('echo DownloadTokenUtility::sign($f);'));
            $this->assertStringStartsWith(
                DownloadTokenUtility::TOKEN_PREFIX,
                $token,
                'A grant was not minted without a usable key file'
            );

            $resolved = trim($this->inAFreshProcess(
                'echo var_export(DownloadTokenUtility::resolve(' . var_export($token, true) . ') !== null, true);'
            ));
            $this->assertSame('true', $resolved, 'A grant minted in one process was rejected in another');
        } finally {
            rmdir($keyFile);
            if ($backup !== null) {
                file_put_contents($keyFile, $backup);
                chmod($keyFile, 0600);
            }
        }
    }

    /**
     * Runs a snippet in a new PHP process with the test bootstrap loaded, the
     * session user this class signs as, and $f pointing at the fixture file.
     */
    private function inAFreshProcess(string $snippet): string
    {
        $script = sprintf(
            '<?php require %s; use App\Utilities\DownloadTokenUtility; $_SESSION["userId"] = "42"; $f = %s; %s',
            var_export(dirname(__DIR__, 2) . '/bootstrap.php', true),
            var_export($this->file, true),
            $snippet
        );

        $path = VAR_PATH . '/download-token-probe.php';
        file_put_contents($path, $script);

        try {
            return (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($path) . ' 2>&1');
        } finally {
            @unlink($path);
        }
    }

    public function testAGrantForADeletedFileIsRefused(): void
    {
        $token = DownloadTokenUtility::sign($this->file);
        unlink($this->file);

        $this->assertNull(DownloadTokenUtility::resolve($token, $reason));
        $this->assertSame('missing_file', $reason);
    }
}
