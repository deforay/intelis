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
use App\Utilities\PatientTimelineUtility;

// Export of the Patient Test History tab, for the patient the page posts.
// The timeline is rebuilt here rather than read from the session: the session
// only keeps the size of each one opened, which is enough to tell a small
// export, run at once, from a big one.

/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$input = _sanitizeInput($request->getParsedBody());
$patientId = trim((string) ($input['patientId'] ?? ''));

// AJAX requests bypass the access control layer. As on the tab itself, each
// test type is only read when the user can open that module's clinic report.
// Checked before a job is queued.
if (ClinicReportUtility::visibleTypes() === []) {
    echo json_encode(['error' => _translate('You do not have permission to perform this action.')]);
    return;
}
if ($patientId === '') {
    return;
}

// The count of this patient's timeline, never another one's: a count of 0
// left by a different patient would answer "no data" for this one. Without a
// count the export runs as a background job.
$_SESSION['patientTimelineCount'] = PatientTimelineUtility::rememberedCount($patientId);
if (ExportJobUtility::queueRequested(__FILE__, null, ['patientTimelineCount'])) {
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

$data = PatientTimelineUtility::timeline($patientId, $db, $general);
$patient = $data['patient'];
$events = $data['events'];
if ($events === []) {
    return;
}

$dates = static fn(array $values): string => implode(' / ', array_filter(array_map(
    static fn($d) => (string) DateUtility::humanReadableDateFormat($d),
    $values
)));

$filename = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Patient-Timeline-' . date('d-M-Y-H-i-s')
    . '-' . bin2hex(random_bytes(4)) . '.xlsx';

$writer = new Writer();
$writer->openToFile($filename);

$writer->addRow(Row::fromValues([
    _translate('Patient ID'),
    _translate('Patient Name'),
    _translate('Sex'),
    _translate('Date of Birth'),
    _translate('First Sample'),
    _translate('Last Sample'),
    _translate('Facilities'),
]));
$writer->addRow(Row::fromValues([
    (string) $patient['id'],
    implode(' / ', $patient['names']),
    implode(' / ', $patient['sexes']),
    $dates($patient['dobs']),
    $dates([$events[0]['collected']]),
    $dates([$events[count($events) - 1]['collected']]),
    implode(', ', $patient['facilities']),
]));

$writer->addRow(Row::fromValues([]));
$writer->addRow(Row::fromValues([_translate('Test History')]));
$writer->addRow(Row::fromValues([
    _translate('Test Type'),
    _translate('Sample ID'),
    _translate('Sample Collection Date'),
    _translate('Sample Type'),
    _translate('Facility'),
    _translate('Testing Lab'),
    _translate('Status'),
    _translate('Result'),
]));

foreach ($events as $event) {
    ExportJobUtility::tick();
    // Custom Tests are told apart by the test they ran.
    $type = $event['type'] === 'generic-tests'
        ? ($event['note'] ?: _translate('Custom Tests'))
        : ($data['types'][$event['type']] ?? $event['type']);
    $writer->addRow(Row::fromValues([
        (string) $type,
        $event['code'],
        $dates([$event['collected']]),
        $event['sampleType'],
        $event['facility'],
        $event['lab'],
        $event['statusName'],
        (string) $event['result'],
    ]));
}

$writer->close();
echo _downloadToken($filename);
