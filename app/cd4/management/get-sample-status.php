<?php

use App\Registries\AppRegistry;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Utilities\MiscUtility;



/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$whereCondition = '';

if ($general->isSTSInstance() && !empty($_SESSION['facilityMap'])) {
    $whereCondition = " AND vl.facility_id IN (" . $_SESSION['facilityMap'] . ")   ";
}

$tsQuery = "SELECT * FROM `r_sample_status` ORDER BY `status_id`";
$tsResult = $db->rawQuery($tsQuery);

$sampleStatusColors = [];

$sampleStatusColors[1] = "#dda41b"; // HOLD
$sampleStatusColors[2] = "#9a1c64"; // LOST
$sampleStatusColors[3] = "grey"; // Sample Reordered
$sampleStatusColors[4] = "#d8424d"; // Rejected
$sampleStatusColors[5] = "black"; // Invalid
$sampleStatusColors[6] = "#e2d44b"; // Sample Received at lab
$sampleStatusColors[7] = "#639e11"; // Accepted
$sampleStatusColors[8] = "#7f22e8"; // Sent to Lab
$sampleStatusColors[9] = "#4BC0D9"; // Sample Registered at Health Center

//date
[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');

[$labStartDate, $labEndDate] = DateUtility::convertDateRange($_POST['sampleReceivedDateAtLab'] ?? '');

[$testedStartDate, $testedEndDate] = DateUtility::convertDateRange($_POST['sampleTestedDate'] ?? '');
$tQuery = "SELECT COUNT(cd4_id) as total,status_id,status_name
                FROM form_cd4 as vl
                JOIN r_sample_status as ts ON ts.status_id=vl.result_status
                JOIN facility_details as f ON vl.lab_id=f.facility_id
                LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";
//filter
$sWhere = [];
if (!empty($whereCondition))
    $sWhere[] = $whereCondition;
if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) != '') {
    $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}
if (!empty($_POST['sampleCollectionDate'])) {
    $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
}
if (isset($_POST['sampleReceivedDateAtLab']) && trim((string) $_POST['sampleReceivedDateAtLab']) != '') {
    $sWhere[] = " DATE(vl.sample_received_at_lab_datetime) BETWEEN '$labStartDate' AND '$labEndDate'";
}
if (isset($_POST['sampleTestedDate']) && trim((string) $_POST['sampleTestedDate']) != '') {
    $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $testedStartDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $testedEndDate . '"';
}
if (!empty($_POST['labName'])) {
    $sWhere[] = ' vl.lab_id = ' . $_POST['labName'];
}
if (!empty($sWhere)) {
    $tQuery .= " where " . implode(" AND ", $sWhere);
}
$tQuery .= " GROUP BY vl.result_status ORDER BY status_id";

$tResult = $db->rawQuery($tQuery);

$vlSuppressionQuery = "SELECT   COUNT(cd4_id) as total,
                                SUM(CASE
                                        WHEN (vl.cd4_result > 0 and vl.cd4_result!='' and vl.cd4_result is not null) THEN 1
                                            ELSE 0
                                        END) AS positiveResult,
                                (SUM(CASE
                                        WHEN (vl.cd4_result < 0 and vl.cd4_result!='' and vl.cd4_result is not null) THEN 1
                                            ELSE 0
                                        END)) AS negativeResult,
                                (SUM(CASE
                                        WHEN (vl.is_sample_rejected = 'yes' ) THEN 1
                                            ELSE 0
                                        END)) AS rejectedResult,
                                status_id,
                                status_name

                                FROM form_cd4 as vl INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status JOIN facility_details as f ON vl.lab_id=f.facility_id LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id ";

// $sWhere = " AND (vl.result!='' and vl.result is not null) ";
$sWhere = [];
if (!empty($whereCondition))
    $sWhere[] = $whereCondition;
if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) != '') {
    $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}
if (!empty($_POST['sampleCollectionDate'])) {
    $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
}
if (isset($_POST['sampleReceivedDateAtLab']) && trim((string) $_POST['sampleReceivedDateAtLab']) != '') {
    $sWhere[] = " DATE(vl.sample_received_at_lab_datetime) BETWEEN '$labStartDate' AND '$labEndDate'";
}
if (isset($_POST['sampleTestedDate']) && trim((string) $_POST['sampleTestedDate']) != '') {
    $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $testedStartDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $testedEndDate . '"';
}
if (isset($_POST['sampleType']) && trim((string) $_POST['sampleType']) != '') {
    $sWhere[] = ' s.sample_id = "' . $_POST['sampleType'] . '"';
}
if (!empty($_POST['labName'])) {
    $sWhere[] = ' vl.lab_id = ' . $_POST['labName'];
}
if (!empty($sWhere)) {
    $vlSuppressionQuery .= " where " . implode(" AND ", $sWhere);
}
$vlSuppressionResult = $db->rawQueryOne($vlSuppressionQuery);

