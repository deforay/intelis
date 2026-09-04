<?php

use Psr\Http\Message\ServerRequestInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Exceptions\SystemException;
use App\Registries\ContainerRegistry;
use App\Services\QualityMonitoringService;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {
    // AJAX requests bypass the access control layer, so the page's own
    // privilege is checked here.
    _requirePrivilege('/eid/qa/eid-quality-monitoring.php');

    /** @var QualityMonitoringService $qaService */
    $qaService = ContainerRegistry::get(QualityMonitoringService::class);

    // Every request value is normalized here: dates are rebuilt from a parsed
    // date, ids become ints, and the age bucket must be one of the fixed set.
    // Scoping is applied inside each query, not here.
    $filters = $qaService->resolveFilters($_POST);

    $section = (string) ($_POST['section'] ?? '');
    $view = (string) ($_POST['view'] ?? '');
    if ($section !== 'summary' && !isset(QualityMonitoringService::VIEWS[$view])) {
        throw new SystemException('Invalid view for quality monitoring');
    }

    if ($section === 'summary') {
        echo JsonUtility::encodeUtf8Json(['summary' => $qaService->getSummary($filters)]);
    } elseif ($section === 'samples') {
        // DataTables envelope for the grid. No exit() anywhere in this file:
        // the endpoint is also driven in-process by the tests.
        $offset = max(0, (int) ($_POST['iDisplayStart'] ?? 0));
        $limit = (int) ($_POST['iDisplayLength'] ?? 25);
        if ($limit <= 0 || $limit > 1000) {
            $limit = 25;
        }

        // The sort column arrives as a grid index; it is resolved against the
        // view's own column list, so an unknown index simply falls back to
        // oldest first.
        $columnKeys = array_keys(QualityMonitoringService::sampleColumns($view));
        $sortKey = $columnKeys[(int) ($_POST['iSortCol_0'] ?? -1)] ?? '';

        $result = $qaService->getSamples(
            $filters,
            $view,
            $offset,
            $limit,
            trim((string) ($_POST['sSearch'] ?? '')),
            $sortKey,
            (string) ($_POST['sSortDir_0'] ?? 'desc')
        );

        echo JsonUtility::encodeUtf8Json([
            'sEcho' => (int) ($_POST['sEcho'] ?? 0),
            'iTotalRecords' => $result['total'],
            'iTotalDisplayRecords' => $result['total'],
            'aaData' => $result['rows'],
        ]);
    } elseif ($section === 'export') {
        // Every waiting sample in the view, streamed straight into the
        // workbook so a large backlog never sits in memory. The view was
        // checked above, so nothing request-supplied reaches the filename raw.
        // The workbook takes the flat column list: what the grid clubs into a
        // child and a mother column is one value per column here, because a
        // spreadsheet gets sorted and filtered on the parts.
        $columns = QualityMonitoringService::exportColumns();

        $filePath = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-EID-Quality-Monitoring-' . $view
            . '-' . date('d-M-Y-H-i-s') . '.xlsx';
        $writer = new XlsxWriter();
        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues(array_values($columns)));
        foreach ($qaService->streamSamples($filters, $view) as $row) {
            $cells = [];
            foreach (array_keys($columns) as $key) {
                $cells[] = $row[$key];
            }
            $writer->addRow(Row::fromValues($cells));
        }
        $writer->close();
        echo _downloadToken($filePath);
    } else {
        throw new SystemException('Invalid section for quality monitoring');
    }
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
    echo JsonUtility::encodeUtf8Json(['error' => _translate('Unable to load the quality monitoring data right now')]);
}
