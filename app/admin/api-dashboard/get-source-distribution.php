<?php
// get-source-distribution.php

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var Laminas\Diactoros\ServerRequest $request */
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
    if (isset($_POST['dateRange']) && trim((string) $_POST['dateRange']) != '') {
        [$start_date, $end_date] = DateUtility::convertDateRange($_POST['dateRange'] ?? '', includeTime: true);
        $sWhere[] = " t.request_created_datetime BETWEEN '$start_date' AND '$end_date' ";
    }

    // Lab filter
    if (isset($_POST['labName']) && trim((string) $_POST['labName']) != '') {
        $sWhere[] = " t.lab_id IN (" . $_POST['labName'] . ")";
    }

    // State filter
    if (isset($_POST['state']) && trim((string) $_POST['state']) != '') {
        $provinceId = implode(',', $_POST['state']);
        $sWhere[] = " f.facility_state_id IN ($provinceId)";
    }

    // District filter
    if (isset($_POST['district']) && trim((string) $_POST['district']) != '') {
        $districtId = implode(',', $_POST['district']);
        $sWhere[] = " f.facility_district_id IN ($districtId)";
    }

    // Facility filter
    if (isset($_POST['facilityId']) && trim((string) $_POST['facilityId']) != '') {
        $facilityId = implode(',', $_POST['facilityId']);
        $sWhere[] = " t.facility_id IN ($facilityId)";
    }

    $whereSql = !empty($sWhere) ? (' WHERE ' . implode(' AND ', $sWhere)) : '';

    // Get source distribution by facility - Remove LIMIT to show all facilities
    $query = "
        SELECT
            f.facility_name,
            f.facility_id,
            COALESCE(f.facility_state, '') as province,
            COALESCE(f.facility_district, '') as district,
            SUM(CASE WHEN COALESCE(t.source_of_request, 'manual') = 'api' THEN 1 ELSE 0 END) as api_count,
            SUM(CASE WHEN COALESCE(t.source_of_request, 'manual') = 'vlsts' THEN 1 ELSE 0 END) as sts_count,
            SUM(CASE WHEN COALESCE(t.source_of_request, 'manual') IN ('vlsm', 'manual') THEN 1 ELSE 0 END) as lis_count,
            COUNT(*) as total_count,
            ROUND((SUM(CASE WHEN COALESCE(t.source_of_request, 'manual') = 'api' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as api_percentage
        FROM $table as t
        LEFT JOIN facility_details as f ON t.facility_id = f.facility_id
        $whereSql
        GROUP BY f.facility_id, f.facility_name, f.facility_state, f.facility_district
        HAVING total_count > 0
        ORDER BY total_count DESC, api_percentage DESC";

    $results = $db->rawQuery($query);

    // Format the results
    $formattedResults = [];
    foreach ($results as $row) {
        $facilityName = $row['facility_name'] ?: 'Unknown Facility #' . $row['facility_id'];

        // Add location info if available
        $locationInfo = '';
        if (!empty($row['district'])) {
            $locationInfo = $row['district'];
            if (!empty($row['province'])) {
                $locationInfo .= ', ' . $row['province'];
            }
        }

        $formattedResults[] = [
            'facility_name' => $facilityName,
            'facility_id' => $row['facility_id'],
            'location' => $locationInfo,
            'api_count' => (int)$row['api_count'],
            'sts_count' => (int)$row['sts_count'],
            'lis_count' => (int)$row['lis_count'],
            'total_count' => (int)$row['total_count'],
            'api_percentage' => (float)$row['api_percentage']
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
    echo JsonUtility::encodeUtf8Json(['error' => 'Failed to load source distribution']);
}

