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
}
