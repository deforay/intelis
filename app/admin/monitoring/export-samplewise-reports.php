<?php

use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use App\Utilities\ExportJobUtility;
use App\Utilities\SourcesOfRequestsReportUtility;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, 'samplewiseReportsQuery', ['samplewiseReportsSummaryFrom'])) {
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

$summary = SourcesOfRequestsReportUtility::summary($db, (string) $_SESSION['samplewiseReportsSummaryFrom']);

$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-SOURCES-OF-REQUESTS-' . date('d-M-Y-H-i-s')
    . '-' . MiscUtility::generateRandomString(6) . '.xlsx';

$writer = new Writer();
$writer->openToFile($filename);

// The summary as the page shows it, with each percentage in its own column.
$writer->getCurrentSheet()->setName(_translate('Summary by Source'));
$writer->addRow(Row::fromValues(array_map('html_entity_decode', [
    _translate('Source of Request'),
    _translate('Requested'),
    _translate('Received at Lab'),
    _translate('% Received'),
    _translate('Tested'),
    _translate('% Tested'),
    _translate('Results Returned'),
    _translate('% Returned'),
    _translate('Median Days to Receipt'),
    _translate('Median Days to Return'),
])));
$percent = fn(int $count, int $of): ?float => $of > 0 ? round($count * 100 / $of, 1) : null;
foreach ([...$summary['rows'], $summary['total']] as $row) {
    $writer->addRow(Row::fromValues([
        $row['label'],
        $row['requested'],
        $row['received'],
        $percent($row['received'], $row['requested']),
        $row['tested'],
        $percent($row['tested'], $row['requested']),
        $row['returned'],
        $percent($row['returned'], $row['requested']),
        $row['receiptDays'],
        $row['returnDays'],
    ]));
}

// Every sample listed, with the detail the page leaves out.
$writer->addNewSheetAndMakeItCurrent()->setName(_translate('Samples'));
$writer->addRow(Row::fromValues(array_map('html_entity_decode', [
    _translate("LIS Sample ID"),
    _translate("STS Sample ID"),
    _translate("External ID"),
    _translate("Source of Request"),
    _translate("Name of the Clinic"),
    _translate("Name of the Testing Lab"),
    _translate("Requested On"),
    _translate("Received at Lab"),
    _translate("Sample added to Batch on"),
    _translate("Sample Tested On"),
    _translate("Result Approved Date and Time"),
    _translate("Result Returned On"),
    _translate("Sample Status"),
    _translate("Test Result"),
    _translate("Last Modified On"),
])));

$no = 0;
foreach ($db->rawQueryGenerator($_SESSION['samplewiseReportsQuery']) as $aRow) {
    ExportJobUtility::tick();
    $writer->addRow(Row::fromValues([
        $aRow['sample_code'],
        $aRow['remote_sample_code'],
        $aRow['external_sample_code'] ?: $aRow['app_sample_code'],
        CommonService::sourceOfRequestLabel((string) $aRow['source_of_request']),
        $aRow['facility_name'],
        $aRow['labname'],
        DateUtility::humanReadableDateFormat($aRow['request_created_datetime'] ?? '', true),
        DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'] ?? '', true),
        DateUtility::humanReadableDateFormat($aRow['batch_request_created'] ?? '', true),
        DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'] ?? '', true),
        DateUtility::humanReadableDateFormat($aRow['result_approved_datetime'] ?? '', true),
        DateUtility::humanReadableDateFormat($aRow['result_returned_datetime'] ?? '', true),
        $aRow['status_name'],
        $aRow['result'],
        DateUtility::humanReadableDateFormat($aRow['last_modified_datetime'] ?? '', true),
    ]));

    if (++$no % 5000 === 0) {
        gc_collect_cycles();
    }
}

$writer->close();

echo _downloadToken($filename);
