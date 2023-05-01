<?php

use App\Services\FacilitiesService;
use App\Registries\ContainerRegistry;
use App\Services\CommonService;

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
  
/** @var MysqliDb $db */
$db = ContainerRegistry::get('db');

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

/** @var FacilitiesService $facilitiesService */
$facilitiesService = ContainerRegistry::get(FacilitiesService::class);
$results = [];
if (isset($_POST['id']) && $_POST['id'] > 0) {
    $db->where("f.facility_id", $_POST['id']);
    $db->join("testing_labs as l", "l.facility_id=f.facility_id", "INNER");
    $results = $db->getOne("facility_details as f");
}
if (isset($results['attributes']) && $results['attributes'] != "") {
    echo $results['attributes'];
} else {
    echo false;
}
