<?php

// ini_set('memory_limit', -1);

require_once(__DIR__ . "/../startup.php");

$general = new \Vlsm\Models\General($db);

/* Save Province / State details to geolocation table */
$query = "SELECT DISTINCT facility_id, facility_state, facility_state_id FROM facility_details WHERE facility_state not in (SELECT geo_name FROM geographical_divisions WHERE geo_parent = 0)";
$provinceResult = $db->rawQuery($query);
foreach ($provinceResult as $p) {
    $exist = $db->rawQueryOne("SELECT geo_id, geo_name FROM geographical_divisions WHERE (geo_name = '?' OR geo_id = ?)", $p['facility_state'], $p['facility_state_id']);
    if ($exist) {
        $db->where("geo_id", $p['facility_state_id']);
        $db->update('geographical_divisions', array(
            "geo_name"          => $p['facility_state'],
            "created_on"        => $general->getDateTime(),
            "updated_datetime"  => $general->getDateTime()
        ));
        $lastInsertId = $exist['facility_state_id'];
    } else {
        $lastInsertId = $db->insert("geographical_divisions", array(
            "geo_name"          => $p['facility_state'],
            "geo_parent"        => "0",
            "created_on"        => $general->getDateTime(),
            "updated_datetime"  => $general->getDateTime()
        ));
    }

    /* Update back to the facility_state_id */
    $db->where("facility_id", $p['facility_id']);
    $db->update("facility_details", array(
        "facility_state_id" => $lastInsertId,
        "updated_datetime"  => $general->getDateTime()
    ));
}

/* Save County / District details to geolocation table */
$query = "SELECT DISTINCT facility_id, facility_state_id, facility_district, facility_district_id FROM facility_details WHERE facility_district not in (SELECT geo_name FROM geographical_divisions WHERE geo_parent != 0)";
$districtResult = $db->rawQuery($query);
foreach ($districtResult as $d) {
    $exist = $db->rawQueryOne("SELECT geo_id, geo_name FROM geographical_divisions WHERE (geo_name = '?' OR geo_id = ?)", $d['facility_district'], $d['facility_district_id']);
    if ($exist) {
        $db->where("geo_id", $exist['facility_district_id']);
        $db->update('geographical_divisions', array(
            "geo_name"          => $d['facility_district'],
            "geo_parent"        => $d['facility_state_id'],
            "created_on"        => $general->getDateTime(),
            "updated_datetime"  => $general->getDateTime()
        ));
        $lastInsertId = $exist['facility_district_id'];
    } else {
        $lastInsertId = $db->insert("geographical_divisions", array(
            "geo_name"          => $d['facility_district'],
            "geo_parent"        => $d['facility_state_id'],
            "created_on"        => $general->getDateTime(),
            "updated_datetime"  => $general->getDateTime()
        ));
    }

    /* Update back to the facility_district_id */
    $db->where("facility_id", $d['facility_id']);
    $db->update("facility_details", array(
        "facility_district_id" => $lastInsertId,
        "updated_datetime"  => $general->getDateTime()
    ));
}
