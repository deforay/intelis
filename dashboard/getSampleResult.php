<?php
ob_start();
#require_once('../startup.php');



$general = new \Vlsm\Models\General($db); // passing $db which is coming from startup.php

$configFormQuery = "SELECT * FROM global_config WHERE name ='vl_form'";
$configFormResult = $db->rawQuery($configFormQuery);
$cDate = date('Y-m-d');
$lastSevenDay = date('Y-m-d', strtotime('-7 days'));



$waitingTotal = 0;
$rejectedTotal = 0;
$receivedTotal = 0;
$dFormat = '';
$waitingDate = '';
$rejectedDate = '';
$i = 0;
if (isset($_POST['type']) && trim($_POST['type']) == 'eid') {
    $table = "eid_form";
    $samplesReceivedChart   = "eidSamplesReceivedChart";
    $samplesTestedChart     = "eidSamplesTestedChart";
    $samplesRejectedChart   = "eidSamplesRejectedChart";
    $samplesWaitingChart    = "eidSamplesWaitingChart";
} else if (isset($_POST['type']) && trim($_POST['type']) == 'covid19') {
    $table = "form_covid19";
    $samplesReceivedChart   = "covid19SamplesReceivedChart";
    $samplesTestedChart     = "covid19SamplesTestedChart";
    $samplesRejectedChart   = "covid19SamplesRejectedChart";
    $samplesWaitingChart    = "covid19SamplesWaitingChart";
} else if (isset($_POST['type']) && trim($_POST['type']) == 'hepatitis') {
    $table = "form_hepatitis";
    $samplesReceivedChart   = "hepatitisSamplesReceivedChart";
    $samplesTestedChart     = "hepatitisSamplesTestedChart";
    $samplesRejectedChart   = "hepatitisSamplesRejectedChart";
    $samplesWaitingChart    = "hepatitisSamplesWaitingChart";
} else if (isset($_POST['type']) && trim($_POST['type']) == 'vl') {

    $recencyWhere = " reason_for_vl_testing != 9999 ";

    $table = "vl_request_form";
    $samplesReceivedChart   = "vlSamplesReceivedChart";
    $samplesTestedChart     = "vlSamplesTestedChart";
    $samplesRejectedChart   = "vlSamplesRejectedChart";
    $samplesWaitingChart    = "vlSamplesWaitingChart";
} else if (isset($_POST['type']) && trim($_POST['type']) == 'recency') {
    $recencyWhere = " reason_for_vl_testing = 9999 ";
    $table = "vl_request_form";
    $samplesReceivedChart   = "recencySamplesReceivedChart";
    $samplesTestedChart     = "recencySamplesTestedChart";
    $samplesRejectedChart   = "recencySamplesRejectedChart";
    $samplesWaitingChart    = "recencySamplesWaitingChart";
}


$u = $general->getSystemConfig('sc_user_type');

if ($u != 'remoteuser') {
    $whereCondition = " result_status!=9  AND ";
} else {
    $whereCondition = "";
    //get user facility map ids
    $userfacilityMapQuery = "SELECT GROUP_CONCAT(DISTINCT facility_id ORDER BY facility_id SEPARATOR ',') as facility_id FROM vl_user_facility_map where user_id='" . $_SESSION['userId'] . "'";
    $userfacilityMapresult = $db->rawQuery($userfacilityMapQuery);
    if ($userfacilityMapresult[0]['facility_id'] != null && $userfacilityMapresult[0]['facility_id'] != '') {
        $userfacilityMapresult[0]['facility_id'] = rtrim($userfacilityMapresult[0]['facility_id'], ",");
        if (isset($_POST['type']) && trim($_POST['type']) == 'eid') {
            $whereCondition = " eid.facility_id IN (" . $userfacilityMapresult[0]['facility_id'] . ") AND ";
        } else if (isset($_POST['type']) && trim($_POST['type']) == 'covid19') {
            $whereCondition = " covid19.facility_id IN (" . $userfacilityMapresult[0]['facility_id'] . ") AND ";
        } else {
            $whereCondition = " vl.facility_id IN (" . $userfacilityMapresult[0]['facility_id'] . ")  AND ";
        }
    }
}



