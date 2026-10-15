<?php

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use App\Services\DatabaseService;
use App\Utilities\RunOnceUtility;
use App\Utilities\ApiTrackingStorageUtility;

/*
 * Deletes the kept request and response bodies of mobile logins and profile saves.
 *
 * Until addApiTracking() redacted secrets, a login's request body held the user's
 * password in plain text and its response the user's API token, and a profile
 * save's request held the new password. Those bodies sit under var/track-api for
 * 30 days. The track_api_requests rows hold no secret and are left alone; only
 * the files go, so the monitoring page shows "no body kept" for those calls.
 *
 * Looks up each file by name in its day folder and the old flat folder, never by
 * listing a folder, which on a busy STS can hold millions of files.
 */
RunOnceUtility::run(__FILE__, function (DatabaseService $db): void {
    $calls = $db->rawQuery(
        "SELECT transaction_id, requested_on FROM track_api_requests
          WHERE request_type IN ('login', 'save-user')
            AND requested_on >= NOW() - INTERVAL ? DAY",
        [ApiTrackingStorageUtility::RETENTION_DAYS + 1]
    );

    $removed = 0;
    foreach ($calls as $call) {
        $transactionId = (string) ($call['transaction_id'] ?? '');
        if ($transactionId === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $transactionId)) {
            continue;
        }
        foreach (ApiTrackingStorageUtility::KINDS as $kind) {
            foreach (ApiTrackingStorageUtility::candidateDirectories($kind, $call['requested_on'] ?? null) as $dir) {
                foreach (['.zst', '.gz', '.zip', ''] as $ext) {
                    $file = "$dir/$transactionId.json$ext";
                    if (is_file($file) && @unlink($file)) {
                        $removed++;
                    }
                }
            }
        }
    }

    echo "Removed $removed kept login and profile bodies" . PHP_EOL;
});
