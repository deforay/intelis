<?php

use App\Services\TestsService;
use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\SystemService;
use App\Utilities\LoggerUtility;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

// Sanitized values from $request object
/** @var Psr\Http\Message\ServerRequestInterface $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var SystemService $systemService */
$systemService = ContainerRegistry::get(SystemService::class);

$mysqlDateFormat = $systemService->getDateFormat('mysql');

$testType = (string) ($_POST['type'] ?? '');
$table = TestsService::getTestTableName($testType);
$primaryKey = TestsService::getPrimaryColumn($testType);

$waitingTotal = 0;
$rejectedTotal = 0;
$receivedTotal = 0;
$acceptedTotal = 0;

$waitingDate = '';
$rejectedDate = '';

$waitingCategories = [];
$waitingCounts = [];
$topRejectionReasons = [];
$topRejectionReasonsTotal = 0;
$backlogRegisteredAtTestingLab = 0;
$backlogAwaitingApproval = 0;
$backlogOnHold = 0;
$backlogTotal = 0;

if ($testType === 'eid') {
    $samplesReceivedChart = "eidSamplesReceivedChart";
    $samplesTestedChart = "eidSamplesTestedChart";
    $samplesRejectedChart = "eidSamplesRejectedChart";
    $samplesWaitingChart = "eidSamplesWaitingChart";
    $samplesOverviewChart = "eidSamplesOverviewChart";
} elseif ($testType === 'covid19') {
    $samplesReceivedChart = "covid19SamplesReceivedChart";
    $samplesTestedChart = "covid19SamplesTestedChart";
    $samplesNotTestedChart = "covid19SamplesNotTestedChart";
    $samplesRejectedChart = "covid19SamplesRejectedChart";
    $samplesWaitingChart = "covid19SamplesWaitingChart";
    $samplesOverviewChart = "covid19SamplesOverviewChart";
} elseif ($testType === 'hepatitis') {
    $samplesReceivedChart = "hepatitisSamplesReceivedChart";
    $samplesTestedChart = "hepatitisSamplesTestedChart";
    $samplesRejectedChart = "hepatitisSamplesRejectedChart";
    $samplesWaitingChart = "hepatitisSamplesWaitingChart";
    $samplesOverviewChart = "hepatitisSamplesOverviewChart";
} elseif ($testType === 'vl') {
    // Partition VL from Recency
    $recencyWhere = " IFNULL(reason_for_vl_testing, 0) != 9999 ";
    $samplesReceivedChart = "vlSamplesReceivedChart";
    $samplesTestedChart = "vlSamplesTestedChart";
    $samplesRejectedChart = "vlSamplesRejectedChart";
    $samplesWaitingChart = "vlSamplesWaitingChart";
    $samplesOverviewChart = "vlSamplesOverviewChart";
} elseif ($testType === 'cd4') {
    $samplesReceivedChart = "cd4SamplesReceivedChart";
    $samplesTestedChart = "cd4SamplesTestedChart";
    $samplesRejectedChart = "cd4SamplesRejectedChart";
    $samplesWaitingChart = "cd4SamplesWaitingChart";
    $samplesOverviewChart = "cd4SamplesOverviewChart";
} elseif ($testType === 'tb') {
    // TB needs its own chart container IDs so the dashboard does not reuse EID widgets.
    $samplesReceivedChart = "tbSamplesReceivedChart";
    $samplesTestedChart = "tbSamplesTestedChart";
    $samplesRejectedChart = "tbSamplesRejectedChart";
    $samplesWaitingChart = "tbSamplesWaitingChart";
    $samplesOverviewChart = "tbSamplesOverviewChart";
} elseif ($testType === 'recency') {
    // The “Recency” view is driven from the VL table but only for reason 9999
    $recencyWhere = " reason_for_vl_testing = 9999 ";
    $samplesReceivedChart = "recencySamplesReceivedChart";
    $samplesTestedChart = "recencySamplesTestedChart";
    $samplesRejectedChart = "recencySamplesRejectedChart";
    $samplesWaitingChart = "recencySamplesWaitingChart";
    $samplesOverviewChart = "recencySamplesOverviewChart";
} elseif ($testType === 'generic-tests') {
    $samplesReceivedChart = "genericTestsSamplesReceivedChart";
    $samplesTestedChart = "genericTestsSamplesTestedChart";
    $samplesRejectedChart = "genericTestsSamplesRejectedChart";
    $samplesWaitingChart = "genericTestsSamplesWaitingChart";
    $samplesOverviewChart = "genericTestsSamplesOverviewChart";
}

