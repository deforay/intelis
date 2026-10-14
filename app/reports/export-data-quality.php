<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use App\Registries\ContainerRegistry;
use App\Utilities\ExportJobUtility;
use App\Utilities\ClinicReportUtility;
use App\Utilities\DataQualityReportUtility;

// Export of the Data Quality tab's sample listing, every sample rather than
// the SAMPLE_LIMIT shown on screen, so it runs as a background export.

/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$filters = _sanitizeInput($request->getParsedBody());
$testType = (string) ($filters['testType'] ?? '');

// AJAX requests bypass the access control layer: a test type is readable by
// whoever can open its clinic report. Checked before a job is queued.
if (!ClinicReportUtility::canView($testType)) {
    echo json_encode(['error' => _translate('You do not have permission to perform this action.')]);
    return;
}

if (!ExportJobUtility::inBackground()) {
    // The listing on screen counted these same samples when it found fewer
    // than its limit; that count lets a small export run at once. A count
    // left by another listing (another filter, or another browser tab) is
    // not used, and the export then runs as a background job.
    $listed = $_SESSION[DataQualityReportUtility::LISTING_SESSION_KEY] ?? null;
    if (
        is_array($listed) && isset($listed['count'])
        && ($listed['key'] ?? null) === DataQualityReportUtility::listingKey($filters)
    ) {
        $_SESSION['dataQualityExportCount'] = (int) $listed['count'];
    } else {
        unset($_SESSION['dataQualityExportCount']);
    }
}

if (ExportJobUtility::queueRequested(__FILE__, null, ['dataQualityExportCount'])) {
    return;
}

// A background job has no request to time out; a direct request keeps its limits.
if (!ExportJobUtility::inBackground()) {
    ini_set('memory_limit', '512M');
    set_time_limit(300);
}

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$labels = array_map(fn($c) => $c['label'], DataQualityReportUtility::checks($testType));
$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Data-Quality-' . $testType . '-' . date('d-M-Y-H-i-s')
    . '-' . bin2hex(random_bytes(4)) . '.xlsx';

$writer = new Writer();
$writer->openToFile($filename);
$writer->addRow(Row::fromValues([
    _translate('Sample ID'),
    _translate('Patient ID'),
    _translate('Sample Collection Date'),
    _translate('Request Created On'),
    _translate('Facility'),
    _translate('Status'),
    _translate('Missing Fields'),
]));
foreach (DataQualityReportUtility::samples($testType, $filters, $db, $general, null) as $sample) {
    ExportJobUtility::tick();
    $writer->addRow(Row::fromValues([
        $sample['sampleCode'],
        $sample['patientId'],
        (string) DateUtility::humanReadableDateFormat($sample['collected']),
        (string) DateUtility::humanReadableDateFormat($sample['requested']),
        $sample['facility'],
        $sample['status'],
        implode(', ', array_map(fn($k) => $labels[$k], $sample['missing'])),
    ]));
}
$writer->close();

echo _downloadToken($filename);
