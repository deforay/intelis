<?php
session_unset(); // no need of session in json response
//header('Content-Type: application/json; charset=utf-8');
header('Content-Type: application/json');

if (!empty($_GET['labId']) && !empty($_GET['version'])) {
    $labId = (int) $_GET['labId'];
    $version = $_GET['version'];
    $sql = 'UPDATE facility_details SET facility_attributes = JSON_SET(facility_attributes, "$.version", ?) WHERE facility_id = ?';
    $db->rawQuery($sql, array($version, $labId));
}

// return VLSTS Version
$payload = array('version' => VERSION);
echo json_encode($payload);
