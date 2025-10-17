<?php

use App\Utilities\DateUtility;
use App\Registries\AppRegistry;
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Registries\ContainerRegistry;

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

// Sanitized values from $request object
/** @var Laminas\Diactoros\ServerRequest $request */
$request = AppRegistry::get('request');
$_POST = _sanitizeInput($request->getParsedBody());

$whereConditionArray = [];
$whereConditionArray[] = " vl.result_status != " . SAMPLE_STATUS\CANCELLED;
if (!empty($_SESSION['facilityMap'])) {
    $whereConditionArray[] = " vl.facility_id IN (" . $_SESSION['facilityMap'] . ")";
}

$whereCondition = implode(" AND ", $whereConditionArray);

if (isset($_POST['type']) && trim((string) $_POST['type']) == 'recency') {
    $recencyWhere = " reason_for_vl_testing = 9999 ";
    $sampleStatusOverviewContainer = "recencySampleStatusOverviewContainer";
    $samplesVlOverview = "recencySmplesVlOverview";
    $samplesResultview = "recencySampleResultView";
    $labAverageTat = "recencyLabAverageTat";
} else {
    $recencyWhere = " IFNULL(reason_for_vl_testing, 0) != 9999 ";
    $sampleStatusOverviewContainer = "vlSampleStatusOverviewContainer";
    $samplesVlOverview = "vlSmplesVlOverview";
    $samplesResultview = "vlSampleResultView";
    $labAverageTat = "vlLabAverageTat";
}

$table = "form_vl";
$highVL = "High Viral Load";
$lowVL = "Low Viral Load";
$suppression = "VL Suppression";

$tsQuery = "SELECT * FROM `r_sample_status` ORDER BY `status_id`";
$tsResult = $db->rawQuery($tsQuery);

$sampleStatusColors = [];

$sampleStatusColors[1] = "#dda41b"; // HOLD
$sampleStatusColors[2] = "#9a1c64"; // LOST
$sampleStatusColors[3] = "#c5c5c5ff"; // Sample Reordered
$sampleStatusColors[4] = "#d8424d"; // Rejected
$sampleStatusColors[5] = "#000000"; // Invalid
$sampleStatusColors[6] = "#e2d44b"; // Sample Received at lab
$sampleStatusColors[7] = "#639e11"; // Accepted
$sampleStatusColors[8] = "#7f22e8"; // Sent to Lab
$sampleStatusColors[9] = "#4BC0D9"; // Sample Registered at Health Center
$sampleStatusColors[10] = "#f0ad4e"; // NO_RESULT
$sampleStatusColors[11] = "#20c997"; // CANCELLED

//date
[$start_date, $end_date] = DateUtility::convertDateRange($_POST['sampleCollectionDate'] ?? '');

[$labStartDate, $labEndDate] = DateUtility::convertDateRange($_POST['sampleReceivedDateAtLab'] ?? '');

[$testedStartDate, $testedEndDate] = DateUtility::convertDateRange($_POST['sampleTestedDate'] ?? '');

$sWhere = [];
$tQuery = "SELECT COUNT(vl_sample_id) as total,
                result_status,
                status_id,
                status_name
            FROM $table as vl
            LEFT JOIN r_sample_status as ts ON ts.status_id=vl.result_status
            LEFT JOIN facility_details as f ON vl.lab_id=f.facility_id
            LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";
//filter

if (!empty($whereCondition)) {
    $sWhere[] = $whereCondition;
}
$sWhere[] = $recencyWhere;
if (!$general->isSTSInstance()) {
    $sWhere[] = ' result_status != ' . SAMPLE_STATUS\RECEIVED_AT_CLINIC;
}
if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) != '') {
    $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}
if (!empty($_POST['sampleCollectionDate'])) {
    $sWhere[] = " DATE(vl.sample_collection_date) BETWEEN '$start_date' AND '$end_date'";
}
if (isset($_POST['sampleReceivedDateAtLab']) && trim((string) $_POST['sampleReceivedDateAtLab']) != '') {
    $sWhere[] = " DATE(vl.sample_received_at_lab_datetime) BETWEEN '$labStartDate' AND '$labEndDate'";
}
if (isset($_POST['sampleTestedDate']) && trim((string) $_POST['sampleTestedDate']) != '') {
    $sWhere[] = " DATE(vl.sample_tested_datetime) BETWEEN '$testedStartDate' AND '$testedEndDate'";
}
if (!empty($_POST['labName'])) {
    $sWhere[] = ' vl.lab_id = ' . $_POST['labName'];
}

