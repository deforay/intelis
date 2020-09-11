<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
ob_start();
#require_once('../../startup.php');



$general = new \Vlsm\Models\General($db); // passing $db which is coming from startup.php
$whereCondition = '';
$configFormQuery = "SELECT * FROM global_config WHERE `name` ='vl_form'";
$configFormResult = $db->rawQuery($configFormQuery);

$userType = $general->getSystemConfig('user_type');



$whereCondition = '';

if ($userType == 'remoteuser') {
    $userfacilityMapQuery = "SELECT GROUP_CONCAT(DISTINCT `facility_id` ORDER BY `facility_id` SEPARATOR ',') as `facility_id` FROM vl_user_facility_map WHERE user_id='" . $_SESSION['userId'] . "'";
    $userfacilityMapresult = $db->rawQuery($userfacilityMapQuery);
    if ($userfacilityMapresult[0]['facility_id'] != null && $userfacilityMapresult[0]['facility_id'] != '') {
        $userfacilityMapresult[0]['facility_id'] = rtrim($userfacilityMapresult[0]['facility_id'], ",");
        $whereCondition = " AND vl.facility_id IN (" . $userfacilityMapresult[0]['facility_id'] . ")   AND remote_sample='yes'";
    }
}



if (isset($_POST['type']) && trim($_POST['type']) == 'recency') {
    $recencyWhere = " AND reason_for_vl_testing = 9999";
    $sampleStatusOverviewContainer  = "recencySampleStatusOverviewContainer";
    $samplesVlOverview              = "recencySmplesVlOverview";
    $labAverageTat                  = "recencyLabAverageTat";

}else{
    $recencyWhere = " AND reason_for_vl_testing != 9999";
    $sampleStatusOverviewContainer  = "vlSampleStatusOverviewContainer";
    $samplesVlOverview              = "vlSmplesVlOverview";
    $labAverageTat                  = "vlLabAverageTat";

}

$table = "vl_request_form";
$highVL                         = "High Viral Load";
$lowVL                          = "Low Viral Load";
$suppression                    = "VL Suppression";

$tsQuery = "SELECT * FROM `r_sample_status` ORDER BY `status_id`";
$tsResult = $db->rawQuery($tsQuery);
// $sampleStatusArray = array();
// foreach($tsResult as $tsRow){
//     $sampleStatusArray = $tsRow['status_name'];
// }

$sampleStatusColors = array();

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
        $start_date = $general->dateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $end_date = $general->dateFormat(trim($s_c_date[1]));
    }
}

$tQuery = "SELECT COUNT(vl_sample_id) as total,status_id,status_name 
    FROM " . $table . " as vl 
    JOIN r_sample_status as ts ON ts.status_id=vl.result_status 
    JOIN facility_details as f ON vl.facility_id=f.facility_id 
    LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id 
    WHERE vl.vlsm_country_id='" . $configFormResult[0]['value'] . "'". $whereCondition . $recencyWhere;

//filter
$sWhere = '';
if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != '') {
    $sWhere .= ' AND b.batch_code = "' . $_POST['batchCode'] . '"';
}
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
    $sWhere .= ' AND DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
}
/*  if (isset($_POST['sampleType']) && trim($_POST['sampleType']) != '') {
        $sWhere .= ' AND s.sample_id = "' . $_POST['sampleType'] . '"';
    } */
if (isset($_POST['facilityName']) && is_array($_POST['facilityName']) && count($_POST['facilityName']) > 0) {
    $sWhere .= ' AND f.facility_id IN (' . implode(",", $_POST['facilityName']) . ')';
}
$tQuery .= " " . $sWhere;

$tQuery .= " " . "GROUP BY vl.result_status ORDER BY status_id";


//echo $tQuery;die;

$tResult = $db->rawQuery($tQuery);


//HVL and LVL Samples

$vlSuppressionQuery = "SELECT COUNT(vl_sample_id) as total,
        SUM(CASE
                WHEN (vl.result > 1000) THEN 1
                    ELSE 0
                END) AS highVL,
        (SUM(CASE
                WHEN (vl.result <= 1000 OR vl.result REGEXP '^[^0-9]+$') THEN 1
                    ELSE 0
                END)) AS lowVL,                                        
        status_id,
        status_name 
        
        FROM " . $table . " as vl INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id where vl.vlsm_country_id='" . $configFormResult[0]['value'] . "'". $whereCondition  . $recencyWhere;

$sWhere = " AND (vl.result!='' and vl.result is not null) ";
if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != '') {
    $sWhere .= ' AND b.batch_code = "' . $_POST['batchCode'] . '"';
}
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
    $sWhere .= ' AND DATE(vl.sample_collection_date) >= "' . $start_date . '" AND DATE(vl.sample_collection_date) <= "' . $end_date . '"';
}

