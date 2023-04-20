<?php

use App\Models\General;
use App\Utilities\DateUtils;
use App\Utilities\MiscUtils;





$general = new General();
$whereCondition = '';
$configFormQuery = "SELECT * FROM global_config WHERE `name` ='vl_form'";
$configFormResult = $db->rawQuery($configFormQuery);

$systemType = $general->getSystemConfig('sc_user_type');

$whereCondition = '';

if ($systemType == 'remoteuser') {
    $userfacilityMapQuery = "SELECT GROUP_CONCAT(DISTINCT `facility_id` ORDER BY `facility_id` SEPARATOR ',') as `facility_id` FROM user_facility_map WHERE user_id='" . $_SESSION['userId'] . "'";
    $userfacilityMapresult = $db->rawQuery($userfacilityMapQuery);
    if ($userfacilityMapresult[0]['facility_id'] != null && $userfacilityMapresult[0]['facility_id'] != '') {
        $userfacilityMapresult[0]['facility_id'] = rtrim($userfacilityMapresult[0]['facility_id'], ",");
        $whereCondition = " AND vl.facility_id IN (" . $userfacilityMapresult[0]['facility_id'] . ")";
    }
}

$tsQuery = "SELECT * FROM `r_sample_status` ORDER BY `status_id`";
$tsResult = $db->rawQuery($tsQuery);
// $sampleStatusArray = [];
// foreach($tsResult as $tsRow){
//     $sampleStatusArray = $tsRow['status_name'];
// }

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
$start_date = '';
$end_date = '';

if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
    $s_c_date = explode("to", $_POST['sampleCollectionDate']);
    //print_r($s_c_date);die;
    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $start_date = DateUtils::isoDateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $end_date = DateUtils::isoDateFormat(trim($s_c_date[1]));
    }
}

$labStartDate = '';
$labEndDate = '';
if (isset($_POST['sampleReceivedDateAtLab']) && trim($_POST['sampleReceivedDateAtLab']) != '') {
    $s_c_date = explode("to", $_POST['sampleReceivedDateAtLab']);
    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $labStartDate = DateUtils::isoDateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $labEndDate = DateUtils::isoDateFormat(trim($s_c_date[1]));
    }
}

$testedStartDate = '';
$testedEndDate = '';
if (isset($_POST['sampleTestedDate']) && trim($_POST['sampleTestedDate']) != '') {
    $s_c_date = explode("to", $_POST['sampleTestedDate']);
    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $testedStartDate = DateUtils::isoDateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $testedEndDate = DateUtils::isoDateFormat(trim($s_c_date[1]));
    }
}
$tQuery = "SELECT COUNT(tb_id) as total,status_id,status_name 
                FROM form_tb as vl 
                JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
                JOIN facility_details as f ON vl.lab_id=f.facility_id 
                LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";
//filter
$sWhere = [];
if (!empty($whereCondition))
    $sWhere[] = $whereCondition;
if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != '') {
    $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
    $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
}
if (isset($_POST['sampleReceivedDateAtLab']) && trim($_POST['sampleReceivedDateAtLab']) != '') {
    $sWhere[] = ' DATE(vl.sample_received_at_lab_datetime) >= "' . $labStartDate . '" AND DATE(vl.sample_received_at_lab_datetime) <= "' . $labEndDate . '"';
}
if (isset($_POST['sampleTestedDate']) && trim($_POST['sampleTestedDate']) != '') {
    $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $testedStartDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $testedEndDate . '"';
}
if (!empty($_POST['labName'])) {
    $sWhere[] = ' vl.lab_id = ' . $_POST['labName'];
}
if (isset($sWhere) && !empty($sWhere)) {
    $tQuery .= " where " . implode(" AND ", $sWhere);
}
$tQuery .= " GROUP BY vl.result_status ORDER BY status_id";
// echo $tQuery;die;
$tResult = $db->rawQuery($tQuery);
//HVL and LVL Samples
$vlSuppressionQuery = "SELECT   COUNT(tb_id) as total,
                                SUM(CASE
                                        WHEN (vl.result = 'positive' and vl.result!='' and vl.result is not null) THEN 1
                                            ELSE 0
                                        END) AS positiveResult,
                                (SUM(CASE
                                        WHEN (vl.result = 'negative' and vl.result!='' and vl.result is not null) THEN 1
                                            ELSE 0
                                        END)) AS negativeResult,
                                (SUM(CASE
                                        WHEN (vl.is_sample_rejected = 'yes' ) THEN 1
                                            ELSE 0
                                        END)) AS rejectedResult,
                                status_id,
                                status_name 
                                
                                FROM form_tb as vl INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status JOIN facility_details as f ON vl.lab_id=f.facility_id LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id ";

