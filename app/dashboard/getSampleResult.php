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
/** @var Laminas\Diactoros\ServerRequest $request */
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

$waitingTotal  = 0;
$rejectedTotal = 0;
$receivedTotal = 0;
$acceptedTotal = 0;

$waitingDate  = '';
$rejectedDate = '';

if ($testType === 'eid') {
    $samplesReceivedChart = "eidSamplesReceivedChart";
    $samplesTestedChart   = "eidSamplesTestedChart";
    $samplesRejectedChart = "eidSamplesRejectedChart";
    $samplesWaitingChart  = "eidSamplesWaitingChart";
    $samplesOverviewChart = "eidSamplesOverviewChart";
} elseif ($testType === 'covid19') {
    $samplesReceivedChart = "covid19SamplesReceivedChart";
    $samplesTestedChart   = "covid19SamplesTestedChart";
    $samplesNotTestedChart = "covid19SamplesNotTestedChart";
    $samplesRejectedChart = "covid19SamplesRejectedChart";
    $samplesWaitingChart  = "covid19SamplesWaitingChart";
    $samplesOverviewChart = "covid19SamplesOverviewChart";
} elseif ($testType === 'hepatitis') {
    $samplesReceivedChart = "hepatitisSamplesReceivedChart";
    $samplesTestedChart   = "hepatitisSamplesTestedChart";
    $samplesRejectedChart = "hepatitisSamplesRejectedChart";
    $samplesWaitingChart  = "hepatitisSamplesWaitingChart";
    $samplesOverviewChart = "hepatitisSamplesOverviewChart";
} elseif ($testType === 'vl') {
    // Partition VL from Recency
    $recencyWhere = " IFNULL(reason_for_vl_testing, 0) != 9999 ";
    $samplesReceivedChart = "vlSamplesReceivedChart";
    $samplesTestedChart   = "vlSamplesTestedChart";
    $samplesRejectedChart = "vlSamplesRejectedChart";
    $samplesWaitingChart  = "vlSamplesWaitingChart";
    $samplesOverviewChart = "vlSamplesOverviewChart";
} elseif ($testType === 'cd4') {
    $samplesReceivedChart = "cd4SamplesReceivedChart";
    $samplesTestedChart   = "cd4SamplesTestedChart";
    $samplesRejectedChart = "cd4SamplesRejectedChart";
    $samplesWaitingChart  = "cd4SamplesWaitingChart";
    $samplesOverviewChart = "cd4SamplesOverviewChart";
} elseif ($testType === 'recency') {
    // The “Recency” view is driven from the VL table but only for reason 9999
    $recencyWhere = " reason_for_vl_testing = 9999 ";
    $samplesReceivedChart = "recencySamplesReceivedChart";
    $samplesTestedChart   = "recencySamplesTestedChart";
    $samplesRejectedChart = "recencySamplesRejectedChart";
    $samplesWaitingChart  = "recencySamplesWaitingChart";
    $samplesOverviewChart = "recencySamplesOverviewChart";
} elseif ($testType === 'tb') {
    $samplesReceivedChart = "tbSamplesReceivedChart";
    $samplesTestedChart   = "tbSamplesTestedChart";
    $samplesRejectedChart = "tbSamplesRejectedChart";
    $samplesWaitingChart  = "tbSamplesWaitingChart";
    $samplesOverviewChart = "tbSamplesOverviewChart";
} elseif ($testType === 'generic-tests') {
    $samplesReceivedChart = "genericTestsSamplesReceivedChart";
    $samplesTestedChart   = "genericTestsSamplesTestedChart";
    $samplesRejectedChart = "genericTestsSamplesRejectedChart";
    $samplesWaitingChart  = "genericTestsSamplesWaitingChart";
    $samplesOverviewChart = "genericTestsSamplesOverviewChart";
}