/* if (isset($_POST['sampleType']) && trim($_POST['sampleType']) != '') {
    $sWhere .= ' AND s.sample_id = "' . $_POST['sampleType'] . '"';
} */
if (isset($_POST['facilityName']) && is_array($_POST['facilityName']) && count($_POST['facilityName']) > 0) {
    $sWhere .= ' AND f.facility_id IN (' . implode(",", $_POST['facilityName']) . ')';
}
$vlSuppressionQuery = $vlSuppressionQuery . ' ' . $sWhere;
$vlSuppressionResult = $db->rawQueryOne($vlSuppressionQuery);

//get LAB TAT
if ($start_date == '' && $end_date == '') {
    $date = strtotime(date('Y-m-d') . ' -1 year');
    $start_date = date('Y-m-d', $date);
    $end_date = date('Y-m-d');
}

$tatSampleQuery = "SELECT
        DATE_FORMAT(DATE(sample_collection_date), '%b-%Y') as monthDate,
        CAST(ABS(AVG(TIMESTAMPDIFF(DAY,sample_tested_datetime,sample_collection_date))) AS DECIMAL (10,2)) as AvgTestedDiff,
        CAST(ABS(AVG(TIMESTAMPDIFF(DAY,sample_received_at_vl_lab_datetime,sample_collection_date))) AS DECIMAL (10,2)) as AvgReceivedDiff,
        CAST(ABS(AVG(TIMESTAMPDIFF(DAY,result_printed_datetime,sample_collection_date))) AS DECIMAL (10,2)) as AvgPrintedDiff
    
        from " . $table . " as vl INNER JOIN r_sample_status as ts ON ts.status_id=vl.result_status JOIN facility_details as f ON vl.facility_id=f.facility_id LEFT JOIN r_vl_sample_type as s ON s.sample_id=vl.sample_type LEFT JOIN batch_details as b ON b.batch_id=vl.sample_batch_id where (vl.sample_collection_date is not null AND vl.sample_collection_date not like '' AND DATE(vl.sample_collection_date) !='1970-01-01' AND DATE(vl.sample_collection_date) !='0000-00-00')
        AND ((vl.sample_tested_datetime is not null AND vl.sample_tested_datetime not like '' AND DATE(vl.sample_tested_datetime) !='1970-01-01' AND DATE(vl.sample_tested_datetime) !='0000-00-00') OR
        (vl.result_printed_datetime is not null AND vl.result_printed_datetime not like '' AND DATE(vl.result_printed_datetime) !='1970-01-01' AND DATE(vl.result_printed_datetime) !='0000-00-00') OR
        (vl.sample_received_at_vl_lab_datetime is not null AND vl.sample_received_at_vl_lab_datetime not like '' AND DATE(vl.sample_received_at_vl_lab_datetime) !='1970-01-01' AND DATE(vl.sample_received_at_vl_lab_datetime) !='0000-00-00'))
        AND vl.result is not null
        AND vl.result != ''
        AND DATE(vl.sample_collection_date) >= '" . $start_date . "'
        AND DATE(vl.sample_collection_date) <= '" . $end_date . "' AND vl.vlsm_country_id='" . $configFormResult[0]['value'] . "'". $whereCondition . $recencyWhere . " GROUP BY MONTH(vl.sample_collection_date) ORDER BY (vl.sample_collection_date)";

$sWhere = '';
if (isset($_POST['batchCode']) && trim($_POST['batchCode']) != '') {
    $sWhere .= ' AND b.batch_code = "' . $_POST['batchCode'] . '"';
}
if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
    //$sWhere.= ' AND DATE(vl.sample_collection_date) >= "'.$start_date.'" AND DATE(vl.sample_collection_date) <= "'.$end_date.'"';
}
/* if (isset($_POST['sampleType']) && trim($_POST['sampleType']) != '') {
    $sWhere .= ' AND s.sample_id = "' . $_POST['sampleType'] . '"';
} */
if (isset($_POST['facilityName']) && is_array($_POST['facilityName']) && count($_POST['facilityName']) > 0) {
    $sWhere .= ' AND f.facility_id IN (' . implode(",", $_POST['facilityName']) . ')';
}
$tatSampleQuery = $tatSampleQuery . " " . $sWhere;
//$tatSampleQuery .= " HAVING TIMESTAMPDIFF(DAY,sample_tested_datetime,sample_collection_date) < 120 ";
$tatResult = $db->rawQuery($tatSampleQuery);
$j = 0;
foreach ($tatResult as $sRow) {
    if ($sRow["monthDate"] == null) {
        continue;
    }

    $result['sampleTestedDiff'][$j] = (isset($sRow["AvgTestedDiff"]) && $sRow["AvgTestedDiff"] > 0 && $sRow["AvgTestedDiff"] != null) ? round($sRow["AvgTestedDiff"], 2) : 'null';
    $result['sampleReceivedDiff'][$j] = (isset($sRow["AvgReceivedDiff"]) && $sRow["AvgReceivedDiff"] > 0 && $sRow["AvgReceivedDiff"] != null) ? round($sRow["AvgReceivedDiff"], 2) : 'null';
    $result['samplePrintedDiff'][$j] = (isset($sRow["AvgPrintedDiff"]) && $sRow["AvgPrintedDiff"] > 0 && $sRow["AvgPrintedDiff"] != null) ? round($sRow["AvgPrintedDiff"], 2) : 'null';
    $result['date'][$j] = $sRow["monthDate"];
    $j++;
}

