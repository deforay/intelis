<?php
ob_start();
#require_once('../startup.php');



$general = new \Vlsm\Models\General(); // passing $db which is coming from startup.php
$facilityDb = new \Vlsm\Models\Facilities(); // passing $db which is coming from startup.php

$configFormQuery = "SELECT * FROM global_config WHERE name ='vl_form' limit 1";
$configFormResult = $db->rawQueryOne($configFormQuery);
$cDate = date('Y-m-d');
$lastSevenDay = date('Y-m-d', strtotime('-7 days'));
$facilityInfo = $facilityDb->getAllFacilities();


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
    $samplesCollectionChart = "eidSamplesCollectionChart";
    $samplesOverviewChart   = "eidSamplesOverviewChart";
    $unique = "Test2";
} else if (isset($_POST['type']) && trim($_POST['type']) == 'covid19') {
    $table = "form_covid19";
    $samplesReceivedChart   = "covid19SamplesReceivedChart";
    $samplesTestedChart     = "covid19SamplesTestedChart";
    $samplesNotTestedChart  = "covid19SamplesNotTestedChart";
    $samplesRejectedChart   = "covid19SamplesRejectedChart";
    $samplesWaitingChart    = "covid19SamplesWaitingChart";
    $samplesCollectionChart = "covid19SamplesCollectionChart";
    $samplesOverviewChart   = "covid19SamplesOverviewChart";
    $unique = "Test3";
} else if (isset($_POST['type']) && trim($_POST['type']) == 'hepatitis') {
    $table = "form_hepatitis";
    $samplesReceivedChart   = "hepatitisSamplesReceivedChart";
    $samplesTestedChart     = "hepatitisSamplesTestedChart";
    $samplesRejectedChart   = "hepatitisSamplesRejectedChart";
    $samplesWaitingChart    = "hepatitisSamplesWaitingChart";
    $samplesCollectionChart = "hepatitisSamplesCollectionChart";
    $samplesOverviewChart   = "hepatitisSamplesOverviewChart";
    $unique = "Test4";
} else if (isset($_POST['type']) && trim($_POST['type']) == 'vl') {

    $recencyWhere = " reason_for_vl_testing != 9999 ";
    $table = "vl_request_form";
    $samplesReceivedChart   = "vlSamplesReceivedChart";
    $samplesTestedChart     = "vlSamplesTestedChart";
    $samplesRejectedChart   = "vlSamplesRejectedChart";
    $samplesWaitingChart    = "vlSamplesWaitingChart";
    $samplesCollectionChart = "vlSamplesCollectionChart";
    $samplesOverviewChart   = "vlSamplesOverviewChart";
    $unique = "Test1";
} else if (isset($_POST['type']) && trim($_POST['type']) == 'recency') {
    $recencyWhere = " reason_for_vl_testing = 9999 ";
    $table = "vl_request_form";
    $samplesReceivedChart   = "recencySamplesReceivedChart";
    $samplesTestedChart     = "recencySamplesTestedChart";
    $samplesRejectedChart   = "recencySamplesRejectedChart";
    $samplesWaitingChart    = "recencySamplesWaitingChart";
    $samplesCollectionChart = "recencySamplesCollectionChart";
    $samplesOverviewChart   = "recencySamplesOverviewChart";
    $unique = "Test5";
} else if (isset($_POST['type']) && trim($_POST['type']) == 'tb') {
    $table = "form_tb";
    $samplesReceivedChart   = "tbSamplesReceivedChart";
    $samplesTestedChart     = "tbSamplesTestedChart";
    $samplesRejectedChart   = "tbSamplesRejectedChart";
    $samplesWaitingChart    = "tbSamplesWaitingChart";
    $samplesCollectionChart = "tbSamplesCollectionChart";
    $samplesOverviewChart   = "tbSamplesOverviewChart";
    $unique = "Test6";
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
        } else if (isset($_POST['type']) && trim($_POST['type']) == 'tb') {
            $whereCondition = " tb.facility_id IN (" . $userfacilityMapresult[0]['facility_id'] . ") AND ";
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
} else if ($table == "form_tb") {
    $waitingQuery = "SELECT COUNT(tb_id) as total FROM " . $table . " as tb JOIN facility_details as f ON f.facility_id=tb.facility_id WHERE $whereCondition (sample_collection_date < DATE_SUB(NOW(), INTERVAL 6 MONTH)) AND (tb.result is null or tb.result = '') AND (tb.is_sample_rejected like 'no' or tb.is_sample_rejected is null or tb.is_sample_rejected like '')";
} else if ($table == "vl_request_form") {
    if ($whereCondition == "") {
        $vlWhereCondition = $recencyWhere . " AND ";
    } else {
        $vlWhereCondition = $recencyWhere . " AND " . $whereCondition;
    }
    $waitingQuery = "SELECT COUNT(vl_sample_id) as total FROM " . $table . " as vl JOIN facility_details as f ON f.facility_id=vl.facility_id WHERE $vlWhereCondition vl.vlsm_country_id = '" . $configFormResult['value'] . "' " . " AND (sample_collection_date < DATE_SUB(NOW(), INTERVAL 6 MONTH)) AND (vl.result is null or vl.result = '') AND (vl.is_sample_rejected like 'no' or vl.is_sample_rejected is null or vl.is_sample_rejected = '')";
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
    $accessionQuery = 'SELECT DATE(eid.sample_collection_date) as `collection_date`, COUNT(eid_id) as `count` FROM ' . $table . ' as eid INNER JOIN facility_details as f ON f.facility_id=eid.facility_id WHERE ' . $whereCondition . ' DATE(eid.sample_collection_date) <= "' . $cDate . '" AND DATE(eid.sample_collection_date) >= "' . $lastSevenDay . '" GROUP BY `collection_date` order by `collection_date`';
    $primaryKey = "eid_id";
} else if ($table == "form_covid19") {
    $accessionQuery = 'SELECT DATE(covid19.sample_collection_date) as `collection_date`, COUNT(covid19_id) as `count` FROM ' . $table . ' as covid19 INNER JOIN facility_details as f ON f.facility_id=covid19.facility_id WHERE ' . $whereCondition . ' DATE(covid19.sample_collection_date) <= "' . $cDate . '" AND DATE(covid19.sample_collection_date) >= "' . $lastSevenDay . '" GROUP BY `collection_date` order by `collection_date`';
    $primaryKey = "covid19_id";
} else if ($table == "form_hepatitis") {
    $accessionQuery = 'SELECT DATE(req.sample_collection_date) as `collection_date`, COUNT(hepatitis_id) as `count` FROM ' . $table . ' as req INNER JOIN facility_details as f ON f.facility_id=req.facility_id WHERE ' . $whereCondition . ' DATE(req.sample_collection_date) <= "' . $cDate . '" AND DATE(req.sample_collection_date) >= "' . $lastSevenDay . '" AND req.vlsm_country_id = "' . $configFormResult['value'] . '" group by `collection_date` order by `collection_date`';
    $primaryKey = "hepatitis_id";
} else if ($table == "form_tb") {
    $accessionQuery = 'SELECT DATE(tb.sample_collection_date) as `collection_date`, COUNT(tb_id) as `count` FROM ' . $table . ' as tb INNER JOIN facility_details as f ON f.facility_id=tb.facility_id WHERE ' . $whereCondition . ' DATE(tb.sample_collection_date) <= "' . $cDate . '" AND DATE(tb.sample_collection_date) >= "' . $lastSevenDay . '" AND tb.vlsm_country_id = "' . $configFormResult['value'] . '" group by `collection_date` order by `collection_date`';
    $primaryKey = "tb_id";
} else {
    if ($whereCondition == "") {
        $vlWhereCondition = $recencyWhere . " AND ";
    } else {
        $vlWhereCondition = $recencyWhere . " AND " . $whereCondition;
    }
    $accessionQuery = 'SELECT DATE(vl.sample_collection_date) as `collection_date`, COUNT(vl_sample_id) as `count` FROM ' . $table . ' as vl INNER JOIN facility_details as f ON f.facility_id=vl.facility_id WHERE ' . $vlWhereCondition . ' DATE(vl.sample_collection_date) <= "' . $cDate . '" AND DATE(vl.sample_collection_date) >= "' . $lastSevenDay . '" AND vl.vlsm_country_id = "' . $configFormResult['value'] . '" group by `collection_date` order by `collection_date`';
    $primaryKey = "vl_sample_id";
}

