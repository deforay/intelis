<?php
// Allow from any origin
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');    // cache for 1 day
}

// Access-Control headers are received during OPTIONS requests
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] == 'OPTIONS') {

if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
}         

if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
    header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
}

    exit(0);
}

 


//$facilityQuery ="SELECT facility_id,facility_name,facility_type from facility_details";
// Doing this temporarily because the other_id holds the recency facilities id
$facilityQuery ="SELECT other_id as 'facility_id',facility_name,facility_type FROM facility_details WHERE other_id IS NOT NULL AND other_id != ''";
$facilityResult=$db->query($facilityQuery);
echo json_encode($facilityResult,true);