if (isset($_POST['sampleCollectionDate']) && trim($_POST['sampleCollectionDate']) != '') {
    $s_c_date = explode("to", $_POST['sampleCollectionDate']);
    //print_r($s_c_date);die;
    if (isset($s_c_date[0]) && trim($s_c_date[0]) != "") {
        $lastSevenDay = $general->dateFormat(trim($s_c_date[0]));
    }
    if (isset($s_c_date[1]) && trim($s_c_date[1]) != "") {
        $cDate = $general->dateFormat(trim($s_c_date[1]));
    }
}
$sWhere = '';


//get waiting data
if ($table == "eid_form") {
    $waitingQuery = "SELECT COUNT(eid_id) as total FROM " . $table . " as eid JOIN facility_details as f ON f.facility_id=eid.facility_id WHERE $whereCondition (sample_collection_date < DATE_SUB(NOW(), INTERVAL 6 MONTH)) AND (eid.result is null or eid.result = '') AND (eid.is_sample_rejected like 'no' or eid.is_sample_rejected is null or eid.is_sample_rejected like '')";
} else if ($table == "form_covid19") {
    $waitingQuery = "SELECT COUNT(covid19_id) as total FROM " . $table . " as covid19 JOIN facility_details as f ON f.facility_id=covid19.facility_id WHERE $whereCondition (sample_collection_date < DATE_SUB(NOW(), INTERVAL 6 MONTH)) AND (covid19.result is null or covid19.result = '') AND (covid19.is_sample_rejected like 'no' or covid19.is_sample_rejected is null or covid19.is_sample_rejected like '')";
} else if ($table == "form_hepatitis") {
    $waitingQuery = "SELECT COUNT(hepatitis_id) as total FROM " . $table . " as hepatitis JOIN facility_details as f ON f.facility_id=hepatitis.facility_id WHERE $whereCondition (sample_collection_date < DATE_SUB(NOW(), INTERVAL 6 MONTH)) AND (hepatitis.result is null or hepatitis.result = '') AND (hepatitis.is_sample_rejected like 'no' or hepatitis.is_sample_rejected is null or hepatitis.is_sample_rejected like '')";
} else {
    if ($whereCondition == "") {
        $whereCondition = $recencyWhere . " AND ";
    } else {
        $whereCondition = $recencyWhere . " AND " . $whereCondition;
    }

    $waitingQuery = "SELECT COUNT(vl_sample_id) as total FROM " . $table . " as vl JOIN facility_details as f ON f.facility_id=vl.facility_id WHERE $whereCondition vl.vlsm_country_id = '" . $configFormResult[0]['value'] . "' " . " AND (sample_collection_date < DATE_SUB(NOW(), INTERVAL 6 MONTH)) AND (vl.result is null or vl.result = '') AND (vl.is_sample_rejected like 'no' or vl.is_sample_rejected is null or vl.is_sample_rejected = '')";
}
$waitingResult[$i] = $db->rawQuery($waitingQuery); //waiting result
if ($waitingResult[$i][0]['total'] != 0) {
    $waitingTotal = $waitingTotal + $waitingResult[$i][0]['total'];
    $waitingResult[$i]['date'] = $dFormat;
    $waitingDate = $dFormat;
} else {
    unset($waitingResult[$i]);
}

