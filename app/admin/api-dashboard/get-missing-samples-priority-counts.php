<?php
// get-missing-samples-priority-counts.php
use Psr\Http\Message\ServerRequestInterface;
use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

try {
    $testType = $_POST['testType'] ?? 'vl';
    $table = TestsService::getTestTableName($testType);
    $primaryColumn = TestsService::getPrimaryColumn($testType);

    $sWhere = [];

    // Base conditions for missing samples - API/EMR requests only
    $sWhere[] = " t.source_of_request = 'api' ";
    $sWhere[] = " t.request_created_datetime IS NOT NULL ";
    $sWhere[] = " t.sample_received_at_lab_datetime IS NULL ";
    $sWhere[] = " (t.is_sample_rejected IS NULL OR t.is_sample_rejected != 'yes') ";

    // Date range filter
    if (isset($_POST['dateRange']) && trim((string) $_POST['dateRange']) !== '') {
        [$start_date, $end_date] = DateUtility::convertDateRange($_POST['dateRange'] ?? '', includeTime: true);
        $sWhere[] = " t.request_created_datetime BETWEEN '$start_date' AND '$end_date' ";
    }

    $whereSql = $sWhere === [] ? ('') : ' WHERE ' . implode(' AND ', $sWhere);

    $priorityQuery = "
        SELECT
            SUM(CASE WHEN DATEDIFF(NOW(), t.request_created_datetime) > 7 THEN 1 ELSE 0 END) as high,
            SUM(CASE WHEN DATEDIFF(NOW(), t.request_created_datetime) BETWEEN 4 AND 7 THEN 1 ELSE 0 END) as medium,
            SUM(CASE WHEN DATEDIFF(NOW(), t.request_created_datetime) <= 3 THEN 1 ELSE 0 END) as low,
            COUNT(*) as total
        FROM $table as t
        $whereSql";

    $counts = $db->rawQueryOne($priorityQuery);

    echo JsonUtility::encodeUtf8Json($counts);

} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
    echo JsonUtility::encodeUtf8Json(['error' => 'Failed to load priority counts']);
}