// $sWhere = " AND (vl.result!='' and vl.result is not null) ";
$sWhere = [];
if (!empty($whereCondition))
    $sWhere[] = $whereCondition;
if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != '') {
    $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
    $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
}
if (isset($_POST['sampleReceivedDateAtLab']) && trim($_POST['sampleReceivedDateAtLab']) != '') {
    $sWhere[] = ' DATE(vl.sample_received_at_lab_datetime) >= "' . $labStartDate . '" AND DATE(vl.sample_received_at_lab_datetime) <= "' . $labEndDate . '"';
}
if (isset($_POST['sampleTestedDate']) && trim($_POST['sampleTestedDate']) != '') {
    $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $testedStartDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $testedEndDate . '"';
}
if (isset($_POST['sampleType']) && trim($_POST['sampleType']) != '') {
    $sWhere[] = ' s.sample_id = "' . $_POST['sampleType'] . '"';
}
if (!empty($_POST['labName'])) {
    $sWhere[] = ' vl.lab_id = ' . $_POST['labName'];
}
if (isset($sWhere) && !empty($sWhere)) {
    $vlSuppressionQuery .= " where " . implode(" AND ", $sWhere);
}
$vlSuppressionResult = $db->rawQueryOne($vlSuppressionQuery);

//get LAB TAT
if ($start_date == '' && $end_date == '') {
    $date = strtotime(date('Y-m-d') . ' -1 year');
    $start_date = date('Y-m-d', $date);
    $end_date = date('Y-m-d');
}
$tatSampleQuery = "SELECT 
    count(*) as 'totalSamples',
    DATE_FORMAT(DATE(sample_tested_datetime), '%b-%Y') as monthDate,
    CAST(ABS(AVG(TIMESTAMPDIFF(DAY,vl.sample_tested_datetime,vl.sample_collection_date))) AS DECIMAL (10,2)) as AvgTestedDiff,
    CAST(ABS(AVG(TIMESTAMPDIFF(DAY,vl.sample_received_at_lab_datetime,vl.sample_collection_date))) AS DECIMAL (10,2)) as AvgReceivedDiff,
    CAST(ABS(AVG(TIMESTAMPDIFF(DAY,vl.sample_tested_datetime,vl.sample_received_at_lab_datetime))) AS DECIMAL (10,2)) as AvgReceivedTested,
    CAST(ABS(AVG(TIMESTAMPDIFF(DAY,vl.result_printed_datetime,vl.sample_collection_date))) AS DECIMAL (10,2)) as AvgReceivedPrinted,
    CAST(ABS(AVG(TIMESTAMPDIFF(DAY,vl.sample_tested_datetime,vl.result_printed_datetime))) AS DECIMAL (10,2)) as AvgResultPrinted

    FROM form_tb as vl 
    INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
    JOIN facility_details as f ON vl.lab_id=f.facility_id 
    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id 
    
    WHERE 
    vl.result is not null
    AND vl.result != ''
    AND DATE(vl.sample_tested_datetime) >= '$start_date'
    AND DATE(vl.sample_tested_datetime) <= '$end_date'";
$sWhere = [];
if (!empty($whereCondition))
    $sWhere[] = $whereCondition;
if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != '') {
    $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}

if (!empty($_POST['labName'])) {
    $sWhere[] = ' vl.lab_id = ' . $_POST['labName'];
}

if (isset($sWhere) && !empty($sWhere)) {
    $tatSampleQuery .= " AND " . implode(" AND ", $sWhere);
}
$tatSampleQuery .= " GROUP BY monthDate";
//$tatSampleQuery .= " HAVING daydiff < 120";
$tatSampleQuery .= " ORDER BY sample_tested_datetime";
//echo $tatSampleQuery;
$tatResult = $db->rawQuery($tatSampleQuery);

