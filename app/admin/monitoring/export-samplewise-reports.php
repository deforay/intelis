<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Utilities\ExportJobUtility;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, 'samplewiseReportsQuery', ['samplewiseReportsCalc'])) {
    return;
}

// A background job has no request to time out, and the worker sets its own
// memory limit; a direct request keeps these.
if (!ExportJobUtility::inBackground()) {
    ini_set('memory_limit', -1);
    set_time_limit(0);
    ini_set('max_execution_time', 300000);
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

$calcResult = $db->rawQuery($_SESSION['samplewiseReportsCalc']);

// Same totals and columns, in the same order, as the page shows.
$totalHeadings = [
    _translate("No. of Samples Requested"),
    _translate("No. of Samples Received at Testing Lab"),
    _translate("No. of Samples Tested"),
    _translate("No. of Results Returned"),
];
$headings = [
    _translate("LIS Sample ID"),
    _translate("STS Sample ID"),
    _translate("External ID"),
    _translate("Name of the Clinic"),
    _translate("Name of the Testing Lab"),
    _translate("Electronic Test request Date and Time"),
    _translate("Samples Received At Lab"),
    _translate("Sample added to Batch on"),
    _translate("Sample Status"),
    _translate("Test Result"),
    _translate("Sample Tested On"),
    _translate("Result Approved Date and Time"),
    _translate("Result Return Date and Time"),
    _translate("Last Modified On"),
];

$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-SOURCES-OF-REQUESTS-' . date('d-M-Y-H-i-s')
    . '-' . MiscUtility::generateRandomString(6) . '.xlsx';

$writer = new Writer();
$writer->openToFile($filename);

$writer->addRow(Row::fromValues(array_map('html_entity_decode', $totalHeadings)));
foreach ($calcResult as $cRow) {
    $writer->addRow(Row::fromValues([
        (int) $cRow['totalSamplesRequested'],
        (int) $cRow['totalSamplesReceived'],
        (int) $cRow['totalSamplesTested'],
        (int) $cRow['totalSamplesDispatched'],
    ]));
}
$writer->addRow(Row::fromValues(['']));

$writer->addRow(Row::fromValues(array_map('html_entity_decode', $headings)));

$no = 0;
foreach ($db->rawQueryGenerator($_SESSION['samplewiseReportsQuery']) as $aRow) {
    ExportJobUtility::tick();
    $writer->addRow(Row::fromValues([
        $aRow['sample_code'],
        $aRow['remote_sample_code'],
        $aRow['external_sample_code'] ?? $aRow['app_sample_code'],
        $aRow['facility_name'],
        $aRow['labname'],
        DateUtility::humanReadableDateFormat($aRow['request_created_datetime'] ?? '', true),
        DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '', true),
        DateUtility::humanReadableDateFormat($aRow['batch_request_created'] ?? '', true),
        $aRow['status_name'],
        $aRow['result'],
        DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '', true),
        DateUtility::humanReadableDateFormat($aRow['result_approved_datetime'] ?? '', true),
        DateUtility::humanReadableDateFormat($aRow['result_returned_datetime'] ?? '', true),
        DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '', true),
    ]));

    if (++$no % 5000 === 0) {
        gc_collect_cycles();
    }
}

$writer->close();

echo _downloadToken($filename);
