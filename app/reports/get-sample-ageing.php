<?php

use Psr\Http\Message\ServerRequestInterface;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Services\SampleFlowService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {
    // AJAX requests bypass the access control layer, so the page's own
    // privilege is checked here.
    _requirePrivilege('/reports/sample-ageing.php');

    /** @var SampleFlowService $sampleFlow */
    $sampleFlow = ContainerRegistry::get(SampleFlowService::class);

    // Filters are normalized by the service: the test key must be a registry
    // key, so it can never reach a query as a table name. Lab scoping happens
    // inside every query.
    $filters = $sampleFlow->resolveFilters($_POST);

    $section = (string) ($_POST['section'] ?? '');
    $stage = (string) ($_POST['stage'] ?? '');
    $groupBy = (string) ($_POST['groupBy'] ?? '');
    $groupKey = (string) ($_POST['groupKey'] ?? '');
    $bucket = (string) ($_POST['bucket'] ?? '');

    if ($section === 'samples') {
        // DataTables envelope for the drilldown grid. No exit() anywhere in
        // this file: the endpoint is also driven in-process by the tests.
        $offset = max(0, (int) ($_POST['iDisplayStart'] ?? 0));
        $limit = (int) ($_POST['iDisplayLength'] ?? 25);
        if ($limit <= 0 || $limit > 1000) {
            $limit = 25;
        }
        $result = $sampleFlow->getSamples(
            $filters,
            $stage,
            $groupBy,
            $groupKey,
            $bucket,
            $offset,
            $limit,
            trim((string) ($_POST['sSearch'] ?? ''))
        );
        $escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $aaData = [];
        foreach ($result['rows'] as $row) {
            $cells = [];
            foreach (array_keys(SampleFlowService::sampleColumns()) as $column) {
                $cells[] = $column === 'age' ? (int) $row[$column] : $escape($row[$column]);
            }
            $aaData[] = $cells;
        }
        echo JsonUtility::encodeUtf8Json([
            'sEcho' => (int) ($_POST['sEcho'] ?? 0),
            'iTotalRecords' => $result['total'],
            'iTotalDisplayRecords' => $result['total'],
            'aaData' => $aaData,
        ]);
    } elseif ($section === 'export') {
        // The stage names the file, so it is checked against the fixed list
        // before any path is built; the test key was already checked against
        // the registry. Nothing request-supplied reaches the filename raw.
        if (!in_array($stage, array_merge(SampleFlowService::STAGES, SampleFlowService::EXITS), true)) {
            throw new \App\Exceptions\SystemException('Invalid stage for the sample flow');
        }

        // Every sample behind the cell, streamed straight into the workbook so
        // a stage holding tens of thousands of samples never sits in memory.
        $columns = SampleFlowService::sampleColumns();
        $filePath = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Sample-Flow-' . $filters['testKey'] . '-' . $stage
            . '-' . date('d-M-Y-H-i-s') . '.xlsx';
        $writer = new XlsxWriter();
        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues(array_values($columns)));
        foreach ($sampleFlow->streamSamples($filters, $stage, $groupBy, $groupKey, $bucket) as $row) {
            $cells = [];
            foreach (array_keys($columns) as $column) {
                $cells[] = $row[$column];
            }
            $writer->addRow(Row::fromValues($cells));
        }
        $writer->close();
        echo _downloadToken($filePath);
    } else {
        $output = match ($section) {
            'flow' => ['flow' => $sampleFlow->getFlow($filters)],
            'breakdown' => ['rows' => $sampleFlow->getBreakdown($filters, $stage, $groupBy)],
            default => throw new \App\Exceptions\SystemException('Invalid sample flow section'),
        };
        echo JsonUtility::encodeUtf8Json($output);
    }
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
    echo JsonUtility::encodeUtf8Json(['error' => _translate('Unable to load the sample flow right now')]);
}
