<?php
// get-duplicates-detail.php
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

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

try {
    $testType = $_POST['testType'] ?? 'vl';
    $table = TestsService::getTestTableName($testType);
    $primaryColumn = TestsService::getPrimaryColumn($testType);

    // DataTables parameters
    $aColumns = [
        'risk_sort_value',  // Hidden column for sorting - index 0
        'risk_level',       // Visible risk level - index 1
        'patient_info',     // index 2
        'sample_ids',       // index 3
        'collection_dates', // index 4
        'sources',          // index 5
        'duplicate_count',  // index 6
        'days_span',        // index 7
        'facility_names'    // index 8
    ];

    $sOffset = $sLimit = null;
    if (isset($_POST['start']) && $_POST['length'] != '-1') {
        $sOffset = $_POST['start'];
        $sLimit = $_POST['length'];
    }

    // Handle ordering - NEW FORMAT
    $sOrder = "";
    if (isset($_POST['order']) && count($_POST['order'])) {
        $orderParts = [];
        foreach ($_POST['order'] as $order) {
            $columnIndex = $order['column'];
            $direction = $order['dir'];

            // Map column index to actual database column
            switch ($columnIndex) {
                case 0: // risk_sort_value
                    $orderParts[] = "risk_sort_value $direction";
                    break;
                case 1: // risk_level (sort by hidden column)
                    $orderParts[] = "risk_sort_value $direction";
                    break;
                case 6: // duplicate_count
                    $orderParts[] = "duplicate_count $direction";
                    break;
                case 7: // days_span
                    $orderParts[] = "days_span $direction";
                    break;
                default:
                    // For non-sortable columns, fallback to risk_sort_value
                    $orderParts[] = "risk_sort_value DESC";
            }
        }
        $sOrder = implode(', ', $orderParts);
    }

    // Search functionality
    $sWhere = [];
    if (isset($_POST['search']['value']) && !empty($_POST['search']['value'])) {
        $searchValue = $_POST['search']['value'];
        // Add search conditions for searchable columns
        $searchConditions = [
            "t1.$patientFirstNameColumn LIKE '%$searchValue%'",
            "t1.$patientLastNameColumn LIKE '%$searchValue%'",
            "t1.$patientIdColumn LIKE '%$searchValue%'",
            "t1.sample_code LIKE '%$searchValue%'",
            "t1.remote_sample_code LIKE '%$searchValue%'",
            "f1.facility_name LIKE '%$searchValue%'"
        ];
        $sWhere[] = "(" . implode(" OR ", $searchConditions) . ")";
    }

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
        $sWhere[] = " f1.facility_state_id IN ($provinceId)";  // Fixed: f1 instead of f
    }

    // District filter
    if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
        $districtId = implode(',', $_POST['district']);
        $sWhere[] = " f1.facility_district_id IN ($districtId)";  // Fixed: f1 instead of f
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

    // Main query to find duplicates - ALIGNED WITH METRICS LOGIC
    $whereSql = $sWhere === [] ? ('') : ' WHERE ' . implode(' AND ', $sWhere);

    // Main query to find duplicates - ALIGNED WITH METRICS LOGIC
    $duplicatesQuery = "SELECT
        COALESCE(t1.$patientIdColumn, CONCAT(COALESCE(t1.$patientFirstNameColumn, ''), '_', COALESCE(t1.$patientLastNameColumn, ''))) as patient_key,
        t1.$patientFirstNameColumn,
        t1.$patientLastNameColumn,
        t1.$patientIdColumn,
        t1.facility_id,
        t1.lab_id,
        GROUP_CONCAT(DISTINCT COALESCE(t1.sample_code, t1.remote_sample_code) ORDER BY t1.sample_collection_date SEPARATOR ', ') as sample_ids,
        GROUP_CONCAT(DISTINCT DATE(t1.sample_collection_date) ORDER BY t1.sample_collection_date SEPARATOR ', ') as collection_dates,
        GROUP_CONCAT(DISTINCT COALESCE(t1.source_of_request, 'manual') ORDER BY t1.sample_collection_date SEPARATOR ', ') as sources,
        GROUP_CONCAT(DISTINCT f1.facility_name ORDER BY t1.sample_collection_date SEPARATOR ', ') as facility_names,
        COUNT(*) as duplicate_count,
        MIN(t1.sample_collection_date) as first_collection,
        MAX(t1.sample_collection_date) as last_collection,
        DATEDIFF(MAX(t1.sample_collection_date), MIN(t1.sample_collection_date)) as days_span,
        CASE
            WHEN DATEDIFF(MAX(t1.sample_collection_date), MIN(t1.sample_collection_date)) <= 1 THEN 'High'
            WHEN DATEDIFF(MAX(t1.sample_collection_date), MIN(t1.sample_collection_date)) <= 3 THEN 'Medium'
            ELSE 'Low'
        END as risk_level,
        CASE
            WHEN DATEDIFF(MAX(t1.sample_collection_date), MIN(t1.sample_collection_date)) <= 1 THEN 3
            WHEN DATEDIFF(MAX(t1.sample_collection_date), MIN(t1.sample_collection_date)) <= 3 THEN 2
            ELSE 1
        END as risk_sort_value
    FROM $table as t1
    LEFT JOIN facility_details as f1 ON t1.facility_id = f1.facility_id
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
        )";

    // Add ordering
    if ($sOrder !== '' && $sOrder !== '0') {
        $sOrder = preg_replace('/\s+/', ' ', $sOrder);
        $duplicatesQuery .= " ORDER BY $sOrder";
    } else {
        $duplicatesQuery .= " ORDER BY last_collection DESC";
    }

    // Count total records for pagination
    $countQuery = "SELECT COUNT(*) as total FROM ($duplicatesQuery) as cnt";
    $totalRecords = $db->rawQueryOne($countQuery)['total'];

    // Add pagination
    if (isset($sLimit) && isset($sOffset)) {
        $duplicatesQuery .= " LIMIT $sOffset, $sLimit";
    }

    $results = $db->rawQuery($duplicatesQuery);

    // Format results for DataTables
    $output = [
        "draw" => (int) ($_POST['draw'] ?? 1),
        "recordsTotal" => $totalRecords,
        "recordsFiltered" => $totalRecords,
        "data" => []
    ];

    foreach ($results as $row) {
        // Format patient info
        $patientInfo = '';
        if (!empty($row[$patientIdColumn])) {
            $idLabel = ($testType === 'vl' || $testType === 'cd4') ? 'ART' : 'ID';
            $patientInfo = '<strong>' . $idLabel . ':</strong> ' . htmlspecialchars((string) $row[$patientIdColumn]);
        }
        if (!empty($row[$patientFirstNameColumn]) || !empty($row[$patientLastNameColumn])) {
            $name = trim(($row[$patientFirstNameColumn] ?? '') . ' ' . ($row[$patientLastNameColumn] ?? ''));
            if ($name !== '' && $name !== '0') {
                $patientInfo .= ($patientInfo === '' || $patientInfo === '0' ? '' : '<br>') . '<strong>Name:</strong> ' . htmlspecialchars($name);
            }
        }

        // Format sources
        $sources = explode(', ', (string) $row['sources']);
        $readableSources = array_map(fn($source): string => $source === 'api' ? 'API' : ucfirst((string) $source), array_unique($sources));

        // Risk level styling
        $riskClass = '';
        $riskClass = match ($row['risk_level']) {
            'High' => 'label-danger',
            'Medium' => 'label-warning',
            default => 'label-info',
        };

        $formattedRow = [
            $row['risk_sort_value'], // Hidden sort value - index 0
            '<span class="label ' . $riskClass . '">' . $row['risk_level'] . '</span>', // Visible risk level - index 1
            $patientInfo, // index 2
            '<small>' . htmlspecialchars((string) $row['sample_ids']) . '</small>', // index 3
            '<small>' . htmlspecialchars((string) $row['collection_dates']) . '</small>', // index 4
            implode(', ', $readableSources), // index 5
            '<span class="label label-primary">' . $row['duplicate_count'] . '</span>', // index 6
            '<span class="label ' . ($row['days_span'] <= 1 ? 'label-danger' : ($row['days_span'] <= 3 ? 'label-warning' : 'label-info')) . '">' . $row['days_span'] . ' days</span>', // index 7
            '<small>' . htmlspecialchars((string) $row['facility_names']) . '</small>' // index 8
        ];

        $output['data'][] = $formattedRow;
    }

    // Debug logging
    LoggerUtility::logDebug("Duplicates Detail DataTable Debug", [
        'total_records' => $totalRecords,
        'results_count' => count($results),
        'test_type' => $testType,
        'filters' => $_POST,
        'query' => $duplicatesQuery
    ]);

    echo JsonUtility::encodeUtf8Json($output);
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);

    echo JsonUtility::encodeUtf8Json([
        "draw" => (int) ($_POST['draw'] ?? 1),
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => "Failed to load duplicate records"
    ]);
}
