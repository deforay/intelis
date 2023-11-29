<?php

use App\Utilities\DateUtility;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = $GLOBALS['request'];
$_POST = $request->getParsedBody();

$waitingTotal = 0;
$rejectedTotal = 0;
$receivedTotal = 0;
$dFormat = '';
$waitingDate = '';
$rejectedDate = '';
$i = 0;
if (isset($_POST['type']) && trim($_POST['type']) == 'eid') {
    $table = "form_eid";
    $samplesReceivedChart = "eidSamplesReceivedChart";
    $samplesTestedChart = "eidSamplesTestedChart";
    $samplesRejectedChart = "eidSamplesRejectedChart";
    $samplesWaitingChart = "eidSamplesWaitingChart";
    $samplesOverviewChart = "eidSamplesOverviewChart";
} elseif (isset($_POST['type']) && trim($_POST['type']) == 'covid19') {
    $table = "form_covid19";
    $samplesReceivedChart = "covid19SamplesReceivedChart";
    $samplesTestedChart = "covid19SamplesTestedChart";
    $samplesNotTestedChart = "covid19SamplesNotTestedChart";
    $samplesRejectedChart = "covid19SamplesRejectedChart";
    $samplesWaitingChart = "covid19SamplesWaitingChart";
    $samplesOverviewChart = "covid19SamplesOverviewChart";
} elseif (isset($_POST['type']) && trim($_POST['type']) == 'hepatitis') {
    $table = "form_hepatitis";
    $samplesReceivedChart = "hepatitisSamplesReceivedChart";
    $samplesTestedChart = "hepatitisSamplesTestedChart";
    $samplesRejectedChart = "hepatitisSamplesRejectedChart";
    $samplesWaitingChart = "hepatitisSamplesWaitingChart";
    $samplesOverviewChart = "hepatitisSamplesOverviewChart";
} elseif (isset($_POST['type']) && trim($_POST['type']) == 'vl') {
    $recencyWhere = " IFNULL(reason_for_vl_testing, 0)  != 9999 ";
    $table = "form_vl";
    $samplesReceivedChart = "vlSamplesReceivedChart";
    $samplesTestedChart = "vlSamplesTestedChart";
    $samplesRejectedChart = "vlSamplesRejectedChart";
    $samplesWaitingChart = "vlSamplesWaitingChart";
    $samplesOverviewChart = "vlSamplesOverviewChart";
} elseif (isset($_POST['type']) && trim($_POST['type']) == 'recency') {
    $recencyWhere = " reason_for_vl_testing = 9999 ";
    $table = "form_vl";
    $samplesReceivedChart = "recencySamplesReceivedChart";
    $samplesTestedChart = "recencySamplesTestedChart";
    $samplesRejectedChart = "recencySamplesRejectedChart";
    $samplesWaitingChart = "recencySamplesWaitingChart";
    $samplesOverviewChart = "recencySamplesOverviewChart";
} elseif (isset($_POST['type']) && trim($_POST['type']) == 'tb') {
    $table = "form_tb";
    $samplesReceivedChart = "tbSamplesReceivedChart";
    $samplesTestedChart = "tbSamplesTestedChart";
    $samplesRejectedChart = "tbSamplesRejectedChart";
    $samplesWaitingChart = "tbSamplesWaitingChart";
    $samplesOverviewChart = "tbSamplesOverviewChart";
} elseif (isset($_POST['type']) && trim($_POST['type']) == 'generic-tests') {
    $table = "form_generic";
    $samplesReceivedChart = "genericTestsSamplesReceivedChart";
    $samplesTestedChart = "genericTestsSamplesTestedChart";
    $samplesRejectedChart = "genericTestsSamplesRejectedChart";
    $samplesWaitingChart = "genericTestsSamplesWaitingChart";
    $samplesOverviewChart = "genericTestsSamplesOverviewChart";
}