$aggregateQuery = "SELECT COUNT($primaryKey) as totalCollected, 
    SUM(CASE WHEN (vl.lab_id is NOT NULL AND vl.sample_tested_datetime is NOT NULL AND vl.result is NOT NULL AND vl.result not like '' AND vl.result_status = 7) THEN 1 ELSE 0 END) as 'tested',
    SUM(CASE WHEN (vl.result_status = 1) THEN 1 ELSE 0 END) as 'hold',
    SUM(CASE WHEN (vl.result_status = 4) THEN 1 ELSE 0 END) as 'rejected',
    SUM(CASE WHEN (vl.result_status = 5) THEN 1 ELSE 0 END) as 'invalid',
    SUM(CASE WHEN (vl.result_status = 6) THEN 1 ELSE 0 END) as 'registeredAtTestingLab',
    SUM(CASE WHEN (vl.result_status = 8) THEN 1 ELSE 0 END) as 'awaitingApproval',
    SUM(CASE WHEN (vl.result_status = 9) THEN 1 ELSE 0 END) as 'registeredAtCollectionPoint',
    SUM(CASE WHEN (vl.result_status = 10) THEN 1 ELSE 0 END) as 'expired'
FROM $table as vl 
INNER JOIN facility_details as f ON f.facility_id=vl.facility_id 
WHERE vlsm_country_id = '" . $configFormResult['value'] . "' 
AND DATE(vl.sample_collection_date) <= '" . $cDate . "' 
AND DATE(vl.sample_collection_date) >= '" . $lastSevenDay . "'";
// die($aggregateQuery);
$aggregateResult = $db->rawQueryOne($aggregateQuery);