//get LAB TAT
//get LAB TAT
if (isset($_POST['sampleTestedDate']) && trim((string) $_POST['sampleTestedDate']) != '') {
    $tatStartDate = $testedStartDate;
    $tatEndDate = $testedEndDate;
} else {
    $date = new DateTime();
    $tatEndDate = $date->format('Y-m-d');
    $date->modify('-1 year');
    $tatStartDate = $date->format('Y-m-d');
}

$tatSampleQuery = "SELECT
        COUNT(DISTINCT vl.unique_id) AS 'totalSamples',
        COUNT(DISTINCT CASE WHEN vl.sample_collection_date BETWEEN '$tatStartDate' AND '$tatEndDate' THEN vl.unique_id END) AS 'numberCollected',
        COUNT(DISTINCT CASE WHEN vl.sample_tested_datetime BETWEEN '$tatStartDate' AND '$tatEndDate' THEN vl.unique_id END) AS 'numberTested',
        COUNT(DISTINCT CASE WHEN vl.sample_received_at_lab_datetime BETWEEN '$tatStartDate' AND '$tatEndDate' THEN vl.unique_id END) AS 'numberReceived',
        DATE_FORMAT(DATE(vl.sample_tested_datetime), '%b-%Y') as monthDate,
    CAST(ABS(AVG(TIMESTAMPDIFF(DAY,vl.sample_tested_datetime,vl.sample_collection_date))) AS DECIMAL (10,2)) as AvgCollectedTested,
    CAST(ABS(AVG(TIMESTAMPDIFF(DAY,vl.sample_received_at_lab_datetime,vl.sample_collection_date))) AS DECIMAL (10,2)) as AvgCollectedReceived,
    CAST(ABS(AVG(TIMESTAMPDIFF(DAY,vl.sample_tested_datetime,vl.sample_received_at_lab_datetime))) AS DECIMAL (10,2)) as AvgReceivedTested,
    CAST(ABS(AVG(TIMESTAMPDIFF(DAY,vl.result_printed_datetime,vl.sample_collection_date))) AS DECIMAL (10,2)) as AvgCollectedPrinted,
    CAST(ABS(AVG(TIMESTAMPDIFF(DAY,vl.sample_tested_datetime,vl.result_printed_datetime))) AS DECIMAL (10,2)) as AvgTestedPrinted

    FROM form_cd4 as vl
    INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status
    INNER JOIN facility_details as f ON vl.lab_id=f.facility_id

    WHERE
    vl.cd4_result is not null AND
    vl.cd4_result != '' AND
    DATE(vl.sample_tested_datetime) BETWEEN '$tatStartDate' AND '$tatEndDate'  ";
$sWhere = [];
if (!empty($whereCondition)) {
    $sWhere[] = $whereCondition;
}

if (isset($_POST['sampleTestedDate']) && trim((string) $_POST['sampleTestedDate']) != '') {
    $sWhere[] = " DATE(vl.sample_tested_datetime) BETWEEN '$testedStartDate' AND '$testedEndDate' ";
} else {
    $date = new DateTime();
    $tatEndDate = $date->format('Y-m-d');
    $date->modify('-1 year');
    $tatStartDate = $date->format('Y-m-d');
    $sWhere[] = " DATE(vl.sample_tested_datetime) BETWEEN '$tatStartDate' AND '$tatEndDate' ";
}

if (!empty($_POST['labName'])) {
    $sWhere[] = ' vl.lab_id = ' . $_POST['labName'];
}

if (!empty($sWhere)) {
    $tatSampleQuery .= " AND " . implode(" AND ", $sWhere);
}
$tatSampleQuery .= " GROUP BY monthDate ORDER BY sample_tested_datetime ";
//echo $tatSampleQuery;
$tatResult = $db->rawQuery($tatSampleQuery);

