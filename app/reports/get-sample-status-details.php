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
use App\Services\SampleStatusDetailsService;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {
    /** @var SampleStatusDetailsService $details */
    $details = ContainerRegistry::get(SampleStatusDetailsService::class);

    // The test type and status are checked against known values before
    // anything else, and AJAX requests bypass the access control layer, so the
    // privilege is checked here once the test type is known.
    $req = $details->resolveRequest($_POST);
    if (!SampleStatusDetailsService::canView($req['testType'])) {
        throw new SystemException(_translate('You do not have permission to perform this action.'), 403);
    }

    $columnKeys = array_keys(SampleStatusDetailsService::columns($req['status']));

    if (($_POST['section'] ?? '') === 'export') {
        $columns = SampleStatusDetailsService::columns($req['status']);
        $filePath = TEMP_PATH . DIRECTORY_SEPARATOR . 'InteLIS-Sample-Status-' . $req['testType'] . '-' . $req['status']
            . '-' . date('d-M-Y-H-i-s') . '.xlsx';
        $writer = new XlsxWriter();
        $writer->openToFile($filePath);
        $writer->addRow(Row::fromValues(array_values($columns)));
        foreach ($details->streamSamples($req) as $row) {
            $writer->addRow(Row::fromValues(array_values($row)));
        }
        $writer->close();
        echo _downloadToken($filePath);
    } else {
        // DataTables server-side request. The sort column is an index into this
        // status's own column list, never a name taken from the request.
        $offset = max(0, (int) ($_POST['start'] ?? 0));
        $limit = (int) ($_POST['length'] ?? 25);
        if ($limit <= 0 || $limit > 1000) {
            $limit = 25;
        }
        $order = $_POST['order'][0] ?? [];
        $orderKey = null;
        if (is_array($order) && isset($order['column'])) {
            $orderKey = $columnKeys[(int) $order['column']] ?? null;
        }
        $orderDir = is_array($order) ? (string) ($order['dir'] ?? 'asc') : 'asc';
        $search = is_array($_POST['search'] ?? null) ? (string) ($_POST['search']['value'] ?? '') : '';

        $result = $details->getSamples($req, $offset, $limit, $search, $orderKey, $orderDir);

        $escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $data = [];
        foreach ($result['rows'] as $row) {
            $data[] = array_map($escape, array_values($row));
        }
        echo JsonUtility::encodeUtf8Json([
            'draw' => (int) ($_POST['draw'] ?? 0),
            'recordsTotal' => $result['total'],
            'recordsFiltered' => $result['filtered'],
            'data' => $data,
        ]);
    }
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
    echo JsonUtility::encodeUtf8Json(['error' => _translate('Unable to load the samples right now')]);
}
