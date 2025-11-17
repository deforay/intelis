<?php
// get-non-originating-results.php
use Laminas\Diactoros\ServerRequest;
use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {
    $testType = $_POST['testType'] ?? 'vl';
    $table = TestsService::getTestTableName($testType);
    $primaryColumn = TestsService::getPrimaryColumn($testType);

    $sWhere = [];

    // Date range filter
    if (isset($_POST['dateRange']) && trim((string) $_POST['dateRange']) !== '') {
        [$start_date, $end_date] = DateUtility::convertDateRange($_POST['dateRange'] ?? '', includeTime: true);
        $sWhere[] = " t.request_created_datetime BETWEEN '$start_date' AND '$end_date' ";
    }

    // Look for results that were sent but didn't originate from API
    $sWhere[] = " t.result_pulled_via_api_datetime IS NOT NULL ";
    $sWhere[] = " COALESCE(t.source_of_request, 'manual') != 'api' ";

    $whereSql = $sWhere === [] ? ('') : ' WHERE ' . implode(' AND ', $sWhere);

    $query = "
        SELECT
            t.sample_code,
            t.remote_sample_code,
            f.facility_name,
            COALESCE(t.source_of_request, 'manual') as request_source,
            t.request_created_datetime,
            t.result_pulled_via_api_datetime,
            t.result_sent_to_source,
            DATEDIFF(t.result_pulled_via_api_datetime, t.request_created_datetime) as days_diff,
            t.patient_first_name,
            t.patient_last_name,
            t.patient_art_no
        FROM $table as t
        LEFT JOIN facility_details as f ON t.facility_id = f.facility_id
        $whereSql
        ORDER BY t.result_pulled_via_api_datetime DESC
        LIMIT 100";

    $results = $db->rawQuery($query);

    // Format the results
    $formattedResults = [];
    foreach ($results as $row) {
        $requestSource = $row['request_source'];
        $readableSource = '';
        $readableSource = match ($requestSource) {
            'api' => 'API',
            'vlsts' => 'STS',
            'vlsm', 'manual' => 'LIS (Manual)',
            'app' => 'Tablet App',
            default => ucfirst((string) $requestSource),
        };

        // Determine issue type
        $issueType = '';
        if ($requestSource === 'vlsm' || $requestSource === 'manual') {
            $issueType = 'Manual entry result sent to API';
        } elseif ($requestSource === 'vlsts') {
            $issueType = 'STS result sent to API';
        } else {
            $issueType = 'Non-API/EMR result sent';
        }

        $formattedResults[] = [
            'sample_code' => $row['sample_code'] ?: $row['remote_sample_code'],
            'facility_name' => $row['facility_name'] ?: 'Unknown Facility',
            'request_source' => $readableSource,
            'result_sent_date' => DateUtility::humanReadableDateFormat($row['result_pulled_via_api_datetime'], true),
            'days_diff' => $row['days_diff'],
            'issue_type' => $issueType,
            'result_sent_to_source' => $row['result_sent_to_source'],
            'patient_info' => trim(($row['patient_first_name'] ?? '') . ' ' . ($row['patient_last_name'] ?? '')) ?: $row['patient_art_no']
        ];
    }

    echo JsonUtility::encodeUtf8Json($formattedResults);

} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
    echo JsonUtility::encodeUtf8Json(['error' => 'Failed to load non-originating results']);
}
