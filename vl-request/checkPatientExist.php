<?php
include('../includes/MysqliDb.php');
include('../General.php');
$general=new Deforay_Commons_General();
$artNo=$_POST['artPatientNo'];
//global config
$cQuery="SELECT * FROM global_config";
$cResult=$db->query($cQuery);
$arr = array();
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($cResult); $i++) {
  $arr[$cResult[$i]['name']] = $cResult[$i]['value'];
}
$count = 0;
$pQuery="SELECT * FROM vl_request_form where vlsm_country_id='".$arr['vl_form']."' AND (patient_art_no like '%".$artNo."%' OR patient_first_name like '%".$artNo."%' OR patient_middle_name like '%".$artNo."%' OR patient_last_name like '%".$artNo."%')";
$pResult = $db->rawQuery($pQuery);
$count = count($pResult);
echo $count;
?>