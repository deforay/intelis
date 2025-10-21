<?php
// get-missing-samples-detail.php

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

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {

    $testType = $_POST['testType'] ?? 'vl';
    $table = TestsService::getTestTableName($testType);
    $primaryColumn = TestsService::getPrimaryColumn($testType);

    $orderColumns = $aColumns = [
        't.request_created_datetime',
        't.sample_code',
        't.remote_sample_code',
        'patient_info',
        'f.facility_name',
        't.request_created_datetime',
        'days_pending',
        't.source_of_request'
    ];

    $sOffset = $sLimit = null;
    if (isset($_POST['start']) && $_POST['length'] != '-1') {
        $sOffset = $_POST['start'];
        $sLimit = $_POST['length'];
    }

    $sOrder = $general->generateDataTablesSorting($_POST, $orderColumns);

    $columnSearch = $general->multipleColumnSearch($_POST['search']['value'] ?? '', $aColumns);
    $sWhere = [];
    if (!empty($columnSearch) && $columnSearch != '') {
        $sWhere[] = $columnSearch;
    }

    // Base conditions for missing samples - API/EMR requests only
    $sWhere[] = " t.source_of_request = 'api' ";
    $sWhere[] = " t.request_created_datetime IS NOT NULL ";
    $sWhere[] = " t.sample_received_at_lab_datetime IS NULL ";
    $sWhere[] = " (t.is_sample_rejected IS NULL OR t.is_sample_rejected != 'yes') ";

    // Date range filter
    if (isset($_POST['dateRange']) && trim((string) $_POST['dateRange']) != '') {
        [$start_date, $end_date] = DateUtility::convertDateRange($_POST['dateRange'] ?? '', includeTime: true);
        $sWhere[] = " t.request_created_datetime BETWEEN '$start_date' AND '$end_date' ";
    }

    $whereSql = !empty($sWhere) ? (' WHERE ' . implode(' AND ', $sWhere)) : '';

    $fromQuery = "
        FROM $table as t
        LEFT JOIN facility_details as f ON t.facility_id = f.facility_id";

    // Get patient field names for this test type
    $patientIdColumn = TestsService::getPatientIdColumn($testType);
    $patientFirstNameColumn = TestsService::getPatientFirstNameColumn($testType);
    $patientLastNameColumn = TestsService::getPatientLastNameColumn($testType);

    $sQuery = "SELECT
                t.$primaryColumn,
                t.sample_code,
                t.remote_sample_code,
                t.external_sample_code,
                CONCAT(
                    COALESCE(t.$patientFirstNameColumn, ''), ' ',
                    COALESCE(t.$patientLastNameColumn, ''),
                    CASE
                        WHEN t.$patientIdColumn IS NOT NULL
                        THEN CONCAT(' (', t.$patientIdColumn, ')')
                        ELSE ''
                    END
                ) as patient_info,
                f.facility_name,
                t.request_created_datetime,
                DATEDIFF(NOW(), t.request_created_datetime) as days_pending,
                COALESCE(t.source_of_request, 'manual') as source_of_request,
                CASE
                    WHEN DATEDIFF(NOW(), t.request_created_datetime) > 7 THEN 'High'
                    WHEN DATEDIFF(NOW(), t.request_created_datetime) > 3 THEN 'Medium'
                    ELSE 'Low'
                END as priority
            $fromQuery $whereSql";

    // Get total count
    $countQuery = "SELECT COUNT(*) as total $fromQuery $whereSql";
    $totalResult = $db->rawQueryOne($countQuery);
    $totalRecords = $totalResult['total'] ?? 0;

    if (!empty($sOrder) && $sOrder !== '') {
        $sOrder = preg_replace('/\s+/', ' ', $sOrder);
        $sQuery = "$sQuery ORDER BY $sOrder";
    }

    if (isset($sLimit) && isset($sOffset)) {
        $sQuery = "$sQuery LIMIT $sOffset,$sLimit";
    }

    $rResult = $db->rawQuery($sQuery);

    $output = [
        "draw" => (int) ($_POST['draw'] ?? 1),
        "recordsTotal" => $totalRecords,
        "recordsFiltered" => $totalRecords,
        "data" => []
    ];

    foreach ($rResult as $aRow) {
        $sourceReadable = $aRow['source_of_request'];
        switch ($sourceReadable) {
            case 'api':
                $sourceReadable = 'API';
                break;
            case 'vlsts':
                $sourceReadable = 'STS';
                break;
            case 'vlsm':
            case 'manual':
                $sourceReadable = 'LIS (Manual)';
                break;
            case 'app':
                $sourceReadable = 'Tablet App';
                break;
            default:
                $sourceReadable = ucfirst($sourceReadable);
        }

        $row = [
            'priority' => $aRow['priority'],
            'sample_code' => $aRow['sample_code'] ?: $aRow['remote_sample_code'] ?: 'N/A',
            'remote_sample_code' => $aRow['remote_sample_code'] ?: 'N/A',
            'patient_info' => trim($aRow['patient_info']) ?: 'Unknown Patient',
            'facility_name' => $aRow['facility_name'] ?: 'Unknown Facility',
            'request_date' => DateUtility::humanReadableDateFormat($aRow['request_created_datetime'], true),
            'days_pending' => $aRow['days_pending'],
            'source_of_request' => $sourceReadable
        ];

        $output['data'][] = $row;
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
    echo JsonUtility::encodeUtf8Json(['error' => 'Failed to load missing samples detail']);
}
