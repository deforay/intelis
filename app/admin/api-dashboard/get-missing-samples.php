<?php
// get-missing-samples.php

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

    // FOCUS: Only API requests
    $sWhere[] = " t.source_of_request = 'api' ";

    // Only show requests without sample receipt
    $sWhere[] = " t.request_created_datetime IS NOT NULL ";
    $sWhere[] = " t.sample_received_at_lab_datetime IS NULL ";

    // Exclude rejected samples
    $sWhere[] = " (t.is_sample_rejected IS NULL OR t.is_sample_rejected != 'yes') ";

    $whereSql = !empty($sWhere) ? (' WHERE ' . implode(' AND ', $sWhere)) : '';

    $query = "
        SELECT
            t.sample_code,
            t.remote_sample_code,
            f.facility_name,
            t.request_created_datetime,
            t.source_of_request,
            DATEDIFF(NOW(), t.request_created_datetime) as days_pending,
            t.patient_first_name,
            t.patient_last_name,
            t.patient_art_no,
            t.is_sample_rejected,
            t.reason_for_sample_rejection
        FROM $table as t
        LEFT JOIN facility_details as f ON t.facility_id = f.facility_id
        $whereSql
        ORDER BY t.request_created_datetime ASC
        LIMIT 100";

    $results = $db->rawQuery($query);

    // Format the results
    $formattedResults = [];
    foreach ($results as $row) {

        // Priority level based on days pending
        $priority = 'Low';
        if ($row['days_pending'] > 7) {
            $priority = 'High';
        } elseif ($row['days_pending'] > 3) {
            $priority = 'Medium';
        }

        $formattedResults[] = [
            'sample_code' => $row['sample_code'] ?: $row['remote_sample_code'],
            'remote_sample_code' => $row['remote_sample_code'],
            'facility_name' => $row['facility_name'] ?: 'Unknown Facility',
            'days_pending' => $row['days_pending'],
            'source_of_request' => 'API', // Since we're only showing API requests
            'request_date' => DateUtility::humanReadableDateFormat($row['request_created_datetime'], true),
            'patient_info' => trim(($row['patient_first_name'] ?? '') . ' ' . ($row['patient_last_name'] ?? '')) ?: $row['patient_art_no'],
            'priority' => $priority,
            'rejection_status' => $row['is_sample_rejected'] ?: 'No',
            'rejection_reason' => $row['reason_for_sample_rejection'] ?: ''
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
