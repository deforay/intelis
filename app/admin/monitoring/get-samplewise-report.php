<?php

use App\Utilities\DataTableUtility;
use App\Utilities\SourcesOfRequestsReportUtility;
use Psr\Http\Message\ServerRequestInterface;
use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// AJAX requests skip the page ACL, so this checks the report's own privilege.
_requirePrivilege('/admin/monitoring/sources-of-requests.php');

// Sanitized values from $request object
/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());


/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);
try {

    /** @var CommonService $general */
    $general = ContainerRegistry::get(CommonService::class);


    $testType = (string) ($_POST['testType'] ?? 'vl');
    ['from' => $fromQuery, 'where' => $reportWhere] = SourcesOfRequestsReportUtility::reportScope($_POST, $general);
    $returnedOn = SourcesOfRequestsReportUtility::RETURNED_ON;

    // In the order the page shows them.
    $orderColumns = [
        'vl.sample_code',
        'vl.source_of_request',
        'f.facility_name',
        'l.facility_name',
        'vl.request_created_datetime',
        'vl.sample_received_at_lab_datetime',
        'vl.sample_tested_datetime',
        $returnedOn,
        'ts.status_name',
    ];
    // The ID cell shows every ID a sample has, so the search box looks in all of them.
    $searchColumns = [
        'vl.sample_code',
        'vl.remote_sample_code',
        'vl.external_sample_code',
        'vl.app_sample_code',
        'vl.source_of_request',
        'f.facility_name',
        'l.facility_name',
        'ts.status_name',
    ];

    [$sOffset, $sLimit] = DataTableUtility::paging($_POST);

    $sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);

    // The summaries compare every source and clinic, so the source, stage, clinic
    // and search box narrow only the sample list.
    $sWhere = $reportWhere;
    $source = trim((string) ($_POST['originalSourceOfRequest'] ?? ''));
    if ($source === 'unrecorded') {
        $sWhere[] = "IFNULL(vl.source_of_request, '') = ''";
    } elseif ($source !== '') {
        $stored = array_map(
            fn(string $value): string => "'" . $db->escape($value) . "'",
            CommonService::storedSourcesOfRequest($source)
        );
        $sWhere[] = 'vl.source_of_request IN (' . implode(', ', $stored) . ')';
    }
    // A clinic picked from the clinic summary; 0 is requests with no clinic.
    $clinicId = trim((string) ($_POST['clinicId'] ?? ''));
    if ($clinicId !== '' && ctype_digit($clinicId)) {
        $sWhere[] = 'IFNULL(vl.facility_id, 0) = ' . (int) $clinicId;
    }
    $stageWhere = SourcesOfRequestsReportUtility::stageWhere((string) ($_POST['stage'] ?? ''));
    if ($stageWhere !== null) {
        $sWhere[] = $stageWhere;
    }
    $columnSearch = $general->multipleColumnSearch($_POST['sSearch'] ?? '', $searchColumns);
    if (!empty($columnSearch)) {
        $sWhere[] = $columnSearch;
    }

    $whereSql = ' WHERE ' . implode(' AND ', $sWhere);
    $summaryFrom = "$fromQuery WHERE " . implode(' AND ', $reportWhere);

    $sQuery = "SELECT
                    f.facility_name,
                    l.facility_name as 'labname',
                    vl.sample_code,
                    vl.source_of_request,
                    ts.status_name,
                    vl.external_sample_code,
                    vl.app_sample_code,
                    vl.sample_tested_datetime,
                    vl.remote_sample_code,
                    vl.request_created_datetime,
                    vl.sample_received_at_lab_datetime,
                    b.request_created_datetime as batch_request_created,
                    vl." . TestsService::getResultColumn($testType) . " AS result,
                    vl.result_approved_datetime,
                    $returnedOn AS result_returned_datetime,
                    vl.last_modified_datetime $fromQuery $whereSql";
    if (!empty($sOrder)) {
        $sOrder = preg_replace('/\s+/', ' ', (string) $sOrder);
        $sQuery = "$sQuery ORDER BY $sOrder";
    }

    $_SESSION['samplewiseReportsQuery'] = $sQuery;
    $_SESSION['samplewiseReportsSummaryFrom'] = $summaryFrom;

    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
    }

    [$rResult, $resultCount] = $db->getDataAndCount($sQuery);
    $_SESSION['samplewiseReportsQueryCount'] = $resultCount;

    $output = [
        "sEcho" => (int) $_POST['sEcho'],
        "iTotalRecords" => $resultCount,
        "iTotalDisplayRecords" => $resultCount,
        "aaData" => []
    ];

    // Paging and sorting leave the summary as it is, so the page asks for it
    // only when the filters change.
    if (($_POST['withSummary'] ?? '') === 'yes') {
        $output['summary'] = SourcesOfRequestsReportUtility::summary($db, $summaryFrom);
    }

    $escape = fn(?string $value): string => htmlspecialchars((string) $value, ENT_QUOTES);
    foreach ($rResult as $aRow) {
        // The lab's ID first, then the STS and external IDs the sample also has.
        $ids = array_values(array_unique(array_filter([
            $aRow['sample_code'],
            $aRow['remote_sample_code'],
            $aRow['external_sample_code'] ?: $aRow['app_sample_code'],
        ], fn($id): bool => trim((string) $id) !== '')));
        $idCell = $escape($ids[0] ?? '');
        foreach (array_slice($ids, 1) as $otherId) {
            $idCell .= '<br><small class="text-muted">' . $escape($otherId) . '</small>';
        }

        $output['aaData'][] = [
            $idCell,
            $escape(CommonService::sourceOfRequestLabel((string) $aRow['source_of_request'])),
            $escape($aRow['facility_name']),
            $escape($aRow['labname']),
            DateUtility::humanReadableDateFormat($aRow['request_created_datetime'], true),
            DateUtility::humanReadableDateFormat($aRow['sample_received_at_lab_datetime'], true),
            DateUtility::humanReadableDateFormat($aRow['sample_tested_datetime'], true),
            DateUtility::humanReadableDateFormat($aRow['result_returned_datetime'], true),
            $escape($aRow['status_name']),
        ];
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
    throw $e;
}
