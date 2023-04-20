<?php


use App\Models\General;

$general = new General();
$artNo = $_POST['artPatientNo'];
//global config
$cQuery = "SELECT * FROM global_config";
$cResult = $db->query($cQuery);
$arr = [];
// now we create an associative array so that we can easily create view variables
for ($i = 0; $i < sizeof($cResult); $i++) {
  $arr[$cResult[$i]['name']] = $cResult[$i]['value'];
}
$count = 0;
$pQuery = "SELECT COUNT(*) AS total FROM form_covid19 where (patient_id like '%" . $artNo . "%' OR patient_name like '%" . $artNo . "%' OR patient_surname like '%" . $artNo . "%' OR patient_phone_number like '%" . $artNo . "%')";

$pResult = $db->rawQueryOne($pQuery);
$count = count($pResult['total']);
echo $count;