$j = 0;
foreach ($tatResult as $sRow) {
    if ($sRow["monthDate"] == null) {
        continue;
    }

    $result['totalSamples'][$j] = (isset($sRow["totalSamples"]) && $sRow["totalSamples"] > 0 && $sRow["totalSamples"] != null) ? $sRow["totalSamples"] : 'null';
    $result['avgResultPrinted'][$j] = (isset($sRow["AvgResultPrinted"]) && $sRow["AvgResultPrinted"] > 0 && $sRow["AvgResultPrinted"] != null) ? $sRow["AvgResultPrinted"] : 'null';
    $result['sampleTestedDiff'][$j] = (isset($sRow["AvgTestedDiff"]) && $sRow["AvgTestedDiff"] > 0 && $sRow["AvgTestedDiff"] != null) ? round($sRow["AvgTestedDiff"], 2) : 'null';
    $result['sampleReceivedDiff'][$j] = (isset($sRow["AvgReceivedDiff"]) && $sRow["AvgReceivedDiff"] > 0 && $sRow["AvgReceivedDiff"] != null) ? round($sRow["AvgReceivedDiff"], 2) : 'null';
    $result['sampleReceivedTested'][$j] = (isset($sRow["AvgReceivedTested"]) && $sRow["AvgReceivedTested"] > 0 && $sRow["AvgReceivedTested"] != null) ? round($sRow["AvgReceivedTested"], 2) : 'null';
    $result['sampleReceivedPrinted'][$j] = (isset($sRow["AvgReceivedPrinted"]) && $sRow["AvgReceivedPrinted"] > 0 && $sRow["AvgReceivedPrinted"] != null) ? round($sRow["AvgReceivedPrinted"], 2) : 'null';
    $result['date'][$j] = $sRow["monthDate"];
    $j++;
}
$sWhere = [];
$testReasonQuery = "SELECT count(vl.sample_code) AS total, tr.test_reason_name 
                    from form_tb as vl 
                    INNER JOIN r_tb_test_reasons as tr ON vl.reason_for_tb_test = tr.test_reason_id 
                    JOIN facility_details as f ON vl.facility_id=f.facility_id 
                    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id";

$sWhere[] = ' vl.reason_for_tb_test IS NOT NULL ';
if (!empty($whereCondition))
    $sWhere[] = $whereCondition;
if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != '') {
    $sWhere[] = ' b.batch_code = "' . $_POST['batchCode'] . '"';
}
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
    $sWhere[] = ' DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
}
if (isset($_POST['sampleReceivedDateAtLab']) && trim($_POST['sampleReceivedDateAtLab']) != '') {
    $sWhere[] = ' DATE(vl.sample_received_at_lab_datetime) >= "' . $labStartDate . '" AND DATE(vl.sample_received_at_lab_datetime) <= "' . $labEndDate . '"';
}
if (isset($_POST['sampleTestedDate']) && trim($_POST['sampleTestedDate']) != '') {
    $sWhere[] = ' DATE(vl.sample_tested_datetime) >= "' . $testedStartDate . '" AND DATE(vl.sample_tested_datetime) <= "' . $testedEndDate . '"';
}
if (!empty($_POST['labName'])) {
    $sWhere[] = ' vl.lab_id = ' . $_POST['labName'];
}
if (isset($sWhere) && !empty($sWhere)) {
    $testReasonQuery .= ' where ' . implode(" AND ", $sWhere);
}
$testReasonQuery = $testReasonQuery . " GROUP BY tr.test_reason_name";
$testReasonResult = $db->rawQuery($testReasonQuery);
// echo "<pre>";print_r($testReasonResult);die;
?>
<div class="col-xs-12">
    <div class="box">
        <div class="box-body">
            <div id="tbSampleStatusOverviewContainer" style="float:left;width:100%; margin: 0 auto;"></div>
        </div>
    </div>
    <div class="box">
        <div class="box-body">
            <div id="tbTestReasonContainer" style="float:left;width:100%; margin: 0 auto;"></div>
        </div>
    </div>
    <div class="box">
        <div class="box-body">
            <div id="tbSamplesOverview" style="float:right;width:100%;margin: 0 auto;"></div>
        </div>
    </div>
</div>
</div>
<div class="col-xs-12 labAverageTatDiv">
    <div class="box">
        <div class="box-body">
            <div id="tbLabAverageTat" style="padding:15px 0px 5px 0px;float:left;width:100%;"></div>
        </div>
    </div>
