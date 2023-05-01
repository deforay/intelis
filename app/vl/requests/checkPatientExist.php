<?php


use App\Registries\ContainerRegistry;
use App\Services\CommonService;

/** @var CommonService $general */
$general = ContainerRegistry::get(CommonService::class);

$artNo = $_POST['artPatientNo'];

$count = 0;
$pQuery = "SELECT * FROM form_vl where (patient_art_no like '%" . $artNo . "%' OR patient_first_name like '%" . $artNo . "%' OR patient_middle_name like '%" . $artNo . "%' OR patient_last_name like '%" . $artNo . "%') ORDER BY sample_tested_datetime DESC";
$pResult = $db->rawQuery($pQuery);
$count = count($pResult);
echo $count;