?>
<div class="col-xs-12">
    <div class="box">
        <div class="box-body">
            <div id="<?php echo $sampleStatusOverviewContainer; ?>" style="float:left;width:100%; margin: 0 auto;"></div>
        </div>
    </div>
    <div class="box">
        <div class="box-body">
            <div id="<?php echo $samplesVlOverview; ?>" style="float:right;width:100%;margin: 0 auto;"></div>
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
    if (isset($tResult) && count($tResult) > 0) {
    ?>
        $('#<?php echo $sampleStatusOverviewContainer; ?>').highcharts({
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: 'Samples Status Overview'
            },
            credits: {
                enabled: false
            },
            tooltip: {
                pointFormat: 'Samples :<b>{point.y}</b>'
            },
            plotOptions: {
                pie: {
                    size: '100%',
                    allowPointSelect: true,
                    cursor: 'pointer',
                    dataLabels: {
                        enabled: true,
                        useHTML: true,
                        format: '<div style="padding-bottom:10px;"><b>{point.name}</b>: {point.y}</div>',
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
                    <?php foreach ($tResult as $tRow) { ?> {
                            name: '<?php echo ($tRow['status_name']); ?>',
                            y: <?php echo ($tRow['total']); ?>,
                            color: '<?php echo $sampleStatusColors[$tRow['status_id']]; ?>',
                            url: '../dashboard/vlTestResultStatus.php?id=<?php echo base64_encode($tRow['status_id']); ?>'
                        },
                    <?php } ?>
                ]
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
                text: '<?php echo $suppression; ?>'
            },
            credits: {
                enabled: false
            },
            tooltip: {
                pointFormat: 'Samples :<b>{point.y}</b>'
            },
            plotOptions: {
                pie: {
                    size: '100%',
                    allowPointSelect: true,
                    cursor: 'pointer',
                    dataLabels: {
                        enabled: true,
                        useHTML: true,
                        format: '<div style="padding-bottom:10px;"><b>{point.name}</b>: {point.y}</div>',
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
    if (isset($result) && count($result) > 0) {
    ?>
        $('#<?php echo $labAverageTat; ?>').highcharts({
            chart: {
                type: 'line'
            },
            title: {
                text: 'Laboratory Turnaround Time'
            },
            exporting: {
                chartOptions: {
                    subtitle: {
                        text: 'Laboratory Turnaround Time',
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
            yAxis: {
                title: {
                    text: 'Average TAT in Days'
                },
                labels: {
                    formatter: function() {
                        return this.value;
                    }
                },
                plotLines: [{
                    value: 16,
                    color: 'red',
                    width: 2
                }]
            },
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
                }
            },

            series: [
                <?php
                if (isset($result['sampleTestedDiff'])) {
                ?> {
                        connectNulls: false,
                        showInLegend: true,
                        name: 'Collected - Tested',
                        data: [<?php echo implode(",", $result['sampleTestedDiff']); ?>],
                        color: '#000',
                    },
                <?php
                }
                if (isset($result['sampleReceivedDiff'])) {
                ?> {
                        connectNulls: false,
                        showInLegend: true,
                        name: 'Collected - Received at Lab',
                        data: [<?php echo implode(",", $result['sampleReceivedDiff']); ?>],
                        color: '#4BC0D9',
                    },
                <?php
                }
                if (isset($result['samplePrintedDiff'])) {
                ?> {
                        connectNulls: false,
                        showInLegend: true,
                        name: 'Collected - Result Printed',
                        data: [<?php echo implode(",", $result['samplePrintedDiff']); ?>],
                        color: '#FF4500',
                    },
                <?php
                }
                ?>
            ],
        });
    <?php } ?>
</script>