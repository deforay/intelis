<?php
// get-duplicate-suspects.php

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\JsonUtility;
use App\Registries\AppRegistry;
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
        $sWhere[] = " t1.request_created_datetime BETWEEN '$start_date' AND '$end_date' ";
    }

    // Look for potential duplicates within 7 days
    // At least one duplicate must be from API
    $sWhere[] = " (t1.patient_first_name IS NOT NULL OR t1.patient_art_no IS NOT NULL) ";
    $sWhere[] = " t1.sample_collection_date IS NOT NULL ";

    $whereSql = !empty($sWhere) ? (' WHERE ' . implode(' AND ', $sWhere)) : '';


    // Get patient field names for this test type
    $patientIdColumn = TestsService::getPatientIdColumn($testType);
    $patientFirstNameColumn = TestsService::getPatientFirstNameColumn($testType);
    $patientLastNameColumn = TestsService::getPatientLastNameColumn($testType);

    // Find potential duplicates: same patient name/ART no within +/- 7 days
    // At least one request must be from API
    $sWhere[] = " (t1.$patientFirstNameColumn IS NOT NULL OR t1.$patientIdColumn IS NOT NULL) ";
    $sWhere[] = " t1.sample_collection_date IS NOT NULL ";

    $whereSql = !empty($sWhere) ? (' WHERE ' . implode(' AND ', $sWhere)) : '';

    $query = "
    SELECT
        t1.$patientFirstNameColumn,
        t1.$patientLastNameColumn,
        t1.$patientIdColumn,
        t1.facility_id,
        t1.lab_id,
        GROUP_CONCAT(DISTINCT COALESCE(t1.sample_code, t1.remote_sample_code) ORDER BY t1.sample_collection_date SEPARATOR ', ') as sample_ids,
        GROUP_CONCAT(DISTINCT DATE(t1.sample_collection_date) ORDER BY t1.sample_collection_date SEPARATOR ', ') as collection_dates,
        GROUP_CONCAT(DISTINCT COALESCE(t1.source_of_request, 'manual') ORDER BY t1.sample_collection_date SEPARATOR ', ') as sources,
        COUNT(*) as duplicate_count,
        MIN(t1.sample_collection_date) as first_collection,
        MAX(t1.sample_collection_date) as last_collection,
        DATEDIFF(MAX(t1.sample_collection_date), MIN(t1.sample_collection_date)) as days_span
    FROM $table as t1
    $whereSql
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
    ORDER BY last_collection DESC
    LIMIT 100";


    $results = $db->rawQuery($query);

    // Format the results
    $formattedResults = [];
    foreach ($results as $row) {
        $patientInfo = '';
        if (!empty($row[$patientIdColumn])) {
            // Use appropriate label based on test type
            $idLabel = ($testType === 'vl' || $testType === 'cd4') ? 'ART' : 'ID';
            $patientInfo = '<strong>'.$idLabel . '</strong>: ' . $row[$patientIdColumn];
        }
        if (!empty($row[$patientFirstNameColumn]) || !empty($row[$patientLastNameColumn])) {
            $name = trim(($row[$patientFirstNameColumn] ?? '') . ' ' . ($row[$patientLastNameColumn] ?? ''));
            if (!empty($name)) {
                $patientInfo .= (!empty($patientInfo) ? ' <br> ' : '') . '<strong>Name: </strong>' . $name;
            }
        }

        // Parse sources and make them readable
        $sources = explode(', ', $row['sources']);
        $readableSources = array_map(fn($source) => $source === 'api' ? 'API' : ucfirst($source), array_unique($sources));

        $formattedResults[] = [
            'patient_info' => $patientInfo,
            'sample_ids' => $row['sample_ids'],
            'collection_dates' => $row['collection_dates'],
            'sources' => implode(', ', $readableSources),
            'duplicate_count' => $row['duplicate_count'],
            'days_span' => $row['days_span'],
            'risk_level' => $row['days_span'] <= 1 ? 'High' : ($row['days_span'] <= 3 ? 'Medium' : 'Low')
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