</div>
<script>
    <?php
    if (isset($tResult) && count($tResult) > 0) {
    ?>
        $('#tbSampleStatusOverviewContainer').highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: "<?php echo _("TB Samples Status Overview"); ?>"
            },
            credits: {
                enabled: false
            },
            tooltip: {
                pointFormat: "<?php echo _("TB Samples"); ?> :<strong>{point.y}</strong>"
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
                            url: '/dashboard/vlTestResultStatus.php?id=<?php echo base64_encode($tRow['status_id']); ?>&d=<?php echo base64_encode($_POST['sampleCollectionDate']); ?>'
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
        $('#tbSamplesOverview').highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: "<?php echo _("TB Results"); ?>"
            },
            credits: {
                enabled: false
            },
            tooltip: {
                pointFormat: "<?php echo _("Samples"); ?> :<strong>{point.y}</strong>"
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
                        name: "<?php echo _("Positive"); ?>",
                        y: <?php echo (isset($vlSuppressionResult['positiveResult']) && $vlSuppressionResult['positiveResult'] > 0) > 0 ? $vlSuppressionResult['positiveResult'] : 0; ?>
                    },
                    {
                        name: "<?php echo _("Negative"); ?>",
                        y: <?php echo (isset($vlSuppressionResult['negativeResult']) && $vlSuppressionResult['negativeResult'] > 0) > 0 ? $vlSuppressionResult['negativeResult'] : 0; ?>
                    },
                    {
                        name: "<?php echo _("Rejected"); ?>",
                        y: <?php echo (isset($vlSuppressionResult['rejectedResult']) && $vlSuppressionResult['rejectedResult'] > 0) > 0 ? $vlSuppressionResult['rejectedResult'] : 0; ?>
                    },
                ]
            }]
        });
    <?php
    }
    if (isset($result) && count($result) > 0) {
    ?>
        $('#tbLabAverageTat').highcharts({
            chart: {
                type: 'line'
            },
            title: {
                text: "<?php echo _("TB Laboratory Turnaround Time"); ?>"
            },
            exporting: {
                chartOptions: {
                    subtitle: {
                        text: "<?php echo _("TB Laboratory Turnaround Time"); ?>",
                    }
                }
            },
            credits: {
                enabled: false
            },
            xAxis: {
                //categories: ["21 Mar", "22 Mar", "23 Mar", "24 Mar", "25 Mar", "26 Mar", "27 Mar"]
                categories: [<?php
                                if (isset($result['date']) && count($result['date']) > 0) {
                                    foreach ($result['date'] as $date) {
                                        echo "'" . $date . "',";
                                    }
                                }
                                ?>]
            },
            yAxis: [{
                title: {
                    text: "<?php echo _("Average TAT in Days"); ?>"
                },
                labels: {
                    formatter: function() {
                        return this.value;
                    }
                }
            }, { // Secondary yAxis
                gridLineWidth: 0,
                title: {
                    text: "<?php echo _("No. of Tests"); ?>"
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
                    name: "<?php echo _("No. of Samples Tested"); ?>",
                    data: [<?php echo implode(",", $result['totalSamples']); ?>],
                    color: '#7CB5ED',
                    yAxis: 1
                },
                <?php
                if (isset($result['avgResultPrinted'])) {
                ?> {
                        connectNulls: false,
                        showInLegend: true,
                        name: "<?php echo _("Result - Printed"); ?>",
                        data: [<?php echo implode(",", $result['avgResultPrinted']); ?>],
                        color: '#0f3f6e',
                    },
                <?php
                }
                if (isset($result['sampleReceivedDiff'])) {
                ?> {
                        connectNulls: false,
                        showInLegend: true,
                        name: "<?php echo _("Collected - Received at Lab"); ?>",
                        data: [<?php echo implode(",", $result['sampleReceivedDiff']); ?>],
                        color: '#edb47c',
                    },
                <?php
                }
                if (isset($result['sampleReceivedTested'])) {
                ?> {
                        connectNulls: false,
                        showInLegend: true,
                        name: "<?php echo _("Received - Tested"); ?>",
                        data: [<?php echo implode(",", $result['sampleReceivedTested']); ?>],
                        color: '#0f3f6e',
                    },
                <?php
                }
                if (isset($result['sampleTestedDiff'])) {
                ?> {
                        connectNulls: false,
                        showInLegend: true,
                        name: "<?php echo _("Collected - Tested"); ?>",
                        data: [<?php echo implode(",", $result['sampleTestedDiff']); ?>],
                        color: '#ed7c7d',
                    },
                <?php
                }
                if (isset($result['sampleReceivedPrinted'])) {
                ?> {
                        connectNulls: false,
                        showInLegend: true,
                        name: "<?php echo _("Collected - Printed"); ?>",
                        data: [<?php echo implode(",", $result['sampleReceivedPrinted']); ?>],
                        color: '#000',
                    },
                <?php
                }
                ?>
            ],
        });
    <?php }
    if (isset($testReasonResult) && count($testReasonResult) > 0) { ?>
        $('#tbTestReasonContainer').highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: "<?php echo _("TB Test Reasons"); ?>"
            },
            credits: {
                enabled: false
            },
            tooltip: {
                pointFormat: "<?php echo _("Test Reasons"); ?> :<strong>{point.y}</strong>"
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
                            color: '#<?php echo MiscUtils::randomHexColor(); ?>',
                        },
                    <?php
                    }
                    ?>
                ]
            }]
        });
    <?php } ?>
</script>