// Samples Accession
if ($table == "eid_form") {
    $accessionQuery = 'SELECT DATE(eid.sample_collection_date) as `collection_date`, COUNT(eid_id) as `count` FROM ' . $table . ' as eid JOIN facility_details as f ON f.facility_id=eid.facility_id where ' . $whereCondition . ' DATE(eid.sample_collection_date) <= "' . $cDate . '" AND DATE(eid.sample_collection_date) >= "' . $lastSevenDay . '" group by `collection_date` order by `collection_date`';
} else if ($table == "form_covid19") {
    $accessionQuery = 'SELECT DATE(covid19.sample_collection_date) as `collection_date`, COUNT(covid19_id) as `count` FROM ' . $table . ' as covid19 JOIN facility_details as f ON f.facility_id=covid19.facility_id where ' . $whereCondition . ' DATE(covid19.sample_collection_date) <= "' . $cDate . '" AND DATE(covid19.sample_collection_date) >= "' . $lastSevenDay . '" AND covid19.vlsm_country_id = "' . $configFormResult[0]['value'] . '" group by `collection_date` order by `collection_date`';
} else if ($table == "form_hepatitis") {
    $accessionQuery = 'SELECT DATE(req.sample_collection_date) as `collection_date`, COUNT(hepatitis_id) as `count` FROM ' . $table . ' as req JOIN facility_details as f ON f.facility_id=req.facility_id where ' . $whereCondition . ' DATE(req.sample_collection_date) <= "' . $cDate . '" AND DATE(req.sample_collection_date) >= "' . $lastSevenDay . '" AND req.vlsm_country_id = "' . $configFormResult[0]['value'] . '" group by `collection_date` order by `collection_date`';
} else {
    if ($whereCondition == "") {
        $whereCondition = $recencyWhere . " AND ";
    } else {
        $whereCondition = $recencyWhere . " AND " . $whereCondition;
    }
    $accessionQuery = 'SELECT DATE(vl.sample_collection_date) as `collection_date`, COUNT(vl_sample_id) as `count` FROM ' . $table . ' as vl JOIN facility_details as f ON f.facility_id=vl.facility_id where ' . $whereCondition . ' DATE(vl.sample_collection_date) <= "' . $cDate . '" AND DATE(vl.sample_collection_date) >= "' . $lastSevenDay . '" AND vl.vlsm_country_id = "' . $configFormResult[0]['value'] . '" group by `collection_date` order by `collection_date`';
}
$tRes = $db->rawQuery($accessionQuery); //overall result
$tResult = array();
foreach ($tRes as $tRow) {
    $receivedTotal += $tRow['count'];
    $tResult[] = array('total' => $tRow['count'], 'date' => $tRow['collection_date']);
}

//Samples Tested
if ($table == "eid_form") {
    $sampleTestedQuery = 'SELECT DATE(eid.sample_tested_datetime) as `test_date`, COUNT(eid_id) as `count` FROM ' . $table . ' as eid JOIN facility_details as f ON f.facility_id=eid.facility_id where ' . $whereCondition . ' DATE(eid.sample_tested_datetime) <= "' . $cDate . '" AND DATE(eid.sample_tested_datetime) >= "' . $lastSevenDay . '" group by `test_date` order by `test_date`';
} else if ($table == "form_covid19") {
    $sampleTestedQuery = 'SELECT DATE(covid19.sample_tested_datetime) as `test_date`, COUNT(covid19_id) as `count` FROM ' . $table . ' as covid19 JOIN facility_details as f ON f.facility_id=covid19.facility_id where ' . $whereCondition . ' DATE(covid19.sample_tested_datetime) <= "' . $cDate . '" AND DATE(covid19.sample_tested_datetime) >= "' . $lastSevenDay . '" group by `test_date` order by `test_date`';
} else if ($table == "form_hepatitis") {
    $sampleTestedQuery = 'SELECT DATE(req.sample_tested_datetime) as `test_date`, COUNT(hepatitis_id) as `count` FROM ' . $table . ' as req JOIN facility_details as f ON f.facility_id=req.facility_id where ' . $whereCondition . ' DATE(req.sample_tested_datetime) <= "' . $cDate . '" AND DATE(req.sample_tested_datetime) >= "' . $lastSevenDay . '" group by `test_date` order by `test_date`';
} else {
    if ($whereCondition == "") {
        $whereCondition = $recencyWhere . " AND ";
    } else {
        $whereCondition = $recencyWhere . " AND " . $whereCondition;
    }
    $sampleTestedQuery = 'SELECT DATE(vl.sample_tested_datetime) as `test_date`, COUNT(vl_sample_id) as `count` FROM ' . $table . ' as vl JOIN facility_details as f ON f.facility_id=vl.facility_id where ' . $whereCondition . ' DATE(vl.sample_tested_datetime) <= "' . $cDate . '" AND DATE(vl.sample_tested_datetime) >= "' . $lastSevenDay . '" AND vl.vlsm_country_id = "' . $configFormResult[0]['value'] . '" group by `test_date` order by `test_date`';
}
$tRes = $db->rawQuery($sampleTestedQuery); //overall result
$acceptedResult = array();
$acceptedTotal = 0;
foreach ($tRes as $tRow) {
    $acceptedTotal += $tRow['count'];
    $acceptedResult[] = array('total' => $tRow['count'], 'date' => $tRow['test_date']);
}