if (!empty($sWhere)) {
    $tQuery .= " WHERE " . implode(" AND ", $sWhere);
}
$tQuery .= " GROUP BY vl.result_status ORDER BY status_id";

$tResult = $db->rawQuery($tQuery);

$sWhere = [];
$vlSuppressionQuery = "SELECT COUNT(vl_sample_id) as total,
        SUM(CASE
                WHEN (IFNULL(vl.vl_result_category, '') like 'not suppressed') THEN 1
                    ELSE 0
                END) AS highVL,
        (SUM(CASE
                WHEN (IFNULL(vl.vl_result_category, '') like 'suppressed') THEN 1
                    ELSE 0
                END)) AS lowVL

        FROM $table as vl
        LEFT JOIN facility_details as f ON vl.lab_id=f.facility_id
        LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id ";

if (!empty($whereCondition)) {
    $sWhere[] = $whereCondition;
}


$sWhere[] = $recencyWhere;
$sWhere[] = " IFNULL(vl.result_status, 0) = 7 ";
if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) != '') {
    $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}
if (!empty($_POST['sampleCollectionDate'])) {
    $sWhere[] = " DATE(vl.sample_collection_date) BETWEEN '$start_date'AND '$end_date'";
}
if (isset($_POST['sampleReceivedDateAtLab']) && trim((string) $_POST['sampleReceivedDateAtLab']) != '') {
    $sWhere[] = " DATE(vl.sample_received_at_lab_datetime) BETWEEN '$labStartDate' AND '$labEndDate'";
}
if (isset($_POST['sampleTestedDate']) && trim((string) $_POST['sampleTestedDate']) != '') {
    $sWhere[] = " DATE(vl.sample_tested_datetime) BETWEEN '$testedStartDate' AND '$testedEndDate'";
}
if (!empty($_POST['labName'])) {
    $sWhere[] = ' vl.lab_id = ' . $_POST['labName'];
}
if (!empty($sWhere)) {
    $vlSuppressionQuery .= " WHERE " . implode(" AND ", $sWhere);
}


$vlSuppressionResult = $db->rawQueryOne($vlSuppressionQuery);



$sWhere = [];
/** Get results that are not blank */
$sampleResultQuery = "SELECT
            SUM(CASE WHEN vl.result REGEXP '^-?[0-9]+$' THEN 1 ELSE 0 END) AS numberValue,
            SUM(CASE WHEN vl.result like 'TND' OR vl.result like 'Target Not Detected' OR vl.result like 'Below Detection Level' OR vl.result like 'HIV-1 Not Detected' THEN 1 ELSE 0 END) AS charValue
            FROM $table as vl
            LEFT JOIN facility_details as f ON vl.lab_id=f.facility_id
            LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id ";



if (isset($_POST['batchCode']) && trim((string) $_POST['batchCode']) != '') {
    $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}
if (!empty($_POST['sampleCollectionDate'])) {
    $sWhere[] = " DATE(vl.sample_collection_date) BETWEEN '$start_date'AND '$end_date'";
}
if (isset($_POST['sampleReceivedDateAtLab']) && trim((string) $_POST['sampleReceivedDateAtLab']) != '') {
    $sWhere[] = " DATE(vl.sample_received_at_lab_datetime) BETWEEN '$labStartDate' AND '$labEndDate'";
}
if (isset($_POST['sampleTestedDate']) && trim((string) $_POST['sampleTestedDate']) != '') {
    $sWhere[] = " DATE(vl.sample_tested_datetime) BETWEEN '$testedStartDate' AND '$testedEndDate'";
}
if (!empty($_POST['labName'])) {
    $sWhere[] = ' vl.lab_id = ' . $_POST['labName'];
}
if (!empty($sWhere)) {
    $sampleResultQuery .= " WHERE " . implode(" AND ", $sWhere);
}

$sampleResultQueryResult = $db->rawQueryOne($sampleResultQuery);

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

