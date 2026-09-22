<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use OpenSpout\Common\Entity\Row;
use App\Registries\ContainerRegistry;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;

ini_set('memory_limit', -1);
set_time_limit(0);
ini_set('max_execution_time', 20000);

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

// Set by get-sync-status-details.php, so the export matches the grid on screen
$saved = $_SESSION['labSyncStatusDetails'] ?? null;
if (!is_array($saved) || empty($saved['query'])) {
    echo '';
    exit;
}

$headings = [
    _translate("Facility Name"),
    _translate("Test Type"),
    _translate("Province"),
    _translate("District"),
    _translate("Requests Sent to Lab"),
    _translate("Awaiting Lab Confirmation"),
    _translate("Results Received from Lab"),
    _translate("Last Request Sent from STS"),
    _translate("Last Result Received From Lab"),
];

$filename = 'InteLIS-LAB-SYNC-STATUS-DETAILS-' . date('d-M-Y-H-i-s') . '-' . MiscUtility::generateRandomNumber(6) . '.xlsx';
$filePath = TEMP_PATH . DIRECTORY_SEPARATOR . $filename;

$writer = new XlsxWriter();
$writer->openToFile($filePath);
$writer->addRow(Row::fromValues($headings));

foreach ($db->rawQuery($saved['query'], $saved['params'] ?? []) as $aRow) {
    $writer->addRow(Row::fromValues([
        $aRow['facility_name'],
        $saved['testName'] ?? '',
        $aRow['province'],
        $aRow['district'],
        (int) $aRow['requestsSent'],
        (int) ($aRow['awaitingConfirmation'] ?? 0),
        (int) $aRow['resultsReceived'],
        DateUtility::humanReadableDateFormat($aRow['lastRequestsSync'], true),
        DateUtility::humanReadableDateFormat($aRow['lastResultsSync'], true),
    ]));
}
$writer->close();

echo _downloadToken($filename);