$j = 0;
foreach ($tatResult as $sRow) {
    if ($sRow["monthDate"] == null) {
        continue;
    }

    $result['totalSamples'][$j] = (isset($sRow["totalSamples"]) && $sRow["totalSamples"] > 0 && $sRow["totalSamples"] != null) ? $sRow["totalSamples"] : 'null';
    $result['numberCollected'][$j] = (isset($sRow["numberCollected"]) && $sRow["numberCollected"] > 0 && $sRow["numberCollected"] != null) ? $sRow["numberCollected"] : 'null';
    $result['numberTested'][$j] = (isset($sRow["numberTested"]) && $sRow["numberTested"] > 0 && $sRow["numberTested"] != null) ? $sRow["numberTested"] : 'null';
    $result['numberReceived'][$j] = (isset($sRow["numberReceived"]) && $sRow["numberReceived"] > 0 && $sRow["numberReceived"] != null) ? $sRow["numberReceived"] : 'null';
    $result['AvgTestedPrinted'][$j] = (isset($sRow["AvgTestedPrinted"]) && $sRow["AvgTestedPrinted"] > 0 && $sRow["AvgTestedPrinted"] != null) ? $sRow["AvgTestedPrinted"] : 'null';
    $result['sampleTestedDiff'][$j] = (isset($sRow["AvgCollectedTested"]) && $sRow["AvgCollectedTested"] > 0 && $sRow["AvgCollectedTested"] != null) ? round($sRow["AvgCollectedTested"], 2) : 'null';
    $result['sampleReceivedDiff'][$j] = (isset($sRow["AvgCollectedReceived"]) && $sRow["AvgCollectedReceived"] > 0 && $sRow["AvgCollectedReceived"] != null) ? round($sRow["AvgCollectedReceived"], 2) : 'null';
    $result['sampleReceivedTested'][$j] = (isset($sRow["AvgReceivedTested"]) && $sRow["AvgReceivedTested"] > 0 && $sRow["AvgReceivedTested"] != null) ? round($sRow["AvgReceivedTested"], 2) : 'null';
    $result['sampleReceivedPrinted'][$j] = (isset($sRow["AvgCollectedPrinted"]) && $sRow["AvgCollectedPrinted"] > 0 && $sRow["AvgCollectedPrinted"] != null) ? round($sRow["AvgCollectedPrinted"], 2) : 'null';
    $result['date'][$j] = $sRow["monthDate"];
    $j++;
}
$sWhere = [];
$testReasonQuery = "SELECT count(vl.sample_code) AS total, tr.test_reason_name
                    from form_cd4 as vl
                    INNER JOIN r_cd4_test_reasons as tr ON vl.reason_for_cd4_testing = tr.test_reason_id
                    JOIN facility_details as f ON vl.facility_id=f.facility_id
                    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

$sWhere[] = ' vl.reason_for_cd4_testing IS NOT NULL ';
if (!empty($whereCondition))
    $sWhere[] = $whereCondition;
if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) != '') {
    $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}
if (!empty($_POST['sampleCollectionDate'])) {
    $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
}
if (isset($_POST['sampleReceivedDateAtLab']) && trim((string) $_POST['sampleReceivedDateAtLab']) != '') {
    $sWhere[] = " DATE(vl.sample_received_at_lab_datetime) BETWEEN '$labStartDate' AND '$labEndDate'";
}
if (isset($_POST['sampleTestedDate']) && trim((string) $_POST['sampleTestedDate']) != '') {
    $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $testedStartDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $testedEndDate . '"';
}
if (!empty($_POST['labName'])) {
    $sWhere[] = ' vl.lab_id = ' . $_POST['labName'];
}
if (!empty($sWhere)) {
    $testReasonQuery .= ' where ' . implode(" AND ", $sWhere);
}
$testReasonQuery = $testReasonQuery . " GROUP BY tr.test_reason_name";
$testReasonResult = $db->rawQuery($testReasonQuery);
// echo "<pre>";print_r($testReasonResult);die;
?>
<div class="col-xs-12">
    <div class="box">
        <div class="box-body">
            <div id="cd4SampleStatusOverviewContainer" style="float:left;width:100%; margin: 0 auto;"></div>
        </div>
    </div>
    <div class="box">
        <div class="box-body">
            <div id="cd4TestReasonContainer" style="float:left;width:100%; margin: 0 auto;"></div>
        </div>
    </div>
    <div class="box">
        <div class="box-body">
            <div id="cd4SamplesOverview" style="float:right;width:100%;margin: 0 auto;"></div>
        </div>
    </div>
</div>
<div class="col-xs-12 labAverageTatDiv">
    <div class="box">
        <div class="box-body">
            <div id="cd4LabAverageTat" style="padding:15px 0px 5px 0px;float:left;width:100%;"></div>
        </div>
    </div>