if ($_SESSION['instanceType'] != 'remoteuser') {
    $whereCondition = " result_status!= " . SAMPLE_STATUS\RECEIVED_AT_CLINIC . "  AND ";
} else {
    $whereCondition = "";
    if (!empty($_SESSION['facilityMap'])) {
        if (isset($_POST['type']) && trim($_POST['type']) == 'eid') {
            $whereCondition = " eid.facility_id IN (" . $_SESSION['facilityMap'] . ") AND ";
        } elseif (isset($_POST['type']) && trim($_POST['type']) == 'covid19') {
            $whereCondition = " covid19.facility_id IN (" . $_SESSION['facilityMap'] . ") AND ";
        } elseif (isset($_POST['type']) && trim($_POST['type']) == 'tb') {
            $whereCondition = " tb.facility_id IN (" . $_SESSION['facilityMap'] . ") AND ";
        } elseif (isset($_POST['type']) && trim($_POST['type']) == 'hepatitis') {
            $whereCondition = " hepatitis.facility_id IN (" . $_SESSION['facilityMap'] . ") AND ";
        } elseif (isset($_POST['type']) && trim($_POST['type']) == 'generic-tests') {
            $whereCondition = " generic.facility_id IN (" . $_SESSION['facilityMap'] . ") AND ";
        } else {
            $whereCondition = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")  AND ";
        }
    }
}

if (!empty($_POST['sampleCollectionDate'])) {
    [$startDate, $endDate] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');
} else {
    $startDate = date('Y-m-d', strtotime('-7 days'));
    $endDate = date('Y-m-d');
}

$sWhere = '';

$currentDateTime = DateUtility::getCurrentDateTime();
//get waiting data
if ($table == "form_eid") {
    $waitingQuery = "SELECT COUNT(unique_id) as total
                        FROM $table as eid
                        LEFT JOIN facility_details as f ON f.facility_id=eid.facility_id
                        WHERE $whereCondition (sample_collection_date > DATE_SUB('$currentDateTime', INTERVAL 6 MONTH))
                        AND (eid.result is null or eid.result = '')
                        AND (eid.is_sample_rejected like 'no'
                                    OR eid.is_sample_rejected is null
                                    OR eid.is_sample_rejected like '' )";
} elseif ($table == "form_covid19") {
    $waitingQuery = "SELECT COUNT(unique_id) as total
                        FROM $table as covid19
                        LEFT JOIN facility_details as f ON f.facility_id=covid19.facility_id
                        WHERE $whereCondition (sample_collection_date > DATE_SUB('$currentDateTime', INTERVAL 6 MONTH))
                        AND (covid19.result is null or covid19.result = '')
                        AND (covid19.is_sample_rejected like 'no' or covid19.is_sample_rejected is null or covid19.is_sample_rejected like '')";
} elseif ($table == "form_hepatitis") {
    $waitingQuery = "SELECT COUNT(unique_id) as total
                        FROM $table as hepatitis
                        LEFT JOIN facility_details as f ON f.facility_id=hepatitis.facility_id
                        WHERE $whereCondition (sample_collection_date > DATE_SUB('$currentDateTime', INTERVAL 6 MONTH))
                        AND (((hepatitis.hcv_vl_count IS NULL OR hepatitis.hcv_vl_count = '')
                        AND (hepatitis.hbv_vl_count IS NULL OR hepatitis.hbv_vl_count = '')))
                        AND (hepatitis.is_sample_rejected like 'no'
                                OR hepatitis.is_sample_rejected is null
                                OR hepatitis.is_sample_rejected like '' )";
} elseif ($table == "form_tb") {
    $waitingQuery = "SELECT COUNT(unique_id) as total
                        FROM $table as tb
                        LEFT JOIN facility_details as f ON f.facility_id=tb.facility_id
                        WHERE $whereCondition (sample_collection_date > DATE_SUB('$currentDateTime', INTERVAL 6 MONTH))
                        AND (tb.result is null or tb.result = '')
                        AND (tb.is_sample_rejected like 'no'
                                    OR tb.is_sample_rejected is null
                                    OR tb.is_sample_rejected like '' )";
} elseif ($table == "form_vl") {
    if ($whereCondition == "") {
        $vlWhereCondition = $recencyWhere . " AND ";
    } else {
        $vlWhereCondition = $recencyWhere . " AND " . $whereCondition;
    }
    $waitingQuery = "SELECT COUNT(unique_id) as total
                        FROM $table as vl LEFT JOIN facility_details as f ON f.facility_id=vl.facility_id
                        WHERE $vlWhereCondition (sample_collection_date > DATE_SUB('$currentDateTime', INTERVAL 6 MONTH))
                        AND (vl.result is null or vl.result = '')
                        AND (vl.is_sample_rejected like 'no'
                                OR vl.is_sample_rejected is null
                                OR vl.is_sample_rejected = '' )";
} elseif ($table == "form_generic") {
    $waitingQuery = "SELECT COUNT(unique_id) as total
                        FROM $table as generic
                        LEFT JOIN facility_details as f ON f.facility_id=generic.facility_id
                        WHERE $whereCondition (sample_collection_date > DATE_SUB('$currentDateTime', INTERVAL 6 MONTH))
                        AND (generic.result is null or generic.result = '')
                        AND (generic.is_sample_rejected like 'no'
                                    OR generic.is_sample_rejected is null
                                    OR generic.is_sample_rejected like '' )";
}

