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

$testType = (string) $_POST['type'];
$table = TestsService::getTestTableName($testType);
$primaryKey = TestsService::getPrimaryColumn($testType);

$waitingTotal = 0;
$rejectedTotal = 0;
$receivedTotal = 0;

$waitingDate = '';
$rejectedDate = '';
if ($testType == 'eid') {
    $samplesReceivedChart = "eidSamplesReceivedChart";
    $samplesTestedChart = "eidSamplesTestedChart";
    $samplesRejectedChart = "eidSamplesRejectedChart";
    $samplesWaitingChart = "eidSamplesWaitingChart";
    $samplesOverviewChart = "eidSamplesOverviewChart";
} elseif ($testType == 'covid19') {
    $samplesReceivedChart = "covid19SamplesReceivedChart";
    $samplesTestedChart = "covid19SamplesTestedChart";
    $samplesNotTestedChart = "covid19SamplesNotTestedChart";
    $samplesRejectedChart = "covid19SamplesRejectedChart";
    $samplesWaitingChart = "covid19SamplesWaitingChart";
    $samplesOverviewChart = "covid19SamplesOverviewChart";
} elseif ($testType == 'hepatitis') {
    $samplesReceivedChart = "hepatitisSamplesReceivedChart";
    $samplesTestedChart = "hepatitisSamplesTestedChart";
    $samplesRejectedChart = "hepatitisSamplesRejectedChart";
    $samplesWaitingChart = "hepatitisSamplesWaitingChart";
    $samplesOverviewChart = "hepatitisSamplesOverviewChart";
} elseif ($testType == 'vl') {
    $recencyWhere = " IFNULL(reason_for_vl_testing, 0)  != 9999 ";
    $samplesReceivedChart = "vlSamplesReceivedChart";
    $samplesTestedChart = "vlSamplesTestedChart";
    $samplesRejectedChart = "vlSamplesRejectedChart";
    $samplesWaitingChart = "vlSamplesWaitingChart";
    $samplesOverviewChart = "vlSamplesOverviewChart";
} elseif ($testType == 'cd4') {
    $samplesReceivedChart = "cd4SamplesReceivedChart";
    $samplesTestedChart = "cd4SamplesTestedChart";
    $samplesRejectedChart = "cd4SamplesRejectedChart";
    $samplesWaitingChart = "cd4SamplesWaitingChart";
    $samplesOverviewChart = "cd4SamplesOverviewChart";
} elseif ($testType == 'recency') {
    $recencyWhere = " reason_for_vl_testing = 9999 ";
    $samplesReceivedChart = "recencySamplesReceivedChart";
    $samplesTestedChart = "recencySamplesTestedChart";
    $samplesRejectedChart = "recencySamplesRejectedChart";
    $samplesWaitingChart = "recencySamplesWaitingChart";
    $samplesOverviewChart = "recencySamplesOverviewChart";
} elseif ($testType == 'tb') {
    $samplesReceivedChart = "tbSamplesReceivedChart";
    $samplesTestedChart = "tbSamplesTestedChart";
    $samplesRejectedChart = "tbSamplesRejectedChart";
    $samplesWaitingChart = "tbSamplesWaitingChart";
    $samplesOverviewChart = "tbSamplesOverviewChart";
} elseif ($testType == 'generic-tests') {
    $samplesReceivedChart = "genericTestsSamplesReceivedChart";
    $samplesTestedChart = "genericTestsSamplesTestedChart";
    $samplesRejectedChart = "genericTestsSamplesRejectedChart";
    $samplesWaitingChart = "genericTestsSamplesWaitingChart";
    $samplesOverviewChart = "genericTestsSamplesOverviewChart";
}