try {
    // ---------- Base WHERE (alias-safe with `t`) ----------
    $whereParts = [];
    $whereParts[] = "t.result_status != " . SAMPLE_STATUS\CANCELLED;

    if (!$general->isSTSInstance()) {
        $whereParts[] = "t.result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC;
    } elseif (!empty($_SESSION['facilityMap'])) {
        $whereParts[] = "t.facility_id IN (" . $_SESSION['facilityMap'] . ")";
    }
    $baseWhere = implode(' AND ', $whereParts);

    // ---------- Date range selection ----------
    if (!empty($_POST['sampleCollectionDate'])) {
        $selectedRange = (string) $_POST['sampleCollectionDate'];
        [$startDate, $endDate] = DateUtility::convertDateRange($_POST['sampleCollectionDate'], includeTime: true);
    } else {
        $startDate = date('Y-m-d H:i:s', strtotime('-7 days'));
        $endDate = date('Y-m-d H:i:s');
        // human fallback for label
        $selectedRange = date('d-M-Y', strtotime($startDate)) . ' to ' . date('d-M-Y', strtotime($endDate));
    }

    $currentDateTime = DateUtility::getCurrentDateTime();

    // ---------- Table-specific fields/predicates ----------
    // Result field per table (null means “special case”)
    $resultField = match ($table) {
        'form_cd4' => 'cd4_result',
        'form_hepatitis' => null,
        default => 'result',
    };

    // “No result yet” per table
    $noResultExpr = match ($table) {
        'form_hepatitis' => "(NULLIF(TRIM(t.hcv_vl_count), '') IS NULL AND NULLIF(TRIM(t.hbv_vl_count), '') IS NULL)",
        'form_cd4' => "NULLIF(TRIM(t.cd4_result), '') IS NULL",
        default => "NULLIF(TRIM(t.result), '') IS NULL",
    };

    // Partition for VL/Recency if applicable
    $partitionWhere = ($table === 'form_vl' && !empty($recencyWhere)) ? "($recencyWhere)" : '';

    // Normalized rejection flag
    $notRejectedExpr = "LOWER(COALESCE(NULLIF(TRIM(t.is_sample_rejected), ''), 'no')) = 'no'";

    // Rejection-reason reference table per sample table
    $rejectionReasonTable = match ($table) {
        'form_vl' => 'r_vl_sample_rejection_reasons',
        'form_eid' => 'r_eid_sample_rejection_reasons',
        'form_covid19' => 'r_covid19_sample_rejection_reasons',
        'form_hepatitis' => 'r_hepatitis_sample_rejection_reasons',
        'form_cd4' => 'r_cd4_sample_rejection_reasons',
        'form_tb' => 'r_tb_sample_rejection_reasons',
        'form_generic_tests' => 'r_generic_sample_rejection_reasons',
        default => null,
    };

    // Common builder for WHERE
    $W = fn(array $extra = []): string => implode(' AND ', array_values(array_filter([
        $baseWhere ?: null,
        $partitionWhere ?: null,
        ...$extra
    ])));

    // ======================================================
    // A) Waiting (last 6 months, no result, not rejected)
    // ======================================================
    $waitingWhere = $W([
        "t.sample_collection_date IS NOT NULL",
        "t.sample_collection_date >= DATE_SUB('$currentDateTime', INTERVAL 6 MONTH)",
        $noResultExpr,
        $notRejectedExpr,
    ]);

    // Breakdown by status
    $waitingByStatusQuery = "SELECT
        COALESCE(s.status_name, CONCAT('Status ', t.result_status)) AS status_name,
        t.result_status,
        COUNT(t.$primaryKey) AS cnt
    FROM $table AS t
    LEFT JOIN r_sample_status AS s ON s.status_id = t.result_status
    WHERE $waitingWhere
    GROUP BY t.result_status, s.status_name
    HAVING cnt > 0
    ORDER BY cnt DESC";

    $waitingByStatusRows = $db->rawQuery($waitingByStatusQuery);

    $waitingCategories = [];
    $waitingCounts = [];
    $waitingTotal = 0;

    foreach ($waitingByStatusRows as $r) {
        $label = (string) ($r['status_name'] ?? 'Unknown');
        $count = (int) $r['cnt'];
        $waitingCategories[] = $label;
        $waitingCounts[] = $count;
        $waitingTotal += $count;
    }
    // ======================================================
    // B) Aggregate (within selected range)
    // ======================================================
    $rangeExpr = "t.sample_collection_date BETWEEN '$startDate' AND '$endDate'";

    // “Tested” matches your earlier semantics
    $testedExpr = "t.lab_id IS NOT NULL
        AND t.sample_tested_datetime IS NOT NULL
        " . ($resultField ? "AND IFNULL(t.$resultField, '') != ''" : "AND 1=1") . "
        AND t.result_status = " . SAMPLE_STATUS\ACCEPTED;

    $aggregateWhere = $W([$rangeExpr]);

    $aggregateQuery = "SELECT
            COUNT(t.$primaryKey) AS totalCollected,
            SUM(CASE WHEN ($testedExpr) THEN 1 ELSE 0 END) AS tested,
            SUM(CASE WHEN (t.result_status = " . SAMPLE_STATUS\ON_HOLD . ") THEN 1 ELSE 0 END) AS hold,
            SUM(CASE WHEN (t.result_status = " . SAMPLE_STATUS\REJECTED . ") THEN 1 ELSE 0 END) AS rejected,
            SUM(CASE WHEN (t.result_status = " . SAMPLE_STATUS\TEST_FAILED . ") THEN 1 ELSE 0 END) AS invalid,
            SUM(CASE WHEN (t.result_status = " . SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB . ") THEN 1 ELSE 0 END) AS registeredAtTestingLab,
            SUM(CASE WHEN (t.result_status = " . SAMPLE_STATUS\PENDING_APPROVAL . ") THEN 1 ELSE 0 END) AS awaitingApproval,
            SUM(CASE WHEN (t.result_status = " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . ") THEN 1 ELSE 0 END) AS registeredAtCollectionPoint,
            SUM(CASE WHEN (t.result_status = " . SAMPLE_STATUS\EXPIRED . ") THEN 1 ELSE 0 END) AS expired
        FROM $table AS t
        WHERE $aggregateWhere
    ";
    $aggregateResult = $db->rawQueryOne($aggregateQuery);

    // ======================================================
    // C) Accession (Samples collected per day)
    // ======================================================
    $accessionWhere = $W(["t.sample_collection_date BETWEEN '$startDate' AND '$endDate'"]);
    $accessionQuery = "SELECT
            DATE_FORMAT(DATE(t.sample_collection_date), '$mysqlDateFormat') AS collection_date,
            COUNT(t.$primaryKey) AS count
        FROM $table AS t
        WHERE $accessionWhere
        GROUP BY DATE(t.sample_collection_date)
        ORDER BY DATE(t.sample_collection_date)
    ";
    $tRes = $db->rawQuery($accessionQuery);
    $tResult = [];
    foreach ($tRes as $r) {
        $receivedTotal += (int) $r['count'];
        $tResult[] = ['total' => (int) $r['count'], 'date' => $r['collection_date']];
    }

    // ======================================================
    // D) Samples Tested (per day)
    // ======================================================
    $testedSeriesWhere = $W([
        "t.result_status = " . SAMPLE_STATUS\ACCEPTED,
        "t.sample_tested_datetime BETWEEN '$startDate' AND '$endDate'",
    ]);
    $sampleTestedQuery = "
        SELECT
            DATE_FORMAT(DATE(t.sample_tested_datetime), '$mysqlDateFormat') AS test_date,
            COUNT(t.$primaryKey) AS count
        FROM $table AS t
        WHERE $testedSeriesWhere
        GROUP BY DATE(t.sample_tested_datetime)
        ORDER BY DATE(t.sample_tested_datetime)
    ";
    $tRes = $db->rawQuery($sampleTestedQuery);
    $acceptedResult = [];
    foreach ($tRes as $r) {
        $acceptedTotal += (int) $r['count'];
        $acceptedResult[] = ['total' => (int) $r['count'], 'date' => $r['test_date']];
    }

    // ======================================================
    // E) Rejected (per day)
    // ======================================================
    $rejectedWhere = $W([
        "t.result_status = " . SAMPLE_STATUS\REJECTED,
        "t.sample_collection_date BETWEEN '$startDate' AND '$endDate'",
    ]);
    $sampleRejectedQuery = "
        SELECT
            DATE_FORMAT(DATE(t.sample_collection_date), '$mysqlDateFormat') AS collection_date,
            COUNT(t.$primaryKey) AS count
        FROM $table AS t
        WHERE $rejectedWhere
        GROUP BY DATE(t.sample_collection_date)
        ORDER BY DATE(t.sample_collection_date)
    ";
    $tRes = $db->rawQuery($sampleRejectedQuery);
    $rejectedResult = [];
    foreach ($tRes as $r) {
        $rejectedTotal += (int) $r['count'];
        $rejectedResult[] = ['total' => (int) $r['count'], 'date' => $r['collection_date']];
    }

    // ======================================================
    // F) (Optional) Status counts — keep behavior only for COVID-19
    // ======================================================
    if ($table === "form_covid19") {
        $statusWhere = $W(["t.sample_collection_date BETWEEN '$startDate' AND '$endDate'"]);
        $statusQuery = "
            SELECT
                s.status_name,
                DATE_FORMAT(DATE(t.sample_collection_date), '$mysqlDateFormat') AS collection_date,
                COUNT(t.$primaryKey) AS count
            FROM r_sample_status AS s
            JOIN $table AS t ON t.result_status = s.status_id
            WHERE $statusWhere
            GROUP BY s.status_name, DATE(t.sample_collection_date)
            ORDER BY DATE(t.sample_collection_date)
        ";
        $statusQueryResult = $db->rawQuery($statusQuery);

        $statusTotal = 0;
        $statusResult = ['date' => [], 'status' => []];

        $statusResult = ['date' => [], 'status' => []];

        foreach ($statusQueryResult as $row) {
            $statusTotal += (int) $row['count'];
            // keep same shape you were using earlier
            $statusResult['date'][$row['collection_date']] = "'" . $row['collection_date'] . "'";
            $statusResult['status'][$row['status_name']][$row['collection_date']] = (int) $row['count'];
        }
    }

    // ======================================================
    // G) Top Rejection Reasons (last 6 months)
    // ======================================================
    $topRejectionReasons = [];
    $topRejectionReasonsTotal = 0;
    if (!empty($rejectionReasonTable)) {
        $rejectionReasonsWhere = $W([
            "t.result_status = " . SAMPLE_STATUS\REJECTED,
            "t.sample_collection_date >= DATE_SUB('$currentDateTime', INTERVAL 6 MONTH)",
            "t.reason_for_sample_rejection IS NOT NULL",
            "TRIM(t.reason_for_sample_rejection) != ''",
        ]);
        $topRejectionReasonsQuery = "SELECT
                rr.rejection_reason_name,
                COUNT(t.$primaryKey) AS cnt
            FROM $table AS t
            INNER JOIN $rejectionReasonTable AS rr
                ON rr.rejection_reason_id = t.reason_for_sample_rejection
            WHERE $rejectionReasonsWhere
            GROUP BY rr.rejection_reason_id, rr.rejection_reason_name
            ORDER BY cnt DESC
            LIMIT 5";
        $rejectionReasonRows = $db->rawQuery($topRejectionReasonsQuery);
        foreach ($rejectionReasonRows as $r) {
            $count = (int) $r['cnt'];
            $topRejectionReasons[] = [
                'name' => (string) ($r['rejection_reason_name'] ?? 'Unknown'),
                'count' => $count,
            ];
            $topRejectionReasonsTotal += $count;
        }
    }

    // ======================================================
    // H) Current Backlog (lab-side queue, last 6 months, no date filter)
    // ======================================================
    $backlogWhere = $W([
        "t.sample_collection_date >= DATE_SUB('$currentDateTime', INTERVAL 6 MONTH)",
    ]);
    $backlogQuery = "SELECT
            SUM(CASE WHEN t.result_status = " . SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB . " THEN 1 ELSE 0 END) AS registeredAtTestingLab,
            SUM(CASE WHEN t.result_status = " . SAMPLE_STATUS\PENDING_APPROVAL . " THEN 1 ELSE 0 END) AS awaitingApproval,
            SUM(CASE WHEN t.result_status = " . SAMPLE_STATUS\ON_HOLD . " THEN 1 ELSE 0 END) AS onHold
        FROM $table AS t
        WHERE $backlogWhere";
    $backlogResult = $db->rawQueryOne($backlogQuery) ?: [];
    $backlogRegisteredAtTestingLab = (int) ($backlogResult['registeredAtTestingLab'] ?? 0);
    $backlogAwaitingApproval = (int) ($backlogResult['awaitingApproval'] ?? 0);
    $backlogOnHold = (int) ($backlogResult['onHold'] ?? 0);
    $backlogTotal = $backlogRegisteredAtTestingLab + $backlogAwaitingApproval + $backlogOnHold;
} catch (Throwable $e) {
    LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
    LoggerUtility::logError($e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}
?>



<style>
    .select2-container .select2-selection--single {
        height: 34px !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__arrow {
        top: 6px !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 22px !important;
    }

    .select2-container .select2-selection--single .select2-selection__rendered {
        margin-top: 0px !important;
    }

    .select2-selection__choice__remove {
        color: red !important;
    }

    .select2-container--default .select2-selection--multiple .select2-selection__choice {
        /* background-color: #00c0ef;
            border-color: #00acd6; */
        color: #000 !important;
        font-family: helvetica, arial, sans-serif;
    }
</style>
<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 ">
    <div class="dashboard-stat2 bluebox" style="cursor:pointer;">
        <div class="display">
            <div class="number">
                <h3 class="font-green-sharp">
                    <span data-counter="counterup"
                        data-value="<?= $receivedTotal; ?>"><?php echo $receivedTotal; ?></span>
                </h3>
                <small class="font-green-sharp">
                    <?= _translate("SAMPLES COLLECTED"); ?>
                </small><br>
                <small class="font-green-sharp" style="font-size:0.75em;">
                    <?php echo _translate("In Selected Range") . " : " . $selectedRange; ?>
                </small>
            </div>
            <div class="icon font-green-sharp">
                <em class="fa-solid fa-chart-simple"></em>
            </div>
        </div>
        <div id="<?= $samplesReceivedChart; ?>" width="210" height="200" style="min-height:200px;"></div>
    </div>
</div>
<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 ">
    <div class="dashboard-stat2" style="cursor:pointer;">
        <div class="display font-blue-sharp">
            <div class="number">
                <h3 class="font-blue-sharp">
                    <span data-counter="counterup"
                        data-value="<?php echo $acceptedTotal; ?>"><?php echo $acceptedTotal; ?></span>
                </h3>
                <small class="font-blue-sharp">
                    <?php echo _translate("SAMPLES TESTED"); ?>
                </small><br>
                <small class="font-blue-sharp" style="font-size:0.75em;">
                    <?php echo _translate("In Selected Range") . " : " . $selectedRange; ?>
                </small>
            </div>
            <div class="icon">
                <em class="fa-solid fa-chart-simple"></em>
            </div>
        </div>
        <div id="<?php echo $samplesTestedChart; ?>" width="210" height="200" style="min-height:200px;"></div>
    </div>
</div>

<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
    <div class="dashboard-stat2 " style="cursor:pointer;">
        <div class="display font-red-haze">
            <div class="number">
                <h3 class="font-red-haze">
                    <span data-counter="counterup"
                        data-value="<?php echo $rejectedTotal; ?>"><?php echo $rejectedTotal; ?></span>
                </h3>
                <small class="font-red-haze">
                    <?php echo _translate("SAMPLES REJECTED"); ?>
                </small><br>
                <small class="font-red-haze" style="font-size:0.75em;">
                    <?php echo _translate("In Selected Range") . " - " . $selectedRange; ?>
                </small>
            </div>
            <div class="icon">
                <em class="fa-solid fa-chart-simple"></em>
            </div>
        </div>
        <div id="<?php echo $samplesRejectedChart; ?>" width="210" height="300" style="min-height:300px;"></div>
    </div>
</div>
<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 ">
    <div class="dashboard-stat2 bluebox" style="cursor:pointer;">
        <div class="display font-purple-soft">
            <div class="number">
                <h4 class="font-purple-soft" style="font-weight:600;">
                    <?= _translate("CURRENT SAMPLES STATUS - OVERALL"); ?>
                </h4>
                <small class="font-purple-soft" style="font-size:0.75em;">
                    <?php echo _translate("In Selected Range") . " : " . $selectedRange; ?>
                </small>
            </div>
            <div class="icon">
                <em class="fa-solid fa-chart-simple"></em>
            </div>
        </div>
        <div id="<?php echo $samplesOverviewChart; ?>" width="210" height="200" style="min-height:200px;"></div>
    </div>
</div>

<?php
    $backlogItems = [
        ['label' => _translate("Registered at Testing Lab"), 'count' => $backlogRegisteredAtTestingLab, 'color' => '#039BE6'],
        ['label' => _translate("Awaiting Approval"),         'count' => $backlogAwaitingApproval,         'color' => '#26a69a'],
        ['label' => _translate("On Hold"),                   'count' => $backlogOnHold,                   'color' => '#f39c12'],
    ];
    $backlogMax = max(1, $backlogRegisteredAtTestingLab, $backlogAwaitingApproval, $backlogOnHold);

    $rejectionMax = 0;
    foreach ($topRejectionReasons as $r) {
        if ($r['count'] > $rejectionMax) {
            $rejectionMax = $r['count'];
        }
    }
    $rejectionMax = max(1, $rejectionMax);
?>

<style>
    .lab-health-header {
        margin: 18px 0 10px;
        padding: 0 5px;
        border-top: 1px solid #eceaf2;
        padding-top: 16px;
    }
    .lab-health-header h4 {
        font-weight: 600;
        color: #5a4b7a;
        margin: 0 0 2px;
        letter-spacing: 0.3px;
    }
    .lab-health-header small {
        color: #999;
        font-size: 0.8em;
    }
    .lab-health-card {
        background: #fff;
        border: 1px solid #eceaf2;
        border-radius: 6px;
        padding: 18px 18px 16px;
        margin-bottom: 20px;
        min-height: 260px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        display: flex;
        flex-direction: column;
    }
    .lab-health-card .lh-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 14px;
    }
    .lab-health-card .lh-title {
        font-size: 0.78em;
        font-weight: 600;
        letter-spacing: 0.6px;
        text-transform: uppercase;
        color: #888;
    }
    .lab-health-card .lh-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
    }
    .lab-health-card .lh-metric {
        font-size: 2.4em;
        font-weight: 300;
        line-height: 1;
        margin: 0 0 4px;
    }
    .lab-health-card .lh-sublabel {
        color: #999;
        font-size: 0.8em;
        margin-bottom: 14px;
    }
    .lab-health-card .lh-body {
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        justify-content: flex-start;
    }
    .lh-bar-row {
        margin-bottom: 10px;
    }
    .lh-bar-row:last-child { margin-bottom: 0; }
    .lh-bar-label {
        display: flex;
        justify-content: space-between;
        font-size: 0.82em;
        color: #555;
        margin-bottom: 4px;
        line-height: 1.25;
    }
    .lh-bar-label strong {
        color: #333;
        font-weight: 600;
        margin-left: 8px;
    }
    .lh-bar-track {
        background: #f2f0f6;
        border-radius: 3px;
        height: 6px;
        overflow: hidden;
    }
    .lh-bar-fill {
        height: 100%;
        border-radius: 3px;
        transition: width 0.3s ease;
    }
    .lh-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        color: #aaa;
        font-size: 0.88em;
        flex: 1;
        padding: 20px 10px;
    }
    .lh-empty em {
        font-size: 28px;
        color: #60d18f;
        margin-bottom: 10px;
    }