$waitingResult[$i] = $db->rawQuery($waitingQuery); //waiting result
if ($waitingResult[$i][0]['total'] != 0) {
    $waitingTotal = $waitingTotal + $waitingResult[$i][0]['total'];
    $waitingResult[$i]['date'] = $dFormat;
    $waitingDate = $dFormat;
} else {
    unset($waitingResult[$i]);
}


$aggregateQuery = "SELECT COUNT(unique_id) as totalCollected,
    SUM(CASE WHEN (vl.lab_id is NOT NULL AND vl.sample_tested_datetime is NOT NULL
                        AND vl.result is NOT NULL AND vl.result not like ''
                        AND vl.result_status = 7) THEN 1 ELSE 0 END)
                                                            as 'tested',
    SUM(CASE WHEN (vl.result_status = 1) THEN 1 ELSE 0 END) as 'hold',
    SUM(CASE WHEN (vl.result_status = 4) THEN 1 ELSE 0 END) as 'rejected',
    SUM(CASE WHEN (vl.result_status = 5) THEN 1 ELSE 0 END) as 'invalid',
    SUM(CASE WHEN (vl.result_status = 6) THEN 1 ELSE 0 END) as 'registeredAtTestingLab',
    SUM(CASE WHEN (vl.result_status = 8) THEN 1 ELSE 0 END) as 'awaitingApproval',
    SUM(CASE WHEN (vl.result_status = 9) THEN 1 ELSE 0 END) as 'registeredAtCollectionPoint',
    SUM(CASE WHEN (vl.result_status = 10) THEN 1 ELSE 0 END) as 'expired'
FROM $table as vl
INNER JOIN facility_details as f ON f.facility_id=vl.facility_id
WHERE DATE(vl.sample_collection_date) BETWEEN '$startDate' AND '$endDate'";

$aggregateResult = $db->rawQueryOne($aggregateQuery);



// Samples Accession
if ($table == "form_vl") {
    $whereCondition = $recencyWhere . " AND " . ($whereCondition ?: "");
}
$accessionQuery = 'SELECT DATE(vl.sample_collection_date) as `collection_date`, COUNT(unique_id) as `count` FROM ' . $table . ' as vl LEFT JOIN facility_details as f ON f.facility_id=vl.facility_id WHERE ' . $whereCondition . ' DATE(vl.sample_collection_date) BETWEEN "' . $startDate . '" AND "' . $endDate . '" GROUP BY `collection_date` ORDER BY `collection_date`';
$tRes = $db->rawQuery($accessionQuery); //overall result
$tResult = [];
foreach ($tRes as $tRow) {
    $receivedTotal += $tRow['count'];
    $tResult[] = array('total' => $tRow['count'], 'date' => $tRow['collection_date']);
}