$sWhere = [];
$tatSampleQuery = "SELECT
    COUNT(DISTINCT vl.unique_id) AS totalSamples,
    COUNT(DISTINCT CASE WHEN vl.sample_collection_date BETWEEN '$tatStartDate' AND '$tatEndDate' THEN vl.unique_id END) AS numberCollected,
    COUNT(DISTINCT CASE WHEN vl.sample_tested_datetime BETWEEN '$tatStartDate' AND '$tatEndDate' THEN vl.unique_id END) AS numberTested,
    COUNT(DISTINCT CASE WHEN vl.sample_received_at_lab_datetime BETWEEN '$tatStartDate' AND '$tatEndDate' THEN vl.unique_id END) AS numberReceived,

    DATE_FORMAT(DATE(vl.sample_tested_datetime), '%b-%Y') AS monthDate,

    ROUND(AVG(GREATEST(TIMESTAMPDIFF(DAY, vl.sample_collection_date, vl.sample_tested_datetime), 0)), 2) AS AvgCollectedTested,
    ROUND(AVG(GREATEST(TIMESTAMPDIFF(DAY, vl.sample_collection_date, vl.sample_received_at_lab_datetime), 0)), 2) AS AvgCollectedReceived,
    ROUND(AVG(GREATEST(TIMESTAMPDIFF(DAY, vl.sample_received_at_lab_datetime, vl.sample_tested_datetime), 0)), 2) AS AvgReceivedTested,
    ROUND(AVG(GREATEST(TIMESTAMPDIFF(DAY, vl.sample_collection_date, vl.result_printed_datetime), 0)), 2) AS AvgCollectedPrinted,
    ROUND(AVG(GREATEST(TIMESTAMPDIFF(DAY, vl.sample_tested_datetime, vl.result_printed_datetime), 0)), 2) AS AvgTestedPrinted,
    ROUND(AVG(GREATEST(TIMESTAMPDIFF(DAY, vl.result_printed_on_lis_datetime, vl.result_printed_on_sts_datetime), 0)), 2) AS AvgTestedPrintedFirstTime

    FROM `$table` AS vl
    LEFT JOIN facility_details AS f ON vl.lab_id = f.facility_id
    LEFT JOIN r_vl_sample_type AS s ON s.sample_id = vl.specimen_type

    WHERE
        vl.result IS NOT NULL AND vl.result != '' AND
        DATE(vl.sample_tested_datetime) BETWEEN '$tatStartDate' AND '$tatEndDate'
";




if (!empty($whereCondition)) {
    $sWhere[] = $whereCondition;
}

$sWhere[] = $recencyWhere;
if (isset($_POST['sampleType']) && trim((string) $_POST['sampleType']) != '') {
    $sWhere[] = ' s.sample_id = "' . $_POST['sampleType'] . '"';
}

if (!empty($_POST['labName'])) {
    $sWhere[] = ' vl.lab_id = ' . $_POST['labName'];
}

if (!empty($sWhere)) {
    $tatSampleQuery .= " AND " . implode(" AND ", $sWhere);
}
$tatSampleQuery .= " GROUP BY monthDate ORDER BY sample_tested_datetime ";

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
    $result['AvgTestedPrintedFirstTime'][$j] = (isset($sRow["AvgTestedPrintedFirstTime"]) && $sRow["AvgTestedPrintedFirstTime"] > 0 && $sRow["AvgTestedPrintedFirstTime"] != null) ? $sRow["AvgTestedPrintedFirstTime"] : 'null';
    $result['sampleTestedDiff'][$j] = (isset($sRow["AvgCollectedTested"]) && $sRow["AvgCollectedTested"] > 0 && $sRow["AvgCollectedTested"] != null) ? round($sRow["AvgCollectedTested"], 2) : 'null';
    $result['sampleReceivedDiff'][$j] = (isset($sRow["AvgCollectedReceived"]) && $sRow["AvgCollectedReceived"] > 0 && $sRow["AvgCollectedReceived"] != null) ? round($sRow["AvgCollectedReceived"], 2) : 'null';
    $result['sampleReceivedTested'][$j] = (isset($sRow["AvgReceivedTested"]) && $sRow["AvgReceivedTested"] > 0 && $sRow["AvgReceivedTested"] != null) ? round($sRow["AvgReceivedTested"], 2) : 'null';
    $result['sampleReceivedPrinted'][$j] = (isset($sRow["AvgCollectedPrinted"]) && $sRow["AvgCollectedPrinted"] > 0 && $sRow["AvgCollectedPrinted"] != null) ? round($sRow["AvgCollectedPrinted"], 2) : 'null';
    $result['date'][$j] = $sRow["monthDate"];
    $j++;
}

