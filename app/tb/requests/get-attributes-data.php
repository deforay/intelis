<?php

use App\Models\Facilities;
use App\Models\General;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
  
$general = new General();
$facilitiesDb = new Facilities();
$results = [];
if (isset($_POST['id']) && $_POST['id'] > 0) {
    $db->where("f.facility_id", $_POST['id']);
    $db->join("testing_labs as l", "l.facility_id=f.facility_id", "INNER");
    $results = $db->getOne("facility_details as f", null, "*");
}
if (isset($results['attributes']) && $results['attributes'] != "") {
    echo $results['attributes'];
} else {
    echo false;
}