//Rejected Samples
if ($table == "eid_form") {
    $sampleRejectedQuery = 'SELECT DATE(eid.sample_collection_date) as `collection_date`, COUNT(eid_id) as `count` FROM ' . $table . ' as eid JOIN facility_details as f ON f.facility_id=eid.facility_id where ' . $whereCondition . ' eid.is_sample_rejected="yes" AND DATE(eid.sample_collection_date) <= "' . $cDate . '" AND DATE(eid.sample_collection_date) >= "' . $lastSevenDay . '" group by `collection_date` order by `collection_date`';
} else if ($table == "form_covid19") {
    $sampleRejectedQuery = 'SELECT DATE(covid19.sample_collection_date) as `collection_date`, COUNT(covid19_id) as `count` FROM ' . $table . ' as covid19 JOIN facility_details as f ON f.facility_id=covid19.facility_id where ' . $whereCondition . ' covid19.is_sample_rejected="yes" AND DATE(covid19.sample_collection_date) <= "' . $cDate . '" AND DATE(covid19.sample_collection_date) >= "' . $lastSevenDay . '" group by `collection_date` order by `collection_date`';
} else if ($table == "form_hepatitis") {
    $sampleRejectedQuery = 'SELECT DATE(req.sample_collection_date) as `collection_date`, COUNT(hepatitis_id) as `count` FROM ' . $table . ' as req JOIN facility_details as f ON f.facility_id=req.facility_id where ' . $whereCondition . ' req.is_sample_rejected="yes" AND DATE(req.sample_collection_date) <= "' . $cDate . '" AND DATE(req.sample_collection_date) >= "' . $lastSevenDay . '" group by `collection_date` order by `collection_date`';
} else {
    if ($whereCondition == "") {
        $whereCondition = $recencyWhere . " AND ";
    } else {
        $whereCondition = $recencyWhere . " AND " . $whereCondition;
    }
    $sampleRejectedQuery = 'SELECT DATE(vl.sample_collection_date) as `collection_date`, COUNT(vl_sample_id) as `count` FROM ' . $table . ' as vl JOIN facility_details as f ON f.facility_id=vl.facility_id where ' . $whereCondition . ' vl.is_sample_rejected="yes" AND DATE(vl.sample_collection_date) <= "' . $cDate . '" AND DATE(vl.sample_collection_date) >= "' . $lastSevenDay . '" GROUP BY `collection_date` order by `collection_date`';
}
$tRes = $db->rawQuery($sampleRejectedQuery); //overall result
$rejectedResult = array();
foreach ($tRes as $tRow) {
    $rejectedTotal += $tRow['count'];
    $rejectedResult[] = array('total' => $tRow['count'], 'date' => $tRow['collection_date']);
}

?>
<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 ">
    <div class="dashboard-stat2 bluebox" style="cursor:pointer;">
        <div class="display">
            <div class="number">
                <h3 class="font-green-sharp">
                    <span data-counter="counterup" data-value="<?php echo $receivedTotal; ?>"><?php echo $receivedTotal; ?></span>
                </h3>
                <small class="font-green-sharp">SAMPLES REGISTERED</small><br>
                <small class="font-green-sharp" style="font-size:0.75em;">in selected range</small>
            </div>
            <div class="icon">
                <i class="icon-pie-chart"></i>
            </div>
        </div>
        <div id="<?php echo $samplesReceivedChart; ?>" width="210" height="150" style="min-height:150px;"></div>
    </div>
