<?php
// get-smart-dashboard-alerts.php
use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;
use App\Registries\AppRegistry;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;
use Psr\Http\Message\ServerRequestInterface;

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
    $duplicatesWhere = [];

    // Date range filter
    if (isset($_POST['dateRange']) && trim((string) $_POST['dateRange']) !== '') {
        [$start_date, $end_date] = DateUtility::convertDateRange($_POST['dateRange'] ?? '', includeTime: true);
        $sWhere[] = " t.request_created_datetime BETWEEN '$start_date' AND '$end_date' ";
        $duplicatesWhere[] = " t1.request_created_datetime BETWEEN '$start_date' AND '$end_date' ";
    }

    // Lab filter
    if (isset($_POST['labName']) && trim((string) $_POST['labName']) !== '') {
        $sWhere[] = " t.lab_id IN (" . $_POST['labName'] . ")";
        $duplicatesWhere[] = " t1.lab_id IN (" . $_POST['labName'] . ")";
    }

    // Facility filter
    if (isset($_POST['facilityId']) && trim((string) $_POST['facilityId']) !== '') {
        $facilityId = implode(',', $_POST['facilityId']);
        $sWhere[] = " t.facility_id IN ($facilityId)";
        $duplicatesWhere[] = " t1.facility_id IN ($facilityId)";
    }

    // Geographic filters
    $facilityJoin = '';
    $duplicatesFacilityJoin = '';
    if (isset($_POST['state']) || isset($_POST['district'])) {
        $facilityJoin = ' LEFT JOIN facility_details as f ON t.facility_id = f.facility_id ';
        $duplicatesFacilityJoin = ' LEFT JOIN facility_details as f1 ON t1.facility_id = f1.facility_id ';

        if (isset($_POST['state']) && trim((string) $_POST['state']) !== '') {
            $provinceId = implode(',', $_POST['state']);
            $sWhere[] = " f.facility_state_id IN ($provinceId)";
            $duplicatesWhere[] = " f1.facility_state_id IN ($provinceId)";
        }

        if (isset($_POST['district']) && trim((string) $_POST['district']) !== '') {
            $districtId = implode(',', $_POST['district']);
            $sWhere[] = " f.facility_district_id IN ($districtId)";
            $duplicatesWhere[] = " f1.facility_district_id IN ($districtId)";
        }
    }

    $whereSql = $sWhere === [] ? ('') : ' WHERE ' . implode(' AND ', $sWhere);

    $alerts = [];

    // Alert 1: High number of API/EMR requests without sample receipt
    $missingSamplesQuery = "
        SELECT COUNT(*) as count
        FROM $table as t
        $facilityJoin
        $whereSql
        AND t.source_of_request = 'api'
        AND t.request_created_datetime IS NOT NULL
        AND t.sample_received_at_lab_datetime IS NULL
        AND (t.is_sample_rejected IS NULL OR t.is_sample_rejected != 'yes')
        AND DATEDIFF(NOW(), t.request_created_datetime) > 30";

    $missingSamplesCount = $db->rawQueryOne($missingSamplesQuery);
    if ($missingSamplesCount['count'] > 10) {
        $alerts[] = [
            'type' => 'danger',
            'title' => _translate('High Missing API/EMR Sample Receipts'),
            'message' => sprintf(_translate('%d API/EMR test requests have been waiting for sample receipt for more than 30 days'), $missingSamplesCount['count']),
            'action' => 'viewMissingSamples()'
        ];
    }

    // Get patient field names for this test type
    $patientIdColumn = TestsService::getPatientIdColumn($testType);
    $patientFirstNameColumn = TestsService::getPatientFirstNameColumn($testType);
    $patientLastNameColumn = TestsService::getPatientLastNameColumn($testType);

    // Base conditions for duplicates
    $duplicatesWhere[] = " (t1.$patientFirstNameColumn IS NOT NULL OR t1.$patientIdColumn IS NOT NULL) ";
    $duplicatesWhere[] = " t1.sample_collection_date IS NOT NULL ";
    $duplicatesWhereSql = $duplicatesWhere === [] ? ('') : ' WHERE ' . implode(' AND ', $duplicatesWhere);

    // Alert 2: High number of potential duplicates - ALIGNED WITH METRICS LOGIC
    $duplicatesQuery = "
    SELECT COUNT(*) as duplicate_groups
    FROM (
        SELECT
        t1.$patientIdColumn,
        t1.$patientFirstNameColumn,
        t1.$patientLastNameColumn,
            COALESCE(t1.$patientIdColumn, CONCAT(COALESCE(t1.$patientFirstNameColumn, ''), '_', COALESCE(t1.$patientLastNameColumn, ''))) as patient_key,
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

    $duplicatesCount = $db->rawQueryOne($duplicatesQuery);
    if ($duplicatesCount['duplicate_groups'] > 5) {
        $alerts[] = [
            'type' => 'warning',
            'title' => _translate('Potential Duplicate Entries Detected'),
            'message' => sprintf(_translate('%d groups of potential duplicate entries found'), $duplicatesCount['duplicate_groups']),
            'action' => 'viewDuplicates()'
        ];
    }

    // Alert 3: Long pending tests
    $longPendingQuery = "
        SELECT COUNT(*) as count
        FROM $table as t
        $facilityJoin
        $whereSql
        AND t.sample_received_at_lab_datetime IS NOT NULL
        AND t.sample_tested_datetime IS NULL
        AND t.source_of_request = 'api'
        AND DATEDIFF(NOW(), t.sample_received_at_lab_datetime) > 30";

    $longPendingCount = $db->rawQueryOne($longPendingQuery);
    if ($longPendingCount['count'] > 0) {
        $alertType = 'info';
        $alertTitle = _translate('Pending Tests');

        if ($longPendingCount['count'] > 1000) {
            $alertType = 'danger';
            $alertTitle = _translate('Critical: Many Long Pending Tests');
        } elseif ($longPendingCount['count'] > 100) {
            $alertType = 'warning';
            $alertTitle = _translate('Long Pending Tests');
        }

        $alerts[] = [
            'type' => $alertType,
            'title' => $alertTitle,
            'message' => sprintf(_translate('%d samples have been pending testing for more than 30 days'), $longPendingCount['count']),
            'action' => 'viewPendingTests()'
        ];
    }

    // Alert 4: Results sent but with issues
    $resultsIssuesQuery = "
        SELECT COUNT(*) as count
        FROM $table as t
        $facilityJoin
        $whereSql
        AND t.source_of_request = 'api'
        AND t.result_pulled_via_api_datetime IS NOT NULL
        AND (t.result_sent_to_source = 'pending' OR t.result_sent_to_source = 'failed')";

    $resultsIssuesCount = $db->rawQueryOne($resultsIssuesQuery);
    if ($resultsIssuesCount['count'] > 0) {
        $alerts[] = [
            'type' => 'warning',
            'title' => _translate('API/EMR Result Transmission Issues'),
            'message' => sprintf(_translate('%d API/EMR results have transmission issues (pending/failed)'), $resultsIssuesCount['count']),
            'action' => 'viewResultsIssues()'
        ];
    }

    // Alert 5: High sample rejection rate (bonus alert)
    $rejectionQuery = "
        SELECT
            COUNT(*) as total_samples,
            SUM(CASE WHEN t.is_sample_rejected = 'yes' THEN 1 ELSE 0 END) as rejected_samples
        FROM $table as t
        $facilityJoin
        $whereSql
        AND t.sample_received_at_lab_datetime IS NOT NULL";

    $rejectionData = $db->rawQueryOne($rejectionQuery);
    if ($rejectionData['total_samples'] > 0) {
        $rejectionRate = ($rejectionData['rejected_samples'] / $rejectionData['total_samples']) * 100;
        if ($rejectionRate > 15) { // Alert if rejection rate > 15%
            $alerts[] = [
                'type' => 'warning',
                'title' => _translate('High Sample Rejection Rate'),
                'message' => sprintf(
                    _translate('Sample rejection rate is %.1f%% (%d out of %d samples)'),
                    $rejectionRate,
                    $rejectionData['rejected_samples'],
                    $rejectionData['total_samples']
                ),
                'action' => 'viewRejectedSamples()'
            ];
        }
    }

    // Generate HTML output for alerts
    $alertsHtml = '';

    if ($alerts === []) {
        $alertsHtml = '<div class="alert-card success">
            <strong><em class="fa-solid fa-check-circle"></em> ' . _translate('All Good!') . '</strong><br>
            ' . _translate('No critical issues detected in the current time period.') . '
        </div>';
    } else {
        foreach ($alerts as $alert) {
            $alertsHtml .= '<div class="alert-card ' . $alert['type'] . '">
                <strong><em class="fa-solid fa-exclamation-triangle"></em> ' . $alert['title'] . '</strong><br>
                ' . $alert['message'] . '
                <div style="margin-top: 10px;">
                    <button class="btn btn-sm btn-' . ($alert['type'] === 'danger' ? 'danger' : 'primary') . '" onclick="' . $alert['action'] . '">
                        ' . _translate('View Details') . '
                    </button>
                </div>
            </div>';
        }
    }

    echo $alertsHtml;
} catch (Throwable $e) {
    LoggerUtility::logError($e->getMessage(), [
        'trace' => $e->getTraceAsString(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'last_db_error' => $db->getLastError(),
        'last_db_query' => $db->getLastQuery()
    ]);
    MiscUtility::safeCliEcho('<div class="alert-card danger">
        <strong>' . _translate('Error') . '</strong><br>
        ' . _translate('Failed to load alerts') . $e->getMessage() . '
    </div>');
}
