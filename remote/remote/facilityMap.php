<?php
//this file in remote
require_once(dirname(__FILE__) . "/../../startup.php");  

$facilityMapQuery = "SELECT facility_id FROM vl_facility_map";
$fMapResult=$db->query($facilityMapQuery);
if(count($fMapResult)>0){
  $fMapResult = array_map('current', $fMapResult);
}else{
    $fMapResult = "";
}
echo json_encode($fMapResult);
?>