//get collection data
$collectionQuery = "SELECT COUNT($primaryKey) as total, facility_name, DATE(vl.sample_collection_date) as `collection_date` FROM " . $table . " as vl INNER JOIN facility_details as f ON f.facility_id=vl.facility_id WHERE vlsm_country_id = '" . $configFormResult['value'] . "' AND DATE(vl.sample_collection_date) <= '" . $cDate . "' AND DATE(vl.sample_collection_date) >= '" . $lastSevenDay . "'  GROUP BY f.facility_id having `total` > 0 ORDER BY total DESC";
$collectionResult = $db->rawQuery($collectionQuery); //collection result
$collectionTotal = 0;
if (sizeof($collectionResult) > 0) {
    foreach ($collectionResult as $total) {
        $collectionTotal = $collectionTotal + $total['total'];
    }
}

$tRes = $db->rawQuery($accessionQuery); //overall result
$tResult = array();
foreach ($tRes as $tRow) {
    $receivedTotal += $tRow['count'];
    $tResult[] = array('total' => $tRow['count'], 'date' => $tRow['collection_date']);
}

//Samples Tested
if ($table == "eid_form") {
    $sampleTestedQuery = 'SELECT DATE(eid.sample_tested_datetime) as `test_date`, COUNT(eid_id) as `count` FROM ' . $table . ' as eid JOIN facility_details as f ON f.facility_id=eid.facility_id INNER JOIN facility_details as l_f ON eid.lab_id=l_f.facility_id WHERE (result_status = 7) AND ' . $whereCondition . ' DATE(eid.sample_tested_datetime) <= "' . $cDate . '" AND DATE(eid.sample_tested_datetime) >= "' . $lastSevenDay . '" group by `test_date` order by `test_date`';
} else if ($table == "form_covid19") {
    $sampleTestedQuery = 'SELECT DATE(covid19.sample_tested_datetime) as `test_date`, COUNT(covid19_id) as `count` FROM ' . $table . ' as covid19 JOIN facility_details as f ON f.facility_id=covid19.facility_id  WHERE (covid19.lab_id is NOT NULL AND covid19.sample_tested_datetime is NOT NULL AND covid19.result is NOT NULL AND covid19.result not like "" AND covid19.result_status = 7) AND ' . $whereCondition . ' DATE(covid19.sample_collection_date) <= "' . $cDate . '" AND DATE(covid19.sample_collection_date) >= "' . $lastSevenDay . '" group by `test_date` order by `test_date`';
} else if ($table == "form_hepatitis") {
    $sampleTestedQuery = 'SELECT DATE(req.sample_tested_datetime) as `test_date`, COUNT(hepatitis_id) as `count` FROM ' . $table . ' as req JOIN facility_details as f ON f.facility_id=req.facility_id  INNER JOIN facility_details as l_f ON req.lab_id=l_f.facility_id WHERE (result_status = 7) AND ' . $whereCondition . ' DATE(req.sample_tested_datetime) <= "' . $cDate . '" AND DATE(req.sample_tested_datetime) >= "' . $lastSevenDay . '" group by `test_date` order by `test_date`';
} else if ($table == "form_tb") {
    $sampleTestedQuery = 'SELECT DATE(tb.sample_tested_datetime) as `test_date`, COUNT(tb_id) as `count` FROM ' . $table . ' as tb JOIN facility_details as f ON f.facility_id=tb.facility_id  INNER JOIN facility_details as l_f ON tb.lab_id=l_f.facility_id WHERE (result_status = 7) AND ' . $whereCondition . ' DATE(tb.sample_tested_datetime) <= "' . $cDate . '" AND DATE(tb.sample_tested_datetime) >= "' . $lastSevenDay . '" group by `test_date` order by `test_date`';
} else {
    if ($whereCondition == "") {
        $vlWhereCondition = $recencyWhere . " AND ";
    } else {
        $vlWhereCondition = $recencyWhere . " AND " . $whereCondition;
    }
    $sampleTestedQuery = "SELECT DATE(vl.sample_tested_datetime) as `test_date`, COUNT(vl_sample_id) as `count` FROM $table as vl INNER JOIN facility_details as f ON f.facility_id=vl.facility_id INNER JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id WHERE (result_status = 7) AND $vlWhereCondition DATE(vl.sample_tested_datetime) <= '$cDate' AND DATE(vl.sample_tested_datetime) >= '$lastSevenDay' GROUP BY `test_date` ORDER BY `test_date`";
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
    $sampleRejectedQuery = 'SELECT DATE(eid.sample_collection_date) as `collection_date`, COUNT(eid_id) as `count` FROM ' . $table . ' as eid INNER JOIN facility_details as f ON f.facility_id=eid.facility_id  INNER JOIN facility_details as l_f ON eid.lab_id=l_f.facility_id WHERE (result_status = 4) AND ' . $whereCondition . ' DATE(eid.sample_collection_date) <= "' . $cDate . '" AND DATE(eid.sample_collection_date) >= "' . $lastSevenDay . '" group by `collection_date` order by `collection_date`';
} else if ($table == "form_covid19") {
    $sampleRejectedQuery = 'SELECT DATE(covid19.sample_collection_date) as `collection_date`, COUNT(covid19_id) as `count` FROM ' . $table . ' as covid19 INNER JOIN facility_details as f ON f.facility_id=covid19.facility_id  INNER JOIN facility_details as l_f ON covid19.lab_id=l_f.facility_id WHERE (result_status = 4) AND  ' . $whereCondition . '  DATE(covid19.sample_collection_date) <= "' . $cDate . '" AND DATE(covid19.sample_collection_date) >= "' . $lastSevenDay . '" group by `collection_date` order by `collection_date`';
} else if ($table == "form_hepatitis") {
    $sampleRejectedQuery = 'SELECT DATE(req.sample_collection_date) as `collection_date`, COUNT(hepatitis_id) as `count` FROM ' . $table . ' as req JOIN facility_details as f ON f.facility_id=req.facility_id  INNER JOIN facility_details as l_f ON req.lab_id=l_f.facility_id WHERE (result_status = 4) AND  ' . $whereCondition . ' DATE(req.sample_collection_date) <= "' . $cDate . '" AND DATE(req.sample_collection_date) >= "' . $lastSevenDay . '" group by `collection_date` order by `collection_date`';
} else if ($table == "form_tb") {
    $sampleRejectedQuery = 'SELECT DATE(tb.sample_collection_date) as `collection_date`, COUNT(tb_id) as `count` FROM ' . $table . ' as tb JOIN facility_details as f ON f.facility_id=tb.facility_id  INNER JOIN facility_details as l_f ON tb.lab_id=l_f.facility_id WHERE (result_status = 4) AND  ' . $whereCondition . ' DATE(tb.sample_collection_date) <= "' . $cDate . '" AND DATE(tb.sample_collection_date) >= "' . $lastSevenDay . '" group by `collection_date` order by `collection_date`';
} else {
    if ($whereCondition == "") {
        $vlWhereCondition = $recencyWhere . " AND ";
    } else {
        $vlWhereCondition = $recencyWhere . " AND " . $whereCondition;
    }
    $sampleRejectedQuery = 'SELECT DATE(vl.sample_collection_date) as `collection_date`, COUNT(vl_sample_id) as `count` FROM ' . $table . ' as vl INNER JOIN facility_details as f ON f.facility_id=vl.facility_id  INNER JOIN facility_details as l_f ON vl.lab_id=l_f.facility_id WHERE (result_status = 4) AND  ' . $vlWhereCondition . ' DATE(vl.sample_collection_date) <= "' . $cDate . '" AND DATE(vl.sample_collection_date) >= "' . $lastSevenDay . '" GROUP BY `collection_date` order by `collection_date`';
}
$tRes = $db->rawQuery($sampleRejectedQuery); //overall result
$rejectedResult = array();
foreach ($tRes as $tRow) {
    $rejectedTotal += $tRow['count'];
    $rejectedResult[] = array('total' => $tRow['count'], 'date' => $tRow['collection_date']);
}

