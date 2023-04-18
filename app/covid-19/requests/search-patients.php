<?php


use App\Models\General;

$general = new General();
$artNo = $_POST['artPatientNo'];

$count = 0;
$pQuery = "SELECT COUNT(*) AS total FROM form_covid19 where (patient_id like '%" . $artNo . "%' OR patient_name like '%" . $artNo . "%' OR patient_surname like '%" . $artNo . "%' OR patient_phone_number like '%" . $artNo . "%') ORDER BY sample_tested_datetime DESC, sample_collection_date DESC LIMIT 25";

$pResult = $db->rawQueryOne($pQuery);
$count = count($pResult['total']);
echo $count;
