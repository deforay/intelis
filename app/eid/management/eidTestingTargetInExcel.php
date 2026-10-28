<?php

use App\Registries\ContainerRegistry;
use App\Services\DatabaseService;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use App\Utilities\ExportJobUtility;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, null, ['eidMonitoringThresholdReportQuery'])) {
    return;
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

if (isset($_SESSION['eidMonitoringThresholdReportQuery']) && trim((string) $_SESSION['eidMonitoringThresholdReportQuery']) !== "") {
    // The screen's query already sums each facility/month and its HAVING applies
    // the Target Type, so write its rows out as they are, the same as the screen.
    $rResult = $db->rawQuery($_SESSION['eidMonitoringThresholdReportQuery']);

    $filename = 'InteLIS-EID-Testing-Target-Report-' . date('d-M-Y-H-i-s') . '.xlsx';

    $writer = new Writer();
    $writer->openToFile(TEMP_PATH . DIRECTORY_SEPARATOR . $filename);
    $writer->addRow(Row::fromValues([_translate('Early Infant Diagnosis - Testing Target')]));
    $writer->addRow(Row::fromValues(['']));
    $writer->addRow(Row::fromValues([
        _translate('Lab Name'),
        _translate('Facility Name'),
        _translate('Month'),
        _translate('Number of Samples Received'),
        _translate('Number of Samples Rejected'),
        _translate('Number of Samples Tested'),
        _translate('Monthly Test Target'),
    ]));
    foreach ($rResult as $aRow) {
        ExportJobUtility::tick();
        $writer->addRow(Row::fromValues([
            html_entity_decode((string) $aRow['lab_name'], ENT_QUOTES, 'UTF-8'),
            html_entity_decode((string) $aRow['facility_name'], ENT_QUOTES, 'UTF-8'),
            $aRow['monthrange'],
            (int) $aRow['totalReceived'],
            (int) $aRow['totalRejected'],
            (int) $aRow['totalCollected'],
            is_numeric($aRow['monthly_target']) ? (int) $aRow['monthly_target'] : $aRow['monthly_target'],
        ]));
    }
    $writer->close();

    echo _downloadToken($filename);
}
