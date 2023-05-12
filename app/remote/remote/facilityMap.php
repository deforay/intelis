<?php
//this file in remote
require_once(dirname(__FILE__) . "/../../../bootstrap.php");

$facilityMapQuery = "SELECT facility_id FROM testing_lab_health_facilities_map";
$fMapResult = $db->query($facilityMapQuery);
if (!empty($fMapResult)) {
  $fMapResult = array_map('current', $fMapResult);
} else {
  $fMapResult = "";
}
echo json_encode($fMapResult);
