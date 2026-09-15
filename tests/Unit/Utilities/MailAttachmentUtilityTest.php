<?php

declare(strict_types=1);

namespace Tests\Unit\Utilities;

use App\Utilities\DownloadTokenUtility;
use App\Utilities\MailAttachmentUtility;
use PHPUnit\Framework\TestCase;

final class MailAttachmentUtilityTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        $_SESSION['userId'] = '42';
        $this->file = TEMP_PATH . DIRECTORY_SEPARATOR . 'intelis-result-attachment-test.pdf';
        file_put_contents($this->file, '%PDF-1.4 test');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        $_SESSION = [];
    }

    public function testADownloadGrantResolvesToItsFile(): void
    {
        $token = DownloadTokenUtility::sign($this->file);
        $this->assertSame(realpath($this->file), MailAttachmentUtility::resolve($token));
    }

    public function testTheLegacyBase64PathResolvesToItsFile(): void
    {
        $this->assertSame(realpath($this->file), MailAttachmentUtility::resolve(base64_encode($this->file)));
    }

    public function testABareFileNameResolvesInsideTheTemporaryFolder(): void
    {
        $this->assertSame(realpath($this->file), MailAttachmentUtility::resolve(basename($this->file)));
    }

    public function testAPathOutsideTheAllowedFoldersIsRefused(): void
    {
        $this->assertNull(MailAttachmentUtility::resolve(base64_encode(ROOT_PATH . '/composer.json')));
        $this->assertNull(MailAttachmentUtility::resolve(base64_encode('/etc/passwd')));
    }

    public function testClimbingOutOfTheTemporaryFolderIsRefused(): void
    {
        $this->assertNull(MailAttachmentUtility::resolve('../../composer.json'));
        $this->assertNull(MailAttachmentUtility::resolve(base64_encode(TEMP_PATH . '/../../composer.json')));
    }

    public function testMissingAndEmptyValuesAttachNothing(): void
    {
        $this->assertNull(MailAttachmentUtility::resolve(null));
        $this->assertNull(MailAttachmentUtility::resolve(''));
        $this->assertNull(MailAttachmentUtility::resolve('no-such-file.pdf'));
        $this->assertNull(MailAttachmentUtility::queueValue('no-such-file.pdf'));
    }

    public function testTheQueueValueIsTheBase64PathTheMailSenderReads(): void
    {
        $queued = MailAttachmentUtility::queueValue(DownloadTokenUtility::sign($this->file));
        $this->assertSame(realpath($this->file), base64_decode((string) $queued));
    }
}
