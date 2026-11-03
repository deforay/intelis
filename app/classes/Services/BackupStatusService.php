<?php

declare(strict_types=1);

namespace App\Services;

/**
 * What an administrator should hear about the backups, for the dashboard.
 *
 * The backup runner writes /var/lib/intelis/backup-status.json after every run,
 * and bin/health.php turns it into an alert. Those alerts were never shown in the
 * browser, so a lab could run for months with no off-machine backup and nobody
 * would know. This reads the same file, plus the marker an unfinished restore of
 * the audit trail leaves, and returns one notice or none.
 *
 * Each notice carries a key. The dashboard hides a notice the viewer dismissed
 * until its key changes: a new problem, or a backup that has stayed old for
 * another day. A lab that has never set backups up is reminded once a week.
 */
final class BackupStatusService
{
    public const STATUS_FILE = '/var/lib/intelis/backup-status.json';
    public const RUNNER = '/usr/local/bin/intelis-backup.sh';

    /** The runner runs every 8 hours, so one missed run is not yet a finding. */
    public const STALE_HOURS = 24;

    /**
     * @return array{level: string, key: string, message: string}|null
     */
    public static function dashboardNotice(
        ?int $now = null,
        string $statusFile = self::STATUS_FILE,
        string $runner = self::RUNNER
    ): ?array {
        $now ??= time();
        $backup = self::backupNotice($now, $statusFile, $runner);

        // An unfinished restore of the audit trail is added to the backup's own
        // notice rather than replacing it, and both make up the key, so a backup
        // that starts failing after the restore notice was dismissed is shown.
        $marker = VAR_PATH . '/.audit-trail-restore-incomplete';
        $since = is_file($marker) ? @filemtime($marker) : false;
        if ($since === false || $now - $since < self::STALE_HOURS * 3600) {
            return $backup;
        }
        $restore = sprintf(
            _translate('A restore of the audit trail started on %s and has not finished. Audit archiving is paused until it does. To finish it, run intelis restore on the server and choose "Only the audit trail".'),
            date('Y-m-d', $since)
        );
        if ($backup === null) {
            return self::notice('warning', 'audit-restore|' . $since, $restore);
        }
        return self::notice(
            $backup['level'],
            $backup['key'] . '|audit-restore|' . $since,
            $backup['message'] . ' ' . $restore
        );
    }

    /**
     * @return array{level: string, key: string, message: string}|null
     */
    private static function backupNotice(int $now, string $statusFile, string $runner): ?array
    {
        if (!is_file($statusFile)) {
            if (is_file($runner)) {
                return self::notice(
                    'danger',
                    'never-run|' . date('o-W', $now),
                    _translate('Off-machine backup is set up, but no backup has run yet. On the server, run: sudo intelis backup')
                );
            }
            return self::notice(
                'warning',
                'not-setup|' . date('o-W', $now),
                _translate('Off-machine backup is not set up. If this machine fails, the lab data is lost. On the server, run: sudo intelis backup setup')
            );
        }

        $raw = @file_get_contents($statusFile);
        $state = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($state)) {
            return self::notice(
                'warning',
                'unreadable|' . date('Y-m-d', $now),
                _translate('The off-machine backup status cannot be read. On the server, run: sudo intelis backup status')
            );
        }

        $lastSuccess = (int) ($state['last_success_epoch'] ?? 0);
        $status = (string) ($state['status'] ?? '');
        $error = trim((string) ($state['last_error'] ?? ''));
        // Appended after any sprintf, so a % in the runner's message stays text.
        $reason = $error !== '' ? ' ' . _translate('Reason:') . ' ' . $error : '';

        if ($lastSuccess <= 0) {
            return self::notice(
                'danger',
                'never-finished|' . date('Y-m-d', $now),
                _translate('Off-machine backup is set up, but no backup has finished yet.') . $reason
            );
        }

        $hours = intdiv(max(0, $now - $lastSuccess), 3600);
        if ($hours >= self::STALE_HOURS) {
            $days = intdiv($hours, 24);
            return self::notice(
                'danger',
                'stale|' . $lastSuccess . '|' . $days,
                sprintf(
                    _translate('The last good off-machine backup was %d day(s) ago. On the server, run: sudo intelis backup status'),
                    $days
                ) . $reason
            );
        }

        if ($status === 'failed') {
            return self::notice(
                'warning',
                'failed|' . hash('sha256', $error),
                _translate('The last off-machine backup failed. The one before it worked.') . $reason
            );
        }

        if ($status === 'warning') {
            return self::notice(
                'warning',
                'warning|' . hash('sha256', $error),
                _translate('The last off-machine backup finished with a warning.') . $reason
            );
        }

        return null;
    }

    /**
     * @return array{level: string, key: string, message: string}
     */
    private static function notice(string $level, string $key, string $message): array
    {
        return ['level' => $level, 'key' => $key, 'message' => $message];
    }
}