//Status counts
if ($table == "form_covid19") {
    $statusQuery = 'SELECT s.status_name, DATE(covid19.sample_collection_date) as `collection_date`, COUNT(covid19_id) as `count` FROM r_sample_status AS s INNER JOIN ' . $table . ' as covid19 ON covid19.result_status=s.status_id WHERE DATE(covid19.sample_collection_date) <= "' . $cDate . '" AND DATE(covid19.sample_collection_date) >= "' . $lastSevenDay . '" GROUP BY `collection_date` order by `collection_date`';
    $statusQueryResult = $db->rawQuery($statusQuery); //overall result
    $statusTotal = 0;
    foreach ($statusQueryResult as $statusRow) {
        $statusTotal += $statusRow['count'];
        $statusResult['date'][$statusRow['collection_date']] = "'" . $statusRow['collection_date'] . "'";
        $statusResult['status'][$statusRow['status_name']][$statusRow['collection_date']] = $statusRow['count'];
    }
}
?>
<link href="/assets/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-beta.1/dist/js/select2.min.js"></script>
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
                    <span data-counter="counterup" data-value="<?php echo $receivedTotal; ?>"><?php echo $receivedTotal; ?></span>
                </h3>
                <small class="font-green-sharp"><?php echo _("SAMPLES REGISTERED"); ?></small><br>
                <small class="font-green-sharp" style="font-size:0.75em;"><?php echo _("in selected range"); ?></small>
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
                <small class="font-blue-sharp"><?php echo _("SAMPLES TESTED"); ?></small><br>
                <small class="font-blue-sharp" style="font-size:0.75em;"><?php echo _("In Selected Range"); ?></small>
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
                <small class="font-red-haze"><?php echo _("SAMPLES REJECTED"); ?></small><br>
                <small class="font-red-haze" style="font-size:0.75em;"><?php echo _("In Selected Range"); ?></small>
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
                <small class="font-purple-soft"><?php echo _("SAMPLES WITH NO RESULTS"); ?></small><br>
                <small class="font-purple-soft" style="font-size:0.75em;"><?php echo _("(LAST 6 MONTHS)"); ?></small>
                <!--<small class="font-purple-soft"><?php echo $waitingDate; ?></small>-->
            </div>
            <div class="icon">
                <i class="icon-pie-chart"></i>
            </div>
        </div>
        <div id="<?php echo $samplesWaitingChart; ?>" width="210" height="150" style="min-height:150px;"></div>
    </div>