try {
    // ---------- Base WHERE (alias-safe with `t`) ----------
    $whereParts = [];
    $whereParts[] = "t.result_status != " . SAMPLE_STATUS\CANCELLED;

    if (!$general->isSTSInstance()) {
        $whereParts[] = "t.result_status != " . SAMPLE_STATUS\RECEIVED_AT_CLINIC;
    } else {
        if (!empty($_SESSION['facilityMap'])) {
            $whereParts[] = "t.facility_id IN (" . $_SESSION['facilityMap'] . ")";
        }
    }
    $baseWhere = implode(' AND ', $whereParts);

    // ---------- Date range selection ----------
    if (!empty($_POST['sampleCollectionDate'])) {
        $selectedRange = (string) $_POST['sampleCollectionDate'];
        [$startDate, $endDate] = DateUtility::convertDateRange($_POST['sampleCollectionDate'], includeTime: true);
    } else {
        $startDate = date('Y-m-d H:i:s', strtotime('-7 days'));
        $endDate   = date('Y-m-d H:i:s');
        // human fallback for label
        $selectedRange = date('d-M-Y', strtotime($startDate)) . ' to ' . date('d-M-Y', strtotime($endDate));
    }

    $currentDateTime = DateUtility::getCurrentDateTime();

    // ---------- Table-specific fields/predicates ----------
    // Result field per table (null means “special case”)
    $resultField = match ($table) {
        'form_cd4'       => 'cd4_result',
        'form_hepatitis' => null,
        default          => 'result',
    };

    // “No result yet” per table
    $noResultExpr = match ($table) {
        'form_hepatitis' => "(NULLIF(TRIM(t.hcv_vl_count), '') IS NULL AND NULLIF(TRIM(t.hbv_vl_count), '') IS NULL)",
        'form_cd4'       => "NULLIF(TRIM(t.cd4_result), '') IS NULL",
        default          => "NULLIF(TRIM(t.result), '') IS NULL",
    };

    // Partition for VL/Recency if applicable
    $partitionWhere = ($table === 'form_vl' && !empty($recencyWhere)) ? "($recencyWhere)" : '';

    // Normalized rejection flag
    $notRejectedExpr = "LOWER(COALESCE(NULLIF(TRIM(t.is_sample_rejected), ''), 'no')) = 'no'";

    // Common builder for WHERE
    $W = function (array $extra = []) use ($baseWhere, $partitionWhere) {
        return implode(' AND ', array_values(array_filter([
            $baseWhere ?: null,
            $partitionWhere ?: null,
            ...$extra
        ])));
    };

    // ======================================================
    // A) Waiting (last 6 months, no result, not rejected)
    // ======================================================
    $waitingWhere = $W([
        "t.sample_collection_date IS NOT NULL",
        "t.sample_collection_date >= DATE_SUB('$currentDateTime', INTERVAL 6 MONTH)",
        $noResultExpr,
        $notRejectedExpr,
    ]);

    $waitingQuery = "SELECT COUNT(t.$primaryKey) AS total FROM $table AS t WHERE $waitingWhere";
    $waitingResult = $db->rawQueryOne($waitingQuery);
    $waitingTotal = (int) ($waitingResult['total'] ?? 0);

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

        $statusTotal  = 0;
        $statusResult = ['date' => [], 'status' => []];

        $statusResult = ['date' => [], 'status' => []];

        foreach ($statusQueryResult as $row) {
            $statusTotal += (int) $row['count'];
            // keep same shape you were using earlier
            $statusResult['date'][$row['collection_date']] = "'" . $row['collection_date'] . "'";
            $statusResult['status'][$row['status_name']][$row['collection_date']] = (int) $row['count'];
        }
    }
} catch (Throwable $e) {
    LoggerUtility::logError($e->getFile() . ':' . $e->getLine() . ":" . $db->getLastError());
    LoggerUtility::logError($e->getMessage(), [
        'file'  => $e->getFile(),
        'line'  => $e->getLine(),
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
                    <span data-counter="counterup" data-value="<?= $receivedTotal; ?>"><?php echo $receivedTotal; ?></span>
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
                    <span data-counter="counterup" data-value="<?php echo $acceptedTotal; ?>"><?php echo $acceptedTotal; ?></span>
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

<div class="col-lg-6 col-md-12 col-sm-12 col-xs-12 ">
    <div class="dashboard-stat2 " style="cursor:pointer;">
        <div class="display font-red-haze">
            <div class="number">
                <h3 class="font-red-haze">
                    <span data-counter="counterup" data-value="<?php echo $rejectedTotal; ?>"><?php echo $rejectedTotal; ?></span>
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
        <div id="<?php echo $samplesRejectedChart; ?>" width="210" height="200" style="min-height:200px;"></div>
    </div>
</div>

<div class="col-lg-6 col-md-12 col-sm-12 col-xs-12 ">
    <div class="dashboard-stat2 " style="cursor:pointer;">
        <div class="display font-purple-soft">
            <div class="number">
                <h3 class="font-purple-soft">
                    <span data-counter="counterup" data-value="<?php echo $waitingTotal; ?>"><?php echo $waitingTotal; ?></span>
                </h3>
                <small class="font-purple-soft">
                    <?php echo _translate("SAMPLES WITH NO RESULTS"); ?>
                </small><br>
                <small class="font-purple-soft" style="font-size:0.75em;">
                    <?php echo _translate("(LAST 6 MONTHS)"); ?>
                </small>

            </div>
            <div class="icon">
                <em class="fa-solid fa-chart-simple"></em>
            </div>
        </div>
        <div id="<?php echo $samplesWaitingChart; ?>" width="210" height="200" style="min-height:200px;"></div>
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
                    <?= _translate("(BASED ON SAMPLES COLLECTED IN THE SELECTED DATE RANGE)"); ?>
                </small>
            </div>
            <div class="icon">
                <em class="fa-solid fa-chart-simple"></em>
            </div>
        </div>
        <div id="<?php echo $samplesOverviewChart; ?>" width="210" height="200" style="min-height:200px;"></div>
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
    //waiting result
    if (!empty($waitingTotal) && $waitingTotal > 0) { ?>
        $('#<?php echo $samplesWaitingChart; ?>').highcharts({
            chart: {
                type: 'column',
                height: 200
            },
            title: {
                text: ''
            },
            exporting: {
                filename: "samples-with-no-results",
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
                categories: [''],
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
                data: [<?= $waitingTotal; ?>]

            }],
            colors: ['#8877a9']
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
                height: 200
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
                    cursor: 'pointer',
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
                formatter: function() {
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
                        y: <?php echo (isset($aggregateResult['tested'])) ? $aggregateResult['tested'] : 0; ?>,
                        color: '#039BE6'
                    },
                    {
                        y: <?php echo (isset($aggregateResult['rejected'])) ? $aggregateResult['rejected'] : 0; ?>,
                        color: '#492828'
                    },
                    {
                        y: <?php echo (isset($aggregateResult['hold'])) ? $aggregateResult['hold'] : 0; ?>,
                        color: '#60d18f'
                    },
                    {
                        y: <?php echo (isset($aggregateResult['registeredAtTestingLab'])) ? $aggregateResult['registeredAtTestingLab'] : 0; ?>,
                        color: '#ff1900'
                    },
                    {
                        y: <?php echo (isset($aggregateResult['awaitingApproval'])) ? $aggregateResult['awaitingApproval'] : 0; ?>,
                        color: '#395B64'
                    },
                    {
                        y: <?php echo (isset($aggregateResult['registeredAtCollectionPoint'])) ? $aggregateResult['registeredAtCollectionPoint'] : 0; ?>,
                        color: '#2C3333'
                    }
                ],
                stack: 'total',
                color: 'red',
            }]
        });
    <?php } ?>
</script>