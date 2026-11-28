<?php

declare(strict_types=1);

namespace App\Utilities;

use App\Services\ActivityLogFeedService;

/**
 * Extracted from monitoring/get-activity-log.php's activityActionType() and
 * activityInitials(). That file executes request-handling code at the top level, so it
 * can't be require'd directly in a test -- moved here to be testable in isolation.
 */
final class ActivityLogUtility
{
    public static function activityActionType(string $eventType): string
    {
        $e = strtolower($eventType);
        return match (true) {
            str_contains($e, 'login') && str_contains($e, 'fail') => 'login-fail',
            str_contains($e, 'log-out'), str_contains($e, 'logout') => 'logout',
            str_contains($e, 'login') => 'login',
            str_contains($e, 'delete'), str_contains($e, 'remove') => 'delete',
            str_contains($e, 'import') => 'import',
            str_contains($e, 'add'), str_contains($e, 'create') => 'create',
            str_contains($e, 'update'), str_contains($e, 'edit'), str_contains($e, 'modif') => 'update',
            str_contains($e, 'export'), str_contains($e, 'download') => 'download',
            str_contains($e, 'mail'), str_contains($e, 'email'), str_contains($e, 'sent') => 'message',
            default => 'other',
        };
    }

    /** 1-2 uppercase initials from a name; '?' when blank. */
    public static function activityInitials(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '?';
        }
        $out = '';
        foreach (preg_split('/\s+/', $name) ?: [] as $p) {
            if ($p !== '' && ctype_alpha(substr($p, 0, 1))) {
                $out .= strtoupper(substr($p, 0, 1));
            }
            if (strlen($out) >= 2) {
                break;
            }
        }
        return $out !== '' ? $out : strtoupper(substr($name, 0, 1));
    }

    /**
     * A "History" link that opens the User Activity Log filtered to one record,
     * for the heading of that record's edit page. Empty for a user who cannot
     * open the log, so a page never shows a link that leads to an error.
     */
    public static function historyLink(string $entityType, int|string $entityId, string $label = ''): string
    {
        $entityId = (string) $entityId;
        if ($entityType === '' || $entityId === '' || $entityId === '0'
            || !function_exists('_isAllowed') || !_isAllowed('/admin/monitoring/activity-log.php')) {
            return '';
        }
        $typeLabel = ActivityLogFeedService::labelFor($entityType);
        $url = '/admin/monitoring/activity-log.php?' . http_build_query([
            'entityType' => $entityType,
            'entityId' => $entityId,
            'entityLabel' => $label === '' ? $typeLabel : "$typeLabel: $label",
        ]);
        return '<a class="btn btn-default btn-sm" style="margin-left:10px;" href="' . htmlspecialchars($url, ENT_QUOTES)
            . '" title="' . htmlspecialchars(_translate('Show every activity log entry for this item'), ENT_QUOTES) . '">'
            . '<em class="fa-solid fa-clock-rotate-left"></em> ' . htmlspecialchars(_translate('History'), ENT_QUOTES) . '</a>';
    }
}