</div>
<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 ">
    <div class="dashboard-stat2 bluebox" style="cursor:pointer;">
        <div class="display">
            <div class="number">
                <h4 class="font-purple-soft" style="font-weight:600;"><?= _("OVERALL SAMPLE STATUS"); ?></h4>
                <small class="font-purple-soft" style="font-size:0.75em;"><?= _("(BASED ON SAMPLES COLLECTED IN THE SELECTED DATE RANGE)"); ?></small>
            </div>
            <div class="icon">
                <i class="icon-pie-chart"></i>
            </div>
        </div>
        <div id="<?php echo $samplesOverviewChart; ?>" width="210" height="150" style="min-height:240px;"></div>
    </div>
</div>
<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12 ">
    <table class="table collectionTable" cellpadding="1" cellspacing="3" style="margin-top:0px;width: 98%;margin-bottom: 0px;">
        <tr>
            <td style="vertical-align:middle;padding-left: 0px;"><b><?php echo _("Collection Point"); ?>&nbsp;:</b>
                <select id="facilityId<?php echo $unique; ?>" name="facilityId" class="form-control" multiple title="<?php echo _('Select facility name to filter'); ?>" style="width:220px;background:#fff;">
                    <?php foreach ($facilityInfo as $facility) { ?>
                        <option vlaue="<?php echo $facility['facility_id']; ?>"><?php echo $facility['facility_name']; ?></option>
                    <?php } ?>
                </select>
            </td>
            <td colspan="3" style=" display: grid; ">&nbsp;<input type="button" onclick="fetchByFacility();" value="<?php echo _('Search'); ?>" class="searchBtn btn btn-success btn-sm">
            </td>
        </tr>
    </table>
    <div class="dashboard-stat2 " style="cursor:pointer;">
        <div class="display">
            <div class="number">
                <h3 class="font-purple-soft">
                    <span data-counter="counterup" class="facilityCounterup" data-value="<?php echo $collectionTotal; ?>"><?php echo $collectionTotal; ?></span>
                </h3>
                <small class="font-purple-soft"><?php echo _("SAMPLES REGISTERED BY COLLECTION POINT"); ?></small><br>
                <!-- <small class="font-purple-soft" style="font-size:0.75em;">(LAST 6 MONTHS)</small> -->
            </div>
            <div class="icon">
                <i class="icon-pie-chart"></i>
            </div>
        </div>
        <div id="collectionSite<?php echo $unique; ?>">
            <div id="<?php echo $samplesCollectionChart; ?>" width="210" height="150" style="min-height:150px;"></div>
        </div>
    </div>