//Samples Tested
if ($table == "form_vl") {
    $whereCondition = $recencyWhere . " AND " . ($whereCondition ?: "");
}
$sampleTestedQuery = "SELECT DATE(vl.sample_tested_datetime) as `test_date`, COUNT(unique_id) as `count` FROM $table as vl LEFT JOIN facility_details as f ON f.facility_id=vl.facility_id LEFT JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id WHERE (result_status = 7) AND $whereCondition DATE(vl.sample_tested_datetime) BETWEEN '$startDate' AND '$endDate' GROUP BY `test_date` ORDER BY `test_date`";
//echo $sampleTestedQuery; die;
$tRes = $db->rawQuery($sampleTestedQuery); //overall result
$acceptedResult = [];
$acceptedTotal = 0;
foreach ($tRes as $tRow) {
    $acceptedTotal += $tRow['count'];
    $acceptedResult[] = array('total' => $tRow['count'], 'date' => $tRow['test_date']);
}

//Rejected Samples
if ($table == "form_vl") {
    $whereCondition = $recencyWhere . " AND " . ($whereCondition ?: "");
}
$sampleRejectedQuery = 'SELECT DATE(vl.sample_collection_date) as `collection_date`, COUNT(unique_id) as `count` FROM ' . $table . ' as vl INNER JOIN facility_details as f ON f.facility_id=vl.facility_id  INNER JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id WHERE (result_status = 4) AND ' . $whereCondition . ' DATE(vl.sample_collection_date) BETWEEN "' . $startDate . '" AND "' . $endDate . '" GROUP BY `collection_date` ORDER BY `collection_date`';
$tRes = $db->rawQuery($sampleRejectedQuery); //overall result
$rejectedResult = [];
foreach ($tRes as $tRow) {
    $rejectedTotal += $tRow['count'];
    $rejectedResult[] = array('total' => $tRow['count'], 'date' => $tRow['collection_date']);
}

