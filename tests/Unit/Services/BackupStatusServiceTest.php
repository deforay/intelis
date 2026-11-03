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

    /** @return array{level: string, key: string, message: string}|null */
    private function notice(int $now = self::NOW): ?array
    {
        return BackupStatusService::dashboardNotice($now, $this->status, $this->runner);
    }

    /** @param array<string, mixed> $state */
    private function writeStatus(array $state): void
    {
        file_put_contents($this->status, json_encode($state));
    }

    public function testNotSetUpIsAWarningThatComesBackEachWeek(): void
    {
        $first = $this->notice();
        $sameWeek = $this->notice(self::NOW + 3600);
        $nextWeek = $this->notice(self::NOW + 8 * 86400);

        $this->assertSame('warning', $first['level'] ?? null);
        $this->assertStringContainsString('not set up', $first['message']);
        $this->assertSame($first['key'], $sameWeek['key'] ?? null);
        $this->assertNotSame($first['key'], $nextWeek['key'] ?? null);
    }

    public function testRunnerWithoutAnyRunIsDanger(): void
    {
        touch($this->runner);

        $this->assertSame('danger', $this->notice()['level'] ?? null);
    }

    public function testUnreadableStatusIsAWarning(): void
    {
        file_put_contents($this->status, '{half a file');

        $this->assertStringContainsString('cannot be read', $this->notice()['message'] ?? '');
    }

    public function testNoBackupEverFinishedShowsTheReason(): void
    {
        $this->writeStatus(['status' => 'failed', 'last_success_epoch' => 0, 'last_error' => 'Cannot reach it']);

        $notice = $this->notice();

        $this->assertSame('danger', $notice['level'] ?? null);
        $this->assertStringContainsString('Cannot reach it', $notice['message']);
    }

    public function testRecentGoodBackupShowsNothing(): void
    {
        $this->writeStatus(['status' => 'ok', 'last_success_epoch' => self::NOW - 3 * 3600, 'last_error' => '']);

        $this->assertNull($this->notice());
    }

    public function testOldBackupIsDangerAndComesBackEachDay(): void
    {
        $this->writeStatus(['status' => 'failed', 'last_success_epoch' => self::NOW - 180000, 'last_error' => '100%']);

        $today = $this->notice();
        $tomorrow = $this->notice(self::NOW + 86400);

        $this->assertSame('danger', $today['level'] ?? null);
        $this->assertStringContainsString('2 day(s)', $today['message']);
        // A % in the runner's message is kept as text, not read as a format.
        $this->assertStringContainsString('Reason: 100%', $today['message']);
        $this->assertNotSame($today['key'], $tomorrow['key'] ?? null);
    }

    public function testOneFailedRunAfterAGoodOneIsAWarningKeyedByItsReason(): void
    {
        $failed = ['status' => 'failed', 'last_success_epoch' => self::NOW - 9 * 3600];
        $this->writeStatus($failed + ['last_error' => 'Disk full']);
        $first = $this->notice();
        $again = $this->notice(self::NOW + 8 * 3600);
        $this->writeStatus($failed + ['last_error' => 'Other']);
        $other = $this->notice();

        $this->assertSame('warning', $first['level'] ?? null);
        $this->assertSame($first['key'], $again['key'] ?? null);
        $this->assertNotSame($first['key'], $other['key'] ?? null);
    }

    public function testWarningRunIsShown(): void
    {
        $this->writeStatus([
            'status' => 'warning',
            'last_success_epoch' => self::NOW - 3600,
            'last_error' => 'The audit trail in the backup was not changed.',
        ]);

        $this->assertStringContainsString('with a warning', $this->notice()['message'] ?? '');
    }

    public function testUnfinishedAuditRestoreIsReportedAfterADay(): void
    {
        $this->writeStatus(['status' => 'ok', 'last_success_epoch' => self::NOW - 3600, 'last_error' => '']);
        touch($this->marker, self::NOW - 3600);
        $this->assertNull($this->notice());

        touch($this->marker, self::NOW - 30 * 3600);
        $restoreOnly = $this->notice();
        $this->assertStringContainsString('Only the audit trail', $restoreOnly['message'] ?? '');

        // A backup failure that follows still shows, even if the restore notice
        // was dismissed: the key changes and both messages are there.
        $this->writeStatus(['status' => 'failed', 'last_success_epoch' => self::NOW - 60, 'last_error' => 'Disk full']);
        $both = $this->notice();
        $this->assertNotSame($restoreOnly['key'], $both['key'] ?? null);
        $this->assertStringContainsString('Disk full', $both['message']);
        $this->assertStringContainsString('Only the audit trail', $both['message']);
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