</div>
<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 ">
    <div class="dashboard-stat2 " style="cursor:pointer;">
        <div class="display">
            <div class="number">
                <h3 class="font-blue-sharp">
                    <span data-counter="counterup" data-value="<?php echo $acceptedTotal; ?>"><?php echo $acceptedTotal; ?></span>
                </h3>
                <small class="font-blue-sharp">SAMPLES TESTED</small><br>
                <small class="font-blue-sharp" style="font-size:0.75em;">In Selected Range</small>
            </div>
            <div class="icon">
                <i class="icon-pie-chart"></i>
            </div>
        </div>
        <div id="<?php echo $samplesTestedChart; ?>" width="210" height="150" style="min-height:150px;"></div>
    </div>
</div>

<div class="col-lg-6 col-md-12 col-sm-12 col-xs-12 ">
    <div class="dashboard-stat2 " style="cursor:pointer;">
        <div class="display">
            <div class="number">
                <h3 class="font-red-haze">
                    <span data-counter="counterup" data-value="<?php echo $rejectedTotal; ?>"><?php echo $rejectedTotal; ?></span>
                </h3>
                <small class="font-red-haze">SAMPLES REJECTED</small><br>
                <small class="font-red-haze" style="font-size:0.75em;">In Selected Range</small>
                <!--<small class="font-red-haze"><?php echo $rejectedDate; ?></small>-->
            </div>
            <div class="icon">
                <i class="icon-pie-chart"></i>
            </div>
        </div>
        <div id="<?php echo $samplesRejectedChart; ?>" width="210" height="150" style="min-height:150px;"></div>
    </div>
</div>

<div class="col-lg-6 col-md-12 col-sm-12 col-xs-12 ">
    <div class="dashboard-stat2 " style="cursor:pointer;">
        <div class="display">
            <div class="number">
                <h3 class="font-purple-soft">
                    <span data-counter="counterup" data-value="<?php echo $waitingTotal; ?>"><?php echo $waitingTotal; ?></span>
                </h3>
                <small class="font-purple-soft">SAMPLES WITH NO RESULTS</small><br>
                <small class="font-purple-soft" style="font-size:0.75em;">(LAST 6 MONTHS)</small>
                <!--<small class="font-purple-soft"><?php echo $waitingDate; ?></small>-->
            </div>
            <div class="icon">
                <i class="icon-pie-chart"></i>
            </div>
        </div>
        <div id="<?php echo $samplesWaitingChart; ?>" width="210" height="150" style="min-height:150px;"></div>
    </div>
</div>

<script>
    <?php
    //if(isset($tResult) && count($tResult)>0){
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
                        echo '"' . ucwords($tRow['date']) . '",';
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
                    '<td style="padding:0"><b>{point.y}</b></td></tr>',
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
                            echo ucwords($tRow['total']) . ",";
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
                                    echo "'" . ucwords($total['date']) . "',";
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
                    '<td style="padding:0"><b>{point.y}</b></td></tr>',
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
                            echo ucwords($total[0]['total']) . ",";
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
                                    echo "'" . ucwords($tRow['date']) . "',";
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
                    '<td style="padding:0"><b>{point.y}</b></td></tr>',
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
                            echo ucwords($tRow['total']) . ",";
                        }
                        ?>]

            }],
            colors: ['#f36a5a']
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
                                    echo "'" . ucwords($tRow['date']) . "',";
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
                    '<td style="padding:0"><b>{point.y}</b></td></tr>',
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
                        foreach ($rejectedResult as $tRow) {
                            echo ucwords($tRow['total']) . ",";
                        }
                        ?>]

            }],
            colors: ['#5C9BD1']
        });
    <?php }
    //}
    ?>
</script>