//Status counts
if ($table == "form_covid19") {
    $statusQuery = 'SELECT s.status_name, DATE(covid19.sample_collection_date) as `collection_date`, COUNT(covid19_id) as `count` FROM r_sample_status AS s INNER JOIN ' . $table . ' as covid19 ON covid19.result_status=s.status_id WHERE DATE(covid19.sample_collection_date) <= "' . $endDate . '" AND DATE(covid19.sample_collection_date) >= "' . $startDate . '" GROUP BY `collection_date` order by `collection_date`';
    $statusQueryResult = $db->rawQuery($statusQuery); //overall result
    $statusTotal = 0;
    foreach ($statusQueryResult as $statusRow) {
        $statusTotal += $statusRow['count'];
        $statusResult['date'][$statusRow['collection_date']] = "'" . $statusRow['collection_date'] . "'";
        $statusResult['status'][$statusRow['status_name']][$statusRow['collection_date']] = $statusRow['count'];
    }
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
                    <?= _translate("SAMPLES REGISTERED"); ?>
                </small><br>
                <small class="font-green-sharp" style="font-size:0.75em;">
                    <?php echo _translate("In Selected Range"); ?>
                </small>
            </div>
            <div class="icon font-green-sharp">
                <em class="fa-solid fa-chart-simple"></em>
            </div>
        </div>
        <div id="<?= $samplesReceivedChart; ?>" width="210" height="150" style="min-height:150px;"></div>
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
                    <?php echo _translate("In Selected Range"); ?>
                </small>
            </div>
            <div class="icon">
                <em class="fa-solid fa-chart-simple"></em>
            </div>
        </div>
        <div id="<?php echo $samplesTestedChart; ?>" width="210" height="150" style="min-height:150px;"></div>
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
                    <?php echo _translate("In Selected Range"); ?>
                </small>
            </div>
            <div class="icon">
                <em class="fa-solid fa-chart-simple"></em>
            </div>
        </div>
        <div id="<?php echo $samplesRejectedChart; ?>" width="210" height="150" style="min-height:150px;"></div>
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
        <div id="<?php echo $samplesWaitingChart; ?>" width="210" height="150" style="min-height:150px;"></div>
    </div>
</div>
<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 ">
    <div class="dashboard-stat2 bluebox" style="cursor:pointer;">
        <div class="display font-purple-soft">
            <div class="number">
                <h4 class="font-purple-soft" style="font-weight:600;">
                    <?= _translate("OVERALL SAMPLE STATUS"); ?>
                </h4>
                <small class="font-purple-soft" style="font-size:0.75em;">
                    <?= _translate("(BASED ON SAMPLES COLLECTED IN THE SELECTED DATE RANGE)"); ?>
                </small>
            </div>
            <div class="icon">
                <em class="fa-solid fa-chart-simple"></em>
            </div>
        </div>
        <div id="<?php echo $samplesOverviewChart; ?>" width="210" height="150" style="min-height:240px;"></div>
    </div>
</div>
<script>
    <?php
    if ($receivedTotal > 0) { ?>
        $('#<?php echo $samplesReceivedChart; ?>').highcharts({
            chart: {
                type: 'column',
                height: 150
            },
            title: {
                text: ''
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
                name: 'Samples',
                data: [<?php
                        foreach ($tResult as $tRow) {
                            echo ($tRow['total']) . ",";
                        }
                        ?>]

            }],
            colors: ['#2ab4c0'],
        });
    <?php }
    //waiting result
    if ($waitingTotal > 0) { ?>
        $('#<?php echo $samplesWaitingChart; ?>').highcharts({
            chart: {
                type: 'column',
                height: 150
            },
            title: {
                text: ''
            },
            subtitle: {
                text: ''
            },
            credits: {
                enabled: false
            },
            xAxis: {
                categories: [<?php
                                foreach ($waitingResult as $total) {
                                    echo "'" . ($total['date']) . "',";
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
                name: 'Samples',
                data: [<?php
                        foreach ($waitingResult as $total) {
                            echo ($total[0]['total']) . ",";
                        }
                        ?>]

            }],
            colors: ['#8877a9']
        });
    <?php }
    if ($acceptedTotal > 0) {
    ?>

        $('#<?php echo $samplesTestedChart; ?>').highcharts({
            chart: {
                type: 'column',
                height: 150
            },
            title: {
                text: ''
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
                name: 'Samples',
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
                height: 150
            },
            title: {
                text: ''
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
                name: "<?php echo _translate("Samples"); ?>",
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
                type: 'column'
            },

            title: {
                text: ''
            },
            exporting: {
                chartOptions: {
                    subtitle: {
                        text: "<?= _translate("Overall Sample Status"); ?>",
                    }
                }
            },
            credits: {
                enabled: false
            },
            xAxis: {
                categories: [
                    "<?= _translate("Samples Tested"); ?>",
                    "<?= _translate("Samples Rejected"); ?>",
                    "<?= _translate("Samples on Hold"); ?>",
                    "<?= _translate("Samples Registered at Testing Lab"); ?>",
                    "<?= _translate("Samples Awaiting Approval"); ?>",
                    "<?= _translate("Samples Registered at Collection Site"); ?>"
                ]
            },

            yAxis: {
                allowDecimals: false,
                min: 0,
                title: {
                    text: 'No. of Samples'
                }
            },

            tooltip: {
                formatter: function() {
                    return '<strong>' + this.x + '</strong><br/>' +
                        this.series.name + ': ' + this.y + '<br/>' +
                        'Total: ' + this.point.stackTotal;
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