</style>

<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 lab-health-header">
    <h4><?= _translate("Lab Health"); ?></h4>
    <small><?= _translate("Last 6 months — independent of the date filter above"); ?></small>
</div>

<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
    <div class="lab-health-card">
        <div class="lh-head">
            <span class="lh-title"><?= _translate("Samples with No Results"); ?></span>
            <span class="lh-icon" style="background:#f1ecf7; color:#8877a9;">
                <em class="fa-solid fa-hourglass-half"></em>
            </span>
        </div>
        <div class="lh-metric" style="color:#8877a9;">
            <span data-counter="counterup" data-value="<?= $waitingTotal; ?>"><?= $waitingTotal; ?></span>
        </div>
        <div class="lh-sublabel"><?= _translate("awaiting results"); ?></div>
        <div class="lh-body">
            <?php if ($waitingTotal > 0) {
                $waitingPalette = ['#8877a9', '#b39ddb', '#c5b4dc', '#6a5a8a', '#d6c9e6', '#9b87c1', '#e0d4ef'];
                $waitingMax = 1;
                foreach ($waitingCounts as $c) {
                    if ((int) $c > $waitingMax) {
                        $waitingMax = (int) $c;
                    }
                }
                foreach ($waitingCategories as $i => $label) {
                    $count = (int) ($waitingCounts[$i] ?? 0);
                    $pct = (int) round(($count / $waitingMax) * 100);
                    $color = $waitingPalette[$i % count($waitingPalette)];
            ?>
                <div class="lh-bar-row">
                    <div class="lh-bar-label">
                        <span title="<?= htmlspecialchars((string) $label); ?>" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:75%;">
                            <?= htmlspecialchars((string) $label); ?>
                        </span>
                        <strong><?= $count; ?></strong>
                    </div>
                    <div class="lh-bar-track">
                        <div class="lh-bar-fill" style="width:<?= $pct; ?>%; background:<?= $color; ?>;"></div>
                    </div>
                </div>
            <?php }
            } else { ?>
                <div class="lh-empty">
                    <em class="fa-solid fa-circle-check"></em>
                    <?= _translate("No pending samples."); ?>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
    <div class="lab-health-card">
        <div class="lh-head">
            <span class="lh-title"><?= _translate("Top Rejection Reasons"); ?></span>
            <span class="lh-icon" style="background:<?= $topRejectionReasonsTotal > 0 ? '#fdecea' : '#e9f7ef'; ?>; color:<?= $topRejectionReasonsTotal > 0 ? '#c0392b' : '#2ecc71'; ?>;">
                <em class="fa-solid <?= $topRejectionReasonsTotal > 0 ? 'fa-triangle-exclamation' : 'fa-circle-check'; ?>"></em>
            </span>
        </div>
        <?php if ($topRejectionReasonsTotal > 0) { ?>
            <div class="lh-metric" style="color:#c0392b;">
                <span data-counter="counterup" data-value="<?= $topRejectionReasonsTotal; ?>"><?= $topRejectionReasonsTotal; ?></span>
            </div>
            <div class="lh-sublabel"><?= _translate("rejections across top reasons"); ?></div>
            <div class="lh-body">
                <?php foreach ($topRejectionReasons as $reason) {
                    $pct = (int) round(($reason['count'] / $rejectionMax) * 100);
                ?>
                    <div class="lh-bar-row">
                        <div class="lh-bar-label">
                            <span title="<?= htmlspecialchars($reason['name']); ?>" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:75%;">
                                <?= htmlspecialchars($reason['name']); ?>
                            </span>
                            <strong><?= $reason['count']; ?></strong>
                        </div>
                        <div class="lh-bar-track">
                            <div class="lh-bar-fill" style="width:<?= $pct; ?>%; background:#e74c3c;"></div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="lh-body">
                <div class="lh-empty">
                    <em class="fa-solid fa-circle-check"></em>
                    <?= _translate("No rejections recorded in the last 6 months."); ?>
                </div>
            </div>
        <?php } ?>
    </div>
</div>

<div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
    <div class="lab-health-card">
        <div class="lh-head">
            <span class="lh-title"><?= _translate("Current Lab Backlog"); ?></span>
            <span class="lh-icon" style="background:#e3f2fd; color:#039BE6;">
                <em class="fa-solid fa-layer-group"></em>
            </span>
        </div>
        <div class="lh-metric" style="color:#039BE6;">
            <span data-counter="counterup" data-value="<?= $backlogTotal; ?>"><?= $backlogTotal; ?></span>
        </div>
        <div class="lh-sublabel"><?= _translate("samples in queue"); ?></div>
        <div class="lh-body">
            <?php if ($backlogTotal > 0) {
                foreach ($backlogItems as $item) {
                    $pct = (int) round(($item['count'] / $backlogMax) * 100);
            ?>
                <div class="lh-bar-row">
                    <div class="lh-bar-label">
                        <span><?= htmlspecialchars($item['label']); ?></span>
                        <strong><?= $item['count']; ?></strong>
                    </div>
                    <div class="lh-bar-track">
                        <div class="lh-bar-fill" style="width:<?= $pct; ?>%; background:<?= $item['color']; ?>;"></div>
                    </div>
                </div>
            <?php }
            } else { ?>
                <div class="lh-empty">
                    <em class="fa-solid fa-circle-check"></em>
                    <?= _translate("No samples currently in the backlog."); ?>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<script>
    <?php
    if ($receivedTotal > 0) { ?>
        $('#<?php echo $samplesReceivedChart; ?>').highcharts({
            chart: {
                type: 'column',
                height: 200
            },
            title: {
                text: ''
            },
            exporting: {
                filename: "samples-registered",
                sourceWidth: 1200,
                sourceHeight: 600
            },
            subtitle: {
                text: ''
            },
            credits: {
                enabled: false
            },
            xAxis: {
                categories: [
                    <?php
                    foreach ($tResult as $tRow) {
                        echo '"' . ($tRow['date']) . '",';
                    }
                    ?>
                ],
                crosshair: true,
                scrollbar: {
                    enabled: true
                },
            },
            yAxis: {
                min: 0,
                title: {
                    text: null
                }
            },
            tooltip: {
                headerFormat: '<span style="font-size:10px">{point.key}</span><table>',
                pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
                    '<td style="padding:0"><strong>{point.y}</strong></td></tr>',
                footerFormat: '</table>',
                shared: true,
                useHTML: true
            },
            plotOptions: {
                column: {
                    pointPadding: 0.2,
                    borderWidth: 0,
                    cursor: 'pointer',
                    //point: {
                    //    events: {
                    //        click: function () {
                    //            window.location.href='/labs/samples-accession';
                    //        }
                    //    }
                    //}
                }
            },
            series: [{
                showInLegend: false,
                name: '<?= _translate("Samples", escapeTextOrContext: true); ?>',
                data: [<?php
                foreach ($tResult as $tRow) {
                    echo ($tRow['total']) . ",";
                }
                ?>]

            }],
            colors: ['#2ab4c0']
        });
    <?php }
    if ($acceptedTotal > 0) {
        ?>

        $('#<?php echo $samplesTestedChart; ?>').highcharts({
            chart: {
                type: 'column',
                height: 200
            },
            title: {
                text: ''
            },
            exporting: {
                filename: "samples-tested",
                sourceWidth: 1200,
                sourceHeight: 600
            },
            subtitle: {
                text: ''
            },
            credits: {
                enabled: false
            },
            xAxis: {
                categories: [<?php
                foreach ($acceptedResult as $tRow) {
                    echo "'" . ($tRow['date']) . "',";
                }
                ?>],
                crosshair: true,
                scrollbar: {
                    enabled: true
                },
            },
            yAxis: {
                min: 0,
                title: {
                    text: null
                }
            },
            tooltip: {
                headerFormat: '<span style="font-size:10px">{point.key}</span><table>',
                pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
                    '<td style="padding:0"><strong>{point.y}</strong></td></tr>',
                footerFormat: '</table>',
                shared: true,
                useHTML: true
            },
            plotOptions: {
                column: {
                    pointPadding: 0.2,
                    borderWidth: 0,
                    cursor: 'pointer',
                }
            },
            series: [{
                showInLegend: false,
                name: '<?= _translate("Samples", escapeTextOrContext: true); ?>',
                data: [<?php
                foreach ($acceptedResult as $tRow) {
                    echo ($tRow['total']) . ",";
                }
                ?>]

            }],
            colors: ['#7cb72a']
        });
    <?php }

    if ($rejectedTotal > 0) { ?>
        $('#<?php echo $samplesRejectedChart; ?>').highcharts({
            chart: {
                type: 'column',
                height: 300
            },
            title: {
                text: ''
            },
            exporting: {
                filename: "samples-rejected",
                sourceWidth: 1200,
                sourceHeight: 600
            },
            subtitle: {
                text: ''
            },
            credits: {
                enabled: false
            },
            xAxis: {
                categories: [<?php
                foreach ($rejectedResult as $tRow) {
                    echo "'" . ($tRow['date']) . "',";
                }
                ?>],
                crosshair: true,
                scrollbar: {
                    enabled: true
                },
            },
            yAxis: {
                min: 0,
                title: {
                    text: null
                }
            },
            tooltip: {
                headerFormat: '<span style="font-size:10px">{point.key}</span><table>',
                pointFormat: '<tr><td style="color:{series.color};padding:0">{series.name}: </td>' +
                    '<td style="padding:0"><strong>{point.y}</strong></td></tr>',
                footerFormat: '</table>',
                shared: true,
                useHTML: true
            },
            plotOptions: {
                column: {
                    pointPadding: 0.2,
                    borderWidth: 0,
                    cursor: 'pointer'
                }
            },

            series: [{
                showInLegend: false,
                name: "<?php echo _translate("Samples", escapeTextOrContext: true); ?>",
                data: [<?php
                foreach ($rejectedResult as $tRow) {
                    echo ($tRow['total']) . ",";
                }
                ?>]

            }],
            colors: ['#5C9BD1']
        });
    <?php }
    //}
    ?>

    <?php if (!empty($aggregateResult)) { ?>
        $('#<?php echo $samplesOverviewChart; ?>').highcharts({
            chart: {
                type: 'column',
                height: 250
            },

            title: {
                text: ''
            },
            exporting: {
                filename: "overall-sample-status",
                sourceWidth: 1200,
                sourceHeight: 600
            },
            credits: {
                enabled: false
            },
            xAxis: {
                categories: [
                    "<?= _translate("Samples Tested", escapeTextOrContext: true); ?>",
                    "<?= _translate("Samples Rejected", escapeTextOrContext: true); ?>",
                    "<?= _translate("Samples on Hold", escapeTextOrContext: true); ?>",
                    "<?= _translate("Samples Registered at Testing Lab", escapeTextOrContext: true); ?>",
                    "<?= _translate("Samples Awaiting Approval", escapeTextOrContext: true); ?>",
                    "<?= _translate("Samples Registered at Collection Sites", escapeTextOrContext: true); ?>"
                ]
            },

            yAxis: {
                allowDecimals: false,
                min: 0,
                title: {
                    text: "<?= _translate("No. of Samples", escapeTextOrContext: true); ?>"
                }
            },

            tooltip: {
                formatter: function () {
                    return '<strong>' + this.x + '</strong><br/>' +
                        this.series.name + ': ' + this.y + '<br/>' +
                        "<?= _translate("Total", escapeTextOrContext: true); ?>" + ': ' + this.point.stackTotal;
                }
            },

            plotOptions: {
                column: {
                    stacking: 'normal',
                    dataLabels: {
                        enabled: true
                    },
                    enableMouseTracking: false
                }
            },

            series: [{
                name: 'Sample',
                showInLegend: false,
                data: [{
                    y: <?php echo $aggregateResult['tested'] ?? 0; ?>,
                    color: '#039BE6'
                },
                {
                    y: <?php echo $aggregateResult['rejected'] ?? 0; ?>,
                    color: '#492828'
                },
                {
                    y: <?php echo $aggregateResult['hold'] ?? 0; ?>,
                    color: '#60d18f'
                },
                {
                    y: <?php echo $aggregateResult['registeredAtTestingLab'] ?? 0; ?>,
                    color: '#ff1900'
                },
                {
                    y: <?php echo $aggregateResult['awaitingApproval'] ?? 0; ?>,
                    color: '#395B64'
                },
                {
                    y: <?php echo $aggregateResult['registeredAtCollectionPoint'] ?? 0; ?>,
                    color: '#2C3333'
                }
                ],
                stack: 'total',
                color: 'red',
            }]
        });
    <?php } ?>
</script>
