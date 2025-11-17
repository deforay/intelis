<?php
// get-missing-samples.php - Aggregated by facility version
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

    // Lab filter
    if (isset($_POST['labName']) && trim((string) $_POST['labName']) !== '') {
        $sWhere[] = " t.lab_id IN (" . $_POST['labName'] . ")";
    }

    // State filter
    if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
        $provinceId = implode(',', $_POST['state']);
        $sWhere[] = " f.facility_state_id IN ($provinceId)";
    }

    // District filter
    if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
        $districtId = implode(',', $_POST['district']);
        $sWhere[] = " f.facility_district_id IN ($districtId)";
    }

    // Facility filter
    if (isset($_POST['facilityId']) && trim((string) $_POST['facilityId']) !== '') {
        $facilityId = implode(',', $_POST['facilityId']);
        $sWhere[] = " t.facility_id IN ($facilityId)";
    }

    // FOCUS: Only API/EMR requests
    $sWhere[] = " t.source_of_request = 'api' ";

    // Only show requests without sample receipt
    $sWhere[] = " t.request_created_datetime IS NOT NULL ";
    $sWhere[] = " t.sample_received_at_lab_datetime IS NULL ";

    // Exclude rejected samples
    $sWhere[] = " (t.is_sample_rejected IS NULL OR t.is_sample_rejected != 'yes') ";

    $whereSql = $sWhere === [] ? ('') : 'WHERE ' . implode(' AND ', $sWhere);

    // Aggregated query by facility
    $query = "
        SELECT
            f.facility_name,
            f.facility_id,
            COUNT(*) as missing_count,
            MIN(DATEDIFF(NOW(), t.request_created_datetime)) as min_days_pending,
            MAX(DATEDIFF(NOW(), t.request_created_datetime)) as max_days_pending,
            AVG(DATEDIFF(NOW(), t.request_created_datetime)) as avg_days_pending,
            SUM(CASE WHEN DATEDIFF(NOW(), t.request_created_datetime) > 7 THEN 1 ELSE 0 END) as over_7_days,
            SUM(CASE WHEN DATEDIFF(NOW(), t.request_created_datetime) <= 7 THEN 1 ELSE 0 END) as within_7_days,
            MIN(t.request_created_datetime) as oldest_request,
            MAX(t.request_created_datetime) as newest_request
        FROM $table as t
        LEFT JOIN facility_details as f ON t.facility_id = f.facility_id
        $whereSql
        GROUP BY f.facility_id, f.facility_name
        HAVING missing_count > 0
        ORDER BY missing_count DESC, max_days_pending DESC";

    $results = $db->rawQuery($query);

    // Format the results
    $formattedResults = [];
    foreach ($results as $row) {
        // Priority level based on max days pending and count
        $priority = 'Low';
        if ($row['max_days_pending'] > 14 || $row['missing_count'] > 10) {
            $priority = 'Critical';
        } elseif ($row['max_days_pending'] > 7 || $row['missing_count'] > 5) {
            $priority = 'High';
        } elseif ($row['max_days_pending'] > 3 || $row['missing_count'] > 2) {
            $priority = 'Medium';
        }

        $formattedResults[] = [
            'facility_name' => $row['facility_name'] ?: 'Unknown Facility #' . $row['facility_id'],
            'facility_id' => $row['facility_id'],
            'missing_count' => (int)$row['missing_count'],
            'min_days_pending' => (int)$row['min_days_pending'],
            'max_days_pending' => (int)$row['max_days_pending'],
            'avg_days_pending' => round($row['avg_days_pending'], 1),
            'over_7_days' => (int)$row['over_7_days'],
            'within_7_days' => (int)$row['within_7_days'],
            'oldest_request' => DateUtility::humanReadableDateFormat($row['oldest_request'], true),
            'newest_request' => DateUtility::humanReadableDateFormat($row['newest_request'], true),
            'priority' => $priority,
            'source_of_request' => 'API/EMR'
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
    echo JsonUtility::encodeUtf8Json(['error' => 'Failed to load missing samples']);
}