</div>
<script src="/assets/js/select2.js"></script>
<script>
    $(document).ready(function() {
        $('#facilityId<?php echo $unique; ?>').select2({
            width: '100%',
            placeholder: "<?= _("Select Collection Point(s)"); ?>"
        });
    });

    function fetchByFacility() {
        $.blockUI();
        $.post("/dashboard/get-collection-samples.php", {
                table: '<?php echo $table; ?>',
                primaryKey: '<?php echo $primaryKey; ?>',
                facilityId: $('#facilityId<?php echo $unique; ?>').val(),
                cDate: <?php echo $cDate; ?>,
                sampleCollectionDate: '<?php echo $_POST['sampleCollectionDate']; ?>',
            },
            function(data) {
                $("#collectionSite<?php echo $unique; ?>").html(data);
            });
        $.unblockUI();
    }
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
    if ($collectionTotal > 0) { ?>
        $('#<?php echo $samplesCollectionChart; ?>').highcharts({
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
                                foreach ($collectionResult as $tRow) {
                                    echo "'" . addslashes($tRow['facility_name']) . "',";
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
                        foreach ($collectionResult as $tRow) {
                            echo ucwords($tRow['total']) . ",";
                        }
                        ?>]

            }],
            colors: ['#f36a5a']
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
                name: "<?php echo _("Samples"); ?>",
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

    <?php if (isset($aggregateResult) && !empty($aggregateResult)) { ?>
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
                        text: "<?= _("Overall Sample Status"); ?>",
                    }
                }
            },
            credits: {
                enabled: false
            },
            xAxis: {
                categories: [
                    "<?= _("Samples Tested"); ?>",
                    "<?= _("Samples Rejected"); ?>",
                    "<?= _("Samples on Hold"); ?>",
                    "<?= _("Samples Registered at Testing Lab"); ?>",
                    "<?= _("Samples Awaiting Approval"); ?>",
                    "<?= _("Samples Registered at Collection Site"); ?>"
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
                    return '<b>' + this.x + '</b><br/>' +
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