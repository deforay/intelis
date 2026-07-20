<?php

use Psr\Http\Message\ServerRequestInterface;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {
    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);

    $aColumns = [
        'a.instrument_id',
        'a.machine_type',
        'a.event_type',
        'a.outcome',
        'a.failure_code',
        'a.app_version',
        'f.facility_name',
    ];
    $orderColumns = [
        'a.occurred_at',
        'f.facility_name',
        'a.instrument_id',
        'a.event_type',
        'a.outcome',
        'a.failure_code',
        'a.app_version',
    ];

    $sOffset = $sLimit = null;
    if (isset($_POST['iDisplayStart']) && $_POST['iDisplayLength'] != '-1') {
        $sOffset = $_POST['iDisplayStart'];
        $sLimit = $_POST['iDisplayLength'];
    }

    $sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);

    $sWhere = [];
    $columnSearch = $general->multipleColumnSearch($_POST['sSearch'] ?? '', $aColumns);
    if (!empty($columnSearch)) {
        $sWhere[] = $columnSearch;
    }

    // An operator only ever sees their own lab's machines. Applied here rather than
    // in the page so the endpoint is safe when called directly.
    $labScope = $general->labAdminScopeWhere('lab_id', 'a');
    if (!empty($labScope)) {
        $sWhere[] = $labScope;
    }

    if (isset($_POST['dateRange']) && trim((string) $_POST['dateRange']) !== '') {
        [$startDate, $endDate] = DateUtility::convertDateRange($_POST['dateRange'] ?? '');
        $sWhere[] = " DATE(a.occurred_at) BETWEEN '$startDate' AND '$endDate' ";
    }

    if (isset($_POST['outcome']) && trim((string) $_POST['outcome']) !== '') {
        $sWhere[] = " a.outcome = '" . $db->escape($_POST['outcome']) . "' ";
    }

    if (isset($_POST['eventType']) && trim((string) $_POST['eventType']) !== '') {
        $sWhere[] = " a.event_type = '" . $db->escape($_POST['eventType']) . "' ";
    }

    if (isset($_POST['instrument']) && trim((string) $_POST['instrument']) !== '') {
        $sWhere[] = " a.instrument_id LIKE '%" . $db->escape($_POST['instrument']) . "%' ";
    }

    $sQuery = "SELECT a.activity_id, a.occurred_at, a.instrument_id, a.machine_type,
                      a.event_type, a.event_category, a.outcome, a.failure_code,
                      a.protocol, a.connection_mode, a.app_version, a.received_via,
                      f.facility_name
                 FROM instrument_activity_log AS a
                 LEFT JOIN facility_details AS f ON f.facility_id = a.lab_id";

    if (!empty($sWhere)) {
        $sQuery .= ' WHERE ' . implode(' AND ', $sWhere);
    }

    if (!empty($sOrder)) {
        $sOrder = preg_replace('/\s+/', ' ', (string) $sOrder);
        $sQuery = "$sQuery ORDER BY $sOrder";
    } else {
        $sQuery = "$sQuery ORDER BY a.occurred_at DESC";
    }

    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
    }

    [$rResult, $resultCount] = $db->getDataAndCount($sQuery);

    $output = [
        "sEcho" => (int) ($_POST['sEcho'] ?? 0),
        "iTotalRecords" => $resultCount,
        "iTotalDisplayRecords" => $resultCount,
        "aaData" => []
    ];

    $escape = static fn(?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

    foreach ($rResult as $aRow) {
        // Colour by what needs attention: a failure is the only thing an operator
        // has to act on, so nothing else competes with it.
        $outcome = (string) $aRow['outcome'];
        $outcomeClass = match ($outcome) {
            'failed' => 'ifa-pill ifa-pill-failed',
            'success' => 'ifa-pill ifa-pill-success',
            default => 'ifa-pill ifa-pill-muted',
        };

        $row = [];
        $row[] = DateUtility::humanReadableDateFormat($aRow['occurred_at'], true);
        $row[] = $escape($aRow['facility_name'] ?? '-');
        $row[] = $escape($aRow['instrument_id'] ?? '-')
            . ($aRow['machine_type'] ? "<br><small class='text-muted'>"
                . $escape($aRow['machine_type']) . "</small>" : '');
        $row[] = $escape($aRow['event_type']);
        $row[] = "<span class='$outcomeClass'>" . $escape(ucfirst($outcome)) . "</span>";
        $row[] = $aRow['failure_code'] ? $escape($aRow['failure_code']) : '-';
        $row[] = $aRow['protocol']
            ? $escape($aRow['protocol']) . ' / ' . $escape($aRow['connection_mode'] ?? '-')
            : '-';
        $row[] = $escape($aRow['app_version'] ?? '-');
        $output['aaData'][] = $row;
    }

    echo JsonUtility::encodeUtf8Json($output);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
}
