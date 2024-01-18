<?php
use App\Services\CommonService;
use App\Services\DatabaseService;
use App\Utilities\DateUtility;
use App\Registries\ContainerRegistry;


require_once(__DIR__ . '/../bootstrap.php');

/** @var DatabaseService $db */
$db = ContainerRegistry::get(DatabaseService::class);

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);


/* Save Province / State details to geolocation table */
$query = "SELECT * FROM instruments";
$instrumentResult = $db->rawQuery($query);

foreach ($instrumentResult as $row) {
    $generatedInstrumentId = $general->generateUUID();
   // if(strlen($row['instrument_id'])==1){
        $db->where("instrument_id",$row['instrument_id']);
        $db->update('instruments',array('instrument_id' => $generatedInstrumentId));
   // }
}

/** VL table */
$vlQuery = "SELECT vl.vl_sample_id,ins.instrument_id FROM form_vl as vl INNER JOIN instruments as ins ON ins.machine_name = vl.vl_test_platform  WHERE vl.vl_test_platform IS NOT NULL";
$vlResult = $db->rawQuery($vlQuery);

foreach ($vlResult as $vlRow) {
    $db->where("vl_sample_id",$vlRow["vl_sample_id"]);
    $db->update('form_vl',array('instrument_id' => $vlRow['instrument_id']));
}

/** EID table */
$eidQuery = "SELECT eid.eid_id,ins.instrument_id FROM form_eid as eid INNER JOIN instruments as ins ON ins.machine_name = eid.eid_test_platform  WHERE eid.eid_test_platform IS NOT NULL";
$eidResult = $db->rawQuery($eidQuery);

foreach ($eidResult as $eidRow) {
    $db->where("eid_id",$eidRow["eid_id"]);
    $db->update('form_eid',array('instrument_id' => $eidRow['instrument_id']));
}

/** Covid-19 table */
$covidQuery = "SELECT covid.test_id,ins.instrument_id FROM covid19_tests as covid INNER JOIN instruments as ins ON ins.machine_name = covid.testing_platform  WHERE covid.testing_platform IS NOT NULL";
$covidResult = $db->rawQuery($covidQuery);

foreach ($covidResult as $covidRow) {
    $db->where("test_id",$covidRow["test_id"]);
    $db->update('covid19_tests',array('instrument_id' => $covidRow['instrument_id']));
}

/** Hepatitis table */
$hepatitisQuery = "SELECT hep.hepatitis_id,ins.instrument_id FROM form_hepatitis as hep INNER JOIN instruments as ins ON ins.machine_name = hep.hepatitis_test_platform  WHERE hep.hepatitis_test_platform IS NOT NULL";
$hepatitisResult = $db->rawQuery($hepatitisQuery);

foreach ($hepatitisResult as $hepatitisRow) {
    $db->where("hepatitis_id",$hepatitisRow["hepatitis_id"]);
    $db->update('form_hepatitis',array('instrument_id' => $hepatitisRow['instrument_id']));
}

/** TB table */
$tbQuery = "SELECT tb.tb_id,ins.instrument_id FROM form_tb as tb INNER JOIN instruments as ins ON ins.machine_name = tb.tb_test_platform  WHERE tb.tb_test_platform IS NOT NULL";
$tbResult = $db->rawQuery($tbQuery);

foreach ($tbResult as $tbRow) {
    $db->where("tb_id",$tbRow["tb_id"]);
    $db->update('form_tb',array('instrument_id' => $tbRow['instrument_id']));
}