</div>
<script>
    <?php
    if (!empty($tResult)) {
    ?>
        $('#cd4SampleStatusOverviewContainer').highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: "<?php echo _translate("CD4 Samples Status Overview"); ?>"
            },
            credits: {
                enabled: false
            },
            tooltip: {
                pointFormat: "<?php echo _translate("CD4 Samples"); ?> :<strong>{point.y}</strong>"
            },
            plotOptions: {
                pie: {
                    size: '100%',
                    allowPointSelect: true,
                    cursor: 'pointer',
                    dataLabels: {
                        enabled: true,
                        useHTML: true,
                        format: '<div style="padding-bottom:10px;"><strong>{point.name}</strong>: {point.y}</div>',
                        style: {

                            //crop:false,
                            //overflow:'none',
                            color: (Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black'
                        },
                        distance: 10
                    },
                    showInLegend: true
                }
            },
            series: [{
                colorByPoint: false,
                point: {
                    events: {
                        click: function(e) {
                            //console.log(e.point.url);
                            window.open(e.point.url, '_blank');
                            e.preventDefault();
                        }
                    }
                },
                data: [
                    <?php
                    foreach ($tResult as $tRow) {
                    ?> {
                            name: '<?php echo ($tRow['status_name']); ?>',
                            y: <?php echo ($tRow['total']); ?>,
                            color: '<?php echo $sampleStatusColors[$tRow['status_id']]; ?>',
                            url: '/dashboard/vlTestResultStatus.php?id=<?php echo base64_encode((string) $tRow['status_id']); ?>&d=<?php echo base64_encode((string) $_POST['sampleCollectionDate']); ?>'
                        },
                    <?php
                    }
                    ?>
                ]
            }]
        });

    <?php

    }

    if (isset($vlSuppressionResult) && (isset($vlSuppressionResult['positiveResult']) || isset($vlSuppressionResult['negativeResult']) || isset($vlSuppressionResult['rejectedResult']))) {

    ?>
        Highcharts.setOptions({
            colors: ['#FF0000', '#50B432', '#ada99c']
        });
        $('#cd4SamplesOverview').highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: "<?php echo _translate("CD4 Results"); ?>"
            },
            credits: {
                enabled: false
            },
            tooltip: {
                pointFormat: "<?php echo _translate("Samples"); ?> :<strong>{point.y}</strong>"
            },
            plotOptions: {
                pie: {
                    size: '100%',
                    allowPointSelect: true,
                    cursor: 'pointer',
                    dataLabels: {
                        enabled: true,
                        useHTML: true,
                        format: '<div style="padding-bottom:10px;"><strong>{point.name}</strong>: {point.y}</div>',
                        style: {
                            color: (Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black'
                        },
                        distance: 10
                    },
                    showInLegend: true
                }
            },
            series: [{
                colorByPoint: true,
                data: [{
                        name: "<?php echo _translate("Positive"); ?>",
                        y: <?php echo (isset($vlSuppressionResult['positiveResult']) && $vlSuppressionResult['positiveResult'] > 0) > 0 ? $vlSuppressionResult['positiveResult'] : 0; ?>
                    },
                    {
                        name: "<?php echo _translate("Negative"); ?>",
                        y: <?php echo (isset($vlSuppressionResult['negativeResult']) && $vlSuppressionResult['negativeResult'] > 0) > 0 ? $vlSuppressionResult['negativeResult'] : 0; ?>
                    },
                    {
                        name: "<?php echo _translate("Rejected"); ?>",
                        y: <?php echo (isset($vlSuppressionResult['rejectedResult']) && $vlSuppressionResult['rejectedResult'] > 0) > 0 ? $vlSuppressionResult['rejectedResult'] : 0; ?>
                    },
                ]
            }]
        });
    <?php
    }
    if (!empty($result)) {
    ?>
        $('#cd4LabAverageTat').highcharts({
            chart: {
                type: 'line'
            },
            title: {
                text: "<?php echo _translate("CD4 Laboratory Turnaround Time"); ?>"
            },
            exporting: {
                chartOptions: {
                    subtitle: {
                        text: "<?php echo _translate("CD4 Laboratory Turnaround Time"); ?>",
                    }
                }
            },
            credits: {
                enabled: false
            },
            xAxis: {
                //categories: ["21 Mar", "22 Mar", "23 Mar", "24 Mar", "25 Mar", "26 Mar", "27 Mar"]
                categories: [<?php
                                if (!empty($result['date'])) {
                                    foreach ($result['date'] as $date) {
                                        echo "'" . $date . "',";
                                    }
                                }
                                ?>]
            },
            yAxis: [{
                title: {
                    text: "<?php echo _translate("Average TAT in Days"); ?>"
                },
                labels: {
                    formatter: function() {
                        return this.value;
                    }
                }
            }, { // Secondary yAxis
                gridLineWidth: 0,
                title: {
                    text: "<?php echo _translate("No. of Tests"); ?>"
                },
                labels: {
                    format: '{value}'
                },
                opposite: true
            }],
            plotOptions: {
                line: {
                    dataLabels: {
                        enabled: true
                    },
                    cursor: 'pointer',
                    point: {
                        events: {
                            click: function(e) {
                                //doLabTATRedirect(e.point.category);
                            }
                        }
                    }
                },
                series: {
                    dataLabels: {
                        enabled: true
                    }
                }
            },

            series: [{
                    type: 'column',
                    name: "<?php echo _translate("No. of Samples Tested"); ?>",
                    data: [<?php echo implode(",", $result['totalSamples']); ?>],
                    color: '#7CB5ED',
                    yAxis: 1
                },
                <?php
                if (isset($result['AvgTestedPrinted'])) {
                ?> {
                        connectNulls: false,
                        showInLegend: true,
                        name: "<?php echo _translate("Tested - Printed"); ?>",
                        data: [<?php echo implode(",", $result['AvgTestedPrinted']); ?>],
                        color: '#0f3f6e',
                    },
                <?php
                }
                if (isset($result['sampleReceivedDiff'])) {
                ?> {
                        connectNulls: false,
                        showInLegend: true,
                        name: "<?php echo _translate("Collected - Received at Lab"); ?>",
                        data: [<?php echo implode(",", $result['sampleReceivedDiff']); ?>],
                        color: '#edb47c',
                    },
                <?php
                }
                if (isset($result['sampleReceivedTested'])) {
                ?> {
                        connectNulls: false,
                        showInLegend: true,
                        name: "<?php echo _translate("Received - Tested"); ?>",
                        data: [<?php echo implode(",", $result['sampleReceivedTested']); ?>],
                        color: '#0f3f6e',
                    },
                <?php
                }
                if (isset($result['sampleTestedDiff'])) {
                ?> {
                        connectNulls: false,
                        showInLegend: true,
                        name: "<?php echo _translate("Collected - Tested"); ?>",
                        data: [<?php echo implode(",", $result['sampleTestedDiff']); ?>],
                        color: '#ed7c7d',
                    },
                <?php
                }
                if (isset($result['sampleReceivedPrinted'])) {
                ?> {
                        connectNulls: false,
                        showInLegend: true,
                        name: "<?php echo _translate("Collected - Printed"); ?>",
                        data: [<?php echo implode(",", $result['sampleReceivedPrinted']); ?>],
                        color: '#000',
                    },
                <?php
                }
                ?>
            ],
        });
    <?php }
    if (!empty($testReasonResult)) { ?>
        $('#cd4TestReasonContainer').highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: "<?php echo _translate("CD4 Test Reasons"); ?>"
            },
            credits: {
                enabled: false
            },
            tooltip: {
                pointFormat: "<?php echo _translate("Test Reasons"); ?> :<strong>{point.y}</strong>"
            },
            plotOptions: {
                pie: {
                    size: '100%',
                    allowPointSelect: true,
                    cursor: 'pointer',
                    dataLabels: {
                        enabled: true,
                        useHTML: true,
                        format: '<div style="padding-bottom:10px;"><strong>{point.name}</strong>: {point.y}</div>',
                        style: {

                            //crop:false,
                            //overflow:'none',
                            color: (Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black'
                        },
                        distance: 10
                    },
                    showInLegend: true
                }
            },
            series: [{
                colorByPoint: false,
                point: {
                    /* events: {
                        click: function(e) {
                            //console.log(e.point.url);
                            window.open(e.point.url, '_blank');
                            e.preventDefault();
                        }
                    } */
                },
                data: [
                    <?php
                    foreach ($testReasonResult as $tRow) {
                    ?> {
                            name: '<?= ($tRow['test_reason_name']); ?>',
                            y: <?= ($tRow['total']); ?>,
                            color: '#<?php echo MiscUtility::randomHexColor(); ?>',
                        },
                    <?php
                    }
                    ?>
                ]
            }]
        });
    <?php } ?>
</script>