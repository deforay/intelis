<?php

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use Psr\Http\Message\ServerRequestInterface;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Utilities\ExportJobUtility;
use App\Registries\ContainerRegistry;
use App\Services\ActivityLogFeedService;

// The entries that match the User Activity Log page's filters, as CSV, one row
// per entry with its changes, details and list flattened into text columns.

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, null, [])) {
    return;
}

ini_set('memory_limit', '512M');
// A background job has no request to time out; a direct request keeps its limit.
if (!ExportJobUtility::inBackground()) {
    set_time_limit(300);
    ini_set('max_execution_time', 300);
}

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {
    // AJAX requests bypass the access control layer, so the page's own
    // privilege is checked here.
    _requirePrivilege('/admin/monitoring/activity-log.php');

    /** @var ActivityLogFeedService $feedService */
    $feedService = ContainerRegistry::get(ActivityLogFeedService::class);
    $feed = $feedService->feed($_POST, 1, ActivityLogFeedService::EXPORT_LIMIT);

    // Spreadsheet apps run a cell that starts with = + - @ as a formula, and
    // entries hold text users typed, such as facility names.
    $cell = static function (mixed $value): string {
        $text = (string) $value;
        return ($text !== '' && strpbrk($text[0], "=+-@\t\r") !== false) ? "'" . $text : $text;
    };
    $empty = static fn(string $value): string => $value === '' ? '∅' : $value;

    $filePath = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-User-Activity-Log-' . date('d-M-Y-H-i-s')
        . '-' . bin2hex(random_bytes(4)) . '.csv';
    $writer = new CsvWriter();
    $writer->openToFile($filePath);
    $writer->addRow(Row::fromValues(array_map(_translate(...), [
        'Date and time', 'Action', 'Important', 'Reason', 'Item type', 'Item', 'Changes', 'Details',
        'User', 'Email', 'Role', 'Context', 'IP address', 'Browser', 'Channel', 'Page', 'Session hash', 'Request ID',
    ])));

    foreach ($feed['items'] as $item) {
        $changes = [];
        foreach ($item['changes'] as $change) {
            if (isset($change['added']) || isset($change['removed'])) {
                $parts = [];
                if (!empty($change['added'])) {
                    $parts[] = '+ ' . implode(', ', $change['added']);
                }
                if (!empty($change['removed'])) {
                    $parts[] = '- ' . implode(', ', $change['removed']);
                }
                $changes[] = $change['label'] . ': ' . implode('; ', $parts);
                continue;
            }
            $changes[] = $change['label'] . ': ' . $empty((string) $change['from']) . ' → ' . $empty((string) $change['to']);
        }
        $details = array_map(static fn(array $fact): string => $fact['label'] . ': ' . $fact['value'], $item['facts']);
        if (!empty($item['list']['rows'])) {
            $details[] = $item['list']['title'] . ': ' . implode('; ', $item['list']['rows']);
        }

        $writer->addRow(Row::fromValues(array_map($cell, [
            $item['timestamp'],
            $item['action'],
            $item['important'] ? _translate('Yes') : '',
            $item['reason'],
            $item['entityTypeLabel'],
            $item['entityLabel'],
            implode("\n", $changes),
            implode("\n", $details),
            $item['userName'],
            $item['userEmail'],
            $item['userRole'],
            $item['context'],
            $item['ipAddress'],
            $item['userAgent'],
            $item['channel'],
            $item['route'],
            $item['sessionHash'],
            $item['requestId'],
        ])));
        ExportJobUtility::tick();
    }
    $writer->close();

    ContainerRegistry::get(CommonService::class)->activityLog(
        'activity-log-export',
        sprintf('Exported the user activity log (%d entries)', count($feed['items'])),
        'activity-log'
    );

    echo _downloadToken($filePath);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
    // Rethrown so the export job is marked failed straight away rather than left running.
    throw $e;
}
