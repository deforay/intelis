<?php


use App\Services\CommonService;

$general=new CommonService();
$artNo=$_POST['artPatientNo'];
//global config
$cQuery="SELECT * FROM global_config";
$cResult=$db->query($cQuery);
$arr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($cResult); $i++) {
  $arr[$cResult[$i]['name']] = $cResult[$i]['value'];
}
$count = 0;
$pQuery="SELECT * FROM form_tb where vlsm_country_id='".$arr['vl_form']."' AND (patient_id like '%".$artNo."%' OR patient_name like '%".$artNo."%' OR patient_surname like '%".$artNo."%')";
$pResult = $db->rawQuery($pQuery);
$count = count($pResult);
echo $count;
