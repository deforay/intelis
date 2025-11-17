<?php
// get-api-dashboard-metrics.php
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

    $whereSql = $sWhere === [] ? ('') : ' WHERE ' . implode(' AND ', $sWhere);

    // Add facility join if needed for geographic filters
    $facilityJoin = '';
    if (isset($_POST['state']) || isset($_POST['district'])) {
        $facilityJoin = ' LEFT JOIN facility_details as f ON t.facility_id = f.facility_id ';
    }

    // Quick metrics query
    $metricsQuery = "SELECT
            COUNT(*) as totalRequests,
            SUM(CASE WHEN t.source_of_request = 'api' THEN 1 ELSE 0 END) as apiRequests,
            SUM(CASE WHEN t.source_of_request != 'api' OR t.source_of_request IS NULL THEN 1 ELSE 0 END) as nonApiRequests,
            SUM(CASE WHEN t.source_of_request = 'api' AND t.sample_received_at_lab_datetime IS NOT NULL THEN 1 ELSE 0 END) as apiReceivedAtLab,
            SUM(CASE WHEN t.source_of_request = 'api' AND t.sample_received_at_lab_datetime IS NULL AND (t.is_sample_rejected IS NULL OR t.is_sample_rejected != 'yes') THEN 1 ELSE 0 END) as apiNotReceivedAtLab,
            SUM(CASE WHEN t.source_of_request = 'api' AND t.sample_received_at_lab_datetime IS NOT NULL THEN 1 ELSE 0 END) as totalReceivedAtLab,
            SUM(CASE WHEN t.source_of_request = 'api' AND t.sample_received_at_lab_datetime IS NOT NULL AND t.sample_tested_datetime IS NOT NULL THEN 1 ELSE 0 END) as receivedAndTested,
            SUM(CASE WHEN t.source_of_request = 'api' AND t.is_sample_rejected = 'yes' THEN 1 ELSE 0 END) as rejectedSamples,
            SUM(CASE WHEN t.source_of_request = 'api' AND t.sample_received_at_lab_datetime IS NOT NULL AND t.sample_tested_datetime IS NULL AND (t.is_sample_rejected IS NULL OR t.is_sample_rejected != 'yes') THEN 1 ELSE 0 END) as receivedNotTested,
            SUM(CASE WHEN t.source_of_request = 'api' AND t.sample_received_at_lab_datetime IS NULL AND (t.is_sample_rejected IS NULL OR t.is_sample_rejected != 'yes') AND DATEDIFF(NOW(), t.request_created_datetime) <= 7 THEN 1 ELSE 0 END) as notReceivedWithin7Days,
            SUM(CASE WHEN t.source_of_request = 'api' AND t.sample_received_at_lab_datetime IS NULL AND (t.is_sample_rejected IS NULL OR t.is_sample_rejected != 'yes') AND DATEDIFF(NOW(), t.request_created_datetime) > 7 THEN 1 ELSE 0 END) as notReceivedOver7Days,
            SUM(CASE WHEN t.result_pulled_via_api_datetime IS NOT NULL AND t.source_of_request = 'api' THEN 1 ELSE 0 END) as resultsSentViaApi,
SUM(CASE WHEN t.result_pulled_via_api_datetime IS NOT NULL AND (t.source_of_request != 'api' OR t.source_of_request IS NULL) THEN 1 ELSE 0 END) as resultsSentOtherMethods
        FROM $table as t
        $facilityJoin
        $whereSql";

    $metrics = $db->rawQueryOne($metricsQuery);

    // Calculate derived metrics
    $metrics['totalNotReceivedAtLab'] = $metrics['notReceivedWithin7Days'] + $metrics['notReceivedOver7Days'];

    // DUPLICATE DETECTION LOGIC - Simplified approach
    $duplicatesWhere = [];

    // Date range filter
    if (isset($_POST['dateRange']) && trim((string) $_POST['dateRange']) !== '') {
        [$start_date, $end_date] = DateUtility::convertDateRange($_POST['dateRange'] ?? '', includeTime: true);
        $duplicatesWhere[] = " t1.request_created_datetime BETWEEN '$start_date' AND '$end_date' ";
    }

    // Lab filter
    if (isset($_POST['labName']) && trim((string) $_POST['labName']) !== '') {
        $duplicatesWhere[] = " t1.lab_id IN (" . $_POST['labName'] . ")";
    }

    // Facility filter
    if (isset($_POST['facilityId']) && trim((string) $_POST['facilityId']) !== '') {
        $facilityId = implode(',', $_POST['facilityId']);
        $duplicatesWhere[] = " t1.facility_id IN ($facilityId)";
    }

    // Geographic filters
    $duplicatesFacilityJoin = '';
    if (isset($_POST['state']) || isset($_POST['district'])) {
        $duplicatesFacilityJoin = ' LEFT JOIN facility_details as f1 ON t1.facility_id = f1.facility_id ';

        if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
            $provinceId = implode(',', $_POST['state']);
            $duplicatesWhere[] = " f1.facility_state_id IN ($provinceId)";
        }

        if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
            $districtId = implode(',', $_POST['district']);
            $duplicatesWhere[] = " f1.facility_district_id IN ($districtId)";
        }
    }

    // Get patient field names for this test type
    $patientIdColumn = TestsService::getPatientIdColumn($testType);
    $patientFirstNameColumn = TestsService::getPatientFirstNameColumn($testType);
    $patientLastNameColumn = TestsService::getPatientLastNameColumn($testType);

    // Base conditions for duplicates
    $duplicatesWhere[] = " (t1.$patientFirstNameColumn IS NOT NULL OR t1.$patientIdColumn IS NOT NULL) ";
    $duplicatesWhere[] = " t1.sample_collection_date IS NOT NULL ";

    $duplicatesWhereSql = $duplicatesWhere === [] ? ('') : ' WHERE ' . implode(' AND ', $duplicatesWhere);

    $duplicatesQuery = "
    SELECT COUNT(*) as duplicateSuspects
    FROM (
        SELECT
            t1.$patientFirstNameColumn,
            t1.$patientLastNameColumn,
            t1.$patientIdColumn,
            t1.facility_id,
            t1.lab_id,
            COUNT(*) as duplicate_count,
            MIN(t1.sample_collection_date) as first_collection,
            MAX(t1.sample_collection_date) as last_collection,
            DATEDIFF(MAX(t1.sample_collection_date), MIN(t1.sample_collection_date)) as days_span,
            GROUP_CONCAT(DISTINCT COALESCE(t1.source_of_request, 'manual') SEPARATOR ',') as sources
        FROM $table as t1
        $duplicatesFacilityJoin
        $duplicatesWhereSql
        GROUP BY
            COALESCE(t1.$patientIdColumn, CONCAT(COALESCE(t1.$patientFirstNameColumn, ''), '_', COALESCE(t1.$patientLastNameColumn, ''))),
            t1.facility_id,
            t1.lab_id
        HAVING
            duplicate_count > 1
            AND days_span <= 7
            AND (
                (t1.$patientIdColumn IS NOT NULL)
                OR
                (t1.$patientFirstNameColumn IS NOT NULL AND t1.$patientLastNameColumn IS NOT NULL)
            )
            AND (
                FIND_IN_SET('api', sources) > 0
            )
    ) as duplicate_groups";

    $duplicatesResult = $db->rawQueryOne($duplicatesQuery);
    $metrics['duplicateSuspects'] = $duplicatesResult['duplicateSuspects'] ?? 0;

    echo JsonUtility::encodeUtf8Json($metrics);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
    echo JsonUtility::encodeUtf8Json(['error' => 'Failed to load metrics']);
}
