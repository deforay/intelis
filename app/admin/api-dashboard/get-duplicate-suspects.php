<?php
// get-duplicate-suspects.php - Aggregated by facility
use Laminas\Diactoros\ServerRequest;
use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
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
        $sWhere[] = " t1.request_created_datetime BETWEEN '$start_date' AND '$end_date' ";
    }

    // Lab filter
    if (isset($_POST['labName']) && trim((string) $_POST['labName']) !== '') {
        $sWhere[] = " t1.lab_id IN (" . $_POST['labName'] . ")";
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
        $sWhere[] = " t1.facility_id IN ($facilityId)";
    }

    // Get patient field names for this test type
    $patientIdColumn = TestsService::getPatientIdColumn($testType);
    $patientFirstNameColumn = TestsService::getPatientFirstNameColumn($testType);
    $patientLastNameColumn = TestsService::getPatientLastNameColumn($testType);

    // Base conditions for duplicates
    $sWhere[] = " (t1.$patientFirstNameColumn IS NOT NULL OR t1.$patientIdColumn IS NOT NULL) ";
    $sWhere[] = " t1.sample_collection_date IS NOT NULL ";

    $whereSql = $sWhere === [] ? ('') : 'WHERE ' . implode(' AND ', $sWhere);

    // Facility join for geographic filters
    $facilityJoin = 'LEFT JOIN facility_details as f ON t1.facility_id = f.facility_id';

    // Aggregated duplicates query by facility
    $query = "
    SELECT
        f.facility_name,
        f.facility_id,
        COUNT(DISTINCT duplicate_groups.patient_key) as duplicate_groups_count,
        SUM(duplicate_groups.duplicate_count) as total_duplicate_samples,
        SUM(CASE WHEN duplicate_groups.days_span <= 1 THEN 1 ELSE 0 END) as high_risk_groups,
        SUM(CASE WHEN duplicate_groups.days_span <= 3 AND duplicate_groups.days_span > 1 THEN 1 ELSE 0 END) as medium_risk_groups,
        SUM(CASE WHEN duplicate_groups.days_span > 3 THEN 1 ELSE 0 END) as low_risk_groups,
        MIN(duplicate_groups.first_collection) as earliest_duplicate,
        MAX(duplicate_groups.last_collection) as latest_duplicate
    FROM (
        SELECT
            t1.$patientIdColumn,
            t1.$patientFirstNameColumn,
            t1.$patientLastNameColumn,
            t1.facility_id,
            COALESCE(t1.$patientIdColumn, CONCAT(COALESCE(t1.$patientFirstNameColumn, ''), '_', COALESCE(t1.$patientLastNameColumn, ''))) as patient_key,
            COUNT(*) as duplicate_count,
            MIN(t1.sample_collection_date) as first_collection,
            MAX(t1.sample_collection_date) as last_collection,
            DATEDIFF(MAX(t1.sample_collection_date), MIN(t1.sample_collection_date)) as days_span,
            GROUP_CONCAT(DISTINCT COALESCE(t1.source_of_request, 'manual') SEPARATOR ',') as sources
        FROM $table as t1
        $facilityJoin
        $whereSql
        GROUP BY
            t1.facility_id,
            COALESCE(t1.$patientIdColumn, CONCAT(COALESCE(t1.$patientFirstNameColumn, ''), '_', COALESCE(t1.$patientLastNameColumn, '')))
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
    ) as duplicate_groups
    LEFT JOIN facility_details as f ON duplicate_groups.facility_id = f.facility_id
    GROUP BY f.facility_id, f.facility_name
    HAVING duplicate_groups_count > 0
    ORDER BY duplicate_groups_count DESC, total_duplicate_samples DESC";

    $results = $db->rawQuery($query);

    // Format the results
    $formattedResults = [];
    foreach ($results as $row) {
        // Risk level based on high risk groups
        $riskLevel = 'Low';
        if ($row['high_risk_groups'] > 0) {
            $riskLevel = 'High';
        } elseif ($row['medium_risk_groups'] > 0) {
            $riskLevel = 'Medium';
        }

        $formattedResults[] = [
            'facility_name' => $row['facility_name'] ?: 'Unknown Facility #' . $row['facility_id'],
            'facility_id' => $row['facility_id'],
            'duplicate_groups_count' => (int)$row['duplicate_groups_count'],
            'total_duplicate_samples' => (int)$row['total_duplicate_samples'],
            'high_risk_groups' => (int)$row['high_risk_groups'],
            'medium_risk_groups' => (int)$row['medium_risk_groups'],
            'low_risk_groups' => (int)$row['low_risk_groups'],
            'earliest_duplicate' => DateUtility::humanReadableDateFormat($row['earliest_duplicate'], true),
            'latest_duplicate' => DateUtility::humanReadableDateFormat($row['latest_duplicate'], true),
            'risk_level' => $riskLevel
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
    echo JsonUtility::encodeUtf8Json(['error' => 'Failed to load duplicate suspects']);
}