try {
    $whereConditionArray = [];
    $whereConditionArray[] = " result_status != " . SAMPLE_STATUS\CANCELLED;
    if (!$general->isSTSInstance()) {
        $whereConditionArray[] = " result_status!= " . SAMPLE_STATUS\RECEIVED_AT_CLINIC;
    } else {
        if (!empty($_SESSION['facilityMap'])) {
            $whereConditionArray[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ") ";
        }
    }

    $whereCondition = implode(" AND ", $whereConditionArray);

    if (!empty($_POST['sampleCollectionDate'])) {
        $selectedRange = $_POST['sampleCollectionDate'];
        [$startDate, $endDate] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '', includeTime: true);
    } else {
        $startDate = date('Y-m-d H:i:s', strtotime('-7 days'));
        $endDate = date('Y-m-d H:i:s');
    }

    $sWhere = '';

    $currentDateTime = DateUtility::getCurrentDateTime();
    //get waiting data
    if ($table == "form_eid") {
        $waitingQuery = "SELECT COUNT(unique_id) as total
                        FROM $table as eid
                        LEFT JOIN facility_details as f ON f.facility_id=eid.facility_id
                        WHERE $whereCondition AND (sample_collection_date > DATE_SUB('$currentDateTime', INTERVAL 6 MONTH))
                        AND (eid.result is null or eid.result = '')
                        AND (eid.is_sample_rejected like 'no'
                                    OR eid.is_sample_rejected is null
                                    OR eid.is_sample_rejected like '' )";
    } elseif ($table == "form_covid19") {
        $waitingQuery = "SELECT COUNT(unique_id) as total
                        FROM $table as covid19
                        LEFT JOIN facility_details as f ON f.facility_id=covid19.facility_id
                        WHERE $whereCondition AND (sample_collection_date > DATE_SUB('$currentDateTime', INTERVAL 6 MONTH))
                        AND (covid19.result is null or covid19.result = '')
                        AND (covid19.is_sample_rejected like 'no' or covid19.is_sample_rejected is null or covid19.is_sample_rejected like '')";
    } elseif ($table == "form_hepatitis") {
        $waitingQuery = "SELECT COUNT(unique_id) as total
                        FROM $table as hepatitis
                        LEFT JOIN facility_details as f ON f.facility_id=hepatitis.facility_id
                        WHERE $whereCondition AND (sample_collection_date > DATE_SUB('$currentDateTime', INTERVAL 6 MONTH))
                        AND (((hepatitis.hcv_vl_count IS NULL OR hepatitis.hcv_vl_count = '')
                        AND (hepatitis.hbv_vl_count IS NULL OR hepatitis.hbv_vl_count = '')))
                        AND (hepatitis.is_sample_rejected like 'no'
                                OR hepatitis.is_sample_rejected is null
                                OR hepatitis.is_sample_rejected like '' )";
    } elseif ($table == "form_tb") {
        $waitingQuery = "SELECT COUNT(unique_id) as total
                        FROM $table as tb
                        LEFT JOIN facility_details as f ON f.facility_id=tb.facility_id
                        WHERE $whereCondition AND (sample_collection_date > DATE_SUB('$currentDateTime', INTERVAL 6 MONTH))
                        AND (tb.result is null or tb.result = '')
                        AND (tb.is_sample_rejected like 'no'
                                    OR tb.is_sample_rejected is null
                                    OR tb.is_sample_rejected like '' )";
    } elseif ($table == "form_vl") {
        if ($whereCondition == "") {
            $vlWhereCondition = "$recencyWhere AND ";
        } else {
            $vlWhereCondition = "$recencyWhere AND $whereCondition";
        }
        $waitingQuery = "SELECT COUNT(unique_id) as total
                        FROM $table as vl LEFT JOIN facility_details as f ON f.facility_id=vl.facility_id
                        WHERE $vlWhereCondition AND (sample_collection_date > DATE_SUB('$currentDateTime', INTERVAL 6 MONTH))
                        AND (vl.result is null or vl.result = '')
                        AND (vl.is_sample_rejected like 'no'
                                OR vl.is_sample_rejected is null
                                OR vl.is_sample_rejected = '' )";
    } elseif ($table == "form_cd4") {
        $waitingQuery = "SELECT COUNT(unique_id) as total
                        FROM $table as vl LEFT JOIN facility_details as f ON f.facility_id=vl.facility_id
                        WHERE $whereCondition AND (sample_collection_date > DATE_SUB('$currentDateTime', INTERVAL 6 MONTH))
                        AND (vl.cd4_result is null or vl.cd4_result = '')
                        AND (vl.is_sample_rejected like 'no'
                                OR vl.is_sample_rejected is null
                                OR vl.is_sample_rejected = '' )";
    } elseif ($table == "form_generic") {
        $waitingQuery = "SELECT COUNT(unique_id) as total
                        FROM $table as vl
                        LEFT JOIN facility_details as f ON f.facility_id=vl.facility_id
                        WHERE $whereCondition AND (sample_collection_date > DATE_SUB('$currentDateTime', INTERVAL 6 MONTH))
                        AND (vl.result is null or vl.result = '')
                        AND (vl.is_sample_rejected like 'no'
                                    OR vl.is_sample_rejected is null
                                    OR vl.is_sample_rejected like '' )";
    }

    $waitingResult = $db->rawQueryOne($waitingQuery); //waiting result
    $waitingTotal = 0;
    if (!empty($waitingResult['total']) && $waitingResult['total'] > 0) {
        $waitingTotal = $waitingResult['total'];
    } else {
        unset($waitingResult);
    }


    $resultField = 'result';
    if ($table === 'form_cd4') {
        $resultField = 'cd4_result';
    }

    $aggregateQuery = "SELECT COUNT(unique_id) as totalCollected,
    SUM(CASE WHEN (
        vl.lab_id IS NOT NULL
        AND vl.sample_tested_datetime IS NOT NULL
        AND IFNULL(vl.$resultField, '') != ''
        AND vl.result_status = " . SAMPLE_STATUS\ACCEPTED . "
    ) THEN 1 ELSE 0 END) as 'tested',
    SUM(CASE WHEN (vl.result_status = " . SAMPLE_STATUS\ON_HOLD . ") THEN 1 ELSE 0 END) as 'hold',
    SUM(CASE WHEN (vl.result_status = " . SAMPLE_STATUS\REJECTED . ") THEN 1 ELSE 0 END) as 'rejected',
    SUM(CASE WHEN (vl.result_status = " . SAMPLE_STATUS\TEST_FAILED . ") THEN 1 ELSE 0 END) as 'invalid',
    SUM(CASE WHEN (vl.result_status = " . SAMPLE_STATUS\RECEIVED_AT_TESTING_LAB . ") THEN 1 ELSE 0 END) as 'registeredAtTestingLab',
    SUM(CASE WHEN (vl.result_status = " . SAMPLE_STATUS\PENDING_APPROVAL . ") THEN 1 ELSE 0 END) as 'awaitingApproval',
    SUM(CASE WHEN (vl.result_status = " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . ") THEN 1 ELSE 0 END) as 'registeredAtCollectionPoint',
    SUM(CASE WHEN (vl.result_status = " . SAMPLE_STATUS\EXPIRED . ") THEN 1 ELSE 0 END) as 'expired'
    FROM $table as vl
    INNER JOIN facility_details as f ON f.facility_id = vl.facility_id
    WHERE vl.sample_collection_date BETWEEN '$startDate' AND '$endDate'";

    $aggregateResult = $db->rawQueryOne($aggregateQuery);

    // Samples Accession
    if ($table == "form_vl") {
        $whereCondition = $recencyWhere . " AND " . ($whereCondition ?: "");
    }
    $accessionQuery = "SELECT
                        DATE_FORMAT(DATE(vl.sample_collection_date), '$mysqlDateFormat') as `collection_date`,
                        COUNT(unique_id) as `count`
                        FROM $table as vl
                        LEFT JOIN facility_details as f ON f.facility_id=vl.facility_id
                        WHERE $whereCondition AND vl.sample_collection_date BETWEEN '$startDate' AND '$endDate'
                        GROUP BY `collection_date`
                        ORDER BY vl.sample_collection_date";
    //      echo $accessionQuery; die;
    $tRes = $db->rawQuery($accessionQuery); //overall result
    $tResult = [];
    foreach ($tRes as $tRow) {
        $receivedTotal += $tRow['count'];
        $tResult[] = ['total' => $tRow['count'], 'date' => $tRow['collection_date']];
    }
    //echo $receivedTotal; die;
    //Samples Tested
    if ($table == "form_vl") {
        $whereCondition = $recencyWhere . " AND " . ($whereCondition ?: "");
    }
    $sampleTestedQuery = "SELECT
                            DATE_FORMAT(DATE(vl.sample_tested_datetime), '$mysqlDateFormat') as `test_date`,
                            COUNT(unique_id) as `count`
                            FROM $table as vl
                            LEFT JOIN facility_details as f ON f.facility_id=vl.facility_id
                            LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id
                            WHERE (result_status = 7) AND
                            $whereCondition AND
                            vl.sample_tested_datetime BETWEEN '$startDate' AND '$endDate'
                            GROUP BY `test_date`
                            ORDER BY vl.sample_tested_datetime";
    //echo $sampleTestedQuery; die;
    $tRes = $db->rawQuery($sampleTestedQuery); //overall result
    $acceptedResult = [];
    $acceptedTotal = 0;
    foreach ($tRes as $tRow) {
        $acceptedTotal += $tRow['count'];
        $acceptedResult[] = ['total' => $tRow['count'], 'date' => $tRow['test_date']];
    }

    //Rejected Samples
    if ($table == "form_vl") {
        $whereCondition = $recencyWhere . " AND " . ($whereCondition ?: "");
    }
    $sampleRejectedQuery = "SELECT
                            DATE_FORMAT(DATE(vl.sample_collection_date), '$mysqlDateFormat') as `collection_date`,
                            COUNT(unique_id) as `count`
                            FROM $table as vl INNER JOIN facility_details as f ON f.facility_id=vl.facility_id
                            INNER JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id
                            WHERE (result_status = 4) AND $whereCondition AND
                            vl.sample_collection_date BETWEEN '$startDate' AND '$endDate'
                            GROUP BY `collection_date`
                            ORDER BY vl.sample_collection_date";
    $tRes = $db->rawQuery($sampleRejectedQuery); //overall result
    $rejectedResult = [];
    foreach ($tRes as $tRow) {
        $rejectedTotal += $tRow['count'];
        $rejectedResult[] = ['total' => $tRow['count'], 'date' => $tRow['collection_date']];
    }

    //Status counts
    if ($table == "form_covid19") {
        $statusQuery = "SELECT s.status_name,
                        DATE_FORMAT(DATE(covid19.sample_collection_date), '$mysqlDateFormat') as `collection_date`,
                        COUNT(covid19_id) as `count`
                        FROM r_sample_status AS s
                        INNER JOIN $table as covid19 ON covid19.result_status=s.status_id
                        WHERE covid19.sample_collection_date BETWEEN '$startDate'  AND '$endDate'
                        GROUP BY `collection_date`
                        ORDER BY covid19.sample_collection_date";
        $statusQueryResult = $db->rawQuery($statusQuery); //overall result
        $statusTotal = 0;
        foreach ($statusQueryResult as $statusRow) {
            $statusTotal += $statusRow['count'];
            $statusResult['date'][$statusRow['collection_date']] = "'" . $statusRow['collection_date'] . "'";
            $statusResult['status'][$statusRow['status_name']][$statusRow['collection_date']] = $statusRow['count'];
        }
    }
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