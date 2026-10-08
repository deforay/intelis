<?php

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Utilities\ExportJobUtility;
use App\Registries\ContainerRegistry;
use App\Utilities\InterfaceActivityFilter;
use App\Services\LabPerformanceIndicatorsService;

// The page asked for a background export: queue it and answer at once, so the
// user can move on while bin/export-worker.php runs this same script.
if (ExportJobUtility::queueRequested(__FILE__, null, [], countsRows: false)) {
    return;
}

ini_set('memory_limit', '512M');
// A background job has no request to time out; a direct request keeps its limit.
if (!ExportJobUtility::inBackground()) {
    set_time_limit(300);
    ini_set('max_execution_time', 300);
}

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {
    // AJAX requests bypass the access control layer, so the page's own
    // privilege is checked here.
    _requirePrivilege('/reports/interface-machine-activity.php');

    $section = ($_POST['section'] ?? '') === 'events' ? 'events' : 'tests';
    $format = ($_POST['format'] ?? '') === 'csv' ? 'csv' : 'xlsx';

    $baseName = $section === 'tests' ? 'InteLIS-Tests-by-Instrument' : 'InteLIS-Interface-Tool-Events';
    // Two exports started in the same second must not write the same file.
    $filePath = TEMP_PATH . DIRECTORY_SEPARATOR . $baseName . '-' . date('d-M-Y-H-i-s')
        . '-' . bin2hex(random_bytes(4)) . '.' . $format;
    $writer = $format === 'xlsx' ? new XlsxWriter() : new CsvWriter();
    $writer->openToFile($filePath);

    if ($section === 'tests') {
        /** @var LabPerformanceIndicatorsService $indicators */
        $indicators = ContainerRegistry::get(LabPerformanceIndicatorsService::class);
        $filters = $indicators->resolveFilters($_POST);
        $rawRows = $indicators->getByInstrument($filters);

        $headings = [
            _translate('Testing Lab'), _translate('Instrument Type'), _translate('Instrument'),
            _translate('Assay'), _translate('Tests Run'), _translate('Valid Results'),
            _translate('Failed or Invalid'), _translate('Failure Rate (%)'),
            _translate('Failed Runs Re-tested'),
        ];
        $writer->addRow(Row::fromValues($headings));
        $notRecorded = _translate('Not recorded');
        // The page's column filters (lab, instrument, assay), matched against the
        // values as the page shows them. Read raw: they are compared as text, never
        // printed, and the sanitizer would turn "HIV-1 & HIV-2" into "HIV-1 &amp; HIV-2".
        $rawFilters = _rawInput('columnFilters', []);
        $columnFilters = array_map(
            static fn(mixed $value): string => is_string($value) ? $value : '',
            array_slice(is_array($rawFilters) ? array_values($rawFilters) : [], 0, 3)
        );
        $kept = [];
        foreach ($rawRows as $r) {
            $instrument = $r['instrument'] !== '' ? $r['instrument'] : $notRecorded;
            $assay = $r['assay'] !== '' ? $r['assay'] : $notRecorded;
            $shown = [$r['lab'], $r['instrumentLabel'] !== '' ? $r['instrumentLabel'] : $notRecorded, $assay];
            foreach ($columnFilters as $column => $value) {
                if ($value !== '' && $shown[$column] !== $value) {
                    continue 2;
                }
            }
            ExportJobUtility::tick();
            $kept[] = $r;
            $writer->addRow(Row::fromValues([
                $r['lab'], $r['instrumentType'], $instrument, $assay,
                $r['tested'], $r['valid'], $r['failed'], $r['failureRate'], $r['retested'],
            ]));
        }
        $rawRows = $kept;

        // A rate is recomputed from the totalled counts, never averaged.
        if (count($rawRows) > 1) {
            $tested = (int) array_sum(array_column($rawRows, 'tested'));
            $failed = (int) array_sum(array_column($rawRows, 'failed'));
            $writer->addRow(Row::fromValues([
                _translate('Total'), '', '', '', $tested, $tested - $failed, $failed,
                $tested > 0 ? round($failed * 100 / $tested, 2) : null,
                (int) array_sum(array_column($rawRows, 'retested')),
            ]));
        }
    } else {
        /** @var CommonService $general */
        $general = ContainerRegistry::get(CommonService::class);

        $where = InterfaceActivityFilter::clauses($_POST, $db, $general);
        // The grid's text search, so the file holds exactly the rows on screen.
        $search = InterfaceActivityFilter::searchClause((string) ($_POST['search'] ?? ''), $general);
        if ($search !== null) {
            $where[] = $search;
        }
        $sQuery = "SELECT a.occurred_at, f.facility_name, a.instrument_id, a.machine_type,
                          a.event_type, a.outcome, a.failure_code, a.protocol,
                          a.connection_mode, a.app_version
                     FROM instrument_activity_log AS a
                     LEFT JOIN facility_details AS f ON f.facility_id = a.lab_id"
            . (empty($where) ? '' : ' WHERE ' . implode(' AND ', $where))
            . " ORDER BY a.occurred_at DESC";

        $headings = [
            _translate('Occurred On'), _translate('Lab'), _translate('Instrument'),
            _translate('Machine Type'), _translate('Event'), _translate('Outcome'),
            _translate('Failure Code'), _translate('Protocol'), _translate('Connection Mode'),
            _translate('App Version'),
        ];
        $writer->addRow(Row::fromValues($headings));
        foreach ($db->rawQueryGenerator($sQuery) as $r) {
            ExportJobUtility::tick();
            $writer->addRow(Row::fromValues([
                DateUtility::humanReadableDateFormat($r['occurred_at'], true),
                $r['facility_name'], $r['instrument_id'], $r['machine_type'], $r['event_type'],
                $r['outcome'], $r['failure_code'], $r['protocol'], $r['connection_mode'],
                $r['app_version'],
            ]));
        }
    }
    $writer->close();

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
