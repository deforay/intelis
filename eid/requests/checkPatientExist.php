<?php
  

$general=new \Vlsm\Models\General();
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
$pQuery="SELECT * FROM form_eid where vlsm_country_id='".$arr['vl_form']."' AND (child_id like '%".$artNo."%' OR child_name like '%".$artNo."%' OR child_surname like '%".$artNo."%' OR caretaker_phone_number like '%".$artNo."%')";
$pResult = $db->rawQuery($pQuery);
$count = count($pResult);
echo $count;
