<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Backup health for the STS fleet view.
 *
 * The backup runner writes /var/lib/intelis/backup-status.json after every run.
 * A lab reads it, plus the marker an unfinished restore of the audit trail
 * leaves, and sends the result to the STS with its metadata sync. Off-machine
 * backup is optional, so labs are not warned about it in the browser.
 */
final class BackupStatusService
{
    public const STATUS_FILE = '/var/lib/intelis/backup-status.json';
    public const RUNNER = '/usr/local/bin/intelis-backup.sh';

    /** The runner runs every 8 hours, so one missed run is not yet a finding. */
    public const STALE_HOURS = 24;

    /**
     * What a lab tells the STS about its backups, with its metadata sync.
     *
     * Ages are worked out here, on the lab, in hours. A lab's clock can be far
     * out (a flat CMOS battery is common), so a timestamp read on the STS would
     * be wrong by the same amount, while an age measured on one clock is not.
     *
     * @return array<string, mixed>
     */
    public static function reportForSts(
        ?int $now = null,
        string $statusFile = self::STATUS_FILE,
        string $runner = self::RUNNER
    ): array {
        $now ??= time();
        $report = ['setUp' => is_file($runner) || is_file($statusFile)];

        $marker = VAR_PATH . '/.audit-trail-restore-incomplete';
        $since = is_file($marker) ? @filemtime($marker) : false;
        if ($since !== false) {
            $report['auditRestoreHours'] = intdiv(max(0, $now - $since), 3600);
        }

        if (!is_file($statusFile)) {
            $report['status'] = $report['setUp'] ? 'never' : 'not-setup';
            return $report;
        }
        $raw = @file_get_contents($statusFile);
        $state = is_string($raw) ? json_decode($raw, true) : null;
        if (!is_array($state)) {
            $report['status'] = 'unreadable';
            return $report;
        }

        $lastSuccess = (int) ($state['last_success_epoch'] ?? 0);
        $report['status'] = (string) ($state['status'] ?? 'unknown');
        $report['lastSuccessHours'] = $lastSuccess > 0 ? intdiv(max(0, $now - $lastSuccess), 3600) : null;
        $report['lastError'] = (string) ($state['last_error'] ?? '');
        $report['destination'] = (string) ($state['destination'] ?? '');
        return $report;
    }

    /**
     * The lab's report as the STS keeps it: known fields only, each of a fixed
     * type and length, so a lab cannot put anything else into facility_attributes.
     *
     * @return array<string, mixed>|null
     */
    public static function sanitizeReport(mixed $report): ?array
    {
        if (!is_array($report)) {
            return null;
        }
        $statuses = ['ok', 'failed', 'warning', 'never', 'not-setup', 'unreadable', 'unknown'];
        $status = is_string($report['status'] ?? null) && in_array($report['status'], $statuses, true)
            ? $report['status'] : 'unknown';
        $hours = static fn($v): ?int => is_int($v) && $v >= 0 ? min($v, 1_000_000) : null;
        $destination = is_string($report['destination'] ?? null)
            && in_array($report['destination'], ['ssh', 'smb', 'local'], true) ? $report['destination'] : null;

        return [
            'setUp' => !empty($report['setUp']),
            'status' => $status,
            'lastSuccessHours' => $hours($report['lastSuccessHours'] ?? null),
            'lastError' => is_string($report['lastError'] ?? null) ? mb_substr($report['lastError'], 0, 300) : '',
            'destination' => $destination,
            'auditRestoreHours' => $hours($report['auditRestoreHours'] ?? null),
        ];
    }

    /**
     * One status chip for the STS lab sync status page.
     *
     * $seenAt is when the STS received the report. Its age is added to the
     * lab's own figures, so a lab that stopped syncing does not keep showing
     * the backup age it had when it last reported.
     *
     * @param array<string, mixed>|null $report as stored by sanitizeReport()
     * @return array{class: string, label: string, title: string}
     */
    public static function stsChip(?array $report, ?string $seenAt, ?int $now = null): array
    {
        $now ??= time();
        $seen = !empty($seenAt) && $seenAt !== 'null' ? strtotime($seenAt) : false;
        if ($report === null || $seen === false) {
            return [
                'class' => 'plane-never',
                'label' => _translate('not reported'),
                'title' => _translate(
                    'This lab has not reported its backups. It runs an older version, or has not synced since updating.'
                ),
            ];
        }

        $sinceReport = intdiv(max(0, $now - $seen), 3600);
        $age = static fn(int $h): string => $h < 24 ? $h . 'h' : intdiv($h, 24) . 'd';
        $title = sprintf(_translate('Reported %s ago.'), $age($sinceReport));
        if (($report['lastError'] ?? '') !== '') {
            $title .= ' ' . _translate('Reason:') . ' ' . $report['lastError'];
        }
        if (isset($report['auditRestoreHours'])) {
            $title .= ' ' . _translate('A restore of the audit trail has not finished.');
        }

        $status = (string) ($report['status'] ?? 'unknown');
        if ($status === 'not-setup') {
            return ['class' => 'plane-stale', 'label' => _translate('not set up'), 'title' => $title];
        }
        if ($status === 'never' || $status === 'unreadable' || $status === 'unknown') {
            return ['class' => 'plane-stale', 'label' => _translate('no backup yet'), 'title' => $title];
        }
        $lastSuccess = $report['lastSuccessHours'] ?? null;
        if (!is_int($lastSuccess)) {
            return ['class' => 'plane-stale', 'label' => _translate('no backup yet'), 'title' => $title];
        }

        $hours = $lastSuccess + $sinceReport;
        $label = sprintf(_translate('last good %s ago'), $age($hours));
        if ($hours >= self::STALE_HOURS) {
            return ['class' => 'plane-stale', 'label' => $label, 'title' => $title];
        }
        if ($status === 'failed' || $status === 'warning' || isset($report['auditRestoreHours'])) {
            return ['class' => 'plane-late', 'label' => $label, 'title' => $title];
        }
        return ['class' => 'plane-ok', 'label' => $label, 'title' => $title];
    }
}
