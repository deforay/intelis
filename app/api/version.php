<?php

use App\Models\General;
use App\Utilities\DateUtils;

$general = new General();

session_unset(); // no need of session in json response
//header('Content-Type: application/json; charset=utf-8');
header('Content-Type: application/json');



if (!empty($_GET['labId']) && !empty($_GET['version'])) {
    $labId = (int) $_GET['labId'];
    $version = $_GET['version'];
    $sql = 'UPDATE facility_details SET facility_attributes = JSON_SET(COALESCE(facility_attributes, "{}"), "$.version", ?, "$.lastHeartBeat", ?) WHERE facility_id = ?';
    $db->rawQuery($sql, array($version, DateUtils::getCurrentDateTime(), $labId));
}

// return VLSTS Version
$payload = array('version' => VERSION);
echo json_encode($payload);
