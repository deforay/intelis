<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BackupStatusService;
use PHPUnit\Framework\TestCase;

final class BackupStatusServiceTest extends TestCase
{
    private const NOW = 1_800_000_000;

    private string $dir;
    private string $status;
    private string $runner;
    private string $marker;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/backup-status-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->status = $this->dir . '/backup-status.json';
        $this->runner = $this->dir . '/intelis-backup.sh';
        $this->marker = VAR_PATH . '/.audit-trail-restore-incomplete';
        @unlink($this->marker);
    }

    protected function tearDown(): void
    {
        @unlink($this->marker);
        array_map('unlink', glob($this->dir . '/*') ?: []);
        rmdir($this->dir);
    }

    /** @param array<string, mixed> $state */
    private function writeStatus(array $state): void
    {
        file_put_contents($this->status, json_encode($state));
    }

    public function testReportForStsWhenNotSetUp(): void
    {
        $report = BackupStatusService::reportForSts(self::NOW, $this->status, $this->runner);

        $this->assertSame(['setUp' => false, 'status' => 'not-setup'], $report);
    }

    public function testReportForStsMeasuresAgesOnTheLab(): void
    {
        $this->writeStatus([
            'status' => 'ok',
            'last_success_epoch' => self::NOW - 5 * 3600,
            'last_error' => '',
            'destination' => 'ssh',
        ]);
        touch($this->marker, self::NOW - 30 * 3600);

        $report = BackupStatusService::reportForSts(self::NOW, $this->status, $this->runner);

        $this->assertTrue($report['setUp']);
        $this->assertSame('ok', $report['status']);
        $this->assertSame(5, $report['lastSuccessHours']);
        $this->assertSame(30, $report['auditRestoreHours']);
        $this->assertSame('ssh', $report['destination']);
    }

    public function testSanitizeKeepsOnlyKnownFieldsOfTheRightShape(): void
    {
        $clean = BackupStatusService::sanitizeReport([
            'setUp' => 1,
            'status' => 'rm -rf /',
            'lastSuccessHours' => '12',
            'lastError' => str_repeat('x', 1000),
            'destination' => 'ftp',
            'auditRestoreHours' => -4,
            'extra' => ['anything'],
        ]);

        $this->assertSame(
            ['setUp', 'status', 'lastSuccessHours', 'lastError', 'destination', 'auditRestoreHours'],
            array_keys($clean)
        );
        $this->assertTrue($clean['setUp']);
        $this->assertSame('unknown', $clean['status']);
        $this->assertNull($clean['lastSuccessHours']);
        $this->assertSame(300, mb_strlen($clean['lastError']));
        $this->assertNull($clean['destination']);
        $this->assertNull($clean['auditRestoreHours']);
        $this->assertNull(BackupStatusService::sanitizeReport('not an array'));
    }

    public function testStsChipForEachState(): void
    {
        $seen = date('Y-m-d H:i:s', self::NOW - 3600);
        $chip = static fn(?array $r, ?string $at = null): array
            => BackupStatusService::stsChip($r, $at ?? $seen, self::NOW);
        $report = static fn(string $status, ?int $hours, string $error = ''): array
            => BackupStatusService::sanitizeReport([
                'setUp' => true, 'status' => $status, 'lastSuccessHours' => $hours, 'lastError' => $error,
            ]);

        $this->assertSame('plane-never', $chip(null)['class']);
        $this->assertSame('plane-stale', $chip(['status' => 'not-setup'])['class']);
        $this->assertSame('plane-stale', $chip($report('never', null))['class']);
        $this->assertSame('plane-ok', $chip($report('ok', 2))['class']);
        $this->assertStringContainsString('3h', $chip($report('ok', 2))['label']);
        $failed = $chip($report('failed', 5, 'Disk full'));
        $this->assertSame('plane-late', $failed['class']);
        $this->assertStringContainsString('Disk full', $failed['title']);
        $this->assertSame('plane-stale', $chip($report('ok', 30))['class']);

        // A good backup reported three days ago is not good now: the lab has
        // stopped syncing, and the age keeps counting from the report.
        $old = $chip($report('ok', 1), date('Y-m-d H:i:s', self::NOW - 3 * 86400));
        $this->assertSame('plane-stale', $old['class']);
        $this->assertStringContainsString('3d', $old['label']);
    }
}
