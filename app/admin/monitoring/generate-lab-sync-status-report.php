<?php

use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Utilities\ExportJobUtility;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, null, ['labSyncStatus', 'labSyncStatusParams'])) {
	return;
}

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 20000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Use the query and parameters stored in session from get-sync-status.php
$query = $_SESSION['labSyncStatus'] ?? '';
$params = $_SESSION['labSyncStatusParams'] ?? [];

if (empty($query)) {
    echo '';
    exit;
}

// Execute query using session data
$resultSet = $db->rawQueryGenerator($query, $params);

// Calculate thresholds
$twoWeeksAgo = strtotime('-2 weeks');
$fourWeeksAgo = strtotime('-4 weeks');

// Note: Filters are already applied in the query stored in session
// Headers
$headings = [
    _translate("Lab Name"),
    _translate("Last Sync Done On"),
    _translate("Latest Results Sync from Lab"),
    _translate("Latest Requests Sync from STS"),
    _translate("LIS Version"),
    _translate("Sync Status"),
    _translate("Days Since Last Sync")
];

// Data rows
$output = [];
$statusSummary = ['active' => 0, 'warning' => 0, 'critical' => 0];

foreach ($resultSet as $aRow) {
    ExportJobUtility::tick();
    $row = [];

    // Determine sync status
    $latestSync = (int)$aRow['latest_timestamp'];
    if ($latestSync > $twoWeeksAgo) {
        $syncStatus = 'Active';
        $statusSummary['active']++;
    } elseif ($latestSync > $fourWeeksAgo) {
        $syncStatus = 'Warning';
        $statusSummary['warning']++;
    } else {
        $syncStatus = 'Critical';
        $statusSummary['critical']++;
    }

    // Calculate days since last sync
    $daysSinceSync = $latestSync !== 0 ? floor((time() - $latestSync) / 86400) : 'Never';

    $row[] = $aRow['facility_name'] ?? '';
    $row[] = $latestSync !== 0 ? DateUtility::humanReadableDateFormat(date('Y-m-d H:i:s', $latestSync), true) : 'Never';
    $row[] = DateUtility::humanReadableDateFormat($aRow['lastResultsSync'] ?? '', true) ?: 'Never';
    $row[] = DateUtility::humanReadableDateFormat($aRow['lastRequestsSync'] ?? '', true) ?: 'Never';
    $row[] = $aRow['version'] ?? '-';
    $row[] = $syncStatus;
    $row[] = $daysSinceSync;

    $output[] = $row;
}

$filename = 'InteLIS-LAB-SYNC-STATUS-' . date('d-M-Y-H-i-s') . '-' . MiscUtility::generateRandomNumber(6) . '.xlsx';

$writer = new Writer();
$writer->openToFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
$writer->getCurrentSheet()->setName('Lab Sync Status');
$writer->addRow(Row::fromValues(['Lab Sync Status Report']));
$writer->addRow(Row::fromValues(['Generated on: ' . DateUtility::humanReadableDateFormat(date('Y-m-d H:i:s'), true)]));
$writer->addRow(Row::fromValues(['']));
$writer->addRow(Row::fromValues($headings));
// Row fills match the colours named in the summary below
$statusFill = ['Active' => 'C8E6C9', 'Warning' => 'FFF9C4', 'Critical' => 'FFCDD2'];
foreach ($output as $rowData) {
    $fill = (new Style())->withBackgroundColor($statusFill[$rowData[5]]);
    $writer->addRow(Row::fromValuesWithStyles($rowData, array_fill_keys(array_keys($rowData), $fill)));
}
$writer->addRow(Row::fromValues(['']));
$writer->addRow(Row::fromValues(['']));
$writer->addRow(Row::fromValues(['Summary:']));
$writer->addRow(Row::fromValues(['Active Labs (Green): ' . $statusSummary['active']]));
$writer->addRow(Row::fromValues(['Warning Labs (Yellow): ' . $statusSummary['warning']]));
$writer->addRow(Row::fromValues(['Critical Labs (Red): ' . $statusSummary['critical']]));
$writer->addRow(Row::fromValues(['Total Labs: ' . (count($output))]));
$writer->close();

echo _downloadToken($filename);