?>
<div class="col-xs-12">
    <div class="box">
        <div class="box-body">
            <div id="<?php echo $sampleStatusOverviewContainer; ?>" style="float:left;width:100%; margin: 0 auto;">
            </div>
        </div>
    </div>
    <div class="box">
        <div class="box-body">
            <div id="<?php echo $samplesVlOverview; ?>" style="float:right;width:100%;margin: 0 auto;"></div>
        </div>
    </div>
    <div class="box">
        <div class="box-body">
            <div id="<?php echo $samplesResultview; ?>" style="float:right;width:100%;margin: 0 auto;"></div>
        </div>
    </div>
</div>
</div>
<div class="col-xs-12 labAverageTatDiv">
    <div class="box">
        <div class="box-body">
            <div id="<?php echo $labAverageTat; ?>" style="padding:15px 0px 5px 0px;float:left;width:100%;"></div>
        </div>
    </div>
</div>
<script>
    <?php
    if (!empty($tResult)) {
        $total = 0;
    ?>
        var _value = [
            <?php foreach ($tResult as $tRow) {
                $total += $tRow['total']; ?> {
                    name: '<?php echo ($tRow['status_name']); ?>',
                    y: <?php echo ($tRow['total']); ?>,
                    color: '<?php echo $sampleStatusColors[$tRow['status_id']]; ?>',
                    url: '/dashboard/vlTestResultStatus.php?id=<?php echo base64_encode((string) $tRow['status_id']); ?>&d=<?php echo base64_encode((string) $_POST['sampleCollectionDate']); ?>'
                },
            <?php } ?>
        ];
        $('#<?php echo $sampleStatusOverviewContainer; ?>').highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: "<?php echo _translate("Samples Status Overview (N = " . $total . ")"); ?>"
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
                data: _value
            }]
        });

    <?php

    }

    if (isset($vlSuppressionResult) && (isset($vlSuppressionResult['highVL']) || isset($vlSuppressionResult['lowVL']))) {

    ?>
        Highcharts.setOptions({
            colors: ['#FF0000', '#50B432']
        });
        $('#<?php echo $samplesVlOverview; ?>').highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: "<?php echo _translate("VL Suppression (N = " . ($vlSuppressionResult['highVL'] + $vlSuppressionResult['lowVL']) . ")"); ?>"
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
                        name: '<?php echo $highVL; ?>',
                        y: <?php echo (isset($vlSuppressionResult['highVL']) && $vlSuppressionResult['highVL'] > 0) > 0 ? $vlSuppressionResult['highVL'] : 0; ?>
                    },
                    {
                        name: '<?php echo $lowVL; ?>',
                        y: <?php echo (isset($vlSuppressionResult['lowVL']) && $vlSuppressionResult['lowVL'] > 0) > 0 ? $vlSuppressionResult['lowVL'] : 0; ?>
                    },
                ]
            }]
        });
    <?php
    }

    /* For new pie chart for not blank results */
    if (!empty($sampleResultQueryResult) && ($sampleResultQueryResult['numberValue'] + $sampleResultQueryResult['charValue']) > 0) {
    ?>
        Highcharts.setOptions({
            colors: ['#7CB5ED', '#808080']
        });
        $('#<?php echo $samplesResultview; ?>').highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: "<?php echo _translate("HIV Viral Load Detection (N = " . ($sampleResultQueryResult['numberValue'] + $sampleResultQueryResult['charValue']) . ")", escapeTextOrContext: true); ?>"
            },
            credits: {
                enabled: false
            },
            tooltip: {
                pointFormat: "<?php echo _translate("Viral Load Detection", escapeTextOrContext: true); ?> :<strong>{point.y}</strong>"
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
                        name: '<?php echo "Viral Load Detected"; ?>',
                        y: <?php echo (isset($sampleResultQueryResult['numberValue']) && $sampleResultQueryResult['numberValue'] > 0) > 0 ? $sampleResultQueryResult['numberValue'] : 0; ?>
                    },
                    {
                        name: '<?php echo "Viral Load Not Detected"; ?>',
                        y: <?php echo (isset($sampleResultQueryResult['charValue']) && $sampleResultQueryResult['charValue'] > 0) > 0 ? $sampleResultQueryResult['charValue'] : 0; ?>
                    },
                ]
            }]
        });
    <?php
    } else { ?>
        $('#<?php echo $samplesResultview; ?>').hide();
    <?php }
    if (!empty($result)) {
    ?>
        $('#<?php echo $labAverageTat; ?>').highcharts({
            chart: {
                type: 'line'
            },
            title: {
                text: "<?php echo _translate("Laboratory Turnaround Time", escapeTextOrContext: true); ?>"
            },
            exporting: {
                chartOptions: {
                    subtitle: {
                        text: "<?php echo _translate("Laboratory Turnaround Time", escapeTextOrContext: true); ?>",
                    }
                },
                sourceWidth: 1200,
                sourceHeight: 600
            },
            credits: {
                enabled: false
            },
            xAxis: {
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
                    text: "<?php echo _translate("Average TAT in Days", escapeTextOrContext: true); ?>"
                },
                labels: {
                    formatter: function() {
                        return this.value;
                    }
                }
            }, { // Secondary yAxis
                gridLineWidth: 0,
                title: {
                    text: "<?php echo _translate("No. of Tests", escapeTextOrContext: true); ?>"
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
                    name: "<?php echo _translate("No. of Samples Tested", escapeTextOrContext: true); ?>",
                    data: [<?php echo implode(",", $result['totalSamples']); ?>],
                    color: '#7CB5ED',
                    yAxis: 1
                },
                <?php
                if (isset($result['AvgTestedPrinted'])) {
                ?> {
                        connectNulls: false,
                        showInLegend: true,
                        name: "<?php echo _translate("Tested - Printed", escapeTextOrContext: true); ?>",
                        data: [<?php echo implode(",", $result['AvgTestedPrinted']); ?>],
                        color: '#0f3f6e',
                    },
                <?php
                }
                if (isset($result['sampleReceivedDiff'])) {
                ?> {
                        connectNulls: false,
                        showInLegend: true,
                        name: "<?php echo _translate("Collected - Received at Lab", escapeTextOrContext: true); ?>",
                        data: [<?php echo implode(",", $result['sampleReceivedDiff']); ?>],
                        color: '#edb47c',
                    },
                <?php
                }
                if (isset($result['sampleReceivedTested'])) {
                ?> {
                        connectNulls: false,
                        showInLegend: true,
                        name: "<?php echo _translate("Received - Tested", escapeTextOrContext: true); ?>",
                        data: [<?php echo implode(",", $result['sampleReceivedTested']); ?>],
                        color: '#0f3f6e',
                    },
                <?php
                }
                if (isset($result['sampleTestedDiff'])) {
                ?> {
                        connectNulls: false,
                        showInLegend: true,
                        name: "<?php echo _translate("Collected - Tested", escapeTextOrContext: true); ?>",
                        data: [<?php echo implode(",", $result['sampleTestedDiff']); ?>],
                        color: '#ed7c7d',
                    },
                <?php
                }
                if (isset($result['sampleReceivedPrinted'])) {
                ?> {
                        connectNulls: false,
                        showInLegend: true,
                        name: "<?php echo _translate("Collected - Printed", escapeTextOrContext: true); ?>",
                        data: [<?php echo implode(",", $result['sampleReceivedPrinted']); ?>],
                        color: '#000',
                    },
                <?php
                }
                if (isset($result['AvgTestedPrintedFirstTime'])) {
                ?> {
                        connectNulls: false,
                        showInLegend: true,
                        name: "<?php echo _translate("Collected - Printed First Time", escapeTextOrContext: true); ?>",
                        data: [<?php echo implode(",", $result['AvgTestedPrintedFirstTime']); ?>],
                        color: '#000',
                    },
                <?php
                }
                ?>
            ],
            exporting: {
                sourceWidth: 1200,
                sourceHeight: 600,
                scale: 10
            }
        });
    <?php } ?>
</script>